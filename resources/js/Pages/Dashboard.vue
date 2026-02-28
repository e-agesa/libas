<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({
    stats: {
        type: Object,
        default: () => ({
            todayOrders: 0,
            pendingInvoices: 0,
            activeClients: 0,
            monthRevenue: 0,
        }),
    },
    recentOrders: {
        type: Array,
        default: () => [],
    },
});

const statCards = [
    { label: "Today's Orders", key: 'todayOrders', icon: 'pi pi-shopping-bag', color: 'bg-blue-500' },
    { label: 'Pending Invoices', key: 'pendingInvoices', icon: 'pi pi-file-edit', color: 'bg-amber-500' },
    { label: 'Active Clients', key: 'activeClients', icon: 'pi pi-users', color: 'bg-green-500' },
    { label: 'Revenue (MTD)', key: 'monthRevenue', icon: 'pi pi-wallet', color: 'bg-purple-500', isCurrency: true },
];

const statusColors = {
    draft: 'bg-gray-100 text-gray-700',
    issued: 'bg-blue-100 text-blue-700',
    paid: 'bg-green-100 text-green-700',
    overdue: 'bg-red-100 text-red-700',
    voided: 'bg-gray-100 text-gray-500',
};

function formatCurrency(value) {
    return 'KES ' + Number(value).toLocaleString('en-KE', { minimumFractionDigits: 0 });
}
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Dashboard</h2>
                <div class="flex gap-2">
                    <Link :href="route('clients.index')" class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 transition-colors">
                        <i class="pi pi-plus text-xs"></i> New Client
                    </Link>
                    <Link :href="route('invoices.create')" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition-colors">
                        <i class="pi pi-plus text-xs"></i> New Invoice
                    </Link>
                </div>
            </div>
        </template>

        <!-- Stat Cards -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
            <div v-for="card in statCards" :key="card.key" class="rounded-xl bg-white p-6 shadow-sm border border-gray-100">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">{{ card.label }}</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900">
                            {{ card.isCurrency ? formatCurrency(stats[card.key]) : stats[card.key] }}
                        </p>
                    </div>
                    <div :class="[card.color, 'flex h-12 w-12 items-center justify-center rounded-lg text-white']">
                        <i :class="[card.icon, 'text-xl']"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Orders & Quick Actions -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Recent Orders -->
            <div class="lg:col-span-2 rounded-xl bg-white shadow-sm border border-gray-100">
                <div class="border-b border-gray-100 px-6 py-4 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900">Recent Invoices</h3>
                    <Link :href="route('invoices.index')" class="text-sm text-green-600 hover:text-green-700 font-medium">View All</Link>
                </div>
                <div class="p-6">
                    <div v-if="recentOrders.length === 0" class="text-center py-12 text-gray-400">
                        <i class="pi pi-inbox text-5xl mb-4 block"></i>
                        <p class="text-lg font-medium">No invoices yet</p>
                        <p class="text-sm mt-1">Create your first invoice to get started.</p>
                        <Link :href="route('invoices.create')" class="mt-3 inline-flex items-center gap-1 text-sm text-green-600 hover:text-green-700 font-medium">
                            <i class="pi pi-plus text-xs"></i> Create Invoice
                        </Link>
                    </div>
                    <table v-else class="w-full">
                        <thead>
                            <tr class="text-left text-xs font-medium uppercase text-gray-500">
                                <th class="pb-3">Invoice</th>
                                <th class="pb-3">Client</th>
                                <th class="pb-3">Status</th>
                                <th class="pb-3 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="order in recentOrders" :key="order.id" class="hover:bg-gray-50 cursor-pointer" @click="router.visit(route('invoices.show', order.id))">
                                <td class="py-3 text-sm font-medium text-gray-900">{{ order.invoice_number }}</td>
                                <td class="py-3 text-sm text-gray-600">{{ order.client }}</td>
                                <td class="py-3">
                                    <span :class="statusColors[order.status] || 'bg-gray-100 text-gray-700'" class="rounded-full px-2.5 py-0.5 text-xs font-medium capitalize">
                                        {{ order.status }}
                                    </span>
                                </td>
                                <td class="py-3 text-sm text-right font-medium text-gray-900">{{ formatCurrency(order.total) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="rounded-xl bg-white shadow-sm border border-gray-100">
                <div class="border-b border-gray-100 px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-900">Quick Actions</h3>
                </div>
                <div class="p-6 space-y-3">
                    <Link :href="route('clients.index')" class="flex w-full items-center gap-3 rounded-lg border border-gray-200 px-4 py-3 text-left hover:bg-gray-50 transition-colors">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-green-100 text-green-600">
                            <i class="pi pi-user-plus"></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900">Add New Client</p>
                            <p class="text-xs text-gray-500">Register a new billing client</p>
                        </div>
                    </Link>
                    <Link :href="route('invoices.create')" class="flex w-full items-center gap-3 rounded-lg border border-gray-200 px-4 py-3 text-left hover:bg-gray-50 transition-colors">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-100 text-blue-600">
                            <i class="pi pi-file-plus"></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900">Create Invoice</p>
                            <p class="text-xs text-gray-500">Start new invoice wizard</p>
                        </div>
                    </Link>
                    <Link :href="route('invoices.create', { type: 'quotation' })" class="flex w-full items-center gap-3 rounded-lg border border-gray-200 px-4 py-3 text-left hover:bg-gray-50 transition-colors">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-cyan-100 text-cyan-600">
                            <i class="pi pi-file"></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900">Create Quotation</p>
                            <p class="text-xs text-gray-500">Send a price estimate first</p>
                        </div>
                    </Link>
                    <Link :href="route('clients.index')" class="flex w-full items-center gap-3 rounded-lg border border-gray-200 px-4 py-3 text-left hover:bg-gray-50 transition-colors">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-purple-100 text-purple-600">
                            <i class="pi pi-sliders-h"></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900">Take Measurement</p>
                            <p class="text-xs text-gray-500">Select a client's contact first</p>
                        </div>
                    </Link>
                    <Link :href="route('fabrics.index')" class="flex w-full items-center gap-3 rounded-lg border border-gray-200 px-4 py-3 text-left hover:bg-gray-50 transition-colors">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-100 text-amber-600">
                            <i class="pi pi-palette"></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-900">Manage Fabrics</p>
                            <p class="text-xs text-gray-500">View stock and add materials</p>
                        </div>
                    </Link>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
