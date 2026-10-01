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
const exporting = ref(false);
const error = ref('');
const chartSvg = ref(null);
const exportMenuOpen = ref(false);
const tooltip = ref(null);
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
const tooltipWidth = 220;
const tooltipHeight = computed(() => 42 + (tooltip.value?.rows.length || 0) * 24);
const tooltipX = computed(() => tooltip.value ? Math.min(width - padding.right - tooltipWidth, Math.max(padding.left, tooltip.value.x - tooltipWidth / 2)) : 0);
const tooltipY = computed(() => tooltip.value ? Math.max(5, tooltip.value.y - tooltipHeight.value - 12) : 0);

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

function showTooltip(index, value) {
    tooltip.value = {
        x: x(index), y: y(value), date: report.value.chart.categories[index],
        rows: report.value.chart.series.map((series) => ({ key: series.key, label: labels[series.key], value: Number(series.data[index] || 0) })),
    };
}

function downloadBlob(blob, filename) {
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    link.click();
    URL.revokeObjectURL(url);
}

function filename(extension) {
    return `visitas-detalles-${filters.value.startDate}-${filters.value.endDate}.${extension}`;
}

function svgMarkup() {
    if (!chartSvg.value) return '';
    const clone = chartSvg.value.cloneNode(true);
    const style = document.createElementNS('http://www.w3.org/2000/svg', 'style');
    const rootStyles = getComputedStyle(document.documentElement);
    const surface = rootStyles.getPropertyValue('--stj-surface').trim() || '#ffffff';
    const muted = rootStyles.getPropertyValue('--stj-muted').trim() || '#64748b';
    style.textContent = `svg { background: ${surface}; } .fill-current { fill: currentColor; } .app-muted { color: ${muted}; }`;
    clone.insertBefore(style, clone.firstChild);
    clone.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
    clone.setAttribute('width', width);
    clone.setAttribute('height', height);
    clone.querySelectorAll('[data-tooltip]').forEach((node) => node.remove());
    return new XMLSerializer().serializeToString(clone);
}

function exportSvg() {
    const markup = svgMarkup();
    if (markup) downloadBlob(new Blob([markup], { type: 'image/svg+xml;charset=utf-8' }), filename('svg'));
    exportMenuOpen.value = false;
}

function exportPng() {
    const markup = svgMarkup();
    if (!markup) return;
    const image = new Image();
    const url = URL.createObjectURL(new Blob([markup], { type: 'image/svg+xml;charset=utf-8' }));
    image.onload = () => {
        const canvas = document.createElement('canvas');
        canvas.width = width * 2;
        canvas.height = height * 2;
        const context = canvas.getContext('2d');
        context.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--stj-surface').trim() || '#ffffff';
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.drawImage(image, 0, 0, canvas.width, canvas.height);
        URL.revokeObjectURL(url);
        canvas.toBlob((blob) => { if (blob) downloadBlob(blob, filename('png')); }, 'image/png');
    };
    image.src = url;
    exportMenuOpen.value = false;
}

function exportCsv() {
    const cell = (value) => `"${String(value ?? '').replaceAll('"', '""')}"`;
    const header = ['Fecha', ...report.value.chart.series.map((series) => labels[series.key])];
    const rows = report.value.chart.categories.map((day, index) => [day, ...report.value.chart.series.map((series) => Number(series.data[index] || 0))]);
    const csv = [header, ...rows].map((row) => row.map(cell).join(',')).join('\n');
    downloadBlob(new Blob([`\uFEFF${csv}`], { type: 'text/csv;charset=utf-8' }), filename('csv'));
    exportMenuOpen.value = false;
}

function exportExcel() {
    exporting.value = true;
    const params = new URLSearchParams(filters.value);
    window.location.href = `/dashboard-api/reports/management/visit-details/export?${params}`;
    window.setTimeout(() => { exporting.value = false; }, 1500);
}

