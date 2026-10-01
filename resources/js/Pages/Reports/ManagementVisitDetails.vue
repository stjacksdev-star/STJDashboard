<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';

const date = (value) => value.toLocaleDateString('en-CA');
const now = new Date();
const start = new Date(now);
start.setDate(start.getDate() - 7);
const filters = ref({ startDate: date(start), endDate: date(now), country: 'GENERAL', platform: 'TODAS' });
const loading = ref(false);
const error = ref('');
const report = ref({ countries: [], platforms: [], summary: { visits: 0 }, rows: [], chart: { categories: [], series: [] } });
const colors = { WEB: '#2563eb', 'APP-IOS': '#db2777', 'APP-ANDROID': '#16a34a' };
const labels = { WEB: 'Web', 'APP-IOS': 'App iOS', 'APP-ANDROID': 'App Android' };
const width = 1000;
const height = 330;
const padding = { top: 20, right: 25, bottom: 45, left: 60 };
const plotWidth = width - padding.left - padding.right;
const plotHeight = height - padding.top - padding.bottom;
const maximum = computed(() => Math.max(1, ...report.value.chart.series.flatMap((series) => series.data.map(Number))));
const ticks = computed(() => Array.from({ length: 5 }, (_, index) => Math.round(maximum.value * index / 4)));
const platformTotals = computed(() => report.value.chart.series.map((series) => ({ ...series, total: series.data.reduce((sum, value) => sum + Number(value || 0), 0) })));

function x(index) {
    const count = Math.max(report.value.chart.categories.length - 1, 1);
    return padding.left + (index / count) * plotWidth;
}

function y(value) {
    return padding.top + plotHeight - (Number(value || 0) / maximum.value) * plotHeight;
}

function line(series) {
    return series.data.map((value, index) => `${index ? 'L' : 'M'} ${x(index)} ${y(value)}`).join(' ');
}

async function fetchReport() {
    loading.value = true;
    error.value = '';
    try {
        const response = await window.axios.get('/dashboard-api/reports/management/visit-details', { params: filters.value });
        report.value = response.data.data;
    } catch (exception) {
        error.value = exception.response?.data?.message || 'No fue posible cargar el reporte.';
    } finally {
        loading.value = false;
    }
}

onMounted(fetchReport);
</script>

