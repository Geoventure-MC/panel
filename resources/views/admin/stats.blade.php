@extends('layouts.admin')

@section('title', __('messages.stats.title'))

@section('page-title', __('messages.stats.header'))

@section('content')
{{-- Palette Geoventure : mêmes couleurs que le launcher, le site et le mod. --}}
<style>
    .geo-stat { border-left: 3px solid var(--geo-accent, var(--geo-geoventure)); }
    .geo-trend-up { color: var(--geo-geoventure); }
    .geo-trend-down { color: var(--geo-danger-soft); }
    .geo-heat { display: grid; grid-template-columns: 2.5rem repeat(24, 1fr); gap: 2px; }
    .geo-heat-cell { aspect-ratio: 1; border-radius: 2px; background: rgba(74, 222, 128, .08); }
    .geo-heat-label { font-size: .7rem; opacity: .6; display: flex; align-items: center; }
    .geo-heat-hours { font-size: .6rem; opacity: .5; text-align: center; }
</style>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div class="btn-group btn-group-sm">
        @foreach ($ranges as $range)
            <a href="{{ route('admin.stats', ['days' => $range]) }}"
               class="btn btn-{{ $days === $range ? 'primary' : 'outline-secondary' }}">
                {{ __('messages.stats.days', ['days' => $range]) }}
            </a>
        @endforeach
    </div>
    <a href="{{ route('admin.stats.export', ['days' => $days]) }}" class="btn btn-sm btn-outline-success">
        <i class="bi bi-download me-1"></i> {{ __('messages.stats.export_csv') }}
    </a>
</div>

@php
    $trend = function (?int $value) {
        if ($value === null) return '';
        $cls = $value >= 0 ? 'geo-trend-up' : 'geo-trend-down';
        $arrow = $value >= 0 ? '▲' : '▼';
        return '<span class="'.$cls.' small ms-2">'.$arrow.' '.abs($value).' %</span>';
    };
@endphp

<div class="row">
    <div class="col-md-6 col-xl-3 mb-4">
        <div class="card shadow-sm border-0 h-100 geo-stat">
            <div class="card-body">
                <h5 class="card-title text-muted mb-2">{{ __('messages.stats.total_launches') }}</h5>
                <h2 class="mb-0">
                    {{ number_format($totalLaunches, 0, ',', ' ') }}{!! $trend($launchesTrend) !!}
                </h2>
                <small class="text-muted">{{ __('messages.stats.vs_previous') }}</small>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3 mb-4">
        <div class="card shadow-sm border-0 h-100 geo-stat">
            <div class="card-body">
                <h5 class="card-title text-muted mb-2">{{ __('messages.stats.unique_players') }}</h5>
                <h2 class="mb-0">
                    {{ number_format($uniquePlayers, 0, ',', ' ') }}{!! $trend($playersTrend) !!}
                </h2>
                <small class="text-muted">{{ __('messages.stats.launches_per_user', ['n' => $launchesPerUser]) }}</small>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3 mb-4">
        <div class="card shadow-sm border-0 h-100 geo-stat">
            <div class="card-body">
                <h5 class="card-title text-muted mb-2">{{ __('messages.stats.retention') }}</h5>
                <h2 class="mb-0">{{ $retentionRate }} %</h2>
                <small class="text-muted">
                    {{ __('messages.stats.new_vs_returning', ['new' => $newPlayers, 'back' => $returningPlayers]) }}
                </small>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3 mb-4">
        <div class="card shadow-sm border-0 h-100 geo-stat">
            <div class="card-body">
                <h5 class="card-title text-muted mb-2">{{ __('messages.stats.reliability') }}</h5>
                <h2 class="mb-0">
                    {{ $uptime === null ? '—' : $uptime.' %' }}
                </h2>
                <small class="text-muted">
                    {{ __('messages.stats.error_rate', ['rate' => $errorRate, 'count' => $errorCount]) }}
                    @if ($latency !== null) · {{ $latency }} ms @endif
                </small>
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

@if (!empty($playerLabels))
<div class="row">
    <div class="col-12 mb-4">
        <div class="card shadow-sm border-0">
            <div class="card-header fw-semibold d-flex justify-content-between">
                <span><i class="bi bi-people me-1"></i> {{ __('messages.stats.players_online') }}</span>
                <span class="text-muted small">{{ __('messages.stats.peak', ['n' => $peakPlayers]) }}</span>
            </div>
            <div class="card-body">
                <canvas id="playersChart" height="90"></canvas>
            </div>
        </div>
    </div>
