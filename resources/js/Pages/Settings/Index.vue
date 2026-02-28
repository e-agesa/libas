<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import { useGarmentTypes } from '@/composables/useGarmentTypes';

const props = defineProps({
    users: Array,
    roles: Array,
    company: Object,
});

const activeTab = ref('users');

// ─── Users ───────────────────────────────────────────────────────────────────
const showUserModal = ref(false);
const editingUser = ref(null);

const userForm = useForm({
    name: '',
    email: '',
    phone: '',
    password: '',
    role: 'Tailor',
    status: 'active',
});

function openAddUser() {
    editingUser.value = null;
    userForm.reset();
    userForm.role = 'Tailor';
    showUserModal.value = true;
}

function openEditUser(user) {
    editingUser.value = user;
    userForm.name = user.name;
    userForm.email = user.email;
    userForm.phone = user.phone || '';
    userForm.password = '';
    userForm.role = user.role;
    userForm.status = user.status;
    showUserModal.value = true;
}

function closeUserModal() {
    showUserModal.value = false;
    editingUser.value = null;
}

function submitUser() {
    if (editingUser.value) {
        userForm.put(route('settings.users.update', editingUser.value.id), {
            onSuccess: () => closeUserModal(),
            preserveScroll: true,
        });
    } else {
        userForm.post(route('settings.users.store'), {
            onSuccess: () => closeUserModal(),
        });
    }
}

function deleteUser(user) {
    if (confirm(`Delete user "${user.name}"? This cannot be undone.`)) {
        router.delete(route('settings.users.destroy', user.id));
    }
}

const roleBadges = {
    Admin: 'bg-red-100 text-red-700',
    Manager: 'bg-blue-100 text-blue-700',
    Tailor: 'bg-green-100 text-green-700',
    Secretary: 'bg-amber-100 text-amber-700',
};

// ─── Garment Types ───────────────────────────────────────────────────────────
const { garmentTypes, getColor } = useGarmentTypes();

const selectedType = ref(null);
const showTypeModal = ref(false);
const showFieldModal = ref(false);
const editingField = ref(null);

const COLOR_PALETTE = [
    { hex: '#22c55e', label: 'Green' },
    { hex: '#3b82f6', label: 'Blue' },
    { hex: '#a16207', label: 'Amber' },
    { hex: '#8b5cf6', label: 'Purple' },
    { hex: '#ef4444', label: 'Red' },
    { hex: '#14b8a6', label: 'Teal' },
    { hex: '#6366f1', label: 'Indigo' },
    { hex: '#ec4899', label: 'Pink' },
    { hex: '#f97316', label: 'Orange' },
    { hex: '#06b6d4', label: 'Cyan' },
    { hex: '#84cc16', label: 'Lime' },
    { hex: '#f43f5e', label: 'Rose' },
];

const typeForm = useForm({
    name: '',
    color: '#22c55e',
    is_active: true,
});

const fieldForm = useForm({
    name: '',
});

function openAddType() {
    selectedType.value = null;
    typeForm.reset();
    typeForm.name = '';
    typeForm.color = '#22c55e';
    typeForm.is_active = true;
    showTypeModal.value = true;
}

function openEditType(type) {
    selectedType.value = type;
    typeForm.name = type.name;
    typeForm.color = type.color;
    typeForm.is_active = type.is_active;
    showTypeModal.value = true;
}

function closeTypeModal() {
    showTypeModal.value = false;
    selectedType.value = null;
}

function submitType() {
    if (selectedType.value) {
        typeForm.put(route('garment-types.update', selectedType.value.id), {
            onSuccess: () => closeTypeModal(),
            preserveScroll: true,
        });
    } else {
        typeForm.post(route('garment-types.store'), {
            onSuccess: () => closeTypeModal(),
        });
    }
}

