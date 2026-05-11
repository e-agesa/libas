<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Modal from '@/Components/Modal.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    invoice: Object,
});

const showPaymentModal = ref(false);
const showStatusModal = ref(false);

const paymentForm = useForm({
    amount: '',
    method: 'cash',
    date: new Date().toISOString().split('T')[0],
    reference: '',
    notes: '',
});

const statusForm = useForm({
    status: '',
});

const statusColors = {
    draft: 'bg-gray-100 text-gray-700',
    issued: 'bg-blue-100 text-blue-700',
    paid: 'bg-green-100 text-green-700',
    overdue: 'bg-red-100 text-red-700',
    voided: 'bg-gray-100 text-gray-500',
};

const methodLabels = {
    cash: 'Cash', mpesa: 'M-Pesa', bank_transfer: 'Bank Transfer', credit: 'Credit',
};

function formatCurrency(amount) {
    return 'KES ' + Number(amount).toLocaleString('en-KE', { minimumFractionDigits: 0 });
}

function formatDate(dateStr) {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString('en-KE', { day: 'numeric', month: 'short', year: 'numeric' });
}

function submitPayment() {
    paymentForm.post(route('payments.store', props.invoice.id), {
        onSuccess: () => {
            showPaymentModal.value = false;
            paymentForm.reset();
        },
        preserveScroll: true,
    });
}

function openStatusModal() {
    statusForm.status = props.invoice.status;
    showStatusModal.value = true;
}

function submitStatus() {
    statusForm.put(route('invoices.update', props.invoice.id), {
        onSuccess: () => {
            showStatusModal.value = false;
        },
        preserveScroll: true,
    });
}

const isPaid = computed(() => Number(props.invoice.balance) <= 0);
const isQuote = computed(() => props.invoice.type === 'quotation');

function convertToInvoice() {
    if (confirm('Convert this quotation to an invoice? A new invoice number will be generated.')) {
        router.put(route('invoices.update', props.invoice.id), {
            status: 'issued',
            convert_to_invoice: true,
        });
    }
}

function shareWhatsApp() {
    const inv = props.invoice;
    const linesSummary = inv.line_items?.map(item => {
        if (item.item_type === 'collection') {
            const name = item.collection?.name || item.description || 'Shelf Item';
            return `  - ${name} x${item.quantity}: ${formatCurrency(item.line_total)}`;
        }
        const garment = item.measurement?.garment_type || 'Item';
        const person = item.contact?.name || '';
        return `  - ${garment}${person ? ` (${person})` : ''}: ${formatCurrency(item.line_total)}`;
    }).join('\n') || '';

    const text = [
        `*${inv.type === 'quotation' ? 'QUOTATION' : 'INVOICE'}: ${inv.invoice_number}*`,
        `Client: ${inv.client?.name}`,
        `Date: ${formatDate(inv.date)}`,
        inv.due_date ? `Due: ${formatDate(inv.due_date)}` : '',
        '',
        '*Items:*',
        linesSummary,
        '',
        `*Total: ${formatCurrency(inv.total)}*`,
        Number(inv.amount_paid) > 0 ? `Paid: ${formatCurrency(inv.amount_paid)}` : '',
        Number(inv.balance) > 0 ? `Balance: ${formatCurrency(inv.balance)}` : '',
        '',
        'Thank you for choosing Libas TMS!',
    ].filter(Boolean).join('\n');

    window.open(`https://wa.me/?text=${encodeURIComponent(text)}`, '_blank');
}
</script>

