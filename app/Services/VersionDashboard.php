<?php

namespace App\Services;

use App\Models\TelemetryEvent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Tableau de bord des versions : version « réelle » de chaque composant face à la dernière release GitHub.
 *
 * Versions réelles (aucune n'est devinée : sans source fiable, le composant est « inconnu ») :
 *  - modpack  : nom des jars publiés dans le modpack du launcher (ce que les joueurs reçoivent) ;
 *  - telemetry: version de launcher la plus répandue ces 14 derniers jours (télémétrie, indicative) ;
 *  - panel    : config('app.version') ;
 *  - reported : fichier JSON config('geoventure.versions.reported_file') (plugins du serveur de jeu, du site).
 *
 * Dernière release : API GitHub côté serveur, cache, délai court, jeton optionnel (GEO_GITHUB_TOKEN).
 * Aucun détail interne (corps de réponse, exception) n'est conservé : seulement un code de raison.
 */
class VersionDashboard
{
    public const UP_TO_DATE = 'uptodate';
    public const BEHIND = 'behind';
    public const UNKNOWN = 'unknown';

    private const ORG = 'Geoventure-MC';

    /** key = clé du fichier de versions relevées ; jar = préfixe du jar dans le modpack ; prefixes = préfixes de tags. */
    public const COMPONENTS = [
        ['key' => 'geomods', 'label' => 'GeoMods', 'repo' => self::ORG . '/geoMods', 'prefixes' => ['v'], 'source' => 'modpack', 'jar' => 'geomods'],
        ['key' => 'geofactions', 'label' => 'GeoFactions', 'repo' => self::ORG . '/Pluginmc', 'prefixes' => ['v'], 'source' => 'reported'],
        ['key' => 'geocuisine', 'label' => 'GeoCuisine', 'repo' => self::ORG . '/GeoCuisine', 'prefixes' => ['v'], 'source' => 'modpack', 'jar' => 'geocuisine'],
        ['key' => 'geodecor', 'label' => 'GeoDecor', 'repo' => self::ORG . '/GeoDecor', 'prefixes' => ['v'], 'source' => 'modpack', 'jar' => 'geodecor'],
        ['key' => 'geowarfare', 'label' => 'GeoWarfare', 'repo' => self::ORG . '/GeoWarfare', 'prefixes' => ['v'], 'source' => 'modpack', 'jar' => 'geowarfare'],
        ['key' => 'geovigie', 'label' => 'GeoVigie', 'repo' => self::ORG . '/GeoVigie', 'prefixes' => ['v'], 'source' => 'reported'],
        ['key' => 'geopasse', 'label' => 'GeoPasse', 'repo' => self::ORG . '/GeoPasse', 'prefixes' => ['v'], 'source' => 'reported'],
        ['key' => 'launcher', 'label' => 'Launcher', 'repo' => self::ORG . '/Launcher-v3', 'prefixes' => ['v'], 'source' => 'telemetry'],
        ['key' => 'site-geocountries', 'label' => 'Site : geo-countries', 'repo' => self::ORG . '/Plugin-azuriom-geocountries', 'prefixes' => ['geo-v', 'v'], 'source' => 'reported'],
        ['key' => 'site-forum', 'label' => 'Site : forum', 'repo' => self::ORG . '/Plugin-azuriom-forum', 'prefixes' => ['forum-v', 'v'], 'source' => 'reported'],
        ['key' => 'site-guide', 'label' => 'Site : guide', 'repo' => self::ORG . '/Plugin-azuriom-guide', 'prefixes' => ['guide-v', 'v'], 'source' => 'reported'],
        ['key' => 'site-geodrop', 'label' => 'Site : geodrop', 'repo' => self::ORG . '/Plugin-azuriom-geodrop', 'prefixes' => ['geodrop-v', 'v'], 'source' => 'reported'],
        ['key' => 'site-tickets', 'label' => 'Site : tickets', 'repo' => self::ORG . '/Plugin-azuriom-tickets', 'prefixes' => ['v'], 'source' => 'reported'],
        ['key' => 'panel', 'label' => 'Panel', 'repo' => self::ORG . '/panel', 'prefixes' => ['v'], 'source' => 'panel'],
    ];

    // ---------------------------------------------------------------- versions (fonctions pures)

    /** Version propre (sans « v » initial) ou null si le texte n'a pas la forme d'une version. */
    public static function clean(?string $v): ?string
    {
        if ($v === null) {
            return null;
        }
        if (preg_match('/^v?(\d{1,9}(?:\.\d{1,9}){0,3}(?:[-+][0-9A-Za-z.+_-]{0,30})?)$/', trim($v), $m) !== 1) {
            return null;
        }

        return $m[1];
    }

    /** @return int[]|null Segments numériques de tête (« 1.2.3-beta » donne [1, 2, 3]). */
    public static function parts(?string $v): ?array
    {
        if ($v === null || preg_match('/(\d{1,9}(?:\.\d{1,9})*)/', $v, $m) !== 1) {
            return null;
        }

        return array_map('intval', explode('.', $m[1]));
    }

    /** -1, 0 ou 1 ; null si l'une des deux n'est pas une version. « 1.2 » égale « 1.2.0 ». */
    public static function compare(?string $a, ?string $b): ?int
    {
        $pa = self::parts($a);
        $pb = self::parts($b);
        if ($pa === null || $pb === null) {
            return null;
        }
        $n = max(count($pa), count($pb));
        for ($i = 0; $i < $n; $i++) {
            $d = ($pa[$i] ?? 0) <=> ($pb[$i] ?? 0);
            if ($d !== 0) {
                return $d;
            }
        }

        return 0;
    }

    /** « à jour » si la version réelle est au moins la dernière release (un build en avance reste à jour). */
    public static function status(?string $running, ?string $latest): string
    {
        $cmp = self::compare($running, $latest);
        if ($cmp === null) {
            return self::UNKNOWN;
        }

        return $cmp >= 0 ? self::UP_TO_DATE : self::BEHIND;
    }

    /** Version portée par un tag selon les préfixes acceptés (« geo-v1.2.3 » avec « geo-v » donne 1.2.3). */
    public static function versionFromTag(string $tag, array $prefixes): ?string
    {
        foreach ($prefixes as $p) {
            if (preg_match('/^' . preg_quote($p, '/') . '(\d{1,9}(?:\.\d{1,9}){0,3})(?:[-+].*)?$/', $tag, $m) === 1) {
                return $m[1];
            }
        }

        return null;
    }

    /**
     * Plus haute release publiée (brouillons et préversions ignorés) dont le tag porte un préfixe accepté.
     *
     * @return array{tag: string, version: string, url: string, published_at: ?string}|null
     */
    public static function latestFromReleases(array $releases, array $prefixes, string $repo): ?array
    {
        $best = null;
        foreach ($releases as $r) {
            if (! is_array($r) || ! empty($r['draft']) || ! empty($r['prerelease']) || ! is_string($r['tag_name'] ?? null)) {
                continue;
            }
            $version = self::versionFromTag($r['tag_name'], $prefixes);
            if ($version === null || ($best !== null && self::compare($version, $best['version']) <= 0)) {
                continue;
            }
            $url = $r['html_url'] ?? null;
            if (! is_string($url) || ! str_starts_with($url, 'https://github.com/')) {
                $url = 'https://github.com/' . $repo . '/releases/tag/' . rawurlencode($r['tag_name']);
            }
            $date = is_string($r['published_at'] ?? null) && preg_match('/^\d{4}-\d{2}-\d{2}/', $r['published_at']) === 1
                ? substr($r['published_at'], 0, 10) : null;
            $best = ['tag' => $r['tag_name'], 'version' => $version, 'url' => $url, 'published_at' => $date];
        }

        return $best;
    }

    // ---------------------------------------------------------------- GitHub (cache, délai court, jeton optionnel)

    public static function hasToken(): bool
    {
        return self::token() !== null;
    }

    private static function token(): ?string
    {
        $t = config('geoventure.versions.github_token');

        return is_string($t) && trim($t) !== '' ? trim($t) : null;
    }

    private static function cacheKey(array $c): string
    {
        return 'geo.versions.latest.' . md5($c['repo'] . '|' . implode(',', $c['prefixes']));
    }

    /**
     * @return array{ok: bool, release: ?array, error: ?string, stale?: bool}
     *   error : no_access | rate_limited | unreachable | error | no_release
     */
    public function fetchLatest(array $c): array
    {
        $timeout = max(1, (int) config('geoventure.versions.timeout', 4));
        try {
            $req = Http::withHeaders([
                'User-Agent' => 'Geoventure-Panel-Versions',
                'Accept' => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => '2022-11-28',
            ])->timeout($timeout)->connectTimeout(min(3, $timeout));
            if (($token = self::token()) !== null) {
                $req = $req->withToken($token);
            }
            $res = $req->get('https://api.github.com/repos/' . $c['repo'] . '/releases', ['per_page' => 30]);
        } catch (\Throwable $e) {
            return ['ok' => false, 'release' => null, 'error' => 'unreachable'];
        }

        $status = $res->status();
        if ($status === 404 || $status === 401) {
            return ['ok' => false, 'release' => null, 'error' => 'no_access'];
        }
        if ($status === 429 || ($status === 403 && $res->header('X-RateLimit-Remaining') === '0')) {
            return ['ok' => false, 'release' => null, 'error' => 'rate_limited'];
        }
        if ($status === 403) {
            return ['ok' => false, 'release' => null, 'error' => 'no_access'];
        }
        $json = $res->successful() ? $res->json() : null;
        if (! is_array($json)) {
            return ['ok' => false, 'release' => null, 'error' => 'error'];
        }

        $release = self::latestFromReleases($json, $c['prefixes'], $c['repo']);

        return ['ok' => true, 'release' => $release, 'error' => $release === null ? 'no_release' : null];
    }

    /**
     * Dernière release d'un composant. Succès gardé `cache_ttl` s (10 min) ; échec gardé 2 min seulement
     * (on ne martèle pas GitHub) ; en cas d'échec, la dernière valeur connue (7 jours) est resservie, marquée `stale`.
     */
    public function latest(array $c): array
    {
        $key = self::cacheKey($c);
        $hit = Cache::get($key);
        if (is_array($hit)) {
            return $hit;
        }

        $res = $this->fetchLatest($c);
        if ($res['ok']) {
            Cache::put($key, $res, max(1, (int) config('geoventure.versions.cache_ttl', 600)));
            if ($res['release'] !== null) {
                Cache::put($key . ':last', $res, 7 * 86400);
            }

            return $res;
        }

        $last = Cache::get($key . ':last');
        if (is_array($last) && is_array($last['release'] ?? null)) {
            $res = ['ok' => false, 'release' => $last['release'], 'error' => $res['error'], 'stale' => true];
        }
        Cache::put($key, $res, 120);

        return $res;
    }

    /** Vide le cache court (le dernier état connu de secours est conservé). */
    public function forgetCache(): void
    {
        foreach (self::COMPONENTS as $c) {
            Cache::forget(self::cacheKey($c));
        }
    }

    // ---------------------------------------------------------------- versions réelles

    /** Clés acceptées dans le fichier de versions relevées (composants dont aucune autre source ne connaît la version). */
    public static function reportedKeys(): array
    {
        return array_values(array_map(fn ($c) => $c['key'], array_filter(self::COMPONENTS, fn ($c) => $c['source'] === 'reported')));
    }

    /**
     * Ajoute ou met à jour des versions relevées : seules les clés de {@see reportedKeys()} et les valeurs de forme
     * « 1.2.3 » sont gardées ; le reste du fichier est conservé. Écriture atomique (fichier temporaire puis renommage).
     *
     * @param  array<string, string>  $versions
     * @return array{written: array<string, string>, refused: list<string>}
     */
    public static function writeReported(array $versions): array
    {
        $path = config('geoventure.versions.reported_file');
        if (! is_string($path) || $path === '') {
            throw new \RuntimeException('Fichier de versions relevées non configuré (GEO_VERSIONS_FILE).');
        }
        $current = [];
        if (is_file($path) && (@filesize($path) ?: 0) <= 65536) {
            $json = json_decode((string) @file_get_contents($path), true);
            if (is_array($json)) {
                foreach ($json as $k => $v) {
                    if (is_string($k) && is_string($v) && self::clean($v) !== null) {
                        $current[strtolower($k)] = self::clean($v);
                    }
                }
            }
        }
        $allowed = self::reportedKeys();
        $written = [];
        $refused = [];
        foreach ($versions as $k => $v) {
            $key = is_string($k) ? strtolower(trim($k)) : '';
            $clean = is_string($v) ? self::clean($v) : null;
            if (! in_array($key, $allowed, true) || $clean === null) {
                $refused[] = (string) $k;
                continue;
            }
            $current[$key] = $clean;
            $written[$key] = $clean;
        }
        if ($written !== []) {
            ksort($current);
            $dir = dirname($path);
            if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
                throw new \RuntimeException('Dossier introuvable pour le fichier de versions.');
            }
            $tmp = $path . '.tmp';
            if (@file_put_contents($tmp, json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n") === false || ! @rename($tmp, $path)) {
                @unlink($tmp);
                throw new \RuntimeException('Écriture du fichier de versions impossible.');
            }
        }

        return ['written' => $written, 'refused' => $refused];
    }

    /** @return array<string, array{version: string, at: ?string}> */
    public function reported(): array
    {
        $path = config('geoventure.versions.reported_file');
        if (! is_string($path) || $path === '' || ! is_file($path) || ! is_readable($path) || (@filesize($path) ?: 0) > 65536) {
            return [];
        }
        $json = json_decode((string) @file_get_contents($path), true);
        if (! is_array($json)) {
            return [];
        }
        $mtime = @filemtime($path);
        $out = [];
        foreach ($json as $k => $v) {
            $clean = is_string($k) && is_string($v) ? self::clean($v) : null;
            if ($clean !== null) {
                $out[strtolower($k)] = ['version' => $clean, 'at' => $mtime ? date('c', $mtime) : null];
            }
        }

        return $out;
    }

    /** Version du mod d'après le nom du jar (« geomods-3.42.20.jar », « geomods-1.20.1-3.42.20.jar »). */
    public static function versionFromJar(string $file, string $prefix): ?string
    {
        if (! str_ends_with(strtolower($file), '.jar')) {
            return null;
        }
        $re = '/^' . preg_quote($prefix, '/') . '[-_]v?(?:(?:mc)?1\.20\.1[-_+])?(\d{1,9}(?:\.\d{1,9}){1,3})/i';

        return preg_match($re, $file, $m) === 1 ? $m[1] : null;
    }

    /** @return array<string, string> préfixe de jar => plus haute version publiée dans le modpack. */
    private function modpackVersions(): array
    {
        try {
            $files = ModpackPublisher::listing(null, 'mods');
        } catch (\Throwable $e) {
            return [];
        }
        $out = [];
        foreach (self::COMPONENTS as $c) {
            if (empty($c['jar'])) {
                continue;
            }
            foreach ($files as $f) {
                $v = self::versionFromJar((string) ($f['file'] ?? ''), $c['jar']);
                if ($v !== null && (! isset($out[$c['jar']]) || self::compare($v, $out[$c['jar']]) > 0)) {
                    $out[$c['jar']] = $v;
                }
            }
        }

        return $out;
    }

    /** @return array{version: string, users: int}|null Version de launcher dominante (joueurs distincts) sur 14 jours. */
    private function launcherVersion(): ?array
    {
        try {
            $rows = TelemetryEvent::query()
                ->where('created_at', '>=', now()->subDays(14))
                ->whereNotNull('launcher_version')
                ->where('launcher_version', '<>', '')
                ->selectRaw('launcher_version as v, count(distinct ip_hash) as u, count(*) as n')
                ->groupBy('launcher_version')
                ->orderByDesc('u')
                ->orderByDesc('n')
                ->limit(20)
                ->get();
        } catch (\Throwable $e) {
            return null;
        }
        foreach ($rows as $row) {
            $v = self::clean((string) $row->v);
            if ($v !== null) {
                return ['version' => $v, 'users' => (int) $row->u];
            }
        }

        return null;
    }

    /**
     * @return array{version: ?string, source: string, detail: ?string}
     *   detail : nombre de joueurs (telemetry), date du relevé (reported) ou null.
     */
    private function running(array $c, array $modpack, ?array $launcher, array $reported): array
    {
        $none = ['version' => null, 'source' => $c['source'], 'detail' => null];
        switch ($c['source']) {
            case 'panel':
                $v = self::clean((string) config('app.version', ''));

                return $v === null ? $none : ['version' => $v, 'source' => 'panel', 'detail' => null];
            case 'telemetry':
                return $launcher === null ? $none : ['version' => $launcher['version'], 'source' => 'telemetry', 'detail' => (string) $launcher['users']];
            case 'modpack':
                if (isset($modpack[$c['jar']])) {
                    return ['version' => $modpack[$c['jar']], 'source' => 'modpack', 'detail' => null];
                }
                // repli : relevé manuel (le jar peut être absent du modpack de l'instance par défaut)
                if (isset($reported[$c['key']])) {
                    return ['version' => $reported[$c['key']]['version'], 'source' => 'reported', 'detail' => $reported[$c['key']]['at']];
                }

                return $none;
            default:
                return isset($reported[$c['key']])
                    ? ['version' => $reported[$c['key']]['version'], 'source' => 'reported', 'detail' => $reported[$c['key']]['at']]
                    : $none;
        }
    }

    /**
     * @return list<array{key: string, label: string, repo: string, running: ?string, source: string, detail: ?string,
     *   latest: ?string, tag: ?string, date: ?string, url: string, status: string, reason: ?string, stale: bool}>
     */
    public function rows(): array
    {
        $modpack = $this->modpackVersions();
        $launcher = $this->launcherVersion();
        $reported = $this->reported();

        $rows = [];
        foreach (self::COMPONENTS as $c) {
            $run = $this->running($c, $modpack, $launcher, $reported);
            $lat = $this->latest($c);
            $rel = $lat['release'] ?? null;
            $status = self::status($run['version'], $rel['version'] ?? null);
            $reason = null;
            if ($status === self::UNKNOWN) {
                $reason = $rel === null ? ($lat['error'] ?? 'error') : 'no_source';
            }
            $rows[] = [
                'key' => $c['key'],
                'label' => $c['label'],
                'repo' => $c['repo'],
                'running' => $run['version'],
                'source' => $run['source'],
                'detail' => $run['detail'],
                'latest' => $rel['version'] ?? null,
                'tag' => $rel['tag'] ?? null,
                'date' => $rel['published_at'] ?? null,
                'url' => $rel['url'] ?? 'https://github.com/' . $c['repo'] . '/releases',
                'status' => $status,
                'reason' => $reason,
                'stale' => ! empty($lat['stale']),
            ];
        }

        return $rows;
    }

    /** @return array{uptodate: int, behind: int, unknown: int} */
    public static function summary(array $rows): array
    {
        $s = [self::UP_TO_DATE => 0, self::BEHIND => 0, self::UNKNOWN => 0];
        foreach ($rows as $r) {
            $s[$r['status']]++;
        }

        return $s;
    }
}
