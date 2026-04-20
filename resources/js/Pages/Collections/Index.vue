<script setup>
import { Head } from '@inertiajs/vue3';
import DataTable from 'datatables.net-vue3';
import DataTablesCore from 'datatables.net-dt';
import { computed, onMounted, ref, watch } from 'vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';

DataTable.use(DataTablesCore);

const loading = ref(true);
const saving = ref(false);
const editSaving = ref(false);
const assetSaving = ref(false);
const assetsLoading = ref(false);
const error = ref('');
const formError = ref('');
const editError = ref('');
const assetError = ref('');
const success = ref('');
const editSuccess = ref('');
const assetSuccess = ref('');
const actionMessage = ref('');
const detailCollection = ref(null);
const editingCollection = ref(null);
const assetCollection = ref(null);
const collectionAssets = ref([]);
const collectionAssetLink = ref('');
const selectedCountry = ref('');
const countries = ref([]);
const collections = ref([]);
const tableKey = ref(0);
const bannerInput = ref(null);
const productsInput = ref(null);
const editBannerInput = ref(null);
const editProductsInput = ref(null);
const assetImageInput = ref(null);
const assetMobileImageInput = ref(null);
const form = ref({
    country: '',
    name: '',
    title: '',
    banner: null,
    products: null,
});
const editForm = ref({
    country: '',
    name: '',
    title: '',
    banner: null,
    products: null,
});
const assetForm = ref(defaultAssetForm());

const columns = [
    { data: 'id', title: 'ID', width: '70px' },
    { data: 'countryLabel', title: 'Pais' },
    { data: 'name', title: 'Nombre' },
    { data: 'title', title: 'Titulo' },
    { data: 'codesCount', title: 'Articulos', className: 'dt-right' },
    { data: 'mobilePosition', title: 'Movil' },
    { data: 'createdAtLabel', title: 'Creado' },
    {
        data: 'actions',
        title: 'Acciones',
        orderable: false,
        searchable: false,
        className: 'dt-actions',
        width: '132px',
    },
];

const options = {
    pageLength: 10,
    lengthMenu: [10, 25, 50, 100],
    order: [[0, 'desc']],
    autoWidth: false,
    language: {
        search: 'Buscar:',
        lengthMenu: 'Mostrar _MENU_ registros',
        info: 'Mostrando _START_ a _END_ de _TOTAL_ colecciones',
        infoEmpty: 'Sin colecciones',
        infoFiltered: '(filtrado de _MAX_ registros)',
        zeroRecords: 'No se encontraron colecciones',
        emptyTable: 'No hay colecciones disponibles',
        paginate: {
            first: 'Primero',
            last: 'Ultimo',
            next: 'Siguiente',
            previous: 'Anterior',
        },
    },
};

const rows = computed(() =>
    collections.value.map((collection) => ({
        ...collection,
        countryLabel: `${collection.country?.code || 'N/D'} - ${collection.country?.name || 'Sin pais'}`,
        createdAtLabel: formatDate(collection.createdAt),
        actions: actionsHtml(collection),
    })),
);

async function fetchCollections() {
    loading.value = true;
    error.value = '';

    try {
        const response = await window.axios.get('/dashboard-api/collections', {
            params: {
                country: selectedCountry.value || undefined,
            },
        });

        countries.value = response.data.data?.countries || [];
        collections.value = response.data.data?.collections || [];
        tableKey.value += 1;
    } catch (exception) {
        error.value = exception.response?.data?.message || 'No fue posible cargar las colecciones.';
    } finally {
        loading.value = false;
    }
}

async function submitCollection() {
    saving.value = true;
    formError.value = '';
    success.value = '';

    const payload = new FormData();
    payload.append('country', form.value.country);
    payload.append('name', form.value.name);
    payload.append('title', form.value.title);
    payload.append('banner', form.value.banner || '');
    payload.append('products', form.value.products || '');

    try {
        const response = await window.axios.post('/dashboard-api/collections', payload, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        });

        success.value = response.data.message || 'Coleccion creada correctamente.';
        resetForm();
        await fetchCollections();
    } catch (exception) {
        formError.value = firstError(exception.response?.data?.errors)
            || exception.response?.data?.message
            || 'No fue posible crear la coleccion.';
    } finally {
        saving.value = false;
    }
}

async function submitCollectionEdit() {
    if (!editingCollection.value) {
        return;
    }

    editSaving.value = true;
    editError.value = '';
    editSuccess.value = '';

    const payload = new FormData();
    payload.append('country', editForm.value.country);
    payload.append('name', editForm.value.name);
    payload.append('title', editForm.value.title);

    if (editForm.value.banner) {
        payload.append('banner', editForm.value.banner);
    }

    if (editForm.value.products) {
        payload.append('products', editForm.value.products);
    }

    try {
        const response = await window.axios.post(`/dashboard-api/collections/${editingCollection.value.id}`, payload, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        });

        editSuccess.value = response.data.message || 'Coleccion actualizada correctamente.';
        await fetchCollections();

        const updated = response.data.data;
        editingCollection.value = updated || collections.value.find((collection) => collection.id === editingCollection.value.id);
        clearEditFiles();
    } catch (exception) {
        editError.value = firstError(exception.response?.data?.errors)
            || exception.response?.data?.message
            || 'No fue posible actualizar la coleccion.';
    } finally {
        editSaving.value = false;
    }
}

