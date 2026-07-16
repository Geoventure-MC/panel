<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Pont web → jeu : dépose des commandes dans la table gf_web_commands de la
 * base du serveur (connexion `game`, identifiants GEO_GAME_DB_*). Le plugin
 * GeoFactions les consomme toutes les 5 s et marque le résultat.
 *
 * Fail-safe : si la connexion `game` n'est pas configurée, la page reste
 * accessible et l'explique — jamais de 500.
 */
class AdminGameCommandController extends Controller
{
    private const TYPES = [
        'give_coins', 'give_key', 'season_points', 'bank_deposit', 'broadcast', 'trigger_event',
    ];

    private function table(): string
    {
        return config('geoventure.game_table_prefix', 'gf_').'web_commands';
    }

    private function gameConfigured(): bool
    {
        return (string) config('database.connections.game.database') !== '';
    }

    public function index()
    {
        $commands = collect();
        $connected = false;
        if ($this->gameConfigured()) {
            try {
                $commands = DB::connection('game')->table($this->table())
                    ->orderByDesc('id')->limit(50)->get();
                $connected = true;
            } catch (\Throwable $e) {
                Log::warning('[GameCommands] lecture impossible : '.$e->getMessage());
            }
        }

        return view('admin.game_commands', [
            'commands'  => $commands,
            'connected' => $connected,
            'types'     => self::TYPES,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'type'   => 'required|in:'.implode(',', self::TYPES),
            'target' => 'required|string|max:255',
            'amount' => 'nullable|integer|min:0|max:100000000',
        ]);

        if (! $this->gameConfigured()) {
            return back()->with('error', __('messages.common.errors_occurred'));
        }

        try {
            DB::connection('game')->table($this->table())->insert([
                'type'   => $request->type,
                'target' => $request->target,
                'amount' => (int) ($request->amount ?? 0),
                'status' => 'pending',
            ]);
        } catch (\Throwable $e) {
            Log::warning('[GameCommands] insertion impossible : '.$e->getMessage());

            return back()->with('error', __('messages.common.errors_occurred'));
        }

        AuditLog::record('game_command.create', null, [
            'type'   => $request->type,
            'target' => $request->target,
            'amount' => (int) ($request->amount ?? 0),
        ]);

        return redirect()->route('admin.game-commands')
            ->with('success', __('messages.flash.game_command_created'));
    }
}
