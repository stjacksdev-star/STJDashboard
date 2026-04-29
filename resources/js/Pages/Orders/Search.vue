<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import DataTable from 'datatables.net-vue3';
import DataTablesCore from 'datatables.net-dt';
import { computed, onMounted, ref, watch } from 'vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import { printOrderReceipt } from '../../Support/orderReceipt';

DataTable.use(DataTablesCore);

const page = usePage();
const loading = ref(false);
const catalogLoading = ref(false);
const error = ref('');
const catalogError = ref('');
const searched = ref(false);
const data = ref(emptyData());
const countries = ref([]);
const stores = ref([]);
const tableKey = ref(0);
const attemptsModal = ref({
    open: false,
    loading: false,
    error: '',
    order: null,
    attempts: [],
    summary: {
        attempts: 0,
    },
});

const user = computed(() => page.props.auth?.user || {});
const permissions = computed(() => page.props.auth?.permissions || []);
const storeCode = computed(() => String(user.value?.storeCode || user.value?.tiendas || ''));
const hasAssignedStore = computed(() => storeCode.value !== '' && storeCode.value !== '00000');
const canUseGlobalFilters = computed(() =>
    permissions.value.includes('ROOT')
    || permissions.value.includes('STIE')
    || permissions.value.includes('GERENTE')
    || permissions.value.includes('SUPERVISOR'),
);
const isStoreManager = computed(() => hasAssignedStore.value && !canUseGlobalFilters.value);
const resolvedStoreLabel = computed(() =>
    data.value.filters?.storeName
        ? `${data.value.filters.storeName} (${data.value.filters.store || storeCode.value || 'N/D'})`
        : user.value?.storeLabel || user.value?.tiendas || 'N/D',
);

const filters = ref({
    country: String(user.value?.idPais || ''),
    store: '',
    query: '',
});

const columns = [
    { data: 'referenceLink', title: 'Referencia' },
    { data: 'paidAtLabel', title: 'Fecha' },
    { data: 'status', title: 'Estado' },
    { data: 'paymentStatus', title: 'Pago' },
    { data: 'storeLabel', title: 'Tienda' },
    { data: 'customer', title: 'Cliente' },
    { data: 'email', title: 'Email' },
    { data: 'checkout', title: 'Checkout' },
    { data: 'addressLabel', title: 'Direccion' },
    { data: 'amountLabel', title: 'Monto', className: 'dt-right' },
    { data: 'actionsHtml', title: 'Acciones', orderable: false, searchable: false },
];

const options = computed(() => ({
    pageLength: 25,
    lengthMenu: [10, 25, 50, 100],
    order: [[1, 'desc']],
    autoWidth: false,
    language: {
        search: 'Filtrar tabla:',
        lengthMenu: 'Mostrar _MENU_ registros',
        info: 'Mostrando _START_ a _END_ de _TOTAL_ pedidos',
        infoEmpty: searched.value ? 'Sin pedidos' : 'Sin busqueda',
        infoFiltered: '(filtrado de _MAX_ registros)',
        zeroRecords: 'No se encontraron coincidencias',
        emptyTable: searched.value ? 'No se encontraron coincidencias' : 'No hay una busqueda activa',
        paginate: {
            first: 'Primero',
            last: 'Ultimo',
            next: 'Siguiente',
            previous: 'Anterior',
        },
    },
}));

const rows = computed(() =>
    data.value.orders.map((order) => ({
        ...order,
        referenceLink: orderLink(order),
        paidAtLabel: formatDateTime(order.paidAt || order.createdAt),
        status: order.status || 'N/D',
        paymentStatus: order.paymentStatus || 'N/D',
        storeLabel: order.destination?.replace(/^Tienda:\s*/i, '') || order.storeName || 'N/D',
        addressLabel: escapeHtml(order.address || order.destination || 'N/D'),
        amountLabel: formatMoney(order.amount),
        actionsHtml: actionsHtml(order),
    })),
);

const summaryCards = computed(() => [
    { label: 'Pedidos', value: formatNumber(data.value.summary.orders) },
    { label: 'Articulos', value: formatNumber(data.value.summary.items) },
    { label: 'Monto', value: formatMoney(data.value.summary.amount) },
]);

