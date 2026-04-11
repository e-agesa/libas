<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import ClientForm from '@/Components/Clients/ClientForm.vue';

const props = defineProps({
    clients: Object,
    filters: Object,
});

const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || '');
const showCreateModal = ref(false);
const editingClient = ref(null);

let searchTimeout = null;
watch(search, (value) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(route('clients.index'), { search: value, status: status.value }, {
            preserveState: true, replace: true,
        });
    }, 300);
});

watch(status, (value) => {
    router.get(route('clients.index'), { search: search.value, status: value }, {
        preserveState: true, replace: true,
    });
});

function openEdit(client) {
    editingClient.value = client;
    showCreateModal.value = true;
}

function closeModal() {
    showCreateModal.value = false;
    editingClient.value = null;
}

function deleteClient(client) {
    if (confirm(`Are you sure you want to delete "${client.name}"?`)) {
        router.delete(route('clients.destroy', client.id));
    }
}

const typeLabels = {
    individual: 'Individual', family: 'Family', corporate: 'Corporate', institution: 'Institution',
};
</script>

<template>
    <Head title="Clients" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Clients</h2>
                <button @click="showCreateModal = true" class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 transition-colors">
                    <i class="pi pi-plus text-xs"></i> Add Client
                </button>
            </div>
        </template>

        <!-- Filters -->
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-1 gap-3">
                <div class="relative flex-1 max-w-md">
                    <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                    <input v-model="search" type="text" placeholder="Search by name, phone, or email..." class="w-full rounded-lg border border-gray-300 bg-white py-2 pl-9 pr-4 text-sm focus:border-brand-600 focus:ring-brand-600" />
                </div>
                <select v-model="status" class="rounded-lg border border-gray-300 bg-white py-2 pl-3 pr-8 text-sm focus:border-brand-600 focus:ring-brand-600">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div class="text-sm text-gray-500">{{ clients.total }} client{{ clients.total !== 1 ? 's' : '' }}</div>
        </div>

        <!-- Table -->
        <div class="rounded-xl bg-white shadow-sm border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left">
                        <tr>
                            <th class="px-6 py-3 font-medium text-gray-500 uppercase text-xs">Name</th>
                            <th class="px-6 py-3 font-medium text-gray-500 uppercase text-xs">Phone</th>
                            <th class="px-6 py-3 font-medium text-gray-500 uppercase text-xs hidden md:table-cell">Email</th>
                            <th class="px-6 py-3 font-medium text-gray-500 uppercase text-xs">Type</th>
                            <th class="px-6 py-3 font-medium text-gray-500 uppercase text-xs text-center">Contacts</th>
                            <th class="px-6 py-3 font-medium text-gray-500 uppercase text-xs text-center hidden lg:table-cell">Orders</th>
                            <th class="px-6 py-3 font-medium text-gray-500 uppercase text-xs text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="client in clients.data" :key="client.id" class="hover:bg-gray-50 cursor-pointer" @click="router.visit(route('clients.show', client.id))">
                            <td class="px-6 py-4"><div class="font-medium text-gray-900">{{ client.name }}</div></td>
                            <td class="px-6 py-4 text-gray-600">{{ client.phone }}</td>
                            <td class="px-6 py-4 text-gray-600 hidden md:table-cell">{{ client.email || '—' }}</td>
                            <td class="px-6 py-4">
                                <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700 capitalize">{{ typeLabels[client.type] || client.type }}</span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-brand-100 text-xs font-medium text-brand-700">{{ client.contacts_count }}</span>
                            </td>
                            <td class="px-6 py-4 text-center hidden lg:table-cell text-gray-600">{{ client.invoices_count }}</td>
                            <td class="px-6 py-4 text-right" @click.stop>
                                <button @click="openEdit(client)" class="text-gray-400 hover:text-blue-600 mr-2" title="Edit"><i class="pi pi-pencil"></i></button>
                                <button @click="deleteClient(client)" class="text-gray-400 hover:text-red-600" title="Delete"><i class="pi pi-trash"></i></button>
                            </td>
                        </tr>
                        <tr v-if="clients.data.length === 0">
                            <td colspan="7" class="px-6 py-12 text-center text-gray-400">
                                <i class="pi pi-users text-4xl mb-3 block"></i>
                                <p class="font-medium">No clients found</p>
                                <p class="text-sm mt-1">Create your first client to get started.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <!-- Pagination -->
            <div v-if="clients.last_page > 1" class="border-t border-gray-100 px-6 py-3 flex items-center justify-between">
                <div class="text-sm text-gray-500">Showing {{ clients.from }} to {{ clients.to }} of {{ clients.total }}</div>
                <div class="flex gap-1">
                    <Link v-for="link in clients.links" :key="link.label" :href="link.url || '#'" v-html="link.label" :class="['px-3 py-1 rounded text-sm', link.active ? 'bg-brand-600 text-white' : 'text-gray-600 hover:bg-gray-100', !link.url ? 'opacity-50 cursor-not-allowed' : '']" />
                </div>
            </div>
        </div>

        <ClientForm :show="showCreateModal" :client="editingClient" @close="closeModal" />
    </AuthenticatedLayout>
</template>
