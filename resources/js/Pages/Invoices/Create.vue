<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import StepClient from '@/Components/Invoices/StepClient.vue';
import StepLineItems from '@/Components/Invoices/StepLineItems.vue';
import StepReview from '@/Components/Invoices/StepReview.vue';
import StepFinalize from '@/Components/Invoices/StepFinalize.vue';

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

function selectClient(client) {
    selectedClient.value = client;
    form.client_id = client.id;
    // Set first contact as default in first line item
    if (client.contacts?.length && form.line_items[0]) {
        form.line_items[0].contact_id = client.contacts[0].id;
    }
}

const subtotal = computed(() => {
    return form.line_items.reduce((sum, item) => {
        const qty = parseInt(item.quantity) || 1;
        if (item.item_type === 'collection') {
            return sum + (parseFloat(item.unit_price) || 0) * qty;
        }
        const fee = parseFloat(item.craftsmanship_fee) || 0;
        const fabric = parseFloat(item.fabric_cost) || 0;
        return sum + (fee + fabric) * qty;
    }, 0);
});

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
            return i.contact_id && parseFloat(i.craftsmanship_fee) > 0;
        });
        case 3: return true;
        case 4: return !!form.date;
        default: return false;
    }
});

function nextStep() {
    if (canNext.value && currentStep.value < 4) {
        currentStep.value++;
    }
}

function prevStep() {
    if (currentStep.value > 1) {
        currentStep.value--;
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
                <Link :href="route('invoices.index')" class="hover:text-green-600">Invoices</Link>
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
                        docType === 'invoice' ? 'bg-green-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
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
                            currentStep === step.num ? 'bg-green-600 text-white' :
                            currentStep > step.num ? 'bg-green-100 text-green-700 cursor-pointer hover:bg-green-200' :
                            'bg-gray-100 text-gray-400'
                        ]"
                    >
                        <i :class="['pi text-xs', step.icon]"></i>
                        <span class="hidden sm:inline">{{ step.label }}</span>
                    </button>
                    <div v-if="step.num < 4" :class="['flex-1 h-0.5 mx-2', currentStep > step.num ? 'bg-green-400' : 'bg-gray-200']"></div>
                </div>
            </div>

            <!-- Step content -->
            <div class="rounded-xl bg-white p-6 shadow-sm border border-gray-100">
                <StepClient
                    v-if="currentStep === 1"
                    :clients="clients"
                    :selected-client-id="form.client_id"
                    @select="selectClient"
                />

                <StepLineItems
                    v-if="currentStep === 2"
                    v-model:line-items="form.line_items"
                    :contacts="contacts"
                    :fabrics="fabrics"
                    :collections="collections"
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
                        class="inline-flex items-center gap-1 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                        Next <i class="pi pi-arrow-right text-xs"></i>
                    </button>

                    <button
                        v-else
                        type="button"
                        @click="submit"
                        :disabled="form.processing || !canNext"
                        class="inline-flex items-center gap-1 rounded-lg bg-green-600 px-5 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50 transition-colors"
                    >
                        <i class="pi pi-check text-xs"></i>
                        {{ form.processing ? 'Creating...' : (docType === 'quotation' ? 'Create Quotation' : 'Create Invoice') }}
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
