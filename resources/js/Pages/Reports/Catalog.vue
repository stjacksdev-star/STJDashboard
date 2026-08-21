<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const reports = [
    {
        title: 'Cupones usados',
        group: 'Cupones',
        description: 'Consulta los cupones consumidos, clientes, pedidos y descuentos aplicados.',
        href: '/cupones/reportes',
        color: 'blue',
        icon: 'receipt',
    },
    {
        title: 'Rendimiento de productos',
        group: 'Productos',
        description: 'Analiza ventas, vistas, favoritos, agregados al carrito y conversión por producto.',
        href: '/reportes/rendimiento-productos',
        color: 'emerald',
        icon: 'chart',
    },
    {
        title: 'Venta general 3',
        group: 'Contabilidad',
        description: 'Genera el reporte contable de venta general por país, tienda y rango de fechas.',
        href: '/reportes/contabilidad/venta-general-3',
        color: 'violet',
        icon: 'document',
    },
];

const colors = {
    blue: 'bg-blue-50 text-blue-600',
    emerald: 'bg-emerald-50 text-emerald-600',
    violet: 'bg-violet-50 text-violet-600',
};
</script>

<template>
    <Head title="Catálogo de reportes" />
    <AdminLayout>
        <div class="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6">
            <section class="app-surface rounded-lg border p-6 shadow-sm" style="border-color: var(--stj-border);">
                <p class="app-primary-text text-xs font-semibold uppercase tracking-[.2em]">Módulo</p>
                <h1 class="app-heading mt-2 text-2xl font-bold">Reportes / Catálogo</h1>
                <p class="app-muted mt-2 max-w-3xl text-sm leading-6">Selecciona el reporte que deseas consultar. Los filtros y opciones de descarga estarán disponibles dentro de cada módulo.</p>
            </section>

            <section>
                <div class="mb-4 flex items-end justify-between gap-4">
                    <div><h2 class="app-heading text-lg font-bold">Reportes disponibles</h2><p class="app-muted mt-1 text-sm">{{ reports.length }} reportes habilitados</p></div>
                </div>
                <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                    <Link v-for="report in reports" :key="report.href" :href="report.href" class="app-surface group flex min-h-[240px] flex-col rounded-xl border p-6 shadow-sm transition duration-200 hover:-translate-y-1 hover:border-blue-300 hover:shadow-lg" style="border-color: var(--stj-border);">
                        <div class="flex items-start justify-between gap-4">
                            <span class="flex h-12 w-12 items-center justify-center rounded-xl" :class="colors[report.color]">
                                <svg v-if="report.icon === 'receipt'" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3Z"/><path d="M9 8h6M9 12h6M9 16h3"/></svg>
                                <svg v-else-if="report.icon === 'chart'" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg>
                                <svg v-else class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 3h9l4 4v14H6V3Z"/><path d="M14 3v5h5M9 12h7M9 16h7"/></svg>
                            </span>
                            <span class="app-surface-soft app-muted rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide">{{ report.group }}</span>
                        </div>
                        <h3 class="app-heading mt-5 text-xl font-bold">{{ report.title }}</h3>
                        <p class="app-muted mt-2 flex-1 text-sm leading-6">{{ report.description }}</p>
                        <div class="mt-6 flex items-center justify-between border-t pt-4 text-sm font-semibold text-blue-600" style="border-color: var(--stj-border);"><span>Abrir reporte</span><span class="text-xl transition-transform group-hover:translate-x-1">→</span></div>
                    </Link>
                </div>
            </section>
        </div>
    </AdminLayout>
</template>
