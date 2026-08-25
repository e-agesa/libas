<script setup>
import VariantManager from '@/Components/Collections/VariantManager.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import CollectionForm from '@/Components/Collections/CollectionForm.vue';
import CategoryForm from '@/Components/Collections/CategoryForm.vue';

const props = defineProps({
    collections: Object,
    categories: Array,
    filters: Object,
});

const search = ref(props.filters?.search || '');
const categoryFilter = ref(props.filters?.category || '');
const statusFilter = ref(props.filters?.status || '');
const showModal = ref(false);
const editingItem = ref(null);
const showCategoryModal = ref(false);
const editingCategory = ref(null);
const showStockModal = ref(false);
const stockItem = ref(null);
const stockAdjustment = ref(0);
const stockReason = ref('');

let searchTimeout = null;
watch(search, (value) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        applyFilters();
    }, 300);
});

watch([categoryFilter, statusFilter], () => applyFilters());

function applyFilters() {
    router.get(route('collections.index'), {
        search: search.value, category: categoryFilter.value, status: statusFilter.value,
    }, { preserveState: true, replace: true });
}

function openNew() {
    editingItem.value = null;
    showModal.value = true;
}

function openEdit(item) {
    editingItem.value = item;
    showModal.value = true;
}

function closeModal() {
    showModal.value = false;
    editingItem.value = null;
}

function deleteItem(item) {
    if (confirm(`Delete "${item.name}"?`)) {
        router.delete(route('collections.destroy', item.id));
    }
}

const showVariants = ref(false);
const variantTarget = ref(null);

function openVariants(item) {
    variantTarget.value = item;
    showVariants.value = true;
}

function onVariantsSaved() {
    // stock totals live on the product row, so refresh the figures in place
    router.reload({ only: ['collections', 'stats'], preserveScroll: true });
}

function openStockAdjust(item) {
    stockItem.value = item;
    stockAdjustment.value = 0;
    stockReason.value = '';
    showStockModal.value = true;
}

function submitStockAdjust() {
    router.post(route('collections.adjust-stock', stockItem.value.id), {
        adjustment: stockAdjustment.value,
        reason: stockReason.value,
    }, {
        preserveScroll: true,
        onSuccess: () => { showStockModal.value = false; },
    });
}

function stockBadge(item) {
    const qty = Number(item.stock_qty);
    const threshold = Number(item.low_stock_threshold);
    if (qty <= 0) return { class: 'bg-red-100 text-red-700', label: 'Out' };
    if (qty <= threshold) return { class: 'bg-amber-100 text-amber-700', label: 'Low' };
    return { class: 'bg-green-100 text-green-700', label: 'OK' };
}

function formatCurrency(amount) {
    return 'KES ' + Number(amount).toLocaleString('en-KE', { minimumFractionDigits: 0 });
}

function openEditCategory(cat) {
    editingCategory.value = cat;
    showCategoryModal.value = true;
}

function closeCategoryModal() {
    showCategoryModal.value = false;
    editingCategory.value = null;
}

function deleteCategory(cat) {
    if (confirm(`Delete category "${cat.name}"? Items will become uncategorized.`)) {
        router.delete(route('collection-categories.destroy', cat.id));
    }
}
</script>

