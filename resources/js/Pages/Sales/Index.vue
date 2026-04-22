<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';

const loading = ref(false);
const bootstrapping = ref(true);
const error = ref('');
const countries = ref([]);
const filters = ref({
    country: '',
    startDate: today(),
    endDate: today(),
});
const data = ref(emptyData());
const activeTab = ref('hour');
const detailOpen = ref(false);
const detailLoading = ref(false);
const detailError = ref('');
const detailMode = ref('approved');
const detailContext = ref({});
const detailData = ref(emptyDetailData());

const selectedCountry = computed(() =>
    countries.value.find((country) => String(country.id) === String(filters.value.country)),
);

const tabs = [
    { id: 'hour', label: 'Venta por hora' },
    { id: 'store', label: 'Venta por tienda' },
    { id: 'promotions', label: 'Promociones' },
    { id: 'pending', label: 'Pedidos pendientes' },
];

async function fetchKpi({ autoSelectCountry = false } = {}) {
    loading.value = true;
    error.value = '';

    try {
        const response = await window.axios.get('/dashboard-api/sales/kpi', {
            params: {
                country: filters.value.country || undefined,
                startDate: filters.value.startDate || undefined,
                endDate: filters.value.endDate || undefined,
            },
        });

        data.value = {
            ...emptyData(),
            ...(response.data.data || {}),
        };
        countries.value = data.value.countries || [];

        if (autoSelectCountry && !filters.value.country && countries.value.length) {
            filters.value.country = String(countries.value[0].id);
            await fetchKpi();
        }
    } catch (exception) {
        error.value = exception.response?.data?.message || 'No fue posible cargar el KPI de ventas.';
        data.value = {
            ...data.value,
            summary: [],
            summaryTotals: emptyData().summaryTotals,
            margin: emptyData().margin,
            preparedTotals: emptyData().preparedTotals,
            salesByHour: [],
            salesByStore: [],
            promotions: emptyData().promotions,
            pendingOrders: emptyData().pendingOrders,
        };
    } finally {
        loading.value = false;
        bootstrapping.value = false;
    }
}

function submitFilters() {
    fetchKpi();
}

async function openApprovedDetail(row = null) {
    detailMode.value = 'approved';
    detailContext.value = {
        source: 'totals',
        origin: row?.origin || '',
        checkout: row?.checkout || '',
    };

    await fetchOrderDetail({
        country: filters.value.country,
        startDate: filters.value.startDate,
        endDate: filters.value.endDate,
        origin: row?.origin || undefined,
        checkout: row?.checkout || undefined,
    });
}

async function openPendingDetail(row = null) {
    detailMode.value = 'pending';
    detailContext.value = {
        store: row?.store || '',
        storeCode: row?.storeCode || '',
    };

    await fetchOrderDetail({
        country: filters.value.country,
        pending: true,
        store: row?.storeCode || undefined,
    });
}

async function fetchOrderDetail(params) {
    if (!params.country) {
        detailError.value = 'Debe seleccionar un pais antes de consultar el detalle.';
        detailOpen.value = true;
        return;
    }

    detailOpen.value = true;
    detailLoading.value = true;
    detailError.value = '';
    detailData.value = emptyDetailData();

    try {
        const response = await window.axios.get('/dashboard-api/sales/orders', { params });
        detailData.value = {
            ...emptyDetailData(),
            ...(response.data.data || {}),
        };
    } catch (exception) {
        detailError.value = exception.response?.data?.message || 'No fue posible cargar el detalle de pedidos.';
    } finally {
        detailLoading.value = false;
    }
}

function closeDetail() {
    detailOpen.value = false;
}

