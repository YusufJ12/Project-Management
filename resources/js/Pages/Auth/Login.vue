<script setup>
import { useForm, Link, Head } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';

const form = useForm({
    login: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Masuk ke Akun" />

        <div class="mb-4">
            <h4 class="fw-bold mb-1">Selamat Datang Kembali 👋</h4>
            <p class="text-muted fs-13 mb-0">Masuk untuk mengelola ruang kerja dan tugas tim Anda.</p>
        </div>

        <form @submit.prevent="submit">
            <div class="mb-3">
                <label for="login" class="form-label fs-13 fw-semibold">Email atau Username</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted">
                        <i class="ri-user-line"></i>
                    </span>
                    <input
                        id="login"
                        v-model="form.login"
                        type="text"
                        class="form-control border-start-0"
                        :class="{ 'is-invalid': form.errors.login }"
                        placeholder="nama@perusahaan.com atau username"
                        required
                        autofocus
                    />
                </div>
                <div v-if="form.errors.login" class="text-danger fs-12 mt-1">
                    {{ form.errors.login }}
                </div>
            </div>

            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label for="password" class="form-label fs-13 fw-semibold mb-0">Kata Sandi</label>
                </div>
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
                        placeholder="••••••••"
                        required
                    />
                </div>
                <div v-if="form.errors.password" class="text-danger fs-12 mt-1">
                    {{ form.errors.password }}
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-4 fs-13">
                <div class="form-check mb-0">
                    <input class="form-check-input" type="checkbox" id="remember" v-model="form.remember" />
                    <label class="form-check-label text-muted" for="remember">Ingat Saya</label>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold d-flex justify-content-center align-items-center gap-2" :disabled="form.processing">
                <span v-if="form.processing" class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                <span>Masuk Sekarang</span>
            </button>
        </form>
    </GuestLayout>
</template>

