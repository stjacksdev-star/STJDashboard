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
const data = ref(emptyData());
const tableKey = ref(0);

const user = computed(() => page.props.auth?.user || {});
const permissions = computed(() => page.props.auth?.permissions || []);
const storeCode = computed(() => String(user.value?.storeCode || user.value?.tiendas || '00000'));
const hasAssignedStore = computed(() => storeCode.value !== '' && storeCode.value !== '00000');
const canUseGlobalFilters = computed(() =>
    permissions.value.includes('ROOT')
    || permissions.value.includes('INDICADORES_GENERICOS'),
);
const showStoreFilter = computed(() => canUseGlobalFilters.value && filters.value.type === 'TIENDA');
const resolvedStoreLabel = computed(() =>
    data.value.filters?.storeName
        ? `${data.value.filters.storeName} (${data.value.filters.store || storeCode.value || 'N/D'})`
        : user.value?.storeLabel || user.value?.tiendas || 'N/D',
);

const filters = ref({
    country: String(user.value?.idPais || ''),
    store: '',
    type: canUseGlobalFilters.value ? 'DOMICILIO' : 'TIENDA',
    startDate: today(),
    endDate: today(),
});

const columns = [
    { data: 'sku', title: 'SKU' },
    { data: 'product', title: 'Producto' },
    { data: 'size', title: 'Talla', className: 'dt-center' },
    { data: 'quantityLabel', title: 'Cantidad', className: 'dt-right' },
    { data: 'imageHtml', title: 'Imagen', orderable: false, searchable: false },
];

const options = {
    pageLength: 25,
    lengthMenu: [10, 25, 50, 100],
    order: [[0, 'asc']],
    autoWidth: false,
    language: {
        search: 'Buscar:',
        lengthMenu: 'Mostrar _MENU_ registros',
        info: 'Mostrando _START_ a _END_ de _TOTAL_ articulos',
        infoEmpty: 'Sin articulos',
        infoFiltered: '(filtrado de _MAX_ registros)',
        zeroRecords: 'No se encontraron articulos pendientes',
        emptyTable: 'No hay articulos pendientes disponibles',
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
        quantityLabel: formatNumber(row.quantity),
        imageHtml: imageHtml(row),
    })),
);

const summaryCards = computed(() => [
    { label: 'Registros', value: formatNumber(data.value.summary.rows) },
    { label: 'Unidades', value: formatNumber(data.value.summary.quantity) },
]);

async function fetchCatalog() {
    catalogLoading.value = true;
    catalogError.value = '';

    if (!canUseGlobalFilters.value) {
        countries.value = [];
        stores.value = [];
        catalogLoading.value = false;
        return;
    }

    try {
        const response = await window.axios.get('/dashboard-api/reports/store/catalog', {
            params: {
                country: activeCountry(),
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

async function fetchReport() {
    loading.value = true;
    error.value = '';

    try {
        const response = await window.axios.get('/dashboard-api/reports/store/pending-items', {
            params: {
                country: activeCountry(),
                store: activeStore(),
                type: activeType(),
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
        error.value = exception.response?.data?.message || 'No fue posible cargar los articulos pendientes.';
        data.value = emptyData();
        tableKey.value += 1;
    } finally {
        loading.value = false;
    }
}

async function submitFilters() {
    await fetchReport();
}

function exportCsv() {
    const header = ['Producto', 'SKU', 'Talla', 'Cantidad', 'Imagen'];
    const lines = data.value.rows.map((row) => [
        row.product,
        row.sku,
        row.size,
        row.quantity,
        row.imageUrl,
    ]);
    const csv = [header, ...lines]
        .map((line) => line.map(csvCell).join(','))
        .join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `articulos-pendientes-${filters.value.startDate}-${filters.value.endDate}.csv`;
    link.click();
    URL.revokeObjectURL(link.href);
}

function activeCountry() {
    return canUseGlobalFilters.value ? filters.value.country : String(user.value?.idPais || '');
}

function activeStore() {
    return canUseGlobalFilters.value ? filters.value.store || '' : storeCode.value;
}

function activeType() {
    return canUseGlobalFilters.value ? filters.value.type : 'TIENDA';
}

function imageHtml(row) {
    if (!row.imageUrl) {
        return '<span class="app-muted">N/D</span>';
    }

    return `<img class="stj-pending-thumb" src="${escapeHtml(row.imageUrl)}" alt="${escapeHtml(row.sku)}" loading="lazy">`;
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
            rows: 0,
            quantity: 0,
        },
        rows: [],
    };
}

function formatNumber(value) {
    return Number(value || 0).toLocaleString('en-US');
}

function csvCell(value) {
    return `"${String(value ?? '').replace(/"/g, '""')}"`;
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
        filters.value.type = 'TIENDA';
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

watch(
    () => filters.value.type,
    () => {
        if (filters.value.type === 'DOMICILIO') {
            filters.value.store = '';
        }
    },
);
</script>

<template>
    <Head title="Reportes / Articulos pendientes" />

    <AdminLayout>
        <section class="mx-auto w-full max-w-7xl">
            <div class="app-surface rounded-lg border p-6">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="app-primary-text text-sm font-semibold uppercase">
                            Reportes / Tiendas
                        </p>
                        <h1 class="app-text mt-3 text-3xl font-semibold">
                            Articulos pendientes
                        </h1>
                        <p class="app-muted mt-2 max-w-3xl text-sm leading-6">
                            Articulos vendidos pendientes de entregar, agrupados por SKU y talla.
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
                    class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-[1fr_1fr_1fr_1fr_1fr_auto_auto]"
                    @submit.prevent="submitFilters"
                >
                    <label v-if="canUseGlobalFilters" class="block text-sm font-semibold">
                        <span class="app-muted">Tipo</span>
                        <select
                            v-model="filters.type"
                            class="app-surface app-text mt-2 h-11 w-full rounded-md border px-3 text-sm outline-none focus:ring-4"
                            required
                        >
                            <option value="DOMICILIO">Domicilio</option>
                            <option value="TIENDA">Tienda</option>
                        </select>
                    </label>

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
                        :disabled="loading || catalogLoading || (showStoreFilter && !filters.store)"
                    >
                        {{ loading ? 'Buscando...' : 'Generar' }}
                    </button>

                    <button
                        type="button"
                        class="app-surface app-text inline-flex h-11 items-center justify-center gap-2 self-end rounded-md border px-5 text-sm font-semibold disabled:opacity-60"
                        :disabled="loading || !data.rows.length"
                        @click="exportCsv"
                    >
                        Exportar
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
                    Cargando articulos pendientes...
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
.stj-pending-thumb {
    display: block;
    height: 96px;
    object-fit: contain;
    width: 96px;
}
</style>