function downloadDetailCsv() {
    if (!detailData.value.orders.length) {
        return;
    }

    const headers = [
        'Origen',
        'Checkout',
        'Ref',
        'Fecha',
        'Cliente',
        'Identificacion',
        'Email',
        'Tipo pago',
        'Emisor',
        'Tarjeta/Cambio',
        'Monto',
        'Articulos',
        'Direccion/Tienda',
    ];
    const rows = detailData.value.orders.map((order) => [
        order.origin,
        order.checkout,
        order.ref,
        formatDateTime(order.paidAt),
        order.customer,
        order.identification,
        order.email,
        order.paymentType,
        order.issuer,
        order.cardOrChange,
        Number(order.amount || 0).toFixed(2),
        order.items,
        order.destination,
    ]);
    const csv = [headers, ...rows]
        .map((row) => row.map(csvCell).join(','))
        .join('\r\n');
    const blob = new Blob([`\uFEFF${csv}`], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');

    link.href = URL.createObjectURL(blob);
    link.download = `${detailFilePrefix()}-${new Date().toISOString().slice(0, 10)}.csv`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(link.href);
}

function csvCell(value) {
    const text = String(value ?? '');

    return `"${text.replace(/"/g, '""')}"`;
}

function emptyData() {
    return {
        countries: [],
        filters: {},
        summary: [],
        summaryTotals: {
            completeOrders: 0,
            partialOrders: 0,
            cancelledOrders: 0,
            completeAmount: 0,
            partialAmount: 0,
            refundAmount: 0,
            totalAmount: 0,
        },
        margin: {
            subtotal: 0,
            total: 0,
            discount: 0,
            discountRate: 0,
        },
        preparedTotals: {
            subtotal: 0,
            total: 0,
            discount: 0,
            discountRate: 0,
        },
        salesByHour: [],
        salesByStore: [],
        promotions: {
            rows: [],
            unassigned: {
                units: 0,
                grossAmount: 0,
                netAmount: 0,
                discountAmount: 0,
            },
            totals: {
                units: 0,
                grossAmount: 0,
                netAmount: 0,
                discountAmount: 0,
            },
        },
        pendingOrders: {
            rows: [],
            totals: {
                orders: 0,
                items: 0,
                amount: 0,
            },
        },
    };
}

function emptyDetailData() {
    return {
        filters: {},
        summary: {
            orders: 0,
            items: 0,
            amount: 0,
        },
        orders: [],
    };
}

function today() {
    return new Date().toISOString().slice(0, 10);
}

function formatMoney(value) {
    return Number(value || 0).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function formatNumber(value) {
    return Number(value || 0).toLocaleString('en-US');
}

function formatPercent(value) {
    return `${Number(value || 0).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}%`;
}

function formatDateTime(value) {
    if (!value) {
        return 'N/D';
    }

    return String(value).replace('T', ' ').slice(0, 16);
}

function detailTitle() {
    if (detailMode.value === 'pending') {
        return detailContext.value.store
            ? `Pedidos pendientes - ${detailContext.value.store}`
            : 'Pedidos pendientes';
    }

    const parts = ['Detalle de pedidos'];

    if (detailContext.value.origin) {
        parts.push(detailContext.value.origin);
    }

    if (detailContext.value.checkout) {
        parts.push(detailContext.value.checkout);
    }

    return parts.join(' - ');
}

function detailFilePrefix() {
    if (detailMode.value === 'pending') {
        return detailContext.value.storeCode
            ? `pedidos-pendientes-tienda-${detailContext.value.storeCode}`
            : 'pedidos-pendientes';
    }

    const parts = ['pedidos-totales'];

    if (detailContext.value.origin) {
        parts.push(detailContext.value.origin);
    }

    if (detailContext.value.checkout) {
        parts.push(detailContext.value.checkout);
    }

    return parts
        .join('-')
        .toLowerCase()
        .replace(/[^a-z0-9-]+/g, '-')
        .replace(/-+/g, '-')
        .replace(/^-|-$/g, '');
}

function orderUrl(order) {
    return `/pedidos/consulta?country=${order.countryId}&id=${encodeURIComponent(order.ref)}`;
}

onMounted(() => fetchKpi({ autoSelectCountry: true }));
</script>

<template>
    <Head title="Venta" />

    <AdminLayout>
        <section class="mx-auto w-full max-w-7xl">
            <div class="app-surface rounded-lg border p-6">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="app-primary-text text-sm font-semibold uppercase">
                            Ventas
                        </p>
                        <h1 class="app-text mt-3 text-3xl font-semibold">
                            Indicadores
                        </h1>
                        <p class="app-muted mt-2 max-w-3xl text-sm leading-6">
                            Cortes migrados desde el KPI anterior: resumen, venta por hora, venta por tienda, promociones y pendientes.
                        </p>
                    </div>

                    <div v-if="selectedCountry" class="app-surface-soft rounded-md border px-4 py-3 text-sm">
                        <span class="app-muted">Pais:</span>
                        <span class="app-text ml-2 font-semibold">{{ selectedCountry.code }} - {{ selectedCountry.name }}</span>
                    </div>
                </div>

                <form class="mt-6 grid gap-4 lg:grid-cols-[1.2fr_1fr_1fr_auto]" @submit.prevent="submitFilters">
                    <label class="block text-sm font-semibold">
                        <span class="app-muted">Pais</span>
                        <select
                            v-model="filters.country"
                            class="app-surface app-text mt-2 h-11 w-full rounded-md border px-3 text-sm outline-none focus:ring-4"
                            required
                        >
                            <option value="">-- Seleccione --</option>
                            <option v-for="country in countries" :key="country.id" :value="String(country.id)">
                                {{ country.code }} - {{ country.name }}
                            </option>
                        </select>
                    </label>

                    <label class="block text-sm font-semibold">
                        <span class="app-muted">Inicio</span>
                        <input
                            v-model="filters.startDate"
                            type="date"
                            class="app-surface app-text mt-2 h-11 w-full rounded-md border px-3 text-sm outline-none focus:ring-4"
                            required
                        />
                    </label>

                    <label class="block text-sm font-semibold">
                        <span class="app-muted">Fin</span>
                        <input
                            v-model="filters.endDate"
                            type="date"
                            class="app-surface app-text mt-2 h-11 w-full rounded-md border px-3 text-sm outline-none focus:ring-4"
                            required
                        />
                    </label>

                    <button
                        type="submit"
                        class="app-primary inline-flex h-11 items-center justify-center gap-2 rounded-md px-5 text-sm font-semibold shadow-sm disabled:opacity-60"
                        :disabled="loading"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 21l-4.3-4.3M11 18a7 7 0 1 1 0-14 7 7 0 0 1 0 14z" stroke-linecap="round" />
                        </svg>
                        {{ loading ? 'Buscando...' : 'Buscar' }}
                    </button>
                </form>
            </div>

            <div v-if="error" class="mt-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                {{ error }}
            </div>

            <div v-if="bootstrapping" class="app-surface mt-6 rounded-lg border p-6 text-sm font-semibold">
                Cargando indicadores...
            </div>

            <template v-else>
                <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="app-surface rounded-lg border p-5">
                        <p class="app-muted text-xs font-semibold uppercase">Pedidos completos</p>
                        <p class="app-text mt-2 text-2xl font-semibold">{{ formatNumber(data.summaryTotals.completeOrders) }}</p>
                    </div>
                    <div class="app-surface rounded-lg border p-5">
                        <p class="app-muted text-xs font-semibold uppercase">Pedidos incompletos</p>
                        <p class="app-text mt-2 text-2xl font-semibold">{{ formatNumber(data.summaryTotals.partialOrders) }}</p>
                    </div>
                    <div class="app-surface rounded-lg border p-5">
                        <p class="app-muted text-xs font-semibold uppercase">Anulados</p>
                        <p class="app-text mt-2 text-2xl font-semibold">{{ formatNumber(data.summaryTotals.cancelledOrders) }}</p>
                    </div>
                    <div class="app-surface rounded-lg border p-5">
                        <p class="app-muted text-xs font-semibold uppercase">Venta total</p>
                        <p class="app-text mt-2 text-2xl font-semibold">{{ formatMoney(data.summaryTotals.totalAmount) }}</p>
                    </div>
                </div>

                <div class="mt-6 grid gap-4 lg:grid-cols-2">
                    <div class="app-surface rounded-lg border p-5">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="app-muted text-xs font-semibold uppercase">Margen por fecha</p>
                                <p class="app-text mt-2 text-2xl font-semibold">{{ formatMoney(data.margin.total) }}</p>
                            </div>
                            <div class="app-surface-soft rounded-md border px-3 py-2 text-right text-xs">
                                <p class="app-muted">Descuento</p>
                                <p class="app-text font-semibold">{{ formatPercent(data.margin.discountRate) }}</p>
                            </div>
                        </div>
                        <div class="app-muted mt-4 grid gap-2 text-sm sm:grid-cols-3">
                            <span>Sin desc. {{ formatMoney(data.margin.subtotal) }}</span>
                            <span>Con desc. {{ formatMoney(data.margin.total) }}</span>
                            <span>Ahorro {{ formatMoney(data.margin.discount) }}</span>
                        </div>
                    </div>

                    <div class="app-surface rounded-lg border p-5">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="app-muted text-xs font-semibold uppercase">Preparado / en ruta</p>
                                <p class="app-text mt-2 text-2xl font-semibold">{{ formatMoney(data.preparedTotals.total) }}</p>
                            </div>
                            <div class="app-surface-soft rounded-md border px-3 py-2 text-right text-xs">
                                <p class="app-muted">Descuento</p>
                                <p class="app-text font-semibold">{{ formatPercent(data.preparedTotals.discountRate) }}</p>
                            </div>
                        </div>
                        <div class="app-muted mt-4 grid gap-2 text-sm sm:grid-cols-3">
                            <span>Sub total {{ formatMoney(data.preparedTotals.subtotal) }}</span>
                            <span>Total {{ formatMoney(data.preparedTotals.total) }}</span>
                            <span>Ahorro {{ formatMoney(data.preparedTotals.discount) }}</span>
                        </div>
                    </div>
                </div>

                <div class="app-surface mt-6 rounded-lg border p-4">
                    <div class="mb-4 flex flex-col gap-1">
                        <h2 class="app-text text-lg font-semibold">Totales</h2>
                        <p class="app-muted text-sm">Equivalente al resumen principal del KPI anterior.</p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[900px] text-sm">
                            <thead>
                                <tr class="app-primary text-left">
                                    <th class="px-3 py-2" rowspan="2">Origen</th>
                                    <th class="px-3 py-2" rowspan="2">Tipo</th>
                                    <th class="px-3 py-2 text-center" colspan="3">Pedidos</th>
                                    <th class="px-3 py-2 text-center" colspan="4">Venta</th>
                                </tr>
                                <tr class="app-primary text-left">
                                    <th class="px-3 py-2 text-right">Completos</th>
                                    <th class="px-3 py-2 text-right">Incompletos</th>
                                    <th class="px-3 py-2 text-right">Anulados</th>
                                    <th class="px-3 py-2 text-right">Completos</th>
                                    <th class="px-3 py-2 text-right">Incompletos</th>
                                    <th class="px-3 py-2 text-right">Devolucion</th>
                                    <th class="px-3 py-2 text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!data.summary.length">
                                    <td class="app-muted px-3 py-6 text-center" colspan="9">
                                        Sin datos para los filtros seleccionados.
                                    </td>
                                </tr>
                                <tr v-for="row in data.summary" :key="`${row.origin}-${row.checkout}`" class="app-border-soft border-b">
                                    <td class="app-text px-3 py-2">{{ row.origin || 'N/D' }}</td>
                                    <td class="app-text px-3 py-2">{{ row.checkout || 'N/D' }}</td>
                                    <td class="app-text px-3 py-2 text-right">{{ formatNumber(row.completeOrders) }}</td>
                                    <td class="app-text px-3 py-2 text-right">{{ formatNumber(row.partialOrders) }}</td>
                                    <td class="app-text px-3 py-2 text-right">{{ formatNumber(row.cancelledOrders) }}</td>
                                    <td class="app-text px-3 py-2 text-right">{{ formatMoney(row.completeAmount) }}</td>
                                    <td class="app-text px-3 py-2 text-right">{{ formatMoney(row.partialAmount) }}</td>
                                    <td class="app-text px-3 py-2 text-right">{{ formatMoney(row.refundAmount) }}</td>
                                    <td class="app-text px-3 py-2 text-right font-semibold">
                                        <button
                                            type="button"
                                            class="app-primary-text font-semibold underline-offset-4 hover:underline"
                                            @click="openApprovedDetail(row)"
                                        >
                                            {{ formatMoney(row.totalAmount) }}
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="app-surface-soft font-semibold">
                                    <td class="app-text px-3 py-2 text-right" colspan="2">Totales</td>
                                    <td class="app-text px-3 py-2 text-right">{{ formatNumber(data.summaryTotals.completeOrders) }}</td>
                                    <td class="app-text px-3 py-2 text-right">{{ formatNumber(data.summaryTotals.partialOrders) }}</td>
                                    <td class="app-text px-3 py-2 text-right">{{ formatNumber(data.summaryTotals.cancelledOrders) }}</td>
                                    <td class="app-text px-3 py-2 text-right">{{ formatMoney(data.summaryTotals.completeAmount) }}</td>
                                    <td class="app-text px-3 py-2 text-right">{{ formatMoney(data.summaryTotals.partialAmount) }}</td>
                                    <td class="app-text px-3 py-2 text-right">{{ formatMoney(data.summaryTotals.refundAmount) }}</td>
                                    <td class="app-text px-3 py-2 text-right">
                                        <button
                                            type="button"
                                            class="app-primary-text font-semibold underline-offset-4 hover:underline"
                                            @click="openApprovedDetail()"
                                        >
                                            {{ formatMoney(data.summaryTotals.totalAmount) }}
                                        </button>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <div class="app-surface mt-6 rounded-lg border p-4">
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="tab in tabs"
                            :key="tab.id"
                            type="button"
                            :class="[
                                'h-10 rounded-md px-4 text-sm font-semibold transition',
                                activeTab === tab.id ? 'app-primary' : 'app-surface-soft app-text',
                            ]"
                            @click="activeTab = tab.id"
                        >
                            {{ tab.label }}
                        </button>
                    </div>

                    <div v-if="activeTab === 'hour'" class="mt-4 overflow-x-auto">
                        <table class="w-full min-w-[720px] text-sm">
                            <thead>
                                <tr class="app-primary text-left">
                                    <th class="px-3 py-2">Fecha</th>
                                    <th class="px-3 py-2">Hora</th>
                                    <th class="px-3 py-2">Checkout</th>
                                    <th class="px-3 py-2 text-right">Pedidos</th>
                                    <th class="px-3 py-2 text-right">Articulos</th>
                                    <th class="px-3 py-2 text-right">Monto</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!data.salesByHour.length">
                                    <td class="app-muted px-3 py-6 text-center" colspan="6">Sin venta por hora.</td>
                                </tr>
                                <tr v-for="row in data.salesByHour" :key="`${row.date}-${row.hour}-${row.checkout}`" class="app-border-soft border-b">
                                    <td class="app-text px-3 py-2">{{ row.date }}</td>
                                    <td class="app-text px-3 py-2">{{ row.hour }}</td>
                                    <td class="app-text px-3 py-2">{{ row.checkout }}</td>
                                    <td class="app-text px-3 py-2 text-right">{{ formatNumber(row.orders) }}</td>
                                    <td class="app-text px-3 py-2 text-right">{{ formatNumber(row.items) }}</td>
                                    <td class="app-text px-3 py-2 text-right">{{ formatMoney(row.amount) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="activeTab === 'store'" class="mt-4 overflow-x-auto">
                        <table class="w-full min-w-[640px] text-sm">
                            <thead>
                                <tr class="app-primary text-left">
                                    <th class="px-3 py-2">Tienda</th>
                                    <th class="px-3 py-2 text-right">Pedidos</th>
                                    <th class="px-3 py-2 text-right">Articulos</th>
                                    <th class="px-3 py-2 text-right">Monto</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!data.salesByStore.length">
                                    <td class="app-muted px-3 py-6 text-center" colspan="4">Sin venta por tienda.</td>
                                </tr>
                                <tr v-for="row in data.salesByStore" :key="row.store" class="app-border-soft border-b">
                                    <td class="app-text px-3 py-2">{{ row.store }}</td>
                                    <td class="app-text px-3 py-2 text-right">{{ formatNumber(row.orders) }}</td>
                                    <td class="app-text px-3 py-2 text-right">{{ formatNumber(row.items) }}</td>
                                    <td class="app-text px-3 py-2 text-right">{{ formatMoney(row.amount) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="activeTab === 'promotions'" class="mt-4">
                        <div class="mb-4 grid gap-3 sm:grid-cols-4">
                            <div class="app-surface-soft rounded-md border p-3">
                                <p class="app-muted text-xs font-semibold uppercase">Unidades</p>
                                <p class="app-text mt-1 text-xl font-semibold">{{ formatNumber(data.promotions.totals.units) }}</p>
                            </div>
                            <div class="app-surface-soft rounded-md border p-3">
                                <p class="app-muted text-xs font-semibold uppercase">Sin descuento</p>
                                <p class="app-text mt-1 text-xl font-semibold">{{ formatMoney(data.promotions.totals.grossAmount) }}</p>
                            </div>
                            <div class="app-surface-soft rounded-md border p-3">
                                <p class="app-muted text-xs font-semibold uppercase">Con descuento</p>
                                <p class="app-text mt-1 text-xl font-semibold">{{ formatMoney(data.promotions.totals.netAmount) }}</p>
                            </div>
                            <div class="app-surface-soft rounded-md border p-3">
                                <p class="app-muted text-xs font-semibold uppercase">Descuento</p>
                                <p class="app-text mt-1 text-xl font-semibold">{{ formatMoney(data.promotions.totals.discountAmount) }}</p>
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[980px] text-sm">
                                <thead>
                                    <tr class="app-primary text-left">
                                        <th class="px-3 py-2">Promocion</th>
                                        <th class="px-3 py-2">Estado</th>
                                        <th class="px-3 py-2">Tipo</th>
                                        <th class="px-3 py-2">Inicio</th>
                                        <th class="px-3 py-2">Fin</th>
                                        <th class="px-3 py-2 text-right">Unidades</th>
                                        <th class="px-3 py-2 text-right">Sin desc.</th>
                                        <th class="px-3 py-2 text-right">Con desc.</th>
                                        <th class="px-3 py-2 text-right">Descuento</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="!data.promotions.rows.length">
                                        <td class="app-muted px-3 py-6 text-center" colspan="9">Sin promociones para el rango seleccionado.</td>
                                    </tr>
                                    <tr v-for="row in data.promotions.rows" :key="row.id" class="app-border-soft border-b">
                                        <td class="app-text px-3 py-2">
                                            <div class="font-semibold">{{ row.commercialName || row.name }}</div>
                                            <div class="app-muted text-xs">#{{ row.id }} · {{ row.name }}</div>
                                        </td>
                                        <td class="app-text px-3 py-2">{{ row.status }}</td>
                                        <td class="app-text px-3 py-2">{{ row.type }}</td>
                                        <td class="app-text px-3 py-2">{{ formatDateTime(row.startAt) }}</td>
                                        <td class="app-text px-3 py-2">{{ formatDateTime(row.endAt) }}</td>
                                        <td class="app-text px-3 py-2 text-right">{{ formatNumber(row.units) }}</td>
                                        <td class="app-text px-3 py-2 text-right">{{ formatMoney(row.grossAmount) }}</td>
                                        <td class="app-text px-3 py-2 text-right">{{ formatMoney(row.netAmount) }}</td>
                                        <td class="app-text px-3 py-2 text-right">{{ formatMoney(row.discountAmount) }}</td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr class="app-surface-soft font-semibold">
                                        <td class="app-text px-3 py-2 text-right" colspan="5">Sin promocion asignada</td>
                                        <td class="app-text px-3 py-2 text-right">{{ formatNumber(data.promotions.unassigned.units) }}</td>
                                        <td class="app-text px-3 py-2 text-right">{{ formatMoney(data.promotions.unassigned.grossAmount) }}</td>
                                        <td class="app-text px-3 py-2 text-right">{{ formatMoney(data.promotions.unassigned.netAmount) }}</td>
                                        <td class="app-text px-3 py-2 text-right">{{ formatMoney(data.promotions.unassigned.discountAmount) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <div v-if="activeTab === 'pending'" class="mt-4">
                        <div class="mb-4 grid gap-3 sm:grid-cols-3">
                            <div class="app-surface-soft rounded-md border p-3">
                                <p class="app-muted text-xs font-semibold uppercase">Total pedidos</p>
                                <p class="app-text mt-1 text-xl font-semibold">{{ formatNumber(data.pendingOrders.totals.orders) }}</p>
                            </div>
                            <div class="app-surface-soft rounded-md border p-3">
                                <p class="app-muted text-xs font-semibold uppercase">Total articulos</p>
                                <p class="app-text mt-1 text-xl font-semibold">{{ formatNumber(data.pendingOrders.totals.items) }}</p>
                            </div>
                            <div class="app-surface-soft rounded-md border p-3">
                                <p class="app-muted text-xs font-semibold uppercase">Total venta</p>
                                <p class="app-text mt-1 text-xl font-semibold">{{ formatMoney(data.pendingOrders.totals.amount) }}</p>
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[640px] text-sm">
                                <thead>
                                    <tr class="app-primary text-left">
                                        <th class="px-3 py-2">Tienda</th>
                                        <th class="px-3 py-2 text-right">Pedidos</th>
                                        <th class="px-3 py-2 text-right">Articulos</th>
                                        <th class="px-3 py-2 text-right">Monto</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="!data.pendingOrders.rows.length">
                                        <td class="app-muted px-3 py-6 text-center" colspan="4">Sin pedidos pendientes.</td>
                                    </tr>
                                    <tr v-for="row in data.pendingOrders.rows" :key="row.storeCode" class="app-border-soft border-b">
                                        <td class="app-text px-3 py-2">{{ row.store }}</td>
                                        <td class="app-text px-3 py-2 text-right">
                                            <button
                                                type="button"
                                                class="app-primary-text font-semibold underline-offset-4 hover:underline"
                                                @click="openPendingDetail(row)"
                                            >
                                                {{ formatNumber(row.orders) }}
                                            </button>
                                        </td>
                                        <td class="app-text px-3 py-2 text-right">{{ formatNumber(row.items) }}</td>
                                        <td class="app-text px-3 py-2 text-right">{{ formatMoney(row.amount) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </template>
        </section>

        <div
            v-if="detailOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 p-4"
            @click.self="closeDetail"
        >
            <div class="app-surface max-h-[92vh] w-full max-w-7xl overflow-hidden rounded-lg border shadow-2xl">
                <div class="app-border-soft flex items-start justify-between gap-4 border-b px-5 py-4">
                    <div>
                        <p class="app-primary-text text-xs font-semibold uppercase">Ventas</p>
                        <h2 class="app-text mt-1 text-xl font-semibold">{{ detailTitle() }}</h2>
                        <div class="app-muted mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs">
                            <span>Pais: {{ selectedCountry?.code || filters.country }}</span>
                            <span v-if="detailMode === 'approved'">Inicio: {{ filters.startDate }}</span>
                            <span v-if="detailMode === 'approved'">Fin: {{ filters.endDate }}</span>
                            <span v-if="detailContext.origin">Origen: {{ detailContext.origin }}</span>
                            <span v-if="detailContext.checkout">Checkout: {{ detailContext.checkout }}</span>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="app-primary inline-flex h-9 items-center gap-2 rounded-md px-3 text-sm font-semibold disabled:opacity-50"
                            :disabled="detailLoading || !detailData.orders.length"
                            title="Descargar Excel"
                            @click="downloadDetailCsv"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 3v12M7 10l5 5 5-5M5 21h14" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            Excel
                        </button>
                        <button
                            type="button"
                            class="app-surface-soft app-text flex h-9 w-9 items-center justify-center rounded-md border text-xl"
                            title="Cerrar"
                            @click="closeDetail"
                        >
                            &times;
                        </button>
                    </div>
                </div>

                <div class="max-h-[calc(92vh-92px)] overflow-y-auto p-5">
                    <div v-if="detailError" class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                        {{ detailError }}
                    </div>

                    <div v-if="detailLoading" class="app-surface-soft rounded-md border p-4 text-sm font-semibold">
                        Cargando detalle...
                    </div>

                    <template v-else>
                        <div class="mb-4 grid gap-3 sm:grid-cols-3">
                            <div class="app-surface-soft rounded-md border p-3">
                                <p class="app-muted text-xs font-semibold uppercase">Pedidos</p>
                                <p class="app-text mt-1 text-xl font-semibold">{{ formatNumber(detailData.summary.orders) }}</p>
                            </div>
                            <div class="app-surface-soft rounded-md border p-3">
                                <p class="app-muted text-xs font-semibold uppercase">Articulos</p>
                                <p class="app-text mt-1 text-xl font-semibold">{{ formatNumber(detailData.summary.items) }}</p>
                            </div>
                            <div class="app-surface-soft rounded-md border p-3">
                                <p class="app-muted text-xs font-semibold uppercase">Monto</p>
                                <p class="app-text mt-1 text-xl font-semibold">{{ formatMoney(detailData.summary.amount) }}</p>
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[1180px] text-xs">
                                <thead>
                                    <tr class="app-primary text-left">
                                        <th class="px-3 py-2">Origen</th>
                                        <th class="px-3 py-2">Checkout</th>
                                        <th class="px-3 py-2">Ref</th>
                                        <th class="px-3 py-2">Fecha</th>
                                        <th class="px-3 py-2">Cliente</th>
                                        <th class="px-3 py-2">Identificacion</th>
                                        <th class="px-3 py-2">Email</th>
                                        <th class="px-3 py-2">Tipo pago</th>
                                        <th class="px-3 py-2">Emisor</th>
                                        <th class="px-3 py-2">Tarjeta/Cambio</th>
                                        <th class="px-3 py-2 text-right">Monto</th>
                                        <th class="px-3 py-2 text-right">Articulos</th>
                                        <th class="px-3 py-2">Direccion/Tienda</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="!detailData.orders.length">
                                        <td class="app-muted px-3 py-6 text-center" colspan="13">
                                            Sin pedidos para el filtro seleccionado.
                                        </td>
                                    </tr>
                                    <tr v-for="order in detailData.orders" :key="order.ref" class="app-border-soft border-b align-top">
                                        <td class="app-text px-3 py-2">{{ order.origin }}</td>
                                        <td class="app-text px-3 py-2">{{ order.checkout }}</td>
                                        <td class="px-3 py-2">
                                            <a class="app-primary-text font-semibold underline-offset-4 hover:underline" :href="orderUrl(order)">
                                                {{ order.ref }}
                                            </a>
                                        </td>
                                        <td class="app-text min-w-32 px-3 py-2">{{ formatDateTime(order.paidAt) }}</td>
                                        <td class="app-text min-w-40 px-3 py-2">{{ order.customer }}</td>
                                        <td class="app-text px-3 py-2">{{ order.identification }}</td>
                                        <td class="app-text px-3 py-2">{{ order.email }}</td>
                                        <td class="app-text px-3 py-2">{{ order.paymentType }}</td>
                                        <td class="app-text px-3 py-2">{{ order.issuer }}</td>
                                        <td class="app-text px-3 py-2">{{ order.cardOrChange }}</td>
                                        <td class="app-text px-3 py-2 text-right">{{ formatMoney(order.amount) }}</td>
                                        <td class="app-text px-3 py-2 text-right">{{ formatNumber(order.items) }}</td>
                                        <td class="app-text min-w-56 px-3 py-2">{{ order.destination }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
