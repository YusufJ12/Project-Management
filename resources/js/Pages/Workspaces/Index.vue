<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { confirmDelete, confirmAction, showWarning } from '@/Utils/alert';

const props = defineProps({
    workspaces: {
        type: Array,
        required: true,
    },
    system_users: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const currentUser = page.props.auth?.user || {};

// Create Workspace Modal
const showCreateModal = ref(false);
const createForm = useForm({
    name: '',
});

const submitCreateWorkspace = () => {
    createForm.post('/workspaces', {
        onSuccess: () => {
            createForm.reset();
            showCreateModal.value = false;
        },
    });
};

// Edit Workspace Modal
const showEditModal = ref(false);
const editWorkspaceForm = useForm({
    id: null,
    name: '',
});

const openEditModal = (ws) => {
    editWorkspaceForm.id = ws.id;
    editWorkspaceForm.name = ws.name;
    showEditModal.value = true;
};

const submitEditWorkspace = () => {
    editWorkspaceForm.put(`/workspaces/${editWorkspaceForm.id}`, {
        onSuccess: () => {
            showEditModal.value = false;
            editWorkspaceForm.reset();
        },
    });
};

// Delete Workspace
const deleteWorkspace = async (ws) => {
    const confirmed = await confirmDelete({
        title: 'Hapus Workspace?',
        text: `Yakin ingin menghapus workspace "${ws.name}"? Semua space, task, dan dokumen di dalamnya akan ikut terhapus permanen.`,
        confirmButtonText: 'Ya, Hapus Workspace!',
    });
    if (confirmed) {
        router.delete(`/workspaces/${ws.id}`);
    }
};

// Switch Active Workspace
const switchWorkspace = (workspaceId) => {
    router.post(`/workspaces/${workspaceId}/switch`);
};

// Manage Members Modal
const showMembersModal = ref(false);
const selectedWorkspace = ref(null);

const addMemberForm = useForm({
    user_id: '',
    role: 'member',
});

const openMembersModal = (ws) => {
    selectedWorkspace.value = ws;
    addMemberForm.reset();
    addMemberForm.user_id = '';
    addMemberForm.role = 'member';
    showMembersModal.value = true;
};

// Users available to be added (exclude existing members)
const availableUsers = computed(() => {
    if (!selectedWorkspace.value || !props.system_users) return [];
    const memberIds = new Set(selectedWorkspace.value.members.map((m) => m.id));
    return props.system_users.filter((u) => !memberIds.has(u.id));
});

const submitAddMember = () => {
    if (!addMemberForm.user_id) return;
    addMemberForm.post(`/workspaces/${selectedWorkspace.value.id}/members`, {
        preserveScroll: true,
        onSuccess: () => {
            addMemberForm.reset();
            addMemberForm.user_id = '';
            // Refresh selectedWorkspace with updated data
            const updated = props.workspaces.find((w) => w.id === selectedWorkspace.value.id);
            if (updated) {
                selectedWorkspace.value = updated;
            }
        },
    });
};

const updateMemberRole = async (member, newRole) => {
    if (member.is_owner) {
        showWarning('Perhatian', 'Peran Pemilik (Owner) workspace tidak dapat diubah.');
        return;
    }
    const roleLabel = newRole === 'admin' ? 'Administrator Workspace' : 'Anggota (Member)';
    const confirmed = await confirmAction({
        title: 'Ubah Peran Anggota?',
        text: `Ubah peran ${member.name} menjadi ${roleLabel}?`,
        confirmButtonText: 'Ya, Ubah Peran',
    });
    if (confirmed) {
        router.put(`/workspaces/${selectedWorkspace.value.id}/members/${member.id}`, {
            role: newRole,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                const updated = props.workspaces.find((w) => w.id === selectedWorkspace.value.id);
                if (updated) {
                    selectedWorkspace.value = updated;
                }
            },
        });
    }
};

const removeMember = async (member) => {
    if (member.is_owner) {
        showWarning('Perhatian', 'Pemilik (Owner) workspace tidak dapat dikeluarkan.');
        return;
    }
    const confirmed = await confirmDelete({
        title: 'Keluarkan Anggota?',
        text: `Yakin ingin mengeluarkan ${member.name} dari workspace "${selectedWorkspace.value.name}"?`,
        confirmButtonText: 'Ya, Keluarkan!',
    });
    if (confirmed) {
        router.delete(`/workspaces/${selectedWorkspace.value.id}/members/${member.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                const updated = props.workspaces.find((w) => w.id === selectedWorkspace.value.id);
                if (updated) {
                    selectedWorkspace.value = updated;
                }
            },
        });
    }
};

const getRoleBadgeClass = (role) => {
    switch (role) {
        case 'owner':
            return 'bg-danger-subtle text-danger border border-danger-subtle';
        case 'admin':
            return 'bg-primary-subtle text-primary border border-primary-subtle';
        default:
            return 'bg-secondary-subtle text-secondary border border-secondary-subtle';
    }
};

const getRoleLabel = (role) => {
    switch (role) {
        case 'owner':
            return 'Pemilik (Owner)';
        case 'admin':
            return 'Admin Workspace';
        default:
            return 'Anggota (Member)';
    }
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Manajemen Ruang Kerja (Workspaces)" />

        <!-- Header Title Card -->
        <div class="card border border-secondary-subtle shadow-sm rounded-3 mb-4">
            <div class="card-body p-3 p-md-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <h4 class="fw-bold mb-0">Manajemen Ruang Kerja (Workspaces)</h4>
                            <span class="badge bg-primary text-white rounded-pill px-2 py-1 fs-11">
                                {{ workspaces.length }} Workspace
                            </span>
                        </div>
                        <p class="text-muted fs-13 mb-0">Kelola workspace divisi/organisasi, tambahkan anggota tim, dan kelola peran akses.</p>
                    </div>
                    <button
                        type="button"
                        @click="showCreateModal = true"
                        class="btn btn-primary btn-sm px-3 rounded-pill fw-semibold shadow-sm"
                    >
                        <i class="ri-add-line me-1"></i> Buat Workspace Baru
                    </button>
                </div>
            </div>
        </div>

        <!-- Empty State if no workspaces -->
        <div v-if="workspaces.length === 0" class="card border border-secondary-subtle rounded-3 p-5 text-center shadow-sm">
            <div class="py-4">
                <div class="bg-primary-subtle text-primary rounded-circle d-inline-flex align-items-center justify-content-center p-3 mb-3" style="width: 64px; height: 64px;">
                    <i class="ri-community-line fs-32"></i>
                </div>
                <h5 class="fw-bold mb-2">Belum Ada Ruang Kerja (Workspace)</h5>
                <p class="text-muted fs-13 mb-4 mx-auto" style="max-width: 480px;">
                    Anda belum memiliki atau belum tergabung ke ruang kerja manapun. Buat workspace baru untuk mulai mengorganisir proyek, papan Kanban, dan tim Anda.
                </p>
                <button type="button" @click="showCreateModal = true" class="btn btn-primary rounded-pill px-4 fw-semibold shadow-sm">
                    <i class="ri-add-line me-1"></i> Buat Workspace Pertama Anda
                </button>
            </div>
        </div>

        <!-- Workspaces Cards Grid -->
        <div v-else class="row g-3">
            <div v-for="ws in workspaces" :key="ws.id" class="col-md-6 col-xl-4">
                <div
                    class="card h-100 border rounded-3 shadow-sm transition-all"
                    :class="ws.is_active ? 'border-primary shadow-sm' : 'border-secondary-subtle'"
                >
                    <div class="card-body p-4 d-flex flex-column">
                        <!-- Top Card Header -->
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="d-flex align-items-center gap-3">
                                <div
                                    class="bg-primary text-white rounded-3 fs-20 d-flex align-items-center justify-content-center flex-shrink-0"
                                    style="width: 44px; height: 44px;"
                                >
                                    <i class="ri-community-line"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold mb-0 fs-15 text-body text-truncate" style="max-width: 170px;">
                                        {{ ws.name }}
                                    </h6>
                                    <span class="fs-11 text-muted d-block">
                                        <i class="ri-user-star-line me-1 text-primary"></i>Owner: {{ ws.owner?.name || 'Sistem' }}
                                    </span>
                                </div>
                            </div>

                            <div class="d-flex align-items-center gap-1">
                                <span
                                    v-if="ws.is_active"
                                    class="badge bg-primary text-white rounded-pill px-2 py-1 fs-11"
                                >
                                    <i class="ri-checkbox-circle-fill me-1"></i> Aktif
                                </span>

                                <!-- Dropdown Opsi Workspace (Edit / Hapus) -->
                                <div v-if="ws.can_manage" class="dropdown">
                                    <button
                                        class="btn btn-sm btn-light border-0 text-muted p-1 rounded-circle"
                                        type="button"
                                        data-bs-toggle="dropdown"
                                        aria-expanded="false"
                                        title="Opsi Ruang Kerja"
                                    >
                                        <i class="ri-more-2-fill fs-16"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow border rounded-3 p-2 fs-12">
                                        <li>
                                            <button
                                                type="button"
                                                class="dropdown-item rounded py-1 px-2 d-flex align-items-center gap-2"
                                                @click="openEditModal(ws)"
                                            >
                                                <i class="ri-edit-line text-muted"></i>
                                                <span>Ubah Nama</span>
                                            </button>
                                        </li>
                                        <li>
                                            <button
                                                type="button"
                                                class="dropdown-item rounded py-1 px-2 d-flex align-items-center gap-2"
                                                @click="openMembersModal(ws)"
                                            >
                                                <i class="ri-team-line text-primary"></i>
                                                <span>Kelola Anggota</span>
                                            </button>
                                        </li>
                                        <li v-if="ws.is_owner || ws.can_manage">
                                            <hr class="dropdown-divider my-1">
                                        </li>
                                        <li v-if="ws.is_owner || ws.can_manage">
                                            <button
                                                type="button"
                                                class="dropdown-item rounded py-1 px-2 d-flex align-items-center gap-2 text-danger"
                                                @click="deleteWorkspace(ws)"
                                            >
                                                <i class="ri-delete-bin-line"></i>
                                                <span>Hapus Workspace</span>
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Workspace Meta Info Grid -->
                        <div class="row g-2 py-3 border-top border-bottom my-2 fs-13">
                            <div class="col-6">
                                <span class="text-muted d-block fs-11">ROLE ANDA</span>
                                <span class="badge rounded-pill px-2 py-1 fs-11 text-capitalize" :class="getRoleBadgeClass(ws.role)">
                                    {{ getRoleLabel(ws.role) }}
                                </span>
                            </div>
                            <div class="col-6">
                                <span class="text-muted d-block fs-11">TOTAL SPACES</span>
                                <span class="fw-semibold text-body">{{ ws.spaces_count }} Space</span>
                            </div>
                            <div class="col-6">
                                <span class="text-muted d-block fs-11">TOTAL ANGGOTA</span>
                                <span class="fw-semibold text-body">{{ ws.members_count }} Member</span>
                            </div>
                            <div class="col-6">
                                <span class="text-muted d-block fs-11">DIBUAT PADA</span>
                                <span class="fw-semibold text-body">{{ ws.created_at }}</span>
                            </div>
                        </div>

                        <!-- Members Preview Avatars -->
                        <div class="d-flex align-items-center justify-content-between my-2">
                            <span class="fs-12 text-muted fw-semibold">Anggota Terdaftar:</span>
                            <div class="d-flex align-items-center">
                                <div
                                    v-for="(m, i) in ws.members.slice(0, 4)"
                                    :key="m.id"
                                    class="rounded-circle text-white d-flex align-items-center justify-content-center fw-semibold fs-11 border border-2 border-white shadow-xs"
                                    :class="i === 0 ? 'bg-danger' : 'bg-primary'"
                                    :style="{ marginLeft: i > 0 ? '-8px' : '0', width: '28px', height: '28px', zIndex: 10 - i }"
                                    :title="`${m.name} (${getRoleLabel(m.role)})`"
                                >
                                    {{ m.name.charAt(0).toUpperCase() }}
                                </div>
                                <span
                                    v-if="ws.members.length > 4"
                                    class="badge bg-light text-muted border rounded-circle ms-1 fs-10 d-flex align-items-center justify-content-center"
                                    style="width: 28px; height: 28px;"
                                >
                                    +{{ ws.members.length - 4 }}
                                </span>
                            </div>
                        </div>

                        <!-- Card Action Buttons -->
                        <div class="mt-auto pt-3 d-flex gap-2">
                            <button
                                type="button"
                                @click="openMembersModal(ws)"
                                class="btn btn-outline-primary btn-sm rounded-pill fw-semibold flex-grow-1"
                            >
                                <i class="ri-user-settings-line me-1"></i> Kelola Anggota
                            </button>

                            <button
                                v-if="!ws.is_active"
                                type="button"
                                @click="switchWorkspace(ws.id)"
                                class="btn btn-primary btn-sm rounded-pill fw-semibold px-3"
                                title="Beralih ke workspace ini"
                            >
                                <i class="ri-swap-box-line me-1"></i> Aktifkan
                            </button>
                            <button
                                v-else
                                type="button"
                                class="btn btn-light btn-sm rounded-pill fw-semibold text-muted px-3"
                                disabled
                            >
                                <i class="ri-check-line me-1 text-success"></i> Aktif
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL 1: KELOLA ANGGOTA WORKSPACE -->
        <div v-if="showMembersModal && selectedWorkspace" class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content rounded-4 border-0 shadow-lg">
                    <div class="modal-header border-bottom py-3 px-4">
                        <div class="d-flex align-items-center gap-2">
                            <div class="bg-primary text-white rounded-3 p-2 fs-16 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                <i class="ri-group-line"></i>
                            </div>
                            <div>
                                <h5 class="modal-title fw-bold fs-16 mb-0">Kelola Anggota Workspace</h5>
                                <span class="fs-12 text-muted">{{ selectedWorkspace.name }} ({{ selectedWorkspace.members?.length || 0 }} Anggota)</span>
                            </div>
                        </div>
                        <button type="button" @click="showMembersModal = false" class="btn-close" aria-label="Close"></button>
                    </div>

                    <div class="modal-body p-4">
                        <!-- Form Tambah Anggota Baru ke Workspace (Jika punya akses) -->
                        <div v-if="selectedWorkspace.can_manage" class="card bg-light border-0 rounded-3 p-3 mb-4">
                            <h6 class="fw-bold fs-13 mb-2 text-primary">
                                <i class="ri-user-add-line me-1"></i> Tambahkan Anggota Baru ke Workspace
                            </h6>
                            <p class="fs-12 text-muted mb-3">
                                Pilih pengguna yang sudah terdaftar di sistem untuk dilibatkan dalam ruang kerja ini.
                            </p>

                            <div v-if="availableUsers.length > 0">
                                <form @submit.prevent="submitAddMember" class="row g-2 align-items-end">
                                    <div class="col-md-7">
                                        <label class="form-label fs-12 fw-semibold">Pilih Pengguna Sistem</label>
                                        <select v-model="addMemberForm.user_id" class="form-select form-select-sm" required>
                                            <option value="" disabled>-- Pilih Pengguna Terdaftar --</option>
                                            <option v-for="user in availableUsers" :key="user.id" :value="user.id">
                                                {{ user.name }} ({{ user.email }}) — {{ user.role }}
                                            </option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label fs-12 fw-semibold">Peran di Workspace</label>
                                        <select v-model="addMemberForm.role" class="form-select form-select-sm">
                                            <option value="member">Anggota (Member)</option>
                                            <option value="admin">Administrator</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <button
                                            type="submit"
                                            class="btn btn-primary btn-sm w-100 fw-semibold rounded-pill"
                                            :disabled="addMemberForm.processing || !addMemberForm.user_id"
                                        >
                                            <span v-if="addMemberForm.processing" class="spinner-border spinner-border-sm me-1"></span>
                                            <span v-else><i class="ri-add-line me-1"></i> Tambah</span>
                                        </button>
                                    </div>
                                </form>
                            </div>
                            <div v-else class="alert alert-info py-2 px-3 mb-0 fs-12 d-flex align-items-center gap-2">
                                <i class="ri-information-line fs-16 text-primary"></i>
                                <span>Semua pengguna yang terdaftar di sistem telah bergabung di workspace ini.</span>
                            </div>
                        </div>

                        <!-- Daftar Anggota Workspace Saat Ini -->
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <h6 class="fw-bold fs-14 mb-0">Daftar Anggota Saat Ini</h6>
                            <span class="fs-12 text-muted">{{ selectedWorkspace.members?.length || 0 }} Orang Tergabung</span>
                        </div>

                        <div class="table-responsive border rounded-3">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light fs-12 text-uppercase">
                                    <tr>
                                        <th>Pengguna</th>
                                        <th>Peran di Workspace</th>
                                        <th>Bergabung Sejak</th>
                                        <th v-if="selectedWorkspace.can_manage" class="text-end">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="fs-13">
                                    <tr v-for="member in selectedWorkspace.members" :key="member.id">
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div
                                                    class="rounded-circle text-white d-flex align-items-center justify-content-center fw-semibold fs-12 flex-shrink-0"
                                                    :class="member.is_owner ? 'bg-danger' : 'bg-primary'"
                                                    style="width: 34px; height: 34px;"
                                                >
                                                    {{ member.name.charAt(0).toUpperCase() }}
                                                </div>
                                                <div>
                                                    <div class="fw-semibold text-body">
                                                        {{ member.name }}
                                                        <span v-if="member.id === currentUser.id" class="badge bg-light text-muted border fs-10 ms-1">Anda</span>
                                                    </div>
                                                    <div class="fs-11 text-muted">{{ member.email }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <!-- Jika Owner: Kunci Permanen -->
                                            <span v-if="member.is_owner" class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2 py-1 fs-11">
                                                <i class="ri-vip-crown-fill me-1"></i> Pemilik (Owner)
                                            </span>

                                            <!-- Jika Bukan Owner & Punya Akses: Dropdown Ubah Peran -->
                                            <div v-else-if="selectedWorkspace.can_manage" class="dropdown d-inline-block">
                                                <button
                                                    class="btn btn-sm btn-light border rounded-pill px-2 py-1 fs-11 d-inline-flex align-items-center gap-1 text-capitalize"
                                                    type="button"
                                                    data-bs-toggle="dropdown"
                                                    aria-expanded="false"
                                                >
                                                    <span :class="member.role === 'admin' ? 'text-primary fw-semibold' : 'text-secondary'">
                                                        {{ getRoleLabel(member.role) }}
                                                    </span>
                                                    <i class="bi bi-chevron-down fs-10 text-muted ms-1"></i>
                                                </button>
                                                <ul class="dropdown-menu shadow border rounded-3 p-1 fs-11" style="min-width: 140px;">
                                                    <li>
                                                        <button
                                                            type="button"
                                                            class="dropdown-item rounded py-1 px-2"
                                                            :class="{ 'text-primary fw-bold': member.role === 'admin' }"
                                                            @click="updateMemberRole(member, 'admin')"
                                                        >
                                                            Administrator
                                                        </button>
                                                    </li>
                                                    <li>
                                                        <button
                                                            type="button"
                                                            class="dropdown-item rounded py-1 px-2"
                                                            :class="{ 'text-secondary fw-bold': member.role === 'member' }"
                                                            @click="updateMemberRole(member, 'member')"
                                                        >
                                                            Anggota (Member)
                                                        </button>
                                                    </li>
                                                </ul>
                                            </div>

                                            <!-- Jika tidak punya akses ubah: Tampilkan Badge -->
                                            <span v-else class="badge rounded-pill px-2 py-1 fs-11" :class="getRoleBadgeClass(member.role)">
                                                {{ getRoleLabel(member.role) }}
                                            </span>
                                        </td>
                                        <td class="fs-12 text-muted">
                                            {{ member.joined_at }}
                                        </td>
                                        <td v-if="selectedWorkspace.can_manage" class="text-end">
                                            <button
                                                v-if="!member.is_owner"
                                                type="button"
                                                @click="removeMember(member)"
                                                class="btn btn-sm btn-outline-danger border-0 rounded-pill px-2 py-1 fs-11 d-inline-flex align-items-center gap-1"
                                                title="Keluarkan dari ruang kerja"
                                            >
                                                <i class="ri-user-unfollow-line"></i>
                                                <span class="d-none d-md-inline">Keluarkan</span>
                                            </button>
                                            <span v-else class="fs-11 text-muted italic">Pemilik</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="modal-footer border-top py-2 px-4">
                        <button type="button" @click="showMembersModal = false" class="btn btn-secondary rounded-pill px-4 btn-sm">
                            Tutup
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL 2: BUAT WORKSPACE BARU -->
        <div v-if="showCreateModal" class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0, 0, 0, 0.5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content rounded-4 border-0 shadow">
                    <div class="modal-header border-bottom py-3 px-4">
                        <h5 class="modal-title fw-bold fs-16">Buat Ruang Kerja Baru</h5>
                        <button type="button" @click="showCreateModal = false" class="btn-close" aria-label="Close"></button>
                    </div>
                    <form @submit.prevent="submitCreateWorkspace">
                        <div class="modal-body p-4">
                            <div class="mb-3">
                                <label for="ws_name" class="form-label fs-13 fw-semibold">Nama Workspace</label>
                                <input
                                    id="ws_name"
                                    v-model="createForm.name"
                                    type="text"
                                    class="form-control"
                                    :class="{ 'is-invalid': createForm.errors.name }"
                                    placeholder="Contoh: Digital Marketing Agency"
                                    required
                                    autofocus
                                />
                                <div v-if="createForm.errors.name" class="text-danger fs-12 mt-1">
                                    {{ createForm.errors.name }}
                                </div>
                                <div class="form-text fs-12 text-muted">
                                    Slug URL unik akan dibuat otomatis untuk ruang kerja ini. Anda dapat mengundang anggota setelahnya.
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer border-top py-2 px-4">
                            <button type="button" @click="showCreateModal = false" class="btn btn-light rounded-pill px-3">Batal</button>
                            <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold" :disabled="createForm.processing">
                                <span v-if="createForm.processing" class="spinner-border spinner-border-sm me-1"></span>
                                Simpan Workspace
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- MODAL 3: EDIT NAMA WORKSPACE -->
        <div v-if="showEditModal" class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0, 0, 0, 0.5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content rounded-4 border-0 shadow">
                    <div class="modal-header border-bottom py-3 px-4">
                        <h5 class="modal-title fw-bold fs-16">Ubah Nama Ruang Kerja</h5>
                        <button type="button" @click="showEditModal = false" class="btn-close" aria-label="Close"></button>
                    </div>
                    <form @submit.prevent="submitEditWorkspace">
                        <div class="modal-body p-4">
                            <div class="mb-3">
                                <label for="edit_ws_name" class="form-label fs-13 fw-semibold">Nama Workspace Baru</label>
                                <input
                                    id="edit_ws_name"
                                    v-model="editWorkspaceForm.name"
                                    type="text"
                                    class="form-control"
                                    :class="{ 'is-invalid': editWorkspaceForm.errors.name }"
                                    required
                                    autofocus
                                />
                                <div v-if="editWorkspaceForm.errors.name" class="text-danger fs-12 mt-1">
                                    {{ editWorkspaceForm.errors.name }}
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer border-top py-2 px-4">
                            <button type="button" @click="showEditModal = false" class="btn btn-light rounded-pill px-3">Batal</button>
                            <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold" :disabled="editWorkspaceForm.processing">
                                <span v-if="editWorkspaceForm.processing" class="spinner-border spinner-border-sm me-1"></span>
                                Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
