<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import DataTable from 'datatables.net-vue3';
import DataTablesCore from 'datatables.net-dt';
import { computed, onMounted, ref, watch } from 'vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';

DataTable.use(DataTablesCore);

const page = usePage();
const loading = ref(false);
const catalogLoading = ref(true);
const error = ref('');
const catalogError = ref('');
const countries = ref([]);
const stores = ref([]);
const report = ref(emptyReport());
const tableKey = ref(0);

const user = computed(() => page.props.auth?.user || {});
const permissions = computed(() => page.props.auth?.permissions || []);
const storeCode = computed(() => String(user.value?.storeCode || user.value?.tiendas || '00000'));
const hasAssignedStore = computed(() => storeCode.value !== '' && storeCode.value !== '00000');
const canUseGlobalFilters = computed(() =>
    permissions.value.includes('ROOT')
    || permissions.value.includes('STIE')
    || permissions.value.includes('GERENTE')
    || permissions.value.includes('SUPERVISOR'),
);
const showStoreFilter = computed(() => canUseGlobalFilters.value);
const resolvedStoreLabel = computed(() =>
    report.value.filters?.storeName
        ? `${report.value.filters.storeName} (${report.value.filters.store || storeCode.value || 'N/D'})`
        : user.value?.storeLabel || user.value?.tiendas || 'N/D',
);

const filters = ref({
    country: String(user.value?.idPais || ''),
    store: '',
    date: today(),
});

const columns = [
    { data: 'purchaseDate', title: 'Fecha compra' },
    { data: 'paymentType', title: 'Forma pago' },
    { data: 'reference', title: 'Referencia' },
    { data: 'ticket', title: 'Ticket' },
    { data: 'processedAt', title: 'Fecha facturado' },
    { data: 'authorization', title: 'Autorizacion' },
    { data: 'chargedAmountLabel', title: 'Monto cobrado', className: 'dt-right' },
    { data: 'shippingLabel', title: 'Envio', className: 'dt-right' },
    { data: 'refundLabel', title: 'Devolucion', className: 'dt-right' },
    { data: 'totalLabel', title: 'Total', className: 'dt-right' },
];

const options = {
    pageLength: 25,
    lengthMenu: [10, 25, 50, 100],
    order: [[0, 'asc']],
    autoWidth: false,
    language: {
        search: 'Buscar:',
        lengthMenu: 'Mostrar _MENU_ registros',
        info: 'Mostrando _START_ a _END_ de _TOTAL_ transacciones',
        infoEmpty: 'Sin transacciones',
        infoFiltered: '(filtrado de _MAX_ registros)',
        zeroRecords: 'No se encontraron transacciones',
        emptyTable: 'No hay transacciones para los filtros seleccionados',
        paginate: {
            first: 'Primero',
            last: 'Ultimo',
            next: 'Siguiente',
            previous: 'Anterior',
        },
    },
};

const rows = computed(() =>
    report.value.rows.map((row) => ({
        ...row,
        purchaseDate: formatDateTime(row.purchaseDate),
        processedAt: formatDateTime(row.processedAt),
        chargedAmountLabel: formatMoney(row.chargedAmount),
        shippingLabel: formatMoney(row.shipping),
        refundLabel: formatMoney(row.refund),
        totalLabel: formatMoney(row.total),
    })),
);

const summaryCards = computed(() => [
    { label: 'Transacciones', value: formatNumber(report.value.summary.transactions) },
    { label: 'Tarjeta', value: currencyMoney(report.value.summary.card) },
    { label: 'Efectivo', value: currencyMoney(report.value.summary.cash) },
    { label: 'Total', value: currencyMoney(report.value.summary.total) },
]);

