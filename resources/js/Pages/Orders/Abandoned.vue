<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const page = usePage();
const user = computed(() => page.props.auth?.user || {});
const loading = ref(false), error = ref(''), countries = ref([]), rows = ref([]);
const pagination = ref({ page: 1, perPage: 20, total: 0, lastPage: 1 });
const summary = ref({ total: 0, byStatus: {} });
const filters = ref({ country: String(user.value?.idPais || ''), startDate: daysAgo(30), endDate: today(), status: '', search: '' });

function today() { return new Date().toISOString().slice(0, 10); }
function daysAgo(days) { const date = new Date(); date.setDate(date.getDate() - days); return date.toISOString().slice(0, 10); }
function formatDate(value) { return value ? new Intl.DateTimeFormat('es', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(String(value).replace(' ', 'T'))) : 'N/D'; }
function formatMoney(value) { return value === null ? 'N/D' : new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(value); }
function detailHref(row) { return row.reference ? `/pedidos/consulta?country=${encodeURIComponent(filters.value.country)}&id=${encodeURIComponent(row.reference)}` : ''; }

async function loadCatalog() {
  const response = await window.axios.get('/dashboard-api/sales/catalog', { params: { country: filters.value.country || undefined } });
  countries.value = response.data.data?.countries || [];
  if (!filters.value.country && countries.value.length) filters.value.country = String(countries.value[0].id || countries.value[0].code);
}
async function load(pageNumber = 1) {
  loading.value = true; error.value = '';
  try {
    const response = await window.axios.get('/dashboard-api/orders/abandoned', { params: { ...filters.value, page: pageNumber, perPage: pagination.value.perPage } });
    rows.value = response.data.data?.rows || []; summary.value = response.data.data?.summary || { total: 0, byStatus: {} };
    pagination.value = response.data.data?.pagination || pagination.value;
  } catch (exception) { error.value = exception.response?.data?.message || 'No fue posible cargar el reporte.'; rows.value = []; }
  finally { loading.value = false; }
}
async function init() { try { await loadCatalog(); await load(); } catch (exception) { error.value = exception.response?.data?.message || 'No fue posible iniciar el reporte.'; } }
onMounted(init);
</script>

