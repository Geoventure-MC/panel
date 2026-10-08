@extends('admin.analytics.layout')

@section('an')
@php $G = \App\Services\GameAnalytics::class; @endphp
@include('admin.analytics._kpi', ['kpi' => $kpi])
@include('admin.analytics._charts', ['charts' => $charts])
<h5 class="mt-2 mb-3">{{ __('analytics.econ_advanced') }}</h5>
@if ($advEmpty)<p class="an-note" id="an-econ-adv-empty">{{ __('analytics.econ_adv_empty') }}</p>@endif
@include('admin.analytics._kpi', ['kpi' => $advKpi])
@include('admin.analytics._charts', ['charts' => $advCharts])
@if ($rows)
<div class="card shadow-sm border-0 mb-4"><div class="card-body">
    <h6 class="card-title">{{ __('analytics.econ_by_country') }}</h6>
    <div class="table-responsive"><table class="table table-sm an-table" id="an-econ-countries">
        <thead><tr><th>{{ __('analytics.country') }}</th>@foreach (['gdp','trade_balance','tax_revenue_24h','bank'] as $m)<th class="num">{{ $G::metricTitle($m) }}</th>@endforeach</tr></thead>
        <tbody>@foreach ($rows as $r)<tr><td><span class="an-dot" style="--c: {{ $G::colorOf($r['name']) }}"></span>{{ $G::country($r['name']) }}</td>@foreach (['gdp','trade_balance','tax_revenue_24h','bank'] as $m)<td class="num">{{ $G::num($r[$m]) }}</td>@endforeach</tr>@endforeach</tbody>
    </table></div>
</div></div>
@endif
<div class="card shadow-sm border-0 mb-4"><div class="card-body">
    <h6 class="card-title">{{ __('analytics.bourse') }}</h6>
    <table class="table table-sm an-table mb-0" id="an-bourse"><tbody>
        @foreach ($bourse['orders'] as [$lab, $n])<tr><td>{{ __('analytics.bourse_orders') }} — {{ $lab }}</td><td class="num">{{ $G::num($n) }}</td></tr>@endforeach
        <tr><td>{{ $G::metricLabel('bourse_shares_sold_24h') }}</td><td class="num">{{ $G::num($bourse['shares']) }}</td></tr>
        <tr><td>{{ $G::metricTitle('bourse_value_sold_24h') }}</td><td class="num">{{ $G::num($bourse['value']) }}</td></tr>
    </tbody></table>
</div></div>
@endsection
