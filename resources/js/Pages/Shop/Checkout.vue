<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';

const props = defineProps({
    cartItems: Array,
    garmentTypes: { type: Array, default: () => [] },
    fabrics: { type: Array, default: () => [] },
    company: Object,
});

// Load custom items from localStorage
const customCartItems = ref([]);
onMounted(() => {
    try {
        const saved = JSON.parse(localStorage.getItem('libas_cart') || '[]');
        customCartItems.value = saved.filter(i => i.type === 'custom');
    } catch(e) {}
});

const form = useForm({
    name: '',
    email: '',
    phone: '',
    notes: '',
    preferred_date: '',
    items: (props.cartItems || []).map(item => ({
        collection_id: item.id,
        // Which size or colour was ordered. The price is re-read on the server
        // from this, so it is the variation that decides what is charged.
        collection_variant_id: item.variant_id ?? null,
        quantity: item.cart_qty || 1,
        unit_price: Number(item.price),
    })),
    custom_items: [],
});

// Sync custom_items into form whenever customCartItems changes
function syncCustomItems() {
    form.custom_items = customCartItems.value.map(item => ({
        garment_type_slug: item.garment_type_slug,
        fabric_id: item.fabric_id,
        fabric_qty: item.fabric_qty,
        measurements: item.measurements,
        unit: item.unit,
        notes: item.notes || '',
    }));
}

onMounted(syncCustomItems);

// Display items for collection products
const displayItems = ref((props.cartItems || []).map(item => ({
    ...item,
    qty: item.cart_qty || 1,
    price: Number(item.price),
})));

const collectionTotal = computed(() => displayItems.value.reduce((sum, i) => sum + (i.price * i.qty), 0));
const customTotal = computed(() => customCartItems.value.reduce((sum, i) => sum + (i.price || 0), 0));
const cartTotal = computed(() => collectionTotal.value + customTotal.value);
const cartCount = computed(() => {
    const colCount = displayItems.value.reduce((sum, i) => sum + i.qty, 0);
    return colCount + customCartItems.value.length;
});

const hasItems = computed(() => displayItems.value.length > 0 || customCartItems.value.length > 0);

function formatCurrency(v) {
    return 'KES ' + Number(v).toLocaleString('en-KE', { minimumFractionDigits: 0 });
}

function updateQty(idx, delta) {
    const item = displayItems.value[idx];
    const newQty = item.qty + delta;
    if (newQty < 1) {
        displayItems.value.splice(idx, 1);
        form.items.splice(idx, 1);
        return;
    }
    if (newQty > item.stock_qty) return;
    item.qty = newQty;
    form.items[idx].quantity = newQty;
}

function removeItem(idx) {
    displayItems.value.splice(idx, 1);
    form.items.splice(idx, 1);
}

function removeCustomItem(idx) {
    customCartItems.value.splice(idx, 1);
    syncCustomItems();
    // Also update localStorage
    try {
        const saved = JSON.parse(localStorage.getItem('libas_cart') || '[]');
        const nonCustom = saved.filter(i => i.type !== 'custom');
        localStorage.setItem('libas_cart', JSON.stringify([...nonCustom, ...customCartItems.value]));
    } catch(e) {}
}

function submitOrder() {
    syncCustomItems();
    form.post(route('shop.place-order'), {
        onSuccess: () => {
            localStorage.removeItem('libas_cart');
        },
    });
}

function checkoutWhatsApp() {
    if (!hasItems.value) return;
    const phone = props.company.phone?.replace(/[^0-9]/g, '');
    if (!phone) { alert('Store phone number not configured'); return; }

    let msg = `Hi ${props.company.name}! I'd like to order:\n\n`;
    let num = 1;

    displayItems.value.forEach((item) => {
        msg += `${num++}. ${item.name}`;
        if (item.variant_label) msg += ` (${item.variant_label})`;
        else {
            if (item.size) msg += ` (${item.size})`;
            if (item.color) msg += ` - ${item.color}`;
        }
        msg += ` x${item.qty} @ ${formatCurrency(item.price)}\n`;
    });

    customCartItems.value.forEach((item) => {
        msg += `${num++}. CUSTOM: ${item.garment_type_name}`;
        msg += ` (${item.fabric_name}, ${item.fabric_qty}m)`;
        msg += ` @ ${formatCurrency(item.price)}\n`;
    });

    msg += `\nTotal: ${formatCurrency(cartTotal.value)}`;
    if (form.preferred_date) msg += `\nPreferred date: ${form.preferred_date}`;
    if (form.name) msg += `\n\nName: ${form.name}`;
    if (form.phone) msg += `\nPhone: ${form.phone}`;
    if (form.notes) msg += `\nNotes: ${form.notes}`;
    msg += `\n\nPlease confirm availability and payment details.`;

    window.open(`https://wa.me/${phone}?text=${encodeURIComponent(msg)}`, '_blank');
}

