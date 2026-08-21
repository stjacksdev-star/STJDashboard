<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onMounted, reactive, ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const page = usePage();
const today = new Date();
const localDate = date => {
    const offset = date.getTimezoneOffset();
    return new Date(date.getTime() - offset * 60000).toISOString().slice(0, 10);
};
const monthStart = new Date(today.getFullYear(), today.getMonth(), 1);
const countries = computed(() => page.props.auth?.countries?.allowed || []);
const filters = reactive({ country: '', startDate: localDate(monthStart), endDate: localDate(today), search: '', page: 1, perPage: 20 });
const rows = ref([]);
const summary = reactive({ uses: 0, productDiscount: 0, shippingDiscount: 0, totalDiscount: 0 });
const pagination = reactive({ page: 1, perPage: 20, total: 0, lastPage: 1 });
const loading = ref(false), error = ref('');

function money(value, currency = 'USD') {
    try { return new Intl.NumberFormat('es-SV', { style: 'currency', currency }).format(Number(value || 0)); }
    catch { return `${currency} ${Number(value || 0).toFixed(2)}`; }
}
function dateTime(value) { return value ? new Date(String(value).replace(' ', 'T')).toLocaleString('es-SV') : '—'; }
function benefit(row) {
    if (row.couponType === 'DESCUENTO') return `${row.configuredDiscount}%`;
    if (row.couponType === 'PRECIO') return `Precio ${money(row.configuredAmount, row.currency)}`;
    return 'Envío gratis';
}
async function load() {
    if (!filters.country) return;
    loading.value = true; error.value = '';
    try {
        const { data } = await axios.get('/dashboard-api/coupons/usage-report', { params: filters });
        rows.value = data.data.rows || [];
        Object.assign(summary, data.data.summary || {});
        Object.assign(pagination, data.data.pagination || {});
        filters.page = pagination.page;
    } catch (e) { error.value = Object.values(e.response?.data?.errors || {}).flat()[0] || e.response?.data?.message || 'No fue posible cargar el reporte.'; }
    finally { loading.value = false; }
}
function applyFilters() { filters.page = 1; load(); }
function goToPage(target) { if (target < 1 || target > pagination.lastPage || target === pagination.page) return; filters.page = target; load(); }
const firstResult = computed(() => pagination.total ? ((pagination.page - 1) * pagination.perPage) + 1 : 0);
const lastResult = computed(() => Math.min(pagination.page * pagination.perPage, pagination.total));

onMounted(() => {
    filters.country = String(countries.value[0]?.code || countries.value[0]?.pai_codigo || '').toUpperCase();
    load();
});
</script>

