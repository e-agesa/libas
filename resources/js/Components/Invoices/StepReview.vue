<script setup>
import { computed } from 'vue';

const props = defineProps({
    client: Object,
    lineItems: Array,
    contacts: Array,
    fabrics: Array,
    discount: [Number, String],
    discountType: String,
    tax: [Number, String],
});

const emit = defineEmits(['update:discount', 'update:discountType', 'update:tax']);

const subtotal = computed(() => {
    return props.lineItems.reduce((sum, item) => {
        const fee = parseFloat(item.craftsmanship_fee) || 0;
        const fabric = parseFloat(item.fabric_cost) || 0;
        const qty = parseInt(item.quantity) || 1;
        return sum + (fee + fabric) * qty;
    }, 0);
});

const discountAmount = computed(() => {
    const d = parseFloat(props.discount) || 0;
    if (props.discountType === 'percentage') {
        return Math.round(subtotal.value * d / 100 * 100) / 100;
    }
    return d;
});

const afterDiscount = computed(() => subtotal.value - discountAmount.value);

const taxAmount = computed(() => {
    const t = parseFloat(props.tax) || 0;
    return Math.round(afterDiscount.value * t / 100 * 100) / 100;
});

const total = computed(() => afterDiscount.value + taxAmount.value);

function getContactName(id) {
    return props.contacts?.find(c => c.id === id)?.name || '—';
}

function getFabricName(id) {
    return props.fabrics?.find(f => f.id === id)?.name || '—';
}

function formatCurrency(amount) {
    return 'KES ' + Number(amount).toLocaleString('en-KE', { minimumFractionDigits: 0 });
}
</script>

<template>
    <div>
        <h3 class="text-lg font-semibold text-gray-900 mb-1">Review Invoice</h3>
        <p class="text-sm text-gray-500 mb-4">Review line items and apply discounts or tax.</p>

        <!-- Client info -->
        <div class="rounded-lg bg-gray-50 p-3 mb-4 flex items-center gap-3">
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-green-100 text-green-700 font-bold text-sm">
                {{ client?.name?.charAt(0)?.toUpperCase() }}
            </div>
            <div>
                <p class="font-medium text-gray-900 text-sm">{{ client?.name }}</p>
                <p class="text-xs text-gray-500">{{ client?.phone }}</p>
            </div>
        </div>

        <!-- Line items summary -->
        <div class="rounded-lg border border-gray-200 overflow-hidden mb-4">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Person</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Fabric</th>
                        <th class="px-4 py-2 text-center text-xs font-medium text-gray-500">Qty</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500">Fee</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500">Fabric</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="(item, i) in lineItems" :key="i">
                        <td class="px-4 py-2 text-gray-900">{{ getContactName(item.contact_id) }}</td>
                        <td class="px-4 py-2 text-gray-600">{{ item.fabric_id ? getFabricName(item.fabric_id) : '—' }}</td>
                        <td class="px-4 py-2 text-center text-gray-600">{{ item.quantity }}</td>
                        <td class="px-4 py-2 text-right text-gray-900">{{ formatCurrency(item.craftsmanship_fee) }}</td>
                        <td class="px-4 py-2 text-right text-gray-600">{{ formatCurrency(item.fabric_cost || 0) }}</td>
                        <td class="px-4 py-2 text-right font-medium text-gray-900">{{ formatCurrency((parseFloat(item.craftsmanship_fee || 0) + parseFloat(item.fabric_cost || 0)) * (parseInt(item.quantity) || 1)) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Discount & Tax -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="text-xs font-medium text-gray-600 mb-1 block">Discount</label>
                <div class="flex gap-2">
                    <input
                        type="number"
                        :value="discount"
                        @input="emit('update:discount', parseFloat($event.target.value) || 0)"
                        min="0"
                        step="1"
                        class="flex-1 rounded-md border-gray-300 text-sm focus:border-green-500 focus:ring-green-500"
                        placeholder="0"
                    />
                    <select
                        :value="discountType"
                        @change="emit('update:discountType', $event.target.value)"
                        class="rounded-md border-gray-300 text-sm focus:border-green-500 focus:ring-green-500"
                    >
                        <option value="flat">KES</option>
                        <option value="percentage">%</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="text-xs font-medium text-gray-600 mb-1 block">Tax Rate (%)</label>
                <input
                    type="number"
                    :value="tax"
                    @input="emit('update:tax', parseFloat($event.target.value) || 0)"
                    min="0"
                    max="100"
                    step="1"
                    class="w-full rounded-md border-gray-300 text-sm focus:border-green-500 focus:ring-green-500"
                    placeholder="0"
                />
            </div>
        </div>

        <!-- Totals -->
        <div class="rounded-lg bg-gray-50 p-4 space-y-2">
            <div class="flex justify-between text-sm">
                <span class="text-gray-600">Subtotal</span>
                <span class="font-medium text-gray-900">{{ formatCurrency(subtotal) }}</span>
            </div>
            <div v-if="discountAmount > 0" class="flex justify-between text-sm">
                <span class="text-gray-600">Discount <span class="text-xs">({{ discountType === 'percentage' ? discount + '%' : 'flat' }})</span></span>
                <span class="font-medium text-red-600">-{{ formatCurrency(discountAmount) }}</span>
            </div>
            <div v-if="taxAmount > 0" class="flex justify-between text-sm">
                <span class="text-gray-600">Tax ({{ tax }}%)</span>
                <span class="font-medium text-gray-900">{{ formatCurrency(taxAmount) }}</span>
            </div>
            <div class="flex justify-between text-base border-t border-gray-200 pt-2">
                <span class="font-semibold text-gray-900">Total</span>
                <span class="font-bold text-green-700 text-lg">{{ formatCurrency(total) }}</span>
            </div>
        </div>
    </div>
</template>
