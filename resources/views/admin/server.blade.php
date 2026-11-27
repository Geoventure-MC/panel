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

        @if(!$options)
            <div class="alert alert-light border small d-flex align-items-center">
                <i class="bi bi-info-circle me-2"></i> {{ __('messages.server.config_error') }}
            </div>
        @endif
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h5 class="fw-bold mb-0">{{ __('messages.server.instances_title') }}</h5>
                <span class="text-muted small">{{ __('messages.server.instances_hint') }}</span>
            </div>
            @if(empty($servers))
                <div class="alert alert-info d-flex align-items-center">
                    <i class="bi bi-info-circle me-2"></i> {{ __('messages.server.no_servers') }}
                </div>
            @else
                <div class="row g-3 mb-4">
                    @foreach($servers as $server)
                        @php
                            $slug = $server['instance_slug'] ?: '';
                            $color = $server['theme_color'] ?: '#64748b';
                            $bundled = in_array($slug, ['geoventure', 'elandor', 'pokeland'], true) ? asset('assets/img/instance_' . $slug . '.png') : null;
                            $logo = $server['icon_url'] ?: $bundled;
                            $channel = $server['channel'] ?? 'stable';
                            $loaderLabel = $server['loader_activation']
                                ? (($server['loader_type'] === 'neoForge' ? 'NeoForge' : ucfirst($server['loader_type'] ?: 'forge')) . ' ' . ($server['loader_build_version'] ?: ''))
                                : __('messages.server.vanilla');
                            $isDefault = $defaultServers[$server['id']] ?? false;
                        @endphp
                        <div class="col-12 col-xl-4 col-lg-6">
                            <div class="card nexus-inst-card h-100 shadow-sm" style="--inst: {{ $color }}">
                                <div class="card-body">
                                    <div class="d-flex align-items-center gap-3 mb-3">
                                        @if($logo)
                                            <img src="{{ $logo }}" alt="" class="nexus-inst-logo">
                                        @else
                                            <div class="nexus-inst-logo d-flex align-items-center justify-content-center"><i class="bi bi-hdd fs-4"></i></div>
                                        @endif
                                        <div class="flex-grow-1 min-w-0">
                                            <h5 class="mb-0 text-truncate">{{ $server['name'] }}</h5>
                                            <code class="small">{{ $slug ?: '—' }}</code>
                                        </div>
                                        @if($isDefault)
                                            <span class="badge bg-success-subtle text-success-emphasis" title="{{ __('messages.server.is_default') }}"><i class="bi bi-star-fill"></i></span>
                                        @endif
                                    </div>
                                    <div class="d-flex flex-wrap gap-1 mb-3">
                                        <span class="badge rounded-pill text-bg-secondary">MC {{ $server['minecraft_version'] ?: '?' }}</span>
                                        <span class="badge rounded-pill text-bg-info">{{ trim($loaderLabel) }}</span>
                                        @if(in_array($channel, ['stable', 'both'], true))<span class="badge rounded-pill text-bg-primary">Stable</span>@endif
                                        @if(in_array($channel, ['test', 'both'], true))<span class="badge rounded-pill text-bg-warning">Test</span>@endif
                                        @if(!empty($server['allowed_roles']))<span class="badge rounded-pill text-bg-danger" title="{{ $server['allowed_roles'] }}"><i class="bi bi-lock-fill"></i> {{ __('messages.server.restricted') }}</span>@endif
                                    </div>
                                    <dl class="row small mb-0 nexus-inst-facts">
                                        <dt class="col-5 text-muted">{{ __('messages.server.address') }}</dt>
                                        <dd class="col-7"><code>{{ $server['address'] }}:{{ $server['port'] }}</code></dd>
                                        <dt class="col-5 text-muted">{{ __('messages.server.data_folder') }}</dt>
                                        <dd class="col-7"><code>{{ $server['data_folder'] ?: ($slug ?: 'data/') }}</code></dd>
                                        @if(in_array($channel, ['test', 'both'], true))
                                            <dt class="col-5 text-muted">{{ __('messages.server.test_target') }}</dt>
                                            <dd class="col-7"><code>{{ ($server['test_ip'] ?: $server['address']) }}:{{ $server['test_port'] ?: $server['port'] }}</code></dd>
                                            <dt class="col-5 text-muted">{{ __('messages.server.test_roles_short') }}</dt>
                                            <dd class="col-7 text-truncate" title="{{ $server['test_roles'] }}">{{ $server['test_roles'] }}</dd>
                                        @endif
                                    </dl>
                                </div>
                                <div class="card-footer bg-transparent d-flex flex-wrap gap-2 align-items-center">
                                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="collapse" data-bs-target="#edit-{{ $server['id'] }}">
                                        <i class="bi bi-pencil me-1"></i>{{ __('messages.server.edit') }}
                                    </button>
                                    <form action="{{ route('admin.server.updateIcon', $server['id']) }}" method="POST" enctype="multipart/form-data" class="d-inline">
                                        @csrf
                                        <label class="btn btn-sm btn-outline-secondary mb-0" title="{{ __('messages.server.upload_icon') }}">
                                            <i class="bi bi-image"></i>
                                            <input type="file" name="icon" class="d-none" accept="image/*" onchange="this.form.submit()">
                                        </label>
                                    </form>
                                    @if($server['icon_local'])
                                        <form action="{{ route('admin.server.deleteIcon', $server['id']) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-secondary" title="{{ __('messages.server.delete_icon') }}" onclick="return confirm('{{ __('messages.server.confirm_delete_icon') }}')"><i class="bi bi-image-alt"></i></button>
                                        </form>
                                    @endif
                                    @if(!$isDefault)
                                        <form method="POST" action="{{ route('admin.server.set-default') }}" class="d-inline set-default-form">
                                            @csrf
                                            <input type="hidden" name="server_id" value="{{ $server['id'] }}">
                                            <button type="submit" class="btn btn-sm btn-outline-success" title="{{ __('messages.server.set_default') }}"><i class="bi bi-star"></i></button>
                                        </form>
                                    @endif
                                    <form action="{{ route('admin.server.delete', $server['id']) }}" method="POST" class="d-inline ms-auto" onsubmit="return confirm('{{ __('messages.server.confirm_delete') }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('messages.server.delete') }}"><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                                <div class="collapse" id="edit-{{ $server['id'] }}">
                                    <form action="{{ route('admin.server.edit', $server['id']) }}" method="POST" class="border-top p-3 nexus-inst-form">
                                        @csrf
                                        <div class="row g-2">
                                            <div class="col-8"><label class="form-label mb-1 small">{{ __('messages.server.name') }}</label><input type="text" name="server_name" class="form-control form-control-sm" value="{{ $server['name'] }}" required></div>
                                            <div class="col-4"><label class="form-label mb-1 small">{{ __('messages.server.instance_slug') }}</label><input type="text" name="instance_slug" class="form-control form-control-sm" value="{{ $server['instance_slug'] }}" placeholder="geoventure"></div>
                                            <div class="col-8"><label class="form-label mb-1 small">{{ __('messages.server.address') }}</label><input type="text" name="server_ip" class="form-control form-control-sm" value="{{ $server['address'] }}" required></div>
                                            <div class="col-4"><label class="form-label mb-1 small">{{ __('messages.server.port') }}</label><input type="text" name="server_port" class="form-control form-control-sm" value="{{ $server['port'] }}" required></div>
                                            <div class="col-4"><label class="form-label mb-1 small">{{ __('messages.server.mc_version') }}</label><input type="text" name="minecraft_version" class="form-control form-control-sm" value="{{ $server['minecraft_version'] }}" placeholder="1.20.1"></div>
                                            <div class="col-4"><label class="form-label mb-1 small">{{ __('messages.server.loader_type') }}</label>
                                                <select name="loader_type" class="form-select form-select-sm">
                                                    @foreach(['forge' => 'Forge', 'neoForge' => 'NeoForge', 'fabric' => 'Fabric', 'quilt' => 'Quilt'] as $lv => $ll)
                                                        <option value="{{ $lv }}" {{ ($server['loader_type'] ?: 'forge') === $lv ? 'selected' : '' }}>{{ $ll }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-4"><label class="form-label mb-1 small">{{ __('messages.server.loader_build') }}</label><input type="text" name="loader_build_version" class="form-control form-control-sm" value="{{ $server['loader_build_version'] }}" placeholder="1.20.1-47.4.23"></div>
                                            <div class="col-12">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" name="loader_activation" value="1" id="loader-act-{{ $server['id'] }}" {{ $server['loader_activation'] ? 'checked' : '' }}>
                                                    <label class="form-check-label small" for="loader-act-{{ $server['id'] }}">{{ __('messages.server.loader_enable') }}</label>
                                                </div>
                                            </div>
                                            <div class="col-6"><label class="form-label mb-1 small">{{ __('messages.server.data_folder') }}</label><input type="text" name="data_folder" class="form-control form-control-sm" value="{{ $server['data_folder'] }}" placeholder="{{ $server['instance_slug'] }}"></div>
                                            <div class="col-3"><label class="form-label mb-1 small">{{ __('messages.server.theme_color') }}</label><input type="color" name="theme_color" class="form-control form-control-sm form-control-color w-100" value="{{ $server['theme_color'] ?: '#64748b' }}"></div>
                                            <div class="col-3"><label class="form-label mb-1 small">{{ __('messages.server.type') }}</label><input type="text" name="type" class="form-control form-control-sm" value="{{ $server['type'] }}"></div>
                                            <div class="col-12"><label class="form-label mb-1 small">{{ __('messages.server.channel') }}</label>
                                                <select name="channel" class="form-select form-select-sm">
                                                    <option value="both" {{ $channel === 'both' ? 'selected' : '' }}>{{ __('messages.server.channel_both') }}</option>
                                                    <option value="stable" {{ $channel === 'stable' ? 'selected' : '' }}>{{ __('messages.server.channel_stable') }}</option>
                                                    <option value="test" {{ $channel === 'test' ? 'selected' : '' }}>{{ __('messages.server.channel_test') }}</option>
                                                </select>
                                            </div>
                                            <div class="col-12"><label class="form-label mb-1 small">{{ __('messages.server.allowed_roles') }}</label><input type="text" name="allowed_roles" class="form-control form-control-sm" value="{{ $server['allowed_roles'] ?? '' }}" placeholder="{{ __('messages.server.allowed_roles_ph') }}"></div>
                                            <div class="col-12"><label class="form-label mb-1 small">{{ __('messages.server.test_roles') }}</label><input type="text" name="test_roles" class="form-control form-control-sm" value="{{ $server['test_roles'] ?? '' }}" placeholder="Admin, Fondateur, Staff, Testeur"></div>
                                            <div class="col-6"><label class="form-label mb-1 small">{{ __('messages.server.test_ip') }}</label><input type="text" name="test_ip" class="form-control form-control-sm" value="{{ $server['test_ip'] ?? '' }}" placeholder="{{ $server['address'] }}"></div>
                                            <div class="col-2"><label class="form-label mb-1 small">{{ __('messages.server.port') }}</label><input type="text" name="test_port" class="form-control form-control-sm" value="{{ $server['test_port'] ?? '' }}" placeholder="{{ $server['port'] }}"></div>
                                            <div class="col-4"><label class="form-label mb-1 small">{{ __('messages.server.test_folder') }}</label><input type="text" name="test_data_folder" class="form-control form-control-sm" value="{{ $server['test_data_folder'] ?? '' }}" placeholder="{{ ($server['instance_slug'] ?: 'test') . '-test' }}"></div>
                                            <div class="col-12 text-end"><button type="submit" class="btn btn-sm btn-success"><i class="bi bi-check-lg me-1"></i>{{ __('messages.common.save') }}</button></div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="alert alert-info small"><i class="bi bi-info-circle me-2"></i>{{ __('messages.server.default_info') }}</div>
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
                            <div class="col-md-2">
                                <label class="form-label">{{ __('messages.server.theme_color') }}</label>
                                <input type="text" name="theme_color" class="form-control" placeholder="#4ade80" pattern="#[0-9a-fA-F]{6}">
                                <small class="text-muted">{{ __('messages.server.theme_color_hint') }}</small>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Canal par défaut</label>
                                <select name="channel" class="form-select">
                                    <option value="stable" selected>{{ __('messages.server.channel_stable') }}</option>
                                    <option value="both">{{ __('messages.server.channel_both') }}</option>
                                    <option value="test">{{ __('messages.server.channel_test') }}</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Grades Azuriom autorisés (Stable)</label>
                                <input type="text" name="allowed_roles" class="form-control" placeholder="Vide = tous les grades">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Grades Azuriom autorisés (Instance Test)</label>
                                <input type="text" name="test_roles" class="form-control" placeholder="Admin, Fondateur, Développeur, Staff, Testeur">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">IP Serveur Test (optionnel)</label>
                                <input type="text" name="test_ip" class="form-control" placeholder="test.geoventure.fr">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Port Test</label>
                                <input type="text" name="test_port" class="form-control" placeholder="25566">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Dossier Modpack Test</label>
                                <input type="text" name="test_data_folder" class="form-control" placeholder="geoventure-test">
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

    </div>

    @if(!empty($servers))
        @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const forms = document.querySelectorAll('.set-default-form');

                forms.forEach(form => {
                    form.addEventListener('submit', function(e) {
                        const serverName = (this.closest('.card')?.querySelector('h5')?.textContent || '').trim();

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
