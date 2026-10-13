<div class="d-flex align-items-center mt-2 mb-3">
    <h5 class="mb-0 flex-grow-1 fw-bold">@yield('pagetitle', 'Dashboard')</h5>
    <div class="flex-shrink-0">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb justify-content-end mb-0">
                <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
                @hasSection('title-sub')
                    <li class="breadcrumb-item"><a href="javascript:void(0)">@yield('title-sub')</a></li>
                @endif
                <li class="breadcrumb-item active" aria-current="page">@yield('pagetitle', 'Dashboard')</li>
            </ol>
        </nav>
    </div>
</div>

