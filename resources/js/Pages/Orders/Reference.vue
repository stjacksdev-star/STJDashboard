<script setup>
import { Head, router } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';

const props = defineProps({
    initialCountry: {
        type: String,
        default: '',
    },
    initialReference: {
        type: String,
        default: '',
    },
});

const countries = [
    { id: '1', code: 'SV', name: 'El Salvador', currency: 'USD' },
    { id: '2', code: 'GT', name: 'Guatemala', currency: 'Q' },
    { id: '3', code: 'CR', name: 'Costa Rica', currency: 'CRC' },
    { id: '5', code: 'PA', name: 'Panama', currency: 'USD' },
];
const page = usePage();
const loading = ref(false);
const error = ref('');
const orderData = ref(null);
const editingLineId = ref(null);
const lineSaving = ref(false);
const lineError = ref('');
const productValidation = ref(null);
const lineForm = ref(defaultLineForm());
const showProcessModal = ref(false);
const processSaving = ref(false);
const processError = ref('');
const processForm = ref({
    ticket: '',
});
const form = ref({
    country: props.initialCountry || '',
    reference: props.initialReference || '',
});

const order = computed(() => orderData.value?.order || null);
const products = computed(() => orderData.value?.products || []);
const selectedCountry = computed(() => countries.find((country) => country.id === String(form.value.country)));
const currency = computed(() => selectedCountry.value?.currency || '');
const permissions = computed(() => page.props.auth?.permissions || []);
const canProcessOrders = computed(() =>
    permissions.value.includes('ROOT')
    || permissions.value.includes('MENU_PROCESAR_PEDIDO')
    || permissions.value.includes('OP_MENU_PROCESAR_PEDIDO'),
);
const canEditProducts = computed(() => order.value?.status === 'RECIBIDO' && canProcessOrders.value);
const canProcessCurrentOrder = computed(() => order.value?.status === 'RECIBIDO' && canProcessOrders.value && !editingLineId.value);
const hasProductsDifference = computed(() => Math.abs(roundMoney(order.value?.totals?.productsDifference || 0)) >= 0.01);
const hasPaidDifference = computed(() => Math.abs(roundMoney(order.value?.totals?.paidDifference || 0)) >= 0.01);
const processDifference = computed(() => roundMoney(order.value?.totals?.paidDifference || 0));
const paymentIsCard = computed(() => String(order.value?.payment?.type || '').toUpperCase() === 'TARJETA');
const processRefund = computed(() => paymentIsCard.value ? Math.max(0, -processDifference.value) : 0);
const processCharge = computed(() => Math.max(0, processDifference.value));
const processImpact = computed(() => {
    if (Number(order.value?.totals?.items || 0) === 0) {
        if (!paymentIsCard.value) {
            return {
                type: 'ok',
                title: 'Pedido anulado por inventario',
                message: 'Todos los articulos estan en cero. El pedido quedara anulado; al ser pago en efectivo no se registrara devolucion.',
            };
        }

        return {
            type: 'refund',
            title: 'Pedido anulado por inventario',
            message: `Todos los articulos estan en cero. El pedido quedara anulado y se registrara una devolucion pendiente por ${currency.value} ${formatMoney(processRefund.value)}.`,
        };
    }

    if (processRefund.value >= 0.01) {
        return {
            type: 'refund',
            title: 'Habra devolucion',
            message: `Se registrara una devolucion pendiente por ${currency.value} ${formatMoney(processRefund.value)}.`,
        };
    }

    if (processDifference.value <= -0.01 && !paymentIsCard.value) {
        return {
            type: 'ok',
            title: 'Diferencia menor sin devolucion',
            message: 'El detalle es menor al pago original, pero al ser pago en efectivo no se registrara devolucion.',
        };
    }

    if (processCharge.value >= 0.01) {
        return {
            type: 'charge',
            title: 'Hay diferencia por cobrar',
            message: `El detalle supera el pago aprobado por ${currency.value} ${formatMoney(processCharge.value)}. Se debera cobrar en efectivo o enviar link de pago al cliente.`,
        };
    }

    return {
        type: 'ok',
        title: 'Sin diferencia de monto',
        message: 'El detalle coincide con el total aprobado.',
    };
});
const statusSteps = computed(() => {
    const current = order.value?.status || '';
    const steps = [
        { key: 'RECIBIDO', label: 'Recibido', date: order.value?.paidAt },
        { key: 'PREPARADO', label: 'Facturado', date: order.value?.processedAt },
    ];

    if (order.value?.checkout === 'DOMICILIO') {
        steps.push({ key: 'EN-RUTA', label: 'En ruta', date: order.value?.shipping?.routeAt });
    }

    steps.push({ key: 'ENTREGADO', label: 'Entregado', date: order.value?.deliveredAt });

    const currentIndex = steps.findIndex((step) => step.key === current);

    return steps.map((step, index) => ({
        ...step,
        done: currentIndex >= 0 && index < currentIndex,
        active: step.key === current,
    }));
});

