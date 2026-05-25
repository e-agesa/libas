<script setup>
import { useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    show: Boolean,
    item: Object,
    categories: Array,
});

const emit = defineEmits(['close']);

const imagePreview = ref(null);

const form = useForm({
    name: '',
    sku: '',
    category_id: '',
    description: '',
    image: null,
    size: '',
    color: '',
    price: 0,
    cost_price: 0,
    stock_qty: 0,
    low_stock_threshold: 5,
    status: 'active',
});

function onImageChange(e) {
    const file = e.target.files[0];
    if (file) {
        form.image = file;
        imagePreview.value = URL.createObjectURL(file);
    }
}

watch(() => props.show, (val) => {
    if (val && props.item) {
        form.name = props.item.name || '';
        form.sku = props.item.sku || '';
        form.category_id = props.item.category_id || '';
        form.description = props.item.description || '';
        form.image = null;
        imagePreview.value = props.item.image_url || null;
        form.size = props.item.size || '';
        form.color = props.item.color || '';
        form.price = props.item.price || 0;
        form.cost_price = props.item.cost_price || 0;
        form.stock_qty = props.item.stock_qty || 0;
        form.low_stock_threshold = props.item.low_stock_threshold || 5;
        form.status = props.item.status || 'active';
    } else if (val) {
        form.reset();
        form.image = null;
        imagePreview.value = null;
        form.status = 'active';
        form.low_stock_threshold = 5;
    }
});

function submit() {
    if (props.item?.id) {
        form.transform(data => ({ ...data, _method: 'PUT' }))
            .post(route('collections.update', props.item.id), {
                onSuccess: () => emit('close'),
                preserveScroll: true,
                forceFormData: true,
            });
    } else {
        form.post(route('collections.store'), {
            onSuccess: () => emit('close'),
            forceFormData: true,
        });
    }
}
</script>

<template>
    <Modal :show="show" @close="emit('close')" max-width="2xl">
        <form @submit.prevent="submit" class="p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">
                {{ item ? 'Edit Collection Item' : 'New Collection Item' }}
            </h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <InputLabel for="c_name" value="Item Name *" />
                    <TextInput id="c_name" v-model="form.name" type="text" class="mt-1 block w-full" placeholder="e.g. Ready-made White Kanzu" required />
                    <InputError :message="form.errors.name" class="mt-1" />
                </div>

                <div>
                    <InputLabel for="c_sku" value="SKU" />
                    <TextInput id="c_sku" v-model="form.sku" type="text" class="mt-1 block w-full" placeholder="e.g. KNZ-WHT-001" />
                    <InputError :message="form.errors.sku" class="mt-1" />
                </div>

                <div>
                    <InputLabel for="c_category" value="Category" />
                    <select id="c_category" v-model="form.category_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm focus:border-brand-600 focus:ring-brand-600">
                        <option value="">Uncategorized</option>
                        <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                    </select>
                    <InputError :message="form.errors.category_id" class="mt-1" />
                </div>

                <div class="sm:col-span-2">
                    <InputLabel for="c_desc" value="Description" />
                    <textarea id="c_desc" v-model="form.description" rows="2" class="mt-1 block w-full rounded-md border-gray-300 text-sm focus:border-brand-600 focus:ring-brand-600" placeholder="Brief description of the item"></textarea>
                    <InputError :message="form.errors.description" class="mt-1" />
                </div>

                <div class="sm:col-span-2">
                    <InputLabel for="c_image" value="Image" />
                    <div class="mt-1 flex items-center gap-4">
                        <div class="h-20 w-20 rounded-lg bg-gray-100 border border-gray-200 flex items-center justify-center overflow-hidden shrink-0">
                            <img v-if="imagePreview" :src="imagePreview" class="w-full h-full object-cover" />
                            <i v-else class="pi pi-image text-2xl text-gray-300"></i>
                        </div>
                        <div class="flex-1">
                            <input id="c_image" type="file" accept="image/*" @change="onImageChange" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-brand-50 file:text-brand-700 hover:file:bg-brand-100" />
                            <p class="text-xs text-gray-400 mt-1">JPG, PNG up to 2MB</p>
                        </div>
                    </div>
                    <InputError :message="form.errors.image" class="mt-1" />
                </div>

                <div>
                    <InputLabel for="c_size" value="Size" />
                    <TextInput id="c_size" v-model="form.size" type="text" class="mt-1 block w-full" placeholder="e.g. M, L, XL" />
                </div>

                <div>
                    <InputLabel for="c_color" value="Color" />
                    <TextInput id="c_color" v-model="form.color" type="text" class="mt-1 block w-full" placeholder="e.g. White, Black" />
                </div>

                <div>
                    <InputLabel for="c_price" value="Selling Price (KES) *" />
                    <TextInput id="c_price" v-model.number="form.price" type="number" min="0" step="any" class="mt-1 block w-full" required />
                    <InputError :message="form.errors.price" class="mt-1" />
                </div>

                <div>
                    <InputLabel for="c_cost" value="Cost Price (KES)" />
                    <TextInput id="c_cost" v-model.number="form.cost_price" type="number" min="0" step="any" class="mt-1 block w-full" />
                </div>

                <div>
                    <InputLabel for="c_stock" value="Stock Quantity *" />
                    <TextInput id="c_stock" v-model.number="form.stock_qty" type="number" min="0" class="mt-1 block w-full" required />
                    <InputError :message="form.errors.stock_qty" class="mt-1" />
                </div>

                <div>
                    <InputLabel for="c_threshold" value="Low Stock Threshold" />
                    <TextInput id="c_threshold" v-model.number="form.low_stock_threshold" type="number" min="0" class="mt-1 block w-full" />
                </div>

                <div>
                    <InputLabel for="c_status" value="Status" />
                    <select id="c_status" v-model="form.status" class="mt-1 block w-full rounded-md border-gray-300 text-sm focus:border-brand-600 focus:ring-brand-600">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <button type="button" @click="emit('close')" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                <button type="submit" :disabled="form.processing" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50">
                    {{ form.processing ? 'Saving...' : (item ? 'Update' : 'Save') }}
                </button>
            </div>
        </form>
    </Modal>
</template>
