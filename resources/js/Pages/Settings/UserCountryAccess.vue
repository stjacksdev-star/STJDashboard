<script setup>
import { Head } from '@inertiajs/vue3';
import DataTable from 'datatables.net-vue3';
import DataTablesCore from 'datatables.net-dt';
import { computed, onMounted, ref } from 'vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';

DataTable.use(DataTablesCore);

const loading = ref(true);
const saving = ref(false);
const deletingId = ref(null);
const error = ref('');
const success = ref('');
const formError = ref('');
const errors = ref({});
const assignments = ref([]);
const countries = ref([]);
const currentUser = ref(null);
const userSearch = ref('');
const userResults = ref([]);
const usersLoading = ref(false);
const tableKey = ref(0);
const showModal = ref(false);
const editingUser = ref(null);
const form = ref(defaultForm());

const columns = [
    { data: 'casUserId', title: 'ID CAS', width: '90px' },
    { data: 'nameLabel', title: 'Usuario' },
    { data: 'emailLabel', title: 'Correo' },
    { data: 'countriesLabel', title: 'Paises', orderable: false },
    { data: 'actions', title: 'Acciones', orderable: false, searchable: false, className: 'dt-actions', width: '90px' },
];

const dataTableOptions = {
    pageLength: 10,
    lengthMenu: [10, 25, 50, 100],
    order: [[1, 'asc']],
    autoWidth: false,
    language: {
        search: 'Buscar:',
        lengthMenu: 'Mostrar _MENU_ registros',
        info: 'Mostrando _START_ a _END_ de _TOTAL_ usuarios',
        infoEmpty: 'Sin usuarios',
        infoFiltered: '(filtrado de _MAX_ registros)',
        zeroRecords: 'No se encontraron usuarios',
        emptyTable: 'No hay asignaciones disponibles',
        paginate: {
            first: 'Primero',
            last: 'Ultimo',
            next: 'Siguiente',
            previous: 'Anterior',
        },
    },
};

const rows = computed(() =>
    assignments.value.map((assignment) => ({
        ...assignment,
        nameLabel: assignment.name || assignment.username || 'N/D',
        emailLabel: assignment.email || assignment.username || 'N/D',
        countriesLabel: countryBadgesHtml(assignment.countries),
        actions: actionsHtml(assignment.casUserId),
    })),
);

onMounted(fetchAssignments);

async function fetchAssignments() {
    loading.value = true;
    error.value = '';

    try {
        const response = await window.axios.get('/dashboard-api/user-country-access');
        assignments.value = response.data.data?.assignments || [];
        countries.value = response.data.data?.countries || [];
        currentUser.value = response.data.data?.currentUser || null;
        tableKey.value += 1;
    } catch (exception) {
        error.value = exception.response?.data?.message || 'No fue posible cargar las asignaciones.';
    } finally {
        loading.value = false;
    }
}

async function searchUsers() {
    usersLoading.value = true;
    formError.value = '';

    try {
        const response = await window.axios.get('/dashboard-api/user-country-access/users', {
            params: {
                search: userSearch.value || undefined,
            },
        });

        userResults.value = response.data.data?.users || [];
    } catch (exception) {
        formError.value = exception.response?.data?.message || 'No fue posible buscar usuarios.';
    } finally {
        usersLoading.value = false;
    }
}

async function saveAssignment() {
    saving.value = true;
    formError.value = '';
    errors.value = {};
    success.value = '';

    try {
        const response = await window.axios.post('/dashboard-api/user-country-access', normalizePayload(form.value));
        success.value = response.data.message || 'Asignacion guardada correctamente.';
        showModal.value = false;
        editingUser.value = null;
        form.value = defaultForm();
        await fetchAssignments();
    } catch (exception) {
        formError.value = exception.response?.data?.message
            || firstError(exception.response?.data?.errors)
            || 'No fue posible guardar la asignacion.';
        errors.value = exception.response?.data?.errors || {};
    } finally {
        saving.value = false;
    }
}

async function removeCountry(country) {
    if (! country.assignmentId || ! window.confirm(`Remover ${country.code} de este usuario?`)) {
        return;
    }

    deletingId.value = country.assignmentId;
    error.value = '';
    success.value = '';

    try {
        const response = await window.axios.delete(`/dashboard-api/user-country-access/${country.assignmentId}`);
        success.value = response.data.message || 'Pais removido correctamente.';
        await fetchAssignments();
    } catch (exception) {
        error.value = firstError(exception.response?.data?.errors)
            || exception.response?.data?.message
            || 'No fue posible remover el pais.';
    } finally {
        deletingId.value = null;
    }
}

