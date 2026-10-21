@extends('admin.moderation.layout')

@section('mod')
@php $G = \App\Services\GameAnalytics::class; @endphp
<div class="card shadow-sm border-0 mb-4" id="mod-report-detail"><div class="card-body">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
        <h5 class="mb-0">{{ __('moderation.detail_report', ['id' => $r['id']]) }}
            @include('admin.moderation._chips', ['kind' => 'report', 'val' => $r['status'], 'label' => __('moderation.status.' . $r['status'])])
        </h5>
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.moderation.reports') }}">&larr; {{ __('moderation.back') }}</a>
    </div>
    <dl class="row">
        <dt class="col-sm-3">{{ __('moderation.target') }}</dt>
        <dd class="col-sm-9">@if (\App\Services\GameModeration::validName($r['target_name']))<a href="{{ route('admin.moderation.player', ['player' => $r['target_name']]) }}">{{ $r['target_name'] }}</a>@else{{ $r['target_name'] !== '' ? $r['target_name'] : '—' }}@endif</dd>
        <dt class="col-sm-3">{{ __('moderation.reporter') }}</dt><dd class="col-sm-9">{{ $r['reporter_label'] }}</dd>
        <dt class="col-sm-3">{{ __('moderation.reason') }}</dt><dd class="col-sm-9 text-break">{{ $r['reason'] !== '' ? $r['reason'] : '—' }}</dd>
        <dt class="col-sm-3">{{ __('moderation.created') }}</dt><dd class="col-sm-9">{{ $G::fmtDate($r['created_at']) }}</dd>
        @if ($r['closed_at'])<dt class="col-sm-3">{{ __('moderation.closed') }}</dt><dd class="col-sm-9">{{ $G::fmtDate($r['closed_at']) }}</dd>@endif
        <dt class="col-sm-3">{{ __('moderation.handled_by') }}</dt><dd class="col-sm-9">{{ $r['handled_by'] !== '' ? $r['handled_by'] : '—' }}</dd>
        @if ($r['world'] !== '' || $r['x'] !== null)
            <dt class="col-sm-3">{{ __('moderation.location') }}</dt>
            <dd class="col-sm-9">{{ $r['world'] !== '' ? \App\Services\AnalyticsCatalog::get()->world($r['world']) : '' }} {{ $r['x'] !== null ? $r['x'] . ' / ' . $r['y'] . ' / ' . $r['z'] : '' }}</dd>
        @endif
        @if ($r['note'] !== '')<dt class="col-sm-3">{{ __('moderation.note') }}</dt><dd class="col-sm-9 text-break">{{ $r['note'] }}</dd>@endif
    </dl>
    <h6>{{ __('moderation.context') }}</h6>
    <p class="small text-muted mb-2">{{ __('moderation.context_note') }}</p>
    @if ($r['context'] !== '')
        <pre class="mod-context p-3 bg-body-secondary rounded" id="mod-context">{{ $r['context'] }}</pre>
    @else
        <p class="text-muted">{{ __('moderation.no_context') }}</p>
    @endif
</div></div>
@endsection
