<script setup>
import { Head } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';

const loading = ref(false);
const exporting = ref(false);
const error = ref('');
const filters = ref({ country: 1 });
const report = ref({ countries: [], previousYear: 2025, currentYear: 2026, periods: {} });

async function fetchReport() {
    loading.value = true;
    error.value = '';
    try {
        const response = await window.axios.get('/dashboard-api/reports/management/cyber-monday', { params: filters.value });
        report.value = response.data.data;
    } catch (exception) {
        error.value = exception.response?.data?.message || 'No fue posible cargar el reporte.';
    } finally {
        loading.value = false;
    }
}

function exportExcel() {
    exporting.value = true;
    window.location.href = `/dashboard-api/reports/management/cyber-monday/export?country=${filters.value.country}`;
    window.setTimeout(() => { exporting.value = false; }, 1500);
}

function money(value) {
    return Number(value || 0).toLocaleString('en-US', { style: 'currency', currency: 'USD' });
}

function signedClass(value) {
    return Number(value) >= 0 ? 'font-semibold text-emerald-600' : 'font-semibold text-red-600';
}

onMounted(fetchReport);
</script>

<template>
    <Head title="Reportes / Gerencias / Cyber Monday" />
    <AdminLayout>
        <section class="mx-auto w-full max-w-7xl space-y-6">
            <div class="app-surface rounded-lg border p-6">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="app-primary-text text-sm font-semibold uppercase">Reportes / Gerencias</p>
                        <h1 class="app-text mt-3 text-3xl font-semibold">Cyber Monday</h1>
                        <p class="app-muted mt-2 text-sm">Comparativo por hora de pedidos y montos: 2025 vs 2026.</p>
                    </div>
                    <form class="flex flex-wrap items-end gap-3" @submit.prevent="fetchReport">
                        <label class="text-sm app-muted">Pais
                            <select v-model.number="filters.country" class="app-input mt-1 min-w-52 rounded-md border px-3 py-2">
                                <option v-for="country in report.countries" :key="country.id" :value="country.id">{{ country.name }}</option>
                                <template v-if="!report.countries.length"><option :value="1">El Salvador</option><option :value="2">Guatemala</option><option :value="3">Costa Rica</option></template>
                            </select>
                        </label>
                        <button :disabled="loading" class="rounded-md bg-blue-600 px-5 py-2.5 font-semibold text-white disabled:opacity-50">{{ loading ? 'Consultando...' : 'Filtrar' }}</button>
                        <button type="button" :disabled="exporting" class="rounded-md bg-emerald-600 px-5 py-2.5 font-semibold text-white disabled:opacity-50" @click="exportExcel">{{ exporting ? 'Exportando...' : 'Exportar Excel' }}</button>
                    </form>
                </div>
                <div v-if="error" class="mt-5 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">{{ error }}</div>
            </div>

            <div v-for="period in report.periods" :key="period.label" class="app-surface overflow-hidden rounded-lg border">
                <div class="border-b px-5 py-4" style="border-color: var(--stj-border);">
                    <h2 class="app-text text-xl font-semibold">{{ period.label }} — 2025 vs 2026</h2>
                    <p class="app-muted mt-1 text-sm">{{ period.previousDate }} comparado con {{ period.currentDate }}</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1100px] text-sm">
                        <thead class="app-surface-soft app-text"><tr><th class="px-3 py-3 text-left">Hora</th><th class="px-3 py-3 text-right">2025 Pedidos</th><th class="px-3 py-3 text-right">2025 Monto</th><th class="px-3 py-3 text-right">2026 Pedidos</th><th class="px-3 py-3 text-right">2026 Monto</th><th class="px-3 py-3 text-right">Diferencia Pedidos</th><th class="px-3 py-3 text-right">Diferencia Monto</th><th class="px-3 py-3 text-right">% Pedidos</th><th class="px-3 py-3 text-right">% Monto</th></tr></thead>
                        <tbody>
                            <tr v-for="row in period.rows" :key="row.hour" class="border-t" style="border-color: var(--stj-border);"><td class="app-text px-3 py-2">{{ row.hour }}</td><td class="app-text px-3 py-2 text-right">{{ row.previousOrders }}</td><td class="app-text px-3 py-2 text-right">{{ money(row.previousAmount) }}</td><td class="app-text px-3 py-2 text-right">{{ row.currentOrders }}</td><td class="app-text px-3 py-2 text-right">{{ money(row.currentAmount) }}</td><td class="px-3 py-2 text-right" :class="signedClass(row.ordersDifference)">{{ row.ordersDifference }}</td><td class="px-3 py-2 text-right" :class="signedClass(row.amountDifference)">{{ money(row.amountDifference) }}</td><td class="app-text px-3 py-2 text-right">{{ Number(row.ordersPercentage).toFixed(2) }}%</td><td class="app-text px-3 py-2 text-right">{{ Number(row.amountPercentage).toFixed(2) }}%</td></tr>
                            <tr class="app-surface-soft border-t font-bold" style="border-color: var(--stj-border);"><td class="app-text px-3 py-3">Total</td><td class="app-text px-3 py-3 text-right">{{ period.totals.previousOrders }}</td><td class="app-text px-3 py-3 text-right">{{ money(period.totals.previousAmount) }}</td><td class="app-text px-3 py-3 text-right">{{ period.totals.currentOrders }}</td><td class="app-text px-3 py-3 text-right">{{ money(period.totals.currentAmount) }}</td><td class="px-3 py-3 text-right" :class="signedClass(period.totals.ordersDifference)">{{ period.totals.ordersDifference }}</td><td class="px-3 py-3 text-right" :class="signedClass(period.totals.amountDifference)">{{ money(period.totals.amountDifference) }}</td><td class="app-text px-3 py-3 text-right">{{ Number(period.totals.ordersPercentage).toFixed(2) }}%</td><td class="app-text px-3 py-3 text-right">{{ Number(period.totals.amountPercentage).toFixed(2) }}%</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </AdminLayout>
</template>
