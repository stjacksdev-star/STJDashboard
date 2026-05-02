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
const categories = ref([]);
const options = ref({
    alignments: ['left', 'center', 'right'],
    brands: ['ST JACKS', 'BUNGEE', 'BASICS', 'JACK & CO'],
});
const tableKey = ref(0);
const showModal = ref(false);
const editingCategory = ref(null);
const form = ref(defaultForm());

const columns = [
    { data: 'id', title: 'ID', width: '70px' },
    { data: 'orderLabel', title: 'Orden', className: 'dt-right', width: '80px' },
    { data: 'code', title: 'Codigo' },
    { data: 'name', title: 'Nombre' },
    { data: 'brandLabel', title: 'Marca' },
    { data: 'webLabel', title: 'Web', orderable: false },
    { data: 'appLabel', title: 'APP', orderable: false },
    { data: 'logoLabel', title: 'Logos', orderable: false },
    { data: 'actions', title: 'Acciones', orderable: false, searchable: false, className: 'dt-actions', width: '96px' },
];

const dataTableOptions = {
    pageLength: 10,
    lengthMenu: [10, 25, 50, 100],
    order: [[1, 'asc'], [3, 'asc']],
    autoWidth: false,
    language: {
        search: 'Buscar:',
        lengthMenu: 'Mostrar _MENU_ registros',
        info: 'Mostrando _START_ a _END_ de _TOTAL_ categorias',
        infoEmpty: 'Sin categorias',
        infoFiltered: '(filtrado de _MAX_ registros)',
        zeroRecords: 'No se encontraron categorias',
        emptyTable: 'No hay categorias disponibles',
        paginate: {
            first: 'Primero',
            last: 'Ultimo',
            next: 'Siguiente',
            previous: 'Anterior',
        },
    },
};

const rows = computed(() =>
    categories.value.map((category) => ({
        ...category,
        orderLabel: category.order ?? '',
        brandLabel: category.brand || 'N/D',
        webLabel: enabledHtml(category),
        appLabel: category.enabled?.app ? '<span class="stj-pill stj-pill-on">APP</span>' : '<span class="stj-pill">APP</span>',
        logoLabel: logoHtml(category),
        actions: actionsHtml(category.id),
    })),
);

onMounted(fetchCategories);

async function fetchCategories() {
    loading.value = true;
    error.value = '';

    try {
        const response = await window.axios.get('/dashboard-api/product-categories');
        categories.value = response.data.data?.categories || [];
        options.value = {
            ...options.value,
            ...(response.data.data?.options || {}),
        };
        tableKey.value += 1;
    } catch (exception) {
        error.value = exception.response?.data?.message || 'No fue posible cargar las categorias.';
    } finally {
        loading.value = false;
    }
}

async function saveCategory() {
    saving.value = true;
    formError.value = '';
    errors.value = {};
    success.value = '';

    const payload = normalizePayload(form.value);
    const url = editingCategory.value
        ? `/dashboard-api/product-categories/${editingCategory.value.id}`
        : '/dashboard-api/product-categories';

    try {
        const response = await window.axios.post(url, payload);
        success.value = response.data.message || 'Categoria guardada correctamente.';
        showModal.value = false;
        editingCategory.value = null;
        form.value = defaultForm();
        await fetchCategories();
    } catch (exception) {
        formError.value = exception.response?.data?.message
            || firstError(exception.response?.data?.errors)
            || 'No fue posible guardar la categoria.';
        errors.value = exception.response?.data?.errors || {};
    } finally {
        saving.value = false;
    }
}

async function deleteCategory(category) {
    if (! window.confirm(`Eliminar la categoria ${category.name}?`)) {
        return;
    }

    deleting.value = true;
    error.value = '';
    success.value = '';

    try {
        const response = await window.axios.delete(`/dashboard-api/product-categories/${category.id}`);
        success.value = response.data.message || 'Categoria eliminada correctamente.';
        await fetchCategories();
    } catch (exception) {
        error.value = firstError(exception.response?.data?.errors)
            || exception.response?.data?.message
            || 'No fue posible eliminar la categoria.';
    } finally {
        deleting.value = false;
    }
}

