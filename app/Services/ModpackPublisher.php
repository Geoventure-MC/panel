<?php

namespace App\Services;

use App\Models\OptionsServer;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

/**
 * Publie un mod (.jar) dans le modpack servi au launcher (storage/app/public/data/<instance>/...).
 * Le launcher compare sha1/taille de GET /data : déposer le fichier suffit à mettre les joueurs à jour.
 * Sécurité : https, hôtes en liste blanche (y compris à chaque redirection), taille plafonnée, signature ZIP,
 * nom de fichier strict, chemins confinés au dossier de l'instance. Les anciens jars remplacés sont mis de
 * côté HORS du modpack (storage/app/modpack-backup) pour ne jamais être téléchargés par les joueurs.
 */
class ModpackPublisher
{
    public static function baseDir(?string $instance): string
    {
        $base = storage_path('app/public/data');
        if ($instance) {
            $inst = OptionsServer::resolveInstance($instance);
            $folder = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($inst ? $inst->modpack_folder : $instance));
            if ($folder === '') {
                throw new \RuntimeException('Instance invalide.');
            }

            return $base . '/' . $folder;
        }

        return $base;
    }

    /** Sous-dossier relatif sûr (sans .., sans absolu). */
    public static function safeSub(string $sub): string
    {
        $sub = trim(str_replace('\\', '/', $sub), '/');
        foreach (explode('/', $sub) as $part) {
            if ($sub !== '' && ($part === '' || $part === '.' || $part === '..' || ! preg_match('/^[A-Za-z0-9._+-]+$/', $part))) {
                throw new \RuntimeException('Sous-dossier invalide.');
            }
        }

        return $sub;
    }

    public static function safeName(string $name): string
    {
        if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9._+-]{0,120}\.jar$/', $name)) {
            throw new \RuntimeException('Nom de fichier invalide (lettres, chiffres, . _ + - ; extension .jar).');
        }

        return $name;
    }

    private static function checkHost(string $url): void
    {
        $p = parse_url($url);
        if (($p['scheme'] ?? '') !== 'https' || ! in_array(strtolower($p['host'] ?? ''), config('mcp.modpack_download_hosts', []), true)) {
            throw new \RuntimeException('URL refusée (https et hôte autorisé obligatoires : ' . implode(', ', config('mcp.modpack_download_hosts', [])) . ').');
        }
    }

    /** @return array{path:string,size:int,sha1:string,replaced:array} */
    public static function publish(string $url, ?string $instance, string $sub, ?string $filename, ?string $replacePrefix): array
    {
        self::checkHost($url);
        if ($filename === null || $filename === '') {
            $filename = rawurldecode(basename((string) parse_url($url, PHP_URL_PATH)));
        }
        $filename = self::safeName($filename);
        $sub = self::safeSub($sub);
        $dir = self::baseDir($instance) . ($sub !== '' ? '/' . $sub : '');
        // Piège : créer data/<instance>/ fait basculer le launcher sur ce dossier (modpack racine ignoré).
        if ($instance && ! is_dir(self::baseDir($instance))) {
            throw new \RuntimeException('Le dossier modpack de cette instance n\'existe pas : publier sans « instance » (modpack racine) ou y copier d\'abord TOUT le modpack.');
        }
        File::ensureDirectoryExists($dir);

        // Préfixe d'artefact par défaut : nom avant la première « -<chiffre> » (geomods-3.21.36.jar -> geomods).
        if ($replacePrefix === null) {
            $replacePrefix = preg_match('/^(.*?)[-_]v?\d/', $filename, $m) ? $m[1] : null;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'modpack');
        $max = (int) config('mcp.modpack_max_bytes');
        try {
            $resp = Http::timeout(300)->withOptions([
                'sink' => $tmp,
                'allow_redirects' => [
                    'max' => 4, 'protocols' => ['https'],
                    'on_redirect' => function ($req, $res, $uri) { self::checkHost((string) $uri); },
                ],
                'progress' => function ($dlTotal, $dlNow) use ($max) {
                    if ($dlNow > $max || $dlTotal > $max) {
                        throw new \RuntimeException('Fichier trop gros.');
                    }
                },
            ])->get($url);
            if (! $resp->successful()) {
                throw new \RuntimeException('Téléchargement refusé (HTTP ' . $resp->status() . ').');
            }
            $size = filesize($tmp);
            if ($size < 1024 || $size > $max) {
                throw new \RuntimeException('Taille de fichier invalide.');
            }
            if (file_get_contents($tmp, false, null, 0, 4) !== "PK\x03\x04") {
                throw new \RuntimeException('Ce n\'est pas un jar (signature ZIP absente).');
            }
            $sha1 = sha1_file($tmp);

            // Anciennes versions du même mod : mises de côté hors du modpack.
            $replaced = [];
            if ($replacePrefix !== null && $replacePrefix !== '') {
                $bak = storage_path('app/modpack-backup/' . date('Ymd-His'));
                foreach (glob($dir . '/*.jar') ?: [] as $old) {
                    $b = basename($old);
                    if ($b !== $filename && (str_starts_with($b, $replacePrefix . '-') || str_starts_with($b, $replacePrefix . '_'))) {
                        File::ensureDirectoryExists($bak);
                        File::move($old, $bak . '/' . $b);
                        $replaced[] = $b;
                    }
                }
            }
            File::move($tmp, $dir . '/' . $filename);
            @chmod($dir . '/' . $filename, 0644);
        } finally {
            if (is_file($tmp)) {
                @unlink($tmp);
            }
        }

        // Le manifeste /data est mis en cache selon la mtime du dossier d'instance : on l'invalide.
        @touch(self::baseDir($instance));
        @touch($dir);

        return ['path' => ($sub !== '' ? $sub . '/' : '') . $filename, 'size' => $size, 'sha1' => $sha1, 'replaced' => $replaced];
    }

    public static function remove(?string $instance, string $relPath): array
    {
        $sub = self::safeSub(dirname($relPath) === '.' ? '' : dirname($relPath));
        $name = self::safeName(basename($relPath));
        $file = self::baseDir($instance) . ($sub !== '' ? '/' . $sub : '') . '/' . $name;
        if (! is_file($file)) {
            throw new \RuntimeException('Fichier introuvable.');
        }
        $bak = storage_path('app/modpack-backup/' . date('Ymd-His'));
        File::ensureDirectoryExists($bak);
        File::move($file, $bak . '/' . $name);
        // Retire les dossiers devenus vides (sinon un dossier d'instance vide masquerait le modpack racine).
        $root = storage_path('app/public/data');
        for ($d = dirname($file); str_starts_with($d, $root . '/') && @rmdir($d); $d = dirname($d)) {
        }
        @touch(is_dir(self::baseDir($instance)) ? self::baseDir($instance) : $root);

        return ['removed' => $relPath];
    }

    public static function listing(?string $instance, string $sub = 'mods'): array
    {
        $dir = self::baseDir($instance) . ($sub !== '' ? '/' . self::safeSub($sub) : '');
        if (! is_dir($dir)) {
            return [];
        }
        $out = [];
        foreach (scandir($dir) ?: [] as $f) {
            if ($f !== '.' && $f !== '..' && is_file($dir . '/' . $f)) {
                $out[] = ['file' => $f, 'size' => filesize($dir . '/' . $f), 'modified' => date('c', filemtime($dir . '/' . $f))];
            }
        }

        return $out;
    }
}
