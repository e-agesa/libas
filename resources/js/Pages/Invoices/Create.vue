<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import StepClient from '@/Components/Invoices/StepClient.vue';
import StepLineItems from '@/Components/Invoices/StepLineItems.vue';
import StepReview from '@/Components/Invoices/StepReview.vue';
import StepFinalize from '@/Components/Invoices/StepFinalize.vue';
import QuickClientModal from '@/Components/Invoices/QuickClientModal.vue';
import QuickPersonModal from '@/Components/Invoices/QuickPersonModal.vue';
import QuickMeasurementModal from '@/Components/Invoices/QuickMeasurementModal.vue';
import { invoiceSubtotal, ridhaaTotal } from '@/composables/useLineTotal';

const props = defineProps({
    clients: Array,
    fabrics: Array,
    collections: Array,
    invoiceNumber: String,
    quoteNumber: String,
    selectedClientId: [Number, null],
    defaultType: { type: String, default: 'invoice' },
});

const currentStep = ref(1);
const selectedClient = ref(null);
const docType = ref(props.defaultType);

const form = useForm({
    type: props.defaultType,
    client_id: null,
    date: new Date().toISOString().split('T')[0],
    due_date: '',
    discount: 0,
    discount_type: 'flat',
    tax: 0,
    payment_method: 'cash',
    initial_payment: 0,
    notes: '',
    line_items: [{
        item_type: 'custom',
        contact_id: '',
        measurement_id: null,
        fabric_id: null,
        collection_id: null,
        description: '',
        unit_price: 0,
        quantity: 1,
        craftsmanship_fee: 0,
        fabric_cost: 0,
        ridhaa_name: '',
        ridhaa_qty: 0,
        ridhaa_price: 0,
    }],
});

// Pre-select client if passed via URL
onMounted(() => {
    if (props.selectedClientId) {
        const client = props.clients.find(c => c.id === props.selectedClientId);
        if (client) {
            selectClient(client);
            currentStep.value = 2;
        }
    }
});

const steps = [
    { num: 1, label: 'Client', icon: 'pi-user' },
    { num: 2, label: 'Items', icon: 'pi-list' },
    { num: 3, label: 'Review', icon: 'pi-eye' },
    { num: 4, label: 'Finalize', icon: 'pi-check-circle' },
];

const contacts = computed(() => selectedClient.value?.contacts || []);

// Quick-add modals (person / measurement) launched from a line item row
const showClientModal = ref(false);
const newClientName = ref('');
const showPersonModal = ref(false);
const showMeasurementModal = ref(false);
const activeItemIndex = ref(null);
const measurementContact = ref(null);
const editingMeasurementId = ref(null);

function openAddClient(prefill) {
    newClientName.value = typeof prefill === 'string' ? prefill : '';
    showClientModal.value = true;
}

function onClientCreated(client) {
    showClientModal.value = false;
    // Make it available to the picker, then run it through the normal selection
    // path so the client-switch guard clears any stale people on the line items.
    props.clients.unshift(client);
    selectClient(client);
}

function openAddPerson(index) {
    activeItemIndex.value = index;
    showPersonModal.value = true;
}

function openAddMeasurement(index, contactId) {
    const contact = contacts.value.find(c => c.id === contactId);
    if (!contact) return;
    activeItemIndex.value = index;
    measurementContact.value = contact;
    editingMeasurementId.value = null;
    showMeasurementModal.value = true;
}

function openEditMeasurement(index, contactId, measurementId) {
    const contact = contacts.value.find(c => c.id === contactId);
    if (!contact || !measurementId) return;
    activeItemIndex.value = index;
    measurementContact.value = contact;
    editingMeasurementId.value = measurementId;
    showMeasurementModal.value = true;
}

function onMeasurementUpdated(measurement) {
    showMeasurementModal.value = false;
    editingMeasurementId.value = null;

    // Refresh the cached copy so the dropdown label and garment badge follow
    // the edit, on every line item that references this measurement.
    const contact = contacts.value.find(c => c.id === measurement.contact_id);
    const existing = contact?.measurements?.find(m => m.id === measurement.id);
    if (existing) {
        Object.assign(existing, measurement);
    }
}

