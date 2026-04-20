<script setup>
import { Head } from '@inertiajs/vue3';
import AdminLayout from '../../Layouts/AdminLayout.vue';

defineProps({
    title: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <Head :title="title" />

    <AdminLayout>
        <section class="mx-auto w-full max-w-7xl">
            <div class="app-surface rounded-lg border p-6">
                <p class="app-primary-text text-sm font-semibold uppercase tracking-[0.18em]">
                    Modulo
                </p>
                <h1 class="app-text mt-3 text-3xl font-semibold tracking-tight">
                    {{ title }}
                </h1>
                <p class="app-muted mt-3 max-w-3xl text-base leading-7">
                    Esta seccion queda lista en el menu para migrar su flujo desde CodeIgniter hacia Inertia y stj-api.
                </p>
            </div>
        </section>
    </AdminLayout>
</template>
