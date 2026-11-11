<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\GameBridge;
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
 *
 * `architecture_rating` : note d'architecture (0-10) d'un pays pour la
 * notation hebdomadaire EN COURS (plugin rating/CountryRatingManager) ;
 * cible = nom du pays, montant = note. Une nouvelle note remplace la
 * précédente de la même semaine.
 */
class AdminGameCommandController extends Controller
{
    private const TYPES = [
        'give_coins', 'give_key', 'season_points', 'bank_deposit', 'broadcast', 'trigger_event',
        'architecture_rating',
    ];

    /** Types qui créditent de l'argent : réservés aux super-admins (comme la portée « admin » côté MCP). */
    private const MONEY_TYPES = ['give_coins', 'bank_deposit'];

    private function table(): string
    {
        return GameBridge::table();
    }

    private function gameConfigured(): bool
    {
        return GameBridge::configured();
    }

    /** Types proposés à l'utilisateur connecté (un modérateur n'a pas les types qui créditent de l'argent). */
    private function allowedTypes(): array
    {
        if (auth()->user()?->isSuperAdmin()) {
            return self::TYPES;
        }

        return array_values(array_diff(self::TYPES, self::MONEY_TYPES));
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
            'types'     => $this->allowedTypes(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'type'       => 'required|in:'.implode(',', $this->allowedTypes()),
            'target'     => 'required|string|max:255',
            'amount'     => 'nullable|integer|min:0|max:100000000',
            'request_id' => 'nullable|uuid',
        ]);
        // La note d'architecture va de 0 à 10 : le plugin la refuserait, autant le dire ici.
        if ($request->type === 'architecture_rating') {
            $request->validate(['amount' => 'required|integer|min:0|max:10']);
        }

        if (! $this->gameConfigured()) {
            return back()->with('error', __('messages.common.errors_occurred'));
        }

        try {
            // request_id (UUID posé par le formulaire) : un double clic ou un renvoi du même formulaire
            // ne dépose qu'une commande (index UNIQUE côté jeu).
            $outcome = GameBridge::queue(
                $request->type,
                $request->target,
                (int) ($request->amount ?? 0),
                $request->filled('request_id') ? 'panel-'.$request->request_id : null,
            );
        } catch (\Throwable $e) {
            Log::warning('[GameCommands] insertion impossible : '.$e->getMessage());

            return back()->with('error', __('messages.common.errors_occurred'));
        }

        if ($outcome === 'duplicate') {
            return redirect()->route('admin.game-commands')
                ->with('success', __('messages.flash.game_command_created'));
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
