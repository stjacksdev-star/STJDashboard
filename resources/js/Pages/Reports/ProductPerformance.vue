<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onMounted, reactive, ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const page = usePage();
const countries = computed(() => page.props.auth?.countries?.allowed || []);
const tabs = [
    { id: 'summary', label: 'Resumen' }, { id: 'sales', label: 'Más vendidos' },
    { id: 'views', label: 'Más vistos' }, { id: 'favorites', label: 'Favoritos' },
    { id: 'cart', label: 'Agregados al carrito' },
];
const filters = reactive({ country: '', period: '30D', tab: 'summary', brand: '', category: '', search: '', page: 1, perPage: 20 });
const options = reactive({ brands: [], categories: [] });
const summary = reactive({ products: 0, sales: 0, amount: 0, views: 0, favorites: 0, cartAdds: 0, calculatedAt: null });
const pagination = reactive({ page: 1, perPage: 20, total: 0, lastPage: 1 });
const rows = ref([]), loading = ref(false), error = ref('');
const number = value => new Intl.NumberFormat('es-SV').format(Number(value || 0));
const money = value => new Intl.NumberFormat('es-SV', { style: 'currency', currency: 'USD' }).format(Number(value || 0));
const dateTime = value => value ? new Date(String(value).replace(' ', 'T')).toLocaleString('es-SV') : 'Sin cálculo';
const firstResult = computed(() => pagination.total ? ((pagination.page - 1) * pagination.perPage) + 1 : 0);
const lastResult = computed(() => Math.min(pagination.page * pagination.perPage, pagination.total));

async function load() {
    if (!filters.country) return;
    loading.value = true; error.value = '';
    try {
        const { data } = await axios.get('/dashboard-api/reports/product-performance', { params: filters });
        rows.value = data.data.rows || [];
        Object.assign(summary, data.data.summary || {});
        Object.assign(options, data.data.filters || {});
        Object.assign(pagination, data.data.pagination || {});
    } catch (e) {
        error.value = Object.values(e.response?.data?.errors || {}).flat()[0] || e.response?.data?.message || 'No fue posible cargar el reporte.';
    } finally { loading.value = false; }
}
function apply() { filters.page = 1; load(); }
function selectTab(tab) { filters.tab = tab; apply(); }
function pageTo(target) { if (target < 1 || target > pagination.lastPage || target === pagination.page) return; filters.page = target; load(); }
onMounted(() => { filters.country = String(countries.value[0]?.code || countries.value[0]?.pai_codigo || '').toUpperCase(); load(); });
</script>

