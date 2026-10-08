@extends('admin.analytics.layout')

@section('an')
@php $G = \App\Services\GameAnalytics::class; @endphp
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between flex-wrap gap-2 mb-2">
            <h6 class="card-title mb-0">{{ __('analytics.research_matrix') }}</h6>
            <form method="get" class="d-flex align-items-center gap-2" id="an-research-form" action="{{ route('admin.analytics.research') }}">
                <input type="hidden" name="period" value="{{ $period }}">
                <label class="small mb-0" for="an-research-country">{{ __('analytics.country') }}</label>
                <select name="country" id="an-research-country" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                    @foreach ($countries as $cn)<option value="{{ $cn }}" @selected($cn === $selCountry)>{{ $G::country($cn) }}</option>@endforeach
                </select>
            </form>
        </div>
        <p class="an-note mb-2">{{ $hasTotals ? __('analytics.research_matrix_hint') : __('analytics.research_matrix_nototal') }}</p>
        <div class="table-responsive">
            <table class="table table-sm an-table an-heat" id="an-research-matrix">
                <thead><tr><th>{{ __('analytics.branch_h') }}</th>@foreach ($ageIds as $a)<th>{{ $ageLabel((string) $a) }}</th>@endforeach</tr></thead>
                <tbody>
                @forelse ($branches as $b)
                    <tr>
                        <th class="text-start">{{ \App\Services\AnalyticsCatalog::get()->branch($b) }}</th>
                        @foreach ($ageIds as $a)
                            @php
                                $cell = $cells[$b][(string) $a] ?? null;
                                $n = $cell['by'][$selCountry] ?? 0;
                                $t = $cell['total'] ?? 0;
                                $ratio = $t > 0 ? min(1, $n / $t) : ($n > 0 ? 1 : 0);
                            @endphp
                            <td data-state="{{ $n > 0 && $t > 0 && $n >= $t ? 'full' : ($n > 0 ? 'part' : 'none') }}" style="background: rgba(74,222,128,{{ number_format($ratio * 0.55, 2, '.', '') }})">
                                @if ($cell === null) <span class="text-muted">·</span>
                                @elseif ($hasTotals && $t > 0) {{ $n }} / {{ $t }}
                                @else {{ $n }} @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($ageIds) + 1 }}" class="text-center text-muted py-3">{{ __('analytics.research_matrix_empty') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($branches)
            <h6 class="mt-3">{{ __('analytics.research_by_country') }}</h6>
            <div class="table-responsive">
                <table class="table table-sm an-table" id="an-research-countries">
                    <thead><tr><th>{{ __('analytics.country') }}</th>@foreach ($branches as $b)<th class="num">{{ \App\Services\AnalyticsCatalog::get()->branch($b) }}</th>@endforeach</tr></thead>
                    <tbody>
                    @foreach ($countries as $cn)
                        <tr @if ($cn === $selCountry) class="table-active" @endif>
                            <td><span class="an-dot" style="--c: {{ $G::colorOf($cn) }}"></span>{{ $G::country($cn) }}</td>
                            @foreach ($branches as $b)
                                @php
                                    $n = 0; $t = 0;
                                    foreach ($cells[$b] ?? [] as $cell) { $n += $cell['by'][$cn] ?? 0; $t += $cell['total'] ?? 0; }
                                @endphp
                                <td class="num">{{ $hasTotals && $t > 0 ? "$n / $t" : $n }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
<div class="row">
    <div class="col-12 mb-4">
        <div class="card shadow-sm border-0 h-100"><div class="card-body">
            <h6 class="card-title">{{ __('analytics.most_advanced') }}</h6>
            <div class="table-responsive">
            <table class="table table-sm an-table" id="an-advanced">
                <thead><tr><th>#</th><th>{{ __('analytics.country') }}</th><th class="num">{{ $G::metricTitle('research_unlocked') }}</th><th class="num">{{ $G::metricTitle('research_points') }}</th><th class="num">{{ $G::metricTitle('age_index') }}</th><th class="num">{{ __('analytics.cost_period') }}</th></tr></thead>
                <tbody>
                @forelse (array_slice($adv, 0, 15) as $i => $row)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td><span class="an-dot" style="--c: {{ $G::colorOf($row['name']) }}"></span><a href="{{ route('admin.analytics.country', ['country' => $row['name'], 'period' => $period]) }}">{{ $G::country($row['name']) }}</a></td>
                        <td class="num">{{ $G::num($row['unlocked']) }}</td>
                        <td class="num">{{ $G::num($row['points']) }}</td>
                        <td class="num">{{ $G::num($row['age']) }}</td>
                        <td class="num">{{ isset($costBy[$row['name']]) ? $G::num($costBy[$row['name']]) : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-3">{{ __('analytics.no_countries') }}</td></tr>
                @endforelse
                </tbody>
            </table>
            </div>
        </div></div>
    </div>
</div>
<div class="row">
    @foreach (array_slice($charts, 0, 2) as $ch)
        @include('admin.analytics._chart', array_merge($ch, ['w' => 6, 'h' => 220]))
    @endforeach
</div>
@include('admin.analytics._charts', ['charts' => array_slice($charts, 2)])
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <h6 class="card-title">{{ __('analytics.research_timeline') }}</h6>
        <div class="table-responsive">
            <table class="table table-sm an-table" id="an-research-timeline">
                <thead><tr><th>{{ __('analytics.date') }}</th><th>{{ __('analytics.country') }}</th><th>{{ __('analytics.research') }}</th><th>{{ __('analytics.branch_h') }}</th><th class="num">{{ __('analytics.cost') }}</th></tr></thead>
                <tbody>
                @forelse ($timeline as $e)
                    <tr>
                        <td class="text-nowrap">{{ $G::fmtDate($e['ts']) }}</td>
                        <td>{{ $G::country($e['ref']) }}</td>
                        <td>{{ $e['research'] }}</td>
                        <td><span class="an-chip">{{ $e['branchName'] }}</span></td>
                        <td class="num">{{ $G::num($e['data']['cost'] ?? null) }}</td>
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