<template>
    <Head title="Collections" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Collections</h2>
                <div class="flex gap-2">
                    <button @click="showCategoryModal = true" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                        <i class="pi pi-tag text-xs"></i> Categories
                    </button>
                    <button @click="openNew" class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 transition-colors">
                        <i class="pi pi-plus text-xs"></i> Add Item
                    </button>
                </div>
            </div>
        </template>

        <!-- Filters -->
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-1 gap-3">
                <div class="relative flex-1 max-w-md">
                    <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                    <input v-model="search" type="text" placeholder="Search by name, SKU, description..." class="w-full rounded-lg border border-gray-300 bg-white py-2 pl-9 pr-4 text-sm focus:border-brand-600 focus:ring-brand-600" />
                </div>
                <select v-model="categoryFilter" class="rounded-lg border border-gray-300 bg-white py-2 pl-3 pr-8 text-sm focus:border-brand-600 focus:ring-brand-600">
                    <option value="">All Categories</option>
                    <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                </select>
                <select v-model="statusFilter" class="rounded-lg border border-gray-300 bg-white py-2 pl-3 pr-8 text-sm focus:border-brand-600 focus:ring-brand-600">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div class="text-sm text-gray-500">{{ collections.total }} item{{ collections.total !== 1 ? 's' : '' }}</div>
        </div>

        <!-- Table -->
        <div class="rounded-xl bg-white shadow-sm border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left">
                        <tr>
                            <th class="px-6 py-3 font-medium text-gray-500 uppercase text-xs">Item</th>
                            <th class="px-6 py-3 font-medium text-gray-500 uppercase text-xs hidden md:table-cell">Category</th>
                            <th class="px-6 py-3 font-medium text-gray-500 uppercase text-xs hidden md:table-cell">SKU</th>
                            <th class="px-6 py-3 font-medium text-gray-500 uppercase text-xs hidden lg:table-cell">Size / Color</th>
                            <th class="px-6 py-3 font-medium text-gray-500 uppercase text-xs text-right">Price</th>
                            <th class="px-6 py-3 font-medium text-gray-500 uppercase text-xs text-center">Stock</th>
                            <th class="px-6 py-3 font-medium text-gray-500 uppercase text-xs text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="item in collections.data" :key="item.id" class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div>
                                    <div class="font-medium text-gray-900">{{ item.name }}</div>
                                    <p v-if="item.description" class="text-xs text-gray-500 mt-0.5 line-clamp-1">{{ item.description }}</p>
                                </div>
                            </td>
                            <td class="px-6 py-4 hidden md:table-cell">
                                <span v-if="item.category" class="inline-flex rounded-full bg-blue-50 text-blue-700 px-2 py-0.5 text-xs font-medium">{{ item.category.name }}</span>
                                <span v-else class="text-gray-400">—</span>
                            </td>
                            <td class="px-6 py-4 text-gray-600 hidden md:table-cell font-mono text-xs">{{ item.sku || '—' }}</td>
                            <td class="px-6 py-4 hidden lg:table-cell text-gray-600">
                                <span v-if="item.size || item.color">{{ [item.size, item.color].filter(Boolean).join(' / ') }}</span>
                                <span v-else class="text-gray-400">—</span>
                            </td>
                            <td class="px-6 py-4 text-right font-medium text-gray-900">{{ formatCurrency(item.price) }}</td>
                            <td class="px-6 py-4 text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    <span class="font-medium" :class="item.stock_qty <= item.low_stock_threshold ? 'text-red-600' : 'text-gray-900'">{{ item.stock_qty }}</span>
                                    <span :class="stockBadge(item).class" class="rounded-full px-1.5 py-0.5 text-xs font-medium">{{ stockBadge(item).label }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button @click="openVariants(item)" class="inline-flex items-center gap-1 rounded-md border border-brand-600 px-2 py-1 text-xs font-medium text-brand-600 hover:bg-brand-50 mr-2" title="Sizes, colours, designs and photos">
                                    <i class="pi pi-clone text-[10px]"></i> Variations
                                </button>
                                <button @click="openStockAdjust(item)" class="text-gray-400 hover:text-amber-600 mr-2" title="Adjust Stock"><i class="pi pi-box"></i></button>
                                <button @click="openEdit(item)" class="text-gray-400 hover:text-blue-600 mr-2" title="Edit"><i class="pi pi-pencil"></i></button>
                                <button @click="deleteItem(item)" class="text-gray-400 hover:text-red-600" title="Delete"><i class="pi pi-trash"></i></button>
                            </td>
                        </tr>
                        <tr v-if="collections.data.length === 0">
                            <td colspan="7" class="px-6 py-12 text-center text-gray-400">
                                <i class="pi pi-shopping-bag text-4xl mb-3 block"></i>
                                <p class="font-medium">No collection items found</p>
                                <p class="text-sm mt-1">Add your first off-the-shelf item.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <!-- Pagination -->
            <div v-if="collections.last_page > 1" class="border-t border-gray-100 px-6 py-3 flex items-center justify-between">
                <div class="text-sm text-gray-500">Showing {{ collections.from }} to {{ collections.to }} of {{ collections.total }}</div>
                <div class="flex gap-1">
                    <Link v-for="link in collections.links" :key="link.label" :href="link.url || '#'" v-html="link.label" :class="['px-3 py-1 rounded text-sm', link.active ? 'bg-brand-600 text-white' : 'text-gray-600 hover:bg-gray-100', !link.url ? 'opacity-50 cursor-not-allowed' : '']" />
                </div>
            </div>
        </div>

        <!-- Stock Adjustment Modal -->
        <teleport to="body">
            <div v-if="showStockModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showStockModal = false">
                <div class="bg-white rounded-xl shadow-xl p-6 w-full max-w-sm">
                    <h3 class="text-lg font-semibold mb-4">Adjust Stock — {{ stockItem?.name }}</h3>
                    <p class="text-sm text-gray-500 mb-3">Current: <strong>{{ stockItem?.stock_qty }}</strong></p>
                    <div class="mb-3">
                        <label class="text-xs font-medium text-gray-600 mb-1 block">Adjustment (+/-)</label>
                        <input v-model.number="stockAdjustment" type="number" class="w-full rounded-md border-gray-300 text-sm focus:border-brand-600 focus:ring-brand-600" placeholder="e.g. +5 or -2" />
                    </div>
                    <div class="mb-4">
                        <label class="text-xs font-medium text-gray-600 mb-1 block">Reason</label>
                        <input v-model="stockReason" type="text" class="w-full rounded-md border-gray-300 text-sm focus:border-brand-600 focus:ring-brand-600" placeholder="e.g. New shipment, Damaged, etc." />
                    </div>
                    <p class="text-sm mb-4">
                        New stock: <strong :class="(stockItem?.stock_qty || 0) + stockAdjustment < 0 ? 'text-red-600' : 'text-green-600'">{{ (stockItem?.stock_qty || 0) + stockAdjustment }}</strong>
                    </p>
                    <div class="flex justify-end gap-3">
                        <button @click="showStockModal = false" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                        <button @click="submitStockAdjust" :disabled="stockAdjustment === 0" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50">Adjust</button>
                    </div>
                </div>
            </div>
        </teleport>

        <CollectionForm :show="showModal" :item="editingItem" :categories="categories" @close="closeModal" />
        <CategoryForm :show="showCategoryModal" :category="editingCategory" :categories="categories" @close="closeCategoryModal" @edit="openEditCategory" @delete="deleteCategory" />
        <VariantManager

            :show="showVariants"

            :collection="variantTarget"

            @close="showVariants = false"

            @saved="onVariantsSaved"

        />

    </AuthenticatedLayout>
</template>
