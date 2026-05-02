<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';

const loading = ref(true);
const importing = ref(false);
const deactivating = ref(false);
const error = ref('');
const importError = ref('');
const deactivateError = ref('');
const success = ref('');
const deactivateSuccess = ref('');
const countries = ref([]);
const selectedCountry = ref('');
const selectedDeactivateCountry = ref('');
const selectedFile = ref(null);
const selectedDeactivateFile = ref(null);
const deactivateReason = ref('');
const fileInput = ref(null);
const deactivateFileInput = ref(null);
const importResult = ref(null);
const deactivateResult = ref(null);
const activeTab = ref('alta');

const importSummaryCards = computed(() => resultCards(importResult.value, true));
const deactivateSummaryCards = computed(() => resultCards(deactivateResult.value, false));
const canPrintImportLog = computed(() => Boolean(importResult.value?.log?.length));
const canPrintDeactivateLog = computed(() => Boolean(deactivateResult.value?.log?.length));

onMounted(fetchCountries);

async function fetchCountries() {
    loading.value = true;
    error.value = '';

    try {
        const response = await window.axios.get('/dashboard-api/products/country/countries');
        countries.value = response.data.data?.countries || [];
        selectedCountry.value = countries.value[0]?.id || '';
        selectedDeactivateCountry.value = countries.value[0]?.id || '';
    } catch (exception) {
        error.value = exception.response?.data?.message || 'No fue posible cargar los paises.';
    } finally {
        loading.value = false;
    }
}

