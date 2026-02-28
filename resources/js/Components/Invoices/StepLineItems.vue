<script setup>
import LineItemRow from './LineItemRow.vue';

const props = defineProps({
    lineItems: Array,
    contacts: Array,
    fabrics: Array,
});

const emit = defineEmits(['update:lineItems']);

function addItem() {
    const updated = [...props.lineItems, {
        contact_id: props.contacts?.[0]?.id || '',
        measurement_id: null,
        fabric_id: null,
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
                <p class="text-sm text-gray-500">Add garments and services for this invoice.</p>
            </div>
            <button
                type="button"
                @click="addItem"
                class="inline-flex items-center gap-1 rounded-lg border border-green-600 px-3 py-1.5 text-sm font-medium text-green-600 hover:bg-green-50 transition-colors"
            >
                <i class="pi pi-plus text-xs"></i> Add Item
            </button>
        </div>

        <div class="space-y-3">
            <LineItemRow
                v-for="(item, i) in lineItems"
                :key="i"
                :item="item"
                :contacts="contacts"
                :fabrics="fabrics"
                :index="i"
                @update="updateItem"
                @remove="removeItem"
            />
        </div>

        <div v-if="lineItems.length === 0" class="text-center py-8 text-gray-400 border border-dashed border-gray-300 rounded-lg">
            <i class="pi pi-plus-circle text-3xl mb-2 block"></i>
            <p class="text-sm">No line items yet. Add at least one.</p>
            <button type="button" @click="addItem" class="mt-2 text-sm text-green-600 hover:underline">Add first item</button>
        </div>
    </div>
</template>
