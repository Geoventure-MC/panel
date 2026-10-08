@extends('layouts.admin')

@section('title', __('messages.monitor.title'))

@section('page-title', __('messages.monitor.header'))

@section('content')
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert" id="monitor-flash">
        <i class="bi bi-check-circle me-2"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert" id="monitor-flash">
        <i class="bi bi-exclamation-triangle me-2"></i> {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
@if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

@php
    $fmtPct = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format($v, 2, ',', ' '), '0'), ',') . ' %';
    $pctClass = fn ($v) => $v === null ? 'text-muted' : ($v >= 99 ? 'text-success' : ($v >= 95 ? 'text-warning' : 'text-danger'));
@endphp

<div class="alert alert-light border small py-2" id="monitor-cron">
    <i class="bi bi-clock me-1"></i> {{ __('messages.monitor.cron_hint') }}
</div>

<h5 class="mb-3"><i class="bi bi-hdd-network me-1"></i> {{ __('messages.monitor.current_state') }}</h5>
<div class="row" id="monitor-servers">
    @forelse($servers as $srv)
        @php $last = $srv['last']; $on = $last && $last->online; @endphp
        <div class="col-12 mb-4" data-server="{{ $srv['key'] }}">
            <div class="card shadow-sm border-0">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span class="fw-semibold">{{ $srv['name'] }} <small class="text-muted">({{ $srv['key'] }})</small></span>
                    @if(! $last)
                        <span class="badge bg-secondary">{{ __('messages.monitor.unknown') }}</span>
                    @elseif($on)
                        <span class="badge bg-success">{{ __('messages.monitor.online') }}</span>
                    @else
                        <span class="badge bg-danger">{{ __('messages.monitor.offline') }}</span>
                    @endif
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-6 col-md-3"><div class="text-muted small">{{ __('messages.monitor.players') }}</div>
                            <div class="fs-5">{{ $last && $last->players !== null ? $last->players . '/' . ($last->max_players ?? '?') : '—' }}</div></div>
                        <div class="col-6 col-md-3"><div class="text-muted small">{{ __('messages.monitor.latency') }}</div>
                            <div class="fs-5">{{ $last && $last->latency !== null ? $last->latency . ' ms' : '—' }}</div></div>
                        <div class="col-6 col-md-3"><div class="text-muted small">{{ __('messages.monitor.version') }}</div>
                            <div>{{ $last->version ?? '—' }}</div></div>
                        <div class="col-6 col-md-3"><div class="text-muted small">{{ __('messages.monitor.last_check') }}</div>
                            <div>{{ $last ? $last->created_at->diffForHumans() : __('messages.monitor.never') }}</div>
                            @if($last && ! $srv['fresh'])<div class="small text-warning">{{ __('messages.monitor.stale') }}</div>@endif</div>
                    </div>
                    <div class="row g-3 mb-3" data-uptime>
                        @foreach(['h24' => 'uptime_24h', 'd7' => 'uptime_7d', 'd30' => 'uptime_30d'] as $k => $label)
                            <div class="col-4">
                                <div class="text-muted small">{{ __('messages.monitor.uptime') }} {{ __('messages.monitor.' . $label) }}</div>
                                <div class="fs-4 {{ $pctClass($srv['uptime'][$k]) }}" data-uptime-{{ $k }}>{{ $fmtPct($srv['uptime'][$k]) }}</div>
                            </div>
                        @endforeach
                    </div>
                    <div class="small text-muted mb-1">{{ __('messages.monitor.chart') }}</div>
                    @if(count($srv['series']['labels']))
                        <div style="position:relative;height:220px"><canvas class="monitor-chart" id="chart-{{ $loop->index }}"></canvas></div>
                    @else
                        <div class="text-muted small">{{ __('messages.monitor.no_data') }}</div>
                    @endif
                </div>
            </div>
        </div>
    @empty
        <div class="col-12 mb-4 text-muted">{{ __('messages.monitor.no_data') }}</div>
    @endforelse
</div>

