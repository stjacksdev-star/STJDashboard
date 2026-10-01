<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';

const page = usePage();
const today = new Date().toLocaleDateString('en-CA');
const loading = ref(false);
const ordersLoading = ref(false);
const error = ref('');
const report = ref({ countries: [], indicators: [], winner: null, rows: [], totals: { orders: 0, amount: 0 } });
const defaultCountry = page.props.auth?.countries?.default?.id
    || page.props.auth?.countries?.default?.countryId
    || page.props.auth?.user?.idPais
    || '1';
const filters = ref({ country: String(defaultCountry), startDate: today, endDate: today });
const detail = ref(null);
const platformOrder = ['WEB', 'APP-IOS', 'APP-ANDROID', 'APP-SIN-PLATAFORMA'];
const platformLabels = {
    WEB: 'Web',
    'APP-IOS': 'App iOS',
    'APP-ANDROID': 'App Android',
    'APP-SIN-PLATAFORMA': 'App sin plataforma',
};

const indicators = computed(() => platformOrder.map((platform) => {
    const indicator = report.value.indicators.find((item) => item.platform === platform);
    return indicator || { platform, orders: 0, amount: 0 };
}));

async function fetchReport() {
    loading.value = true;
    error.value = '';
    closeDetail();
    try {
        const response = await window.axios.get('/dashboard-api/reports/management/platform-sales', { params: filters.value });
        report.value = response.data.data;
    } catch (exception) {
        error.value = exception.response?.data?.message || 'No fue posible cargar el reporte.';
    } finally {
        loading.value = false;
    }
}

async function openDetail(row) {
    detail.value = { platform: row.platform, type: row.type, summary: { orders: 0, amount: 0 }, orders: [] };
    ordersLoading.value = true;
    error.value = '';
    try {
        const response = await window.axios.get('/dashboard-api/reports/management/platform-sales/orders', {
            params: { ...filters.value, platform: row.platform, type: row.type },
        });
        detail.value = response.data.data;
    } catch (exception) {
        error.value = exception.response?.data?.message || 'No fue posible cargar los pedidos.';
        detail.value = null;
    } finally {
        ordersLoading.value = false;
    }
}

function closeDetail() {
    detail.value = null;
    ordersLoading.value = false;
}

