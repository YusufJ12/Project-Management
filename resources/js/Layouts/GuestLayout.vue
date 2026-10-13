<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="min-vh-100 d-flex flex-column justify-content-center align-items-center py-5 px-3 bg-light-subtle">
        <div class="mb-4 text-center">
            <Link href="/" class="d-inline-flex align-items-center gap-2 text-decoration-none">
                <img src="/admin-assets/images/ns.png" alt="NexSphere Logo" style="height: 44px; width: 44px; object-fit: contain;" />
                <div class="text-start">
                    <div class="fs-20 fw-bold text-primary lh-1">PROJECT<span class="text-body">SPHERE</span></div>
                    <div class="fs-10 text-muted fw-semibold ls-1">NEXSPHERE PM PLATFORM</div>
                </div>
            </Link>
        </div>

        <div class="card shadow-sm border border-secondary-subtle rounded-4 w-100 overflow-hidden" style="max-width: 440px;">
            <div class="card-body p-4 p-md-5">
                <slot />
            </div>
        </div>

        <div class="mt-4 text-center text-muted fs-12">
            2026 © <span class="fw-semibold text-primary">ProjectSphere</span>. Hak Cipta Dilindungi.
        </div>
    </div>
</template>

