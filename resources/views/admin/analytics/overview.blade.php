@extends('admin.analytics.layout')

@section('an')
@include('admin.analytics._kpi', ['kpi' => $kpi])
@include('admin.analytics._charts', ['charts' => $charts])
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between mb-2">
            <h6 class="card-title mb-0">{{ __('analytics.feed') }}</h6>
            <a href="{{ route('admin.analytics.events', ['period' => $period]) }}" class="small">{{ __('analytics.all_events') }}</a>
        </div>
        @include('admin.analytics._events', ['events' => $feed])
    </div>
</div>
@endsection
