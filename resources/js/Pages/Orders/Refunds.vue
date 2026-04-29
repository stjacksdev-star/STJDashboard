<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import DataTable from 'datatables.net-vue3';
import DataTablesCore from 'datatables.net-dt';
import { computed, onMounted, ref, watch } from 'vue';
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
const detailModal = ref({
    open: false,
    title: '',
    subtitle: '',
    raw: '',
    rejected: false,
});

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
const showStoreFilter = computed(() => canUseGlobalFilters.value && !hasAssignedStore.value);
const resolvedStoreLabel = computed(() =>
    data.value.filters?.storeName
        ? `${data.value.filters.storeName} (${data.value.filters.store || user.value?.tiendas || 'N/D'})`
        : user.value?.storeLabel || user.value?.tiendas || 'N/D',
);

const filters = ref({
    country: String(user.value?.idPais || ''),
    store: '',
    status: '',
    startDate: today(),
    endDate: today(),
});

const columns = [
    { data: 'referenceLink', title: 'Referencia' },
    { data: 'refundAtLabel', title: 'Fecha dev.' },
    { data: 'paidAtLabel', title: 'Fecha pago' },
    { data: 'refundLabel', title: 'Devolucion' },
    { data: 'status', title: 'Pedido' },
    { data: 'storeLabel', title: 'Tienda' },
    { data: 'customer', title: 'Cliente' },
    { data: 'email', title: 'Email' },
    { data: 'refundAmountLabel', title: 'Monto dev.', className: 'dt-right' },
    { data: 'actionsHtml', title: 'Acciones', orderable: false, searchable: false },
];

const options = {
    pageLength: 25,
    lengthMenu: [10, 25, 50, 100],
    order: [[1, 'desc']],
    autoWidth: false,
    language: {
        search: 'Buscar: ',
        lengthMenu: 'Mostrar _MENU_ registros',
        info: 'Mostrando _START_ a _END_ de _TOTAL_ devoluciones',
        infoEmpty: 'Sin devoluciones',
        infoFiltered: '(filtrado de _MAX_ registros)',
        zeroRecords: 'No se encontraron devoluciones',
        emptyTable: 'No hay devoluciones disponibles',
        paginate: {
            first: 'Primero',
            last: 'Ultimo',
            next: 'Siguiente',
            previous: 'Anterior',
        },
    },
};

const rows = computed(() =>
    data.value.refunds.map((refund) => ({
        ...refund,
        referenceLink: orderLink(refund),
        refundAtLabel: formatDateTime(refund.refundAt),
        paidAtLabel: formatDateTime(refund.paidAt),
        refundLabel: refund.refundLabel || 'N/D',
        storeLabel: refund.storeName || refund.storeCode || 'N/D',
        refundAmountLabel: formatMoney(refund.refundAmount),
        actionsHtml: actionsHtml(refund),
    })),
);

const summaryCards = computed(() => [
    { label: 'Devoluciones', value: formatNumber(data.value.summary.orders) },
    { label: 'Pendientes', value: formatNumber(data.value.summary.pending) },
    { label: 'Procesadas', value: formatNumber(data.value.summary.processed) },
    { label: 'Monto', value: formatMoney(data.value.summary.amount) },
]);

async function fetchCatalog() {
    if (!canUseGlobalFilters.value) {
        countries.value = [];
        stores.value = [];
        return;
    }

    summaryError.value = '';

    try {
        const response = await window.axios.get('/dashboard-api/sales/kpi', {
            params: {
                country: activeCountry(),
                startDate: filters.value.startDate,
                endDate: filters.value.endDate,
            },
        });
        const payload = response.data.data || {};

        countries.value = payload.countries || [];
        stores.value = payload.stores || [];

        if (showStoreFilter.value && filters.value.store) {
            const exists = stores.value.some((store) => String(store.storeId) === String(filters.value.store));

            if (!exists) {
                filters.value.store = '';
            }
        }
    } catch (exception) {
        summaryError.value = exception.response?.data?.message || 'No fue posible cargar paises y tiendas.';
        stores.value = [];
    }
}

async function fetchRefunds() {
    loading.value = true;
    error.value = '';

    try {
        const response = await window.axios.get('/dashboard-api/orders/refunds', {
            params: {
                country: activeCountry(),
                store: activeStore(),
                status: filters.value.status || undefined,
                startDate: filters.value.startDate,
                endDate: filters.value.endDate,
            },
        });

        data.value = {
            ...emptyData(),
            ...(response.data.data || {}),
        };
        tableKey.value += 1;
    } catch (exception) {
        error.value = exception.response?.data?.message || 'No fue posible cargar las devoluciones.';
        data.value = emptyData();
        tableKey.value += 1;
    } finally {
        loading.value = false;
    }
}

