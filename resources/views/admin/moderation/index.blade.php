@extends('admin.moderation.layout')

@section('mod')
@php
    $G = \App\Services\GameAnalytics::class;
    $kpis = [
        ['active_bans', $G::num($stats['active_bans']), '#f87171'],
        ['active_mutes', $G::num($stats['active_mutes']), '#60a5fa'],
        ['s24', $G::num($stats['s24']), '#fb923c'],
        ['s7', $G::num($stats['s7']), '#fb923c'],
        ['reports_open', $G::num($stats['reports_open']), '#f87171'],
        ['reports_taken', $G::num($stats['reports_taken']), '#facc15'],
        ['avg_handle', \App\Services\GameModeration::duration($stats['avg_handle_s']), '#4ade80'],
    ];
@endphp
<div class="row g-3 mb-4">
    @foreach ($kpis as [$k, $v, $c])
        <div class="col-6 col-md-4 col-xl">
            <div class="card shadow-sm border-0 mod-kpi h-100" style="--mod-c: {{ $c }}" data-kpi="{{ $k }}">
                <div class="card-body py-2"><div class="small text-muted">{{ __("moderation.kpi.$k") }}</div><div class="v">{{ $v }}</div></div>
            </div>
        </div>
    @endforeach
</div>

<div class="row">
    <div class="col-12 col-xl-8 mb-4">
        <div class="card shadow-sm border-0 h-100"><div class="card-body">
            <h6 class="card-title">{{ __('moderation.chart_day') }}</h6>
            <div class="mod-chart"><canvas data-mod-chart="{{ json_encode(['type' => 'bar', 'data' => $chartDay], JSON_UNESCAPED_UNICODE) }}"></canvas><div class="mod-empty d-none">{{ __('moderation.js.no_points') }}</div></div>
        </div></div>
    </div>
    <div class="col-12 col-xl-4 mb-4">
        <div class="card shadow-sm border-0 h-100"><div class="card-body">
            <h6 class="card-title">{{ __('moderation.chart_type') }}</h6>
            <div class="mod-chart"><canvas data-mod-chart="{{ json_encode(['type' => 'doughnut', 'data' => $chartType], JSON_UNESCAPED_UNICODE) }}"></canvas><div class="mod-empty d-none">{{ __('moderation.js.no_points') }}</div></div>
        </div></div>
    </div>
</div>

<div class="row">
    <div class="col-12 col-xl-6 mb-4">
        <div class="card shadow-sm border-0 h-100"><div class="card-body">
            <h6 class="card-title">{{ __('moderation.top_players') }}</h6>
            <div class="table-responsive"><table class="table table-sm mod-table" id="mod-top">
                <tbody>
                @forelse ($stats['top_players'] as $t)
                    <tr>
                        <td>@if ($t['name'] !== '' && \App\Services\GameModeration::validName($t['name']))<a href="{{ route('admin.moderation.player', ['player' => $t['name']]) }}">{{ $t['label'] }}</a>@else{{ $t['label'] }}@endif</td>
                        <td class="num">{{ __('moderation.sanctions_count', ['n' => $t['n']]) }}</td>
                        <td class="num small text-muted">{{ __('moderation.last') }} {{ $G::fmtDate($t['last'], true) }}</td>
                    </tr>
                @empty
                    <tr><td class="text-muted">{{ __('moderation.none') }}</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div></div>
    </div>
    <div class="col-12 col-xl-6 mb-4">
        <div class="card shadow-sm border-0 h-100"><div class="card-body">
            <h6 class="card-title">{{ __('moderation.by_staff') }}</h6>
            <div class="table-responsive"><table class="table table-sm mod-table" id="mod-staff">
                <tbody>
                @forelse ($stats['by_staff'] as $t)
                    <tr><td>{{ $t['name'] }}</td><td class="num">{{ $G::num($t['n']) }}</td></tr>
                @empty
                    <tr><td class="text-muted">{{ __('moderation.none') }}</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div></div>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4"><div class="card-body">
    <h6 class="card-title">{{ __('moderation.open_reports') }} <a class="btn btn-sm btn-link" href="{{ route('admin.moderation.reports', ['status' => 'open']) }}">&rarr;</a></h6>
    @include('admin.moderation._reports_table', ['list' => $open, 'tid' => 'mod-open-reports'])
</div></div>

<div class="card shadow-sm border-0 mb-4"><div class="card-body">
    <h6 class="card-title">{{ __('moderation.recent_sanctions') }} <a class="btn btn-sm btn-link" href="{{ route('admin.moderation.sanctions') }}">&rarr;</a></h6>
    @include('admin.moderation._sanctions_table', ['list' => $recent, 'tid' => 'mod-recent'])
</div></div>
@endsection
