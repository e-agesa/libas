<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import CustomOrderBuilder from '@/Components/Shop/CustomOrderBuilder.vue';

const props = defineProps({
    collections: Array,
    categories: Array,
    garmentTypes: { type: Array, default: () => [] },
    fabrics: { type: Array, default: () => [] },
    company: Object,
});

const searchTerm = ref('');
const selectedCategory = ref('');
const sortBy = ref('name');
const visibleCount = ref(60);
const cart = ref([]);
const showCart = ref(false);
const showMobileMenu = ref(false);
const quickViewItem = ref(null);
const chosenVariantId = ref(null);
const showCustomBuilder = ref(false);

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
    let items = [...(props.collections || [])];
    if (searchTerm.value) {
        const q = searchTerm.value.toLowerCase();
        items = items.filter(c =>
            c.name.toLowerCase().includes(q) ||
            c.sku?.toLowerCase().includes(q) ||
            c.description?.toLowerCase().includes(q) ||
            // The size and colour live on the variations, so searching "21.5"
            // would otherwise find nothing at all.
            (c.variants || []).some(v =>
                [v.size, v.color, v.design, v.sku].filter(Boolean)
                    .some(x => String(x).toLowerCase().includes(q)))
        );
    }
    if (selectedCategory.value) {
        items = items.filter(c => c.category_id == selectedCategory.value);
    }
    if (sortBy.value === 'price_asc') items.sort((a, b) => Number(a.price) - Number(b.price));
    else if (sortBy.value === 'price_desc') items.sort((a, b) => Number(b.price) - Number(a.price));
    else items.sort((a, b) => a.name.localeCompare(b.name));
    return items;
});

// Render only a window of the catalog for speed; "Load more" reveals the rest.
const visibleItems = computed(() => filteredItems.value.slice(0, visibleCount.value));
watch([searchTerm, selectedCategory, sortBy], () => { visibleCount.value = 60; });

const collectionCartItems = computed(() => cart.value.filter(c => c.type !== 'custom'));
const customCartItems = computed(() => cart.value.filter(c => c.type === 'custom'));

const cartCount = computed(() => cart.value.reduce((sum, i) => sum + (i.qty || 1), 0));
const cartTotal = computed(() => cart.value.reduce((sum, i) => sum + (i.price * (i.qty || 1)), 0));

function formatCurrency(v) {
    return 'KES ' + Number(v).toLocaleString('en-KE', { minimumFractionDigits: 0 });
}

/**
 * The sizes, colours or designs a customer can choose between.
 * A product with only one, unnamed, has nothing to choose — it is just itself.
 */
function variantsOf(item) {
    return (item.variants || []).filter(v => Number(v.stock_qty) > 0);
}

/** Which option the customer has picked in the quick view, if any. */
const chosenVariant = computed(() => {
    if (!quickViewItem.value || !chosenVariantId.value) return null;
    return variantsOf(quickViewItem.value).find(v => v.id === chosenVariantId.value) || null;
});

const quickViewImage = computed(() =>
    (chosenVariant.value && chosenVariant.value.image_url) || quickViewItem.value?.image_url || null
);

const quickViewPrice = computed(() => {
    if (!quickViewItem.value) return 0;
    if (chosenVariant.value) return variantPrice(quickViewItem.value, chosenVariant.value);
    return priceRange(quickViewItem.value).from;
});

// Null while a choice is still owed, so the panel does not promise stock of
// something the customer has not picked yet.
const quickViewStock = computed(() => {
    if (!quickViewItem.value) return null;
    if (chosenVariant.value) return Number(chosenVariant.value.stock_qty);
    if (hasChoices(quickViewItem.value)) return null;
    const only = variantsOf(quickViewItem.value)[0];
    return Number(only ? only.stock_qty : quickViewItem.value.stock_qty);
});

function hasChoices(item) {
    const vs = variantsOf(item);
    return vs.length > 1 || vs.some(v => v.size || v.color || v.design);
}

