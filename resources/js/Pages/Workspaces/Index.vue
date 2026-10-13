<script setup>
import { ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

defineProps({
    workspaces: {
        type: Array,
        required: true,
    },
});

const showModal = ref(false);

const form = useForm({
    name: '',
});

const createWorkspace = () => {
    form.post('/workspaces', {
        onSuccess: () => {
            form.reset();
            showModal.value = false;
        },
    });
};

const switchWorkspace = (workspaceId) => {
    router.post(`/workspaces/${workspaceId}/switch`);
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Manajemen Ruang Kerja (Workspaces)" />

        <!-- Header Title Card with Border -->
        <div class="card border border-secondary-subtle shadow-sm rounded-3 mb-4">
            <div class="card-body p-3 p-md-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <h4 class="fw-bold mb-1">Manajemen Ruang Kerja (Workspaces)</h4>
                        <p class="text-muted fs-13 mb-0">Kelola workspace organisasi dan beralih lingkungan kerja tim Anda.</p>
                    </div>
                    <button type="button" @click="showModal = true" class="btn btn-primary btn-sm px-3 rounded-pill fw-semibold">
                        <i class="ri-add-line me-1"></i> Buat Workspace Baru
                    </button>
                </div>
            </div>
        </div>

        <!-- Workspaces Cards Grid -->
        <div class="row g-3">
            <div v-for="ws in workspaces" :key="ws.id" class="col-md-6 col-xl-4">
                <div class="card h-100 border rounded-3 shadow-sm transition-all" :class="ws.is_active ? 'border-primary' : 'border-secondary-subtle'">
                    <div class="card-body p-4 d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="d-flex align-items-center gap-2">
                                <div class="bg-primary text-white rounded-3 p-2 fs-18 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                                    <i class="ri-community-line"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold mb-0 fs-15">{{ ws.name }}</h6>
                                    <span class="fs-11 text-muted">{{ ws.slug }}</span>
                                </div>
                            </div>
                            <span v-if="ws.is_active" class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2 py-1 fs-11">
                                <i class="ri-checkbox-circle-fill me-1"></i> Aktif
                            </span>
                        </div>

                        <div class="row g-2 py-3 border-top border-bottom my-2 fs-13">
                            <div class="col-6">
                                <span class="text-muted d-block fs-11">ROLE ANDA</span>
                                <span class="fw-semibold text-capitalize">{{ ws.role }}</span>
                            </div>
                            <div class="col-6">
                                <span class="text-muted d-block fs-11">TOTAL SPACES</span>
                                <span class="fw-semibold">{{ ws.spaces_count }} Space</span>
                            </div>
                            <div class="col-6">
                                <span class="text-muted d-block fs-11">TOTAL ANGGOTA</span>
                                <span class="fw-semibold">{{ ws.members_count }} Member</span>
                            </div>
                            <div class="col-6">
                                <span class="text-muted d-block fs-11">DIBUAT PADA</span>
                                <span class="fw-semibold">{{ ws.created_at }}</span>
                            </div>
                        </div>

                        <div class="mt-auto pt-3 d-flex gap-2">
                            <button
                                v-if="!ws.is_active"
                                type="button"
                                @click="switchWorkspace(ws.id)"
                                class="btn btn-outline-primary btn-sm w-100 rounded-pill fw-semibold"
                            >
                                <i class="ri-swap-box-line me-1"></i> Jadikan Aktif
                            </button>
                            <button
                                v-else
                                type="button"
                                class="btn btn-light btn-sm w-100 rounded-pill fw-semibold text-muted"
                                disabled
                            >
                                Sedang Digunakan
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create Workspace Modal Backdrop & Dialog -->
        <div v-if="showModal" class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0, 0, 0, 0.5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content rounded-4 border-0 shadow">
                    <div class="modal-header border-bottom py-3 px-4">
                        <h5 class="modal-title fw-bold fs-16">Buat Ruang Kerja Baru</h5>
                        <button type="button" @click="showModal = false" class="btn-close" aria-label="Close"></button>
                    </div>
                    <form @submit.prevent="createWorkspace">
                        <div class="modal-body p-4">
                            <div class="mb-3">
                                <label for="ws_name" class="form-label fs-13 fw-semibold">Nama Workspace</label>
                                <input
                                    id="ws_name"
                                    v-model="form.name"
                                    type="text"
                                    class="form-control"
                                    :class="{ 'is-invalid': form.errors.name }"
                                    placeholder="Contoh: Digital Marketing Agency"
                                    required
                                    autofocus
                                />
                                <div v-if="form.errors.name" class="text-danger fs-12 mt-1">
                                    {{ form.errors.name }}
                                </div>
                                <div class="form-text fs-12 text-muted">
                                    Slug URL unik akan dibuat otomatis untuk workspace ini.
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer border-top py-2 px-4">
                            <button type="button" @click="showModal = false" class="btn btn-light rounded-pill px-3">Batal</button>
                            <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold" :disabled="form.processing">
                                Simpan Workspace
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

