<script setup>
import { Link } from '@inertiajs/vue3';
import { useGarmentTypes } from '@/composables/useGarmentTypes';

const props = defineProps({
    contact: Object,
});

defineEmits(['edit', 'delete']);

const { getColor, getLabel } = useGarmentTypes();

const relationshipLabels = {
    self: 'Self',
    son: 'Son',
    daughter: 'Daughter',
    brother: 'Brother',
    sister: 'Sister',
    wife: 'Wife',
    husband: 'Husband',
    employee: 'Employee',
    other: 'Other',
};

const genderIcons = {
    male: 'pi-mars',
    female: 'pi-venus',
};

function garmentSlugs(contact) {
    if (!contact.measurements?.length) return [];
    return [...new Set(contact.measurements.map(m => m.garment_type))];
}
</script>

<template>
    <div class="rounded-xl bg-white shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
        <div class="p-5">
            <div class="flex items-start justify-between">
                <Link :href="route('contacts.show', contact.id)" class="flex items-start gap-3 group flex-1 min-w-0">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-brand-100 text-brand-700 font-bold text-sm">
                        {{ contact.name.charAt(0).toUpperCase() }}
                    </div>
                    <div class="min-w-0">
                        <h3 class="font-semibold text-gray-900 group-hover:text-brand-600 transition-colors truncate">{{ contact.name }}</h3>
                        <div class="flex items-center gap-2 mt-0.5">
                            <span v-if="contact.relationship" class="text-xs text-gray-500 capitalize">{{ relationshipLabels[contact.relationship] || contact.relationship }}</span>
                            <i v-if="contact.gender" :class="['pi text-xs text-gray-400', genderIcons[contact.gender]]"></i>
                            <span v-if="contact.age_group" class="text-xs text-gray-400 capitalize">{{ contact.age_group }}</span>
                        </div>
                    </div>
                </Link>
                <div class="flex gap-1 shrink-0 ml-2">
                    <button @click="$emit('edit')" class="p-1.5 text-gray-400 hover:text-blue-600 rounded-lg hover:bg-blue-50 transition-colors" title="Edit">
                        <i class="pi pi-pencil text-xs"></i>
                    </button>
                    <button @click="$emit('delete')" class="p-1.5 text-gray-400 hover:text-red-600 rounded-lg hover:bg-red-50 transition-colors" title="Delete">
                        <i class="pi pi-trash text-xs"></i>
                    </button>
                </div>
            </div>

            <!-- Phone -->
            <div v-if="contact.phone" class="mt-3 flex items-center gap-1.5 text-sm text-gray-500">
                <i class="pi pi-phone text-xs"></i>
                <span>{{ contact.phone }}</span>
            </div>

            <!-- Measurements summary -->
            <div class="mt-3 flex items-center justify-between">
                <div class="flex flex-wrap gap-1.5">
                    <span
                        v-for="slug in garmentSlugs(contact)"
                        :key="slug"
                        :class="[getColor(slug).bg, getColor(slug).text]"
                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                    >
                        {{ getLabel(slug) }}
                    </span>
                    <span v-if="!contact.measurements?.length" class="text-xs text-gray-400 italic">No measurements</span>
                </div>
                <span class="inline-flex items-center gap-1 text-xs text-gray-500">
                    <i class="pi pi-sliders-h text-xs"></i>
                    {{ contact.measurements?.length || 0 }}
                </span>
            </div>
        </div>
    </div>
</template>
