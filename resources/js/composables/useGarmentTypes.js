import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function useGarmentTypes() {
    const garmentTypes = computed(() => usePage().props.garmentTypes || []);

    function getType(slug) {
        return garmentTypes.value.find(t => t.slug === slug) || null;
    }

    function getColor(slug) {
        const type = getType(slug);
        if (!type) {
            return { bg: 'bg-gray-100', text: 'text-gray-800', border: 'border-gray-200', dot: 'bg-gray-500' };
        }
        return type.tailwind_classes;
    }

    function getFields(slug) {
        const type = getType(slug);
        return type?.fields || [];
    }

    function getLabel(slug) {
        const type = getType(slug);
        return type?.name || slug;
    }

    function getHex(slug) {
        const type = getType(slug);
        return type?.color || '#6b7280';
    }

    return { garmentTypes, getType, getColor, getFields, getLabel, getHex };
}
