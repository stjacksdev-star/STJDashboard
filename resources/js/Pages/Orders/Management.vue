<script setup>
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';

const showModal = ref(false);
const reference = ref('');
const loading = ref(false);
const saving = ref(false);
const error = ref('');
const success = ref('');
const order = ref(null);
const form = ref(defaultForm());

function defaultForm() {
    return {
        reference: '',
        shippingType: '',
        urbanId: '',
        shippingId: '',
        shippingCost: 0,
        shippingCostText: '',
        finalShippingCost: 0,
        freeShipping: 'NO',
        routeAt: '',
        addressType: '',
        samePerson: 'NO',
        sameAddress: 'NO',
        country: '',
        latitude: '',
        longitude: '',
        address: '',
        referencePoint: '',
        departmentId: '',
        municipalityId: '',
        department: '',
        municipality: '',
        district: '',
        receiverName: '',
        receiverPhone: '',
        saveType: '',
    };
}

function openModal() {
    showModal.value = true;
    resetLookup();
}

function closeModal() {
    if (loading.value || saving.value) return;
    showModal.value = false;
}

function resetLookup() {
    reference.value = '';
    order.value = null;
    form.value = defaultForm();
    error.value = '';
    success.value = '';
}

function editAnother() {
    resetLookup();
}

function fillForm(data) {
    const shipping = data.orderShipping || {};
    const address = data.address || {};
    form.value = {
        reference: data.reference,
        shippingType: shipping.shippingType || '',
        urbanId: shipping.urbanId || '',
        shippingId: shipping.shippingId || '',
        shippingCost: shipping.shippingCost ?? 0,
        shippingCostText: shipping.shippingCostText || '',
        finalShippingCost: shipping.finalShippingCost ?? 0,
        freeShipping: shipping.freeShipping || 'NO',
        routeAt: shipping.routeAt || '',
        addressType: address.addressType || '',
        samePerson: address.samePerson || 'NO',
        sameAddress: address.sameAddress || 'NO',
        country: address.country || '',
        latitude: address.latitude || '',
        longitude: address.longitude || '',
        address: address.address || '',
        referencePoint: address.referencePoint || '',
        departmentId: address.departmentId || '',
        municipalityId: address.municipalityId || '',
        department: address.department || '',
        municipality: address.municipality || '',
        district: address.district || '',
        receiverName: address.receiverName || '',
        receiverPhone: address.receiverPhone || '',
        saveType: address.saveType || '',
    };
}

async function lookup() {
    if (!reference.value.trim()) {
        error.value = 'Ingrese la referencia STJ del pedido.';
        return;
    }

    loading.value = true;
    error.value = '';
    success.value = '';

    try {
        const response = await window.axios.post('/dashboard-api/orders/shipping-management/lookup', {
            reference: reference.value.trim(),
        });
        order.value = response.data.data;
        if (order.value.isHomeDelivery) fillForm(order.value);
    } catch (exception) {
        error.value = exception.response?.data?.message || 'No fue posible consultar el pedido.';
    } finally {
        loading.value = false;
    }
}