<template>
  <Head title="Pedidos abandonados y fallidos" />
  <AdminLayout>
    <div class="mx-auto max-w-7xl space-y-6 p-4 sm:p-6">
      <section class="app-surface rounded-2xl border p-6 shadow-sm" style="border-color: var(--stj-border)">
        <p class="app-primary-text text-xs font-semibold uppercase tracking-[.2em]">Pedidos</p>
        <h1 class="app-heading mt-2 text-2xl font-bold">Abandonados y fallidos</h1>
        <p class="app-muted mt-1 text-sm">Pedidos creados que no tienen ningún pago aprobado, con el último rechazo o error registrado durante el checkout.</p>
      </section>

      <section class="app-surface rounded-2xl border p-5 shadow-sm" style="border-color: var(--stj-border)">
        <form class="grid gap-4 md:grid-cols-2 lg:grid-cols-6" @submit.prevent="load(1)">
          <label class="text-sm">País<select v-model="filters.country" required class="app-input mt-1 w-full rounded-lg border px-3 py-2"><option v-for="country in countries" :key="country.id" :value="String(country.id || country.code)">{{ country.name }}</option></select></label>
          <label class="text-sm">Desde<input v-model="filters.startDate" required type="date" class="app-input mt-1 w-full rounded-lg border px-3 py-2"></label>
          <label class="text-sm">Hasta<input v-model="filters.endDate" required type="date" class="app-input mt-1 w-full rounded-lg border px-3 py-2"></label>
          <label class="text-sm">Estado<select v-model="filters.status" class="app-input mt-1 w-full rounded-lg border px-3 py-2"><option value="">Todos</option><option>SIN_PAGO</option><option>PENDIENTE</option><option>DENEGADA</option><option>TIMEOUT</option><option>REVERSION</option><option>DEVOLUCION</option></select></label>
          <label class="text-sm lg:col-span-2">Buscar<input v-model.trim="filters.search" class="app-input mt-1 w-full rounded-lg border px-3 py-2" placeholder="Pedido, referencia, cliente o correo"></label>
          <div class="lg:col-span-6"><button :disabled="loading" class="rounded-lg bg-blue-600 px-5 py-2.5 font-semibold text-white disabled:opacity-50">{{ loading ? 'Consultando...' : 'Consultar' }}</button></div>
        </form>
      </section>

      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="app-surface rounded-2xl border p-5"><p class="app-muted text-sm">Total sin aprobación</p><strong class="app-heading mt-2 block text-3xl">{{ summary.total }}</strong></div>
        <div v-for="(total, status) in summary.byStatus" :key="status" class="app-surface rounded-2xl border p-5"><p class="app-muted text-sm">{{ status }}</p><strong class="app-heading mt-2 block text-3xl">{{ total }}</strong></div>
      </div>

      <p v-if="error" class="rounded-xl bg-red-50 p-4 text-sm text-red-700">{{ error }}</p>
      <section class="app-surface overflow-hidden rounded-2xl border shadow-sm" style="border-color: var(--stj-border)">
        <div class="overflow-x-auto"><table class="w-full min-w-[1100px] text-left text-sm"><thead class="app-surface-soft"><tr><th class="p-3">Pedido</th><th class="p-3">Creado</th><th class="p-3">Cliente</th><th class="p-3">Canal</th><th class="p-3">Pago</th><th class="p-3">Monto</th><th class="p-3">Etapa / código</th><th class="p-3">Motivo</th></tr></thead><tbody>
          <tr v-for="row in rows" :key="row.orderId" class="border-t align-top" style="border-color: var(--stj-border)">
            <td class="p-3"><a v-if="detailHref(row)" :href="detailHref(row)" class="font-semibold text-blue-600">#{{ row.orderId }}</a><strong v-else>#{{ row.orderId }}</strong><div class="app-muted mt-1 text-xs">{{ row.reference || 'Sin referencia' }}</div></td>
            <td class="p-3">{{ formatDate(row.createdAt) }}<div class="app-muted mt-1 text-xs">{{ row.orderStatus }}</div></td>
            <td class="p-3">{{ row.customer || 'N/D' }}<div class="app-muted mt-1 text-xs">{{ row.email || 'N/D' }}</div></td>
            <td class="p-3">{{ row.origin || 'N/D' }}<div class="app-muted mt-1 text-xs">{{ row.checkout || 'N/D' }}</div></td>
            <td class="p-3"><span class="rounded-full bg-amber-100 px-2 py-1 text-xs font-semibold text-amber-800">{{ row.paymentStatus }}</span><div class="app-muted mt-2 text-xs">{{ row.paymentMethod || 'Sin método' }}</div></td>
            <td class="p-3 font-semibold">{{ formatMoney(row.amount) }}</td>
            <td class="p-3">{{ row.failureStage || 'N/D' }}<div class="app-muted mt-1 text-xs">{{ row.failureCode || row.failureEvent || 'Sin código' }}</div></td>
            <td class="max-w-sm p-3"><p class="whitespace-normal">{{ row.reason }}</p><div class="app-muted mt-1 text-xs">{{ formatDate(row.failureAt) }}</div></td>
          </tr><tr v-if="!loading && !rows.length"><td colspan="8" class="app-muted p-10 text-center">No se encontraron pedidos sin pago aprobado.</td></tr>
        </tbody></table></div>
        <div class="flex items-center justify-between border-t px-5 py-4 text-sm"><span class="app-muted">{{ pagination.total }} registros</span><div class="flex gap-2"><button class="rounded-lg border px-3 py-2 disabled:opacity-40" :disabled="pagination.page <= 1 || loading" @click="load(pagination.page - 1)">Anterior</button><span class="px-3 py-2">{{ pagination.page }} / {{ pagination.lastPage }}</span><button class="rounded-lg border px-3 py-2 disabled:opacity-40" :disabled="pagination.page >= pagination.lastPage || loading" @click="load(pagination.page + 1)">Siguiente</button></div></div>
      </section>
    </div>
  </AdminLayout>
</template>
