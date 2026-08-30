<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import QuickClientModal from '@/Components/Invoices/QuickClientModal.vue';

const props = defineProps({
    collections: Array,
    clients: Array,
    preloadInvoice: Object,
});

const cart = ref([]);
const searchTerm = ref('');
const selectedCategory = ref('');
const clientId = ref(null);
const clientList = ref([...(props.clients || [])]);
const showClientModal = ref(false);

function onClientCreated(client) {
    showClientModal.value = false;
    clientList.value.unshift(client);
    clientId.value = client.id;   // serve the customer you just registered
    walkInName.value = '';
}
const walkInName = ref('');
const paymentMethod = ref('cash');
const discount = ref(0);
const notes = ref('');
const lastReceiptId = ref(null);
const clientOrders = ref([]);
const loadingOrders = ref(false);
const linkedInvoice = ref(null);
const includedOrders = ref([]); // order IDs included in this POS bill

// Preload invoice items into cart if coming from an order
if (props.preloadInvoice) {
    const inv = props.preloadInvoice;
    linkedInvoice.value = inv;
    clientId.value = inv.client?.id || null;
    notes.value = `Linked to ${inv.invoice_number}`;

    inv.line_items?.forEach(item => {
        if (item.item_type === 'collection' && item.collection) {
            const col = item.collection;
            const v = item.variant;
            cart.value.push({
                collection_id: col.id,
                // Carry the variation across, or settling the order takes the
                // stock off whichever variation happened to be first.
                variant_id: v?.id ?? item.collection_variant_id ?? null,
                name: col.name,
                variant_label: v ? [v.size, v.color, v.design].filter(Boolean).join(' · ') : '',
                sku: v?.sku || col.sku,
                size: v?.size ?? col.size,
                color: v?.color ?? col.color,
                unit_price: parseFloat(item.unit_price),
                quantity: item.quantity,
                max_qty: (v ? v.stock_qty : col.stock_qty) + item.quantity,
                image_url: (v?.image_path || col.image_path) ? `/storage/${v?.image_path || col.image_path}` : null,
                from_invoice: inv.invoice_number,
            });
        }
    });
}

const page = usePage();

// Watch for successful POS sale
watch(() => page.props.flash, (flash) => {
    if (flash?.pos_invoice_id) {
        lastReceiptId.value = flash.pos_invoice_id;
        cart.value = [];
        discount.value = 0;
        notes.value = '';
        includedOrders.value = [];
    }
}, { deep: true });

// Fetch client pending orders when client changes
watch(clientId, async (id) => {
    clientOrders.value = [];
    includedOrders.value = [];
    if (!id) return;
    loadingOrders.value = true;
    try {
        const resp = await fetch(route('pos.client-orders', id));
        clientOrders.value = await resp.json();
    } catch (e) {
        console.error(e);
    }
    loadingOrders.value = false;
});

const categories = computed(() => {
    const cats = new Set();
    props.collections?.forEach(c => {
        if (c.category?.name) cats.add(c.category.name);
    });
    return [...cats].sort();
});

// A product with variations is not one thing to sell — it is several. Each
// variation becomes its own tile carrying its own price, stock and photo, so
// the till can charge the right amount and take stock off the right one.
const sellables = computed(() => {
    const out = [];
    for (const c of (props.collections || [])) {
        const variants = (c.variants || []).filter(v => v.status !== 'inactive');
        const meaningful = variants.filter(v => v.size || v.color || v.design);

        if (variants.length > 1 || meaningful.length) {
            for (const v of variants) {
                const label = [v.size, v.color, v.design].filter(Boolean).join(' · ');
                out.push({
                    key: 'v' + v.id,
                    collection_id: c.id,
                    variant_id: v.id,
                    name: c.name,
                    variant_label: label || 'Standard',
                    sku: v.sku || c.sku,
                    size: v.size, color: v.color, design: v.design,
                    price: v.price != null ? v.price : c.price,
                    stock_qty: v.stock_qty,
                    image_url: v.image_url || c.image_url,
                    category: c.category,
                    description: c.description,
                });
            }
        } else {
            out.push({
                key: 'c' + c.id,
                collection_id: c.id,
                variant_id: variants.length === 1 ? variants[0].id : null,
                name: c.name,
                variant_label: '',
                sku: c.sku, size: c.size, color: c.color, design: null,
                price: c.price,
                stock_qty: c.stock_qty,
                // A lone unnamed variation is still what is being sold, so its
                // own photograph wins over the product's.
                image_url: (variants.length === 1 ? variants[0].image_url : null) || c.image_url,
                category: c.category,
                description: c.description,
            });
        }
    }
    return out.filter(x => Number(x.stock_qty) > 0);
});