function onPersonCreated(contact) {
    showPersonModal.value = false;
    if (!selectedClient.value) return;
    if (!Array.isArray(selectedClient.value.contacts)) {
        selectedClient.value.contacts = [];
    }
    selectedClient.value.contacts.push({ ...contact, measurements: [] });
    const item = activeItemIndex.value !== null ? form.line_items[activeItemIndex.value] : null;
    if (item) {
        item.contact_id = contact.id;
        item.measurement_id = null;
    }
}

function onMeasurementCreated(measurement) {
    showMeasurementModal.value = false;
    const contact = contacts.value.find(c => c.id === measurement.contact_id);
    if (contact) {
        if (!Array.isArray(contact.measurements)) {
            contact.measurements = [];
        }
        contact.measurements.push(measurement);
    }
    const item = activeItemIndex.value !== null ? form.line_items[activeItemIndex.value] : null;
    if (item && item.contact_id === measurement.contact_id) {
        item.measurement_id = measurement.id;
    }
}

// Re-sync fabrics and shelf items from the server whenever the Items step is
// shown, so fabrics added in the Fabrics module appear without a full reload.
function syncCatalogs() {
    router.reload({ only: ['fabrics', 'collections'] });
}

function selectClient(client) {
    const switched = selectedClient.value && selectedClient.value.id !== client.id;

    selectedClient.value = client;
    form.client_id = client.id;

    // Switching client invalidates every person and measurement already picked:
    // they belong to the previous client's file, and leaving them would put
    // someone else's family member (and their measurements) on this invoice.
    if (switched) {
        form.line_items.forEach((item) => {
            if (item.item_type !== 'collection') {
                item.contact_id = '';
                item.measurement_id = null;
            }
        });
    }

    // Convenience default: first person of the new client on the first custom line.
    const first = form.line_items[0];
    if (client.contacts?.length && first && first.item_type !== 'collection' && !first.contact_id) {
        first.contact_id = client.contacts[0].id;
    }
}

const subtotal = computed(() => invoiceSubtotal(form.line_items));

const discountAmount = computed(() => {
    const d = parseFloat(form.discount) || 0;
    return form.discount_type === 'percentage'
        ? Math.round(subtotal.value * d / 100 * 100) / 100
        : d;
});

const total = computed(() => {
    const afterDiscount = subtotal.value - discountAmount.value;
    const taxAmt = Math.round(afterDiscount * (parseFloat(form.tax) || 0) / 100 * 100) / 100;
    return afterDiscount + taxAmt;
});

const canNext = computed(() => {
    switch (currentStep.value) {
        case 1: return !!form.client_id;
        case 2: return form.line_items.length > 0 && form.line_items.every(i => {
            if (i.item_type === 'collection') return i.collection_id && parseFloat(i.unit_price) > 0;
            // A line may be billed for the tailoring, for a ridhaa, or both.
            return i.contact_id && (parseFloat(i.craftsmanship_fee) > 0 || ridhaaTotal(i) > 0);
        });
        case 3: return true;
        case 4: return !!form.date;
        default: return false;
    }
});

function nextStep() {
    if (canNext.value && currentStep.value < 4) {
        currentStep.value++;
        if (currentStep.value === 2) syncCatalogs();
    }
}

function prevStep() {
    if (currentStep.value > 1) {
        currentStep.value--;
        if (currentStep.value === 2) syncCatalogs();
    }
}

function submit() {
    form.post(route('invoices.store'));
}
</script>

