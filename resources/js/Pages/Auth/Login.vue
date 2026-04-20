<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
    casLoginUrl: {
        type: String,
        required: true,
    },
    status: {
        type: Object,
        default: null,
    },
});
</script>

<template>
    <Head title="Login" />

    <main class="min-h-screen bg-slate-50 text-slate-900">
        <section class="grid min-h-screen lg:grid-cols-[440px_1fr]">
            <div class="flex flex-col justify-between bg-white px-8 py-10 shadow-xl shadow-blue-950/10 sm:px-12">
                <div>
                    <div class="inline-flex items-end gap-2">
                        <span class="text-4xl font-bold tracking-tight text-blue-600">st.jack's</span>
                        <span class="mb-1 text-[10px] font-semibold uppercase tracking-[0.28em] text-blue-400">
                            vive la ilusion
                        </span>
                    </div>

                    <div class="mt-20">
                        <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-500">
                            Dashboard
                        </p>
                        <h1 class="mt-3 text-4xl font-semibold tracking-tight text-slate-950">
                            Administracion e-commerce
                        </h1>
                        <p class="mt-5 max-w-sm text-base leading-7 text-slate-500">
                            Ingresa con tu cuenta corporativa de ST Jack's para continuar al panel administrativo.
                        </p>

                        <div
                            v-if="status"
                            :class="[
                                'mt-8 rounded-lg border px-4 py-3 text-sm',
                                status.type === 'danger'
                                    ? 'border-red-200 bg-red-50 text-red-900'
                                    : 'border-amber-200 bg-amber-50 text-amber-900',
                            ]"
                        >
                            <p class="font-semibold">{{ status.title }}</p>
                            <p class="mt-1">{{ status.message }}</p>
                        </div>

                        <a
                            :href="casLoginUrl"
                            class="mt-8 inline-flex h-12 w-full items-center justify-center rounded-md bg-blue-600 px-5 text-sm font-semibold text-white shadow-lg shadow-blue-600/20 transition hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-200"
                        >
                            Iniciar sesion
                        </a>

                        <p class="mt-5 text-sm leading-6 text-slate-500">
                            La sesion sera validada con CAS y el token quedara protegido en la sesion del dashboard.
                        </p>
                    </div>
                </div>

                <p class="mt-12 text-xs text-slate-400">
                    STJ Dashboard usa stj-api para consultar los datos administrativos.
                </p>
            </div>

            <div class="relative hidden overflow-hidden bg-blue-600 lg:block">
                <div class="absolute inset-0 bg-[linear-gradient(135deg,rgba(37,99,235,0.96),rgba(59,130,246,0.84)),radial-gradient(circle_at_20%_20%,rgba(255,255,255,0.26),transparent_30%),radial-gradient(circle_at_80%_70%,rgba(255,255,255,0.18),transparent_28%)]"></div>
                <div class="relative flex h-full items-center px-16">
                    <div class="max-w-2xl text-white">
                        <p class="text-sm font-semibold uppercase tracking-[0.24em] text-blue-100">
                            Nuevo panel
                        </p>
                        <h2 class="mt-4 text-5xl font-semibold leading-tight tracking-tight">
                            Flujo moderno, misma autenticacion por token.
                        </h2>
                        <p class="mt-6 max-w-xl text-lg leading-8 text-blue-50">
                            El dashboard conserva el acceso con CAS, pero se prepara para operar sin MySQL local y consumir la informacion desde stj-api.
                        </p>
                        <div class="mt-10 grid max-w-xl grid-cols-3 gap-3">
                            <div class="rounded-lg bg-white/14 p-4 ring-1 ring-white/20">
                                <p class="text-xs uppercase tracking-[0.18em] text-blue-100">Auth</p>
                                <p class="mt-2 text-lg font-semibold">CAS</p>
                            </div>
                            <div class="rounded-lg bg-white/14 p-4 ring-1 ring-white/20">
                                <p class="text-xs uppercase tracking-[0.18em] text-blue-100">UI</p>
                                <p class="mt-2 text-lg font-semibold">Inertia</p>
                            </div>
                            <div class="rounded-lg bg-white/14 p-4 ring-1 ring-white/20">
                                <p class="text-xs uppercase tracking-[0.18em] text-blue-100">Datos</p>
                                <p class="mt-2 text-lg font-semibold">API</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>
</template>
