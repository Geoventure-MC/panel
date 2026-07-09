<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('messages.two_factor.challenge_title') }} - {{ config('app.name', 'CentralCorp Panel') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css">
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background-color: #212529; min-height: 100vh; }
        .login-card { max-width: 400px; }
        .form-control { background-color: #2b3035; border-color: #495057; }
        .form-control:focus { background-color: #343a40; border-color: #3b7ddd; box-shadow: 0 0 0 0.2rem rgba(59, 125, 221, .25); }
    </style>
</head>

<body class="d-flex align-items-center justify-content-center py-4">
    <div class="container">
        <div class="login-card mx-auto">
            <div class="text-center mb-4">
                <h1 class="h4 fw-bold mb-1"><i class="bi bi-shield-lock"></i> {{ config('app.name', 'CentralCorp Panel') }}</h1>
                <p class="text-secondary small mb-0">{{ __('messages.two_factor.challenge_subtitle') }}</p>
            </div>

            <div class="card border-secondary">
                <div class="card-body p-4">
                    <h2 class="h5 text-center mb-4">{{ __('messages.two_factor.challenge_title') }}</h2>

                    @if($errors->any())
                        <div class="alert alert-danger py-2 small">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            @foreach($errors->all() as $error)
                                {{ $error }}
                            @endforeach
                        </div>
                    @endif

                    <form method="POST" action="{{ route('two-factor.verify') }}">
                        @csrf
                        <div class="mb-3">
                            <label for="code" class="form-label small">{{ __('messages.two_factor.challenge_label') }}</label>
                            <input type="text" class="form-control text-center fs-5 @error('code') is-invalid @enderror"
                                id="code" name="code" required autofocus autocomplete="one-time-code"
                                inputmode="numeric" placeholder="123 456">
                            <div class="form-text small">{{ __('messages.two_factor.challenge_hint') }}</div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">{{ __('messages.two_factor.challenge_submit') }}</button>
                    </form>

                    <div class="text-center mt-3">
                        <a class="small text-secondary text-decoration-none" href="{{ route('login') }}">
                            {{ __('messages.two_factor.back_to_login') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>
