<script setup>
import { ref, onMounted, onUnmounted, computed, watch } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import echo from '@/echo';

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
const isMobileScreen = ref(false);

const updateScreenSize = () => {
    if (typeof window !== 'undefined') {
        isMobileScreen.value = window.innerWidth < 992;
    }
};

const toggleSidebar = () => {
    updateScreenSize();
    if (isMobileScreen.value) {
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
    updateScreenSize();
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

// Notifications & Realtime Bell System
const localNotifications = ref([...(page.props.notifications?.list || [])]);
const localUnreadCount = ref(page.props.notifications?.unread_count || 0);

watch(
    () => page.props.notifications,
    (newVal) => {
        if (newVal) {
            localNotifications.value = [...(newVal.list || [])];
            localUnreadCount.value = newVal.unread_count || 0;
        }
    },
    { deep: true }
);

// Floating Toast Notifications (Teams/Slack-style in-app popup)
const floatingToasts = ref([]);

const pushFloatingToast = (toast) => {
    const id = toast.id || ('toast_' + Date.now() + '_' + Math.random().toString(36).substr(2, 5));
    const newToast = {
        id,
        title: toast.title || 'Notifikasi',
        message: toast.message || '',
        actor_name: toast.actor_name || 'Rekan Tim',
        actor_avatar: toast.actor_avatar || null,
        url: toast.url || null,
        time: 'Baru saja',
        type: toast.type || 'info',
    };

    floatingToasts.value.unshift(newToast);

    // Auto-dismiss after 6.5s
    setTimeout(() => {
        removeFloatingToast(id);
    }, 6500);
};

const removeFloatingToast = (id) => {
    floatingToasts.value = floatingToasts.value.filter((t) => t.id !== id);
};

const markNotificationRead = (notif) => {
    if (!notif) return;

    if (!notif.read_at) {
        notif.read_at = new Date().toISOString();
        if (localUnreadCount.value > 0) localUnreadCount.value--;
    }

    // Persist via fetch with CSRF so it is not cancelled by router navigation
    const csrfToken = page.props.csrf_token || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    try {
        fetch(`/notifications/${notif.id}/read`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                Accept: 'application/json',
            },
        });
    } catch (e) {
        console.error('Failed to mark notification read', e);
    }

    const targetUrl = notif.data?.url;
    if (targetUrl) {
        try {
            const urlObj = new URL(targetUrl, window.location.origin);
            const pathWithQuery = urlObj.pathname + urlObj.search + urlObj.hash;
            router.visit(pathWithQuery, {
                preserveScroll: true,
            });
        } catch {
            router.visit(targetUrl, {
                preserveScroll: true,
            });
        }
    }
};

const clickToast = (toast) => {
    removeFloatingToast(toast.id);

    // If matching unread notification in localNotifications, mark it as read
    const matchingNotif = localNotifications.value.find((n) => String(n.id) === String(toast.id));
    if (matchingNotif) {
        markNotificationRead(matchingNotif);
        return;
    }

    // Otherwise mark read via fetch if numeric ID
    if (toast.id && !isNaN(Number(toast.id))) {
        const csrfToken = page.props.csrf_token || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        try {
            fetch(`/notifications/${toast.id}/read`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    Accept: 'application/json',
                },
            });
        } catch (e) {}
    }

    if (toast.url) {
        try {
            const urlObj = new URL(toast.url, window.location.origin);
            const pathWithQuery = urlObj.pathname + urlObj.search + urlObj.hash;
            router.visit(pathWithQuery, {
                preserveScroll: true,
            });
        } catch {
            router.visit(toast.url, {
                preserveScroll: true,
            });
        }
    }
};

const markAllNotificationsRead = () => {
    localNotifications.value.forEach((n) => {
        n.read_at = new Date().toISOString();
    });
    localUnreadCount.value = 0;

    const csrfToken = page.props.csrf_token || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    try {
        fetch('/notifications/read-all', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                Accept: 'application/json',
            },
        });
    } catch (e) {
        console.error('Failed to mark all as read', e);
    }
};

// Realtime User Notification Echo Subscription
let userEchoChannel = null;