async function importProducts() {
    if (! selectedCountry.value) {
        importError.value = 'Debe seleccionar un pais.';
        return;
    }

    if (! selectedFile.value) {
        importError.value = 'Debe seleccionar un archivo Excel.';
        return;
    }

    importing.value = true;
    importError.value = '';
    success.value = '';
    importResult.value = null;

    const payload = new FormData();
    payload.append('country', selectedCountry.value);
    payload.append('file', selectedFile.value);

    try {
        const response = await window.axios.post('/dashboard-api/products/country/import', payload, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        importResult.value = response.data.data;
        success.value = response.data.message || 'Importacion finalizada.';
        selectedFile.value = null;

        if (fileInput.value) {
            fileInput.value.value = '';
        }
    } catch (exception) {
        importError.value = firstError(exception.response?.data?.errors)
            || exception.response?.data?.message
            || 'No fue posible importar el archivo.';
    } finally {
        importing.value = false;
    }
}

async function deactivateProducts() {
    if (! selectedDeactivateCountry.value) {
        deactivateError.value = 'Debe seleccionar un pais.';
        return;
    }

    if (! deactivateReason.value.trim()) {
        deactivateError.value = 'Debe indicar un motivo breve.';
        return;
    }

    if (! selectedDeactivateFile.value) {
        deactivateError.value = 'Debe seleccionar un archivo Excel.';
        return;
    }

    deactivating.value = true;
    deactivateError.value = '';
    deactivateSuccess.value = '';
    deactivateResult.value = null;

    const payload = new FormData();
    payload.append('country', selectedDeactivateCountry.value);
    payload.append('reason', deactivateReason.value.trim());
    payload.append('file', selectedDeactivateFile.value);

    try {
        const response = await window.axios.post('/dashboard-api/products/country/deactivate', payload, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        deactivateResult.value = response.data.data;
        deactivateSuccess.value = response.data.message || 'Baja finalizada.';
        selectedDeactivateFile.value = null;

        if (deactivateFileInput.value) {
            deactivateFileInput.value.value = '';
        }
    } catch (exception) {
        deactivateError.value = firstError(exception.response?.data?.errors)
            || exception.response?.data?.message
            || 'No fue posible inactivar los productos.';
    } finally {
        deactivating.value = false;
    }
}

function handleFile(event) {
    selectedFile.value = event.target.files[0] || null;
    importError.value = '';
}

function handleDeactivateFile(event) {
    selectedDeactivateFile.value = event.target.files[0] || null;
    deactivateError.value = '';
}

function resultCards(result, includePriceSheets = false) {
    const summary = result?.summary;

    if (! summary) {
        return [];
    }

    const cards = [['Filas', summary.rows]];

    if (includePriceSheets) {
        cards.push(['Precios', summary.priceRows]);
        cards.push(['Tallas', summary.sizeRows]);
        cards.push(['Creados', summary.created]);
    }

    cards.push(['Actualizados', summary.updated]);
    cards.push(['Omitidos', summary.skipped]);
    cards.push(['Errores', summary.failed]);

    return cards;
}

function printLog(result, title) {
    if (! result?.log?.length) {
        return;
    }

    const summary = result.summary || {};
    const country = result.country?.name || 'N/D';
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
                <td>${escapeHtml(item.sheet || 'N/D')}</td>
                <td>${escapeHtml(item.row)}</td>
                <td>${escapeHtml(item.code || 'N/D')}</td>
                <td>${escapeHtml(statusLabel(item.status))}</td>
                <td>${escapeHtml(item.message || '')}</td>
            </tr>
        `)
        .join('');
    const sheetCards = summary.priceRows !== undefined
        ? `
            <div class="card"><span>Precios</span><strong>${escapeHtml(summary.priceRows ?? 0)}</strong></div>
            <div class="card"><span>Tallas</span><strong>${escapeHtml(summary.sizeRows ?? 0)}</strong></div>
            <div class="card"><span>Creados</span><strong>${escapeHtml(summary.created ?? 0)}</strong></div>
        `
        : '';
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
                    .summary { display: grid; gap: 10px; grid-template-columns: repeat(4, 1fr); margin: 18px 0; }
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
                <div class="muted">Pais: ${escapeHtml(country)} | Generado: ${escapeHtml(generatedAt)}</div>
                <div class="summary">
                    <div class="card"><span>Filas</span><strong>${escapeHtml(summary.rows ?? 0)}</strong></div>
                    ${sheetCards}
                    <div class="card"><span>Actualizados</span><strong>${escapeHtml(summary.updated ?? 0)}</strong></div>
                    <div class="card"><span>Omitidos</span><strong>${escapeHtml(summary.skipped ?? 0)}</strong></div>
                    <div class="card"><span>Errores</span><strong>${escapeHtml(summary.failed ?? 0)}</strong></div>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Hoja</th>
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

function statusLabel(status) {
    return {
        created: 'Creado',
        updated: 'Actualizado',
        skipped: 'Omitido',
        error: 'Error',
    }[status] || status || 'N/D';
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
        <Head title="Productos / Por pais" />

        <div class="mx-auto max-w-6xl space-y-6">
            <section class="app-surface rounded-lg border p-5 shadow-sm" style="border-color: var(--stj-border);">
                <div>
                    <p class="app-primary-text text-xs font-semibold uppercase">Productos</p>
                    <h1 class="app-text mt-2 text-2xl font-bold">Por pais</h1>
                    <p class="app-muted mt-2 max-w-3xl text-sm">
                        Administra altas de precios y bajas de productos para cada pais.
                    </p>
                </div>
            </section>

            <div v-if="error" class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                {{ error }}
            </div>

            <section class="app-surface rounded-lg border p-5 shadow-sm" style="border-color: var(--stj-border);">
                <div class="mb-5 inline-flex rounded-md border p-1" style="border-color: var(--stj-border);">
                    <button
                        type="button"
                        :class="['stj-tab-btn', activeTab === 'alta' ? 'stj-tab-btn-active' : '']"
                        @click="activeTab = 'alta'"
                    >
                        Alta de precios
                    </button>
                    <button
                        type="button"
                        :class="['stj-tab-btn', activeTab === 'baja' ? 'stj-tab-btn-active' : '']"
                        @click="activeTab = 'baja'"
                    >
                        Baja de productos por pais
                    </button>
                </div>

                <div v-if="activeTab === 'alta'" class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(320px,420px)]">
                    <div>
                        <h2 class="app-text text-lg font-semibold">Importar Excel</h2>
                        <p class="app-muted mt-2 text-sm">
                            El Excel debe tener ambas hojas: la primera para alta de precios y la segunda para alta de tallas. La segunda hoja puede venir vacia, pero debe existir.
                        </p>
                        <div class="mt-3 rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-800">
                            Hoja 1: codigo, leyenda, precio_talla, precio. Hoja 2: codigo, talla, precio.
                        </div>

                        <form class="mt-5 grid gap-4" @submit.prevent="importProducts">
                            <label class="block">
                                <span class="app-muted text-sm font-medium">Pais</span>
                                <select
                                    v-model="selectedCountry"
                                    class="stj-input mt-2 h-11 w-full"
                                    :disabled="loading || importing"
                                >
                                    <option value="" disabled>Seleccione pais</option>
                                    <option v-for="country in countries" :key="country.id" :value="country.id">
                                        {{ country.name }} ({{ country.code }})
                                    </option>
                                </select>
                            </label>

                            <label class="block">
                                <span class="app-muted text-sm font-medium">Archivo</span>
                                <input
                                    ref="fileInput"
                                    accept=".xlsx,.xls"
                                    type="file"
                                    class="app-surface app-text mt-2 block h-11 w-full rounded-md border px-3 py-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-blue-700"
                                    style="border-color: var(--stj-border);"
                                    :disabled="loading || importing"
                                    @change="handleFile"
                                >
                            </label>

                            <div>
                                <button
                                    type="submit"
                                    class="inline-flex h-11 items-center justify-center rounded-md bg-blue-600 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                                    :disabled="loading || importing"
                                >
                                    {{ importing ? 'Procesando...' : 'Importar por pais' }}
                                </button>
                            </div>
                        </form>

                        <div v-if="importError" class="mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                            {{ importError }}
                        </div>

                        <div v-if="success" class="mt-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                            {{ success }}
                        </div>
                    </div>

                    <div v-if="importSummaryCards.length" class="rounded-md border p-4" style="border-color: var(--stj-border);">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h3 class="app-text text-sm font-semibold">Resultado</h3>
                                <p class="app-muted mt-1 text-xs">{{ importResult?.country?.name }}</p>
                            </div>
                            <button
                                v-if="canPrintImportLog"
                                type="button"
                                class="inline-flex h-9 items-center justify-center rounded-md border px-3 text-xs font-semibold transition hover:bg-blue-50"
                                style="border-color: var(--stj-border); color: var(--stj-primary);"
                                @click="printLog(importResult, 'Log importacion productos por pais')"
                            >
                                Guardar PDF
                            </button>
                        </div>
                        <div class="mt-3 grid grid-cols-2 gap-3">
                            <div v-for="[label, value] in importSummaryCards" :key="label" class="app-surface-soft rounded-md p-3">
                                <p class="app-muted text-xs font-semibold uppercase">{{ label }}</p>
                                <p class="app-text mt-1 text-xl font-bold">{{ value }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(320px,420px)]">
                    <div>
                        <h2 class="app-text text-lg font-semibold">Baja de productos por pais</h2>
                        <p class="app-muted mt-2 text-sm">
                            El Excel debe contener solo una columna: codigo de articulo en columna A, iniciando desde la fila 2.
                        </p>
                        <div class="mt-3 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                            Esta accion marca el producto-pais como INACTIVO y registra la fecha del sistema junto con el motivo indicado.
                        </div>

                        <form class="mt-5 grid gap-4" @submit.prevent="deactivateProducts">
                            <label class="block">
                                <span class="app-muted text-sm font-medium">Pais</span>
                                <select
                                    v-model="selectedDeactivateCountry"
                                    class="stj-input mt-2 h-11 w-full"
                                    :disabled="loading || deactivating"
                                >
                                    <option value="" disabled>Seleccione pais</option>
                                    <option v-for="country in countries" :key="country.id" :value="country.id">
                                        {{ country.name }} ({{ country.code }})
                                    </option>
                                </select>
                            </label>

                            <label class="block">
                                <span class="app-muted text-sm font-medium">Motivo</span>
                                <input
                                    v-model.trim="deactivateReason"
                                    maxlength="100"
                                    type="text"
                                    class="stj-input mt-2 h-11 w-full"
                                    placeholder="Ej. Fin de temporada"
                                    :disabled="loading || deactivating"
                                >
                            </label>

                            <label class="block">
                                <span class="app-muted text-sm font-medium">Archivo</span>
                                <input
                                    ref="deactivateFileInput"
                                    accept=".xlsx,.xls"
                                    type="file"
                                    class="app-surface app-text mt-2 block h-11 w-full rounded-md border px-3 py-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-blue-700"
                                    style="border-color: var(--stj-border);"
                                    :disabled="loading || deactivating"
                                    @change="handleDeactivateFile"
                                >
                            </label>

                            <div>
                                <button
                                    type="submit"
                                    class="inline-flex h-11 items-center justify-center rounded-md bg-red-600 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-60"
                                    :disabled="loading || deactivating"
                                >
                                    {{ deactivating ? 'Procesando...' : 'Inactivar productos' }}
                                </button>
                            </div>
                        </form>

                        <div v-if="deactivateError" class="mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                            {{ deactivateError }}
                        </div>

                        <div v-if="deactivateSuccess" class="mt-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                            {{ deactivateSuccess }}
                        </div>
                    </div>

                    <div v-if="deactivateSummaryCards.length" class="rounded-md border p-4" style="border-color: var(--stj-border);">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h3 class="app-text text-sm font-semibold">Resultado</h3>
                                <p class="app-muted mt-1 text-xs">{{ deactivateResult?.country?.name }}</p>
                            </div>
                            <button
                                v-if="canPrintDeactivateLog"
                                type="button"
                                class="inline-flex h-9 items-center justify-center rounded-md border px-3 text-xs font-semibold transition hover:bg-blue-50"
                                style="border-color: var(--stj-border); color: var(--stj-primary);"
                                @click="printLog(deactivateResult, 'Log baja productos por pais')"
                            >
                                Guardar PDF
                            </button>
                        </div>
                        <div class="mt-3 grid grid-cols-2 gap-3">
                            <div v-for="[label, value] in deactivateSummaryCards" :key="label" class="app-surface-soft rounded-md p-3">
                                <p class="app-muted text-xs font-semibold uppercase">{{ label }}</p>
                                <p class="app-text mt-1 text-xl font-bold">{{ value }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="activeTab === 'alta' && importResult?.log?.length" class="mt-5 max-h-80 overflow-auto rounded-md border" style="border-color: var(--stj-border);">
                    <LogTable :items="importResult.log" @print="printLog(importResult, 'Log importacion productos por pais')" />
                </div>

                <div v-if="activeTab === 'baja' && deactivateResult?.log?.length" class="mt-5 max-h-80 overflow-auto rounded-md border" style="border-color: var(--stj-border);">
                    <LogTable :items="deactivateResult.log" @print="printLog(deactivateResult, 'Log baja productos por pais')" />
                </div>
            </section>
        </div>
    </AdminLayout>
</template>

<script>
export default {
    components: {
        LogTable: {
            props: {
                items: {
                    type: Array,
                    required: true,
                },
            },
            emits: ['print'],
            template: `
                <div>
                    <div class="app-border-soft flex items-center justify-between gap-3 border-b px-3 py-2">
                        <p class="app-text text-sm font-semibold">Detalle por fila</p>
                        <button
                            type="button"
                            class="inline-flex h-9 items-center justify-center rounded-md border px-3 text-xs font-semibold transition hover:bg-blue-50"
                            style="border-color: var(--stj-border); color: var(--stj-primary);"
                            @click="$emit('print')"
                        >
                            Guardar PDF
                        </button>
                    </div>
                    <table class="w-full text-left text-sm">
                        <thead class="app-surface-soft app-text sticky top-0">
                            <tr>
                                <th class="px-3 py-2">Hoja</th>
                                <th class="px-3 py-2">Fila</th>
                                <th class="px-3 py-2">Codigo</th>
                                <th class="px-3 py-2">Estado</th>
                                <th class="px-3 py-2">Mensaje</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in items" :key="\`\${item.sheet}-\${item.row}-\${item.code}-\${item.status}\`" class="app-border-soft border-t">
                                <td class="px-3 py-2">{{ item.sheet }}</td>
                                <td class="px-3 py-2">{{ item.row }}</td>
                                <td class="px-3 py-2">{{ item.code || 'N/D' }}</td>
                                <td class="px-3 py-2">{{ item.status }}</td>
                                <td class="px-3 py-2">{{ item.message }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            `,
        },
    },
};
</script>

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
</style>
