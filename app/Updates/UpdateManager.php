<?php

namespace App\Updates;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use ZipArchive;
use RuntimeException;

class UpdateManager
{
    protected Filesystem $files;
    protected string $apiUrl = 'https://api.github.com/repos/Geoventure-MC/panel/releases/latest';
    protected string $currentVersion;

    public function __construct(Filesystem $files, string $currentVersion)
    {
        $this->files = $files;
        $this->currentVersion = $currentVersion;
    }

    public function fetchUpdateInfo(): ?array
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Geoventure-Panel-UpdateManager',
                'Accept'     => 'application/vnd.github.v3+json',
            ])->timeout(15)->get($this->apiUrl);

            if (!$response->successful()) {
                Log::error('UpdateManager: GitHub API returned ' . $response->status());
                return null;
            }

            $data = $response->json();
            $version = isset($data['tag_name']) ? ltrim($data['tag_name'], 'v') : null;

            $zipUrl  = null;
            $fileName = null;
            $assetSize = null;

            foreach ($data['assets'] ?? [] as $asset) {
                if (str_ends_with($asset['name'], '.zip')) {
                    $zipUrl    = $asset['browser_download_url'];
                    $fileName  = $asset['name'];
                    $assetSize = $asset['size'] ?? null;
                    break;
                }
            }

            if (!$version) {
                Log::error('UpdateManager: no tag_name in GitHub response');
                return null;
            }

            if (!$zipUrl || !$fileName) {
                Log::error('UpdateManager: no .zip asset found in release ' . $version);
                return null;
            }

            Log::info("UpdateManager: latest release is v{$version}, asset: {$fileName}");

            return [
                'version' => $version,
                'url'     => $zipUrl,
                'file'    => $fileName,
                'hash'    => null,
                'size'    => $assetSize,
            ];
        } catch (\Exception $e) {
            Log::error('UpdateManager fetch error: ' . $e->getMessage());
            return null;
        }
    }

    public function hasUpdate(?array $info = null): bool
    {
        $info = $info ?: $this->fetchUpdateInfo();
        if (!$info || empty($info['version'])) {
            return false;
        }
        return version_compare($info['version'], $this->currentVersion, '>');
    }

    public function downloadUpdate(array $info): string
    {
        $updatesPath = storage_path('app/updates/');
        if (!$this->files->isDirectory($updatesPath)) {
            $this->files->makeDirectory($updatesPath, 0755, true);
        }

        $filePath = $updatesPath . $info['file'];

        if ($this->files->exists($filePath)) {
            $this->files->delete($filePath);
        }

        Log::info('UpdateManager: downloading from ' . $info['url']);

        // Utilise cURL directement pour suivre les redirections de GitHub → CDN
        // et écrire en streaming sans charger le ZIP en mémoire.
        $fp = fopen($filePath, 'wb');
        if (!$fp) {
            throw new RuntimeException("Impossible d'ouvrir le fichier de destination : {$filePath}");
        }

        $ch = curl_init($info['url']);
        curl_setopt_array($ch, [
            CURLOPT_FILE           => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => 180,
            CURLOPT_USERAGENT      => 'Geoventure-Panel-UpdateManager',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => ['Accept: application/octet-stream'],
        ]);

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        fclose($fp);

        if ($curlError) {
            $this->files->delete($filePath);
            throw new RuntimeException("Erreur cURL lors du téléchargement : {$curlError}");
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            $this->files->delete($filePath);
            throw new RuntimeException("Téléchargement échoué (HTTP {$httpCode})");
        }

        $size = filesize($filePath);
        Log::info("UpdateManager: downloaded {$info['file']} ({$size} bytes)");

        if ($size < 1024) {
            $this->files->delete($filePath);
            throw new RuntimeException("Fichier téléchargé trop petit ({$size} octets) — probablement vide ou erreur réseau.");
        }

        // Vérifie la taille annoncée par GitHub si disponible (intégrité basique).
        if (!empty($info['size']) && (int) $info['size'] !== (int) $size) {
            $this->files->delete($filePath);
            throw new RuntimeException(
                "Taille du fichier téléchargée ({$size}) différente de celle annoncée ({$info['size']}) — téléchargement corrompu."
            );
        }

        // Vérifie le SHA256 si fourni dans les métadonnées de la release.
        if (!empty($info['hash'])) {
            $actual = hash_file('sha256', $filePath);
            if (!hash_equals(strtolower($info['hash']), strtolower($actual))) {
                $this->files->delete($filePath);
                throw new RuntimeException("Empreinte SHA256 invalide — téléchargement rejeté.");
            }
        }

        return $filePath;
    }

    public function installUpdate(string $zipPath): void
    {
        $basePath = base_path();

        if (!is_writable($basePath)) {
            throw new RuntimeException("Le dossier racine n'est pas accessible en écriture : {$basePath}");
        }

        // Vérifie que le fichier est bien un ZIP (magic bytes "PK\x03\x04" /
        // "PK\x05\x06" pour un zip vide) avant de tenter l'extraction.
        $fh = @fopen($zipPath, 'rb');
        if ($fh === false) {
            throw new RuntimeException("Impossible de lire le fichier téléchargé : {$zipPath}");
        }
        $magic = fread($fh, 4);
        fclose($fh);
        if ($magic !== "PK\x03\x04" && $magic !== "PK\x05\x06") {
            $this->files->delete($zipPath);
            throw new RuntimeException("Le fichier téléchargé n'est pas une archive ZIP valide.");
        }

        $zip = new ZipArchive();
        $result = $zip->open($zipPath);

        if ($result !== true) {
            $codes = [
                ZipArchive::ER_NOZIP  => 'pas un fichier ZIP',
                ZipArchive::ER_INCONS => 'archive incohérente',
                ZipArchive::ER_INVAL  => 'argument invalide',
                ZipArchive::ER_MEMORY => 'mémoire insuffisante',
                ZipArchive::ER_NOENT  => 'fichier introuvable',
                ZipArchive::ER_OPEN   => "impossible d'ouvrir",
                ZipArchive::ER_READ   => 'erreur de lecture',
                ZipArchive::ER_SEEK   => 'erreur de positionnement',
            ];
            $msg = $codes[$result] ?? "code d'erreur {$result}";
            throw new RuntimeException("Impossible d'ouvrir le ZIP : {$msg}");
        }

        $count = $zip->count();
        Log::info("UpdateManager: extracting {$count} files to {$basePath}");

        // Garde anti zip-slip : on rejette toute entrée dont le chemin résolu
        // échapperait au dossier racine (../, chemin absolu, etc.). On valide
        // toutes les entrées AVANT d'extraire quoi que ce soit.
        $realBase = rtrim(str_replace('\\', '/', realpath($basePath) ?: $basePath), '/');
        for ($i = 0; $i < $count; $i++) {
            $entry = $zip->getNameIndex($i);
            if ($entry === false) {
                continue;
            }

            // Rejette les chemins absolus (unix et windows).
            if (str_starts_with($entry, '/') || preg_match('#^[A-Za-z]:[\\\\/]#', $entry)) {
                $zip->close();
                throw new RuntimeException("Archive rejetée (zip-slip) : chemin absolu interdit « {$entry} ».");
            }

            $target = $realBase . '/' . ltrim(str_replace('\\', '/', $entry), '/');
            // Normalise les segments '.' et '..' sans toucher au disque
            // (l'entrée n'existe pas encore).
            $parts = [];
            foreach (explode('/', $target) as $segment) {
                if ($segment === '..') {
                    array_pop($parts);
                } elseif ($segment !== '.' && $segment !== '') {
                    $parts[] = $segment;
                }
            }
            $resolved = '/' . implode('/', $parts);

            if ($resolved !== $realBase && !str_starts_with($resolved, $realBase . '/')) {
                $zip->close();
                throw new RuntimeException("Archive rejetée (zip-slip) : « {$entry} » sort du dossier racine.");
            }
        }

        // Fichiers protégés par l'hébergeur (aaPanel/宝塔 pose chattr +i sur
        // .user.ini) : une extraction globale échouerait entièrement dès le
        // premier fichier non écrasable ("Operation not permitted"). On extrait
        // donc entrée par entrée, en sautant les fichiers protégés au lieu
        // d'avorter toute la mise à jour.
        $skipBasenames = ['.user.ini', '.htaccess'];
        $skipped = [];
        $extracted = 0;

        for ($i = 0; $i < $count; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false) {
                continue;
            }

            if (in_array(basename($name), $skipBasenames, true)) {
                $skipped[] = $name;
                continue;
            }

            if (!@$zip->extractTo($basePath, $name)) {
                // Probablement un fichier immuable/protégé : on le saute.
                $skipped[] = $name;
                Log::warning("UpdateManager: fichier protégé ignoré pendant l'extraction : {$name}");
                continue;
            }

            $extracted++;
        }

        $zip->close();
        $this->files->delete($zipPath);

        if ($extracted === 0) {
            throw new RuntimeException("L'extraction du ZIP a échoué : aucun fichier extrait.");
        }

        if (!empty($skipped)) {
            Log::info('UpdateManager: ' . count($skipped) . ' fichier(s) protégé(s) ignoré(s) : ' . implode(', ', $skipped));
        }

        Log::info('UpdateManager: extraction OK, running migrations...');

        Artisan::call('migrate', ['--force' => true]);
        Artisan::call('config:clear');
        Artisan::call('cache:clear');
        Artisan::call('view:clear');

        Log::info('UpdateManager: update complete.');
    }

    public function updateIfAvailable(): bool
    {
        $info = $this->fetchUpdateInfo();

        if (!$this->hasUpdate($info)) {
            Log::info("UpdateManager: no update needed (current={$this->currentVersion}, latest=" . ($info['version'] ?? 'unknown') . ')');
            return false;
        }

        Log::warning("UpdateManager: applying release tag v{$info['version']} (from v{$this->currentVersion})");

        $zipPath = $this->downloadUpdate($info);
        $this->installUpdate($zipPath);

        return true;
    }
}
