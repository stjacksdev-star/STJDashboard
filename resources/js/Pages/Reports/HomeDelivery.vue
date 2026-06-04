<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import DataTable from 'datatables.net-vue3';
import DataTablesCore from 'datatables.net-dt';
import { computed, onMounted, ref, watch } from 'vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';

DataTable.use(DataTablesCore);

const page = usePage();
const loading = ref(false);
const exporting = ref(false);
const catalogLoading = ref(true);
const error = ref('');
const catalogError = ref('');
const countries = ref([]);
const data = ref(emptyData());
const tableKey = ref(0);

const user = computed(() => page.props.auth?.user || {});
const permissions = computed(() => page.props.auth?.permissions || []);
const canUseGlobalFilters = computed(() =>
    permissions.value.includes('ROOT')
    || permissions.value.includes('INDICADORES_GENERICOS'),
);

const filters = ref({
    country: String(user.value?.idPais || ''),
    startDate: today(),
    endDate: today(),
});

const columns = [
    { data: 'stjLink', title: 'STJ' },
    { data: 'orderDateLabel', title: 'Fecha Pedido' },
    { data: 'processedAtLabel', title: 'Fecha facturado' },
    { data: 'platform', title: 'Plataforma' },
    { data: 'orderStatus', title: 'Estado Pedido' },
    { data: 'checkoutType', title: 'Tipo checkout' },
    { data: 'store', title: 'Tienda' },
    { data: 'names', title: 'Nombres' },
    { data: 'lastNames', title: 'Apellidos' },
    { data: 'email', title: 'Correo' },
    { data: 'dui', title: 'Dui' },
    { data: 'address', title: 'Direccion' },
    { data: 'phone', title: 'Telefono' },
    { data: 'whatsapp', title: 'Whatsapp' },
    { data: 'paymentType', title: 'Tipo pago' },
    { data: 'card', title: 'Tarjeta' },
    { data: 'paymentStatus', title: 'Pago estado' },
];

const options = {
    pageLength: 25,
    lengthMenu: [10, 25, 50, 100],
    order: [[1, 'asc']],
    autoWidth: false,
    scrollX: true,
    language: {
        search: 'Buscar:',
        lengthMenu: 'Mostrar _MENU_ registros',
        info: 'Mostrando _START_ a _END_ de _TOTAL_ pedidos',
        infoEmpty: 'Sin pedidos',
        infoFiltered: '(filtrado de _MAX_ registros)',
        zeroRecords: 'No se encontraron pedidos de domicilio',
        emptyTable: 'No hay pedidos de domicilio disponibles',
        paginate: {
            first: 'Primero',
            last: 'Ultimo',
            next: 'Siguiente',
            previous: 'Anterior',
        },
    },
};

const rows = computed(() =>
    data.value.rows.map((row) => ({
        ...row,
        stjLink: orderLink(row),
        orderDateLabel: formatDateTime(row.orderDate),
        processedAtLabel: formatDateTime(row.processedAt),
    })),
);

const summaryCards = computed(() => [
    { label: 'Pedidos', value: formatNumber(data.value.summary.orders) },
    { label: 'Tienda domicilio', value: data.value.filters.store || 'N/D' },
]);

async function fetchCatalog() {
    catalogLoading.value = true;
    catalogError.value = '';

    if (!canUseGlobalFilters.value) {
        countries.value = [];
        catalogLoading.value = false;
        return;
    }

    try {
        const response = await window.axios.get('/dashboard-api/reports/store/catalog', {
            params: {
                country: filters.value.country,
            },
        });
        const payload = response.data.data || {};
        countries.value = payload.countries || [];

        if (!filters.value.country && countries.value.length) {
            filters.value.country = String(countries.value[0].id);
        }
    } catch (exception) {
        catalogError.value = exception.response?.data?.message || 'No fue posible cargar paises.';
    } finally {
        catalogLoading.value = false;
    }
}

async function fetchReport() {
    loading.value = true;
    error.value = '';

    try {
        const response = await window.axios.get('/dashboard-api/reports/store/home-delivery', {
            params: reportParams(),
        });

        data.value = {
            ...emptyData(),
            ...(response.data.data || {}),
        };
        tableKey.value += 1;
    } catch (exception) {
        error.value = exception.response?.data?.message || 'No fue posible cargar el reporte de domicilio.';
        data.value = emptyData();
        tableKey.value += 1;
    } finally {
        loading.value = false;
    }
}