const filteredCollections = computed(() => {
    let items = sellables.value;
    if (searchTerm.value) {
        const q = searchTerm.value.toLowerCase();
        items = items.filter(c =>
            c.name.toLowerCase().includes(q) ||
            c.sku?.toLowerCase().includes(q) ||
            c.variant_label?.toLowerCase().includes(q) ||
            c.size?.toLowerCase?.().includes(q) ||
            c.color?.toLowerCase?.().includes(q) ||
            c.design?.toLowerCase?.().includes(q) ||
            c.description?.toLowerCase().includes(q)
        );
    }
    if (selectedCategory.value) {
        items = items.filter(c => c.category?.name === selectedCategory.value);
    }
    return items;
});

function addToCart(item) {
    // Two variations of one product are two different lines in the cart.
    const existing = cart.value.find(c =>
        c.collection_id === item.collection_id && c.variant_id === (item.variant_id ?? null));

    if (existing) {
        if (existing.quantity < item.stock_qty) {
            existing.quantity++;
        }
        return;
    }

    cart.value.push({
        collection_id: item.collection_id,
        variant_id: item.variant_id ?? null,
        name: item.name,
        variant_label: item.variant_label,
        sku: item.sku,
        size: item.size,
        color: item.color,
        unit_price: parseFloat(item.price),
        quantity: 1,
        max_qty: item.stock_qty,
        image_url: item.image_url,
    });
}

function removeFromCart(index) {
    cart.value.splice(index, 1);
}

function updateQty(index, qty) {
    const item = cart.value[index];
    item.quantity = Math.max(1, Math.min(qty, item.max_qty));
}

// Toggle including a pending order in this POS bill
function toggleOrderInclusion(order) {
    const idx = includedOrders.value.findIndex(o => o.id === order.id);
    if (idx >= 0) {
        includedOrders.value.splice(idx, 1);
    } else {
        includedOrders.value.push({
            id: order.id,
            invoice_number: order.invoice_number,
            balance: parseFloat(order.balance),
        });
    }
}

function isOrderIncluded(orderId) {
    return includedOrders.value.some(o => o.id === orderId);
}

const includedOrdersTotal = computed(() => includedOrders.value.reduce((sum, o) => sum + o.balance, 0));

const subtotal = computed(() => cart.value.reduce((sum, i) => sum + i.unit_price * i.quantity, 0));
const total = computed(() => Math.max(0, subtotal.value + includedOrdersTotal.value - (parseFloat(discount.value) || 0)));
const cartCount = computed(() => cart.value.reduce((sum, i) => sum + i.quantity, 0));

// Check if we can complete (need cart items OR included orders)
const canComplete = computed(() => cart.value.length > 0 || includedOrders.value.length > 0);

const form = useForm({});

function completeSale() {
    if (!canComplete.value) return;

    form.transform(() => ({
        client_id: clientId.value || null,
        walk_in_name: walkInName.value || null,
        payment_method: paymentMethod.value,
        discount: parseFloat(discount.value) || 0,
        notes: notes.value || null,
        items: cart.value.map(i => ({
            collection_id: i.collection_id,
            collection_variant_id: i.variant_id ?? null,
            quantity: i.quantity,
            unit_price: i.unit_price,
        })),
        include_invoices: includedOrders.value.map(o => o.id),
    })).post(route('pos.store'), {
        preserveScroll: true,
        onSuccess: () => {
            cart.value = [];
            discount.value = 0;
            notes.value = '';
            includedOrders.value = [];
        },
    });
}

