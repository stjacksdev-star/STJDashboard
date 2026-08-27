<script setup>
import { Head } from '@inertiajs/vue3';
import DataTable from 'datatables.net-vue3';
import DataTablesCore from 'datatables.net-dt';
import { computed, onMounted, ref } from 'vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';

DataTable.use(DataTablesCore);
const assets = ref([]), countries = ref([]), loading = ref(true), saving = ref(false), error = ref(''), success = ref(''), editing = ref(null), tableKey = ref(0);
const imageInput = ref(null), mobileInput = ref(null);
const blank = () => ({ countryId: '', type: 'BANNER', platform: 'WEB', position: '', order: 1, status: 'PENDIENTE', startAt: '', endAt: '', link: '', title: '', image: null, mobileImage: null });
const form = ref(blank());
const columns = [
  { data: 'id', title: 'ID' }, { data: 'countryLabel', title: 'País' }, { data: 'type', title: 'Tipo' },
  { data: 'status', title: 'Estado' }, { data: 'startLabel', title: 'Inicio' }, { data: 'endLabel', title: 'Fin' },
  { data: 'desktop', title: 'Desktop', orderable: false, searchable: false }, { data: 'mobile', title: 'Móvil', orderable: false, searchable: false },
  { data: 'actions', title: 'Acciones', orderable: false, searchable: false },
];
const options = { pageLength: 10, order: [[0, 'desc']], autoWidth: false, language: { search: 'Buscar:', lengthMenu: 'Mostrar _MENU_ registros', info: 'Mostrando _START_ a _END_ de _TOTAL_ assets', infoEmpty: 'Sin assets', zeroRecords: 'No se encontraron assets', emptyTable: 'No hay assets disponibles', paginate: { next: 'Siguiente', previous: 'Anterior' } } };
const rows = computed(() => assets.value.map(a => ({ ...a, countryLabel: `${a.country?.code || ''} - ${a.country?.name || ''}`, startLabel: date(a.startAt), endLabel: date(a.endAt), desktop: preview(a.image, 'Desktop'), mobile: preview(a.mobileImage, 'Móvil'), actions: `<button type="button" class="stj-action-button" data-edit-asset="${a.id}" title="Editar" aria-label="Editar asset ${a.id}">✎</button>` })));

async function load() {
  loading.value = true; error.value = '';
  try { const r = await window.axios.get('/dashboard-api/assets'); assets.value = r.data.data?.assets || []; countries.value = r.data.data?.countries || []; tableKey.value++; }
  catch (e) { error.value = e.response?.data?.message || 'No fue posible cargar los assets.'; }
  finally { loading.value = false; }
}
function edit(asset) { editing.value = asset; form.value = { countryId: asset.country?.id || '', type: asset.type, platform: asset.platform || 'WEB', position: asset.position || '', order: asset.order ?? 1, status: asset.status || 'PENDIENTE', startAt: localDate(asset.startAt), endAt: localDate(asset.endAt), link: asset.link || '', title: asset.title || '', image: null, mobileImage: null }; window.scrollTo({ top: 0, behavior: 'smooth' }); }
function cancel() { editing.value = null; form.value = blank(); clearFiles(); error.value = ''; }
async function submit() {
  saving.value = true; error.value = ''; success.value = '';
  const payload = new FormData(); Object.entries(form.value).forEach(([key, value]) => { if (value !== null) payload.append(key, value); });
  try { const url = editing.value ? `/dashboard-api/assets/${editing.value.id}` : '/dashboard-api/assets'; const r = await window.axios.post(url, payload); success.value = r.data.message; cancel(); await load(); }
  catch (e) { const errors = e.response?.data?.errors; error.value = errors ? Object.values(errors).flat()[0] : (e.response?.data?.message || 'No fue posible guardar el asset.'); }
  finally { saving.value = false; }
}
function handleAction(e) { const button = e.target.closest('[data-edit-asset]'); if (button) { const asset = assets.value.find(a => a.id === Number(button.dataset.editAsset)); if (asset) edit(asset); } }
function clearFiles() { if (imageInput.value) imageInput.value.value = ''; if (mobileInput.value) mobileInput.value.value = ''; }
function date(value) { return value ? new Intl.DateTimeFormat('es', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(String(value).replace(' ', 'T'))) : ''; }
function localDate(value) { return value ? String(value).replace(' ', 'T').slice(0, 16) : ''; }
function preview(url, label) { return url ? `<a href="${escapeHtml(url)}" target="_blank" rel="noopener" class="text-blue-600 underline">Ver ${label}</a>` : '—'; }
function escapeHtml(value) { return String(value).replaceAll('&', '&amp;').replaceAll('"', '&quot;').replaceAll('<', '&lt;').replaceAll('>', '&gt;'); }
onMounted(load);
</script>