async function searchOrder({ updateUrl = true } = {}) {
    if (!form.value.country || !form.value.reference) {
        error.value = 'Debe seleccionar pais e ingresar la referencia.';
        return;
    }

    loading.value = true;
    error.value = '';
    orderData.value = null;

    try {
        const response = await window.axios.get('/dashboard-api/orders/reference', {
            params: {
                country: form.value.country,
                reference: form.value.reference,
            },
        });

        orderData.value = response.data.data;
        cancelLineEdit();

        if (updateUrl) {
            router.replace({
                url: `/pedidos/consulta?country=${encodeURIComponent(form.value.country)}&id=${encodeURIComponent(form.value.reference)}`,
                preserveState: true,
                preserveScroll: true,
            });
        }
    } catch (exception) {
        error.value = exception.response?.data?.message || 'No fue posible cargar el pedido.';
    } finally {
        loading.value = false;
    }
}

function startLineEdit(product) {
    lineError.value = '';
    productValidation.value = null;
    editingLineId.value = product.id;
    lineForm.value = {
        sku: product.sku,
        size: product.size,
        quantity: product.quantity,
        discount: product.discount,
    };
}

function cancelLineEdit() {
    editingLineId.value = null;
    lineSaving.value = false;
    lineError.value = '';
    productValidation.value = null;
    lineForm.value = defaultLineForm();
}

async function validateLineProduct() {
    if (!lineForm.value.sku || !form.value.country) {
        productValidation.value = null;
        return;
    }

    productValidation.value = {
        loading: true,
        ok: false,
        message: 'Validando articulo...',
        product: null,
    };

    try {
        const response = await window.axios.get('/dashboard-api/orders/product', {
            params: {
                country: form.value.country,
                sku: lineForm.value.sku,
                size: lineForm.value.size || undefined,
            },
        });

        productValidation.value = {
            loading: false,
            ok: true,
            message: 'Articulo y talla validos para el pais.',
            product: response.data.data,
        };
    } catch (exception) {
        productValidation.value = {
            loading: false,
            ok: false,
            message: exception.response?.data?.message || 'El articulo no existe o no esta activo para el pais.',
            product: null,
        };
    }
}

async function saveLineEdit() {
    if (!editingLineId.value) {
        return;
    }

    lineSaving.value = true;
    lineError.value = '';

    try {
        const response = await window.axios.post(`/dashboard-api/orders/lines/${editingLineId.value}`, {
            sku: lineForm.value.sku,
            size: lineForm.value.size,
            quantity: Number(lineForm.value.quantity),
            discount: Number(lineForm.value.discount || 0),
        });

        orderData.value = response.data.data;
        cancelLineEdit();
    } catch (exception) {
        lineError.value = exception.response?.data?.message || 'No fue posible actualizar la linea.';
    } finally {
        lineSaving.value = false;
    }
}

function openProcessModal() {
    processError.value = '';
    processForm.value.ticket = order.value?.payment?.ticket || '';
    showProcessModal.value = true;
}

function closeProcessModal() {
    if (processSaving.value) {
        return;
    }

    showProcessModal.value = false;
    processError.value = '';
}

