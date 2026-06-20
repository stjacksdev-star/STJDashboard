<script setup>
import { Head } from '@inertiajs/vue3';
import DataTable from 'datatables.net-vue3';
import DataTablesCore from 'datatables.net-dt';
import { computed, onMounted, ref } from 'vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';

DataTable.use(DataTablesCore);

const loading = ref(true);
const saving = ref(false);
const error = ref('');
const success = ref('');
const formError = ref('');
const errors = ref({});
const notifications = ref([]);
const statuses = ref(['TODO', 'PENDIENTE', 'ENVIADO', 'ERROR']);
const topics = ref(defaultTopics());
const tableKey = ref(0);
const showModal = ref(false);
const filters = ref(defaultFilters());
const form = ref(defaultForm());

const columns = [
    { data: 'id', title: 'Envio', width: '80px' },
    { data: 'scheduledAtLabel', title: 'Programacion', width: '160px' },
    { data: 'statusLabel', title: 'Estado', width: '130px' },
    { data: 'title', title: 'Titulo' },
    { data: 'body', title: 'Cuerpo' },
    { data: 'to', title: 'Destino', width: '160px' },
    { data: 'action', title: 'Accion' },
    { data: 'result', title: 'Resultado' },
];

const dataTableOptions = {
    pageLength: 25,
    lengthMenu: [10, 25, 50, 100],
    order: [[1, 'desc']],
    autoWidth: false,
    language: {
        search: 'Buscar:',
        lengthMenu: 'Mostrar _MENU_ registros',
        info: 'Mostrando _START_ a _END_ de _TOTAL_ push',
        infoEmpty: 'Sin push',
        infoFiltered: '(filtrado de _MAX_ registros)',
        zeroRecords: 'No se encontraron push',
        emptyTable: 'No hay notificaciones push disponibles',
        paginate: {
            first: 'Primero',
            last: 'Ultimo',
            next: 'Siguiente',
            previous: 'Anterior',
        },
    },
};

const rows = computed(() =>
    notifications.value.map((item) => ({
        ...item,
        scheduledAtLabel: formatDateTime(item.scheduledAt),
        statusLabel: statusHtml(item.status),
        body: truncate(item.body, 120),
        action: truncate(item.action, 80),
        result: truncate(item.result, 120),
    })),
);

onMounted(fetchNotifications);

async function fetchNotifications() {
    loading.value = true;
    error.value = '';

    try {
        const response = await window.axios.get('/dashboard-api/push-notifications', {
            params: normalizeFilters(filters.value),
        });

        notifications.value = response.data.data?.notifications || [];
        statuses.value = response.data.data?.options?.statuses || statuses.value;
        topics.value = response.data.data?.options?.topics || defaultTopics();
        tableKey.value += 1;
    } catch (exception) {
        error.value = exception.response?.data?.message || 'No fue posible cargar las notificaciones push.';
    } finally {
        loading.value = false;
    }
}

async function saveNotification() {
    saving.value = true;
    formError.value = '';
    errors.value = {};
    success.value = '';

    try {
        const response = await window.axios.post('/dashboard-api/push-notifications', normalizePayload(form.value), {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        });
        success.value = response.data.message || 'Notificacion push programada correctamente.';
        showModal.value = false;
        form.value = defaultForm();
        await fetchNotifications();
    } catch (exception) {
        formError.value = exception.response?.data?.message
            || firstError(exception.response?.data?.errors)
            || 'No fue posible programar la notificacion push.';
        errors.value = exception.response?.data?.errors || {};
    } finally {
        saving.value = false;
    }
}

function openCreateModal() {
    form.value = defaultForm();
    formError.value = '';
    errors.value = {};
    showModal.value = true;
}

function defaultFilters() {
    const today = new Date();
    const start = new Date(today);
    start.setDate(today.getDate() - 30);

    return {
        startDate: toDateInput(start),
        endDate: toDateInput(today),
        status: 'TODO',
        search: '',
    };
}

