<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\OptionsIgnore;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
    public function getFiles(): JsonResponse
    {
        $dir = storage_path('app/public/data');
        $ignoredFolders = OptionsIgnore::pluck('folder_name')->toArray();

        // Si le dossier modpack n'existe pas encore (aucun fichier uploadé),
        // on renvoie une liste vide plutôt qu'un 500 : le launcher plante sur
        // une réponse HTML (GetInfoVersion / téléchargement du jeu).
        if (!is_dir($dir)) {
            return response()->json([], 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        // Le hash sha1 de tout le modpack est coûteux : on met le manifeste en
        // cache 5 min. La clé inclut la mtime du dossier et les dossiers ignorés
        // pour invalider le cache dès qu'un fichier change ou que la config bouge.
        $cacheKey = 'modpack_manifest_' . md5($dir . '|' . filemtime($dir) . '|' . implode(',', $ignoredFolders));
        $manifest = Cache::remember($cacheKey, 300, function () use ($dir, $ignoredFolders) {
            return $this->dirToArray($dir, '', $ignoredFolders);
        });

        return response()->json($manifest, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function dirToArray($dir, $basePath = '', $ignoredFolders = [])
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
                    $files = array_merge($files, $this->dirToArray($path, $relativePath, $ignoredFolders));
                } else {
                    $hash = hash_file('sha1', $path);
                    $size = filesize($path);
                    $url = url('storage/data/' . $relativePath);

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
