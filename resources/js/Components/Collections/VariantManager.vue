<script setup>
import { ref, computed, watch } from 'vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    show: Boolean,
    collection: Object,
});

const emit = defineEmits(['close', 'saved']);

const loading = ref(false);
const saving = ref(false);
const error = ref('');
const variants = ref([]);
const images = ref([]);
const uploadTarget = ref('');        // '' = the product itself, or a variant id
const uploading = ref(false);

const blankRow = () => ({
    id: null, size: '', color: '', design: '',
    price: '', stock_qty: 0, low_stock_threshold: 5, status: 'active',
    _dirty: false, _saving: false,
});

const draft = ref(blankRow());

watch(() => props.show, async (open) => {
    if (!open) return;
    error.value = '';
    draft.value = blankRow();
    uploadTarget.value = '';
    await load();
});

async function load() {
    loading.value = true;
    try {
        const { data } = await window.axios.get(route('collections.variants', props.collection.id));
        variants.value = (data.variants || []).map(v => ({
            ...v,
            price: v.price ?? '',
            _dirty: false,
            _saving: false,
        }));
        images.value = data.images || [];
    } catch (e) {
        error.value = 'Could not load the variations. Close and try again.';
    } finally {
        loading.value = false;
    }
}

const totalStock = computed(() =>
    variants.value.reduce((n, v) => n + (parseInt(v.stock_qty) || 0), 0)
);

// Photos belonging to one variation, so a design shows its own pictures
function imagesFor(variantId) {
    return images.value.filter(i => i.collection_variant_id === variantId);
}
const productImages = computed(() => images.value.filter(i => !i.collection_variant_id));

function payload(v) {
    return {
        size: v.size || null,
        color: v.color || null,
        design: v.design || null,
        price: v.price === '' || v.price === null ? null : parseFloat(v.price),
        stock_qty: parseInt(v.stock_qty) || 0,
        low_stock_threshold: parseInt(v.low_stock_threshold) || 0,
        status: v.status || 'active',
    };
}

async function addVariant() {
    if (saving.value) return;
    const d = draft.value;
    if (!d.size && !d.color && !d.design) {
        error.value = 'Give the variation at least a size, a colour or a design.';
        return;
    }
    saving.value = true;
    error.value = '';
    try {
        const { data } = await window.axios.post(
            route('collections.variants.store', props.collection.id), payload(d)
        );
        variants.value.push({ ...data, price: data.price ?? '', _dirty: false, _saving: false });
        draft.value = blankRow();
        emit('saved');
    } catch (e) {
        error.value = e.response?.data?.message || 'Could not add that variation.';
    } finally {
        saving.value = false;
    }
}

async function saveVariant(v) {
    v._saving = true;
    error.value = '';
    try {
        await window.axios.put(route('collection-variants.update', v.id), payload(v));
        v._dirty = false;
        emit('saved');
    } catch (e) {
        error.value = e.response?.data?.message || 'Could not save that variation.';
    } finally {
        v._saving = false;
    }
}

async function removeVariant(v) {
    if (!window.confirm(`Remove the variation "${[v.size, v.color, v.design].filter(Boolean).join(' · ') || 'Standard'}"? Its stock count goes with it.`)) return;
    try {
        await window.axios.delete(route('collection-variants.destroy', v.id));
        variants.value = variants.value.filter(x => x.id !== v.id);
        images.value = images.value.filter(i => i.collection_variant_id !== v.id);
        emit('saved');
    } catch (e) {
        error.value = 'Could not remove that variation.';
    }
}

async function uploadImages(event) {
    const files = Array.from(event.target.files || []);
    if (!files.length) return;
    uploading.value = true;
    error.value = '';
    const form = new FormData();
    files.forEach(f => form.append('images[]', f));
    if (uploadTarget.value) form.append('collection_variant_id', uploadTarget.value);
    try {
        const { data } = await window.axios.post(
            route('collections.images.store', props.collection.id), form
        );
        images.value.push(...data);
        emit('saved');
    } catch (e) {
        error.value = e.response?.data?.message || 'Could not upload those photos.';
    } finally {
        uploading.value = false;
        event.target.value = '';
    }
}

