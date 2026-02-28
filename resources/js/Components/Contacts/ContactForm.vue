<script setup>
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    show: Boolean,
    clientId: [Number, String],
    contact: Object,
});

const emit = defineEmits(['close']);

const form = useForm({
    name: '',
    relationship: 'self',
    phone: '',
    gender: 'male',
    age_group: 'adult',
    notes: '',
    open_measurement: false,
});

watch(() => props.show, (val) => {
    if (val && props.contact) {
        form.name = props.contact.name || '';
        form.relationship = props.contact.relationship || 'self';
        form.phone = props.contact.phone || '';
        form.gender = props.contact.gender || 'male';
        form.age_group = props.contact.age_group || 'adult';
        form.notes = props.contact.notes || '';
        form.open_measurement = false;
    } else if (val) {
        form.reset();
    }
});

function submit(openMeasurement = false) {
    form.open_measurement = openMeasurement;

    if (props.contact) {
        form.put(route('contacts.update', props.contact.id), {
            onSuccess: () => emit('close'),
            preserveScroll: true,
        });
    } else {
        form.post(route('contacts.store', props.clientId), {
            onSuccess: () => emit('close'),
        });
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

const genders = [
    { value: 'male', label: 'Male' },
    { value: 'female', label: 'Female' },
];

const ageGroups = [
    { value: 'child', label: 'Child' },
    { value: 'teen', label: 'Teen' },
    { value: 'adult', label: 'Adult' },
    { value: 'senior', label: 'Senior' },
];
</script>

<template>
    <Modal :show="show" @close="emit('close')" max-width="lg">
        <form @submit.prevent="submit(false)" class="p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">
                {{ contact ? 'Edit Contact' : 'Add New Contact' }}
            </h3>

            <div class="space-y-4">
                <!-- Name -->
                <div>
                    <InputLabel for="contact_name" value="Full Name *" />
                    <TextInput id="contact_name" v-model="form.name" type="text" class="mt-1 block w-full" required autofocus placeholder="Person's full name" />
                    <InputError :message="form.errors.name" class="mt-1" />
                </div>

                <!-- Relationship & Phone -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="relationship" value="Relationship" />
                        <select id="relationship" v-model="form.relationship" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 text-sm">
                            <option v-for="r in relationships" :key="r.value" :value="r.value">{{ r.label }}</option>
                        </select>
                        <InputError :message="form.errors.relationship" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="contact_phone" value="Phone" />
                        <TextInput id="contact_phone" v-model="form.phone" type="tel" class="mt-1 block w-full" placeholder="Optional" />
                        <InputError :message="form.errors.phone" class="mt-1" />
                    </div>
                </div>

                <!-- Gender & Age Group -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <InputLabel for="gender" value="Gender" />
                        <select id="gender" v-model="form.gender" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 text-sm">
                            <option v-for="g in genders" :key="g.value" :value="g.value">{{ g.label }}</option>
                        </select>
                        <InputError :message="form.errors.gender" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="age_group" value="Age Group" />
                        <select id="age_group" v-model="form.age_group" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 text-sm">
                            <option v-for="a in ageGroups" :key="a.value" :value="a.value">{{ a.label }}</option>
                        </select>
                        <InputError :message="form.errors.age_group" class="mt-1" />
                    </div>
                </div>

                <!-- Notes -->
                <div>
                    <InputLabel for="contact_notes" value="Notes" />
                    <textarea id="contact_notes" v-model="form.notes" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 text-sm" placeholder="Any notes about this person"></textarea>
                    <InputError :message="form.errors.notes" class="mt-1" />
                </div>
            </div>

            <!-- Actions -->
            <div class="mt-6 flex flex-col sm:flex-row justify-end gap-3">
                <button type="button" @click="emit('close')" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                    Cancel
                </button>
                <button v-if="!contact" type="button" @click="submit(true)" :disabled="form.processing" class="rounded-lg border border-green-600 px-4 py-2 text-sm font-medium text-green-600 hover:bg-green-50 disabled:opacity-50 transition-colors">
                    <i class="pi pi-sliders-h text-xs mr-1"></i> Save & Add Measurement
                </button>
                <button type="submit" :disabled="form.processing" class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50 transition-colors">
                    {{ form.processing ? 'Saving...' : (contact ? 'Update Contact' : 'Save Contact') }}
                </button>
            </div>
        </form>
    </Modal>
</template>
