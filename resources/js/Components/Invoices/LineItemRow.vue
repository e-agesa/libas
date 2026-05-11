<script setup>
import { computed } from 'vue';
import { useGarmentTypes } from '@/composables/useGarmentTypes';

const props = defineProps({
    item: Object,
    contacts: Array,
    fabrics: Array,
    collections: { type: Array, default: () => [] },
    index: Number,
});

const emit = defineEmits(['update', 'remove']);

const isCollection = computed(() => props.item.item_type === 'collection');

const selectedContact = computed(() =>
    props.contacts?.find(c => c.id === props.item.contact_id)
);

const contactMeasurements = computed(() =>
    selectedContact.value?.measurements || []
);

const selectedCollection = computed(() =>
    props.collections?.find(c => c.id === parseInt(props.item.collection_id))
);

const lineTotal = computed(() => {
    const qty = parseInt(props.item.quantity) || 1;
    if (isCollection.value) {
        return (parseFloat(props.item.unit_price) || 0) * qty;
    }
    const fee = parseFloat(props.item.craftsmanship_fee) || 0;
    const fabric = parseFloat(props.item.fabric_cost) || 0;
    return (fee + fabric) * qty;
});

function update(field, value) {
    emit('update', props.index, { ...props.item, [field]: value });
}

function onCollectionChange(collectionId) {
    const col = props.collections.find(c => c.id === parseInt(collectionId));
    const updates = {
        ...props.item,
        collection_id: collectionId ? parseInt(collectionId) : null,
        description: col ? `${col.name}${col.size ? ' — ' + col.size : ''}${col.color ? ' (' + col.color + ')' : ''}` : '',
        unit_price: col ? parseFloat(col.price) : 0,
    };
    emit('update', props.index, updates);
}

function onFabricChange(fabricId) {
    const fabric = props.fabrics.find(f => f.id === parseInt(fabricId));
    const updates = { ...props.item, fabric_id: fabricId ? parseInt(fabricId) : null };
    if (fabric) {
        updates.fabric_cost = parseFloat(fabric.price_per_unit) || 0;
    }
    emit('update', props.index, updates);
}

const { getColor, getLabel } = useGarmentTypes();

const garmentBadge = computed(() => {
    const m = contactMeasurements.value.find(m => m.id === props.item.measurement_id);
    if (!m) return null;
    const c = getColor(m.garment_type);
    return { type: m.garment_type, label: m.label || getLabel(m.garment_type), color: `${c.bg} ${c.text}` };
});
</script>