async function fetchCatalog() {
    catalogLoading.value = true;
    catalogError.value = '';

    try {
        const response = await window.axios.get('/dashboard-api/reports/store/catalog', {
            params: {
                country: activeCountry(),
            },
        });
        const payload = response.data.data || {};
        countries.value = payload.countries || [];
        stores.value = payload.stores || [];

        if (canUseGlobalFilters.value && countries.value.length) {
            const selectedCountryExists = countries.value.some((country) => String(country.id) === String(filters.value.country));

            if (!selectedCountryExists) {
                filters.value.country = String(countries.value[0].id);
                return;
            }
        }

        if (showStoreFilter.value && filters.value.store) {
            const exists = stores.value.some((store) => String(store.storeId) === String(filters.value.store));

            if (!exists) {
                filters.value.store = '';
            }
        }
    } catch (exception) {
        catalogError.value = exception.response?.data?.message || 'No fue posible cargar paises y tiendas.';
        stores.value = [];
    } finally {
        catalogLoading.value = false;
    }
}

async function fetchReport() {
    loading.value = true;
    error.value = '';

    try {
        const response = await window.axios.get('/dashboard-api/reports/store/virtual-cut', {
            params: {
                country: activeCountry(),
                store: activeStore(),
                date: filters.value.date,
            },
        });

        report.value = {
            ...emptyReport(),
            ...(response.data.data || {}),
        };
        tableKey.value += 1;
    } catch (exception) {
        error.value = exception.response?.data?.message || 'No fue posible generar el corte virtual.';
        report.value = emptyReport();
        tableKey.value += 1;
    } finally {
        loading.value = false;
    }
}

async function submitFilters() {
    await fetchReport();
}

function printReport() {
    window.print();
}

function downloadPdf() {
    const params = new URLSearchParams({
        country: activeCountry(),
        store: activeStore(),
        date: filters.value.date,
    });

    window.location.href = `/dashboard-api/reports/store/virtual-cut/pdf?${params.toString()}`;
}

function activeCountry() {
    return canUseGlobalFilters.value ? filters.value.country : String(user.value?.idPais || '');
}

function activeStore() {
    return canUseGlobalFilters.value ? filters.value.store || '' : storeCode.value;
}

