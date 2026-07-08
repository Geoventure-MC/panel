<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\OptionsServer;
use App\Models\ServerPlayerSample;
use Illuminate\Support\Facades\Cache;

class ServerStatusController extends Controller
{
    public function getServersStatus()
    {
        return $this->buildStatuses(false);
    }

    /**
     * Variante non bloquante : ne lit QUE le cache de ping existant. Si aucun
     * statut n'est en cache pour un serveur, renvoie un statut « inconnu »
     * (offline, joueurs null) SANS déclencher de ping réseau synchrone.
     *
     * Utilisé par le tableau de bord admin qui poll toutes les 20s : on évite
     * de mobiliser des workers PHP-FPM sur des fsockopen/stream timeouts (~2-4s)
     * quand un serveur de jeu est hors ligne. Le cache est alimenté par les
     * appels normaux à /utils/servers-status (launcher).
     */
    public function getServersStatusCached()
    {
        return $this->buildStatuses(true);
    }

    /**
     * @param bool $cachedOnly Si true, n'effectue aucun ping réseau : lit
     *                         uniquement le cache (statut inconnu sinon).
     */
    private function buildStatuses(bool $cachedOnly)
    {
        $servers = OptionsServer::all();
        $statuses = [];

        foreach ($servers as $server) {
            $serverKey = $server->instance_slug ?: $server->server_id;
            $ping = $cachedOnly
                ? $this->cachedPing($server->server_ip, (int) $server->server_port)
                : $this->pingServer($server->server_ip, (int) $server->server_port, (string) $serverKey);

            $status = [
                'id'          => $server->instance_slug ?: $server->server_id,
                'server_id'   => $server->server_id,
                'name'        => $server->server_name,
                'ip'          => $server->server_ip,
                'port'        => (int) $server->server_port,
                'online'      => $ping['online'],
                'players'     => $ping['players'],
                'max_players' => $ping['max_players'],
                'version'     => $ping['version'],
                'latency'     => $ping['latency'],
                'is_default'  => (bool) $server->is_default,
            ];

            // Échantillon de pseudos en ligne (players.sample du SLP) : champ
            // volontairement OMIS quand le serveur n'en fournit pas (fail-safe,
            // rétrocompatible avec les anciens launchers).
            if (!empty($ping['players_sample']) && is_array($ping['players_sample'])) {
                $status['players_sample'] = $ping['players_sample'];
            }

            $statuses[] = $status;
        }

        return response()->json($statuses, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Lit uniquement le cache de ping (sans aucun appel réseau). Retourne un
     * statut « inconnu » (offline, valeurs null) si rien n'est en cache.
     *
     * @return array{online: bool, players: ?int, max_players: ?int, version: ?string, latency: ?int}
     */
    private function cachedPing(string $ip, int $port): array
    {
        return Cache::get("server_status_{$ip}_{$port}", [
            'online'      => false,
            'players'     => null,
            'max_players' => null,
            'version'     => null,
            'latency'     => null,
        ]);
    }

    /**
     * Interroge un serveur Minecraft via le Server List Ping (SLP) moderne
     * (handshake + status request) pour récupérer online/joueurs/version/latence.
     * Résultat mis en cache 30s pour éviter de pinger à chaque appel du launcher.
     *
     * Quand `$serverKey` est fourni, chaque ping frais (cache miss) alimente
     * l'historique d'affluence (`server_player_samples`) — voir recordSample().
     *
     * @return array{online: bool, players: ?int, max_players: ?int, version: ?string, latency: ?int}
     */
    private function pingServer(string $ip, int $port, ?string $serverKey = null): array
    {
        return Cache::remember("server_status_{$ip}_{$port}", 30, function () use ($ip, $port, $serverKey) {
            $empty = [
                'online'      => false,
                'players'     => null,
                'max_players' => null,
                'version'     => null,
                'latency'     => null,
            ];

            $start = microtime(true);
            $socket = @fsockopen($ip, $port, $errno, $errstr, 2);
            if (!$socket) {
                return $empty;
            }

            stream_set_timeout($socket, 2);

            try {
                // --- Handshake (state = 1 : status) ---
                $handshake = $this->writeVarInt(0x00)            // packet id
                    . $this->writeVarInt(-1)                     // protocol version (-1 = non spécifié)
                    . $this->writeString($ip)                    // adresse serveur
                    . pack('n', $port)                           // port (unsigned short, big-endian)
                    . $this->writeVarInt(0x01);                  // next state : status
                fwrite($socket, $this->writeVarInt(strlen($handshake)) . $handshake);

                // --- Status request (paquet vide id 0x00) ---
                fwrite($socket, $this->writeVarInt(1) . $this->writeVarInt(0x00));

                // --- Réponse ---
                $this->readVarInt($socket);          // longueur totale du paquet (ignorée)
                $packetId = $this->readVarInt($socket);
                if ($packetId !== 0x00) {
                    fclose($socket);
                    return array_merge($empty, ['online' => true]);
                }

                $jsonLength = $this->readVarInt($socket);
                $json = '';
                while (strlen($json) < $jsonLength) {
                    $chunk = fread($socket, $jsonLength - strlen($json));
                    if ($chunk === false || $chunk === '') {
                        break;
                    }
                    $json .= $chunk;
                }
                fclose($socket);

                $latency = (int) round((microtime(true) - $start) * 1000);
                $data = json_decode($json, true);

                if (!is_array($data)) {
                    // Port ouvert mais réponse illisible : on sait juste que c'est en ligne.
                    return array_merge($empty, ['online' => true, 'latency' => $latency]);
                }

                $players = isset($data['players']['online']) ? (int) $data['players']['online'] : null;

                // Affluence : ce ping est frais (cache miss), on en profite
                // pour échantillonner le nombre de joueurs (jamais bloquant).
                if ($serverKey !== null && $serverKey !== '' && $players !== null) {
                    $this->recordSample($serverKey, $players);
                }

                return [
                    'online'         => true,
                    'players'        => $players,
                    'max_players'    => isset($data['players']['max']) ? (int) $data['players']['max'] : null,
                    'version'        => $data['version']['name'] ?? null,
                    'latency'        => $latency,
                    'players_sample' => $this->extractPlayersSample($data),
                ];
            } catch (\Throwable $e) {
                if (is_resource($socket)) {
                    fclose($socket);
                }
                // Le socket s'est ouvert : le serveur écoute, mais le SLP a échoué.
                return array_merge($empty, ['online' => true]);
            }
        });
    }

    /**
     * Extrait l'échantillon de pseudos en ligne du JSON SLP (`players.sample`,
     * tableau d'objets { name, id }). Totalement tolérant : champ absent ou
     * malformé → tableau vide (le champ sera alors omis de la réponse).
     * Limité à 12 pseudos, dédoublonné, pseudos non-string ignorés.
     *
     * @return string[]
     */
    private function extractPlayersSample(array $data): array
    {
        $sample = $data['players']['sample'] ?? null;
        if (!is_array($sample)) {
            return [];
        }

        $names = [];
        foreach ($sample as $entry) {
            $name = is_array($entry) ? ($entry['name'] ?? null) : null;
            if (!is_string($name)) {
                continue;
            }
            // Nettoie les codes couleur legacy (§x) éventuels des faux pseudos
            // de MOTD et ignore les entrées vides.
            $name = trim(preg_replace('/\x{00A7}./u', '', $name) ?? '');
            if ($name === '' || in_array($name, $names, true)) {
                continue;
            }
            $names[] = mb_substr($name, 0, 32);
            if (count($names) >= 12) {
                break;
            }
        }

        return $names;
    }

    /**
     * Enregistre un échantillon d'affluence pour un serveur, au plus une fois
     * toutes les 5 minutes (verrou cache), et purge les échantillons de plus
     * de 7 jours environ 1 fois sur 50. Entièrement fail-safe : ne doit JAMAIS
     * faire échouer le ping (table absente avant migration, DB down…).
     */
    private function recordSample(string $serverKey, int $players): void
    {
        try {
            // Cache::add ne retourne true que si la clé n'existait pas encore :
            // il sert de verrou « au plus un échantillon par 5 min » sans requête SQL.
            if (!Cache::add('player_sample_gate_' . md5($serverKey), 1, 300)) {
                return;
            }

            ServerPlayerSample::create([
                'server_key' => mb_substr($serverKey, 0, 255),
                'players'    => $players,
                'sampled_at' => now(),
            ]);

            // Purge occasionnelle des échantillons > 7 jours (pas de cron requis).
            if (random_int(1, 50) === 1) {
                ServerPlayerSample::where('sampled_at', '<', now()->subDays(7))->delete();
            }
        } catch (\Throwable $e) {
            // Volontairement silencieux : l'affluence est un bonus, pas une
            // fonctionnalité critique du statut serveur.
        }
    }

    /**
     * Encode un entier en VarInt (format protocole Minecraft).
     */
    private function writeVarInt(int $value): string
    {
        $out = '';
        $value &= 0xFFFFFFFF; // gère les valeurs négatives (ex: protocol -1)
        do {
            $temp = $value & 0x7F;
            $value = ($value >> 7) & (PHP_INT_MAX >> 6);
            if ($value !== 0) {
                $temp |= 0x80;
            }
            $out .= chr($temp);
        } while ($value !== 0);

        return $out;
    }

    /**
     * Lit un VarInt depuis le socket.
     */
    private function readVarInt($socket): int
    {
        $value = 0;
        $position = 0;
        while (true) {
            $byte = fread($socket, 1);
            if ($byte === false || $byte === '') {
                throw new \RuntimeException('VarInt read failed');
            }
            $byte = ord($byte);
            $value |= ($byte & 0x7F) << $position;
            if (($byte & 0x80) === 0) {
                break;
            }
            $position += 7;
            if ($position >= 32) {
                throw new \RuntimeException('VarInt too big');
            }
        }

        return $value;
    }

    /**
     * Encode une chaîne UTF-8 préfixée de sa longueur en VarInt.
     */
    private function writeString(string $str): string
    {
        return $this->writeVarInt(strlen($str)) . $str;
    }
}
