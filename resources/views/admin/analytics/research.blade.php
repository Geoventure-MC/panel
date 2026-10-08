@extends('admin.analytics.layout')

@section('an')
@php $G = \App\Services\GameAnalytics::class; @endphp
<div class="row">
    <div class="col-12 col-xl-5 mb-4">
        <div class="card shadow-sm border-0 h-100"><div class="card-body">
            <h6 class="card-title">{{ __('analytics.most_advanced') }}</h6>
            <table class="table table-sm an-table" id="an-advanced">
                <thead><tr><th>#</th><th>{{ __('analytics.country') }}</th><th class="text-end">{{ $G::metricLabel('research_unlocked') }}</th><th class="text-end">{{ $G::metricLabel('research_points') }}</th><th class="text-end">{{ $G::metricLabel('age_index') }}</th><th class="text-end">{{ __('analytics.cost_period') }}</th></tr></thead>
                <tbody>
                @forelse (array_slice($adv, 0, 15) as $i => $row)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td><a href="{{ route('admin.analytics.country', ['country' => $row['name'], 'period' => $period]) }}">{{ $row['name'] }}</a></td>
                        <td class="text-end">{{ $G::num($row['unlocked']) }}</td>
                        <td class="text-end">{{ $G::num($row['points']) }}</td>
                        <td class="text-end">{{ $G::num($row['age']) }}</td>
                        <td class="text-end">{{ isset($costBy[$row['name']]) ? $G::num($costBy[$row['name']]) : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-3">{{ __('analytics.no_countries') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div></div>
    </div>
    <div class="col-12 col-xl-7">
        <div class="row">
            @foreach (array_slice($charts, 0, 2) as $ch)
                @include('admin.analytics._chart', array_merge($ch, ['w' => 12, 'h' => 200]))
            @endforeach
        </div>
    </div>
</div>
@include('admin.analytics._charts', ['charts' => array_slice($charts, 2)])
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <h6 class="card-title">{{ __('analytics.research_timeline') }}</h6>
        <div class="table-responsive">
            <table class="table table-sm an-table" id="an-research-timeline">
                <thead><tr><th>{{ __('analytics.date') }}</th><th>{{ __('analytics.country') }}</th><th>{{ __('analytics.research') }}</th><th>{{ __('analytics.branch_h') }}</th><th class="text-end">{{ __('analytics.cost') }}</th></tr></thead>
                <tbody>
                @forelse ($timeline as $e)
                    <tr>
                        <td class="text-nowrap">{{ $G::fmtDate($e['ts']) }}</td>
                        <td>{{ $e['ref'] }}</td>
                        <td>{{ $e['data']['name'] ?? $e['data']['id'] ?? '—' }}</td>
                        <td><span class="an-chip">{{ $branchOf($e) }}</span></td>
                        <td class="text-end">{{ $G::num($e['data']['cost'] ?? null) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-3">{{ __('analytics.no_events') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
