@extends('admin.analytics.layout')

@section('an')
@php $G = \App\Services\GameAnalytics::class; @endphp
<div class="card shadow-sm border-0 mb-4"><div class="card-body">
    <form method="get" id="an-compare-form" action="{{ route('admin.analytics.compare') }}">
        <input type="hidden" name="period" value="{{ $period }}">
        <h6 class="card-title">{{ __('analytics.compare_pick') }}</h6>
        <div class="d-flex flex-wrap gap-3 mb-2">
            @forelse ($all as $cn)
                <label class="form-check mb-0"><input class="form-check-input" type="checkbox" name="countries[]" value="{{ $cn }}" @checked(in_array($cn, $sel, true))>
                    <span class="form-check-label"><span class="an-dot" style="--c: {{ $G::colorOf($cn) }}"></span>{{ $G::country($cn) }}</span></label>
            @empty
                <span class="text-muted">{{ __('analytics.no_countries') }}</span>
            @endforelse
        </div>
        <button class="btn btn-sm btn-primary">{{ __('analytics.apply') }}</button>
        <small class="text-muted ms-2">{{ __('analytics.compare_radar_hint') }}</small>
    </form>
</div></div>
@include('admin.analytics._charts', ['charts' => $charts])
<div class="card shadow-sm border-0 mb-4"><div class="card-body">
    <div class="table-responsive">
        <table class="table table-sm an-table" id="an-compare-table">
            <thead><tr><th>{{ __('analytics.metric') }}</th>@foreach ($sel as $cn)<th class="num"><span class="an-dot" style="--c: {{ $G::colorOf($cn) }}"></span>{{ $G::country($cn) }}</th>@endforeach</tr></thead>
            <tbody>
            @foreach ($axes as $a)
                <tr><td>{{ $G::metricTitle($a) }}</td>@foreach ($sel as $cn)<td class="num">{{ $G::num($mx[$a][$cn] ?? null) }}</td>@endforeach</tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div></div>
@endsection
