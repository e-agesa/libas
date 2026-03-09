<script setup>
import { useGarmentTypes } from '@/composables/useGarmentTypes';

defineProps({
    measurement: Object,
    revisionCount: { type: Number, default: 0 },
    isLatest: { type: Boolean, default: true },
});

defineEmits(['edit', 'delete', 'new-revision', 'show-history']);

const { getColor, getFields, getLabel } = useGarmentTypes();

function formatDate(dateStr) {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('en-KE', { day: 'numeric', month: 'short', year: 'numeric' });
}
</script>

<template>
    <div :class="['rounded-xl bg-white shadow-sm border hover:shadow-md transition-shadow', getColor(measurement.garment_type).border]">
        <div class="p-4">
            <!-- Header -->
            <div class="flex items-start justify-between mb-3">
                <div>
                    <div class="flex items-center gap-2">
                        <span
                            :class="[getColor(measurement.garment_type).bg, getColor(measurement.garment_type).text]"
                            class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                        >
                            {{ getLabel(measurement.garment_type) }}
                        </span>
                        <h4 class="font-semibold text-gray-900 text-sm">{{ measurement.label || getLabel(measurement.garment_type) }}</h4>
                    </div>
                    <div class="flex items-center gap-1.5 mt-0.5">
                        <p class="text-xs text-gray-400">
                            {{ formatDate(measurement.date_taken) }}
                            <span v-if="measurement.measured_by_user"> &middot; {{ measurement.measured_by_user.name }}</span>
                        </p>
                        <span v-if="measurement.revision > 1" class="inline-flex items-center rounded-full bg-amber-100 text-amber-700 px-1.5 py-0.5 text-[10px] font-semibold">
                            Rev {{ measurement.revision }}
                        </span>
                        <button
                            v-if="revisionCount > 1"
                            @click="$emit('show-history')"
                            class="inline-flex items-center gap-0.5 rounded-full bg-blue-50 text-blue-600 px-1.5 py-0.5 text-[10px] font-medium hover:bg-blue-100 transition-colors"
                            title="View revision history"
                        >
                            <i class="pi pi-history text-[9px]"></i> {{ revisionCount }}
                        </button>
                    </div>
                </div>
                <div class="flex items-center gap-1">
                    <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 uppercase">{{ measurement.unit }}</span>
                    <button @click="$emit('new-revision')" class="p-1.5 text-gray-400 hover:text-amber-600 rounded-lg hover:bg-amber-50 transition-colors" title="New Revision">
                        <i class="pi pi-plus-circle text-xs"></i>
                    </button>
                    <button @click="$emit('edit')" class="p-1.5 text-gray-400 hover:text-blue-600 rounded-lg hover:bg-blue-50 transition-colors" title="Edit">
                        <i class="pi pi-pencil text-xs"></i>
                    </button>
                    <button @click="$emit('delete')" class="p-1.5 text-gray-400 hover:text-red-600 rounded-lg hover:bg-red-50 transition-colors" title="Delete">
                        <i class="pi pi-trash text-xs"></i>
                    </button>
                </div>
            </div>

            <!-- Values grid — dynamic from field definitions -->
            <div v-if="getFields(measurement.garment_type).length" class="grid grid-cols-2 gap-x-4 gap-y-1">
                <template v-for="field in getFields(measurement.garment_type)" :key="field.slug">
                    <div v-if="measurement.values?.[field.slug] != null" class="flex justify-between items-baseline py-0.5">
                        <span class="text-xs text-gray-500">{{ field.name }}</span>
                        <span class="text-sm font-medium text-gray-900">
                            {{ measurement.values[field.slug] }}
                            <span class="text-xs text-gray-400 ml-0.5">{{ measurement.unit }}</span>
                        </span>
                    </div>
                </template>
            </div>

            <!-- Fallback: show raw values if no field definitions (e.g. custom type with no fields set) -->
            <div v-else class="grid grid-cols-2 gap-x-4 gap-y-1">
                <template v-for="(val, key) in (measurement.values || {})" :key="key">
                    <div v-if="val != null" class="flex justify-between items-baseline py-0.5">
                        <span class="text-xs text-gray-500 capitalize">{{ String(key).replace(/_/g, ' ') }}</span>
                        <span class="text-sm font-medium text-gray-900">
                            {{ val }}
                            <span class="text-xs text-gray-400 ml-0.5">{{ measurement.unit }}</span>
                        </span>
                    </div>
                </template>
            </div>

            <!-- Notes -->
            <p v-if="measurement.notes" class="mt-3 text-xs text-gray-500 border-t border-gray-100 pt-2">
                <i class="pi pi-info-circle mr-1"></i>{{ measurement.notes }}
            </p>
        </div>
    </div>
</template>
