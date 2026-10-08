@extends('admin.analytics.layout')

@section('an')
@php $G = \App\Services\GameAnalytics::class; @endphp
<p><a href="{{ route('admin.analytics.countries', ['period' => $period]) }}"><i class="bi bi-arrow-left"></i> {{ __('analytics.back_countries') }}</a></p>
<h4 class="mb-3" id="an-country-name">{{ $country }}</h4>
@include('admin.analytics._kpi', ['kpi' => collect($now)->map(fn ($v, $m) => [$m, $v, null])->values()->all()])
@include('admin.analytics._charts', ['charts' => $charts])
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <h6 class="card-title">{{ __('analytics.country_timeline') }}</h6>
        @include('admin.analytics._events', ['events' => $timeline])
    </div>
</div>
@endsection
