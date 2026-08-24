<script setup>
import { ref, watch } from 'vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    show: Boolean,
    // Whatever the operator had typed in the client search carries into the form,
    // so a first-time customer is not typed twice.
    prefillName: { type: String, default: '' },
});

const emit = defineEmits(['close', 'created']);

const blank = () => ({
    name: '',
    phone: '',
    alt_phone: '',
    email: '',
    address: '',
    type: 'individual',
    notes: '',
});

const form = ref(blank());
const errors = ref({});
const processing = ref(false);

watch(() => props.show, (val) => {
    if (!val) return;
    form.value = { ...blank(), name: props.prefillName || '' };
    errors.value = {};
});

async function submit() {
    if (processing.value) return;
    processing.value = true;
    errors.value = {};
    try {
        const { data } = await window.axios.post(route('clients.store'), form.value);
        emit('created', data);
    } catch (err) {
        if (err.response?.status === 422) {
            errors.value = err.response.data.errors || {};
        } else {
            errors.value = { name: ['Could not save the client. Please try again.'] };
        }
    } finally {
        processing.value = false;
    }
}

const types = [
    { value: 'individual', label: 'Individual' },
    { value: 'family', label: 'Family' },
    { value: 'corporate', label: 'Corporate' },
    { value: 'institution', label: 'Institution' },
];
</script>

<template>
    <Modal :show="show" @close="emit('close')" max-width="lg">
        <form @submit.prevent="submit" class="p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-1">Add New Client</h3>
            <p class="text-sm text-gray-500 mb-4">
                For a first-time customer — saved and selected straight away, without leaving this invoice.
            </p>

            <div class="space-y-4">
                <div>
                    <InputLabel for="qc_name" value="Client Name *" />
                    <TextInput id="qc_name" v-model="form.name" type="text" class="mt-1 block w-full" required autofocus placeholder="Full name or company name" />
                    <InputError :message="errors.name?.[0]" class="mt-1" />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="qc_phone" value="Phone *" />
                        <TextInput id="qc_phone" v-model="form.phone" type="tel" class="mt-1 block w-full" required placeholder="e.g. +254 7XX XXX XXX" />
                        <InputError :message="errors.phone?.[0]" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="qc_alt_phone" value="Alternate Phone" />
                        <TextInput id="qc_alt_phone" v-model="form.alt_phone" type="tel" class="mt-1 block w-full" placeholder="Optional" />
                        <InputError :message="errors.alt_phone?.[0]" class="mt-1" />
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="qc_email" value="Email" />
                        <TextInput id="qc_email" v-model="form.email" type="email" class="mt-1 block w-full" placeholder="Optional" />
                        <InputError :message="errors.email?.[0]" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="qc_type" value="Client Type" />
                        <select id="qc_type" v-model="form.type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-600 focus:ring-brand-600 text-sm">
                            <option v-for="t in types" :key="t.value" :value="t.value">{{ t.label }}</option>
                        </select>
                        <InputError :message="errors.type?.[0]" class="mt-1" />
                    </div>
                </div>

                <div>
                    <InputLabel for="qc_address" value="Address" />
                    <TextInput id="qc_address" v-model="form.address" type="text" class="mt-1 block w-full" placeholder="Optional" />
                    <InputError :message="errors.address?.[0]" class="mt-1" />
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <button type="button" @click="emit('close')" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                    Cancel
                </button>
                <button type="submit" :disabled="processing" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50 transition-colors">
                    <i class="pi pi-user-plus text-xs mr-1"></i>
                    {{ processing ? 'Saving...' : 'Save Client' }}
                </button>
            </div>

            <p class="text-xs text-gray-400 mt-3">
                People (family members) and their measurements are added on the next step.
            </p>
        </form>
    </Modal>
</template>
