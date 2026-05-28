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
const storeCode = computed(() => String(user.value?.storeId || user.value?.storeCode || user.value?.tiendas || '00000'));
const canUseGlobalFilters = computed(() =>
    permissions.value.includes('ROOT')
    || permissions.value.includes('INDICADORES_GENERICOS'),
);
const showFilters = computed(() => canUseGlobalFilters.value);
const resolvedStoreLabel = computed(() =>
    report.value.filters?.storeName
        ? `${report.value.filters.storeName} (${report.value.filters.store || 'N/D'})`
        : user.value?.storeLabel || user.value?.tiendas || 'N/D',
);

const filters = ref({
    country: String(user.value?.idPais || ''),
    store: '',
});

const columns = [
    { data: 'registeredAtLabel', title: 'Fecha registro' },
    { data: 'code', title: 'ID' },
    { data: 'dui', title: 'DUI' },
    { data: 'name', title: 'Nombre' },
    { data: 'phone', title: 'Telefono' },
    { data: 'email', title: 'Correo' },
    { data: 'schedule', title: 'Horario' },
    { data: 'day', title: 'Dia' },
    { data: 'emailSentLabel', title: 'Correo enviado', width: '130px' },
];

const options = {
    pageLength: 25,
    lengthMenu: [10, 25, 50, 100],
    order: [[6, 'asc']],
    autoWidth: false,
    language: {
        search: 'Buscar:',
        lengthMenu: 'Mostrar _MENU_ registros',
        info: 'Mostrando _START_ a _END_ de _TOTAL_ citas',
        infoEmpty: 'Sin citas',
        infoFiltered: '(filtrado de _MAX_ registros)',
        zeroRecords: 'No se encontraron citas',
        emptyTable: 'No hay citas para los filtros seleccionados',
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
        registeredAtLabel: formatDateTime(row.registeredAt),
        emailSentLabel: row.emailSent ? '<span class="stj-pill stj-pill-on">SI</span>' : '<span class="stj-pill">NO</span>',
    })),
);

const summaryCards = computed(() => [
    { label: 'Citas', value: formatNumber(report.value.summary.appointments) },
    { label: 'Correos enviados', value: formatNumber(report.value.summary.emailsSent) },
    { label: 'Tienda', value: resolvedStoreLabel.value },
]);

