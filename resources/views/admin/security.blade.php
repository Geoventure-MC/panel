@extends('layouts.admin')

@section('title', __('messages.security.title'))

@section('content')
    @if(session('success'))
        <div class="alert alert-success d-flex align-items-center" role="alert">
            <i class="fas fa-check-circle me-2"></i> {{ session('success') }}
        </div>
    @endif

    <div class="container-fluid p-0">
        <h2 class="mb-4 fw-bold">{{ __('messages.security.header') }}</h2>

        {{-- Widget de bascule rapide de la maintenance --}}
        <div class="card shadow-sm border-{{ $securityOptions->maintenance ? 'warning' : 'success' }} mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                    <div>
                        <h5 class="card-title mb-1">{{ __('messages.maintenance.title') }}</h5>
                        <span class="badge bg-{{ $securityOptions->maintenance ? 'warning text-dark' : 'success' }}">
                            {{ $securityOptions->maintenance ? __('messages.maintenance.state_on') : __('messages.maintenance.state_off') }}
                        </span>
                        <p class="text-muted small mb-0 mt-1">{{ __('messages.maintenance.hint') }}</p>
                    </div>
                    <form action="{{ route('admin.maintenance.toggle') }}" method="POST" class="m-0">
                        @csrf
                        <button type="submit" class="btn btn-{{ $securityOptions->maintenance ? 'warning' : 'outline-success' }}">
                            <i class="fas fa-power-off me-1"></i>
                            {{ $securityOptions->maintenance ? __('messages.maintenance.disable') : __('messages.maintenance.enable') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body">
                <form action="{{ route('admin.security.update') }}" method="POST">
                    @csrf

                    <div class="form-check form-switch mb-4">
                        <input type="hidden" name="maintenance" value="0">
                        <input type="checkbox" class="form-check-input" id="maintenance" name="maintenance" value="1" 
                               {{ $securityOptions->maintenance ? 'checked' : '' }}>
                        <label class="form-check-label ms-2" for="maintenance">
                            {{ __('messages.security.maintenance_enable') }}
                            <i class="fas fa-tools ms-1 text-muted"></i>
                            <br>
                            <small class="text-muted">{{ __('messages.security.maintenance_desc') }}</small>
                        </label>
                    </div>

                    <div class="mb-3">
                        <label for="maintenance_message" class="form-label fw-semibold">{{ __('messages.security.maintenance_msg') }}</label>
                        <textarea class="form-control" id="maintenance_message" name="maintenance_message" rows="3" required
                                  maxlength="255"
                                  placeholder="{{ __('messages.security.maintenance_placeholder') }}">{{ $securityOptions->maintenance_message }}</textarea>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-save me-1"></i> {{ __('messages.common.update') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
@endsection
