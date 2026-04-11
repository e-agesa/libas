<script setup>
import { useForm } from '@inertiajs/vue3';
import { watch, ref, computed } from 'vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import UnitToggle from './UnitToggle.vue';
import DynamicGarmentFields from './DynamicGarmentFields.vue';
import { useGarmentTypes } from '@/composables/useGarmentTypes';

const props = defineProps({
    show: Boolean,
    contactId: [Number, String],
    measurement: Object,       // For editing an existing measurement
    parentMeasurement: Object, // For creating a new revision (pre-fills values from parent)
});

const emit = defineEmits(['close']);

const CM_TO_INCHES = 0.393701;
const INCHES_TO_CM = 2.54;

const { garmentTypes, getColor, getLabel } = useGarmentTypes();

const form = useForm({
    garment_type: '',
    label: '',
    date_taken: new Date().toISOString().split('T')[0],
    unit: 'cm',
    values: {},
    notes: '',
    parent_id: null,
});

const previousUnit = ref('cm');

// Set default garment type to first available type
const defaultType = computed(() => garmentTypes.value[0]?.slug || '');

watch(() => props.show, (val) => {
    if (val && props.measurement) {
        // Editing existing measurement
        form.garment_type = props.measurement.garment_type || defaultType.value;
        form.label = props.measurement.label || '';
        form.date_taken = props.measurement.date_taken?.split('T')[0] || new Date().toISOString().split('T')[0];
        form.unit = props.measurement.unit || 'cm';
        form.values = { ...(props.measurement.values || {}) };
        form.notes = props.measurement.notes || '';
        form.parent_id = null; // Not a new revision when editing
        previousUnit.value = form.unit;
    } else if (val && props.parentMeasurement) {
        // Creating a new revision — pre-fill from parent
        const parent = props.parentMeasurement;
        const now = new Date();
        const monthName = now.toLocaleString('en', { month: 'long' });
        form.garment_type = parent.garment_type || defaultType.value;
        form.label = `${parent.label ? parent.label.replace(/\s*\(Rev\s*\d+\)/, '') : (parent.garment_type || '')} ${monthName} ${now.getFullYear()}`;
        form.date_taken = now.toISOString().split('T')[0];
        form.unit = parent.unit || 'cm';
        form.values = { ...(parent.values || {}) };
        form.notes = '';
        form.parent_id = parent.parent_id || parent.id; // Point to root
        previousUnit.value = form.unit;
    } else if (val) {
        // Brand new measurement
        form.reset();
        form.garment_type = defaultType.value;
        form.date_taken = new Date().toISOString().split('T')[0];
        form.values = {};
        form.parent_id = null;
        previousUnit.value = 'cm';
    }
});

// Set garment type once types load (in case they loaded after modal opened)
watch(defaultType, (val) => {
    if (!form.garment_type && val) {
        form.garment_type = val;
    }
});

// Reset values when garment type changes (but not for revisions/edits)
watch(() => form.garment_type, () => {
    if (!props.measurement && !props.parentMeasurement) {
        form.values = {};
    }
});

// Convert values when unit changes
watch(() => form.unit, (newUnit) => {
    if (newUnit === previousUnit.value) return;
    const factor = newUnit === 'inches' ? CM_TO_INCHES : INCHES_TO_CM;
    const converted = {};
    for (const [key, val] of Object.entries(form.values)) {
        if (val && !isNaN(val)) {
            converted[key] = Math.round(parseFloat(val) * factor * 10) / 10;
        } else {
            converted[key] = val;
        }
    }
    form.values = converted;
    previousUnit.value = newUnit;
});

function tabColor(slug, isActive) {
    const c = getColor(slug);
    if (isActive) {
        return `border-b-2 ${c.text} border-current`;
    }
    return 'border-b-2 border-transparent text-gray-500 hover:text-gray-700';
}

