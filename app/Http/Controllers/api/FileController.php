<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\OptionsIgnore;
use App\Models\OptionsServer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class FileController extends Controller
{
    public function getFiles(): Response
    {
        $base = storage_path('app/public/data');
        $ignoredFolders = OptionsIgnore::pluck('folder_name')->toArray();

        // Multi-instance : ?instance=<slug> sert le modpack du sous-dossier
        // data/<slug>/ (ou data_folder personnalisé). Les URLs renvoyées
        // pointent vers storage/data/<slug>/...  Sans instance (ou si le
        // sous-dossier n'existe pas) → modpack racine historique (rétrocompat).
        $urlPrefix = 'data';
        $dir = $base;

        $slug = request()->query('instance');
        $channel = strtolower((string) request()->query('channel', 'stable')) === 'test' ? 'test' : 'stable';
        if ($slug || $channel === 'test') {
            $instance = $slug ? OptionsServer::resolveInstance($slug) : OptionsServer::where('is_default', true)->first();
            if ($channel === 'test') {
                $testFolder = $instance ? $instance->test_modpack_folder : ($slug ? ($slug . '-test') : 'test');
                $testFolder = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $testFolder);
                if ($testFolder !== '' && is_dir($base . '/' . $testFolder) && $this->hasFiles($base . '/' . $testFolder)) {
                    $dir = $base . '/' . $testFolder;
                    $urlPrefix = 'data/' . $testFolder;
                } elseif (is_dir($base . '/test') && $this->hasFiles($base . '/test')) {
                    $dir = $base . '/test';
                    $urlPrefix = 'data/test';
                }
            }
            if ($dir === $base && $slug) {
                $folder = $instance ? $instance->modpack_folder : $slug;
                // Anti path-traversal : on slugifie le nom de dossier.
                $folder = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $folder);
                // Un dossier d'instance sans AUCUN fichier ne doit pas masquer le modpack racine (manifeste vide = launcher qui supprime).
                if ($folder !== '' && is_dir($base . '/' . $folder) && $this->hasFiles($base . '/' . $folder)) {
                    $dir = $base . '/' . $folder;
                    $urlPrefix = 'data/' . $folder;
                }
            }
        }

        // Si le dossier modpack n'existe pas encore (aucun fichier uploadé),
        // on renvoie une liste vide plutôt qu'un 500 : le launcher plante sur
        // une réponse HTML (GetInfoVersion / téléchargement du jeu).
        if (!is_dir($dir)) {
            return response()->json([], 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        // Le hash sha1 de tout le modpack est coûteux : le manifeste reste en cache tant que l'empreinte de
        // l'arborescence (mtime, taille, nombre de fichiers, sous-dossiers compris) ne change pas.
        $cacheKey = 'modpack_manifest_' . md5($dir . '|' . $this->treeStamp($dir) . '|' . implode(',', $ignoredFolders));
        $manifest = Cache::get($cacheKey);
        if ($manifest === null) {
            $build = fn () => $this->dirToArray($dir, '', $ignoredFolders, $urlPrefix);
            try {
                // Un seul calcul à la fois : les clients simultanés attendent son résultat au lieu de tout rehacher.
                $manifest = Cache::lock($cacheKey . '_lock', 300)->block(120, fn () => Cache::remember($cacheKey, 86400, $build));
            } catch (\Throwable $e) {
                // Verrou indisponible ou attente dépassée : le launcher doit quand même recevoir son manifeste.
                $manifest = $build();
            }
        }

        $response = response()->json($manifest, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $etag = '"' . md5($response->getContent()) . '"';
        $headers = ['ETag' => $etag, 'Cache-Control' => 'public, max-age=30'];
        if (trim((string) request()->header('If-None-Match', '')) === $etag) {
            return response('', 304, $headers);
        }

        return $response->withHeaders($headers);
    }

    /** Empreinte peu coûteuse de l'arborescence (aucun hash de contenu), recalculée au plus toutes les 30 s. */
    private function treeStamp(string $dir): string
    {
        return Cache::remember('modpack_stamp_' . md5($dir), 30, function () use ($dir) {
            $count = 0;
            $size = 0;
            $newest = 0;
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $f) {
                if ($f->isFile()) {
                    $count++;
                    $size += $f->getSize();
                    $newest = max($newest, $f->getMTime());
                }
            }

            return $count . '-' . $size . '-' . $newest;
        });
    }

    private function hasFiles(string $dir): bool
    {
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $f) {
            if ($f->isFile()) {
                return true;
            }
        }

        return false;
    }

    private function dirToArray($dir, $basePath = '', $ignoredFolders = [], $urlPrefix = 'data')
    {
        $files = [];
        $cdir = scandir($dir);
        if ($cdir === false) {
            return $files;
        }

        foreach ($cdir as $value) {
            if (!in_array($value, [".", ".."]) && !in_array($value, $ignoredFolders)) {
                $path = $dir . '/' . $value;
                $relativePath = ltrim($basePath . '/' . $value, '/');

                if (is_dir($path)) {
                    $files = array_merge($files, $this->dirToArray($path, $relativePath, $ignoredFolders, $urlPrefix));
                } else {
                    $hash = hash_file('sha1', $path);
                    $size = filesize($path);
                    $url = url('storage/' . $urlPrefix . '/' . $relativePath);

                    $files[] = [
                        'path' => $relativePath,
                        'size' => $size,
                        'hash' => $hash,
                        'url' => $url
                    ];
                }
            }
        }

        return $files;
    }
}
