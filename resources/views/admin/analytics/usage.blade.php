@extends('admin.analytics.layout')

@section('an')
@php $G = \App\Services\GameAnalytics::class; @endphp
@forelse ($groups as $g)
    <section class="mb-4" id="an-fam-{{ $g['key'] }}">
        <div class="row">
            @include('admin.analytics._chart', $g['chart'])
            <div class="col-12 col-xl-6 mb-4">
                <div class="card shadow-sm border-0 h-100"><div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm an-table">
                            <thead><tr><th>{{ __('analytics.metric') }}</th><th>{{ __('analytics.ref') }}</th><th>{{ __('analytics.detail') }}</th><th class="text-end">{{ __('analytics.value') }}</th></tr></thead>
                            <tbody>
                            @foreach ($g['rows'] as $row)
                                <tr class="an-click" data-usage-series="{{ json_encode(['scope' => 'usage', 'metric' => $row['metric'], 'ref' => $row['ref']]) }}" title="{{ __('analytics.click_curve') }}">
                                    <td>{{ $G::metricLabel($row['metric']) }}</td><td>{{ $row['ref'] !== '' ? $row['ref'] : '—' }}</td><td>{{ $row['extra'] ?: '—' }}</td>
                                    <td class="text-end">{{ $G::num($row['value']) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div></div>
            </div>
        </div>
    </section>
@empty
    <div class="card shadow-sm border-0"><div class="card-body text-muted text-center" id="an-usage-empty">{{ __('analytics.usage_empty') }}</div></div>
@endforelse
<div class="modal fade" id="an-usage-modal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content">
        <div class="modal-header"><h6 class="modal-title" id="an-usage-title"></h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body"><div class="an-chart-wrap" style="height:280px"><canvas id="an-usage-canvas"></canvas><div class="an-empty d-none">{{ __('analytics.no_points') }}</div></div></div>
    </div></div>
</div>
@endsection