function variantLabel(v) {
    return [v.size, v.color, v.design].filter(Boolean).join(' · ') || 'Standard';
}

function variantPrice(item, v) {
    return Number(v && v.price != null ? v.price : item.price);
}

/** What the card shows when the options are not all the same money. */
function priceRange(item) {
    const vs = variantsOf(item);
    if (!vs.length) return { from: Number(item.price), varies: false };
    const prices = vs.map(v => variantPrice(item, v));
    const from = Math.min(...prices);
    return { from, varies: Math.max(...prices) !== from };
}

/** One cart line per product-and-variation, so two sizes are two lines. */
function cartLineFor(itemId, variantId) {
    return cart.value.find(c => c.type !== 'custom'
        && c.id === itemId
        && (c.variant_id ?? null) === (variantId ?? null));
}

function addToCart(item, variant = null) {
    // A lone unnamed variation is still what is being sold, so it carries the
    // price and the stock even though the customer was never asked to pick it.
    const only = variantsOf(item).length === 1 ? variantsOf(item)[0] : null;
    const v = variant || (hasChoices(item) ? null : only);

    if (hasChoices(item) && !variant) {
        // Nothing chosen yet — open the product so they can pick.
        quickViewItem.value = item;
        chosenVariantId.value = null;
        return;
    }

    const stock = Number(v ? v.stock_qty : item.stock_qty);
    const existing = cartLineFor(item.id, v?.id ?? null);

    if (existing) {
        if (existing.qty < stock) existing.qty++;
    } else {
        cart.value.push({
            type: 'collection',
            id: item.id,
            variant_id: v?.id ?? null,
            name: item.name,
            variant_label: v ? variantLabel(v) : '',
            price: variantPrice(item, v),
            image_url: (v && v.image_url) || item.image_url,
            size: v?.size ?? item.size,
            color: v?.color ?? item.color,
            stock_qty: stock,
            qty: 1,
        });
    }
    showCart.value = true;
}

function addCustomToCart(customItem) {
    cart.value.push(customItem);
    showCart.value = true;
}

function removeFromCart(index) {
    cart.value.splice(index, 1);
}

function updateQty(index, delta) {
    const item = cart.value[index];
    if (item.type === 'custom') return;
    const newQty = item.qty + delta;
    if (newQty < 1) return removeFromCart(index);
    if (newQty > item.stock_qty) return;
    item.qty = newQty;
}

function clearCart() {
    cart.value = [];
}

function isInCart(itemId, variantId = null) {
    return !!cartLineFor(itemId, variantId);
}

function cartQtyFor(itemId, variantId = null) {
    const c = cartLineFor(itemId, variantId);
    return c ? c.qty : 0;
}

/** How many of this product are in the basket across all its variations. */
function cartQtyForProduct(itemId) {
    return cart.value
        .filter(c => c.type !== 'custom' && c.id === itemId)
        .reduce((n, c) => n + c.qty, 0);
}

function cartIndexFor(itemId, variantId = null) {
    return cart.value.findIndex(c => c.type !== 'custom'
        && c.id === itemId
        && (c.variant_id ?? null) === (variantId ?? null));
}

function checkoutWhatsApp() {
    if (cart.value.length === 0) return;
    const phone = props.company.phone?.replace(/[^0-9]/g, '');
    if (!phone) { alert('Store phone number not configured'); return; }

    let msg = `Hi ${props.company.name}! I'd like to order:\n\n`;
    let num = 1;

    collectionCartItems.value.forEach((item) => {
        msg += `${num++}. ${item.name}`;
        // The variation is the whole point of the order — say which one.
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
        const meas = Object.entries(item.measurements).map(([k, v]) => `${k}: ${v}${item.unit}`).join(', ');
        msg += `   Measurements: ${meas}\n`;
        if (item.notes) msg += `   Notes: ${item.notes}\n`;
    });

    msg += `\nTotal: ${formatCurrency(cartTotal.value)}`;
    msg += `\n\nPlease confirm availability and payment details.`;

    window.open(`https://wa.me/${phone}?text=${encodeURIComponent(msg)}`, '_blank');
}

