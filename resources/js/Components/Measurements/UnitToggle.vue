<script setup>
const props = defineProps({
    modelValue: {
        type: String,
        default: 'cm',
    },
});

const emit = defineEmits(['update:modelValue']);

function toggle() {
    emit('update:modelValue', props.modelValue === 'cm' ? 'inches' : 'cm');
}
</script>

<template>
    <button type="button" @click="toggle" class="inline-flex items-center rounded-lg border border-gray-300 text-sm overflow-hidden">
        <span :class="['px-3 py-1.5 font-medium transition-colors', modelValue === 'cm' ? 'bg-green-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">cm</span>
        <span :class="['px-3 py-1.5 font-medium transition-colors', modelValue === 'inches' ? 'bg-green-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50']">in</span>
    </button>
</template>
