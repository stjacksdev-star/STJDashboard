<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import AdminLayout from '../Layouts/AdminLayout.vue';

const today = new Date();
const sevenDaysAgo = new Date(today);
sevenDaysAgo.setDate(today.getDate() - 7);

const formatDate = (date) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
};

const filters = ref({
    startDate: formatDate(sevenDaysAgo),
    endDate: formatDate(today),
    country: '0',
});

const loading = ref(false);
const error = ref('');
const activeTab = ref('sales');
const conversionLoading = ref(false);
const conversionError = ref('');
const activeConversionTab = ref('general');
const visitsLoading = ref(false);
const visitsError = ref('');
const activeVisitsTab = ref('general');
const chartData = ref({
    categories: [],
    series: [],
    filters: {},
    notes: {},
});
const conversionData = ref({
    categories: [],
    series: [],
    rows: [],
    totals: {},
    filters: {},
});
const visitsData = ref({
    mode: 'general',
    categories: [],
    series: [],
    rows: [],
    totals: {},
    filters: {},
});

const countries = [
    { id: '0', label: 'Todos' },
    { id: '1', label: 'El Salvador' },
    { id: '2', label: 'Guatemala' },
    { id: '3', label: 'Costa Rica' },
    { id: '7', label: 'Honduras' },
    { id: '4', label: 'Consolidado' },
];

const tabs = [
    { id: 'sales', label: 'Venta' },
    { id: 'conversion', label: 'Conversion' },
    { id: 'visits', label: 'Visitas' },
];

const conversionTabs = [
    { id: 'general', label: 'General' },
    { id: 'sv', label: 'El Salvador' },
    { id: 'gt', label: 'Guatemala' },
    { id: 'cr', label: 'Costa Rica' },
    { id: 'hn', label: 'Honduras' },
];
const visitsTabs = conversionTabs;

const palette = {
    sv_current: '#2f9f6b',
    sv_previous: '#8bd9b0',
    gt_current: '#dd5f61',
    gt_previous: '#f2a3a3',
    cr_current: '#2f83d0',
    cr_previous: '#8fc4f1',
    hn_current: '#d97706',
    hn_previous: '#fbbf24',
    total_current: '#7c3aed',
    total_previous: '#c4b5fd',
    conversion_current: '#2563eb',
    conversion_previous: '#93c5fd',
    visits_web: '#2563eb',
    visits_android: '#16a34a',
    visits_ios: '#db2777',
    visits_current: '#2563eb',
    visits_previous: '#93c5fd',
};

const visibleSeries = computed(() => {
    if (filters.value.country === '0') {
        return chartData.value.series.filter((serie) => serie.countryId !== 0);
    }

    if (filters.value.country === '4') {
        return chartData.value.series.filter((serie) => serie.countryId === 0);
    }

    return chartData.value.series.filter((serie) => String(serie.countryId) === filters.value.country);
});

const maxValue = computed(() => Math.max(10, ...visibleSeries.value.flatMap((serie) => serie.data || [])));
const conversionMaxValue = computed(() => Math.max(5, ...conversionData.value.series.flatMap((serie) => serie.data || [])));
const visitsMaxValue = computed(() => Math.max(10, ...visitsData.value.series.flatMap((serie) => serie.data || [])));

const yTicks = computed(() => {
    const top = maxValue.value;
    return [top, top * 0.75, top * 0.5, top * 0.25, 0].map((value) => Math.round(value));
});
const conversionYTicks = computed(() => {
    const top = conversionMaxValue.value;
    return [top, top * 0.75, top * 0.5, top * 0.25, 0].map((value) => Number(value.toFixed(2)));
});
const visitsYTicks = computed(() => {
    const top = visitsMaxValue.value;
    return [top, top * 0.75, top * 0.5, top * 0.25, 0].map((value) => Math.round(value));
});

const totals = computed(() => visibleSeries.value.map((serie) => ({
    key: serie.key,
    label: serie.label,
    total: (serie.data || []).reduce((sum, value) => sum + Number(value || 0), 0),
})));