function money(value) {
    return Number(value || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function dateTime(value) {
    if (!value) return '';
    return new Intl.DateTimeFormat('es-SV', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value.replace(' ', 'T')));
}

function orderHref(order) {
    const country = report.value.filters?.country?.id || filters.value.country;
    return `/pedidos/consulta?country=${encodeURIComponent(country)}&id=${encodeURIComponent(order.reference)}`;
}

onMounted(fetchReport);
</script>

<template>
    <Head title="Reportes / Gerencias / Venta x Plataforma" />
    <AdminLayout>
        <section class="mx-auto w-full max-w-7xl space-y-6">
            <div class="app-surface rounded-lg border p-6">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="app-primary-text text-sm font-semibold uppercase">Reportes / Gerencias</p>
                        <h1 class="app-text mt-3 text-3xl font-semibold">Venta x Plataforma</h1>
                        <p class="app-muted mt-2 text-sm">Pagos aprobados agrupados por Web, iOS y Android, sin excluir pedidos por su estado.</p>
                    </div>
                    <form class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" @submit.prevent="fetchReport">
                        <label class="text-sm app-muted">País
                            <select v-model="filters.country" class="app-input mt-1 w-full rounded-md border px-3 py-2">
                                <option v-for="country in report.countries" :key="country.id" :value="String(country.id)">{{ country.code }} - {{ country.name }}</option>
                            </select>
                        </label>
                        <label class="text-sm app-muted">Inicio
                            <input v-model="filters.startDate" type="date" class="app-input mt-1 w-full rounded-md border px-3 py-2">
                        </label>
                        <label class="text-sm app-muted">Fin
                            <input v-model="filters.endDate" type="date" class="app-input mt-1 w-full rounded-md border px-3 py-2">
                        </label>
                        <button :disabled="loading" class="rounded-md bg-blue-600 px-5 py-2.5 font-semibold text-white disabled:opacity-50">{{ loading ? 'Consultando...' : 'Buscar' }}</button>
                    </form>
                </div>
                <div v-if="error" class="mt-5 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">{{ error }}</div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article v-for="indicator in indicators" :key="indicator.platform" class="app-surface rounded-lg border p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="app-muted text-xs font-semibold uppercase">{{ platformLabels[indicator.platform] }}</p>
                            <p class="app-text mt-2 text-2xl font-semibold">{{ money(indicator.amount) }}</p>
                            <p class="app-muted mt-1 text-sm">{{ indicator.orders }} pedidos</p>
                        </div>
                        <span v-if="report.winner?.platform === indicator.platform" class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800">Ganadora</span>
                    </div>
                </article>
            </div>

            <div class="app-surface overflow-hidden rounded-lg border">
                <div class="flex flex-col gap-2 border-b px-5 py-4 sm:flex-row sm:items-center sm:justify-between" style="border-color: var(--stj-border);">
                    <div>
                        <h2 class="app-text text-xl font-semibold">Resumen por plataforma</h2>
                        <p class="app-muted mt-1 text-sm">Haz clic sobre un monto para consultar sus pedidos.</p>
                    </div>
                    <div class="app-text text-sm font-semibold">{{ report.totals.orders }} pedidos · {{ money(report.totals.amount) }}</div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[680px] text-sm">
                        <thead class="app-surface-soft app-text"><tr><th class="px-5 py-3 text-left">Origen</th><th class="px-5 py-3 text-left">Tipo</th><th class="px-5 py-3 text-right">Total pedidos</th><th class="px-5 py-3 text-right">Total monto</th></tr></thead>
                        <tbody>
                            <tr v-for="row in report.rows" :key="`${row.platform}-${row.type}`" class="border-t" style="border-color: var(--stj-border);">
                                <td class="app-text px-5 py-3"><span class="font-semibold">{{ platformLabels[row.platform] }}</span><div class="app-muted mt-0.5 text-xs">{{ row.origin }}</div></td>
                                <td class="app-text px-5 py-3">{{ row.type }}</td>
                                <td class="app-text px-5 py-3 text-right">{{ row.orders }}</td>
                                <td class="px-5 py-3 text-right"><button class="font-semibold text-blue-600 hover:underline" type="button" @click="openDetail(row)">{{ money(row.amount) }}</button></td>
                            </tr>
                            <tr v-if="loading"><td colspan="4" class="app-muted px-5 py-10 text-center">Cargando ventas...</td></tr>
                            <tr v-else-if="!report.rows.length"><td colspan="4" class="app-muted px-5 py-10 text-center">No hay pagos aprobados para los filtros seleccionados.</td></tr>
                        </tbody>
                        <tfoot v-if="report.rows.length" class="app-surface-soft app-text font-semibold"><tr><td colspan="2" class="px-5 py-3">Totales</td><td class="px-5 py-3 text-right">{{ report.totals.orders }}</td><td class="px-5 py-3 text-right">{{ money(report.totals.amount) }}</td></tr></tfoot>
                    </table>
                </div>
            </div>
        </section>

        <div v-if="detail" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" @click.self="closeDetail">
            <section class="app-surface flex max-h-[90vh] w-full max-w-6xl flex-col overflow-hidden rounded-xl border shadow-2xl">
                <header class="flex items-start justify-between gap-4 border-b p-5" style="border-color: var(--stj-border);">
                    <div><p class="app-primary-text text-xs font-semibold uppercase">Pedidos por plataforma</p><h2 class="app-text mt-1 text-xl font-semibold">{{ platformLabels[detail.filters?.platform || detail.platform] }} · {{ detail.filters?.type || detail.type }}</h2><p v-if="detail.summary" class="app-muted mt-1 text-sm">{{ detail.summary.orders }} pedidos · {{ money(detail.summary.amount) }}</p></div>
                    <button type="button" class="app-input rounded-md border px-3 py-2 text-sm" @click="closeDetail">Cerrar</button>
                </header>
                <div class="overflow-auto">
                    <table class="w-full min-w-[900px] text-sm">
                        <thead class="app-surface-soft app-text sticky top-0"><tr><th class="p-3 text-left">Pedido</th><th class="p-3 text-left">Fecha pago</th><th class="p-3 text-left">Cliente</th><th class="p-3 text-left">Estado pedido</th><th class="p-3 text-left">Plataforma</th><th class="p-3 text-left">Tipo</th><th class="p-3 text-right">Monto</th></tr></thead>
                        <tbody>
                            <tr v-for="order in detail.orders" :key="`${order.reference}-${order.date}`" class="border-t" style="border-color: var(--stj-border);"><td class="p-3"><a :href="orderHref(order)" class="font-semibold text-blue-600 hover:underline">{{ order.reference }}</a></td><td class="app-text whitespace-nowrap p-3">{{ dateTime(order.date) }}</td><td class="app-text p-3">{{ order.customer || 'Sin nombre' }}</td><td class="app-text p-3">{{ order.status || '-' }}</td><td class="app-text p-3">{{ platformLabels[order.platform] }}</td><td class="app-text p-3">{{ order.type }}</td><td class="app-text p-3 text-right font-semibold">{{ money(order.amount) }}</td></tr>
                            <tr v-if="ordersLoading"><td colspan="7" class="app-muted p-10 text-center">Cargando pedidos...</td></tr>
                            <tr v-else-if="!detail.orders.length"><td colspan="7" class="app-muted p-10 text-center">No se encontraron pedidos.</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AdminLayout>
</template>
