@extends('layouts.admin')

@section('title', __('messages.stats.title'))

@section('page-title', __('messages.stats.header'))

@section('content')
<div class="row">
    <div class="col-md-6 col-xl-3 mb-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <h5 class="card-title text-muted mb-2">{{ __('messages.stats.total_launches') }}</h5>
                <h2 class="mb-0">{{ number_format($totalLaunches, 0, ',', ' ') }}</h2>
                <small class="text-muted">{{ __('messages.stats.last_30_days') }}</small>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3 mb-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <h5 class="card-title text-muted mb-2">{{ __('messages.stats.unique_players') }}</h5>
                <h2 class="mb-0">{{ number_format($uniquePlayers, 0, ',', ' ') }}</h2>
                <small class="text-muted">{{ __('messages.stats.last_30_days') }}</small>
            </div>
        </div>
    </div>
</div>

@if($totalLaunches === 0 && empty($versionData))
    <div class="alert alert-info">
        <i class="bi bi-info-circle me-2"></i> {{ __('messages.stats.no_data') }}
    </div>
@endif

<div class="row">
    <div class="col-12 mb-4">
        <div class="card shadow-sm border-0">
            <div class="card-header fw-semibold">
                <i class="bi bi-graph-up me-1"></i> {{ __('messages.stats.launches_per_day') }}
            </div>
            <div class="card-body">
                <canvas id="dailyChart" height="90"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-4 mb-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header fw-semibold">
                <i class="bi bi-hdd-network me-1"></i> {{ __('messages.stats.by_server') }}
            </div>
            <div class="card-body"><canvas id="serverChart" height="200"></canvas></div>
        </div>
    </div>
    <div class="col-lg-4 mb-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header fw-semibold">
                <i class="bi bi-tag me-1"></i> {{ __('messages.stats.by_version') }}
            </div>
            <div class="card-body"><canvas id="versionChart" height="200"></canvas></div>
        </div>
    </div>
    <div class="col-lg-4 mb-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header fw-semibold">
                <i class="bi bi-display me-1"></i> {{ __('messages.stats.by_os') }}
            </div>
            <div class="card-body"><canvas id="osChart" height="200"></canvas></div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    if (typeof Chart === 'undefined') return;
    var accent = '#FFA500';
    var palette = ['#4ade80', '#a78bfa', '#fb923c', '#60a5fa', '#f472b6', '#facc15', '#34d399', '#f87171'];

    new Chart(document.getElementById('dailyChart'), {
        type: 'line',
        data: {
            labels: @json($dailyLabels),
            datasets: [{
                label: @json(__('messages.stats.launches')),
                data: @json($dailyData),
                borderColor: accent,
                backgroundColor: 'rgba(255,165,0,0.1)',
                fill: true,
                tension: 0.3,
                pointRadius: 2,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            legend: { display: false },
            scales: { yAxes: [{ ticks: { beginAtZero: true, precision: 0 } }] },
        },
    });

    function doughnut(id, labels, data) {
        var el = document.getElementById(id);
        if (!el || !data.length) return;
        new Chart(el, {
            type: 'doughnut',
            data: { labels: labels, datasets: [{ data: data, backgroundColor: palette }] },
            options: { responsive: true, maintainAspectRatio: false, legend: { position: 'bottom' } },
        });
    }

    doughnut('serverChart', @json($serverLabels), @json($serverData));
    doughnut('versionChart', @json($versionLabels), @json($versionData));
    doughnut('osChart', @json($osLabels), @json($osData));
})();
</script>
@endsection
