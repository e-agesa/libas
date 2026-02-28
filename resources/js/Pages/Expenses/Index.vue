<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';

const props = defineProps({
    expenses: Object,
    filters: Object,
    categories: Array,
    summary: Object,
});

const flash = computed(() => usePage().props.flash || {});

const search = ref(props.filters?.search || '');
const categoryFilter = ref(props.filters?.category || '');
const fromDate = ref(props.filters?.from || '');
const toDate = ref(props.filters?.to || '');

function applyFilters() {
    router.get(route('expenses.index'), {
        search: search.value,
        category: categoryFilter.value,
        from: fromDate.value,
        to: toDate.value,
    }, { preserveState: true, replace: true });
}

let searchTimeout = null;
watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 300);
});
watch([categoryFilter, fromDate, toDate], applyFilters);

// Modal state
const showModal = ref(false);
const editing = ref(null);
const form = ref(emptyForm());

function emptyForm() {
    return {
        category: '',
        description: '',
        amount: '',
        date: new Date().toISOString().slice(0, 10),
        paid_to: '',
        payment_method: '',
        reference: '',
        notes: '',
    };
}

function openCreate() {
    editing.value = null;
    form.value = emptyForm();
    showModal.value = true;
}

function openEdit(expense) {
    editing.value = expense;
    form.value = {
        category: expense.category,
        description: expense.description,
        amount: expense.amount,
        date: expense.date,
        paid_to: expense.paid_to || '',
        payment_method: expense.payment_method || '',
        reference: expense.reference || '',
        notes: expense.notes || '',
    };
    showModal.value = true;
}

function closeModal() {
    showModal.value = false;
    editing.value = null;
}

function submitForm() {
    if (editing.value) {
        router.put(route('expenses.update', editing.value.id), form.value, {
            preserveScroll: true,
            onSuccess: closeModal,
        });
    } else {
        router.post(route('expenses.store'), form.value, {
            preserveScroll: true,
            onSuccess: closeModal,
        });
    }
}

function deleteExpense(expense) {
    if (confirm(`Delete expense "${expense.description}"?`)) {
        router.delete(route('expenses.destroy', expense.id), { preserveScroll: true });
    }
}

function fmt(n) {
    return 'KES ' + Number(n || 0).toLocaleString('en-KE', { minimumFractionDigits: 0 });
}

const paymentMethods = ['Cash', 'M-Pesa', 'Bank Transfer', 'Cheque', 'Card', 'Other'];
</script>