// Minimum date is today
const minDate = new Date().toISOString().split('T')[0];
</script>

<template>
    <Head :title="company.name + ' — Checkout'" />

    <div class="min-h-screen bg-gray-50">
        <!-- Header -->
        <header class="bg-white border-b border-gray-200 sticky top-0 z-30">
            <div class="max-w-4xl mx-auto px-4 sm:px-6">
                <div class="flex items-center justify-between h-14">
                    <Link :href="route('shop')" class="flex items-center gap-2 text-gray-600 hover:text-brand-600 transition-colors">
                        <i class="pi pi-arrow-left text-sm"></i>
                        <span class="text-sm font-medium">Back to Shop</span>
                    </Link>
                    <img :src="company.logo_url || '/logo.jpeg'" :alt="company.name" class="h-9 sm:h-11 w-auto max-w-[160px] sm:max-w-[220px] object-contain" />
                </div>
            </div>
        </header>

        <div class="max-w-4xl mx-auto px-4 sm:px-6 py-6 sm:py-10">
            <!-- Checkout steps indicator -->
            <div class="flex items-center justify-center gap-2 sm:gap-4 mb-8">
                <div class="flex items-center gap-1.5">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-brand-600 text-white text-xs font-bold">1</span>
                    <span class="text-xs sm:text-sm font-medium text-brand-700">Cart</span>
                </div>
                <div class="w-8 sm:w-12 h-px bg-brand-300"></div>
                <div class="flex items-center gap-1.5">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-brand-600 text-white text-xs font-bold">2</span>
                    <span class="text-xs sm:text-sm font-medium text-brand-700">Details</span>
                </div>
                <div class="w-8 sm:w-12 h-px bg-gray-300"></div>
                <div class="flex items-center gap-1.5">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-gray-200 text-gray-500 text-xs font-bold">3</span>
                    <span class="text-xs sm:text-sm font-medium text-gray-400">Confirm</span>
                </div>
            </div>

            <div v-if="!hasItems" class="text-center py-16">
                <i class="pi pi-shopping-cart text-6xl text-gray-200 block mb-4"></i>
                <h2 class="text-lg font-semibold text-gray-600">Your cart is empty</h2>
                <p class="text-sm text-gray-400 mt-1">Add some items from the shop first.</p>
                <Link :href="route('shop')" class="inline-flex items-center gap-1 mt-4 rounded-lg bg-brand-600 text-white px-6 py-2.5 text-sm font-medium hover:bg-brand-700">
                    <i class="pi pi-arrow-left text-xs"></i> Browse Shop
                </Link>
            </div>

            <div v-else class="grid grid-cols-1 lg:grid-cols-5 gap-6">
                <!-- Left: Order items + Form -->
                <div class="lg:col-span-3 space-y-6">
                    <!-- Collection Items -->
                    <div v-if="displayItems.length > 0" class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                        <div class="px-4 sm:px-6 py-4 border-b border-gray-100 bg-gray-50">
                            <h2 class="text-sm font-semibold text-gray-900"><i class="pi pi-shopping-cart mr-2 text-brand-600"></i>Ready-Made Items ({{ displayItems.length }})</h2>
                        </div>
                        <div class="divide-y divide-gray-100">
                            <div v-for="(item, idx) in displayItems" :key="item.id + '-' + (item.variant_id || 0)" class="flex gap-3 p-4">
                                <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-xl overflow-hidden flex-none bg-gray-100">
                                    <img v-if="item.image_url" :src="item.image_url" :alt="item.name" class="w-full h-full object-cover"/>
                                    <div v-else class="w-full h-full flex items-center justify-center">
                                        <i class="pi pi-image text-2xl text-gray-300"></i>
                                    </div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h4 class="text-sm font-semibold text-gray-900">{{ item.name }}</h4>
                                    <div class="text-xs text-gray-500 mt-0.5">
                                        <span v-if="item.variant_label" class="font-medium text-brand-600">{{ item.variant_label }}</span>
                                        <template v-else>
                                            <span v-if="item.size">{{ item.size }}</span>
                                            <span v-if="item.color"> · {{ item.color }}</span>
                                        </template>
                                        <span v-if="item.category"> · {{ item.category.name }}</span>
                                    </div>
                                    <div class="flex items-center justify-between mt-2">
                                        <div class="flex items-center gap-1.5">
                                            <button @click="updateQty(idx, -1)" class="w-7 h-7 rounded-lg bg-gray-100 text-gray-600 font-bold hover:bg-gray-200 text-xs">−</button>
                                            <span class="w-8 text-center text-sm font-bold">{{ item.qty }}</span>
                                            <button @click="updateQty(idx, 1)" class="w-7 h-7 rounded-lg bg-gray-100 text-gray-600 font-bold hover:bg-gray-200 text-xs">+</button>
                                            <button @click="removeItem(idx)" class="ml-2 text-xs text-red-400 hover:text-red-600"><i class="pi pi-trash"></i></button>
                                        </div>
                                        <span class="text-sm font-bold text-brand-700">{{ formatCurrency(item.price * item.qty) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Custom Tailored Items -->
                    <div v-if="customCartItems.length > 0" class="bg-white rounded-2xl border border-amber-200 shadow-sm overflow-hidden">
                        <div class="px-4 sm:px-6 py-4 border-b border-amber-100 bg-amber-50">
                            <h2 class="text-sm font-semibold text-gray-900"><i class="pi pi-pencil mr-2 text-amber-600"></i>Custom Tailored Items ({{ customCartItems.length }})</h2>
                        </div>
                        <div class="divide-y divide-amber-100">
                            <div v-for="(item, idx) in customCartItems" :key="item.uid" class="p-4">
                                <div class="flex items-start gap-3">
                                    <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-xl bg-amber-100 flex flex-col items-center justify-center flex-none">
                                        <i class="pi pi-pencil text-xl text-amber-600"></i>
                                        <span class="text-[8px] text-amber-500 font-bold mt-0.5">CUSTOM</span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-start justify-between">
                                            <div>
                                                <h4 class="text-sm font-semibold text-gray-900">{{ item.garment_type_name }} (Custom)</h4>
                                                <p class="text-xs text-gray-500 mt-0.5">{{ item.fabric_name }} · {{ item.fabric_qty }}m</p>
                                            </div>
                                            <div class="flex items-center gap-2 flex-none">
                                                <span class="text-sm font-bold text-amber-700">{{ formatCurrency(item.price) }}</span>
                                                <button @click="removeCustomItem(idx)" class="text-xs text-red-400 hover:text-red-600"><i class="pi pi-trash"></i></button>
                                            </div>
                                        </div>
                                        <!-- Measurement summary -->
                                        <div class="mt-2 bg-gray-50 rounded-lg p-2">
                                            <p class="text-[10px] font-semibold text-gray-400 uppercase mb-1">Measurements ({{ item.unit }})</p>
                                            <div class="flex flex-wrap gap-x-3 gap-y-0.5">
                                                <span v-for="(val, key) in item.measurements" :key="key" class="text-xs text-gray-600">
                                                    {{ key }}: <strong>{{ val }}</strong>
                                                </span>
                                            </div>
                                        </div>
                                        <!-- Price breakdown -->
                                        <div class="mt-1.5 flex gap-3 text-[10px] text-gray-400">
                                            <span>Craftsmanship: {{ formatCurrency(item.craftsmanship_fee) }}</span>
                                            <span>Fabric: {{ formatCurrency(item.fabric_cost) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Customer Details Form -->
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                        <div class="px-4 sm:px-6 py-4 border-b border-gray-100 bg-gray-50">
                            <h2 class="text-sm font-semibold text-gray-900"><i class="pi pi-user mr-2 text-brand-600"></i>Your Details</h2>
                            <p class="text-xs text-gray-500 mt-0.5">We'll use this to confirm your order</p>
                        </div>
                        <div class="p-4 sm:p-6 space-y-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Full Name <span class="text-red-400">*</span></label>
                                <input v-model="form.name" type="text" placeholder="e.g. Ali Hassan"
                                    class="w-full rounded-xl border-gray-200 text-sm focus:border-brand-600 focus:ring-brand-600 py-2.5" />
                                <p v-if="form.errors.name" class="text-xs text-red-500 mt-1">{{ form.errors.name }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Phone / WhatsApp <span class="text-red-400">*</span></label>
                                <div class="relative">
                                    <i class="pi pi-whatsapp absolute left-3 top-1/2 -translate-y-1/2 text-brand-600 text-sm"></i>
                                    <input v-model="form.phone" type="tel" placeholder="+254 7XX XXX XXX"
                                        class="w-full rounded-xl border-gray-200 text-sm focus:border-brand-600 focus:ring-brand-600 py-2.5 pl-9" />
                                </div>
                                <p v-if="form.errors.phone" class="text-xs text-red-500 mt-1">{{ form.errors.phone }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Email <span class="text-gray-400">(optional)</span></label>
                                <input v-model="form.email" type="email" placeholder="your@email.com"
                                    class="w-full rounded-xl border-gray-200 text-sm focus:border-brand-600 focus:ring-brand-600 py-2.5" />
                                <p v-if="form.errors.email" class="text-xs text-red-500 mt-1">{{ form.errors.email }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">
                                    <i class="pi pi-calendar text-xs mr-1 text-brand-600"></i>Preferred Date
                                    <span class="text-gray-400">(pickup/delivery)</span>
                                </label>
                                <input v-model="form.preferred_date" type="date" :min="minDate"
                                    class="w-full rounded-xl border-gray-200 text-sm focus:border-brand-600 focus:ring-brand-600 py-2.5" />
                                <p v-if="form.errors.preferred_date" class="text-xs text-red-500 mt-1">{{ form.errors.preferred_date }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Order Notes <span class="text-gray-400">(optional)</span></label>
                                <textarea v-model="form.notes" rows="2" placeholder="Any special instructions..."
                                    class="w-full rounded-xl border-gray-200 text-sm focus:border-brand-600 focus:ring-brand-600"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right: Order Summary -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden sticky top-20">
                        <div class="px-4 sm:px-6 py-4 border-b border-gray-100 bg-gradient-to-r from-brand-50 to-brand-50">
                            <h2 class="text-sm font-semibold text-gray-900"><i class="pi pi-receipt mr-2 text-brand-600"></i>Order Summary</h2>
                        </div>
                        <div class="p-4 sm:p-6 space-y-3">
                            <!-- Collection items -->
                            <div v-for="item in displayItems" :key="item.id + '-' + (item.variant_id || 0)" class="flex justify-between text-sm">
                                <span class="text-gray-600 truncate mr-2">{{ item.name }} x{{ item.qty }}</span>
                                <span class="font-medium text-gray-900 flex-none">{{ formatCurrency(item.price * item.qty) }}</span>
                            </div>
                            <!-- Custom items -->
                            <div v-for="item in customCartItems" :key="item.uid" class="flex justify-between text-sm">
                                <span class="text-amber-700 truncate mr-2"><i class="pi pi-pencil text-[10px] mr-1"></i>{{ item.garment_type_name }}</span>
                                <span class="font-medium text-gray-900 flex-none">{{ formatCurrency(item.price) }}</span>
                            </div>

                            <div class="border-t border-gray-100 pt-3 mt-3">
                                <div v-if="displayItems.length > 0" class="flex justify-between text-sm">
                                    <span class="text-gray-500">Ready-made items</span>
                                    <span class="font-medium">{{ formatCurrency(collectionTotal) }}</span>
                                </div>
                                <div v-if="customCartItems.length > 0" class="flex justify-between text-sm mt-1">
                                    <span class="text-gray-500">Custom tailoring</span>
                                    <span class="font-medium">{{ formatCurrency(customTotal) }}</span>
                                </div>
                                <div class="flex justify-between text-sm mt-1">
                                    <span class="text-gray-500">Delivery</span>
                                    <span class="text-brand-600 text-xs font-medium">To be confirmed</span>
                                </div>
                            </div>
                            <div class="border-t border-gray-200 pt-3">
                                <div class="flex justify-between">
                                    <span class="font-semibold text-gray-900">Total</span>
                                    <span class="text-xl font-bold text-brand-700">{{ formatCurrency(cartTotal) }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="p-4 sm:p-6 pt-0 space-y-2.5">
                            <button @click="checkoutWhatsApp()"
                                class="w-full rounded-xl bg-[#25D366] text-white py-3 text-sm font-semibold hover:bg-[#20bd5a] transition-colors shadow-sm flex items-center justify-center gap-2">
                                <i class="pi pi-whatsapp text-lg"></i> Order via WhatsApp
                            </button>
                            <button @click="submitOrder()" :disabled="form.processing || !form.name || !form.phone"
                                :class="[
                                    'w-full rounded-xl py-3 text-sm font-semibold transition-all shadow-sm flex items-center justify-center gap-2',
                                    form.processing || !form.name || !form.phone
                                        ? 'bg-gray-200 text-gray-400 cursor-not-allowed'
                                        : 'bg-gradient-to-r from-brand-600 to-brand-700 text-white hover:from-brand-700 hover:to-brand-800'
                                ]">
                                <i v-if="form.processing" class="pi pi-spin pi-spinner"></i>
                                <i v-else class="pi pi-check-circle text-sm"></i>
                                {{ form.processing ? 'Placing Order...' : 'Place Order' }}
                            </button>
                            <p class="text-[10px] text-gray-400 text-center mt-2">
                                Payment will be confirmed separately. Your order will be reserved.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="bg-gray-900 text-gray-400 py-6 mt-8">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 text-center text-xs">
                <p>Powered by <a href="https://twinfusion.com" target="_blank" class="text-brand-600 hover:text-brand-400">TwinFusion</a></p>
            </div>
        </footer>
    </div>
</template>
