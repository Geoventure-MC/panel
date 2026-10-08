@php $G = \App\Services\GameAnalytics::class; @endphp
<div class="row mb-2">
    @foreach ($kpi as $k)
        @php
            $unit = \App\Services\AnalyticsCatalog::get()->unit($k[0]);
            $sub = (is_string($k[2] ?? null) && $k[2] !== $unit && $k[2] !== '%' && $k[2] !== 'ms' && $k[2] !== 'min') ? $k[2] : null;
        @endphp
        <div class="col-6 col-md-4 col-xl-3 mb-3">
            <div class="card shadow-sm border-0 h-100 an-kpi" data-kpi="{{ $k[0] }}">
                <div class="card-body py-3">
                    <div class="text-muted small">{{ $G::metricLabel($k[0]) }}</div>
                    <div class="v">{{ $k[1] === null ? '—' : ($unit === '' ? $G::compact($k[1]) : $G::fmtMetric($k[0], $k[1], true)) }}@if ($sub && $k[1] !== null) <small class="text-muted fs-6">{{ $sub }}</small>@endif</div>
                </div>
            </div>
        </div>
    @endforeach
</div>