function defaultForm() {
    const nextHour = new Date();
    nextHour.setHours(nextHour.getHours() + 1, 0, 0, 0);

    return {
        title: '',
        body: '',
        image: null,
        action: 'https://stjacks.com',
        to: '/topics/all',
        scheduledAt: toDateTimeInput(nextHour),
        promotionId: '',
    };
}

function normalizeFilters(values) {
    return {
        startDate: values.startDate || null,
        endDate: values.endDate || null,
        status: values.status || 'TODO',
        search: values.search || null,
    };
}

function normalizePayload(values) {
    const payload = new FormData();

    payload.append('title', values.title);
    payload.append('body', values.body);
    payload.append('action', values.action);
    payload.append('to', values.to);
    payload.append('scheduledAt', values.scheduledAt);

    if (values.promotionId) {
        payload.append('promotionId', values.promotionId);
    }

    if (values.image) {
        payload.append('image', values.image);
    }

    return payload;
}

function defaultTopics() {
    return [
        { value: '/topics/all', label: 'All' },
        { value: '/topics/ios', label: 'IOS' },
        { value: '/topics/android', label: 'Android' },
        { value: '/topics/androidcr', label: 'Android CR' },
        { value: '/topics/androidgt', label: 'Android GT' },
        { value: '/topics/androidhn', label: 'Android HN' },
        { value: '/topics/androidsv', label: 'Android SV' },
        { value: '/topics/generalWEB', label: 'General WEB' },
    ];
}

function handleImageChange(event) {
    form.value.image = event.target.files?.[0] || null;
}

function fieldError(field) {
    return Array.isArray(errors.value?.[field]) ? errors.value[field][0] : '';
}

function firstError(errorBag) {
    if (! errorBag) {
        return '';
    }

    const first = Object.values(errorBag)[0];

    return Array.isArray(first) ? first[0] : '';
}

function formatDateTime(value) {
    if (! value) {
        return 'N/D';
    }

    return String(value).replace('T', ' ').slice(0, 19);
}

function toDateInput(date) {
    return date.toISOString().slice(0, 10);
}

function toDateTimeInput(date) {
    const local = new Date(date.getTime() - date.getTimezoneOffset() * 60000);

    return local.toISOString().slice(0, 16);
}

function truncate(value, max) {
    const text = String(value || '');

    return text.length > max ? `${text.slice(0, max)}...` : text;
}

function statusHtml(status) {
    const normalized = String(status || '').toUpperCase();
    const className = {
        PENDIENTE: 'stj-status-pending',
        ENVIADO: 'stj-status-sent',
        ERROR: 'stj-status-error',
    }[normalized] || 'stj-status-muted';

    return `<span class="stj-status ${className}">${normalized || 'N/D'}</span>`;
}
</script>