async function removeImage(img) {
    if (!window.confirm('Delete this photo?')) return;
    try {
        await window.axios.delete(route('collection-images.destroy', img.id));
        images.value = images.value.filter(i => i.id !== img.id);
        emit('saved');
    } catch (e) {
        error.value = 'Could not delete that photo.';
    }
}

async function makePrimary(img) {
    try {
        await window.axios.post(route('collection-images.primary', img.id));
        images.value.forEach(i => { i.is_primary = i.id === img.id; });
        emit('saved');
    } catch (e) {
        error.value = 'Could not set the main photo.';
    }
}

function label(v) {
    return [v.size, v.color, v.design].filter(Boolean).join(' · ') || 'Standard';
}
</script>

<template>
    <Modal :show="show" @close="emit('close')" max-width="2xl">
        <div class="p-6">
            <div class="flex items-start justify-between gap-3 mb-1">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Variations &amp; Photos</h3>
                    <p class="text-sm text-gray-500">
                        {{ collection?.name }} — one product, many variations. Size, colour and design each vary it,
                        and every variation keeps its own stock.
                    </p>
                </div>
                <div class="text-right shrink-0">
                    <div class="text-lg font-semibold text-gray-900">{{ totalStock }}</div>
                    <div class="text-[11px] uppercase tracking-wide text-gray-400">total stock</div>
                </div>
            </div>

            <p v-if="error" class="mt-3 rounded-md bg-red-50 border border-red-200 px-3 py-2 text-sm text-red-700">{{ error }}</p>

            <div v-if="loading" class="py-10 text-center text-sm text-gray-500">
                <i class="pi pi-spin pi-spinner mr-2"></i> Loading variations…
            </div>

            <template v-else>
                <!-- existing variations -->
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-sm min-w-[640px]">
                        <thead>
                            <tr class="text-left text-[11px] uppercase tracking-wide text-gray-500 border-b border-gray-200">
                                <th class="py-2 pr-2">Size</th>
                                <th class="py-2 pr-2">Colour</th>
                                <th class="py-2 pr-2">Design</th>
                                <th class="py-2 pr-2 w-24">Price</th>
                                <th class="py-2 pr-2 w-20">Stock</th>
                                <th class="py-2 pr-2 w-24">Photos</th>
                                <th class="py-2 w-24"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="v in variants" :key="v.id" class="border-b border-gray-100">
                                <td class="py-2 pr-2"><input v-model="v.size" @input="v._dirty = true" type="text" placeholder="21.5" class="w-full rounded-md border-gray-300 text-sm" /></td>
                                <td class="py-2 pr-2"><input v-model="v.color" @input="v._dirty = true" type="text" placeholder="White" class="w-full rounded-md border-gray-300 text-sm" /></td>
                                <td class="py-2 pr-2"><input v-model="v.design" @input="v._dirty = true" type="text" placeholder="Design 1" class="w-full rounded-md border-gray-300 text-sm" /></td>
                                <td class="py-2 pr-2"><input v-model="v.price" @input="v._dirty = true" type="number" min="0" step="any" :placeholder="collection?.price" class="w-full rounded-md border-gray-300 text-sm" /></td>
                                <td class="py-2 pr-2"><input v-model="v.stock_qty" @input="v._dirty = true" type="number" min="0" class="w-full rounded-md border-gray-300 text-sm" /></td>
                                <td class="py-2 pr-2 text-gray-500">{{ imagesFor(v.id).length }}</td>
                                <td class="py-2 text-right whitespace-nowrap">
                                    <button v-if="v._dirty" @click="saveVariant(v)" :disabled="v._saving"
                                        class="rounded-md bg-brand-600 px-2 py-1 text-xs font-medium text-white hover:bg-brand-700 disabled:opacity-50">
                                        {{ v._saving ? 'Saving…' : 'Save' }}
                                    </button>
                                    <button @click="removeVariant(v)" class="ml-1 p-1 text-gray-400 hover:text-red-600" title="Remove variation">
                                        <i class="pi pi-trash text-xs"></i>
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="!variants.length">
                                <td colspan="7" class="py-4 text-center text-sm text-gray-400">No variations yet — add the first below.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- add a variation -->
                <div class="mt-4 rounded-lg border border-dashed border-brand-300 bg-brand-50/40 p-3">
                    <p class="text-xs font-semibold text-brand-800 mb-2">Add a variation</p>
                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-2">
                        <input v-model="draft.size" type="text" placeholder="Size (21.5)" class="rounded-md border-gray-300 text-sm" />
                        <input v-model="draft.color" type="text" placeholder="Colour (White)" class="rounded-md border-gray-300 text-sm" />
                        <input v-model="draft.design" type="text" placeholder="Design 1" class="rounded-md border-gray-300 text-sm" />
                        <input v-model="draft.price" type="number" min="0" step="any" :placeholder="'Price (' + (collection?.price ?? '') + ')'" class="rounded-md border-gray-300 text-sm" />
                        <input v-model="draft.stock_qty" type="number" min="0" placeholder="Stock" class="rounded-md border-gray-300 text-sm" />
                    </div>
                    <div class="mt-2 flex items-center justify-between gap-2">
                        <p class="text-[11px] text-gray-500">Leave the price blank to use the product price.</p>
                        <button @click="addVariant" :disabled="saving"
                            class="rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-brand-700 disabled:opacity-50">
                            <i class="pi pi-plus text-[10px] mr-1"></i>{{ saving ? 'Adding…' : 'Add variation' }}
                        </button>
                    </div>
                </div>

                <!-- photos -->
                <div class="mt-5">
                    <div class="flex items-center justify-between gap-2 mb-2">
                        <p class="text-xs font-semibold text-gray-700">Photos</p>
                        <div class="flex items-center gap-2">
                            <select v-model="uploadTarget" class="rounded-md border-gray-300 text-xs py-1">
                                <option value="">Whole product</option>
                                <option v-for="v in variants" :key="v.id" :value="v.id">{{ label(v) }}</option>
                            </select>
                            <label class="cursor-pointer rounded-lg border border-brand-600 px-3 py-1.5 text-xs font-medium text-brand-600 hover:bg-brand-50">
                                <i class="pi pi-upload text-[10px] mr-1"></i>{{ uploading ? 'Uploading…' : 'Upload photos' }}
                                <input type="file" accept="image/*" multiple class="hidden" @change="uploadImages" :disabled="uploading" />
                            </label>
                        </div>
                    </div>
                    <p class="text-[11px] text-gray-500 mb-2">
                        Choose a variation before uploading to give that design its own pictures; otherwise they belong to the product.
                    </p>

                    <div v-if="images.length" class="grid grid-cols-3 sm:grid-cols-6 gap-2">
                        <figure v-for="img in images" :key="img.id" class="relative group rounded-lg overflow-hidden border border-gray-200 bg-gray-50">
                            <img :src="img.url" :alt="img.alt || collection?.name" class="w-full h-20 object-cover" />
                            <figcaption class="px-1 py-0.5 text-[10px] text-gray-500 truncate">
                                {{ img.collection_variant_id ? label(variants.find(v => v.id === img.collection_variant_id) || {}) : 'Product' }}
                            </figcaption>
                            <span v-if="img.is_primary" class="absolute top-1 left-1 rounded bg-brand-600 px-1 text-[9px] font-medium text-white">Main</span>
                            <div class="absolute top-1 right-1 hidden group-hover:flex gap-1">
                                <button v-if="!img.is_primary" @click="makePrimary(img)" class="rounded bg-white/90 p-1 text-gray-700 hover:text-brand-600" title="Make main photo">
                                    <i class="pi pi-star text-[10px]"></i>
                                </button>
                                <button @click="removeImage(img)" class="rounded bg-white/90 p-1 text-gray-700 hover:text-red-600" title="Delete photo">
                                    <i class="pi pi-trash text-[10px]"></i>
                                </button>
                            </div>
                        </figure>
                    </div>
                    <p v-else class="text-sm text-gray-400 text-center py-6 border border-dashed border-gray-300 rounded-lg">
                        No photos yet.
                    </p>
                </div>
            </template>

            <div class="mt-6 flex justify-end">
                <button type="button" @click="emit('close')"
                    class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Done
                </button>
            </div>
        </div>
    </Modal>
</template>
