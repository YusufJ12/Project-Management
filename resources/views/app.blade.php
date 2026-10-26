<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-layout="vertical" data-sidebar="default" data-bs-theme="light">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title inertia>{{ config('app.name', 'NexSphere Project Management') }}</title>

    <!-- Reverb runtime config injected from server .env -->
    <script>
        window.__reverb = {
            key: @json(config('broadcasting.connections.reverb.key')),
            host: @json(config('broadcasting.connections.reverb.options.host')),
            port: @json(config('broadcasting.connections.reverb.options.port')),
            scheme: @json(config('broadcasting.connections.reverb.options.scheme'))
        };
    </script>

    <!-- Theme Initialization Script to prevent flash -->
    <script>
        (function() {
            var theme = localStorage.getItem('data-bs-theme') || 'light';
            if (theme === 'auto') {
                theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            }
            document.documentElement.setAttribute('data-bs-theme', theme);

            var serverThemeColor = 'default';
            document.documentElement.setAttribute('data-theme-colors', serverThemeColor);
            localStorage.setItem('data-theme-colors', serverThemeColor);

            var settings = ['data-layout', 'data-sidebar-size', 'data-sidebar-color', 'data-sidebar'];
            settings.forEach(function(attr) {
                var val = localStorage.getItem(attr);
                if (val) document.documentElement.setAttribute(attr, val);
            });
        })();
    </script>

    <link rel="shortcut icon" href="{{ asset('admin-assets/images/ns.png') }}">
    <link rel="icon" type="image/png" href="{{ asset('admin-assets/images/ns.png') }}">

    <!-- MasterHub Admin Assets -->
    <link rel="stylesheet" href="{{ asset('admin-assets/libs/simplebar/simplebar.min.css') }}">
    <link href="{{ asset('admin-assets/css/bootstrap.min.css') }}" id="bootstrap-style" rel="stylesheet" type="text/css">
    <link href="{{ asset('admin-assets/css/icons.min.css') }}" rel="stylesheet" type="text/css">
    <link rel="stylesheet" href="{{ asset('admin-assets/libs/font-awesome/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('admin-assets/libs/sweetalert2/sweetalert2.min.css') }}">
    <link href="{{ asset('admin-assets/css/app.min.css') }}" id="app-style" rel="stylesheet" type="text/css">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>

<body>
    @inertia

    <!-- Vendor Scripts -->
    <script src="{{ asset('admin-assets/libs/jquery/jquery-3.7.1.min.js') }}"></script>
    <script src="{{ asset('admin-assets/libs/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('admin-assets/libs/simplebar/simplebar.min.js') }}"></script>
    <script src="{{ asset('admin-assets/libs/sweetalert2/sweetalert2.all.min.js') }}"></script>
</body>

</html>

