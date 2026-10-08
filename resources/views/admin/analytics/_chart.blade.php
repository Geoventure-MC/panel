<div class="col-12 col-xl-{{ $w ?? 6 }} mb-4">
    <div class="card shadow-sm border-0 h-100">
        <div class="card-body">
            <h6 class="card-title">{{ $title }}</h6>
            @if (!empty($hint))<small class="text-muted d-block mb-2">{{ $hint }}</small>@endif
            <div class="an-chart-wrap" style="height: {{ $h ?? 240 }}px">
                <canvas data-an-chart="{{ json_encode($spec, JSON_UNESCAPED_UNICODE) }}"></canvas>
                <div class="an-empty d-none">{{ __('analytics.no_points') }}</div>
            </div>
        </div>
    </div>
</div>
