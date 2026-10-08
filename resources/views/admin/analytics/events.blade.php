@extends('admin.analytics.layout')

@section('an')
@php
    $G = \App\Services\GameAnalytics::class;
    $pages = max(1, (int) ceil($total / $per));
    $q = fn (array $x) => request()->fullUrlWithQuery($x);
@endphp
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end mb-3" id="an-events-form" action="{{ route('admin.analytics.events') }}">
            <input type="hidden" name="period" value="{{ $period }}">
            <div class="col-12 col-md-4">
                <label class="form-label small mb-0">{{ __('analytics.type') }}</label>
                <select name="type" class="form-select form-select-sm">
                    <option value="">{{ __('analytics.all_types') }}</option>
                    @foreach ($types as [$t, $tl])
                        <option value="{{ $t }}" @selected($fType === $t)>{{ $tl }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label small mb-0">{{ __('analytics.country') }}</label>
                <select name="country" class="form-select form-select-sm">
                    <option value="">{{ __('analytics.all_countries') }}</option>
                    @foreach ($countries as [$cv, $cl])<option value="{{ $cv }}" @selected($fRef === $cv)>{{ $cl }}</option>@endforeach
                </select>
            </div>
            <div class="col-12 col-md-4 d-flex gap-2">
                <button class="btn btn-sm btn-primary">{{ __('analytics.apply') }}</button>
                <a class="btn btn-sm btn-outline-success" id="an-export" href="{{ route('admin.analytics.events.export', ['period' => $period, 'type' => $fType, 'country' => $fRef]) }}"><i class="bi bi-download me-1"></i>{{ __('analytics.export_csv') }}</a>
            </div>
        </form>
        <p class="small text-muted" id="an-events-total">{{ __('analytics.events_total', ['n' => $G::num($total)]) }}</p>
        @include('admin.analytics._events', ['events' => $rows])
        @if ($pages > 1)
            <nav class="d-flex justify-content-between align-items-center">
                <a class="btn btn-sm btn-outline-secondary {{ $pg <= 1 ? 'disabled' : '' }}" href="{{ $q(['page' => max(1, $pg - 1)]) }}">&larr;</a>
                <span class="small">{{ $pg }} / {{ $pages }}</span>
                <a class="btn btn-sm btn-outline-secondary {{ $pg >= $pages ? 'disabled' : '' }}" href="{{ $q(['page' => min($pages, $pg + 1)]) }}">&rarr;</a>
            </nav>
        @endif
    </div>
</div>
@endsection