function submitFilters() {
    fetchReport();
}

function exportExcel() {
    exporting.value = true;
    const params = new URLSearchParams(reportParams());

    window.location.href = `/dashboard-api/reports/store/home-delivery/export?${params.toString()}`;
    window.setTimeout(() => {
        exporting.value = false;
    }, 1500);
}

function reportParams() {
    return {
        country: activeCountry(),
        startDate: filters.value.startDate,
        endDate: filters.value.endDate,
    };
}

function activeCountry() {
    return canUseGlobalFilters.value ? filters.value.country : String(user.value?.idPais || '');
}

function orderLink(row) {
    if (!row.stj) {
        return '<span>N/D</span>';
    }

    const url = `/pedidos/consulta?country=${encodeURIComponent(activeCountry())}&id=${encodeURIComponent(row.stj)}`;

    return `<a class="stj-link" href="${url}">${escapeHtml(row.stj)}</a>`;
}

function today() {
    const date = new Date();
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

function emptyData() {
    return {
        filters: {},
        summary: {
            orders: 0,
        },
        rows: [],
    };
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

    await fetchCatalog();
    await fetchReport();
});

watch(
    () => filters.value.country,
    async () => {
        if (!canUseGlobalFilters.value) {
            return;
        }

        await fetchCatalog();
    },
);
</script>

<template>
    <Head title="Reportes / Domicilio" />

    <AdminLayout>
        <section class="mx-auto w-full max-w-7xl">
            <div class="app-surface rounded-lg border p-6">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="app-primary-text text-sm font-semibold uppercase">
                            Reportes / Tiendas
                        </p>
                        <h1 class="app-text mt-3 text-3xl font-semibold">
                            Domicilio
                        </h1>
                        <p class="app-muted mt-2 max-w-3xl text-sm leading-6">
                            Pedidos domicilio aprobados por pais y rango de fechas.
                        </p>
                    </div>

                    <div v-if="!canUseGlobalFilters" class="app-surface-soft rounded-md border px-4 py-3 text-sm">
                        <span class="app-muted">Pais:</span>
                        <span class="app-text ml-2 font-semibold">{{ user?.pais || 'N/D' }}</span>
                    </div>
                </div>

                <form
                    class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-[1fr_1fr_1fr_auto_auto]"
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

                    <label class="block text-sm font-semibold">
                        <span class="app-muted">Fecha inicio</span>
                        <input
                            v-model="filters.startDate"
                            type="date"
                            class="app-surface app-text mt-2 h-11 w-full rounded-md border px-3 text-sm outline-none focus:ring-4"
                            required
                        />
                    </label>

                    <label class="block text-sm font-semibold">
                        <span class="app-muted">Fecha fin</span>
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
                        :disabled="loading || catalogLoading || !activeCountry()"
                    >
                        {{ loading ? 'Buscando...' : 'Generar' }}
                    </button>

                    <button
                        type="button"
                        class="app-surface app-text inline-flex h-11 items-center justify-center gap-2 self-end rounded-md border px-5 text-sm font-semibold disabled:opacity-60"
                        :disabled="loading || exporting || !data.rows.length"
                        @click="exportExcel"
                    >
                        {{ exporting ? 'Exportando...' : 'Excel' }}
                    </button>
                </form>
            </div>

            <div v-if="catalogError" class="mt-6 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-700">
                {{ catalogError }}
            </div>

            <div v-if="error" class="mt-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                {{ error }}
            </div>

            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                <div v-for="card in summaryCards" :key="card.label" class="app-surface rounded-lg border p-5">
                    <p class="app-muted text-xs font-semibold uppercase">{{ card.label }}</p>
                    <p class="app-text mt-2 text-2xl font-semibold">{{ card.value }}</p>
                </div>
            </div>

            <div class="app-surface mt-6 rounded-lg border p-4">
                <div v-if="loading" class="app-muted px-4 py-8 text-center text-sm">
                    Cargando reporte de domicilio...
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
.stj-link {
    color: var(--stj-primary);
    font-weight: 600;
    text-decoration: none;
}

.stj-link:hover {
    text-decoration: underline;
}
</style>
