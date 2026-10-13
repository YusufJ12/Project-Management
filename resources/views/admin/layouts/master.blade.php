<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8" />
    <title>@yield('title', 'Admin Panel | NexSphere')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta content="Admin Panel NexSphere Studio" name="description" />
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Apply theme IMMEDIATELY to prevent flash -->
    <script>
        (function() {
            var theme = localStorage.getItem('data-bs-theme') || 'light';
            if (theme === 'auto') {
                theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            }
            document.documentElement.setAttribute('data-bs-theme', theme);

            var serverThemeColor = @json($siteSetting->admin_theme_color ?? 'default');
            document.documentElement.setAttribute('data-theme-colors', serverThemeColor);
            localStorage.setItem('data-theme-colors', serverThemeColor);

            var settings = ['data-layout', 'data-sidebar-size', 'data-sidebar-color', 'data-sidebar'];
            settings.forEach(function(attr) {
                var val = localStorage.getItem(attr);
                if (val) document.documentElement.setAttribute(attr, val);
            });
        })();
    </script>

    <script type="module" src="{{ asset('admin-assets/js/layout-setup.js') }}"></script>
    @php
        $adminFavicon = !empty($siteSetting?->favicon)
            ? asset('storage/' . ltrim($siteSetting->favicon, '/'))
            : asset('admin-assets/images/ns.png');
    @endphp
    <link rel="shortcut icon" href="{{ $adminFavicon }}">
    <link rel="icon" type="image/png" href="{{ $adminFavicon }}">

    @yield('css')
    @stack('styles')
    @include('admin.partials.head-css')

    <style>
        /* Complete reset & proper flex column for #sidebar */
        #sidebar {
            display: flex !important;
            flex-direction: column !important;
            position: fixed !important;
            top: 0;
            bottom: 0;
            left: 0;
            height: 100vh !important;
            z-index: 1000 !important;
            background: var(--pe-app-sidebar-bg, #ffffff) !important;
            border-right: 1px solid var(--pe-border-color, #e2e8f0) !important;
            transition: width 0.22s cubic-bezier(0.4, 0, 0.2, 1), transform 0.22s cubic-bezier(0.4, 0, 0.2, 1) !important;
            --sidebar-hover-accent: var(--pe-primary, #00aaad);
            --sidebar-hover-bg: var(--pe-primary-bg-subtle, rgba(0, 170, 173, .09));
            --sidebar-icon-glow: 0 0 10px var(--pe-primary-border-subtle, rgba(0, 170, 173, .35));
        }

        #sidebar .pe-app-sidebar-logo {
            position: relative !important;
            top: auto !important;
            left: auto !important;
            flex-shrink: 0 !important;
            height: 70px !important;
            min-height: 70px !important;
            max-height: 70px !important;
            width: 100% !important;
            border-bottom: 1px solid var(--pe-border-color, #e2e8f0) !important;
            border-right: none !important;
            display: flex !important;
            align-items: center !important;
            z-index: 10 !important;
        }

        #sidebar #sidebar-simplebar {
            flex: 1 1 auto !important;
            height: calc(100vh - 70px) !important;
            overflow-y: auto !important;
            overflow-x: hidden !important;
            padding-top: 6px !important;
        }

        #sidebar .pe-main-menu {
            padding: 0 !important;
            margin: 0 !important;
        }

        #sidebar .pe-app-sidebar-menu .pe-nav-link {
            transition: color .16s ease, background-color .16s ease, box-shadow .16s ease, transform .16s ease;
        }

        #sidebar .pe-app-sidebar-menu .pe-nav-link:hover,
        #sidebar .pe-app-sidebar-menu .pe-nav-link.active {
            color: var(--sidebar-hover-accent);
            background-color: var(--sidebar-hover-bg);
        }

        #sidebar .pe-app-sidebar-menu .pe-nav-link:hover .pe-nav-icon,
        #sidebar .pe-app-sidebar-menu .pe-nav-link.active .pe-nav-icon {
            color: var(--sidebar-hover-accent);
            filter: drop-shadow(var(--sidebar-icon-glow));
        }

        [data-bs-theme="dark"] #sidebar {
            --sidebar-hover-bg: var(--pe-primary-bg-subtle, rgba(0, 170, 173, .16));
            --sidebar-icon-glow: 0 0 12px var(--pe-primary-border-subtle, rgba(0, 210, 214, .45));
        }

        /* Maximized Mode: 260px wide */
        :root:not([data-sidebar="icon"]) #sidebar,
        [data-sidebar="default"] #sidebar {
            width: 260px !important;
        }

        @media (min-width: 992px) {
            :root:not([data-sidebar="icon"]) .app-header,
            [data-sidebar="default"] .app-header {
                left: 260px !important;
                transition: left 0.22s cubic-bezier(0.4, 0, 0.2, 1) !important;
            }

            :root:not([data-sidebar="icon"]) .app-wrapper,
            :root:not([data-sidebar="icon"]) .footer,
            [data-sidebar="default"] .app-wrapper,
            [data-sidebar="default"] .footer {
                margin-inline-start: 260px !important;
                transition: margin-left 0.22s cubic-bezier(0.4, 0, 0.2, 1), margin-inline-start 0.22s cubic-bezier(0.4, 0, 0.2, 1) !important;
            }
        }

        /* Minimized Mode: 75px wide (Icon dock) */
        [data-sidebar="icon"] body {
            min-height: 100vh !important;
        }

        [data-sidebar="icon"] #sidebar {
            width: 75px !important;
            overflow-x: visible !important;
        }

        [data-sidebar="icon"] #sidebar .pe-app-sidebar-logo {
            position: relative !important;
            width: 75px !important;
            padding: 0 !important;
            justify-content: center !important;
        }

        [data-sidebar="icon"] #sidebar .sidebar-brand-link {
            justify-content: center !important;
            padding: 0 !important;
            width: 100% !important;
        }

        [data-sidebar="icon"] #sidebar .sidebar-brand-icon {
            margin: 0 auto !important;
        }

        [data-sidebar="icon"] #sidebar .sidebar-brand-text {
            display: none !important;
        }

        /* Completely hide section titles and any dividers in icon mode */
        [data-sidebar="icon"] #sidebar .pe-menu-title {
            display: none !important;
            margin: 0 !important;
            padding: 0 !important;
            height: 0 !important;
            line-height: 0 !important;
            border: none !important;
        }
        [data-sidebar="icon"] #sidebar .pe-menu-title::before,
        [data-sidebar="icon"] #sidebar .pe-menu-title::after {
            display: none !important;
            content: none !important;
        }

        [data-sidebar="icon"] #sidebar #sidebar-simplebar {
            overflow: visible !important;
        }

        [data-sidebar="icon"] #sidebar .pe-main-menu > .pe-slide {
            position: relative !important;
            display: flex !important;
            justify-content: center !important;
            margin: 0 auto 6px auto !important;
            width: 100% !important;
        }

        [data-sidebar="icon"] #sidebar .pe-main-menu > .pe-slide > .pe-nav-link {
            width: 44px !important;
            height: 44px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 0 !important;
            margin: 0 auto !important;
            border-radius: 10px !important;
            transition: all 0.18s ease-in-out !important;
        }

        [data-sidebar="icon"] #sidebar .pe-main-menu > .pe-slide > .pe-nav-link > .pe-nav-icon {
            font-size: 20px !important;
            margin: 0 !important;
            line-height: 1 !important;
        }

        /* Tooltip in icon mode */
        [data-sidebar="icon"] #sidebar .pe-main-menu > .pe-slide > .pe-nav-link > .pe-nav-content {
            display: none !important;
        }

        [data-sidebar="icon"] #sidebar .pe-main-menu > .pe-slide:hover > .pe-nav-link > .pe-nav-content {
            display: flex !important;
            align-items: center !important;
            position: absolute !important;
            left: 64px !important;
            top: 50% !important;
            transform: translateY(-50%) !important;
            background: #0f172a !important;
            color: #ffffff !important;
            padding: 6px 14px !important;
            font-size: 12px !important;
            font-weight: 500 !important;
            border-radius: 6px !important;
            white-space: nowrap !important;
            z-index: 1099 !important;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.25) !important;
            pointer-events: none !important;
            animation: pe-tooltip-pop 0.15s cubic-bezier(0.16, 1, 0.3, 1) !important;
        }

        [data-sidebar="icon"] #sidebar .pe-main-menu > .pe-slide:hover > .pe-nav-link > .pe-nav-content::before {
            content: '' !important;
            position: absolute !important;
            right: 100% !important;
            top: 50% !important;
            transform: translateY(-50%) !important;
            border: 5px solid transparent !important;
            border-right-color: #0f172a !important;
        }

        [data-bs-theme="dark"][data-sidebar="icon"] #sidebar .pe-main-menu > .pe-slide:hover > .pe-nav-link > .pe-nav-content {
            background: #1e293b !important;
            border: 1px solid rgba(255, 255, 255, 0.12) !important;
        }

        [data-bs-theme="dark"][data-sidebar="icon"] #sidebar .pe-main-menu > .pe-slide:hover > .pe-nav-link > .pe-nav-content::before {
            border-right-color: #1e293b !important;
        }

        @keyframes pe-tooltip-pop {
            from {
                opacity: 0;
                transform: translateY(-50%) translateX(-6px);
            }
            to {
                opacity: 1;
                transform: translateY(-50%) translateX(0);
            }
        }

        @media (min-width: 992px) {
            [data-sidebar="icon"] .app-header {
                left: 75px !important;
                transition: left 0.22s cubic-bezier(0.4, 0, 0.2, 1) !important;
            }
            [data-sidebar="icon"] .app-wrapper,
            [data-sidebar="icon"] .footer {
                margin-inline-start: 75px !important;
                transition: margin-left 0.22s cubic-bezier(0.4, 0, 0.2, 1), margin-inline-start 0.22s cubic-bezier(0.4, 0, 0.2, 1) !important;
            }
            [data-sidebar="icon"] .app-wrapper {
                min-height: calc(100vh - (var(--pe-app-header-height, 70px) + var(--pe-app-footer-height, 60px))) !important;
            }
        }

        /* Mobile drawer styles */
        @media (max-width: 991.98px) {
            #sidebar {
                position: fixed !important;
                top: 0;
                bottom: 0;
                left: 0;
                width: 260px !important;
                transform: translateX(-100%);
                z-index: 1050;
                transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1) !important;
            }
            #sidebar.show {
                transform: translateX(0);
            }
            .sidebar-backdrop {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(15, 23, 42, 0.55);
                backdrop-filter: blur(2px);
                z-index: 1040;
                display: none;
                opacity: 0;
                transition: opacity 0.2s ease-in-out;
            }
            .sidebar-backdrop.show {
                display: block;
                opacity: 1;
            }
            .app-wrapper, .footer {
                margin-inline-start: 0 !important;
            }
            .app-header {
                left: 0 !important;
            }
        }
    </style>
