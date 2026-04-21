<script setup>
import { Head } from '@inertiajs/vue3';
import DataTable from 'datatables.net-vue3';
import DataTablesCore from 'datatables.net-dt';
import { computed, onMounted, ref, watch } from 'vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';

DataTable.use(DataTablesCore);

const loading = ref(true);
const error = ref('');
const selectedCountry = ref('');
const selectedStatus = ref('');
const countries = ref([]);
const statuses = ref([]);
const formOptions = ref({
    origins: ['TODO', 'WEB', 'APP'],
    checkoutTypes: ['TODO', 'D', 'T'],
    types: ['TODO', 'CATEGORIA', 'SUB-CATEGORIA', 'SKU', 'TARJETA', 'ENTREGA'],
    promotionTypes: ['DESCUENTO', 'DESCUENTO-SKU', 'PUNTO-PRECIO', 'CONDICION-SKU', 'CONDICION-ENTREGA', 'CONDICION-PAGO'],
    restrictions: ['TOTAL_COMPRA', '21/2', '2x1', '3x2', '2doPrecio', 'TARJETA', 'ENTREGA', '2xPP'],
});
const promotions = ref([]);
const tableKey = ref(0);
const showCreateModal = ref(false);
const creating = ref(false);
const createError = ref('');
const createErrors = ref({});
const createForm = ref(defaultCreateForm());
const showEditModal = ref(false);
const editing = ref(false);
const editError = ref('');
const editErrors = ref({});
const selectedPromotion = ref(null);
const editForm = ref({
    startAt: '',
    endAt: '',
});

const columns = [
    { data: 'id', title: 'ID', width: '70px' },
    { data: 'countryLabel', title: 'Pais' },
    { data: 'name', title: 'Nombre' },
    { data: 'statusLabel', title: 'Estado' },
    { data: 'origin', title: 'Origen' },
    { data: 'promotionType', title: 'Tipo promo' },
    { data: 'productsCount', title: 'Articulos', className: 'dt-right' },
    { data: 'assetsCount', title: 'Assets', className: 'dt-right' },
    { data: 'startAtLabel', title: 'Inicio' },
    { data: 'endAtLabel', title: 'Fin' },
    { data: 'actionsLabel', title: 'Acciones', orderable: false, searchable: false, width: '110px' },
];

const options = {
    pageLength: 10,
    lengthMenu: [10, 25, 50, 100],
    order: [[0, 'desc']],
    autoWidth: false,
    language: {
        search: 'Buscar:',
        lengthMenu: 'Mostrar _MENU_ registros',
        info: 'Mostrando _START_ a _END_ de _TOTAL_ promociones',
        infoEmpty: 'Sin promociones',
        infoFiltered: '(filtrado de _MAX_ registros)',
        zeroRecords: 'No se encontraron promociones',
        emptyTable: 'No hay promociones disponibles',
        paginate: {
            first: 'Primero',
            last: 'Ultimo',
            next: 'Siguiente',
            previous: 'Anterior',
        },
    },
};

const rows = computed(() =>
    promotions.value.map((promotion) => ({
        ...promotion,
        countryLabel: `${promotion.country?.code || 'N/D'} - ${promotion.country?.name || 'Sin pais'}`,
        statusLabel: statusHtml(promotion.status),
        startAtLabel: formatDate(promotion.startAt),
        endAtLabel: formatDate(promotion.endAt),
        actionsLabel: actionsHtml(promotion.id),
    })),
);

async function fetchPromotions() {
    loading.value = true;
    error.value = '';

    try {
        const response = await window.axios.get('/dashboard-api/promotions', {
            params: {
                country: selectedCountry.value || undefined,
                status: selectedStatus.value || undefined,
            },
        });

        countries.value = response.data.data?.countries || [];
        statuses.value = response.data.data?.statuses || [];
        formOptions.value = {
            ...formOptions.value,
            ...(response.data.data?.options || {}),
        };
        promotions.value = response.data.data?.promotions || [];
        tableKey.value += 1;
    } catch (exception) {
        error.value = exception.response?.data?.message || 'No fue posible cargar las promociones.';
    } finally {
        loading.value = false;
    }
}

