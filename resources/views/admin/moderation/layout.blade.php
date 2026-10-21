@extends('layouts.admin')

@section('title', __('moderation.title'))

@section('page-title', __('moderation.title'))

@php
    $tabs = ['overview' => ['bi-speedometer2', 'admin.moderation.index'], 'sanctions' => ['bi-hammer', 'admin.moderation.sanctions'], 'reports' => ['bi-flag', 'admin.moderation.reports']];
    $statusOk = ($st['state'] ?? '') === 'ok';
@endphp

@section('content')
<style>
    .mod-table td, .mod-table th { vertical-align: middle; }
    .mod-table td.num, .mod-table th.num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .mod-table td.text-break { overflow-wrap: anywhere; }
    .mod-kpi { border-left: 3px solid var(--mod-c, #fb923c); }
    .mod-kpi .v { font-size: 1.6rem; font-weight: 600; line-height: 1.1; }
    .mod-chip { display: inline-block; padding: .05rem .5rem; border-radius: 999px; font-size: .75rem; border: 1px solid transparent; white-space: nowrap; }
    .mod-chip.t-warn { background: rgba(250,204,21,.15); border-color: rgba(250,204,21,.45); }
    .mod-chip.t-mute { background: rgba(96,165,250,.15); border-color: rgba(96,165,250,.45); }
    .mod-chip.t-kick { background: rgba(167,139,250,.15); border-color: rgba(167,139,250,.45); }
    .mod-chip.t-ban { background: rgba(248,113,113,.15); border-color: rgba(248,113,113,.45); }
    .mod-chip.s-active, .mod-chip.r-open { background: rgba(248,113,113,.12); border-color: rgba(248,113,113,.4); }
    .mod-chip.s-expired, .mod-chip.r-closed { background: rgba(127,127,127,.15); border-color: rgba(127,127,127,.35); }
    .mod-chip.s-revoked { background: rgba(74,222,128,.15); border-color: rgba(74,222,128,.4); }
    .mod-chip.r-taken { background: rgba(251,146,60,.15); border-color: rgba(251,146,60,.45); }
    .mod-chart { position: relative; height: 240px; }
    .mod-empty { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; opacity: .6; font-size: .9rem; }
    .mod-context { white-space: pre-wrap; overflow-wrap: anywhere; font-family: ui-monospace, monospace; font-size: .8rem; max-height: 22rem; overflow: auto; }
    @media (max-width: 575px) { .mod-kpi .v { font-size: 1.25rem; } }
</style>

<div id="mod-root" data-locale="{{ app()->getLocale() }}">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <ul class="nav nav-pills gap-1">
            @foreach ($tabs as $key => [$icon, $route])
                <li class="nav-item"><a class="nav-link {{ $page === $key || ($key === 'sanctions' && $page === 'player') ? 'active' : '' }}" href="{{ route($route) }}"><i class="bi {{ $icon }} me-1"></i>{{ __("moderation.tab.$key") }}</a></li>
            @endforeach
        </ul>
        @if ($statusOk)
            <form method="get" action="{{ route('admin.moderation.search') }}" class="d-flex gap-2" id="mod-search">
                <input type="text" name="q" class="form-control form-control-sm" maxlength="64" placeholder="{{ __('moderation.search_ph') }}" aria-label="{{ __('moderation.search') }}">
                <button class="btn btn-sm btn-primary"><i class="bi bi-search"></i></button>
            </form>
        @endif
    </div>
    @if ($statusOk)
        <p class="small text-muted mb-3"><i class="bi bi-lock me-1"></i>{{ __('moderation.readonly') }} {{ __('moderation.privacy') }}</p>
    @endif
    @yield('mod')
</div>

<script>window.MODL = @json(__('moderation.js'));</script>
<script src="{{ asset('assets/vendor/chart.umd.js') }}"></script>
<script src="{{ asset('assets/js/moderation.js') }}?v=1"></script>
@endsection