const initUserEcho = () => {
    const echoInstance = window.Echo || echo;
    const userId = page.props.auth?.user?.id;
    if (!echoInstance || !userId) return;

    userEchoChannel = echoInstance.private(`App.Models.User.${userId}`);
    userEchoChannel.listen('NotificationSentEvent', (event) => {
        const notif = event.notification;
        if (!notif) return;

        // Deduplicate in localNotifications
        const notifId = String(notif.id);
        const exists = localNotifications.value.some((n) => String(n.id) === notifId);
        if (!exists) {
            localNotifications.value.unshift(notif);
        }

        if (event.unread_count !== undefined) {
            localUnreadCount.value = event.unread_count;
        } else {
            localUnreadCount.value++;
        }

        // Push floating Teams-style toast
        pushFloatingToast({
            id: notif.id,
            title: notif.data?.title || 'Notifikasi Baru',
            message: notif.data?.message || '',
            actor_name: notif.data?.actor_name || 'Rekan Tim',
            actor_avatar: notif.data?.actor_avatar || null,
            url: notif.data?.url || null,
            type: notif.type || 'info',
        });
    });
};

const cleanupUserEcho = () => {
    const echoInstance = window.Echo || echo;
    const userId = page.props.auth?.user?.id;
    if (echoInstance && userId) {
        try {
            echoInstance.leave(`App.Models.User.${userId}`);
            echoInstance.leave(`user.${userId}`);
        } catch (e) {}
    }
};

// Flash Alerts System
const activeAlert = ref({
    type: null,
    message: null,
});

let alertTimeout = null;

const dismissAlert = () => {
    activeAlert.value.message = null;
    activeAlert.value.type = null;
    if (alertTimeout) clearTimeout(alertTimeout);
};

const checkFlash = () => {
    const flash = page.props.flash || {};
    if (flash.success) {
        activeAlert.value = { type: 'success', message: flash.success };
    } else if (flash.error) {
        activeAlert.value = { type: 'error', message: flash.error };
    } else if (flash.warning) {
        activeAlert.value = { type: 'warning', message: flash.warning };
    } else if (flash.info) {
        activeAlert.value = { type: 'info', message: flash.info };
    } else {
        activeAlert.value = { type: null, message: null };
    }

    if (activeAlert.value.message && activeAlert.value.type !== 'error') {
        if (alertTimeout) clearTimeout(alertTimeout);
        alertTimeout = setTimeout(() => {
            activeAlert.value = { type: null, message: null };
        }, 5000);
    }
};

watch(() => page.props.flash, () => {
    checkFlash();
}, { deep: true });

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

onMounted(() => {
    // Theme setup
    const savedTheme = localStorage.getItem('data-bs-theme') || 'light';
    currentTheme.value = savedTheme;
    document.documentElement.setAttribute('data-bs-theme', savedTheme);

    // Sidebar setup
    updateScreenSize();
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

    checkFlash();
    initUserEcho();
    window.addEventListener('resize', handleResize);
    window.addEventListener('scroll', handleScroll);
});

onUnmounted(() => {
    cleanupUserEcho();
    if (typeof window !== 'undefined') {
        window.removeEventListener('resize', handleResize);
        window.removeEventListener('scroll', handleScroll);
    }
    if (alertTimeout) clearTimeout(alertTimeout);
});
</script>