async function createPromotion() {
    creating.value = true;
    createError.value = '';
    createErrors.value = {};

    const payload = new FormData();
    Object.entries(createForm.value).forEach(([key, value]) => {
        if (key === 'products') {
            if (value) {
                payload.append(key, value);
            }

            return;
        }

        if (value !== null && value !== undefined && value !== '') {
            payload.append(key, value);
        }
    });

    try {
        await window.axios.post('/dashboard-api/promotions', payload, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        showCreateModal.value = false;
        createForm.value = defaultCreateForm();
        await fetchPromotions();
    } catch (exception) {
        createError.value = exception.response?.data?.message || 'No fue posible crear la promocion.';
        createErrors.value = exception.response?.data?.errors || {};
    } finally {
        creating.value = false;
    }
}

function openCreateModal() {
    createError.value = '';
    createErrors.value = {};
    createForm.value = {
        ...defaultCreateForm(),
        country: selectedCountry.value || '',
    };
    showCreateModal.value = true;
}

function handleTableAction(event) {
    const button = event.target.closest('[data-promotion-action]');

    if (!button) {
        return;
    }

    const promotion = promotions.value.find((item) => item.id === Number(button.dataset.promotionId));

    if (!promotion) {
        return;
    }

    if (button.dataset.promotionAction === 'edit') {
        openEditModal(promotion);
        return;
    }

    if (button.dataset.promotionAction === 'assets') {
        return;
    }
}

function openEditModal(promotion) {
    selectedPromotion.value = promotion;
    editForm.value = {
        commercialName: promotion.commercialName || '',
        startAt: sqlToDatetimeLocal(promotion.startAt),
        endAt: sqlToDatetimeLocal(promotion.endAt),
    };
    editError.value = '';
    editErrors.value = {};
    showEditModal.value = true;
}

async function updatePromotionSchedule() {
    if (!selectedPromotion.value) {
        return;
    }

    editing.value = true;
    editError.value = '';
    editErrors.value = {};

    try {
        await window.axios.post(`/dashboard-api/promotions/${selectedPromotion.value.id}/schedule`, {
            commercialName: editForm.value.commercialName,
            startAt: canEditStart.value ? editForm.value.startAt : undefined,
            endAt: canEditEnd.value ? editForm.value.endAt : undefined,
        });

        showEditModal.value = false;
        await fetchPromotions();
    } catch (exception) {
        editError.value = exception.response?.data?.message || 'No fue posible actualizar el horario.';
        editErrors.value = exception.response?.data?.errors || {};
    } finally {
        editing.value = false;
    }
}

function onProductsSelected(event) {
    createForm.value.products = event.target.files?.[0] || null;
}

function defaultCreateForm() {
    const start = new Date();
    start.setDate(start.getDate() + 1);
    start.setHours(8, 0, 0, 0);

    const end = new Date(start);
    end.setDate(end.getDate() + 7);
    end.setHours(23, 59, 0, 0);

    return {
        country: '',
        name: '',
        commercialName: '',
        origin: 'TODO',
        checkoutType: 'TODO',
        type: 'SKU',
        promotionType: 'DESCUENTO-SKU',
        restriction: '',
        price: '',
        percentage: '',
        startAt: toDatetimeLocal(start),
        endAt: toDatetimeLocal(end),
        products: null,
    };
}

function toDatetimeLocal(date) {
    const pad = (number) => String(number).padStart(2, '0');

    return [
        date.getFullYear(),
        pad(date.getMonth() + 1),
        pad(date.getDate()),
    ].join('-') + `T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function sqlToDatetimeLocal(value) {
    if (!value) {
        return '';
    }

    return String(value).replace(' ', 'T').slice(0, 16);
}

function fieldError(field) {
    return createErrors.value?.[field]?.[0] || '';
}

function editFieldError(field) {
    return editErrors.value?.[field]?.[0] || '';
}

const canEditStart = computed(() => selectedPromotion.value?.status === 'PENDIENTE');
const canEditEnd = computed(() => ['PENDIENTE', 'EN-PROCESO'].includes(selectedPromotion.value?.status));
const canEditSchedule = computed(() => canEditStart.value || canEditEnd.value);

function statusHtml(status) {
    const normalized = String(status || 'N/D');
    return `<span class="stj-status-pill stj-status-${normalized.toLowerCase().replace(/[^a-z0-9]+/g, '-')}">${normalized}</span>`;
}

function formatDate(value) {
    if (!value) {
        return '';
    }

    return new Intl.DateTimeFormat('es-SV', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value.replace(' ', 'T')));
}

function actionsHtml(id) {
    return `
        <div class="stj-row-actions">
            <button type="button" class="stj-icon-btn" data-promotion-action="edit" data-promotion-id="${id}" title="Ver o editar horario" aria-label="Ver o editar horario">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 16.5V20h3.5L18.1 9.4l-3.5-3.5L4 16.5Zm15-8.9 1.1-1.1a1.5 1.5 0 0 0 0-2.1l-.5-.5a1.5 1.5 0 0 0-2.1 0L16.4 5 19 7.6Z"/></svg>
            </button>
            <button type="button" class="stj-icon-btn" data-promotion-action="assets" data-promotion-id="${id}" title="Assets" aria-label="Assets">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5Zm3 2v10h10V7H7Zm2 2h6v6H9V9Z"/></svg>
            </button>
        </div>
    `;
}

watch([selectedCountry, selectedStatus], fetchPromotions);
onMounted(fetchPromotions);
</script>

<template>
    <Head title="Promociones" />

    <AdminLayout>
        <section class="mx-auto w-full max-w-7xl">
            <div class="app-surface rounded-lg border p-6">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
                    <div>
                        <p class="app-primary-text text-sm font-semibold uppercase tracking-[0.18em]">
                            Promociones
                        </p>
                        <h1 class="app-text mt-3 text-3xl font-semibold tracking-tight">
                            Promociones recientes
                        </h1>
                        <p class="app-muted mt-3 max-w-3xl text-base leading-7">
                            Listado consultado desde stj-api con horarios, productos y assets ligados.
                        </p>
                    </div>

                    <div class="grid w-full gap-3 sm:grid-cols-2 xl:max-w-2xl">
                        <label class="block">
                            <span class="app-muted text-sm font-medium">Pais</span>
                            <select
                                v-model="selectedCountry"
                                class="app-surface app-text mt-2 h-11 w-full rounded-md border px-3 text-sm outline-none focus:ring-4"
                                style="border-color: var(--stj-border); --tw-ring-color: var(--stj-primary-soft);"
                            >
                                <option value="">Todos</option>
                                <option
                                    v-for="country in countries"
                                    :key="country.id"
                                    :value="country.code"
                                >
                                    {{ country.code }} - {{ country.name }}
                                </option>
                            </select>
                        </label>

                        <label class="block">
                            <span class="app-muted text-sm font-medium">Estado</span>
                            <select
                                v-model="selectedStatus"
                                class="app-surface app-text mt-2 h-11 w-full rounded-md border px-3 text-sm outline-none focus:ring-4"
                                style="border-color: var(--stj-border); --tw-ring-color: var(--stj-primary-soft);"
                            >
                                <option value="">Todos</option>
                                <option
                                    v-for="status in statuses"
                                    :key="status"
                                    :value="status"
                                >
                                    {{ status }}
                                </option>
                            </select>
                        </label>

                        <div class="flex items-end">
                            <button
                                type="button"
                                class="inline-flex h-11 w-full items-center justify-center rounded-md px-4 text-sm font-semibold text-white shadow-sm transition"
                                style="background: var(--stj-primary);"
                                @click="openCreateModal"
                            >
                                Crear promocion
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="app-surface mt-6 rounded-lg border p-4" @click="handleTableAction">
                <div v-if="error" class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
                    {{ error }}
                </div>

                <div v-else-if="loading" class="app-muted px-4 py-8 text-center text-sm">
                    Cargando promociones...
                </div>

                <DataTable
                    v-else
                    :key="tableKey"
                    :data="rows"
                    :columns="columns"
                    :options="options"
                    class="display stj-data-table w-full"
                />
            </div>
        </section>

        <Teleport to="body">
            <div
                v-if="showCreateModal"
                class="stj-modal-backdrop"
                role="dialog"
                aria-modal="true"
            >
                <form class="stj-modal app-surface" @submit.prevent="createPromotion">
                    <div class="flex items-start justify-between gap-4 border-b px-6 py-5" style="border-color: var(--stj-border);">
                        <div>
                            <p class="app-primary-text text-xs font-semibold uppercase tracking-[0.18em]">Promociones</p>
                            <h2 class="app-text mt-2 text-2xl font-semibold">Nueva promocion</h2>
                        </div>
                        <button
                            type="button"
                            class="app-muted rounded-md border px-3 py-2 text-sm font-semibold"
                            style="border-color: var(--stj-border);"
                            @click="showCreateModal = false"
                        >
                            Cerrar
                        </button>
                    </div>

                    <div class="max-h-[76vh] overflow-y-auto px-6 py-5">
                        <div v-if="createError" class="mb-5 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
                            {{ createError }}
                        </div>

                        <section>
                            <h3 class="app-text text-base font-semibold">Configuracion</h3>
                            <div class="mt-4 grid gap-4 md:grid-cols-3">
                                <label class="block">
                                    <span class="app-muted text-sm font-medium">Titulo</span>
                                    <input v-model="createForm.name" required maxlength="100" class="stj-input mt-2" type="text">
                                    <span v-if="fieldError('name')" class="stj-field-error">{{ fieldError('name') }}</span>
                                </label>

                                <label class="block">
                                    <span class="app-muted text-sm font-medium">Titulo comercial</span>
                                    <input v-model="createForm.commercialName" maxlength="255" class="stj-input mt-2" type="text">
                                    <span v-if="fieldError('commercialName')" class="stj-field-error">{{ fieldError('commercialName') }}</span>
                                </label>

                                <label class="block">
                                    <span class="app-muted text-sm font-medium">Pais</span>
                                    <select v-model="createForm.country" required class="stj-input mt-2">
                                        <option value="">Seleccione...</option>
                                        <option v-for="country in countries" :key="country.id" :value="country.code">
                                            {{ country.code }} - {{ country.name }}
                                        </option>
                                    </select>
                                    <span v-if="fieldError('country')" class="stj-field-error">{{ fieldError('country') }}</span>
                                </label>

                                <label class="block">
                                    <span class="app-muted text-sm font-medium">Plataforma</span>
                                    <select v-model="createForm.origin" required class="stj-input mt-2">
                                        <option v-for="origin in formOptions.origins" :key="origin" :value="origin">
                                            {{ origin }}
                                        </option>
                                    </select>
                                </label>

                                <label class="block">
                                    <span class="app-muted text-sm font-medium">Aplica</span>
                                    <select v-model="createForm.checkoutType" class="stj-input mt-2">
                                        <option value="TODO">TODO</option>
                                        <option value="D">SOLO DOMICILIO</option>
                                        <option value="T">SOLO TIENDA</option>
                                    </select>
                                </label>

                                <label class="block">
                                    <span class="app-muted text-sm font-medium">Tipo</span>
                                    <select v-model="createForm.type" required class="stj-input mt-2">
                                        <option v-for="type in formOptions.types" :key="type" :value="type">
                                            {{ type }}
                                        </option>
                                    </select>
                                </label>

                                <label class="block">
                                    <span class="app-muted text-sm font-medium">Tipo promocion</span>
                                    <select v-model="createForm.promotionType" required class="stj-input mt-2">
                                        <option v-for="type in formOptions.promotionTypes" :key="type" :value="type">
                                            {{ type }}
                                        </option>
                                    </select>
                                </label>

                                <label class="block">
                                    <span class="app-muted text-sm font-medium">Restriccion</span>
                                    <select v-model="createForm.restriction" class="stj-input mt-2">
                                        <option value="">Seleccione...</option>
                                        <option v-for="restriction in formOptions.restrictions" :key="restriction" :value="restriction">
                                            {{ restriction }}
                                        </option>
                                    </select>
                                </label>

                                <label class="block">
                                    <span class="app-muted text-sm font-medium">Precio</span>
                                    <input v-model="createForm.price" min="0" step="0.01" class="stj-input mt-2" type="number">
                                    <span v-if="fieldError('price')" class="stj-field-error">{{ fieldError('price') }}</span>
                                </label>

                                <label class="block">
                                    <span class="app-muted text-sm font-medium">Descuento</span>
                                    <input v-model="createForm.percentage" min="0" max="100" step="0.01" class="stj-input mt-2" type="number">
                                    <span v-if="fieldError('percentage')" class="stj-field-error">{{ fieldError('percentage') }}</span>
                                </label>

                                <label class="block md:col-span-2">
                                    <span class="app-muted text-sm font-medium">Excel productos</span>
                                    <input
                                        class="stj-input mt-2 file:mr-4 file:rounded-md file:border-0 file:px-3 file:py-2 file:text-sm file:font-semibold"
                                        type="file"
                                        accept=".xlsx,.csv,.txt"
                                        :required="createForm.type !== 'TODO'"
                                        @change="onProductsSelected"
                                    >
                                    <span v-if="fieldError('products')" class="stj-field-error">{{ fieldError('products') }}</span>
                                </label>
                            </div>
                        </section>

                        <section class="mt-8">
                            <h3 class="app-text text-base font-semibold">Horario</h3>
                            <div class="mt-4 grid gap-4 md:grid-cols-2">
                                <label class="block">
                                    <span class="app-muted text-sm font-medium">Inicio</span>
                                    <input v-model="createForm.startAt" required class="stj-input mt-2" type="datetime-local">
                                    <span v-if="fieldError('startAt')" class="stj-field-error">{{ fieldError('startAt') }}</span>
                                </label>

                                <label class="block">
                                    <span class="app-muted text-sm font-medium">Fin</span>
                                    <input v-model="createForm.endAt" required class="stj-input mt-2" type="datetime-local">
                                    <span v-if="fieldError('endAt')" class="stj-field-error">{{ fieldError('endAt') }}</span>
                                </label>
                            </div>
                        </section>
                    </div>

                    <div class="flex items-center justify-end gap-3 border-t px-6 py-4" style="border-color: var(--stj-border);">
                        <button
                            type="button"
                            class="app-muted rounded-md border px-4 py-2 text-sm font-semibold"
                            style="border-color: var(--stj-border);"
                            @click="showCreateModal = false"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            class="rounded-md px-4 py-2 text-sm font-semibold text-white"
                            style="background: var(--stj-primary);"
                            :disabled="creating"
                        >
                            {{ creating ? 'Guardando...' : 'Guardar promocion' }}
                        </button>
                    </div>
                </form>
            </div>
        </Teleport>

        <Teleport to="body">
            <div
                v-if="showEditModal && selectedPromotion"
                class="stj-modal-backdrop"
                role="dialog"
                aria-modal="true"
            >
                <form class="stj-modal app-surface" @submit.prevent="updatePromotionSchedule">
                    <div class="flex items-start justify-between gap-4 border-b px-6 py-5" style="border-color: var(--stj-border);">
                        <div>
                            <p class="app-primary-text text-xs font-semibold uppercase tracking-[0.18em]">Promociones</p>
                            <h2 class="app-text mt-2 text-2xl font-semibold">Promocion #{{ selectedPromotion.id }}</h2>
                        </div>
                        <button
                            type="button"
                            class="app-muted rounded-md border px-3 py-2 text-sm font-semibold"
                            style="border-color: var(--stj-border);"
                            @click="showEditModal = false"
                        >
                            Cerrar
                        </button>
                    </div>

                    <div class="max-h-[76vh] overflow-y-auto px-6 py-5">
                        <div v-if="editError" class="mb-5 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
                            {{ editError }}
                        </div>

                        <div
                            v-if="!canEditSchedule"
                            class="mb-5 rounded-md border px-4 py-3 text-sm"
                            style="border-color: var(--stj-border); color: var(--stj-muted);"
                        >
                            Solo el titulo comercial puede editarse porque su estado es {{ selectedPromotion.status }}.
                        </div>

                        <section>
                            <h3 class="app-text text-base font-semibold">Informacion</h3>
                            <div class="mt-4 grid gap-4 md:grid-cols-3">
                                <label class="block">
                                    <span class="app-muted text-sm font-medium">Titulo</span>
                                    <input :value="selectedPromotion.name" readonly class="stj-input stj-input-readonly mt-2" type="text">
                                </label>

                                <label class="block">
                                    <span class="app-muted text-sm font-medium">Titulo comercial</span>
                                    <input v-model="editForm.commercialName" maxlength="255" class="stj-input mt-2" type="text">
                                    <span v-if="editFieldError('commercialName')" class="stj-field-error">{{ editFieldError('commercialName') }}</span>
                                </label>

                                <label class="block">
                                    <span class="app-muted text-sm font-medium">Pais</span>
                                    <input :value="`${selectedPromotion.country?.code || ''} - ${selectedPromotion.country?.name || ''}`" readonly class="stj-input stj-input-readonly mt-2" type="text">
                                </label>

                                <label class="block">
                                    <span class="app-muted text-sm font-medium">Estado</span>
                                    <input :value="selectedPromotion.status" readonly class="stj-input stj-input-readonly mt-2" type="text">
                                </label>

                                <label class="block">
                                    <span class="app-muted text-sm font-medium">Plataforma</span>
                                    <input :value="selectedPromotion.origin" readonly class="stj-input stj-input-readonly mt-2" type="text">
                                </label>

                                <label class="block">
                                    <span class="app-muted text-sm font-medium">Tipo</span>
                                    <input :value="selectedPromotion.type" readonly class="stj-input stj-input-readonly mt-2" type="text">
                                </label>

                                <label class="block">
                                    <span class="app-muted text-sm font-medium">Tipo promocion</span>
                                    <input :value="selectedPromotion.promotionType" readonly class="stj-input stj-input-readonly mt-2" type="text">
                                </label>

                                <label class="block">
                                    <span class="app-muted text-sm font-medium">Restriccion</span>
                                    <input :value="selectedPromotion.restriction || ''" readonly class="stj-input stj-input-readonly mt-2" type="text">
                                </label>

                                <label class="block">
                                    <span class="app-muted text-sm font-medium">Articulos</span>
                                    <input :value="selectedPromotion.productsCount" readonly class="stj-input stj-input-readonly mt-2" type="text">
                                </label>
                            </div>
                        </section>

                        <section class="mt-8">
                            <h3 class="app-text text-base font-semibold">Horario</h3>
                            <div class="mt-4 grid gap-4 md:grid-cols-2">
                                <label class="block">
                                    <span class="app-muted text-sm font-medium">Inicio</span>
                                    <input
                                        v-model="editForm.startAt"
                                        required
                                        class="stj-input mt-2"
                                        :class="{ 'stj-input-readonly': !canEditStart }"
                                        type="datetime-local"
                                        :disabled="!canEditStart"
                                    >
                                    <span v-if="editFieldError('startAt')" class="stj-field-error">{{ editFieldError('startAt') }}</span>
                                </label>

                                <label class="block">
                                    <span class="app-muted text-sm font-medium">Fin</span>
                                    <input
                                        v-model="editForm.endAt"
                                        required
                                        class="stj-input mt-2"
                                        :class="{ 'stj-input-readonly': !canEditEnd }"
                                        type="datetime-local"
                                        :disabled="!canEditEnd"
                                    >
                                    <span v-if="editFieldError('endAt')" class="stj-field-error">{{ editFieldError('endAt') }}</span>
                                </label>
                            </div>
                        </section>
                    </div>

                    <div class="flex items-center justify-end gap-3 border-t px-6 py-4" style="border-color: var(--stj-border);">
                        <button
                            type="button"
                            class="app-muted rounded-md border px-4 py-2 text-sm font-semibold"
                            style="border-color: var(--stj-border);"
                            @click="showEditModal = false"
                        >
                            Cerrar
                        </button>
                        <button
                            type="submit"
                            class="rounded-md px-4 py-2 text-sm font-semibold text-white"
                            style="background: var(--stj-primary);"
                            :disabled="editing"
                        >
                            {{ editing ? 'Guardando...' : 'Guardar cambios' }}
                        </button>
                    </div>
                </form>
            </div>
        </Teleport>
    </AdminLayout>
</template>

<style>
.stj-status-pill {
    border-radius: 999px;
    display: inline-flex;
    font-size: 0.72rem;
    font-weight: 800;
    padding: 0.2rem 0.55rem;
}

.stj-status-pendiente {
    background: #fef3c7;
    color: #92400e;
}

.stj-status-en-proceso {
    background: #dbeafe;
    color: #1d4ed8;
}

.stj-status-finalizada,
.stj-status-cancelado,
.stj-status-suspendido {
    background: #e5e7eb;
    color: #374151;
}

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
    max-width: 980px;
    overflow: hidden;
    width: min(980px, 100%);
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

.stj-input-readonly,
.stj-input:disabled {
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

.stj-icon-btn svg {
    fill: currentColor;
    height: 1rem;
    pointer-events: none;
    width: 1rem;
}
</style>