function addOrderItemToCart(lineItem, invoiceNumber) {
    const col = lineItem.collection;
    if (!col) return;

    const v = lineItem.variant;
    const variantId = v?.id ?? lineItem.collection_variant_id ?? null;

    // Two variations of one product are two cart lines, and settling the order
    // has to take the stock off the one that was actually ordered.
    const existing = cart.value.find(c =>
        c.collection_id === col.id && (c.variant_id ?? null) === variantId);

    if (existing) {
        existing.quantity = Math.min(existing.quantity + lineItem.quantity, existing.max_qty);
        return;
    }

    cart.value.push({
        collection_id: col.id,
        variant_id: variantId,
        name: col.name,
        variant_label: v ? [v.size, v.color, v.design].filter(Boolean).join(' · ') : '',
        sku: v?.sku || col.sku || null,
        size: v?.size ?? col.size ?? null,
        color: v?.color ?? col.color ?? null,
        unit_price: parseFloat(lineItem.unit_price || v?.price || col.price),
        quantity: lineItem.quantity,
        max_qty: (v ? v.stock_qty : col.stock_qty) || 999,
        image_url: (v?.image_path || col.image_path) ? `/storage/${v?.image_path || col.image_path}` : null,
        from_invoice: invoiceNumber,
    });
}

function printReceipt() {
    if (lastReceiptId.value) {
        window.open(route('pos.receipt', lastReceiptId.value), '_blank');
    }
}

function formatCurrency(v) {
    return 'KES ' + Number(v).toLocaleString('en-KE', { minimumFractionDigits: 0 });
}
</script>