<template>
    <Head :title="`Invoice ${invoice.invoice_number}`" />
    <AuthenticatedLayout>
        <template #breadcrumb>
            <nav class="flex items-center gap-2 text-sm text-gray-500">
                <Link :href="route('invoices.index')" class="hover:text-brand-600">Invoices</Link>
                <i class="pi pi-angle-right text-xs"></i>
                <span class="text-gray-900 font-medium">{{ invoice.invoice_number }}</span>
            </nav>
        </template>

        <div class="max-w-4xl mx-auto">
            <!-- Header -->
            <div class="mb-6 rounded-xl bg-white p-6 shadow-sm border border-gray-100">
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-3 mb-2">
                            <h1 class="text-2xl font-bold text-gray-900">{{ invoice.invoice_number }}</h1>
                            <span v-if="isQuote" class="rounded-full bg-blue-50 px-3 py-0.5 text-sm font-medium text-blue-700">Quotation</span>
                            <span :class="statusColors[invoice.status]" class="rounded-full px-3 py-0.5 text-sm font-medium capitalize">{{ invoice.status }}</span>
                        </div>
                        <div class="flex flex-wrap items-center gap-3 text-sm text-gray-500">
                            <span class="flex items-center gap-1">
                                <i class="pi pi-user text-xs"></i>
                                <Link :href="route('clients.show', invoice.client.id)" class="text-brand-600 hover:underline">{{ invoice.client.name }}</Link>
                            </span>
                            <span class="flex items-center gap-1"><i class="pi pi-calendar text-xs"></i> {{ formatDate(invoice.date) }}</span>
                            <span v-if="invoice.due_date" class="flex items-center gap-1"><i class="pi pi-clock text-xs"></i> Due {{ formatDate(invoice.due_date) }}</span>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button @click="openStatusModal" class="inline-flex items-center gap-1 rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            <i class="pi pi-sync text-xs"></i> Status
                        </button>
                        <a :href="route('invoices.pdf', invoice.id)" class="inline-flex items-center gap-1 rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            <i class="pi pi-file-pdf text-xs"></i> PDF
                        </a>
                        <button @click="shareWhatsApp" class="inline-flex items-center gap-1 rounded-lg border border-green-300 bg-green-50 px-3 py-2 text-sm font-medium text-green-700 hover:bg-green-100">
                            <i class="pi pi-whatsapp text-xs"></i> WhatsApp
                        </button>
                        <button v-if="isQuote" @click="convertToInvoice" class="inline-flex items-center gap-1 rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700">
                            <i class="pi pi-arrow-right-arrow-left text-xs"></i> Convert to Invoice
                        </button>
                        <button v-if="!isPaid && !isQuote" @click="showPaymentModal = true" class="inline-flex items-center gap-1 rounded-lg bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700">
                            <i class="pi pi-wallet text-xs"></i> Record Payment
                        </button>
                        <Link v-if="!isPaid && !isQuote" :href="route('pos.index') + '?invoice_id=' + invoice.id" class="inline-flex items-center gap-1 rounded-lg bg-amber-500 px-3 py-2 text-sm font-medium text-white hover:bg-amber-600">
                            <i class="pi pi-shopping-cart text-xs"></i> Add to POS
                        </Link>
                    </div>
                </div>

                <!-- Amount summary -->
                <div class="mt-4 grid grid-cols-3 gap-4 border-t border-gray-100 pt-4">
                    <div class="text-center">
                        <p class="text-2xl font-bold text-gray-900">{{ formatCurrency(invoice.total) }}</p>
                        <p class="text-xs text-gray-500">Total</p>
                    </div>
                    <div class="text-center">
                        <p class="text-2xl font-bold text-green-600">{{ formatCurrency(invoice.amount_paid) }}</p>
                        <p class="text-xs text-gray-500">Paid</p>
                    </div>
                    <div class="text-center">
                        <p class="text-2xl font-bold" :class="Number(invoice.balance) > 0 ? 'text-red-600' : 'text-gray-500'">{{ formatCurrency(invoice.balance) }}</p>
                        <p class="text-xs text-gray-500">Balance</p>
                    </div>
                </div>
            </div>

            <!-- Line Items -->
            <div class="mb-6 rounded-xl bg-white shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-3 bg-gray-50 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-700">Line Items</h3>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-gray-50/50">
                        <tr>
                            <th class="px-6 py-2.5 text-left text-xs font-medium text-gray-500">Item</th>
                            <th class="px-6 py-2.5 text-left text-xs font-medium text-gray-500">Details</th>
                            <th class="px-6 py-2.5 text-center text-xs font-medium text-gray-500">Qty</th>
                            <th class="px-6 py-2.5 text-right text-xs font-medium text-gray-500">Price</th>
                            <th class="px-6 py-2.5 text-right text-xs font-medium text-gray-500">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="item in invoice.line_items" :key="item.id">
                            <!-- Collection / shelf item -->
                            <template v-if="item.item_type === 'collection'">
                                <td class="px-6 py-3">
                                    <div class="flex items-center gap-2">
                                        <span class="inline-flex items-center gap-1 rounded-full bg-blue-100 text-blue-700 px-2 py-0.5 text-xs font-medium">
                                            <i class="pi pi-shopping-bag text-[10px]"></i> Shelf
                                        </span>
                                        <span class="text-gray-900 font-medium">{{ item.collection?.name || item.description || 'Collection Item' }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-3 text-gray-500 text-xs">
                                    <span v-if="item.collection?.size">{{ item.collection.size }}</span>
                                    <span v-if="item.collection?.color"> · {{ item.collection.color }}</span>
                                </td>
                                <td class="px-6 py-3 text-center text-gray-600">{{ item.quantity }}</td>
                                <td class="px-6 py-3 text-right text-gray-900">{{ formatCurrency(item.unit_price) }}</td>
                                <td class="px-6 py-3 text-right font-medium text-gray-900">{{ formatCurrency(item.line_total) }}</td>
                            </template>
                            <!-- Custom tailored item -->
                            <template v-else>
                                <td class="px-6 py-3">
                                    <div class="flex items-center gap-2">
                                        <span class="inline-flex items-center gap-1 rounded-full bg-green-100 text-green-700 px-2 py-0.5 text-xs font-medium">
                                            <i class="pi pi-scissors text-[10px]"></i> Custom
                                        </span>
                                        <span class="text-gray-900">{{ item.contact?.name || '—' }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-3">
                                    <span v-if="item.measurement" class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium capitalize"
                                        :class="{
                                            'bg-green-100 text-green-700': item.measurement.garment_type === 'kanzu',
                                            'bg-blue-100 text-blue-700': item.measurement.garment_type === 'shirt',
                                            'bg-amber-100 text-amber-700': item.measurement.garment_type === 'trouser',
                                            'bg-purple-100 text-purple-700': item.measurement.garment_type === 'vest',
                                        }">{{ item.measurement.garment_type }}</span>
                                    <span v-if="item.fabric" class="text-xs text-gray-500 ml-1">· {{ item.fabric.name }}</span>
                                    <span v-if="!item.measurement && !item.fabric" class="text-gray-400">—</span>
                                </td>
                                <td class="px-6 py-3 text-center text-gray-600">{{ item.quantity }}</td>
                                <td class="px-6 py-3 text-right text-gray-900 text-xs">
                                    <div>Fee: {{ formatCurrency(item.craftsmanship_fee) }}</div>
                                    <div v-if="Number(item.fabric_cost) > 0" class="text-gray-500">Fabric: {{ formatCurrency(item.fabric_cost) }}</div>
                                </td>
                                <td class="px-6 py-3 text-right font-medium text-gray-900">{{ formatCurrency(item.line_total) }}</td>
                            </template>
                        </tr>
                    </tbody>
                    <tfoot class="bg-gray-50">
                        <tr>
                            <td colspan="4" class="px-6 py-2 text-right text-sm text-gray-600">Subtotal</td>
                            <td class="px-6 py-2 text-right font-medium text-gray-900">{{ formatCurrency(invoice.subtotal) }}</td>
                        </tr>
                        <tr v-if="Number(invoice.discount) > 0">
                            <td colspan="4" class="px-6 py-1 text-right text-sm text-gray-600">Discount</td>
                            <td class="px-6 py-1 text-right font-medium text-red-600">-{{ formatCurrency(invoice.discount) }}</td>
                        </tr>
                        <tr v-if="Number(invoice.tax) > 0">
                            <td colspan="4" class="px-6 py-1 text-right text-sm text-gray-600">Tax</td>
                            <td class="px-6 py-1 text-right font-medium text-gray-900">{{ formatCurrency(invoice.tax) }}</td>
                        </tr>
                        <tr class="border-t border-gray-200">
                            <td colspan="4" class="px-6 py-2 text-right text-sm font-semibold text-gray-900">Total</td>
                            <td class="px-6 py-2 text-right font-bold text-green-700 text-base">{{ formatCurrency(invoice.total) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Payments -->
            <div class="mb-6 rounded-xl bg-white shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-3 bg-gray-50 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-700">Payments ({{ invoice.payments?.length || 0 }})</h3>
                </div>
                <div v-if="invoice.payments?.length">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50/50">
                            <tr>
                                <th class="px-6 py-2.5 text-left text-xs font-medium text-gray-500">Date</th>
                                <th class="px-6 py-2.5 text-left text-xs font-medium text-gray-500">Method</th>
                                <th class="px-6 py-2.5 text-left text-xs font-medium text-gray-500 hidden sm:table-cell">Reference</th>
                                <th class="px-6 py-2.5 text-right text-xs font-medium text-gray-500">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="payment in invoice.payments" :key="payment.id">
                                <td class="px-6 py-3 text-gray-600">{{ formatDate(payment.date) }}</td>
                                <td class="px-6 py-3">
                                    <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">{{ methodLabels[payment.method] || payment.method }}</span>
                                </td>
                                <td class="px-6 py-3 text-gray-500 hidden sm:table-cell">{{ payment.reference || '—' }}</td>
                                <td class="px-6 py-3 text-right font-medium text-green-700">{{ formatCurrency(payment.amount) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="px-6 py-8 text-center text-gray-400">
                    <p class="text-sm">No payments recorded yet.</p>
                </div>
            </div>

            <!-- Notes -->
            <div v-if="invoice.notes" class="mb-6 rounded-xl bg-white p-6 shadow-sm border border-gray-100">
                <h3 class="text-sm font-semibold text-gray-700 mb-2">Notes</h3>
                <p class="text-sm text-gray-600 whitespace-pre-line">{{ invoice.notes }}</p>
            </div>

            <!-- Footer branding -->
            <div class="text-center py-4">
                <p class="text-xs text-gray-400">Powered by <a href="https://twinfusion.com" target="_blank" class="text-brand-600 hover:text-brand-700 font-medium">TwinFusion</a></p>
            </div>
        </div>

        <!-- Record Payment Modal -->
        <Modal :show="showPaymentModal" @close="showPaymentModal = false" max-width="md">
            <form @submit.prevent="submitPayment" class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Record Payment</h3>
                <div class="space-y-4">
                    <div>
                        <label class="text-sm font-medium text-gray-700 mb-1 block">Amount (KES) *</label>
                        <input v-model="paymentForm.amount" type="number" min="0.01" :max="invoice.balance" step="1" required class="w-full rounded-md border-gray-300 text-sm focus:border-brand-600 focus:ring-brand-600" :placeholder="`Max: ${Number(invoice.balance).toLocaleString()}`" />
                        <InputError :message="paymentForm.errors.amount" class="mt-1" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-sm font-medium text-gray-700 mb-1 block">Method *</label>
                            <select v-model="paymentForm.method" class="w-full rounded-md border-gray-300 text-sm focus:border-brand-600 focus:ring-brand-600">
                                <option value="cash">Cash</option>
                                <option value="mpesa">M-Pesa</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="credit">Credit</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-700 mb-1 block">Date *</label>
                            <input v-model="paymentForm.date" type="date" required class="w-full rounded-md border-gray-300 text-sm focus:border-brand-600 focus:ring-brand-600" />
                        </div>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700 mb-1 block">Reference</label>
                        <input v-model="paymentForm.reference" type="text" class="w-full rounded-md border-gray-300 text-sm focus:border-brand-600 focus:ring-brand-600" placeholder="M-Pesa code, receipt #, etc." />
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700 mb-1 block">Notes</label>
                        <textarea v-model="paymentForm.notes" rows="2" class="w-full rounded-md border-gray-300 text-sm focus:border-brand-600 focus:ring-brand-600" placeholder="Optional notes"></textarea>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" @click="showPaymentModal = false" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="submit" :disabled="paymentForm.processing" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50">
                        {{ paymentForm.processing ? 'Saving...' : 'Record Payment' }}
                    </button>
                </div>
            </form>
        </Modal>

        <!-- Status Modal -->
        <Modal :show="showStatusModal" @close="showStatusModal = false" max-width="sm">
            <form @submit.prevent="submitStatus" class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Update Status</h3>
                <select v-model="statusForm.status" class="w-full rounded-md border-gray-300 text-sm focus:border-brand-600 focus:ring-brand-600 mb-4">
                    <option value="draft">Draft</option>
                    <option value="issued">Issued</option>
                    <option value="paid">Paid</option>
                    <option value="overdue">Overdue</option>
                    <option value="voided">Voided</option>
                </select>
                <div class="flex justify-end gap-3">
                    <button type="button" @click="showStatusModal = false" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="submit" :disabled="statusForm.processing" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50">Update</button>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
