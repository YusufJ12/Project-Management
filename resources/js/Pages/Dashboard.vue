<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

defineProps({
    stats: {
        type: Object,
        default: () => ({
            totalWorkspaces: 1,
            totalSpaces: 0,
            activeTasks: 0,
            completedTasks: 0,
            overdueTasks: 0,
            urgentTasksCount: 0,
            completionRate: 0,
        }),
    },
    recentSpaces: {
        type: Array,
        default: () => [],
    },
    myTasks: {
        type: Array,
        default: () => [],
    },
    recentActivities: {
        type: Array,
        default: () => [],
    },
});

const priorityBadge = (priority) => {
    switch (priority) {
        case 'urgent':
            return 'bg-danger text-white';
        case 'high':
            return 'bg-danger-subtle text-danger border border-danger-subtle';
        case 'medium':
            return 'bg-warning-subtle text-warning-emphasis border border-warning-subtle';
        default:
            return 'bg-success-subtle text-success border border-success-subtle';
    }
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Dashboard Ringkasan" />

        <!-- Header Title Card with Border -->
        <div class="card border border-secondary-subtle shadow-sm rounded-3 mb-4">
            <div class="card-body p-3 p-md-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <h4 class="fw-bold mb-1">Dashboard Proyek & Metrik Tim</h4>
                        <p class="text-muted fs-13 mb-0">Pantau performa kerja, progres proyek, dan tenggat waktu tugas secara langsung.</p>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <Link href="/spaces" class="btn btn-outline-primary btn-sm px-3 rounded-pill fw-semibold">
                            <i class="ri-folder-6-line me-1"></i> Semua Spaces
                        </Link>
                        <Link href="/spaces" class="btn btn-primary btn-sm px-3 rounded-pill fw-semibold">
                            <i class="ri-add-line me-1"></i> Buka Proyek
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- Metric Stat Cards -->
        <div class="row g-3 mb-4">
            <!-- Card 1: Total Spaces -->
            <div class="col-sm-6 col-xl-3">
                <div class="card border border-secondary-subtle shadow-sm rounded-3 h-100">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="fs-12 text-muted fw-semibold">PROYEK / SPACES</span>
                            <div class="bg-primary-subtle text-primary rounded-circle p-2 fs-16 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                <i class="ri-folder-6-line"></i>
                            </div>
                        </div>
                        <h3 class="fw-bold mb-1">{{ stats.totalSpaces }}</h3>
                        <div class="fs-12 text-muted">Di workspace aktif</div>
                    </div>
                </div>
            </div>

            <!-- Card 2: Task Berjalan & Completion Rate -->
            <div class="col-sm-6 col-xl-3">
                <div class="card border border-secondary-subtle shadow-sm rounded-3 h-100">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="fs-12 text-muted fw-semibold">RASIO SELESAI</span>
                            <div class="bg-success-subtle text-success rounded-circle p-2 fs-16 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                <i class="ri-pie-chart-2-line"></i>
                            </div>
                        </div>
                        <h3 class="fw-bold mb-1">{{ stats.completionRate }}%</h3>
                        <div class="fs-12 text-muted">
                            <span class="text-success fw-semibold">{{ stats.completedTasks }} selesai</span> / {{ stats.activeTasks + stats.completedTasks }} total task
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card 3: Prioritas Tinggi & Urgent -->
            <div class="col-sm-6 col-xl-3">
                <div class="card border border-secondary-subtle shadow-sm rounded-3 h-100">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="fs-12 text-muted fw-semibold">PRIORITAS TINGGI</span>
                            <div class="bg-warning-subtle text-warning-emphasis rounded-circle p-2 fs-16 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                <i class="ri-flashlight-line"></i>
                            </div>
                        </div>
                        <h3 class="fw-bold mb-1 text-warning-emphasis">{{ stats.urgentTasksCount }}</h3>
                        <div class="fs-12 text-muted">Task urgent & high priority</div>
                    </div>
                </div>
            </div>

            <!-- Card 4: Overdue Tasks -->
            <div class="col-sm-6 col-xl-3">
                <div class="card border border-secondary-subtle shadow-sm rounded-3 h-100">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="fs-12 text-muted fw-semibold">OVERDUE / TERLAMBAT</span>
                            <div class="bg-danger-subtle text-danger rounded-circle p-2 fs-16 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                <i class="ri-alarm-warning-line"></i>
                            </div>
                        </div>
                        <h3 class="fw-bold mb-1 text-danger">{{ stats.overdueTasks }}</h3>
                        <div class="fs-12 text-muted">Lewat dari tenggat waktu</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Projects / Spaces Table -->
        <div class="card border border-secondary-subtle shadow-sm rounded-3 overflow-hidden mb-4">
            <div class="card-header bg-body py-3 px-4 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold">Daftar Proyek & Status Pengerjaan</h6>
                <Link href="/spaces" class="fs-13 text-primary text-decoration-none fw-semibold">
                    Lihat Semua <i class="ri-arrow-right-line ms-1"></i>
                </Link>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light fs-12 text-muted text-uppercase">
                        <tr>
                            <th class="ps-4">Nama Space / Proyek</th>
                            <th>Progress Penyelesaian</th>
                            <th>Total Tasks</th>
                            <th>Tenggat Waktu</th>
                            <th>Status</th>
                            <th class="text-end pe-4">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="space in recentSpaces" :key="space.id">
                            <td class="ps-4">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="bg-primary-subtle text-primary rounded p-2 fs-15 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                        <i class="ri-folder-6-line"></i>
                                    </div>
                                    <div>
                                        <div class="fw-semibold fs-14">{{ space.name }}</div>
                                        <div class="fs-11 text-muted">{{ space.slug }}</div>
                                    </div>
                                </div>
                            </td>
                            <td style="min-width: 180px;">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="progress flex-grow-1" style="height: 6px;">
                                        <div
                                            class="progress-bar"
                                            :class="space.progress === 100 ? 'bg-success' : 'bg-primary'"
                                            role="progressbar"
                                            :style="{ width: space.progress + '%' }"
                                            :aria-valuenow="space.progress"
                                            aria-valuemin="0"
                                            aria-valuemax="100"
                                        ></div>
                                    </div>
                                    <span class="fs-12 fw-semibold">{{ space.progress }}%</span>
                                </div>
                            </td>
                            <td>
                                <span class="fs-13">{{ space.completed_tasks }}/{{ space.total_tasks }} task</span>
                            </td>
                            <td class="fs-13 text-muted">
                                <i class="ri-calendar-line me-1"></i> {{ space.due_at }}
                            </td>
                            <td>
                                <span
                                    class="badge rounded-pill px-2 py-1 fs-11"
                                    :class="space.status === 'active' ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-secondary'"
                                >
                                    {{ space.status === 'active' ? 'Aktif' : 'Selesai' }}
                                </span>
                            </td>
                            <td class="text-end pe-4">
                                <Link :href="`/spaces/${space.slug}`" class="btn btn-sm btn-light border rounded px-3 py-1 fs-12 fw-semibold">
                                    Buka Papan <i class="ri-arrow-right-s-line"></i>
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="!recentSpaces.length">
                            <td colspan="6" class="text-center py-4 text-muted fs-13">
                                Belum ada Space yang dibuat di workspace ini.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Two Columns: My Assigned Tasks & Recent Activities -->
        <div class="row g-4 mb-4">
            <!-- Left: My Assigned Tasks -->
            <div class="col-lg-7">
                <div class="card border border-secondary-subtle shadow-sm rounded-3 h-100">
                    <div class="card-header bg-body py-3 px-4 d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2">
                            <i class="ri-user-star-line text-primary fs-18"></i>
                            <h6 class="mb-0 fw-bold">Tugas yang Ditugaskan ke Saya</h6>
                        </div>
                        <span class="badge bg-primary-subtle text-primary rounded-pill fs-11">
                            {{ myTasks.length }} Task
                        </span>
                    </div>
                    <div class="card-body p-0">
                        <div v-if="myTasks.length" class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light fs-11 text-muted text-uppercase">
                                    <tr>
                                        <th class="ps-4">Judul Tugas</th>
                                        <th>Space</th>
                                        <th>Prioritas</th>
                                        <th>Status</th>
                                        <th class="pe-4 text-end">Tenggat</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="task in myTasks" :key="task.id">
                                        <td class="ps-4">
                                            <Link :href="`/spaces/${task.space_slug}`" class="text-body text-decoration-none fw-semibold fs-13 hover-primary">
                                                {{ task.title }}
                                            </Link>
                                        </td>
                                        <td>
                                            <span class="fs-12 text-muted">{{ task.space_name }}</span>
                                        </td>
                                        <td>
                                            <span class="badge rounded-pill px-2 py-1 fs-10 text-uppercase" :class="priorityBadge(task.priority)">
                                                {{ task.priority }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge rounded-pill px-2 py-1 fs-10" :style="{ backgroundColor: task.stage_color + '20', color: task.stage_color, border: '1px solid ' + task.stage_color }">
                                                {{ task.stage_name }}
                                            </span>
                                        </td>
                                        <td class="pe-4 text-end fs-12 text-muted">
                                            {{ task.due_at }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else class="p-4 text-center text-muted fs-13">
                            <i class="ri-checkbox-circle-line fs-28 d-block mb-1 text-success"></i>
                            Bagus! Tidak ada tugas pending yang tertunda untuk Anda saat ini.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Recent Workspace Activities -->
            <div class="col-lg-5">
                <div class="card border border-secondary-subtle shadow-sm rounded-3 h-100">
                    <div class="card-header bg-body py-3 px-4 d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2">
                            <i class="ri-history-line text-primary fs-18"></i>
                            <h6 class="mb-0 fw-bold">Aktivitas Tim Terkini</h6>
                        </div>
                        <span class="badge bg-secondary-subtle text-secondary rounded-pill fs-11">
                            Live Stream
                        </span>
                    </div>
                    <div class="card-body p-3">
                        <div v-if="recentActivities.length" class="d-flex flex-column gap-2">
                            <div
                                v-for="act in recentActivities"
                                :key="act.id"
                                class="p-2 rounded-2 bg-light-subtle d-flex align-items-start gap-2 border border-secondary-subtle"
                            >
                                <div class="bg-primary text-white rounded-circle p-1 d-flex align-items-center justify-content-center flex-shrink-0 mt-1" style="width: 22px; height: 22px;">
                                    <i class="ri-git-commit-line fs-12"></i>
                                </div>
                                <div class="flex-grow-1 overflow-hidden">
                                    <div class="fs-12 text-body">
                                        <strong>{{ act.actor_name }}</strong>
                                        <span class="text-muted ms-1">melakukan <code>{{ act.action }}</code></span>
                                    </div>
                                    <div v-if="act.metadata?.title" class="fs-11 text-muted text-truncate">
                                        "{{ act.metadata.title }}"
                                    </div>
                                    <div class="fs-10 text-muted mt-1">
                                        {{ act.time_ago }}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center py-4 text-muted fs-13">
                            Belum ada riwayat aktivitas yang tercatat.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.hover-primary:hover {
    color: var(--pe-primary, #00aaad) !important;
}
</style>
