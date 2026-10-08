@extends('admin.analytics.layout')

@section('an')
@php $G = \App\Services\GameAnalytics::class; @endphp
<div class="card shadow-sm border-0 mb-4"><div class="card-body">
    <h6 class="card-title">{{ __('analytics.peak_heatmap', ['n' => $spanDays]) }}</h6>
    @if ($has)
        <div class="table-responsive">
            <table class="table table-sm an-table an-heat" id="an-heatmap">
                <thead><tr><th></th>@for ($h = 0; $h < 24; $h++)<th>{{ $h }}</th>@endfor</tr></thead>
                <tbody>
                @foreach ($days as $d => $dn)
                    <tr>
                        <th class="text-start">{{ $dn }}</th>
                        @for ($h = 0; $h < 24; $h++)
                            @php $v = $grid[$d][$h] ?? null; @endphp
                            <td title="{{ $dn }} {{ $h }} h : {{ $G::num($v) }}" style="background: rgba(74,222,128,{{ $v === null ? 0 : number_format(0.08 + 0.7 * $v / $max, 2, '.', '') }})">{{ $v === null ? '' : $G::num($v, 0) }}</td>
                        @endfor
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <p class="an-note mb-0" id="an-peak-summary">{{ __('analytics.peak_summary', ['best' => sprintf('%02d h', $best), 'quiet' => sprintf('%02d h – %02d h', $quiet, ($quiet + 2) % 24)]) }}</p>
    @else
        <p class="text-muted text-center py-3 mb-0">{{ __('analytics.peak_empty') }}</p>
    @endif
</div></div>
@include('admin.analytics._charts', ['charts' => $charts])
@endsection
