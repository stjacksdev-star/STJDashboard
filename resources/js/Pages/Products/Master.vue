<script setup>
import { Head } from '@inertiajs/vue3';
import DataTable from 'datatables.net-vue3';
import DataTablesCore from 'datatables.net-dt';
import { computed, onMounted, ref } from 'vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';

DataTable.use(DataTablesCore);

const loading = ref(true);
const importing = ref(false);
const photoImporting = ref(false);
const detailLoading = ref(false);
const photosLoading = ref(false);
const countriesLoading = ref(false);
const error = ref('');
const importError = ref('');
const photoImportError = ref('');
const modalError = ref('');
const success = ref('');
const photoSuccess = ref('');
const search = ref('');
const products = ref([]);
const selectedProduct = ref(null);
const productDetail = ref(null);
const productPhotos = ref(null);
const productCountries = ref(null);
const importResult = ref(null);
const photoImportResult = ref(null);
const selectedFile = ref(null);
const selectedPhotoFile = ref(null);
const fileInput = ref(null);
const photoFileInput = ref(null);
const tableKey = ref(0);
const activeImportTab = ref('products');
const showDetailModal = ref(false);
const showPhotosModal = ref(false);
const showCountriesModal = ref(false);

const columns = [
    { data: 'id', title: 'ID', width: '70px' },
    { data: 'code', title: 'Codigo' },
    { data: 'name', title: 'Nombre' },
    { data: 'brandLabel', title: 'Marca' },
    { data: 'categoryLabel', title: 'Categoria' },
    { data: 'subcategoryLabel', title: 'Subcategoria' },
    { data: 'sizes', title: 'Tallas' },
    { data: 'collectionLabel', title: 'Coleccion' },
    { data: 'statusLabel', title: 'Estado', width: '95px' },
    { data: 'createdAtLabel', title: 'Registro' },
    { data: 'actions', title: 'Acciones', orderable: false, searchable: false, width: '150px' },
];

const dataTableOptions = {
    pageLength: 10,
    lengthMenu: [10, 25, 50, 100],
    order: [[0, 'desc']],
    autoWidth: false,
    language: {
        search: 'Buscar:',
        lengthMenu: 'Mostrar _MENU_ registros',
        info: 'Mostrando _START_ a _END_ de _TOTAL_ productos',
        infoEmpty: 'Sin productos',
        infoFiltered: '(filtrado de _MAX_ registros)',
        zeroRecords: 'No se encontraron productos',
        emptyTable: 'No hay productos disponibles',
        paginate: {
            first: 'Primero',
            last: 'Ultimo',
            next: 'Siguiente',
            previous: 'Anterior',
        },
    },
};

const rows = computed(() =>
    products.value.map((product) => ({
        ...product,
        brandLabel: product.brand || 'N/D',
        categoryLabel: product.category?.name || 'N/D',
        subcategoryLabel: product.subcategory?.name || 'N/D',
        collectionLabel: product.collection || 'N/D',
        statusLabel: statusHtml(product.status),
        createdAtLabel: formatDate(product.createdAt),
        actions: actionsHtml(product.id),
    })),
);

const summaryCards = computed(() => {
    return resultCards(importResult.value);
});

const canPrintImportLog = computed(() => Boolean(importResult.value?.log?.length));
const photoSummaryCards = computed(() => resultCards(photoImportResult.value, true));
const canPrintPhotoImportLog = computed(() => Boolean(photoImportResult.value?.log?.length));

onMounted(fetchProducts);

async function fetchProducts() {
    loading.value = true;
    error.value = '';

    try {
        const response = await window.axios.get('/dashboard-api/products/master', {
            params: {
                search: search.value || undefined,
            },
        });

        products.value = response.data.data?.products || [];
        tableKey.value += 1;
    } catch (exception) {
        error.value = exception.response?.data?.message || 'No fue posible cargar los productos.';
    } finally {
        loading.value = false;
    }
}

