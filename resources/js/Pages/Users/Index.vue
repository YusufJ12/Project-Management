<script setup>
import { ref, watch } from 'vue';
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    users: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({
            search: '',
            role: 'all',
        }),
    },
    stats: {
        type: Object,
        default: () => ({
            total: 0,
            super_admin: 0,
            admin: 0,
            member: 0,
        }),
    },
});

const page = usePage();
const currentUser = page.props.auth?.user || {};

const search = ref(props.filters.search || '');
const role = ref(props.filters.role || 'all');

let searchDebounce = null;

const applyFilters = () => {
    router.get(
        '/users',
        {
            search: search.value || undefined,
            role: role.value !== 'all' ? role.value : undefined,
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

watch(role, () => {
    applyFilters();
});

// Create User Modal
const showCreateModal = ref(false);
const createForm = useForm({
    name: '',
    username: '',
    email: '',
    password: '',
    role: 'member',
});

const submitCreateUser = () => {
    createForm.post('/users', {
        onSuccess: () => {
            createForm.reset();
            showCreateModal.value = false;
        },
    });
};

// Edit User Modal
const showEditModal = ref(false);
const editForm = useForm({
    id: null,
    name: '',
    username: '',
    email: '',
    password: '',
    role: 'member',
    is_super_admin: false,
});

const openEditModal = (user) => {
    editForm.id = user.id;
    editForm.name = user.name;
    editForm.username = user.username || '';
    editForm.email = user.email;
    editForm.password = '';
    editForm.role = user.role;
    editForm.is_super_admin = user.role === 'super_admin';
    showEditModal.value = true;
};

const submitEditUser = () => {
    editForm.put(`/users/${editForm.id}`, {
        onSuccess: () => {
            showEditModal.value = false;
            editForm.reset();
        },
    });
};

// Change Role In-Place (Only for non-super_admin)
const changeRole = (user, newRole) => {
    if (user.role === 'super_admin') {
        alert('Role Super Admin bersifat permanen dan tidak dapat diubah.');
        return;
    }
    if (user.role === newRole) return;
    if (confirm(`Yakin ingin mengubah role ${user.name} menjadi ${newRole}?`)) {
        router.put(`/users/${user.id}/role`, {
            role: newRole,
        }, {
            preserveScroll: true,
        });
    }
};

// Delete User
const deleteUser = (user) => {
    if (user.role === 'super_admin') {
        alert('Akun dengan role Super Admin bersifat permanen dan tidak dapat dihapus.');
        return;
    }
    if (confirm(`Yakin ingin menghapus pengguna ${user.name}? Tindakan ini tidak dapat dibatalkan.`)) {
        router.delete(`/users/${user.id}`, {
            preserveScroll: true,
        });
    }
};

const getRoleBadgeClass = (r) => {
    switch (r) {
        case 'super_admin':
            return 'bg-danger text-white';
        case 'admin':
            return 'bg-primary-subtle text-primary border border-primary-subtle';
        default:
            return 'bg-secondary-subtle text-secondary border border-secondary-subtle';
    }
};

const getRoleLabel = (r) => {
    switch (r) {
        case 'super_admin':
            return 'Super Admin';
        case 'admin':
            return 'Administrator';
        default:
            return 'Anggota (Member)';
    }
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Manajemen Pengguna & RBAC" />

        <!-- Header Title Card with Border -->
        <div class="card border border-secondary-subtle shadow-sm rounded-3 mb-4">
            <div class="card-body p-3 p-md-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <h4 class="fw-bold mb-0">Manajemen Pengguna & Hak Akses (RBAC)</h4>
                            <span class="badge bg-danger text-white rounded-pill px-2 py-1 fs-11">
                                <i class="bi bi-shield-lock-fill me-1"></i> Area Super Admin
                            </span>
                        </div>
                        <p class="text-muted fs-13 mb-0">Kelola akun pengguna, penetapan peran sistem (Super Admin, Admin, Member), dan otorisasi tim.</p>
                    </div>
                    <div v-if="currentUser.is_super_admin" class="d-flex align-items-center gap-2">
                        <button
                            type="button"
                            @click="showCreateModal = true"
                            class="btn btn-primary btn-sm px-3 rounded-pill fw-semibold"
                        >
                            <i class="ri-user-add-line me-1"></i> Tambah Pengguna Baru
                        </button>
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
                            <span class="fs-12 text-muted fw-semibold">TOTAL PENGGUNA</span>
                            <div class="bg-primary-subtle text-primary rounded-circle p-2 fs-16 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                <i class="ri-team-line"></i>
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
                            <span class="fs-12 text-muted fw-semibold">SUPER ADMIN</span>
                            <div class="bg-danger-subtle text-danger rounded-circle p-2 fs-16 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                <i class="ri-shield-star-line"></i>
                            </div>
                        </div>
                        <h3 class="fw-bold mb-0 text-danger">{{ stats.super_admin }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card border border-secondary-subtle shadow-sm rounded-3 h-100">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="fs-12 text-muted fw-semibold">ADMINISTRATOR</span>
                            <div class="bg-primary-subtle text-primary rounded-circle p-2 fs-16 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                <i class="ri-admin-line"></i>
                            </div>
                        </div>
                        <h3 class="fw-bold mb-0 text-primary">{{ stats.admin }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card border border-secondary-subtle shadow-sm rounded-3 h-100">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="fs-12 text-muted fw-semibold">MEMBER / ANGGOTA</span>
                            <div class="bg-secondary-subtle text-secondary rounded-circle p-2 fs-16 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                <i class="ri-user-line"></i>
                            </div>
                        </div>
                        <h3 class="fw-bold mb-0 text-secondary">{{ stats.member }}</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Users Table Card -->
        <div class="card border border-secondary-subtle shadow-sm rounded-3">
            <div class="card-body p-3 p-md-4">
                <!-- Filters Row -->
                <div class="row g-2 mb-3">
                    <div class="col-md-8">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-body border-secondary-subtle text-muted">
                                <i class="bi bi-search"></i>
                            </span>
                            <input
                                type="text"
                                v-model="search"
                                class="form-control border-secondary-subtle"
                                placeholder="Cari nama atau email pengguna..."
                            />
                        </div>
                    </div>
                    <div class="col-md-4">
                        <select v-model="role" class="form-select form-select-sm border-secondary-subtle">
                            <option value="all">Semua Peran (Roles)</option>
                            <option value="super_admin">Super Admin</option>
                            <option value="admin">Administrator</option>
                            <option value="member">Anggota (Member)</option>
                        </select>
                    </div>
                </div>

                <!-- Users Data Table -->
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light fs-12 text-uppercase">
                            <tr>
                                <th>Pengguna</th>
                                <th>Peran Sistem (RBAC)</th>
                                <th>Workspace Terdaftar</th>
                                <th>Tugas Diberikan</th>
                                <th class="text-end">Aksi / Ubah Role</th>
                            </tr>
                        </thead>
                        <tbody class="fs-13">
                            <tr v-if="users.data.length === 0">
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="ri-user-unfollow-line fs-36 d-block mb-2 text-secondary opacity-50"></i>
                                    Tidak ada data pengguna yang sesuai dengan filter.
                                </td>
                            </tr>
                            <tr v-for="u in users.data" :key="u.id">
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div
                                            class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-semibold fs-13 flex-shrink-0"
                                            style="width: 36px; height: 36px;"
                                        >
                                            {{ u.name.charAt(0).toUpperCase() }}
                                        </div>
                                        <div>
                                            <div class="fw-semibold text-body">
                                                {{ u.name }}
                                                <span v-if="u.id === currentUser.id" class="badge bg-light text-muted border fs-10 ms-1">Anda</span>
                                            </div>
                                            <div class="fs-11 text-muted d-flex align-items-center gap-2 flex-wrap">
                                                <span>{{ u.email }}</span>
                                                <span v-if="u.username" class="badge bg-light text-primary border border-secondary-subtle fs-10">
                                                    @{{ u.username }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge rounded-pill px-2 py-1 fs-11" :class="getRoleBadgeClass(u.role)">
                                        <i v-if="u.role === 'super_admin'" class="ri-vip-crown-fill me-1"></i>
                                        <i v-else-if="u.role === 'admin'" class="ri-shield-user-fill me-1"></i>
                                        <i v-else class="ri-user-line me-1"></i>
                                        {{ getRoleLabel(u.role) }}
                                    </span>
                                </td>
                                <td>
                                    <div v-if="u.workspaces?.length" class="d-flex flex-wrap gap-1">
                                        <span v-for="ws in u.workspaces" :key="ws.id" class="badge bg-light text-muted border fs-11">
                                            {{ ws.name }}
                                        </span>
                                    </div>
                                    <span v-else class="fs-12 text-muted">-</span>
                                </td>
                                <td>
                                    <span class="badge bg-primary-subtle text-primary rounded-pill fs-11">
                                        {{ u.assigned_tasks_count || 0 }} Tugas
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div v-if="currentUser.is_super_admin" class="d-inline-flex align-items-center gap-1">
                                        <!-- Tombol Edit Data Pengguna -->
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-light border rounded-pill px-2 py-1 fs-12 d-inline-flex align-items-center gap-1"
                                            @click="openEditModal(u)"
                                            title="Edit data akun pengguna"
                                        >
                                            <i class="ri-edit-line text-muted"></i>
                                            <span class="d-none d-md-inline">Edit</span>
                                        </button>

                                        <!-- Jika Super Admin: Role Permanen Tidak Bisa Diubah -->
                                        <span
                                            v-if="u.role === 'super_admin'"
                                            class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2 py-1 fs-11 d-inline-flex align-items-center gap-1"
                                            title="Role Super Admin bersifat permanen dan tidak dapat diubah"
                                        >
                                            <i class="ri-lock-2-line"></i>
                                            <span>Role Permanen</span>
                                        </span>

                                        <!-- Jika Non-Super Admin: Dropdown Kelola Role & Hapus -->
                                        <div v-else class="dropdown d-inline-block">
                                            <button
                                                class="btn btn-sm btn-light border rounded-pill px-3 fs-12 d-inline-flex align-items-center gap-1"
                                                type="button"
                                                data-bs-toggle="dropdown"
                                                aria-expanded="false"
                                            >
                                                <span>Ubah Role</span>
                                                <i class="bi bi-chevron-down fs-10 text-muted ms-1"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow border rounded-3 p-2" style="min-width: 180px;">
                                                <li class="dropdown-header fs-11 text-muted text-uppercase fw-bold px-2 py-1">Tetapkan Peran</li>
                                                <li>
                                                    <button
                                                        type="button"
                                                        class="dropdown-item d-flex align-items-center justify-content-between rounded py-2 px-2 fs-12"
                                                        :class="{ 'text-primary fw-bold': u.role === 'admin' }"
                                                        @click="changeRole(u, 'admin')"
                                                    >
                                                        <span>Administrator</span>
                                                        <i v-if="u.role === 'admin'" class="ri-check-line text-primary"></i>
                                                    </button>
                                                </li>
                                                <li>
                                                    <button
                                                        type="button"
                                                        class="dropdown-item d-flex align-items-center justify-content-between rounded py-2 px-2 fs-12"
                                                        :class="{ 'text-secondary fw-bold': u.role === 'member' }"
                                                        @click="changeRole(u, 'member')"
                                                    >
                                                        <span>Anggota (Member)</span>
                                                        <i v-if="u.role === 'member'" class="ri-check-line text-secondary"></i>
                                                    </button>
                                                </li>
                                                <li><hr class="dropdown-divider my-1"></li>
                                                <li>
                                                    <button
                                                        type="button"
                                                        class="dropdown-item d-flex align-items-center gap-2 rounded py-2 px-2 fs-12 text-danger"
                                                        @click="deleteUser(u)"
                                                    >
                                                        <i class="ri-delete-bin-line"></i>
                                                        <span>Hapus Akun</span>
                                                    </button>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                    <span v-else class="fs-12 text-muted">Akses Terbatas</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="users.total > 0" class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3 pt-3 border-top border-secondary-subtle">
                    <div class="fs-12 text-muted">
                        Menampilkan {{ users.from || 0 }} - {{ users.to || 0 }} dari {{ users.total }} pengguna
                    </div>
                    <nav v-if="users.links && users.links.length > 1">
                        <ul class="pagination pagination-sm mb-0">
                            <li
                                v-for="(link, i) in users.links"
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

        <!-- Modal Tambah Pengguna Baru -->
        <div v-if="showCreateModal" class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg rounded-3">
                    <div class="modal-header border-bottom border-secondary-subtle">
                        <h5 class="modal-title fw-bold fs-16">Tambah Pengguna Baru</h5>
                        <button type="button" class="btn-close" @click="showCreateModal = false"></button>
                    </div>
                    <form @submit.prevent="submitCreateUser">
                        <div class="modal-body p-4">
                            <div class="mb-3">
                                <label class="form-label fs-13 fw-semibold">Nama Lengkap</label>
                                <input
                                    type="text"
                                    v-model="createForm.name"
                                    class="form-control"
                                    :class="{ 'is-invalid': createForm.errors.name }"
                                    placeholder="Contoh: Ahmad Fauzi"
                                    required
                                />
                                <div v-if="createForm.errors.name" class="invalid-feedback">{{ createForm.errors.name }}</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fs-13 fw-semibold">Username Login (Opsional)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted">@</span>
                                    <input
                                        type="text"
                                        v-model="createForm.username"
                                        class="form-control"
                                        :class="{ 'is-invalid': createForm.errors.username }"
                                        placeholder="fauzi123"
                                    />
                                </div>
                                <div v-if="createForm.errors.username" class="text-danger fs-12 mt-1">{{ createForm.errors.username }}</div>
                                <div class="form-text fs-11 text-muted">Bisa digunakan untuk login tanpa perlu email.</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fs-13 fw-semibold">Alamat Email</label>
                                <input
                                    type="email"
                                    v-model="createForm.email"
                                    class="form-control"
                                    :class="{ 'is-invalid': createForm.errors.email }"
                                    placeholder="fauzi@nexsphere.web.id"
                                    required
                                />
                                <div v-if="createForm.errors.email" class="invalid-feedback">{{ createForm.errors.email }}</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fs-13 fw-semibold">Kata Sandi Awal</label>
                                <input
                                    type="password"
                                    v-model="createForm.password"
                                    class="form-control"
                                    :class="{ 'is-invalid': createForm.errors.password }"
                                    placeholder="Minimal 8 karakter"
                                    required
                                />
                                <div v-if="createForm.errors.password" class="invalid-feedback">{{ createForm.errors.password }}</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fs-13 fw-semibold">Peran Sistem (RBAC)</label>
                                <select v-model="createForm.role" class="form-select">
                                    <option value="member">Anggota Biasa (Member)</option>
                                    <option value="admin">Administrator</option>
                                </select>
                                <div class="form-text fs-11 text-muted">
                                    <i class="ri-shield-check-line me-1 text-primary"></i>Super Admin adalah peran tunggal permanen sistem dan tidak dapat ditambahkan lagi.
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer border-top border-secondary-subtle">
                            <button type="button" class="btn btn-light rounded-pill px-3" @click="showCreateModal = false">Batal</button>
                            <button type="submit" class="btn btn-primary rounded-pill px-4" :disabled="createForm.processing">
                                <span v-if="createForm.processing" class="spinner-border spinner-border-sm me-1"></span>
                                Daftarkan Pengguna
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal Edit Pengguna (Super Admin) -->
        <div v-if="showEditModal" class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg rounded-3">
                    <div class="modal-header border-bottom border-secondary-subtle">
                        <div class="d-flex align-items-center gap-2">
                            <h5 class="modal-title fw-bold fs-16 mb-0">Edit Data Pengguna</h5>
                            <span v-if="editForm.is_super_admin" class="badge bg-danger text-white rounded-pill fs-11">
                                Super Admin
                            </span>
                        </div>
                        <button type="button" class="btn-close" @click="showEditModal = false"></button>
                    </div>
                    <form @submit.prevent="submitEditUser">
                        <div class="modal-body p-4">
                            <div class="mb-3">
                                <label class="form-label fs-13 fw-semibold">Nama Lengkap</label>
                                <input
                                    type="text"
                                    v-model="editForm.name"
                                    class="form-control"
                                    :class="{ 'is-invalid': editForm.errors.name }"
                                    required
                                />
                                <div v-if="editForm.errors.name" class="invalid-feedback">{{ editForm.errors.name }}</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fs-13 fw-semibold">Username Login</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted">@</span>
                                    <input
                                        type="text"
                                        v-model="editForm.username"
                                        class="form-control"
                                        :class="{ 'is-invalid': editForm.errors.username }"
                                        placeholder="username_pengguna"
                                    />
                                </div>
                                <div v-if="editForm.errors.username" class="text-danger fs-12 mt-1">{{ editForm.errors.username }}</div>
                                <div class="form-text fs-11 text-muted">Dapat digunakan pengguna untuk masuk akun selain email.</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fs-13 fw-semibold">Alamat Email</label>
                                <input
                                    type="email"
                                    v-model="editForm.email"
                                    class="form-control"
                                    :class="{ 'is-invalid': editForm.errors.email }"
                                    required
                                />
                                <div v-if="editForm.errors.email" class="invalid-feedback">{{ editForm.errors.email }}</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fs-13 fw-semibold">Kata Sandi Baru (Opsional)</label>
                                <input
                                    type="password"
                                    v-model="editForm.password"
                                    class="form-control"
                                    :class="{ 'is-invalid': editForm.errors.password }"
                                    placeholder="Biarkan kosong jika tidak ingin mengubah sandi"
                                />
                                <div v-if="editForm.errors.password" class="invalid-feedback">{{ editForm.errors.password }}</div>
                            </div>

                            <!-- Role: Jika Super Admin dikunci permanen -->
                            <div class="mb-3">
                                <label class="form-label fs-13 fw-semibold">Peran Sistem (RBAC)</label>
                                <div v-if="editForm.is_super_admin">
                                    <input
                                        type="text"
                                        class="form-control bg-light text-danger fw-semibold"
                                        value="Super Admin (Role Permanen - Tidak Dapat Diubah)"
                                        disabled
                                    />
                                    <div class="form-text fs-11 text-danger mt-1">
                                        <i class="ri-information-line me-1"></i>Role Super Admin berstatus mutlak dan tidak dapat diturunkan perannya.
                                    </div>
                                </div>
                                <select v-else v-model="editForm.role" class="form-select">
                                    <option value="member">Anggota Biasa (Member)</option>
                                    <option value="admin">Administrator</option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer border-top border-secondary-subtle">
                            <button type="button" class="btn btn-light rounded-pill px-3" @click="showEditModal = false">Batal</button>
                            <button type="submit" class="btn btn-primary rounded-pill px-4" :disabled="editForm.processing">
                                <span v-if="editForm.processing" class="spinner-border spinner-border-sm me-1"></span>
                                Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

