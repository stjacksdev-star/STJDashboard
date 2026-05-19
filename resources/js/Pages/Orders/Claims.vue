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
const formError = ref('');
const errors = ref({});
const success = ref('');
const claims = ref([]);
const options = ref({
    types: [],
    origins: [],
    areas: [],
    statuses: [],
});
const filters = ref({
    search: '',
    status: '',
    type: '',
});
const tableKey = ref(0);
const showModal = ref(false);
const editingClaim = ref(null);
const form = ref(defaultForm());

const columns = [
    { data: 'managementNumber', title: 'Gestion', width: '145px' },
    { data: 'registeredLabel', title: 'Registro', width: '145px' },
    { data: 'customerLabel', title: 'Cliente' },
    { data: 'orderLabel', title: 'Pedido/STJ', width: '120px' },
    { data: 'typeLabel', title: 'Tipo', width: '145px' },
    { data: 'responsibleAreaLabel', title: 'Area', width: '140px' },
    { data: 'statusHtml', title: 'Estado', orderable: false, width: '130px' },
    { data: 'actions', title: 'Acciones', orderable: false, searchable: false, className: 'dt-actions', width: '96px' },
];

const dataTableOptions = {
    pageLength: 10,
    lengthMenu: [10, 25, 50, 100],
    order: [[1, 'desc']],
    autoWidth: false,
    language: {
        search: 'Buscar:',
        lengthMenu: 'Mostrar _MENU_ registros',
        info: 'Mostrando _START_ a _END_ de _TOTAL_ reclamos',
        infoEmpty: 'Sin reclamos',
        infoFiltered: '(filtrado de _MAX_ registros)',
        zeroRecords: 'No se encontraron reclamos',
        emptyTable: 'No hay reclamos disponibles',
        paginate: {
            first: 'Primero',
            last: 'Ultimo',
            next: 'Siguiente',
            previous: 'Anterior',
        },
    },
};

const rows = computed(() =>
    claims.value.map((claim) => ({
        ...claim,
        registeredLabel: formatDate(claim.registeredAt),
        customerLabel: customerHtml(claim),
        orderLabel: orderHtml(claim),
        statusHtml: statusHtml(claim),
        actions: actionsHtml(claim.id),
    })),
);

onMounted(fetchClaims);

async function fetchClaims() {
    loading.value = true;
    error.value = '';

    try {
        const response = await window.axios.get('/dashboard-api/claims', {
            params: cleanParams(filters.value),
        });
        claims.value = response.data.data?.claims || [];
        options.value = {
            ...options.value,
            ...(response.data.data?.options || {}),
        };
        tableKey.value += 1;
    } catch (exception) {
        error.value = exception.response?.data?.message || 'No fue posible cargar los reclamos.';
    } finally {
        loading.value = false;
    }
}

async function saveClaim() {
    saving.value = true;
    formError.value = '';
    errors.value = {};
    success.value = '';

    const creating = ! editingClaim.value;
    const payload = normalizePayload(form.value);
    const url = editingClaim.value
        ? `/dashboard-api/claims/${editingClaim.value.id}`
        : '/dashboard-api/claims';

    try {
        const response = await window.axios.post(url, payload);
        const savedClaim = response.data.data || {};
        success.value = response.data.message || 'Reclamo guardado correctamente.';
        showModal.value = false;
        editingClaim.value = null;
        form.value = defaultForm();
        await fetchClaims();

        if (creating && savedClaim.managementNumber) {
            window.prompt('Numero de gestion generado. Puede copiarlo ahora:', savedClaim.managementNumber);
        }
    } catch (exception) {
        formError.value = exception.response?.data?.message
            || firstError(exception.response?.data?.errors)
            || 'No fue posible guardar el reclamo.';
        errors.value = exception.response?.data?.errors || {};
    } finally {
        saving.value = false;
    }
}