<template>
    <AdminLayout>
        <Head title="Configuracion / Push" />

        <div class="mx-auto max-w-7xl space-y-6">
            <section class="app-surface rounded-lg border p-5 shadow-sm" style="border-color: var(--stj-border);">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                    <div>
                        <p class="app-primary-text text-xs font-semibold uppercase">Configuracion</p>
                        <h1 class="app-text mt-2 text-2xl font-bold">Notificaciones push</h1>
                    </div>

                    <div class="grid gap-3 md:grid-cols-[150px_150px_150px_minmax(180px,1fr)_auto_auto]">
                        <label class="block">
                            <span class="app-muted text-sm font-medium">Desde</span>
                            <input v-model="filters.startDate" type="date" class="stj-input mt-2">
                        </label>

                        <label class="block">
                            <span class="app-muted text-sm font-medium">Hasta</span>
                            <input v-model="filters.endDate" type="date" class="stj-input mt-2">
                        </label>

                        <label class="block">
                            <span class="app-muted text-sm font-medium">Estado</span>
                            <select v-model="filters.status" class="stj-input mt-2">
                                <option v-for="status in statuses" :key="status" :value="status">
                                    {{ status === 'TODO' ? 'Todos' : status }}
                                </option>
                            </select>
                        </label>

                        <label class="block">
                            <span class="app-muted text-sm font-medium">Busqueda</span>
                            <input v-model.trim="filters.search" maxlength="255" type="search" class="stj-input mt-2">
                        </label>

                        <button
                            type="button"
                            class="mt-7 inline-flex h-11 items-center justify-center rounded-md border px-4 text-sm font-semibold transition"
                            style="border-color: var(--stj-border); color: var(--stj-text);"
                            :disabled="loading"
                            @click="fetchNotifications"
                        >
                            Buscar
                        </button>

                        <button
                            type="button"
                            class="mt-7 inline-flex h-11 items-center justify-center rounded-md bg-blue-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700"
                            @click="openCreateModal"
                        >
                            Nueva push
                        </button>
                    </div>
                </div>
            </section>

            <div v-if="error" class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                {{ error }}
            </div>

            <div v-if="success" class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ success }}
            </div>

            <section class="app-surface overflow-hidden rounded-lg border shadow-sm" style="border-color: var(--stj-border);">
                <div class="app-border-soft border-b px-5 py-4">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <h2 class="app-text text-lg font-semibold">Historico</h2>
                        <p class="app-muted text-sm">{{ notifications.length }} push cargadas</p>
                    </div>
                </div>

                <div class="p-5">
                    <div v-if="loading" class="app-muted rounded-md border p-4 text-sm" style="border-color: var(--stj-border);">
                        Cargando notificaciones push...
                    </div>

                    <div v-else class="overflow-x-auto">
                        <DataTable
                            :key="tableKey"
                            :columns="columns"
                            :data="rows"
                            :options="dataTableOptions"
                            class="display compact stripe w-full text-sm"
                        />
                    </div>
                </div>
            </section>
        </div>

        <Teleport to="body">
            <div v-if="showModal" class="stj-modal-backdrop">
                <div class="stj-modal app-surface">
                    <div class="app-border-soft flex items-center justify-between border-b px-5 py-4">
                        <div>
                            <p class="app-primary-text text-xs font-semibold uppercase">Push</p>
                            <h2 class="app-text mt-1 text-xl font-bold">Nueva notificacion push</h2>
                        </div>

                        <button type="button" class="stj-modal-close" title="Cerrar" @click="showModal = false">
                            <svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18" /></svg>
                        </button>
                    </div>

                    <form class="p-5" @submit.prevent="saveNotification">
                        <div v-if="formError" class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                            {{ formError }}
                        </div>

                        <div class="grid gap-4 lg:grid-cols-2">
                            <label class="block">
                                <span class="app-muted text-sm font-medium">Titulo</span>
                                <input v-model.trim="form.title" required maxlength="160" type="text" class="stj-input mt-2">
                                <span v-if="fieldError('title')" class="stj-field-error">{{ fieldError('title') }}</span>
                            </label>

                            <label class="block">
                                <span class="app-muted text-sm font-medium">Fecha de programacion</span>
                                <input v-model="form.scheduledAt" required type="datetime-local" class="stj-input mt-2">
                                <span v-if="fieldError('scheduledAt')" class="stj-field-error">{{ fieldError('scheduledAt') }}</span>
                            </label>
                        </div>

                        <label class="mt-4 block">
                            <span class="app-muted text-sm font-medium">Cuerpo</span>
                            <textarea v-model.trim="form.body" required maxlength="500" rows="3" class="stj-input mt-2"></textarea>
                            <span v-if="fieldError('body')" class="stj-field-error">{{ fieldError('body') }}</span>
                        </label>

                        <div class="mt-4 grid gap-4 lg:grid-cols-2">
                            <label class="block">
                                <span class="app-muted text-sm font-medium">Imagen</span>
                                <input accept="image/*" type="file" class="stj-input mt-2" @change="handleImageChange">
                                <span class="app-muted mt-1 block text-xs">Medida sugerida: 700 x 400 px</span>
                                <span v-if="form.image" class="app-muted mt-1 block text-xs">{{ form.image.name }}</span>
                                <span v-if="fieldError('image')" class="stj-field-error">{{ fieldError('image') }}</span>
                            </label>

                            <label class="block">
                                <span class="app-muted text-sm font-medium">Accion</span>
                                <input v-model.trim="form.action" required maxlength="500" type="text" class="stj-input mt-2">
                                <span v-if="fieldError('action')" class="stj-field-error">{{ fieldError('action') }}</span>
                            </label>
                        </div>

                        <div class="mt-4 grid gap-4 lg:grid-cols-2">
                            <label class="block">
                                <span class="app-muted text-sm font-medium">Destino</span>
                                <select v-model="form.to" required class="stj-input mt-2">
                                    <option v-for="topic in topics" :key="topic.value" :value="topic.value">
                                        {{ topic.label }}
                                    </option>
                                </select>
                                <span v-if="fieldError('to')" class="stj-field-error">{{ fieldError('to') }}</span>
                            </label>

                            <label class="block">
                                <span class="app-muted text-sm font-medium">Promocion ID</span>
                                <input v-model="form.promotionId" min="1" type="number" class="stj-input mt-2">
                                <span v-if="fieldError('promotionId')" class="stj-field-error">{{ fieldError('promotionId') }}</span>
                            </label>
                        </div>

                        <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                            <button
                                type="button"
                                class="inline-flex h-11 items-center justify-center rounded-md border px-5 text-sm font-semibold transition"
                                style="border-color: var(--stj-border); color: var(--stj-text);"
                                @click="showModal = false"
                            >
                                Cancelar
                            </button>

                            <button
                                type="submit"
                                class="inline-flex h-11 items-center justify-center rounded-md bg-blue-600 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="saving"
                            >
                                {{ saving ? 'Guardando...' : 'Programar push' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>

<style>
.stj-modal-backdrop {
    align-items: center;
    background: rgb(15 23 42 / 0.52);
    display: flex;
    inset: 0;
    justify-content: center;
    padding: 1rem;
    position: fixed;
    z-index: 60;
}

.stj-modal {
    border: 1px solid var(--stj-border);
    border-radius: 0.5rem;
    box-shadow: 0 24px 80px rgb(15 23 42 / 0.24);
    max-height: calc(100vh - 2rem);
    max-width: 860px;
    overflow: auto;
    width: min(860px, 100%);
}

.stj-modal-close {
    align-items: center;
    border: 1px solid var(--stj-border);
    border-radius: 0.375rem;
    color: var(--stj-muted);
    display: inline-flex;
    height: 2.25rem;
    justify-content: center;
    width: 2.25rem;
}

.stj-modal-close:hover {
    color: var(--stj-text);
}

.stj-modal-close svg {
    fill: none;
    height: 1rem;
    stroke: currentColor;
    stroke-linecap: round;
    stroke-linejoin: round;
    stroke-width: 2;
    width: 1rem;
}

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

.stj-field-error {
    color: #b91c1c;
    display: block;
    font-size: 0.78rem;
    margin-top: 0.35rem;
}

.stj-status {
    border-radius: 999px;
    display: inline-flex;
    font-size: 0.72rem;
    font-weight: 700;
    line-height: 1;
    padding: 0.35rem 0.55rem;
}

.stj-status-pending {
    background: #fef3c7;
    color: #92400e;
}

.stj-status-sent {
    background: #dcfce7;
    color: #166534;
}

.stj-status-error {
    background: #fee2e2;
    color: #991b1b;
}

.stj-status-muted {
    background: #e5e7eb;
    color: #374151;
}
</style>
