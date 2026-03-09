<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';

const props = defineProps({
    collections: Array,
    categories: Array,
    company: Object,
});

const searchTerm = ref('');
const selectedCategory = ref('');
const cart = ref([]);
const showCart = ref(false);
const showMobileMenu = ref(false);
const quickViewItem = ref(null);

// Load cart from localStorage
const savedCart = localStorage.getItem('libas_cart');
if (savedCart) {
    try { cart.value = JSON.parse(savedCart); } catch(e) {}
}

// Save cart to localStorage on change
watch(cart, (val) => {
    localStorage.setItem('libas_cart', JSON.stringify(val));
}, { deep: true });

const filteredItems = computed(() => {
    let items = props.collections || [];
    if (searchTerm.value) {
        const q = searchTerm.value.toLowerCase();
        items = items.filter(c =>
            c.name.toLowerCase().includes(q) ||
            c.sku?.toLowerCase().includes(q) ||
            c.description?.toLowerCase().includes(q)
        );
    }
    if (selectedCategory.value) {
        items = items.filter(c => c.category_id == selectedCategory.value);
    }
    return items;
});

const cartCount = computed(() => cart.value.reduce((sum, i) => sum + i.qty, 0));
const cartTotal = computed(() => cart.value.reduce((sum, i) => sum + (i.price * i.qty), 0));

function formatCurrency(v) {
    return 'KES ' + Number(v).toLocaleString('en-KE', { minimumFractionDigits: 0 });
}

function addToCart(item) {
    const existing = cart.value.find(c => c.id === item.id);
    if (existing) {
        if (existing.qty < item.stock_qty) existing.qty++;
    } else {
        cart.value.push({
            id: item.id,
            name: item.name,
            price: Number(item.price),
            image_url: item.image_url,
            size: item.size,
            color: item.color,
            stock_qty: item.stock_qty,
            qty: 1,
        });
    }
    showCart.value = true;
}

function removeFromCart(index) {
    cart.value.splice(index, 1);
}

function updateQty(index, delta) {
    const item = cart.value[index];
    const newQty = item.qty + delta;
    if (newQty < 1) return removeFromCart(index);
    if (newQty > item.stock_qty) return;
    item.qty = newQty;
}

function clearCart() {
    cart.value = [];
}

function isInCart(itemId) {
    return cart.value.some(c => c.id === itemId);
}

function cartQtyFor(itemId) {
    const c = cart.value.find(c => c.id === itemId);
    return c ? c.qty : 0;
}

function checkoutWhatsApp() {
    if (cart.value.length === 0) return;
    const phone = props.company.phone?.replace(/[^0-9]/g, '');
    if (!phone) { alert('Store phone number not configured'); return; }

    let msg = `Hi ${props.company.name}! I'd like to order:\n\n`;
    cart.value.forEach((item, i) => {
        msg += `${i + 1}. ${item.name}`;
        if (item.size) msg += ` (${item.size})`;
        if (item.color) msg += ` - ${item.color}`;
        msg += ` x${item.qty} @ ${formatCurrency(item.price)}\n`;
    });
    msg += `\nTotal: ${formatCurrency(cartTotal.value)}`;
    msg += `\n\nPlease confirm availability and payment details.`;

    window.open(`https://wa.me/${phone}?text=${encodeURIComponent(msg)}`, '_blank');
}

function checkoutOnline() {
    if (cart.value.length === 0) return;
    router.post(route('shop.checkout'), {
        items: cart.value.map(c => ({ id: c.id, qty: c.qty })),
    });
}

// Demo images for items without images
const placeholderColors = ['#f0fdf4', '#eff6ff', '#fef3c7', '#fce7f3', '#f3e8ff', '#ecfeff', '#fef2f2', '#f0f9ff'];
function getPlaceholderBg(index) {
    return placeholderColors[index % placeholderColors.length];
}

const placeholderIcons = ['pi-star', 'pi-heart', 'pi-tag', 'pi-gift', 'pi-bookmark', 'pi-bolt', 'pi-sparkles', 'pi-crown'];
function getPlaceholderIcon(index) {
    return placeholderIcons[index % placeholderIcons.length];
}
</script>