<template>
    <Head title="Reportes / Gerencias / Visitas detalles" />
    <AdminLayout>
        <section class="mx-auto w-full max-w-7xl space-y-6">
            <div class="app-surface rounded-lg border p-6">
                <div class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
                    <div>
                        <p class="app-primary-text text-sm font-semibold uppercase">Reportes / Gerencias</p>
                        <h1 class="app-text mt-3 text-3xl font-semibold">Visitas detalles</h1>
                        <p class="app-muted mt-2 text-sm">Visitas registradas, desglosadas por fecha, país y plataforma.</p>
                    </div>
                    <form class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5" @submit.prevent="fetchReport">
                        <label class="app-muted text-sm">Inicio<input v-model="filters.startDate" type="date" class="app-input mt-1 w-full rounded-md border px-3 py-2"></label>
                        <label class="app-muted text-sm">Fin<input v-model="filters.endDate" type="date" class="app-input mt-1 w-full rounded-md border px-3 py-2"></label>
                        <label class="app-muted text-sm">País<select v-model="filters.country" class="app-input mt-1 w-full rounded-md border px-3 py-2"><option value="GENERAL">General</option><option v-for="country in report.countries" :key="country.code" :value="country.code">{{ country.code }} - {{ country.name }}</option></select></label>
                        <label class="app-muted text-sm">Plataforma<select v-model="filters.platform" class="app-input mt-1 w-full rounded-md border px-3 py-2"><option value="TODAS">Todas</option><option v-for="platform in report.platforms" :key="platform" :value="platform">{{ labels[platform] }}</option></select></label>
                        <button :disabled="loading" class="rounded-md bg-blue-600 px-5 py-2.5 font-semibold text-white disabled:opacity-50">{{ loading ? 'Generando...' : 'Generar' }}</button>
                    </form>
                </div>
                <div v-if="error" class="mt-5 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">{{ error }}</div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <article class="app-surface rounded-lg border p-5"><p class="app-muted text-xs font-semibold uppercase">Total visitas</p><p class="app-text mt-2 text-3xl font-semibold">{{ report.summary.visits }}</p></article>
                <article v-for="series in platformTotals" :key="series.key" class="app-surface rounded-lg border p-5"><div class="flex items-center gap-2"><span class="h-3 w-3 rounded-full" :style="{ background: colors[series.key] }"></span><p class="app-muted text-xs font-semibold uppercase">{{ labels[series.key] }}</p></div><p class="app-text mt-2 text-3xl font-semibold">{{ series.total }}</p></article>
            </div>

            <div class="app-surface rounded-lg border p-5">
                <h2 class="app-text text-xl font-semibold">Visitas por fecha y plataforma</h2>
                <div class="mt-4 overflow-x-auto">
                    <svg :viewBox="`0 0 ${width} ${height}`" class="min-w-[760px]" role="img" aria-label="Gráfico de visitas por plataforma">
                        <g v-for="tick in ticks" :key="tick"><line :x1="padding.left" :x2="width - padding.right" :y1="y(tick)" :y2="y(tick)" stroke="currentColor" class="app-muted opacity-20"/><text :x="padding.left - 10" :y="y(tick) + 4" text-anchor="end" class="app-muted fill-current text-xs">{{ tick }}</text></g>
                        <g v-for="(day, index) in report.chart.categories" :key="day"><text :x="x(index)" :y="height - 14" text-anchor="middle" class="app-muted fill-current text-xs">{{ day.slice(5) }}</text></g>
                        <g v-for="series in report.chart.series" :key="series.key"><path v-if="series.data.length" :d="line(series)" fill="none" :stroke="colors[series.key]" stroke-width="3"/><circle v-for="(value, index) in series.data" :key="index" :cx="x(index)" :cy="y(value)" r="3" :fill="colors[series.key]"><title>{{ report.chart.categories[index] }} · {{ labels[series.key] }}: {{ value }}</title></circle></g>
                    </svg>
                </div>
                <div class="mt-2 flex flex-wrap gap-4"><span v-for="series in report.chart.series" :key="series.key" class="app-muted flex items-center gap-2 text-sm"><i class="h-3 w-3 rounded-full" :style="{ background: colors[series.key] }"></i>{{ labels[series.key] }}</span></div>
            </div>

            <div class="app-surface overflow-hidden rounded-lg border">
                <div class="border-b px-5 py-4" style="border-color: var(--stj-border);"><h2 class="app-text text-xl font-semibold">Detalle</h2><p class="app-muted mt-1 text-sm">Cada fila agrupa las visitas de una fecha, país y plataforma.</p></div>
                <div class="overflow-x-auto"><table class="w-full min-w-[700px] text-sm"><thead class="app-surface-soft app-text"><tr><th class="px-5 py-3 text-left">Fecha</th><th class="px-5 py-3 text-right">Total visitas</th><th class="px-5 py-3 text-left">País</th><th class="px-5 py-3 text-left">Plataforma</th></tr></thead><tbody><tr v-for="row in report.rows" :key="`${row.date}-${row.countryCode}-${row.platform}`" class="border-t" style="border-color: var(--stj-border);"><td class="app-text px-5 py-3">{{ row.date }}</td><td class="app-text px-5 py-3 text-right font-semibold">{{ row.visits }}</td><td class="app-text px-5 py-3">{{ row.country }}</td><td class="px-5 py-3"><span class="inline-flex items-center gap-2"><i class="h-2.5 w-2.5 rounded-full" :style="{ background: colors[row.platform] }"></i>{{ labels[row.platform] }}</span></td></tr><tr v-if="loading"><td colspan="4" class="app-muted px-5 py-10 text-center">Cargando visitas...</td></tr><tr v-else-if="!report.rows.length"><td colspan="4" class="app-muted px-5 py-10 text-center">No hay visitas para los filtros seleccionados.</td></tr></tbody></table></div>
            </div>
        </section>
    </AdminLayout>
</template>
