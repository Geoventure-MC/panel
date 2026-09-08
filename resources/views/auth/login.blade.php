<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('messages.auth.login') }} - {{ config('app.name', 'CentralCorp Panel') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css">
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }

        body {
            background-color: #212529;
            min-height: 100vh;
        }

        .login-card {
            max-width: 400px;
        }

        .form-control {
            background-color: #2b3035;
            border-color: #495057;
        }

        .form-control:focus {
            background-color: #343a40;
            border-color: #3b7ddd;
            box-shadow: 0 0 0 0.2rem rgba(59, 125, 221, .25);
        }

        .theme-btn {
            position: fixed;
            top: 1rem;
            right: 1rem;
        }

        [data-bs-theme="light"] body {
            background-color: #f5f7fb;
        }

        [data-bs-theme="light"] .form-control {
            background-color: #fff;
            border-color: #dee2e6;
        }

        [data-bs-theme="light"] .form-control:focus {
            background-color: #fff;
        }
    </style>
</head>

<body class="d-flex align-items-center justify-content-center py-4">
    <button class="btn btn-outline-secondary theme-btn" onclick="toggleTheme()"
        title="{{ __('messages.auth.change_theme') }}">
        <i class="bi bi-moon-stars" id="themeIcon"></i>
    </button>

    <div class="container">
        <div class="login-card mx-auto">
            <!-- Header -->
            <div class="text-center mb-4">
                <a href="{{ url('/') }}">
                    <img src="https://centralcorp.github.io/img/panel.png"
                        style="width: 100%; max-width: 250px; height: auto">
                </a>
                <h1 class="h4 fw-bold mb-1">{{ config('app.name', 'CentralCorp Panel') }}</h1>
                <p class="text-secondary small mb-0">{{ __('messages.auth.admin_panel') }}</p>
            </div>

            <!-- Login Card -->
            <div class="card border-secondary">
                <div class="card-body p-4">
                    <h2 class="h5 text-center mb-1">{{ __('messages.auth.login_title') }}</h2>
                    <p class="text-secondary small text-center mb-4">{{ __('messages.auth.login_subtitle') }}</p>

                    @if($errors->any())
                        <div class="alert alert-danger py-2 small">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            @foreach($errors->all() as $error)
                                {{ $error }}
                            @endforeach
                        </div>
                    @endif

                    <form method="POST" action="{{ route('login') }}">
                        @csrf

                        <div class="mb-3">
                            <label for="email" class="form-label small">{{ __('messages.auth.email_address') }}</label>
                            <input type="email" class="form-control @error('email') is-invalid @enderror" id="email"
                                name="email" value="{{ old('email') }}" required autocomplete="email" autofocus
                                placeholder="exemple@email.com">
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label small">{{ __('messages.auth.password') }}</label>
                            <input type="password" class="form-control @error('password') is-invalid @enderror"
                                id="password" name="password" required autocomplete="current-password"
                                placeholder="••••••••">
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                                <label class="form-check-label small"
                                    for="remember">{{ __('messages.auth.remember_me') }}</label>
                            </div>
                            @if (Route::has('password.request'))
                                <a class="small text-primary text-decoration-none" href="{{ route('password.request') }}">
                                    {{ __('messages.auth.forgot_password') }}
                                </a>
                            @endif
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-box-arrow-in-right me-2"></i>{{ __('messages.auth.login_btn') }}
                        </button>
                    </form>

                    @php
                        // La page de connexion doit s'afficher même sur une
                        // base pas encore migrée (première installation) :
                        // sans ce garde-fou, la colonne manquante ferait une
                        // 500 avant même de pouvoir se connecter.
                        $ssoOn = rescue(fn () => (bool) \App\Models\OptionsGeneral::first()?->sso_enabled, false, false);
                    @endphp
                    @if ($ssoOn)
                        {{-- Connexion déléguée au site : le mot de passe local
                             reste au-dessus, comme porte de secours si le site
                             est indisponible. --}}
                        <div class="d-flex align-items-center my-3">
                            <hr class="flex-grow-1">
                            <span class="px-2 text-secondary small">{{ __('messages.auth.or') }}</span>
                            <hr class="flex-grow-1">
                        </div>
                        <a href="{{ route('sso.redirect') }}" class="btn btn-outline-success w-100">
                            <i class="bi bi-globe2 me-2"></i>{{ __('messages.sso.login_with_site') }}
                        </a>
                    @endif
                </div>
            </div>

            <p class="text-secondary small text-center mt-4 mb-0">
                &copy; {{ date('Y') }} {{ config('app.name', 'CentralCorp') }}
            </p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleTheme() {
            const html = document.documentElement;
            const icon = document.getElementById('themeIcon');
            const isDark = html.getAttribute('data-bs-theme') === 'dark';

            html.setAttribute('data-bs-theme', isDark ? 'light' : 'dark');
            icon.className = isDark ? 'bi bi-sun' : 'bi bi-moon-stars';
            localStorage.setItem('theme', isDark ? 'light' : 'dark');
        }

        // Load saved theme
        (function () {
            const saved = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-bs-theme', saved);
            document.getElementById('themeIcon').className = saved === 'dark' ? 'bi bi-moon-stars' : 'bi bi-sun';
        })();
    </script>
</body>

</html>