</div>
@endif

@if ($heatmapMax > 0)
<div class="row">
    <div class="col-12 mb-4">
        <div class="card shadow-sm border-0">
            <div class="card-header fw-semibold">
                <i class="bi bi-calendar3 me-1"></i> {{ __('messages.stats.heatmap') }}
            </div>
            <div class="card-body">
                <p class="text-muted small">{{ __('messages.stats.heatmap_hint') }}</p>
                <div class="geo-heat mb-1">
                    <span></span>
                    @for ($h = 0; $h < 24; $h++)
                        <span class="geo-heat-hours">{{ $h % 3 === 0 ? $h : '' }}</span>
                    @endfor
                </div>
                @php $dayNames = [__('messages.stats.mon'), __('messages.stats.tue'), __('messages.stats.wed'), __('messages.stats.thu'), __('messages.stats.fri'), __('messages.stats.sat'), __('messages.stats.sun')]; @endphp
                @foreach ($heatmap as $dayIndex => $hours)
                    <div class="geo-heat mb-1">
                        <span class="geo-heat-label">{{ $dayNames[$dayIndex] }}</span>
                        @foreach ($hours as $hour => $count)
                            @php $intensity = $heatmapMax > 0 ? $count / $heatmapMax : 0; @endphp
                            <span class="geo-heat-cell"
                                  style="background: rgba(74, 222, 128, {{ max(0.06, $intensity) }})"
                                  title="{{ $dayNames[$dayIndex] }} {{ $hour }}h — {{ $count }}"></span>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endif

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
            <div class="card-header fw-semibold d-flex justify-content-between">
                <span><i class="bi bi-tag me-1"></i> {{ __('messages.stats.by_version') }}</span>
                @if ($topVersion)
                    <span class="text-muted small">{{ $topVersion }} · {{ $topVersionShare }} %</span>
                @endif
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

@if (!empty($eventLabels))
<div class="row">
    <div class="col-12 mb-4">
        <div class="card shadow-sm border-0">
            <div class="card-header fw-semibold">
                <i class="bi bi-activity me-1"></i> {{ __('messages.stats.by_event') }}
            </div>
            <div class="card-body"><canvas id="eventChart" height="70"></canvas></div>
        </div>
    </div>
</div>
@endif
@endsection

@section('scripts')
<script>
(function () {
    if (typeof Chart === 'undefined') return;
    var accent = '#4ade80';
    var palette = ['#4ade80', '#a78bfa', '#fb923c', '#60a5fa', '#f472b6', '#facc15', '#34d399', '#f87171'];

    new Chart(document.getElementById('dailyChart'), {
        type: 'line',
        data: {
            labels: @json($dailyLabels),
            datasets: [{
                label: @json(__('messages.stats.launches')),
                data: @json($dailyData),
                borderColor: accent,
                backgroundColor: 'rgba(74,222,128,0.12)',
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

    var playersEl = document.getElementById('playersChart');
    if (playersEl) {
        new Chart(playersEl, {
            type: 'line',
            data: {
                labels: @json($playerLabels),
                datasets: [
                    {
                        label: @json(__('messages.stats.average')),
                        data: @json($playerAvg),
                        borderColor: '#60a5fa',
                        backgroundColor: 'rgba(96,165,250,0.12)',
                        fill: true, tension: 0.3, pointRadius: 0,
                    },
                    {
                        label: @json(__('messages.stats.peak_label')),
                        data: @json($playerPeak),
                        borderColor: '#a78bfa',
                        backgroundColor: 'transparent',
                        fill: false, tension: 0.3, pointRadius: 0, borderDash: [4, 3],
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                legend: { position: 'bottom' },
                scales: { yAxes: [{ ticks: { beginAtZero: true, precision: 0 } }] },
            },
        });
    }

    var eventEl = document.getElementById('eventChart');
    if (eventEl) {
        new Chart(eventEl, {
            type: 'bar',
            data: {
                labels: @json($eventLabels),
                datasets: [{ data: @json($eventData), backgroundColor: palette }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                legend: { display: false },
                scales: { yAxes: [{ ticks: { beginAtZero: true, precision: 0 } }] },
            },
        });
    }

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