function openCreateModal() {
    editingUser.value = null;
    form.value = defaultForm();
    userSearch.value = '';
    userResults.value = [];
    formError.value = '';
    errors.value = {};
    showModal.value = true;
}

function openCurrentUserModal() {
    openCreateModal();

    if (!currentUser.value) {
        return;
    }

    form.value = {
        ...form.value,
        casUserId: currentUser.value.casUserId || '',
        username: currentUser.value.username || '',
        email: currentUser.value.email || '',
        name: currentUser.value.name || '',
        countryIds: (currentUser.value.allowedCountries || []).map((country) => Number(country.id)),
        defaultCountryId: currentUser.value.baseCountry?.id || '',
    };
}

function openEditModal(assignment) {
    editingUser.value = assignment;
    userSearch.value = '';
    userResults.value = [];
    form.value = {
        casUserId: assignment.casUserId || '',
        username: assignment.username || '',
        email: assignment.email || '',
        name: assignment.name || '',
        countryIds: assignment.countries.map((country) => Number(country.id)),
        defaultCountryId: assignment.countries.find((country) => country.isDefault)?.id || assignment.countries[0]?.id || '',
    };
    formError.value = '';
    errors.value = {};
    showModal.value = true;
}

function selectUser(user) {
    form.value = {
        ...form.value,
        casUserId: user.id || '',
        username: user.email || '',
        email: user.email || '',
        name: user.name || '',
    };
    userSearch.value = `${user.name || 'Usuario'} ${user.email || ''}`.trim();
    userResults.value = [];
}

function handleTableAction(event) {
    const button = event.target.closest('[data-user-country-action]');

    if (! button) {
        return;
    }

    const assignment = assignments.value.find((item) => Number(item.casUserId) === Number(button.dataset.casUserId));

    if (! assignment) {
        return;
    }

    if (button.dataset.userCountryAction === 'edit') {
        openEditModal(assignment);
    }
}

function defaultForm() {
    return {
        casUserId: '',
        username: '',
        email: '',
        name: '',
        countryIds: [],
        defaultCountryId: '',
    };
}

function normalizePayload(values) {
    const selectedCountries = countries.value
        .filter((country) => values.countryIds.map(Number).includes(Number(country.id)))
        .map((country) => ({
            id: Number(country.id),
            code: country.code,
            name: country.name,
        }));

    return {
        casUserId: Number(values.casUserId),
        username: values.username || null,
        email: values.email || null,
        name: values.name || null,
        countries: selectedCountries,
        defaultCountryId: values.defaultCountryId || selectedCountries[0]?.id || null,
    };
}

function toggleCountry(country) {
    const id = Number(country.id);
    const current = form.value.countryIds.map(Number);

    if (current.includes(id)) {
        form.value.countryIds = current.filter((countryId) => countryId !== id);

        if (Number(form.value.defaultCountryId) === id) {
            form.value.defaultCountryId = form.value.countryIds[0] || '';
        }

        return;
    }

    form.value.countryIds = [...current, id];

    if (!form.value.defaultCountryId) {
        form.value.defaultCountryId = id;
    }
}

function countryChecked(country) {
    return form.value.countryIds.map(Number).includes(Number(country.id));
}

function countryBadgesHtml(items) {
    return items
        .map((country) => `<span class="stj-country-pill ${country.isDefault ? 'stj-country-pill-default' : ''}">${escapeHtml(country.code)}${country.isDefault ? ' *' : ''}</span>`)
        .join(' ');
}

