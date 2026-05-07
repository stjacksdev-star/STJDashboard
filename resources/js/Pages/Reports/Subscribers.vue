<script setup>
import { Head } from '@inertiajs/vue3';
import DataTable from 'datatables.net-vue3';
import DataTablesCore from 'datatables.net-dt';
import { computed, onMounted, ref } from 'vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';

DataTable.use(DataTablesCore);

const loading = ref(true);
const saving = ref(false);
const deleting = ref(false);
const error = ref('');
const success = ref('');
const formError = ref('');
const errors = ref({});
const subscribers = ref([]);
const countries = ref([]);
const selectedCountry = ref('SV');
const tableKey = ref(0);
const showModal = ref(false);
const editingSubscriber = ref(null);
const form = ref(defaultForm());

const columns = [
    { data: 'id', title: 'ID', width: '80px' },
    { data: 'email', title: 'Email' },
    { data: 'subscribedAtLabel', title: 'Fecha de suscripcion' },
    { data: 'countryLabel', title: 'Pais', width: '140px' },
    { data: 'actions', title: 'Acciones', orderable: false, searchable: false, className: 'dt-actions', width: '96px' },
];

const dataTableOptions = {
    pageLength: 25,
    lengthMenu: [10, 25, 50, 100],
    order: [[2, 'desc']],
    autoWidth: false,
    language: {
        search: 'Buscar:',
        lengthMenu: 'Mostrar _MENU_ registros',
        info: 'Mostrando _START_ a _END_ de _TOTAL_ suscriptores',
        infoEmpty: 'Sin suscriptores',
        infoFiltered: '(filtrado de _MAX_ registros)',
        zeroRecords: 'No se encontraron suscriptores',
        emptyTable: 'No hay suscriptores disponibles',
        paginate: {
            first: 'Primero',
            last: 'Ultimo',
            next: 'Siguiente',
            previous: 'Anterior',
        },
    },
};

const rows = computed(() =>
    subscribers.value.map((subscriber) => ({
        ...subscriber,
        subscribedAtLabel: formatDateTime(subscriber.subscribedAt),
        countryLabel: `${subscriber.country || 'N/D'} - ${subscriber.countryName || 'N/D'}`,
        actions: actionsHtml(subscriber.id),
    })),
);

onMounted(fetchSubscribers);

async function fetchSubscribers() {
    loading.value = true;
    error.value = '';

    try {
        const response = await window.axios.get('/dashboard-api/subscribers', {
            params: {
                country: selectedCountry.value || '',
            },
        });

        subscribers.value = response.data.data?.subscribers || [];
        countries.value = response.data.data?.options?.countries || defaultCountries();
        tableKey.value += 1;
    } catch (exception) {
        error.value = exception.response?.data?.message || 'No fue posible cargar los suscriptores.';
    } finally {
        loading.value = false;
    }
}

async function saveSubscriber() {
    saving.value = true;
    formError.value = '';
    errors.value = {};
    success.value = '';

    const url = editingSubscriber.value
        ? `/dashboard-api/subscribers/${editingSubscriber.value.id}`
        : '/dashboard-api/subscribers';

    try {
        const response = await window.axios.post(url, normalizePayload(form.value));
        success.value = response.data.message || 'Suscriptor guardado correctamente.';
        showModal.value = false;
        editingSubscriber.value = null;
        form.value = defaultForm();
        await fetchSubscribers();
    } catch (exception) {
        formError.value = exception.response?.data?.message
            || firstError(exception.response?.data?.errors)
            || 'No fue posible guardar el suscriptor.';
        errors.value = exception.response?.data?.errors || {};
    } finally {
        saving.value = false;
    }
}

