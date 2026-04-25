<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import DataTable from 'datatables.net-vue3';
import DataTablesCore from 'datatables.net-dt';
import { computed, onMounted, ref } from 'vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';

DataTable.use(DataTablesCore);

const page = usePage();
const loading = ref(true);
const error = ref('');
const summaryError = ref('');
const data = ref(emptyData());
const countries = ref([]);
const stores = ref([]);
const tableKey = ref(0);

const user = computed(() => page.props.auth?.user || {});
const permissions = computed(() => page.props.auth?.permissions || []);
const storeCode = computed(() => String(user.value?.tiendas || '00000'));
const hasAssignedStore = computed(() => storeCode.value !== '' && storeCode.value !== '00000');
const canUseGlobalFilters = computed(() =>
    permissions.value.includes('ROOT')
    || permissions.value.includes('STIE')
    || permissions.value.includes('GERENTE'),
);
const showFilters = computed(() => canUseGlobalFilters.value && !hasAssignedStore.value);
const resolvedStoreLabel = computed(() =>
    data.value.filters?.storeName
        ? `${data.value.filters.storeName} (${data.value.filters.store || user.value?.tiendas || 'N/D'})`
        : user.value?.tiendas || 'N/D',
);

const filters = ref({
    country: String(user.value?.idPais || ''),
    store: '',
    date: today(),
});

const columns = [
    { data: 'referenceLink', title: 'Referencia' },
    { data: 'paidAtLabel', title: 'Fecha' },
    { data: 'storeLabel', title: 'Tienda' },
    { data: 'customer', title: 'Cliente' },
    { data: 'email', title: 'Email' },
    { data: 'origin', title: 'Origen' },
    { data: 'checkout', title: 'Checkout' },
    { data: 'paymentType', title: 'Pago' },
    { data: 'itemsLabel', title: 'Articulos', className: 'dt-right' },
    { data: 'amountLabel', title: 'Monto', className: 'dt-right' },
];

const options = {
    pageLength: 25,
    lengthMenu: [10, 25, 50, 100],
    order: [[1, 'desc']],
    autoWidth: false,
    language: {
        search: 'Buscar:',
        lengthMenu: 'Mostrar _MENU_ registros',
        info: 'Mostrando _START_ a _END_ de _TOTAL_ pedidos',
        infoEmpty: 'Sin pedidos',
        infoFiltered: '(filtrado de _MAX_ registros)',
        zeroRecords: 'No se encontraron pedidos pendientes',
        emptyTable: 'No hay pedidos pendientes disponibles',
        paginate: {
            first: 'Primero',
            last: 'Ultimo',
            next: 'Siguiente',
            previous: 'Anterior',
        },
    },
};

const rows = computed(() =>
    data.value.orders.map((order) => ({
        ...order,
        referenceLink: orderLink(order),
        paidAtLabel: formatDateTime(order.paidAt),
        storeLabel: order.destination?.replace(/^Tienda:\s*/i, '') || 'N/D',
        itemsLabel: formatNumber(order.items),
        amountLabel: formatMoney(order.amount),
    })),
);

const summaryCards = computed(() => [
    { label: 'Pedidos', value: formatNumber(data.value.summary.orders) },
    { label: 'Articulos', value: formatNumber(data.value.summary.items) },
    { label: 'Monto', value: formatMoney(data.value.summary.amount) },
]);

async function fetchPendingOrders() {
    loading.value = true;
    error.value = '';

    try {
        const params = {
            country: activeCountry(),
            pending: true,
            store: activeStore(),
        };

        if (!hasAssignedStore.value) {
            params.startDate = filters.value.date;
            params.endDate = filters.value.date;
        }

        const response = await window.axios.get('/dashboard-api/sales/orders', { params });
        data.value = {
            ...emptyData(),
            ...(response.data.data || {}),
        };
        tableKey.value += 1;
    } catch (exception) {
        error.value = exception.response?.data?.message || 'No fue posible cargar los pedidos pendientes.';
        data.value = emptyData();
        tableKey.value += 1;
    } finally {
        loading.value = false;
    }
}

