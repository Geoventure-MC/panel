@extends('layouts.admin')

@section('title', __('messages.changelog.title'))

@section('page-title', __('messages.changelog.header'))

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
                <i class="bi bi-plus-circle me-1"></i> {{ __('messages.changelog.add') }}
            </div>
            <div class="card-body">
                <form action="{{ route('admin.changelog.store') }}" method="POST">
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
                        <label class="form-label fw-semibold">
                            {{ __('messages.changelog.version') }}
                            <span class="text-muted fw-normal">({{ __('messages.common.none') }})</span>
                        </label>
                        <input type="text" name="version" class="form-control" maxlength="50" placeholder="1.4.0" value="{{ old('version') }}">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">{{ __('messages.changelog.entry_title') }}</label>
                        <input type="text" name="title" class="form-control" maxlength="255" required
                            placeholder="{{ __('messages.changelog.title_placeholder') }}" value="{{ old('title') }}">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">{{ __('messages.changelog.body') }}</label>
                        <textarea name="body" class="form-control" rows="6" maxlength="10000" required
                            placeholder="{{ __('messages.changelog.body_placeholder') }}">{{ old('body') }}</textarea>
                        <div class="form-text">{{ __('messages.changelog.body_hint') }}</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            {{ __('messages.changelog.image_url') }}
                            <span class="text-muted fw-normal">({{ __('messages.common.none') }})</span>
                        </label>
                        <input type="url" name="image_url" class="form-control" placeholder="https://..." value="{{ old('image_url') }}">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            {{ __('messages.changelog.published_at') }}
                            <span class="text-muted fw-normal">({{ __('messages.changelog.published_at_hint') }})</span>
                        </label>
                        <input type="datetime-local" name="published_at" class="form-control">
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-send me-1"></i> {{ __('messages.common.add') }}
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Liste des entrées --}}
    <div class="col-md-7 mb-4">
        <div class="card shadow-sm border-0">
            <div class="card-header fw-semibold">
                <i class="bi bi-journal-text me-1"></i> {{ __('messages.changelog.list') }}
            </div>
            <div class="card-body p-0">
                @if($changelogs->isEmpty())
                    <p class="text-muted p-3 mb-0">{{ __('messages.changelog.none') }}</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('messages.common.status') }}</th>
                                    <th>{{ __('messages.changelog.version') }}</th>
                                    <th>{{ __('messages.changelog.entry_title') }}</th>
                                    <th>{{ __('messages.changelog.published_at') }}</th>
                                    <th>{{ __('messages.common.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($changelogs as $entry)
                                <tr>
                                    <td>
                                        @if($entry->active)
                                            <span class="badge bg-success">{{ __('messages.common.enabled') }}</span>
                                        @else
                                            <span class="badge bg-secondary">{{ __('messages.common.disabled') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($entry->version)
                                            <span class="badge bg-info text-dark">v{{ $entry->version }}</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="text-truncate" style="max-width:200px" title="{{ $entry->title }}">
                                        <strong>{{ $entry->title }}</strong>
                                        <div class="text-muted small text-truncate">{{ \Illuminate\Support\Str::limit($entry->body, 60) }}</div>
                                    </td>
                                    <td class="text-muted small">
                                        {{ $entry->published_at ? $entry->published_at->format('d/m/Y H:i') : '—' }}
                                    </td>
                                    <td>
                                        <form action="{{ route('admin.changelog.toggle', $entry) }}" method="POST" class="d-inline">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="btn btn-sm {{ $entry->active ? 'btn-outline-secondary' : 'btn-outline-success' }}"
                                                title="{{ $entry->active ? __('messages.common.disable') : __('messages.common.enable') }}">
                                                <i class="bi bi-{{ $entry->active ? 'pause-fill' : 'play-fill' }}"></i>
                                            </button>
                                        </form>
                                        <form action="{{ route('admin.changelog.destroy', $entry) }}" method="POST" class="d-inline"
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
