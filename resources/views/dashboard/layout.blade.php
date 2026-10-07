<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') — {{ __('messages.dashboard.brand') }}</title>
    <meta name="description" content="@yield('description')">
    <meta name="theme-color" content="#0c0d1a">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ __('messages.dashboard.brand') }}">
    <meta property="og:title" content="@yield('title') — {{ __('messages.dashboard.brand') }}">
    <meta property="og:description" content="@yield('description')">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta name="twitter:card" content="summary">
    @hasSection('robots')<meta name="robots" content="@yield('robots')">@endif
    <link rel="stylesheet" href="{{ asset('assets/css/palette.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/dashboard.css') }}">
    <script>document.documentElement.className = 'js';</script>
</head>
<body>
<div class="wrap">
    <header class="top">
        <h1><span>&#9679;</span> <a href="{{ route('players.index') }}" style="color:inherit">{{ __('messages.dashboard.brand') }}</a> — {{ __('messages.dashboard.title') }}</h1>
        <div class="lang" aria-label="{{ __('messages.dashboard.language') }}">
            <a href="{{ route('lang.switch', 'fr') }}" class="{{ app()->getLocale() === 'fr' ? 'on' : '' }}">FR</a>
            <a href="{{ route('lang.switch', 'en') }}" class="{{ app()->getLocale() === 'en' ? 'on' : '' }}">EN</a>
        </div>
    </header>
    @yield('content')
    <footer>{{ __('messages.dashboard.updated') }}</footer>
</div>
</body>
</html>
