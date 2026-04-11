<script setup>
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    show: Boolean,
    category: Object,
    categories: Array,
});

const emit = defineEmits(['close', 'edit', 'delete']);

const form = useForm({
    name: '',
    description: '',
});

watch(() => props.show, (val) => {
    if (val && props.category) {
        form.name = props.category.name || '';
        form.description = props.category.description || '';
    } else if (val) {
        form.reset();
    }
});

function submit() {
    if (props.category?.id) {
        form.put(route('collection-categories.update', props.category.id), {
            onSuccess: () => { form.reset(); emit('close'); },
            preserveScroll: true,
        });
    } else {
        form.post(route('collection-categories.store'), {
            onSuccess: () => form.reset(),
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <Modal :show="show" @close="emit('close')" max-width="lg">
        <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">
                {{ category ? 'Edit Category' : 'Manage Categories' }}
            </h3>

            <!-- Existing categories list -->
            <div v-if="!category && categories?.length" class="mb-6">
                <div class="space-y-2">
                    <div v-for="cat in categories" :key="cat.id" class="flex items-center justify-between rounded-lg border border-gray-200 px-3 py-2">
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ cat.name }}</p>
                            <p v-if="cat.description" class="text-xs text-gray-500">{{ cat.description }}</p>
                        </div>
                        <div class="flex gap-1">
                            <button @click="emit('edit', cat)" class="p-1.5 text-gray-400 hover:text-blue-600 rounded hover:bg-blue-50"><i class="pi pi-pencil text-xs"></i></button>
                            <button @click="emit('delete', cat)" class="p-1.5 text-gray-400 hover:text-red-600 rounded hover:bg-red-50"><i class="pi pi-trash text-xs"></i></button>
                        </div>
                    </div>
                </div>
                <hr class="my-4" />
                <p class="text-sm font-medium text-gray-700 mb-2">Add New Category</p>
            </div>

            <form @submit.prevent="submit" class="space-y-3">
                <div>
                    <InputLabel for="cat_name" value="Category Name *" />
                    <TextInput id="cat_name" v-model="form.name" type="text" class="mt-1 block w-full" placeholder="e.g. Ready-made Kanzus" required />
                    <InputError :message="form.errors.name" class="mt-1" />
                </div>
                <div>
                    <InputLabel for="cat_desc" value="Description" />
                    <TextInput id="cat_desc" v-model="form.description" type="text" class="mt-1 block w-full" placeholder="Optional description" />
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" @click="emit('close')" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Close</button>
                    <button type="submit" :disabled="form.processing" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50">
                        {{ form.processing ? 'Saving...' : (category ? 'Update' : 'Add Category') }}
                    </button>
                </div>
            </form>
        </div>
    </Modal>
</template>
