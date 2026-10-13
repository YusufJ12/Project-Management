<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';

const page = usePage();
const user = computed(() => page.props.auth?.user || { name: 'User', email: '' });
const workspaces = computed(() => page.props.auth?.workspaces || []);
const currentWorkspace = computed(() => page.props.auth?.currentWorkspace || null);

// Theme Management
const currentTheme = ref('light');

const setTheme = (theme) => {
    currentTheme.value = theme;
    document.documentElement.setAttribute('data-bs-theme', theme);
    localStorage.setItem('data-bs-theme', theme);
};

const toggleTheme = () => {
    const nextTheme = currentTheme.value === 'light' ? 'dark' : 'light';
    setTheme(nextTheme);
};

// Sidebar Toggle Management
const isSidebarMinimized = ref(false);
const isMobileSidebarOpen = ref(false);

const toggleSidebar = () => {
    if (typeof window !== 'undefined' && window.innerWidth < 992) {
        isMobileSidebarOpen.value = !isMobileSidebarOpen.value;
    } else {
        isSidebarMinimized.value = !isSidebarMinimized.value;
        const mode = isSidebarMinimized.value ? 'icon' : 'default';
        document.documentElement.setAttribute('data-sidebar', mode);
        localStorage.setItem('data-sidebar', mode);
    }
};

const closeMobileSidebar = () => {
    isMobileSidebarOpen.value = false;
};

const handleSidebarNavClick = (event) => {
    if (typeof window !== 'undefined' && window.innerWidth < 992) {
        if (event.target.closest('a') || event.target.closest('button')) {
            isMobileSidebarOpen.value = false;
        }
    }
};

const handleResize = () => {
    if (typeof window === 'undefined') return;
    if (window.innerWidth >= 992) {
        isMobileSidebarOpen.value = false;
        const savedSidebar = localStorage.getItem('data-sidebar');
        if (savedSidebar === 'icon') {
            isSidebarMinimized.value = true;
            document.documentElement.setAttribute('data-sidebar', 'icon');
        } else {
            isSidebarMinimized.value = false;
            document.documentElement.setAttribute('data-sidebar', 'default');
        }
    } else {
        document.documentElement.removeAttribute('data-sidebar');
    }
};

// Workspace Switch
const switchWorkspace = (workspaceId) => {
    if (workspaceId !== currentWorkspace.value?.id) {
        router.post(`/workspaces/${workspaceId}/switch`);
    }
};

// Logout
const logout = () => {
    router.post('/logout');
};

// Notifications
const notifications = computed(() => page.props.notifications?.list || []);
const unreadNotificationCount = computed(() => page.props.notifications?.unread_count || 0);

const markNotificationRead = (notif) => {
    router.post(`/notifications/${notif.id}/read`, {}, {
        preserveScroll: true,
        only: ['notifications'],
    });
};

const markAllNotificationsRead = () => {
    router.post('/notifications/read-all', {}, {
        preserveScroll: true,
        only: ['notifications'],
    });
};

onMounted(() => {
    // Theme setup
    const savedTheme = localStorage.getItem('data-bs-theme') || 'light';
    currentTheme.value = savedTheme;
    document.documentElement.setAttribute('data-bs-theme', savedTheme);

    // Sidebar setup
    if (window.innerWidth >= 992) {
        const savedSidebar = localStorage.getItem('data-sidebar');
        if (savedSidebar === 'icon') {
            isSidebarMinimized.value = true;
            document.documentElement.setAttribute('data-sidebar', 'icon');
        } else {
            isSidebarMinimized.value = false;
            document.documentElement.setAttribute('data-sidebar', 'default');
        }
    } else {
        document.documentElement.removeAttribute('data-sidebar');
    }

    window.addEventListener('resize', handleResize);
    window.addEventListener('scroll', handleScroll);
});

// Scroll to Top
const showBackToTop = ref(false);
const handleScroll = () => {
    if (typeof window !== 'undefined') {
        showBackToTop.value = window.scrollY > 150;
    }
};

const scrollToTop = () => {
    if (typeof window !== 'undefined') {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
};

onUnmounted(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('resize', handleResize);
        window.removeEventListener('scroll', handleScroll);
    }
});
</script>