<template>
    <Head title="Point of Sale" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    <i class="pi pi-shopping-cart text-brand-600 mr-2"></i> Point of Sale
                </h2>
                <button v-if="lastReceiptId" @click="printReceipt" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    <i class="pi pi-print text-xs"></i> Print Last Receipt
                </button>
            </div>
        </template>

        <div class="flex flex-col lg:flex-row gap-6">
            <!-- Left: Product grid -->
            <div class="flex-1">
                <!-- Search & Filter -->
                <div class="flex flex-col sm:flex-row gap-3 mb-4">
                    <div class="flex-1 relative">
                        <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                        <input
                            v-model="searchTerm"
                            type="text"
                            placeholder="Search items by name, SKU..."
                            class="w-full rounded-lg border-gray-300 py-2.5 pl-9 pr-4 text-sm focus:border-brand-600 focus:ring-brand-600"
                        />
                    </div>
                    <select v-model="selectedCategory" class="rounded-lg border-gray-300 text-sm focus:border-brand-600 focus:ring-brand-600">
                        <option value="">All Categories</option>
                        <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
                    </select>
                </div>

                <!-- Products grid -->
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                    <button
                        v-for="item in filteredCollections"
                        :key="item.key"
                        @click="addToCart(item)"
                        class="rounded-xl border border-gray-200 bg-white p-3 text-left hover:border-brand-300 hover:shadow-md transition-all group"
                        :class="{ 'opacity-50 cursor-not-allowed': item.stock_qty <= 0 }"
                        :disabled="item.stock_qty <= 0"
                    >
                        <div class="aspect-square rounded-lg bg-gray-100 mb-2 flex items-center justify-center overflow-hidden">
                            <img v-if="item.image_url" :src="item.image_url" :alt="item.name" class="w-full h-full object-cover rounded-lg" />
                            <i v-else class="pi pi-image text-3xl text-gray-300 group-hover:text-brand-400 transition-colors"></i>
                        </div>
                        <p class="text-sm font-medium text-gray-900 truncate">{{ item.name }}</p>
                        <p v-if="item.variant_label" class="text-xs font-medium text-brand-600 truncate mt-0.5">{{ item.variant_label }}</p>
                        <div v-else class="flex items-center gap-1 mt-0.5">
                            <span v-if="item.size" class="text-xs text-gray-400">{{ item.size }}</span>
                            <span v-if="item.color" class="text-xs text-gray-400">· {{ item.color }}</span>
                        </div>
                        <div class="flex items-center justify-between mt-2">
                            <span class="text-sm font-bold text-brand-700">{{ formatCurrency(item.price) }}</span>
                            <span class="text-xs text-gray-400">{{ item.stock_qty }} left</span>
                        </div>
                    </button>
                </div>

                <div v-if="filteredCollections.length === 0" class="text-center py-12 text-gray-400">
                    <i class="pi pi-search text-4xl mb-3 block"></i>
                    <p class="text-sm">No items found.</p>
                </div>
            </div>

            <!-- Right: Cart -->
            <div class="w-full lg:w-96 shrink-0">
                <div class="rounded-xl bg-white shadow-sm border border-gray-100 sticky top-20">
                    <!-- Cart header -->
                    <div class="px-4 py-3 bg-gray-50 rounded-t-xl border-b border-gray-100 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-700">
                            <i class="pi pi-shopping-cart text-xs mr-1"></i> Cart
                            <span v-if="cartCount" class="ml-1 rounded-full bg-brand-600 text-white text-xs px-2 py-0.5">{{ cartCount }}</span>
                        </h3>
                    </div>

                    <!-- Linked invoice banner -->
                    <div v-if="linkedInvoice" class="px-4 py-2 bg-amber-50 border-b border-amber-200 text-xs">
                        <div class="flex items-center gap-1 text-amber-700 font-medium">
                            <i class="pi pi-link text-[10px]"></i> Linked: {{ linkedInvoice.invoice_number }}
                            <span class="ml-auto text-amber-600">{{ formatCurrency(linkedInvoice.balance) }} due</span>
                        </div>
                    </div>

                    <!-- Cart items -->
                    <div class="max-h-[40vh] overflow-y-auto divide-y divide-gray-100">
                        <div v-for="(item, i) in cart" :key="(item.collection_id) + '-' + (item.variant_id ?? 0)" class="px-4 py-3 flex items-start gap-3" :class="item.from_invoice ? 'bg-amber-50/50' : ''">
                            <div class="w-10 h-10 rounded bg-gray-100 flex items-center justify-center shrink-0 overflow-hidden">
                                <img v-if="item.image_url" :src="item.image_url" class="w-full h-full object-cover rounded" />
                                <i v-else class="pi pi-box text-gray-300 text-sm"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 truncate">{{ item.name }}</p>
                                <p v-if="item.variant_label" class="text-xs text-brand-600 truncate">{{ item.variant_label }}</p>
                                <p class="text-xs text-gray-500">{{ formatCurrency(item.unit_price) }} each</p>
                                <span v-if="item.from_invoice" class="text-[10px] text-amber-600"><i class="pi pi-link text-[8px]"></i> from {{ item.from_invoice }}</span>
                                <div class="flex items-center gap-2 mt-1">
                                    <button @click="updateQty(i, item.quantity - 1)" class="w-6 h-6 rounded bg-gray-100 text-gray-600 hover:bg-gray-200 text-xs flex items-center justify-center">−</button>
                                    <span class="text-sm font-medium w-6 text-center">{{ item.quantity }}</span>
                                    <button @click="updateQty(i, item.quantity + 1)" class="w-6 h-6 rounded bg-gray-100 text-gray-600 hover:bg-gray-200 text-xs flex items-center justify-center">+</button>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-bold text-gray-900">{{ formatCurrency(item.unit_price * item.quantity) }}</p>
                                <button @click="removeFromCart(i)" class="text-xs text-red-500 hover:text-red-700 mt-1"><i class="pi pi-trash"></i></button>
                            </div>
                        </div>
                        <div v-if="cart.length === 0 && includedOrders.length === 0" class="px-4 py-8 text-center text-gray-400">
                            <i class="pi pi-shopping-cart text-3xl mb-2 block"></i>
                            <p class="text-sm">Cart is empty</p>
                            <p class="text-xs mt-1">Click items to add them</p>
                        </div>
                    </div>

                    <!-- Cart footer -->
                    <div v-if="canComplete" class="border-t border-gray-100 p-4 space-y-3">
                        <!-- Customer (optional) -->
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="text-xs font-medium text-gray-600 block">Customer (optional)</label>
                                <button
                                    type="button"
                                    @click="showClientModal = true"
                                    class="inline-flex items-center gap-1 text-xs font-medium text-brand-600 hover:text-brand-700 hover:underline"
                                >
                                    <i class="pi pi-user-plus text-[10px]"></i> Add Client
                                </button>
                            </div>
                            <select v-model="clientId" class="w-full rounded-md border-gray-300 text-sm focus:border-brand-600 focus:ring-brand-600">
                                <option :value="null">Walk-in customer</option>
                                <option v-for="c in clientList" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                        <div v-if="!clientId">
                            <input v-model="walkInName" type="text" placeholder="Walk-in customer name (optional)" class="w-full rounded-md border-gray-300 text-sm focus:border-brand-600 focus:ring-brand-600" />
                        </div>

                        <!-- Client pending orders with INCLUDE toggle -->
                        <div v-if="clientId && (loadingOrders || clientOrders.length)" class="rounded-lg border border-amber-200 bg-amber-50 p-3">
                            <p class="text-xs font-semibold text-amber-700 mb-2"><i class="pi pi-clock text-xs mr-1"></i> Pending Orders</p>
                            <div v-if="loadingOrders" class="text-xs text-amber-600">Loading...</div>
                            <div v-else class="space-y-2 max-h-48 overflow-y-auto">
                                <div v-for="order in clientOrders" :key="order.id"
                                    class="rounded-lg border p-2 transition-all"
                                    :class="isOrderIncluded(order.id)
                                        ? 'border-brand-400 bg-brand-50'
                                        : 'border-amber-100 bg-white/50'"
                                >
                                    <div class="flex items-center gap-2">
                                        <!-- Include checkbox -->
                                        <button @click="toggleOrderInclusion(order)"
                                            :class="['w-5 h-5 rounded border flex items-center justify-center shrink-0 transition-all',
                                                isOrderIncluded(order.id)
                                                    ? 'bg-brand-600 border-brand-600 text-white'
                                                    : 'border-gray-300 bg-white hover:border-amber-400']"
                                        >
                                            <i v-if="isOrderIncluded(order.id)" class="pi pi-check text-[10px]"></i>
                                        </button>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center justify-between text-xs">
                                                <span class="font-semibold text-gray-900">{{ order.invoice_number }}</span>
                                                <span class="font-bold" :class="isOrderIncluded(order.id) ? 'text-brand-700' : 'text-amber-700'">
                                                    {{ formatCurrency(order.balance) }}
                                                </span>
                                            </div>
                                            <div class="text-[10px] text-gray-500 mt-0.5">
                                                <span v-for="(li, idx) in order.line_items" :key="li.id">
                                                    {{ li.item_type === 'collection' ? (li.collection?.name || li.description) : (li.contact?.name ? li.contact.name + ' — ' : '') + (li.measurement?.garment_type || 'Custom') }}{{ idx < order.line_items.length - 1 ? ', ' : '' }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Per-item add buttons for collection items -->
                                    <div v-if="!isOrderIncluded(order.id)" class="mt-1.5 pl-7 space-y-0.5">
                                        <div v-for="li in order.line_items" :key="li.id" class="flex items-center justify-between text-[10px]">
                                            <span class="text-gray-500 truncate mr-2">
                                                {{ li.item_type === 'collection' ? (li.collection?.name || li.description) : (li.measurement?.garment_type || 'Custom') }}
                                                x{{ li.quantity }}
                                            </span>
                                            <button v-if="li.item_type === 'collection' && li.collection" @click="addOrderItemToCart(li, order.invoice_number)"
                                                class="flex-none bg-amber-200 text-amber-800 rounded px-1.5 py-0.5 hover:bg-amber-300 font-medium">
                                                + Cart
                                            </button>
                                            <span v-else class="text-gray-400 italic">custom</span>
                                        </div>
                                    </div>
                                    <div v-else class="mt-1 pl-7">
                                        <span class="text-[10px] text-brand-600 font-medium"><i class="pi pi-check-circle text-[8px] mr-0.5"></i> Bill included in this sale</span>
                                    </div>
                                </div>
                            </div>
                            <p v-if="!loadingOrders && clientOrders.length === 0" class="text-xs text-amber-600">No pending orders.</p>
                        </div>

                        <!-- Payment method -->
                        <div>
                            <label class="text-xs font-medium text-gray-600 mb-1 block">Payment Method</label>
                            <div class="grid grid-cols-2 gap-2">
                                <button v-for="m in [{v:'cash',l:'Cash',i:'pi-wallet'},{v:'mpesa',l:'M-Pesa',i:'pi-mobile'},{v:'bank_transfer',l:'Bank',i:'pi-building'},{v:'credit',l:'Credit',i:'pi-credit-card'}]"
                                    :key="m.v" type="button" @click="paymentMethod = m.v"
                                    :class="['rounded-lg border px-3 py-2 text-xs font-medium transition-colors flex items-center gap-1',
                                        paymentMethod === m.v ? 'border-brand-600 bg-brand-50 text-brand-700' : 'border-gray-200 text-gray-600 hover:bg-gray-50']"
                                >
                                    <i :class="['pi text-xs', m.i]"></i> {{ m.l }}
                                </button>
                            </div>
                        </div>

                        <!-- Discount -->
                        <div class="flex gap-3">
                            <div class="flex-1">
                                <label class="text-xs font-medium text-gray-600 mb-1 block">Discount (KES)</label>
                                <input v-model="discount" type="number" min="0" step="any" class="w-full rounded-md border-gray-300 text-sm focus:border-brand-600 focus:ring-brand-600" />
                            </div>
                        </div>

                        <!-- Totals -->
                        <div class="bg-gray-50 rounded-lg p-3 space-y-1">
                            <div v-if="cart.length > 0" class="flex justify-between text-sm text-gray-600">
                                <span>Cart Items</span>
                                <span>{{ formatCurrency(subtotal) }}</span>
                            </div>
                            <div v-for="o in includedOrders" :key="o.id" class="flex justify-between text-sm text-amber-700">
                                <span class="flex items-center gap-1"><i class="pi pi-file-edit text-[10px]"></i> {{ o.invoice_number }}</span>
                                <span>{{ formatCurrency(o.balance) }}</span>
                            </div>
                            <div v-if="discount > 0" class="flex justify-between text-sm text-red-600">
                                <span>Discount</span>
                                <span>-{{ formatCurrency(discount) }}</span>
                            </div>
                            <div class="flex justify-between text-lg font-bold text-gray-900 border-t border-gray-200 pt-2">
                                <span>Total</span>
                                <span class="text-brand-700">{{ formatCurrency(total) }}</span>
                            </div>
                        </div>

                        <!-- Notes -->
                        <textarea v-model="notes" rows="2" placeholder="Notes (optional)" class="w-full rounded-md border-gray-300 text-sm focus:border-brand-600 focus:ring-brand-600"></textarea>

                        <!-- Complete sale -->
                        <button
                            @click="completeSale"
                            :disabled="form.processing || !canComplete"
                            class="w-full rounded-lg bg-brand-600 px-4 py-3 text-sm font-bold text-white hover:bg-brand-700 disabled:opacity-50 transition-colors"
                        >
                            <i class="pi pi-check mr-1"></i>
                            {{ form.processing ? 'Processing...' : `Complete Sale — ${formatCurrency(total)}` }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <QuickClientModal

            :show="showClientModal"

            @close="showClientModal = false"

            @created="onClientCreated"

        />

    </AuthenticatedLayout>
</template>
