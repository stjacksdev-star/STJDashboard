<script setup>
import { Head } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';

const loading = ref(false);
const exporting = ref(false);
const error = ref('');
const report = ref(emptyReport());
const filters = ref({ month: 0, country: 0 });
const months = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

async function fetchReport() {
    loading.value = true;
    error.value = '';
    try {
        const response = await window.axios.get('/dashboard-api/reports/management/monthly-sales', { params: filters.value });
        report.value = { ...emptyReport(), ...(response.data.data || {}) };
    } catch (exception) {
        error.value = exception.response?.data?.message || 'No fue posible cargar el reporte.';
        report.value = emptyReport();
    } finally {
        loading.value = false;
    }
}

function exportExcel() {
    exporting.value = true;
    const params = new URLSearchParams({ month: String(filters.value.month), country: String(filters.value.country) });
    window.location.href = `/dashboard-api/reports/management/monthly-sales/export?${params}`;
    window.setTimeout(() => { exporting.value = false; }, 1500);
}

function emptyReport() {
    return { years: [2023, 2024, 2025, 2026], countries: [], rows: [], totals: { years: {}, growth: 0 } };
}

function money(value) {
    return Number(value || 0).toLocaleString('en-US', { style: 'currency', currency: 'USD' });
}

onMounted(fetchReport);
</script>

<template>
    <Head title="Reportes / Gerencias / Venta x Mes" />
    <AdminLayout>
        <section class="mx-auto w-full max-w-7xl space-y-6">
            <div class="app-surface rounded-lg border p-6">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="app-primary-text text-sm font-semibold uppercase">Reportes / Gerencias</p>
                        <h1 class="app-text mt-3 text-3xl font-semibold">Venta x Mes</h1>
                        <p class="app-muted mt-2 text-sm">Comparativo mensual de venta eCommerce expresado en USD.</p>
                    </div>
                    <form class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" @submit.prevent="fetchReport">
                        <label class="text-sm app-muted">Mes
                            <select v-model.number="filters.month" class="app-input mt-1 w-full rounded-md border px-3 py-2">
                                <option :value="0">Todos</option>
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
                    <table class="w-full min-w-[760px] text-sm">
                        <thead class="app-surface-soft app-text">
                            <tr><th class="px-4 py-3 text-left">Mes</th><th v-for="year in report.years" :key="year" class="px-4 py-3 text-right">{{ year }}</th><th class="px-4 py-3 text-right">Crecimiento 2026 vs 2025</th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in report.rows" :key="row.month" class="border-t" style="border-color: var(--stj-border);">
                                <td class="app-text px-4 py-3 font-semibold">{{ row.monthName }}</td>
                                <td v-for="year in report.years" :key="year" class="app-text px-4 py-3 text-right">{{ money(row.years[year]) }}</td>
                                <td class="px-4 py-3 text-right" :class="row.growth < 0 ? 'font-semibold text-red-600' : 'app-text'">{{ money(row.growth) }}</td>
                            </tr>
                            <tr v-if="report.rows.length" class="app-surface-soft border-t font-bold" style="border-color: var(--stj-border);">
                                <td class="app-text px-4 py-3">Totales</td>
                                <td v-for="year in report.years" :key="year" class="app-text px-4 py-3 text-right">{{ money(report.totals.years[year]) }}</td>
                                <td class="app-text px-4 py-3 text-right">{{ money(report.totals.growth) }}</td>
                            </tr>
                            <tr v-if="!loading && !report.rows.length"><td colspan="6" class="app-muted px-4 py-10 text-center">No hay datos para los filtros seleccionados.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </AdminLayout>
</template>