function actionsHtml(casUserId) {
    return `
        <div class="stj-row-actions">
            <button type="button" class="stj-icon-btn" data-user-country-action="edit" data-cas-user-id="${casUserId}" title="Editar">
                <svg viewBox="0 0 24 24"><path d="M4 20h4l10.5-10.5a2.8 2.8 0 0 0-4-4L4 16v4zM13.5 6.5l4 4"/></svg>
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

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}
</script>

<template>
    <AdminLayout>
        <Head title="Configuracion / Paises por usuario" />

        <div class="mx-auto max-w-7xl space-y-6">
            <section class="app-surface rounded-lg border p-5 shadow-sm" style="border-color: var(--stj-border);">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="app-primary-text text-xs font-semibold uppercase">Configuracion</p>
                        <h1 class="app-text mt-2 text-2xl font-bold">Paises por usuario</h1>
                        <p class="app-muted mt-2 max-w-3xl text-sm">
                            Asigna paises adicionales a usuarios autenticados por CAS. El pais base de la sesion siempre queda permitido por defecto.
                        </p>
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row">
                        <button
                            type="button"
                            class="inline-flex h-11 items-center justify-center rounded-md border px-4 text-sm font-semibold transition"
                            style="border-color: var(--stj-border); color: var(--stj-text);"
                            @click="openCurrentUserModal"
                        >
                            Usar mi usuario
                        </button>
                        <button
                            type="button"
                            class="inline-flex h-11 items-center justify-center rounded-md bg-blue-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700"
                            @click="openCreateModal"
                        >
                            Nueva asignacion
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
                        <h2 class="app-text text-lg font-semibold">Asignaciones</h2>
                        <p class="app-muted text-sm">{{ assignments.length }} usuarios con paises asignados</p>
                    </div>
                </div>

                <div class="p-5">
                    <div v-if="loading" class="app-muted rounded-md border p-4 text-sm" style="border-color: var(--stj-border);">
                        Cargando asignaciones...
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
                            <p class="app-primary-text text-xs font-semibold uppercase">Usuario CAS</p>
                            <h2 class="app-text mt-1 text-xl font-bold">
                                {{ editingUser ? 'Editar asignacion' : 'Nueva asignacion' }}
                            </h2>
                        </div>

                        <button type="button" class="stj-modal-close" title="Cerrar" @click="showModal = false">
                            <svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18" /></svg>
                        </button>
                    </div>

                    <form class="max-h-[78vh] overflow-y-auto p-5" @submit.prevent="saveAssignment">
                        <div v-if="formError" class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                            {{ formError }}
                        </div>

                        <section class="rounded-md border p-4" style="border-color: var(--stj-border);">
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-end">
                                <label class="block lg:flex-1">
                                    <span class="app-muted text-sm font-medium">Buscar usuario CAS</span>
                                    <input
                                        v-model.trim="userSearch"
                                        maxlength="120"
                                        type="search"
                                        class="stj-input mt-2"
                                        placeholder="Nombre, correo o ID"
                                        @keyup.enter.prevent="searchUsers"
                                    >
                                </label>

                                <button
                                    type="button"
                                    class="inline-flex h-11 items-center justify-center rounded-md border px-4 text-sm font-semibold transition"
                                    style="border-color: var(--stj-border); color: var(--stj-text);"
                                    :disabled="usersLoading"
                                    @click="searchUsers"
                                >
                                    {{ usersLoading ? 'Buscando...' : 'Buscar' }}
                                </button>
                            </div>

                            <div v-if="userResults.length" class="mt-4 grid gap-2">
                                <button
                                    v-for="user in userResults"
                                    :key="user.id"
                                    type="button"
                                    class="stj-user-result"
                                    @click="selectUser(user)"
                                >
                                    <span class="min-w-0">
                                        <span class="app-text block truncate text-sm font-semibold">{{ user.name || 'Sin nombre' }}</span>
                                        <span class="app-muted block truncate text-xs">{{ user.email || 'Sin correo' }} · ID {{ user.id }} · {{ user.department || 'N/D' }}</span>
                                    </span>
                                    <span class="stj-user-status">{{ user.status || 'N/D' }}</span>
                                </button>
                            </div>
                        </section>

                        <div class="mt-4 grid gap-4 lg:grid-cols-4">
                            <label class="block">
                                <span class="app-muted text-sm font-medium">ID CAS</span>
                                <input v-model="form.casUserId" required min="1" readonly type="number" class="stj-input stj-input-readonly mt-2">
                                <span v-if="fieldError('casUserId')" class="stj-field-error">{{ fieldError('casUserId') }}</span>
                            </label>

                            <label class="block">
                                <span class="app-muted text-sm font-medium">Usuario</span>
                                <input v-model.trim="form.username" maxlength="120" readonly type="text" class="stj-input stj-input-readonly mt-2">
                                <span v-if="fieldError('username')" class="stj-field-error">{{ fieldError('username') }}</span>
                            </label>

                            <label class="block">
                                <span class="app-muted text-sm font-medium">Correo</span>
                                <input v-model.trim="form.email" maxlength="255" readonly type="email" class="stj-input stj-input-readonly mt-2">
                                <span v-if="fieldError('email')" class="stj-field-error">{{ fieldError('email') }}</span>
                            </label>

                            <label class="block">
                                <span class="app-muted text-sm font-medium">Nombre</span>
                                <input v-model.trim="form.name" maxlength="180" readonly type="text" class="stj-input stj-input-readonly mt-2">
                                <span v-if="fieldError('name')" class="stj-field-error">{{ fieldError('name') }}</span>
                            </label>
                        </div>

                        <section class="mt-5 rounded-md border p-4" style="border-color: var(--stj-border);">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h3 class="app-text text-sm font-semibold">Paises permitidos</h3>
                                    <p class="app-muted mt-1 text-xs">Selecciona uno o varios paises. Uno puede quedar marcado como default.</p>
                                </div>
                                <span class="app-muted text-xs">{{ form.countryIds.length }} seleccionados</span>
                            </div>

                            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                <div
                                    v-for="country in countries"
                                    :key="country.id"
                                    class="stj-country-option"
                                >
                                    <label class="flex min-w-0 flex-1 items-center gap-3">
                                        <input
                                            :checked="countryChecked(country)"
                                            type="checkbox"
                                            @change="toggleCountry(country)"
                                        >
                                        <span class="min-w-0">
                                            <span class="app-text block truncate text-sm font-semibold">{{ country.code }} - {{ country.name }}</span>
                                            <span class="app-muted text-xs">ID {{ country.id }}</span>
                                        </span>
                                    </label>

                                    <label class="stj-default-radio" title="Pais default">
                                        <input
                                            v-model="form.defaultCountryId"
                                            :value="country.id"
                                            :disabled="!countryChecked(country)"
                                            type="radio"
                                        >
                                        Default
                                    </label>
                                </div>
                            </div>
                            <span v-if="fieldError('countries')" class="stj-field-error">{{ fieldError('countries') }}</span>
                        </section>

                        <section v-if="editingUser?.countries?.length" class="mt-5 rounded-md border p-4" style="border-color: var(--stj-border);">
                            <h3 class="app-text text-sm font-semibold">Remover pais individual</h3>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <button
                                    v-for="country in editingUser.countries"
                                    :key="country.assignmentId"
                                    type="button"
                                    class="rounded-md border px-3 py-2 text-xs font-semibold"
                                    style="border-color: var(--stj-border); color: var(--stj-text);"
                                    :disabled="deletingId === country.assignmentId"
                                    @click="removeCountry(country)"
                                >
                                    {{ deletingId === country.assignmentId ? 'Removiendo...' : `Remover ${country.code}` }}
                                </button>
                            </div>
                        </section>

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
                                :disabled="saving || deletingId !== null"
                            >
                                {{ saving ? 'Guardando...' : 'Guardar asignacion' }}
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
    max-width: 1080px;
    overflow: hidden;
    width: min(1080px, 100%);
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

.stj-input-readonly {
    opacity: 0.72;
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

.stj-country-pill {
    background: #e5e7eb;
    border-radius: 999px;
    color: #374151;
    display: inline-flex;
    font-size: 0.72rem;
    font-weight: 800;
    margin: 0.1rem;
    padding: 0.2rem 0.5rem;
}

.stj-country-pill-default {
    background: #dbeafe;
    color: #1d4ed8;
}

.stj-country-option {
    align-items: center;
    border: 1px solid var(--stj-border);
    border-radius: 0.375rem;
    display: flex;
    gap: 0.75rem;
    justify-content: space-between;
    min-height: 4.25rem;
    padding: 0.75rem;
}

.stj-default-radio {
    align-items: center;
    color: var(--stj-muted);
    display: inline-flex;
    flex-shrink: 0;
    font-size: 0.75rem;
    font-weight: 700;
    gap: 0.35rem;
}

.stj-user-result {
    align-items: center;
    border: 1px solid var(--stj-border);
    border-radius: 0.375rem;
    display: flex;
    gap: 1rem;
    justify-content: space-between;
    padding: 0.75rem;
    text-align: left;
}

.stj-user-result:hover {
    background: var(--stj-surface-hover);
}

.stj-user-status {
    background: #dcfce7;
    border-radius: 999px;
    color: #166534;
    flex-shrink: 0;
    font-size: 0.68rem;
    font-weight: 800;
    padding: 0.2rem 0.55rem;
}
</style>
