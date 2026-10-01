<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';

const loading = ref(false);
const exporting = ref(false);
const error = ref('');
const report = ref({ years: [2023, 2024, 2025, 2026], countries: [], rows: [], chart: { labels: [], series: [] } });
const filters = ref({ month: new Date().getMonth() + 1, country: 0 });
const months = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
const colors = { 2023: '#14b8a6', 2024: '#3b82f6', 2025: '#f43f5e', 2026: '#8b5cf6' };
const maxChartValue = computed(() => Math.max(1, ...report.value.chart.series.flatMap((series) => series.data.map(Number))));

async function fetchReport() {
    loading.value = true;
    error.value = '';
    try {
        const response = await window.axios.get('/dashboard-api/reports/management/daily-sales', { params: filters.value });
        report.value = response.data.data;
    } catch (exception) {
        error.value = exception.response?.data?.message || 'No fue posible cargar el reporte.';
    } finally {
        loading.value = false;
    }
}

function exportExcel() {
    exporting.value = true;
    const params = new URLSearchParams({ month: String(filters.value.month), country: String(filters.value.country) });
    window.location.href = `/dashboard-api/reports/management/daily-sales/export?${params}`;
    window.setTimeout(() => { exporting.value = false; }, 1500);
}

function money(value) {
    return Number(value || 0).toLocaleString('en-US', { style: 'currency', currency: 'USD' });
}

function differenceClass(value) {
    return Number(value) > 0 ? 'font-bold' : 'font-semibold text-red-600';
}

function barWidth(value) {
    return `${Math.max(1, (Number(value || 0) / maxChartValue.value) * 100)}%`;
}

onMounted(fetchReport);
</script>

<template>
    <Head title="Reportes / Gerencias / Venta x Día" />
    <AdminLayout>
        <section class="mx-auto w-full max-w-7xl space-y-6">
            <div class="app-surface rounded-lg border p-6">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="app-primary-text text-sm font-semibold uppercase">Reportes / Gerencias</p>
                        <h1 class="app-text mt-3 text-3xl font-semibold">Venta x Día</h1>
                        <p class="app-muted mt-2 text-sm">Comparativo diario de venta eCommerce expresado en USD.</p>
                    </div>
                    <form class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" @submit.prevent="fetchReport">
                        <label class="text-sm app-muted">Mes
                            <select v-model.number="filters.month" class="app-input mt-1 w-full rounded-md border px-3 py-2">
                                <option v-for="(month, index) in months" :key="month" :value="index + 1">{{ month }}</option>
                            </select>
                        </label>
                        <label class="text-sm app-muted">Pais
                            <select v-model.number="filters.country" class="app-input mt-1 w-full rounded-md border px-3 py-2">
                                <option :value="0">Todos</option>
                                <option v-for="country in report.countries" :key="country.id" :value="country.id">{{ country.name }}</option>
                            </select>
                        </label>
                        <button :disabled="loading" class="rounded-md bg-blue-600 px-5 py-2.5 font-semibold text-white disabled:opacity-50">{{ loading ? 'Consultando...' : 'Ver reporte' }}</button>
                        <button type="button" :disabled="exporting" class="rounded-md bg-emerald-600 px-5 py-2.5 font-semibold text-white disabled:opacity-50" @click="exportExcel">{{ exporting ? 'Exportando...' : 'Exportar Excel' }}</button>
                    </form>
                </div>
                <div v-if="error" class="mt-5 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">{{ error }}</div>
            </div>

            <div class="app-surface overflow-hidden rounded-lg border">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1000px] text-sm">
                        <thead class="app-surface-soft app-text">
                            <tr><th class="px-4 py-3 text-left">Dia</th><th v-for="year in report.years" :key="year" class="px-4 py-3 text-right">{{ year }}</th><th class="px-4 py-3 text-right">Grand Total</th><th class="px-4 py-3 text-right">2026 - 2025</th><th class="px-4 py-3 text-right">2026 - 2024</th><th class="px-4 py-3 text-right">2026 - 2023</th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in report.rows" :key="row.day" class="border-t" style="border-color: var(--stj-border);">
                                <td class="app-text px-4 py-3 font-semibold">{{ row.day }}</td>
                                <td v-for="year in report.years" :key="year" class="app-text px-4 py-3 text-right">{{ money(row.years[year]) }}</td>
                                <td class="app-text px-4 py-3 text-right font-semibold">{{ money(row.grandTotal) }}</td>
                                <td v-for="key in ['2026-2025', '2026-2024', '2026-2023']" :key="key" class="px-4 py-3 text-right" :class="differenceClass(row.differences[key])">{{ money(row.differences[key]) }}</td>
                            </tr>
                            <tr v-if="!loading && !report.rows.length"><td colspan="9" class="app-muted px-4 py-10 text-center">No hay ventas para los filtros seleccionados.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="report.rows.length" class="app-surface rounded-lg border p-6">
                <h2 class="app-text text-xl font-semibold">Grafica comparativa de ventas</h2>
                <div class="mt-5 flex flex-wrap gap-4 text-xs"><span v-for="series in report.chart.series" :key="series.year" class="app-muted flex items-center gap-2"><i class="h-3 w-3 rounded-sm" :style="{ background: colors[series.year] }" />{{ series.year }}</span></div>
                <div class="mt-5 space-y-4">
                    <div v-for="(day, dayIndex) in report.chart.labels" :key="day" class="grid grid-cols-[2rem_1fr] items-center gap-3">
                        <span class="app-muted text-right text-xs">{{ day }}</span>
                        <div class="space-y-1">
                            <div v-for="series in report.chart.series" :key="series.year" class="h-2 rounded-r" :style="{ width: barWidth(series.data[dayIndex]), background: colors[series.year] }" :title="`${series.year}: ${money(series.data[dayIndex])}`" />
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </AdminLayout>
</template>
