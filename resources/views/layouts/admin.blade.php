<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title')</title>

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
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
</head>

<body>
<div class="wrapper">
    <nav id="sidebar" class="sidebar js-sidebar">
        <div class="sidebar-content js-simplebar">
            <a class="sidebar-brand text-white" href="{{ route('admin.index') }}">
                <img src="{{ asset('assets/img/logo.png') }}" alt="Logo" class="sidebar-brand-img">
            </a>
            <ul class="sidebar-nav">
                <li class="sidebar-header">
                        {{ __('messages.sidebar.panel') }}
                    </li>
                    <li class="sidebar-item">
                    <a class="sidebar-link {{ request()->routeIs('admin.users') ? 'active' : '' }}" href="{{ route('admin.users') }}">
                        <i class="bi bi-people align-middle"></i> <span class="align-middle">{{ __('messages.sidebar.users') }}</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link {{ request()->routeIs('admin.config') ? 'active' : '' }}" href="{{ route('admin.config') }}">
                        <i class="bi bi-gear align-middle"></i> <span class="align-middle">{{ __('messages.sidebar.config') }}</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link {{ request()->routeIs('admin.file-manager') ? 'active' : '' }}" href="{{ route('admin.file-manager') }}">
                        <i class="bi bi-folder align-middle"></i> <span class="align-middle">{{ __('messages.sidebar.file_manager') }}</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link {{ request()->routeIs('admin.update') ? 'active' : '' }}" href="{{ route('admin.update') }}">
                        <i class="bi bi-sort-numeric-up-alt align-middle"></i> <span class="align-middle">{{ __('messages.sidebar.update') }}</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link {{ request()->routeIs('admin.notifications*') ? 'active' : '' }}" href="{{ route('admin.notifications') }}">
                        <i class="bi bi-megaphone align-middle"></i> <span class="align-middle">{{ __('messages.sidebar.notifications') }}</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link {{ request()->routeIs('admin.audit') ? 'active' : '' }}" href="{{ route('admin.audit') }}">
                        <i class="bi bi-journal-text align-middle"></i> <span class="align-middle">{{ __('messages.sidebar.audit') }}</span>
                    </a>
                </li>
                <li class="sidebar-header">
                    {{ __('messages.sidebar.configuration') }}
                </li>
                
                <li class="sidebar-item">
                    <a class="sidebar-link {{ request()->routeIs('admin.general') ? 'active' : '' }}" href="{{ route('admin.general') }}">
                        <i class="bi bi-sliders align-middle"></i> <span class="align-middle">{{ __('messages.sidebar.general') }}</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link {{ request()->routeIs('admin.rpc') ? 'active' : '' }}" href="{{ route('admin.rpc') }}">
                        <i class="bi bi-cpu align-middle"></i> <span class="align-middle">{{ __('messages.sidebar.rpc') }}</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link {{ request()->routeIs('admin.server') ? 'active' : '' }}" href="{{ route('admin.server') }}">
                        <i class="bi bi-hdd align-middle"></i> <span class="align-middle">{{ __('messages.sidebar.server') }}</span>
                    </a>
                </li>

                <li class="sidebar-header">
                    {{ __('messages.sidebar.security_header') }}
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link {{ request()->routeIs('admin.security') ? 'active' : '' }}" href="{{ route('admin.security') }}">
                        <i class="bi bi-lock align-middle"></i> <span class="align-middle">{{ __('messages.sidebar.security_link') }}</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link {{ request()->routeIs('admin.whitelist') ? 'active' : '' }}" href="{{ route('admin.whitelist') }}">
                        <i class="bi bi-list-check align-middle"></i> <span class="align-middle">{{ __('messages.sidebar.whitelist') }}</span>
                    </a>
                </li>
                

                <li class="sidebar-header">
                    {{ __('messages.sidebar.client') }}
                </li>
                
                <li class="sidebar-item">
                    <a class="sidebar-link {{ request()->routeIs('admin.mods') ? 'active' : '' }}" href="{{ route('admin.mods') }}">
                        <i class="bi bi-box align-middle"></i> <span class="align-middle">{{ __('messages.sidebar.optional_mods') }}</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link {{ request()->routeIs('admin.loader') ? 'active' : '' }}" href="{{ route('admin.loader') }}">
                        <i class="bi bi-cloud-arrow-down"></i> <span class="align-middle">{{ __('messages.sidebar.loader') }}</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link {{ request()->routeIs('admin.ignore') ? 'active' : '' }}" href="{{ route('admin.ignore') }}">
                        <i class="bi bi-slash align-middle"></i> <span class="align-middle">{{ __('messages.sidebar.ignore') }}</span>
                    </a>
                </li>

                <li class="sidebar-header">
                    {{ __('messages.sidebar.interface') }}
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link {{ request()->routeIs('admin.ui') ? 'active' : '' }}" href="{{ route('admin.ui') }}">
                        <i class="bi bi-display align-middle"></i> <span class="align-middle">{{ __('messages.sidebar.ui') }}</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link {{ request()->routeIs('admin.bg') ? 'active' : '' }}" href="{{ route('admin.bg') }}">
                        <i class="bi bi-image align-middle"></i> <span class="align-middle">{{ __('messages.sidebar.background') }}</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a class="sidebar-link {{ request()->routeIs('admin.launcher-content*') ? 'active' : '' }}" href="{{ route('admin.launcher-content') }}">
                        <i class="bi bi-layout-text-window-reverse align-middle"></i> <span class="align-middle">{{ __('messages.sidebar.launcher_content') }}</span>
                    </a>
                </li>

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

                <a href="https://centralcorp.github.io/" class="btn btn-outline-secondary mx-1" target="_blank" rel="noopener noreferrer">
                    <i class="bi bi-journals"></i>
                    {{ __('messages.navbar.documentation') }}
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