function deleteType(type) {
    if (confirm(`Delete garment type "${type.name}"? This will fail if measurements exist for this type.`)) {
        router.delete(route('garment-types.destroy', type.id), { preserveScroll: true });
    }
}

function toggleActive(type) {
    router.put(route('garment-types.update', type.id), {
        name: type.name,
        color: type.color,
        is_active: !type.is_active,
    }, { preserveScroll: true });
}

// Fields management — track by ID so the panel auto-refreshes after Inertia navigations
const workingTypeId = ref(null);
const workingType = computed(() => garmentTypes.value.find(t => t.id === workingTypeId.value) || null);

function openFieldManager(type) {
    workingTypeId.value = type.id;
}

function openAddField() {
    editingField.value = null;
    fieldForm.reset();
    showFieldModal.value = true;
}

function openEditField(field) {
    editingField.value = field;
    fieldForm.name = field.name;
    showFieldModal.value = true;
}

function closeFieldModal() {
    showFieldModal.value = false;
    editingField.value = null;
}

function submitField() {
    if (editingField.value) {
        fieldForm.put(route('garment-type-fields.update', editingField.value.id), {
            onSuccess: () => closeFieldModal(),
            preserveScroll: true,
        });
    } else {
        fieldForm.post(route('garment-type-fields.store', workingType.value.id), {
            onSuccess: () => closeFieldModal(),
            preserveScroll: true,
        });
    }
}

function deleteField(field) {
    if (confirm(`Delete field "${field.name}"?`)) {
        router.delete(route('garment-type-fields.destroy', field.id), { preserveScroll: true });
    }
}

// ─── Company / Business Settings ─────────────────────────────────────────────
const companyForm = useForm({
    business_name: props.company?.business_name || '',
    tagline: props.company?.tagline || '',
    phone: props.company?.phone || '',
    email: props.company?.email || '',
    address: props.company?.address || '',
    tax_number: props.company?.tax_number || '',
    footer_text: props.company?.footer_text || '',
});

function submitCompany() {
    companyForm.put(route('settings.company.update'), { preserveScroll: true });
}

const logoFile = ref(null);
const logoInput = ref(null);
const logoPreview = ref(props.company?.logo_url || null);

function pickLogo() {
    logoInput.value?.click();
}

function onLogoChange(e) {
    const file = e.target.files[0];
    if (!file) return;
    logoFile.value = file;
    logoPreview.value = URL.createObjectURL(file);
}

function uploadLogo() {
    if (!logoFile.value) return;
    const data = new FormData();
    data.append('logo', logoFile.value);
    router.post(route('settings.company.logo'), data, {
        preserveScroll: true,
        onSuccess: () => {
            logoFile.value = null;
        },
    });
}

function removeLogo() {
    if (!confirm('Remove the current logo?')) return;
    router.delete(route('settings.company.logo.remove'), {
        preserveScroll: true,
        onSuccess: () => {
            logoPreview.value = null;
        },
    });
}
</script>