async function fetchCollectionAssets(collection) {
    assetsLoading.value = true;
    assetError.value = '';
    assetSuccess.value = '';
    collectionAssets.value = [];
    collectionAssetLink.value = '';

    try {
        const response = await window.axios.get(`/dashboard-api/collections/${collection.id}/assets`);
        collectionAssets.value = response.data.data?.assets || [];
        collectionAssetLink.value = response.data.data?.link || '';
    } catch (exception) {
        assetError.value = exception.response?.data?.message || 'No fue posible cargar los assets.';
    } finally {
        assetsLoading.value = false;
    }
}

async function submitCollectionAsset() {
    if (!assetCollection.value) {
        return;
    }

    assetSaving.value = true;
    assetError.value = '';
    assetSuccess.value = '';

    const payload = new FormData();
    payload.append('type', assetForm.value.type);
    payload.append('platform', assetForm.value.platform);
    payload.append('position', assetForm.value.type === 'LO-MAS-NUEVO' ? assetForm.value.position : '');
    payload.append('order', assetForm.value.order);
    payload.append('status', assetForm.value.status);
    payload.append('startAt', assetForm.value.startAt);
    payload.append('endAt', assetForm.value.endAt);
    payload.append('title', assetForm.value.title || '');

    if (assetForm.value.image) {
        payload.append('image', assetForm.value.image);
    }

    if (assetForm.value.mobileImage) {
        payload.append('mobileImage', assetForm.value.mobileImage);
    }

    try {
        const response = await window.axios.post(`/dashboard-api/collections/${assetCollection.value.id}/assets`, payload, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        });

        assetSuccess.value = response.data.message || 'Asset creado correctamente.';
        assetForm.value = defaultAssetForm();
        clearAssetFiles();
        await fetchCollectionAssets(assetCollection.value);
    } catch (exception) {
        assetError.value = firstError(exception.response?.data?.errors)
            || exception.response?.data?.message
            || 'No fue posible crear el asset.';
    } finally {
        assetSaving.value = false;
    }
}

function resetForm() {
    form.value = {
        country: selectedCountry.value || '',
        name: '',
        title: '',
        banner: null,
        products: null,
    };

    if (bannerInput.value) {
        bannerInput.value.value = '';
    }

    if (productsInput.value) {
        productsInput.value.value = '';
    }
}

function clearEditFiles() {
    editForm.value.banner = null;
    editForm.value.products = null;

    if (editBannerInput.value) {
        editBannerInput.value.value = '';
    }

    if (editProductsInput.value) {
        editProductsInput.value.value = '';
    }
}

function clearAssetFiles() {
    assetForm.value.image = null;
    assetForm.value.mobileImage = null;

    if (assetImageInput.value) {
        assetImageInput.value.value = '';
    }

    if (assetMobileImageInput.value) {
        assetMobileImageInput.value.value = '';
    }
}

function firstError(errors) {
    if (!errors) {
        return '';
    }

    const first = Object.values(errors)[0];
    return Array.isArray(first) ? first[0] : first;
}

function actionsHtml(collection) {
    return `
        <div class="stj-row-actions" aria-label="Acciones de coleccion ${collection.id}">
            ${actionButton('view', collection.id, 'Ver detalles', eyeIcon())}
            ${actionButton('edit', collection.id, 'Editar', pencilIcon())}
            ${actionButton('assets', collection.id, 'Agregar assets', imageIcon())}
        </div>
    `;
}

function actionButton(action, id, label, icon) {
    return `
        <button
            type="button"
            class="stj-action-button"
            data-collection-action="${action}"
            data-collection-id="${id}"
            title="${label}"
            aria-label="${label}"
        >
            ${icon}
        </button>
    `;
}

function handleTableAction(event) {
    const button = event.target.closest('[data-collection-action]');

    if (!button) {
        return;
    }

    const collection = collections.value.find((item) => item.id === Number(button.dataset.collectionId));

    if (!collection) {
        return;
    }

    const action = button.dataset.collectionAction;

    if (action === 'view') {
        openCollectionDetails(collection);
        return;
    }

    if (action === 'edit') {
        editCollection(collection);
        return;
    }

    if (action === 'assets') {
        openCollectionAssets(collection);
    }
}

function openCollectionDetails(collection) {
    actionMessage.value = '';
    detailCollection.value = collection;
}

function editCollection(collection) {
    detailCollection.value = null;
    actionMessage.value = '';
    editError.value = '';
    editSuccess.value = '';
    editingCollection.value = collection;
    editForm.value = {
        country: collection.country?.code || '',
        name: collection.name || '',
        title: collection.title || '',
        banner: null,
        products: null,
    };
    clearEditFiles();
}

