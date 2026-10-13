<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    user: {
        type: Object,
        required: true,
    },
});

const form = useForm({
    name: props.user.name,
    email: props.user.email,
    username: props.user.username || '',
    current_password: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.put('/profile', {
        onSuccess: () => {
            form.reset('current_password', 'password', 'password_confirmation');
        },
    });
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Edit Profil Pengguna" />

        <!-- Header Title Card with Border -->
        <div class="card border border-secondary-subtle shadow-sm rounded-3 mb-4">
            <div class="card-body p-3 p-md-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <h4 class="fw-bold mb-1">Pengaturan Profil</h4>
                        <p class="text-muted fs-13 mb-0">Kelola informasi data akun pengguna, username login, dan keamanan kata sandi Anda.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8 col-xl-6">
                <div class="card border border-secondary-subtle shadow-sm rounded-3">
                    <div class="card-header bg-transparent border-bottom border-secondary-subtle py-3 px-4">
                        <h6 class="fw-bold mb-0">Informasi Pribadi & Akun</h6>
                    </div>
                    <div class="card-body p-4">
                        <form @submit.prevent="submit">
                            <div class="mb-3">
                                <label class="form-label fs-13 fw-semibold">Nama Lengkap</label>
                                <input
                                    type="text"
                                    v-model="form.name"
                                    class="form-control"
                                    :class="{ 'is-invalid': form.errors.name }"
                                    required
                                />
                                <div v-if="form.errors.name" class="invalid-feedback">
                                    {{ form.errors.name }}
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fs-13 fw-semibold">Username Login</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0 text-muted">@</span>
                                    <input
                                        type="text"
                                        v-model="form.username"
                                        class="form-control border-start-0"
                                        :class="{ 'is-invalid': form.errors.username }"
                                        placeholder="contoh: admin_jkt"
                                    />
                                </div>
                                <div v-if="form.errors.username" class="text-danger fs-12 mt-1">
                                    {{ form.errors.username }}
                                </div>
                                <div class="form-text fs-11 text-muted">
                                    Username unik dapat digunakan untuk login ke sistem tanpa mengetik email.
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fs-13 fw-semibold">Alamat Email</label>
                                <input
                                    type="email"
                                    v-model="form.email"
                                    class="form-control"
                                    :class="{ 'is-invalid': form.errors.email }"
                                    required
                                />
                                <div v-if="form.errors.email" class="invalid-feedback">
                                    {{ form.errors.email }}
                                </div>
                            </div>

                            <hr class="my-4 border-secondary-subtle" />

                            <h6 class="fw-bold mb-3 fs-14">Ubah Kata Sandi (Opsional)</h6>

                            <div class="mb-3">
                                <label class="form-label fs-13 fw-semibold">Kata Sandi Saat Ini</label>
                                <input
                                    type="password"
                                    v-model="form.current_password"
                                    class="form-control"
                                    :class="{ 'is-invalid': form.errors.current_password }"
                                    placeholder="Masukkan kata sandi saat ini"
                                />
                                <div v-if="form.errors.current_password" class="invalid-feedback">
                                    {{ form.errors.current_password }}
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fs-13 fw-semibold">Kata Sandi Baru</label>
                                <input
                                    type="password"
                                    v-model="form.password"
                                    class="form-control"
                                    :class="{ 'is-invalid': form.errors.password }"
                                    placeholder="Minimal 8 karakter"
                                />
                                <div v-if="form.errors.password" class="invalid-feedback">
                                    {{ form.errors.password }}
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fs-13 fw-semibold">Konfirmasi Kata Sandi Baru</label>
                                <input
                                    type="password"
                                    v-model="form.password_confirmation"
                                    class="form-control"
                                    placeholder="Ulangi kata sandi baru"
                                />
                            </div>

                            <div class="d-flex justify-content-end">
                                <button
                                    type="submit"
                                    class="btn btn-primary px-4 rounded-pill fw-semibold"
                                    :disabled="form.processing"
                                >
                                    <span v-if="form.processing" class="spinner-border spinner-border-sm me-1"></span>
                                    Simpan Perubahan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

