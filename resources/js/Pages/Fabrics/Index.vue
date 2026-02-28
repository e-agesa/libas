<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import FabricForm from '@/Components/Fabrics/FabricForm.vue';

const props = defineProps({
    fabrics: Object,
    filters: Object,
});

const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || '');
const showModal = ref(false);
const editingFabric = ref(null);

let searchTimeout = null;
watch(search, (value) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(route('fabrics.index'), { search: value, status: status.value }, {
            preserveState: true, replace: true,
        });
    }, 300);
});

watch(status, (value) => {
    router.get(route('fabrics.index'), { search: search.value, status: value }, {
        preserveState: true, replace: true,
    });
});

function openEdit(fabric) {
    editingFabric.value = fabric;
    showModal.value = true;
}

function closeModal() {
    showModal.value = false;
    editingFabric.value = null;
}

function deleteFabric(fabric) {
    if (confirm(`Delete "${fabric.name}"?`)) {
        router.delete(route('fabrics.destroy', fabric.id));
    }
}

function stockBadge(qty) {
    if (qty <= 0) return { class: 'bg-red-100 text-red-700', label: 'Out' };
    if (qty < 10) return { class: 'bg-amber-100 text-amber-700', label: 'Low' };
    return { class: 'bg-green-100 text-green-700', label: 'OK' };
}

function formatCurrency(amount) {
    return 'KES ' + Number(amount).toLocaleString('en-KE', { minimumFractionDigits: 0 });
}
</script>

<template>
    <Head title="Fabrics" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Fabrics & Materials</h2>
                <button @click="showModal = true" class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 transition-colors">
                    <i class="pi pi-plus text-xs"></i> Add Fabric
                </button>
            </div>
        </template>

        <!-- Filters -->
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-1 gap-3">
                <div class="relative flex-1 max-w-md">
                    <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                    <input v-model="search" type="text" placeholder="Search by name, type, color, supplier..." class="w-full rounded-lg border border-gray-300 bg-white py-2 pl-9 pr-4 text-sm focus:border-green-500 focus:ring-green-500" />
                </div>
                <select v-model="status" class="rounded-lg border border-gray-300 bg-white py-2 pl-3 pr-8 text-sm focus:border-green-500 focus:ring-green-500">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div class="text-sm text-gray-500">{{ fabrics.total }} fabric{{ fabrics.total !== 1 ? 's' : '' }}</div>
        </div>

        <!-- Table -->
        <div class="rounded-xl bg-white shadow-sm border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left">
                        <tr>
                            <th class="px-6 py-3 font-medium text-gray-500 uppercase text-xs">Fabric</th>
                            <th class="px-6 py-3 font-medium text-gray-500 uppercase text-xs hidden md:table-cell">Type</th>
                            <th class="px-6 py-3 font-medium text-gray-500 uppercase text-xs hidden md:table-cell">Color</th>
                            <th class="px-6 py-3 font-medium text-gray-500 uppercase text-xs text-right">Price/Unit</th>
                            <th class="px-6 py-3 font-medium text-gray-500 uppercase text-xs text-center">Stock</th>
                            <th class="px-6 py-3 font-medium text-gray-500 uppercase text-xs text-center hidden lg:table-cell">Used In</th>
                            <th class="px-6 py-3 font-medium text-gray-500 uppercase text-xs hidden lg:table-cell">Supplier</th>
                            <th class="px-6 py-3 font-medium text-gray-500 uppercase text-xs text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="fabric in fabrics.data" :key="fabric.id" class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <div class="font-medium text-gray-900">{{ fabric.name }}</div>
                                    <span v-if="fabric.status === 'inactive'" class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500">Inactive</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-gray-600 hidden md:table-cell">{{ fabric.type || '—' }}</td>
                            <td class="px-6 py-4 hidden md:table-cell">
                                <span v-if="fabric.color" class="inline-flex items-center gap-1.5">
                                    <span class="text-gray-600">{{ fabric.color }}</span>
                                </span>
                                <span v-else class="text-gray-400">—</span>
                            </td>
                            <td class="px-6 py-4 text-right font-medium text-gray-900">{{ formatCurrency(fabric.price_per_unit) }}</td>
                            <td class="px-6 py-4 text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    <span class="font-medium" :class="fabric.stock_qty < 10 ? 'text-red-600' : 'text-gray-900'">{{ fabric.stock_qty }}</span>
                                    <span :class="stockBadge(fabric.stock_qty).class" class="rounded-full px-1.5 py-0.5 text-xs font-medium">{{ stockBadge(fabric.stock_qty).label }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center text-gray-600 hidden lg:table-cell">{{ fabric.invoice_line_items_count }}</td>
                            <td class="px-6 py-4 text-gray-600 hidden lg:table-cell">{{ fabric.supplier || '—' }}</td>
                            <td class="px-6 py-4 text-right">
                                <button @click="openEdit(fabric)" class="text-gray-400 hover:text-blue-600 mr-2" title="Edit"><i class="pi pi-pencil"></i></button>
                                <button @click="deleteFabric(fabric)" class="text-gray-400 hover:text-red-600" title="Delete"><i class="pi pi-trash"></i></button>
                            </td>
                        </tr>
                        <tr v-if="fabrics.data.length === 0">
                            <td colspan="8" class="px-6 py-12 text-center text-gray-400">
                                <i class="pi pi-palette text-4xl mb-3 block"></i>
                                <p class="font-medium">No fabrics found</p>
                                <p class="text-sm mt-1">Add your first fabric to get started.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <!-- Pagination -->
            <div v-if="fabrics.last_page > 1" class="border-t border-gray-100 px-6 py-3 flex items-center justify-between">
                <div class="text-sm text-gray-500">Showing {{ fabrics.from }} to {{ fabrics.to }} of {{ fabrics.total }}</div>
                <div class="flex gap-1">
                    <Link v-for="link in fabrics.links" :key="link.label" :href="link.url || '#'" v-html="link.label" :class="['px-3 py-1 rounded text-sm', link.active ? 'bg-green-600 text-white' : 'text-gray-600 hover:bg-gray-100', !link.url ? 'opacity-50 cursor-not-allowed' : '']" />
                </div>
            </div>
        </div>

        <FabricForm :show="showModal" :fabric="editingFabric" @close="closeModal" />
    </AuthenticatedLayout>
</template>