async function fetchCatalog() {
    if (!canUseGlobalFilters.value) {
        countries.value = [];
        stores.value = [];
        return;
    }

    catalogLoading.value = true;
    catalogError.value = '';

    try {
        const todayValue = today();
        const response = await window.axios.get('/dashboard-api/sales/kpi', {
            params: {
                country: filters.value.country,
                startDate: todayValue,
                endDate: todayValue,
            },
        });

        const payload = response.data.data || {};
        countries.value = payload.countries || [];
        stores.value = payload.stores || [];

        if (filters.value.store) {
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

async function searchOrders() {
    error.value = '';

    if (filters.value.query.trim().length < 2) {
        error.value = 'Escriba al menos 2 caracteres para buscar.';
        return;
    }

    loading.value = true;
    searched.value = true;

    try {
        const response = await window.axios.get('/dashboard-api/orders/search', {
            params: {
                country: activeCountry(),
                store: activeStore(),
                query: filters.value.query.trim(),
                limit: 100,
            },
        });

        data.value = {
            ...emptyData(),
            ...(response.data.data || {}),
        };
        tableKey.value += 1;
    } catch (exception) {
        error.value = exception.response?.data?.message || 'No fue posible buscar pedidos.';
        data.value = emptyData();
        tableKey.value += 1;
    } finally {
        loading.value = false;
    }
}

async function openPaymentAttempts(orderId) {
    const order = data.value.orders.find((item) => Number(item.id) === Number(orderId));

    attemptsModal.value = {
        open: true,
        loading: true,
        error: '',
        order: order || null,
        attempts: [],
        summary: {
            attempts: 0,
        },
    };

    try {
        const response = await window.axios.get('/dashboard-api/orders/payment-attempts', {
            params: {
                country: order?.countryId || activeCountry(),
                order: orderId,
                store: activeStore(),
            },
        });
        const payload = response.data.data || {};

        attemptsModal.value = {
            open: true,
            loading: false,
            error: '',
            order: payload.order || order || null,
            attempts: payload.attempts || [],
            summary: payload.summary || { attempts: 0 },
        };
    } catch (exception) {
        attemptsModal.value = {
            ...attemptsModal.value,
            loading: false,
            error: exception.response?.data?.message || 'No fue posible cargar los intentos de pago.',
        };
    }
}

async function openOrderPdf(orderId) {
    const order = data.value.orders.find((item) => Number(item.id) === Number(orderId));

    if (!order?.ref) {
        return;
    }

    await printOrderReceipt(order.countryId, order.ref);
}

function closePaymentAttempts() {
    attemptsModal.value.open = false;
}

function handleTableClick(event) {
    const button = event.target.closest('[data-payment-attempts]');
    const pdfButton = event.target.closest('[data-order-pdf]');

    if (button) {
        event.preventDefault();
        openPaymentAttempts(button.getAttribute('data-payment-attempts'));
    }

    if (pdfButton) {
        event.preventDefault();
        openOrderPdf(pdfButton.getAttribute('data-order-pdf'));
    }
}

function activeCountry() {
    return canUseGlobalFilters.value ? filters.value.country : String(user.value?.idPais || '');
}

function activeStore() {
    return isStoreManager.value ? storeCode.value : filters.value.store || undefined;
}

function orderLink(order) {
    const reference = order.ref || `Pedido ${order.id}`;

    if (!order.ref || order.paymentStatus !== 'APROBADA') {
        return `<span>${escapeHtml(reference)}</span>`;
    }

    const url = `/pedidos/consulta?country=${encodeURIComponent(order.countryId)}&id=${encodeURIComponent(order.ref)}`;

    return `<a class="stj-link" href="${url}">${escapeHtml(order.ref)}</a>`;
}

function actionsHtml(order) {
    const actions = [];

    if (order.ref && order.paymentStatus === 'APROBADA') {
        actions.push(`<button type="button" class="stj-link stj-table-action" data-order-pdf="${escapeHtml(order.id)}">PDF</button>`);
    }

    if (String(order.paymentType || '').toUpperCase() !== 'TARJETA') {
        return `<div class="stj-action-list">${actions.join('')}</div>`;
    }

    actions.push(`<button type="button" class="stj-link stj-table-action" data-payment-attempts="${escapeHtml(order.id)}">Ver intentos</button>`);

    return `<div class="stj-action-list">${actions.join('')}</div>`;
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

watch(
    () => filters.value.country,
    async () => {
        filters.value.store = '';
        await fetchCatalog();
    },
);

onMounted(async () => {
    if (!canUseGlobalFilters.value) {
        filters.value.country = String(user.value?.idPais || '');
    }

    await fetchCatalog();
});
</script>

<template>
    <Head title="Pedidos / Busqueda" />

    <AdminLayout>
        <section class="mx-auto w-full max-w-7xl">
            <div class="app-surface rounded-lg border p-6">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="app-primary-text text-sm font-semibold uppercase">
                            Pedidos
                        </p>
                        <h1 class="app-text mt-3 text-3xl font-semibold">
                            Busqueda general
                        </h1>
                        <p class="app-muted mt-2 max-w-3xl text-sm leading-6">
                            Busque coincidencias por cliente, correo, identificacion, direccion, telefono, tarjeta, sesion o numero de pedido.
                        </p>
                    </div>

                    <div v-if="isStoreManager" class="grid gap-3 sm:grid-cols-2">
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
                    class="mt-6 grid gap-4 lg:grid-cols-[1fr_1fr_2fr_auto]"
                    @submit.prevent="searchOrders"
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

                    <label v-if="canUseGlobalFilters" class="block text-sm font-semibold">
                        <span class="app-muted">Tienda</span>
                        <select
                            v-model="filters.store"
                            class="app-surface app-text mt-2 h-11 w-full rounded-md border px-3 text-sm outline-none focus:ring-4"
                            :disabled="catalogLoading"
                        >
                            <option value="">Todas</option>
                            <option v-for="store in stores" :key="store.storeId" :value="String(store.storeId)">
                                {{ store.store }}
                            </option>
                        </select>
                    </label>

                    <label class="block text-sm font-semibold">
                        <span class="app-muted">Buscar</span>
                        <input
                            v-model="filters.query"
                            type="search"
                            class="app-surface app-text mt-2 h-11 w-full rounded-md border px-3 text-sm outline-none focus:ring-4"
                            placeholder="Nombre, correo, direccion, referencia..."
                            required
                            minlength="2"
                            maxlength="120"
                        />
                    </label>

                    <button
                        type="submit"
                        class="app-primary inline-flex h-11 items-center justify-center gap-2 self-end rounded-md px-5 text-sm font-semibold shadow-sm disabled:opacity-60"
                        :disabled="loading || catalogLoading"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 21l-4.3-4.3M11 18a7 7 0 1 1 0-14 7 7 0 0 1 0 14z" stroke-linecap="round" />
                        </svg>
                        {{ loading ? 'Buscando...' : 'Buscar' }}
                    </button>
                </form>
            </div>

            <div v-if="catalogError" class="mt-6 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-700">
                {{ catalogError }}
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
                    Buscando pedidos...
                </div>

                <div v-else @click="handleTableClick">
                    <DataTable
                        :key="tableKey"
                        :data="rows"
                        :columns="columns"
                        :options="options"
                        class="display stj-data-table w-full"
                    />
                </div>
            </div>
        </section>

        <div
            v-if="attemptsModal.open"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4 py-6"
            @click.self="closePaymentAttempts"
        >
            <div class="app-surface max-h-[90vh] w-full max-w-5xl overflow-hidden rounded-lg border shadow-xl">
                <div class="flex items-start justify-between gap-4 border-b px-5 py-4">
                    <div>
                        <p class="app-primary-text text-xs font-semibold uppercase">Intentos de pago</p>
                        <h2 class="app-text mt-1 text-xl font-semibold">
                            Pedido {{ attemptsModal.order?.id || 'N/D' }}
                        </h2>
                    </div>
                    <button
                        type="button"
                        class="app-surface-soft app-text rounded-md border px-3 py-2 text-sm font-semibold"
                        @click="closePaymentAttempts"
                    >
                        Cerrar
                    </button>
                </div>

                <div class="max-h-[calc(90vh-88px)] overflow-auto p-5">
                    <div v-if="attemptsModal.loading" class="app-muted py-8 text-center text-sm">
                        Cargando intentos de pago...
                    </div>

                    <div v-else>
                        <div v-if="attemptsModal.error" class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                            {{ attemptsModal.error }}
                        </div>

                        <div v-if="attemptsModal.order" class="mb-5 grid gap-3 md:grid-cols-3">
                            <div>
                                <p class="app-muted text-xs font-semibold uppercase">Cliente</p>
                                <p class="app-text mt-1 text-sm font-semibold">{{ attemptsModal.order.customer || 'N/D' }}</p>
                            </div>
                            <div>
                                <p class="app-muted text-xs font-semibold uppercase">Correo</p>
                                <p class="app-text mt-1 text-sm font-semibold">{{ attemptsModal.order.email || 'N/D' }}</p>
                            </div>
                            <div>
                                <p class="app-muted text-xs font-semibold uppercase">{{ attemptsModal.order.identificationType || 'Identificacion' }}</p>
                                <p class="app-text mt-1 text-sm font-semibold">{{ attemptsModal.order.identification || 'N/D' }}</p>
                            </div>
                        </div>

                        <p class="app-text mb-4 text-sm font-semibold">
                            Intentos: {{ attemptsModal.summary?.attempts || attemptsModal.attempts.length }}
                        </p>

                        <div v-if="attemptsModal.attempts.length === 0" class="app-muted rounded-md border px-4 py-6 text-center text-sm">
                            No hay intentos de pago registrados.
                        </div>

                        <div v-else class="space-y-4">
                            <div
                                v-for="attempt in attemptsModal.attempts"
                                :key="attempt.id"
                                class="rounded-lg border"
                            >
                                <div class="app-surface-soft flex items-center justify-between gap-3 border-b px-4 py-3">
                                    <p class="app-text text-sm font-semibold">Intento {{ attempt.number }}</p>
                                    <p class="app-muted text-xs">{{ formatDateTime(attempt.date) }}</p>
                                </div>

                                <div class="grid gap-0 text-sm md:grid-cols-2">
                                    <div class="attempt-row"><span>REF</span><strong>{{ attempt.reference || 'N/D' }}</strong></div>
                                    <div class="attempt-row"><span>Monto</span><strong>{{ formatMoney(attempt.amount) }}</strong></div>
                                    <div class="attempt-row"><span>Tarjeta</span><strong>{{ attempt.card || 'N/D' }}</strong></div>
                                    <div class="attempt-row"><span>Emisor</span><strong>{{ attempt.issuer || 'N/D' }}</strong></div>
                                    <div class="attempt-row"><span>Estado</span><strong>{{ attempt.status || 'N/D' }}</strong></div>
                                    <div class="attempt-row"><span>Autorizacion</span><strong>{{ attempt.authorization || 'N/D' }}</strong></div>
                                    <div class="attempt-row"><span>Codigo</span><strong>{{ attempt.code || 'N/D' }}</strong></div>
                                    <div class="attempt-row"><span>Mensaje</span><strong>{{ attempt.message || 'N/D' }}</strong></div>
                                </div>

                                <div class="border-t px-4 py-3">
                                    <p class="app-muted text-xs font-semibold uppercase">Respuesta banco</p>
                                    <pre class="app-surface-soft app-text mt-2 max-h-72 overflow-auto rounded-md border p-3 text-xs">{{ attempt.bankResponse || 'N/D' }}</pre>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
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

.stj-table-action {
    background: transparent;
    border: 0;
    cursor: pointer;
    padding: 0;
}

.stj-action-list {
    display: flex;
    gap: 10px;
    white-space: nowrap;
}

.attempt-row {
    align-items: center;
    border-bottom: 1px solid var(--stj-border);
    display: grid;
    gap: 12px;
    grid-template-columns: 38% 1fr;
    padding: 10px 16px;
}

.attempt-row span {
    color: var(--stj-muted);
    font-weight: 600;
}

.attempt-row strong {
    color: var(--stj-text);
    font-weight: 600;
    overflow-wrap: anywhere;
}
</style>
