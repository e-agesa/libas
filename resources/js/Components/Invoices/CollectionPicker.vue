<script setup>
import { ref, computed, nextTick } from 'vue';

/**
 * Picking a shelf item used to be a plain dropdown, where typing only jumps to
 * the first letter — useless against hundreds of products. This searches the
 * whole catalogue as you type: name, code/SKU, colour, size and category all
 * match, in any order and anywhere in the word.
 */
const props = defineProps({
    collections: { type: Array, default: () => [] },
    modelValue: { type: [Number, String, null], default: null },
});

const emit = defineEmits(['update:modelValue', 'select']);

const open = ref(false);
const search = ref('');
const box = ref(null);

const selected = computed(() =>
    props.collections.find(c => c.id === parseInt(props.modelValue)) || null
);

function haystack(c) {
    return [c.name, c.sku, c.color, c.size, c.category?.name]
        .filter(Boolean).join(' ').toLowerCase();
}

const matches = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return props.collections.slice(0, 60);
    // every word must appear somewhere, so "white 21.5" narrows properly
    const words = q.split(/\s+/);
    return props.collections
        .filter(c => { const h = haystack(c); return words.every(w => h.includes(w)); })
        .slice(0, 60);
});

function label(c) {
    const bits = [c.name];
    if (c.size) bits.push(c.size);
    if (c.color) bits.push(c.color);
    return bits.join(' · ');
}

function choose(c) {
    emit('update:modelValue', c.id);
    emit('select', c);
    open.value = false;
    search.value = '';
}

function clear() {
    emit('update:modelValue', null);
    emit('select', null);
}

async function openList() {
    open.value = true;
    await nextTick();
    box.value?.focus();
}
</script>

<template>
    <div class="relative">
        <!-- what is currently chosen -->
        <button
            v-if="!open"
            type="button"
            @click="openList"
            class="w-full flex items-center justify-between gap-2 rounded-md border border-gray-300 bg-white px-3 py-2 text-left text-sm hover:border-blue-400"
        >
            <span v-if="selected" class="truncate">
                <span class="font-medium text-gray-900">{{ selected.name }}</span>
                <span v-if="selected.size || selected.color" class="text-gray-500">
                    — {{ [selected.size, selected.color].filter(Boolean).join(' · ') }}
                </span>
                <span class="text-gray-400"> · KES {{ Number(selected.price).toLocaleString() }}</span>
            </span>
            <span v-else class="text-gray-400">Search for an item…</span>
            <i class="pi pi-search text-xs text-gray-400 shrink-0"></i>
        </button>

        <!-- the search itself -->
        <div v-else class="rounded-md border border-blue-400 bg-white shadow-sm">
            <div class="flex items-center gap-2 px-2 py-1.5 border-b border-gray-100">
                <i class="pi pi-search text-xs text-gray-400"></i>
                <input
                    ref="box"
                    v-model="search"
                    type="text"
                    placeholder="Type any part of the name, code, colour or size…"
                    class="w-full border-0 p-0 text-sm focus:ring-0"
                    @keydown.esc="open = false"
                    @keydown.enter.prevent="matches.length === 1 && choose(matches[0])"
                />
                <button type="button" @click="open = false" class="text-gray-400 hover:text-gray-600">
                    <i class="pi pi-times text-xs"></i>
                </button>
            </div>

            <ul class="max-h-64 overflow-y-auto py-1">
                <li v-for="c in matches" :key="c.id">
                    <button
                        type="button"
                        @click="choose(c)"
                        class="w-full px-3 py-2 text-left text-sm hover:bg-blue-50 flex items-start gap-2"
                    >
                        <img v-if="c.image_url" :src="c.image_url" :alt="c.name" loading="lazy"
                             class="h-8 w-8 shrink-0 rounded object-cover border border-gray-200" />
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-medium text-gray-900">{{ label(c) }}</span>
                            <span class="block text-xs text-gray-500">
                                <span v-if="c.sku" class="font-mono">{{ c.sku }}</span>
                                <span v-if="c.category"> · {{ c.category.name }}</span>
                                · KES {{ Number(c.price).toLocaleString() }}
                                · {{ c.stock_qty }} in stock
                            </span>
                        </span>
                    </button>
                </li>
                <li v-if="!matches.length" class="px-3 py-4 text-center text-sm text-gray-400">
                    Nothing matches “{{ search }}”.
                </li>
            </ul>
        </div>

        <button v-if="selected && !open" type="button" @click="clear"
                class="absolute -top-5 right-0 text-[11px] text-gray-400 hover:text-red-600">
            clear
        </button>
    </div>
</template>
