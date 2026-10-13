<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8" />
    <title>@yield('title', 'Admin Sign In | NexSphere Studio')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta content="Admin & Dashboards" name="description" />
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <script type="module" src="{{ asset('admin-assets/js/layout-setup.js') }}"></script>
    @php
        $adminFavicon = !empty($siteSetting?->favicon)
            ? asset('storage/' . ltrim($siteSetting->favicon, '/'))
            : asset('admin-assets/images/ns.png');
    @endphp
    <link rel="shortcut icon" href="{{ $adminFavicon }}">
    <link rel="icon" type="image/png" href="{{ $adminFavicon }}">

    @yield('css')
    @include('admin.partials.head-css')
</head>

<body>

    @yield('content')

    @include('admin.partials.vendor-scripts')
    @yield('js')

</body>

</html>
