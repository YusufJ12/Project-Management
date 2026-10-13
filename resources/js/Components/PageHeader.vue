<script setup>
defineProps({
    title: {
        type: String,
        default: '',
    },
    subtitle: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <div class="card border border-secondary-subtle shadow-sm rounded-3 mb-4">
        <div class="card-body p-3 p-md-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <slot name="pre-title" />
                        <h4 v-if="title" class="fw-bold mb-0">{{ title }}</h4>
                        <slot name="title" />
                        <slot name="badge" />
                    </div>
                    <p v-if="subtitle" class="text-muted fs-13 mb-0">{{ subtitle }}</p>
                    <slot name="subtitle" />
                </div>
                <div v-if="$slots.actions" class="d-flex flex-wrap align-items-center gap-2">
                    <slot name="actions" />
                </div>
            </div>
            <slot />
        </div>
    </div>
</template>

