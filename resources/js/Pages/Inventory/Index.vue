<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    fabrics: Array,
    collections: Array,
    movements: Array,
    stats: Object,
    filters: Object,
});

const activeTab = ref(props.filters?.tab || 'fabrics');
const searchTerm = ref(props.filters?.search || '');
const showAdjustModal = ref(false);
const showReserveModal = ref(false);
const showMovementsModal = ref(false);
const selectedItem = ref(null);
const itemMovements = ref([]);
const loadingMovements = ref(false);

const adjustForm = useForm({
    item_type: 'fabric',
    item_id: null,
    type: 'purchase',
    quantity: 0,
    unit_cost: '',
    notes: '',
});

const reserveForm = useForm({
    fabric_id: null,
    quantity: 1,
    notes: '',
});

function formatCurrency(v) {
    return 'KES ' + Number(v).toLocaleString('en-KE', { minimumFractionDigits: 0 });
}

function formatDate(d) {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('en-KE', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function search() {
    router.get(route('inventory.index'), { search: searchTerm.value, tab: activeTab.value }, { preserveState: true });
}

function openAdjust(item, type) {
    adjustForm.item_type = type;
    adjustForm.item_id = item.id;
    adjustForm.type = 'purchase';
    adjustForm.quantity = 0;
    adjustForm.unit_cost = type === 'fabric' ? item.price_per_unit : item.price;
    adjustForm.notes = '';
    selectedItem.value = item;
    showAdjustModal.value = true;
}

function submitAdjust() {
    adjustForm.post(route('inventory.adjust'), {
        onSuccess: () => {
            showAdjustModal.value = false;
            adjustForm.reset();
        },
        preserveScroll: true,
    });
}

function openReserve(fabric) {
    reserveForm.fabric_id = fabric.id;
    reserveForm.quantity = 1;
    reserveForm.notes = '';
    selectedItem.value = fabric;
    showReserveModal.value = true;
}

function submitReserve() {
    reserveForm.post(route('inventory.reserve'), {
        onSuccess: () => {
            showReserveModal.value = false;
            reserveForm.reset();
        },
        preserveScroll: true,
    });
}

function releaseFabric(fabric) {
    if (!confirm(`Release all ${fabric.reserved_qty} reserved units of ${fabric.name}?`)) return;
    router.post(route('inventory.release'), {
        fabric_id: fabric.id,
        quantity: fabric.reserved_qty,
        notes: 'Manual release',
    }, { preserveScroll: true });
}

async function viewMovements(item, type) {
    selectedItem.value = item;
    showMovementsModal.value = true;
    loadingMovements.value = true;
    itemMovements.value = [];
    try {
        const resp = await fetch(route('inventory.movements', { type, id: item.id }));
        itemMovements.value = await resp.json();
    } catch (e) { console.error(e); }
    loadingMovements.value = false;
}

const typeColors = {
    purchase: 'bg-green-100 text-green-700',
    sale: 'bg-red-100 text-red-700',
    reservation: 'bg-amber-100 text-amber-700',
    release: 'bg-blue-100 text-blue-700',
    adjustment: 'bg-gray-100 text-gray-700',
    return: 'bg-teal-100 text-teal-700',
    waste: 'bg-orange-100 text-orange-700',
};

const typeIcons = {
    purchase: 'pi-plus-circle',
    sale: 'pi-minus-circle',
    reservation: 'pi-lock',
    release: 'pi-lock-open',
    adjustment: 'pi-sliders-h',
    return: 'pi-replay',
    waste: 'pi-trash',
};

// Filtered items
const filteredFabrics = computed(() => {
    if (!searchTerm.value) return props.fabrics;
    const q = searchTerm.value.toLowerCase();
    return props.fabrics.filter(f =>
        f.name.toLowerCase().includes(q) ||
        f.type?.toLowerCase().includes(q) ||
        f.supplier?.toLowerCase().includes(q)
    );
});

const filteredCollections = computed(() => {
    if (!searchTerm.value) return props.collections;
    const q = searchTerm.value.toLowerCase();
    return props.collections.filter(c =>
        c.name.toLowerCase().includes(q) ||
        c.sku?.toLowerCase().includes(q)
    );
});
</script>

<template>
    <Head title="Inventory" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    <i class="pi pi-box text-green-600 mr-2"></i> Inventory
                </h2>
            </div>
        </template>

        <!-- Stats cards -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
            <div class="rounded-xl bg-white p-4 shadow-sm border border-gray-100">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-green-100 text-green-600"><i class="pi pi-wallet text-lg"></i></div>
                    <div>
                        <p class="text-xl font-bold text-gray-900">{{ formatCurrency(stats.totalInventoryValue) }}</p>
                        <p class="text-xs text-gray-500">Total Inventory Value</p>
                    </div>
                </div>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm border border-gray-100">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-100 text-blue-600"><i class="pi pi-palette text-lg"></i></div>
                    <div>
                        <p class="text-xl font-bold text-gray-900">{{ stats.fabricCount }}</p>
                        <p class="text-xs text-gray-500">Fabrics ({{ formatCurrency(stats.totalFabricValue) }})</p>
                    </div>
                </div>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm border border-gray-100">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-purple-100 text-purple-600"><i class="pi pi-shopping-bag text-lg"></i></div>
                    <div>
                        <p class="text-xl font-bold text-gray-900">{{ stats.collectionCount }}</p>
                        <p class="text-xs text-gray-500">Collections ({{ formatCurrency(stats.totalCollectionValue) }})</p>
                    </div>
                </div>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm border border-gray-100">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg" :class="(stats.lowStockFabrics + stats.lowStockCollections) > 0 ? 'bg-red-100 text-red-600' : 'bg-gray-100 text-gray-600'">
                        <i class="pi pi-exclamation-triangle text-lg"></i>
                    </div>
                    <div>
                        <p class="text-xl font-bold" :class="(stats.lowStockFabrics + stats.lowStockCollections) > 0 ? 'text-red-600' : 'text-gray-900'">{{ stats.lowStockFabrics + stats.lowStockCollections }}</p>
                        <p class="text-xs text-gray-500">Low Stock Alerts ({{ stats.totalReserved }} reserved)</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search + Tabs -->
        <div class="rounded-xl bg-white shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-4 sm:px-6 py-3 bg-gray-50 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center gap-3">
                <div class="flex gap-1">
                    <button v-for="tab in [{k:'fabrics',l:'Fabrics',i:'pi-palette'},{k:'collections',l:'Collections',i:'pi-shopping-bag'},{k:'movements',l:'Movement Log',i:'pi-history'}]"
                        :key="tab.k" @click="activeTab = tab.k"
                        :class="['rounded-lg px-3 py-1.5 text-xs font-medium transition-colors flex items-center gap-1',
                            activeTab === tab.k ? 'bg-green-600 text-white' : 'text-gray-600 hover:bg-gray-100']"
                    >
                        <i :class="['pi text-[10px]', tab.i]"></i> {{ tab.l }}
                    </button>
                </div>
                <div class="flex-1 relative sm:max-w-xs ml-auto">
                    <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                    <input v-model="searchTerm" @keyup.enter="search" type="text" placeholder="Search inventory..."
                        class="w-full rounded-lg border-gray-300 py-2 pl-8 pr-3 text-sm focus:border-green-500 focus:ring-green-500" />
                </div>
            </div>

            <!-- Fabrics Tab -->
            <div v-if="activeTab === 'fabrics'">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50/50">
                            <tr>
                                <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-500">Fabric</th>
                                <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-500 hidden sm:table-cell">Type</th>
                                <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-500 hidden md:table-cell">Supplier</th>
                                <th class="px-4 py-2.5 text-right text-xs font-medium text-gray-500">Price/Unit</th>
                                <th class="px-4 py-2.5 text-center text-xs font-medium text-gray-500">Stock</th>
                                <th class="px-4 py-2.5 text-center text-xs font-medium text-gray-500">Reserved</th>
                                <th class="px-4 py-2.5 text-center text-xs font-medium text-gray-500">Available</th>
                                <th class="px-4 py-2.5 text-right text-xs font-medium text-gray-500 hidden sm:table-cell">Value</th>
                                <th class="px-4 py-2.5 text-center text-xs font-medium text-gray-500">Used</th>
                                <th class="px-4 py-2.5 text-right text-xs font-medium text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="f in filteredFabrics" :key="f.id" :class="{ 'bg-red-50': f.is_low }">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <div class="w-6 h-6 rounded-full border border-gray-200 shrink-0" :style="{ backgroundColor: f.color || '#ccc' }"></div>
                                        <div>
                                            <p class="font-medium text-gray-900">{{ f.name }}</p>
                                            <span v-if="f.is_low" class="text-[10px] text-red-500 font-medium"><i class="pi pi-exclamation-triangle text-[8px]"></i> Low Stock</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-gray-500 hidden sm:table-cell">{{ f.type || '—' }}</td>
                                <td class="px-4 py-3 text-gray-500 hidden md:table-cell">{{ f.supplier || '—' }}</td>
                                <td class="px-4 py-3 text-right text-gray-900">{{ formatCurrency(f.price_per_unit) }}</td>
                                <td class="px-4 py-3 text-center font-medium" :class="f.stock_qty <= 0 ? 'text-red-600' : 'text-gray-900'">{{ f.stock_qty }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span v-if="f.reserved_qty > 0" class="inline-flex items-center gap-0.5 rounded-full bg-amber-100 text-amber-700 px-2 py-0.5 text-xs font-medium">
                                        <i class="pi pi-lock text-[8px]"></i> {{ f.reserved_qty }}
                                    </span>
                                    <span v-else class="text-gray-300">0</span>
                                </td>
                                <td class="px-4 py-3 text-center font-bold" :class="f.available_qty <= 0 ? 'text-red-600' : 'text-green-700'">{{ f.available_qty }}</td>
                                <td class="px-4 py-3 text-right text-gray-900 hidden sm:table-cell">{{ formatCurrency(f.stock_value) }}</td>
                                <td class="px-4 py-3 text-center text-gray-500">{{ f.invoice_line_items_count }}</td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <button @click="openAdjust(f, 'fabric')" class="w-7 h-7 rounded-lg bg-green-50 text-green-600 hover:bg-green-100 flex items-center justify-center" title="Adjust Stock">
                                            <i class="pi pi-plus text-xs"></i>
                                        </button>
                                        <button @click="openReserve(f)" class="w-7 h-7 rounded-lg bg-amber-50 text-amber-600 hover:bg-amber-100 flex items-center justify-center" title="Reserve">
                                            <i class="pi pi-lock text-xs"></i>
                                        </button>
                                        <button v-if="f.reserved_qty > 0" @click="releaseFabric(f)" class="w-7 h-7 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 flex items-center justify-center" title="Release Reserved">
                                            <i class="pi pi-lock-open text-xs"></i>
                                        </button>
                                        <button @click="viewMovements(f, 'fabric')" class="w-7 h-7 rounded-lg bg-gray-50 text-gray-600 hover:bg-gray-100 flex items-center justify-center" title="View History">
                                            <i class="pi pi-history text-xs"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="filteredFabrics.length === 0" class="text-center py-12 text-gray-400">
                    <i class="pi pi-palette text-4xl mb-2 block"></i>
                    <p class="text-sm">No fabrics found.</p>
                </div>
            </div>

            <!-- Collections Tab -->
            <div v-if="activeTab === 'collections'">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50/50">
                            <tr>
                                <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-500">Item</th>
                                <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-500 hidden sm:table-cell">Category</th>
                                <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-500 hidden md:table-cell">SKU</th>
                                <th class="px-4 py-2.5 text-right text-xs font-medium text-gray-500">Price</th>
                                <th class="px-4 py-2.5 text-center text-xs font-medium text-gray-500">Stock</th>
                                <th class="px-4 py-2.5 text-right text-xs font-medium text-gray-500 hidden sm:table-cell">Value</th>
                                <th class="px-4 py-2.5 text-center text-xs font-medium text-gray-500">Sold</th>
                                <th class="px-4 py-2.5 text-center text-xs font-medium text-gray-500">Status</th>
                                <th class="px-4 py-2.5 text-right text-xs font-medium text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="c in filteredCollections" :key="c.id" :class="{ 'bg-red-50': c.is_low }">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <div class="w-8 h-8 rounded-lg bg-gray-100 overflow-hidden flex-none">
                                            <img v-if="c.image_url" :src="c.image_url" class="w-full h-full object-cover" />
                                            <div v-else class="w-full h-full flex items-center justify-center"><i class="pi pi-image text-gray-300 text-xs"></i></div>
                                        </div>
                                        <div>
                                            <p class="font-medium text-gray-900">{{ c.name }}</p>
                                            <div class="flex items-center gap-1">
                                                <span v-if="c.size" class="text-[10px] text-gray-400">{{ c.size }}</span>
                                                <span v-if="c.color" class="text-[10px] text-gray-400">· {{ c.color }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-gray-500 hidden sm:table-cell">{{ c.category?.name || '—' }}</td>
                                <td class="px-4 py-3 text-gray-500 hidden md:table-cell text-xs font-mono">{{ c.sku || '—' }}</td>
                                <td class="px-4 py-3 text-right text-gray-900">{{ formatCurrency(c.price) }}</td>
                                <td class="px-4 py-3 text-center font-medium" :class="c.stock_qty <= 0 ? 'text-red-600' : 'text-gray-900'">
                                    {{ c.stock_qty }}
                                    <span v-if="c.is_low" class="block text-[10px] text-red-500"><i class="pi pi-exclamation-triangle text-[8px]"></i> Low</span>
                                </td>
                                <td class="px-4 py-3 text-right text-gray-900 hidden sm:table-cell">{{ formatCurrency(c.stock_value) }}</td>
                                <td class="px-4 py-3 text-center text-gray-500">{{ c.invoice_line_items_count }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span :class="['rounded-full px-2 py-0.5 text-xs font-medium', c.status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500']">
                                        {{ c.status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <button @click="openAdjust(c, 'collection')" class="w-7 h-7 rounded-lg bg-green-50 text-green-600 hover:bg-green-100 flex items-center justify-center" title="Adjust Stock">
                                            <i class="pi pi-plus text-xs"></i>
                                        </button>
                                        <button @click="viewMovements(c, 'collection')" class="w-7 h-7 rounded-lg bg-gray-50 text-gray-600 hover:bg-gray-100 flex items-center justify-center" title="View History">
                                            <i class="pi pi-history text-xs"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="filteredCollections.length === 0" class="text-center py-12 text-gray-400">
                    <i class="pi pi-shopping-bag text-4xl mb-2 block"></i>
                    <p class="text-sm">No collection items found.</p>
                </div>
            </div>

            <!-- Movement Log Tab -->
            <div v-if="activeTab === 'movements'">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50/50">
                            <tr>
                                <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-500">Date</th>
                                <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-500">Type</th>
                                <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-500">Item</th>
                                <th class="px-4 py-2.5 text-center text-xs font-medium text-gray-500">Qty</th>
                                <th class="px-4 py-2.5 text-center text-xs font-medium text-gray-500">Balance</th>
                                <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-500 hidden sm:table-cell">Reference</th>
                                <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-500 hidden md:table-cell">By</th>
                                <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-500 hidden lg:table-cell">Notes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="m in movements" :key="m.id">
                                <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">{{ formatDate(m.created_at) }}</td>
                                <td class="px-4 py-3">
                                    <span :class="['inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-medium', typeColors[m.type] || 'bg-gray-100 text-gray-700']">
                                        <i :class="['pi', typeIcons[m.type] || 'pi-circle']" style="font-size: 8px;"></i>
                                        {{ m.type }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="font-medium text-gray-900">{{ m.item_name }}</span>
                                    <span class="text-[10px] ml-1" :class="m.item_type_label === 'Fabric' ? 'text-blue-500' : 'text-purple-500'">{{ m.item_type_label }}</span>
                                </td>
                                <td class="px-4 py-3 text-center font-bold" :class="m.quantity > 0 ? 'text-green-600' : 'text-red-600'">
                                    {{ m.quantity > 0 ? '+' : '' }}{{ m.quantity }}
                                </td>
                                <td class="px-4 py-3 text-center text-gray-700 font-medium">{{ m.balance_after }}</td>
                                <td class="px-4 py-3 text-gray-500 text-xs hidden sm:table-cell">{{ m.invoice?.invoice_number || m.reference || '—' }}</td>
                                <td class="px-4 py-3 text-gray-500 text-xs hidden md:table-cell">{{ m.user?.name || '—' }}</td>
                                <td class="px-4 py-3 text-gray-400 text-xs hidden lg:table-cell truncate max-w-[200px]">{{ m.notes || '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="movements.length === 0" class="text-center py-12 text-gray-400">
                    <i class="pi pi-history text-4xl mb-2 block"></i>
                    <p class="text-sm">No stock movements recorded yet.</p>
                    <p class="text-xs mt-1">Movements will appear here as stock changes occur.</p>
                </div>
            </div>
        </div>

        <!-- Stock Adjustment Modal -->
        <Modal :show="showAdjustModal" @close="showAdjustModal = false" max-width="md">
            <form @submit.prevent="submitAdjust" class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-1">Adjust Stock</h3>
                <p class="text-sm text-gray-500 mb-4">{{ selectedItem?.name }}</p>
                <div class="space-y-4">
                    <div>
                        <label class="text-sm font-medium text-gray-700 mb-1 block">Movement Type</label>
                        <div class="grid grid-cols-2 gap-2">
                            <button v-for="t in [{v:'purchase',l:'Purchase In',c:'green'},{v:'adjustment',l:'Adjustment',c:'gray'},{v:'return',l:'Return',c:'teal'},{v:'waste',l:'Waste/Loss',c:'orange'}]"
                                :key="t.v" type="button" @click="adjustForm.type = t.v"
                                :class="['rounded-lg border px-3 py-2 text-xs font-medium transition-colors',
                                    adjustForm.type === t.v ? `border-${t.c}-500 bg-${t.c}-50 text-${t.c}-700` : 'border-gray-200 text-gray-600 hover:bg-gray-50']"
                            >
                                {{ t.l }}
                            </button>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-sm font-medium text-gray-700 mb-1 block">Quantity</label>
                            <input v-model="adjustForm.quantity" type="number" min="1" required class="w-full rounded-md border-gray-300 text-sm focus:border-green-500 focus:ring-green-500" />
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-700 mb-1 block">Unit Cost (KES)</label>
                            <input v-model="adjustForm.unit_cost" type="number" min="0" step="0.01" class="w-full rounded-md border-gray-300 text-sm focus:border-green-500 focus:ring-green-500" />
                        </div>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700 mb-1 block">Notes</label>
                        <textarea v-model="adjustForm.notes" rows="2" class="w-full rounded-md border-gray-300 text-sm focus:border-green-500 focus:ring-green-500" placeholder="Reason for adjustment..."></textarea>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" @click="showAdjustModal = false" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="submit" :disabled="adjustForm.processing" class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50">
                        {{ adjustForm.processing ? 'Saving...' : 'Apply Adjustment' }}
                    </button>
                </div>
            </form>
        </Modal>

        <!-- Reserve Modal -->
        <Modal :show="showReserveModal" @close="showReserveModal = false" max-width="sm">
            <form @submit.prevent="submitReserve" class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-1">Reserve Material</h3>
                <p class="text-sm text-gray-500 mb-4">
                    {{ selectedItem?.name }}
                    <span class="text-green-600 font-medium">({{ selectedItem?.available_qty }} available)</span>
                </p>
                <div class="space-y-4">
                    <div>
                        <label class="text-sm font-medium text-gray-700 mb-1 block">Quantity to Reserve</label>
                        <input v-model="reserveForm.quantity" type="number" min="1" :max="selectedItem?.available_qty" required class="w-full rounded-md border-gray-300 text-sm focus:border-green-500 focus:ring-green-500" />
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-700 mb-1 block">Notes</label>
                        <input v-model="reserveForm.notes" type="text" class="w-full rounded-md border-gray-300 text-sm focus:border-green-500 focus:ring-green-500" placeholder="e.g., Reserved for order INV-0042" />
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" @click="showReserveModal = false" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="submit" :disabled="reserveForm.processing" class="rounded-lg bg-amber-500 px-4 py-2 text-sm font-medium text-white hover:bg-amber-600 disabled:opacity-50">
                        {{ reserveForm.processing ? 'Reserving...' : 'Reserve' }}
                    </button>
                </div>
            </form>
        </Modal>

        <!-- Movement History Modal -->
        <Modal :show="showMovementsModal" @close="showMovementsModal = false" max-width="2xl">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-1">Stock Movement History</h3>
                <p class="text-sm text-gray-500 mb-4">{{ selectedItem?.name }}</p>

                <div v-if="loadingMovements" class="text-center py-8 text-gray-400">
                    <i class="pi pi-spin pi-spinner text-2xl block mb-2"></i>
                    Loading...
                </div>

                <div v-else-if="itemMovements.length === 0" class="text-center py-8 text-gray-400">
                    <i class="pi pi-history text-3xl block mb-2"></i>
                    <p class="text-sm">No movements recorded yet.</p>
                </div>

                <div v-else class="max-h-[60vh] overflow-y-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 sticky top-0">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Date</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Type</th>
                                <th class="px-3 py-2 text-center text-xs font-medium text-gray-500">Qty</th>
                                <th class="px-3 py-2 text-center text-xs font-medium text-gray-500">Balance</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Ref</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Notes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="m in itemMovements" :key="m.id">
                                <td class="px-3 py-2 text-xs text-gray-500 whitespace-nowrap">{{ formatDate(m.created_at) }}</td>
                                <td class="px-3 py-2">
                                    <span :class="['inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-medium', typeColors[m.type] || 'bg-gray-100 text-gray-700']">
                                        {{ m.type }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-center font-bold" :class="m.quantity > 0 ? 'text-green-600' : 'text-red-600'">
                                    {{ m.quantity > 0 ? '+' : '' }}{{ m.quantity }}
                                </td>
                                <td class="px-3 py-2 text-center text-gray-700">{{ m.balance_after }}</td>
                                <td class="px-3 py-2 text-xs text-gray-500">{{ m.invoice?.invoice_number || m.reference || '—' }}</td>
                                <td class="px-3 py-2 text-xs text-gray-400 truncate max-w-[200px]">{{ m.notes || '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex justify-end">
                    <button @click="showMovementsModal = false" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Close</button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
