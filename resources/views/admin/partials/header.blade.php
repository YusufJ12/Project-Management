<!-- Begin Header -->
<header class="app-header" id="appHeader">
    <div class="container-fluid w-100">
        <div class="d-flex align-items-center">
            <div class="me-auto">
                <div class="d-inline-flex align-items-center gap-3">
                    <button type="button"
                        class="vertical-toggle btn btn-light-light text-muted icon-btn fs-5 rounded-pill"
                        id="toggleSidebar">
                        <i class="bi bi-arrow-bar-left header-icon"></i>
                    </button>
                    <div class="d-none d-md-flex align-items-center gap-2">
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-1 fs-12">
                            <i class="bi bi-shield-lock-fill me-1"></i> Central Control Plane
                        </span>
                    </div>
                </div>
            </div>
            <div class="flex-shrink-0 d-flex align-items-center gap-2">
                <div class="dark-mode-btn" id="toggleMode">
                    <button class="btn header-btn active" id="lightModeBtn" title="Light Mode">
                        <i class="bi bi-brightness-high"></i>
                    </button>
                    <button class="btn header-btn" id="darkModeBtn" title="Dark Mode">
                        <i class="bi bi-moon-stars"></i>
                    </button>
                </div>

                @auth
                    @php
                        $user = Auth::user();
                        $userName = $user->name;
                        $initials = collect(explode(' ', $userName))
                            ->map(fn($w) => strtoupper(substr($w, 0, 1)))
                            ->take(2)
                            ->implode('');
                        $avatarUrl = $user->avatar_url ? asset('storage/' . ltrim(preg_replace('#^/?storage/#', '', $user->avatar_url), '/')) : null;
                    @endphp
                    <div class="dropdown pe-dropdown-mega">
                        <button class="header-profile-btn btn gap-2 text-start d-flex align-items-center" type="button" data-bs-toggle="dropdown"
                            aria-expanded="false">
                            @if($avatarUrl)
                                <img src="{{ $avatarUrl }}" alt="{{ $userName }}" style="width: 38px; height: 38px; border-radius: 50%; object-fit: cover; border: 1px solid #e2e8f0;">
                            @else
                                <div class="bg-primary text-white" style="width: 38px; height: 38px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 14px;">
                                    {{ $initials ?: 'SA' }}
                                </div>
                            @endif
                            <div class="d-none d-lg-block pe-1">
                                <span class="d-block mb-0 fs-13 fw-semibold">{{ $user->name }}</span>
                                <span class="d-block mb-0 fs-12 text-muted text-capitalize">{{ str_replace('_', ' ', $user->role ?? 'Super Admin') }}</span>
                            </div>
                        </button>
                        <div class="dropdown-menu dropdown-mega-sm header-dropdown-menu dropdown-menu-end p-3 shadow border">
                            <div class="border-bottom pb-2 mb-2 d-flex align-items-center gap-2">
                                <div>
                                    <h6 class="mb-0 lh-base fw-semibold">{{ $user->name }}</h6>
                                    <p class="mb-0 fs-12 text-muted">{{ $user->email }}</p>
                                </div>
                            </div>
                            <ul class="list-unstyled mb-0 pt-1">
                                <li class="mb-1">
                                    <a href="{{ route('admin.profile.edit') }}" class="dropdown-item py-2 rounded">
                                        <i class="ri-user-settings-line me-2 text-primary"></i> Profil & Keamanan
                                    </a>
                                </li>
                                <li>
                                    <form method="POST" action="{{ route('admin.logout') }}">
                                        @csrf
                                        <button type="submit" class="dropdown-item py-2 text-danger rounded">
                                            <i class="bi bi-box-arrow-right me-2"></i> Keluar
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    </div>
                @endauth
            </div>
        </div>
    </div>
</header>
<!-- END Header -->