const conversionTotals = computed(() => conversionData.value.totals || {});
const visitsTotals = computed(() => visitsData.value.totals || {});

const chartWidth = 920;
const chartHeight = 320;
const padding = { top: 24, right: 28, bottom: 46, left: 64 };
const plotWidth = chartWidth - padding.left - padding.right;
const plotHeight = chartHeight - padding.top - padding.bottom;

const xFor = (index) => {
    const count = Math.max(chartData.value.categories.length - 1, 1);
    return padding.left + (index / count) * plotWidth;
};

const yFor = (value) => padding.top + plotHeight - (Number(value || 0) / maxValue.value) * plotHeight;
const conversionXFor = (index) => {
    const count = Math.max(conversionData.value.categories.length - 1, 1);
    return padding.left + (index / count) * plotWidth;
};
const conversionYFor = (value) => padding.top + plotHeight - (Number(value || 0) / conversionMaxValue.value) * plotHeight;
const visitsXFor = (index) => {
    const count = Math.max(visitsData.value.categories.length - 1, 1);
    return padding.left + (index / count) * plotWidth;
};
const visitsYFor = (value) => padding.top + plotHeight - (Number(value || 0) / visitsMaxValue.value) * plotHeight;

const linePath = (data) => (data || [])
    .map((value, index) => `${index === 0 ? 'M' : 'L'} ${xFor(index).toFixed(2)} ${yFor(value).toFixed(2)}`)
    .join(' ');
const conversionLinePath = (data) => (data || [])
    .map((value, index) => `${index === 0 ? 'M' : 'L'} ${conversionXFor(index).toFixed(2)} ${conversionYFor(value).toFixed(2)}`)
    .join(' ');
const visitsLinePath = (data) => (data || [])
    .map((value, index) => `${index === 0 ? 'M' : 'L'} ${visitsXFor(index).toFixed(2)} ${visitsYFor(value).toFixed(2)}`)
    .join(' ');

const money = (value) => new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    maximumFractionDigits: 2,
}).format(Number(value || 0));

const loadChart = async () => {
    loading.value = true;
    error.value = '';

    try {
        const response = await window.axios.get('/dashboard-api/sales/regional-chart', {
            params: {
                startDate: filters.value.startDate,
                endDate: filters.value.endDate,
            },
        });

        chartData.value = response.data.data || chartData.value;
    } catch (exception) {
        error.value = exception.response?.data?.message || 'No fue posible cargar el grafico de ventas.';
    } finally {
        loading.value = false;
    }
};

const loadConversion = async () => {
    conversionLoading.value = true;
    conversionError.value = '';

    try {
        const response = await window.axios.get('/dashboard-api/sales/conversion', {
            params: {
                startDate: filters.value.startDate,
                endDate: filters.value.endDate,
                country: activeConversionTab.value,
            },
        });

        conversionData.value = response.data.data || conversionData.value;
    } catch (exception) {
        conversionError.value = exception.response?.data?.message || 'No fue posible cargar la conversion.';
    } finally {
        conversionLoading.value = false;
    }
};

const loadVisits = async () => {
    visitsLoading.value = true;
    visitsError.value = '';

    try {
        const params = {
            startDate: filters.value.startDate,
            endDate: filters.value.endDate,
            country: activeVisitsTab.value,
        };

        if (activeVisitsTab.value !== 'general') {
            params.previousStartDate = visitsPreviousFilters.value.startDate;
            params.previousEndDate = visitsPreviousFilters.value.endDate;
        }

        const response = await window.axios.get('/dashboard-api/sales/visits', { params });

        visitsData.value = response.data.data || visitsData.value;
    } catch (exception) {
        visitsError.value = exception.response?.data?.message || 'No fue posible cargar las visitas.';
    } finally {
        visitsLoading.value = false;
    }
};

