@extends('admin.moderation.layout')

@section('mod')
@php $G = \App\Services\GameAnalytics::class; @endphp
<div class="card shadow-sm border-0 mb-4" id="mod-sanction-detail"><div class="card-body">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
        <h5 class="mb-0">{{ __('moderation.detail_sanction', ['id' => $s['id']]) }}
            @include('admin.moderation._chips', ['kind' => 'type', 'val' => $s['type'], 'label' => $s['type_label']])
            @include('admin.moderation._chips', ['kind' => 'state', 'val' => $s['state'], 'label' => __('moderation.state.' . $s['state'])])
        </h5>
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.moderation.sanctions') }}">&larr; {{ __('moderation.back') }}</a>
    </div>
    <dl class="row mb-0">
        <dt class="col-sm-3">{{ __('moderation.player') }}</dt>
        <dd class="col-sm-9">@if (\App\Services\GameModeration::validName($s['player_label']))<a href="{{ route('admin.moderation.player', ['player' => $s['player_label']]) }}">{{ $s['player_label'] }}</a>@else{{ $s['player_label'] }}@endif</dd>
        <dt class="col-sm-3">{{ __('moderation.reason') }}</dt><dd class="col-sm-9 text-break">{{ $s['reason'] !== '' ? $s['reason'] : '—' }}</dd>
        <dt class="col-sm-3">{{ __('moderation.staff') }}</dt><dd class="col-sm-9">{{ $s['staff_name'] !== '' ? $s['staff_name'] : '—' }}</dd>
        <dt class="col-sm-3">{{ __('moderation.created') }}</dt><dd class="col-sm-9">{{ $G::fmtDate($s['created_at']) }}</dd>
        <dt class="col-sm-3">{{ __('moderation.expires') }}</dt><dd class="col-sm-9">{{ $s['expires_at'] ? $G::fmtDate($s['expires_at']) : __('moderation.permanent') }}</dd>
        @if ($s['revoked_at'])
            <dt class="col-sm-3">{{ __('moderation.revoked_by') }}</dt><dd class="col-sm-9">{{ $s['revoked_by'] !== '' ? $s['revoked_by'] : '—' }} — {{ $G::fmtDate($s['revoked_at']) }}</dd>
            <dt class="col-sm-3">{{ __('moderation.revoke_reason') }}</dt><dd class="col-sm-9 text-break">{{ $s['revoke_reason'] !== '' ? $s['revoke_reason'] : '—' }}</dd>
        @endif
    </dl>
</div></div>
@endsection
