<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { confirmDelete, confirmAction, showToast, showWarning } from '@/Utils/alert';

const props = defineProps({
    workspace: {
        type: Object,
        required: true,
    },
    spaces: {
        type: Array,
        required: true,
    },
    workspaceMembers: {
        type: Array,
        default: () => [],
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

// ==========================================
// SPACE MEMBER MANAGEMENT MODAL & SELECT2
// ==========================================
const showMemberModal = ref(false);
const activeSpace = ref(null);
const isUserDropdownOpen = ref(false);
const userSearchQuery = ref('');

const addMemberForm = useForm({
    user_ids: [],
    role: 'member',
});

const openMemberModal = (space) => {
    activeSpace.value = JSON.parse(JSON.stringify(space));
    addMemberForm.reset();
    addMemberForm.user_ids = [];
    addMemberForm.role = 'member';
    userSearchQuery.value = '';
    isUserDropdownOpen.value = false;
    showMemberModal.value = true;
};

// Members in workspace who are not yet members of this space
const availableUsers = computed(() => {
    if (!activeSpace.value) return [];
    const currentMemberIds = (activeSpace.value.members || []).map((m) => m.id);
    return props.workspaceMembers.filter((u) => !currentMemberIds.includes(u.id));
});

const filteredAvailableUsers = computed(() => {
    if (!userSearchQuery.value.trim()) return availableUsers.value;
    const q = userSearchQuery.value.toLowerCase();
    return availableUsers.value.filter(
        (u) => u.name.toLowerCase().includes(q) || (u.email && u.email.toLowerCase().includes(q))
    );
});

const toggleSelectUser = (user) => {
    const idx = addMemberForm.user_ids.indexOf(user.id);
    if (idx > -1) {
        addMemberForm.user_ids.splice(idx, 1);
    } else {
        addMemberForm.user_ids.push(user.id);
    }
};

const selectedUserObjects = computed(() => {
    return props.workspaceMembers.filter((u) => addMemberForm.user_ids.includes(u.id));
});

const submitAddMember = () => {
    if (!activeSpace.value || addMemberForm.user_ids.length === 0) return;

    addMemberForm.post(`/spaces/${activeSpace.value.id}/members`, {
        preserveScroll: true,
        onSuccess: () => {
            showToast('Anggota berhasil ditambahkan ke Space!', 'success');
            // Update local space members
            const updatedSpace = props.spaces.find((s) => s.id === activeSpace.value.id);
            if (updatedSpace) {
                activeSpace.value = JSON.parse(JSON.stringify(updatedSpace));
            }
            addMemberForm.user_ids = [];
            isUserDropdownOpen.value = false;
        },
    });
};

const updateMemberRole = async (member, newRole) => {
    const roleLabel = newRole === 'manager' ? 'Manajer Space' : 'Anggota (Member)';
    const confirmed = await confirmAction({
        title: 'Ubah Peran?',
        text: `Ubah peran ${member.name} menjadi ${roleLabel}?`,
        confirmButtonText: 'Ya, Ubah Peran',
    });
    if (confirmed) {
        router.put(`/spaces/${activeSpace.value.id}/members/${member.id}`, {
            role: newRole,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                showToast(`Peran ${member.name} berhasil diubah!`, 'success');
                const updatedSpace = props.spaces.find((s) => s.id === activeSpace.value.id);
                if (updatedSpace) {
                    activeSpace.value = JSON.parse(JSON.stringify(updatedSpace));
                }
            },
        });
    }
};

const removeMemberFromSpace = async (member) => {
    const confirmed = await confirmDelete({
        title: 'Keluarkan dari Space?',
        text: `Yakin ingin mengeluarkan ${member.name} dari Space "${activeSpace.value.name}"? Pengguna tidak akan dapat melihat Space ini lagi.`,
        confirmButtonText: 'Ya, Keluarkan!',
    });
    if (confirmed) {
        router.delete(`/spaces/${activeSpace.value.id}/members/${member.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                showToast(`${member.name} berhasil dikeluarkan dari Space.`, 'success');
                const updatedSpace = props.spaces.find((s) => s.id === activeSpace.value.id);
                if (updatedSpace) {
                    activeSpace.value = JSON.parse(JSON.stringify(updatedSpace));
                }
            },
        });
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
                                        <li>
                                            <button
                                                type="button"
                                                class="dropdown-item rounded py-1 px-2 d-flex align-items-center gap-2 text-body"
                                                @click="openMemberModal(space)"
                                            >
                                                <i class="ri-group-line text-success"></i>
                                                <span>Kelola Anggota Space</span>
                                            </button>
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

                        <!-- Members Row & Stats Meta -->
                        <div class="d-flex justify-content-between align-items-center py-2 border-top border-bottom fs-12 text-muted mb-3">
                            <div class="d-flex align-items-center">
                                <div
                                    v-for="(member, idx) in space.members?.slice(0, 4)"
                                    :key="member.id"
                                    class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-semibold fs-11 border border-2 border-white shadow-xs"
                                    style="width: 28px; height: 28px; margin-left: -6px;"
                                    :style="idx === 0 ? 'margin-left: 0;' : ''"
                                    :title="member.name"
                                >
                                    {{ member.name.charAt(0).toUpperCase() }}
                                </div>
                                <span v-if="space.members?.length > 4" class="ms-1 fs-11 text-muted fw-semibold">
                                    +{{ space.members.length - 4 }}
                                </span>
                                <button
                                    type="button"
                                    class="btn btn-xs btn-link text-decoration-none p-0 ms-2 text-primary fs-11 fw-semibold"
                                    @click="openMemberModal(space)"
                                >
                                    <i class="ri-user-add-line"></i> Anggota ({{ space.members?.length || 0 }})
                                </button>
                            </div>
                            <div>
                                <i class="ri-checkbox-line me-1"></i>
                                <span>{{ space.completed_tasks }}/{{ space.total_tasks }} Tasks</span>
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

        <!-- Kelola Anggota Space Modal -->
        <div v-if="showMemberModal && activeSpace" class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0, 0, 0, 0.5);">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content rounded-4 border-0 shadow-lg">
                    <div class="modal-header border-bottom py-3 px-4">
                        <div>
                            <h5 class="modal-title fw-bold fs-16 mb-0 d-flex align-items-center gap-2">
                                <span>Kelola Anggota Space</span>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill fs-11">
                                    {{ activeSpace.name }}
                                </span>
                            </h5>
                            <p class="text-muted fs-12 mb-0 mt-1">
                                Hanya anggota yang dimasukkan ke Space ini yang dapat melihat dan mengaksesnya.
                            </p>
                        </div>
                        <button type="button" @click="showMemberModal = false" class="btn-close" aria-label="Close"></button>
                    </div>

                    <div class="modal-body p-4">
                        <!-- Form Tambah Anggota (Select2 Style Multi-Select) -->
                        <div class="card border border-secondary-subtle rounded-3 p-3 mb-4 bg-light-subtle">
                            <h6 class="fw-bold fs-13 mb-2 d-flex align-items-center gap-1">
                                <i class="ri-user-add-line text-primary"></i> Tambah Anggota dari Workspace
                            </h6>

                            <div v-if="availableUsers.length > 0">
                                <form @submit.prevent="submitAddMember" class="row g-2 align-items-end">
                                    <!-- Select2 User Picker -->
                                    <div class="col-md-7 position-relative">
                                        <label class="form-label fs-12 fw-semibold mb-1">Pilih Anggota</label>
                                        
                                        <!-- Select2 Clickable Input Box -->
                                        <div
                                            class="form-control form-control-sm p-1 d-flex flex-wrap align-items-center gap-1 cursor-pointer bg-light-subtle rounded-2 min-h-38"
                                            :class="{ 'border-primary shadow-xs': isUserDropdownOpen }"
                                            @click="isUserDropdownOpen = !isUserDropdownOpen"
                                        >
                                            <span
                                                v-for="u in selectedUserObjects"
                                                :key="u.id"
                                                class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill py-1 px-2 d-flex align-items-center gap-1 fs-11"
                                                @click.stop
                                            >
                                                {{ u.name }}
                                                <i class="ri-close-line cursor-pointer" @click="toggleSelectUser(u)"></i>
                                            </span>
                                            <span v-if="!addMemberForm.user_ids.length" class="text-muted fs-12 ms-2">
                                                Klik untuk memilih anggota workspace...
                                            </span>
                                        </div>

                                        <!-- Dropdown Menu List -->
                                        <div
                                            v-if="isUserDropdownOpen"
                                            class="position-absolute start-0 end-0 mt-1 bg-body border border-secondary-subtle rounded-3 shadow-lg p-2 z-3"
                                            style="top: 100%; max-height: 240px; overflow-y: auto;"
                                        >
                                            <!-- Search filter inside dropdown -->
                                            <div class="input-group input-group-sm mb-2">
                                                <span class="input-group-text bg-transparent border-end-0">
                                                    <i class="ri-search-line text-muted"></i>
                                                </span>
                                                <input
                                                    v-model="userSearchQuery"
                                                    type="text"
                                                    class="form-control border-start-0 fs-12"
                                                    placeholder="Cari anggota..."
                                                    autofocus
                                                    @click.stop
                                                />
                                            </div>

                                            <div class="d-flex flex-column gap-1">
                                                <div
                                                    v-for="u in filteredAvailableUsers"
                                                    :key="u.id"
                                                    class="d-flex align-items-center justify-content-between p-2 rounded-2 cursor-pointer transition-all hover-bg-item"
                                                    :class="{ 'bg-primary-subtle text-primary': addMemberForm.user_ids.includes(u.id) }"
                                                    @click.stop="toggleSelectUser(u)"
                                                >
                                                    <div class="d-flex align-items-center gap-2 overflow-hidden">
                                                        <div
                                                            class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold fs-11 flex-shrink-0"
                                                            :class="addMemberForm.user_ids.includes(u.id) ? 'bg-primary' : 'bg-secondary'"
                                                            style="width: 26px; height: 26px;"
                                                        >
                                                            {{ u.name.charAt(0).toUpperCase() }}
                                                        </div>
                                                        <div class="overflow-hidden">
                                                            <div class="fs-12 fw-semibold text-truncate text-body">{{ u.name }}</div>
                                                            <div class="fs-10 text-muted text-truncate">{{ u.email }}</div>
                                                        </div>
                                                    </div>
                                                    <i
                                                        :class="addMemberForm.user_ids.includes(u.id) ? 'ri-checkbox-circle-fill text-primary fs-16' : 'ri-checkbox-blank-circle-line text-muted fs-16'"
                                                    ></i>
                                                </div>
                                                <div v-if="!filteredAvailableUsers.length" class="text-center py-2 text-muted fs-12">
                                                    Tidak ada anggota yang cocok.
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <label class="form-label fs-12 fw-semibold mb-1">Peran di Space</label>
                                        <select v-model="addMemberForm.role" class="form-select form-select-sm rounded-2">
                                            <option value="member">Anggota (Member)</option>
                                            <option value="manager">Manajer Space</option>
                                            <option value="viewer">Pengamat (Viewer)</option>
                                        </select>
                                    </div>

                                    <div class="col-md-2">
                                        <button
                                            type="submit"
                                            class="btn btn-primary btn-sm w-100 fw-semibold rounded-pill"
                                            :disabled="addMemberForm.processing || !addMemberForm.user_ids.length"
                                        >
                                            <i class="ri-user-add-line me-1"></i> Tambah
                                        </button>
                                    </div>
                                </form>
                            </div>
                            <div v-else class="alert alert-info py-2 px-3 mb-0 fs-12 d-flex align-items-center gap-2">
                                <i class="ri-information-line fs-16 text-primary"></i>
                                <span>Seluruh anggota workspace ini sudah terdaftar di dalam Space ini.</span>
                            </div>
                        </div>

                        <!-- Current Space Members List Table -->
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <h6 class="fw-bold fs-13 mb-0">Anggota Terdaftar ({{ activeSpace.members?.length || 0 }})</h6>
                            <span class="fs-11 text-muted">Hanya anggota berikut yang memiliki izin mengakses Space ini</span>
                        </div>

                        <div class="table-responsive border border-secondary-subtle rounded-3">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light-subtle fs-11 text-muted text-uppercase border-bottom">
                                    <tr>
                                        <th>Pengguna</th>
                                        <th>Peran di Space</th>
                                        <th class="text-end">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="fs-12">
                                    <tr v-for="member in activeSpace.members" :key="member.id">
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-semibold fs-11" style="width: 30px; height: 30px;">
                                                    {{ member.name.charAt(0).toUpperCase() }}
                                                </div>
                                                <div>
                                                    <div class="fw-semibold text-body">{{ member.name }}</div>
                                                    <div class="fs-11 text-muted">{{ member.email }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span
                                                class="badge rounded-pill px-2 py-1 fs-10 text-capitalize"
                                                :class="member.pivot?.role === 'manager' ? 'bg-danger-subtle text-danger border border-danger-subtle' : 'bg-primary-subtle text-primary border border-primary-subtle'"
                                            >
                                                {{ member.pivot?.role || 'member' }}
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-link text-danger p-0 border-0"
                                                title="Keluarkan dari Space"
                                                @click="removeMemberFromSpace(member)"
                                            >
                                                <i class="ri-delete-bin-line fs-14"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <tr v-if="!activeSpace.members?.length">
                                        <td colspan="3" class="text-center py-3 text-muted fs-12">
                                            Belum ada anggota di Space ini.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
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
.hover-bg-item:hover {
    background-color: var(--bs-tertiary-bg, rgba(255, 255, 255, 0.05));
}
.cursor-pointer {
    cursor: pointer;
}
.min-h-38 {
    min-height: 38px;
}
</style>