function checkoutOnline() {
    if (cart.value.length === 0) return;
    router.post(route('shop.checkout'), {
        items: collectionCartItems.value.map(c => ({ id: c.id, variant_id: c.variant_id ?? null, qty: c.qty })),
    });
}

const placeholderColors = ['#f0fdf4', '#eff6ff', '#fef3c7', '#fce7f3', '#f3e8ff', '#ecfeff', '#fef2f2', '#f0f9ff'];
function getPlaceholderBg(seed) {
    return placeholderColors[(seed || 0) % placeholderColors.length];
}

const placeholderIcons = ['pi-star', 'pi-heart', 'pi-tag', 'pi-gift', 'pi-bookmark', 'pi-bolt', 'pi-sparkles', 'pi-crown'];
function getPlaceholderIcon(seed) {
    return placeholderIcons[(seed || 0) % placeholderIcons.length];
}
</script>

<template>
    <Head :title="company.name + ' — Shop'" />

    <div class="min-h-screen bg-gray-50">
        <!-- Header -->
        <header class="bg-white border-b border-gray-200 sticky top-0 z-40">
            <div class="max-w-7xl mx-auto px-4 sm:px-6">
                <div class="flex items-center justify-between h-16 sm:h-20">
                    <Link :href="route('shop')" class="flex items-center py-1.5 shrink-0">
                        <img :src="company.logo_url || '/logo.jpeg'" :alt="company.name" class="h-11 sm:h-14 w-auto max-w-[190px] sm:max-w-[300px] object-contain" />
                    </Link>
                    <nav class="flex items-center gap-2 sm:gap-4">
                        <a v-if="company.phone" :href="'tel:' + company.phone" class="hidden md:inline-flex items-center gap-1 text-sm text-gray-600 hover:text-brand-600">
                            <i class="pi pi-phone text-xs"></i> {{ company.phone }}
                        </a>
                        <button @click="showCart = true" class="relative inline-flex items-center gap-1 rounded-full bg-brand-50 px-3 py-2 text-sm font-medium text-brand-700 hover:bg-brand-100 transition-colors">
                            <i class="pi pi-shopping-cart text-base"></i>
                            <span class="hidden sm:inline">Cart</span>
                            <span v-if="cartCount > 0" class="absolute -top-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white">{{ cartCount }}</span>
                        </button>
                    </nav>
                </div>
            </div>
        </header>

        <!-- Hero -->
        <section class="bg-gradient-to-br from-brand-600 via-brand-700 to-brand-900 text-white py-10 sm:py-16 relative overflow-hidden">
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
                <p class="text-brand-100 text-sm sm:text-lg max-w-2xl mx-auto mb-6">Custom-made garments crafted to perfection, plus off-the-shelf items ready to wear.</p>
                <div class="flex flex-col sm:flex-row gap-3 justify-center max-w-xl mx-auto">
                    <div class="flex-1 relative">
                        <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-brand-300 text-sm"></i>
                        <input
                            v-model="searchTerm"
                            type="text"
                            placeholder="Search items..."
                            class="w-full rounded-xl border-0 bg-white/20 backdrop-blur-sm py-3 pl-9 pr-4 text-sm text-white placeholder-brand-200 focus:ring-2 focus:ring-white/50 focus:bg-white/30"
                        />
                    </div>
                </div>
                <div class="flex items-center justify-center gap-4 sm:gap-6 mt-6 text-sm text-brand-100">
                    <span class="flex items-center gap-1"><i class="pi pi-truck"></i> Fast Delivery</span>
                    <span class="flex items-center gap-1"><i class="pi pi-shield"></i> Quality Guarantee</span>
                    <span class="flex items-center gap-1 hidden sm:flex"><i class="pi pi-whatsapp"></i> WhatsApp Orders</span>
                </div>
            </div>
        </section>

        <!-- Custom Order CTA -->
        <div v-if="garmentTypes.length > 0" class="max-w-7xl mx-auto px-4 sm:px-6 -mt-6 relative z-10 mb-4">
            <button @click="showCustomBuilder = true"
                class="w-full rounded-2xl bg-gradient-to-r from-amber-500 to-orange-500 text-white p-4 sm:p-5 shadow-lg hover:shadow-xl hover:from-amber-600 hover:to-orange-600 transition-all flex items-center justify-between group">
                <div class="flex items-center gap-3 sm:gap-4">
                    <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-xl bg-white/20 flex items-center justify-center flex-none">
                        <i class="pi pi-pencil text-xl sm:text-2xl"></i>
                    </div>
                    <div class="text-left">
                        <h3 class="text-base sm:text-lg font-bold">Design Your Own Garment</h3>
                        <p class="text-amber-100 text-xs sm:text-sm">Choose fabric, enter measurements, get it custom-made</p>
                    </div>
                </div>
                <i class="pi pi-arrow-right text-lg group-hover:translate-x-1 transition-transform hidden sm:block"></i>
            </button>
        </div>

        <!-- Categories -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-4 sm:py-6">
            <div class="flex gap-2 overflow-x-auto pb-2 scrollbar-hide">
                <button
                    @click="selectedCategory = ''"
                    :class="['rounded-full px-4 py-2 text-xs sm:text-sm font-medium transition-all whitespace-nowrap', !selectedCategory ? 'bg-brand-600 text-white shadow-md shadow-brand-200' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50']"
                >
                    All Items ({{ collections.length }})
                </button>
                <button
                    v-for="cat in categories"
                    :key="cat.id"
                    @click="selectedCategory = cat.id"
                    :class="['rounded-full px-4 py-2 text-xs sm:text-sm font-medium transition-all whitespace-nowrap', selectedCategory == cat.id ? 'bg-brand-600 text-white shadow-md shadow-brand-200' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50']"
                >
                    {{ cat.name }}
                </button>
            </div>
        </div>

        <!-- Products grid -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 pb-16">
            <div v-if="filteredItems.length" class="flex items-center justify-between mb-4">
                <p class="text-sm text-gray-500">{{ filteredItems.length }} item{{ filteredItems.length === 1 ? '' : 's' }}</p>
                <select v-model="sortBy" class="rounded-lg border-gray-200 text-sm text-gray-700 focus:border-brand-600 focus:ring-brand-600 py-1.5">
                    <option value="name">Sort: Name</option>
                    <option value="price_asc">Price: Low to High</option>
                    <option value="price_desc">Price: High to Low</option>
                </select>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-5">
                <div
                    v-for="item in visibleItems"
                    :key="item.id"
                    class="rounded-2xl bg-white border border-gray-100 shadow-sm overflow-hidden hover:shadow-lg transition-all duration-300 group"
                >
                    <div class="aspect-square relative overflow-hidden cursor-pointer" @click="quickViewItem = item">
                        <img v-if="item.image_url" :src="item.image_url" :alt="item.name" loading="lazy" decoding="async" width="400" height="400" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" />
                        <div v-else class="w-full h-full flex flex-col items-center justify-center" :style="{ background: getPlaceholderBg(item.id) }">
                            <i :class="['pi', getPlaceholderIcon(item.id), 'text-5xl sm:text-6xl opacity-30']" :style="{ color: '#C41E2A' }"></i>
                            <span class="text-xs text-gray-400 mt-2">{{ item.name }}</span>
                        </div>
                        <span v-if="item.stock_qty <= 5" class="absolute top-2 left-2 inline-flex rounded-full bg-red-500 text-white px-2 py-0.5 text-[10px] font-bold shadow">Only {{ item.stock_qty }} left</span>
                        <span v-else class="absolute top-2 left-2 inline-flex rounded-full bg-brand-600 text-white px-2 py-0.5 text-[10px] font-bold shadow">In Stock</span>
                        <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                            <span class="text-white text-xs font-medium bg-white/20 backdrop-blur rounded-full px-3 py-1.5"><i class="pi pi-eye mr-1"></i>Quick View</span>
                        </div>
                    </div>
                    <div class="p-3 sm:p-4">
                        <span v-if="item.category" class="inline-flex rounded-full bg-brand-100 text-brand-700 px-2 py-0.5 text-[10px] font-medium mb-1.5">{{ item.category.name }}</span>
                        <h3 class="text-xs sm:text-sm font-semibold text-gray-900 line-clamp-1">{{ item.name }}</h3>
                        <div class="flex items-center gap-1 mt-0.5">
                            <span v-if="item.size" class="text-[10px] sm:text-xs text-gray-400">{{ item.size }}</span>
                            <span v-if="item.color" class="text-[10px] sm:text-xs text-gray-400">· {{ item.color }}</span>
                        </div>
                        <p v-if="item.description" class="text-[10px] sm:text-xs text-gray-500 mt-1 line-clamp-2">{{ item.description }}</p>
                        <div class="mt-2 sm:mt-3 flex items-center justify-between">
                            <span class="text-sm sm:text-lg font-bold text-brand-700">
                                <span v-if="priceRange(item).varies" class="text-[10px] sm:text-xs font-medium text-gray-400">from </span>{{ formatCurrency(priceRange(item).from) }}
                            </span>
                            <span v-if="hasChoices(item)" class="text-[10px] sm:text-xs text-gray-400">{{ variantsOf(item).length }} options</span>
                        </div>

                        <!-- A product sold in sizes or colours needs the customer
                             to choose one, so the card opens it rather than
                             guessing on their behalf. -->
                        <button
                            v-if="hasChoices(item)"
                            @click="quickViewItem = item; chosenVariantId = null"
                            class="mt-2 w-full rounded-xl bg-gradient-to-r from-brand-600 to-brand-700 text-white py-2 sm:py-2.5 text-xs sm:text-sm font-medium hover:from-brand-600 hover:to-brand-800 transition-all shadow-sm hover:shadow-md active:scale-95"
                        >
                            <i class="pi pi-sliders-h mr-1"></i>
                            {{ cartQtyForProduct(item.id) ? 'Add another option' : 'Choose an option' }}
                        </button>
                        <button
                            v-else-if="!isInCart(item.id, variantsOf(item)[0]?.id ?? null)"
                            @click="addToCart(item)"
                            class="mt-2 w-full rounded-xl bg-gradient-to-r from-brand-600 to-brand-700 text-white py-2 sm:py-2.5 text-xs sm:text-sm font-medium hover:from-brand-600 hover:to-brand-800 transition-all shadow-sm hover:shadow-md active:scale-95"
                        >
                            <i class="pi pi-shopping-cart mr-1"></i> Add to Cart
                        </button>
                        <div v-else class="mt-2 flex items-center gap-1">
                            <button @click="updateQty(cartIndexFor(item.id, variantsOf(item)[0]?.id ?? null), -1)" class="flex-none w-8 h-8 rounded-lg bg-red-50 text-red-600 font-bold hover:bg-red-100 transition-colors text-sm">−</button>
                            <span class="flex-1 text-center text-sm font-bold text-brand-700">{{ cartQtyFor(item.id, variantsOf(item)[0]?.id ?? null) }} in cart</span>
                            <button @click="updateQty(cartIndexFor(item.id, variantsOf(item)[0]?.id ?? null), 1)" class="flex-none w-8 h-8 rounded-lg bg-brand-50 text-brand-600 font-bold hover:bg-brand-100 transition-colors text-sm">+</button>
                        </div>
                        <p v-if="hasChoices(item) && cartQtyForProduct(item.id)" class="mt-1 text-center text-[10px] sm:text-xs text-brand-600 font-medium">
                            {{ cartQtyForProduct(item.id) }} in cart
                        </p>
                    </div>
                </div>
            </div>

            <div v-if="visibleItems.length < filteredItems.length" class="text-center mt-8">
                <button @click="visibleCount += 60" class="rounded-xl bg-white border border-brand-200 text-brand-700 px-6 py-3 text-sm font-semibold hover:bg-brand-50 shadow-sm">
                    Load more ({{ filteredItems.length - visibleItems.length }} more)
                </button>
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
            <transition name="fade">
                <div v-if="showCart" class="fixed inset-0 bg-black/50 z-50" @click="showCart = false"></div>
            </transition>
            <transition name="slide-right">
                <div v-if="showCart" class="fixed inset-y-0 right-0 z-50 w-full sm:w-96 bg-white shadow-2xl flex flex-col">
                    <div class="flex items-center justify-between px-4 sm:px-6 py-4 bg-gradient-to-r from-brand-600 to-brand-700 text-white">
                        <h2 class="text-lg font-bold"><i class="pi pi-shopping-cart mr-2"></i>Your Cart ({{ cartCount }})</h2>
                        <button @click="showCart = false" class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center hover:bg-white/30">
                            <i class="pi pi-times"></i>
                        </button>
                    </div>

                    <div class="flex-1 overflow-y-auto p-4 space-y-3">
                        <div v-if="cart.length === 0" class="text-center py-16 text-gray-400">
                            <i class="pi pi-shopping-cart text-5xl mb-3 block opacity-30"></i>
                            <p class="font-medium">Your cart is empty</p>
                            <p class="text-sm mt-1">Browse our collection and add items!</p>
                            <button @click="showCart = false" class="mt-4 rounded-lg bg-brand-600 text-white px-6 py-2 text-sm font-medium hover:bg-brand-700">
                                Continue Shopping
                            </button>
                        </div>

                        <div v-for="(item, idx) in cart" :key="item.uid || item.id" class="flex gap-3 rounded-xl p-3 border border-gray-100"
                             :class="item.type === 'custom' ? 'bg-amber-50' : 'bg-gray-50'">
                            <div class="w-16 h-16 rounded-lg overflow-hidden flex-none" :class="item.type === 'custom' ? 'bg-amber-100' : 'bg-gray-200'">
                                <template v-if="item.type === 'custom'">
                                    <div class="w-full h-full flex flex-col items-center justify-center">
                                        <i class="pi pi-pencil text-lg text-amber-600"></i>
                                        <span class="text-[8px] text-amber-500 font-bold mt-0.5">CUSTOM</span>
                                    </div>
                                </template>
                                <template v-else>
                                    <img v-if="item.image_url" :src="item.image_url" :alt="item.name" class="w-full h-full object-cover"/>
                                    <div v-else class="w-full h-full flex items-center justify-center">
                                        <i class="pi pi-image text-xl text-gray-300"></i>
                                    </div>
                                </template>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h4 class="text-sm font-semibold text-gray-900 truncate">
                                    {{ item.type === 'custom' ? item.garment_type_name + ' (Custom)' : item.name }}
                                </h4>
                                <div class="text-xs text-gray-500">
                                    <template v-if="item.type === 'custom'">
                                        {{ item.fabric_name }} · {{ item.fabric_qty }}m
                                    </template>
                                    <template v-else>
                                        <span v-if="item.variant_label" class="font-medium text-brand-600">{{ item.variant_label }}</span>
                                        <template v-else>
                                            <span v-if="item.size">{{ item.size }}</span>
                                            <span v-if="item.color"> · {{ item.color }}</span>
                                        </template>
                                    </template>
                                </div>
                                <div class="flex items-center justify-between mt-1.5">
                                    <span class="text-sm font-bold" :class="item.type === 'custom' ? 'text-amber-700' : 'text-brand-700'">{{ formatCurrency(item.price * (item.qty || 1)) }}</span>
                                    <div class="flex items-center gap-1">
                                        <template v-if="item.type !== 'custom'">
                                            <button @click="updateQty(idx, -1)" class="w-6 h-6 rounded bg-gray-200 text-gray-600 text-xs font-bold hover:bg-gray-300">−</button>
                                            <span class="w-6 text-center text-xs font-bold">{{ item.qty }}</span>
                                            <button @click="updateQty(idx, 1)" class="w-6 h-6 rounded bg-gray-200 text-gray-600 text-xs font-bold hover:bg-gray-300">+</button>
                                        </template>
                                        <span v-else class="text-xs text-gray-400">x1</span>
                                        <button @click="removeFromCart(idx)" class="w-6 h-6 rounded bg-red-50 text-red-500 text-xs hover:bg-red-100 ml-1">
                                            <i class="pi pi-trash text-[10px]"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="cart.length > 0" class="border-t border-gray-200 p-4 sm:p-6 space-y-3 bg-gray-50">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-500">Subtotal ({{ cartCount }} items)</span>
                            <span class="text-xl font-bold text-gray-900">{{ formatCurrency(cartTotal) }}</span>
                        </div>
                        <button @click="checkoutWhatsApp()" class="w-full rounded-xl bg-[#25D366] text-white py-3 text-sm font-semibold hover:bg-[#20bd5a] transition-colors shadow-sm flex items-center justify-center gap-2">
                            <i class="pi pi-whatsapp text-lg"></i> Order via WhatsApp
                        </button>
                        <button @click="checkoutOnline()" class="w-full rounded-xl bg-gradient-to-r from-brand-600 to-brand-700 text-white py-3 text-sm font-semibold hover:from-brand-700 hover:to-brand-800 transition-all shadow-sm flex items-center justify-center gap-2">
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
                            <img v-if="quickViewImage" :src="quickViewImage" :alt="quickViewItem.name" class="w-full h-full object-cover"/>
                            <div v-else class="w-full h-full flex items-center justify-center bg-gradient-to-br from-brand-50 to-brand-50">
                                <i class="pi pi-image text-6xl text-gray-300"></i>
                            </div>
                            <button @click="quickViewItem = null" class="absolute top-3 right-3 w-8 h-8 rounded-full bg-white/80 flex items-center justify-center text-gray-600 hover:bg-white shadow">
                                <i class="pi pi-times text-sm"></i>
                            </button>
                        </div>
                        <div class="p-5 sm:p-6">
                            <span v-if="quickViewItem.category" class="inline-flex rounded-full bg-brand-100 text-brand-700 px-2.5 py-0.5 text-xs font-medium mb-2">{{ quickViewItem.category.name }}</span>
                            <h3 class="text-xl font-bold text-gray-900">{{ quickViewItem.name }}</h3>
                            <div class="flex items-center gap-2 mt-1 text-sm text-gray-500">
                                <span v-if="quickViewItem.sku" class="text-xs bg-gray-100 px-2 py-0.5 rounded">SKU: {{ quickViewItem.sku }}</span>
                                <span v-if="quickViewItem.size">Size: {{ quickViewItem.size }}</span>
                                <span v-if="quickViewItem.color">{{ quickViewItem.color }}</span>
                            </div>
                            <p v-if="quickViewItem.description" class="text-sm text-gray-600 mt-3">{{ quickViewItem.description }}</p>

                            <!-- Choosing the size, colour or design. Each one has
                                 its own price, its own stock and its own photo. -->
                            <div v-if="hasChoices(quickViewItem)" class="mt-4">
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2">Choose an option</p>
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        v-for="v in variantsOf(quickViewItem)"
                                        :key="v.id"
                                        type="button"
                                        @click="chosenVariantId = v.id"
                                        class="rounded-xl border px-3 py-2 text-left transition-all"
                                        :class="chosenVariantId === v.id
                                            ? 'border-brand-600 bg-brand-50 ring-1 ring-brand-600'
                                            : 'border-gray-200 hover:border-brand-300'"
                                    >
                                        <span class="block text-xs font-semibold text-gray-900">{{ variantLabel(v) }}</span>
                                        <span class="block text-[11px] text-gray-500">
                                            {{ formatCurrency(variantPrice(quickViewItem, v)) }}
                                            <span v-if="Number(v.stock_qty) <= 5" class="text-red-500"> · only {{ v.stock_qty }} left</span>
                                        </span>
                                    </button>
                                </div>
                            </div>

                            <div class="flex items-center justify-between mt-4">
                                <span class="text-2xl font-bold text-brand-700">{{ formatCurrency(quickViewPrice) }}</span>
                                <span v-if="quickViewStock !== null && quickViewStock <= 5" class="text-sm text-red-500 font-medium">Only {{ quickViewStock }} left!</span>
                                <span v-else-if="quickViewStock !== null" class="text-sm text-brand-600"><i class="pi pi-check-circle mr-1"></i>In Stock</span>
                            </div>

                            <button
                                @click="addToCart(quickViewItem, chosenVariant); if (!hasChoices(quickViewItem) || chosenVariant) quickViewItem = null;"
                                :disabled="hasChoices(quickViewItem) && !chosenVariant"
                                class="mt-4 w-full rounded-xl bg-gradient-to-r from-brand-600 to-brand-700 text-white py-3 text-sm font-semibold hover:from-brand-600 hover:to-brand-800 transition-all shadow-sm active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <i class="pi pi-shopping-cart mr-1"></i>
                                {{ hasChoices(quickViewItem) && !chosenVariant ? 'Choose an option first' : 'Add to Cart' }}
                            </button>
                        </div>
                    </div>
                </div>
            </transition>
        </Teleport>

        <!-- Custom Order Builder Modal -->
        <CustomOrderBuilder
            :show="showCustomBuilder"
            :garment-types="garmentTypes"
            :fabrics="fabrics"
            @close="showCustomBuilder = false"
            @add-to-cart="addCustomToCart"
        />

        <!-- Mobile Cart Bar -->
        <div v-if="cartCount > 0" class="fixed bottom-0 inset-x-0 z-30 sm:hidden bg-white border-t border-gray-200 shadow-lg px-4 py-3">
            <button @click="showCart = true" class="w-full flex items-center justify-between rounded-xl bg-gradient-to-r from-brand-600 to-brand-700 text-white px-4 py-3 shadow-md">
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
                        <img :src="company.logo_url || '/logo.jpeg'" :alt="company.name" class="h-16 w-auto max-w-[240px] object-contain mb-3 bg-white rounded-lg p-1.5" />
                        <p v-if="company.tagline" class="text-sm">{{ company.tagline }}</p>
                    </div>
                    <div>
                        <h4 class="text-white font-semibold text-sm mb-3">Contact</h4>
                        <div class="space-y-2 text-sm">
                            <p v-if="company.phone"><i class="pi pi-phone text-xs mr-2 text-brand-400"></i>{{ company.phone }}</p>
                            <p v-if="company.email"><i class="pi pi-envelope text-xs mr-2 text-brand-400"></i>{{ company.email }}</p>
                            <p v-if="company.address"><i class="pi pi-map-marker text-xs mr-2 text-brand-400"></i>{{ company.address }}</p>
                        </div>
                    </div>
                    <div>
                        <h4 class="text-white font-semibold text-sm mb-3">Quick Links</h4>
                        <div class="space-y-2 text-sm">
                            <Link :href="route('shop')" class="block hover:text-brand-400 transition-colors">Shop</Link>
                        </div>
                    </div>
                </div>
                <div class="border-t border-gray-800 mt-8 pt-6 text-center text-xs">
                    <p>Powered by <a href="https://twinfusion.com" target="_blank" class="text-brand-600 hover:text-brand-400">TwinFusion</a></p>
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
