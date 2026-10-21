@extends('admin.moderation.layout')

@section('mod')
@if (! $p['found'])
    <div class="card shadow-sm border-0" id="mod-player-unknown"><div class="card-body">
        <p class="mb-0"><i class="bi bi-person-x me-2"></i>{{ __('moderation.player_unknown', ['name' => $key]) }}</p>
    </div></div>
@else
@php $G = \App\Services\GameAnalytics::class; @endphp
<div class="card shadow-sm border-0 mb-4" id="mod-player"><div class="card-body">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <h4 class="mb-2"><i class="bi bi-person me-2"></i><span id="mod-player-name">{{ $p['name'] }}</span></h4>
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.moderation.sanctions', ['player' => $p['name']]) }}">{{ __('moderation.all_sanctions') }}</a>
    </div>
    <div class="d-flex flex-wrap gap-2 mb-2" id="mod-player-counts">
        @foreach (\App\Services\GameModeration::TYPES as $t)
            <span class="mod-chip t-{{ $t }}" data-count="{{ $t }}">{{ __("moderation.type.$t") }} : {{ $p['counts'][$t] ?? 0 }}</span>
        @endforeach
        <span class="mod-chip r-taken">{{ __('moderation.reports_in', ['n' => $p['reports_in_total']]) }}</span>
        <span class="mod-chip r-closed">{{ __('moderation.reports_out', ['n' => $p['reports_out_total']]) }}</span>
    </div>
</div></div>

<div class="card shadow-sm border-0 mb-4"><div class="card-body">
    <h6 class="card-title">{{ __('moderation.active_now') }}</h6>
    @include('admin.moderation._sanctions_table', ['list' => $p['active'], 'tid' => 'mod-player-active'])
</div></div>

<div class="card shadow-sm border-0 mb-4"><div class="card-body">
    <h6 class="card-title">{{ __('moderation.sheet_counts') }}</h6>
    @include('admin.moderation._sanctions_table', ['list' => $p['sanctions'], 'tid' => 'mod-player-history'])
</div></div>

<div class="row">
    <div class="col-12 col-xl-6 mb-4"><div class="card shadow-sm border-0 h-100"><div class="card-body">
        <h6 class="card-title">{{ __('moderation.reports_in', ['n' => $p['reports_in_total']]) }}</h6>
        @include('admin.moderation._reports_table', ['list' => $p['reports_in'], 'tid' => 'mod-player-reports-in'])
    </div></div></div>
    <div class="col-12 col-xl-6 mb-4"><div class="card shadow-sm border-0 h-100"><div class="card-body">
        <h6 class="card-title">{{ __('moderation.reports_out', ['n' => $p['reports_out_total']]) }}</h6>
        @include('admin.moderation._reports_table', ['list' => $p['reports_out'], 'tid' => 'mod-player-reports-out'])
    </div></div></div>
</div>

@if ($isSuper)
<div class="card shadow-sm border-0 mb-4" id="mod-linked"><div class="card-body">
    <h6 class="card-title"><i class="bi bi-diagram-3 me-1"></i>{{ __('moderation.linked') }}</h6>
    <p class="small text-muted">{{ __('moderation.linked_note') }}</p>
    @if (! ($st['ips'] ?? false))
        <p class="text-muted mb-0">{{ __('moderation.linked_unavailable') }}</p>
    @elseif (empty($linked))
        <p class="text-muted mb-0">{{ __('moderation.linked_none') }}</p>
    @else
        <ul class="list-unstyled mb-0">
            @foreach ($linked as $l)
                <li data-linked>
                    @if (\App\Services\GameModeration::validName($l['label']) && ! str_starts_with($l['label'], '#'))
                        <a href="{{ route('admin.moderation.player', ['player' => $l['label']]) }}">{{ $l['label'] }}</a>
                    @else
                        {{ $l['label'] }}
                    @endif
                    <span class="small text-muted">— {{ __('moderation.shared', ['n' => $l['shared']]) }}</span>
                    @if ($l['banned'])<span class="mod-chip t-ban">{{ __('moderation.banned') }}</span>@endif
                </li>
            @endforeach
        </ul>
    @endif
</div></div>
@endif
@endif
@endsection