function openCreateModal() {
    editingCategory.value = null;
    form.value = defaultForm();
    formError.value = '';
    errors.value = {};
    showModal.value = true;
}

function openEditModal(category) {
    editingCategory.value = category;
    form.value = {
        order: category.order ?? '',
        appOrder: category.appOrder ?? '',
        code: category.code || '',
        name: category.name || '',
        align: category.align || '',
        header: category.header || '',
        logo: category.logo || '',
        appName: category.appName || '',
        appLogo: category.appLogo || '',
        hasOtherSubcategories: Boolean(category.hasOtherSubcategories),
        otherSubcategories: category.otherSubcategories || '',
        description: category.description || '',
        sizes: category.sizes || '',
        brand: category.brand || '',
        enabledSv: Boolean(category.enabled?.sv),
        enabledGt: Boolean(category.enabled?.gt),
        enabledCr: Boolean(category.enabled?.cr),
        enabledNi: Boolean(category.enabled?.ni),
        enabledApp: Boolean(category.enabled?.app),
    };
    formError.value = '';
    errors.value = {};
    showModal.value = true;
}

function handleTableAction(event) {
    const button = event.target.closest('[data-category-action]');

    if (! button) {
        return;
    }

    const category = categories.value.find((item) => item.id === Number(button.dataset.categoryId));

    if (! category) {
        return;
    }

    if (button.dataset.categoryAction === 'edit') {
        openEditModal(category);
        return;
    }

    if (button.dataset.categoryAction === 'delete') {
        deleteCategory(category);
    }
}

function defaultForm() {
    return {
        order: '',
        appOrder: '',
        code: '',
        name: '',
        align: 'left',
        header: '',
        logo: '',
        appName: '',
        appLogo: '',
        hasOtherSubcategories: false,
        otherSubcategories: '',
        description: '',
        sizes: '',
        brand: 'ST JACKS',
        enabledSv: true,
        enabledGt: true,
        enabledCr: true,
        enabledNi: false,
        enabledApp: true,
    };
}

function normalizePayload(values) {
    return {
        ...values,
        order: values.order === '' ? null : Number(values.order),
        appOrder: values.appOrder === '' ? null : Number(values.appOrder),
        align: values.align || null,
        brand: values.brand || null,
    };
}

function enabledHtml(category) {
    const enabled = category.enabled || {};
    const countries = [
        ['SV', enabled.sv],
        ['GT', enabled.gt],
        ['CR', enabled.cr],
        ['NI', enabled.ni],
    ];

    return countries
        .map(([label, active]) => `<span class="stj-pill ${active ? 'stj-pill-on' : ''}">${label}</span>`)
        .join(' ');
}

function logoHtml(category) {
    return `
        <div class="stj-logo-cell">
            <span>${escapeHtml(category.logo || 'N/D')}</span>
            <span>${escapeHtml(category.appLogo || 'N/D')}</span>
        </div>
    `;
}

