<script setup>
import { Head } from '@inertiajs/vue3';
import { onMounted, ref, watch } from 'vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';

const loading = ref(false);
const catalogLoading = ref(true);
const error = ref('');
const catalogError = ref('');
const countries = ref([]);
const stores = ref([]);

const filters = ref({
    country: '',
    store: '0',
    startDate: today(),
    endDate: today(),
});

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

        if (filters.value.store !== '0') {
            const exists = stores.value.some((store) => String(store.storeCode) === String(filters.value.store));

            if (!exists) {
                filters.value.store = '0';
            }
        }
    } catch (exception) {
        catalogError.value = exception.response?.data?.message || 'No fue posible cargar paises y tiendas.';
        stores.value = [];
    } finally {
        catalogLoading.value = false;
    }
}

function generatePdf() {
    loading.value = true;
    error.value = '';

    if (!filters.value.country || !filters.value.startDate || !filters.value.endDate) {
        error.value = 'Complete pais y fechas para generar el reporte.';
        loading.value = false;
        return;
    }

    const params = new URLSearchParams({
        country: filters.value.country,
        store: filters.value.store,
        startDate: filters.value.startDate,
        endDate: filters.value.endDate,
    });

    window.location.href = `/dashboard-api/reports/accounting/sales-by-store/pdf?${params.toString()}`;
    window.setTimeout(() => {
        loading.value = false;
    }, 1500);
}

function clearFilters() {
    filters.value.store = '0';
    filters.value.startDate = today();
    filters.value.endDate = today();
    error.value = '';
}

function today() {
    const date = new Date();
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

onMounted(async () => {
    await fetchCatalog();
});

watch(
    () => filters.value.country,
    async () => {
        filters.value.store = '0';
        await fetchCatalog();
    },
);
</script>

<template>
    <Head title="Reportes / Contabilidad" />

    <AdminLayout>
        <section class="mx-auto w-full max-w-7xl">
            <div class="app-surface rounded-lg border p-6">
                <div>
                    <p class="app-primary-text text-sm font-semibold uppercase">
                        Reportes / Contabilidad
                    </p>
                    <h1 class="app-text mt-3 text-3xl font-semibold">
                        Venta general
                    </h1>
                    <p class="app-muted mt-2 max-w-3xl text-sm leading-6">
                        Reporte de venta en linea por tienda en formato PDF.
                    </p>
                </div>

                <form
                    class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-[1fr_1fr_1fr_1fr_auto_auto]"
                    @submit.prevent="generatePdf"
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
                            <option value="0">Todas</option>
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

                    <button
                        type="submit"
                        class="app-primary inline-flex h-11 items-center justify-center rounded-md px-5 text-sm font-semibold shadow-sm disabled:opacity-60"
                        :disabled="loading || catalogLoading"
                    >
                        {{ loading ? 'Generando...' : 'Generar PDF' }}
                    </button>

                    <button
                        type="button"
                        class="app-surface app-text inline-flex h-11 items-center justify-center rounded-md border px-5 text-sm font-semibold"
                        @click="clearFilters"
                    >
                        Limpiar
                    </button>
                </form>
            </div>

            <div v-if="catalogError" class="mt-6 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-700">
                {{ catalogError }}
            </div>

            <div v-if="error" class="mt-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                {{ error }}
            </div>
        </section>
    </AdminLayout>
</template>