async function deleteClaim(claim) {
    if (! window.confirm(`Eliminar el reclamo ${claim.managementNumber}?`)) {
        return;
    }

    deleting.value = true;
    error.value = '';
    success.value = '';

    try {
        const response = await window.axios.delete(`/dashboard-api/claims/${claim.id}`);
        success.value = response.data.message || 'Reclamo eliminado correctamente.';
        await fetchClaims();
    } catch (exception) {
        error.value = firstError(exception.response?.data?.errors)
            || exception.response?.data?.message
            || 'No fue posible eliminar el reclamo.';
    } finally {
        deleting.value = false;
    }
}

function openCreateModal() {
    editingClaim.value = null;
    form.value = defaultForm();
    formError.value = '';
    errors.value = {};
    showModal.value = true;
}

function openEditModal(claim) {
    editingClaim.value = claim;
    form.value = {
        managementNumber: claim.managementNumber || '',
        registeredAt: toInputDateTime(claim.registeredAt),
        stj: claim.stj || '',
        customerName: claim.customerName || '',
        customerEmail: claim.customerEmail || '',
        customerPhone: claim.customerPhone || '',
        customerDui: claim.customerDui || '',
        type: claim.type || 'otro',
        origin: claim.origin || 'web',
        responsibleArea: claim.responsibleArea || 'atencion_cliente',
        store: claim.store || '',
        description: claim.description || '',
        response: claim.response || '',
        status: claim.status || 'recibido',
        rejectionReason: claim.rejectionReason || '',
        resolvedAt: toInputDateTime(claim.resolvedAt),
        closedAt: toInputDateTime(claim.closedAt),
        assignedTo: claim.assignedTo ?? '',
    };
    formError.value = '';
    errors.value = {};
    showModal.value = true;
}

function handleTableAction(event) {
    const button = event.target.closest('[data-claim-action]');

    if (! button) {
        return;
    }

    const claim = claims.value.find((item) => item.id === Number(button.dataset.claimId));

    if (! claim) {
        return;
    }

    if (button.dataset.claimAction === 'edit') {
        openEditModal(claim);
        return;
    }

    if (button.dataset.claimAction === 'delete') {
        deleteClaim(claim);
    }
}

function defaultForm() {
    return {
        managementNumber: '',
        registeredAt: '',
        stj: '',
        customerName: '',
        customerEmail: '',
        customerPhone: '',
        customerDui: '',
        type: 'otro',
        origin: 'web',
        responsibleArea: 'atencion_cliente',
        store: '',
        description: '',
        response: '',
        status: 'recibido',
        rejectionReason: '',
        resolvedAt: '',
        closedAt: '',
        assignedTo: '',
    };
}

function normalizePayload(values) {
    return {
        ...values,
        assignedTo: values.assignedTo === '' ? null : Number(values.assignedTo),
        registeredAt: values.registeredAt || null,
        resolvedAt: values.resolvedAt || null,
        closedAt: values.closedAt || null,
    };
}

function cleanParams(values) {
    return Object.fromEntries(
        Object.entries(values).filter(([, value]) => value !== null && value !== undefined && value !== ''),
    );
}

function customerHtml(claim) {
    const contact = [claim.customerEmail, claim.customerPhone]
        .filter(Boolean)
        .map(escapeHtml)
        .join(' / ');

    return `
        <div class="stj-claim-customer">
            <strong>${escapeHtml(claim.customerName || 'N/D')}</strong>
            <span>${contact || 'Sin contacto'}</span>
        </div>
    `;
}

function orderHtml(claim) {
    const values = [];

    if (claim.orderId) {
        values.push(`#${escapeHtml(claim.orderId)}`);
    }

    if (claim.stj) {
        values.push(escapeHtml(claim.stj));
    }

    return values.length ? values.join('<br>') : 'N/D';
}

function statusHtml(claim) {
    return `<span class="stj-status stj-status-${escapeHtml(claim.status)}">${escapeHtml(claim.statusLabel || claim.status)}</span>`;
}

