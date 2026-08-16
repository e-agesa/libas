<script setup>
import { ref, watch } from 'vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    show: Boolean,
    clientId: [Number, String],
    clientName: String,
});

const emit = defineEmits(['close', 'created']);

const blank = () => ({
    name: '',
    relationship: 'self',
    phone: '',
    gender: 'male',
    age_group: 'adult',
    notes: '',
});

const form = ref(blank());
const errors = ref({});
const processing = ref(false);

watch(() => props.show, (val) => {
    if (val) {
        form.value = blank();
        errors.value = {};
    }
});

async function submit() {
    if (processing.value) return;
    processing.value = true;
    errors.value = {};
    try {
        const { data } = await window.axios.post(route('contacts.store', props.clientId), form.value);
        emit('created', data);
    } catch (err) {
        if (err.response?.status === 422) {
            errors.value = err.response.data.errors || {};
        } else {
            errors.value = { name: ['Could not save the person. Please try again.'] };
        }
    } finally {
        processing.value = false;
    }
}

const relationships = [
    { value: 'self', label: 'Self' },
    { value: 'son', label: 'Son' },
    { value: 'daughter', label: 'Daughter' },
    { value: 'brother', label: 'Brother' },
    { value: 'sister', label: 'Sister' },
    { value: 'wife', label: 'Wife' },
    { value: 'husband', label: 'Husband' },
    { value: 'employee', label: 'Employee' },
    { value: 'other', label: 'Other' },
];
</script>

<template>
    <Modal :show="show" @close="emit('close')" max-width="lg">
        <form @submit.prevent="submit" class="p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-1">Add New Person</h3>
            <p v-if="clientName" class="text-sm text-gray-500 mb-4">
                Under client <span class="font-medium text-gray-700">{{ clientName }}</span> — available immediately in this invoice.
            </p>

            <div class="space-y-4">
                <div>
                    <InputLabel for="qp_name" value="Full Name *" />
                    <TextInput id="qp_name" v-model="form.name" type="text" class="mt-1 block w-full" required autofocus placeholder="Person's full name" />
                    <InputError :message="errors.name?.[0]" class="mt-1" />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="qp_relationship" value="Relationship" />
                        <select id="qp_relationship" v-model="form.relationship" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-600 focus:ring-brand-600 text-sm">
                            <option v-for="r in relationships" :key="r.value" :value="r.value">{{ r.label }}</option>
                        </select>
                        <InputError :message="errors.relationship?.[0]" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="qp_phone" value="Phone" />
                        <TextInput id="qp_phone" v-model="form.phone" type="tel" class="mt-1 block w-full" placeholder="Optional" />
                        <InputError :message="errors.phone?.[0]" class="mt-1" />
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="qp_gender" value="Gender" />
                        <select id="qp_gender" v-model="form.gender" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-600 focus:ring-brand-600 text-sm">
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                        </select>
                        <InputError :message="errors.gender?.[0]" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="qp_age_group" value="Age Group" />
                        <select id="qp_age_group" v-model="form.age_group" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-600 focus:ring-brand-600 text-sm">
                            <option value="child">Child</option>
                            <option value="teen">Teen</option>
                            <option value="adult">Adult</option>
                            <option value="senior">Senior</option>
                        </select>
                        <InputError :message="errors.age_group?.[0]" class="mt-1" />
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <button type="button" @click="emit('close')" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                    Cancel
                </button>
                <button type="submit" :disabled="processing" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700 disabled:opacity-50 transition-colors">
                    <i class="pi pi-user-plus text-xs mr-1"></i>
                    {{ processing ? 'Saving...' : 'Save Person' }}
                </button>
            </div>
        </form>
    </Modal>
</template>