<div class="row">
    <div class="col-lg-7 mb-4">
        <div class="card shadow-sm border-0">
            <div class="card-header fw-semibold"><i class="bi bi-exclamation-octagon me-1"></i> {{ __('messages.monitor.incidents') }}</div>
            <div class="card-body p-0">
                @if($incidents->isEmpty())
                    <div class="p-3 text-muted" id="monitor-no-incidents">{{ __('messages.monitor.no_incidents') }}</div>
                @else
                    <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle" id="monitor-incidents">
                        <thead><tr>
                            <th>{{ __('messages.monitor.col_server') }}</th><th>{{ __('messages.monitor.col_type') }}</th>
                            <th>{{ __('messages.monitor.col_started') }}</th><th>{{ __('messages.monitor.col_duration') }}</th>
                            <th>{{ __('messages.monitor.col_status') }}</th>
                        </tr></thead>
                        <tbody>
                        @foreach($incidents as $i)
                            <tr data-type="{{ $i->type }}">
                                <td>{{ $i->server_name ?: $i->server_key }}</td>
                                <td>{{ __('messages.monitor.type_' . $i->type) }}</td>
                                <td>{{ $i->started_at->format('d/m/Y H:i') }}</td>
                                <td>{{ \App\Services\ServerMonitor::formatDuration($i->durationMinutes()) }}</td>
                                <td>@if($i->resolved_at)<span class="badge bg-success">{{ __('messages.monitor.resolved') }}</span>@else<span class="badge bg-danger">{{ __('messages.monitor.ongoing') }}</span>@endif</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-5 mb-4">
        <div class="card shadow-sm border-0">
            <div class="card-header fw-semibold"><i class="bi bi-sliders me-1"></i> {{ __('messages.monitor.settings') }}</div>
            <div class="card-body">
                <form action="{{ route('admin.monitor.update') }}" method="POST" id="monitor-settings">
                    @csrf @method('PUT')
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="enabled" value="1" id="mon-enabled" {{ old('enabled', $settings->enabled) ? 'checked' : '' }}>
                        <label class="form-check-label" for="mon-enabled">{{ __('messages.monitor.enabled') }}</label>
                    </div>
                    @foreach([
                        ['offline_after_minutes', 'offline_after', 1, 120],
                        ['latency_threshold_ms', 'latency_threshold', 100, 10000],
                        ['latency_after_minutes', 'latency_after', 1, 120],
                        ['empty_after_minutes', 'empty_after', 0, 1440],
                        ['reminder_minutes', 'reminder', 5, 1440],
                    ] as [$field, $label, $min, $max])
                        <div class="mb-3">
                            <label class="form-label" for="mon-{{ $field }}">{{ __('messages.monitor.' . $label) }}</label>
                            <input type="number" class="form-control" id="mon-{{ $field }}" name="{{ $field }}" min="{{ $min }}" max="{{ $max }}" value="{{ old($field, $settings->$field) }}" required>
                        </div>
                    @endforeach
                    <div class="mb-3">
                        <label class="form-label" for="mon-webhook_url">{{ __('messages.monitor.webhook') }}</label>
                        <input type="url" class="form-control" id="mon-webhook_url" name="webhook_url" maxlength="500" value="{{ old('webhook_url', $settings->webhook_url) }}" placeholder="https://discord.com/api/webhooks/…">
                        <div class="form-text">{{ __('messages.monitor.webhook_hint') }}</div>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> {{ __('messages.monitor.save') }}</button>
                </form>
                <hr>
                <form action="{{ route('admin.monitor.test') }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-outline-secondary" id="monitor-test"><i class="bi bi-send me-1"></i> {{ __('messages.monitor.send_test') }}</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    if (typeof Chart === 'undefined') return;
    var series = @json(array_map(fn ($s) => $s['series'], $servers));
    var L = {players: @json(__('messages.monitor.chart_players')), latency: @json(__('messages.monitor.chart_latency'))};
    document.querySelectorAll('canvas.monitor-chart').forEach(function (cv, i) {
        var d = series[i]; if (!d) return;
        new Chart(cv, {
            type: 'line',
            data: {
                labels: d.labels,
                datasets: [
                    {label: L.players, yAxisID: 'p', data: d.players, borderColor: '#4ade80', backgroundColor: 'rgba(74,222,128,0.12)', fill: true, tension: 0.3, pointRadius: 0, spanGaps: true},
                    {label: L.latency, yAxisID: 'l', data: d.latency, borderColor: '#fb923c', backgroundColor: 'transparent', fill: false, tension: 0.3, pointRadius: 0, spanGaps: true},
                ],
            },
            options: {
                responsive: true, maintainAspectRatio: false, legend: {position: 'bottom'},
                scales: {
                    xAxes: [{ticks: {maxTicksLimit: 12}}],
                    yAxes: [
                        {id: 'p', position: 'left', ticks: {beginAtZero: true, precision: 0}},
                        {id: 'l', position: 'right', ticks: {beginAtZero: true}, gridLines: {drawOnChartArea: false}},
                    ],
                },
            },
        });
    });
})();
</script>
@endsection