async function fetchCatalog() {
    catalogLoading.value = true;
    catalogError.value = '';

    try {
        const response = await window.axios.get('/dashboard-api/appointments/catalog', {
            params: {
                country: activeCountry(),
            },
        });
        const payload = response.data.data || {};
        countries.value = payload.countries || [];
        stores.value = payload.stores || [];

        if (showFilters.value && filters.value.store) {
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

async function fetchAppointments() {
    loading.value = true;
    error.value = '';

    try {
        const response = await window.axios.get('/dashboard-api/appointments', {
            params: {
                country: activeCountry(),
                store: activeStore(),
            },
        });

        report.value = {
            ...emptyReport(),
            ...(response.data.data || {}),
        };
        tableKey.value += 1;
    } catch (exception) {
        error.value = exception.response?.data?.message || 'No fue posible cargar las citas.';
        report.value = emptyReport();
        tableKey.value += 1;
    } finally {
        loading.value = false;
    }
}

async function submitFilters() {
    await fetchAppointments();
}

function activeCountry() {
    return showFilters.value ? filters.value.country : String(user.value?.idPais || '');
}

function activeStore() {
    return showFilters.value ? filters.value.store || '' : storeCode.value;
}

function emptyReport() {
    return {
        filters: {},
        summary: {
            appointments: 0,
            emailsSent: 0,
        },
        rows: [],
    };
}

function formatDateTime(value) {
    if (!value) {
        return 'N/D';
    }

    return String(value).replace('T', ' ').slice(0, 19);
}

function formatNumber(value) {
    return Number(value || 0).toLocaleString('en-US');
}

onMounted(async () => {
    await fetchCatalog();

    if (!showFilters.value || filters.value.store) {
        await fetchAppointments();
    }
});

watch(() => filters.value.country, async () => {
    filters.value.store = '';
    report.value = emptyReport();
    tableKey.value += 1;
    await fetchCatalog();
});
</script>

<template>
    <AdminLayout>
        <Head title="Citas" />

        <div class="mx-auto max-w-7xl space-y-6">
            <section class="app-surface rounded-lg border p-5 shadow-sm" style="border-color: var(--stj-border);">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="app-primary-text text-xs font-semibold uppercase">St. Jack's</p>
                        <h1 class="app-text mt-2 text-2xl font-bold">Citas</h1>
                        <p v-if="!showFilters" class="app-muted mt-2 text-sm">
                            {{ resolvedStoreLabel }}
                        </p>
                    </div>

                    <form v-if="showFilters" class="grid gap-3 lg:grid-cols-[220px_280px_auto]" @submit.prevent="submitFilters">
                        <label class="block">
                            <span class="app-muted text-sm font-medium">Pais</span>
                            <select v-model="filters.country" required class="stj-input mt-2" :disabled="catalogLoading">
                                <option value="">Seleccione</option>
                                <option v-for="country in countries" :key="country.id" :value="String(country.id)">
                                    {{ country.name }}
                                </option>
                            </select>
                        </label>

                        <label class="block">
                            <span class="app-muted text-sm font-medium">Tienda</span>
                            <select v-model="filters.store" required class="stj-input mt-2" :disabled="catalogLoading || stores.length === 0">
                                <option value="">Seleccione</option>
                                <option v-for="store in stores" :key="store.storeId" :value="String(store.storeId)">
                                    {{ store.store }}
                                </option>
                            </select>
                        </label>

                        <button
                            type="submit"
                            class="mt-7 inline-flex h-11 items-center justify-center rounded-md bg-blue-600 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="loading || catalogLoading || !filters.store"
                        >
                            {{ loading ? 'Buscando...' : 'Buscar' }}
                        </button>
                    </form>
                </div>
            </section>

            <div v-if="catalogError" class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                {{ catalogError }}
            </div>

            <div v-if="error" class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                {{ error }}
            </div>

            <section class="grid gap-4 md:grid-cols-3">
                <div
                    v-for="card in summaryCards"
                    :key="card.label"
                    class="app-surface rounded-lg border p-4 shadow-sm"
                    style="border-color: var(--stj-border);"
                >
                    <p class="app-muted text-sm">{{ card.label }}</p>
                    <p class="app-text mt-2 text-xl font-bold">{{ card.value }}</p>
                </div>
            </section>

            <section class="app-surface overflow-hidden rounded-lg border shadow-sm" style="border-color: var(--stj-border);">
                <div class="app-border-soft border-b px-5 py-4">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <h2 class="app-text text-lg font-semibold">Listado</h2>
                        <p class="app-muted text-sm">{{ report.rows.length }} citas cargadas</p>
                    </div>
                </div>

                <div class="p-5">
                    <div v-if="loading || catalogLoading" class="app-muted rounded-md border p-4 text-sm" style="border-color: var(--stj-border);">
                        Cargando citas...
                    </div>

                    <div v-else class="overflow-x-auto">
                        <DataTable
                            :key="tableKey"
                            :columns="columns"
                            :data="rows"
                            :options="options"
                            class="display compact stripe w-full text-sm"
                        />
                    </div>
                </div>
            </section>
        </div>
    </AdminLayout>
</template>

<style>
.stj-input {
    background: var(--stj-surface);
    border: 1px solid var(--stj-border);
    border-radius: 0.375rem;
    color: var(--stj-text);
    min-height: 2.75rem;
    outline: none;
    padding: 0.55rem 0.75rem;
    width: 100%;
}

.stj-input:focus {
    box-shadow: 0 0 0 4px var(--stj-primary-soft);
}

.stj-pill {
    background: #e5e7eb;
    border-radius: 999px;
    color: #374151;
    display: inline-flex;
    font-size: 0.68rem;
    font-weight: 800;
    padding: 0.18rem 0.45rem;
}

.stj-pill-on {
    background: #dcfce7;
    color: #166534;
}
</style>
