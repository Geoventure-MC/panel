@extends('admin.moderation.layout')

@section('mod')
<div class="card shadow-sm border-0 mb-4"><div class="card-body">
    <form method="get" class="row g-2 align-items-end mb-3" id="mod-reports-form" action="{{ route('admin.moderation.reports') }}">
        <div class="col-6 col-md-3">
            <label class="form-label small mb-0">{{ __('moderation.target') }}</label>
            <input type="text" name="target" maxlength="64" class="form-control form-control-sm" value="{{ $f['target'] }}">
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label small mb-0">{{ __('moderation.state_h') }}</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">{{ __('moderation.all_statuses') }}</option>
                @foreach (\App\Services\GameModeration::REPORT_STATUSES as $t)<option value="{{ $t }}" @selected($f['status'] === $t)>{{ __("moderation.status.$t") }}</option>@endforeach
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
        <div class="col-12 d-flex gap-2">
            <button class="btn btn-sm btn-primary">{{ __('moderation.apply') }}</button>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.moderation.reports') }}">{{ __('moderation.reset') }}</a>
        </div>
    </form>
    <p class="small text-muted" id="mod-total">{{ __('moderation.total', ['n' => \App\Services\GameAnalytics::num($total)]) }}</p>
    @include('admin.moderation._reports_table', ['list' => $rows])
    @include('admin.moderation._pager')
</div></div>
@endsection
