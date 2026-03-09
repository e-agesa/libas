<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { useGarmentTypes } from '@/composables/useGarmentTypes';

const props = defineProps({
    filters: Object,
    summary: Object,
    collectionStats: Object,
    topCollectionItems: Array,
    monthlyRevenue: Array,
    garmentBreakdown: Array,
    topClients: Array,
    statusDistribution: Object,
    expensesByCategory: Array,
});

const from = ref(props.filters?.from || '');
const to = ref(props.filters?.to || '');

function applyFilter() {
    router.get(route('reports.index'), { from: from.value, to: to.value }, {
        preserveState: true, replace: true,
    });
}

function formatCurrency(amount) {
    return 'KES ' + Number(amount).toLocaleString('en-KE', { minimumFractionDigits: 0 });
}

// Simple bar chart using CSS
const maxRevenue = computed(() => {
    if (!props.monthlyRevenue?.length) return 1;
    return Math.max(...props.monthlyRevenue.map(m => m.revenue), 1);
});

const { getColor, getLabel } = useGarmentTypes();

const totalGarments = computed(() => {
    return props.garmentBreakdown?.reduce((sum, g) => sum + g.count, 0) || 1;
});

const statusColors = {
    draft: 'bg-gray-400',
    issued: 'bg-blue-500',
    paid: 'bg-green-500',
    overdue: 'bg-red-500',
    voided: 'bg-gray-300',
};
</script>

