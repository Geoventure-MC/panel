@extends('layouts.admin')

@section('title', __('messages.general.title'))
@section('page-title', __('messages.general.title'))

@section('content')
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong>{{ __('messages.common.errors_occurred') }}</strong>
            <ul class="mb-0 mt-2">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form action="{{ route('admin.general.update') }}" method="POST">
                @csrf

                <fieldset class="border p-3 mb-4 rounded">
                    <legend class="float-none w-auto px-2">{{ __('messages.general.features') }}</legend>
                    <div class="row row-cols-1 row-cols-md-2 g-3">
                        @php
                            $switches = [
                                ['id' => 'mods_enabled', 'label' => __('messages.general.mods_enabled')],
                                ['id' => 'file_verification', 'label' => __('messages.general.file_verification')],
                                ['id' => 'embedded_java', 'label' => __('messages.general.embedded_java')],
                                ['id' => 'email_verified', 'label' => __('messages.general.email_verified')],
                                ['id' => 'role_display', 'label' => __('messages.general.role_display')],
                                ['id' => 'money_display', 'label' => __('messages.general.money_display')],
                            ];
                        @endphp

                        @foreach ($switches as $switch)
                            <div class="col">
                                <div class="form-check form-switch">
                                    <input type="hidden" name="{{ $switch['id'] }}" value="0">
                                    <input type="checkbox"
                                           class="form-check-input"
                                           id="{{ $switch['id'] }}"
                                           name="{{ $switch['id'] }}"
                                           value="1"
                                           {{ old($switch['id'], $options->{$switch['id']}) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="{{ $switch['id'] }}">{{ $switch['label'] }}</label>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </fieldset>

                <fieldset class="border p-3 mb-4 rounded">
                    <legend class="float-none w-auto px-2">{{ __('messages.general.technical_settings') }}</legend>

                    <div class="mb-3">
                        <label for="game_folder_name" class="form-label">{{ __('messages.general.game_folder_name') }}</label>
                        <input type="text"
                               class="form-control"
                               id="game_folder_name"
                               name="game_folder_name"
                               placeholder="lenomdevotredossier"
                               value="{{ old('game_folder_name', $options->game_folder_name) }}">
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="default_min_ram" class="form-label">{{ __('messages.general.min_ram') }}</label>
                            <input type="number"
                                   class="form-control"
                                   id="default_min_ram"
                                   name="min_ram"
                                   placeholder="2048"
                                   min="512"
                                   max="65536"
                                   value="{{ old('min_ram', $options['min_ram'] ?? 2048) }}">
                        </div>

                        <div class="col-md-6">
                            <label for="default_max_ram" class="form-label">{{ __('messages.general.max_ram') }}</label>
                            <input type="number"
                                   class="form-control"
                                   id="default_max_ram"
                                   name="max_ram"
                                   placeholder="4096"
                                   min="512"
                                   max="65536"
                                   value="{{ old('max_ram', $options['max_ram'] ?? 4096) }}">
                        </div>
                    </div>

                    <div class="mt-3">
                        <label for="discord_webhook_url" class="form-label">{{ __('messages.general.discord_webhook') }}</label>
                        <input type="url"
                               class="form-control"
                               id="discord_webhook_url"
                               name="discord_webhook_url"
                               placeholder="https://discord.com/api/webhooks/..."
                               value="{{ old('discord_webhook_url', $options->discord_webhook_url ?? '') }}">
                        <div class="form-text">{{ __('messages.general.discord_webhook_help') }}</div>
                    </div>
                </fieldset>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg">💾 {{ __('messages.common.update') }}</button>
                </div>
            </form>
        </div>
    </div>
@endsection
