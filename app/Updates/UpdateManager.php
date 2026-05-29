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

            foreach ($data['assets'] ?? [] as $asset) {
                if (str_ends_with($asset['name'], '.zip')) {
                    $zipUrl   = $asset['browser_download_url'];
                    $fileName = $asset['name'];
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

        return $filePath;
    }

    public function installUpdate(string $zipPath): void
    {
        $basePath = base_path();

        if (!is_writable($basePath)) {
            throw new RuntimeException("Le dossier racine n'est pas accessible en écriture : {$basePath}");
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

        Log::info("UpdateManager: extracting {$zip->count()} files to {$basePath}");

        if (!$zip->extractTo($basePath)) {
            $zip->close();
            throw new RuntimeException("L'extraction du ZIP a échoué.");
        }

        $zip->close();
        $this->files->delete($zipPath);

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

        Log::info("UpdateManager: updating from v{$this->currentVersion} to v{$info['version']}");

        $zipPath = $this->downloadUpdate($info);
        $this->installUpdate($zipPath);

        return true;
    }
}
