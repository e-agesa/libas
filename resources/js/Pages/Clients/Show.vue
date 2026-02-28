<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import ClientForm from '@/Components/Clients/ClientForm.vue';
import ContactForm from '@/Components/Contacts/ContactForm.vue';
import ContactCard from '@/Components/Contacts/ContactCard.vue';
import { useGarmentTypes } from '@/composables/useGarmentTypes';

const props = defineProps({
    client: Object,
});

const activeTab = ref('contacts');
const showEditClient = ref(false);
const showAddContact = ref(false);
const editingContact = ref(null);

const typeLabels = {
    individual: 'Individual', family: 'Family', corporate: 'Corporate', institution: 'Institution',
};

const { getColor, getLabel } = useGarmentTypes();

function openEditContact(contact) {
    editingContact.value = contact;
    showAddContact.value = true;
}

function closeContactModal() {
    showAddContact.value = false;
    editingContact.value = null;
}

function deleteContact(contact) {
    if (confirm(`Delete "${contact.name}" and all their measurements?`)) {
        router.delete(route('contacts.destroy', contact.id));
    }
}
</script>

<template>
    <Head :title="client.name" />
    <AuthenticatedLayout>
        <template #breadcrumb>
            <nav class="flex items-center gap-2 text-sm text-gray-500">
                <Link :href="route('clients.index')" class="hover:text-green-600">Clients</Link>
                <i class="pi pi-angle-right text-xs"></i>
                <span class="text-gray-900 font-medium">{{ client.name }}</span>
            </nav>
        </template>

        <!-- Client Header -->
        <div class="mb-6 rounded-xl bg-white p-6 shadow-sm border border-gray-100">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                <div class="flex items-start gap-4">
                    <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl bg-green-100 text-green-700 text-xl font-bold">
                        {{ client.name.charAt(0).toUpperCase() }}
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">{{ client.name }}</h1>
                        <div class="mt-1 flex flex-wrap items-center gap-3 text-sm text-gray-500">
                            <span class="flex items-center gap-1"><i class="pi pi-phone text-xs"></i> {{ client.phone }}</span>
                            <span v-if="client.email" class="flex items-center gap-1"><i class="pi pi-envelope text-xs"></i> {{ client.email }}</span>
                            <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium capitalize">{{ typeLabels[client.type] }}</span>
                        </div>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link :href="route('clients.statement', client.id)" class="inline-flex items-center gap-1 rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        <i class="pi pi-file text-xs"></i> Statement
                    </Link>
                    <button @click="showEditClient = true" class="inline-flex items-center gap-1 rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        <i class="pi pi-pencil text-xs"></i> Edit
                    </button>
                    <button @click="showAddContact = true" class="inline-flex items-center gap-1 rounded-lg bg-green-600 px-3 py-2 text-sm font-medium text-white hover:bg-green-700">
                        <i class="pi pi-plus text-xs"></i> Add Contact
                    </button>
                </div>
            </div>

            <!-- Stats row -->
            <div class="mt-4 grid grid-cols-3 gap-4 border-t border-gray-100 pt-4">
                <div class="text-center">
                    <p class="text-2xl font-bold text-gray-900">{{ client.contacts_count }}</p>
                    <p class="text-xs text-gray-500">Contacts</p>
                </div>
                <div class="text-center">
                    <p class="text-2xl font-bold text-gray-900">{{ client.invoices_count }}</p>
                    <p class="text-xs text-gray-500">Invoices</p>
                </div>
                <div class="text-center">
                    <p class="text-2xl font-bold text-gray-900">{{ client.address || '—' }}</p>
                    <p class="text-xs text-gray-500">Address</p>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="mb-4 flex gap-1 border-b border-gray-200">
            <button @click="activeTab = 'contacts'" :class="['px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors', activeTab === 'contacts' ? 'border-green-600 text-green-600' : 'border-transparent text-gray-500 hover:text-gray-700']">
                <i class="pi pi-users mr-1.5"></i> Contacts ({{ client.contacts?.length || 0 }})
            </button>
            <button @click="activeTab = 'invoices'" :class="['px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors', activeTab === 'invoices' ? 'border-green-600 text-green-600' : 'border-transparent text-gray-500 hover:text-gray-700']">
                <i class="pi pi-file-edit mr-1.5"></i> Invoices ({{ client.invoices?.length || 0 }})
            </button>
        </div>

        <!-- Contacts Tab -->
        <div v-if="activeTab === 'contacts'">
            <div v-if="client.contacts?.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <ContactCard
                    v-for="contact in client.contacts"
                    :key="contact.id"
                    :contact="contact"
                    @edit="openEditContact(contact)"
                    @delete="deleteContact(contact)"
                />
            </div>
            <div v-else class="rounded-xl bg-white p-12 shadow-sm border border-gray-100 text-center text-gray-400">
                <i class="pi pi-user-plus text-5xl mb-4 block"></i>
                <p class="text-lg font-medium">No contacts yet</p>
                <p class="text-sm mt-1">Add the first person to be measured under this client.</p>
                <button @click="showAddContact = true" class="mt-4 inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">
                    <i class="pi pi-plus text-xs"></i> Add Contact
                </button>
            </div>
        </div>

        <!-- Invoices Tab -->
        <div v-if="activeTab === 'invoices'">
            <div v-if="client.invoices?.length" class="rounded-xl bg-white shadow-sm border border-gray-100 overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Invoice #</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Balance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="inv in client.invoices" :key="inv.id" class="hover:bg-gray-50">
                            <td class="px-6 py-3 font-medium text-gray-900">{{ inv.invoice_number }}</td>
                            <td class="px-6 py-3 text-gray-600">{{ inv.date }}</td>
                            <td class="px-6 py-3">
                                <span :class="{
                                    'bg-gray-100 text-gray-700': inv.status === 'draft',
                                    'bg-blue-100 text-blue-700': inv.status === 'issued',
                                    'bg-green-100 text-green-700': inv.status === 'paid',
                                    'bg-red-100 text-red-700': inv.status === 'overdue',
                                }" class="rounded-full px-2.5 py-0.5 text-xs font-medium capitalize">{{ inv.status }}</span>
                            </td>
                            <td class="px-6 py-3 text-right text-gray-900">KES {{ Number(inv.total).toLocaleString() }}</td>
                            <td class="px-6 py-3 text-right" :class="Number(inv.balance) > 0 ? 'text-red-600 font-medium' : 'text-gray-500'">KES {{ Number(inv.balance).toLocaleString() }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-else class="rounded-xl bg-white p-12 shadow-sm border border-gray-100 text-center text-gray-400">
                <i class="pi pi-file-edit text-5xl mb-4 block"></i>
                <p class="text-lg font-medium">No invoices yet</p>
                <p class="text-sm mt-1">Create an invoice for this client.</p>
            </div>
        </div>

        <!-- Modals -->
        <ClientForm :show="showEditClient" :client="client" @close="showEditClient = false" />
        <ContactForm :show="showAddContact" :client-id="client.id" :contact="editingContact" @close="closeContactModal" />
    </AuthenticatedLayout>
</template>
