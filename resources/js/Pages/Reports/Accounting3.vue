<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';

const loading = ref(false);
const exporting = ref(false);
const catalogLoading = ref(true);
const error = ref('');
const catalogError = ref('');
const countries = ref([]);
const stores = ref([]);
const data = ref(emptyData());

const filters = ref({
    paymentType: 'TODO',
    status: 'TODO',
    country: '',
    store: 'TODO',
    startDate: today(),
    endDate: today(),
});

const summaryCards = computed(() => [
    { label: 'Total de registros', value: formatNumber(data.value.summary.orders) },
]);

async function fetchCatalog() {
    catalogLoading.value = true;
    catalogError.value = '';

    try {
        const response = await window.axios.get('/dashboard-api/reports/store/catalog', {
            params: {
                country: filters.value.country,
            },
        });
        const payload = response.data.data || {};
        countries.value = payload.countries || [];
        stores.value = payload.stores || [];

        if (!filters.value.country && countries.value.length) {
            filters.value.country = String(countries.value[0].id);
            await fetchCatalog();
            return;
        }

        if (filters.value.store !== 'TODO') {
            const exists = stores.value.some((store) => String(store.storeCode) === String(filters.value.store));

            if (!exists) {
                filters.value.store = 'TODO';
            }
        }
    } catch (exception) {
        catalogError.value = exception.response?.data?.message || 'No fue posible cargar paises y tiendas.';
        stores.value = [];
    } finally {
        catalogLoading.value = false;
    }
}

async function fetchCount() {
    loading.value = true;
    error.value = '';

    try {
        const response = await window.axios.get('/dashboard-api/reports/accounting/3/count', {
            params: reportParams(),
        });

        data.value = {
            ...emptyData(),
            ...(response.data.data || {}),
        };
    } catch (exception) {
        error.value = exception.response?.data?.message || 'No fue posible obtener el total de registros.';
        data.value = emptyData();
    } finally {
        loading.value = false;
    }
}

function submitFilters() {
    fetchCount();
}

function exportExcel() {
    exporting.value = true;
    const params = new URLSearchParams(reportParams());

    window.location.href = `/dashboard-api/reports/accounting/3/export?${params.toString()}`;
    window.setTimeout(() => {
        exporting.value = false;
    }, 1500);
}

function clearFilters() {
    filters.value.paymentType = 'TODO';
    filters.value.status = 'TODO';
    filters.value.store = 'TODO';
    filters.value.startDate = today();
    filters.value.endDate = today();
    data.value = emptyData();
    error.value = '';
}

function reportParams() {
    return {
        country: filters.value.country,
        store: filters.value.store,
        paymentType: filters.value.paymentType,
        status: filters.value.status,
        startDate: filters.value.startDate,
        endDate: filters.value.endDate,
    };
}

function today() {
    return new Date().toISOString().slice(0, 10);
}

function emptyData() {
    return {
        filters: {},
        summary: {
            orders: 0,
        },
    };
}

function formatNumber(value) {
    return Number(value || 0).toLocaleString('en-US');
}

onMounted(async () => {
    await fetchCatalog();
});

watch(
    () => filters.value.country,
    async () => {
        filters.value.store = 'TODO';
        await fetchCatalog();
    },
);
</script>

<template>
    <Head title="Reportes / Contabilidad 3" />

    <AdminLayout>
        <section class="mx-auto w-full max-w-7xl">
            <div class="app-surface rounded-lg border p-6">
                <div>
                    <p class="app-primary-text text-sm font-semibold uppercase">
                        Reportes / Contabilidad
                    </p>
                    <h1 class="app-text mt-3 text-3xl font-semibold">
                        Venta general 3
                    </h1>
                    <p class="app-muted mt-2 max-w-3xl text-sm leading-6">
                        Exportacion de venta eCommerce para contabilidad con el formato historico.
                    </p>
                </div>

                <form class="mt-6 grid gap-5" @submit.prevent="submitFilters">
                    <fieldset class="flex flex-wrap gap-4">
                        <legend class="app-muted mb-2 text-sm font-semibold">Tipo de pago</legend>
                        <label v-for="option in ['TARJETA', 'EFECTIVO', 'TODO']" :key="option" class="app-text inline-flex items-center gap-2 text-sm">
                            <input v-model="filters.paymentType" type="radio" :value="option" />
                            {{ option === 'TODO' ? 'Todos' : option.charAt(0) + option.slice(1).toLowerCase() }}
                        </label>
                    </fieldset>

                    <fieldset class="flex flex-wrap gap-4">
                        <legend class="app-muted mb-2 text-sm font-semibold">Estado</legend>
                        <label v-for="option in ['FACTURADO', 'PENDIENTE', 'TODO']" :key="option" class="app-text inline-flex items-center gap-2 text-sm">
                            <input v-model="filters.status" type="radio" :value="option" />
                            {{ option === 'TODO' ? 'Todos' : option.charAt(0) + option.slice(1).toLowerCase() }}
                        </label>
                    </fieldset>

                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="block text-sm font-semibold">
                            <span class="app-muted">Pais</span>
                            <select
                                v-model="filters.country"
                                class="app-surface app-text mt-2 h-11 w-full rounded-md border px-3 text-sm outline-none focus:ring-4"
                                required
                            >
                                <option value="">-- Seleccione --</option>
                                <option v-for="country in countries" :key="country.id" :value="String(country.id)">
                                    {{ country.name }}
                                </option>
                            </select>
                        </label>

                        <label class="block text-sm font-semibold">
                            <span class="app-muted">Tienda</span>
                            <select
                                v-model="filters.store"
                                class="app-surface app-text mt-2 h-11 w-full rounded-md border px-3 text-sm outline-none focus:ring-4"
                                required
                                :disabled="catalogLoading || !filters.country"
                            >
                                <option value="TODO">TODO</option>
                                <option v-for="store in stores" :key="store.storeCode" :value="String(store.storeCode)">
                                    {{ store.store }}
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
                    </div>

                    <div class="flex flex-wrap justify-end gap-3">
                        <button
                            type="submit"
                            class="app-primary inline-flex h-11 items-center justify-center rounded-md px-5 text-sm font-semibold shadow-sm disabled:opacity-60"
                            :disabled="loading || catalogLoading"
                        >
                            {{ loading ? 'Generando...' : 'Generar' }}
                        </button>
                        <button
                            type="button"
                            class="app-surface app-text inline-flex h-11 items-center justify-center rounded-md border px-5 text-sm font-semibold"
                            @click="clearFilters"
                        >
                            Limpiar
                        </button>
                    </div>
                </form>
            </div>

            <div v-if="catalogError" class="mt-6 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-700">
                {{ catalogError }}
            </div>

            <div v-if="error" class="mt-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                {{ error }}
            </div>

            <div class="app-surface mt-6 rounded-lg border p-5">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div v-for="card in summaryCards" :key="card.label">
                        <p class="app-muted text-xs font-semibold uppercase">{{ card.label }}</p>
                        <p class="app-text mt-2 text-2xl font-semibold">{{ card.value }}</p>
                    </div>

                    <button
                        type="button"
                        class="app-primary inline-flex h-11 items-center justify-center rounded-md px-5 text-sm font-semibold shadow-sm disabled:opacity-60"
                        :disabled="exporting || loading || !data.summary.orders"
                        @click="exportExcel"
                    >
                        {{ exporting ? 'Exportando...' : 'Exportar resultado' }}
                    </button>
                </div>
            </div>
        </section>
    </AdminLayout>
</template>
