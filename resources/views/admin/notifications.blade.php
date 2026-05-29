@extends('layouts.admin')

@section('title', __('messages.notifications.title'))

@section('page-title', __('messages.notifications.header'))

@section('content')
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="row">
    {{-- Formulaire d'ajout --}}
    <div class="col-md-5 mb-4">
        <div class="card shadow-sm border-0">
            <div class="card-header fw-semibold">
                <i class="bi bi-plus-circle me-1"></i> {{ __('messages.notifications.add') }}
            </div>
            <div class="card-body">
                <form action="{{ route('admin.notifications.store') }}" method="POST">
                    @csrf
                    @if($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach($errors->all() as $e)
                                    <li>{{ $e }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="mb-3">
                        <label class="form-label fw-semibold">{{ __('messages.notifications.type') }}</label>
                        <select name="type" class="form-select" required>
                            <option value="info">ℹ️ info</option>
                            <option value="warning">⚠️ warning</option>
                            <option value="maintenance">🔧 maintenance</option>
                            <option value="event">🎉 event</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">{{ __('messages.notifications.message') }}</label>
                        <textarea name="message" class="form-control" rows="3" maxlength="500" required
                            placeholder="{{ __('messages.notifications.message_placeholder') }}"></textarea>
                        <div class="form-text">500 {{ __('messages.notifications.chars_max') }}</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            {{ __('messages.notifications.url') }}
                            <span class="text-muted fw-normal">({{ __('messages.common.none') }})</span>
                        </label>
                        <input type="url" name="url" class="form-control" placeholder="https://...">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            {{ __('messages.notifications.expires_at') }}
                            <span class="text-muted fw-normal">({{ __('messages.common.none') }})</span>
                        </label>
                        <input type="datetime-local" name="expires_at" class="form-control">
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-send me-1"></i> {{ __('messages.common.add') }}
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Liste des annonces --}}
    <div class="col-md-7 mb-4">
        <div class="card shadow-sm border-0">
            <div class="card-header fw-semibold">
                <i class="bi bi-megaphone me-1"></i> {{ __('messages.notifications.list') }}
            </div>
            <div class="card-body p-0">
                @if($notifications->isEmpty())
                    <p class="text-muted p-3 mb-0">{{ __('messages.notifications.none') }}</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('messages.common.status') }}</th>
                                    <th>{{ __('messages.notifications.type') }}</th>
                                    <th>{{ __('messages.notifications.message') }}</th>
                                    <th>{{ __('messages.notifications.expires_at') }}</th>
                                    <th>{{ __('messages.common.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($notifications as $notif)
                                <tr>
                                    <td>
                                        @if($notif->active)
                                            <span class="badge bg-success">{{ __('messages.common.enabled') }}</span>
                                        @else
                                            <span class="badge bg-secondary">{{ __('messages.common.disabled') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @switch($notif->type)
                                            @case('warning')
                                                <span class="badge bg-warning text-dark">⚠️ warning</span>
                                                @break
                                            @case('maintenance')
                                                <span class="badge bg-dark">🔧 maintenance</span>
                                                @break
                                            @case('event')
                                                <span class="badge" style="background:#8b5cf6">🎉 event</span>
                                                @break
                                            @default
                                                <span class="badge bg-info text-dark">ℹ️ info</span>
                                        @endswitch
                                    </td>
                                    <td class="text-truncate" style="max-width:180px" title="{{ $notif->message }}">{{ $notif->message }}</td>
                                    <td class="text-muted small">
                                        {{ $notif->expires_at ? $notif->expires_at->format('d/m/Y H:i') : '—' }}
                                    </td>
                                    <td>
                                        <form action="{{ route('admin.notifications.toggle', $notif) }}" method="POST" class="d-inline">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="btn btn-sm {{ $notif->active ? 'btn-outline-secondary' : 'btn-outline-success' }}"
                                                title="{{ $notif->active ? __('messages.common.disable') : __('messages.common.enable') }}">
                                                <i class="bi bi-{{ $notif->active ? 'pause-fill' : 'play-fill' }}"></i>
                                            </button>
                                        </form>
                                        <form action="{{ route('admin.notifications.destroy', $notif) }}" method="POST" class="d-inline"
                                            onsubmit="return confirm('{{ __('messages.common.confirm_delete') }}')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('messages.common.delete') }}">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
