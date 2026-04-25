<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import AdminLayout from '../Layouts/AdminLayout.vue';

const page = usePage();
const user = page.props.auth?.user;
const permissions = page.props.auth?.permissions || [];
</script>

<template>
    <Head title="Dashboard" />

    <AdminLayout>
        <section class="mx-auto w-full max-w-7xl">
            <div class="app-surface rounded-lg border p-6">
                <p class="app-primary-text text-sm font-semibold uppercase tracking-[0.18em]">
                    Inicio
                </p>
                <h1 class="app-text mt-3 text-3xl font-semibold tracking-tight">
                    Dashboard administrativo
                </h1>
                <p class="app-muted mt-3 max-w-3xl text-base leading-7">
                    La sesion fue iniciada con token CAS. Desde aqui iremos migrando los modulos del dashboard viejo hacia flujos Inertia que consulten stj-api.
                </p>
            </div>

            <div class="mt-6 grid gap-4 sm:grid-cols-3">
                <div class="app-surface rounded-lg border p-5">
                    <p class="app-muted text-sm">Pais</p>
                    <p class="app-text mt-2 text-2xl font-semibold">{{ user?.pais || 'N/D' }}</p>
                </div>
                <div class="app-surface rounded-lg border p-5">
                    <p class="app-muted text-sm">Tiendas</p>
                    <p class="app-text mt-2 text-2xl font-semibold">{{ user?.storeLabel || user?.tiendas || '00000' }}</p>
                </div>
                <div class="app-surface rounded-lg border p-5">
                    <p class="app-muted text-sm">Permisos</p>
                    <p class="app-text mt-2 text-2xl font-semibold">{{ permissions.length }}</p>
                </div>
            </div>
        </section>
    </AdminLayout>
</template>
