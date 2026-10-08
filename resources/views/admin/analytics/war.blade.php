@extends('admin.analytics.layout')

@section('an')
@php $G = \App\Services\GameAnalytics::class; @endphp
@include('admin.analytics._kpi', ['kpi' => array_map(fn ($k) => [['launched'=>'missiles_launched','refused'=>'missiles_refused','intercepted'=>'missiles_intercepted','rate'=>'interception_rate','impacts'=>'missiles_impact','blocks'=>'blocks_destroyed','native'=>'missiles_native','sieges'=>'sieges','treaties'=>'treaties'][$k[0]], $k[1], $k[2] ?? null], $kpi)])
@if ($capped)<p class="small text-warning">{{ __('analytics.capped') }}</p>@endif

<div class="row">@include('admin.analytics._chart', $charts[0])</div>
<div class="row">
    @foreach (array_slice($charts, 1, 3) as $ch)
        @include('admin.analytics._chart', array_merge($ch, ['w' => 4]))
    @endforeach
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between flex-wrap gap-2 mb-2">
            <h6 class="card-title mb-0">{{ __('analytics.trajectories') }}</h6>
            <select id="an-map-world" class="form-select form-select-sm w-auto">
                <option value="">{{ __('analytics.all_worlds') }}</option>
                @foreach ($worlds as $w)<option value="{{ $w }}">{{ $w }}</option>@endforeach
            </select>
        </div>
        <svg id="an-map" viewBox="0 0 800 420" preserveAspectRatio="xMidYMid meet" role="img" aria-label="{{ __('analytics.trajectories') }}"></svg>
        <div class="small text-muted mt-1" id="an-map-legend"></div>
        <div id="an-map-empty" class="text-center text-muted py-2 d-none">{{ __('analytics.map_empty') }}</div>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4" id="an-missile-detail" hidden>
    <div class="card-body">
        <div class="d-flex justify-content-between"><h6 class="card-title" id="an-md-title"></h6><button type="button" class="btn-close" id="an-md-close"></button></div>
        <dl class="row small mb-2" id="an-md-facts"></dl>
        <h6 class="small">{{ __('analytics.related') }}</h6>
        <ul class="small" id="an-md-related"></ul>
        <details><summary class="small">JSON</summary><pre class="an-mono mb-0" id="an-md-raw"></pre></details>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <h6 class="card-title">{{ __('analytics.recent_launches') }}</h6>
        <div class="table-responsive">
            <table class="table table-sm an-table" id="an-recent">
                <thead><tr><th>{{ __('analytics.date') }}</th><th>{{ __('analytics.country') }}</th><th>{{ __('analytics.tier_h') }}</th><th>{{ __('analytics.warhead') }}</th><th>{{ __('analytics.target') }}</th><th class="text-end">{{ __('analytics.distance') }}</th></tr></thead>
                <tbody>
                @forelse ($recent as $m)
                    <tr class="an-click" data-missile="{{ $m['id'] }}">
                        <td class="text-nowrap">{{ $G::fmtDate($m['ts']) }}</td><td>{{ $m['ref'] ?: '—' }}</td>
                        <td><span class="an-chip">T{{ $m['tier'] }}</span></td><td>{{ $m['warhead'] ?: '—' }}</td>
                        <td>{{ $m['target_faction'] ?: '—' }}</td><td class="text-end">{{ $G::num($m['distance']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-3">{{ __('analytics.no_launches') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="row">
    @foreach (array_slice($charts, 4, 1) as $ch)
        @include('admin.analytics._chart', $ch)
    @endforeach
    <div class="col-12 col-xl-6 mb-4">
        <div class="card shadow-sm border-0 h-100"><div class="card-body">
            <h6 class="card-title">{{ __('analytics.refusals') }}</h6>
            <table class="table table-sm an-table" id="an-reasons"><tbody>
                @forelse ($reasons as $k => $n)<tr><td>{{ $k }}</td><td class="text-end">{{ $n }}</td></tr>@empty<tr><td class="text-muted text-center">{{ __('analytics.none') }}</td></tr>@endforelse
            </tbody></table>
            <h6 class="card-title mt-3">{{ __('analytics.intercepted_by') }}</h6>
            <table class="table table-sm an-table" id="an-by"><tbody>
                @forelse ($by as $k => $n)<tr><td>{{ $k }}</td><td class="text-end">{{ $n }}</td></tr>@empty<tr><td class="text-muted text-center">{{ __('analytics.none') }}</td></tr>@endforelse
            </tbody></table>
        </div></div>
    </div>
</div>

<div class="row">
    <div class="col-12 col-xl-6 mb-4">
        <div class="card shadow-sm border-0 h-100"><div class="card-body">
            <h6 class="card-title">{{ __('analytics.offensive') }}</h6>
            <table class="table table-sm an-table" id="an-offense">
                <thead><tr><th>{{ __('analytics.country') }}</th><th class="text-end">{{ __('analytics.launched') }}</th><th class="text-end">{{ __('analytics.refused') }}</th></tr></thead>
                <tbody>@forelse ($offense as $r)<tr><td>{{ $r['name'] }}</td><td class="text-end">{{ $r['launched'] }}</td><td class="text-end">{{ $r['refused'] }}</td></tr>@empty<tr><td colspan="3" class="text-center text-muted">{{ __('analytics.none') }}</td></tr>@endforelse</tbody>
            </table>
        </div></div>
    </div>
    <div class="col-12 col-xl-6 mb-4">
        <div class="card shadow-sm border-0 h-100"><div class="card-body">
            <h6 class="card-title">{{ __('analytics.defensive') }}</h6>
            <table class="table table-sm an-table" id="an-defense">
                <thead><tr><th>{{ __('analytics.country') }}</th><th class="text-end">{{ __('analytics.targeted') }}</th><th class="text-end">{{ __('analytics.intercepted') }}</th><th class="text-end">{{ __('analytics.hit') }}</th><th class="text-end">%</th></tr></thead>
                <tbody>@forelse ($defense as $r)<tr><td>{{ $r['name'] }}</td><td class="text-end">{{ $r['targeted'] }}</td><td class="text-end">{{ $r['intercepted'] }}</td><td class="text-end">{{ $r['hit'] }}</td><td class="text-end">{{ $G::num($r['rate']) }}</td></tr>@empty<tr><td colspan="5" class="text-center text-muted">{{ __('analytics.none') }}</td></tr>@endforelse</tbody>
            </table>
        </div></div>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <h6 class="card-title">{{ __('analytics.counters24') }}</h6>
        <div class="table-responsive">
            <table class="table table-sm an-table" id="an-missile-metrics">
                @php $cols = ['launched_24h','tier1_24h','tier2_24h','tier3_24h','tier4_24h','tier5_24h','intercepted_24h','hit_24h','refused_24h','interception_rate_pct','blocks_destroyed_24h','defense_radars','defense_interceptors','defense_ciws']; @endphp
                <thead><tr><th>{{ __('analytics.country') }}</th>@foreach ($cols as $m)<th class="text-end">{{ $G::metricLabel($m) }}</th>@endforeach</tr></thead>
                <tbody>@forelse ($metricRows as $r)<tr><td>{{ $r['name'] }}</td>@foreach ($cols as $m)<td class="text-end">{{ $G::num($r[$m]) }}</td>@endforeach</tr>@empty<tr><td colspan="15" class="text-center text-muted">{{ __('analytics.none') }}</td></tr>@endforelse</tbody>
            </table>
        </div>
    </div>
</div>

<div class="row">
    @include('admin.analytics._chart', array_merge($charts[5], ['w' => 6]))
    <div class="col-12 col-xl-6 mb-4">
        <div class="card shadow-sm border-0 h-100"><div class="card-body">
            <h6 class="card-title">{{ __('analytics.kd_table') }}</h6>
            <div class="table-responsive">
                <table class="table table-sm an-table" id="an-kd">
                    @php $kc = ['kills_24h','deaths_24h','kd_ratio','sieges_active','assault_gauge_peak','annexed_claims','reparations_paid']; @endphp
                    <thead><tr><th>{{ __('analytics.country') }}</th>@foreach ($kc as $m)<th class="text-end">{{ $G::metricLabel($m) }}</th>@endforeach</tr></thead>
                    <tbody>@forelse ($kdRows as $r)<tr><td>{{ $r['name'] }}</td>@foreach ($kc as $m)<td class="text-end">{{ $G::num($r[$m]) }}</td>@endforeach</tr>@empty<tr><td colspan="8" class="text-center text-muted">{{ __('analytics.none') }}</td></tr>@endforelse</tbody>
                </table>
            </div>
        </div></div>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <h6 class="card-title">{{ __('analytics.sieges_treaties') }}</h6>
        @include('admin.analytics._events', ['events' => $warEvents])
    </div>
</div>
@endsection
