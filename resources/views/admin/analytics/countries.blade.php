@extends('admin.analytics.layout')

@section('an')
@php $G = \App\Services\GameAnalytics::class; @endphp
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <h6 class="card-title">{{ __('analytics.ranking') }}</h6>
        <form method="get" id="an-countries-form" action="{{ route('admin.analytics.countries') }}">
            <input type="hidden" name="period" value="{{ $period }}">
            <div class="table-responsive">
                <table class="table table-sm an-table" id="an-ranking">
                    <thead><tr>
                        <th>{{ __('analytics.compare') }}</th><th>#</th><th>{{ __('analytics.country') }}</th>
                        @foreach (['power','members','members_online','claims','bank','research_points','age_index','emissions','wars_active'] as $m)
                            <th class="text-end">{{ $G::metricLabel($m) }}</th>
                        @endforeach
                    </tr></thead>
                    <tbody>
                    @forelse ($rows as $i => $row)
                        <tr>
                            <td><input class="form-check-input" type="checkbox" name="countries[]" value="{{ $row['name'] }}" @checked(in_array($row['name'], $sel, true))></td>
                            <td>{{ $i + 1 }}</td>
                            <td><a href="{{ route('admin.analytics.country', ['country' => $row['name'], 'period' => $period]) }}">{{ $row['name'] }}</a></td>
                            @foreach (['power','members','members_online','claims','bank','research_points','age_index','emissions','wars_active'] as $m)
                                <td class="text-end">{{ $G::num($row[$m]) }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="12" class="text-center text-muted py-3">{{ __('analytics.no_countries') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <noscript><button class="btn btn-sm btn-primary">{{ __('analytics.apply') }}</button></noscript>
            <small class="text-muted">{{ __('analytics.compare_hint') }}</small>
        </form>
    </div>
</div>
@include('admin.analytics._charts', ['charts' => $charts])
@endsection
