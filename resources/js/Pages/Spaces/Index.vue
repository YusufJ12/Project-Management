<script setup>
import { ref } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { confirmDelete } from '@/Utils/alert';

const props = defineProps({
    workspace: {
        type: Object,
        required: true,
    },
    spaces: {
        type: Array,
        required: true,
    },
});

const showModal = ref(false);

const form = useForm({
    name: '',
    description: '',
    starts_at: '',
    due_at: '',
});

const createSpace = () => {
    form.post('/spaces', {
        onSuccess: () => {
            form.reset();
            showModal.value = false;
        },
    });
};

const deleteSpace = async (space) => {
    const confirmed = await confirmDelete({
        title: 'Hapus Space / Proyek?',
        text: `PERINGATAN: Yakin ingin menghapus space "${space.name}"? Seluruh papan Kanban, task, komentar, dan lampiran di dalamnya akan terhapus permanen.`,
        confirmButtonText: 'Ya, Hapus Space!',
    });
    if (confirmed) {
        router.delete(`/spaces/${space.id}`);
    }
};
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="`Spaces & Projects - ${workspace.name}`" />

        <!-- Header Title Card with Border -->
        <div class="card border border-secondary-subtle shadow-sm rounded-3 mb-4">
            <div class="card-body p-3 p-md-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <h4 class="fw-bold mb-0">Spaces & Proyek</h4>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2 py-1 fs-11">
                                {{ workspace.name }}
                            </span>
                        </div>
                        <p class="text-muted fs-13 mb-0">Daftar seluruh ruang proyek dan alur kerja tim dalam workspace aktif.</p>
                    </div>
                    <button type="button" @click="showModal = true" class="btn btn-primary btn-sm px-3 rounded-pill fw-semibold">
                        <i class="ri-add-line me-1"></i> Buat Space Baru
                    </button>
                </div>
            </div>
        </div>

        <!-- Empty State if no spaces -->
        <div v-if="spaces.length === 0" class="card border border-secondary-subtle rounded-3 p-5 text-center shadow-sm">
            <div class="bg-primary-subtle text-primary rounded-circle p-3 mx-auto mb-3 fs-32 d-flex align-items-center justify-content-center" style="width: 70px; height: 70px;">
                <i class="ri-folder-open-line"></i>
            </div>
            <h5 class="fw-bold mb-1">Belum Ada Space di Workspace Ini</h5>
            <p class="text-muted fs-13 mx-auto mb-4" style="max-width: 400px;">
                Mulai kelola proyek pertama tim Anda dengan membuat Space baru. Setiap Space akan otomatis memiliki papan Kanban.
            </p>
            <button type="button" @click="showModal = true" class="btn btn-primary rounded-pill px-4 fw-semibold mx-auto">
                <i class="ri-add-line me-1"></i> Buat Space Pertama
            </button>
        </div>

        <!-- Spaces Grid -->
        <div v-else class="row g-3">
            <div v-for="space in spaces" :key="space.id" class="col-md-6 col-xl-4">
                <div class="card h-100 border border-secondary-subtle rounded-3 shadow-sm transition-all hover-shadow">
                    <div class="card-body p-4 d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h5 class="fw-bold mb-0 fs-16 text-truncate" style="max-width: 180px;">
                                <Link :href="`/spaces/${space.slug}`" class="text-body text-decoration-none hover-primary">
                                    {{ space.name }}
                                </Link>
                            </h5>
                            <div class="d-flex align-items-center gap-1">
                                <span
                                    class="badge rounded-pill px-2 py-1 fs-11"
                                    :class="space.status === 'active' ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-secondary'"
                                >
                                    {{ space.status === 'active' ? 'Aktif' : 'Selesai' }}
                                </span>
                                <div class="dropdown">
                                    <button
                                        class="btn btn-sm btn-light border-0 text-muted p-1 rounded-circle"
                                        type="button"
                                        data-bs-toggle="dropdown"
                                        aria-expanded="false"
                                        title="Opsi Space"
                                    >
                                        <i class="ri-more-2-fill fs-15"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow border rounded-3 p-1 fs-12">
                                        <li>
                                            <Link :href="`/spaces/${space.slug}`" class="dropdown-item rounded py-1 px-2 d-flex align-items-center gap-2">
                                                <i class="ri-kanban-view text-primary"></i>
                                                <span>Buka Papan</span>
                                            </Link>
                                        </li>
                                        <li><hr class="dropdown-divider my-1"></li>
                                        <li>
                                            <button
                                                type="button"
                                                class="dropdown-item rounded py-1 px-2 d-flex align-items-center gap-2 text-danger"
                                                @click="deleteSpace(space)"
                                            >
                                                <i class="ri-delete-bin-line"></i>
                                                <span>Hapus Space</span>
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <p class="text-muted fs-12 mb-3 text-truncate-2" style="min-height: 36px;">
                            {{ space.description || 'Tidak ada deskripsi proyek.' }}
                        </p>

                        <!-- Progress Bar -->
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center fs-12 mb-1">
                                <span class="text-muted">Progres Selesai</span>
                                <span class="fw-semibold">{{ space.progress }}%</span>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <div
                                    class="progress-bar"
                                    :class="space.progress === 100 ? 'bg-success' : 'bg-primary'"
                                    role="progressbar"
                                    :style="{ width: space.progress + '%' }"
                                ></div>
                            </div>
                        </div>

                        <!-- Stats Meta -->
                        <div class="d-flex justify-content-between align-items-center py-2 border-top border-bottom fs-12 text-muted mb-3">
                            <div>
                                <i class="ri-checkbox-line me-1"></i>
                                <span>{{ space.completed_tasks }}/{{ space.total_tasks }} Tasks</span>
                            </div>
                            <div>
                                <i class="ri-calendar-line me-1"></i>
                                <span>{{ space.due_at || 'Tanpa deadline' }}</span>
                            </div>
                        </div>

                        <!-- Action Button -->
                        <div class="mt-auto">
                            <Link :href="`/spaces/${space.slug}`" class="btn btn-outline-primary btn-sm w-100 rounded-pill fw-semibold">
                                Buka Papan Kanban <i class="ri-arrow-right-line ms-1"></i>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create Space Modal -->
        <div v-if="showModal" class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0, 0, 0, 0.5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content rounded-4 border-0 shadow">
                    <div class="modal-header border-bottom py-3 px-4">
                        <h5 class="modal-title fw-bold fs-16">Buat Space / Proyek Baru</h5>
                        <button type="button" @click="showModal = false" class="btn-close" aria-label="Close"></button>
                    </div>
                    <form @submit.prevent="createSpace">
                        <div class="modal-body p-4">
                            <div class="mb-3">
                                <label for="name" class="form-label fs-13 fw-semibold">Nama Space / Proyek</label>
                                <input
                                    id="name"
                                    v-model="form.name"
                                    type="text"
                                    class="form-control"
                                    :class="{ 'is-invalid': form.errors.name }"
                                    placeholder="Contoh: Mobile HRIS Redesign"
                                    required
                                    autofocus
                                />
                                <div v-if="form.errors.name" class="text-danger fs-12 mt-1">
                                    {{ form.errors.name }}
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label fs-13 fw-semibold">Deskripsi (Opsional)</label>
                                <textarea
                                    id="description"
                                    v-model="form.description"
                                    class="form-control"
                                    rows="3"
                                    placeholder="Ringkasan tujuan proyek..."
                                ></textarea>
                            </div>

                            <div class="row g-2">
                                <div class="col-6 mb-3">
                                    <label for="starts_at" class="form-label fs-13 fw-semibold">Tanggal Mulai</label>
                                    <input id="starts_at" v-model="form.starts_at" type="date" class="form-control" />
                                </div>
                                <div class="col-6 mb-3">
                                    <label for="due_at" class="form-label fs-13 fw-semibold">Tenggat Waktu</label>
                                    <input id="due_at" v-model="form.due_at" type="date" class="form-control" />
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer border-top py-2 px-4">
                            <button type="button" @click="showModal = false" class="btn btn-light rounded-pill px-3">Batal</button>
                            <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold" :disabled="form.processing">
                                Simpan & Buat Papan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.hover-shadow:hover {
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.08) !important;
    transform: translateY(-2px);
}
.transition-all {
    transition: all 0.2s ease-in-out;
}
.hover-primary:hover {
    color: var(--pe-primary, #00aaad) !important;
}
.text-truncate-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>