<template>
    <div class="pe-layout-root min-vh-100 d-flex flex-column">
        <!-- Header -->
        <header class="app-header" id="appHeader">
            <div class="container-fluid w-100">
                <div class="d-flex align-items-center">
                    <div class="me-auto">
                        <div class="d-inline-flex align-items-center gap-3">
                            <button
                                type="button"
                                @click="toggleSidebar"
                                class="vertical-toggle btn btn-light-light text-muted icon-btn fs-5 rounded-pill"
                                id="toggleSidebar"
                                :title="isSidebarMinimized ? 'Maximize sidebar' : 'Minimize sidebar'"
                            >
                                <i :class="isSidebarMinimized ? 'bi bi-arrow-bar-right header-icon' : 'bi bi-arrow-bar-left header-icon'"></i>
                            </button>

                            <!-- Workspace Switcher Dropdown in Header -->
                            <div v-if="currentWorkspace" class="dropdown">
                                <button
                                    class="btn btn-light border rounded-pill btn-sm d-flex align-items-center gap-2 px-3 py-1 fs-12"
                                    type="button"
                                    data-bs-toggle="dropdown"
                                    aria-expanded="false"
                                >
                                    <i class="ri-community-line text-primary"></i>
                                    <span class="fw-semibold text-truncate" style="max-width: 160px;">{{ currentWorkspace.name }}</span>
                                    <i class="bi bi-chevron-down fs-10 text-muted"></i>
                                </button>
                                <ul class="dropdown-menu shadow border rounded-3 p-2" style="min-width: 220px;">
                                    <li class="dropdown-header fs-11 text-muted text-uppercase fw-bold px-2 py-1">Pilih Ruang Kerja</li>
                                    <li v-for="ws in workspaces" :key="ws.id">
                                        <button
                                            type="button"
                                            @click="switchWorkspace(ws.id)"
                                            class="dropdown-item d-flex align-items-center justify-content-between rounded py-2 px-2 fs-13"
                                            :class="{ 'bg-primary-subtle text-primary fw-semibold': ws.id === currentWorkspace.id }"
                                        >
                                            <span class="text-truncate">{{ ws.name }}</span>
                                            <i v-if="ws.id === currentWorkspace.id" class="ri-check-line text-primary"></i>
                                        </button>
                                    </li>
                                    <li><hr class="dropdown-divider my-1"></li>
                                    <li>
                                        <Link href="/workspaces" class="dropdown-item d-flex align-items-center gap-2 rounded py-2 px-2 fs-12 text-primary fw-semibold">
                                            <i class="ri-add-circle-line"></i>
                                            <span>Kelola / Tambah Workspace</span>
                                        </Link>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="flex-shrink-0 d-flex align-items-center gap-2">
                        <!-- Theme Toggle matching MasterHub design -->
                        <div class="dark-mode-btn cursor-pointer" id="toggleMode" @click="toggleTheme" title="Ganti Mode Tema (Terang / Gelap)">
                            <button
                                type="button"
                                class="btn header-btn"
                                :class="{ active: currentTheme === 'light' }"
                                id="lightModeBtn"
                                title="Light Mode"
                                @click.stop="setTheme('light')"
                            >
                                <i class="bi bi-brightness-high"></i>
                            </button>
                            <button
                                type="button"
                                class="btn header-btn"
                                :class="{ active: currentTheme === 'dark' }"
                                id="darkModeBtn"
                                title="Dark Mode"
                                @click.stop="setTheme('dark')"
                            >
                                <i class="bi bi-moon-stars"></i>
                            </button>
                        </div>

                        <!-- Notifications Bell Dropdown -->
                        <div class="dropdown">
                            <button
                                class="btn btn-light-light text-muted icon-btn rounded-pill position-relative"
                                type="button"
                                data-bs-toggle="dropdown"
                                aria-expanded="false"
                                title="Notifikasi"
                            >
                                <i class="ri-notification-3-line fs-5"></i>
                                <span
                                    v-if="unreadNotificationCount > 0"
                                    class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger fs-10"
                                >
                                    {{ unreadNotificationCount }}
                                </span>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end shadow-lg border rounded-3 p-0 overflow-hidden" style="width: 320px;">
                                <div class="p-3 bg-light-subtle border-bottom d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center gap-2">
                                        <h6 class="mb-0 fw-bold fs-13">Notifikasi</h6>
                                        <span v-if="unreadNotificationCount > 0" class="badge bg-primary-subtle text-primary rounded-pill fs-10">
                                            {{ unreadNotificationCount }} Baru
                                        </span>
                                    </div>
                                    <button
                                        v-if="unreadNotificationCount > 0"
                                        type="button"
                                        class="btn btn-sm btn-link p-0 text-decoration-none fs-11 text-primary"
                                        @click="markAllNotificationsRead"
                                    >
                                        Tandai Semua Dibaca
                                    </button>
                                </div>
                                <div class="overflow-auto" style="max-height: 280px;">
                                    <div v-if="notifications.length">
                                        <div
                                            v-for="notif in notifications"
                                            :key="notif.id"
                                            class="p-3 border-bottom d-flex align-items-start gap-2 hover-bg-light cursor-pointer"
                                            :class="{ 'bg-primary-subtle bg-opacity-25': !notif.read_at }"
                                            @click="markNotificationRead(notif)"
                                        >
                                            <div class="bg-primary text-white rounded-circle p-1 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 24px; height: 24px;">
                                                <i class="ri-information-line fs-12"></i>
                                            </div>
                                            <div class="flex-grow-1 overflow-hidden">
                                                <div class="fs-12 fw-semibold text-body text-truncate">
                                                    {{ notif.data?.title || notif.type }}
                                                </div>
                                                <div class="fs-11 text-muted text-break">
                                                    {{ notif.data?.message || 'Anda memiliki pembaruan baru.' }}
                                                </div>
                                            </div>
                                            <span v-if="!notif.read_at" class="badge rounded-circle bg-primary p-1 mt-1"></span>
                                        </div>
                                    </div>
                                    <div v-else class="p-4 text-center text-muted fs-12">
                                        <i class="ri-checkbox-circle-line fs-24 d-block mb-1 text-success"></i>
                                        Semua notifikasi sudah dibaca.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- User Profile Dropdown -->
                        <div class="dropdown pe-dropdown-mega ms-1">
                            <button class="header-profile-btn btn gap-2 text-start d-flex align-items-center" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-semibold fs-14" style="width: 36px; height: 36px;">
                                    {{ user.name ? user.name.charAt(0).toUpperCase() : 'U' }}
                                </div>
                                <div class="d-none d-lg-block pe-1">
                                    <span class="d-block mb-0 fs-13 fw-semibold">{{ user.name }}</span>
                                    <span class="d-block mb-0 fs-12 text-muted">{{ user.email }}</span>
                                </div>
                                <i class="bi bi-chevron-down fs-11 text-muted ms-1"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end p-3 shadow border rounded-3" style="min-width: 220px;">
                                <div class="border-bottom pb-2 mb-2">
                                    <h6 class="mb-0 fs-14 fw-semibold">{{ user.name }}</h6>
                                    <p class="mb-0 fs-12 text-muted">{{ user.email }}</p>
                                </div>
                                <Link href="/profile" class="dropdown-item d-flex align-items-center gap-2 py-2 rounded">
                                    <i class="ri-user-settings-line text-muted"></i>
                                    <span>Edit Profil</span>
                                </Link>
                                <div class="dropdown-divider my-2"></div>
                                <button @click="logout" type="button" class="dropdown-item d-flex align-items-center gap-2 py-2 rounded text-danger">
                                    <i class="ri-logout-box-r-line"></i>
                                    <span>Keluar (Logout)</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Sidebar -->
        <aside class="pe-app-sidebar" id="sidebar" :class="{ show: isMobileSidebarOpen }" @click="handleSidebarNavClick">
            <div class="pe-app-sidebar-logo d-flex align-items-center position-relative">
                <Link href="/dashboard" class="sidebar-brand-link text-decoration-none d-flex align-items-center gap-2 w-100 px-3">
                    <img src="/admin-assets/images/ns.png" alt="NexSphere Logo" class="sidebar-brand-icon" style="height: 32px; width: 32px; object-fit: contain; flex-shrink: 0;" />
                    <div class="sidebar-brand-text d-flex flex-column text-truncate">
                        <span class="lh-1 text-primary fw-bold fs-16">PROJECT<span class="text-body">SPHERE</span></span>
                        <span class="fs-10 text-muted fw-semibold ls-1">NEXSPHERE PM</span>
                    </div>
                </Link>
            </div>

            <nav class="pe-app-sidebar-menu nav nav-pills" id="sidebar-simplebar">
                <ul class="pe-main-menu list-unstyled w-100">
                    <li class="pe-menu-title">Ringkasan</li>
                    <li class="pe-slide">
                        <Link href="/dashboard" class="pe-nav-link" :class="{ active: $page.url === '/dashboard' }">
                            <i class="ri-dashboard-3-line pe-nav-icon"></i>
                            <span class="pe-nav-content">Dashboard</span>
                        </Link>
                    </li>

                    <li class="pe-menu-title">Ruang Kerja & Proyek</li>
                    <li class="pe-slide">
                        <Link href="/workspaces" class="pe-nav-link" :class="{ active: $page.url === '/workspaces' }">
                            <i class="ri-community-line pe-nav-icon"></i>
                            <span class="pe-nav-content">Workspaces</span>
                        </Link>
                    </li>
                    <li class="pe-slide">
                        <Link href="/spaces" class="pe-nav-link" :class="{ active: $page.url === '/spaces' }">
                            <i class="ri-folder-6-line pe-nav-icon"></i>
                            <span class="pe-nav-content">Spaces & Projects</span>
                        </Link>
                    </li>

                    <!-- Sub-list of active Spaces in Sidebar (hidden automatically when in icon mode) -->
                    <li v-if="currentWorkspace?.spaces?.length" class="pe-sub-menu px-3 my-1">
                        <ul class="list-unstyled ps-3 pe-1 border-start border-2 border-secondary-subtle">
                            <li v-for="sp in currentWorkspace.spaces" :key="sp.id" class="my-1">
                                <Link
                                    :href="`/spaces/${sp.slug}`"
                                    class="text-decoration-none d-flex align-items-center gap-2 py-1 px-2 rounded fs-12 text-truncate"
                                    :class="$page.url.startsWith(`/spaces/${sp.slug}`) ? 'text-primary fw-bold bg-primary-subtle' : 'text-muted'"
                                >
                                    <i class="ri-folder-3-line fs-13"></i>
                                    <span class="text-truncate">{{ sp.name }}</span>
                                </Link>
                            </li>
                        </ul>
                    </li>

                    <li class="pe-slide">
                        <Link href="/my-tasks" class="pe-nav-link" :class="{ active: $page.url.startsWith('/my-tasks') }">
                            <i class="ri-checkbox-line pe-nav-icon"></i>
                            <span class="pe-nav-content">Tugas Saya (My Tasks)</span>
                        </Link>
                    </li>

                    <li class="pe-menu-title">Kolaborasi & Riwayat</li>
                    <li class="pe-slide">
                        <Link href="/activity-logs" class="pe-nav-link" :class="{ active: $page.url.startsWith('/activity-logs') }">
                            <i class="ri-history-line pe-nav-icon"></i>
                            <span class="pe-nav-content">Activity Logs</span>
                        </Link>
                    </li>

                    <li v-if="user.is_admin" class="pe-menu-title">Administrasi & RBAC</li>
                    <li v-if="user.is_admin" class="pe-slide">
                        <Link href="/users" class="pe-nav-link" :class="{ active: $page.url.startsWith('/users') }">
                            <i class="ri-shield-user-line pe-nav-icon"></i>
                            <span class="pe-nav-content">Pengguna & Role (RBAC)</span>
                        </Link>
                    </li>
                </ul>
            </nav>
        </aside>

        <!-- Sidebar Backdrop for Mobile -->
        <div
            class="sidebar-backdrop"
            id="sidebar-backdrop"
            :class="{ show: isMobileSidebarOpen }"
            @click="closeMobileSidebar"
        ></div>

        <!-- Main Content Wrapper matching MasterHub Layout -->
        <main class="app-wrapper flex-grow-1">
            <div class="container-fluid p-3 p-md-4">
                <!-- Flash Alerts if any -->
                <div v-if="$page.props.flash?.success" class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2 mb-4" role="alert">
                    <i class="bi bi-check-circle-fill fs-5"></i>
                    <div>{{ $page.props.flash.success }}</div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <div v-if="$page.props.flash?.error" class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2 mb-4" role="alert">
                    <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                    <div>{{ $page.props.flash.error }}</div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>

                <!-- Page Content -->
                <slot />
            </div>
        </main>

        <!-- Footer matching MasterHub -->
        <footer class="footer border-top">
            <div class="container-fluid px-3 px-md-4">
                <div class="row align-items-center">
                    <div class="col-sm-6 text-center text-sm-start fs-12 text-muted">
                        2026 © <span class="fw-semibold text-primary">ProjectSphere</span> by NexSphere Studio. Hak Cipta Dilindungi.
                    </div>
                    <div class="col-sm-6">
                        <div class="text-sm-end text-center d-none d-sm-block text-muted fs-12">
                            Crafted with <i class="ri-heart-fill text-danger align-middle animate-heart"></i> for Tim Kolaborasi Proyek
                        </div>
                    </div>
                </div>
            </div>
        </footer>

        <!-- Scroll To Top Button -->
        <div
            v-if="showBackToTop"
            class="progress-wrap d-flex align-items-center justify-content-center cursor-pointer position-fixed shadow-sm"
            id="progress-scroll"
            @click="scrollToTop"
            title="Kembali ke atas"
            style="bottom: 24px; right: 24px; width: 40px; height: 40px; z-index: 1040; background-color: var(--pe-secondary-bg); border-radius: 50%; border: 1px solid var(--pe-border-color);"
        >
            <i class="ri-arrow-up-line fs-18 text-primary"></i>
        </div>
    </div>
</template>
