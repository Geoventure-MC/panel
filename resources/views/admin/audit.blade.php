@extends('layouts.admin')

@section('title', __('messages.audit.title'))

@section('page-title', __('messages.audit.header'))

@section('content')
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
                            <th>IP</th>
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
