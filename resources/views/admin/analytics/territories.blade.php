@extends('admin.analytics.layout')

@section('an')
@php $G = \App\Services\GameAnalytics::class; [$bx, $bz, $bw, $bh] = $box; @endphp
<div class="card shadow-sm border-0 mb-4"><div class="card-body">
    <form method="get" class="d-flex flex-wrap align-items-end gap-3 mb-3" id="an-terr-form" action="{{ route('admin.analytics.territories') }}">
        <input type="hidden" name="period" value="{{ $period }}">
        <div><label class="form-label small mb-0" for="an-terr-date">{{ __('analytics.date') }}</label>
            <input type="date" name="date" id="an-terr-date" value="{{ $day }}" class="form-control form-control-sm"></div>
        @if (count($worlds) > 1)
            <div><label class="form-label small mb-0">{{ __('analytics.world') }}</label>
                <select name="world" class="form-select form-select-sm">@foreach ($worlds as [$wv, $wl])<option value="{{ $wv }}" @selected($wv === $world)>{{ $wl }}</option>@endforeach</select></div>
        @endif
        <button class="btn btn-sm btn-primary">{{ __('analytics.apply') }}</button>
    </form>
    @if ($has)
        <svg id="an-territory-map" class="an-svg" viewBox="{{ $bx - 1 }} {{ $bz - 1 }} {{ $bw + 2 }} {{ $bh + 2 }}" preserveAspectRatio="xMidYMid meet" style="max-height: 480px" role="img" aria-label="{{ __('analytics.tab.territories') }}">
            @foreach ($rects as [$rx, $rz, $rw, $rc])
                <rect x="{{ $rx }}" y="{{ $rz }}" width="{{ $rw }}" height="1" fill="{{ $G::colorOf($rc) }}" stroke="rgba(0,0,0,.35)" stroke-width="0.06"><title>{{ $G::country($rc) }}</title></rect>
            @endforeach
        </svg>
        <div class="mt-3 table-responsive">
            <table class="table table-sm an-table" id="an-territory-stats">
                <thead><tr><th>{{ __('analytics.country') }}</th><th class="num">{{ __('analytics.chunks') }}</th><th class="num">{{ __('analytics.snapshot_date') }}</th></tr></thead>
                <tbody>@foreach ($stats as $cn => $st)<tr><td><span class="an-dot" style="--c: {{ $G::colorOf($cn) }}"></span>{{ $G::country($cn) }}</td><td class="num">{{ $G::num($st['chunks']) }}</td><td class="num">{{ $G::fmtDate($st['ts']) }}</td></tr>@endforeach</tbody>
            </table>
        </div>
    @else
        <p class="text-muted text-center py-4 mb-0" id="an-territory-empty">{{ __('analytics.territory_empty') }}</p>
    @endif
</div></div>
@endsection
