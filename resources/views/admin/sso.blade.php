@extends('layouts.admin')

@section('title', __('messages.sso.title'))
@section('page-title', __('messages.sso.title'))

@section('content')
<div class="row">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h2 class="h5 mb-0"><i class="bi bi-shield-lock me-2"></i>{{ __('messages.sso.title') }}</h2>
                @if ($configured)
                    <span class="badge bg-success">{{ __('messages.sso.state_on') }}</span>
                @else
                    <span class="badge bg-secondary">{{ __('messages.sso.state_off') }}</span>
                @endif
            </div>
            <div class="card-body">
                <p class="text-muted">{{ __('messages.sso.intro') }}</p>

                <div class="alert alert-info small">
                    {{ __('messages.sso.callback_hint') }}<br>
                    <code>{{ $callback }}</code>
                </div>

                <form method="POST" action="{{ route('admin.sso.update') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">{{ __('messages.sso.site_url') }}</label>
                        <input type="url" name="sso_url" class="form-control"
                               value="{{ old('sso_url', optional($options)->sso_url) }}"
                               placeholder="https://geoventure.fr">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ __('messages.sso.client_id') }}</label>
                        <input type="text" name="sso_client_id" class="form-control"
                               value="{{ old('sso_client_id', optional($options)->sso_client_id) }}">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ __('messages.sso.client_secret') }}</label>
                        <input type="password" name="sso_client_secret" class="form-control"
                               autocomplete="new-password"
                               placeholder="{{ $hasSecret ? __('messages.sso.secret_set') : __('messages.sso.secret_empty') }}">
                        <div class="form-text">{{ __('messages.sso.secret_hint') }}</div>
                    </div>

                    <div class="form-check form-switch mb-2">
                        <input type="checkbox" name="sso_enabled" value="1" class="form-check-input" id="sso_enabled"
                               @checked(old('sso_enabled', optional($options)->sso_enabled))>
                        <label class="form-check-label" for="sso_enabled">{{ __('messages.sso.enable') }}</label>
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input type="checkbox" name="sso_auto_provision" value="1" class="form-check-input" id="sso_auto"
                               @checked(old('sso_auto_provision', optional($options)->sso_auto_provision))>
                        <label class="form-check-label" for="sso_auto">{{ __('messages.sso.auto_provision') }}</label>
                        <div class="form-text">{{ __('messages.sso.auto_provision_hint') }}</div>
                    </div>

                    <button class="btn btn-primary">{{ __('messages.common.save') }}</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header"><h2 class="h6 mb-0">{{ __('messages.sso.accounts') }}</h2></div>
            <div class="card-body">
                <div class="display-6">{{ $linked }} <span class="text-muted fs-6">/ {{ $total }}</span></div>
                <p class="text-muted small mb-0">{{ __('messages.sso.linked_hint') }}</p>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2 class="h6 mb-0">{{ __('messages.sso.howto') }}</h2></div>
            <div class="card-body small text-muted">
                <ol class="ps-3 mb-0">
                    <li>{{ __('messages.sso.step1') }}</li>
                    <li>{{ __('messages.sso.step2') }}</li>
                    <li>{{ __('messages.sso.step3') }}</li>
                    <li>{{ __('messages.sso.step4') }}</li>
                </ol>
            </div>
        </div>
    </div>
</div>
@endsection
