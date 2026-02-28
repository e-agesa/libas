<script setup>
import { computed } from 'vue';
import { useGarmentTypes } from '@/composables/useGarmentTypes';

const props = defineProps({
    item: Object,
    contacts: Array,
    fabrics: Array,
    index: Number,
});

const emit = defineEmits(['update', 'remove']);

const selectedContact = computed(() =>
    props.contacts?.find(c => c.id === props.item.contact_id)
);

const contactMeasurements = computed(() =>
    selectedContact.value?.measurements || []
);

const lineTotal = computed(() => {
    const fee = parseFloat(props.item.craftsmanship_fee) || 0;
    const fabric = parseFloat(props.item.fabric_cost) || 0;
    const qty = parseInt(props.item.quantity) || 1;
    return (fee + fabric) * qty;
});

function update(field, value) {
    emit('update', props.index, { ...props.item, [field]: value });
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
    <div class="rounded-lg border border-gray-200 p-4 bg-white">
        <div class="flex items-start justify-between mb-3">
            <span class="text-xs font-medium text-gray-400">Item {{ index + 1 }}</span>
            <button type="button" @click="emit('remove', index)" class="p-1 text-gray-400 hover:text-red-600 rounded hover:bg-red-50 transition-colors">
                <i class="pi pi-times text-xs"></i>
            </button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <!-- Contact -->
            <div>
                <label class="text-xs font-medium text-gray-600 mb-1 block">Person *</label>
                <select
                    :value="item.contact_id"
                    @change="update('contact_id', parseInt($event.target.value))"
                    class="w-full rounded-md border-gray-300 text-sm focus:border-green-500 focus:ring-green-500"
                    required
                >
                    <option value="">Select person...</option>
                    <option v-for="c in contacts" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
            </div>

            <!-- Measurement -->
            <div>
                <label class="text-xs font-medium text-gray-600 mb-1 block">Measurement</label>
                <select
                    :value="item.measurement_id"
                    @change="update('measurement_id', $event.target.value ? parseInt($event.target.value) : null)"
                    class="w-full rounded-md border-gray-300 text-sm focus:border-green-500 focus:ring-green-500"
                >
                    <option value="">None</option>
                    <option v-for="m in contactMeasurements" :key="m.id" :value="m.id">
                        {{ m.garment_type }} — {{ m.label || 'Untitled' }}
                    </option>
                </select>
                <span v-if="garmentBadge" :class="garmentBadge.color" class="inline-flex mt-1 rounded-full px-2 py-0.5 text-xs font-medium capitalize">{{ garmentBadge.label }}</span>
            </div>

            <!-- Fabric -->
            <div>
                <label class="text-xs font-medium text-gray-600 mb-1 block">Fabric</label>
                <select
                    :value="item.fabric_id"
                    @change="onFabricChange($event.target.value)"
                    class="w-full rounded-md border-gray-300 text-sm focus:border-green-500 focus:ring-green-500"
                >
                    <option value="">No fabric</option>
                    <option v-for="f in fabrics" :key="f.id" :value="f.id">{{ f.name }} (KES {{ Number(f.price_per_unit).toLocaleString() }})</option>
                </select>
            </div>

            <!-- Quantity -->
            <div>
                <label class="text-xs font-medium text-gray-600 mb-1 block">Quantity</label>
                <input
                    type="number"
                    :value="item.quantity"
                    @input="update('quantity', parseInt($event.target.value) || 1)"
                    min="1"
                    class="w-full rounded-md border-gray-300 text-sm focus:border-green-500 focus:ring-green-500"
                />
            </div>

            <!-- Craftsmanship Fee -->
            <div>
                <label class="text-xs font-medium text-gray-600 mb-1 block">Craftsmanship Fee (KES) *</label>
                <input
                    type="number"
                    :value="item.craftsmanship_fee"
                    @input="update('craftsmanship_fee', parseFloat($event.target.value) || 0)"
                    min="0"
                    step="50"
                    class="w-full rounded-md border-gray-300 text-sm focus:border-green-500 focus:ring-green-500"
                    required
                />
            </div>

            <!-- Fabric Cost -->
            <div>
                <label class="text-xs font-medium text-gray-600 mb-1 block">Fabric Cost (KES)</label>
                <input
                    type="number"
                    :value="item.fabric_cost"
                    @input="update('fabric_cost', parseFloat($event.target.value) || 0)"
                    min="0"
                    step="50"
                    class="w-full rounded-md border-gray-300 text-sm focus:border-green-500 focus:ring-green-500"
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
