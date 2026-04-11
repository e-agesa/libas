<script setup>
import { useForm } from '@inertiajs/vue3';
import { watch, ref } from 'vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    show: Boolean,
    client: Object,
});

const emit = defineEmits(['close']);

const form = useForm({
    name: '',
    phone: '',
    alt_phone: '',
    email: '',
    address: '',
    type: 'individual',
    notes: '',
});

watch(() => props.show, (val) => {
    if (val && props.client) {
        form.name = props.client.name || '';
        form.phone = props.client.phone || '';
        form.alt_phone = props.client.alt_phone || '';
        form.email = props.client.email || '';
        form.address = props.client.address || '';
        form.type = props.client.type || 'individual';
        form.notes = props.client.notes || '';
    } else if (val) {
        form.reset();
    }
});

const isEditing = ref(false);
watch(() => props.client, (val) => {
    isEditing.value = !!val;
});

function submit() {
    if (props.client?.id) {
        form.put(route('clients.update', props.client.id), {
            onSuccess: () => emit('close'),
            preserveScroll: true,
        });
    } else {
        form.post(route('clients.store'), {
            onSuccess: () => emit('close'),
        });
    }
}

const clientTypes = [
    { value: 'individual', label: 'Individual' },
    { value: 'family', label: 'Family' },
    { value: 'corporate', label: 'Corporate' },
    { value: 'institution', label: 'Institution' },
];
</script>

<template>
    <Modal :show="show" @close="emit('close')" max-width="lg">
        <form @submit.prevent="submit" class="p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">
                {{ client ? 'Edit Client' : 'Add New Client' }}
            </h3>

            <div class="space-y-4">
                <!-- Name -->
                <div>
                    <InputLabel for="name" value="Full Name *" />
                    <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" required autofocus placeholder="Client's full name" />
                    <InputError :message="form.errors.name" class="mt-1" />
                </div>

                <!-- Phone numbers -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="phone" value="Phone Number *" />
                        <TextInput id="phone" v-model="form.phone" type="tel" class="mt-1 block w-full" required placeholder="+254..." />
                        <InputError :message="form.errors.phone" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="alt_phone" value="Alt. Phone" />
                        <TextInput id="alt_phone" v-model="form.alt_phone" type="tel" class="mt-1 block w-full" placeholder="Optional" />
                        <InputError :message="form.errors.alt_phone" class="mt-1" />
                    </div>
                </div>

                <!-- Email & Type -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="email" value="Email" />
                        <TextInput id="email" v-model="form.email" type="email" class="mt-1 block w-full" placeholder="Optional" />
                        <InputError :message="form.errors.email" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="type" value="Client Type" />
                        <select id="type" v-model="form.type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-600 focus:ring-brand-600 text-sm">
                            <option v-for="t in clientTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                        </select>
                        <InputError :message="form.errors.type" class="mt-1" />
                    </div>
                </div>

                <!-- Address -->
                <div>
                    <InputLabel for="address" value="Address" />
                    <textarea id="address" v-model="form.address" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-600 focus:ring-brand-600 text-sm" placeholder="Physical or delivery address"></textarea>
                    <InputError :message="form.errors.address" class="mt-1" />
                </div>

                <!-- Notes -->
                <div>
                    <InputLabel for="notes" value="Notes" />
                    <textarea id="notes" v-model="form.notes" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-600 focus:ring-brand-600 text-sm" placeholder="General notes about this client"></textarea>
                    <InputError :message="form.errors.notes" class="mt-1" />
                </div>
            </div>

            <!-- Actions -->
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" @click="emit('close')" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                    Cancel
                </button>
                <button type="submit" :disabled="form.processing" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50 transition-colors">
                    {{ form.processing ? 'Saving...' : (client ? 'Update Client' : 'Save Client') }}
                </button>
            </div>
        </form>
    </Modal>
</template>