async function fetchStoreSummary() {
    if (!activeCountry()) {
        stores.value = [];
        return;
    }

    summaryError.value = '';

    try {
        const response = await window.axios.get('/dashboard-api/sales/kpi', {
            params: {
                country: activeCountry(),
                startDate: filters.value.date,
                endDate: filters.value.date,
            },
        });

        const payload = response.data.data || {};
        countries.value = payload.countries || [];
        stores.value = payload.pendingOrders?.rows || [];

        if (showFilters.value && filters.value.store) {
            const exists = stores.value.some((store) => String(store.storeId) === String(filters.value.store));

            if (!exists) {
                filters.value.store = '';
            }
        }
    } catch (exception) {
        summaryError.value = exception.response?.data?.message || 'No fue posible cargar las tiendas disponibles.';
        stores.value = [];
    }
}

async function loadPage() {
    await fetchStoreSummary();
    await fetchPendingOrders();
}

function submitFilters() {
    loadPage();
}

function activeCountry() {
    return showFilters.value ? filters.value.country : String(user.value?.idPais || '');
}

function activeStore() {
    return hasAssignedStore.value ? storeCode.value : filters.value.store || undefined;
}

function orderLink(order) {
    const url = `/pedidos/consulta?country=${encodeURIComponent(order.countryId)}&id=${encodeURIComponent(order.ref)}`;

    return `<a class="stj-link" href="${url}">${escapeHtml(order.ref)}</a>`;
}

function today() {
    return new Date().toISOString().slice(0, 10);
}

function emptyData() {
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

    return String(value).replace('T', ' ').slice(0, 16);
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

onMounted(async () => {
    if (!showFilters.value) {
        filters.value.country = String(user.value?.idPais || '');
    }

    await loadPage();
});
</script>

<template>
    <Head title="Pedidos / Pendientes" />

    <AdminLayout>
        <section class="mx-auto w-full max-w-7xl">
            <div class="app-surface rounded-lg border p-6">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="app-primary-text text-sm font-semibold uppercase">
                            Pedidos
                        </p>
                        <h1 class="app-text mt-3 text-3xl font-semibold">
                            Pendientes
                        </h1>
                        <p class="app-muted mt-2 max-w-3xl text-sm leading-6">
                            Listado de pedidos recibidos y aun no procesados.
                        </p>
                    </div>

                    <div v-if="!showFilters" class="grid gap-3 sm:grid-cols-2">
                        <div class="app-surface-soft rounded-md border px-4 py-3 text-sm">
                            <span class="app-muted">Pais:</span>
                            <span class="app-text ml-2 font-semibold">{{ user?.pais || 'N/D' }}</span>
                        </div>
                        <div class="app-surface-soft rounded-md border px-4 py-3 text-sm">
                            <span class="app-muted">Tienda:</span>
                            <span class="app-text ml-2 font-semibold">{{ resolvedStoreLabel }}</span>
                        </div>
                    </div>
                </div>

                <form
                    v-if="showFilters"
                    class="mt-6 grid gap-4 lg:grid-cols-[1.1fr_1.1fr_1fr_auto]"
                    @submit.prevent="submitFilters"
                >
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
                        <span class="app-muted">Tienda</span>
                        <select
                            v-model="filters.store"
                            class="app-surface app-text mt-2 h-11 w-full rounded-md border px-3 text-sm outline-none focus:ring-4"
                        >
                            <option value="">Todas</option>
                            <option v-for="store in stores" :key="store.storeCode" :value="String(store.storeId)">
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

            <div v-if="summaryError" class="mt-6 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-700">
                {{ summaryError }}
            </div>

            <div v-if="error" class="mt-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                {{ error }}
            </div>

            <div class="mt-6 grid gap-4 sm:grid-cols-3">
                <div
                    v-for="card in summaryCards"
                    :key="card.label"
                    class="app-surface rounded-lg border p-5"
                >
                    <p class="app-muted text-xs font-semibold uppercase">{{ card.label }}</p>
                    <p class="app-text mt-2 text-2xl font-semibold">{{ card.value }}</p>
                </div>
            </div>

            <div class="app-surface mt-6 rounded-lg border p-4">
                <div v-if="loading" class="app-muted px-4 py-8 text-center text-sm">
                    Cargando pedidos pendientes...
                </div>

                <DataTable
                    :key="tableKey"
                    v-else
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
.stj-link {
    color: var(--stj-primary);
    font-weight: 600;
    text-decoration: none;
}

.stj-link:hover {
    text-decoration: underline;
}
</style>
