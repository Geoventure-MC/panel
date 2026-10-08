@extends('layouts.admin')

@section('title', __('analytics.title'))

@section('page-title', __('analytics.title'))

@php
    $tabs = [
        'overview' => ['bi-speedometer2', 'admin.analytics.overview'],
        'countries' => ['bi-flag', 'admin.analytics.countries'],
        'research' => ['bi-diagram-3', 'admin.analytics.research'],
        'oil' => ['bi-droplet-half', 'admin.analytics.oil'],
        'economy' => ['bi-coin', 'admin.analytics.economy'],
        'ecology' => ['bi-tree', 'admin.analytics.ecology'],
        'players' => ['bi-people', 'admin.analytics.players'],
        'war' => ['bi-bullseye', 'admin.analytics.war'],
        'usage' => ['bi-wrench-adjustable', 'admin.analytics.usage'],
        'events' => ['bi-list-ul', 'admin.analytics.events'],
    ];
    $statusOk = ($st['state'] ?? '') === 'ok';
@endphp

@section('content')
<link rel="stylesheet" href="{{ asset('assets/css/palette.css') }}">
<style>
    .an-tabs .nav-link { padding: .35rem .75rem; font-size: .9rem; }
    .an-tabs .nav-link.active { background: var(--geo-geoventure); color: #0b1a10; }
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
    <ul class="nav nav-pills an-tabs flex-wrap gap-1 mb-3">
        @foreach ($tabs as $key => [$icon, $route])
            <li class="nav-item">
                <a class="nav-link {{ $page === $key ? 'active' : '' }}" href="{{ route($route, ['period' => $period]) }}">
                    <i class="bi {{ $icon }} me-1"></i>{{ __("analytics.tab.$key") }}
                </a>
            </li>
        @endforeach
    </ul>

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
<script src="{{ asset('assets/js/analytics.js') }}?v=2"></script>
@endsection
