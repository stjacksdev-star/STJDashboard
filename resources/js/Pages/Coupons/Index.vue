<script setup>
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const countries = ref([]), coupons = ref([]), loading = ref(false), saving = ref(false), changingStatusId = ref(null), error = ref(''), message = ref('');
const filters = reactive({ country: '', status: '', search: '', page: 1, perPage: 20 });
const pagination = reactive({ page: 1, perPage: 20, total: 0, lastPage: 1 });
const catalogs = reactive({ categories: [], collections: [], automaticTemplates: [] });
const productsFile = ref(null), customersFile = ref(null);
let searchTimer;
const blank = () => ({ id: null, country: '', name: '', commercialName: '', channel: 'WEB', type: 'DESCUENTO', checkout: 'TODO', generic: 'NO', code: '', amount: null, discount: null, minimumEnabled: 'NO', minimumAmount: 0, extraDiscount: 'NO', multiple: 'NO', promotionRule: 'REGULAR', firstPurchaseOnly: 'NO', startAt: '', endAt: '', status: 'ACTIVO', audience: 'NA', productScope: 'NA', categoryId: null, collectionId: null, automaticTemplate: '' });
const form = reactive(blank());
const editing = computed(() => !!form.id);
const localDate = value => value ? String(value).replace(' ', 'T').slice(0, 16) : '';

async function load() {
  loading.value = true; error.value = '';
  try { const { data } = await axios.get('/dashboard-api/coupons', { params: filters }); countries.value = data.data.countries; coupons.value = data.data.coupons; Object.assign(pagination, data.data.pagination || {}); filters.page = pagination.page; if (!form.country && countries.value.length) form.country = countries.value[0].code; }
  catch (e) { error.value = e.response?.data?.message || 'No fue posible cargar los cupones.'; }
  finally { loading.value = false; }
}
function reset() { Object.assign(form, blank(), { country: filters.country || countries.value[0]?.code || '' }); productsFile.value = null; customersFile.value = null; error.value = ''; message.value = ''; }
function edit(c) { Object.assign(form, blank(), c, { country: c.country.code, minimumEnabled: c.minimumEnabled || 'NO', extraDiscount: c.extraDiscount || 'NO', multiple: c.multiple || 'NO', promotionRule: c.promotionRule || 'REGULAR', firstPurchaseOnly: c.firstPurchaseOnly || 'NO', startAt: localDate(c.startAt), endAt: localDate(c.endAt) }); window.scrollTo({ top: 0, behavior: 'smooth' }); }
async function save() {
  saving.value = true; error.value = ''; message.value = '';
  try { const body = new FormData(); Object.entries(form).forEach(([key, value]) => { if (value !== null) body.append(key, value); }); if (productsFile.value) body.append('productsFile', productsFile.value); if (customersFile.value) body.append('customersFile', customersFile.value); const url = form.id ? `/dashboard-api/coupons/${form.id}` : '/dashboard-api/coupons'; const { data } = await axios.post(url, body); message.value = data.message; reset(); productsFile.value = null; customersFile.value = null; message.value = data.message; await load(); }
  catch (e) { const errors = e.response?.data?.errors || {}; error.value = Object.values(errors).flat()[0] || e.response?.data?.message || 'No fue posible guardar el cupón.'; }
  finally { saving.value = false; }
}
async function loadCatalogs() { if (!form.country) return; const { data } = await axios.get('/dashboard-api/coupons/catalogs', { params: { country: form.country } }); Object.assign(catalogs, data.data); }
async function changeStatus(coupon) {
  const nextStatus = coupon.status === 'ACTIVO' ? 'INACTIVO' : 'ACTIVO';
  const action = nextStatus === 'INACTIVO' ? 'inactivar' : 'activar';
  if (!window.confirm(`¿Confirma que desea ${action} el cupón #${coupon.id} (${coupon.name})?`)) return;
  changingStatusId.value = coupon.id; error.value = ''; message.value = '';
  try {
    const { data } = await axios.patch(`/dashboard-api/coupons/${coupon.id}/status`, { status: nextStatus, country: coupon.country.code });
    message.value = data.message;
    await load();
  } catch (e) { error.value = e.response?.data?.errors ? Object.values(e.response.data.errors).flat()[0] : (e.response?.data?.message || `No fue posible ${action} el cupón.`); }
  finally { changingStatusId.value = null; }
}
onMounted(load);
watch(() => form.country, loadCatalogs);
watch(() => form.extraDiscount, value => {
  if (value === 'SI' && form.promotionRule === 'REGULAR') form.promotionRule = 'TODOS';
});
watch(() => filters.search, () => { clearTimeout(searchTimer); searchTimer = setTimeout(() => { filters.page = 1; load(); }, 350); });
function applyFilters() { filters.page = 1; load(); }
function goToPage(page) { if (page < 1 || page > pagination.lastPage || page === pagination.page) return; filters.page = page; load(); }
const firstResult = computed(() => pagination.total ? ((pagination.page - 1) * pagination.perPage) + 1 : 0);
const lastResult = computed(() => Math.min(pagination.page * pagination.perPage, pagination.total));
</script>