function openCollectionAssets(collection) {
    detailCollection.value = null;
    editingCollection.value = null;
    actionMessage.value = '';
    assetCollection.value = collection;
    assetForm.value = defaultAssetForm();
    clearAssetFiles();
    fetchCollectionAssets(collection);
}

function closeCollectionDetails() {
    detailCollection.value = null;
}

function closeCollectionEdit() {
    editingCollection.value = null;
    editError.value = '';
    editSuccess.value = '';
    clearEditFiles();
}

function closeCollectionAssets() {
    assetCollection.value = null;
    assetError.value = '';
    assetSuccess.value = '';
    collectionAssets.value = [];
    collectionAssetLink.value = '';
    clearAssetFiles();
}

function collectionCodes(collection) {
    return collection?.codes || collection?.codesPreview || [];
}

function defaultAssetForm() {
    return {
        type: 'BANNER',
        platform: 'WEB',
        position: '',
        order: 1,
        status: 'PENDIENTE',
        startAt: dateTimeLocal(new Date()),
        endAt: dateTimeLocal(addDays(new Date(), 7)),
        title: '',
        image: null,
        mobileImage: null,
    };
}

function addDays(date, days) {
    const value = new Date(date);
    value.setDate(value.getDate() + days);
    return value;
}

function dateTimeLocal(date) {
    const offset = date.getTimezoneOffset();
    const local = new Date(date.getTime() - offset * 60000);
    return local.toISOString().slice(0, 16);
}

function assetPreview(asset) {
    return asset.image || asset.mobileImage || '';
}

function eyeIcon() {
    return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.25 12s3.5-6.25 9.75-6.25S21.75 12 21.75 12 18.25 18.25 12 18.25 2.25 12 2.25 12Zm9.75 3.25A3.25 3.25 0 1 0 12 8.75a3.25 3.25 0 0 0 0 6.5Zm0-1.5A1.75 1.75 0 1 1 12 10.25a1.75 1.75 0 0 1 0 3.5Z"/></svg>';
}

function pencilIcon() {
    return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16.9 3.6a2.1 2.1 0 0 1 3 3L8.75 17.75 4.5 19.5l1.75-4.25L16.9 3.6Zm1.95 1.05a.62.62 0 0 0-.9 0l-.95.95 1.4 1.4.95-.95a.62.62 0 0 0 0-.9l-.5-.5ZM7.55 16.55l9.8-9.8 1.4 1.4-9.8 9.8-1.92.79.52-2.19Z"/></svg>';
}

