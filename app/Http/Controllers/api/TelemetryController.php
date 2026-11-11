<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\TelemetryEvent;
use Illuminate\Http\Request;

class TelemetryController extends Controller
{
    /**
     * Reçoit un événement de télémétrie du launcher (opt-in côté joueur).
     *
     * Le launcher (utils/telemetry.js) envoie soit un payload plat
     * { event, serverId, launcherVersion, os }, soit l'ancien format
     * { action: 'telemetry', data: { ... } }. On accepte les deux.
     */
    public function store(Request $request)
    {
        $payload = $request->input('data', $request->all());

        // Endpoint public : une charge qui n'est pas un objet, ou dont un champ est un tableau, ferait
        // échouer la conversion en chaîne (500) ; on la refuse proprement.
        if (! is_array($payload)) {
            return response()->json(['ok' => false], 422);
        }
        $text = fn ($v, int $max) => is_scalar($v) ? mb_substr((string) $v, 0, $max) : null;

        $event = (string) $text($payload['event'] ?? null, 64);
        if ($event === '' || ! preg_match('/^[A-Za-z0-9_.:-]+$/', $event)) {
            return response()->json(['ok' => false], 422);
        }

        TelemetryEvent::create([
            'event'            => $event,
            'server_id'        => $text($payload['serverId'] ?? null, 191),
            'launcher_version' => $text($payload['launcherVersion'] ?? null, 191),
            'os'               => $text($payload['os'] ?? null, 191),
            // IP pseudonymisée : HMAC salé par APP_KEY (un SHA-256 simple d'une IPv4 se retrouve par force brute).
            'ip_hash'          => $request->ip() ? hash_hmac('sha256', $request->ip(), (string) config('app.key')) : null,
        ]);

        return response()->json(['ok' => true], 200, [], JSON_UNESCAPED_SLASHES);
    }
}