<template>
    <Head title="Reports" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Reports</h2>
        </template>

        <!-- Date Range Filter -->
        <div class="mb-6 rounded-xl bg-white p-4 shadow-sm border border-gray-100">
            <div class="flex flex-col sm:flex-row items-end gap-3">
                <div class="flex-1">
                    <label class="text-xs font-medium text-gray-600 mb-1 block">From</label>
                    <input v-model="from" type="date" class="w-full rounded-md border-gray-300 text-sm focus:border-green-500 focus:ring-green-500" />
                </div>
                <div class="flex-1">
                    <label class="text-xs font-medium text-gray-600 mb-1 block">To</label>
                    <input v-model="to" type="date" class="w-full rounded-md border-gray-300 text-sm focus:border-green-500 focus:ring-green-500" />
                </div>
                <button @click="applyFilter" class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 transition-colors">
                    <i class="pi pi-filter text-xs mr-1"></i> Apply
                </button>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="rounded-xl bg-white p-5 shadow-sm border border-gray-100">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-green-100 text-green-600"><i class="pi pi-wallet"></i></div>
                    <div>
                        <p class="text-2xl font-bold text-gray-900">{{ formatCurrency(summary.totalRevenue) }}</p>
                        <p class="text-xs text-gray-500">Revenue (Paid)</p>
                    </div>
                </div>
            </div>
            <div class="rounded-xl bg-white p-5 shadow-sm border border-gray-100">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-100 text-blue-600"><i class="pi pi-file-edit"></i></div>
                    <div>
                        <p class="text-2xl font-bold text-gray-900">{{ formatCurrency(summary.totalInvoiced) }}</p>
                        <p class="text-xs text-gray-500">Total Invoiced</p>
                    </div>
                </div>
            </div>
            <div class="rounded-xl bg-white p-5 shadow-sm border border-gray-100">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-red-100 text-red-600"><i class="pi pi-exclamation-circle"></i></div>
                    <div>
                        <p class="text-2xl font-bold text-gray-900">{{ formatCurrency(summary.totalOutstanding) }}</p>
                        <p class="text-xs text-gray-500">Outstanding</p>
                    </div>
                </div>
            </div>
            <div class="rounded-xl bg-white p-5 shadow-sm border border-gray-100">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-100 text-amber-600"><i class="pi pi-receipt"></i></div>
                    <div>
                        <p class="text-2xl font-bold text-gray-900">{{ summary.invoiceCount }}</p>
                        <p class="text-xs text-gray-500">Invoices</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Revenue by Source -->
        <div class="mb-6 rounded-xl bg-white p-6 shadow-sm border border-gray-100">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Revenue by Source (Paid)</h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
                <div class="rounded-lg bg-green-50 border border-green-100 p-4">
                    <div class="flex items-center gap-2 mb-1">
                        <i class="pi pi-scissors text-green-600 text-xs"></i>
                        <p class="text-xs font-medium text-green-600 uppercase tracking-wide">Custom Tailoring</p>
                    </div>
                    <p class="text-xl font-bold text-green-700">{{ formatCurrency(summary.customRevenue) }}</p>
                    <div v-if="summary.totalRevenue > 0" class="mt-2 bg-green-200 rounded-full h-2 overflow-hidden">
                        <div class="bg-green-600 h-full rounded-full" :style="{ width: (summary.customRevenue / summary.totalRevenue * 100) + '%' }"></div>
                    </div>
                    <p v-if="summary.totalRevenue > 0" class="text-xs text-green-600 mt-1">{{ Math.round(summary.customRevenue / summary.totalRevenue * 100) }}% of revenue</p>
                </div>
                <div class="rounded-lg bg-blue-50 border border-blue-100 p-4">
                    <div class="flex items-center gap-2 mb-1">
                        <i class="pi pi-shopping-bag text-blue-600 text-xs"></i>
                        <p class="text-xs font-medium text-blue-600 uppercase tracking-wide">Off-the-Shelf</p>
                    </div>
                    <p class="text-xl font-bold text-blue-700">{{ formatCurrency(summary.collectionRevenue) }}</p>
                    <div v-if="summary.totalRevenue > 0" class="mt-2 bg-blue-200 rounded-full h-2 overflow-hidden">
                        <div class="bg-blue-600 h-full rounded-full" :style="{ width: (summary.collectionRevenue / summary.totalRevenue * 100) + '%' }"></div>
                    </div>
                    <p v-if="summary.totalRevenue > 0" class="text-xs text-blue-600 mt-1">{{ Math.round(summary.collectionRevenue / summary.totalRevenue * 100) }}% of revenue</p>
                </div>
                <div class="rounded-lg bg-gray-50 border border-gray-200 p-4">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Collection Inventory</p>
                    <p class="text-xl font-bold text-gray-900">{{ collectionStats?.totalItems || 0 }} items</p>
                    <p class="text-xs text-gray-500 mt-1">Stock value: {{ formatCurrency(collectionStats?.totalStockValue || 0) }}</p>
                    <p v-if="collectionStats?.lowStockCount" class="text-xs text-red-600 mt-1"><i class="pi pi-exclamation-triangle text-xs"></i> {{ collectionStats.lowStockCount }} low stock</p>
                </div>
            </div>

            <!-- Top selling collection items -->
            <div v-if="topCollectionItems?.length" class="mt-4">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">Top Selling Shelf Items</p>
                <div class="space-y-2">
                    <div v-for="(item, i) in topCollectionItems" :key="item.collection_id" class="flex items-center gap-3">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-blue-100 text-blue-700 text-xs font-bold">{{ i + 1 }}</span>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate">{{ item.collection?.name || 'Item' }}</p>
                            <p class="text-xs text-gray-400">{{ item.total_qty }} sold</p>
                        </div>
                        <span class="text-sm font-semibold text-blue-700">{{ formatCurrency(item.total_revenue) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Profit & Loss Summary -->
        <div class="mb-6 rounded-xl bg-white p-6 shadow-sm border border-gray-100">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Profit &amp; Loss (Selected Period)</h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
                <div class="rounded-lg bg-green-50 border border-green-100 p-4">
                    <p class="text-xs font-medium text-green-600 uppercase tracking-wide">Revenue (Paid)</p>
                    <p class="mt-1 text-xl font-bold text-green-700">{{ formatCurrency(summary.totalRevenue) }}</p>
                </div>
                <div class="rounded-lg bg-red-50 border border-red-100 p-4">
                    <p class="text-xs font-medium text-red-600 uppercase tracking-wide">Total Expenses</p>
                    <p class="mt-1 text-xl font-bold text-red-700">{{ formatCurrency(summary.totalExpenses) }}</p>
                </div>
                <div class="rounded-lg p-4" :class="summary.netProfit >= 0 ? 'bg-blue-50 border border-blue-100' : 'bg-amber-50 border border-amber-100'">
                    <p class="text-xs font-medium uppercase tracking-wide" :class="summary.netProfit >= 0 ? 'text-blue-600' : 'text-amber-600'">Net Profit</p>
                    <p class="mt-1 text-xl font-bold" :class="summary.netProfit >= 0 ? 'text-blue-700' : 'text-amber-700'">{{ formatCurrency(summary.netProfit) }}</p>
                </div>
            </div>
            <!-- Expense breakdown by category -->
            <div v-if="expensesByCategory?.length" class="mt-2">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">Expenses by Category</p>
                <div class="flex flex-wrap gap-2">
                    <span v-for="cat in expensesByCategory" :key="cat.category" class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 text-amber-700 px-3 py-1 text-xs font-medium">
                        {{ cat.category }}: {{ formatCurrency(cat.total) }}
                    </span>
                </div>
            </div>
            <div v-else class="text-xs text-gray-400 mt-2">No expenses recorded in this period.</div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Revenue Chart (CSS bars) -->
            <div class="rounded-xl bg-white p-6 shadow-sm border border-gray-100">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">Monthly Revenue (Last 6 Months)</h3>
                <div v-if="monthlyRevenue?.length" class="space-y-3">
                    <div v-for="m in monthlyRevenue" :key="m.month" class="flex items-center gap-3">
                        <span class="text-xs text-gray-500 w-16 shrink-0">{{ m.month }}</span>
                        <div class="flex-1 bg-gray-100 rounded-full h-6 overflow-hidden">
                            <div
                                class="bg-green-500 h-full rounded-full flex items-center justify-end pr-2 transition-all"
                                :style="{ width: Math.max((m.revenue / maxRevenue) * 100, 2) + '%' }"
                            >
                                <span v-if="m.revenue > 0" class="text-xs text-white font-medium whitespace-nowrap">{{ formatCurrency(m.revenue) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-else class="text-center py-8 text-gray-400">
                    <i class="pi pi-chart-bar text-3xl mb-2 block"></i>
                    <p class="text-sm">No revenue data yet.</p>
                </div>
            </div>

            <!-- Garment Breakdown -->
            <div class="rounded-xl bg-white p-6 shadow-sm border border-gray-100">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">Garment Type Breakdown</h3>
                <div v-if="garmentBreakdown?.length" class="space-y-4">
                    <div v-for="g in garmentBreakdown" :key="g.garment_type" class="flex items-center gap-3">
                        <span :class="getColor(g.garment_type).bg" class="inline-flex items-center justify-center w-10 h-10 rounded-lg">
                            <span :class="getColor(g.garment_type).text" class="text-sm font-bold">{{ getLabel(g.garment_type)?.charAt(0)?.toUpperCase() }}</span>
                        </span>
                        <div class="flex-1">
                            <div class="flex justify-between mb-1">
                                <span class="text-sm font-medium text-gray-900">{{ getLabel(g.garment_type) }}</span>
                                <span class="text-sm text-gray-500">{{ g.count }} <span class="text-xs">({{ Math.round(g.count / totalGarments * 100) }}%)</span></span>
                            </div>
                            <div class="bg-gray-100 rounded-full h-2 overflow-hidden">
                                <div :class="getColor(g.garment_type).dot" class="h-full rounded-full transition-all" :style="{ width: (g.count / totalGarments * 100) + '%' }"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-else class="text-center py-8 text-gray-400">
                    <i class="pi pi-sliders-h text-3xl mb-2 block"></i>
                    <p class="text-sm">No measurements recorded yet.</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Top Clients -->
            <div class="rounded-xl bg-white p-6 shadow-sm border border-gray-100">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">Top Clients by Revenue</h3>
                <div v-if="topClients?.length" class="space-y-3">
                    <div v-for="(client, i) in topClients" :key="client.id" class="flex items-center gap-3">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-green-100 text-green-700 text-xs font-bold">{{ i + 1 }}</span>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate">{{ client.name }}</p>
                        </div>
                        <span class="text-sm font-semibold text-green-700">{{ formatCurrency(client.total_spent) }}</span>
                    </div>
                </div>
                <div v-else class="text-center py-8 text-gray-400">
                    <i class="pi pi-users text-3xl mb-2 block"></i>
                    <p class="text-sm">No paid invoices in this period.</p>
                </div>
            </div>

            <!-- Quick Stats + Invoice Status -->
            <div class="rounded-xl bg-white p-6 shadow-sm border border-gray-100">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">System Overview</h3>
                <div class="grid grid-cols-3 gap-4 mb-6">
                    <div class="text-center rounded-lg bg-gray-50 p-3">
                        <p class="text-2xl font-bold text-gray-900">{{ summary.totalClients }}</p>
                        <p class="text-xs text-gray-500">Clients</p>
                    </div>
                    <div class="text-center rounded-lg bg-gray-50 p-3">
                        <p class="text-2xl font-bold text-gray-900">{{ summary.totalContacts }}</p>
                        <p class="text-xs text-gray-500">Contacts</p>
                    </div>
                    <div class="text-center rounded-lg bg-gray-50 p-3">
                        <p class="text-2xl font-bold text-gray-900">{{ summary.totalMeasurements }}</p>
                        <p class="text-xs text-gray-500">Measurements</p>
                    </div>
                </div>

                <h4 class="text-xs font-semibold text-gray-500 uppercase mb-3">Invoice Status Distribution</h4>
                <div class="space-y-2">
                    <div v-for="(count, status) in statusDistribution" :key="status" class="flex items-center gap-3">
                        <span :class="statusColors[status]" class="w-3 h-3 rounded-full shrink-0"></span>
                        <span class="text-sm text-gray-700 capitalize flex-1">{{ status }}</span>
                        <span class="text-sm font-medium text-gray-900">{{ count }}</span>
                    </div>
                    <div v-if="!Object.keys(statusDistribution || {}).length" class="text-center py-4 text-gray-400 text-sm">No invoices yet.</div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