async function save() {
    saving.value = true;
    error.value = '';
    success.value = '';

    try {
        const response = await window.axios.post('/dashboard-api/orders/shipping-management', form.value);
        order.value = response.data.data;
        fillForm(order.value);
        success.value = response.data.message || 'Datos de envio actualizados correctamente.';
    } catch (exception) {
        error.value = exception.response?.data?.message || 'No fue posible actualizar los datos de envio.';
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <Head title="Pedidos / Gestiones" />

    <AdminLayout>
        <section class="mx-auto w-full max-w-7xl">
            <div>
                <p class="app-primary-text text-sm font-semibold uppercase tracking-[0.18em]">Pedidos</p>
                <h1 class="app-text mt-2 text-3xl font-semibold tracking-tight">Gestiones</h1>
                <p class="app-muted mt-2">Herramientas administrativas para gestionar pedidos.</p>
            </div>

            <div class="mt-6 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                <article class="app-surface flex min-h-56 flex-col rounded-lg border p-6">
                    <div class="flex h-11 w-11 items-center justify-center rounded-lg bg-blue-50 text-xl text-blue-600">↗</div>
                    <h2 class="app-text mt-5 text-xl font-semibold">Gestion de envio por pedido</h2>
                    <p class="app-muted mt-2 flex-1 text-sm leading-6">
                        Consulte un pedido por referencia STJ y actualice sus datos de envio cuando sea a domicilio.
                    </p>
                    <button type="button" class="mt-5 self-start rounded-md bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700" @click="openModal">
                        Consultar
                    </button>
                </article>
            </div>
        </section>

        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4" @click.self="closeModal">
            <div class="app-surface max-h-[92vh] w-full max-w-5xl overflow-y-auto rounded-xl border shadow-2xl">
                <div class="sticky top-0 z-10 flex items-center justify-between border-b bg-white px-6 py-4">
                    <div>
                        <p class="app-primary-text text-xs font-semibold uppercase">Gestion de envio</p>
                        <h2 class="app-text mt-1 text-xl font-semibold">Pedido por referencia STJ</h2>
                    </div>
                    <button type="button" class="app-muted text-2xl" :disabled="loading || saving" @click="closeModal">×</button>
                </div>

                <div class="p-6">
                    <form v-if="!order" class="mx-auto max-w-xl" @submit.prevent="lookup">
                        <p class="app-muted mb-5 text-sm">Ingrese el STJ. La referencia es unica y no requiere seleccionar pais ni fechas.</p>
                        <label class="block text-sm font-semibold">
                            <span class="app-muted">STJ del pedido</span>
                            <input v-model="reference" type="text" maxlength="60" required autofocus class="app-surface app-text mt-2 h-11 w-full rounded-md border px-3 outline-none focus:ring-4" placeholder="STJ..." />
                        </label>
                        <div v-if="error" class="mt-4 rounded-md border border-red-200 bg-red-50 p-3 text-sm font-semibold text-red-700">{{ error }}</div>
                        <div class="mt-6 flex justify-end gap-3">
                            <button type="button" class="rounded-md border px-4 py-2 text-sm font-semibold" @click="closeModal">Cancelar</button>
                            <button type="submit" class="rounded-md bg-blue-600 px-5 py-2 text-sm font-semibold text-white disabled:opacity-60" :disabled="loading">
                                {{ loading ? 'Consultando...' : 'Consultar' }}
                            </button>
                        </div>
                    </form>

                    <div v-else>
                        <div class="app-surface-soft flex flex-wrap items-center justify-between gap-3 rounded-lg border p-4">
                            <div>
                                <p class="app-muted text-xs font-semibold uppercase">Pedido</p>
                                <p class="app-text mt-1 font-semibold">{{ order.reference }} · {{ order.status }}</p>
                            </div>
                            <button type="button" class="rounded-md border px-4 py-2 text-sm font-semibold" @click="editAnother">Consultar otro STJ</button>
                        </div>

                        <div v-if="!order.isHomeDelivery" class="mt-5 rounded-lg border border-blue-200 bg-blue-50 p-6 text-blue-900">
                            <h3 class="text-lg font-semibold">El pedido fue tipo TIENDA</h3>
                            <p class="mt-2 text-sm">Este pedido no contiene datos de envio a domicilio para editar.</p>
                        </div>

                        <form v-else class="mt-5 space-y-6" @submit.prevent="save">
                            <div class="rounded-lg border p-5">
                                <h3 class="app-text text-lg font-semibold">stj_pedidos_direccion</h3>
                                <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                    <label class="field"><span>ID (solo lectura)</span><input :value="order.orderShipping.id" disabled /></label>
                                    <label class="field"><span>ID pedido (solo lectura)</span><input :value="order.orderShipping.orderId" disabled /></label>
                                    <label class="field"><span>ID direccion (solo lectura)</span><input :value="order.orderShipping.addressId" disabled /></label>
                                    <label class="field"><span>Tipo envio</span><input v-model="form.shippingType" required /></label>
                                    <label class="field"><span>ID Urbano</span><input v-model="form.urbanId" /></label>
                                    <label class="field"><span>ID Shipping</span><input v-model="form.shippingId" /></label>
                                    <label class="field"><span>Costo envio</span><input v-model.number="form.shippingCost" type="number" min="0" step="0.01" required /></label>
                                    <label class="field"><span>Costo envio final</span><input v-model.number="form.finalShippingCost" type="number" min="0" step="0.01" required /></label>
                                    <label class="field"><span>Aplica envio gratis</span><select v-model="form.freeShipping"><option>NO</option><option>SI</option></select></label>
                                    <label class="field sm:col-span-2"><span>Texto costo envio</span><input v-model="form.shippingCostText" /></label>
                                    <label class="field"><span>Fecha en ruta</span><input v-model="form.routeAt" type="datetime-local" /></label>
                                </div>
                            </div>

                            <div class="rounded-lg border p-5">
                                <h3 class="app-text text-lg font-semibold">stj_direcciones</h3>
                                <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                    <label class="field"><span>ID (solo lectura)</span><input :value="order.address.id" disabled /></label>
                                    <label class="field"><span>Fecha (solo lectura)</span><input :value="order.address.createdAt" disabled /></label>
                                    <label class="field"><span>Usuario (solo lectura)</span><input :value="order.address.userId" disabled /></label>
                                    <label class="field"><span>Tipo direccion</span><input v-model="form.addressType" /></label>
                                    <label class="field"><span>Misma persona</span><select v-model="form.samePerson"><option>NO</option><option>SI</option></select></label>
                                    <label class="field"><span>Misma direccion</span><select v-model="form.sameAddress"><option>NO</option><option>SI</option></select></label>
                                    <label class="field"><span>Pais</span><input v-model="form.country" required /></label>
                                    <label class="field"><span>Latitud</span><input v-model="form.latitude" /></label>
                                    <label class="field"><span>Longitud</span><input v-model="form.longitude" /></label>
                                    <label class="field sm:col-span-2 lg:col-span-3"><span>Direccion</span><textarea v-model="form.address" rows="2" required /></label>
                                    <label class="field sm:col-span-2 lg:col-span-3"><span>Punto de referencia</span><textarea v-model="form.referencePoint" rows="2" /></label>
                                    <label class="field"><span>ID departamento</span><input v-model="form.departmentId" /></label>
                                    <label class="field"><span>Departamento</span><input v-model="form.department" /></label>
                                    <label class="field"><span>Distrito</span><input v-model="form.district" /></label>
                                    <label class="field"><span>ID municipio</span><input v-model="form.municipalityId" /></label>
                                    <label class="field"><span>Municipio</span><input v-model="form.municipality" /></label>
                                    <label class="field"><span>Tipo guardado</span><input v-model="form.saveType" /></label>
                                    <label class="field"><span>Persona recibe</span><input v-model="form.receiverName" /></label>
                                    <label class="field"><span>Telefono recibe</span><input v-model="form.receiverPhone" /></label>
                                </div>
                            </div>

                            <div v-if="error" class="rounded-md border border-red-200 bg-red-50 p-3 text-sm font-semibold text-red-700">{{ error }}</div>
                            <div v-if="success" class="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm font-semibold text-emerald-700">{{ success }}</div>
                            <div class="flex justify-end gap-3">
                                <button type="button" class="rounded-md border px-4 py-2 text-sm font-semibold" @click="closeModal">Cerrar</button>
                                <button type="submit" class="rounded-md bg-blue-600 px-5 py-2 text-sm font-semibold text-white disabled:opacity-60" :disabled="saving">
                                    {{ saving ? 'Guardando...' : 'Guardar cambios' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<style scoped>
.field { display: flex; flex-direction: column; gap: 0.45rem; color: var(--app-muted, #64748b); font-size: 0.8rem; font-weight: 600; }
.field input, .field select, .field textarea { width: 100%; border: 1px solid #dbe3ef; border-radius: 0.375rem; background: var(--app-surface, #fff); color: var(--app-text, #0f172a); padding: 0.6rem 0.7rem; font-size: 0.875rem; outline: none; }
.field input:disabled { background: #f1f5f9; color: #64748b; }
</style>
