<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\LauncherDiagnostic;
use Illuminate\Http\Request;

class DiagnosticController extends Controller
{
    /** Taille maximale d'un rapport (octets). */
    public const MAX_REPORT = 300_000;

    /**
     * Reçoit un rapport de diagnostic du launcher : { launcherVersion, instance, os, report }.
     * Public (le joueur n'a pas de compte panel) : limité en débit par la route et en taille ici.
     */
    public function store(Request $request)
    {
        $report = $request->input('report');
        if (! is_string($report) || trim($report) === '' || strlen($report) > self::MAX_REPORT) {
            return response()->json(['ok' => false], 422);
        }
        $text = fn ($v, int $max) => is_scalar($v) ? mb_substr((string) $v, 0, $max) : null;
        $ipHash = $request->ip() ? hash_hmac('sha256', $request->ip(), (string) config('app.key')) : null;

        // Un même PC ne remplit pas la table : 5 rapports par jour au plus.
        if ($ipHash && LauncherDiagnostic::where('ip_hash', $ipHash)->where('created_at', '>=', now()->subDay())->count() >= 5) {
            return response()->json(['ok' => false, 'reason' => 'too_many'], 429);
        }

        LauncherDiagnostic::create([
            'launcher_version' => $text($request->input('launcherVersion'), 64),
            'instance'         => $text($request->input('instance'), 64),
            'os'               => $text($request->input('os'), 64),
            'summary'          => self::summarize($report),
            'report'           => $report,
            'ip_hash'          => $ipHash,
        ]);
        LauncherDiagnostic::where('created_at', '<', now()->subDays(LauncherDiagnostic::KEEP_DAYS))->delete();

        return response()->json(['ok' => true]);
    }

    /** Résumé en une ligne : joueur, cause de plantage reconnue ou erreurs de connexion. */
    public static function summarize(string $report): string
    {
        $parts = [];
        if (preg_match('/^Joueur : (.+)$/m', $report, $m)) {
            $parts[] = trim($m[1]);
        }
        if (preg_match('/Plantage analysé : ([^—\n]+)/u', $report, $m)) {
            $parts[] = trim($m[1]);
        } elseif (preg_match('/OutOfMemoryError|EXCEPTION_ACCESS_VIOLATION|Missing or unsupported mandatory dependencies|GLFW error/', $report, $m)) {
            $parts[] = $m[0];
        }
        if (preg_match_all('/^(\S+) : ÉCHEC/mu', $report, $m) && $m[1]) {
            $parts[] = 'injoignable : ' . implode(', ', array_unique($m[1]));
        }
        return mb_substr(implode(' · ', $parts) ?: 'Rapport sans erreur reconnue', 0, 255);
    }
}
