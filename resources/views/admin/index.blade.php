@extends('layouts.admin')

@section('title', __('messages.dashboard.title'))

@section('page-title', __('messages.dashboard.welcome'))

@section('content')
<div class="container-fluid">
    {{-- Ligne de stats --}}
    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">{{ __('messages.dashboard.stats') }}</h5>
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="text-muted mb-0">{{ __('messages.dashboard.account_count') }}</p>
                            <h2 class="mb-0 fw-bold">{{ $userCount ?? 0 }}</h2>
                        </div>
                        <i class="bi bi-people fs-1 text-primary opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-3">
            <div class="card h-100 border-{{ $maintenanceActive ? 'warning' : 'success' }}">
                <div class="card-body">
                    <h5 class="card-title">{{ __('messages.dashboard.maintenance_status') }}</h5>
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span id="maintenanceBadge"
                                class="badge bg-{{ $maintenanceActive ? 'warning text-dark' : 'success' }} mb-1">
                                {{ $maintenanceActive ? __('messages.dashboard.maintenance_on') : __('messages.dashboard.maintenance_off') }}
                            </span>
                            <p class="text-muted small mb-0">{{ __('messages.dashboard.maintenance_hint') }}</p>
                        </div>
                        <form action="{{ route('admin.maintenance.toggle') }}" method="POST" class="m-0">
                            @csrf
                            <button type="submit"
                                class="btn btn-{{ $maintenanceActive ? 'warning' : 'outline-success' }}"
                                title="{{ __('messages.dashboard.maintenance_toggle') }}">
                                <i class="bi bi-{{ $maintenanceActive ? 'tools' : 'check-circle' }}"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        @if(auth()->user()->isSuperAdmin())
        <div class="col-md-4 mb-3">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">{{ __('messages.dashboard.export_import') }}</h5>
                    <div class="d-flex flex-column gap-2">
                        <a href="{{ route('admin.settings.export') }}" class="btn btn-primary btn-sm">
                            <i class="bi bi-download me-1"></i>{{ __('messages.dashboard.export_btn') }}
                        </a>
                        <form action="{{ route('admin.settings.import') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="input-group input-group-sm">
                                <input type="file" class="form-control" name="settings_file" accept=".centralcorp" required>
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-upload me-1"></i>{{ __('messages.dashboard.import_btn') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>

    {{-- Serveurs en ligne --}}
    @if(!empty($serverStatuses))
    <div class="row mb-4">
        @foreach($serverStatuses as $server)
        <div class="col-md-4 mb-3">
            <div class="card h-100 border-{{ $server['online'] ? 'success' : 'danger' }}">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="card-title mb-1">{{ $server['name'] }}</h6>
                            <span class="badge bg-{{ $server['online'] ? 'success' : 'danger' }}">
                                {{ $server['online'] ? __('messages.dashboard.server_online') : __('messages.dashboard.server_offline') }}
                            </span>
                        </div>
                        <i class="bi bi-{{ $server['online'] ? 'wifi' : 'wifi-off' }} fs-3 text-{{ $server['online'] ? 'success' : 'danger' }} opacity-75"></i>
                    </div>
                    @if($server['online'])
                    <div class="mt-2">
                        <small class="text-muted">
                            <i class="bi bi-people-fill me-1"></i>{{ $server['players'] ?? 0 }} / {{ $server['max_players'] ?? '?' }}
                            @if($server['latency'])
                                <span class="ms-2"><i class="bi bi-speedometer2 me-1"></i>{{ $server['latency'] }}ms</span>
                            @endif
                        </small>
                    </div>
                    @endif
                    <div class="mt-1">
                        <small class="text-muted">{{ $server['ip'] }}:{{ $server['port'] }}</small>
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- Notes de version --}}
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">{{ __('messages.dashboard.release_notes') }}</h5>
                    <div class="list-group list-group-flush">
                        @if(isset($releases) && count($releases) > 0)
                            @foreach($releases as $release)
                                <div class="list-group-item px-0">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1">
                                            <a href="{{ $release->link }}" target="_blank" class="text-decoration-none">
                                                {{ __('messages.dashboard.version') }} {{ $release->title }}
                                            </a>
                                        </h6>
                                        <small class="text-muted">{{ $release->date }}</small>
                                    </div>
                                    <p class="mb-1 text-muted small">{{ $release->description }}</p>
                                </div>
                            @endforeach
                        @else
                            <p class="text-muted mb-0">{{ __('messages.dashboard.no_notes') }}</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show position-fixed bottom-0 end-0 m-3" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show position-fixed bottom-0 end-0 m-3" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif
@endsection
