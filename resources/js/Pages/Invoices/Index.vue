<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    invoices: Object,
    filters: Object,
});

const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || '');
const typeFilter = ref(props.filters?.type || '');

function applyFilters() {
    router.get(route('invoices.index'), {
        search: search.value,
        status: status.value,
        type: typeFilter.value,
    }, { preserveState: true, replace: true });
}

let searchTimeout = null;
watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 300);
});

watch(status, applyFilters);
watch(typeFilter, applyFilters);

const statusColors = {
    draft: 'bg-gray-100 text-gray-700',
    issued: 'bg-blue-100 text-blue-700',
    paid: 'bg-green-100 text-green-700',
    overdue: 'bg-red-100 text-red-700',
    voided: 'bg-gray-100 text-gray-500',
};

function formatCurrency(amount) {
    return 'KES ' + Number(amount).toLocaleString('en-KE', { minimumFractionDigits: 0 });
}

function formatDate(dateStr) {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString('en-KE', { day: 'numeric', month: 'short', year: 'numeric' });
}

function deleteInvoice(invoice) {
    if (confirm(`Delete invoice ${invoice.invoice_number}?`)) {
        router.delete(route('invoices.destroy', invoice.id));
    }
}
</script>

<template>
    <Head title="Invoices" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Invoices & Quotations</h2>
                <div class="flex gap-2">
                    <Link :href="route('invoices.create', { type: 'quotation' })" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition-colors">
                        <i class="pi pi-plus text-xs"></i> New Quote
                    </Link>
                    <Link :href="route('invoices.create')" class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 transition-colors">
                        <i class="pi pi-plus text-xs"></i> New Invoice
                    </Link>
                </div>
            </div>
        </template>

        <!-- Filters -->
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-1 gap-3">
                <div class="relative flex-1 max-w-md">
                    <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                    <input v-model="search" type="text" placeholder="Search by invoice # or client..." class="w-full rounded-lg border border-gray-300 bg-white py-2 pl-9 pr-4 text-sm focus:border-brand-600 focus:ring-brand-600" />
                </div>
                <select v-model="typeFilter" class="rounded-lg border border-gray-300 bg-white py-2 pl-3 pr-8 text-sm focus:border-brand-600 focus:ring-brand-600">
                    <option value="">All Types</option>
                    <option value="invoice">Invoices</option>
                    <option value="quotation">Quotations</option>
                </select>
                <select v-model="status" class="rounded-lg border border-gray-300 bg-white py-2 pl-3 pr-8 text-sm focus:border-brand-600 focus:ring-brand-600">
                    <option value="">All Status</option>
                    <option value="draft">Draft</option>
                    <option value="issued">Issued</option>
                    <option value="paid">Paid</option>
                    <option value="overdue">Overdue</option>
                    <option value="voided">Voided</option>
                </select>
            </div>
            <div class="text-sm text-gray-500">{{ invoices.total }} invoice{{ invoices.total !== 1 ? 's' : '' }}</div>
        </div>

        <!-- Table -->
        <div class="rounded-xl bg-white shadow-sm border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left">
                        <tr>
                            <th class="px-6 py-3 font-medium text-gray-500 uppercase text-xs">Invoice #</th>
                            <th class="px-6 py-3 font-medium text-gray-500 uppercase text-xs">Client</th>
                            <th class="px-6 py-3 font-medium text-gray-500 uppercase text-xs">Date</th>
                            <th class="px-6 py-3 font-medium text-gray-500 uppercase text-xs">Status</th>
                            <th class="px-6 py-3 font-medium text-gray-500 uppercase text-xs text-right">Total</th>
                            <th class="px-6 py-3 font-medium text-gray-500 uppercase text-xs text-right hidden md:table-cell">Balance</th>
                            <th class="px-6 py-3 font-medium text-gray-500 uppercase text-xs text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr
                            v-for="inv in invoices.data"
                            :key="inv.id"
                            class="hover:bg-gray-50 cursor-pointer"
                            @click="router.visit(route('invoices.show', inv.id))"
                        >
                            <td class="px-6 py-4">
                                <span class="font-medium text-gray-900">{{ inv.invoice_number }}</span>
                                <span v-if="inv.type === 'quotation'" class="ml-1.5 inline-flex rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-medium text-blue-600">Quote</span>
                            </td>
                            <td class="px-6 py-4 text-gray-700">{{ inv.client?.name || '—' }}</td>
                            <td class="px-6 py-4 text-gray-600">{{ formatDate(inv.date) }}</td>
                            <td class="px-6 py-4">
                                <span :class="statusColors[inv.status] || 'bg-gray-100 text-gray-700'" class="rounded-full px-2.5 py-0.5 text-xs font-medium capitalize">{{ inv.status }}</span>
                            </td>
                            <td class="px-6 py-4 text-right font-medium text-gray-900">{{ formatCurrency(inv.total) }}</td>
                            <td class="px-6 py-4 text-right hidden md:table-cell" :class="Number(inv.balance) > 0 ? 'text-red-600 font-medium' : 'text-gray-500'">
                                {{ formatCurrency(inv.balance) }}
                            </td>
                            <td class="px-6 py-4 text-right" @click.stop>
                                <Link :href="route('invoices.show', inv.id)" class="text-gray-400 hover:text-brand-600 mr-2" title="View"><i class="pi pi-eye"></i></Link>
                                <button v-if="inv.status !== 'paid'" @click="deleteInvoice(inv)" class="text-gray-400 hover:text-red-600" title="Delete"><i class="pi pi-trash"></i></button>
                            </td>
                        </tr>
                        <tr v-if="invoices.data.length === 0">
                            <td colspan="7" class="px-6 py-12 text-center text-gray-400">
                                <i class="pi pi-file-edit text-4xl mb-3 block"></i>
                                <p class="font-medium">No invoices found</p>
                                <p class="text-sm mt-1">Create your first invoice to get started.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <!-- Pagination -->
            <div v-if="invoices.last_page > 1" class="border-t border-gray-100 px-6 py-3 flex items-center justify-between">
                <div class="text-sm text-gray-500">Showing {{ invoices.from }} to {{ invoices.to }} of {{ invoices.total }}</div>
                <div class="flex gap-1">
                    <Link v-for="link in invoices.links" :key="link.label" :href="link.url || '#'" v-html="link.label" :class="['px-3 py-1 rounded text-sm', link.active ? 'bg-brand-600 text-white' : 'text-gray-600 hover:bg-gray-100', !link.url ? 'opacity-50 cursor-not-allowed' : '']" />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