<template>
    <Head :title="docType === 'quotation' ? 'Create Quotation' : 'Create Invoice'" />
    <AuthenticatedLayout>
        <template #breadcrumb>
            <nav class="flex items-center gap-2 text-sm text-gray-500">
                <Link :href="route('invoices.index')" class="hover:text-brand-600">Invoices</Link>
                <i class="pi pi-angle-right text-xs"></i>
                <span class="text-gray-900 font-medium">New {{ docType === 'quotation' ? 'Quotation' : 'Invoice' }}</span>
            </nav>
        </template>

        <div class="max-w-3xl mx-auto">
            <!-- Document type toggle -->
            <div class="mb-4 flex items-center gap-2">
                <button
                    type="button"
                    @click="docType = 'invoice'; form.type = 'invoice'"
                    :class="[
                        'rounded-lg px-4 py-2 text-sm font-medium transition-colors',
                        docType === 'invoice' ? 'bg-brand-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                    ]"
                >
                    <i class="pi pi-file-edit text-xs mr-1"></i> Invoice
                </button>
                <button
                    type="button"
                    @click="docType = 'quotation'; form.type = 'quotation'"
                    :class="[
                        'rounded-lg px-4 py-2 text-sm font-medium transition-colors',
                        docType === 'quotation' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                    ]"
                >
                    <i class="pi pi-file text-xs mr-1"></i> Quotation
                </button>
            </div>
            <!-- Step indicator -->
            <div class="mb-6 flex items-center justify-between">
                <div v-for="step in steps" :key="step.num" class="flex items-center" :class="step.num < 4 ? 'flex-1' : ''">
                    <button
                        type="button"
                        @click="step.num < currentStep ? currentStep = step.num : null"
                        :class="[
                            'flex items-center gap-2 rounded-full px-3 py-1.5 text-sm font-medium transition-colors',
                            currentStep === step.num ? 'bg-brand-600 text-white' :
                            currentStep > step.num ? 'bg-brand-100 text-brand-700 cursor-pointer hover:bg-brand-200' :
                            'bg-gray-100 text-gray-400'
                        ]"
                    >
                        <i :class="['pi text-xs', step.icon]"></i>
                        <span class="hidden sm:inline">{{ step.label }}</span>
                    </button>
                    <div v-if="step.num < 4" :class="['flex-1 h-0.5 mx-2', currentStep > step.num ? 'bg-brand-400' : 'bg-gray-200']"></div>
                </div>
            </div>

            <!-- Step content -->
            <div class="rounded-xl bg-white p-6 shadow-sm border border-gray-100">
                <StepClient
                    v-if="currentStep === 1"
                    :clients="clients"
                    :selected-client-id="form.client_id"
                    @select="selectClient"
                    @add-client="openAddClient"
                />

                <StepLineItems
                    v-if="currentStep === 2"
                    v-model:line-items="form.line_items"
                    :contacts="contacts"
                    :fabrics="fabrics"
                    :collections="collections"
                    @add-person="openAddPerson"
                    @add-measurement="openAddMeasurement"
                    @edit-measurement="openEditMeasurement"
                />

                <StepReview
                    v-if="currentStep === 3"
                    :client="selectedClient"
                    :line-items="form.line_items"
                    :contacts="contacts"
                    :fabrics="fabrics"
                    v-model:discount="form.discount"
                    v-model:discount-type="form.discount_type"
                    v-model:tax="form.tax"
                />

                <StepFinalize
                    v-if="currentStep === 4"
                    v-model:date="form.date"
                    v-model:due-date="form.due_date"
                    v-model:payment-method="form.payment_method"
                    v-model:initial-payment="form.initial_payment"
                    v-model:notes="form.notes"
                    :total="total"
                />

                <!-- Navigation -->
                <div class="mt-6 flex justify-between items-center border-t border-gray-100 pt-4">
                    <button
                        v-if="currentStep > 1"
                        type="button"
                        @click="prevStep"
                        class="inline-flex items-center gap-1 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors"
                    >
                        <i class="pi pi-arrow-left text-xs"></i> Back
                    </button>
                    <div v-else></div>

                    <button
                        v-if="currentStep < 4"
                        type="button"
                        @click="nextStep"
                        :disabled="!canNext"
                        class="inline-flex items-center gap-1 rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                        Next <i class="pi pi-arrow-right text-xs"></i>
                    </button>

                    <button
                        v-else
                        type="button"
                        @click="submit"
                        :disabled="form.processing || !canNext"
                        class="inline-flex items-center gap-1 rounded-lg bg-brand-600 px-5 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50 transition-colors"
                    >
                        <i class="pi pi-check text-xs"></i>
                        {{ form.processing ? 'Creating...' : (docType === 'quotation' ? 'Create Quotation' : 'Create Invoice') }}
                    </button>
                </div>
            </div>
        </div>

        <QuickClientModal
            :show="showClientModal"
            :prefill-name="newClientName"
            @close="showClientModal = false"
            @created="onClientCreated"
        />

        <QuickPersonModal
            :show="showPersonModal"
            :client-id="form.client_id"
            :client-name="selectedClient?.name"
            @close="showPersonModal = false"
            @created="onPersonCreated"
        />

        <QuickMeasurementModal
            :show="showMeasurementModal"
            :contact-id="measurementContact?.id"
            :contact-name="measurementContact?.name"
            :measurement-id="editingMeasurementId"
            @close="showMeasurementModal = false; editingMeasurementId = null"
            @created="onMeasurementCreated"
            @updated="onMeasurementUpdated"
        />
    </AuthenticatedLayout>
</template>