function imageIcon() {
    return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4.75 5.5h14.5c.69 0 1.25.56 1.25 1.25v10.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25V6.75c0-.69.56-1.25 1.25-1.25Zm.25 1.5v8.6l3.55-3.55c.3-.3.78-.3 1.08 0l2.12 2.12 3.12-3.12c.3-.3.78-.3 1.08 0L19 14.1V7H5Zm14 9.25-3.58-3.58-3.12 3.12c-.3.3-.78.3-1.08 0L9.1 13.67 5 17.77v.23h14v-1.75ZM8.25 10.5a1.25 1.25 0 1 1 0-2.5 1.25 1.25 0 0 1 0 2.5Z"/></svg>';
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

watch(selectedCountry, fetchCollections);
watch(selectedCountry, (country) => {
    if (!form.value.country) {
        form.value.country = country;
    }
});
onMounted(() => {
    fetchCollections();
});
</script>

<template>
    <Head title="Colecciones" />

    <AdminLayout>
        <section class="mx-auto w-full max-w-7xl">
            <div class="app-surface rounded-lg border p-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="app-primary-text text-sm font-semibold uppercase tracking-[0.18em]">
                            Colecciones
                        </p>
                        <h1 class="app-text mt-3 text-3xl font-semibold tracking-tight">
                            Colecciones recientes por pais
                        </h1>
                        <p class="app-muted mt-3 max-w-3xl text-base leading-7">
                            Listado consultado desde stj-api para administrar las colecciones del ecommerce.
                        </p>
                    </div>

                    <label class="block w-full max-w-xs">
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
                </div>
            </div>

            <form class="app-surface mt-6 rounded-lg border p-5" @submit.prevent="submitCollection">
                <div class="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h2 class="app-text text-xl font-semibold">Nueva coleccion</h2>
                        <p class="app-muted mt-1 text-sm">
                            Sube el banner y un Excel con los codigos de producto en la columna A.
                        </p>
                    </div>
                    <button
                        type="submit"
                        class="inline-flex h-11 items-center justify-center rounded-md bg-blue-600 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="saving"
                    >
                        {{ saving ? 'Guardando...' : 'Crear coleccion' }}
                    </button>
                </div>

                <div v-if="formError" class="mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
                    {{ formError }}
                </div>

                <div v-if="success" class="mt-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                    {{ success }}
                </div>

                <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                    <label class="block">
                        <span class="app-muted text-sm font-medium">Pais</span>
                        <select
                            v-model="form.country"
                            required
                            class="app-surface app-text mt-2 h-11 w-full rounded-md border px-3 text-sm outline-none focus:ring-4"
                            style="border-color: var(--stj-border); --tw-ring-color: var(--stj-primary-soft);"
                        >
                            <option value="">Seleccionar</option>
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
                        <span class="app-muted text-sm font-medium">Nombre</span>
                        <input
                            v-model="form.name"
                            required
                            maxlength="100"
                            type="text"
                            class="app-surface app-text mt-2 h-11 w-full rounded-md border px-3 text-sm outline-none focus:ring-4"
                            style="border-color: var(--stj-border); --tw-ring-color: var(--stj-primary-soft);"
                        >
                    </label>

                    <label class="block">
                        <span class="app-muted text-sm font-medium">Titulo</span>
                        <input
                            v-model="form.title"
                            required
                            maxlength="100"
                            type="text"
                            class="app-surface app-text mt-2 h-11 w-full rounded-md border px-3 text-sm outline-none focus:ring-4"
                            style="border-color: var(--stj-border); --tw-ring-color: var(--stj-primary-soft);"
                        >
                    </label>

                    <label class="block">
                        <span class="app-muted text-sm font-medium">Imagen banner</span>
                        <input
                            ref="bannerInput"
                            required
                            accept="image/*"
                            type="file"
                            class="app-surface app-text mt-2 block h-11 w-full rounded-md border px-3 py-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-blue-700"
                            style="border-color: var(--stj-border);"
                            @change="form.banner = $event.target.files[0] || null"
                        >
                    </label>

                    <label class="block">
                        <span class="app-muted text-sm font-medium">Productos Excel</span>
                        <input
                            ref="productsInput"
                            required
                            accept=".xlsx,.csv"
                            type="file"
                            class="app-surface app-text mt-2 block h-11 w-full rounded-md border px-3 py-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-blue-700"
                            style="border-color: var(--stj-border);"
                            @change="form.products = $event.target.files[0] || null"
                        >
                    </label>
                </div>
            </form>

            <div class="app-surface mt-6 rounded-lg border p-4">
                <div v-if="error" class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
                    {{ error }}
                </div>

                <div v-else-if="loading" class="app-muted px-4 py-8 text-center text-sm">
                    Cargando colecciones...
                </div>

                <template v-else>
                    <div v-if="actionMessage" class="mb-4 rounded-md border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
                        {{ actionMessage }}
                    </div>

                    <div @click="handleTableAction">
                        <DataTable
                            :key="tableKey"
                            :data="rows"
                            :columns="columns"
                            :options="options"
                            class="display stj-data-table w-full"
                        />
                    </div>
                </template>
            </div>
        </section>

        <div
            v-if="detailCollection"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/55 px-4 py-6"
            role="dialog"
            aria-modal="true"
            @click.self="closeCollectionDetails"
        >
            <div class="app-surface max-h-[92vh] w-full max-w-5xl overflow-hidden rounded-lg border shadow-2xl">
                <div class="flex items-start justify-between gap-4 border-b px-6 py-5" style="border-color: var(--stj-border);">
                    <div>
                        <p class="app-primary-text text-xs font-semibold uppercase tracking-[0.18em]">
                            Detalle de coleccion
                        </p>
                        <h2 class="app-text mt-2 text-2xl font-semibold">
                            {{ detailCollection.name }}
                        </h2>
                        <p class="app-muted mt-1 text-sm">
                            {{ detailCollection.country?.code }} - {{ detailCollection.country?.name }}
                        </p>
                    </div>

                    <button
                        type="button"
                        class="stj-modal-close"
                        aria-label="Cerrar"
                        title="Cerrar"
                        @click="closeCollectionDetails"
                    >
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="m6.7 5.6 5.3 5.3 5.3-5.3 1.1 1.1-5.3 5.3 5.3 5.3-1.1 1.1-5.3-5.3-5.3 5.3-1.1-1.1 5.3-5.3-5.3-5.3 1.1-1.1Z" />
                        </svg>
                    </button>
                </div>

                <div class="max-h-[calc(92vh-90px)] overflow-y-auto p-6">
                    <div class="grid gap-6 lg:grid-cols-[minmax(0,1.1fr)_minmax(320px,0.9fr)]">
                        <div>
                            <div class="overflow-hidden rounded-lg border" style="border-color: var(--stj-border);">
                                <img
                                    v-if="detailCollection.header"
                                    :src="detailCollection.header"
                                    :alt="detailCollection.title || detailCollection.name"
                                    class="h-72 w-full object-cover"
                                >
                                <div v-else class="app-muted flex h-72 items-center justify-center text-sm">
                                    Sin banner
                                </div>
                            </div>

                            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                                <div class="stj-detail-field">
                                    <span>ID</span>
                                    <strong>{{ detailCollection.id }}</strong>
                                </div>
                                <div class="stj-detail-field">
                                    <span>Creado</span>
                                    <strong>{{ formatDate(detailCollection.createdAt) || 'N/D' }}</strong>
                                </div>
                                <div class="stj-detail-field">
                                    <span>Titulo</span>
                                    <strong>{{ detailCollection.title || 'N/D' }}</strong>
                                </div>
                                <div class="stj-detail-field">
                                    <span>Posicion movil</span>
                                    <strong>{{ detailCollection.mobilePosition || 'N/D' }}</strong>
                                </div>
                            </div>

                            <label class="mt-4 block">
                                <span class="app-muted text-sm font-medium">URL banner</span>
                                <input
                                    :value="detailCollection.header"
                                    readonly
                                    class="app-surface app-text mt-2 h-11 w-full rounded-md border px-3 text-sm outline-none"
                                    style="border-color: var(--stj-border);"
                                >
                            </label>
                        </div>

                        <div>
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <h3 class="app-text text-lg font-semibold">Articulos agregados</h3>
                                    <p class="app-muted mt-1 text-sm">
                                        {{ detailCollection.codesCount }} codigos en esta coleccion
                                    </p>
                                </div>
                            </div>

                            <div class="mt-4 max-h-[32rem] overflow-y-auto rounded-lg border" style="border-color: var(--stj-border);">
                                <div
                                    v-if="collectionCodes(detailCollection).length === 0"
                                    class="app-muted px-4 py-8 text-center text-sm"
                                >
                                    Sin codigos registrados
                                </div>

                                <div v-else class="grid grid-cols-2 gap-2 p-3 sm:grid-cols-3">
                                    <span
                                        v-for="code in collectionCodes(detailCollection)"
                                        :key="code"
                                        class="stj-code-pill"
                                    >
                                        {{ code }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="editingCollection"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/55 px-4 py-6"
            role="dialog"
            aria-modal="true"
            @click.self="closeCollectionEdit"
        >
            <form
                class="app-surface max-h-[92vh] w-full max-w-4xl overflow-hidden rounded-lg border shadow-2xl"
                @submit.prevent="submitCollectionEdit"
            >
                <div class="flex items-start justify-between gap-4 border-b px-6 py-5" style="border-color: var(--stj-border);">
                    <div>
                        <p class="app-primary-text text-xs font-semibold uppercase tracking-[0.18em]">
                            Editar coleccion
                        </p>
                        <h2 class="app-text mt-2 text-2xl font-semibold">
                            {{ editingCollection.name }}
                        </h2>
                        <p class="app-muted mt-1 text-sm">
                            Cambia datos generales, banner o reemplaza el Excel de articulos.
                        </p>
                    </div>

                    <button
                        type="button"
                        class="stj-modal-close"
                        aria-label="Cerrar"
                        title="Cerrar"
                        @click="closeCollectionEdit"
                    >
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="m6.7 5.6 5.3 5.3 5.3-5.3 1.1 1.1-5.3 5.3 5.3 5.3-1.1 1.1-5.3-5.3-5.3 5.3-1.1-1.1 5.3-5.3-5.3-5.3 1.1-1.1Z" />
                        </svg>
                    </button>
                </div>

                <div class="max-h-[calc(92vh-164px)] overflow-y-auto p-6">
                    <div v-if="editError" class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
                        {{ editError }}
                    </div>

                    <div v-if="editSuccess" class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                        {{ editSuccess }}
                    </div>

                    <div class="grid gap-6 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
                        <div>
                            <div class="overflow-hidden rounded-lg border" style="border-color: var(--stj-border);">
                                <img
                                    v-if="editingCollection.header"
                                    :src="editingCollection.header"
                                    :alt="editingCollection.title || editingCollection.name"
                                    class="h-64 w-full object-cover"
                                >
                                <div v-else class="app-muted flex h-64 items-center justify-center text-sm">
                                    Sin banner
                                </div>
                            </div>

                            <div class="mt-4 rounded-lg border p-4" style="border-color: var(--stj-border);">
                                <p class="app-muted text-sm font-medium">Articulos actuales</p>
                                <p class="app-text mt-1 text-2xl font-semibold">
                                    {{ editingCollection.codesCount }}
                                </p>
                                <p class="app-muted mt-1 text-sm">
                                    Si subes un nuevo Excel, reemplazara la lista completa.
                                </p>
                            </div>
                        </div>

                        <div class="grid gap-4">
                            <label class="block">
                                <span class="app-muted text-sm font-medium">Pais</span>
                                <select
                                    v-model="editForm.country"
                                    required
                                    class="app-surface app-text mt-2 h-11 w-full rounded-md border px-3 text-sm outline-none focus:ring-4"
                                    style="border-color: var(--stj-border); --tw-ring-color: var(--stj-primary-soft);"
                                >
                                    <option value="">Seleccionar</option>
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
                                <span class="app-muted text-sm font-medium">Nombre</span>
                                <input
                                    v-model="editForm.name"
                                    required
                                    maxlength="100"
                                    type="text"
                                    class="app-surface app-text mt-2 h-11 w-full rounded-md border px-3 text-sm outline-none focus:ring-4"
                                    style="border-color: var(--stj-border); --tw-ring-color: var(--stj-primary-soft);"
                                >
                            </label>

                            <label class="block">
                                <span class="app-muted text-sm font-medium">Titulo</span>
                                <input
                                    v-model="editForm.title"
                                    required
                                    maxlength="100"
                                    type="text"
                                    class="app-surface app-text mt-2 h-11 w-full rounded-md border px-3 text-sm outline-none focus:ring-4"
                                    style="border-color: var(--stj-border); --tw-ring-color: var(--stj-primary-soft);"
                                >
                            </label>

                            <label class="block">
                                <span class="app-muted text-sm font-medium">Reemplazar imagen banner</span>
                                <input
                                    ref="editBannerInput"
                                    accept="image/*"
                                    type="file"
                                    class="app-surface app-text mt-2 block h-11 w-full rounded-md border px-3 py-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-blue-700"
                                    style="border-color: var(--stj-border);"
                                    @change="editForm.banner = $event.target.files[0] || null"
                                >
                            </label>

                            <label class="block">
                                <span class="app-muted text-sm font-medium">Reemplazar productos Excel</span>
                                <input
                                    ref="editProductsInput"
                                    accept=".xlsx,.csv"
                                    type="file"
                                    class="app-surface app-text mt-2 block h-11 w-full rounded-md border px-3 py-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-blue-700"
                                    style="border-color: var(--stj-border);"
                                    @change="editForm.products = $event.target.files[0] || null"
                                >
                            </label>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col-reverse gap-3 border-t px-6 py-4 sm:flex-row sm:justify-end" style="border-color: var(--stj-border);">
                    <button
                        type="button"
                        class="inline-flex h-11 items-center justify-center rounded-md border px-5 text-sm font-semibold transition"
                        style="border-color: var(--stj-border); color: var(--stj-text);"
                        @click="closeCollectionEdit"
                    >
                        Cancelar
                    </button>
                    <button
                        type="submit"
                        class="inline-flex h-11 items-center justify-center rounded-md bg-blue-600 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="editSaving"
                    >
                        {{ editSaving ? 'Guardando...' : 'Guardar cambios' }}
                    </button>
                </div>
            </form>
        </div>

        <div
            v-if="assetCollection"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/55 px-4 py-6"
            role="dialog"
            aria-modal="true"
            @click.self="closeCollectionAssets"
        >
            <div class="app-surface max-h-[92vh] w-full max-w-6xl overflow-hidden rounded-lg border shadow-2xl">
                <div class="flex items-start justify-between gap-4 border-b px-6 py-5" style="border-color: var(--stj-border);">
                    <div>
                        <p class="app-primary-text text-xs font-semibold uppercase tracking-[0.18em]">
                            Assets de coleccion
                        </p>
                        <h2 class="app-text mt-2 text-2xl font-semibold">
                            {{ assetCollection.name }}
                        </h2>
                        <p class="app-muted mt-1 text-sm">
                            {{ collectionAssetLink || 'Link automatico de coleccion' }}
                        </p>
                    </div>

                    <button
                        type="button"
                        class="stj-modal-close"
                        aria-label="Cerrar"
                        title="Cerrar"
                        @click="closeCollectionAssets"
                    >
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="m6.7 5.6 5.3 5.3 5.3-5.3 1.1 1.1-5.3 5.3 5.3 5.3-1.1 1.1-5.3-5.3-5.3 5.3-1.1-1.1 5.3-5.3-5.3-5.3 1.1-1.1Z" />
                        </svg>
                    </button>
                </div>

                <div class="max-h-[calc(92vh-90px)] overflow-y-auto p-6">
                    <div v-if="assetError" class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
                        {{ assetError }}
                    </div>

                    <div v-if="assetSuccess" class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                        {{ assetSuccess }}
                    </div>

                    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(360px,0.78fr)]">
                        <div>
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <h3 class="app-text text-lg font-semibold">Assets ligados</h3>
                                    <p class="app-muted mt-1 text-sm">
                                        Imagenes que apuntan a esta coleccion.
                                    </p>
                                </div>
                            </div>

                            <div v-if="assetsLoading" class="app-muted mt-4 rounded-lg border px-4 py-8 text-center text-sm" style="border-color: var(--stj-border);">
                                Cargando assets...
                            </div>

                            <div v-else-if="collectionAssets.length === 0" class="app-muted mt-4 rounded-lg border px-4 py-8 text-center text-sm" style="border-color: var(--stj-border);">
                                Todavia no hay assets para esta coleccion.
                            </div>

                            <div v-else class="mt-4 grid gap-3">
                                <article
                                    v-for="asset in collectionAssets"
                                    :key="asset.id"
                                    class="stj-asset-item"
                                >
                                    <div class="h-20 w-28 overflow-hidden rounded-md border" style="border-color: var(--stj-border);">
                                        <img
                                            v-if="assetPreview(asset)"
                                            :src="assetPreview(asset)"
                                            :alt="asset.title || asset.type"
                                            class="h-full w-full object-cover"
                                        >
                                        <div v-else class="app-muted flex h-full items-center justify-center text-xs">
                                            Sin imagen
                                        </div>
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="stj-asset-badge">{{ asset.type }}</span>
                                            <span class="stj-asset-badge">{{ asset.platform }}</span>
                                            <span class="stj-asset-badge">{{ asset.status }}</span>
                                            <span v-if="asset.position" class="stj-asset-badge">{{ asset.position }}</span>
                                        </div>
                                        <p class="app-text mt-2 truncate text-sm font-semibold">
                                            {{ asset.title || asset.link }}
                                        </p>
                                        <p class="app-muted mt-1 text-xs">
                                            Orden {{ asset.order || 0 }} · {{ formatDate(asset.startAt) }} - {{ formatDate(asset.endAt) }}
                                        </p>
                                    </div>
                                </article>
                            </div>
                        </div>

                        <form class="rounded-lg border p-4" style="border-color: var(--stj-border);" @submit.prevent="submitCollectionAsset">
                            <h3 class="app-text text-lg font-semibold">Agregar asset</h3>

                            <div class="mt-4 grid gap-4">
                                <label class="block">
                                    <span class="app-muted text-sm font-medium">Tipo</span>
                                    <select
                                        v-model="assetForm.type"
                                        required
                                        class="app-surface app-text mt-2 h-11 w-full rounded-md border px-3 text-sm outline-none focus:ring-4"
                                        style="border-color: var(--stj-border); --tw-ring-color: var(--stj-primary-soft);"
                                    >
                                        <option value="BANNER">BANNER</option>
                                        <option value="SLIDER">SLIDER</option>
                                        <option value="MODAL">MODAL</option>
                                        <option value="CUPON">CUPON</option>
                                        <option value="LO-MAS-NUEVO">LO-MAS-NUEVO</option>
                                    </select>
                                </label>

                                <div class="grid gap-3 sm:grid-cols-2">
                                    <label class="block">
                                        <span class="app-muted text-sm font-medium">Plataforma</span>
                                        <select
                                            v-model="assetForm.platform"
                                            class="app-surface app-text mt-2 h-11 w-full rounded-md border px-3 text-sm outline-none focus:ring-4"
                                            style="border-color: var(--stj-border); --tw-ring-color: var(--stj-primary-soft);"
                                        >
                                            <option value="WEB">WEB</option>
                                            <option value="APP">APP</option>
                                            <option value="TODO">TODO</option>
                                        </select>
                                    </label>

                                    <label class="block">
                                        <span class="app-muted text-sm font-medium">Estado</span>
                                        <select
                                            v-model="assetForm.status"
                                            class="app-surface app-text mt-2 h-11 w-full rounded-md border px-3 text-sm outline-none focus:ring-4"
                                            style="border-color: var(--stj-border); --tw-ring-color: var(--stj-primary-soft);"
                                        >
                                            <option value="PENDIENTE">PENDIENTE</option>
                                            <option value="ACTIVO">ACTIVO</option>
                                            <option value="FINALIZADO">FINALIZADO</option>
                                            <option value="CANCELADO">CANCELADO</option>
                                        </select>
                                    </label>
                                </div>

                                <div class="grid gap-3 sm:grid-cols-2">
                                    <label class="block">
                                        <span class="app-muted text-sm font-medium">Orden</span>
                                        <input
                                            v-model="assetForm.order"
                                            min="0"
                                            type="number"
                                            class="app-surface app-text mt-2 h-11 w-full rounded-md border px-3 text-sm outline-none focus:ring-4"
                                            style="border-color: var(--stj-border); --tw-ring-color: var(--stj-primary-soft);"
                                        >
                                    </label>

                                    <label class="block">
                                        <span class="app-muted text-sm font-medium">Posicion</span>
                                        <select
                                            v-model="assetForm.position"
                                            :disabled="assetForm.type !== 'LO-MAS-NUEVO'"
                                            class="app-surface app-text mt-2 h-11 w-full rounded-md border px-3 text-sm outline-none focus:ring-4 disabled:opacity-60"
                                            style="border-color: var(--stj-border); --tw-ring-color: var(--stj-primary-soft);"
                                        >
                                            <option value="">No aplica</option>
                                            <option value="IZQUIERDA">IZQUIERDA</option>
                                            <option value="DERECHA">DERECHA</option>
                                            <option value="CENTRO">CENTRO</option>
                                        </select>
                                    </label>
                                </div>

                                <label class="block">
                                    <span class="app-muted text-sm font-medium">Titulo</span>
                                    <input
                                        v-model="assetForm.title"
                                        maxlength="45"
                                        type="text"
                                        class="app-surface app-text mt-2 h-11 w-full rounded-md border px-3 text-sm outline-none focus:ring-4"
                                        style="border-color: var(--stj-border); --tw-ring-color: var(--stj-primary-soft);"
                                    >
                                </label>

                                <div class="grid gap-3 sm:grid-cols-2">
                                    <label class="block">
                                        <span class="app-muted text-sm font-medium">Inicio</span>
                                        <input
                                            v-model="assetForm.startAt"
                                            required
                                            type="datetime-local"
                                            class="app-surface app-text mt-2 h-11 w-full rounded-md border px-3 text-sm outline-none focus:ring-4"
                                            style="border-color: var(--stj-border); --tw-ring-color: var(--stj-primary-soft);"
                                        >
                                    </label>

                                    <label class="block">
                                        <span class="app-muted text-sm font-medium">Fin</span>
                                        <input
                                            v-model="assetForm.endAt"
                                            required
                                            type="datetime-local"
                                            class="app-surface app-text mt-2 h-11 w-full rounded-md border px-3 text-sm outline-none focus:ring-4"
                                            style="border-color: var(--stj-border); --tw-ring-color: var(--stj-primary-soft);"
                                        >
                                    </label>
                                </div>

                                <label class="block">
                                    <span class="app-muted text-sm font-medium">Imagen desktop</span>
                                    <input
                                        ref="assetImageInput"
                                        required
                                        accept="image/*"
                                        type="file"
                                        class="app-surface app-text mt-2 block h-11 w-full rounded-md border px-3 py-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-blue-700"
                                        style="border-color: var(--stj-border);"
                                        @change="assetForm.image = $event.target.files[0] || null"
                                    >
                                </label>

                                <label class="block">
                                    <span class="app-muted text-sm font-medium">Imagen movil</span>
                                    <input
                                        ref="assetMobileImageInput"
                                        accept="image/*"
                                        type="file"
                                        class="app-surface app-text mt-2 block h-11 w-full rounded-md border px-3 py-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-blue-700"
                                        style="border-color: var(--stj-border);"
                                        @change="assetForm.mobileImage = $event.target.files[0] || null"
                                    >
                                </label>

                                <button
                                    type="submit"
                                    class="inline-flex h-11 items-center justify-center rounded-md bg-blue-600 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                                    :disabled="assetSaving"
                                >
                                    {{ assetSaving ? 'Guardando...' : 'Guardar asset' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<style>
.stj-data-table .dt-actions {
    text-align: right;
}

.stj-row-actions {
    display: inline-flex;
    gap: 0.375rem;
    justify-content: flex-end;
    min-width: 7.5rem;
}

.stj-action-button {
    align-items: center;
    background: var(--stj-surface);
    border: 1px solid var(--stj-border);
    border-radius: 0.375rem;
    color: var(--stj-muted);
    display: inline-flex;
    height: 2rem;
    justify-content: center;
    transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease;
    width: 2rem;
}

.stj-action-button:hover {
    background: var(--stj-primary-soft);
    border-color: var(--stj-primary);
    color: var(--stj-primary);
}

.stj-action-button svg {
    fill: currentColor;
    height: 1rem;
    width: 1rem;
}

.stj-modal-close {
    align-items: center;
    background: var(--stj-surface);
    border: 1px solid var(--stj-border);
    border-radius: 0.375rem;
    color: var(--stj-muted);
    display: inline-flex;
    flex: 0 0 auto;
    height: 2.25rem;
    justify-content: center;
    transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease;
    width: 2.25rem;
}

.stj-modal-close:hover {
    background: var(--stj-primary-soft);
    border-color: var(--stj-primary);
    color: var(--stj-primary);
}

.stj-modal-close svg {
    fill: currentColor;
    height: 1.25rem;
    width: 1.25rem;
}

.stj-detail-field {
    background: color-mix(in srgb, var(--stj-surface) 88%, var(--stj-primary-soft));
    border: 1px solid var(--stj-border);
    border-radius: 0.5rem;
    padding: 0.875rem 1rem;
}

.stj-detail-field span {
    color: var(--stj-muted);
    display: block;
    font-size: 0.75rem;
    font-weight: 600;
    margin-bottom: 0.35rem;
    text-transform: uppercase;
}

.stj-detail-field strong {
    color: var(--stj-text);
    display: block;
    font-size: 0.95rem;
    font-weight: 700;
    overflow-wrap: anywhere;
}

.stj-code-pill {
    background: var(--stj-primary-soft);
    border: 1px solid color-mix(in srgb, var(--stj-primary) 34%, var(--stj-border));
    border-radius: 0.375rem;
    color: var(--stj-text);
    display: inline-flex;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
    font-size: 0.82rem;
    font-weight: 700;
    justify-content: center;
    min-height: 2rem;
    padding: 0.4rem 0.55rem;
}

.stj-asset-item {
    align-items: center;
    border: 1px solid var(--stj-border);
    border-radius: 0.5rem;
    display: flex;
    gap: 0.875rem;
    padding: 0.875rem;
}

.stj-asset-badge {
    background: var(--stj-primary-soft);
    border-radius: 999px;
    color: var(--stj-primary);
    display: inline-flex;
    font-size: 0.68rem;
    font-weight: 800;
    letter-spacing: 0.02em;
    padding: 0.2rem 0.55rem;
}
</style>
