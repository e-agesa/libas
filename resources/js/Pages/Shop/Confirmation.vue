<script setup>
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    order: Object,
    company: Object,
});

function formatCurrency(v) {
    return 'KES ' + Number(v).toLocaleString('en-KE', { minimumFractionDigits: 0 });
}

function shareWhatsApp() {
    const phone = props.company.phone?.replace(/[^0-9]/g, '');
    if (!phone) return;

    let msg = `Hi ${props.company.name}!\n\nI just placed order ${props.order.invoice_number}.\n`;
    msg += `Total: ${formatCurrency(props.order.total)}\n\n`;
    msg += `Please confirm and let me know the payment details.\n`;
    msg += `\nName: ${props.order.client?.name}`;
    msg += `\nPhone: ${props.order.client?.phone}`;

    window.open(`https://wa.me/${phone}?text=${encodeURIComponent(msg)}`, '_blank');
}
</script>

<template>
    <Head :title="company.name + ' — Order Confirmed'" />

    <div class="min-h-screen bg-gray-50">
        <!-- Header -->
        <header class="bg-white border-b border-gray-200">
            <div class="max-w-4xl mx-auto px-4 sm:px-6">
                <div class="flex items-center justify-center h-16">
                    <img :src="company.logo_url || '/logo.jpeg'" :alt="company.name" class="h-10 sm:h-12 w-auto max-w-[200px] object-contain" />
                </div>
            </div>
        </header>

        <div class="max-w-lg mx-auto px-4 sm:px-6 py-10 sm:py-16">
            <!-- Success animation -->
            <div class="text-center mb-8">
                <div class="inline-flex h-20 w-20 items-center justify-center rounded-full bg-brand-100 mb-4">
                    <i class="pi pi-check text-3xl text-brand-600"></i>
                </div>
                <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Order Placed!</h1>
                <p class="text-gray-500 mt-2">Thank you for your order. We'll be in touch shortly.</p>
            </div>

            <!-- Order details card -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="px-6 py-4 bg-gradient-to-r from-brand-50 to-brand-50 border-b border-brand-100">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-semibold text-gray-900">Order {{ order.invoice_number }}</span>
                        <span class="inline-flex rounded-full bg-blue-100 text-blue-700 px-2.5 py-0.5 text-xs font-medium">{{ order.status }}</span>
                    </div>
                    <p class="text-xs text-gray-500 mt-0.5">{{ new Date(order.date).toLocaleDateString('en-KE', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) }}</p>
                    <p v-if="order.due_date" class="text-xs text-amber-600 mt-0.5">
                        <i class="pi pi-calendar text-[10px] mr-1"></i>Preferred date: {{ new Date(order.due_date).toLocaleDateString('en-KE', { weekday: 'short', month: 'short', day: 'numeric' }) }}
                    </p>
                </div>

                <!-- Customer info -->
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="text-xs font-semibold text-gray-400 uppercase mb-2">Customer</h3>
                    <p class="text-sm font-medium text-gray-900">{{ order.client?.name }}</p>
                    <p v-if="order.client?.phone" class="text-xs text-gray-500 mt-0.5"><i class="pi pi-phone text-[10px] mr-1"></i>{{ order.client.phone }}</p>
                    <p v-if="order.client?.email" class="text-xs text-gray-500 mt-0.5"><i class="pi pi-envelope text-[10px] mr-1"></i>{{ order.client.email }}</p>
                </div>

                <!-- Items -->
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="text-xs font-semibold text-gray-400 uppercase mb-3">Items</h3>
                    <div class="space-y-3">
                        <div v-for="item in order.line_items" :key="item.id" class="flex items-start gap-3">
                            <div class="w-10 h-10 rounded-lg overflow-hidden flex-none"
                                 :class="item.item_type === 'custom' ? 'bg-amber-100' : 'bg-gray-100'">
                                <template v-if="item.item_type === 'custom'">
                                    <div class="w-full h-full flex items-center justify-center">
                                        <i class="pi pi-pencil text-amber-600 text-sm"></i>
                                    </div>
                                </template>
                                <template v-else>
                                    <img v-if="item.collection?.image_url" :src="item.collection.image_url" class="w-full h-full object-cover"/>
                                    <div v-else class="w-full h-full flex items-center justify-center"><i class="pi pi-image text-gray-300 text-xs"></i></div>
                                </template>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 truncate">{{ item.description }}</p>
                                <template v-if="item.item_type === 'custom'">
                                    <p class="text-xs text-gray-500">
                                        Fabric: {{ item.fabric?.name || 'N/A' }}
                                    </p>
                                    <div class="text-[10px] text-gray-400 mt-0.5">
                                        Craftsmanship: {{ formatCurrency(item.craftsmanship_fee) }} + Fabric: {{ formatCurrency(item.fabric_cost) }}
                                    </div>
                                </template>
                                <template v-else>
                                    <p class="text-xs text-gray-500">Qty: {{ item.quantity }} x {{ formatCurrency(item.unit_price) }}</p>
                                </template>
                            </div>
                            <span class="text-sm font-bold text-gray-900 flex-none">{{ formatCurrency(item.line_total) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Total -->
                <div class="px-6 py-4">
                    <div class="flex items-center justify-between">
                        <span class="font-semibold text-gray-900">Total</span>
                        <span class="text-xl font-bold text-brand-700">{{ formatCurrency(order.total) }}</span>
                    </div>
                    <p class="text-xs text-gray-400 mt-1">Payment to be confirmed by the store</p>
                </div>
            </div>

            <!-- Actions -->
            <div class="mt-6 space-y-3">
                <button @click="shareWhatsApp()" v-if="company.phone"
                    class="w-full rounded-xl bg-[#25D366] text-white py-3 text-sm font-semibold hover:bg-[#20bd5a] transition-colors shadow-sm flex items-center justify-center gap-2">
                    <i class="pi pi-whatsapp text-lg"></i> Confirm on WhatsApp
                </button>
                <Link :href="route('shop')"
                    class="w-full rounded-xl bg-white border border-gray-200 text-gray-700 py-3 text-sm font-semibold hover:bg-gray-50 transition-colors flex items-center justify-center gap-2">
                    <i class="pi pi-arrow-left text-xs"></i> Continue Shopping
                </Link>
            </div>
        </div>
    </div>
</template>
