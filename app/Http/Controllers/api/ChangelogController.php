<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\Changelog;

class ChangelogController extends Controller
{
    /**
     * Les 10 dernières entrées de changelog actives, triées de la plus récente
     * à la plus ancienne. Fail-safe : toute erreur (table absente, DB down…)
     * renvoie [] (200) plutôt qu'un 500 HTML qui casserait le launcher.
     */
    public function getChangelog()
    {
        try {
            $entries = Changelog::where('active', true)
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->limit(10)
                ->get()
                ->map(fn ($c) => [
                    'id'          => $c->id,
                    'version'     => $c->version,
                    'title'       => $c->title,
                    'body'        => $c->body,
                    'imageUrl'    => $c->image_url,
                    'publishedAt' => $c->published_at ? $c->published_at->getTimestampMs() : $c->created_at->getTimestampMs(),
                ])
                ->values();
        } catch (\Throwable $e) {
            $entries = [];
        }

        return response()->json($entries, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
