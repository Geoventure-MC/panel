@extends('layouts.admin')

@php
    use App\Models\OptionsServer;
@endphp

@section('title', __('messages.server.title'))

@section('content')
    <div class="container-fluid p-0">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold mb-0">{{ __('messages.server.list_title') }}</h2>
            <form method="POST" action="{{ route('admin.server.sync') }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-repeat me-1"></i> {{ __('messages.server.sync_btn') }}
                </button>
            </form>
        </div>

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('messages.common.close') }}"></button>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('messages.common.close') }}"></button>
            </div>
        @endif

        @if($error)
            <div class="alert alert-danger d-flex align-items-center">
                <i class="fas fa-exclamation-triangle me-2"></i> {{ $error }}
            </div>
        @endif

        {{-- Formulaire d'ajout manuel d'un serveur (toujours disponible) --}}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><i class="bi bi-plus-circle me-2"></i>{{ __('messages.server.add_title') }}</h5>
                <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#addServerForm">
                    <i class="bi bi-plus-lg"></i> {{ __('messages.server.add_btn') }}
                </button>
            </div>
            <div class="collapse" id="addServerForm">
                <div class="card-body">
                    <form action="{{ route('admin.server.add') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">{{ __('messages.server.name') }}</label>
                                <input type="text" name="server_name" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">{{ __('messages.server.address') }}</label>
                                <input type="text" name="server_ip" class="form-control" placeholder="play.exemple.fr" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">{{ __('messages.server.port') }}</label>
                                <input type="text" name="server_port" class="form-control" value="25565" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">{{ __('messages.server.type') }}</label>
                                <input type="text" name="type" class="form-control" value="minecraft">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('messages.server.icon') }}</label>
                                <input type="file" name="icon" class="form-control" accept="image/*">
                            </div>

                            {{-- Section multi-instance --}}
                            <div class="col-12">
                                <hr class="my-2">
                                <h6 class="text-primary mb-1"><i class="bi bi-box-seam me-1"></i>{{ __('messages.server.instance_section') }}</h6>
                                <p class="text-muted small mb-2">{{ __('messages.server.instance_hint') }}</p>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">{{ __('messages.server.instance_slug') }}</label>
                                <input type="text" name="instance_slug" class="form-control" placeholder="geoventure">
                                <small class="text-muted">{{ __('messages.server.instance_slug_hint') }}</small>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">{{ __('messages.server.data_folder') }}</label>
                                <input type="text" name="data_folder" class="form-control" placeholder="{{ __('messages.server.data_folder_ph') }}">
                                <small class="text-muted">{{ __('messages.server.data_folder_hint') }}</small>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">{{ __('messages.server.mc_version') }}</label>
                                <input type="text" name="minecraft_version" class="form-control" placeholder="1.20.1">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">{{ __('messages.server.loader_type') }}</label>
                                <input type="text" name="loader_type" class="form-control" placeholder="forge">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">{{ __('messages.server.loader_build') }}</label>
                                <input type="text" name="loader_build_version" class="form-control" placeholder="1.20.1-47.4.20">
                            </div>

                            <div class="col-12">
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-check-lg"></i> {{ __('messages.server.add_save') }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        @if(!$options)
            <div class="alert alert-warning d-flex align-items-center">
                <i class="fas fa-cogs me-2"></i> {{ __('messages.server.config_error') }}
            </div>
        @endif
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white border-bottom">
                    <h5 class="card-title mb-0">{{ __('messages.server.synced_servers') }}</h5>
                </div>
                <div class="card-body">
                    @if(empty($servers))
                        <div class="alert alert-info d-flex align-items-center">
                            <i class="fas fa-info-circle me-2"></i> {{ __('messages.server.no_servers') }}
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>{{ __('messages.server.name') }}</th>
                                        <th>{{ __('messages.server.address') }}</th>
                                        <th>{{ __('messages.server.port') }}</th>
                                        <th>{{ __('messages.server.type') }}</th>
                                        <th>{{ __('messages.server.icon') }}</th>
                                        <th class="text-center" style="width: 240px;">{{ __('messages.common.actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($servers as $server)
                                        <tr>
                                            <td><strong>{{ $server['name'] }}</strong></td>
                                            <td><code>{{ $server['address'] }}</code></td>
                                            <td>{{ $server['port'] }}</td>
                                            <td><span class="badge bg-info">{{ $server['type'] }}</span></td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    @if($server['icon_url'])
                                                        <img src="{{ $server['icon_url'] }}"
                                                             alt="{{ __('messages.server.icon') }}"
                                                             class="img-thumbnail rounded-circle"
                                                             style="max-width: 40px; max-height: 40px;">
                                                        @if($server['icon_local'])
                                                            <span class="badge bg-success" title="{{ __('messages.server.icon_local') }}">
                                                                <i class="bi bi-hdd"></i>
                                                            </span>
                                                        @endif
                                                    @else
                                                        <span class="text-muted">{{ __('messages.common.none') }}</span>
                                                    @endif

                                                    {{-- Upload icon form --}}
                                                    <form action="{{ route('admin.server.updateIcon', $server['id']) }}" method="POST" enctype="multipart/form-data" class="d-inline">
                                                        @csrf
                                                        <label class="btn btn-sm btn-outline-secondary" title="{{ __('messages.server.upload_icon') }}">
                                                            <i class="bi bi-upload"></i>
                                                            <input type="file" name="icon" class="d-none" accept="image/*" onchange="this.form.submit()">
                                                        </label>
                                                    </form>

                                                    {{-- Delete local icon form --}}
                                                    @if($server['icon_local'])
                                                        <form action="{{ route('admin.server.deleteIcon', $server['id']) }}" method="POST" class="d-inline">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('messages.server.delete_icon') }}" onclick="return confirm('{{ __('messages.server.confirm_delete_icon') }}')">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </form>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <div class="d-flex flex-column gap-2 align-items-center">
                                                    @if(!($defaultServers[$server['id']] ?? false))
                                                        <form method="POST" action="{{ route('admin.server.set-default') }}" style="display: inline;" class="set-default-form">
                                                            @csrf
                                                            <input type="hidden" name="server_id" value="{{ $server['id'] }}">
                                                            <button type="submit" class="btn btn-sm btn-primary">
                                                                <i class="bi bi-star"></i> {{ __('messages.server.set_default') }}
                                                            </button>
                                                        </form>
                                                    @else
                                                        <span class="text-success fw-bold">
                                                            <i class="bi bi-check-circle-fill"></i> {{ __('messages.server.is_default') }}
                                                        </span>
                                                    @endif
                                                    <div class="btn-group btn-group-sm">
                                                        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#edit-{{ $server['id'] }}" title="{{ __('messages.server.edit') }}">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                        <form action="{{ route('admin.server.delete', $server['id']) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ __('messages.server.confirm_delete') }}')">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-outline-danger" title="{{ __('messages.server.delete') }}">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        {{-- Ligne d'édition repliable --}}
                                        <tr class="collapse" id="edit-{{ $server['id'] }}">
                                            <td colspan="6" class="bg-light">
                                                <form action="{{ route('admin.server.edit', $server['id']) }}" method="POST">
                                                    @csrf
                                                    <div class="row g-2 align-items-end">
                                                        <div class="col-md-4">
                                                            <label class="form-label mb-1">{{ __('messages.server.name') }}</label>
                                                            <input type="text" name="server_name" class="form-control form-control-sm" value="{{ $server['name'] }}" required>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label mb-1">{{ __('messages.server.address') }}</label>
                                                            <input type="text" name="server_ip" class="form-control form-control-sm" value="{{ $server['address'] }}" required>
                                                        </div>
                                                        <div class="col-md-2">
                                                            <label class="form-label mb-1">{{ __('messages.server.port') }}</label>
                                                            <input type="text" name="server_port" class="form-control form-control-sm" value="{{ $server['port'] }}" required>
                                                        </div>
                                                        <div class="col-md-2">
                                                            <label class="form-label mb-1">{{ __('messages.server.type') }}</label>
                                                            <input type="text" name="type" class="form-control form-control-sm" value="{{ $server['type'] }}">
                                                        </div>

                                                        {{-- Champs multi-instance --}}
                                                        <div class="col-md-3">
                                                            <label class="form-label mb-1">{{ __('messages.server.instance_slug') }}</label>
                                                            <input type="text" name="instance_slug" class="form-control form-control-sm" value="{{ $server['instance_slug'] }}" placeholder="geoventure">
                                                        </div>
                                                        <div class="col-md-3">
                                                            <label class="form-label mb-1">{{ __('messages.server.data_folder') }}</label>
                                                            <input type="text" name="data_folder" class="form-control form-control-sm" value="{{ $server['data_folder'] }}" placeholder="{{ $server['instance_slug'] }}">
                                                        </div>
                                                        <div class="col-md-2">
                                                            <label class="form-label mb-1">{{ __('messages.server.mc_version') }}</label>
                                                            <input type="text" name="minecraft_version" class="form-control form-control-sm" value="{{ $server['minecraft_version'] }}" placeholder="1.20.1">
                                                        </div>
                                                        <div class="col-md-2">
                                                            <label class="form-label mb-1">{{ __('messages.server.loader_type') }}</label>
                                                            <input type="text" name="loader_type" class="form-control form-control-sm" value="{{ $server['loader_type'] }}" placeholder="forge">
                                                        </div>
                                                        <div class="col-md-2">
                                                            <label class="form-label mb-1">{{ __('messages.server.loader_build') }}</label>
                                                            <input type="text" name="loader_build_version" class="form-control form-control-sm" value="{{ $server['loader_build_version'] }}" placeholder="1.20.1-47.4.20">
                                                        </div>
                                                        <div class="col-md-12 d-flex align-items-center gap-3 mt-1">
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="checkbox" name="loader_activation" value="1" id="loader-act-{{ $server['id'] }}" {{ $server['loader_activation'] ? 'checked' : '' }}>
                                                                <label class="form-check-label small" for="loader-act-{{ $server['id'] }}">{{ __('messages.server.loader_enable') }}</label>
                                                            </div>
                                                            <button type="submit" class="btn btn-sm btn-success ms-auto"><i class="bi bi-check-lg me-1"></i>{{ __('messages.common.save') }}</button>
                                                        </div>
                                                    </div>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="alert alert-info mt-3">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>{{ __('messages.server.default_info') }}</strong>
                        </div>
                    @endif
                </div>
            </div>
    </div>

    @if(!empty($servers))
        @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const forms = document.querySelectorAll('.set-default-form');

                forms.forEach(form => {
                    form.addEventListener('submit', function(e) {
                        const serverName = this.closest('tr').querySelector('td:nth-child(2)').textContent.trim();

                        if (!confirm(`{{ __('messages.server.confirm_default') }}`)) {
                            e.preventDefault();
                            return false;
                        }

                        const button = this.querySelector('button');
                        button.disabled = true;
                        button.innerHTML = '<i class="bi bi-hourglass-split"></i> {{ __('messages.server.processing') }}';
                    });
                });
            });
        </script>
        @endpush
    @endif
@endsection
