<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\WonderEdition;
use App\Models\WonderScore;
use App\Models\WonderTeam;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Wonder : concours de construction par équipes.
 *
 * Le panel est la source de vérité (édition, équipes, jury, résultats) ; le
 * plugin (event/WonderManager) applique côté jeu via la table gf_web_commands
 * (connexion `game`), exactement comme AdminGameCommandController.
 *
 * Contrat des commandes (colonnes type / target / amount, `|` séparateur) :
 *  - wonder_edition : target = JSON {id,name,theme,world,open,close,size,builders}
 *                     (dates en secondes epoch) ; crée ou met à jour l'édition.
 *  - wonder_team    : target = "id|équipe|#couleur" ; amount 1 = créer/MAJ, 0 = supprimer.
 *  - wonder_member  : target = "id|équipe|pseudo" ; amount 1 = bâtisseur,
 *                     2 = remplaçant (spectateur), 0 = retirer.
 *  - wonder_podium  : target = "id|1er|2e|3e" (noms d'équipe) à la publication.
 *  - wonder_cancel  : target = "id" ; supprime l'édition côté jeu.
 *
 * Fail-safe : base du jeu absente ou en erreur => les données sont tout de même
 * enregistrées dans le panel, un avertissement invite à « Resynchroniser ».
 */
class AdminWonderController extends Controller
{
    private const PSEUDO = '/^[A-Za-z0-9_]{3,16}$/';

    private function table(): string
    {
        return config('geoventure.game_table_prefix', 'gf_').'web_commands';
    }

    private function gameConfigured(): bool
    {
        return (string) config('database.connections.game.database') !== '';
    }

    /** Dépose des commandes [type, target, amount] ; renvoie false si la base du jeu est injoignable. */
    private function send(array $commands): bool
    {
        if (! $this->gameConfigured() || empty($commands)) {
            return false;
        }
        try {
            $rows = array_map(fn ($c) => [
                'type'   => $c[0],
                'target' => mb_substr($c[1], 0, 255),
                'amount' => (int) ($c[2] ?? 0),
                'status' => 'pending',
            ], $commands);
            DB::connection('game')->table($this->table())->insert($rows);

            return true;
        } catch (\Throwable $e) {
            Log::warning('[Wonder] envoi impossible : '.$e->getMessage());

            return false;
        }
    }

    private function clean(string $s): string
    {
        return trim(str_replace(['|', "\n", "\r"], ' ', $s));
    }

    private function editionCommand(WonderEdition $e): array
    {
        $json = json_encode([
            'id'       => $e->id,
            'name'     => $e->name,
            'theme'    => $e->theme,
            'world'    => $e->world,
            'open'     => $e->opens_at?->getTimestamp() ?? 0,
            'close'    => $e->closes_at?->getTimestamp() ?? 0,
            'size'     => $e->team_size,
            'builders' => $e->builders,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return ['wonder_edition', $json, 0];
    }

    private function teamCommands(WonderEdition $e, WonderTeam $t): array
    {
        $cmds = [['wonder_team', "{$e->id}|{$t->name}|{$t->color}", 1]];
        foreach ((array) $t->builders as $p) {
            $cmds[] = ['wonder_member', "{$e->id}|{$t->name}|{$p}", 1];
        }
        foreach ((array) $t->reserves as $p) {
            $cmds[] = ['wonder_member', "{$e->id}|{$t->name}|{$p}", 2];
        }

        return $cmds;
    }

    private function flashSync(bool $ok)
    {
        return $ok ? ['success' => __('messages.flash.wonder_saved')]
                   : ['warning' => __('messages.wonder.sync_failed')];
    }

    public function index(Request $request)
    {
        $editions = WonderEdition::orderByDesc('opens_at')->orderByDesc('id')->get();
        $current = $editions->firstWhere('id', (int) $request->query('edition')) ?? $editions->first();
        $teams = $current ? $current->teams()->with('scores')->orderBy('name')->get() : collect();

        return view('admin.wonder', [
            'editions'  => $editions,
            'current'   => $current,
            'teams'     => $teams,
            'ranking'   => $current ? $current->ranking() : [],
            'connected' => $this->gameConfigured(),
        ]);
    }

    private function editionRules(): array
    {
        return [
            'name'             => 'required|string|max:40',
            'theme'            => 'required|string|max:80',
            'world'            => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'opens_at'         => 'required|date',
            'closes_at'        => 'required|date|after:opens_at',
            'team_size'        => 'required|integer|min:1|max:60',
            'builders'         => 'required|integer|min:1|max:10',
            'weight_technical' => 'required|integer|min:0|max:100',
            'weight_aesthetic' => 'required|integer|min:0|max:100',
            'weight_theme'     => 'required|integer|min:0|max:100',
            'rewards'          => 'nullable|string|max:1000',
        ];
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->editionRules());
        $data['name'] = $this->clean($data['name']);
        $edition = WonderEdition::create($data + ['status' => 'draft']);
        AuditLog::record('wonder.edition.create', $edition, ['name' => $edition->name]);

        return redirect()->route('admin.wonder', ['edition' => $edition->id])
            ->with($this->flashSync($this->send([$this->editionCommand($edition)])));
    }

    public function update(Request $request, WonderEdition $edition)
    {
        $data = $request->validate($this->editionRules());
        $data['name'] = $this->clean($data['name']);
        $edition->update($data);
        AuditLog::record('wonder.edition.update', $edition, ['name' => $edition->name]);

        return redirect()->route('admin.wonder', ['edition' => $edition->id])
            ->with($this->flashSync($this->send([$this->editionCommand($edition)])));
    }

    public function destroy(WonderEdition $edition)
    {
        $id = $edition->id;
        AuditLog::record('wonder.edition.delete', $edition, ['name' => $edition->name]);
        $edition->delete();
        $this->send([['wonder_cancel', (string) $id, 0]]);

        return redirect()->route('admin.wonder')->with('success', __('messages.flash.wonder_deleted'));
    }

    /** Renvoie édition + équipes + membres au plugin (rattrapage après une panne). */
    public function sync(WonderEdition $edition)
    {
        $cmds = [$this->editionCommand($edition)];
        foreach ($edition->teams as $t) {
            $cmds = array_merge($cmds, $this->teamCommands($edition, $t));
        }
        AuditLog::record('wonder.sync', $edition);

        return back()->with($this->flashSync($this->send($cmds)));
    }

    private function parsePseudos(?string $raw): array
    {
        $out = [];
        foreach (preg_split('/[\s,;]+/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY) as $p) {
            if (preg_match(self::PSEUDO, $p)) {
                $out[strtolower($p)] = $p;
            }
        }

        return array_values($out);
    }

    private function parseImages(?string $raw): array
    {
        $out = [];
        foreach (preg_split('/\R+/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY) as $u) {
            $u = trim($u);
            if (preg_match('#^https://[^\s<>"\']{4,400}$#i', $u)) {
                $out[] = $u;
            }
        }

        return array_slice($out, 0, 12);
    }

    public function storeTeam(Request $request, WonderEdition $edition)
    {
        $request->validate([
            'name'     => ['required', 'string', 'max:32'],
            'color'    => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'builders' => 'nullable|string|max:1000',
            'reserves' => 'nullable|string|max:1500',
            'images'   => 'nullable|string|max:5000',
        ]);
        $name = $this->clean($request->name);
        if ($edition->teams()->where('name', $name)->exists()) {
            return back()->withErrors(['name' => __('messages.wonder.team_exists')])->withInput();
        }

        $err = $this->fillTeam($edition, $request, $team = new WonderTeam([
            'wonder_edition_id' => $edition->id, 'name' => $name,
        ]));
        if ($err) {
            return back()->withErrors(['builders' => $err])->withInput();
        }
        $team->save();
        AuditLog::record('wonder.team.create', $team, ['name' => $name]);

        return back()->with($this->flashSync($this->send($this->teamCommands($edition, $team))));
    }

    /** Applique le formulaire à l'équipe ; renvoie un message d'erreur ou null. */
    private function fillTeam(WonderEdition $edition, Request $request, WonderTeam $team): ?string
    {
        $builders = $this->parsePseudos($request->builders);
        $reserves = array_values(array_udiff($this->parsePseudos($request->reserves), $builders, 'strcasecmp'));
        if (count($builders) > $edition->builders) {
            return __('messages.wonder.too_many_builders', ['max' => $edition->builders]);
        }
        if (count($builders) + count($reserves) > $edition->team_size) {
            return __('messages.wonder.too_many_members', ['max' => $edition->team_size]);
        }
        // Un joueur ne peut appartenir qu'à une équipe de l'édition.
        $taken = [];
        foreach ($edition->teams()->where('id', '!=', $team->id ?? 0)->get() as $o) {
            foreach (array_merge((array) $o->builders, (array) $o->reserves) as $p) {
                $taken[strtolower($p)] = $o->name;
            }
        }
        foreach (array_merge($builders, $reserves) as $p) {
            if (isset($taken[strtolower($p)])) {
                return __('messages.wonder.player_taken', ['player' => $p, 'team' => $taken[strtolower($p)]]);
            }
        }
        $team->builders = $builders;
        $team->reserves = $reserves;
        $team->color = strtolower($request->color);
        $team->images = $this->parseImages($request->images);

        return null;
    }

    public function updateTeam(Request $request, WonderTeam $team)
    {
        $request->validate([
            'color'    => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'builders' => 'nullable|string|max:1000',
            'reserves' => 'nullable|string|max:1500',
            'images'   => 'nullable|string|max:5000',
        ]);
        $edition = $team->edition;
        $before = [
            'b' => array_map('strtolower', (array) $team->builders),
            'r' => array_map('strtolower', (array) $team->reserves),
        ];
        $err = $this->fillTeam($edition, $request, $team);
        if ($err) {
            return back()->withErrors(['team_'.$team->id => $err]);
        }
        $team->save();
        AuditLog::record('wonder.team.update', $team, ['name' => $team->name]);

        $cmds = [['wonder_team', "{$edition->id}|{$team->name}|{$team->color}", 1]];
        $now = array_merge((array) $team->builders, (array) $team->reserves);
        $nowLower = array_map('strtolower', $now);
        foreach (array_merge($before['b'], $before['r']) as $gone) {
            if (! in_array($gone, $nowLower, true)) {
                $cmds[] = ['wonder_member', "{$edition->id}|{$team->name}|{$gone}", 0];
            }
        }
        foreach ((array) $team->builders as $p) {
            $cmds[] = ['wonder_member', "{$edition->id}|{$team->name}|{$p}", 1];
        }
        foreach ((array) $team->reserves as $p) {
            $cmds[] = ['wonder_member', "{$edition->id}|{$team->name}|{$p}", 2];
        }

        return back()->with($this->flashSync($this->send($cmds)));
    }

    public function destroyTeam(WonderTeam $team)
    {
        $edition = $team->edition;
        $this->send([['wonder_team', "{$edition->id}|{$team->name}|{$team->color}", 0]]);
        AuditLog::record('wonder.team.delete', $team, ['name' => $team->name]);
        $team->delete();

        return back()->with('success', __('messages.flash.wonder_deleted'));
    }

    public function score(Request $request, WonderTeam $team)
    {
        if ($team->edition->status === 'published') {
            return back()->with('error', __('messages.wonder.already_published'));
        }
        $data = $request->validate([
            'juror'     => ['required', 'string', 'max:32'],
            'technical' => 'required|numeric|min:0|max:10',
            'aesthetic' => 'required|numeric|min:0|max:10',
            'theme'     => 'required|numeric|min:0|max:10',
        ]);
        $data['juror'] = $this->clean($data['juror']);
        WonderScore::updateOrCreate(
            ['wonder_team_id' => $team->id, 'juror' => $data['juror']],
            ['technical' => $data['technical'], 'aesthetic' => $data['aesthetic'], 'theme' => $data['theme']]
        );
        AuditLog::record('wonder.score', $team, ['juror' => $data['juror']] + $request->only('technical', 'aesthetic', 'theme'));

        return back()->with('success', __('messages.flash.wonder_score_saved'));
    }

    public function destroyScore(WonderScore $score)
    {
        $team = WonderTeam::find($score->wonder_team_id);
        if ($team && $team->edition->status === 'published') {
            return back()->with('error', __('messages.wonder.already_published'));
        }
        AuditLog::record('wonder.score.delete', $score, ['juror' => $score->juror]);
        $score->delete();

        return back()->with('success', __('messages.flash.wonder_deleted'));
    }

    public function publish(WonderEdition $edition)
    {
        if ($edition->status === 'published') {
            return back()->with('error', __('messages.wonder.already_published'));
        }
        $ranking = $edition->ranking();
        if (empty($ranking)) {
            return back()->with('error', __('messages.wonder.nothing_to_publish'));
        }
        $edition->update(['status' => 'published', 'published_at' => now()]);
        AuditLog::record('wonder.publish', $edition, ['winner' => $ranking[0]['team']]);

        $podium = array_map(fn ($i) => $ranking[$i]['team'] ?? '', [0, 1, 2]);
        $ok = $this->send([['wonder_podium', $edition->id.'|'.implode('|', $podium), 0]]);

        return back()->with($ok ? ['success' => __('messages.flash.wonder_published')]
                                : ['warning' => __('messages.wonder.sync_failed')]);
    }
}
