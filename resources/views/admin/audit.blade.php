@extends('layouts.admin')

@section('title', __('messages.audit.title'))

@section('page-title', __('messages.audit.header'))

@section('content')
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.audit.index') }}" class="row g-3 align-items-end">
            <div class="col-md-5">
                <label for="action" class="form-label">{{ __('messages.audit.filter_action') }}</label>
                <select name="action" id="action" class="form-select">
                    <option value="">{{ __('messages.audit.filter_all') }}</option>
                    @foreach($actions as $a)
                        <option value="{{ $a }}" {{ $action === $a ? 'selected' : '' }}>{{ $a }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-5">
                <label for="user_id" class="form-label">{{ __('messages.audit.filter_user') }}</label>
                <select name="user_id" id="user_id" class="form-select">
                    <option value="">{{ __('messages.audit.filter_all') }}</option>
                    @foreach($users as $u)
                        <option value="{{ $u->id }}" {{ (string) $userId === (string) $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-fill">{{ __('messages.audit.filter_apply') }}</button>
                <a href="{{ route('admin.audit.index') }}" class="btn btn-outline-secondary">{{ __('messages.audit.filter_reset') }}</a>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        @if($logs->isEmpty())
            <p class="text-muted p-4 mb-0">{{ __('messages.audit.none') }}</p>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>{{ __('messages.audit.date') }}</th>
                            <th>{{ __('messages.audit.user') }}</th>
                            <th>{{ __('messages.audit.action') }}</th>
                            <th>{{ __('messages.audit.target') }}</th>
                            <th>{{ __('messages.audit.details') }}</th>
                            <th>{{ __('messages.audit.ip') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($logs as $log)
                        <tr>
                            <td class="text-muted small">{{ $log->id }}</td>
                            <td class="text-muted small text-nowrap">{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
                            <td>{{ $log->user?->name ?? '—' }}</td>
                            <td><code>{{ $log->action }}</code></td>
                            <td class="text-muted small">{{ $log->target ?? '—' }}</td>
                            <td class="text-muted small">
                                @if(!empty($log->changes))
                                    <code class="text-break">{{ \Illuminate\Support\Str::limit(json_encode($log->changes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 120) }}</code>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="text-muted small">{{ $log->ip ?? '—' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-3">{{ $logs->links() }}</div>
        @endif
    </div>
</div>
@endsection