async function processOrder() {
    if (!order.value || !processForm.value.ticket) {
        processError.value = 'Debe ingresar el numero de ticket.';
        return;
    }

    processSaving.value = true;
    processError.value = '';

    try {
        const response = await window.axios.post('/dashboard-api/orders/process', {
            country: form.value.country,
            reference: order.value.reference,
            ticket: processForm.value.ticket,
        });

        orderData.value = response.data.data;
        showProcessModal.value = false;
        cancelLineEdit();
    } catch (exception) {
        processError.value = exception.response?.data?.message || 'No fue posible procesar el pedido.';
    } finally {
        processSaving.value = false;
    }
}

function defaultLineForm() {
    return {
        sku: '',
        size: '',
        quantity: 1,
        discount: 0,
    };
}

function formatMoney(value) {
    return Number(value || 0).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function roundMoney(value) {
    return Math.round((Number(value || 0) + Number.EPSILON) * 100) / 100;
}

function formatNumber(value) {
    return Number(value || 0).toLocaleString('en-US');
}

function formatDateTime(value) {
    if (!value || value === '0000-00-00 00:00:00') {
        return 'N/D';
    }

    return String(value).replace('T', ' ').slice(0, 19);
}

function display(value, fallback = 'N/D') {
    return value !== null && value !== undefined && String(value).trim() !== '' ? value : fallback;
}

function productSubtotal(product, key) {
    return `${currency.value} ${formatMoney(product[key])}`;
}

function signedMoney(value) {
    const amount = Number(value || 0);
    return `${amount > 0 ? '+' : ''}${currency.value} ${formatMoney(amount)}`;
}

function productChanged(product) {
    return Boolean(product.loggedChange?.productChanged || product.substitute?.hasSubstitute);
}

function productOriginal(product, field) {
    if (!product.loggedChange?.productChanged) {
        return product[field] || '';
    }

    return {
        sku: product.loggedChange.sku,
        name: product.loggedChange.name,
        size: product.loggedChange.size,
    }[field] || product[field] || '';
}

function productNew(product, field) {
    if (product.loggedChange?.productChanged) {
        return {
            sku: product.loggedChange.newSku,
            name: product.loggedChange.newName,
            size: product.loggedChange.newSize,
        }[field] || product[field] || '';
    }

    if (product.substitute?.hasSubstitute) {
        return {
            sku: product.substitute.sku,
            name: product.substitute.name,
            size: product.substitute.size,
        }[field] || product[field] || '';
    }

    return '';
}

onMounted(() => {
    if (form.value.country && form.value.reference) {
        searchOrder({ updateUrl: false });
    }
});
</script>

<template>
    <Head title="Pedidos / Referencia" />

    <AdminLayout>
        <section class="mx-auto w-full max-w-7xl">
            <div class="app-surface rounded-lg border p-6">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="app-primary-text text-sm font-semibold uppercase">Pedidos</p>
                        <h1 class="app-text mt-3 text-3xl font-semibold">Consulta por referencia</h1>
                        <p class="app-muted mt-2 max-w-3xl text-sm leading-6">
                            Busca el pedido por pais y numero de referencia.
                        </p>
                    </div>
                </div>

                <form class="mt-6 grid gap-4 lg:grid-cols-[1fr_1.3fr_auto]" @submit.prevent="searchOrder()">
                    <label class="block text-sm font-semibold">
                        <span class="app-muted">Pais</span>
                        <select
                            v-model="form.country"
                            class="app-surface app-text mt-2 h-11 w-full rounded-md border px-3 text-sm outline-none focus:ring-4"
                            required
                        >
                            <option value="">-- Seleccione --</option>
                            <option v-for="country in countries" :key="country.id" :value="country.id">
                                {{ country.code }} - {{ country.name }}
                            </option>
                        </select>
                    </label>

                    <label class="block text-sm font-semibold">
                        <span class="app-muted">Referencia</span>
                        <input
                            v-model="form.reference"
                            type="text"
                            class="app-surface app-text mt-2 h-11 w-full rounded-md border px-3 text-sm outline-none focus:ring-4"
                            placeholder="Numero de referencia"
                            required
                        />
                    </label>

                    <button
                        type="submit"
                        class="app-primary inline-flex h-11 items-center justify-center gap-2 rounded-md px-5 text-sm font-semibold shadow-sm disabled:opacity-60"
                        :disabled="loading"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 21l-4.3-4.3M11 18a7 7 0 1 1 0-14 7 7 0 0 1 0 14z" stroke-linecap="round" />
                        </svg>
                        {{ loading ? 'Buscando...' : 'Buscar' }}
                    </button>
                </form>
            </div>

            <div v-if="error" class="mt-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                {{ error }}
            </div>

            <div v-if="loading" class="app-surface mt-6 rounded-lg border p-6 text-sm font-semibold">
                Cargando pedido...
            </div>

            <template v-if="order && !loading">
                <div class="app-surface mt-6 overflow-hidden rounded-lg border">
                    <div class="app-border-soft border-b px-6 py-5">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <p class="app-primary-text text-xs font-semibold uppercase">Pedido</p>
                                <h2 class="app-text mt-1 text-2xl font-semibold">{{ order.reference }}</h2>
                                <p class="app-muted mt-2 text-sm">
                                    Checkout {{ order.checkout }} - {{ order.payment.type }}
                                </p>
                            </div>

                            <div class="app-surface-soft rounded-md border px-4 py-3 text-sm">
                                <span class="app-muted">Estado:</span>
                                <span class="app-text ml-2 font-semibold">{{ order.status }}</span>
                            </div>
                        </div>

                        <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                            <div>
                                <p class="app-muted text-xs font-semibold uppercase">ID</p>
                                <p class="app-text mt-1 font-semibold">{{ order.id }}</p>
                            </div>
                            <div>
                                <p class="app-muted text-xs font-semibold uppercase">Pago</p>
                                <p class="app-text mt-1 font-semibold">{{ order.paymentId }}</p>
                            </div>
                            <div>
                                <p class="app-muted text-xs font-semibold uppercase">Plataforma</p>
                                <p class="app-text mt-1 font-semibold">{{ order.origin }}</p>
                            </div>
                            <div>
                                <p class="app-muted text-xs font-semibold uppercase">Fecha pedido</p>
                                <p class="app-text mt-1 font-semibold">{{ formatDateTime(order.createdAt) }}</p>
                            </div>
                            <div>
                                <p class="app-muted text-xs font-semibold uppercase">Fecha pago</p>
                                <p class="app-text mt-1 font-semibold">{{ formatDateTime(order.paidAt) }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="px-6 py-5">
                        <div class="grid gap-3 md:grid-cols-4">
                            <div
                                v-for="step in statusSteps"
                                :key="step.key"
                                :class="[
                                    'rounded-md border p-4',
                                    step.done || step.active ? 'border-blue-500 bg-blue-50 text-blue-800' : 'app-surface-soft app-text',
                                ]"
                            >
                                <div class="flex items-center gap-2">
                                    <span
                                        :class="[
                                            'flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold',
                                            step.done ? 'bg-emerald-600 text-white' : step.active ? 'bg-blue-600 text-white' : 'app-surface border',
                                        ]"
                                    >
                                        {{ step.done ? '✓' : step.active ? '•' : '' }}
                                    </span>
                                    <span class="font-semibold">{{ step.label }}</span>
                                </div>
                                <p class="mt-2 text-xs">{{ formatDateTime(step.date) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 grid gap-4 lg:grid-cols-3">
                    <div class="app-surface rounded-lg border p-5">
                        <p class="app-primary-text text-xs font-semibold uppercase">Cliente</p>
                        <dl class="mt-4 space-y-3 text-sm">
                            <div>
                                <dt class="app-muted font-semibold">Nombre</dt>
                                <dd class="app-text">{{ display(order.customer.name) }}</dd>
                            </div>
                            <div>
                                <dt class="app-muted font-semibold">Correo</dt>
                                <dd class="app-text break-all">{{ display(order.customer.email) }}</dd>
                            </div>
                            <div>
                                <dt class="app-muted font-semibold">Identificacion</dt>
                                <dd class="app-text">{{ display(order.customer.identification || order.customer.rtu) }}</dd>
                            </div>
                            <div>
                                <dt class="app-muted font-semibold">Telefono</dt>
                                <dd class="app-text">{{ display(order.customer.phone) }}</dd>
                            </div>
                            <div>
                                <dt class="app-muted font-semibold">Whatsapp</dt>
                                <dd class="app-text">{{ display(order.customer.whatsapp) }}</dd>
                            </div>
                            <div>
                                <dt class="app-muted font-semibold">Direccion facturacion</dt>
                                <dd class="app-text">{{ display(order.customer.billingAddress) }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="app-surface rounded-lg border p-5">
                        <p class="app-primary-text text-xs font-semibold uppercase">Pago</p>
                        <dl class="mt-4 space-y-3 text-sm">
                            <div>
                                <dt class="app-muted font-semibold">Metodo</dt>
                                <dd class="app-text">{{ display(order.payment.card || order.payment.type) }}</dd>
                            </div>
                            <div>
                                <dt class="app-muted font-semibold">Emisor</dt>
                                <dd class="app-text">{{ display(order.payment.issuer) }}</dd>
                            </div>
                            <div>
                                <dt class="app-muted font-semibold">Estado</dt>
                                <dd class="app-text">{{ display(order.payment.status) }}</dd>
                            </div>
                            <div>
                                <dt class="app-muted font-semibold">Autorizacion</dt>
                                <dd class="app-text">{{ display(order.payment.authorization) }}</dd>
                            </div>
                            <div>
                                <dt class="app-muted font-semibold">Ticket</dt>
                                <dd class="app-text">{{ display(order.payment.ticket) }}</dd>
                            </div>
                            <div v-if="order.payment.type === 'EFECTIVO'">
                                <dt class="app-muted font-semibold">Cambio</dt>
                                <dd class="app-text">{{ formatMoney(order.payment.change) }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="app-surface rounded-lg border p-5">
                        <p class="app-primary-text text-xs font-semibold uppercase">
                            {{ order.checkout === 'DOMICILIO' ? 'Envio' : 'Tienda' }}
                        </p>
                        <dl v-if="order.checkout === 'DOMICILIO'" class="mt-4 space-y-3 text-sm">
                            <div>
                                <dt class="app-muted font-semibold">ID envio</dt>
                                <dd class="app-text">{{ display(order.shipping.id) }}</dd>
                            </div>
                            <div>
                                <dt class="app-muted font-semibold">Direccion</dt>
                                <dd class="app-text">{{ display(order.shipping.address) }}</dd>
                            </div>
                            <div>
                                <dt class="app-muted font-semibold">Punto referencia</dt>
                                <dd class="app-text">{{ display(order.shipping.reference) }}</dd>
                            </div>
                            <div>
                                <dt class="app-muted font-semibold">Costo</dt>
                                <dd class="app-text">{{ currency }} {{ formatMoney(order.shipping.cost) }}</dd>
                            </div>
                            <div v-if="order.shipping.lat && order.shipping.lng">
                                <dt class="app-muted font-semibold">Coordenadas</dt>
                                <dd class="app-text">{{ order.shipping.lat }}, {{ order.shipping.lng }}</dd>
                            </div>
                        </dl>
                        <dl v-else class="mt-4 space-y-3 text-sm">
                            <div>
                                <dt class="app-muted font-semibold">Tienda</dt>
                                <dd class="app-text">{{ display(order.storePickup.storeName) }}</dd>
                            </div>
                            <div>
                                <dt class="app-muted font-semibold">Codigo</dt>
                                <dd class="app-text">{{ display(order.storePickup.storeCode) }}</dd>
                            </div>
                            <div>
                                <dt class="app-muted font-semibold">Retira misma persona</dt>
                                <dd class="app-text">{{ display(order.storePickup.samePerson) }}</dd>
                            </div>
                            <div v-if="order.storePickup.samePerson === 'NO'">
                                <dt class="app-muted font-semibold">Persona retira</dt>
                                <dd class="app-text">{{ display(order.storePickup.person) }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    <div class="app-surface rounded-lg border p-5">
                        <p class="app-muted text-xs font-semibold uppercase">Articulos</p>
                        <p class="app-text mt-2 text-2xl font-semibold">{{ formatNumber(order.totals.items) }}</p>
                        <p v-if="order.totals.itemsOriginal !== order.totals.items" class="mt-1 text-xs font-semibold text-amber-600">
                            Original {{ formatNumber(order.totals.itemsOriginal) }}
                        </p>
                    </div>
                    <div class="app-surface rounded-lg border p-5">
                        <p class="app-muted text-xs font-semibold uppercase">Venta detalle</p>
                        <p class="app-text mt-2 text-2xl font-semibold">{{ currency }} {{ formatMoney(order.totals.products) }}</p>
                        <p v-if="hasProductsDifference" class="mt-1 text-xs font-semibold text-amber-600">
                            Pago original {{ currency }} {{ formatMoney(order.totals.productsOriginal) }} ({{ signedMoney(order.totals.productsDifference) }})
                        </p>
                    </div>
                    <div class="app-surface rounded-lg border p-5">
                        <p class="app-muted text-xs font-semibold uppercase">Envio</p>
                        <p class="app-text mt-2 text-2xl font-semibold">{{ currency }} {{ formatMoney(order.totals.shipping) }}</p>
                    </div>
                    <div class="app-surface rounded-lg border p-5">
                        <p class="app-muted text-xs font-semibold uppercase">Total aprobado</p>
                        <p class="app-text mt-2 text-2xl font-semibold">{{ currency }} {{ formatMoney(order.totals.paid) }}</p>
                        <p v-if="hasPaidDifference" class="mt-1 text-xs font-semibold text-amber-600">
                            Detalle {{ currency }} {{ formatMoney(order.totals.paidCalculated) }} ({{ signedMoney(order.totals.paidDifference) }})
                        </p>
                    </div>
                    <div class="app-surface rounded-lg border p-5">
                        <p class="app-muted text-xs font-semibold uppercase">Facturado</p>
                        <p class="app-text mt-2 text-2xl font-semibold">{{ currency }} {{ formatMoney(order.totals.billed) }}</p>
                    </div>
                </div>

                <div class="app-surface mt-6 rounded-lg border p-4">
                    <div class="mb-4">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <h2 class="app-text text-lg font-semibold">Productos</h2>
                                <p class="app-muted text-sm">Detalle cobrado y facturado por articulo.</p>
                            </div>
                            <div
                                v-if="canEditProducts"
                                class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700"
                            >
                                Edicion disponible para pedido recibido
                            </div>
                        </div>
                        <div v-if="lineError" class="mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                            {{ lineError }}
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[1180px] text-sm">
                            <thead>
                                <tr class="app-primary text-left">
                                    <th v-if="canEditProducts" class="px-3 py-2">Acciones</th>
                                    <th class="px-3 py-2">Sustituto</th>
                                    <th class="px-3 py-2">SKU</th>
                                    <th class="px-3 py-2">Talla</th>
                                    <th class="px-3 py-2">Descripcion</th>
                                    <th class="px-3 py-2 text-right">Cantidad</th>
                                    <th class="px-3 py-2 text-right">Facturado</th>
                                    <th class="px-3 py-2 text-right">Precio</th>
                                    <th class="px-3 py-2 text-right">Desc.</th>
                                    <th class="px-3 py-2 text-right">Desc. fact.</th>
                                    <th class="px-3 py-2 text-right">Sub cobrado</th>
                                    <th class="px-3 py-2 text-right">Sub facturado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!products.length">
                                    <td class="app-muted px-3 py-6 text-center" :colspan="canEditProducts ? 12 : 11">Sin productos.</td>
                                </tr>
                                <tr v-for="product in products" :key="product.id" class="app-border-soft border-b align-top">
                                    <td v-if="canEditProducts" class="px-3 py-2">
                                        <div v-if="editingLineId === product.id" class="flex flex-col gap-2">
                                            <button
                                                type="button"
                                                class="app-primary inline-flex h-8 items-center justify-center rounded-md px-3 text-xs font-semibold disabled:opacity-60"
                                                :disabled="lineSaving"
                                                @click="saveLineEdit"
                                            >
                                                Guardar
                                            </button>
                                            <button
                                                type="button"
                                                class="app-surface-soft app-text inline-flex h-8 items-center justify-center rounded-md border px-3 text-xs font-semibold"
                                                :disabled="lineSaving"
                                                @click="cancelLineEdit"
                                            >
                                                Cancelar
                                            </button>
                                        </div>
                                        <button
                                            v-else
                                            type="button"
                                            class="app-surface-soft app-primary-text inline-flex h-8 items-center justify-center rounded-md border px-3 text-xs font-semibold"
                                            @click="startLineEdit(product)"
                                        >
                                            Editar
                                        </button>
                                    </td>
                                    <td class="app-text px-3 py-2">{{ productChanged(product) ? 'SI' : 'NO' }}</td>
                                    <td class="app-text px-3 py-2">
                                        <template v-if="editingLineId === product.id">
                                            <input
                                                v-model="lineForm.sku"
                                                type="text"
                                                class="app-surface app-text h-9 w-28 rounded-md border px-2 text-sm outline-none"
                                                @blur="validateLineProduct"
                                            />
                                            <div
                                                v-if="productValidation"
                                                :class="[
                                                    'mt-1 max-w-44 text-xs font-semibold',
                                                    productValidation.ok ? 'text-emerald-600' : 'text-red-600',
                                                ]"
                                            >
                                                {{ productValidation.message }}
                                            </div>
                                        </template>
                                        <template v-else>
                                            <div :class="productChanged(product) ? 'line-through' : ''">{{ productOriginal(product, 'sku') }}</div>
                                            <div v-if="productChanged(product)" class="app-primary-text font-semibold">{{ productNew(product, 'sku') }}</div>
                                        </template>
                                    </td>
                                    <td class="app-text px-3 py-2">
                                        <input
                                            v-if="editingLineId === product.id"
                                            v-model="lineForm.size"
                                            type="text"
                                            class="app-surface app-text h-9 w-20 rounded-md border px-2 text-sm outline-none"
                                        />
                                        <template v-else>
                                            <div :class="productChanged(product) ? 'line-through' : ''">{{ productOriginal(product, 'size') }}</div>
                                            <div v-if="productChanged(product)" class="app-primary-text font-semibold">{{ productNew(product, 'size') }}</div>
                                        </template>
                                    </td>
                                    <td class="app-text px-3 py-2">
                                        <div :class="productChanged(product) ? 'line-through' : ''">{{ productOriginal(product, 'name') }}</div>
                                        <div v-if="productChanged(product)" class="app-primary-text font-semibold">{{ productNew(product, 'name') }}</div>
                                        <div v-if="product.promotion" class="app-muted mt-1 text-xs">{{ product.promotion }}</div>
                                    </td>
                                    <td class="app-text px-3 py-2 text-right">
                                        <input
                                            v-if="editingLineId === product.id"
                                            v-model="lineForm.quantity"
                                            type="number"
                                            min="0"
                                            class="app-surface app-text h-9 w-20 rounded-md border px-2 text-right text-sm outline-none"
                                        />
                                        <span v-else>{{ formatNumber(product.quantity) }}</span>
                                    </td>
                                    <td class="app-text px-3 py-2 text-right">{{ product.billedQuantity ?? '-' }}</td>
                                    <td class="app-text px-3 py-2 text-right">{{ currency }} {{ formatMoney(product.price) }}</td>
                                    <td class="app-text px-3 py-2 text-right">
                                        <input
                                            v-if="editingLineId === product.id"
                                            v-model="lineForm.discount"
                                            type="number"
                                            min="0"
                                            max="100"
                                            step="0.01"
                                            class="app-surface app-text h-9 w-20 rounded-md border px-2 text-right text-sm outline-none"
                                        />
                                        <span v-else>{{ formatMoney(product.discount) }}%</span>
                                    </td>
                                    <td class="app-text px-3 py-2 text-right">{{ formatMoney(product.billedDiscount) }}%</td>
                                    <td class="app-text px-3 py-2 text-right">{{ productSubtotal(product, 'chargedSubtotal') }}</td>
                                    <td class="app-text px-3 py-2 text-right">{{ productSubtotal(product, 'billedSubtotal') }}</td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr class="app-surface-soft font-semibold">
                                    <td class="app-text px-3 py-2 text-right" :colspan="canEditProducts ? 10 : 9">Total</td>
                                    <td class="app-text px-3 py-2 text-right">{{ currency }} {{ formatMoney(order.totals.products) }}</td>
                                    <td class="app-text px-3 py-2 text-right">{{ currency }} {{ formatMoney(order.totals.billed) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <div v-if="canProcessCurrentOrder" class="mt-5 flex justify-end">
                        <button
                            type="button"
                            class="app-primary inline-flex h-10 items-center justify-center rounded-md px-5 text-sm font-semibold shadow-sm"
                            @click="openProcessModal"
                        >
                            Procesar pedido
                        </button>
                    </div>
                </div>
            </template>

            <div
                v-if="showProcessModal && order"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
                role="dialog"
                aria-modal="true"
            >
                <div class="app-surface w-full max-w-2xl overflow-hidden rounded-lg border shadow-xl">
                    <div class="app-border-soft flex items-start justify-between gap-4 border-b px-6 py-5">
                        <div>
                            <p class="app-primary-text text-xs font-semibold uppercase">Procesar pedido</p>
                            <h2 class="app-text mt-1 text-xl font-semibold">{{ order.reference }}</h2>
                        </div>
                        <button
                            type="button"
                            class="app-surface-soft app-text flex h-9 w-9 items-center justify-center rounded-md border text-xl"
                            :disabled="processSaving"
                            @click="closeProcessModal"
                        >
                            x
                        </button>
                    </div>

                    <form class="px-6 py-5" @submit.prevent="processOrder">
                        <div
                            :class="[
                                'rounded-md border px-4 py-3 text-sm font-semibold',
                                processImpact.type === 'refund'
                                    ? 'border-amber-200 bg-amber-50 text-amber-800'
                                    : processImpact.type === 'charge'
                                        ? 'border-red-200 bg-red-50 text-red-700'
                                        : 'border-emerald-200 bg-emerald-50 text-emerald-700',
                            ]"
                        >
                            <p class="text-base">{{ processImpact.title }}</p>
                            <p class="mt-1">{{ processImpact.message }}</p>
                            <p v-if="processImpact.type === 'refund'" class="mt-2">
                                Se guardara en el pedido como devolucion pendiente de realizar.
                            </p>
                        </div>

                        <div class="mt-5 grid gap-3 sm:grid-cols-2">
                            <div class="app-surface-soft rounded-md border p-4">
                                <p class="app-muted text-xs font-semibold uppercase">Total aprobado</p>
                                <p class="app-text mt-1 text-xl font-semibold">{{ currency }} {{ formatMoney(order.totals.paid) }}</p>
                            </div>
                            <div class="app-surface-soft rounded-md border p-4">
                                <p class="app-muted text-xs font-semibold uppercase">Total detalle</p>
                                <p class="app-text mt-1 text-xl font-semibold">{{ currency }} {{ formatMoney(order.totals.paidCalculated) }}</p>
                            </div>
                        </div>

                        <label class="mt-5 block text-sm font-semibold">
                            <span class="app-muted">Numero de ticket</span>
                            <input
                                v-model="processForm.ticket"
                                type="text"
                                class="app-surface app-text mt-2 h-11 w-full rounded-md border px-3 text-sm outline-none focus:ring-4"
                                maxlength="100"
                                required
                            />
                        </label>

                        <div v-if="processError" class="mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                            {{ processError }}
                        </div>

                        <p class="app-muted mt-4 text-xs">
                            El envio de correo al cliente queda pendiente para el siguiente corte.
                        </p>

                        <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                            <button
                                type="button"
                                class="app-surface-soft app-text inline-flex h-10 items-center justify-center rounded-md border px-4 text-sm font-semibold"
                                :disabled="processSaving"
                                @click="closeProcessModal"
                            >
                                Cancelar
                            </button>
                            <button
                                type="submit"
                                class="app-primary inline-flex h-10 items-center justify-center rounded-md px-4 text-sm font-semibold shadow-sm disabled:opacity-60"
                                :disabled="processSaving"
                            >
                                {{ processSaving ? 'Procesando...' : 'Confirmar proceso' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    </AdminLayout>
</template>
