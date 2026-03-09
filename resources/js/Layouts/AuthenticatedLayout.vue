<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';

const sidebarOpen = ref(true);
const mobileSidebarOpen = ref(false);
const toast = useToast();

const page = usePage();
const user = computed(() => page.props.auth.user);

// Deduplicate flash messages to prevent multiple toasts
let lastFlashKey = '';

function showFlashMessages() {
    const flash = page.props.flash;
    const key = [flash?.success, flash?.error, flash?.warning, flash?.info].filter(Boolean).join('|');
    if (!key || key === lastFlashKey) return;
    lastFlashKey = key;

    if (flash?.success) {
        toast.add({ severity: 'success', summary: 'Success', detail: flash.success, life: 4000 });
    }
    if (flash?.error) {
        toast.add({ severity: 'error', summary: 'Error', detail: flash.error, life: 5000 });
    }
    if (flash?.warning) {
        toast.add({ severity: 'warn', summary: 'Warning', detail: flash.warning, life: 4000 });
    }
    if (flash?.info) {
        toast.add({ severity: 'info', summary: 'Info', detail: flash.info, life: 4000 });
    }

    // Allow same message again after 2s
    setTimeout(() => { lastFlashKey = ''; }, 2000);
}

// Show flash on initial page load
onMounted(showFlashMessages);

// Show flash after Inertia navigations (cleanup on unmount)
const removeListener = router.on('navigate', () => {
    nextTick(showFlashMessages);
});
onUnmounted(removeListener);

const navigation = [
    { name: 'Dashboard', href: 'dashboard', icon: 'pi pi-home' },
    { name: 'POS', href: 'pos.index', icon: 'pi pi-shopping-cart', highlight: true },
    { name: 'Clients', href: 'clients.index', icon: 'pi pi-users' },
    { name: 'Invoices', href: 'invoices.index', icon: 'pi pi-file-edit' },
    { name: 'Collections', href: 'collections.index', icon: 'pi pi-shopping-bag' },
    { name: 'Inventory', href: 'inventory.index', icon: 'pi pi-box' },
    { name: 'Fabrics', href: 'fabrics.index', icon: 'pi pi-palette' },
    { name: 'Expenses', href: 'expenses.index', icon: 'pi pi-wallet' },
    { name: 'Reports', href: 'reports.index', icon: 'pi pi-chart-bar' },
    { name: 'Users', href: 'users.index', icon: 'pi pi-user-edit' },
    { name: 'Settings', href: 'settings.index', icon: 'pi pi-cog' },
];

function isActive(routeName) {
    try {
        return route().current(routeName) || route().current(routeName + '.*');
    } catch {
        return false;
    }
}

