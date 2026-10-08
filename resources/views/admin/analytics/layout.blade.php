@extends('layouts.admin')

@section('title', __('analytics.title'))

@section('page-title', __('analytics.title'))

@php
    // Menu regroupé par catégories : groupe => [page => [icône, route]]
    $groups = [
        'general' => ['overview' => ['bi-speedometer2', 'admin.analytics.overview'], 'health' => ['bi-heart-pulse', 'admin.analytics.health'], 'peak' => ['bi-clock-history', 'admin.analytics.peak']],
        'countries' => ['countries' => ['bi-flag', 'admin.analytics.countries'], 'compare' => ['bi-hexagon', 'admin.analytics.compare'], 'territories' => ['bi-map', 'admin.analytics.territories'],
            'diplomacy' => ['bi-diagram-2', 'admin.analytics.diplomacy'], 'research' => ['bi-diagram-3', 'admin.analytics.research']],
        'economy' => ['economy' => ['bi-coin', 'admin.analytics.economy'], 'oil' => ['bi-droplet-half', 'admin.analytics.oil'], 'logistics' => ['bi-truck', 'admin.analytics.logistics'],
            'ecology' => ['bi-tree', 'admin.analytics.ecology']],
        'war' => ['war' => ['bi-bullseye', 'admin.analytics.war'], 'conflicts' => ['bi-shield-exclamation', 'admin.analytics.conflicts']],
        'players' => ['players' => ['bi-people', 'admin.analytics.players'], 'retention' => ['bi-arrow-repeat', 'admin.analytics.retention'], 'rankings' => ['bi-trophy', 'admin.analytics.rankings'],
            'achievements' => ['bi-award', 'admin.analytics.achievements']],
        'usage' => ['usage' => ['bi-wrench-adjustable', 'admin.analytics.usage'], 'events' => ['bi-list-ul', 'admin.analytics.events']],
    ];
    $statusOk = ($st['state'] ?? '') === 'ok';
@endphp

@section('content')
<link rel="stylesheet" href="{{ asset('assets/css/palette.css') }}">
<style>
    .an-menu { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: .5rem 1rem; margin-bottom: 1rem; }
    .an-menu .grp { font-size: .68rem; text-transform: uppercase; letter-spacing: .06em; opacity: .6; margin-bottom: .15rem; }
    .an-menu .nav-link { padding: .25rem .6rem; font-size: .85rem; }
    .an-menu .nav-link.active { background: var(--geo-geoventure); color: #0b1a10; }
    .an-table td.num, .an-table th.num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .an-table td.text-break { overflow-wrap: anywhere; }
    .an-card { border-left: 4px solid var(--c, var(--geo-geoventure)); }
    .an-dot { display: inline-block; width: .7rem; height: .7rem; border-radius: 50%; margin-right: .4rem; background: var(--c, #888); vertical-align: baseline; }
    .an-heat td { text-align: center; font-variant-numeric: tabular-nums; font-size: .78rem; padding: .2rem; min-width: 2rem; }
    .an-note { font-size: .85rem; opacity: .75; }
    .an-svg { width: 100%; background: rgba(127,127,127,.08); border-radius: 6px; }
    @media (max-width: 575px) { .an-kpi .v { font-size: 1.25rem; } .an-menu { grid-template-columns: 1fr 1fr; } }
    .an-kpi { border-left: 3px solid var(--geo-geoventure); }
    .an-kpi .v { font-size: 1.6rem; font-weight: 600; line-height: 1.1; }
    .an-chart-wrap { position: relative; }
    .an-empty { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; opacity: .6; font-size: .9rem; text-align: center; padding: 1rem; }
    .an-chip { display: inline-block; padding: .05rem .5rem; border-radius: 999px; font-size: .75rem; background: rgba(74,222,128,.15); border: 1px solid rgba(74,222,128,.35); white-space: nowrap; }
    .an-bar { height: .5rem; border-radius: 4px; background: rgba(127,127,127,.2); overflow: hidden; min-width: 80px; }
    .an-bar > i { display: block; height: 100%; background: var(--geo-geoventure); }
    .an-table td, .an-table th { vertical-align: middle; }
    .an-click { cursor: pointer; }
    #an-map { width: 100%; height: 420px; background: rgba(127,127,127,.08); border-radius: 6px; }
    #an-map .tr { cursor: pointer; fill: none; stroke-width: 2; opacity: .85; }
    #an-map .tr:hover, #an-map .tr.sel { stroke-width: 4; opacity: 1; }
    .an-mono { font-family: ui-monospace, monospace; font-size: .8rem; }
</style>

<div id="an-root" data-period="{{ $period }}" data-url="{{ route('admin.analytics.data') }}" data-locale="{{ app()->getLocale() }}">
    <nav class="an-menu" aria-label="{{ __('analytics.title') }}">
        @foreach ($groups as $g => $pages)
            <div>
                <div class="grp">{{ __("analytics.grp.$g") }}</div>
                <ul class="nav nav-pills flex-wrap gap-1">
                    @foreach ($pages as $key => [$icon, $route])
                        @continue(! \Illuminate\Support\Facades\Route::has($route))
                        <li class="nav-item">
                            <a class="nav-link {{ $page === $key ? 'active' : '' }}" href="{{ route($route, ['period' => $period]) }}">
                                <i class="bi {{ $icon }} me-1"></i>{{ __("analytics.tab.$key") }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </nav>

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div class="btn-group btn-group-sm" role="group" aria-label="{{ __('analytics.period') }}">
            @foreach ($periods as $p)
                <a href="{{ request()->fullUrlWithQuery(['period' => $p, 'page' => null]) }}" data-period-link="{{ $p }}"
                   class="btn btn-{{ $period === $p ? 'primary' : 'outline-secondary' }}">{{ __("analytics.p.$p") }}</a>
            @endforeach
        </div>
        <div class="d-flex align-items-center gap-3 small">
            <label class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" id="an-auto" checked>
                <span class="form-check-label">{{ __('analytics.auto') }}</span>
            </label>
        </div>
    </div>

    <div id="an-live">
        @if ($statusOk)
            <p class="small text-muted mb-3">
                @if ($lastTs)
                    {{ __('analytics.last_data', ['date' => \App\Services\GameAnalytics::fmtDate($lastTs)]) }}
                @else
                    {{ __('analytics.no_metrics_yet') }}
                @endif
                @if ($stale)
                    <span class="text-warning ms-2"><i class="bi bi-exclamation-triangle"></i> {{ __('analytics.stale') }}</span>
                @endif
            </p>
        @endif
        @yield('an')
    </div>
</div>

<script>
    window.ANL = @json(__('analytics.js'));
</script>
<script src="{{ asset('assets/vendor/chart.umd.js') }}"></script>
<script src="{{ asset('assets/js/analytics.js') }}?v=3"></script>
@endsection