<template>
  <Head title="Cupones" />
  <AdminLayout>
    <div class="mx-auto max-w-7xl space-y-6 p-4 sm:p-6">
      <section class="app-surface rounded-lg border p-6 shadow-sm" style="border-color: var(--stj-border);">
        <p class="app-primary-text text-xs font-semibold uppercase tracking-[.2em]">Módulo</p>
        <div class="mt-2 flex flex-wrap items-center justify-between gap-3"><div><h1 class="app-heading text-2xl font-bold">Cupones / Mantenimiento</h1><p class="app-muted mt-1 text-sm">Administra las reglas consumidas por el nuevo ecommerce.</p></div><button v-if="editing" class="rounded-lg border px-4 py-2 text-sm" @click="reset">Nuevo cupón</button></div>
      </section>

      <section class="app-surface rounded-lg border p-6 shadow-sm" style="border-color: var(--stj-border);">
        <h2 class="app-heading text-lg font-semibold">{{ editing ? `Editar cupón #${form.id}` : 'Crear cupón' }}</h2>
        <form class="mt-5 grid gap-4 md:grid-cols-2 lg:grid-cols-3" @submit.prevent="save">
          <label class="text-sm">Nombre<input v-model="form.name" required maxlength="100" class="app-input mt-1 w-full rounded-lg border px-3 py-2"></label>
          <label class="text-sm">Nombre comercial<input v-model="form.commercialName" maxlength="250" class="app-input mt-1 w-full rounded-lg border px-3 py-2"></label>
          <label class="text-sm">País<select v-model="form.country" required class="app-input mt-1 w-full rounded-lg border px-3 py-2"><option v-for="c in countries" :key="c.id" :value="c.code">{{ c.name }}</option></select></label>
          <label class="text-sm">Canal<select v-model="form.channel" class="app-input mt-1 w-full rounded-lg border px-3 py-2"><option>WEB</option><option>APP</option><option>TODO</option></select></label>
          <label class="text-sm">Tipo<select v-model="form.type" class="app-input mt-1 w-full rounded-lg border px-3 py-2"><option value="DESCUENTO">Descuento porcentual</option><option value="PRECIO">Precio objetivo</option><option value="ENVIO_GRATIS">Envío gratis</option></select></label>
          <label class="text-sm">Checkout<select v-model="form.checkout" class="app-input mt-1 w-full rounded-lg border px-3 py-2"><option>TODO</option><option>DOMICILIO</option><option>TIENDA</option></select></label>
          <label v-if="form.type === 'DESCUENTO'" class="text-sm">Descuento (%)<input v-model.number="form.discount" required type="number" min="0.01" max="99.99" step="0.01" class="app-input mt-1 w-full rounded-lg border px-3 py-2"></label>
          <label v-if="form.type === 'PRECIO'" class="text-sm">Precio objetivo<input v-model.number="form.amount" required type="number" min="0.01" step="0.01" class="app-input mt-1 w-full rounded-lg border px-3 py-2"></label>
          <label class="text-sm">Genérico<select v-model="form.generic" class="app-input mt-1 w-full rounded-lg border px-3 py-2"><option>NO</option><option>SI</option></select></label>
          <label v-if="form.generic === 'SI'" class="text-sm">Código<input v-model.trim="form.code" required maxlength="100" class="app-input mt-1 w-full rounded-lg border px-3 py-2 uppercase"></label>
          <label class="text-sm">Aplica sobre<select v-model="form.promotionRule" class="app-input mt-1 w-full rounded-lg border px-3 py-2"><option value="REGULAR" :disabled="form.extraDiscount === 'SI'">Precio regular</option><option value="PROMO">Productos en promoción</option><option value="TODOS">Todos</option></select></label>
          <label class="text-sm">Monto mínimo<select v-model="form.minimumEnabled" class="app-input mt-1 w-full rounded-lg border px-3 py-2"><option>NO</option><option>SI</option></select></label>
          <label v-if="form.minimumEnabled === 'SI'" class="text-sm">Monto mínimo<input v-model.number="form.minimumAmount" type="number" min="0" step="0.01" class="app-input mt-1 w-full rounded-lg border px-3 py-2"></label>
          <label class="text-sm">Primera compra<select v-model="form.firstPurchaseOnly" class="app-input mt-1 w-full rounded-lg border px-3 py-2"><option>NO</option><option>SI</option></select></label>
          <label class="text-sm">Uso múltiple<select v-model="form.multiple" class="app-input mt-1 w-full rounded-lg border px-3 py-2"><option>NO</option><option>SI</option></select></label>
          <label class="text-sm">Descuento extra<select v-model="form.extraDiscount" class="app-input mt-1 w-full rounded-lg border px-3 py-2"><option>NO</option><option>SI</option></select></label>
          <label class="text-sm">Inicio<input v-model="form.startAt" required type="datetime-local" class="app-input mt-1 w-full rounded-lg border px-3 py-2"></label>
          <label class="text-sm">Final<input v-model="form.endAt" required type="datetime-local" class="app-input mt-1 w-full rounded-lg border px-3 py-2"></label>
          <label class="text-sm">Estado<select v-model="form.status" class="app-input mt-1 w-full rounded-lg border px-3 py-2"><option>ACTIVO</option><option>INACTIVO</option></select></label>
          <label class="text-sm">Destinatarios<select v-model="form.audience" class="app-input mt-1 w-full rounded-lg border px-3 py-2"><option value="NA">Sin generación personal</option><option value="VIP">Clientes VIP del país</option><option value="PLA">Clientes desde Excel/CSV</option></select></label>
          <label v-if="form.audience === 'PLA'" class="text-sm">Archivo de clientes (correo en columna A)<input type="file" accept=".xlsx,.csv,.txt" class="app-input mt-1 w-full rounded-lg border px-3 py-2" @change="customersFile = $event.target.files[0]"></label>
          <label class="text-sm">Alcance de productos<select v-model="form.productScope" class="app-input mt-1 w-full rounded-lg border px-3 py-2"><option value="NA">Todos los productos elegibles</option><option value="PLA">Productos desde Excel/CSV</option><option value="GEN">Categoría</option><option value="COL">Colección</option></select></label>
          <label v-if="form.productScope === 'PLA'" class="text-sm">Archivo de productos (código en columna A)<input type="file" accept=".xlsx,.csv,.txt" class="app-input mt-1 w-full rounded-lg border px-3 py-2" @change="productsFile = $event.target.files[0]"></label>
          <label v-if="form.productScope === 'GEN'" class="text-sm">Categoría<select v-model.number="form.categoryId" required class="app-input mt-1 w-full rounded-lg border px-3 py-2"><option :value="null">Seleccione</option><option v-for="item in catalogs.categories" :key="item.id" :value="item.id">{{ item.name }}</option></select></label>
          <label v-if="form.productScope === 'COL'" class="text-sm">Colección<select v-model.number="form.collectionId" required class="app-input mt-1 w-full rounded-lg border px-3 py-2"><option :value="null">Seleccione</option><option v-for="item in catalogs.collections" :key="item.id" :value="item.id">{{ item.name }}</option></select></label>
          <label class="text-sm">Plantilla automática<input v-model.trim="form.automaticTemplate" list="automatic-templates" maxlength="50" class="app-input mt-1 w-full rounded-lg border px-3 py-2 uppercase" placeholder="Ej. REGISTRO_EMAIL"><datalist id="automatic-templates"><option v-for="item in catalogs.automaticTemplates" :key="item" :value="item" /></datalist><small class="app-muted">Al definirla, se guardan reglas reutilizables y no se genera código.</small></label>
          <div class="md:col-span-2 lg:col-span-3"><p v-if="error" class="mb-3 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ error }}</p><p v-if="message" class="mb-3 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-700">{{ message }}</p><button :disabled="saving" class="rounded-lg bg-blue-600 px-5 py-2.5 font-semibold text-white disabled:opacity-50">{{ saving ? 'Guardando...' : (editing ? 'Actualizar cupón' : 'Crear cupón') }}</button></div>
        </form>
      </section>

      <section class="app-surface overflow-hidden rounded-lg border shadow-sm" style="border-color: var(--stj-border);">
        <div class="flex flex-wrap items-end gap-3 border-b p-5" style="border-color: var(--stj-border);">
          <label class="min-w-64 flex-1 text-sm">Buscar<input v-model="filters.search" type="search" class="app-surface app-text mt-1 h-10 w-full rounded-md border px-3" placeholder="ID, nombre o código de cupón"></label>
          <label class="text-sm">País<select v-model="filters.country" class="app-surface app-text mt-1 h-10 rounded-md border px-3" @change="applyFilters"><option value="">Todos los países</option><option v-for="c in countries" :key="c.id" :value="c.code">{{ c.name }}</option></select></label>
          <label class="text-sm">Estado<select v-model="filters.status" class="app-surface app-text mt-1 h-10 rounded-md border px-3" @change="applyFilters"><option value="">Todos los estados</option><option>ACTIVO</option><option>INACTIVO</option></select></label>
          <label class="text-sm">Mostrar<select v-model.number="filters.perPage" class="app-surface app-text mt-1 h-10 rounded-md border px-3" @change="applyFilters"><option :value="10">10</option><option :value="20">20</option><option :value="50">50</option><option :value="100">100</option></select></label>
        </div>
        <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="app-surface-soft app-text"><tr><th class="p-3">ID</th><th class="p-3">Cupón</th><th class="p-3">País</th><th class="p-3">Beneficio</th><th class="p-3">Código</th><th class="p-3">Vigencia</th><th class="p-3">Estado</th><th class="p-3"></th></tr></thead><tbody><tr v-for="c in coupons" :key="c.id" class="border-t" style="border-color: var(--stj-border);"><td class="p-3">{{ c.id }}</td><td class="p-3"><strong>{{ c.name }}</strong><div class="app-muted text-xs">{{ c.commercialName }}</div></td><td class="p-3">{{ c.country.code }}</td><td class="p-3">{{ c.type === 'DESCUENTO' ? `${c.discount}%` : c.type === 'PRECIO' ? `$${c.amount}` : 'Envío gratis' }}</td><td class="p-3 font-mono">{{ c.code || 'Personal' }}</td><td class="p-3 text-xs">{{ c.startAt }}<br>{{ c.endAt }}</td><td class="p-3"><span class="rounded-full px-2 py-1 text-xs" :class="c.status === 'ACTIVO' ? 'bg-emerald-100 text-emerald-700' : 'bg-stone-100 text-stone-600'">{{ c.status }}</span></td><td class="p-3"><div class="flex items-center gap-3"><button class="font-semibold text-blue-600" @click="edit(c)">Editar</button><button :disabled="changingStatusId === c.id" class="font-semibold disabled:opacity-40" :class="c.status === 'ACTIVO' ? 'text-red-600' : 'text-emerald-700'" @click="changeStatus(c)">{{ changingStatusId === c.id ? 'Procesando...' : (c.status === 'ACTIVO' ? 'Inactivar' : 'Activar') }}</button></div></td></tr><tr v-if="loading"><td colspan="8" class="app-muted p-8 text-center">Cargando cupones...</td></tr><tr v-else-if="!coupons.length"><td colspan="8" class="app-muted p-8 text-center">No hay cupones para los filtros seleccionados.</td></tr></tbody></table></div>
        <div class="flex flex-wrap items-center justify-between gap-3 border-t px-5 py-4 text-sm" style="border-color: var(--stj-border);">
          <span class="app-muted">Mostrando {{ firstResult }} a {{ lastResult }} de {{ pagination.total }} cupones</span>
          <div class="flex items-center gap-2"><button class="app-surface app-text rounded-md border px-3 py-2 disabled:opacity-40" :disabled="pagination.page <= 1 || loading" @click="goToPage(pagination.page - 1)">Anterior</button><span class="app-muted px-2">Página {{ pagination.page }} de {{ pagination.lastPage }}</span><button class="app-surface app-text rounded-md border px-3 py-2 disabled:opacity-40" :disabled="pagination.page >= pagination.lastPage || loading" @click="goToPage(pagination.page + 1)">Siguiente</button></div>
        </div>
      </section>
    </div>
  </AdminLayout>
</template>