function navHref(routeName) {
    try {
        return route(routeName);
    } catch {
        return '#';
    }
}
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <Toast position="top-right" />
        <!-- Mobile sidebar overlay -->
        <div
            v-if="mobileSidebarOpen"
            class="fixed inset-0 z-40 bg-gray-600/75 lg:hidden"
            @click="mobileSidebarOpen = false"
        ></div>

        <!-- Sidebar -->
        <aside
            :class="[
                'fixed inset-y-0 left-0 z-50 flex flex-col bg-gray-900 transition-all duration-300',
                sidebarOpen ? 'w-64' : 'w-20',
                mobileSidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
            ]"
        >
            <!-- Logo -->
            <div class="flex h-16 items-center justify-between px-4">
                <Link :href="route('dashboard')" class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-green-600 text-white font-bold text-lg">
                        L
                    </div>
                    <span v-if="sidebarOpen" class="text-white font-semibold text-lg whitespace-nowrap">
                        Libas TMS
                    </span>
                </Link>
                <button
                    @click="sidebarOpen = !sidebarOpen"
                    class="hidden lg:block text-gray-400 hover:text-white"
                >
                    <i :class="sidebarOpen ? 'pi pi-angle-double-left' : 'pi pi-angle-double-right'" class="text-sm"></i>
                </button>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 space-y-1 px-3 py-4">
                <Link
                    v-for="item in navigation"
                    :key="item.name"
                    :href="navHref(item.href)"
                    :class="[
                        item.highlight && !isActive(item.href)
                            ? 'bg-green-600 text-white hover:bg-green-700'
                            : isActive(item.href)
                                ? 'bg-gray-800 text-white'
                                : 'text-gray-300 hover:bg-gray-800 hover:text-white',
                        'group flex items-center rounded-lg px-3 py-2.5 text-sm font-medium transition-colors',
                    ]"
                    @click="mobileSidebarOpen = false"
                >
                    <i
                        :class="[
                            item.icon,
                            item.highlight ? 'text-white' : isActive(item.href) ? 'text-green-400' : 'text-gray-400 group-hover:text-gray-300',
                            'shrink-0 text-lg',
                        ]"
                    ></i>
                    <span v-if="sidebarOpen" class="ml-3 whitespace-nowrap">{{ item.name }}</span>
                </Link>
            </nav>

            <!-- User section at bottom -->
            <div class="border-t border-gray-700 p-3">
                <div :class="['flex items-center', sidebarOpen ? 'gap-3' : 'justify-center']">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-green-700 text-white text-sm font-medium">
                        {{ user.name?.charAt(0).toUpperCase() }}
                    </div>
                    <div v-if="sidebarOpen" class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-white truncate">{{ user.name }}</p>
                        <p class="text-xs text-gray-400 truncate">{{ user.email }}</p>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main content area -->
        <div :class="['transition-all duration-300', sidebarOpen ? 'lg:pl-64' : 'lg:pl-20']">
            <!-- Top bar -->
            <header class="sticky top-0 z-30 flex h-16 items-center gap-4 border-b border-gray-200 bg-white px-4 sm:px-6">
                <!-- Mobile menu button -->
                <button
                    @click="mobileSidebarOpen = true"
                    class="text-gray-500 lg:hidden"
                >
                    <i class="pi pi-bars text-xl"></i>
                </button>

                <!-- Breadcrumb -->
                <div class="flex-1">
                    <slot name="breadcrumb" />
                </div>

                <!-- Right side: POS + search + user -->
                <div class="flex items-center gap-4">
                    <!-- POS quick button -->
                    <Link :href="navHref('pos.index')" class="inline-flex items-center gap-2 rounded-lg bg-green-600 px-3 py-2 text-sm font-medium text-white hover:bg-green-700 transition-colors shadow-sm">
                        <i class="pi pi-shopping-cart text-xs"></i>
                        <span class="hidden sm:inline">POS</span>
                    </Link>

                    <!-- Global search placeholder -->
                    <div class="hidden sm:block">
                        <div class="relative">
                            <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                            <input
                                type="text"
                                placeholder="Search clients..."
                                class="w-64 rounded-lg border border-gray-300 bg-gray-50 py-2 pl-9 pr-4 text-sm focus:border-green-500 focus:ring-green-500"
                            />
                        </div>
                    </div>

                    <!-- Notifications -->
                    <button class="relative text-gray-500 hover:text-gray-700">
                        <i class="pi pi-bell text-xl"></i>
                    </button>

                    <!-- User dropdown -->
                    <Dropdown align="right" width="48">
                        <template #trigger>
                            <button class="flex items-center gap-2 text-sm text-gray-600 hover:text-gray-800">
                                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-green-100 text-green-700 text-sm font-medium">
                                    {{ user.name?.charAt(0).toUpperCase() }}
                                </div>
                                <span class="hidden sm:inline font-medium">{{ user.name }}</span>
                                <i class="pi pi-chevron-down text-xs"></i>
                            </button>
                        </template>
                        <template #content>
                            <DropdownLink :href="route('profile.edit')">
                                <i class="pi pi-user mr-2"></i> Profile
                            </DropdownLink>
                            <DropdownLink :href="route('logout')" method="post" as="button">
                                <i class="pi pi-sign-out mr-2"></i> Log Out
                            </DropdownLink>
                        </template>
                    </Dropdown>
                </div>
            </header>

            <!-- Page heading -->
            <div v-if="$slots.header" class="border-b border-gray-200 bg-white px-4 py-4 sm:px-6">
                <slot name="header" />
            </div>

            <!-- Page content -->
            <main class="p-4 sm:p-6">
                <slot />
            </main>
        </div>
    </div>
</template>