<template>
    <div class="pe-layout-root min-vh-100 d-flex flex-column">
        <!-- Header -->
        <header class="app-header" id="appHeader">
            <div class="container-fluid w-100">
                <div class="d-flex align-items-center">
                    <div class="me-auto">
                        <div class="d-inline-flex align-items-center gap-2 gap-sm-3">
                            <button
                                type="button"
                                @click="toggleSidebar"
                                class="vertical-toggle btn btn-light-light text-muted icon-btn fs-5 rounded-pill"
                                id="toggleSidebar"
                                :title="isMobileScreen ? (isMobileSidebarOpen ? 'Tutup menu navigasi' : 'Buka menu navigasi') : (isSidebarMinimized ? 'Maximize sidebar' : 'Minimize sidebar')"
                            >
                                <i v-if="isMobileScreen" :class="isMobileSidebarOpen ? 'ri-close-line fs-18 text-body' : 'ri-menu-2-line fs-18 text-body'"></i>
                                <i v-else :class="isSidebarMinimized ? 'bi bi-arrow-bar-right header-icon' : 'bi bi-arrow-bar-left header-icon'"></i>
                            </button>

                            <!-- Workspace Switcher Dropdown in Header -->
                            <div v-if="currentWorkspace" class="dropdown">
                                <button
                                    class="btn btn-light border rounded-pill btn-sm d-flex align-items-center gap-1.5 px-2.5 px-sm-3 py-1 fs-12"
                                    type="button"
                                    data-bs-toggle="dropdown"
                                    aria-expanded="false"
                                >
                                    <i class="ri-community-line text-primary"></i>
                                    <span class="fw-semibold text-truncate" style="max-width: min(150px, 26vw);">{{ currentWorkspace.name }}</span>
                                    <i class="bi bi-chevron-down fs-10 text-muted d-none d-sm-inline-block"></i>
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
                                    v-if="localUnreadCount > 0"
                                    class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger fs-10"
                                >
                                    {{ localUnreadCount }}
                                </span>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end shadow-lg border rounded-3 p-0 overflow-hidden" style="width: 320px;">
                                <div class="p-3 bg-light-subtle border-bottom d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center gap-2">
                                        <h6 class="mb-0 fw-bold fs-13">Notifikasi</h6>
                                        <span v-if="localUnreadCount > 0" class="badge bg-primary-subtle text-primary rounded-pill fs-10">
                                            {{ localUnreadCount }} Baru
                                        </span>
                                    </div>
                                    <button
                                        v-if="localUnreadCount > 0"
                                        type="button"
                                        class="btn btn-sm btn-link p-0 text-decoration-none fs-11 text-primary"
                                        @click="markAllNotificationsRead"
                                    >
                                        Tandai Semua Dibaca
                                    </button>
                                </div>
                                <div class="overflow-auto" style="max-height: 280px;">
                                    <div v-if="localNotifications.length">
                                        <div
                                            v-for="notif in localNotifications"
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
                            <button class="header-profile-btn btn gap-2 text-start d-flex align-items-center p-1 p-sm-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-semibold fs-14 flex-shrink-0" style="width: 34px; height: 34px;">
                                    {{ user.name ? user.name.charAt(0).toUpperCase() : 'U' }}
                                </div>
                                <div class="d-none d-lg-block pe-1">
                                    <span class="d-block mb-0 fs-13 fw-semibold text-truncate" style="max-width: 140px;">{{ user.name }}</span>
                                    <span class="d-block mb-0 fs-12 text-muted text-truncate" style="max-width: 140px;">{{ user.email }}</span>
                                </div>
                                <i class="bi bi-chevron-down fs-11 text-muted ms-1 d-none d-sm-inline-block"></i>
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
            <div class="pe-app-sidebar-logo d-flex align-items-center justify-content-between position-relative px-3">
                <Link href="/dashboard" class="sidebar-brand-link text-decoration-none d-flex align-items-center gap-2">
                    <img src="/admin-assets/images/ns.png" alt="NexSphere Logo" class="sidebar-brand-icon" style="height: 32px; width: 32px; object-fit: contain; flex-shrink: 0;" />
                    <div class="sidebar-brand-text d-flex flex-column text-truncate">
                        <span class="lh-1 text-primary fw-bold fs-16">PROJECT<span class="text-body">SPHERE</span></span>
                        <span class="fs-10 text-muted fw-semibold ls-1">NEXSPHERE PM</span>
                    </div>
                </Link>
                <!-- Explicit Close Button on Mobile Drawer -->
                <button
                    v-if="isMobileScreen"
                    type="button"
                    class="btn btn-sm btn-light border-0 rounded-circle text-muted p-1 d-flex align-items-center justify-content-center"
                    style="width: 30px; height: 30px;"
                    @click.stop="closeMobileSidebar"
                    title="Tutup Menu Navigasi"
                >
                    <i class="ri-close-line fs-18"></i>
                </button>
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
                <!-- Flash Alerts System -->
                <transition name="fade">
                    <div
                        v-if="activeAlert.message"
                        class="alert alert-dismissible fade show d-flex align-items-center justify-content-between gap-3 mb-4 shadow-sm border rounded-3 p-3"
                        :class="{
                            'alert-success border-success-subtle bg-success-subtle text-success-emphasis': activeAlert.type === 'success',
                            'alert-danger border-danger-subtle bg-danger-subtle text-danger-emphasis': activeAlert.type === 'error',
                            'alert-warning border-warning-subtle bg-warning-subtle text-warning-emphasis': activeAlert.type === 'warning',
                            'alert-info border-info-subtle bg-info-subtle text-info-emphasis': activeAlert.type === 'info',
                        }"
                        role="alert"
                    >
                        <div class="d-flex align-items-center gap-2">
                            <i v-if="activeAlert.type === 'success'" class="ri-checkbox-circle-fill fs-5 text-success"></i>
                            <i v-else-if="activeAlert.type === 'error'" class="ri-error-warning-fill fs-5 text-danger"></i>
                            <i v-else-if="activeAlert.type === 'warning'" class="ri-alert-fill fs-5 text-warning"></i>
                            <i v-else class="ri-information-fill fs-5 text-info"></i>
                            <span class="fs-13 fw-medium">{{ activeAlert.message }}</span>
                        </div>
                        <button
                            type="button"
                            class="btn-close position-static p-0"
                            @click="dismissAlert"
                            aria-label="Tutup Notifikasi"
                        ></button>
                    </div>
                </transition>

                <!-- Page Content -->
                <slot />
            </div>
        </main>

        <!-- Footer matching MasterHub -->
        <footer class="footer border-top">
            <div class="container-fluid px-3 px-md-4">
                <div class="row align-items-center">
                    <div class="col-sm-6 text-center text-sm-start fs-12 text-muted">
                        2026 © <span class="fw-semibold text-primary">ProjectSphere</span>. Hak Cipta Dilindungi.
                    </div>
                    <div class="col-sm-6">
                        <div class="text-sm-end text-center d-none d-sm-block text-muted fs-12 fw-medium">
                            NexSphere
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

        <!-- Floating Teams-Style In-App Notification Toasts -->
        <div class="floating-toasts-container" aria-live="polite">
            <TransitionGroup name="toast-slide">
                <div
                    v-for="toast in floatingToasts"
                    :key="toast.id"
                    class="floating-toast-card d-flex align-items-start gap-3 p-3 rounded-3 border bg-body shadow-lg"
                    @click="clickToast(toast)"
                    role="alert"
                >
                    <!-- Avatar / Icon -->
                    <div class="toast-avatar-box flex-shrink-0">
                        <div v-if="toast.actor_avatar" class="rounded-circle overflow-hidden shadow-2xs" style="width: 38px; height: 38px;">
                            <img :src="toast.actor_avatar" alt="Avatar" class="w-100 h-100 object-fit-cover" />
                        </div>
                        <div v-else class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold fs-14 shadow-2xs" style="width: 38px; height: 38px;">
                            {{ (toast.actor_name || 'N').charAt(0).toUpperCase() }}
                        </div>
                    </div>

                    <!-- Body -->
                    <div class="flex-grow-1 overflow-hidden pe-1">
                        <div class="d-flex align-items-center justify-content-between mb-1 gap-2">
                            <span class="fs-12 fw-bold text-body text-truncate">{{ toast.title }}</span>
                            <span class="fs-10 text-muted text-nowrap">{{ toast.time }}</span>
                        </div>
                        <div class="fs-11 text-secondary text-break mb-1" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.35;">
                            {{ toast.message }}
                        </div>
                        <div v-if="toast.url" class="fs-11 fw-semibold text-primary d-flex align-items-center gap-1">
                            <span>Buka & Tinjau</span>
                            <i class="ri-arrow-right-line fs-11"></i>
                        </div>
                    </div>

                    <!-- Dismiss X -->
                    <button
                        type="button"
                        class="btn btn-sm btn-link text-muted p-0 border-0 flex-shrink-0 hover-text-dark"
                        @click.stop="removeFloatingToast(toast.id)"
                        title="Tutup Notifikasi"
                    >
                        <i class="ri-close-line fs-15"></i>
                    </button>
                </div>
            </TransitionGroup>
        </div>
    </div>
</template>

<style scoped>
.floating-toasts-container {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 1090;
    display: flex;
    flex-direction: column;
    gap: 10px;
    max-width: 380px;
    width: 100%;
    pointer-events: none;
}

.floating-toast-card {
    pointer-events: auto;
    cursor: pointer;
    position: relative;
    border-left: 4px solid var(--bs-primary) !important;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.16), 0 8px 10px -6px rgba(0, 0, 0, 0.1) !important;
}

.floating-toast-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 15px 30px -5px rgba(0, 0, 0, 0.22), 0 10px 12px -6px rgba(0, 0, 0, 0.14) !important;
}

/* Slide in / out animation */
.toast-slide-enter-active,
.toast-slide-leave-active {
    transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
}

.toast-slide-enter-from {
    opacity: 0;
    transform: translateX(80px) scale(0.92);
}

.toast-slide-leave-to {
    opacity: 0;
    transform: translateX(80px) scale(0.92);
}

@media (max-width: 576px) {
    .floating-toasts-container {
        left: 12px;
        right: 12px;
        bottom: 12px;
        max-width: calc(100% - 24px);
    }
}
</style>
