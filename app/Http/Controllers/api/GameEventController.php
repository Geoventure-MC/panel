<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\GameEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * GET /utils/game-events — événements de jeu pilotés par le panel (public, sans secret).
 * [{ slug, name, enabled, phase, opensAt, endsAt (epoch ms), settings }]
 * ETag + Cache-Control 30 s + 304, jamais de 500. Interrogé par le plugin GeoFactions.
 */
class GameEventController extends Controller
{
    public function index(Request $request)
    {
        try {
            $payload = GameEvent::orderBy('id')->get()->map->toPublic()->values()->all();
        } catch (\Throwable $e) {
            Log::warning('GameEventController@index: ' . $e->getMessage());
            $payload = [];
        }
        $body = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $etag = '"' . md5($body) . '"';
        $headers = ['Content-Type' => 'application/json', 'ETag' => $etag, 'Cache-Control' => 'public, max-age=30'];
        if (trim($request->header('If-None-Match', '')) === $etag) {
            return response('', 304, $headers);
        }

        return response($body, 200, $headers);
    }
}