async function loadPage() {
    await fetchCatalog();
    await fetchRefunds();
}

function submitFilters() {
    loadPage();
}

function activeCountry() {
    return canUseGlobalFilters.value ? filters.value.country : String(user.value?.idPais || '');
}

function activeStore() {
    return hasAssignedStore.value ? storeCode.value : filters.value.store || undefined;
}

function handleTableClick(event) {
    const viewButton = event.target.closest('[data-refund-view]');
    const pdfButton = event.target.closest('[data-refund-pdf]');

    if (viewButton) {
        event.preventDefault();
        openDetail(Number(viewButton.getAttribute('data-refund-view')), false);
    }

    if (pdfButton) {
        event.preventDefault();
        handlePdf(Number(pdfButton.getAttribute('data-refund-pdf')));
    }
}

function openDetail(id, rejected) {
    const refund = data.value.refunds.find((item) => Number(item.id) === Number(id));

    if (!refund) {
        return;
    }

    detailModal.value = {
        open: true,
        title: rejected ? 'Devolucion rechazada' : 'Respuesta servicio',
        subtitle: `${refund.ref || 'N/D'} - ${refund.customer || 'N/D'}`,
        raw: refund.serviceRaw || 'No hay respuesta del servicio registrada.',
        rejected,
    };
}

function closeDetail() {
    detailModal.value.open = false;
}

function handlePdf(id) {
    const refund = data.value.refunds.find((item) => Number(item.id) === Number(id));

    if (!refund) {
        return;
    }

    if (refund.serviceApproved !== true) {
        openDetail(id, true);
        return;
    }

    printRefund(refund);
}

function printRefund(refund) {
    const html = `<!doctype html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>Comprobante devolucion ${escapeHtml(refund.ref)}</title>
            <style>
                body { font-family: Arial, sans-serif; color: #111827; margin: 32px; }
                .header { border-bottom: 2px solid #1d4ed8; margin-bottom: 24px; padding-bottom: 16px; }
                h1 { font-size: 24px; margin: 0; }
                .muted { color: #6b7280; font-size: 12px; text-transform: uppercase; }
                table { border-collapse: collapse; margin-top: 16px; width: 100%; }
                td, th { border: 1px solid #d1d5db; padding: 10px; text-align: left; vertical-align: top; }
                th { background: #f3f4f6; width: 32%; }
                pre { white-space: pre-wrap; word-break: break-word; }
            </style>
        </head>
        <body>
            <div class="header">
                <p class="muted">St. Jack's Admin</p>
                <h1>Comprobante de devolucion</h1>
            </div>
            <table>
                <tr><th>Referencia</th><td>${escapeHtml(refund.ref)}</td></tr>
                <tr><th>Cliente</th><td>${escapeHtml(refund.customer)}</td></tr>
                <tr><th>Email</th><td>${escapeHtml(refund.email)}</td></tr>
                <tr><th>Estado devolucion</th><td>${escapeHtml(refund.refundLabel)}</td></tr>
                <tr><th>Fecha devolucion</th><td>${escapeHtml(formatDateTime(refund.refundAt))}</td></tr>
                <tr><th>Monto devolucion</th><td>${escapeHtml(formatMoney(refund.refundAmount))}</td></tr>
                <tr><th>Tienda</th><td>${escapeHtml(refund.storeName || refund.storeCode || 'N/D')}</td></tr>
                <tr><th>Aprobado</th><td>SI</td></tr>
            </table>
            <h2>Respuesta servicio</h2>
            <pre>${escapeHtml(refund.serviceRaw || '')}</pre>
        </body>
        </html>`;
    const printWindow = window.open('', '_blank', 'width=900,height=700');

    if (!printWindow) {
        return;
    }

    printWindow.document.open();
    printWindow.document.write(html);
    printWindow.document.close();
    printWindow.focus();
    printWindow.print();
}

function actionsHtml(refund) {
    const view = `<button type="button" class="stj-link stj-table-action" data-refund-view="${escapeHtml(refund.id)}">Ver</button>`;
    const pdf = refund.refundStatus === 'SI'
        ? `<button type="button" class="stj-link stj-table-action" data-refund-pdf="${escapeHtml(refund.id)}">PDF</button>`
        : '';

    return `<div class="stj-action-list">${view}${pdf}</div>`;
}

function orderLink(refund) {
    if (!refund.ref) {
        return `<span>Pedido ${escapeHtml(refund.id)}</span>`;
    }

    const url = `/pedidos/consulta?country=${encodeURIComponent(refund.countryId)}&id=${encodeURIComponent(refund.ref)}`;

    return `<a class="stj-link" href="${url}">${escapeHtml(refund.ref)}</a>`;
}

function today() {
    return new Date().toISOString().slice(0, 10);
}

