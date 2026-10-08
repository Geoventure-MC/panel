<div class="row mb-2">
    @foreach ($kpi as $k)
        <div class="col-6 col-md-4 col-xl-3 mb-3">
            <div class="card shadow-sm border-0 h-100 an-kpi" data-kpi="{{ $k[0] }}">
                <div class="card-body py-3">
                    <div class="text-muted small">{{ \App\Services\GameAnalytics::metricLabel($k[0]) }}</div>
                    <div class="v">{{ \App\Services\GameAnalytics::num($k[1]) }}@if (($k[2] ?? null) && $k[1] !== null) <small class="text-muted fs-6">{{ $k[2] }}</small>@endif</div>
                </div>
            </div>
        </div>
    @endforeach
</div>
