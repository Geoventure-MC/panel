@extends('layouts.admin')

@section('title', __('messages.config.title'))

@section('content')
    <div class="container-fluid p-0">
        <h2 class="fw-bold mb-4">{{ __('messages.config.header') }}</h2>

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('messages.common.close') }}"></button>
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <strong>{{ __('messages.common.errors_occurred') }}</strong>
                <ul class="mb-0 mt-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('messages.common.close') }}"></button>
            </div>
        @endif

        {{-- Card 1: Paramètres généraux --}}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white border-bottom">
                <h5 class="card-title mb-0"><i class="bi bi-gear me-2"></i>{{ __('messages.config.general_settings') }}</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.config.update') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label for="app_name" class="form-label">{{ __('messages.config.app_name') }}</label>
                        <input type="text" class="form-control" id="app_name" name="app_name"
                               value="{{ config('app.name') }}" required>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i> {{ __('messages.common.save') }}
                    </button>
                </form>
            </div>
        </div>

        {{-- Card 2: Sites Azuriom --}}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><i class="bi bi-globe me-2"></i>{{ __('messages.config.azuriom_sites') }}</h5>
                <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#addAzuriomForm">
                    <i class="bi bi-plus-lg"></i> {{ __('messages.config.azuriom_add') }}
                </button>
            </div>
            <div class="card-body">

                {{-- Info alert --}}
                <div class="alert alert-info d-flex align-items-start mb-4">
                    <i class="bi bi-info-circle-fill me-2 mt-1 flex-shrink-0"></i>
                    <span>{{ __('messages.config.azuriom_primary_info') }}</span>
                </div>

                {{-- Table of existing sites --}}
                @if($azuriomSites->isEmpty())
                    <p class="text-muted">{{ __('messages.config.azuriom_none') }}</p>
                @else
                    <div class="table-responsive mb-4">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('messages.server.name') }}</th>
                                    <th>URL</th>
                                    <th>{{ __('messages.config.azuriom_api_key') }}</th>
                                    <th>{{ __('messages.config.azuriom_linked_server') }}</th>
                                    <th>{{ __('messages.common.status') }}</th>
                                    <th>{{ __('messages.common.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($azuriomSites as $site)
                                    <tr>
                                        <td><strong>{{ $site->name }}</strong></td>
                                        <td><code>{{ $site->url }}</code></td>
                                        <td>
                                            @if($site->api_key)
                                                <span class="text-muted">****{{ substr($site->api_key, -4) }}</span>
                                            @else
                                                <span class="text-danger">{{ __('messages.config.azuriom_no_key') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($site->server)
                                                <span class="badge bg-secondary">{{ $site->server->server_name }}</span>
                                            @else
                                                <span class="text-muted fst-italic">{{ __('messages.config.azuriom_global') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($site->is_primary)
                                                <span class="badge bg-success"><i class="bi bi-star-fill me-1"></i>{{ __('messages.config.azuriom_primary') }}</span>
                                            @else
                                                <form action="{{ route('admin.config.azuriom.primary', $site->id) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-warning"
                                                            onclick="return confirm('{{ __('messages.config.azuriom_confirm_primary') }}')">
                                                        <i class="bi bi-star me-1"></i>{{ __('messages.config.azuriom_set_primary') }}
                                                    </button>
                                                </form>
                                            @endif
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-secondary me-1"
                                                    type="button"
                                                    data-bs-toggle="collapse"
                                                    data-bs-target="#editAzuriom{{ $site->id }}"
                                                    title="{{ __('messages.common.edit') }}">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <form action="{{ route('admin.config.azuriom.delete', $site->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger"
                                                        onclick="return confirm('{{ __('messages.common.confirm_delete') }}')"
                                                        title="{{ __('messages.common.delete') }}">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    {{-- Inline edit row --}}
                                    <tr class="collapse" id="editAzuriom{{ $site->id }}">
                                        <td colspan="6" class="bg-light p-3">
                                            <form action="{{ route('admin.config.azuriom.edit', $site->id) }}" method="POST">
                                                @csrf
                                                <div class="row g-3">
                                                    <div class="col-md-3">
                                                        <label class="form-label">{{ __('messages.config.azuriom_name') }}</label>
                                                        <input type="text" name="name" class="form-control form-control-sm"
                                                               value="{{ $site->name }}" required>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label">URL</label>
                                                        <input type="url" name="url" class="form-control form-control-sm"
                                                               value="{{ $site->url }}" required>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label">{{ __('messages.config.azuriom_api_key') }}</label>
                                                        <input type="text" name="api_key" class="form-control form-control-sm"
                                                               value="{{ $site->api_key }}" placeholder="{{ __('messages.config.azuriom_no_key') }}">
                                                    </div>
                                                    <div class="col-md-2">
                                                        <label class="form-label">{{ __('messages.config.azuriom_linked_server') }}</label>
                                                        <select name="server_id" class="form-select form-select-sm">
                                                            <option value="">{{ __('messages.config.azuriom_global') }}</option>
                                                            @foreach($servers as $server)
                                                                <option value="{{ $server->server_id }}"
                                                                    {{ $site->server_id == $server->server_id ? 'selected' : '' }}>
                                                                    {{ $server->server_name }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="col-md-1 d-flex align-items-end">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="is_primary" value="1"
                                                                   id="editPrimary{{ $site->id }}"
                                                                   {{ $site->is_primary ? 'checked' : '' }}>
                                                            <label class="form-check-label" for="editPrimary{{ $site->id }}">
                                                                {{ __('messages.config.azuriom_primary') }}
                                                            </label>
                                                        </div>
                                                    </div>
                                                    <div class="col-12">
                                                        <button type="submit" class="btn btn-sm btn-success me-2">
                                                            <i class="bi bi-check-lg"></i> {{ __('messages.common.save') }}
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-secondary"
                                                                data-bs-toggle="collapse"
                                                                data-bs-target="#editAzuriom{{ $site->id }}">
                                                            {{ __('messages.common.cancel') }}
                                                        </button>
                                                    </div>
                                                </div>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                {{-- Add new site form (collapsible) --}}
                <div class="collapse" id="addAzuriomForm">
                    <div class="card border-0 bg-light">
                        <div class="card-body">
                            <h6 class="card-title mb-3"><i class="bi bi-plus-circle me-1"></i>{{ __('messages.config.azuriom_add') }}</h6>
                            <form action="{{ route('admin.config.azuriom.add') }}" method="POST">
                                @csrf
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label">{{ __('messages.config.azuriom_name') }} <span class="text-danger">*</span></label>
                                        <input type="text" name="name" class="form-control"
                                               placeholder="Ex: Geoventure Azuriom" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">URL <span class="text-danger">*</span></label>
                                        <input type="url" name="url" class="form-control"
                                               placeholder="https://votre-site.azuriom.com" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">{{ __('messages.config.azuriom_api_key') }}</label>
                                        <input type="text" name="api_key" class="form-control"
                                               placeholder="{{ __('messages.config.azuriom_no_key') }}">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">{{ __('messages.config.azuriom_linked_server') }}</label>
                                        <select name="server_id" class="form-select">
                                            <option value="">{{ __('messages.config.azuriom_global') }}</option>
                                            @foreach($servers as $server)
                                                <option value="{{ $server->server_id }}">{{ $server->server_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-1 d-flex align-items-end pb-1">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="is_primary" value="1" id="addIsPrimary">
                                            <label class="form-check-label" for="addIsPrimary">
                                                {{ __('messages.config.azuriom_primary') }}
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-success">
                                            <i class="bi bi-plus-lg"></i> {{ __('messages.config.azuriom_add_save') }}
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
@endsection