function emptyData() {
    return {
        filters: {},
        summary: {
            orders: 0,
            pending: 0,
            processed: 0,
            amount: 0,
        },
        refunds: [],
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
    if (!canUseGlobalFilters.value) {
        filters.value.country = String(user.value?.idPais || '');
    }

    await loadPage();
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
    <Head title="Pedidos / Devoluciones" />

    <AdminLayout>
        <section class="mx-auto w-full max-w-7xl">
            <div class="app-surface rounded-lg border p-6">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="app-primary-text text-sm font-semibold uppercase">Pedidos</p>
                        <h1 class="app-text mt-3 text-3xl font-semibold">Devoluciones</h1>
                        <p class="app-muted mt-2 max-w-3xl text-sm leading-6">
                            Pedidos con devolucion pendiente o procesada.
                        </p>
                    </div>

                    <div v-if="!canUseGlobalFilters || hasAssignedStore" class="grid gap-3 sm:grid-cols-2">
                        <div v-if="!canUseGlobalFilters" class="app-surface-soft rounded-md border px-4 py-3 text-sm">
                            <span class="app-muted">Pais:</span>
                            <span class="app-text ml-2 font-semibold">{{ user?.pais || 'N/D' }}</span>
                        </div>
                        <div v-if="hasAssignedStore" class="app-surface-soft rounded-md border px-4 py-3 text-sm">
                            <span class="app-muted">Tienda:</span>
                            <span class="app-text ml-2 font-semibold">{{ resolvedStoreLabel }}</span>
                        </div>
                    </div>
                </div>

                <form
                    class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-[1fr_1fr_1fr_1fr_1fr_auto]"
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
                        >
                            <option value="">Todas</option>
                            <option v-for="store in stores" :key="store.storeId" :value="String(store.storeId)">
                                {{ store.store }}
                            </option>
                        </select>
                    </label>

                    <label class="block text-sm font-semibold">
                        <span class="app-muted">Estado</span>
                        <select
                            v-model="filters.status"
                            class="app-surface app-text mt-2 h-11 w-full rounded-md border px-3 text-sm outline-none focus:ring-4"
                        >
                            <option value="">Todos</option>
                            <option value="NO">Devolucion pendiente</option>
                            <option value="SI">Devolucion procesada</option>
                        </select>
                    </label>

                    <label class="block text-sm font-semibold">
                        <span class="app-muted">Fecha inicial</span>
                        <input
                            v-model="filters.startDate"
                            type="date"
                            class="app-surface app-text mt-2 h-11 w-full rounded-md border px-3 text-sm outline-none focus:ring-4"
                            required
                        />
                    </label>

                    <label class="block text-sm font-semibold">
                        <span class="app-muted">Fecha final</span>
                        <input
                            v-model="filters.endDate"
                            type="date"
                            class="app-surface app-text mt-2 h-11 w-full rounded-md border px-3 text-sm outline-none focus:ring-4"
                            required
                        />
                    </label>

                    <button
                        type="submit"
                        class="app-primary inline-flex h-11 items-center justify-center gap-2 self-end rounded-md px-5 text-sm font-semibold shadow-sm disabled:opacity-60"
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

            <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div v-for="card in summaryCards" :key="card.label" class="app-surface rounded-lg border p-5">
                    <p class="app-muted text-xs font-semibold uppercase">{{ card.label }}</p>
                    <p class="app-text mt-2 text-2xl font-semibold">{{ card.value }}</p>
                </div>
            </div>

            <div class="app-surface mt-6 rounded-lg border p-4">
                <div v-if="loading" class="app-muted px-4 py-8 text-center text-sm">
                    Cargando devoluciones...
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
            v-if="detailModal.open"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4 py-6"
            @click.self="closeDetail"
        >
            <div class="app-surface max-h-[90vh] w-full max-w-4xl overflow-hidden rounded-lg border shadow-xl">
                <div class="flex items-start justify-between gap-4 border-b px-5 py-4">
                    <div>
                        <p class="app-primary-text text-xs font-semibold uppercase">Devoluciones</p>
                        <h2 class="app-text mt-1 text-xl font-semibold">{{ detailModal.title }}</h2>
                        <p class="app-muted mt-1 text-sm">{{ detailModal.subtitle }}</p>
                    </div>
                    <button
                        type="button"
                        class="app-surface-soft app-text rounded-md border px-3 py-2 text-sm font-semibold"
                        @click="closeDetail"
                    >
                        Cerrar
                    </button>
                </div>

                <div class="max-h-[calc(90vh-88px)] overflow-auto p-5">
                    <div v-if="detailModal.rejected" class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                        La devolucion fue rechazada por el servicio. Revise el detalle de la respuesta.
                    </div>
                    <pre class="app-surface-soft app-text overflow-auto rounded-md border p-4 text-xs">{{ detailModal.raw }}</pre>
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
</style>
