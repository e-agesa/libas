<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    users: Array,
    roles: Array,
    permissions: Array,
});

// User CRUD
const showUserModal = ref(false);
const editingUser = ref(null);

const userForm = useForm({
    name: '',
    email: '',
    phone: '',
    password: '',
    roles: [],
    status: 'active',
});

function openNewUser() {
    editingUser.value = null;
    userForm.reset();
    userForm.roles = [];
    showUserModal.value = true;
}

function openEditUser(user) {
    editingUser.value = user;
    userForm.name = user.name;
    userForm.email = user.email;
    userForm.phone = user.phone || '';
    userForm.password = '';
    userForm.roles = [...user.roles];
    userForm.status = user.status || 'active';
    showUserModal.value = true;
}

function submitUser() {
    if (editingUser.value) {
        userForm.put(route('users.update', editingUser.value.id), {
            onSuccess: () => { showUserModal.value = false; userForm.reset(); },
            preserveScroll: true,
        });
    } else {
        userForm.post(route('users.store'), {
            onSuccess: () => { showUserModal.value = false; userForm.reset(); },
            preserveScroll: true,
        });
    }
}

function deleteUser(user) {
    if (confirm(`Delete user "${user.name}"? This action cannot be undone.`)) {
        useForm({}).delete(route('users.destroy', user.id), { preserveScroll: true });
    }
}

function toggleRole(roleName) {
    const idx = userForm.roles.indexOf(roleName);
    if (idx >= 0) {
        userForm.roles.splice(idx, 1);
    } else {
        userForm.roles.push(roleName);
    }
}

// Role CRUD
const showRoleModal = ref(false);
const editingRole = ref(null);

const roleForm = useForm({
    name: '',
    permissions: [],
});

function openNewRole() {
    editingRole.value = null;
    roleForm.reset();
    roleForm.permissions = [];
    showRoleModal.value = true;
}

function openEditRole(role) {
    editingRole.value = role;
    roleForm.name = role.name;
    roleForm.permissions = [];
    showRoleModal.value = true;
}

function submitRole() {
    if (editingRole.value) {
        roleForm.put(route('roles.update', editingRole.value.id), {
            onSuccess: () => { showRoleModal.value = false; roleForm.reset(); },
            preserveScroll: true,
        });
    } else {
        roleForm.post(route('roles.store'), {
            onSuccess: () => { showRoleModal.value = false; roleForm.reset(); },
            preserveScroll: true,
        });
    }
}

function deleteRole(role) {
    if (confirm(`Delete role "${role.name}"? Users with this role will lose it.`)) {
        useForm({}).delete(route('roles.destroy', role.id), { preserveScroll: true });
    }
}

const statusColors = {
    active: 'bg-green-100 text-green-700',
    inactive: 'bg-gray-100 text-gray-500',
};

const roleColors = ['bg-blue-100 text-blue-700', 'bg-purple-100 text-purple-700', 'bg-amber-100 text-amber-700', 'bg-cyan-100 text-cyan-700', 'bg-pink-100 text-pink-700'];
</script>

