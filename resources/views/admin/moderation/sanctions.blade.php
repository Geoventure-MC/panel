@extends('admin.moderation.layout')

@section('mod')
<div class="card shadow-sm border-0 mb-4"><div class="card-body">
    <form method="get" class="row g-2 align-items-end mb-3" id="mod-sanctions-form" action="{{ route('admin.moderation.sanctions') }}">
        <div class="col-6 col-md-3">
            <label class="form-label small mb-0">{{ __('moderation.player') }}</label>
            <input type="text" name="player" maxlength="64" class="form-control form-control-sm" value="{{ $f['player'] }}">
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label small mb-0">{{ __('moderation.staff') }}</label>
            <input type="text" name="staff" maxlength="64" class="form-control form-control-sm" value="{{ $f['staff'] }}">
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label small mb-0">{{ __('moderation.type_h') }}</label>
            <select name="type" class="form-select form-select-sm">
                <option value="">{{ __('moderation.all_types') }}</option>
                @foreach (\App\Services\GameModeration::TYPES as $t)<option value="{{ $t }}" @selected($f['type'] === $t)>{{ __("moderation.type.$t") }}</option>@endforeach
            </select>
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label small mb-0">{{ __('moderation.state_h') }}</label>
            <select name="state" class="form-select form-select-sm">
                <option value="">{{ __('moderation.all_states') }}</option>
                @foreach (\App\Services\GameModeration::STATES as $t)<option value="{{ $t }}" @selected($f['state'] === $t)>{{ __("moderation.state.$t") }}</option>@endforeach
            </select>
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label small mb-0">{{ __('moderation.from') }}</label>
            <input type="date" name="from" class="form-control form-control-sm" value="{{ $f['from'] ? \Carbon\Carbon::createFromTimestamp($f['from'], \App\Services\GameAnalytics::TZ)->format('Y-m-d') : '' }}">
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label small mb-0">{{ __('moderation.to') }}</label>
            <input type="date" name="to" class="form-control form-control-sm" value="{{ $f['to'] ? \Carbon\Carbon::createFromTimestamp($f['to'], \App\Services\GameAnalytics::TZ)->format('Y-m-d') : '' }}">
        </div>
        <div class="col-12 col-md-6 d-flex gap-2 flex-wrap">
            <button class="btn btn-sm btn-primary">{{ __('moderation.apply') }}</button>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.moderation.sanctions') }}">{{ __('moderation.reset') }}</a>
            @if ($isSuper)
                <a class="btn btn-sm btn-outline-success" id="mod-export" href="{{ route('admin.moderation.export', array_filter(request()->only(['player', 'staff', 'type', 'state', 'from', 'to']))) }}" title="{{ __('moderation.export_note') }}"><i class="bi bi-download me-1"></i>{{ __('moderation.export_csv') }}</a>
            @endif
        </div>
    </form>
    <p class="small text-muted" id="mod-total">{{ __('moderation.total', ['n' => \App\Services\GameAnalytics::num($total)]) }}</p>
    @include('admin.moderation._sanctions_table', ['list' => $rows])
    @include('admin.moderation._pager')
</div></div>
@endsection
