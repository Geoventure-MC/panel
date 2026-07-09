@extends('layouts.admin')

@section('title', __('messages.two_factor.title'))
@section('page-title', __('messages.two_factor.title'))

@section('content')
<div class="row">
    <div class="col-lg-8">

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        {{-- Codes de récupération : affichés UNE SEULE fois, juste après l'activation. --}}
        @if($freshRecoveryCodes)
            <div class="card mb-3 border-warning">
                <div class="card-header bg-warning-subtle">
                    <h5 class="card-title mb-0"><i class="bi bi-life-preserver"></i> {{ __('messages.two_factor.recovery_title') }}</h5>
                </div>
                <div class="card-body">
                    <p class="text-danger fw-semibold mb-2">{{ __('messages.two_factor.recovery_warning') }}</p>
                    <div class="row">
                        @foreach($freshRecoveryCodes as $code)
                            <div class="col-6 col-md-3 mb-2"><code class="fs-6">{{ $code }}</code></div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="bi bi-shield-lock"></i> {{ __('messages.two_factor.title') }}</h5>
            </div>
            <div class="card-body">

                @if($enabled)
                    <p class="text-success"><i class="bi bi-check-circle-fill"></i> {{ __('messages.two_factor.status_enabled') }}</p>
                    <p class="text-muted small">{{ __('messages.two_factor.disable_hint') }}</p>
                    <form method="POST" action="{{ route('admin.two-factor.disable') }}" class="row g-2" style="max-width: 420px">
                        @csrf
                        <div class="col-8">
                            <input type="password" name="password" class="form-control @error('password') is-invalid @enderror"
                                   placeholder="{{ __('messages.two_factor.password_placeholder') }}" required>
                        </div>
                        <div class="col-4">
                            <button class="btn btn-outline-danger w-100">{{ __('messages.two_factor.disable') }}</button>
                        </div>
                    </form>

                @elseif($pendingSecret)
                    <p>{{ __('messages.two_factor.setup_step1') }}</p>
                    <div class="p-3 bg-body-tertiary rounded mb-3">
                        <div class="mb-2"><strong>{{ __('messages.two_factor.secret_label') }}</strong>
                            <code class="fs-5 user-select-all">{{ trim(chunk_split($pendingSecret, 4, ' ')) }}</code>
                        </div>
                        <div class="small text-muted text-break">
                            <strong>otpauth :</strong> <span class="user-select-all">{{ $otpauthUri }}</span>
                        </div>
                    </div>
                    <p>{{ __('messages.two_factor.setup_step2') }}</p>
                    <form method="POST" action="{{ route('admin.two-factor.confirm') }}" class="row g-2" style="max-width: 420px">
                        @csrf
                        <div class="col-7">
                            <input type="text" name="code" class="form-control @error('code') is-invalid @enderror"
                                   inputmode="numeric" autocomplete="one-time-code" placeholder="123 456" required autofocus>
                        </div>
                        <div class="col-5">
                            <button class="btn btn-primary w-100">{{ __('messages.two_factor.confirm') }}</button>
                        </div>
                    </form>
                    <form method="POST" action="{{ route('admin.two-factor.cancel') }}" class="mt-2">
                        @csrf
                        <button class="btn btn-link btn-sm text-muted p-0">{{ __('messages.two_factor.cancel') }}</button>
                    </form>

                @else
                    <p class="text-muted"><i class="bi bi-circle"></i> {{ __('messages.two_factor.status_disabled') }}</p>
                    <p>{{ __('messages.two_factor.intro') }}</p>
                    <form method="POST" action="{{ route('admin.two-factor.begin') }}">
                        @csrf
                        <button class="btn btn-primary"><i class="bi bi-shield-plus"></i> {{ __('messages.two_factor.enable') }}</button>
                    </form>
                @endif

            </div>
        </div>
    </div>
</div>
@endsection
