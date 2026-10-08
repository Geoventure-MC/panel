@extends('admin.analytics.layout')

@section('an')
@php $G = \App\Services\GameAnalytics::class; @endphp
@include('admin.analytics._kpi', ['kpi' => $kpi])
<div class="card shadow-sm border-0 mb-4"><div class="card-body">
    <h6 class="card-title">{{ __('analytics.cohorts') }}</h6>
    <div class="table-responsive"><table class="table table-sm an-table an-heat" id="an-cohorts">
        <thead><tr><th class="text-start">{{ __('analytics.cohort') }}</th><th>{{ __('analytics.cohort_size') }}</th>@foreach (['W1','W2','W3','W4'] as $w)<th>{{ $w }}</th>@endforeach</tr></thead>
        <tbody>
        @forelse ($rows as $row)
            <tr><th class="text-start fw-normal">{{ $row['label'] }}</th><td>{{ $G::num($row['size']) }}</td>
                @foreach ($row['w'] as $v)<td style="background: rgba(96,165,250,{{ $v === null ? 0 : number_format(0.08 + 0.6 * $v / 100, 2, '.', '') }})">{{ $v === null ? '' : $G::num($v, 0) . ' %' }}</td>@endforeach</tr>
        @empty<tr><td colspan="6" class="text-center text-muted py-3">{{ __('analytics.cohorts_empty') }}</td></tr>@endforelse
        </tbody></table></div>
</div></div>
@include('admin.analytics._charts', ['charts' => $charts])
@endsection
