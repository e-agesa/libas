<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import UnitToggle from '@/Components/Measurements/UnitToggle.vue';
import DynamicGarmentFields from '@/Components/Measurements/DynamicGarmentFields.vue';
import { useGarmentTypes } from '@/composables/useGarmentTypes';

const props = defineProps({
    show: Boolean,
    contactId: [Number, String],
    contactName: String,
    // When set, the modal edits this existing measurement instead of creating one.
    measurementId: { type: [Number, String], default: null },
});

const emit = defineEmits(['close', 'created', 'updated']);

const isEdit = computed(() => !!props.measurementId);
const loading = ref(false);

const CM_TO_INCHES = 0.393701;
const INCHES_TO_CM = 2.54;

const { garmentTypes } = useGarmentTypes();

const blank = () => ({
    garment_type: garmentTypes.value[0]?.slug || '',
    label: '',
    date_taken: new Date().toISOString().split('T')[0],
    unit: 'cm',
    values: {},
    notes: '',
});

const form = ref(blank());
const errors = ref({});
const processing = ref(false);
const previousUnit = ref('cm');

// Guards the "clear values on garment-type change" watcher while we populate
// the form from the server — otherwise loading an existing measurement would
// immediately wipe the values it just loaded.
const populating = ref(false);

watch(() => props.show, async (val) => {
    if (!val) return;

    form.value = blank();
    errors.value = {};
    previousUnit.value = 'cm';

    if (!isEdit.value) return;

    loading.value = true;
    populating.value = true;
    try {
        const { data } = await window.axios.get(route('measurements.show', props.measurementId));
        form.value = {
            garment_type: data.garment_type || '',
            label: data.label || '',
            date_taken: (data.date_taken || '').split('T')[0] || new Date().toISOString().split('T')[0],
            unit: data.unit || 'cm',
            values: { ...(data.values || {}) },
            notes: data.notes || '',
        };
        previousUnit.value = form.value.unit;
    } catch (err) {
        errors.value = { values: ['Could not load this measurement. Close and try again.'] };
    } finally {
        loading.value = false;
        // Let the garment_type watcher fire for this change before re-arming it.
        await nextTick();
        populating.value = false;
    }
});

// Reset entered values when switching garment type (fields differ per type)
watch(() => form.value.garment_type, () => {
    if (populating.value) return;
    form.value.values = {};
});

// Convert already-entered values when the unit is toggled
watch(() => form.value.unit, (newUnit) => {
    if (newUnit === previousUnit.value) return;
    const factor = newUnit === 'inches' ? CM_TO_INCHES : INCHES_TO_CM;
    const converted = {};
    for (const [key, val] of Object.entries(form.value.values)) {
        converted[key] = val && !isNaN(val)
            ? Math.round(parseFloat(val) * factor * 10) / 10
            : val;
    }
    form.value.values = converted;
    previousUnit.value = newUnit;
});

const hasValues = computed(() =>
    Object.values(form.value.values).some(v => v !== '' && v !== null && v !== undefined)
);

async function submit() {
    if (processing.value) return;

    const cleanValues = {};
    for (const [key, val] of Object.entries(form.value.values)) {
        if (val !== '' && val !== null && val !== undefined) {
            cleanValues[key] = parseFloat(val);
        }
    }
    if (!Object.keys(cleanValues).length) {
        errors.value = { values: ['Enter at least one measurement value.'] };
        return;
    }

    processing.value = true;
    errors.value = {};
    try {
        const payload = { ...form.value, values: cleanValues };
        if (isEdit.value) {
            const { data } = await window.axios.put(route('measurements.update', props.measurementId), payload);
            emit('updated', data);
        } else {
            const { data } = await window.axios.post(route('measurements.store', props.contactId), payload);
            emit('created', data);
        }
    } catch (err) {
        if (err.response?.status === 422) {
            errors.value = err.response.data.errors || {};
        } else {
            errors.value = { values: ['Could not save the measurement. Please try again.'] };
        }
    } finally {
        processing.value = false;
    }
}
</script>

<template>
    <Modal :show="show" @close="emit('close')" max-width="2xl">
        <form @submit.prevent="submit" class="p-6">
            <div class="flex items-center justify-between mb-1">
                <h3 class="text-lg font-semibold text-gray-900">{{ isEdit ? 'Edit Measurement' : 'New Measurement' }}</h3>
                <UnitToggle v-model="form.unit" />
            </div>
            <p v-if="contactName" class="text-sm text-gray-500 mb-4">
                For <span class="font-medium text-gray-700">{{ contactName }}</span> —
                {{ isEdit ? 'changes apply everywhere this measurement is used.' : 'linked to this line item once saved.' }}
            </p>

            <div v-if="loading" class="py-10 text-center text-sm text-gray-500">
                <i class="pi pi-spin pi-spinner mr-2"></i> Loading measurement…
            </div>

            <div v-show="!loading" class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
                <div>
                    <InputLabel for="qm_type" value="Garment Type *" />
                    <select id="qm_type" v-model="form.garment_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-600 focus:ring-brand-600 text-sm" required>
                        <option v-for="t in garmentTypes" :key="t.slug" :value="t.slug">{{ t.name }}</option>
                    </select>
                    <InputError :message="errors.garment_type?.[0]" class="mt-1" />
                </div>
                <div>
                    <InputLabel for="qm_label" value="Label / Occasion" />
                    <TextInput id="qm_label" v-model="form.label" type="text" class="mt-1 block w-full" placeholder="e.g. 2026 Eid Kanzu" />
                    <InputError :message="errors.label?.[0]" class="mt-1" />
                </div>
                <div>
                    <InputLabel for="qm_date" value="Date Taken *" />
                    <TextInput id="qm_date" v-model="form.date_taken" type="date" class="mt-1 block w-full" required />
                    <InputError :message="errors.date_taken?.[0]" class="mt-1" />
                </div>
            </div>

            <div v-show="!loading" class="rounded-lg border border-gray-200 p-4 mb-2 bg-gray-50/50">
                <DynamicGarmentFields
                    v-if="form.garment_type"
                    :garment-type="form.garment_type"
                    v-model="form.values"
                    :unit="form.unit"
                />
                <div v-else class="text-sm text-gray-400 text-center py-4">
                    Select a garment type to enter measurements.
                </div>
            </div>
            <InputError :message="errors.values?.[0]" class="mb-3" />

            <div v-show="!loading" class="mb-5">
                <InputLabel for="qm_notes" value="Notes" />
                <textarea id="qm_notes" v-model="form.notes" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-600 focus:ring-brand-600 text-sm" placeholder="Style preferences, fit notes, etc."></textarea>
                <InputError :message="errors.notes?.[0]" class="mt-1" />
            </div>

            <div class="flex justify-end gap-3">
                <button type="button" @click="emit('close')" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                    Cancel
                </button>
                <button type="submit" :disabled="processing || loading || !hasValues" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50 transition-colors">
                    <i class="pi pi-check text-xs mr-1"></i>
                    {{ processing ? 'Saving...' : (isEdit ? 'Update Measurement' : 'Save Measurement') }}
                </button>
            </div>
        </form>
    </Modal>
</template>