async function fetchReport() {
    loading.value = true;
    error.value = '';
    try {
        const response = await window.axios.get('/dashboard-api/reports/management/visit-details', { params: filters.value });
        report.value = response.data.data;
        tooltip.value = null;
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
                <div class="flex items-start justify-between gap-4">
                    <h2 class="app-text text-xl font-semibold">Visitas por fecha y plataforma</h2>
                    <div class="relative">
                        <button type="button" class="app-input rounded-md border px-3 py-2 text-xl leading-none" aria-label="Descargar gráfico" @click="exportMenuOpen = !exportMenuOpen">☰</button>
                        <div v-if="exportMenuOpen" class="app-surface absolute right-0 z-20 mt-2 w-44 overflow-hidden rounded-md border shadow-xl">
                            <button type="button" class="app-text block w-full px-4 py-2 text-left text-sm hover:bg-slate-100" @click="exportSvg">Descargar SVG</button>
                            <button type="button" class="app-text block w-full px-4 py-2 text-left text-sm hover:bg-slate-100" @click="exportPng">Descargar PNG</button>
                            <button type="button" class="app-text block w-full px-4 py-2 text-left text-sm hover:bg-slate-100" @click="exportCsv">Descargar CSV</button>
                        </div>
                    </div>
                </div>
                <div class="mt-4 overflow-x-auto">
                    <svg ref="chartSvg" :viewBox="`0 0 ${width} ${height}`" class="min-w-[760px]" role="img" aria-label="Gráfico de visitas por plataforma" @mouseleave="tooltip = null">
                        <g v-for="tick in ticks" :key="tick"><line :x1="padding.left" :x2="width - padding.right" :y1="y(tick)" :y2="y(tick)" stroke="currentColor" class="app-muted opacity-20"/><text :x="padding.left - 10" :y="y(tick) + 4" text-anchor="end" class="app-muted fill-current text-xs">{{ tick }}</text></g>
                        <g v-for="(day, index) in report.chart.categories" :key="day"><text :x="x(index)" :y="height - 14" text-anchor="middle" class="app-muted fill-current text-xs">{{ day.slice(5) }}</text></g>
                        <g v-for="series in report.chart.series" :key="series.key"><path v-if="series.data.length" :d="line(series)" fill="none" :stroke="colors[series.key]" stroke-width="3"/><circle v-for="(value, index) in series.data" :key="index" :cx="x(index)" :cy="y(value)" :r="tooltip?.date === report.chart.categories[index] ? 5 : 3" :fill="colors[series.key]" class="cursor-pointer" @mouseenter="showTooltip(index, value)" @focus="showTooltip(index, value)"/></g>
                        <g v-if="tooltip" data-tooltip class="pointer-events-none">
                            <line :x1="tooltip.x" :x2="tooltip.x" :y1="padding.top" :y2="padding.top + plotHeight" stroke="#94a3b8" stroke-dasharray="4 4"/>
                            <rect :x="tooltipX" :y="tooltipY" :width="tooltipWidth" :height="tooltipHeight" rx="8" fill="white" stroke="#cbd5e1"/>
                            <text :x="tooltipX + 14" :y="tooltipY + 24" fill="#0f172a" font-size="14" font-weight="700">{{ tooltip.date }}</text>
                            <g v-for="(row, index) in tooltip.rows" :key="row.key"><circle :cx="tooltipX + 17" :cy="tooltipY + 49 + index * 24" r="5" :fill="colors[row.key]"/><text :x="tooltipX + 30" :y="tooltipY + 54 + index * 24" fill="#334155" font-size="13">{{ row.label }}: {{ row.value }}</text></g>
                        </g>
                    </svg>
                </div>
                <div class="mt-2 flex flex-wrap gap-4"><span v-for="series in report.chart.series" :key="series.key" class="app-muted flex items-center gap-2 text-sm"><i class="h-3 w-3 rounded-full" :style="{ background: colors[series.key] }"></i>{{ labels[series.key] }}</span></div>
            </div>

            <div class="app-surface overflow-hidden rounded-lg border">
                <div class="flex items-center justify-between gap-4 border-b px-5 py-4" style="border-color: var(--stj-border);"><div><h2 class="app-text text-xl font-semibold">Detalle</h2><p class="app-muted mt-1 text-sm">Cada fila agrupa las visitas de una fecha, país y plataforma.</p></div><button type="button" :disabled="exporting || loading" class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" @click="exportExcel">{{ exporting ? 'Exportando...' : 'Descargar Excel' }}</button></div>
                <div class="overflow-x-auto"><table class="w-full min-w-[700px] text-sm"><thead class="app-surface-soft app-text"><tr><th class="px-5 py-3 text-left">Fecha</th><th class="px-5 py-3 text-right">Total visitas</th><th class="px-5 py-3 text-left">País</th><th class="px-5 py-3 text-left">Plataforma</th></tr></thead><tbody><tr v-for="row in report.rows" :key="`${row.date}-${row.countryCode}-${row.platform}`" class="border-t" style="border-color: var(--stj-border);"><td class="app-text px-5 py-3">{{ row.date }}</td><td class="app-text px-5 py-3 text-right font-semibold">{{ row.visits }}</td><td class="app-text px-5 py-3">{{ row.country }}</td><td class="px-5 py-3"><span class="inline-flex items-center gap-2"><i class="h-2.5 w-2.5 rounded-full" :style="{ background: colors[row.platform] }"></i>{{ labels[row.platform] }}</span></td></tr><tr v-if="loading"><td colspan="4" class="app-muted px-5 py-10 text-center">Cargando visitas...</td></tr><tr v-else-if="!report.rows.length"><td colspan="4" class="app-muted px-5 py-10 text-center">No hay visitas para los filtros seleccionados.</td></tr></tbody></table></div>
            </div>
        </section>
    </AdminLayout>
</template>
