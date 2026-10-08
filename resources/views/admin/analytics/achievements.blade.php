@extends('admin.analytics.layout')

@section('an')
@php $G = \App\Services\GameAnalytics::class; @endphp
<div class="row"><div class="col-12">
@if ($rows)
    @include('admin.analytics._charts', ['charts' => $charts])
@endif
</div></div>
<div class="card shadow-sm border-0 mb-4"><div class="card-body">
    <h6 class="card-title">{{ __('analytics.unlock_table') }}</h6>
    <div class="table-responsive"><table class="table table-sm an-table" id="an-achievements">
        <thead><tr><th>{{ __('analytics.achievement') }}</th><th>{{ __('analytics.rarity_h') }}</th><th class="num">{{ __('analytics.points') }}</th><th class="num">{{ __('analytics.unlocked') }}</th><th style="min-width:140px">{{ __('analytics.unlock_rate') }}</th></tr></thead>
        <tbody>
        @forelse ($rows as $x)
            <tr><td>{{ $x['name'] }}</td><td>{{ $x['rarity'] ?? '—' }}</td><td class="num">{{ $G::num($x['points']) }}</td><td class="num">{{ $G::num($x['n']) }}</td>
                <td>@if ($x['pct'] !== null)<div class="d-flex align-items-center gap-2"><div class="an-bar flex-grow-1"><i style="width: {{ min(100, $x['pct']) }}%"></i></div><span class="small">{{ $G::num($x['pct']) }}&nbsp;%</span></div>@else — @endif</td></tr>
        @empty<tr><td colspan="5" class="text-center text-muted py-3">{{ __('analytics.achievements_empty') }}</td></tr>@endforelse
        </tbody></table></div>
</div></div>
@endsection
