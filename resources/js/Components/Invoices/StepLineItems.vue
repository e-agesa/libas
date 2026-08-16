<script setup>
import LineItemRow from './LineItemRow.vue';

const props = defineProps({
    lineItems: Array,
    contacts: Array,
    fabrics: Array,
    collections: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:lineItems', 'add-person', 'add-measurement']);

function addCustomItem() {
    const updated = [...props.lineItems, {
        item_type: 'custom',
        contact_id: props.contacts?.[0]?.id || '',
        measurement_id: null,
        fabric_id: null,
        collection_id: null,
        description: '',
        unit_price: 0,
        quantity: 1,
        craftsmanship_fee: 0,
        fabric_cost: 0,
    }];
    emit('update:lineItems', updated);
}

function addCollectionItem() {
    const updated = [...props.lineItems, {
        item_type: 'collection',
        contact_id: null,
        measurement_id: null,
        fabric_id: null,
        collection_id: '',
        description: '',
        unit_price: 0,
        quantity: 1,
        craftsmanship_fee: 0,
        fabric_cost: 0,
    }];
    emit('update:lineItems', updated);
}

function updateItem(index, item) {
    const updated = [...props.lineItems];
    updated[index] = item;
    emit('update:lineItems', updated);
}

function removeItem(index) {
    if (props.lineItems.length <= 1) return;
    const updated = props.lineItems.filter((_, i) => i !== index);
    emit('update:lineItems', updated);
}
</script>

<template>
    <div>
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Line Items</h3>
                <p class="text-sm text-gray-500">Add custom-made garments and/or off-the-shelf items.</p>
            </div>
            <div class="flex gap-2">
                <button
                    type="button"
                    @click="addCustomItem"
                    class="inline-flex items-center gap-1 rounded-lg border border-brand-600 px-3 py-1.5 text-sm font-medium text-brand-600 hover:bg-brand-50 transition-colors"
                >
                    <i class="pi pi-plus text-xs"></i> Custom
                </button>
                <button
                    v-if="collections?.length"
                    type="button"
                    @click="addCollectionItem"
                    class="inline-flex items-center gap-1 rounded-lg border border-blue-600 px-3 py-1.5 text-sm font-medium text-blue-600 hover:bg-blue-50 transition-colors"
                >
                    <i class="pi pi-shopping-bag text-xs"></i> Shelf Item
                </button>
            </div>
        </div>

        <div class="space-y-3">
            <LineItemRow
                v-for="(item, i) in lineItems"
                :key="i"
                :item="item"
                :contacts="contacts"
                :fabrics="fabrics"
                :collections="collections"
                :index="i"
                @update="updateItem"
                @remove="removeItem"
                @add-person="(index) => emit('add-person', index)"
                @add-measurement="(index, contactId) => emit('add-measurement', index, contactId)"
            />
        </div>

        <div v-if="lineItems.length === 0" class="text-center py-8 text-gray-400 border border-dashed border-gray-300 rounded-lg">
            <i class="pi pi-plus-circle text-3xl mb-2 block"></i>
            <p class="text-sm">No line items yet. Add at least one.</p>
            <div class="mt-2 flex justify-center gap-3">
                <button type="button" @click="addCustomItem" class="text-sm text-brand-600 hover:underline">Add custom garment</button>
                <button v-if="collections?.length" type="button" @click="addCollectionItem" class="text-sm text-blue-600 hover:underline">Add shelf item</button>
            </div>
        </div>
    </div>
</template>