<template>
    <Head title="Rendimiento de productos" />
    <AdminLayout>
        <div class="mx-auto max-w-[1550px] space-y-6 p-4 sm:p-6">
            <section class="app-surface rounded-lg border p-6 shadow-sm" style="border-color: var(--stj-border);">
                <p class="app-primary-text text-xs font-semibold uppercase tracking-[.2em]">Reportes</p>
                <h1 class="app-heading mt-2 text-2xl font-bold">Rendimiento de productos</h1>
                <p class="app-muted mt-1 text-sm">Consulta las métricas consolidadas de ventas e interacción con los productos.</p>
            </section>

            <section class="app-surface rounded-lg border p-5 shadow-sm" style="border-color: var(--stj-border);">
                <form class="grid gap-4 md:grid-cols-2 xl:grid-cols-5" @submit.prevent="apply">
                    <label class="text-sm">País<select v-model="filters.country" class="app-input mt-1 h-10 w-full rounded-md border px-3" @change="filters.brand=''; filters.category=''; apply()"><option v-for="country in countries" :key="country.id || country.code" :value="String(country.code || country.pai_codigo).toUpperCase()">{{ country.name || country.pai_nombre }}</option></select></label>
                    <label class="text-sm">Período<select v-model="filters.period" class="app-input mt-1 h-10 w-full rounded-md border px-3" @change="filters.brand=''; filters.category=''; apply()"><option value="7D">Últimos 7 días</option><option value="14D">Últimos 14 días</option><option value="30D">Últimos 30 días</option><option value="ANUAL">Anual</option></select></label>
                    <label class="text-sm">Marca<select v-model="filters.brand" class="app-input mt-1 h-10 w-full rounded-md border px-3"><option value="">Todas las marcas</option><option v-for="brand in options.brands" :key="brand" :value="brand">{{ brand }}</option></select></label>
                    <label class="text-sm">Categoría<select v-model="filters.category" class="app-input mt-1 h-10 w-full rounded-md border px-3"><option value="">Todas las categorías</option><option v-for="category in options.categories" :key="category.id" :value="category.id">{{ category.name }}</option></select></label>
                    <label class="text-sm">Buscar producto<div class="mt-1 flex gap-2"><input v-model.trim="filters.search" type="search" class="app-input h-10 min-w-0 flex-1 rounded-md border px-3" placeholder="Nombre o código"><button class="rounded-md bg-blue-600 px-4 font-semibold text-white">Buscar</button></div></label>
                </form>
                <p v-if="error" class="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ error }}</p>
            </section>

            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
                <article v-for="card in [{l:'Productos',v:summary.products},{l:'Unidades vendidas',v:summary.sales},{l:'Monto vendido',v:money(summary.amount)},{l:'Vistas',v:summary.views},{l:'Favoritos',v:summary.favorites},{l:'Agregados',v:summary.cartAdds}]" :key="card.l" class="app-surface rounded-lg border p-4 shadow-sm"><p class="app-muted text-xs uppercase">{{ card.l }}</p><strong class="app-heading mt-2 block text-xl">{{ typeof card.v === 'number' ? number(card.v) : card.v }}</strong></article>
            </section>

            <section class="app-surface overflow-hidden rounded-lg border shadow-sm" style="border-color: var(--stj-border);">
                <div class="flex flex-wrap gap-1 border-b p-3" style="border-color: var(--stj-border);"><button v-for="tab in tabs" :key="tab.id" class="rounded-md px-4 py-2 text-sm font-semibold" :class="filters.tab === tab.id ? 'bg-blue-600 text-white' : 'app-surface-soft app-text'" @click="selectTab(tab.id)">{{ tab.label }}</button></div>
                <div class="overflow-x-auto"><table class="w-full min-w-[1100px] text-left text-sm"><thead class="app-surface-soft"><tr><th class="p-3">Producto</th><th class="p-3">Marca / categoría</th><th class="p-3">Ventas</th><th class="p-3">Pedidos</th><th class="p-3">Monto</th><th class="p-3">Vistas</th><th class="p-3">Favoritos</th><th class="p-3">Agregados</th><th class="p-3">Conversión</th></tr></thead>
                    <tbody><tr v-for="row in rows" :key="row.productId" class="border-t" style="border-color: var(--stj-border);"><td class="p-3"><strong>{{ row.name }}</strong><div class="app-muted mt-1 font-mono text-xs">{{ row.code }}</div></td><td class="p-3"><strong>{{ row.brand || '—' }}</strong><div class="app-muted mt-1 text-xs">{{ row.category || 'Sin categoría' }}</div></td><td class="p-3 font-semibold">{{ number(row.sales) }}</td><td class="p-3">{{ number(row.orders) }}</td><td class="p-3">{{ money(row.amount) }}</td><td class="p-3">{{ number(row.views) }}</td><td class="p-3">{{ number(row.favorites) }}</td><td class="p-3">{{ number(row.cartAdds) }}</td><td class="p-3 font-semibold text-emerald-700">{{ Number(row.conversionRate).toFixed(2) }}%</td></tr><tr v-if="loading"><td colspan="9" class="app-muted p-10 text-center">Cargando rendimiento...</td></tr><tr v-else-if="!rows.length"><td colspan="9" class="app-muted p-10 text-center">No hay métricas para los filtros seleccionados.</td></tr></tbody></table></div>
                <div class="flex flex-wrap items-center justify-between gap-3 border-t px-5 py-4 text-sm" style="border-color: var(--stj-border);"><div><span class="app-muted">Mostrando {{ firstResult }} a {{ lastResult }} de {{ pagination.total }}</span><span class="app-muted ml-4">Actualizado: {{ dateTime(summary.calculatedAt) }}</span></div><div class="flex items-center gap-2"><select v-model.number="filters.perPage" class="app-input rounded-md border px-2 py-1" @change="apply"><option :value="10">10</option><option :value="20">20</option><option :value="50">50</option><option :value="100">100</option></select><button class="rounded-md border px-3 py-2 disabled:opacity-40" :disabled="pagination.page <= 1 || loading" @click="pageTo(pagination.page-1)">Anterior</button><span>Página {{ pagination.page }} de {{ pagination.lastPage }}</span><button class="rounded-md border px-3 py-2 disabled:opacity-40" :disabled="pagination.page >= pagination.lastPage || loading" @click="pageTo(pagination.page+1)">Siguiente</button></div></div>
            </section>
        </div>
    </AdminLayout>
</template>