function actionsHtml(id) {
    return `
        <div class="stj-row-actions">
            <button type="button" class="stj-icon-btn" data-claim-action="edit" data-claim-id="${id}" title="Editar">
                <svg viewBox="0 0 24 24"><path d="M4 20h4l10.5-10.5a2.8 2.8 0 0 0-4-4L4 16v4zM13.5 6.5l4 4"/></svg>
            </button>
            <button type="button" class="stj-icon-btn stj-icon-btn-danger" data-claim-action="delete" data-claim-id="${id}" title="Eliminar">
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

function formatDate(value) {
    if (! value) {
        return 'N/D';
    }

    return String(value).replace('T', ' ').slice(0, 16);
}

function toInputDateTime(value) {
    if (! value) {
        return '';
    }

    return String(value).replace(' ', 'T').slice(0, 16);
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
        <Head title="Pedidos / Reclamos" />

        <div class="mx-auto max-w-7xl space-y-6">
            <section class="app-surface rounded-lg border p-5 shadow-sm" style="border-color: var(--stj-border);">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="app-primary-text text-xs font-semibold uppercase">Pedidos</p>
                        <h1 class="app-text mt-2 text-2xl font-bold">Reclamos</h1>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3 lg:min-w-[680px]">
                        <label class="block">
                            <span class="app-muted text-sm font-medium">Busqueda</span>
                            <input v-model.trim="filters.search" type="search" class="stj-input mt-2" placeholder="Gestion, cliente, correo, DUI">
                        </label>

                        <label class="block">
                            <span class="app-muted text-sm font-medium">Estado</span>
                            <select v-model="filters.status" class="stj-input mt-2">
                                <option value="">Todos</option>
                                <option v-for="status in options.statuses" :key="status.value" :value="status.value">{{ status.label }}</option>
                            </select>
                        </label>

                        <label class="block">
                            <span class="app-muted text-sm font-medium">Tipo</span>
                            <select v-model="filters.type" class="stj-input mt-2">
                                <option value="">Todos</option>
                                <option v-for="type in options.types" :key="type.value" :value="type.value">{{ type.label }}</option>
                            </select>
                        </label>
                    </div>
                </div>

                <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        class="inline-flex h-11 items-center justify-center rounded-md border px-4 text-sm font-semibold transition"
                        style="border-color: var(--stj-border); color: var(--stj-text);"
                        @click="fetchClaims"
                    >
                        Filtrar
                    </button>

                    <button
                        type="button"
                        class="inline-flex h-11 items-center justify-center rounded-md bg-blue-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700"
                        @click="openCreateModal"
                    >
                        Nuevo reclamo
                    </button>
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
                        <p class="app-muted text-sm">{{ claims.length }} reclamos cargados</p>
                    </div>
                </div>

                <div class="p-5">
                    <div v-if="loading" class="app-muted rounded-md border p-4 text-sm" style="border-color: var(--stj-border);">
                        Cargando reclamos...
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
                            <p class="app-primary-text text-xs font-semibold uppercase">Reclamo</p>
                            <h2 class="app-text mt-1 text-xl font-bold">
                                {{ editingClaim ? 'Editar reclamo' : 'Nuevo reclamo' }}
                            </h2>
                        </div>

                        <button type="button" class="stj-modal-close" title="Cerrar" @click="showModal = false">
                            <svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18" /></svg>
                        </button>
                    </div>

                    <form class="max-h-[78vh] overflow-y-auto p-5" @submit.prevent="saveClaim">
                        <div v-if="formError" class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                            {{ formError }}
                        </div>

                        <div class="grid gap-4 lg:grid-cols-3">
                            <label class="block">
                                <span class="app-muted text-sm font-medium">Numero gestion</span>
                                <input v-model.trim="form.managementNumber" maxlength="30" type="text" class="stj-input mt-2" placeholder="Auto: STJ-REC-...">
                                <span v-if="fieldError('managementNumber')" class="stj-field-error">{{ fieldError('managementNumber') }}</span>
                            </label>

                            <label class="block">
                                <span class="app-muted text-sm font-medium">Fecha registro</span>
                                <input v-model="form.registeredAt" type="datetime-local" class="stj-input mt-2">
                                <span v-if="fieldError('registeredAt')" class="stj-field-error">{{ fieldError('registeredAt') }}</span>
                            </label>

                            <label class="block">
                                <span class="app-muted text-sm font-medium">STJ</span>
                                <input v-model.trim="form.stj" maxlength="50" type="text" class="stj-input mt-2">
                                <span v-if="fieldError('stj')" class="stj-field-error">{{ fieldError('stj') }}</span>
                            </label>
                        </div>

                        <div class="mt-4 grid gap-4 lg:grid-cols-4">
                            <label class="block lg:col-span-2">
                                <span class="app-muted text-sm font-medium">Cliente</span>
                                <input v-model.trim="form.customerName" required maxlength="150" type="text" class="stj-input mt-2">
                                <span v-if="fieldError('customerName')" class="stj-field-error">{{ fieldError('customerName') }}</span>
                            </label>

                            <label class="block">
                                <span class="app-muted text-sm font-medium">Correo</span>
                                <input v-model.trim="form.customerEmail" maxlength="150" type="email" class="stj-input mt-2">
                                <span v-if="fieldError('customerEmail')" class="stj-field-error">{{ fieldError('customerEmail') }}</span>
                            </label>

                            <label class="block">
                                <span class="app-muted text-sm font-medium">Telefono</span>
                                <input v-model.trim="form.customerPhone" maxlength="30" type="text" class="stj-input mt-2">
                                <span v-if="fieldError('customerPhone')" class="stj-field-error">{{ fieldError('customerPhone') }}</span>
                            </label>
                        </div>

                        <div class="mt-4 grid gap-4 lg:grid-cols-4">
                            <label class="block">
                                <span class="app-muted text-sm font-medium">DUI</span>
                                <input v-model.trim="form.customerDui" maxlength="20" type="text" class="stj-input mt-2">
                                <span v-if="fieldError('customerDui')" class="stj-field-error">{{ fieldError('customerDui') }}</span>
                            </label>

                            <label class="block">
                                <span class="app-muted text-sm font-medium">Tipo</span>
                                <select v-model="form.type" required class="stj-input mt-2">
                                    <option v-for="type in options.types" :key="type.value" :value="type.value">{{ type.label }}</option>
                                </select>
                                <span v-if="fieldError('type')" class="stj-field-error">{{ fieldError('type') }}</span>
                            </label>

                            <label class="block">
                                <span class="app-muted text-sm font-medium">Origen</span>
                                <select v-model="form.origin" required class="stj-input mt-2">
                                    <option v-for="origin in options.origins" :key="origin.value" :value="origin.value">{{ origin.label }}</option>
                                </select>
                                <span v-if="fieldError('origin')" class="stj-field-error">{{ fieldError('origin') }}</span>
                            </label>

                            <label class="block">
                                <span class="app-muted text-sm font-medium">Estado</span>
                                <select v-model="form.status" required class="stj-input mt-2">
                                    <option v-for="status in options.statuses" :key="status.value" :value="status.value">{{ status.label }}</option>
                                </select>
                                <span v-if="fieldError('status')" class="stj-field-error">{{ fieldError('status') }}</span>
                            </label>
                        </div>

                        <div class="mt-4 grid gap-4 lg:grid-cols-3">
                            <label class="block">
                                <span class="app-muted text-sm font-medium">Area responsable</span>
                                <select v-model="form.responsibleArea" required class="stj-input mt-2">
                                    <option v-for="area in options.areas" :key="area.value" :value="area.value">{{ area.label }}</option>
                                </select>
                                <span v-if="fieldError('responsibleArea')" class="stj-field-error">{{ fieldError('responsibleArea') }}</span>
                            </label>

                            <label class="block">
                                <span class="app-muted text-sm font-medium">Tienda</span>
                                <input v-model.trim="form.store" maxlength="100" type="text" class="stj-input mt-2">
                                <span v-if="fieldError('store')" class="stj-field-error">{{ fieldError('store') }}</span>
                            </label>

                            <label class="block">
                                <span class="app-muted text-sm font-medium">Usuario asignado</span>
                                <input v-model="form.assignedTo" type="number" min="1" class="stj-input mt-2">
                                <span v-if="fieldError('assignedTo')" class="stj-field-error">{{ fieldError('assignedTo') }}</span>
                            </label>
                        </div>

                        <label class="mt-4 block">
                            <span class="app-muted text-sm font-medium">Descripcion</span>
                            <textarea v-model.trim="form.description" required rows="4" class="stj-input mt-2"></textarea>
                            <span v-if="fieldError('description')" class="stj-field-error">{{ fieldError('description') }}</span>
                        </label>

                        <label class="mt-4 block">
                            <span class="app-muted text-sm font-medium">Respuesta</span>
                            <textarea v-model.trim="form.response" rows="4" class="stj-input mt-2"></textarea>
                            <span v-if="fieldError('response')" class="stj-field-error">{{ fieldError('response') }}</span>
                        </label>

                        <div class="mt-4 grid gap-4 lg:grid-cols-2">
                            <label class="block">
                                <span class="app-muted text-sm font-medium">Fecha resolucion</span>
                                <input v-model="form.resolvedAt" type="datetime-local" class="stj-input mt-2">
                                <span v-if="fieldError('resolvedAt')" class="stj-field-error">{{ fieldError('resolvedAt') }}</span>
                            </label>

                            <label class="block">
                                <span class="app-muted text-sm font-medium">Fecha cierre</span>
                                <input v-model="form.closedAt" type="datetime-local" class="stj-input mt-2">
                                <span v-if="fieldError('closedAt')" class="stj-field-error">{{ fieldError('closedAt') }}</span>
                            </label>

                        </div>

                        <label class="mt-4 block">
                            <span class="app-muted text-sm font-medium">Motivo rechazo</span>
                            <textarea v-model.trim="form.rejectionReason" rows="3" class="stj-input mt-2"></textarea>
                            <span v-if="fieldError('rejectionReason')" class="stj-field-error">{{ fieldError('rejectionReason') }}</span>
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
                                {{ saving ? 'Guardando...' : 'Guardar reclamo' }}
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
    max-width: 1120px;
    overflow: hidden;
    width: min(1120px, 100%);
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
    height: 1rem;
    stroke: currentColor;
    stroke-linecap: round;
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

.stj-row-actions {
    display: inline-flex;
    gap: 0.4rem;
}

.stj-icon-btn {
    align-items: center;
    border: 1px solid var(--stj-border);
    border-radius: 0.375rem;
    color: var(--stj-primary);
    display: inline-flex;
    height: 2rem;
    justify-content: center;
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

.stj-icon-btn svg {
    fill: none;
    height: 1rem;
    pointer-events: none;
    stroke: currentColor;
    stroke-linecap: round;
    stroke-linejoin: round;
    stroke-width: 2;
    width: 1rem;
}

.stj-claim-customer {
    display: grid;
    gap: 0.15rem;
}

.stj-claim-customer span {
    color: var(--stj-muted);
    font-size: 0.78rem;
}

.stj-status {
    border-radius: 999px;
    display: inline-flex;
    font-size: 0.72rem;
    font-weight: 800;
    padding: 0.22rem 0.55rem;
}

.stj-status-recibido,
.stj-status-en_revision {
    background: #dbeafe;
    color: #1d4ed8;
}

.stj-status-asignado,
.stj-status-en_proceso {
    background: #fef3c7;
    color: #92400e;
}

.stj-status-resuelto,
.stj-status-cerrado {
    background: #dcfce7;
    color: #166534;
}

.stj-status-rechazado {
    background: #fee2e2;
    color: #b91c1c;
}
</style>