<template>
    <Head :title="company.name + ' — Shop'" />

    <div class="min-h-screen bg-gray-50">
        <!-- Header -->
        <header class="bg-white border-b border-gray-200 sticky top-0 z-40">
            <div class="max-w-7xl mx-auto px-4 sm:px-6">
                <div class="flex items-center justify-between h-14 sm:h-16">
                    <div class="flex items-center gap-2 sm:gap-3">
                        <div class="flex h-8 w-8 sm:h-10 sm:w-10 items-center justify-center rounded-lg bg-gradient-to-br from-green-500 to-emerald-600 text-white font-bold text-sm sm:text-lg shadow-md">L</div>
                        <div>
                            <h1 class="text-sm sm:text-lg font-bold text-gray-900">{{ company.name }}</h1>
                            <p v-if="company.tagline" class="text-[10px] sm:text-xs text-gray-500 -mt-0.5 hidden sm:block">{{ company.tagline }}</p>
                        </div>
                    </div>
                    <nav class="flex items-center gap-2 sm:gap-4">
                        <a v-if="company.phone" :href="'tel:' + company.phone" class="hidden md:inline-flex items-center gap-1 text-sm text-gray-600 hover:text-green-600">
                            <i class="pi pi-phone text-xs"></i> {{ company.phone }}
                        </a>
                        <!-- Cart button -->
                        <button @click="showCart = true" class="relative inline-flex items-center gap-1 rounded-full bg-green-50 px-3 py-2 text-sm font-medium text-green-700 hover:bg-green-100 transition-colors">
                            <i class="pi pi-shopping-cart text-base"></i>
                            <span class="hidden sm:inline">Cart</span>
                            <span v-if="cartCount > 0" class="absolute -top-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white">{{ cartCount }}</span>
                        </button>
                        <Link :href="route('login')" class="inline-flex items-center gap-1 rounded-lg bg-green-600 px-3 sm:px-4 py-2 text-xs sm:text-sm font-medium text-white hover:bg-green-700 transition-colors shadow-sm">
                            <i class="pi pi-sign-in text-xs"></i> <span class="hidden sm:inline">Login</span>
                        </Link>
                    </nav>
                </div>
            </div>
        </header>

        <!-- Hero -->
        <section class="bg-gradient-to-br from-green-600 via-emerald-600 to-teal-700 text-white py-10 sm:py-16 relative overflow-hidden">
            <!-- Decorative elements -->
            <div class="absolute inset-0 opacity-10">
                <div class="absolute top-10 left-10 w-32 h-32 rounded-full bg-white"></div>
                <div class="absolute bottom-5 right-20 w-48 h-48 rounded-full bg-white"></div>
                <div class="absolute top-20 right-10 w-20 h-20 rounded-full bg-white"></div>
            </div>
            <div class="max-w-7xl mx-auto px-4 sm:px-6 text-center relative">
                <div class="inline-flex items-center gap-1 rounded-full bg-white/20 px-3 py-1 text-xs font-medium mb-4">
                    <i class="pi pi-sparkles text-yellow-300"></i> Premium Quality Tailoring
                </div>
                <h2 class="text-2xl sm:text-4xl lg:text-5xl font-bold mb-3">Quality Tailoring &<br class="sm:hidden"/> Ready-Made Garments</h2>
                <p class="text-green-100 text-sm sm:text-lg max-w-2xl mx-auto mb-6">Custom-made garments crafted to perfection, plus off-the-shelf items ready to wear.</p>
                <div class="flex flex-col sm:flex-row gap-3 justify-center max-w-xl mx-auto">
                    <div class="flex-1 relative">
                        <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-green-300 text-sm"></i>
                        <input
                            v-model="searchTerm"
                            type="text"
                            placeholder="Search items..."
                            class="w-full rounded-xl border-0 bg-white/20 backdrop-blur-sm py-3 pl-9 pr-4 text-sm text-white placeholder-green-200 focus:ring-2 focus:ring-white/50 focus:bg-white/30"
                        />
                    </div>
                </div>
                <div class="flex items-center justify-center gap-6 mt-6 text-sm text-green-100">
                    <span class="flex items-center gap-1"><i class="pi pi-truck"></i> Fast Delivery</span>
                    <span class="flex items-center gap-1"><i class="pi pi-shield"></i> Quality Guarantee</span>
                    <span class="flex items-center gap-1 hidden sm:flex"><i class="pi pi-whatsapp"></i> WhatsApp Orders</span>
                </div>
            </div>
        </section>

        <!-- Categories -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-4 sm:py-6">
            <div class="flex gap-2 overflow-x-auto pb-2 scrollbar-hide">
                <button
                    @click="selectedCategory = ''"
                    :class="['rounded-full px-4 py-2 text-xs sm:text-sm font-medium transition-all whitespace-nowrap', !selectedCategory ? 'bg-green-600 text-white shadow-md shadow-green-200' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50']"
                >
                    All Items ({{ collections.length }})
                </button>
                <button
                    v-for="cat in categories"
                    :key="cat.id"
                    @click="selectedCategory = cat.id"
                    :class="['rounded-full px-4 py-2 text-xs sm:text-sm font-medium transition-all whitespace-nowrap', selectedCategory == cat.id ? 'bg-green-600 text-white shadow-md shadow-green-200' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50']"
                >
                    {{ cat.name }}
                </button>
            </div>
        </div>

        <!-- Products grid -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 pb-16">
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-5">
                <div
                    v-for="(item, idx) in filteredItems"
                    :key="item.id"
                    class="rounded-2xl bg-white border border-gray-100 shadow-sm overflow-hidden hover:shadow-lg transition-all duration-300 group"
                >
                    <!-- Image -->
                    <div class="aspect-square relative overflow-hidden cursor-pointer" @click="quickViewItem = item">
                        <img v-if="item.image_url" :src="item.image_url" :alt="item.name" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" />
                        <div v-else class="w-full h-full flex flex-col items-center justify-center" :style="{ background: getPlaceholderBg(idx) }">
                            <i :class="['pi', getPlaceholderIcon(idx), 'text-5xl sm:text-6xl opacity-30']" :style="{ color: '#059669' }"></i>
                            <span class="text-xs text-gray-400 mt-2">{{ item.name }}</span>
                        </div>
                        <!-- Stock badge -->
                        <span v-if="item.stock_qty <= 5" class="absolute top-2 left-2 inline-flex rounded-full bg-red-500 text-white px-2 py-0.5 text-[10px] font-bold shadow">Only {{ item.stock_qty }} left</span>
                        <span v-else class="absolute top-2 left-2 inline-flex rounded-full bg-green-500 text-white px-2 py-0.5 text-[10px] font-bold shadow">In Stock</span>
                        <!-- Quick view overlay -->
                        <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                            <span class="text-white text-xs font-medium bg-white/20 backdrop-blur rounded-full px-3 py-1.5"><i class="pi pi-eye mr-1"></i>Quick View</span>
                        </div>
                    </div>
                    <!-- Info -->
                    <div class="p-3 sm:p-4">
                        <span v-if="item.category" class="inline-flex rounded-full bg-green-100 text-green-700 px-2 py-0.5 text-[10px] font-medium mb-1.5">{{ item.category.name }}</span>
                        <h3 class="text-xs sm:text-sm font-semibold text-gray-900 line-clamp-1">{{ item.name }}</h3>
                        <div class="flex items-center gap-1 mt-0.5">
                            <span v-if="item.size" class="text-[10px] sm:text-xs text-gray-400">{{ item.size }}</span>
                            <span v-if="item.color" class="text-[10px] sm:text-xs text-gray-400">· {{ item.color }}</span>
                        </div>
                        <p v-if="item.description" class="text-[10px] sm:text-xs text-gray-500 mt-1 line-clamp-2">{{ item.description }}</p>
                        <div class="mt-2 sm:mt-3 flex items-center justify-between">
                            <span class="text-sm sm:text-lg font-bold text-green-700">{{ formatCurrency(item.price) }}</span>
                        </div>
                        <!-- Add to cart -->
                        <button
                            v-if="!isInCart(item.id)"
                            @click="addToCart(item)"
                            class="mt-2 w-full rounded-xl bg-gradient-to-r from-green-500 to-emerald-600 text-white py-2 sm:py-2.5 text-xs sm:text-sm font-medium hover:from-green-600 hover:to-emerald-700 transition-all shadow-sm hover:shadow-md active:scale-95"
                        >
                            <i class="pi pi-shopping-cart mr-1"></i> Add to Cart
                        </button>
                        <!-- Qty controls if in cart -->
                        <div v-else class="mt-2 flex items-center gap-1">
                            <button @click="updateQty(cart.findIndex(c => c.id === item.id), -1)" class="flex-none w-8 h-8 rounded-lg bg-red-50 text-red-600 font-bold hover:bg-red-100 transition-colors text-sm">−</button>
                            <span class="flex-1 text-center text-sm font-bold text-green-700">{{ cartQtyFor(item.id) }} in cart</span>
                            <button @click="updateQty(cart.findIndex(c => c.id === item.id), 1)" class="flex-none w-8 h-8 rounded-lg bg-green-50 text-green-600 font-bold hover:bg-green-100 transition-colors text-sm">+</button>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="filteredItems.length === 0" class="text-center py-16 text-gray-400">
                <i class="pi pi-search text-5xl mb-3 block"></i>
                <p class="text-lg font-medium">No items found</p>
                <p class="text-sm mt-1">Try a different search or category.</p>
            </div>
        </div>

        <!-- WhatsApp Floating Button -->
        <a v-if="company.phone" :href="'https://wa.me/' + company.phone?.replace(/[^0-9]/g, '')" target="_blank"
           class="fixed bottom-20 right-4 sm:bottom-6 sm:right-6 z-30 flex h-14 w-14 items-center justify-center rounded-full bg-[#25D366] text-white shadow-lg hover:shadow-xl hover:scale-110 transition-all">
            <i class="pi pi-whatsapp text-2xl"></i>
        </a>

        <!-- Cart Slide-over -->
        <Teleport to="body">
            <!-- Backdrop -->
            <transition name="fade">
                <div v-if="showCart" class="fixed inset-0 bg-black/50 z-50" @click="showCart = false"></div>
            </transition>
            <!-- Panel -->
            <transition name="slide-right">
                <div v-if="showCart" class="fixed inset-y-0 right-0 z-50 w-full sm:w-96 bg-white shadow-2xl flex flex-col">
                    <!-- Cart header -->
                    <div class="flex items-center justify-between px-4 sm:px-6 py-4 bg-gradient-to-r from-green-600 to-emerald-600 text-white">
                        <h2 class="text-lg font-bold"><i class="pi pi-shopping-cart mr-2"></i>Your Cart ({{ cartCount }})</h2>
                        <button @click="showCart = false" class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center hover:bg-white/30">
                            <i class="pi pi-times"></i>
                        </button>
                    </div>

                    <!-- Cart items -->
                    <div class="flex-1 overflow-y-auto p-4 space-y-3">
                        <div v-if="cart.length === 0" class="text-center py-16 text-gray-400">
                            <i class="pi pi-shopping-cart text-5xl mb-3 block opacity-30"></i>
                            <p class="font-medium">Your cart is empty</p>
                            <p class="text-sm mt-1">Browse our collection and add items!</p>
                            <button @click="showCart = false" class="mt-4 rounded-lg bg-green-600 text-white px-6 py-2 text-sm font-medium hover:bg-green-700">
                                Continue Shopping
                            </button>
                        </div>
                        <div v-for="(item, idx) in cart" :key="item.id" class="flex gap-3 rounded-xl bg-gray-50 p-3 border border-gray-100">
                            <div class="w-16 h-16 rounded-lg overflow-hidden flex-none bg-gray-200">
                                <img v-if="item.image_url" :src="item.image_url" :alt="item.name" class="w-full h-full object-cover"/>
                                <div v-else class="w-full h-full flex items-center justify-center">
                                    <i class="pi pi-image text-xl text-gray-300"></i>
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h4 class="text-sm font-semibold text-gray-900 truncate">{{ item.name }}</h4>
                                <div class="text-xs text-gray-500">
                                    <span v-if="item.size">{{ item.size }}</span>
                                    <span v-if="item.color"> · {{ item.color }}</span>
                                </div>
                                <div class="flex items-center justify-between mt-1.5">
                                    <span class="text-sm font-bold text-green-700">{{ formatCurrency(item.price * item.qty) }}</span>
                                    <div class="flex items-center gap-1">
                                        <button @click="updateQty(idx, -1)" class="w-6 h-6 rounded bg-gray-200 text-gray-600 text-xs font-bold hover:bg-gray-300">−</button>
                                        <span class="w-6 text-center text-xs font-bold">{{ item.qty }}</span>
                                        <button @click="updateQty(idx, 1)" class="w-6 h-6 rounded bg-gray-200 text-gray-600 text-xs font-bold hover:bg-gray-300">+</button>
                                        <button @click="removeFromCart(idx)" class="w-6 h-6 rounded bg-red-50 text-red-500 text-xs hover:bg-red-100 ml-1">
                                            <i class="pi pi-trash text-[10px]"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Cart footer -->
                    <div v-if="cart.length > 0" class="border-t border-gray-200 p-4 sm:p-6 space-y-3 bg-gray-50">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500">Subtotal ({{ cartCount }} items)</span>
                            <span class="text-xl font-bold text-gray-900">{{ formatCurrency(cartTotal) }}</span>
                        </div>
                        <!-- WhatsApp checkout -->
                        <button @click="checkoutWhatsApp()" class="w-full rounded-xl bg-[#25D366] text-white py-3 text-sm font-semibold hover:bg-[#20bd5a] transition-colors shadow-sm flex items-center justify-center gap-2">
                            <i class="pi pi-whatsapp text-lg"></i> Order via WhatsApp
                        </button>
                        <!-- Online checkout -->
                        <button @click="checkoutOnline()" class="w-full rounded-xl bg-gradient-to-r from-green-600 to-emerald-600 text-white py-3 text-sm font-semibold hover:from-green-700 hover:to-emerald-700 transition-all shadow-sm flex items-center justify-center gap-2">
                            <i class="pi pi-credit-card text-sm"></i> Checkout Online
                        </button>
                        <button @click="clearCart()" class="w-full text-center text-xs text-gray-400 hover:text-red-500 transition-colors py-1">
                            Clear Cart
                        </button>
                    </div>
                </div>
            </transition>
        </Teleport>

        <!-- Quick View Modal -->
        <Teleport to="body">
            <transition name="fade">
                <div v-if="quickViewItem" class="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4" @click.self="quickViewItem = null">
                    <div class="bg-white rounded-2xl max-w-lg w-full overflow-hidden shadow-2xl max-h-[90vh] overflow-y-auto">
                        <div class="relative aspect-square bg-gray-100">
                            <img v-if="quickViewItem.image_url" :src="quickViewItem.image_url" :alt="quickViewItem.name" class="w-full h-full object-cover"/>
                            <div v-else class="w-full h-full flex items-center justify-center bg-gradient-to-br from-green-50 to-emerald-50">
                                <i class="pi pi-image text-6xl text-gray-300"></i>
                            </div>
                            <button @click="quickViewItem = null" class="absolute top-3 right-3 w-8 h-8 rounded-full bg-white/80 flex items-center justify-center text-gray-600 hover:bg-white shadow">
                                <i class="pi pi-times text-sm"></i>
                            </button>
                        </div>
                        <div class="p-5 sm:p-6">
                            <span v-if="quickViewItem.category" class="inline-flex rounded-full bg-green-100 text-green-700 px-2.5 py-0.5 text-xs font-medium mb-2">{{ quickViewItem.category.name }}</span>
                            <h3 class="text-xl font-bold text-gray-900">{{ quickViewItem.name }}</h3>
                            <div class="flex items-center gap-2 mt-1 text-sm text-gray-500">
                                <span v-if="quickViewItem.sku" class="text-xs bg-gray-100 px-2 py-0.5 rounded">SKU: {{ quickViewItem.sku }}</span>
                                <span v-if="quickViewItem.size">Size: {{ quickViewItem.size }}</span>
                                <span v-if="quickViewItem.color">{{ quickViewItem.color }}</span>
                            </div>
                            <p v-if="quickViewItem.description" class="text-sm text-gray-600 mt-3">{{ quickViewItem.description }}</p>
                            <div class="flex items-center justify-between mt-4">
                                <span class="text-2xl font-bold text-green-700">{{ formatCurrency(quickViewItem.price) }}</span>
                                <span v-if="quickViewItem.stock_qty <= 5" class="text-sm text-red-500 font-medium">Only {{ quickViewItem.stock_qty }} left!</span>
                                <span v-else class="text-sm text-green-600"><i class="pi pi-check-circle mr-1"></i>In Stock</span>
                            </div>
                            <button @click="addToCart(quickViewItem); quickViewItem = null;" class="mt-4 w-full rounded-xl bg-gradient-to-r from-green-500 to-emerald-600 text-white py-3 text-sm font-semibold hover:from-green-600 hover:to-emerald-700 transition-all shadow-sm active:scale-95">
                                <i class="pi pi-shopping-cart mr-1"></i> Add to Cart
                            </button>
                        </div>
                    </div>
                </div>
            </transition>
        </Teleport>

        <!-- Mobile Cart Bar (fixed bottom) -->
        <div v-if="cartCount > 0" class="fixed bottom-0 inset-x-0 z-30 sm:hidden bg-white border-t border-gray-200 shadow-lg px-4 py-3">
            <button @click="showCart = true" class="w-full flex items-center justify-between rounded-xl bg-gradient-to-r from-green-600 to-emerald-600 text-white px-4 py-3 shadow-md">
                <div class="flex items-center gap-2">
                    <i class="pi pi-shopping-cart"></i>
                    <span class="font-semibold text-sm">{{ cartCount }} item{{ cartCount > 1 ? 's' : '' }}</span>
                </div>
                <span class="font-bold">{{ formatCurrency(cartTotal) }} <i class="pi pi-arrow-right text-xs ml-1"></i></span>
            </button>
        </div>

        <!-- Footer -->
        <footer class="bg-gray-900 text-gray-400 py-10" :class="{ 'pb-24 sm:pb-10': cartCount > 0 }">
            <div class="max-w-7xl mx-auto px-4 sm:px-6">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-8">
                    <div>
                        <div class="flex items-center gap-2 mb-3">
                            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-green-500 to-emerald-600 text-white font-bold text-sm">L</div>
                            <span class="text-white font-semibold">{{ company.name }}</span>
                        </div>
                        <p v-if="company.tagline" class="text-sm">{{ company.tagline }}</p>
                    </div>
                    <div>
                        <h4 class="text-white font-semibold text-sm mb-3">Contact</h4>
                        <div class="space-y-2 text-sm">
                            <p v-if="company.phone"><i class="pi pi-phone text-xs mr-2 text-green-400"></i>{{ company.phone }}</p>
                            <p v-if="company.email"><i class="pi pi-envelope text-xs mr-2 text-green-400"></i>{{ company.email }}</p>
                            <p v-if="company.address"><i class="pi pi-map-marker text-xs mr-2 text-green-400"></i>{{ company.address }}</p>
                        </div>
                    </div>
                    <div>
                        <h4 class="text-white font-semibold text-sm mb-3">Quick Links</h4>
                        <div class="space-y-2 text-sm">
                            <Link :href="route('shop')" class="block hover:text-green-400 transition-colors">Shop</Link>
                            <Link :href="route('login')" class="block hover:text-green-400 transition-colors">Admin Login</Link>
                        </div>
                    </div>
                </div>
                <div class="border-t border-gray-800 mt-8 pt-6 text-center text-xs">
                    <p>&copy; {{ new Date().getFullYear() }} {{ company.name }}. All rights reserved.</p>
                    <p class="mt-1">Powered by <a href="https://twinfusion.com" target="_blank" class="text-green-500 hover:text-green-400">TwinFusion</a></p>
                </div>
            </div>
        </footer>
    </div>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

.slide-right-enter-active { transition: transform 0.3s ease; }
.slide-right-leave-active { transition: transform 0.2s ease; }
.slide-right-enter-from { transform: translateX(100%); }
.slide-right-leave-to { transform: translateX(100%); }

.scrollbar-hide::-webkit-scrollbar { display: none; }
.scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
</style>
