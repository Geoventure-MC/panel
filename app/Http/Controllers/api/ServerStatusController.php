<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\OptionsServer;
use Illuminate\Support\Facades\Cache;

class ServerStatusController extends Controller
{
    public function getServersStatus()
    {
        $servers = OptionsServer::all();
        $statuses = [];

        foreach ($servers as $server) {
            $ping = $this->pingServer($server->server_ip, (int) $server->server_port);

            $statuses[] = [
                'id'          => $server->server_id,
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
        }

        return response()->json($statuses, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Interroge un serveur Minecraft via le Server List Ping (SLP) moderne
     * (handshake + status request) pour récupérer online/joueurs/version/latence.
     * Résultat mis en cache 30s pour éviter de pinger à chaque appel du launcher.
     *
     * @return array{online: bool, players: ?int, max_players: ?int, version: ?string, latency: ?int}
     */
    private function pingServer(string $ip, int $port): array
    {
        return Cache::remember("server_status_{$ip}_{$port}", 30, function () use ($ip, $port) {
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

                return [
                    'online'      => true,
                    'players'     => isset($data['players']['online']) ? (int) $data['players']['online'] : null,
                    'max_players' => isset($data['players']['max']) ? (int) $data['players']['max'] : null,
                    'version'     => $data['version']['name'] ?? null,
                    'latency'     => $latency,
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
