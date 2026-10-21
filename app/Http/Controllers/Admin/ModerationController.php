<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\GameAnalytics;
use App\Services\GameModeration;
use Illuminate\Http\Request;

/**
 * Admin → Modération : sanctions et signalements lus (en lecture seule) dans la base du jeu
 * (gf_sanctions / gf_reports, voir App\Services\GameModeration).
 *
 * Fail-safe : base absente, injoignable ou tables absentes => écran explicatif, jamais de 500.
 * Vie privée : ni IP ni hash d'IP ni e-mail ne sont affichés ou exportés ; les « comptes liés par
 * adresse » ne sont visibles que d'un super-admin.
 */
class ModerationController extends Controller
{
    public function __construct(protected GameModeration $m)
    {
    }

    protected function ctx(string $page): array
    {
        return [
            'page' => $page,
            'st' => $this->m->status(),
            'isSuper' => (bool) auth()->user()?->isSuperAdmin(),
        ];
    }

    protected function render(string $view, array $ctx, array $data = [])
    {
        if ($ctx['st']['state'] !== 'ok') {
            return view('admin.moderation.unavailable', $ctx);
        }

        return view($view, array_merge($ctx, $data));
    }

    /** Pagination : page courante et décalage. @return array{0:int,1:int} */
    protected function paging(Request $r): array
    {
        $pg = max(1, min(100000, (int) $r->query('page', 1)));

        return [$pg, ($pg - 1) * GameModeration::PER_PAGE];
    }

    public function index()
    {
        $c = $this->ctx('overview');
        if ($c['st']['state'] !== 'ok') {
            return $this->render('', $c);
        }
        $stats = $this->m->stats();
        $types = array_map(fn ($t) => __("moderation.type.$t"), GameModeration::TYPES);
        $chartDay = ['labels' => array_map(fn ($d) => \Carbon\Carbon::parse($d)->locale(app()->getLocale())->translatedFormat('j M'), array_keys($stats['by_day'])),
            'datasets' => [['label' => __('moderation.sanctions'), 'data' => array_values($stats['by_day']), 'color' => '#fb923c']]];
        $chartType = ['labels' => array_values($types), 'datasets' => [['label' => __('moderation.sanctions'), 'data' => array_values($stats['by_type']),
            'colors' => ['#facc15', '#60a5fa', '#a78bfa', '#f87171']]]];

        return $this->render('admin.moderation.index', $c, [
            'stats' => $stats,
            'recent' => $this->m->recentSanctions(8),
            'open' => $this->m->openReports(8),
            'chartDay' => $chartDay,
            'chartType' => $chartType,
            'now' => $this->m->now(),
        ]);
    }

    public function sanctions(Request $r)
    {
        $c = $this->ctx('sanctions');
        if ($c['st']['state'] !== 'ok') {
            return $this->render('', $c);
        }
        $f = GameModeration::sanctionFilters($r->query());
        [$pg, $off] = $this->paging($r);
        $res = $this->m->sanctions($f, GameModeration::PER_PAGE, $off);

        return $this->render('admin.moderation.sanctions', $c, [
            'f' => $f,
            'raw' => ['from' => (string) $r->query('from', ''), 'to' => (string) $r->query('to', '')],
            'rows' => $res['rows'], 'total' => $res['total'], 'pg' => $pg, 'per' => GameModeration::PER_PAGE,
        ]);
    }

    public function sanction(int $id)
    {
        $c = $this->ctx('sanctions');
        if ($c['st']['state'] !== 'ok') {
            return $this->render('', $c);
        }
        $s = $this->m->sanction($id);
        abort_if($s === null, 404);

        return $this->render('admin.moderation.sanction', $c, ['s' => $s]);
    }

    public function reports(Request $r)
    {
        $c = $this->ctx('reports');
        if ($c['st']['state'] !== 'ok') {
            return $this->render('', $c);
        }
        $f = GameModeration::reportFilters($r->query());
        [$pg, $off] = $this->paging($r);
        $res = $this->m->reports($f, GameModeration::PER_PAGE, $off);

        return $this->render('admin.moderation.reports', $c, [
            'f' => $f,
            'raw' => ['from' => (string) $r->query('from', ''), 'to' => (string) $r->query('to', '')],
            'rows' => $res['rows'], 'total' => $res['total'], 'pg' => $pg, 'per' => GameModeration::PER_PAGE,
        ]);
    }

    public function report(int $id)
    {
        $c = $this->ctx('reports');
        if ($c['st']['state'] !== 'ok') {
            return $this->render('', $c);
        }
        $rep = $this->m->report($id);
        abort_if($rep === null, 404);

        return $this->render('admin.moderation.report', $c, ['r' => $rep]);
    }

    /** Recherche d'un joueur : redirige vers sa fiche (pseudo ou UUID validé). */
    public function search(Request $r)
    {
        $q = trim((string) $r->query('q', ''));
        if (! GameModeration::validName($q)) {
            return redirect()->route('admin.moderation.sanctions', ['player' => mb_substr($q, 0, 64)]);
        }

        return redirect()->route('admin.moderation.player', ['player' => $q]);
    }

    public function player(string $player)
    {
        $c = $this->ctx('player');
        if ($c['st']['state'] !== 'ok') {
            return $this->render('', $c);
        }
        $p = $this->m->player($player);
        if ($p['found']) {
            AuditLog::record('moderation.player.view', null, ['player' => $p['name']]);
        }
        $linked = null;
        if ($c['isSuper'] && $p['found']) {
            $linked = $this->m->linkedAccounts($p['uuid']);
            AuditLog::record('moderation.linked.view', null, ['player' => $p['name']]);
        }

        return $this->render('admin.moderation.player', $c, ['p' => $p, 'key' => $player, 'linked' => $linked, 'now' => $this->m->now()]);
    }

    /** Export CSV (super-admin) : séparateur « ; », formules neutralisées, ni IP ni e-mail. */
    public function export(Request $r)
    {
        $c = $this->ctx('sanctions');
        if ($c['st']['state'] !== 'ok') {
            return redirect()->route('admin.moderation.sanctions');
        }
        $f = GameModeration::sanctionFilters($r->query());
        $rows = $this->m->sanctions($f, GameModeration::EXPORT_MAX, 0)['rows'];
        AuditLog::record('moderation.export', null, ['rows' => count($rows), 'filters' => array_filter($f, fn ($v) => $v !== '' && $v !== null)]);
        $safe = fn ($s) => (is_string($s) && $s !== '' && strpbrk($s[0], "=+-@\t\r") !== false) ? "'" . $s : $s;

        return response()->streamDownload(function () use ($rows, $safe) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['ID', __('moderation.date'), __('moderation.type_h'), __('moderation.player'), __('moderation.reason'), __('moderation.staff'),
                __('moderation.expires'), __('moderation.state_h'), __('moderation.revoked_by'), __('moderation.revoked_at'), __('moderation.revoke_reason')], ';');
            foreach ($rows as $s) {
                fputcsv($out, [
                    $s['id'], GameAnalytics::fmtDate($s['created_at']), $s['type_label'], $safe($s['player_label']), $safe($s['reason']), $safe($s['staff_name']),
                    $s['expires_at'] ? GameAnalytics::fmtDate($s['expires_at']) : __('moderation.permanent'), __("moderation.state.{$s['state']}"),
                    $safe($s['revoked_by']), $s['revoked_at'] ? GameAnalytics::fmtDate($s['revoked_at']) : '', $safe($s['revoke_reason']),
                ], ';');
            }
            fclose($out);
        }, 'geoventure-sanctions-' . date('Y-m-d') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
