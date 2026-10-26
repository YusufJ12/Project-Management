<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    logs: {
        type: Object,
        required: true,
    },
    actionTypes: {
        type: Object,
        default: () => ({}),
    },
    filters: {
        type: Object,
        default: () => ({
            action: 'all',
            search: '',
            workspace: 'active',
        }),
    },
    totalLogs: {
        type: Number,
        default: 0,
    },
});

const search = ref(props.filters.search || '');
const action = ref(props.filters.action || 'all');

let searchDebounce = null;

const applyFilters = () => {
    router.get(
        '/activity-logs',
        {
            search: search.value || undefined,
            action: action.value !== 'all' ? action.value : undefined,
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

watch(action, () => {
    applyFilters();
});

const getActionBadgeClass = (act) => {
    switch (act) {
        case 'task_created':
            return 'bg-success-subtle text-success border border-success-subtle';
        case 'task_moved':
            return 'bg-primary-subtle text-primary border border-primary-subtle';
        case 'timeline_adjusted':
            return 'bg-info-subtle text-info border border-info-subtle';
        case 'timeline_cascaded':
            return 'bg-primary-subtle text-primary border border-primary-subtle';
        case 'dependency_added':
        case 'dependency_removed':
            return 'bg-indigo-subtle text-indigo border border-indigo-subtle';
        case 'task_deleted':
            return 'bg-danger-subtle text-danger border border-danger-subtle';
        case 'comment_added':
            return 'bg-info-subtle text-info border border-info-subtle';
        case 'checklist_toggled':
            return 'bg-purple-subtle text-purple border border-purple-subtle';
        case 'attachment_uploaded':
            return 'bg-warning-subtle text-warning-emphasis border border-warning-subtle';
        default:
            return 'bg-secondary-subtle text-secondary border border-secondary-subtle';
    }
};

const getActionIcon = (act) => {
    switch (act) {
        case 'task_created':
            return 'ri-add-circle-line';
        case 'task_moved':
            return 'ri-drag-move-fill';
        case 'timeline_adjusted':
            return 'ri-calendar-event-line';
        case 'timeline_cascaded':
            return 'ri-git-merge-line';
        case 'dependency_added':
        case 'dependency_removed':
            return 'ri-links-line';
        case 'task_deleted':
            return 'ri-delete-bin-line';
        case 'comment_added':
            return 'ri-chat-3-line';
        case 'checklist_toggled':
            return 'ri-checkbox-circle-line';
        case 'attachment_uploaded':
            return 'ri-attachment-line';
        default:
            return 'ri-history-line';
    }
};

const formatDate = (dateString) => {
    if (!dateString) return '';
    const d = new Date(dateString);
    return d.toLocaleString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Activity Logs (Riwayat Aktivitas)" />

        <!-- Header Title Card with Border -->
        <div class="card border border-secondary-subtle shadow-sm rounded-3 mb-4">
            <div class="card-body p-3 p-md-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <h4 class="fw-bold mb-1">Activity Logs (Riwayat Aktivitas)</h4>
                        <p class="text-muted fs-13 mb-0">Audit trail transparan dari seluruh kolaborasi, pembaruan tugas, dan aksi tim di ruang kerja.</p>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-2 fs-12">
                            <i class="ri-history-line me-1"></i> {{ totalLogs }} Total Aktivitas
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Bar Card -->
        <div class="card border border-secondary-subtle shadow-sm rounded-3 mb-4">
            <div class="card-body p-3">
                <div class="row g-2 align-items-center">
                    <div class="col-md-7">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body border-secondary-subtle text-muted">
                                <i class="bi bi-search"></i>
                            </span>
                            <input
                                type="text"
                                v-model="search"
                                class="form-control border-secondary-subtle"
                                placeholder="Cari isi catatan aktivitas..."
                            />
                        </div>
                    </div>
                    <div class="col-md-5">
                        <select v-model="action" class="form-select form-select-sm border-secondary-subtle">
                            <option v-for="(label, key) in actionTypes" :key="key" :value="key">
                                {{ label }}
                            </option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Timeline Log Card -->
        <div class="card border border-secondary-subtle shadow-sm rounded-3">
            <div class="card-body p-3 p-md-4">
                <div v-if="logs.data.length === 0" class="text-center py-5 text-muted">
                    <i class="ri-inbox-archive-line fs-36 d-block mb-2 text-secondary opacity-50"></i>
                    Belum ada riwayat aktivitas yang tercatat dengan filter saat ini.
                </div>

                <div v-else class="list-group list-group-flush">
                    <div
                        v-for="log in logs.data"
                        :key="log.id"
                        class="list-group-item px-0 py-3 border-secondary-subtle d-flex align-items-start gap-3"
                    >
                        <!-- Actor Avatar -->
                        <div
                            class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-semibold fs-12 flex-shrink-0"
                            style="width: 36px; height: 36px;"
                        >
                            {{ log.actor?.name ? log.actor.name.charAt(0).toUpperCase() : 'S' }}
                        </div>

                        <!-- Content Body -->
                        <div class="flex-grow-1 min-w-0">
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                <span class="fw-bold fs-13 text-body">{{ log.actor?.name || 'Sistem' }}</span>
                                <span class="badge rounded-pill fs-10 px-2 py-0 d-inline-flex align-items-center gap-1" :class="getActionBadgeClass(log.action)">
                                    <i :class="getActionIcon(log.action)"></i>
                                    {{ log.action }}
                                </span>
                                <Link
                                    v-if="log.space"
                                    :href="`/spaces/${log.space.slug}`"
                                    class="badge bg-light text-muted border text-decoration-none fs-11"
                                >
                                    <i class="ri-folder-3-line text-primary me-1"></i>{{ log.space.name }}
                                </Link>
                            </div>
                            <div class="fs-13 text-body-secondary text-break mb-1">
                                {{ log.description }}
                            </div>
                            <div class="fs-11 text-muted">
                                <i class="ri-time-line me-1"></i>{{ formatDate(log.created_at) }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <div v-if="logs.total > 0" class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3 pt-3 border-top border-secondary-subtle">
                    <div class="fs-12 text-muted">
                        Menampilkan {{ logs.from || 0 }} - {{ logs.to || 0 }} dari {{ logs.total }} entri
                    </div>
                    <nav v-if="logs.links && logs.links.length > 1">
                        <ul class="pagination pagination-sm mb-0">
                            <li
                                v-for="(link, i) in logs.links"
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