<template>
    <div :class="[
        'rounded-lg border p-4 bg-white',
        isCollection ? 'border-blue-200' : 'border-gray-200'
    ]">
        <div class="flex items-start justify-between mb-3">
            <div class="flex items-center gap-2">
                <span class="text-xs font-medium text-gray-400">Item {{ index + 1 }}</span>
                <span v-if="isCollection" class="inline-flex items-center gap-1 rounded-full bg-blue-100 text-blue-700 px-2 py-0.5 text-xs font-medium">
                    <i class="pi pi-shopping-bag text-[10px]"></i> Shelf
                </span>
                <span v-else class="inline-flex items-center gap-1 rounded-full bg-green-100 text-green-700 px-2 py-0.5 text-xs font-medium">
                    <i class="pi pi-scissors text-[10px]"></i> Custom
                </span>
            </div>
            <button type="button" @click="emit('remove', index)" class="p-1 text-gray-400 hover:text-red-600 rounded hover:bg-red-50 transition-colors">
                <i class="pi pi-times text-xs"></i>
            </button>
        </div>

        <!-- Collection item fields -->
        <div v-if="isCollection" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div class="sm:col-span-2">
                <label class="text-xs font-medium text-gray-600 mb-1 block">Collection Item *</label>
                <select
                    :value="item.collection_id"
                    @change="onCollectionChange($event.target.value)"
                    class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500"
                    required
                >
                    <option value="">Select item...</option>
                    <option v-for="col in collections" :key="col.id" :value="col.id">
                        {{ col.name }}
                        <template v-if="col.size"> — {{ col.size }}</template>
                        <template v-if="col.color"> ({{ col.color }})</template>
                        · KES {{ Number(col.price).toLocaleString() }}
                        · Stock: {{ col.stock_qty }}
                    </option>
                </select>
            </div>

            <div>
                <label class="text-xs font-medium text-gray-600 mb-1 block">Unit Price (KES) *</label>
                <input
                    type="number"
                    :value="item.unit_price"
                    @input="update('unit_price', parseFloat($event.target.value) || 0)"
                    min="0"
                    step="1"
                    class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500"
                    required
                />
            </div>

            <div>
                <label class="text-xs font-medium text-gray-600 mb-1 block">Quantity</label>
                <input
                    type="number"
                    :value="item.quantity"
                    @input="update('quantity', parseInt($event.target.value) || 1)"
                    min="1"
                    :max="selectedCollection?.stock_qty || 999"
                    class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500"
                />
                <p v-if="selectedCollection" class="text-xs text-gray-400 mt-0.5">{{ selectedCollection.stock_qty }} in stock</p>
            </div>

            <div class="sm:col-span-2">
                <label class="text-xs font-medium text-gray-600 mb-1 block">Description</label>
                <input
                    type="text"
                    :value="item.description"
                    @input="update('description', $event.target.value)"
                    class="w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500"
                    placeholder="Auto-filled from selection"
                />
            </div>
        </div>

        <!-- Custom item fields -->
        <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label class="text-xs font-medium text-gray-600 mb-1 block">Person *</label>
                <select
                    :value="item.contact_id"
                    @change="update('contact_id', parseInt($event.target.value))"
                    class="w-full rounded-md border-gray-300 text-sm focus:border-brand-600 focus:ring-brand-600"
                    required
                >
                    <option value="">Select person...</option>
                    <option v-for="c in contacts" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
            </div>

            <div>
                <label class="text-xs font-medium text-gray-600 mb-1 block">Measurement</label>
                <select
                    :value="item.measurement_id"
                    @change="update('measurement_id', $event.target.value ? parseInt($event.target.value) : null)"
                    class="w-full rounded-md border-gray-300 text-sm focus:border-brand-600 focus:ring-brand-600"
                >
                    <option value="">None</option>
                    <option v-for="m in contactMeasurements" :key="m.id" :value="m.id">
                        {{ m.garment_type }} — {{ m.label || 'Untitled' }}
                    </option>
                </select>
                <span v-if="garmentBadge" :class="garmentBadge.color" class="inline-flex mt-1 rounded-full px-2 py-0.5 text-xs font-medium capitalize">{{ garmentBadge.label }}</span>
            </div>

            <div>
                <label class="text-xs font-medium text-gray-600 mb-1 block">Fabric</label>
                <select
                    :value="item.fabric_id"
                    @change="onFabricChange($event.target.value)"
                    class="w-full rounded-md border-gray-300 text-sm focus:border-brand-600 focus:ring-brand-600"
                >
                    <option value="">No fabric</option>
                    <option v-for="f in fabrics" :key="f.id" :value="f.id">{{ f.name }} (KES {{ Number(f.price_per_unit).toLocaleString() }})</option>
                </select>
            </div>

            <div>
                <label class="text-xs font-medium text-gray-600 mb-1 block">Quantity</label>
                <input
                    type="number"
                    :value="item.quantity"
                    @input="update('quantity', parseInt($event.target.value) || 1)"
                    min="1"
                    class="w-full rounded-md border-gray-300 text-sm focus:border-brand-600 focus:ring-brand-600"
                />
            </div>

            <div>
                <label class="text-xs font-medium text-gray-600 mb-1 block">Craftsmanship Fee (KES) *</label>
                <input
                    type="number"
                    :value="item.craftsmanship_fee"
                    @input="update('craftsmanship_fee', parseFloat($event.target.value) || 0)"
                    min="0"
                    step="1"
                    class="w-full rounded-md border-gray-300 text-sm focus:border-brand-600 focus:ring-brand-600"
                    required
                />
            </div>

            <div>
                <label class="text-xs font-medium text-gray-600 mb-1 block">Fabric Cost (KES)</label>
                <input
                    type="number"
                    :value="item.fabric_cost"
                    @input="update('fabric_cost', parseFloat($event.target.value) || 0)"
                    min="0"
                    step="1"
                    class="w-full rounded-md border-gray-300 text-sm focus:border-brand-600 focus:ring-brand-600"
                />
            </div>
        </div>

        <!-- Line total -->
        <div class="mt-3 pt-2 border-t border-gray-100 flex justify-between items-center">
            <span class="text-xs text-gray-500">Line Total</span>
            <span class="font-semibold text-gray-900">KES {{ lineTotal.toLocaleString() }}</span>
        </div>
    </div>
</template>
