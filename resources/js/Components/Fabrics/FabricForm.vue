<script setup>
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    show: Boolean,
    fabric: Object,
});

const emit = defineEmits(['close']);

const form = useForm({
    name: '',
    type: '',
    color: '',
    price_per_unit: '',
    stock_qty: 0,
    supplier: '',
    notes: '',
    status: 'active',
});

watch(() => props.show, (val) => {
    if (val && props.fabric) {
        form.name = props.fabric.name || '';
        form.type = props.fabric.type || '';
        form.color = props.fabric.color || '';
        form.price_per_unit = props.fabric.price_per_unit || '';
        form.stock_qty = props.fabric.stock_qty ?? 0;
        form.supplier = props.fabric.supplier || '';
        form.notes = props.fabric.notes || '';
        form.status = props.fabric.status || 'active';
    } else if (val) {
        form.reset();
    }
});

function submit() {
    if (props.fabric) {
        form.put(route('fabrics.update', props.fabric.id), {
            onSuccess: () => emit('close'),
            preserveScroll: true,
        });
    } else {
        form.post(route('fabrics.store'), {
            onSuccess: () => emit('close'),
        });
    }
}
</script>

<template>
    <Modal :show="show" @close="emit('close')" max-width="lg">
        <form @submit.prevent="submit" class="p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">
                {{ fabric ? 'Edit Fabric' : 'Add New Fabric' }}
            </h3>

            <div class="space-y-4">
                <!-- Name -->
                <div>
                    <InputLabel for="fab_name" value="Fabric Name *" />
                    <TextInput id="fab_name" v-model="form.name" type="text" class="mt-1 block w-full" required autofocus placeholder="e.g. White Cotton Poplin" />
                    <InputError :message="form.errors.name" class="mt-1" />
                </div>

                <!-- Type & Color -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="fab_type" value="Type" />
                        <TextInput id="fab_type" v-model="form.type" type="text" class="mt-1 block w-full" placeholder="e.g. Cotton, Linen, Silk" />
                        <InputError :message="form.errors.type" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="fab_color" value="Color" />
                        <TextInput id="fab_color" v-model="form.color" type="text" class="mt-1 block w-full" placeholder="e.g. White, Cream, Navy" />
                        <InputError :message="form.errors.color" class="mt-1" />
                    </div>
                </div>

                <!-- Price & Stock -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="fab_price" value="Price per Unit (KES) *" />
                        <TextInput id="fab_price" v-model="form.price_per_unit" type="number" min="0" step="50" class="mt-1 block w-full" required placeholder="0" />
                        <InputError :message="form.errors.price_per_unit" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="fab_stock" value="Stock Quantity *" />
                        <TextInput id="fab_stock" v-model="form.stock_qty" type="number" min="0" class="mt-1 block w-full" required />
                        <InputError :message="form.errors.stock_qty" class="mt-1" />
                    </div>
                </div>

                <!-- Supplier & Status -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="fab_supplier" value="Supplier" />
                        <TextInput id="fab_supplier" v-model="form.supplier" type="text" class="mt-1 block w-full" placeholder="Supplier name" />
                        <InputError :message="form.errors.supplier" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="fab_status" value="Status" />
                        <select id="fab_status" v-model="form.status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 text-sm">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <!-- Notes -->
                <div>
                    <InputLabel for="fab_notes" value="Notes" />
                    <textarea id="fab_notes" v-model="form.notes" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 text-sm" placeholder="Optional notes"></textarea>
                    <InputError :message="form.errors.notes" class="mt-1" />
                </div>
            </div>

            <!-- Actions -->
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" @click="emit('close')" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">Cancel</button>
                <button type="submit" :disabled="form.processing" class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50 transition-colors">
                    {{ form.processing ? 'Saving...' : (fabric ? 'Update Fabric' : 'Add Fabric') }}
                </button>
            </div>
        </form>
    </Modal>
</template>