function today() {
    const date = new Date();
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

function emptyReport() {
    return {
        filters: {},
        currency: {
            symbol: '',
        },
        summary: {
            transactions: 0,
            card: 0,
            cash: 0,
            total: 0,
        },
        rows: [],
    };
}

function currencyMoney(value) {
    const symbol = report.value.currency?.symbol || '';
    const amount = formatMoney(value);

    return symbol ? `${symbol}${amount}` : amount;
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

function formatDateTime(value) {
    if (!value) {
        return 'N/D';
    }

    return String(value).replace('T', ' ').slice(0, 19);
}

onMounted(async () => {
    if (!canUseGlobalFilters.value) {
        filters.value.country = String(user.value?.idPais || '');
    }

    await fetchCatalog();

    if (!showStoreFilter.value || filters.value.store) {
        await fetchReport();
    }
});

watch(
    () => filters.value.country,
    async () => {
        if (!canUseGlobalFilters.value) {
            return;
        }

        filters.value.store = '';
        await fetchCatalog();
    },
);
</script>

<template>
    <Head title="Reportes / Corte Virtual" />

    <AdminLayout>
        <section class="mx-auto w-full max-w-7xl">
            <div class="app-surface rounded-lg border p-6 print:shadow-none">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="app-primary-text text-sm font-semibold uppercase">
                            Reportes / Tiendas
                        </p>
                        <h1 class="app-text mt-3 text-3xl font-semibold">
                            Corte Virtual
                        </h1>
                        <p class="app-muted mt-2 max-w-3xl text-sm leading-6">
                            Resumen de transacciones aprobadas por tienda y fecha.
                        </p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div v-if="!canUseGlobalFilters" class="app-surface-soft rounded-md border px-4 py-3 text-sm">
                            <span class="app-muted">Pais:</span>
                            <span class="app-text ml-2 font-semibold">{{ user?.pais || 'N/D' }}</span>
                        </div>
                        <div v-if="hasAssignedStore && !showStoreFilter" class="app-surface-soft rounded-md border px-4 py-3 text-sm">
                            <span class="app-muted">Tienda:</span>
                            <span class="app-text ml-2 font-semibold">{{ resolvedStoreLabel }}</span>
                        </div>
                    </div>
                </div>

                <form
                    class="no-print mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-[1fr_1fr_1fr_auto_auto_auto]"
                    @submit.prevent="submitFilters"
                >
                    <label v-if="canUseGlobalFilters" class="block text-sm font-semibold">
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

                    <label v-if="showStoreFilter" class="block text-sm font-semibold">
                        <span class="app-muted">Tienda</span>
                        <select
                            v-model="filters.store"
                            class="app-surface app-text mt-2 h-11 w-full rounded-md border px-3 text-sm outline-none focus:ring-4"
                            required
                            :disabled="catalogLoading || !filters.country"
                        >
                            <option value="">-- Seleccione --</option>
                            <option v-for="store in stores" :key="store.storeId" :value="String(store.storeId)">
                                {{ store.store }}
                            </option>
                        </select>
                    </label>

                    <label class="block text-sm font-semibold">
                        <span class="app-muted">Fecha</span>
                        <input
                            v-model="filters.date"
                            type="date"
                            class="app-surface app-text mt-2 h-11 w-full rounded-md border px-3 text-sm outline-none focus:ring-4"
                            required
                        />
                    </label>

                    <button
                        type="submit"
                        class="app-primary inline-flex h-11 items-center justify-center gap-2 self-end rounded-md px-5 text-sm font-semibold shadow-sm disabled:opacity-60"
                        :disabled="loading || catalogLoading || (showStoreFilter && !filters.store)"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 21l-4.3-4.3M11 18a7 7 0 1 1 0-14 7 7 0 0 1 0 14z" stroke-linecap="round" />
                        </svg>
                        {{ loading ? 'Generando...' : 'Generar' }}
                    </button>

                    <button
                        type="button"
                        class="app-surface app-text inline-flex h-11 items-center justify-center gap-2 self-end rounded-md border px-5 text-sm font-semibold disabled:opacity-60"
                        :disabled="loading || !report.rows.length"
                        @click="printReport"
                    >
                        Imprimir
                    </button>

                    <button
                        type="button"
                        class="app-surface app-text inline-flex h-11 items-center justify-center gap-2 self-end rounded-md border px-5 text-sm font-semibold disabled:opacity-60"
                        :disabled="loading || (showStoreFilter && !filters.store)"
                        @click="downloadPdf"
                    >
                        PDF
                    </button>
                </form>
            </div>

            <div v-if="catalogError" class="no-print mt-6 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-700">
                {{ catalogError }}
            </div>

            <div v-if="error" class="no-print mt-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                {{ error }}
            </div>

            <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div
                    v-for="card in summaryCards"
                    :key="card.label"
                    class="app-surface rounded-lg border p-5 print:shadow-none"
                >
                    <p class="app-muted text-xs font-semibold uppercase">{{ card.label }}</p>
                    <p class="app-text mt-2 text-2xl font-semibold">{{ card.value }}</p>
                </div>
            </div>

            <div class="app-surface mt-6 rounded-lg border p-4 print:shadow-none">
                <div class="mb-4 grid gap-2 text-sm sm:grid-cols-3">
                    <div>
                        <span class="app-muted">Tienda:</span>
                        <span class="app-text ml-2 font-semibold">{{ report.filters?.storeName || resolvedStoreLabel }}</span>
                    </div>
                    <div>
                        <span class="app-muted">Codigo:</span>
                        <span class="app-text ml-2 font-semibold">{{ report.filters?.store || activeStore() || 'N/D' }}</span>
                    </div>
                    <div>
                        <span class="app-muted">Fecha:</span>
                        <span class="app-text ml-2 font-semibold">{{ report.filters?.date || filters.date }}</span>
                    </div>
                </div>

                <div v-if="loading" class="app-muted px-4 py-8 text-center text-sm">
                    Generando corte virtual...
                </div>

                <DataTable
                    v-else
                    :key="tableKey"
                    :data="rows"
                    :columns="columns"
                    :options="options"
                    class="display stj-data-table w-full"
                />
            </div>
        </section>
    </AdminLayout>
</template>

<style>
@media print {
    .no-print,
    .dt-search,
    .dt-length,
    .dt-info,
    .dt-paging {
        display: none !important;
    }

    body {
        background: #ffffff !important;
    }

    .app-surface {
        border-color: #d1d5db !important;
        box-shadow: none !important;
    }
}
</style>
