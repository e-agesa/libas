<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="flex min-h-screen flex-col items-center bg-gray-50 pt-6 sm:justify-center sm:pt-0">
        <div class="mb-2">
            <Link href="/" class="flex flex-col items-center gap-2">
                <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-green-600 text-white font-bold text-3xl shadow-lg">
                    L
                </div>
                <span class="text-2xl font-bold text-gray-900">Libas <span class="text-green-600">TMS</span></span>
                <span class="text-xs text-gray-400 uppercase tracking-widest -mt-1">Tailor Management System</span>
            </Link>
        </div>

        <div class="mt-4 w-full overflow-hidden bg-white px-6 py-6 shadow-lg sm:max-w-md sm:rounded-xl border border-gray-100">
            <slot />
        </div>

    </div>
</template>