<template>
    <Head title="Reporte de cupones usados" />
    <AdminLayout>
        <div class="mx-auto max-w-[1500px] space-y-6 p-4 sm:p-6">
            <section class="app-surface rounded-lg border p-6 shadow-sm" style="border-color: var(--stj-border);">
                <p class="app-primary-text text-xs font-semibold uppercase tracking-[.2em]">Módulo</p>
                <h1 class="app-heading mt-2 text-2xl font-bold">Cupones / Reporte de usados</h1>
                <p class="app-muted mt-1 text-sm">Consulta cupones consumidos en pedidos aprobados.</p>
            </section>

            <section class="app-surface rounded-lg border p-5 shadow-sm" style="border-color: var(--stj-border);">
                <form class="grid gap-4 md:grid-cols-2 xl:grid-cols-5" @submit.prevent="applyFilters">
                    <label class="text-sm">País<select v-model="filters.country" required class="app-input mt-1 h-10 w-full rounded-md border px-3"><option v-for="country in countries" :key="country.id || country.code" :value="String(country.code || country.pai_codigo).toUpperCase()">{{ country.name || country.pai_nombre }}</option></select></label>
                    <label class="text-sm">Fecha inicial<input v-model="filters.startDate" required type="date" class="app-input mt-1 h-10 w-full rounded-md border px-3"></label>
                    <label class="text-sm">Fecha final<input v-model="filters.endDate" required type="date" class="app-input mt-1 h-10 w-full rounded-md border px-3"></label>
                    <label class="text-sm xl:col-span-2">Código o correo<input v-model.trim="filters.search" type="search" maxlength="255" class="app-input mt-1 h-10 w-full rounded-md border px-3" placeholder="Ej. A589E7 o cliente@correo.com"></label>
                    <div class="flex items-end gap-3 md:col-span-2 xl:col-span-5"><button :disabled="loading" class="rounded-md bg-blue-600 px-5 py-2.5 font-semibold text-white disabled:opacity-50">{{ loading ? 'Consultando...' : 'Consultar' }}</button><button type="button" class="rounded-md border px-4 py-2.5 text-sm" @click="filters.search = ''; applyFilters()">Limpiar búsqueda</button></div>
                </form>
                <p v-if="error" class="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ error }}</p>
            </section>

            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article class="app-surface rounded-lg border p-4 shadow-sm"><p class="app-muted text-xs uppercase">Usos encontrados</p><strong class="app-heading mt-2 block text-2xl">{{ summary.uses }}</strong></article>
                <article class="app-surface rounded-lg border p-4 shadow-sm"><p class="app-muted text-xs uppercase">Descuento productos</p><strong class="app-heading mt-2 block text-2xl">{{ money(summary.productDiscount, rows[0]?.currency) }}</strong></article>
                <article class="app-surface rounded-lg border p-4 shadow-sm"><p class="app-muted text-xs uppercase">Descuento envío</p><strong class="app-heading mt-2 block text-2xl">{{ money(summary.shippingDiscount, rows[0]?.currency) }}</strong></article>
                <article class="app-surface rounded-lg border p-4 shadow-sm"><p class="app-muted text-xs uppercase">Descuento total</p><strong class="mt-2 block text-2xl font-bold text-emerald-700">{{ money(summary.totalDiscount, rows[0]?.currency) }}</strong></article>
            </section>

            <section class="app-surface overflow-hidden rounded-lg border shadow-sm" style="border-color: var(--stj-border);">
                <div class="overflow-x-auto"><table class="w-full min-w-[1250px] text-left text-sm"><thead class="app-surface-soft app-text"><tr><th class="p-3">Fecha de uso</th><th class="p-3">Código / cupón</th><th class="p-3">Cliente</th><th class="p-3">STJ / pedido</th><th class="p-3">Monto pedido</th><th class="p-3">Tipo / beneficio</th><th class="p-3">Desc. productos</th><th class="p-3">Desc. envío</th><th class="p-3">Descuento total</th></tr></thead>
                    <tbody><tr v-for="row in rows" :key="row.id" class="border-t" style="border-color: var(--stj-border);"><td class="whitespace-nowrap p-3">{{ dateTime(row.usedAt) }}</td><td class="p-3"><strong class="font-mono">{{ row.code }}</strong><div class="app-muted mt-1 text-xs">{{ row.couponName }}</div></td><td class="p-3"><strong>{{ row.customerName || 'Invitado' }}</strong><div class="app-muted mt-1 text-xs">{{ row.customerEmail }}</div></td><td class="p-3"><a :href="`/pedidos/consulta?country=${filters.country.toLowerCase()}&id=${encodeURIComponent(row.orderReference)}`" class="font-semibold text-blue-600 hover:underline">{{ row.orderReference || `Pedido #${row.orderId}` }}</a><div class="app-muted mt-1 text-xs">ID {{ row.orderId }} · {{ row.checkout }}</div></td><td class="whitespace-nowrap p-3 font-semibold">{{ money(row.orderAmount, row.currency) }}</td><td class="p-3"><strong>{{ row.couponType }}</strong><div class="app-muted mt-1 text-xs">{{ benefit(row) }}</div></td><td class="whitespace-nowrap p-3">{{ money(row.productDiscount, row.currency) }}</td><td class="whitespace-nowrap p-3">{{ money(row.shippingDiscount, row.currency) }}</td><td class="whitespace-nowrap p-3 font-bold text-emerald-700">{{ money(row.totalDiscount, row.currency) }}</td></tr><tr v-if="loading"><td colspan="9" class="app-muted p-10 text-center">Cargando cupones usados...</td></tr><tr v-else-if="!rows.length"><td colspan="9" class="app-muted p-10 text-center">No se encontraron cupones consumidos para los filtros seleccionados.</td></tr></tbody></table></div>
                <div class="flex flex-wrap items-center justify-between gap-3 border-t px-5 py-4 text-sm" style="border-color: var(--stj-border);"><span class="app-muted">Mostrando {{ firstResult }} a {{ lastResult }} de {{ pagination.total }}</span><div class="flex items-center gap-2"><label class="app-muted">Mostrar <select v-model.number="filters.perPage" class="app-input rounded-md border px-2 py-1" @change="applyFilters"><option :value="10">10</option><option :value="20">20</option><option :value="50">50</option><option :value="100">100</option></select></label><button class="rounded-md border px-3 py-2 disabled:opacity-40" :disabled="pagination.page <= 1 || loading" @click="goToPage(pagination.page - 1)">Anterior</button><span class="app-muted">Página {{ pagination.page }} de {{ pagination.lastPage }}</span><button class="rounded-md border px-3 py-2 disabled:opacity-40" :disabled="pagination.page >= pagination.lastPage || loading" @click="goToPage(pagination.page + 1)">Siguiente</button></div></div>
            </section>
        </div>
    </AdminLayout>
</template>
