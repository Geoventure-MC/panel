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

        $event = (string) ($payload['event'] ?? '');
        if ($event === '') {
            return response()->json(['ok' => false], 422);
        }

        TelemetryEvent::create([
            'event'            => mb_substr($event, 0, 191),
            'server_id'        => isset($payload['serverId']) ? mb_substr((string) $payload['serverId'], 0, 191) : null,
            'launcher_version' => isset($payload['launcherVersion']) ? mb_substr((string) $payload['launcherVersion'], 0, 191) : null,
            'os'               => isset($payload['os']) ? mb_substr((string) $payload['os'], 0, 191) : null,
            // IP anonymisée : on ne stocke qu'un hash (compte des uniques sans PII).
            'ip_hash'          => $request->ip() ? hash('sha256', $request->ip()) : null,
        ]);

        return response()->json(['ok' => true], 200, [], JSON_UNESCAPED_SLASHES);
    }
}
