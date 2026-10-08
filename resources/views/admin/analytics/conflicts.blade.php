@extends('admin.analytics.layout')

@section('an')
@php $G = \App\Services\GameAnalytics::class; $cat = \App\Services\AnalyticsCatalog::get(); @endphp
@include('admin.analytics._kpi', ['kpi' => $kpi])
<div class="card shadow-sm border-0 mb-4"><div class="card-body">
    <h6 class="card-title">{{ __('analytics.wars_table') }}</h6>
    <div class="table-responsive"><table class="table table-sm an-table" id="an-wars-table">
        <thead><tr><th>{{ __('analytics.belligerents') }}</th><th>{{ __('analytics.casus') }}</th><th>{{ __('analytics.started') }}</th><th class="num">{{ __('analytics.duration') }}</th><th>{{ __('analytics.outcome') }}</th></tr></thead>
        <tbody>
        @forelse ($wars as $w)
            <tr><td>{{ $G::country($w['a']) }} ⚔ {{ $G::country($w['b']) }}</td><td>{{ $w['casus'] !== '' ? $cat->word($w['casus']) : '—' }}</td><td class="text-nowrap">{{ $G::fmtDate($w['start']) }}</td>
                <td class="num">{{ $G::num(round((($w['end'] ?? time()) - $w['start']) / 86400, 1)) }} j</td><td>{{ $outcome($w) }}</td></tr>
        @empty<tr><td colspan="5" class="text-center text-muted py-3">{{ __('analytics.none') }}</td></tr>@endforelse
        </tbody></table></div>
</div></div>
<div class="row">
    <div class="col-12 col-xl-6 mb-4"><div class="card shadow-sm border-0 h-100"><div class="card-body">
        <h6 class="card-title">{{ __('analytics.sieges_table') }}</h6>
        <div class="table-responsive"><table class="table table-sm an-table" id="an-sieges-table">
            <thead><tr><th>{{ __('analytics.belligerents') }}</th><th>{{ __('analytics.started') }}</th><th class="num">{{ __('analytics.k.breaches') }}</th><th>{{ __('analytics.outcome') }}</th></tr></thead>
            <tbody>@forelse ($sieges as $s)<tr><td>{{ $G::country($s['a']) }} → {{ $G::country($s['b']) }}</td><td class="text-nowrap">{{ $G::fmtDate($s['start']) }}</td><td class="num">{{ $G::num($s['breaches']) }}</td>
                <td>{{ $s['end'] === null ? __('analytics.ongoing') : ($s['winner'] !== '' ? __('analytics.victory_of', ['c' => $G::country($s['winner'])]) : __('analytics.ended')) }}</td></tr>@empty<tr><td colspan="4" class="text-center text-muted">{{ __('analytics.none') }}</td></tr>@endforelse</tbody>
        </table></div>
    </div></div></div>
    <div class="col-12 col-xl-6 mb-4"><div class="card shadow-sm border-0 h-100"><div class="card-body">
        <h6 class="card-title">{{ __('analytics.war_balance') }}</h6>
        <ul class="list-unstyled small mb-0" id="an-war-kill">
            @forelse ($kills as $e)<li>{{ $G::fmtDate($e['ts']) }} · {{ $G::country($e['ref']) }} / {{ $G::country((string) ($e['data']['target'] ?? '')) }} : {{ $G::num($e['data']['kills_a'] ?? null) }} – {{ $G::num($e['data']['kills_b'] ?? null) }}</li>@empty<li class="text-muted">{{ __('analytics.none') }}</li>@endforelse
        </ul>
    </div></div></div>
</div>
@include('admin.analytics._charts', ['charts' => $charts])
@endsection
