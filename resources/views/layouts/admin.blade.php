<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') · Nexus</title>
    <link rel="icon" type="image/png" href="{{ asset('assets/img/nexus.png') }}">

    <script>
        (function() {
            const currentTheme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-bs-theme', currentTheme);
            if (currentTheme === 'dark') {
                document.write('<style>#file-manager-css{display:none;} #file-manager-dark-css{display:block;}</style>');
            } else {
                document.write('<style>#file-manager-css{display:block;} #file-manager-dark-css{display:none;}</style>');
            }
        })();
    </script>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{{ asset('assets/vendor/admin.css') }}">
    <link id="file-manager-css" rel="stylesheet" href="{{ asset('vendor/file-manager/css/file-manager.css') }}">
    <link id="file-manager-dark-css" rel="stylesheet" href="{{ asset('vendor/file-manager/css/file-manager-dark.css') }}" disabled>
    {{-- Version dans l'adresse : sinon navigateur et Cloudflare gardent l'ancienne feuille 12 h après une mise à jour. --}}
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v={{ @filemtime(public_path('assets/css/app.css')) ?: config('app.version') }}">
</head>

<body>
<div class="wrapper">
    @php
        $isSuper = auth()->user()->isSuperAdmin();
        // Menu regroupé : [route, motif actif, icône, libellé, réservé super-admin, lien externe].
        $menu = [
            'pilotage' => ['icon' => 'bi-speedometer2', 'items' => [
                ['admin.dashboard.live', 'admin.dashboard.live', 'bi-broadcast', __('messages.sidebar.live_dashboard'), false],
                ['admin.monitor', 'admin.monitor*', 'bi-activity', __('messages.sidebar.monitor'), false],
                ['admin.stats', 'admin.stats', 'bi-bar-chart', __('messages.sidebar.stats'), false],
                ['admin.analytics.overview', 'admin.analytics.*', 'bi-graph-up-arrow', __('analytics.sidebar'), false],
                ['admin.diagnostics', 'admin.diagnostics*', 'bi-heart-pulse', __('messages.sidebar.diagnostics'), false],
            ]],
            'instances' => ['icon' => 'bi-hdd-stack', 'items' => [
                ['admin.server', 'admin.server', 'bi-hdd-network', __('messages.sidebar.server'), true],
                ['admin.loader', 'admin.loader', 'bi-cloud-arrow-down', __('messages.sidebar.loader'), true],
                ['admin.file-manager', 'admin.file-manager', 'bi-folder2-open', __('messages.sidebar.file_manager'), true],
                ['admin.mods', 'admin.mods', 'bi-box', __('messages.sidebar.optional_mods'), true],
                ['admin.community-mods', 'admin.community-mods*', 'bi-puzzle', __('messages.sidebar.community_mods'), false],
                ['admin.ignore', 'admin.ignore', 'bi-slash-circle', __('messages.sidebar.ignore'), true],
                ['admin.whitelist', 'admin.whitelist', 'bi-list-check', __('messages.sidebar.whitelist'), true],
            ]],
            'launcher' => ['icon' => 'bi-rocket-takeoff', 'items' => [
                ['admin.general', 'admin.general', 'bi-sliders', __('messages.sidebar.general'), true],
                ['admin.ui', 'admin.ui', 'bi-display', __('messages.sidebar.ui'), true],
                ['admin.bg', 'admin.bg', 'bi-image', __('messages.sidebar.background'), true],
                ['admin.launcher-content', 'admin.launcher-content*', 'bi-layout-text-window-reverse', __('messages.sidebar.launcher_content'), false],
                ['admin.notifications', 'admin.notifications*', 'bi-megaphone', __('messages.sidebar.notifications'), false],
                ['admin.changelog', 'admin.changelog*', 'bi-journal-text', __('messages.sidebar.changelog'), false],
                ['admin.rpc', 'admin.rpc', 'bi-discord', __('messages.sidebar.rpc'), true],
            ]],
            'jeu' => ['icon' => 'bi-controller', 'items' => [
                ['admin.game-events', 'admin.game-events*', 'bi-calendar-event', __('messages.sidebar.game_events'), true],
                ['admin.scheduled-events', 'admin.scheduled-events', 'bi-calendar-week', __('messages.sidebar.scheduled_events'), false],
                ['admin.game-commands', 'admin.game-commands*', 'bi-joystick', __('messages.sidebar.game_commands'), true],
                ['admin.achievements', 'admin.achievements*', 'bi-award', __('messages.sidebar.achievements'), false],
                ['admin.seasons', 'admin.seasons', 'bi-flag', __('messages.sidebar.seasons'), false],
                ['admin.wonder', 'admin.wonder*', 'bi-buildings', __('messages.sidebar.wonder'), false],
                ['admin.moderation.index', 'admin.moderation.*', 'bi-shield-exclamation', __('moderation.sidebar'), false],
                ['players.index', null, 'bi-trophy', __('messages.dashboard.title'), false, true],
            ]],
            'admin' => ['icon' => 'bi-gear', 'items' => [
                ['admin.users', 'admin.users', 'bi-people', __('messages.sidebar.users'), true],
                ['admin.security', 'admin.security', 'bi-lock', __('messages.sidebar.security_link'), true],
                ['admin.two-factor.index', 'admin.two-factor*', 'bi-shield-lock', __('messages.sidebar.two_factor'), false],
                ['admin.sso', 'admin.sso*', 'bi-key', __('messages.sidebar.sso'), true],
                ['admin.audit.index', 'admin.audit.*', 'bi-clock-history', __('messages.sidebar.audit'), false],
                ['admin.config', 'admin.config', 'bi-gear-wide-connected', __('messages.sidebar.config'), true],
                ['admin.versions', 'admin.versions*', 'bi-boxes', __('versions.sidebar'), true],
                ['admin.mcp', 'admin.mcp*', 'bi-robot', __('mcp.sidebar'), true],
                ['admin.update', 'admin.update', 'bi-arrow-repeat', __('messages.sidebar.update'), true],
            ]],
        ];
        try {
            $navInstances = \App\Models\OptionsServer::orderByDesc('is_default')->orderBy('server_id')->get();
        } catch (\Throwable $e) {
            $navInstances = collect();
        }
    @endphp
    <nav id="sidebar" class="sidebar js-sidebar nexus-sidebar">
        <div class="sidebar-content js-simplebar">
            <a class="sidebar-brand nexus-brand" href="{{ route('admin.index') }}">
                <img src="{{ asset('assets/img/nexus.png') }}" alt="Nexus" class="nexus-brand-logo" width="40" height="40">
                <span class="nexus-brand-text">
                    <strong>Nexus</strong>
                    <small>{{ __('messages.sidebar.brand_sub') }}</small>
                </span>
            </a>

            @if($navInstances->isNotEmpty())
            <div class="nexus-instances">
                @foreach($navInstances as $inst)
                    @php
                        $instSlug = $inst->instance_slug ?: \Illuminate\Support\Str::slug($inst->server_name ?? '');
                        $instLogo = in_array($instSlug, ['geoventure', 'elandor', 'pokeland'], true) ? asset('assets/img/instance_' . $instSlug . '.png') : null;
                    @endphp
                    <a class="nexus-instance" href="{{ $isSuper ? route('admin.server') : '#' }}" title="{{ $inst->server_name }} — {{ $inst->server_ip }}:{{ $inst->server_port }}" style="--inst: {{ $inst->theme_color ?: '#64748b' }}">
                        @if($instLogo)<img src="{{ $instLogo }}" alt="" width="22" height="22">@else<i class="bi bi-hdd"></i>@endif
                        <span>{{ $inst->server_name }}</span>
                        @if(in_array($inst->channel, ['both', 'test'], true))<em class="nexus-chip">{{ __('messages.sidebar.test_chip') }}</em>@endif
                    </a>
                @endforeach
            </div>
            @endif

            <ul class="sidebar-nav nexus-nav">
                @foreach($menu as $key => $group)
                    @php
                        $items = array_values(array_filter($group['items'], fn ($it) => !$it[4] || $isSuper));
                        $open = collect($items)->contains(fn ($it) => $it[1] && request()->routeIs($it[1]));
                    @endphp
                    @continue(empty($items))
                    <li class="sidebar-item nexus-group">
                        <a class="sidebar-link nexus-group-toggle {{ $open ? '' : 'collapsed' }}" data-bs-toggle="collapse" href="#nav-{{ $key }}" role="button" aria-expanded="{{ $open ? 'true' : 'false' }}" aria-controls="nav-{{ $key }}">
                            <i class="bi {{ $group['icon'] }} align-middle"></i>
                            <span class="align-middle">{{ __('messages.sidebar.group_' . $key) }}</span>
                        </a>
                        <ul id="nav-{{ $key }}" class="sidebar-dropdown list-unstyled collapse {{ $open ? 'show' : '' }}">
                            @foreach($items as $it)
                                <li class="sidebar-item">
                                    <a class="sidebar-link {{ $it[1] && request()->routeIs($it[1]) ? 'active' : '' }}" href="{{ route($it[0]) }}" @if(!empty($it[5])) target="_blank" rel="noopener" @endif>
                                        <i class="bi {{ $it[2] }} align-middle"></i> <span class="align-middle">{{ $it[3] }}</span>
                                        @if(!empty($it[5]))<i class="bi bi-box-arrow-up-right nexus-ext"></i>@endif
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </li>
                @endforeach
            </ul>
        </div>
    </nav>

    <div class="main">
        <nav class="navbar navbar-expand bg-body-secondary navbar-bgw">
            <a class="sidebar-toggle js-sidebar-toggle">
                <i class="hamburger align-self-center"></i>
            </a>
            <div class="d-none d-sm-inline-block" bis_skin_checked="1">
                <a href="https://discord.gg/VCmNXHvf77" class="btn btn-outline-primary mx-1" target="_blank" rel="noopener noreferrer">
                    <i class="bi bi-discord"></i>
                    {{ __('messages.navbar.discord') }}
                </a>

            </div>
            <div class="ms-auto d-flex align-items-center">
                <button class="btn btn-outline-secondary" id="themeToggle" data-bs-toggle="tooltip" data-bs-placement="bottom" aria-label="{{ __('messages.navbar.theme_dark') }}" data-bs-original-title="{{ __('messages.navbar.theme_dark') }}">
                    <i id="themeIcon" class="bi bi-moon-stars"></i>
                </button>
                <div class="dropdown ms-2">
                     <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="langDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-translate"></i> {{ strtoupper(app()->getLocale()) }}
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="langDropdown">
                        <li><a class="dropdown-item" href="{{ route('lang.switch', 'fr') }}">Français</a></li>
                        <li><a class="dropdown-item" href="{{ route('lang.switch', 'en') }}">English</a></li>
                    </ul>
                </div>
                <div class="dropdown ms-2">
                    <button class="btn btn-outline-primary dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        {{ Auth::user()->name }}
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                        <li>
                            <a class="dropdown-item" href="{{ route('logout') }}"
                               onclick="event.preventDefault();
                                             document.getElementById('logout-form').submit();">
                                {{ __('messages.navbar.logout') }}
                            </a>
                            <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                                @csrf
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>

        <main class="content">
            <div class="container-fluid">
                <h1 class="mt-4">@yield('page-title')</h1>
                @yield('content')
            </div>
        </main>
        <footer class="bg-body-secondary py-4 mt-4">
                <div class="text-center">
                    <p class="mb-0">{{ __('messages.dashboard.copy_rights') }}</p>
                </div>
            </div>
        </footer>
    </div>
