<script setup>
import { ref, computed, nextTick } from 'vue';

/**
 * Picking a shelf item used to be a plain dropdown, where typing only jumps to
 * the first letter — useless against hundreds of products. This searches the
 * whole catalogue as you type: name, code/SKU, colour, size, design and
 * category all match, in any order and anywhere in the word.
 *
 * A product that has variations is offered as its variations, never as the
 * product itself: "White Topi" is not a thing you can sell, "White Topi · 21.5
 * · Design 2" is — and it carries its own price and its own stock.
 */
const props = defineProps({
    collections: { type: Array, default: () => [] },
    modelValue: { type: [Number, String, null], default: null },
    variantId: { type: [Number, String, null], default: null },
});

const emit = defineEmits(['update:modelValue', 'select']);

const open = ref(false);
const search = ref('');
const box = ref(null);

/** Every individually sellable thing: a variation, or a plain product. */
const entries = computed(() => {
    const out = [];

    for (const c of props.collections) {
        const variants = (c.variants || []).filter(v => v.status !== 'inactive');
        const meaningful = variants.filter(v => v.size || v.color || v.design);

        if (variants.length > 1 || meaningful.length) {
            for (const v of variants) {
                const label = [v.size, v.color, v.design].filter(Boolean).join(' · ') || 'Standard';
                out.push({
                    key: 'v' + v.id,
                    id: c.id,
                    variant_id: v.id,
                    name: c.name,
                    variant_label: label,
                    sku: v.sku || c.sku,
                    size: v.size, color: v.color, design: v.design,
                    price: v.price != null ? v.price : c.price,
                    stock_qty: v.stock_qty,
                    image_url: v.image_url || c.image_url,
                    category: c.category,
                });
            }
        } else {
            out.push({
                key: 'c' + c.id,
                id: c.id,
                variant_id: variants.length === 1 ? variants[0].id : null,
                name: c.name,
                variant_label: '',
                sku: c.sku, size: c.size, color: c.color, design: null,
                price: c.price,
                stock_qty: c.stock_qty,
                image_url: c.image_url,
                category: c.category,
            });
        }
    }

    return out;
});

const selected = computed(() => {
    if (!props.modelValue) return null;
    const cid = parseInt(props.modelValue);
    const vid = props.variantId ? parseInt(props.variantId) : null;

    return entries.value.find(e => e.id === cid && (e.variant_id ?? null) === vid)
        || entries.value.find(e => e.id === cid)
        || null;
});

function haystack(e) {
    return [e.name, e.sku, e.color, e.size, e.design, e.category?.name]
        .filter(Boolean).join(' ').toLowerCase();
}

const matches = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return entries.value.slice(0, 60);
    // every word must appear somewhere, so "white 21.5" narrows properly
    const words = q.split(/\s+/);
    return entries.value
        .filter(e => { const h = haystack(e); return words.every(w => h.includes(w)); })
        .slice(0, 60);
});

function label(e) {
    const bits = [e.name];
    if (e.variant_label) {
        bits.push(e.variant_label);
    } else {
        if (e.size) bits.push(e.size);
        if (e.color) bits.push(e.color);
    }
    return bits.join(' · ');
}

function choose(e) {
    emit('update:modelValue', e.id);
    emit('select', e);
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
                <span v-if="selected.variant_label" class="font-medium text-blue-600">
                    — {{ selected.variant_label }}
                </span>
                <span v-else-if="selected.size || selected.color" class="text-gray-500">
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
                    placeholder="Type any part of the name, code, colour, size or design…"
                    class="w-full border-0 p-0 text-sm focus:ring-0"
                    @keydown.esc="open = false"
                    @keydown.enter.prevent="matches.length === 1 && choose(matches[0])"
                />
                <button type="button" @click="open = false" class="text-gray-400 hover:text-gray-600">
                    <i class="pi pi-times text-xs"></i>
                </button>
            </div>

            <ul class="max-h-64 overflow-y-auto py-1">
                <li v-for="e in matches" :key="e.key">
                    <button
                        type="button"
                        @click="choose(e)"
                        :disabled="Number(e.stock_qty) <= 0"
                        class="w-full px-3 py-2 text-left text-sm hover:bg-blue-50 flex items-start gap-2 disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-transparent"
                    >
                        <img v-if="e.image_url" :src="e.image_url" :alt="e.name" loading="lazy"
                             class="h-8 w-8 shrink-0 rounded object-cover border border-gray-200" />
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-medium text-gray-900">{{ label(e) }}</span>
                            <span class="block text-xs text-gray-500">
                                <span v-if="e.sku" class="font-mono">{{ e.sku }}</span>
                                <span v-if="e.category"> · {{ e.category.name }}</span>
                                · KES {{ Number(e.price).toLocaleString() }}
                                ·
                                <span :class="Number(e.stock_qty) <= 0 ? 'font-medium text-red-500' : ''">
                                    {{ Number(e.stock_qty) <= 0 ? 'out of stock' : e.stock_qty + ' in stock' }}
                                </span>
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