function actionsHtml(id) {
    return `
        <div class="stj-row-actions">
            <button type="button" class="stj-icon-btn" data-category-action="edit" data-category-id="${id}" title="Editar">
                <svg viewBox="0 0 24 24"><path d="M4 20h4l10.5-10.5a2.8 2.8 0 0 0-4-4L4 16v4zM13.5 6.5l4 4"/></svg>
            </button>
            <button type="button" class="stj-icon-btn stj-icon-btn-danger" data-category-action="delete" data-category-id="${id}" title="Eliminar">
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
        <Head title="Productos / Categorias" />

        <div class="mx-auto max-w-7xl space-y-6">
            <section class="app-surface rounded-lg border p-5 shadow-sm" style="border-color: var(--stj-border);">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="app-primary-text text-xs font-semibold uppercase">Productos</p>
                        <h1 class="app-text mt-2 text-2xl font-bold">Categorias</h1>
                    </div>

                    <button
                        type="button"
                        class="inline-flex h-11 items-center justify-center rounded-md bg-blue-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700"
                        @click="openCreateModal"
                    >
                        Nueva categoria
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
                        <p class="app-muted text-sm">{{ categories.length }} categorias cargadas</p>
                    </div>
                </div>

                <div class="p-5">
                    <div v-if="loading" class="app-muted rounded-md border p-4 text-sm" style="border-color: var(--stj-border);">
                        Cargando categorias...
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
                            <p class="app-primary-text text-xs font-semibold uppercase">Categoria</p>
                            <h2 class="app-text mt-1 text-xl font-bold">
                                {{ editingCategory ? 'Editar categoria' : 'Nueva categoria' }}
                            </h2>
                        </div>

                        <button type="button" class="stj-modal-close" title="Cerrar" @click="showModal = false">
                            <svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18" /></svg>
                        </button>
                    </div>

                    <form class="max-h-[78vh] overflow-y-auto p-5" @submit.prevent="saveCategory">
                        <div v-if="formError" class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                            {{ formError }}
                        </div>

                        <div class="grid gap-4 lg:grid-cols-4">
                            <label class="block">
                                <span class="app-muted text-sm font-medium">Orden web</span>
                                <input v-model="form.order" type="number" min="0" class="stj-input mt-2">
                                <span v-if="fieldError('order')" class="stj-field-error">{{ fieldError('order') }}</span>
                            </label>

                            <label class="block">
                                <span class="app-muted text-sm font-medium">Orden app</span>
                                <input v-model="form.appOrder" type="number" min="0" class="stj-input mt-2">
                                <span v-if="fieldError('appOrder')" class="stj-field-error">{{ fieldError('appOrder') }}</span>
                            </label>

                            <label class="block">
                                <span class="app-muted text-sm font-medium">Codigo</span>
                                <input v-model.trim="form.code" required maxlength="25" type="text" class="stj-input mt-2">
                                <span v-if="fieldError('code')" class="stj-field-error">{{ fieldError('code') }}</span>
                            </label>

                            <label class="block">
                                <span class="app-muted text-sm font-medium">Marca</span>
                                <select v-model="form.brand" class="stj-input mt-2">
                                    <option value="">Sin marca</option>
                                    <option v-for="brand in options.brands" :key="brand" :value="brand">{{ brand }}</option>
                                </select>
                                <span v-if="fieldError('brand')" class="stj-field-error">{{ fieldError('brand') }}</span>
                            </label>
                        </div>

                        <div class="mt-4 grid gap-4 lg:grid-cols-2">
                            <label class="block">
                                <span class="app-muted text-sm font-medium">Nombre web</span>
                                <input v-model.trim="form.name" required maxlength="100" type="text" class="stj-input mt-2">
                                <span v-if="fieldError('name')" class="stj-field-error">{{ fieldError('name') }}</span>
                            </label>

                            <label class="block">
                                <span class="app-muted text-sm font-medium">Nombre app</span>
                                <input v-model.trim="form.appName" maxlength="100" type="text" class="stj-input mt-2">
                                <span v-if="fieldError('appName')" class="stj-field-error">{{ fieldError('appName') }}</span>
                            </label>
                        </div>

                        <div class="mt-4 grid gap-4 lg:grid-cols-3">
                            <label class="block">
                                <span class="app-muted text-sm font-medium">Logo web</span>
                                <input v-model.trim="form.logo" required maxlength="100" type="text" class="stj-input mt-2">
                                <span v-if="fieldError('logo')" class="stj-field-error">{{ fieldError('logo') }}</span>
                            </label>

                            <label class="block">
                                <span class="app-muted text-sm font-medium">Logo app</span>
                                <input v-model.trim="form.appLogo" required maxlength="100" type="text" class="stj-input mt-2">
                                <span v-if="fieldError('appLogo')" class="stj-field-error">{{ fieldError('appLogo') }}</span>
                            </label>

                            <label class="block">
                                <span class="app-muted text-sm font-medium">Header</span>
                                <input v-model.trim="form.header" maxlength="100" type="text" class="stj-input mt-2">
                                <span v-if="fieldError('header')" class="stj-field-error">{{ fieldError('header') }}</span>
                            </label>
                        </div>

                        <div class="mt-4 grid gap-4 lg:grid-cols-3">
                            <label class="block lg:col-span-2">
                                <span class="app-muted text-sm font-medium">Tallas</span>
                                <input v-model.trim="form.sizes" required maxlength="250" type="text" class="stj-input mt-2">
                                <span v-if="fieldError('sizes')" class="stj-field-error">{{ fieldError('sizes') }}</span>
                            </label>

                            <label class="block">
                                <span class="app-muted text-sm font-medium">Alineacion</span>
                                <select v-model="form.align" class="stj-input mt-2">
                                    <option value="">N/D</option>
                                    <option v-for="align in options.alignments" :key="align" :value="align">{{ align }}</option>
                                </select>
                                <span v-if="fieldError('align')" class="stj-field-error">{{ fieldError('align') }}</span>
                            </label>
                        </div>

                        <label class="mt-4 block">
                            <span class="app-muted text-sm font-medium">Descripcion</span>
                            <textarea v-model.trim="form.description" required maxlength="500" rows="3" class="stj-input mt-2"></textarea>
                            <span v-if="fieldError('description')" class="stj-field-error">{{ fieldError('description') }}</span>
                        </label>

                        <div class="mt-5 grid gap-4 lg:grid-cols-2">
                            <div class="rounded-md border p-4" style="border-color: var(--stj-border);">
                                <p class="app-text text-sm font-semibold">Habilitado</p>
                                <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-5">
                                    <label class="stj-check"><input v-model="form.enabledSv" type="checkbox"> SV</label>
                                    <label class="stj-check"><input v-model="form.enabledGt" type="checkbox"> GT</label>
                                    <label class="stj-check"><input v-model="form.enabledCr" type="checkbox"> CR</label>
                                    <label class="stj-check"><input v-model="form.enabledNi" type="checkbox"> NI</label>
                                    <label class="stj-check"><input v-model="form.enabledApp" type="checkbox"> APP</label>
                                </div>
                            </div>

                            <div class="rounded-md border p-4" style="border-color: var(--stj-border);">
                                <label class="stj-check">
                                    <input v-model="form.hasOtherSubcategories" type="checkbox">
                                    Permite otras subcategorias
                                </label>

                                <label class="mt-3 block">
                                    <span class="app-muted text-sm font-medium">Texto otras subcategorias</span>
                                    <input v-model.trim="form.otherSubcategories" maxlength="100" type="text" class="stj-input mt-2">
                                    <span v-if="fieldError('otherSubcategories')" class="stj-field-error">{{ fieldError('otherSubcategories') }}</span>
                                </label>
                            </div>
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
                                :disabled="saving || deleting"
                            >
                                {{ saving ? 'Guardando...' : 'Guardar categoria' }}
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

.stj-pill {
    background: #e5e7eb;
    border-radius: 999px;
    color: #374151;
    display: inline-flex;
    font-size: 0.68rem;
    font-weight: 800;
    margin: 0.1rem;
    padding: 0.18rem 0.45rem;
}

.stj-pill-on {
    background: #dcfce7;
    color: #166534;
}

.stj-logo-cell {
    display: grid;
    gap: 0.15rem;
    max-width: 220px;
}

.stj-logo-cell span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.stj-check {
    align-items: center;
    color: var(--stj-text);
    display: inline-flex;
    font-size: 0.9rem;
    font-weight: 600;
    gap: 0.5rem;
}
</style>