<template>
    <Head title="Settings" />
    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Settings</h2>
        </template>

        <!-- Tabs -->
        <div class="mb-4 flex gap-1 border-b border-gray-200 overflow-x-auto">
            <button
                @click="activeTab = 'users'"
                :class="['px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors whitespace-nowrap', activeTab === 'users' ? 'border-green-600 text-green-600' : 'border-transparent text-gray-500 hover:text-gray-700']"
            >
                <i class="pi pi-users mr-1.5"></i> Users & Roles
            </button>
            <button
                @click="activeTab = 'garments'"
                :class="['px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors whitespace-nowrap', activeTab === 'garments' ? 'border-green-600 text-green-600' : 'border-transparent text-gray-500 hover:text-gray-700']"
            >
                <i class="pi pi-sliders-h mr-1.5"></i> Garment Types
            </button>
            <button
                @click="activeTab = 'business'"
                :class="['px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors whitespace-nowrap', activeTab === 'business' ? 'border-green-600 text-green-600' : 'border-transparent text-gray-500 hover:text-gray-700']"
            >
                <i class="pi pi-building mr-1.5"></i> Business Info
            </button>
            <button
                @click="activeTab = 'invoice'"
                :class="['px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors whitespace-nowrap', activeTab === 'invoice' ? 'border-green-600 text-green-600' : 'border-transparent text-gray-500 hover:text-gray-700']"
            >
                <i class="pi pi-file-edit mr-1.5"></i> Invoice Settings
            </button>
        </div>

        <!-- Users Tab -->
        <div v-if="activeTab === 'users'">
            <div class="flex items-center justify-between mb-4">
                <p class="text-sm text-gray-500">Manage staff accounts and their roles.</p>
                <button @click="openAddUser" class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 transition-colors">
                    <i class="pi pi-plus text-xs"></i> Add User
                </button>
            </div>

            <div class="rounded-xl bg-white shadow-sm border border-gray-100 overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left">
                        <tr>
                            <th class="px-6 py-3 font-medium text-gray-500 uppercase text-xs">Name</th>
                            <th class="px-6 py-3 font-medium text-gray-500 uppercase text-xs">Email</th>
                            <th class="px-6 py-3 font-medium text-gray-500 uppercase text-xs hidden md:table-cell">Phone</th>
                            <th class="px-6 py-3 font-medium text-gray-500 uppercase text-xs">Role</th>
                            <th class="px-6 py-3 font-medium text-gray-500 uppercase text-xs">Status</th>
                            <th class="px-6 py-3 font-medium text-gray-500 uppercase text-xs hidden lg:table-cell">Joined</th>
                            <th class="px-6 py-3 font-medium text-gray-500 uppercase text-xs text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="user in users" :key="user.id" class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-green-100 text-green-700 text-xs font-bold">
                                        {{ user.name.charAt(0).toUpperCase() }}
                                    </div>
                                    <span class="font-medium text-gray-900">{{ user.name }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-gray-600">{{ user.email }}</td>
                            <td class="px-6 py-4 text-gray-600 hidden md:table-cell">{{ user.phone || '—' }}</td>
                            <td class="px-6 py-4">
                                <span :class="roleBadges[user.role] || 'bg-gray-100 text-gray-700'" class="rounded-full px-2.5 py-0.5 text-xs font-medium">{{ user.role }}</span>
                            </td>
                            <td class="px-6 py-4">
                                <span :class="user.status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'" class="rounded-full px-2.5 py-0.5 text-xs font-medium capitalize">{{ user.status }}</span>
                            </td>
                            <td class="px-6 py-4 text-gray-500 text-sm hidden lg:table-cell">{{ user.created_at }}</td>
                            <td class="px-6 py-4 text-right">
                                <button @click="openEditUser(user)" class="text-gray-400 hover:text-blue-600 mr-2" title="Edit"><i class="pi pi-pencil"></i></button>
                                <button @click="deleteUser(user)" class="text-gray-400 hover:text-red-600" title="Delete"><i class="pi pi-trash"></i></button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Garment Types Tab -->
        <div v-if="activeTab === 'garments'">
            <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">

                <!-- Left: Types list -->
                <div class="lg:col-span-2">
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-sm text-gray-500">Define garment types for your tailoring.</p>
                        <button
                            @click="openAddType"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-green-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-green-700 transition-colors"
                        >
                            <i class="pi pi-plus text-xs"></i> Add Type
                        </button>
                    </div>

                    <div class="space-y-2">
                        <div
                            v-for="type in garmentTypes"
                            :key="type.id"
                            @click="openFieldManager(type)"
                            :class="[
                                'flex items-center gap-3 rounded-xl border p-4 cursor-pointer transition-all',
                                workingType?.id === type.id
                                    ? 'border-green-400 bg-green-50 shadow-sm'
                                    : 'border-gray-200 bg-white hover:border-green-200 hover:shadow-sm'
                            ]"
                        >
                            <!-- Color dot -->
                            <div
                                class="h-4 w-4 rounded-full shrink-0 border border-white shadow-sm"
                                :style="{ backgroundColor: type.color }"
                            ></div>

                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-gray-900 text-sm">{{ type.name }}</p>
                                <p class="text-xs text-gray-400">{{ type.fields?.length || 0 }} field{{ (type.fields?.length || 0) !== 1 ? 's' : '' }}</p>
                            </div>

                            <!-- Active toggle -->
                            <button
                                @click.stop="toggleActive(type)"
                                :class="[
                                    'text-xs px-2 py-0.5 rounded-full font-medium transition-colors',
                                    type.is_active
                                        ? 'bg-green-100 text-green-700 hover:bg-green-200'
                                        : 'bg-gray-100 text-gray-500 hover:bg-gray-200'
                                ]"
                                :title="type.is_active ? 'Click to deactivate' : 'Click to activate'"
                            >
                                {{ type.is_active ? 'Active' : 'Inactive' }}
                            </button>

                            <div class="flex gap-1 shrink-0">
                                <button @click.stop="openEditType(type)" class="p-1 text-gray-400 hover:text-blue-600 rounded hover:bg-blue-50" title="Edit">
                                    <i class="pi pi-pencil text-xs"></i>
                                </button>
                                <button @click.stop="deleteType(type)" class="p-1 text-gray-400 hover:text-red-600 rounded hover:bg-red-50" title="Delete">
                                    <i class="pi pi-trash text-xs"></i>
                                </button>
                            </div>
                        </div>

                        <div v-if="!garmentTypes.length" class="rounded-xl border border-dashed border-gray-300 p-8 text-center">
                            <i class="pi pi-sliders-h text-3xl text-gray-300 mb-3 block"></i>
                            <p class="text-sm text-gray-500">No garment types yet.</p>
                            <button @click="openAddType" class="mt-3 text-sm text-green-600 hover:text-green-700 font-medium">Add your first type</button>
                        </div>
                    </div>
                </div>

                <!-- Right: Field manager -->
                <div class="lg:col-span-3">
                    <div v-if="workingType" class="rounded-xl bg-white border border-gray-200 shadow-sm">
                        <!-- Header -->
                        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                            <div class="flex items-center gap-2">
                                <div class="h-3 w-3 rounded-full" :style="{ backgroundColor: workingType.color }"></div>
                                <h3 class="font-semibold text-gray-900">{{ workingType.name }} — Measurement Fields</h3>
                            </div>
                            <button
                                @click="openAddField"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-green-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-green-700 transition-colors"
                            >
                                <i class="pi pi-plus text-xs"></i> Add Field
                            </button>
                        </div>

                        <!-- Fields list -->
                        <div class="p-5">
                            <p class="text-xs text-gray-400 mb-4">These fields will appear as inputs when taking a measurement for <strong>{{ workingType.name }}</strong>.</p>

                            <div v-if="workingType.fields?.length" class="space-y-2">
                                <div
                                    v-for="(field, idx) in workingType.fields"
                                    :key="field.id"
                                    class="flex items-center gap-3 rounded-lg border border-gray-200 px-4 py-3 bg-gray-50"
                                >
                                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-white border border-gray-200 text-xs font-medium text-gray-500">{{ idx + 1 }}</span>
                                    <div class="flex-1">
                                        <p class="text-sm font-medium text-gray-900">{{ field.name }}</p>
                                        <p class="text-xs text-gray-400 font-mono">{{ field.slug }}</p>
                                    </div>
                                    <div class="flex gap-1">
                                        <button @click="openEditField(field)" class="p-1.5 text-gray-400 hover:text-blue-600 rounded hover:bg-blue-50 transition-colors" title="Edit">
                                            <i class="pi pi-pencil text-xs"></i>
                                        </button>
                                        <button @click="deleteField(field)" class="p-1.5 text-gray-400 hover:text-red-600 rounded hover:bg-red-50 transition-colors" title="Delete">
                                            <i class="pi pi-trash text-xs"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div v-else class="rounded-lg border border-dashed border-gray-300 p-8 text-center">
                                <i class="pi pi-list text-3xl text-gray-300 mb-3 block"></i>
                                <p class="text-sm text-gray-500">No fields defined yet.</p>
                                <button @click="openAddField" class="mt-3 text-sm text-green-600 hover:text-green-700 font-medium">Add the first field</button>
                            </div>
                        </div>
                    </div>

                    <!-- Placeholder when no type selected -->
                    <div v-else class="rounded-xl border border-dashed border-gray-300 p-12 text-center text-gray-400">
                        <i class="pi pi-arrow-left text-3xl mb-3 block"></i>
                        <p class="text-sm">Select a garment type on the left to manage its fields.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Business Tab -->
        <div v-if="activeTab === 'business'" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Logo Card -->
            <div class="rounded-xl bg-white p-6 shadow-sm border border-gray-100">
                <h3 class="text-base font-semibold text-gray-900 mb-4">Logo</h3>
                <div class="flex flex-col items-center gap-4">
                    <div class="h-28 w-28 rounded-xl border-2 border-dashed border-gray-200 flex items-center justify-center overflow-hidden bg-gray-50">
                        <img v-if="logoPreview" :src="logoPreview" class="h-full w-full object-contain p-2" alt="Logo" />
                        <i v-else class="pi pi-image text-3xl text-gray-300"></i>
                    </div>
                    <input ref="logoInput" type="file" accept="image/jpeg,image/png,image/jpg,image/svg+xml" class="hidden" @change="onLogoChange" />
                    <div class="flex flex-col gap-2 w-full">
                        <button type="button" @click="pickLogo" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            <i class="pi pi-upload mr-1.5 text-xs"></i> Choose Logo
                        </button>
                        <button v-if="logoFile" type="button" @click="uploadLogo" class="w-full rounded-lg bg-green-600 px-3 py-2 text-sm font-medium text-white hover:bg-green-700">
                            <i class="pi pi-check mr-1.5 text-xs"></i> Upload
                        </button>
                        <button v-if="company?.logo_path" type="button" @click="removeLogo" class="w-full rounded-lg border border-red-200 px-3 py-2 text-sm font-medium text-red-600 hover:bg-red-50">
                            <i class="pi pi-trash mr-1.5 text-xs"></i> Remove Logo
                        </button>
                    </div>
                    <p class="text-xs text-gray-400 text-center">JPEG, PNG or SVG · Max 2MB</p>
                </div>
            </div>

            <!-- Details Form -->
            <div class="lg:col-span-2 rounded-xl bg-white p-6 shadow-sm border border-gray-100">
                <h3 class="text-base font-semibold text-gray-900 mb-4">Business Details</h3>
                <form @submit.prevent="submitCompany" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="c_name" value="Business Name *" />
                            <TextInput id="c_name" v-model="companyForm.business_name" type="text" class="mt-1 block w-full" required />
                            <InputError :message="companyForm.errors.business_name" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel for="c_tagline" value="Tagline / Subtitle" />
                            <TextInput id="c_tagline" v-model="companyForm.tagline" type="text" class="mt-1 block w-full" placeholder="Tailor Management System" />
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="c_phone" value="Phone" />
                            <TextInput id="c_phone" v-model="companyForm.phone" type="text" class="mt-1 block w-full" placeholder="+254..." />
                        </div>
                        <div>
                            <InputLabel for="c_email" value="Email" />
                            <TextInput id="c_email" v-model="companyForm.email" type="email" class="mt-1 block w-full" placeholder="info@business.com" />
                        </div>
                    </div>
                    <div>
                        <InputLabel for="c_address" value="Address" />
                        <textarea id="c_address" v-model="companyForm.address" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 text-sm" placeholder="Full business address"></textarea>
                    </div>
                    <div>
                        <InputLabel for="c_tax" value="Tax / PIN Number" />
                        <TextInput id="c_tax" v-model="companyForm.tax_number" type="text" class="mt-1 block w-full" placeholder="KRA PIN or VAT number" />
                    </div>
                    <div>
                        <InputLabel for="c_footer" value="Invoice Footer Text" />
                        <textarea id="c_footer" v-model="companyForm.footer_text" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 text-sm" placeholder="Thank you for your business!"></textarea>
                        <p class="mt-1 text-xs text-gray-400">This text appears at the bottom of every invoice and quotation PDF.</p>
                    </div>
                    <div class="flex justify-end pt-2">
                        <button type="submit" :disabled="companyForm.processing" class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50">
                            <i class="pi pi-save text-xs"></i>
                            {{ companyForm.processing ? 'Saving...' : 'Save Details' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Invoice Settings Tab -->
        <div v-if="activeTab === 'invoice'">
            <div class="max-w-2xl rounded-xl bg-white p-6 shadow-sm border border-gray-100">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Invoice & Receipt Settings</h3>
                <div class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <InputLabel value="Invoice Prefix" />
                            <TextInput type="text" class="mt-1 block w-full" value="INV-" disabled />
                        </div>
                        <div>
                            <InputLabel value="Default Tax Rate (%)" />
                            <TextInput type="number" class="mt-1 block w-full" value="0" min="0" max="100" />
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <InputLabel value="Currency" />
                            <TextInput type="text" class="mt-1 block w-full" value="KES" disabled />
                        </div>
                        <div>
                            <InputLabel value="Default Payment Terms (days)" />
                            <TextInput type="number" class="mt-1 block w-full" value="30" min="0" />
                        </div>
                    </div>
                    <div>
                        <InputLabel value="Receipt Footer Text" />
                        <textarea rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 text-sm" placeholder="Thank you for your business!"></textarea>
                    </div>
                </div>
                <div class="mt-6 pt-4 border-t border-gray-100">
                    <p class="text-xs text-gray-400"><i class="pi pi-info-circle mr-1"></i>Invoice configuration will be persisted in a future update.</p>
                </div>
            </div>
        </div>

        <!-- ─── Add/Edit User Modal ─────────────────────────────── -->
        <Modal :show="showUserModal" @close="closeUserModal" max-width="lg">
            <form @submit.prevent="submitUser" class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">
                    {{ editingUser ? 'Edit User' : 'Add New User' }}
                </h3>

                <div class="space-y-4">
                    <div>
                        <InputLabel for="u_name" value="Full Name *" />
                        <TextInput id="u_name" v-model="userForm.name" type="text" class="mt-1 block w-full" required autofocus />
                        <InputError :message="userForm.errors.name" class="mt-1" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="u_email" value="Email *" />
                            <TextInput id="u_email" v-model="userForm.email" type="email" class="mt-1 block w-full" required />
                            <InputError :message="userForm.errors.email" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel for="u_phone" value="Phone" />
                            <TextInput id="u_phone" v-model="userForm.phone" type="tel" class="mt-1 block w-full" />
                            <InputError :message="userForm.errors.phone" class="mt-1" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="u_password" :value="editingUser ? 'New Password (leave blank to keep)' : 'Password *'" />
                            <TextInput id="u_password" v-model="userForm.password" type="password" class="mt-1 block w-full" :required="!editingUser" />
                            <InputError :message="userForm.errors.password" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel for="u_role" value="Role *" />
                            <select id="u_role" v-model="userForm.role" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 text-sm" required>
                                <option v-for="role in roles" :key="role" :value="role">{{ role }}</option>
                            </select>
                            <InputError :message="userForm.errors.role" class="mt-1" />
                        </div>
                    </div>

                    <div v-if="editingUser">
                        <InputLabel for="u_status" value="Status" />
                        <select id="u_status" v-model="userForm.status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 text-sm">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" @click="closeUserModal" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">Cancel</button>
                    <button type="submit" :disabled="userForm.processing" class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50 transition-colors">
                        {{ userForm.processing ? 'Saving...' : (editingUser ? 'Update User' : 'Create User') }}
                    </button>
                </div>
            </form>
        </Modal>

        <!-- ─── Add/Edit Garment Type Modal ────────────────────── -->
        <Modal :show="showTypeModal" @close="closeTypeModal" max-width="md">
            <form @submit.prevent="submitType" class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">
                    {{ selectedType ? 'Edit Garment Type' : 'New Garment Type' }}
                </h3>

                <div class="space-y-4">
                    <div>
                        <InputLabel for="gt_name" value="Type Name *" />
                        <TextInput id="gt_name" v-model="typeForm.name" type="text" class="mt-1 block w-full" placeholder="e.g. Jalabiya, Abaya, Bui-Bui..." required autofocus />
                        <InputError :message="typeForm.errors.name" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel value="Color *" />
                        <div class="mt-2 flex flex-wrap gap-2">
                            <button
                                v-for="c in COLOR_PALETTE"
                                :key="c.hex"
                                type="button"
                                @click="typeForm.color = c.hex"
                                :title="c.label"
                                :class="[
                                    'h-8 w-8 rounded-full border-2 transition-all',
                                    typeForm.color === c.hex ? 'border-gray-900 scale-110' : 'border-transparent hover:scale-105'
                                ]"
                                :style="{ backgroundColor: c.hex }"
                            ></button>
                        </div>
                        <InputError :message="typeForm.errors.color" class="mt-1" />
                    </div>

                    <div v-if="selectedType" class="flex items-center gap-3">
                        <InputLabel value="Active" class="!mb-0" />
                        <button
                            type="button"
                            @click="typeForm.is_active = !typeForm.is_active"
                            :class="[
                                'relative inline-flex h-6 w-11 items-center rounded-full transition-colors',
                                typeForm.is_active ? 'bg-green-600' : 'bg-gray-300'
                            ]"
                        >
                            <span :class="['inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform', typeForm.is_active ? 'translate-x-6' : 'translate-x-1']"></span>
                        </button>
                        <span class="text-sm text-gray-500">{{ typeForm.is_active ? 'Active — visible in forms' : 'Inactive — hidden from forms' }}</span>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" @click="closeTypeModal" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="submit" :disabled="typeForm.processing" class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50">
                        {{ typeForm.processing ? 'Saving...' : (selectedType ? 'Update Type' : 'Create Type') }}
                    </button>
                </div>
            </form>
        </Modal>

        <!-- ─── Add/Edit Field Modal ────────────────────────────── -->
        <Modal :show="showFieldModal" @close="closeFieldModal" max-width="sm">
            <form @submit.prevent="submitField" class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-1">
                    {{ editingField ? 'Edit Field' : 'Add Field' }}
                </h3>
                <p class="text-sm text-gray-500 mb-4">for <strong>{{ workingType?.name }}</strong></p>

                <div>
                    <InputLabel for="f_name" value="Field Name *" />
                    <TextInput id="f_name" v-model="fieldForm.name" type="text" class="mt-1 block w-full" placeholder="e.g. Chest, Shoulder, Length..." required autofocus />
                    <p class="text-xs text-gray-400 mt-1">The slug (storage key) will be auto-generated from the name.</p>
                    <InputError :message="fieldForm.errors.name" class="mt-1" />
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" @click="closeFieldModal" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="submit" :disabled="fieldForm.processing" class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50">
                        {{ fieldForm.processing ? 'Saving...' : (editingField ? 'Update Field' : 'Add Field') }}
                    </button>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