async function importProducts() {
    if (! selectedFile.value) {
        importError.value = 'Debe seleccionar un archivo Excel.';
        return;
    }

    importing.value = true;
    importError.value = '';
    success.value = '';
    importResult.value = null;

    const payload = new FormData();
    payload.append('file', selectedFile.value);

    try {
        const response = await window.axios.post('/dashboard-api/products/master/import', payload, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        importResult.value = response.data.data;
        success.value = response.data.message || 'Importacion finalizada.';
        selectedFile.value = null;

        if (fileInput.value) {
            fileInput.value.value = '';
        }

        await fetchProducts();
    } catch (exception) {
        importError.value = firstError(exception.response?.data?.errors)
            || exception.response?.data?.message
            || 'No fue posible importar el archivo.';
    } finally {
        importing.value = false;
    }
}

async function importPhotos() {
    if (! selectedPhotoFile.value) {
        photoImportError.value = 'Debe seleccionar un archivo Excel.';
        return;
    }

    photoImporting.value = true;
    photoImportError.value = '';
    photoSuccess.value = '';
    photoImportResult.value = null;

    const payload = new FormData();
    payload.append('file', selectedPhotoFile.value);

    try {
        const response = await window.axios.post('/dashboard-api/products/master/photos/import', payload, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        photoImportResult.value = response.data.data;
        photoSuccess.value = response.data.message || 'Importacion de fotografias finalizada.';
        selectedPhotoFile.value = null;

        if (photoFileInput.value) {
            photoFileInput.value.value = '';
        }

        await fetchProducts();
    } catch (exception) {
        photoImportError.value = firstError(exception.response?.data?.errors)
            || exception.response?.data?.message
            || 'No fue posible importar el archivo de fotografias.';
    } finally {
        photoImporting.value = false;
    }
}

async function openProductDetail(product) {
    selectedProduct.value = product;
    showDetailModal.value = true;
    detailLoading.value = true;
    modalError.value = '';
    productDetail.value = null;

    try {
        const response = await window.axios.get(`/dashboard-api/products/master/${product.id}`);
        productDetail.value = response.data.data;
    } catch (exception) {
        modalError.value = exception.response?.data?.message || 'No fue posible cargar el detalle del producto.';
    } finally {
        detailLoading.value = false;
    }
}

async function openProductPhotos(product) {
    selectedProduct.value = product;
    showPhotosModal.value = true;
    photosLoading.value = true;
    modalError.value = '';
    productPhotos.value = null;

    try {
        const response = await window.axios.get(`/dashboard-api/products/master/${product.id}/photos`);
        productPhotos.value = response.data.data;
    } catch (exception) {
        modalError.value = exception.response?.data?.message || 'No fue posible cargar las fotos del producto.';
    } finally {
        photosLoading.value = false;
    }
}

async function openProductCountries(product) {
    selectedProduct.value = product;
    showCountriesModal.value = true;
    countriesLoading.value = true;
    modalError.value = '';
    productCountries.value = null;

    try {
        const response = await window.axios.get(`/dashboard-api/products/master/${product.id}/countries`);
        productCountries.value = response.data.data;
    } catch (exception) {
        modalError.value = exception.response?.data?.message || 'No fue posible cargar los paises del producto.';
    } finally {
        countriesLoading.value = false;
    }
}

function handleTableAction(event) {
    const button = event.target.closest('[data-product-action]');

    if (! button) {
        return;
    }

    const product = products.value.find((item) => item.id === Number(button.dataset.productId));

    if (! product) {
        return;
    }

    if (button.dataset.productAction === 'detail') {
        openProductDetail(product);
        return;
    }

    if (button.dataset.productAction === 'photos') {
        openProductPhotos(product);
        return;
    }

    if (button.dataset.productAction === 'countries') {
        openProductCountries(product);
    }
}

function handleFile(event) {
    selectedFile.value = event.target.files[0] || null;
    importError.value = '';
}

function handlePhotoFile(event) {
    selectedPhotoFile.value = event.target.files[0] || null;
    photoImportError.value = '';
}

function printImportLog(result = importResult.value, title = 'Log importacion productos') {
    if (! result?.log?.length) {
        return;
    }

    const summary = result.summary || {};
    const generatedAt = new Intl.DateTimeFormat('es-SV', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date());
    const rows = result.log
        .map((item) => `
            <tr>
                <td>${escapeHtml(item.row)}</td>
                <td>${escapeHtml(item.code || 'N/D')}</td>
                <td>${escapeHtml(statusLabel(item.status))}</td>
                <td>${escapeHtml(item.message || '')}</td>
            </tr>
        `)
        .join('');
    const popup = window.open('', '_blank', 'width=1100,height=800');

    if (! popup) {
        error.value = 'El navegador bloqueo la ventana de impresion.';
        return;
    }

    popup.document.write(`
        <!doctype html>
        <html>
            <head>
                <meta charset="utf-8">
                <title>${escapeHtml(title)}</title>
                <style>
                    * { box-sizing: border-box; }
                    body { color: #111827; font-family: Arial, sans-serif; margin: 28px; }
                    h1 { font-size: 22px; margin: 0 0 6px; }
                    .muted { color: #6b7280; font-size: 12px; margin-bottom: 18px; }
                    .summary { display: grid; gap: 10px; grid-template-columns: repeat(5, 1fr); margin: 18px 0; }
                    .card { border: 1px solid #d1d5db; border-radius: 6px; padding: 10px; }
                    .card span { color: #6b7280; display: block; font-size: 10px; font-weight: 700; text-transform: uppercase; }
                    .card strong { display: block; font-size: 20px; margin-top: 4px; }
                    table { border-collapse: collapse; font-size: 12px; width: 100%; }
                    th, td { border: 1px solid #d1d5db; padding: 7px; text-align: left; vertical-align: top; }
                    th { background: #f3f4f6; font-size: 11px; text-transform: uppercase; }
                    tr:nth-child(even) td { background: #f9fafb; }
                    @media print {
                        body { margin: 14mm; }
                        .no-print { display: none; }
                        .summary { break-inside: avoid; }
                    }
                </style>
            </head>
            <body>
                <button class="no-print" onclick="window.print()" style="margin-bottom: 16px;">Guardar como PDF / imprimir</button>
                <h1>${escapeHtml(title)}</h1>
                <div class="muted">Generado: ${escapeHtml(generatedAt)}</div>
                <div class="summary">
                    <div class="card"><span>Filas</span><strong>${escapeHtml(summary.rows ?? 0)}</strong></div>
                    <div class="card"><span>Creados</span><strong>${escapeHtml(summary.created ?? 0)}</strong></div>
                    <div class="card"><span>Actualizados</span><strong>${escapeHtml(summary.updated ?? 0)}</strong></div>
                    <div class="card"><span>Omitidos</span><strong>${escapeHtml(summary.skipped ?? 0)}</strong></div>
                    <div class="card"><span>Errores</span><strong>${escapeHtml(summary.failed ?? 0)}</strong></div>
                    ${summary.spaces !== undefined ? `<div class="card"><span>Spaces</span><strong>${escapeHtml(summary.spaces ?? 0)}</strong></div>` : ''}
                    ${summary.local !== undefined ? `<div class="card"><span>Local</span><strong>${escapeHtml(summary.local ?? 0)}</strong></div>` : ''}
                    ${summary.omittedByLimit !== undefined ? `<div class="card"><span>Fuera limite</span><strong>${escapeHtml(summary.omittedByLimit ?? 0)}</strong></div>` : ''}
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Fila</th>
                            <th>Codigo</th>
                            <th>Estado</th>
                            <th>Mensaje</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
                <script>
                    window.addEventListener('load', () => setTimeout(() => window.print(), 250));
                <\/script>
            </body>
        </html>
    `);
    popup.document.close();
}

function resultCards(result, includeStorage = false) {
    const summary = result?.summary;

    if (! summary) {
        return [];
    }

    const cards = [
        ['Filas', summary.rows],
        ['Creados', summary.created],
        ['Actualizados', summary.updated],
        ['Omitidos', summary.skipped],
        ['Errores', summary.failed],
    ];

    if (includeStorage) {
        cards.push(['Spaces', summary.spaces || 0]);
        cards.push(['Local', summary.local || 0]);
        cards.push(['Fuera limite', summary.omittedByLimit || 0]);
    }

    return cards;
}

function statusHtml(status) {
    const active = status === 'ACTIVO';

    return `<span class="stj-status ${active ? 'stj-status-active' : ''}">${escapeHtml(status || 'N/D')}</span>`;
}

function actionsHtml(id) {
    return `
        <div class="stj-row-actions">
            <button type="button" class="stj-icon-btn" data-product-action="detail" data-product-id="${id}" title="Ver detalle">
                <svg viewBox="0 0 24 24"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
            <button type="button" class="stj-icon-btn" data-product-action="photos" data-product-id="${id}" title="Ver fotos">
                <svg viewBox="0 0 24 24"><path d="M4 5h16v14H4z"/><path d="m4 16 4-4 3 3 2-2 7 6"/><circle cx="15.5" cy="9.5" r="1.5"/></svg>
            </button>
            <button type="button" class="stj-icon-btn" data-product-action="countries" data-product-id="${id}" title="Ver paises">
                <svg viewBox="0 0 24 24"><path d="M4 22V4"/><path d="M4 4h13l-1 5 1 5H4"/></svg>
            </button>
        </div>
    `;
}

function closeModals() {
    showDetailModal.value = false;
    showPhotosModal.value = false;
    showCountriesModal.value = false;
    selectedProduct.value = null;
    productDetail.value = null;
    productPhotos.value = null;
    productCountries.value = null;
    modalError.value = '';
}

function detailRows(fields) {
    if (! fields) {
        return [];
    }

    return Object.entries(fields).map(([key, value]) => ({
        key,
        value: value === null || value === undefined || value === '' ? 'N/D' : value,
    }));
}

function statusLabel(status) {
    return {
        created: 'Creado',
        updated: 'Actualizado',
        skipped: 'Omitido',
        error: 'Error',
    }[status] || status || 'N/D';
}

function formatDate(value) {
    if (! value) {
        return 'N/D';
    }

    return new Intl.DateTimeFormat('es-SV', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).format(new Date(value));
}

function formatMoney(value) {
    if (value === null || value === undefined || value === '') {
        return 'N/D';
    }

    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value));
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
        <Head title="Productos / Maestro" />

        <div class="mx-auto max-w-7xl space-y-6">
            <section class="app-surface rounded-lg border p-5 shadow-sm" style="border-color: var(--stj-border);">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="app-primary-text text-xs font-semibold uppercase">Productos</p>
                        <h1 class="app-text mt-2 text-2xl font-bold">Maestro</h1>
                    </div>

                    <form class="flex flex-col gap-3 sm:flex-row sm:items-end" @submit.prevent="fetchProducts">
                        <label class="block">
                            <span class="app-muted text-sm font-medium">Buscar</span>
                            <input
                                v-model.trim="search"
                                type="search"
                                class="stj-input mt-2 h-11 w-full sm:w-80"
                                placeholder="Codigo, nombre, coleccion"
                            >
                        </label>

                        <button
                            type="submit"
                            class="inline-flex h-11 items-center justify-center rounded-md bg-blue-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700"
                        >
                            Consultar
                        </button>
                    </form>
                </div>
            </section>

            <section class="app-surface rounded-lg border p-5 shadow-sm" style="border-color: var(--stj-border);">
                <div class="mb-5 inline-flex rounded-md border p-1" style="border-color: var(--stj-border);">
                    <button
                        type="button"
                        :class="['stj-tab-btn', activeImportTab === 'products' ? 'stj-tab-btn-active' : '']"
                        @click="activeImportTab = 'products'"
                    >
                        Productos
                    </button>
                    <button
                        type="button"
                        :class="['stj-tab-btn', activeImportTab === 'photos' ? 'stj-tab-btn-active' : '']"
                        @click="activeImportTab = 'photos'"
                    >
                        Fotos
                    </button>
                </div>

                <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(320px,420px)]">
                    <div v-if="activeImportTab === 'products'">
                        <h2 class="app-text text-lg font-semibold">Importar Excel</h2>
                        <p class="app-muted mt-2 text-sm">
                            Se procesa solo la hoja 1 del archivo con columnas A-R del flujo anterior.
                        </p>

                        <form class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end" @submit.prevent="importProducts">
                            <label class="block flex-1">
                                <span class="app-muted text-sm font-medium">Archivo</span>
                                <input
                                    ref="fileInput"
                                    accept=".xlsx,.xls"
                                    type="file"
                                    class="app-surface app-text mt-2 block h-11 w-full rounded-md border px-3 py-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-blue-700"
                                    style="border-color: var(--stj-border);"
                                    @change="handleFile"
                                >
                            </label>

                            <button
                                type="submit"
                                class="inline-flex h-11 items-center justify-center rounded-md bg-blue-600 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="importing"
                            >
                                {{ importing ? 'Procesando...' : 'Importar' }}
                            </button>
                        </form>

                        <div v-if="importError" class="mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                            {{ importError }}
                        </div>

                        <div v-if="success" class="mt-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                            {{ success }}
                        </div>
                    </div>

                    <div v-else>
                        <h2 class="app-text text-lg font-semibold">Importar fotos</h2>
                        <p class="app-muted mt-2 text-sm">
                            El Excel debe traer columnas: codigo, orden y url. Se sube primero a DigitalOcean Spaces; si falla, se guarda localmente.
                        </p>
                        <p class="mt-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                            Por seguridad se procesan maximo 30 imagenes por archivo. Las filas posteriores se omiten y quedan reflejadas en el resumen.
                        </p>

                        <form class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end" @submit.prevent="importPhotos">
                            <label class="block flex-1">
                                <span class="app-muted text-sm font-medium">Archivo</span>
                                <input
                                    ref="photoFileInput"
                                    accept=".xlsx,.xls"
                                    type="file"
                                    class="app-surface app-text mt-2 block h-11 w-full rounded-md border px-3 py-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-blue-700"
                                    style="border-color: var(--stj-border);"
                                    @change="handlePhotoFile"
                                >
                            </label>

                            <button
                                type="submit"
                                class="inline-flex h-11 items-center justify-center rounded-md bg-blue-600 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                                :disabled="photoImporting"
                            >
                                {{ photoImporting ? 'Procesando...' : 'Importar fotos' }}
                            </button>
                        </form>

                        <div v-if="photoImportError" class="mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                            {{ photoImportError }}
                        </div>

                        <div v-if="photoSuccess" class="mt-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                            {{ photoSuccess }}
                        </div>
                    </div>

                    <div v-if="activeImportTab === 'products' && summaryCards.length" class="rounded-md border p-4" style="border-color: var(--stj-border);">
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="app-text text-sm font-semibold">Resultado</h3>
                            <button
                                v-if="canPrintImportLog"
                                type="button"
                                class="inline-flex h-9 items-center justify-center rounded-md border px-3 text-xs font-semibold transition hover:bg-blue-50"
                                style="border-color: var(--stj-border); color: var(--stj-primary);"
                                @click="printImportLog(importResult, 'Log importacion productos')"
                            >
                                Guardar PDF
                            </button>
                        </div>
                        <div class="mt-3 grid grid-cols-2 gap-3">
                            <div v-for="[label, value] in summaryCards" :key="label" class="app-surface-soft rounded-md p-3">
                                <p class="app-muted text-xs font-semibold uppercase">{{ label }}</p>
                                <p class="app-text mt-1 text-xl font-bold">{{ value }}</p>
                            </div>
                        </div>

                        <p v-if="importResult?.summary?.photosIgnored" class="app-muted mt-3 text-sm">
                            El archivo trae hoja de fotos; no se proceso en este paso.
                        </p>
                    </div>

                    <div v-if="activeImportTab === 'photos' && photoSummaryCards.length" class="rounded-md border p-4" style="border-color: var(--stj-border);">
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="app-text text-sm font-semibold">Resultado</h3>
                            <button
                                v-if="canPrintPhotoImportLog"
                                type="button"
                                class="inline-flex h-9 items-center justify-center rounded-md border px-3 text-xs font-semibold transition hover:bg-blue-50"
                                style="border-color: var(--stj-border); color: var(--stj-primary);"
                                @click="printImportLog(photoImportResult, 'Log importacion fotos')"
                            >
                                Guardar PDF
                            </button>
                        </div>
                        <div class="mt-3 grid grid-cols-2 gap-3">
                            <div v-for="[label, value] in photoSummaryCards" :key="label" class="app-surface-soft rounded-md p-3">
                                <p class="app-muted text-xs font-semibold uppercase">{{ label }}</p>
                                <p class="app-text mt-1 text-xl font-bold">{{ value }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="activeImportTab === 'products' && importResult?.log?.length" class="mt-5 max-h-72 overflow-auto rounded-md border" style="border-color: var(--stj-border);">
                    <div class="app-border-soft flex items-center justify-between gap-3 border-b px-3 py-2">
                        <p class="app-text text-sm font-semibold">Detalle por fila</p>
                        <button
                            type="button"
                            class="inline-flex h-9 items-center justify-center rounded-md border px-3 text-xs font-semibold transition hover:bg-blue-50"
                            style="border-color: var(--stj-border); color: var(--stj-primary);"
                            @click="printImportLog(importResult, 'Log importacion productos')"
                        >
                            Guardar PDF
                        </button>
                    </div>
                    <table class="w-full text-left text-sm">
                        <thead class="app-surface-soft app-text sticky top-0">
                            <tr>
                                <th class="px-3 py-2">Fila</th>
                                <th class="px-3 py-2">Codigo</th>
                                <th class="px-3 py-2">Estado</th>
                                <th class="px-3 py-2">Mensaje</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in importResult.log" :key="`${item.row}-${item.code}-${item.status}`" class="app-border-soft border-t">
                                <td class="px-3 py-2">{{ item.row }}</td>
                                <td class="px-3 py-2">{{ item.code || 'N/D' }}</td>
                                <td class="px-3 py-2">{{ item.status }}</td>
                                <td class="px-3 py-2">{{ item.message }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="activeImportTab === 'photos' && photoImportResult?.log?.length" class="mt-5 max-h-72 overflow-auto rounded-md border" style="border-color: var(--stj-border);">
                    <div class="app-border-soft flex items-center justify-between gap-3 border-b px-3 py-2">
                        <p class="app-text text-sm font-semibold">Detalle por fila</p>
                        <button
                            type="button"
                            class="inline-flex h-9 items-center justify-center rounded-md border px-3 text-xs font-semibold transition hover:bg-blue-50"
                            style="border-color: var(--stj-border); color: var(--stj-primary);"
                            @click="printImportLog(photoImportResult, 'Log importacion fotos')"
                        >
                            Guardar PDF
                        </button>
                    </div>
                    <table class="w-full text-left text-sm">
                        <thead class="app-surface-soft app-text sticky top-0">
                            <tr>
                                <th class="px-3 py-2">Fila</th>
                                <th class="px-3 py-2">Codigo</th>
                                <th class="px-3 py-2">Estado</th>
                                <th class="px-3 py-2">Mensaje</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in photoImportResult.log" :key="`${item.row}-${item.code}-${item.status}`" class="app-border-soft border-t">
                                <td class="px-3 py-2">{{ item.row }}</td>
                                <td class="px-3 py-2">{{ item.code || 'N/D' }}</td>
                                <td class="px-3 py-2">{{ item.status }}</td>
                                <td class="px-3 py-2">{{ item.message }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <div v-if="error" class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                {{ error }}
            </div>

            <section class="app-surface overflow-hidden rounded-lg border shadow-sm" style="border-color: var(--stj-border);">
                <div class="app-border-soft border-b px-5 py-4">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <h2 class="app-text text-lg font-semibold">Productos</h2>
                        <p class="app-muted text-sm">{{ products.length }} productos cargados</p>
                    </div>
                </div>

                <div class="p-5">
                    <div v-if="loading" class="app-muted rounded-md border p-4 text-sm" style="border-color: var(--stj-border);">
                        Cargando productos...
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
            <div v-if="showDetailModal" class="stj-modal-backdrop">
                <div class="stj-modal app-surface">
                    <div class="app-border-soft flex items-center justify-between border-b px-5 py-4">
                        <div>
                            <p class="app-primary-text text-xs font-semibold uppercase">Detalle producto</p>
                            <h2 class="app-text mt-1 text-xl font-bold">
                                {{ selectedProduct?.code }} - {{ selectedProduct?.name }}
                            </h2>
                        </div>
                        <button type="button" class="stj-modal-close" title="Cerrar" @click="closeModals">
                            <svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18" /></svg>
                        </button>
                    </div>

                    <div class="max-h-[78vh] overflow-y-auto p-5">
                        <div v-if="detailLoading" class="app-muted rounded-md border p-4 text-sm" style="border-color: var(--stj-border);">
                            Cargando detalle...
                        </div>
                        <div v-else-if="modalError" class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                            {{ modalError }}
                        </div>
                        <div v-else class="grid gap-3 md:grid-cols-2">
                            <div
                                v-for="item in detailRows(productDetail?.fields)"
                                :key="item.key"
                                class="rounded-md border p-3"
                                style="border-color: var(--stj-border);"
                            >
                                <p class="app-muted text-xs font-semibold uppercase">{{ item.key }}</p>
                                <p class="app-text mt-1 break-words text-sm">{{ item.value }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <Teleport to="body">
            <div v-if="showPhotosModal" class="stj-modal-backdrop">
                <div class="stj-modal app-surface stj-modal-wide">
                    <div class="app-border-soft flex items-center justify-between border-b px-5 py-4">
                        <div>
                            <p class="app-primary-text text-xs font-semibold uppercase">Fotos producto</p>
                            <h2 class="app-text mt-1 text-xl font-bold">
                                {{ selectedProduct?.code }} - {{ selectedProduct?.name }}
                            </h2>
                        </div>
                        <button type="button" class="stj-modal-close" title="Cerrar" @click="closeModals">
                            <svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18" /></svg>
                        </button>
                    </div>

                    <div class="max-h-[78vh] overflow-y-auto p-5">
                        <div v-if="photosLoading" class="app-muted rounded-md border p-4 text-sm" style="border-color: var(--stj-border);">
                            Cargando fotos...
                        </div>
                        <div v-else-if="modalError" class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                            {{ modalError }}
                        </div>
                        <div v-else-if="!productPhotos?.photos?.length" class="app-muted rounded-md border p-4 text-sm" style="border-color: var(--stj-border);">
                            Este producto no tiene fotos registradas.
                        </div>
                        <div v-else class="space-y-5">
                            <article
                                v-for="photo in productPhotos.photos"
                                :key="photo.id"
                                class="rounded-md border p-4"
                                style="border-color: var(--stj-border);"
                            >
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <h3 class="app-text text-base font-semibold">{{ photo.filename }}</h3>
                                        <p class="app-muted text-sm">
                                            Orden {{ photo.order }} - {{ photo.isCover ? 'Portada' : 'Galeria' }}
                                        </p>
                                    </div>
                                </div>

                                <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                                    <a
                                        v-for="variant in photo.variants"
                                        :key="`${photo.id}-${variant.folder}`"
                                        :href="variant.url"
                                        target="_blank"
                                        rel="noreferrer"
                                        class="stj-photo-variant"
                                    >
                                        <div class="stj-photo-frame">
                                            <img :src="variant.url" :alt="`${photo.filename} ${variant.label}`">
                                        </div>
                                        <div class="mt-2">
                                            <p class="app-text text-sm font-semibold">{{ variant.label }}</p>
                                            <p class="app-muted text-xs">{{ variant.width ? `${variant.width}px` : 'original' }}</p>
                                        </div>
                                    </a>
                                </div>
                            </article>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <Teleport to="body">
            <div v-if="showCountriesModal" class="stj-modal-backdrop">
                <div class="stj-modal app-surface stj-modal-wide">
                    <div class="app-border-soft flex items-center justify-between border-b px-5 py-4">
                        <div>
                            <p class="app-primary-text text-xs font-semibold uppercase">Paises producto</p>
                            <h2 class="app-text mt-1 text-xl font-bold">
                                {{ selectedProduct?.code }} - {{ selectedProduct?.name }}
                            </h2>
                        </div>
                        <button type="button" class="stj-modal-close" title="Cerrar" @click="closeModals">
                            <svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18" /></svg>
                        </button>
                    </div>

                    <div class="max-h-[78vh] overflow-y-auto p-5">
                        <div v-if="countriesLoading" class="app-muted rounded-md border p-4 text-sm" style="border-color: var(--stj-border);">
                            Cargando paises...
                        </div>
                        <div v-else-if="modalError" class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                            {{ modalError }}
                        </div>
                        <div v-else-if="!productCountries?.countries?.length" class="app-muted rounded-md border p-4 text-sm" style="border-color: var(--stj-border);">
                            Este producto no esta dado de alta en ningun pais.
                        </div>
                        <div v-else class="overflow-x-auto rounded-md border" style="border-color: var(--stj-border);">
                            <table class="w-full text-left text-sm">
                                <thead class="app-surface-soft app-text">
                                    <tr>
                                        <th class="px-3 py-2">Pais</th>
                                        <th class="px-3 py-2">Estado</th>
                                        <th class="px-3 py-2">Precio</th>
                                        <th class="px-3 py-2">Precio talla</th>
                                        <th class="px-3 py-2">Leyenda</th>
                                        <th class="px-3 py-2">Promo</th>
                                        <th class="px-3 py-2">Activo desde</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="item in productCountries.countries" :key="item.id" class="app-border-soft border-t">
                                        <td class="px-3 py-2">
                                            <p class="app-text font-semibold">{{ item.country.name }}</p>
                                            <p class="app-muted text-xs">{{ item.country.code }}</p>
                                        </td>
                                        <td class="px-3 py-2">
                                            <span :class="['stj-status', item.status === 'ACTIVO' ? 'stj-status-active' : '']">
                                                {{ item.status }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2">{{ formatMoney(item.price) }}</td>
                                        <td class="px-3 py-2">{{ item.priceBySize || 'N/D' }}</td>
                                        <td class="px-3 py-2">{{ item.legend || 'N/D' }}</td>
                                        <td class="px-3 py-2">{{ item.promoName || (item.isPopular ? 'Popular' : 'N/D') }}</td>
                                        <td class="px-3 py-2">{{ formatDate(item.activeAt) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>

<style>
.stj-input {
    background: var(--stj-surface);
    border: 1px solid var(--stj-border);
    border-radius: 0.375rem;
    color: var(--stj-text);
    outline: none;
    padding: 0.55rem 0.75rem;
}

.stj-input:focus {
    box-shadow: 0 0 0 4px var(--stj-primary-soft);
}

.stj-status {
    background: #e5e7eb;
    border-radius: 999px;
    color: #374151;
    display: inline-flex;
    font-size: 0.68rem;
    font-weight: 800;
    padding: 0.18rem 0.5rem;
}

.stj-status-active {
    background: #dcfce7;
    color: #166534;
}

.stj-tab-btn {
    border-radius: 0.375rem;
    color: var(--stj-muted);
    font-size: 0.875rem;
    font-weight: 700;
    min-height: 2.25rem;
    padding: 0.45rem 0.9rem;
    transition: background-color 0.16s ease, color 0.16s ease;
}

.stj-tab-btn:hover {
    background: var(--stj-surface-hover);
    color: var(--stj-text);
}

.stj-tab-btn-active {
    background: var(--stj-primary);
    color: #fff;
}

.stj-tab-btn-active:hover {
    background: var(--stj-primary);
    color: #fff;
}

.stj-row-actions {
    align-items: center;
    display: flex;
    gap: 0.45rem;
}

.stj-icon-btn {
    align-items: center;
    background: var(--stj-surface);
    border: 1px solid var(--stj-border);
    border-radius: 0.375rem;
    color: var(--stj-primary);
    display: inline-flex;
    height: 2rem;
    justify-content: center;
    transition: background-color 0.16s ease, border-color 0.16s ease, color 0.16s ease;
    width: 2rem;
}

.stj-icon-btn:hover {
    background: var(--stj-primary-soft);
    border-color: var(--stj-primary);
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

.stj-modal-backdrop {
    align-items: center;
    background: rgb(15 23 42 / 0.68);
    display: flex;
    inset: 0;
    justify-content: center;
    padding: 1rem;
    position: fixed;
    z-index: 80;
}

.stj-modal {
    border: 1px solid var(--stj-border);
    border-radius: 0.5rem;
    box-shadow: 0 24px 70px rgb(15 23 42 / 0.3);
    max-width: 760px;
    overflow: hidden;
    width: min(100%, 760px);
}

.stj-modal-wide {
    max-width: 1120px;
    width: min(100%, 1120px);
}

.stj-modal-close {
    align-items: center;
    border: 1px solid var(--stj-border);
    border-radius: 0.375rem;
    color: var(--stj-muted);
    display: inline-flex;
    flex: 0 0 auto;
    height: 2.25rem;
    justify-content: center;
    transition: background-color 0.16s ease, color 0.16s ease;
    width: 2.25rem;
}

.stj-modal-close:hover {
    background: var(--stj-surface-hover);
    color: var(--stj-text);
}

.stj-photo-variant {
    border: 1px solid var(--stj-border);
    border-radius: 0.375rem;
    display: block;
    padding: 0.65rem;
    text-decoration: none;
    transition: border-color 0.16s ease, transform 0.16s ease;
}

.stj-photo-variant:hover {
    border-color: var(--stj-primary);
    transform: translateY(-1px);
}

.stj-photo-frame {
    align-items: center;
    aspect-ratio: 1 / 1;
    background: var(--stj-surface-soft);
    border-radius: 0.25rem;
    display: flex;
    justify-content: center;
    overflow: hidden;
}

.stj-photo-frame img {
    height: 100%;
    object-fit: contain;
    width: 100%;
}
</style>
