@extends('admin.analytics.layout')

@section('an')
@include('admin.analytics._kpi', ['kpi' => $kpi])
@if ($empty)<p class="an-note" id="an-logi-empty">{{ __('analytics.logistics_empty') }}</p>@endif
@include('admin.analytics._charts', ['charts' => $charts])
@endsection
