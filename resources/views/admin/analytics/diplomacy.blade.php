@extends('admin.analytics.layout')

@section('an')
@php $G = \App\Services\GameAnalytics::class; @endphp
<div class="card shadow-sm border-0 mb-4"><div class="card-body">
    <h6 class="card-title">{{ __('analytics.diplo_graph') }}</h6>
    @if ($names)
        <svg id="an-diplo" class="an-svg" viewBox="0 0 600 420" role="img" aria-label="{{ __('analytics.tab.diplomacy') }}">
            @foreach ($rel['treaties'] as [$a, $b])@if (isset($pos[$a], $pos[$b]))<line x1="{{ $pos[$a][0] }}" y1="{{ $pos[$a][1] }}" x2="{{ $pos[$b][0] }}" y2="{{ $pos[$b][1] }}" stroke="#94a3b8" stroke-width="2" stroke-dasharray="2 5"/>@endif @endforeach
            @foreach ($rel['alliances'] as [$a, $b])@if (isset($pos[$a], $pos[$b]))<line x1="{{ $pos[$a][0] }}" y1="{{ $pos[$a][1] }}" x2="{{ $pos[$b][0] }}" y2="{{ $pos[$b][1] }}" stroke="#4ade80" stroke-width="4"/>@endif @endforeach
            @foreach ($rel['wars'] as [$a, $b])@if (isset($pos[$a], $pos[$b]))<line x1="{{ $pos[$a][0] }}" y1="{{ $pos[$a][1] }}" x2="{{ $pos[$b][0] }}" y2="{{ $pos[$b][1] }}" stroke="#f87171" stroke-width="4" stroke-dasharray="10 6"/>@endif @endforeach
            @foreach ($names as $n)
                <g><circle cx="{{ $pos[$n][0] }}" cy="{{ $pos[$n][1] }}" r="18" fill="{{ $G::colorOf($n) }}" stroke="#0b1a10" stroke-width="2"/>
                <text x="{{ $pos[$n][0] }}" y="{{ $pos[$n][1] + 34 }}" text-anchor="middle" font-size="14" fill="currentColor">{{ $G::country($n) }}</text></g>
            @endforeach
        </svg>
        <div class="small mt-2 d-flex flex-wrap gap-3">
            <span><span style="color:#4ade80">━━</span> {{ __('analytics.alliance') }}</span><span><span style="color:#f87171">╍╍</span> {{ __('analytics.war') }}</span><span><span style="color:#94a3b8">┈┈</span> {{ __('analytics.treaty') }}</span>
        </div>
    @else
        <p class="text-muted text-center py-4 mb-0">{{ __('analytics.diplo_empty') }}</p>
    @endif
</div></div>
<div class="row">
    @foreach ([['alliances', 'alliance', 'an-alliances'], ['wars', 'war', 'an-wars'], ['treaties', 'treaty', 'an-treaties']] as [$k, $lab, $id])
        <div class="col-12 col-md-4 mb-4"><div class="card shadow-sm border-0 h-100"><div class="card-body">
            <h6 class="card-title">{{ __("analytics.rel_$k") }}</h6>
            <ul class="list-unstyled mb-0 small" id="{{ $id }}">
                @forelse ($rel[$k] as $row)<li>{{ $G::country($row[0]) }} ↔ {{ $G::country($row[1]) }}</li>@empty<li class="text-muted">{{ __('analytics.none') }}</li>@endforelse
            </ul>
        </div></div></div>
    @endforeach
</div>
<div class="card shadow-sm border-0 mb-4"><div class="card-body">
    <h6 class="card-title">{{ __('analytics.diplo_events') }}</h6>
    @include('admin.analytics._events', ['events' => $recent])
</div></div>
@endsection
