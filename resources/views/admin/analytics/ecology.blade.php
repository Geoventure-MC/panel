@extends('admin.analytics.layout')

@section('an')
@php $G = \App\Services\GameAnalytics::class; @endphp
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <h6 class="card-title">{{ __('analytics.pollution_worlds') }}</h6>
        <table class="table table-sm an-table" id="an-pollution">
            <thead><tr><th>{{ __('analytics.world') }}</th><th class="num">{{ $G::metricTitle('avg_level') }}</th><th class="num">{{ $G::metricTitle('max_level') }}</th><th class="num">{{ $G::metricTitle('chunks_over_hazard') }}</th></tr></thead>
            <tbody>
            @forelse ($rows as $w)
                <tr><td>{{ \App\Services\AnalyticsCatalog::get()->world($w['world']) }}</td><td class="num">{{ $G::num($w['avg']) }}</td><td class="num">{{ $G::num($w['max']) }}</td><td class="num">{{ $G::num($w['hazard']) }}</td></tr>
            @empty
                <tr><td colspan="4" class="text-center text-muted py-3">{{ __('analytics.no_worlds') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@include('admin.analytics._charts', ['charts' => $charts])
<div class="card shadow-sm border-0 mb-4"><div class="card-body">
    <h6 class="card-title">{{ __('analytics.incidents') }}</h6>
    <table class="table table-sm an-table mb-0" id="an-incidents"><tbody>
        @forelse ($incidents as $i)<tr><td>{{ $i['name'] }}@if ($i['detail'] !== '') — {{ $i['detail'] }}@endif</td><td class="num">{{ $G::num($i['value']) }}</td></tr>@empty<tr><td class="text-muted text-center">{{ __('analytics.none') }}</td></tr>@endforelse
    </tbody></table>
</div></div>
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <h6 class="card-title">{{ __('analytics.meltdowns', ['n' => $meltdownTotal]) }}</h6>
        @include('admin.analytics._events', ['events' => $meltdowns])
    </div>
</div>
@endsection
