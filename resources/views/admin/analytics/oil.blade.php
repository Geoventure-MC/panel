@extends('admin.analytics.layout')

@section('an')
@php $G = \App\Services\GameAnalytics::class; @endphp
@include('admin.analytics._kpi', ['kpi' => [['extracted_24h', $extracted24, null], ['extracted_total', $totalExtracted ?: null, null], ['wells', $wellsTotal ?: null, null], ['depleted_zones', count($depleted), null]]])
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <h6 class="card-title">{{ __('analytics.oil_zones') }}</h6>
        <div class="table-responsive">
            <table class="table table-sm an-table" id="an-oil">
                <thead><tr><th>{{ __('analytics.zone') }}</th><th style="min-width:160px">{{ $G::metricLabel('reserve_pct') }}</th><th class="text-end">{{ $G::metricLabel('reserve') }}</th><th class="text-end">{{ $G::metricLabel('extracted_total') }}</th><th class="text-end">{{ $G::metricLabel('wells') }}</th><th></th></tr></thead>
                <tbody>
                @forelse ($rows as $z)
                    <tr>
                        <td>{{ $z['zone'] }}</td>
                        <td><div class="d-flex align-items-center gap-2"><div class="an-bar flex-grow-1"><i style="width: {{ max(0, min(100, $z['pct'] ?? 0)) }}%; @if (($z['pct'] ?? 100) < 20) background: var(--geo-danger-soft) @endif"></i></div><span class="small">{{ $G::num($z['pct']) }} %</span></div></td>
                        <td class="text-end">{{ $G::num($z['reserve']) }}</td>
                        <td class="text-end">{{ $G::num($z['total']) }}</td>
                        <td class="text-end">{{ $G::num($z['wells']) }}</td>
                        <td>@if (in_array($z['zone'], $depleted, true))<span class="an-chip" style="background:rgba(248,113,113,.15);border-color:rgba(248,113,113,.4)">{{ __('analytics.depleted') }}</span>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-3">{{ __('analytics.no_zones') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@include('admin.analytics._charts', ['charts' => $charts])
@endsection