async function deleteSubscriber(subscriber) {
    if (! window.confirm(`Eliminar el suscriptor ${subscriber.email}?`)) {
        return;
    }

    deleting.value = true;
    error.value = '';
    success.value = '';

    try {
        const response = await window.axios.delete(`/dashboard-api/subscribers/${subscriber.id}`);
        success.value = response.data.message || 'Suscriptor eliminado correctamente.';
        await fetchSubscribers();
    } catch (exception) {
        error.value = firstError(exception.response?.data?.errors)
            || exception.response?.data?.message
            || 'No fue posible eliminar el suscriptor.';
    } finally {
        deleting.value = false;
    }
}

function openCreateModal() {
    editingSubscriber.value = null;
    form.value = {
        ...defaultForm(),
        country: selectedCountry.value && selectedCountry.value !== 'TODO' ? selectedCountry.value : 'SV',
    };
    formError.value = '';
    errors.value = {};
    showModal.value = true;
}

function openEditModal(subscriber) {
    editingSubscriber.value = subscriber;
    form.value = {
        email: subscriber.email || '',
        country: subscriber.country || 'SV',
        subscribedAt: toDateTimeLocal(subscriber.subscribedAt),
    };
    formError.value = '';
    errors.value = {};
    showModal.value = true;
}

function handleTableAction(event) {
    const button = event.target.closest('[data-subscriber-action]');

    if (! button) {
        return;
    }

    const subscriber = subscribers.value.find((item) => item.id === Number(button.dataset.subscriberId));

    if (! subscriber) {
        return;
    }

    if (button.dataset.subscriberAction === 'edit') {
        openEditModal(subscriber);
        return;
    }

    if (button.dataset.subscriberAction === 'delete') {
        deleteSubscriber(subscriber);
    }
}

function defaultForm() {
    return {
        email: '',
        country: 'SV',
        subscribedAt: '',
    };
}

function normalizePayload(values) {
    return {
        email: values.email,
        country: values.country,
        subscribedAt: values.subscribedAt || null,
    };
}

function defaultCountries() {
    return [
        { code: 'SV', name: 'El Salvador' },
        { code: 'GT', name: 'Guatemala' },
        { code: 'CR', name: 'Costa Rica' },
        { code: 'NI', name: 'Nicaragua' },
        { code: 'HN', name: 'Honduras' },
    ];
}