<template>
  <AdminLayout>
    <Head title="Assets" />
    <div class="mx-auto max-w-7xl space-y-6 p-4 sm:p-6">
      <section class="app-surface app-border rounded-xl border p-5">
        <div class="mb-5 flex items-start justify-between gap-4">
          <div><h1 class="app-text text-2xl font-semibold">Assets del ecommerce</h1><p class="app-muted mt-1 text-sm">Gestión independiente de imágenes publicadas por país y vigencia.</p></div>
          <button v-if="editing" type="button" class="app-button-secondary rounded-lg px-4 py-2" @click="cancel">Cancelar edición</button>
        </div>
        <div v-if="error" class="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ error }}</div>
        <div v-if="success" class="mb-4 rounded-lg bg-green-50 p-3 text-sm text-green-700">{{ success }}</div>
        <form class="grid gap-4 md:grid-cols-2 lg:grid-cols-4" @submit.prevent="submit">
          <label class="text-sm">País *<select v-model="form.countryId" required class="app-input mt-1 w-full rounded-lg border px-3 py-2"><option value="">Seleccione</option><option v-for="c in countries" :key="c.id" :value="c.id">{{ c.code }} - {{ c.name }}</option></select></label>
          <label class="text-sm">Tipo de asset *<select v-model="form.type" required class="app-input mt-1 w-full rounded-lg border px-3 py-2"><option v-for="t in ['BANNER','SLIDER','MODAL','CUPON','LO-MAS-NUEVO']" :key="t">{{ t }}</option></select></label>
          <label class="text-sm">Plataforma<select v-model="form.platform" class="app-input mt-1 w-full rounded-lg border px-3 py-2"><option>WEB</option><option>APP</option><option>TODO</option></select></label>
          <label class="text-sm">Estado<select v-model="form.status" class="app-input mt-1 w-full rounded-lg border px-3 py-2"><option>PENDIENTE</option><option>ACTIVO</option><option>CANCELADO</option><option>FINALIZADO</option></select></label>
          <label class="text-sm">Fecha inicial *<input v-model="form.startAt" required type="datetime-local" class="app-input mt-1 w-full rounded-lg border px-3 py-2"></label>
          <label class="text-sm">Fecha final *<input v-model="form.endAt" required type="datetime-local" class="app-input mt-1 w-full rounded-lg border px-3 py-2"></label>
          <label class="text-sm">Orden<input v-model.number="form.order" min="0" type="number" class="app-input mt-1 w-full rounded-lg border px-3 py-2"></label>
          <label class="text-sm">Posición<select v-model="form.position" class="app-input mt-1 w-full rounded-lg border px-3 py-2"><option value="">Sin posición</option><option>DERECHA</option><option>IZQUIERDA</option><option>CENTRO</option></select></label>
          <label class="text-sm lg:col-span-2">Link (opcional)<input v-model="form.link" type="text" maxlength="1000" class="app-input mt-1 w-full rounded-lg border px-3 py-2"></label>
          <label class="text-sm lg:col-span-2">Título (opcional)<input v-model="form.title" type="text" maxlength="255" class="app-input mt-1 w-full rounded-lg border px-3 py-2"></label>
          <label class="text-sm lg:col-span-2">Imagen desktop {{ editing ? '(dejar vacía para conservar)' : '*' }}<input ref="imageInput" :required="!editing" type="file" accept="image/*" class="app-input mt-1 w-full rounded-lg border px-3 py-2" @change="form.image = $event.target.files[0] || null"></label>
          <label class="text-sm lg:col-span-2">Imagen móvil (opcional)<input ref="mobileInput" type="file" accept="image/*" class="app-input mt-1 w-full rounded-lg border px-3 py-2" @change="form.mobileImage = $event.target.files[0] || null"></label>
          <div class="lg:col-span-4"><button :disabled="saving" class="app-button-primary rounded-lg px-5 py-2.5 disabled:opacity-60">{{ saving ? 'Guardando...' : (editing ? 'Guardar cambios' : 'Crear asset') }}</button></div>
        </form>
      </section>
      <section class="app-surface app-border overflow-hidden rounded-xl border p-5">
        <h2 class="app-text mb-4 text-lg font-semibold">Listado de assets</h2>
        <div v-if="loading" class="app-muted py-10 text-center">Cargando assets...</div>
        <div v-else class="overflow-x-auto" @click="handleAction"><DataTable :key="tableKey" :data="rows" :columns="columns" :options="options" class="display w-full" /></div>
      </section>
    </div>
  </AdminLayout>
</template>
