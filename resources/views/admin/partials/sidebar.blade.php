<aside class="pe-app-sidebar" id="sidebar">
    <div class="pe-app-sidebar-logo d-flex align-items-center position-relative">
        <a href="{{ route('admin.dashboard') }}" class="sidebar-brand-link text-decoration-none d-flex align-items-center gap-2 w-100 px-3">
            <img src="{{ asset('admin-assets/images/ns.png') }}" alt="NexSphere Logo" class="sidebar-brand-icon" style="height: 32px; width: 32px; object-fit: contain; flex-shrink: 0;">
            <div class="sidebar-brand-text d-flex flex-column text-truncate">
                <span class="lh-1 text-primary fw-bold fs-16">MASTER<span class="text-body">HUB</span></span>
                <span class="fs-10 text-muted fw-semibold ls-1">NEXSPHERE CONSOLE</span>
            </div>
        </a>
    </div>
    <nav class="pe-app-sidebar-menu nav nav-pills" data-simplebar id="sidebar-simplebar">
        <ul class="pe-main-menu list-unstyled">
            <li class="pe-menu-title">Ringkasan</li>
            <li class="pe-slide">
                <a href="{{ route('admin.dashboard') }}"
                    class="pe-nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                    <i class="ri-dashboard-3-line pe-nav-icon"></i>
                    <span class="pe-nav-content">Dashboard</span>
                </a>
            </li>

            <li class="pe-menu-title">Manajemen Tenant & Lisensi</li>
            <li class="pe-slide">
                <a href="{{ route('admin.tenants.index') }}"
                    class="pe-nav-link {{ request()->routeIs('admin.tenants.*') ? 'active' : '' }}">
                    <i class="ri-building-4-line pe-nav-icon"></i>
                    <span class="pe-nav-content">Direktori Klien (Tenants)</span>
                </a>
            </li>
            <li class="pe-slide">
                <a href="{{ route('admin.packages.index') }}"
                    class="pe-nav-link {{ request()->routeIs('admin.packages.*') ? 'active' : '' }}">
                    <i class="ri-stack-line pe-nav-icon"></i>
                    <span class="pe-nav-content">Paket & Modul HRIS</span>
                </a>
            </li>
            <li class="pe-slide">
                <a href="{{ route('admin.subscriptions.index') }}"
                    class="pe-nav-link {{ request()->routeIs('admin.subscriptions.*') ? 'active' : '' }}">
                    <i class="ri-shield-check-line pe-nav-icon"></i>
                    <span class="pe-nav-content">Status Langganan</span>
                </a>
            </li>

            <li class="pe-menu-title">Billing & Keuangan</li>
            <li class="pe-slide">
                <a href="{{ route('admin.invoices.index') }}"
                    class="pe-nav-link {{ request()->routeIs('admin.invoices.*') ? 'active' : '' }}">
                    <i class="ri-file-list-3-line pe-nav-icon"></i>
                    <span class="pe-nav-content">Invoices & Tagihan</span>
                </a>
            </li>

            <li class="pe-menu-title">Telemetri & Keamanan</li>
            <li class="pe-slide">
                <a href="{{ route('admin.telemetries.index') }}"
                    class="pe-nav-link {{ request()->routeIs('admin.telemetries.*') ? 'active' : '' }}">
                    <i class="ri-heart-pulse-line pe-nav-icon"></i>
                    <span class="pe-nav-content">Heartbeat Node VPS</span>
                </a>
            </li>
            <li class="pe-slide">
                <a href="{{ route('admin.audit-logs.index') }}"
                    class="pe-nav-link {{ request()->routeIs('admin.audit-logs.*') ? 'active' : '' }}">
                    <i class="ri-lock-password-line pe-nav-icon"></i>
                    <span class="pe-nav-content">Audit Vault Kredensial</span>
                </a>
            </li>

            <li class="pe-menu-title">Sistem</li>
            <li class="pe-slide">
                <a href="{{ route('admin.users.index') }}"
                    class="pe-nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                    <i class="ri-admin-line pe-nav-icon"></i>
                    <span class="pe-nav-content">Admin Operator</span>
                </a>
            </li>
            <li class="pe-slide">
                <a href="{{ route('admin.profile.edit') }}"
                    class="pe-nav-link {{ request()->routeIs('admin.profile.*') ? 'active' : '' }}">
                    <i class="ri-user-settings-line pe-nav-icon"></i>
                    <span class="pe-nav-content">Profil Saya</span>
                </a>
            </li>
        </ul>
    </nav>
</aside>