const visitsPreviousFilters = ref({
    startDate: formatDate(new Date(sevenDaysAgo.getFullYear() - 1, sevenDaysAgo.getMonth(), sevenDaysAgo.getDate())),
    endDate: formatDate(new Date(today.getFullYear() - 1, today.getMonth(), today.getDate())),
});

const percent = (value) => `${Number(value || 0).toFixed(2)}%`;

watch(activeTab, (tab) => {
    if (tab === 'conversion' && conversionData.value.categories.length === 0) {
        loadConversion();
    }

    if (tab === 'visits' && visitsData.value.categories.length === 0) {
        loadVisits();
    }
});

watch(activeConversionTab, () => {
    if (activeTab.value === 'conversion') {
        loadConversion();
    }
});

watch(activeVisitsTab, () => {
    if (activeTab.value === 'visits') {
        loadVisits();
    }
});

onMounted(loadChart);
</script>

<template>
    <Head title="Dashboard" />

    <AdminLayout>
        <section class="mx-auto w-full max-w-7xl">
            <div class="app-surface rounded-lg border">
                <div class="app-border-soft overflow-x-auto border-b">
                    <nav class="flex min-w-max px-4" aria-label="Dashboard tabs">
                        <button
                            v-for="tab in tabs"
                            :key="tab.id"
                            type="button"
                            :class="[
                                'border-b-2 px-4 py-3 text-sm font-medium transition',
                                activeTab === tab.id
                                    ? 'border-blue-500 app-text'
                                    : 'border-transparent app-primary-text hover:bg-blue-50/10',
                            ]"
                            @click="activeTab = tab.id"
                        >
                            {{ tab.label }}
                        </button>
                    </nav>
                </div>

                <div v-if="activeTab === 'sales'" class="app-border-soft border-b p-5">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <p class="app-muted text-sm">Dashboard Analytics</p>
                            <h2 class="app-text mt-1 text-xl font-semibold">Ventas por pais</h2>
                        </div>

                        <form class="grid gap-3 sm:grid-cols-4" @submit.prevent="loadChart">
                            <label class="block">
                                <span class="app-muted text-sm">Inicio</span>
                                <input v-model="filters.startDate" type="date" class="stj-dashboard-input mt-1">
                            </label>
                            <label class="block">
                                <span class="app-muted text-sm">Fin</span>
                                <input v-model="filters.endDate" type="date" class="stj-dashboard-input mt-1">
                            </label>
                            <label class="block">
                                <span class="app-muted text-sm">Pais</span>
                                <select v-model="filters.country" class="stj-dashboard-input mt-1">
                                    <option v-for="country in countries" :key="country.id" :value="country.id">
                                        {{ country.label }}
                                    </option>
                                </select>
                            </label>
                            <button type="submit" class="mt-6 h-10 rounded-md app-primary px-4 text-sm font-semibold transition hover:opacity-90">
                                Actualizar
                            </button>
                        </form>
                    </div>
                </div>

                <div v-if="activeTab === 'sales'" class="p-5">
                    <div v-if="error" class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        {{ error }}
                    </div>

                    <div class="relative min-h-[360px] overflow-x-auto">
                        <div v-if="loading" class="app-muted absolute inset-0 z-10 flex items-center justify-center bg-white/70 text-sm">
                            Cargando ventas...
                        </div>

                        <svg
                            class="min-w-[760px]"
                            :viewBox="`0 0 ${chartWidth} ${chartHeight}`"
                            role="img"
                            aria-label="Grafico de ventas por pais"
                        >
                            <g v-for="tick in yTicks" :key="tick">
                                <line
                                    :x1="padding.left"
                                    :x2="chartWidth - padding.right"
                                    :y1="yFor(tick)"
                                    :y2="yFor(tick)"
                                    stroke="var(--stj-border-soft)"
                                />
                                <text
                                    :x="padding.left - 10"
                                    :y="yFor(tick) + 4"
                                    text-anchor="end"
                                    class="fill-current app-muted"
                                    font-size="12"
                                >
                                    {{ tick }}
                                </text>
                            </g>

                            <g v-for="(date, index) in chartData.categories" :key="date">
                                <text
                                    :x="xFor(index)"
                                    :y="chartHeight - 18"
                                    text-anchor="middle"
                                    class="fill-current app-text-soft"
                                    font-size="12"
                                >
                                    {{ date.slice(5) }}
                                </text>
                            </g>

                            <g v-for="serie in visibleSeries" :key="serie.key">
                                <path
                                    :d="linePath(serie.data)"
                                    fill="none"
                                    :stroke="palette[serie.key]"
                                    :stroke-width="serie.period === 'current' ? 3 : 3"
                                    :stroke-dasharray="serie.period === 'previous' ? '6 7' : '0'"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                                <circle
                                    v-for="(value, index) in serie.data"
                                    :key="`${serie.key}-${index}`"
                                    :cx="xFor(index)"
                                    :cy="yFor(value)"
                                    r="3"
                                    :fill="palette[serie.key]"
                                />
                            </g>
                        </svg>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-3">
                        <div
                            v-for="serie in visibleSeries"
                            :key="serie.key"
                            class="app-surface-soft app-border flex items-center gap-2 rounded-md border px-3 py-2 text-sm"
                        >
                            <span class="h-3 w-3 rounded-full" :style="{ background: palette[serie.key] }"></span>
                            <span class="app-text-soft">{{ serie.label }}</span>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div
                            v-for="row in totals"
                            :key="row.key"
                            class="app-surface-soft app-border rounded-md border p-3"
                        >
                            <p class="app-muted text-xs">{{ row.label }}</p>
                            <p class="app-text mt-1 text-lg font-semibold">{{ money(row.total) }}</p>
                        </div>
                    </div>
                </div>

                <div v-if="activeTab === 'conversion'" class="p-5">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <p class="app-muted text-sm">Dashboard Analytics</p>
                            <h2 class="app-text mt-1 text-xl font-semibold">% Conversion</h2>
                        </div>

                        <form class="grid gap-3 sm:grid-cols-3" @submit.prevent="loadConversion">
                            <label class="block">
                                <span class="app-muted text-sm">Inicio</span>
                                <input v-model="filters.startDate" type="date" class="stj-dashboard-input mt-1">
                            </label>
                            <label class="block">
                                <span class="app-muted text-sm">Fin</span>
                                <input v-model="filters.endDate" type="date" class="stj-dashboard-input mt-1">
                            </label>
                            <button type="submit" class="mt-6 h-10 rounded-md app-primary px-4 text-sm font-semibold transition hover:opacity-90">
                                Generar
                            </button>
                        </form>
                    </div>

                    <div class="app-border-soft mt-5 overflow-x-auto border-b">
                        <nav class="flex min-w-max" aria-label="Conversion tabs">
                            <button
                                v-for="tab in conversionTabs"
                                :key="tab.id"
                                type="button"
                                :class="[
                                    'border-b-2 px-4 py-3 text-sm font-medium transition',
                                    activeConversionTab === tab.id
                                        ? 'border-blue-500 app-text'
                                        : 'border-transparent app-primary-text hover:bg-blue-50/10',
                                ]"
                                @click="activeConversionTab = tab.id"
                            >
                                {{ tab.label }}
                            </button>
                        </nav>
                    </div>

                    <div v-if="conversionError" class="mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        {{ conversionError }}
                    </div>

                    <div class="relative mt-5 min-h-[360px] overflow-x-auto">
                        <div v-if="conversionLoading" class="app-muted absolute inset-0 z-10 flex items-center justify-center bg-white/70 text-sm">
                            Cargando conversion...
                        </div>

                        <svg
                            class="min-w-[760px]"
                            :viewBox="`0 0 ${chartWidth} ${chartHeight}`"
                            role="img"
                            aria-label="Grafico de conversion"
                        >
                            <g v-for="tick in conversionYTicks" :key="tick">
                                <line
                                    :x1="padding.left"
                                    :x2="chartWidth - padding.right"
                                    :y1="conversionYFor(tick)"
                                    :y2="conversionYFor(tick)"
                                    stroke="var(--stj-border-soft)"
                                />
                                <text
                                    :x="padding.left - 10"
                                    :y="conversionYFor(tick) + 4"
                                    text-anchor="end"
                                    class="fill-current app-muted"
                                    font-size="12"
                                >
                                    {{ percent(tick) }}
                                </text>
                            </g>

                            <g v-for="(date, index) in conversionData.categories" :key="date">
                                <text
                                    :x="conversionXFor(index)"
                                    :y="chartHeight - 18"
                                    text-anchor="middle"
                                    class="fill-current app-text-soft"
                                    font-size="12"
                                >
                                    {{ date.slice(5) }}
                                </text>
                            </g>

                            <g v-for="serie in conversionData.series" :key="serie.key">
                                <path
                                    :d="conversionLinePath(serie.data)"
                                    fill="none"
                                    :stroke="palette[serie.key]"
                                    stroke-width="3"
                                    :stroke-dasharray="serie.period === 'previous' ? '6 7' : '0'"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                                <circle
                                    v-for="(value, index) in serie.data"
                                    :key="`${serie.key}-${index}`"
                                    :cx="conversionXFor(index)"
                                    :cy="conversionYFor(value)"
                                    r="3"
                                    :fill="palette[serie.key]"
                                />
                            </g>
                        </svg>
                    </div>

                    <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="app-surface-soft app-border rounded-md border p-3">
                            <p class="app-muted text-xs">Visitas</p>
                            <p class="app-text mt-1 text-lg font-semibold">{{ conversionTotals.visits || 0 }}</p>
                        </div>
                        <div class="app-surface-soft app-border rounded-md border p-3">
                            <p class="app-muted text-xs">Compras</p>
                            <p class="app-text mt-1 text-lg font-semibold">{{ conversionTotals.orders || 0 }}</p>
                        </div>
                        <div class="app-surface-soft app-border rounded-md border p-3">
                            <p class="app-muted text-xs">Conversion actual</p>
                            <p class="app-text mt-1 text-lg font-semibold">{{ percent(conversionTotals.rate) }}</p>
                        </div>
                        <div class="app-surface-soft app-border rounded-md border p-3">
                            <p class="app-muted text-xs">Conversion anterior</p>
                            <p class="app-text mt-1 text-lg font-semibold">{{ percent(conversionTotals.previousRate) }}</p>
                        </div>
                    </div>

                    <div class="mt-5 overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead class="app-surface-soft app-text-soft">
                                <tr>
                                    <th class="px-3 py-2 font-semibold">Fecha</th>
                                    <th class="px-3 py-2 text-right font-semibold">Visitas</th>
                                    <th class="px-3 py-2 text-right font-semibold">Compras</th>
                                    <th class="px-3 py-2 text-right font-semibold">%</th>
                                    <th class="px-3 py-2 text-right font-semibold">Fecha anterior</th>
                                    <th class="px-3 py-2 text-right font-semibold">% anterior</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!conversionData.rows.length">
                                    <td colspan="6" class="app-muted px-3 py-6 text-center">Sin datos para el rango seleccionado.</td>
                                </tr>
                                <tr v-for="row in conversionData.rows" :key="row.date" class="app-border-soft border-b">
                                    <td class="px-3 py-2">{{ row.date }}</td>
                                    <td class="px-3 py-2 text-right">{{ row.visits }}</td>
                                    <td class="px-3 py-2 text-right">{{ row.orders }}</td>
                                    <td class="px-3 py-2 text-right">{{ percent(row.rate) }}</td>
                                    <td class="px-3 py-2 text-right">{{ row.previousDate }}</td>
                                    <td class="px-3 py-2 text-right">{{ percent(row.previousRate) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-else-if="activeTab === 'visits'" class="p-5">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <p class="app-muted text-sm">Dashboard Analytics</p>
                            <h2 class="app-text mt-1 text-xl font-semibold">Visitas</h2>
                        </div>

                        <form class="grid gap-3 sm:grid-cols-3" @submit.prevent="loadVisits">
                            <label class="block">
                                <span class="app-muted text-sm">Inicio</span>
                                <input v-model="filters.startDate" type="date" class="stj-dashboard-input mt-1">
                            </label>
                            <label class="block">
                                <span class="app-muted text-sm">Fin</span>
                                <input v-model="filters.endDate" type="date" class="stj-dashboard-input mt-1">
                            </label>
                            <button type="submit" class="mt-6 h-10 rounded-md app-primary px-4 text-sm font-semibold transition hover:opacity-90">
                                Generar
                            </button>
                        </form>
                    </div>

                    <div class="app-border-soft mt-5 overflow-x-auto border-b">
                        <nav class="flex min-w-max" aria-label="Visitas tabs">
                            <button
                                v-for="tab in visitsTabs"
                                :key="tab.id"
                                type="button"
                                :class="[
                                    'border-b-2 px-4 py-3 text-sm font-medium transition',
                                    activeVisitsTab === tab.id
                                        ? 'border-blue-500 app-text'
                                        : 'border-transparent app-primary-text hover:bg-blue-50/10',
                                ]"
                                @click="activeVisitsTab = tab.id"
                            >
                                {{ tab.label }}
                            </button>
                        </nav>
                    </div>

                    <form
                        v-if="activeVisitsTab !== 'general'"
                        class="app-surface-soft app-border mt-5 grid gap-3 rounded-md border p-4 sm:grid-cols-3"
                        @submit.prevent="loadVisits"
                    >
                        <label class="block">
                            <span class="app-muted text-sm">Rango anterior inicio</span>
                            <input v-model="visitsPreviousFilters.startDate" type="date" class="stj-dashboard-input mt-1">
                        </label>
                        <label class="block">
                            <span class="app-muted text-sm">Rango anterior fin</span>
                            <input v-model="visitsPreviousFilters.endDate" type="date" class="stj-dashboard-input mt-1">
                        </label>
                        <button type="submit" class="mt-6 h-10 rounded-md app-primary px-4 text-sm font-semibold transition hover:opacity-90">
                            Comparar
                        </button>
                    </form>

                    <div v-if="visitsError" class="mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        {{ visitsError }}
                    </div>

                    <div class="relative mt-5 min-h-[360px] overflow-x-auto">
                        <div v-if="visitsLoading" class="app-muted absolute inset-0 z-10 flex items-center justify-center bg-white/70 text-sm">
                            Cargando visitas...
                        </div>

                        <svg
                            class="min-w-[760px]"
                            :viewBox="`0 0 ${chartWidth} ${chartHeight}`"
                            role="img"
                            aria-label="Grafico de visitas"
                        >
                            <g v-for="tick in visitsYTicks" :key="tick">
                                <line
                                    :x1="padding.left"
                                    :x2="chartWidth - padding.right"
                                    :y1="visitsYFor(tick)"
                                    :y2="visitsYFor(tick)"
                                    stroke="var(--stj-border-soft)"
                                />
                                <text
                                    :x="padding.left - 10"
                                    :y="visitsYFor(tick) + 4"
                                    text-anchor="end"
                                    class="fill-current app-muted"
                                    font-size="12"
                                >
                                    {{ tick }}
                                </text>
                            </g>

                            <g v-for="(date, index) in visitsData.categories" :key="date">
                                <text
                                    :x="visitsXFor(index)"
                                    :y="chartHeight - 18"
                                    text-anchor="middle"
                                    class="fill-current app-text-soft"
                                    font-size="12"
                                >
                                    {{ date.slice(5) }}
                                </text>
                            </g>

                            <g v-for="serie in visitsData.series" :key="serie.key">
                                <path
                                    :d="visitsLinePath(serie.data)"
                                    fill="none"
                                    :stroke="palette[serie.key]"
                                    stroke-width="3"
                                    :stroke-dasharray="serie.period === 'previous' ? '6 7' : '0'"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                                <circle
                                    v-for="(value, index) in serie.data"
                                    :key="`${serie.key}-${index}`"
                                    :cx="visitsXFor(index)"
                                    :cy="visitsYFor(value)"
                                    r="3"
                                    :fill="palette[serie.key]"
                                />
                            </g>
                        </svg>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-3">
                        <div
                            v-for="serie in visitsData.series"
                            :key="serie.key"
                            class="app-surface-soft app-border flex items-center gap-2 rounded-md border px-3 py-2 text-sm"
                        >
                            <span class="h-3 w-3 rounded-full" :style="{ background: palette[serie.key] }"></span>
                            <span class="app-text-soft">{{ serie.label }}</span>
                        </div>
                    </div>

                    <div v-if="visitsData.mode === 'country'" class="mt-4 grid gap-3 sm:grid-cols-2">
                        <div class="app-surface-soft app-border rounded-md border p-3">
                            <p class="app-muted text-xs">Visitas actuales</p>
                            <p class="app-text mt-1 text-lg font-semibold">{{ visitsTotals.visits || 0 }}</p>
                        </div>
                        <div class="app-surface-soft app-border rounded-md border p-3">
                            <p class="app-muted text-xs">Visitas anteriores</p>
                            <p class="app-text mt-1 text-lg font-semibold">{{ visitsTotals.previousVisits || 0 }}</p>
                        </div>
                    </div>

                    <div class="mt-5 overflow-x-auto">
                        <table v-if="visitsData.mode === 'general'" class="min-w-full text-left text-sm">
                            <thead class="app-surface-soft app-text-soft">
                                <tr>
                                    <th class="px-3 py-2 font-semibold">Pais</th>
                                    <th class="px-3 py-2 text-right font-semibold">Visitas</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!visitsData.rows.length">
                                    <td colspan="2" class="app-muted px-3 py-6 text-center">Sin visitas para el rango seleccionado.</td>
                                </tr>
                                <tr v-for="row in visitsData.rows" :key="row.country" class="app-border-soft border-b">
                                    <td class="px-3 py-2">{{ row.country }}</td>
                                    <td class="px-3 py-2 text-right">{{ row.visits }}</td>
                                </tr>
                            </tbody>
                        </table>

                        <table v-else class="min-w-full text-left text-sm">
                            <thead class="app-surface-soft app-text-soft">
                                <tr>
                                    <th class="px-3 py-2 font-semibold">#</th>
                                    <th class="px-3 py-2 font-semibold">Fecha actual</th>
                                    <th class="px-3 py-2 text-right font-semibold">Visitas actual</th>
                                    <th class="px-3 py-2 text-right font-semibold">Fecha anterior</th>
                                    <th class="px-3 py-2 text-right font-semibold">Visitas anterior</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!visitsData.rows.length">
                                    <td colspan="5" class="app-muted px-3 py-6 text-center">Sin datos para el rango seleccionado.</td>
                                </tr>
                                <tr v-for="row in visitsData.rows" :key="row.date" class="app-border-soft border-b">
                                    <td class="px-3 py-2">{{ row.index }}</td>
                                    <td class="px-3 py-2">{{ row.date }}</td>
                                    <td class="px-3 py-2 text-right">{{ row.visits }}</td>
                                    <td class="px-3 py-2 text-right">{{ row.previousDate }}</td>
                                    <td class="px-3 py-2 text-right">{{ row.previousVisits }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    </AdminLayout>
</template>

<style scoped>
.stj-dashboard-input {
    min-height: 40px;
    width: 100%;
    border: 1px solid var(--stj-border);
    border-radius: 6px;
    background: var(--stj-surface);
    color: var(--stj-text);
    padding: 0 0.75rem;
    font-size: 0.875rem;
}

.stj-dashboard-input:focus {
    border-color: var(--stj-primary);
    outline: 3px solid var(--stj-primary-soft);
}
</style>
