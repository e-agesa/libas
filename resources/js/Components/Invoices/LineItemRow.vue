<script setup>
import { computed } from 'vue';
import { useGarmentTypes } from '@/composables/useGarmentTypes';
import { lineItemTotal, ridhaaTotal } from '@/composables/useLineTotal';

const props = defineProps({
    item: Object,
    contacts: Array,
    fabrics: Array,
    collections: { type: Array, default: () => [] },
    index: Number,
});

const emit = defineEmits(['update', 'remove', 'add-person', 'add-measurement', 'edit-measurement']);

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

const lineTotal = computed(() => lineItemTotal(props.item));
const ridhaaLineTotal = computed(() => ridhaaTotal(props.item));

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

function onPersonChange(e) {
    const v = e.target.value;
    if (v === '__new__') {
        // Restore the previous selection and open the quick-add modal instead
        e.target.value = props.item.contact_id != null ? String(props.item.contact_id) : '';
        emit('add-person', props.index);
        return;
    }
    // Changing the person invalidates any measurement picked for the old person
    emit('update', props.index, {
        ...props.item,
        contact_id: v ? parseInt(v) : '',
        measurement_id: null,
    });
}

function onMeasurementChange(e) {
    const v = e.target.value;
    // The two action entries are triggers, not values — restore the real
    // selection before opening the modal so the dropdown never shows them.
    if (v === '__new__' || v === '__edit__') {
        e.target.value = props.item.measurement_id != null ? String(props.item.measurement_id) : '';
        if (v === '__new__') {
            emit('add-measurement', props.index, props.item.contact_id);
        } else {
            emit('edit-measurement', props.index, props.item.contact_id, props.item.measurement_id);
        }
        return;
    }
    update('measurement_id', v ? parseInt(v) : null);
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
                    step="any"
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
            <div class="sm:col-span-2">
                <label class="text-xs font-medium text-gray-600 mb-1 block">
                    Item / Product Name
                    <span class="font-normal text-gray-400">— what the customer is buying; this is what shows on the receipt</span>
                </label>
                <input
                    type="text"
                    :value="item.description"
                    @input="update('description', $event.target.value)"
                    placeholder="e.g. Kanzu, Abaya, Customer Rida"
                    class="w-full rounded-md border-gray-300 text-sm focus:border-brand-600 focus:ring-brand-600"
                />
            </div>

            <div>
                <div class="flex items-center justify-between mb-1">
                    <label class="text-xs font-medium text-gray-600 block">Person *</label>
                    <button
                        type="button"
                        @click="emit('add-person', index)"
                        class="inline-flex items-center gap-1 text-xs font-medium text-brand-600 hover:text-brand-700 hover:underline"
                    >
                        <i class="pi pi-user-plus text-[10px]"></i> Add New Person
                    </button>
                </div>
                <select
                    :value="item.contact_id"
                    @change="onPersonChange"
                    class="w-full rounded-md border-gray-300 text-sm focus:border-brand-600 focus:ring-brand-600"
                    required
                >
                    <option value="">Select person...</option>
                    <option v-for="c in contacts" :key="c.id" :value="c.id">{{ c.name }}</option>
                    <option value="__new__">＋ Add new person…</option>
                </select>
            </div>

            <div>
                <div class="flex items-center justify-between mb-1 gap-2">
                    <label class="text-xs font-medium text-gray-600 block">Measurement</label>
                    <div v-if="item.contact_id" class="flex items-center gap-2">
                        <button
                            v-if="item.measurement_id"
                            type="button"
                            @click="emit('edit-measurement', index, item.contact_id, item.measurement_id)"
                            class="inline-flex items-center gap-1 text-xs font-medium text-brand-600 hover:text-brand-700 hover:underline"
                        >
                            <i class="pi pi-pencil text-[10px]"></i> Edit
                        </button>
                        <span v-if="item.measurement_id" class="text-gray-300 text-xs">|</span>
                        <button
                            type="button"
                            @click="emit('add-measurement', index, item.contact_id)"
                            class="inline-flex items-center gap-1 text-xs font-medium text-brand-600 hover:text-brand-700 hover:underline"
                        >
                            <i class="pi pi-plus text-[10px]"></i> Add
                        </button>
                    </div>
                </div>
                <select
                    :value="item.measurement_id"
                    @change="onMeasurementChange"
                    class="w-full rounded-md border-gray-300 text-sm focus:border-brand-600 focus:ring-brand-600"
                    :disabled="!item.contact_id"
                >
                    <option value="">None</option>
                    <option v-for="m in contactMeasurements" :key="m.id" :value="m.id">
                        {{ getLabel(m.garment_type) }} — {{ m.label || 'Untitled' }}
                    </option>
                    <option v-if="item.contact_id" value="__new__">＋ Add new measurement…</option>
                    <option v-if="item.measurement_id" value="__edit__">✎ Edit selected measurement…</option>
                </select>
                <p v-if="item.contact_id && !contactMeasurements.length" class="text-xs text-amber-600 mt-1">
                    No saved measurements for this person yet — use "Add Measurement".
                </p>
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
                    step="any"
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
                    step="any"
                    class="w-full rounded-md border-gray-300 text-sm focus:border-brand-600 focus:ring-brand-600"
                />
            </div>

            <!-- Ridhaa is not carried as stock: every one is a new item with its
                 own price, so the name, quantity and price are typed here at
                 invoice time rather than picked from the fabric catalogue. -->
            <div class="sm:col-span-2 rounded-lg border border-dashed border-amber-300 bg-amber-50/50 p-3">
                <div class="flex items-center justify-between gap-2 mb-2">
                    <label class="text-xs font-semibold text-amber-900">
                        Ridhaa
                        <span class="font-normal text-amber-700">— not stocked, enter it manually</span>
                    </label>
                    <span v-if="ridhaaLineTotal > 0" class="text-xs font-semibold text-amber-900 whitespace-nowrap">
                        KES {{ ridhaaLineTotal.toLocaleString() }}
                    </span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-2">
                    <div class="sm:col-span-2">
                        <label class="text-[11px] font-medium text-gray-600 mb-1 block">Name</label>
                        <input
                            type="text"
                            :value="item.ridhaa_name"
                            @input="update('ridhaa_name', $event.target.value)"
                            placeholder="Write the ridhaa name"
                            class="w-full rounded-md border-gray-300 text-sm focus:border-amber-500 focus:ring-amber-500"
                        />
                    </div>
                    <div>
                        <label class="text-[11px] font-medium text-gray-600 mb-1 block">Quantity</label>
                        <input
                            type="number"
                            :value="item.ridhaa_qty"
                            @input="update('ridhaa_qty', parseInt($event.target.value) || 0)"
                            min="0"
                            class="w-full rounded-md border-gray-300 text-sm focus:border-amber-500 focus:ring-amber-500"
                        />
                    </div>
                    <div>
                        <label class="text-[11px] font-medium text-gray-600 mb-1 block">Price (KES)</label>
                        <input
                            type="number"
                            :value="item.ridhaa_price"
                            @input="update('ridhaa_price', parseFloat($event.target.value) || 0)"
                            min="0"
                            step="any"
                            class="w-full rounded-md border-gray-300 text-sm focus:border-amber-500 focus:ring-amber-500"
                        />
                    </div>
                </div>
                <p v-if="item.ridhaa_name && ridhaaLineTotal <= 0" class="text-[11px] text-amber-700 mt-1.5">
                    Add a quantity and price so this ridhaa is billed.
                </p>
            </div>
        </div>

        <!-- Line total -->
        <div class="mt-3 pt-2 border-t border-gray-100 flex justify-between items-center">
            <span class="text-xs text-gray-500">Line Total</span>
            <span class="font-semibold text-gray-900">KES {{ lineTotal.toLocaleString() }}</span>
        </div>
    </div>
</template>