function submit() {
    // Filter out empty values
    const cleanValues = {};
    for (const [key, val] of Object.entries(form.values)) {
        if (val !== '' && val !== null && val !== undefined) {
            cleanValues[key] = parseFloat(val);
        }
    }
    form.values = cleanValues;

    if (props.measurement?.id) {
        form.put(route('measurements.update', props.measurement.id), {
            onSuccess: () => emit('close'),
            preserveScroll: true,
        });
    } else {
        form.post(route('measurements.store', props.contactId), {
            onSuccess: () => emit('close'),
        });
    }
}
</script>

<template>
    <Modal :show="show" @close="emit('close')" max-width="2xl">
        <form @submit.prevent="submit" class="p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">
                    {{ measurement ? 'Edit Measurement' : (parentMeasurement ? 'New Revision' : 'New Measurement') }}
                </h3>
                <UnitToggle v-model="form.unit" />
            </div>

            <!-- Revision banner -->
            <div v-if="parentMeasurement" class="mb-4 rounded-lg bg-amber-50 border border-amber-200 px-4 py-2 text-sm text-amber-800">
                <i class="pi pi-info-circle mr-1"></i>
                Creating a new revision based on <strong>{{ parentMeasurement.label || parentMeasurement.garment_type }}</strong>.
                Previous values are pre-filled — update what has changed.
            </div>

            <!-- Garment type tabs -->
            <div class="flex gap-1 border-b border-gray-200 mb-5 overflow-x-auto">
                <button
                    v-for="type in garmentTypes"
                    :key="type.slug"
                    type="button"
                    @click="!parentMeasurement && (form.garment_type = type.slug)"
                    :disabled="!!parentMeasurement"
                    :class="[
                        'px-4 py-2 text-sm font-medium -mb-px transition-colors whitespace-nowrap',
                        tabColor(type.slug, form.garment_type === type.slug),
                        parentMeasurement ? 'cursor-not-allowed opacity-60' : '',
                    ]"
                >
                    {{ type.name }}
                </button>
                <div v-if="!garmentTypes.length" class="text-sm text-gray-400 px-4 py-2">
                    No garment types configured. Add them in Settings.
                </div>
            </div>
            <InputError :message="form.errors.garment_type" class="mb-3" />

            <!-- Label & Date -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-5">
                <div>
                    <InputLabel for="m_label" value="Label / Occasion" />
                    <TextInput
                        id="m_label"
                        v-model="form.label"
                        type="text"
                        class="mt-1 block w-full"
                        placeholder="e.g. 2026 Eid Kanzu"
                    />
                    <InputError :message="form.errors.label" class="mt-1" />
                </div>
                <div>
                    <InputLabel for="m_date" value="Date Taken *" />
                    <TextInput
                        id="m_date"
                        v-model="form.date_taken"
                        type="date"
                        class="mt-1 block w-full"
                        required
                    />
                    <InputError :message="form.errors.date_taken" class="mt-1" />
                </div>
            </div>

            <!-- Dynamic garment fields -->
            <div class="rounded-lg border border-gray-200 p-4 mb-5 bg-gray-50/50">
                <DynamicGarmentFields
                    v-if="form.garment_type"
                    :garment-type="form.garment_type"
                    v-model="form.values"
                    :unit="form.unit"
                />
                <div v-else class="text-sm text-gray-400 text-center py-4">
                    Select a garment type above to enter measurements.
                </div>
            </div>
            <InputError :message="form.errors.values" class="mb-3" />

            <!-- Notes -->
            <div class="mb-5">
                <InputLabel for="m_notes" value="Notes" />
                <textarea
                    id="m_notes"
                    v-model="form.notes"
                    rows="2"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-600 focus:ring-brand-600 text-sm"
                    placeholder="Style preferences, fit notes, etc."
                ></textarea>
                <InputError :message="form.errors.notes" class="mt-1" />
            </div>

            <!-- Actions -->
            <div class="flex justify-end gap-3">
                <button
                    type="button"
                    @click="emit('close')"
                    class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors"
                >
                    Cancel
                </button>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50 transition-colors"
                >
                    {{ form.processing ? 'Saving...' : (measurement ? 'Update Measurement' : (parentMeasurement ? 'Save Revision' : 'Save Measurement')) }}
                </button>
            </div>
        </form>
    </Modal>
</template>
