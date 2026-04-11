<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    clients: Array,
    selectedClientId: [Number, String, null],
});

const emit = defineEmits(['select']);

const search = ref('');

const filteredClients = computed(() => {
    if (!search.value) return props.clients;
    const q = search.value.toLowerCase();
    return props.clients.filter(c =>
        c.name.toLowerCase().includes(q) ||
        c.phone?.toLowerCase().includes(q) ||
        c.email?.toLowerCase().includes(q)
    );
});

function selectClient(client) {
    emit('select', client);
}
</script>

<template>
    <div>
        <h3 class="text-lg font-semibold text-gray-900 mb-1">Select Client</h3>
        <p class="text-sm text-gray-500 mb-4">Choose which client this invoice is for.</p>

        <!-- Search -->
        <div class="relative mb-4">
            <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
            <input
                v-model="search"
                type="text"
                placeholder="Search clients by name, phone, or email..."
                class="w-full rounded-lg border border-gray-300 bg-white py-2.5 pl-9 pr-4 text-sm focus:border-brand-600 focus:ring-brand-600"
                autofocus
            />
        </div>

        <!-- Client list -->
        <div class="space-y-2 max-h-96 overflow-y-auto">
            <button
                v-for="client in filteredClients"
                :key="client.id"
                type="button"
                @click="selectClient(client)"
                :class="[
                    'w-full flex items-center gap-3 rounded-lg border p-3 text-left transition-colors',
                    selectedClientId === client.id ? 'border-brand-600 bg-brand-50 ring-1 ring-brand-600' : 'border-gray-200 hover:border-brand-400 hover:bg-brand-50/50'
                ]"
            >
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-100 text-brand-700 font-bold text-sm">
                    {{ client.name.charAt(0).toUpperCase() }}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-medium text-gray-900 text-sm truncate">{{ client.name }}</p>
                    <p class="text-xs text-gray-500">{{ client.phone }} <span v-if="client.contacts?.length" class="ml-2 text-gray-400">&middot; {{ client.contacts.length }} contacts</span></p>
                </div>
                <i v-if="selectedClientId === client.id" class="pi pi-check-circle text-brand-600"></i>
            </button>
            <div v-if="filteredClients.length === 0" class="text-center py-8 text-gray-400">
                <i class="pi pi-search text-3xl mb-2 block"></i>
                <p class="text-sm">No clients match your search.</p>
            </div>
        </div>
    </div>
</template>