</div>

<script src="{{ asset('assets/vendor/admin.js') }}"></script>
<script src="{{ asset('vendor/file-manager/js/file-manager.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const themeToggle = document.getElementById('themeToggle');
        const themeIcon = document.getElementById('themeIcon');
        const htmlElement = document.documentElement;
        const fileManagerCssLink = document.getElementById('file-manager-css');
        const fileManagerDarkCssLink = document.getElementById('file-manager-dark-css');
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        const currentTheme = localStorage.getItem('theme') || 'light';
        htmlElement.setAttribute('data-bs-theme', currentTheme);
        updateFileManagerCss(currentTheme);

        function updateTooltipAndIcon() {
            const isDarkMode = htmlElement.getAttribute('data-bs-theme') === 'dark';
            if (isDarkMode) {
                themeIcon.classList.replace('bi-moon-stars', 'bi-sun');
                themeToggle.setAttribute('data-bs-original-title', 'Thème clair');
            } else {
                themeIcon.classList.replace('bi-sun', 'bi-moon-stars');
                themeToggle.setAttribute('data-bs-original-title', 'Thème sombre');
            }
            bootstrap.Tooltip.getInstance(themeToggle).setContent({ '.tooltip-inner': themeToggle.getAttribute('data-bs-original-title') });
        }

        function updateFileManagerCss(theme) {
            if (theme === 'dark') {
                fileManagerCssLink.disabled = true;
                fileManagerDarkCssLink.disabled = false;
            } else {
                fileManagerCssLink.disabled = false;
                fileManagerDarkCssLink.disabled = true;
            }
        }

        updateTooltipAndIcon();

        themeToggle.addEventListener('click', function() {
            const isDarkMode = htmlElement.getAttribute('data-bs-theme') === 'dark';

            if (isDarkMode) {
                htmlElement.setAttribute('data-bs-theme', 'light');
                localStorage.setItem('theme', 'light');
            } else {
                htmlElement.setAttribute('data-bs-theme', 'dark');
                localStorage.setItem('theme', 'dark');
            }
            updateTooltipAndIcon();
            updateFileManagerCss(htmlElement.getAttribute('data-bs-theme'));
            bootstrap.Tooltip.getInstance(themeToggle).hide();
        });
    });
</script>
@yield('scripts')
</body>
</html>
