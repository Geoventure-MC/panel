@extends('admin.analytics.layout')

@section('an')
@php $G = \App\Services\GameAnalytics::class; @endphp
@include('admin.analytics._kpi', ['kpi' => [['extracted_24h', $extracted24, null], ['extracted_total', $totalExtracted ?: null, null], ['wells', $wellsTotal ?: null, null], ['depleted_zones', $depletedCount, null]]])
<h6 class="mb-2">{{ __('analytics.oil_zones') }}</h6>
<div class="row" id="an-oil">
    @forelse ($cards as $z)
        @php $pct = $z['pct']; $col = $z['depleted'] ? '#f87171' : ($pct !== null && $pct < 20 ? '#fb923c' : ($pct !== null && $pct < 50 ? '#facc15' : '#4ade80')); @endphp
        <div class="col-12 col-md-6 col-xl-4 mb-3">
            <div class="card shadow-sm border-0 h-100 an-card" style="--c: {{ $col }}" data-oil-zone>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div>
                            <h6 class="mb-0">{{ $z['name'] }}</h6>
                            <small class="text-muted">{{ $z['world'] ?? __('analytics.world_unknown') }}</small>
                        </div>
                        @if ($z['depleted'])<span class="an-chip" style="background:rgba(248,113,113,.15);border-color:rgba(248,113,113,.4)">{{ __('analytics.depleted') }}</span>@endif
                    </div>
                    <div class="d-flex align-items-center gap-2 my-2">
                        <div class="an-bar flex-grow-1" style="height:.7rem" role="progressbar" aria-valuenow="{{ (int) ($pct ?? 0) }}" aria-valuemin="0" aria-valuemax="100"><i style="width: {{ max(0, min(100, $pct ?? 0)) }}%; background: {{ $col }}"></i></div>
                        <strong class="small">{{ $G::num($pct) }}&nbsp;%</strong>
                    </div>
                    <dl class="row small mb-0">
                        <dt class="col-6 fw-normal text-muted">{{ __('analytics.oil_reserve_now') }}</dt><dd class="col-6 text-end mb-1">{{ $G::fmtMetric('reserve', $z['reserve'], true) }}@if ($z['capacity']) <span class="text-muted">/ {{ $G::compact($z['capacity']) }}</span>@endif</dd>
                        <dt class="col-6 fw-normal text-muted">{{ __('analytics.oil_extraction') }}</dt><dd class="col-6 text-end mb-1">{{ $z['perDay'] === null ? '—' : $G::compact($z['perDay']) . "\u{202F}mB / " . __('analytics.day_short') }}</dd>
                        <dt class="col-6 fw-normal text-muted">{{ $G::metricLabel('wells') }}</dt><dd class="col-6 text-end mb-1">{{ $G::num($z['wells']) }}</dd>
                        <dt class="col-6 fw-normal text-muted">{{ $G::metricLabel('extracted_total') }}</dt><dd class="col-6 text-end mb-1">{{ $G::fmtMetric('extracted_total', $z['total'], true) }}</dd>
                        <dt class="col-6 fw-normal text-muted">{{ __('analytics.oil_eta') }}</dt>
                        <dd class="col-6 text-end mb-0" data-eta>
                            @if ($z['depleted']) {{ $z['depletedAt'] ? __('analytics.depleted_on', ['date' => $G::fmtDay($z['depletedAt'])]) : __('analytics.depleted') }}
                            @elseif ($z['eta'] !== null) {{ $z['eta'] > 730 ? __('analytics.eta_years', ['n' => $G::num(round($z['eta'] / 365, 1))]) : __('analytics.eta_days', ['n' => $G::num(max(1, round($z['eta'])))]) }}
                            @else — @endif
                        </dd>
                    </dl>
                </div>
            </div>
        </div>
    @empty
        <div class="col-12"><div class="card shadow-sm border-0"><div class="card-body text-center text-muted py-4">{{ __('analytics.no_zones') }}</div></div></div>
    @endforelse
</div>
@include('admin.analytics._charts', ['charts' => $charts])
@endsection
