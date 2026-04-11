<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    client: Object,
    invoices: Array,
    totalInvoiced: Number,
    totalPaid: Number,
    totalBalance: Number,
});

const statusClasses = {
    draft: 'bg-gray-100 text-gray-600',
    issued: 'bg-blue-100 text-blue-700',
    paid: 'bg-green-100 text-green-700',
    overdue: 'bg-red-100 text-red-700',
    partial: 'bg-amber-100 text-amber-700',
    voided: 'bg-gray-100 text-gray-400',
};

function fmt(n) {
    return 'KES ' + Number(n || 0).toLocaleString('en-KE', { minimumFractionDigits: 0 });
}
</script>

<template>
    <Head :title="`Statement – ${client.name}`" />
    <AuthenticatedLayout>
        <template #breadcrumb>
            <nav class="flex items-center gap-2 text-sm text-gray-500">
                <Link :href="route('clients.index')" class="hover:text-brand-600">Clients</Link>
                <i class="pi pi-angle-right text-xs"></i>
                <Link :href="route('clients.show', client.id)" class="hover:text-brand-600">{{ client.name }}</Link>
                <i class="pi pi-angle-right text-xs"></i>
                <span class="text-gray-900 font-medium">Statement</span>
            </nav>
        </template>

        <!-- Header -->
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Account Statement</h1>
                <p class="text-sm text-gray-500 mt-0.5">{{ client.name }}</p>
            </div>
            <a
                :href="route('clients.statement.pdf', client.id)"
                target="_blank"
                class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-700 shadow-sm"
            >
                <i class="pi pi-download text-sm"></i>
                Download PDF
            </a>
        </div>

        <!-- Summary Cards -->
        <div class="mb-6 grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="rounded-xl bg-white border border-gray-100 shadow-sm p-5">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Total Invoiced</p>
                <p class="mt-1 text-2xl font-bold text-gray-900">{{ fmt(totalInvoiced) }}</p>
            </div>
            <div class="rounded-xl bg-white border border-gray-100 shadow-sm p-5">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Total Paid</p>
                <p class="mt-1 text-2xl font-bold text-green-600">{{ fmt(totalPaid) }}</p>
            </div>
            <div class="rounded-xl bg-white border border-gray-100 shadow-sm p-5">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Balance Due</p>
                <p class="mt-1 text-2xl font-bold" :class="totalBalance > 0 ? 'text-red-600' : 'text-gray-400'">{{ fmt(totalBalance) }}</p>
            </div>
        </div>

        <!-- Invoices Table -->
        <div class="rounded-xl bg-white border border-gray-100 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-900">Invoice History</h2>
            </div>

            <div v-if="invoices.length === 0" class="px-6 py-12 text-center text-sm text-gray-400">
                No invoices found for this client.
            </div>

            <table v-else class="min-w-full divide-y divide-gray-100">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Invoice #</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Due Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Status</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wide">Amount</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wide">Paid</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wide">Balance</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="inv in invoices" :key="inv.id" class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-3 text-sm font-semibold text-gray-900">{{ inv.invoice_number }}</td>
                        <td class="px-6 py-3 text-sm text-gray-600">{{ inv.date }}</td>
                        <td class="px-6 py-3 text-sm text-gray-600">{{ inv.due_date || '—' }}</td>
                        <td class="px-6 py-3">
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold capitalize" :class="statusClasses[inv.status] || 'bg-gray-100 text-gray-600'">
                                {{ inv.status }}
                            </span>
                        </td>
                        <td class="px-6 py-3 text-sm text-right text-gray-900">{{ fmt(inv.total) }}</td>
                        <td class="px-6 py-3 text-sm text-right text-green-600 font-medium">{{ fmt(inv.amount_paid) }}</td>
                        <td class="px-6 py-3 text-sm text-right font-semibold" :class="inv.balance > 0 ? 'text-red-600' : 'text-gray-400'">
                            {{ fmt(inv.balance) }}
                        </td>
                        <td class="px-6 py-3 text-right">
                            <Link :href="route('invoices.show', inv.id)" class="text-xs text-brand-600 hover:underline">View</Link>
                        </td>
                    </tr>
                </tbody>
                <tfoot class="bg-gray-50 border-t border-gray-200">
                    <tr>
                        <td colspan="4" class="px-6 py-3 text-sm font-bold text-gray-900">Totals</td>
                        <td class="px-6 py-3 text-sm font-bold text-right text-gray-900">{{ fmt(totalInvoiced) }}</td>
                        <td class="px-6 py-3 text-sm font-bold text-right text-green-600">{{ fmt(totalPaid) }}</td>
                        <td class="px-6 py-3 text-sm font-bold text-right" :class="totalBalance > 0 ? 'text-red-600' : 'text-gray-400'">{{ fmt(totalBalance) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </AuthenticatedLayout>
</template>