function actionsHtml(id) {
    return `
        <div class="stj-row-actions">
            <button type="button" class="stj-icon-btn" data-subscriber-action="edit" data-subscriber-id="${id}" title="Editar">
                <svg viewBox="0 0 24 24"><path d="M4 20h4l10.5-10.5a2.8 2.8 0 0 0-4-4L4 16v4zM13.5 6.5l4 4"/></svg>
            </button>
            <button type="button" class="stj-icon-btn stj-icon-btn-danger" data-subscriber-action="delete" data-subscriber-id="${id}" title="Eliminar">
                <svg viewBox="0 0 24 24"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 14h10l1-14M9 7V4h6v3"/></svg>
            </button>
        </div>
    `;
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

function toDateTimeLocal(value) {
    if (! value) {
        return '';
    }

    return String(value).replace(' ', 'T').slice(0, 16);
}
</script>

<template>
    <AdminLayout>
        <Head title="Reportes / Suscriptores" />

        <div class="mx-auto max-w-7xl space-y-6">
            <section class="app-surface rounded-lg border p-5 shadow-sm" style="border-color: var(--stj-border);">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="app-primary-text text-xs font-semibold uppercase">Reportes</p>
                        <h1 class="app-text mt-2 text-2xl font-bold">Suscriptores</h1>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-[220px_auto_auto]">
                        <label class="block">
                            <span class="app-muted text-sm font-medium">Pais</span>
                            <select v-model="selectedCountry" class="stj-input mt-2">
                                <option value="TODO">Todos</option>
                                <option v-for="country in countries" :key="country.code" :value="country.code">
                                    {{ country.code }} - {{ country.name }}
                                </option>
                            </select>
                        </label>

                        <button
                            type="button"
                            class="mt-7 inline-flex h-11 items-center justify-center rounded-md border px-4 text-sm font-semibold transition"
                            style="border-color: var(--stj-border); color: var(--stj-text);"
                            :disabled="loading"
                            @click="fetchSubscribers"
                        >
                            Buscar
                        </button>

                        <button
                            type="button"
                            class="mt-7 inline-flex h-11 items-center justify-center rounded-md bg-blue-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700"
                            @click="openCreateModal"
                        >
                            Nuevo suscriptor
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
                        <h2 class="app-text text-lg font-semibold">Listado</h2>
                        <p class="app-muted text-sm">{{ subscribers.length }} suscriptores cargados</p>
                    </div>
                </div>

                <div class="p-5">
                    <div v-if="loading" class="app-muted rounded-md border p-4 text-sm" style="border-color: var(--stj-border);">
                        Cargando suscriptores...
                    </div>

                    <div v-else class="overflow-x-auto" @click="handleTableAction">
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
                            <p class="app-primary-text text-xs font-semibold uppercase">Suscriptor</p>
                            <h2 class="app-text mt-1 text-xl font-bold">
                                {{ editingSubscriber ? 'Editar suscriptor' : 'Nuevo suscriptor' }}
                            </h2>
                        </div>

                        <button type="button" class="stj-modal-close" title="Cerrar" @click="showModal = false">
                            <svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18" /></svg>
                        </button>
                    </div>

                    <form class="p-5" @submit.prevent="saveSubscriber">
                        <div v-if="formError" class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                            {{ formError }}
                        </div>

                        <div class="grid gap-4 lg:grid-cols-2">
                            <label class="block">
                                <span class="app-muted text-sm font-medium">Email</span>
                                <input v-model.trim="form.email" required maxlength="255" type="email" class="stj-input mt-2">
                                <span v-if="fieldError('email')" class="stj-field-error">{{ fieldError('email') }}</span>
                            </label>

                            <label class="block">
                                <span class="app-muted text-sm font-medium">Pais</span>
                                <select v-model="form.country" required class="stj-input mt-2">
                                    <option v-for="country in countries" :key="country.code" :value="country.code">
                                        {{ country.code }} - {{ country.name }}
                                    </option>
                                </select>
                                <span v-if="fieldError('country')" class="stj-field-error">{{ fieldError('country') }}</span>
                            </label>
                        </div>

                        <label class="mt-4 block">
                            <span class="app-muted text-sm font-medium">Fecha de suscripcion</span>
                            <input v-model="form.subscribedAt" type="datetime-local" class="stj-input mt-2">
                            <span v-if="fieldError('subscribedAt')" class="stj-field-error">{{ fieldError('subscribedAt') }}</span>
                        </label>

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
                                :disabled="saving || deleting"
                            >
                                {{ saving ? 'Guardando...' : 'Guardar suscriptor' }}
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
    max-width: 760px;
    overflow: hidden;
    width: min(760px, 100%);
}

.stj-modal-close,
.stj-icon-btn {
    align-items: center;
    border: 1px solid var(--stj-border);
    border-radius: 0.375rem;
    display: inline-flex;
    justify-content: center;
}

.stj-modal-close {
    color: var(--stj-muted);
    height: 2.25rem;
    width: 2.25rem;
}

.stj-modal-close:hover {
    color: var(--stj-text);
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

.stj-row-actions {
    display: inline-flex;
    gap: 0.4rem;
}

.stj-icon-btn {
    color: var(--stj-primary);
    height: 2rem;
    transition: background-color 0.16s ease, color 0.16s ease;
    width: 2rem;
}

.stj-icon-btn:hover {
    background: var(--stj-primary-soft);
}

.stj-icon-btn-danger {
    color: #dc2626;
}

.stj-icon-btn-danger:hover {
    background: #fee2e2;
}

.stj-icon-btn svg,
.stj-modal-close svg {
    fill: none;
    height: 1rem;
    pointer-events: none;
    stroke: currentColor;
    stroke-linecap: round;
    stroke-linejoin: round;
    stroke-width: 2;
    width: 1rem;
}
</style>