<template>
    <Head title="User Management" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">User Management</h2>
                <div class="flex gap-2">
                    <button @click="openNewRole" class="inline-flex items-center gap-1 rounded-lg border border-purple-600 px-3 py-2 text-sm font-medium text-purple-600 hover:bg-purple-50 transition-colors">
                        <i class="pi pi-shield text-xs"></i> New Role
                    </button>
                    <button @click="openNewUser" class="inline-flex items-center gap-1 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 transition-colors">
                        <i class="pi pi-user-plus text-xs"></i> New User
                    </button>
                </div>
            </div>
        </template>

        <!-- Roles overview -->
        <div class="mb-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Roles</h3>
            <div class="flex flex-wrap gap-2">
                <div v-for="(role, i) in roles" :key="role.id" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 shadow-sm">
                    <span :class="roleColors[i % roleColors.length]" class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium">
                        {{ role.name }}
                    </span>
                    <span class="text-xs text-gray-400">{{ role.users_count }} users</span>
                    <button @click="openEditRole(role)" class="text-gray-400 hover:text-blue-600"><i class="pi pi-pencil text-xs"></i></button>
                    <button @click="deleteRole(role)" class="text-gray-400 hover:text-red-600"><i class="pi pi-trash text-xs"></i></button>
                </div>
            </div>
        </div>

        <!-- Users table -->
        <div class="rounded-xl bg-white shadow-sm border border-gray-100 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500">User</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 hidden md:table-cell">Phone</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500">Roles</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 hidden sm:table-cell">Joined</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="user in users" :key="user.id" class="hover:bg-gray-50">
                        <td class="px-6 py-3">
                            <div class="flex items-center gap-3">
                                <div class="flex h-9 w-9 items-center justify-center rounded-full bg-green-100 text-green-700 text-sm font-bold shrink-0">
                                    {{ user.name?.charAt(0).toUpperCase() }}
                                </div>
                                <span class="font-medium text-gray-900">{{ user.name }}</span>
                            </div>
                        </td>
                        <td class="px-6 py-3 text-gray-600">{{ user.email }}</td>
                        <td class="px-6 py-3 text-gray-600 hidden md:table-cell">{{ user.phone || '—' }}</td>
                        <td class="px-6 py-3">
                            <div class="flex flex-wrap gap-1">
                                <span v-for="(role, i) in user.roles" :key="role" :class="roleColors[roles.findIndex(r => r.name === role) % roleColors.length]" class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium">
                                    {{ role }}
                                </span>
                            </div>
                        </td>
                        <td class="px-6 py-3">
                            <span :class="statusColors[user.status] || statusColors.active" class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium capitalize">
                                {{ user.status || 'active' }}
                            </span>
                        </td>
                        <td class="px-6 py-3 text-gray-500 hidden sm:table-cell">{{ user.created_at }}</td>
                        <td class="px-6 py-3 text-right">
                            <button @click="openEditUser(user)" class="p-1.5 text-gray-400 hover:text-blue-600 rounded hover:bg-blue-50"><i class="pi pi-pencil text-xs"></i></button>
                            <button @click="deleteUser(user)" class="p-1.5 text-gray-400 hover:text-red-600 rounded hover:bg-red-50"><i class="pi pi-trash text-xs"></i></button>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div v-if="!users?.length" class="text-center py-12 text-gray-400">
                <i class="pi pi-users text-4xl mb-3 block"></i>
                <p class="text-sm">No users yet.</p>
            </div>
        </div>

        <!-- User Modal -->
        <Modal :show="showUserModal" @close="showUserModal = false" max-width="lg">
            <form @submit.prevent="submitUser" class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ editingUser ? 'Edit User' : 'Create User' }}</h3>
                <div class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="user_name" value="Full Name *" />
                            <TextInput id="user_name" v-model="userForm.name" type="text" class="mt-1 block w-full" required />
                            <InputError :message="userForm.errors.name" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel for="user_email" value="Email *" />
                            <TextInput id="user_email" v-model="userForm.email" type="email" class="mt-1 block w-full" required />
                            <InputError :message="userForm.errors.email" class="mt-1" />
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="user_phone" value="Phone" />
                            <TextInput id="user_phone" v-model="userForm.phone" type="text" class="mt-1 block w-full" />
                        </div>
                        <div>
                            <InputLabel for="user_password" :value="editingUser ? 'New Password (leave blank to keep)' : 'Password *'" />
                            <TextInput id="user_password" v-model="userForm.password" type="password" class="mt-1 block w-full" :required="!editingUser" />
                            <InputError :message="userForm.errors.password" class="mt-1" />
                        </div>
                    </div>

                    <!-- Status -->
                    <div v-if="editingUser">
                        <InputLabel value="Status" />
                        <div class="flex gap-3 mt-1">
                            <button type="button" @click="userForm.status = 'active'" :class="['rounded-lg border px-4 py-2 text-sm font-medium', userForm.status === 'active' ? 'border-green-500 bg-green-50 text-green-700' : 'border-gray-200 text-gray-600']">Active</button>
                            <button type="button" @click="userForm.status = 'inactive'" :class="['rounded-lg border px-4 py-2 text-sm font-medium', userForm.status === 'inactive' ? 'border-red-500 bg-red-50 text-red-700' : 'border-gray-200 text-gray-600']">Inactive</button>
                        </div>
                    </div>

                    <!-- Roles -->
                    <div>
                        <InputLabel value="Roles *" />
                        <InputError :message="userForm.errors.roles" class="mt-1" />
                        <div class="flex flex-wrap gap-2 mt-2">
                            <button
                                v-for="(role, i) in roles"
                                :key="role.id"
                                type="button"
                                @click="toggleRole(role.name)"
                                :class="[
                                    'rounded-lg border px-4 py-2 text-sm font-medium transition-colors',
                                    userForm.roles.includes(role.name) ? 'border-green-500 bg-green-50 text-green-700' : 'border-gray-200 text-gray-500 hover:bg-gray-50'
                                ]"
                            >
                                <i class="pi pi-shield text-xs mr-1"></i> {{ role.name }}
                            </button>
                        </div>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" @click="showUserModal = false" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="submit" :disabled="userForm.processing" class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50">
                        {{ userForm.processing ? 'Saving...' : (editingUser ? 'Update User' : 'Create User') }}
                    </button>
                </div>
            </form>
        </Modal>

        <!-- Role Modal -->
        <Modal :show="showRoleModal" @close="showRoleModal = false" max-width="md">
            <form @submit.prevent="submitRole" class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ editingRole ? 'Edit Role' : 'Create Role' }}</h3>
                <div class="space-y-4">
                    <div>
                        <InputLabel for="role_name" value="Role Name *" />
                        <TextInput id="role_name" v-model="roleForm.name" type="text" class="mt-1 block w-full" placeholder="e.g. Cashier" required />
                        <InputError :message="roleForm.errors.name" class="mt-1" />
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" @click="showRoleModal = false" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="submit" :disabled="roleForm.processing" class="rounded-lg bg-purple-600 px-4 py-2 text-sm font-medium text-white hover:bg-purple-700 disabled:opacity-50">
                        {{ roleForm.processing ? 'Saving...' : (editingRole ? 'Update Role' : 'Create Role') }}
                    </button>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