</head>

<body>

    @include('admin.partials.header')
    @include('admin.partials.sidebar')
    <div class="sidebar-backdrop" id="sidebar-backdrop"></div>

    <main class="app-wrapper">
        <div class="container-fluid">

            @include('admin.partials.page-title')

            {{-- Flash Alert Banners --}}
            @if (session('success'))
                <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-3" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="ri-checkbox-circle-fill fs-18 text-success me-2"></i>
                        <div class="fs-13 fw-medium">{{ session('success') }}</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-3" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="ri-error-warning-fill fs-18 text-danger me-2"></i>
                        <div class="fs-13 fw-medium">{{ session('error') }}</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif
            @if (session('warning'))
                <div class="alert alert-warning alert-dismissible fade show border-0 shadow-sm mb-3" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="ri-alert-fill fs-18 text-warning me-2"></i>
                        <div class="fs-13 fw-medium">{{ session('warning') }}</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif
            @if (session('info'))
                <div class="alert alert-info alert-dismissible fade show border-0 shadow-sm mb-3" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="ri-information-fill fs-18 text-info me-2"></i>
                        <div class="fs-13 fw-medium">{{ session('info') }}</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @yield('content')

        </div>

    </main>

    @include('admin.partials.footer')
    @include('admin.partials.scroll-to-top')

    @include('admin.partials.vendor-scripts')

    @yield('js')
    @stack('scripts')

</body>

</html>
