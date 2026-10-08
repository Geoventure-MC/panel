@extends('admin.analytics.layout')

@section('an')
@php $G = \App\Services\GameAnalytics::class; @endphp
@include('admin.analytics._kpi', ['kpi' => $kpi])
@if ($uptime)
    <p class="an-note">{{ __('analytics.uptime_since', ['d' => $uptime >= 86400 ? $G::num(round($uptime / 86400, 1)) . ' j' : $G::num(round($uptime / 3600, 1)) . ' h']) }}</p>
@endif
@include('admin.analytics._charts', ['charts' => $charts])
@endsection
