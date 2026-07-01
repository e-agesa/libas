<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    canLogin: { type: Boolean },
    canRegister: { type: Boolean },
    laravelVersion: { type: String, required: true },
    phpVersion: { type: String, required: true },
});
</script>

<template>
    <Head title="Welcome" />
    <div class="min-h-screen bg-gradient-to-br from-brand-600 via-brand-700 to-brand-900 flex flex-col items-center justify-center relative overflow-hidden">
        <!-- Decorative circles -->
        <div class="absolute inset-0 opacity-10">
            <div class="absolute top-10 left-10 w-40 h-40 rounded-full bg-white"></div>
            <div class="absolute bottom-20 right-20 w-64 h-64 rounded-full bg-white"></div>
            <div class="absolute top-1/3 right-10 w-24 h-24 rounded-full bg-white"></div>
        </div>

        <div class="relative text-center px-6">
            <!-- Logo -->
            <div class="mb-6">
                <img src="/logo.jpeg" alt="Libas ul Anwar" class="h-24 sm:h-32 mx-auto rounded-2xl shadow-2xl" />
            </div>

            <h1 class="text-3xl sm:text-5xl font-bold text-white mb-3">Libas ul Anwar</h1>
            <p class="text-brand-200 text-sm sm:text-lg mb-8 max-w-md mx-auto">Perfection In All Types Of Clothes</p>

            <div class="flex flex-col sm:flex-row gap-3 justify-center">
                <Link :href="route('shop')" class="inline-flex items-center justify-center gap-2 rounded-xl bg-white text-brand-700 px-8 py-3 text-sm font-semibold hover:bg-gray-100 transition-colors shadow-lg">
                    <i class="pi pi-shopping-bag"></i> Visit Shop
                </Link>
            </div>

            <div class="flex items-center justify-center gap-6 mt-10 text-sm text-brand-200">
                <span class="flex items-center gap-1"><i class="pi pi-check-circle"></i> Custom Tailoring</span>
                <span class="flex items-center gap-1"><i class="pi pi-check-circle"></i> Ready-Made</span>
                <span class="flex items-center gap-1 hidden sm:flex"><i class="pi pi-check-circle"></i> Alterations</span>
            </div>
        </div>

        <footer class="absolute bottom-4 text-brand-300 text-xs">
            Powered by <a href="https://twinfusion.com" target="_blank" class="text-brand-200 hover:text-white">TwinFusion</a>
        </footer>
    </div>
</template>
