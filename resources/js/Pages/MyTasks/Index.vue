<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    tasks: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({
            search: '',
            priority: 'all',
            status: 'all',
            due: 'all',
            workspace: 'active',
        }),
    },
    stats: {
        type: Object,
        default: () => ({
            total: 0,
            pending: 0,
            completed: 0,
            overdue: 0,
        }),
    },
});

const search = ref(props.filters.search || '');
const priority = ref(props.filters.priority || 'all');
const status = ref(props.filters.status || 'all');
const due = ref(props.filters.due || 'all');

let searchDebounce = null;

const applyFilters = () => {
    router.get(
        '/my-tasks',
        {
            search: search.value || undefined,
            priority: priority.value !== 'all' ? priority.value : undefined,
            status: status.value !== 'all' ? status.value : undefined,
            due: due.value !== 'all' ? due.value : undefined,
        },
        {
            preserveState: true,
            replace: true,
        }
    );
};

watch(search, () => {
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(() => {
        applyFilters();
    }, 400);
});

watch([priority, status, due], () => {
    applyFilters();
});

const priorityBadge = (pri) => {
    switch (pri) {
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

const isOverdue = (task) => {
    if (!task.due_at || task.stage?.is_done) return false;
    return new Date(task.due_at) < new Date(new Date().setHours(0, 0, 0, 0));
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Tugas Saya (My Tasks)" />

        <!-- Header Title Card with Border -->
        <div class="card border border-secondary-subtle shadow-sm rounded-3 mb-4">
            <div class="card-body p-3 p-md-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <h4 class="fw-bold mb-1">Tugas Saya (My Tasks)</h4>
                        <p class="text-muted fs-13 mb-0">Kelola dan pantau seluruh daftar tugas yang ditugaskan kepada Anda.</p>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <Link href="/spaces" class="btn btn-outline-primary btn-sm px-3 rounded-pill fw-semibold">
                            <i class="ri-folder-6-line me-1"></i> Buka Proyek & Kanban
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- Metric Stat Cards -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="card border border-secondary-subtle shadow-sm rounded-3 h-100">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="fs-12 text-muted fw-semibold">TOTAL TUGAS</span>
                            <div class="bg-primary-subtle text-primary rounded-circle p-2 fs-16 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                <i class="ri-checkbox-line"></i>
                            </div>
                        </div>
                        <h3 class="fw-bold mb-0">{{ stats.total }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card border border-secondary-subtle shadow-sm rounded-3 h-100">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="fs-12 text-muted fw-semibold">BELUM SELESAI</span>
                            <div class="bg-info-subtle text-info rounded-circle p-2 fs-16 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                <i class="ri-time-line"></i>
                            </div>
                        </div>
                        <h3 class="fw-bold mb-0 text-info">{{ stats.pending }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card border border-secondary-subtle shadow-sm rounded-3 h-100">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="fs-12 text-muted fw-semibold">SUDAH SELESAI</span>
                            <div class="bg-success-subtle text-success rounded-circle p-2 fs-16 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                <i class="ri-check-double-line"></i>
                            </div>
                        </div>
                        <h3 class="fw-bold mb-0 text-success">{{ stats.completed }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card border border-secondary-subtle shadow-sm rounded-3 h-100">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="fs-12 text-muted fw-semibold">TERLAMBAT (OVERDUE)</span>
                            <div class="bg-danger-subtle text-danger rounded-circle p-2 fs-16 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                <i class="ri-alarm-warning-line"></i>
                            </div>
                        </div>
                        <h3 class="fw-bold mb-0" :class="stats.overdue > 0 ? 'text-danger' : 'text-muted'">{{ stats.overdue }}</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Bar & Task List Table Card -->
        <div class="card border border-secondary-subtle shadow-sm rounded-3">
            <div class="card-body p-3 p-md-4">
                <!-- Filters Row -->
                <div class="row g-2 mb-3">
                    <div class="col-md-5">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body border-secondary-subtle text-muted">
                                <i class="bi bi-search"></i>
                            </span>
                            <input
                                type="text"
                                v-model="search"
                                class="form-control border-secondary-subtle"
                                placeholder="Cari judul atau keterangan tugas..."
                            />
                        </div>
                    </div>
                    <div class="col-6 col-md-2">
                        <select v-model="status" class="form-select form-select-sm border-secondary-subtle">
                            <option value="all">Semua Status</option>
                            <option value="pending">Sedang Berjalan</option>
                            <option value="completed">Sudah Selesai</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <select v-model="priority" class="form-select form-select-sm border-secondary-subtle">
                            <option value="all">Semua Prioritas</option>
                            <option value="urgent">Urgent</option>
                            <option value="high">Tinggi</option>
                            <option value="medium">Sedang</option>
                            <option value="low">Rendah</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-3">
                        <select v-model="due" class="form-select form-select-sm border-secondary-subtle">
                            <option value="all">Semua Waktu</option>
                            <option value="today">Tenggat Hari Ini</option>
                            <option value="overdue">Terlambat (Overdue)</option>
                            <option value="upcoming">Mendatang</option>
                        </select>
                    </div>
                </div>

                <!-- Table of Tasks -->
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light fs-12 text-uppercase">
                            <tr>
                                <th>Nama Tugas</th>
                                <th>Space / Proyek</th>
                                <th>Status (Stage)</th>
                                <th>Prioritas</th>
                                <th>Subtask</th>
                                <th>Tenggat</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="fs-13">
                            <tr v-if="tasks.data.length === 0">
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="ri-inbox-line fs-36 d-block mb-2 text-secondary opacity-50"></i>
                                    Tidak ada tugas yang cocok dengan filter yang dipilih.
                                </td>
                            </tr>
                            <tr v-for="task in tasks.data" :key="task.id">
                                <td>
                                    <div class="fw-semibold text-body">{{ task.title }}</div>
                                    <div v-if="task.description" class="fs-11 text-muted text-truncate" style="max-width: 320px;">
                                        {{ task.description }}
                                    </div>
                                </td>
                                <td>
                                    <span v-if="task.list?.space" class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill fs-11">
                                        <i class="ri-folder-3-line me-1"></i>{{ task.list.space.name }}
                                    </span>
                                </td>
                                <td>
                                    <span v-if="task.stage" class="badge rounded-pill fs-11 px-2 py-1" :class="task.stage.is_done ? 'bg-success text-white' : 'bg-secondary-subtle text-secondary'">
                                        {{ task.stage.name }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge rounded-pill px-2 py-1 fs-11 text-uppercase" :class="priorityBadge(task.priority)">
                                        {{ task.priority }}
                                    </span>
                                </td>
                                <td>
                                    <span v-if="task.checklists?.length" class="fs-12 text-muted">
                                        <i class="ri-checkbox-line text-primary me-1"></i>
                                        {{ task.checklists.filter((c) => c.is_completed).length }}/{{ task.checklists.length }}
                                    </span>
                                    <span v-else class="fs-12 text-muted">-</span>
                                </td>
                                <td>
                                    <div v-if="task.due_at" class="fs-12" :class="isOverdue(task) ? 'text-danger fw-bold' : 'text-muted'">
                                        <i class="ri-calendar-line me-1"></i>{{ task.due_at }}
                                        <span v-if="isOverdue(task)" class="badge bg-danger text-white rounded-pill fs-10 ms-1">Overdue</span>
                                    </div>
                                    <span v-else class="text-muted fs-12">-</span>
                                </td>
                                <td class="text-end">
                                    <Link
                                        v-if="task.list?.space"
                                        :href="`/spaces/${task.list.space.slug}`"
                                        class="btn btn-sm btn-outline-primary rounded-pill px-3 fs-12"
                                        title="Buka di Papan Kanban"
                                    >
                                        Buka Papan <i class="ri-arrow-right-line ms-1"></i>
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="tasks.total > 0" class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3 pt-3 border-top border-secondary-subtle">
                    <div class="fs-12 text-muted">
                        Menampilkan {{ tasks.from || 0 }} - {{ tasks.to || 0 }} dari {{ tasks.total }} tugas
                    </div>
                    <nav v-if="tasks.links && tasks.links.length > 1">
                        <ul class="pagination pagination-sm mb-0">
                            <li
                                v-for="(link, i) in tasks.links"
                                :key="i"
                                class="page-item"
                                :class="{ active: link.active, disabled: !link.url }"
                            >
                                <Link
                                    v-if="link.url"
                                    :href="link.url"
                                    class="page-link"
                                    v-html="link.label"
                                    preserve-state
                                />
                                <span v-else class="page-link" v-html="link.label"></span>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

