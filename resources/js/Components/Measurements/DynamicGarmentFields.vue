<script setup>
import { computed } from 'vue';
import { useGarmentTypes } from '@/composables/useGarmentTypes';

const props = defineProps({
    garmentType: { type: String, required: true },
    modelValue: { type: Object, default: () => ({}) },
    unit: { type: String, default: 'cm' },
});

const emit = defineEmits(['update:modelValue']);

const { getFields } = useGarmentTypes();

const fields = computed(() => getFields(props.garmentType));

function updateField(slug, value) {
    const updated = { ...props.modelValue };
    updated[slug] = value === '' ? null : parseFloat(value);
    emit('update:modelValue', updated);
}

function formatLabel(name) {
    return name;
}
</script>

<template>
    <div v-if="fields.length" class="grid grid-cols-2 gap-3">
        <div v-for="field in fields" :key="field.slug" class="space-y-1">
            <label :for="'field-' + field.slug" class="block text-xs font-medium text-gray-600">
                {{ formatLabel(field.name) }}
                <span class="text-gray-400">({{ unit }})</span>
            </label>
            <input
                :id="'field-' + field.slug"
                type="number"
                step="0.1"
                min="0"
                max="999"
                class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-brand-600 focus:ring-brand-600"
                :value="modelValue[field.slug] ?? ''"
                @input="updateField(field.slug, $event.target.value)"
                :placeholder="field.name"
            />
        </div>
    </div>
    <div v-else class="rounded-lg border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500">
        No fields defined for this garment type. Add fields in Settings.
    </div>
</template>