<template>
    <Head title="Expenses" />
    <AuthenticatedLayout>
        <template #breadcrumb>
            <span class="text-sm font-medium text-gray-900">Expenses</span>
        </template>

        <!-- Flash message -->
        <div v-if="flash.success" class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">
            {{ flash.success }}
        </div>

        <!-- Header -->
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Expenses</h1>
                <p class="text-sm text-gray-500 mt-0.5">Track all business expenditures</p>
            </div>
            <button @click="openCreate" class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-green-700 shadow-sm">
                <i class="pi pi-plus text-sm"></i>
                Record Expense
            </button>
        </div>

        <!-- Summary Cards -->
        <div class="mb-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="rounded-xl bg-white border border-gray-100 shadow-sm p-5">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Total Expenses</p>
                <p class="mt-1 text-2xl font-bold text-red-600">{{ fmt(summary.total) }}</p>
            </div>
            <div class="rounded-xl bg-white border border-gray-100 shadow-sm p-5">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Records</p>
                <p class="mt-1 text-2xl font-bold text-gray-900">{{ summary.count }}</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="mb-4 flex flex-wrap gap-3">
            <div class="relative">
                <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search expenses..."
                    class="pl-8 pr-4 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 w-56"
                />
            </div>
            <select v-model="categoryFilter" class="py-2 pl-3 pr-8 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                <option value="">All Categories</option>
                <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
            </select>
            <input v-model="fromDate" type="date" class="py-2 px-3 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500" placeholder="From" />
            <input v-model="toDate" type="date" class="py-2 px-3 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500" placeholder="To" />
        </div>

        <!-- Table -->
        <div class="rounded-xl bg-white border border-gray-100 shadow-sm overflow-hidden">
            <div v-if="expenses.data.length === 0" class="px-6 py-16 text-center">
                <i class="pi pi-wallet text-4xl text-gray-300 mb-3"></i>
                <p class="text-sm text-gray-400">No expenses found</p>
                <button @click="openCreate" class="mt-3 text-sm text-green-600 hover:underline">Record your first expense</button>
            </div>

            <table v-else class="min-w-full divide-y divide-gray-100">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Date</th>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Category</th>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Description</th>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Paid To</th>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Method</th>
                        <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wide">Amount</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="expense in expenses.data" :key="expense.id" class="hover:bg-gray-50 transition-colors">
                        <td class="px-5 py-3 text-sm text-gray-600 whitespace-nowrap">{{ expense.date }}</td>
                        <td class="px-5 py-3">
                            <span class="inline-flex rounded-full bg-amber-100 text-amber-700 px-2.5 py-0.5 text-xs font-medium">{{ expense.category }}</span>
                        </td>
                        <td class="px-5 py-3 text-sm text-gray-900">{{ expense.description }}</td>
                        <td class="px-5 py-3 text-sm text-gray-500">{{ expense.paid_to || '—' }}</td>
                        <td class="px-5 py-3 text-sm text-gray-500 capitalize">{{ expense.payment_method || '—' }}</td>
                        <td class="px-5 py-3 text-sm font-semibold text-right text-red-600">{{ fmt(expense.amount) }}</td>
                        <td class="px-5 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <button @click="openEdit(expense)" class="text-gray-400 hover:text-green-600 transition-colors">
                                    <i class="pi pi-pencil text-xs"></i>
                                </button>
                                <button @click="deleteExpense(expense)" class="text-gray-400 hover:text-red-500 transition-colors">
                                    <i class="pi pi-trash text-xs"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Pagination -->
            <div v-if="expenses.last_page > 1" class="flex items-center justify-between border-t border-gray-100 px-5 py-3">
                <p class="text-xs text-gray-500">
                    Showing {{ expenses.from }}–{{ expenses.to }} of {{ expenses.total }} expenses
                </p>
                <div class="flex gap-1">
                    <template v-for="link in expenses.links" :key="link.label">
                        <component
                            :is="link.url ? 'a' : 'span'"
                            :href="link.url || undefined"
                            v-html="link.label"
                            @click.prevent="link.url && router.get(link.url, {}, { preserveState: true })"
                            class="px-2.5 py-1 rounded text-xs border cursor-pointer"
                            :class="link.active ? 'bg-green-600 text-white border-green-600' : 'border-gray-200 text-gray-600 hover:bg-gray-50'"
                        />
                    </template>
                </div>
            </div>
        </div>

        <!-- Add/Edit Modal -->
        <Teleport to="body">
            <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
                <div class="w-full max-w-lg rounded-2xl bg-white shadow-2xl">
                    <div class="flex items-center justify-between p-6 border-b border-gray-100">
                        <h2 class="text-lg font-bold text-gray-900">{{ editing ? 'Edit Expense' : 'Record Expense' }}</h2>
                        <button @click="closeModal" class="text-gray-400 hover:text-gray-600">
                            <i class="pi pi-times text-sm"></i>
                        </button>
                    </div>
                    <form @submit.prevent="submitForm" class="p-6 space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Category *</label>
                                <select v-model="form.category" required class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                                    <option value="">Select category</option>
                                    <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Date *</label>
                                <input v-model="form.date" type="date" required class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500" />
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Description *</label>
                            <input v-model="form.description" type="text" required maxlength="500" placeholder="What was this expense for?" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Amount (KES) *</label>
                            <input v-model="form.amount" type="number" required min="0.01" step="0.01" placeholder="0.00" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500" />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Paid To</label>
                                <input v-model="form.paid_to" type="text" maxlength="255" placeholder="Vendor / person name" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Payment Method</label>
                                <select v-model="form.payment_method" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                                    <option value="">Select method</option>
                                    <option v-for="m in paymentMethods" :key="m" :value="m">{{ m }}</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Reference / Receipt #</label>
                            <input v-model="form.reference" type="text" maxlength="255" placeholder="Optional reference number" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Notes</label>
                            <textarea v-model="form.notes" rows="2" maxlength="2000" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500 resize-none"></textarea>
                        </div>
                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" @click="closeModal" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                Cancel
                            </button>
                            <button type="submit" class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">
                                {{ editing ? 'Update' : 'Record' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>
