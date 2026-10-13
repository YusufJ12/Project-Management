<script setup>
import { useForm, Link, Head } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post('/register', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Pendaftaran Akun Baru" />

        <div class="mb-4">
            <h4 class="fw-bold mb-1">Daftar Akun Baru 🚀</h4>
            <p class="text-muted fs-13 mb-0">Mulai kolaborasi proyek modern bersama tim Anda.</p>
        </div>

        <form @submit.prevent="submit">
            <div class="mb-3">
                <label for="name" class="form-label fs-13 fw-semibold">Nama Lengkap</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted">
                        <i class="ri-user-line"></i>
                    </span>
                    <input
                        id="name"
                        v-model="form.name"
                        type="text"
                        class="form-control border-start-0"
                        :class="{ 'is-invalid': form.errors.name }"
                        placeholder="Contoh: Yusuf Maulana"
                        required
                        autofocus
                    />
                </div>
                <div v-if="form.errors.name" class="text-danger fs-12 mt-1">
                    {{ form.errors.name }}
                </div>
            </div>

            <div class="mb-3">
                <label for="email" class="form-label fs-13 fw-semibold">Alamat Email</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted">
                        <i class="ri-mail-line"></i>
                    </span>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        class="form-control border-start-0"
                        :class="{ 'is-invalid': form.errors.email }"
                        placeholder="nama@perusahaan.com"
                        required
                    />
                </div>
                <div v-if="form.errors.email" class="text-danger fs-12 mt-1">
                    {{ form.errors.email }}
                </div>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label fs-13 fw-semibold">Kata Sandi</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted">
                        <i class="ri-lock-2-line"></i>
                    </span>
                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        class="form-control border-start-0"
                        :class="{ 'is-invalid': form.errors.password }"
                        placeholder="Minimal 8 karakter"
                        required
                    />
                </div>
                <div v-if="form.errors.password" class="text-danger fs-12 mt-1">
                    {{ form.errors.password }}
                </div>
            </div>

            <div class="mb-4">
                <label for="password_confirmation" class="form-label fs-13 fw-semibold">Konfirmasi Kata Sandi</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted">
                        <i class="ri-shield-check-line"></i>
                    </span>
                    <input
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        type="password"
                        class="form-control border-start-0"
                        placeholder="Ulangi kata sandi"
                        required
                    />
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold d-flex justify-content-center align-items-center gap-2" :disabled="form.processing">
                <span v-if="form.processing" class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                <span>Daftar Sekarang</span>
            </button>
        </form>

        <div class="mt-4 pt-2 border-top text-center fs-13 text-muted">
            Sudah memiliki akun?
            <Link href="/login" class="text-primary fw-semibold text-decoration-none">Masuk ke Akun</Link>
        </div>
    </GuestLayout>
</template>

