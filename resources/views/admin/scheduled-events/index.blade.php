@extends('layouts.admin')

@section('title', __('messages.scheduled_events.title'))

@section('page-title', __('messages.scheduled_events.header'))

@section('content')
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="row">
    {{-- Formulaire de programmation --}}
    <div class="col-md-5 mb-4">
        <div class="card shadow-sm border-0">
            <div class="card-header fw-semibold">
                <i class="bi bi-calendar-week me-1"></i> {{ __('messages.scheduled_events.add') }}
            </div>
            <div class="card-body">
                <form action="{{ route('admin.scheduled-events.store') }}" method="POST">
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
                        <label class="form-label fw-semibold">{{ __('messages.scheduled_events.type') }}</label>
                        <select name="type" class="form-select" required>
                            @foreach(\App\Models\ScheduledEvent::TYPES as $type)
                                <option value="{{ $type }}" @selected(old('type') === $type)>{{ __('messages.scheduled_events.types.' . $type) }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">{{ __('messages.scheduled_events.type_hint') }}</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">{{ __('messages.scheduled_events.event_title') }}</label>
                        <input type="text" name="title" class="form-control" maxlength="255" required value="{{ old('title') }}">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">{{ __('messages.scheduled_events.description') }}</label>
                        <textarea name="description" class="form-control" rows="2" maxlength="1000">{{ old('description') }}</textarea>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-7">
                            <label class="form-label fw-semibold">{{ __('messages.scheduled_events.scheduled_at') }}</label>
                            <input type="datetime-local" name="scheduled_at" class="form-control" required value="{{ old('scheduled_at') }}">
                        </div>
                        <div class="col-5">
                            <label class="form-label fw-semibold">{{ __('messages.scheduled_events.recurring') }}</label>
                            <select name="recurring" class="form-select">
                                <option value="none">{{ __('messages.scheduled_events.recurring_none') }}</option>
                                <option value="daily">{{ __('messages.scheduled_events.recurring_daily') }}</option>
                                <option value="weekly">{{ __('messages.scheduled_events.recurring_weekly') }}</option>
                            </select>
                        </div>
                    </div>

                    <button class="btn btn-primary w-100"><i class="bi bi-calendar-plus me-1"></i>{{ __('messages.scheduled_events.submit') }}</button>
                </form>
            </div>
        </div>
    </div>

    {{-- Listes --}}
    <div class="col-md-7 mb-4">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header fw-semibold">
                <i class="bi bi-hourglass-split me-1"></i> {{ __('messages.scheduled_events.upcoming') }}
            </div>
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-3">{{ __('messages.scheduled_events.type') }}</th>
                            <th>{{ __('messages.scheduled_events.event_title') }}</th>
                            <th>{{ __('messages.scheduled_events.scheduled_at') }}</th>
                            <th>{{ __('messages.scheduled_events.recurring') }}</th>
                            <th class="text-end pe-3">{{ __('messages.scheduled_events.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($upcoming as $event)
                            <tr>
                                <td class="ps-3"><span class="badge bg-primary">{{ __('messages.scheduled_events.types.' . $event->type) }}</span></td>
                                <td>{{ $event->title }}</td>
                                <td>{{ $event->scheduled_at->format('d/m/Y H:i') }}</td>
                                <td class="text-muted small">{{ __('messages.scheduled_events.recurring_' . $event->recurring) }}</td>
                                <td class="text-end pe-3">
                                    <form action="{{ route('admin.scheduled-events.cancel', $event) }}" method="POST" class="d-inline">
                                        @csrf @method('PATCH')
                                        <button class="btn btn-sm btn-outline-warning" title="{{ __('messages.scheduled_events.cancel') }}"><i class="bi bi-x-circle"></i></button>
                                    </form>
                                    <form action="{{ route('admin.scheduled-events.destroy', $event) }}" method="POST" class="d-inline"
                                          onsubmit="return confirm('{{ __('messages.scheduled_events.confirm_delete') }}')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" title="{{ __('messages.scheduled_events.delete') }}"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">{{ __('messages.scheduled_events.none_upcoming') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header fw-semibold">
                <i class="bi bi-clock-history me-1"></i> {{ __('messages.scheduled_events.past') }}
            </div>
            <div class="card-body p-0">
                <table class="table align-middle mb-0">
                    <tbody>
                        @forelse($past as $event)
                            <tr>
                                <td class="ps-3"><span class="badge {{ $event->status === 'fired' ? 'bg-success' : 'bg-secondary' }}">{{ __('messages.scheduled_events.status_' . $event->status) }}</span></td>
                                <td>{{ $event->title }}</td>
                                <td class="text-muted small">{{ ($event->fired_at ?? $event->scheduled_at)->format('d/m/Y H:i') }}</td>
                                <td class="text-end pe-3">
                                    <form action="{{ route('admin.scheduled-events.destroy', $event) }}" method="POST" class="d-inline">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td class="text-center text-muted py-4">{{ __('messages.scheduled_events.none_past') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
