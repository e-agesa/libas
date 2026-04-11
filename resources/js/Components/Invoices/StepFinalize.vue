<script setup>
const props = defineProps({
    date: String,
    dueDate: String,
    paymentMethod: String,
    initialPayment: [Number, String],
    notes: String,
    total: Number,
});

const emit = defineEmits(['update:date', 'update:dueDate', 'update:paymentMethod', 'update:initialPayment', 'update:notes']);

function formatCurrency(amount) {
    return 'KES ' + Number(amount).toLocaleString('en-KE', { minimumFractionDigits: 0 });
}
</script>

<template>
    <div>
        <h3 class="text-lg font-semibold text-gray-900 mb-1">Finalize Invoice</h3>
        <p class="text-sm text-gray-500 mb-4">Set dates, payment, and notes.</p>

        <div class="space-y-4">
            <!-- Dates -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="text-xs font-medium text-gray-600 mb-1 block">Invoice Date *</label>
                    <input
                        type="date"
                        :value="date"
                        @input="emit('update:date', $event.target.value)"
                        class="w-full rounded-md border-gray-300 text-sm focus:border-brand-600 focus:ring-brand-600"
                        required
                    />
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-600 mb-1 block">Due Date</label>
                    <input
                        type="date"
                        :value="dueDate"
                        @input="emit('update:dueDate', $event.target.value)"
                        class="w-full rounded-md border-gray-300 text-sm focus:border-brand-600 focus:ring-brand-600"
                    />
                </div>
            </div>

            <!-- Payment -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="text-xs font-medium text-gray-600 mb-1 block">Payment Method</label>
                    <select
                        :value="paymentMethod"
                        @change="emit('update:paymentMethod', $event.target.value)"
                        class="w-full rounded-md border-gray-300 text-sm focus:border-brand-600 focus:ring-brand-600"
                    >
                        <option value="cash">Cash</option>
                        <option value="mpesa">M-Pesa</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="credit">Credit</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-600 mb-1 block">Initial Payment (KES)</label>
                    <input
                        type="number"
                        :value="initialPayment"
                        @input="emit('update:initialPayment', parseFloat($event.target.value) || 0)"
                        min="0"
                        :max="total"
                        step="100"
                        class="w-full rounded-md border-gray-300 text-sm focus:border-brand-600 focus:ring-brand-600"
                        placeholder="0"
                    />
                    <p class="text-xs text-gray-400 mt-1">Total: {{ formatCurrency(total) }}</p>
                </div>
            </div>

            <!-- Notes -->
            <div>
                <label class="text-xs font-medium text-gray-600 mb-1 block">Notes</label>
                <textarea
                    :value="notes"
                    @input="emit('update:notes', $event.target.value)"
                    rows="3"
                    class="w-full rounded-md border-gray-300 text-sm focus:border-brand-600 focus:ring-brand-600"
                    placeholder="Special instructions, delivery details, etc."
                ></textarea>
            </div>

            <!-- Summary -->
            <div class="rounded-lg bg-brand-50 border border-brand-100 p-4">
                <div class="flex justify-between items-center">
                    <div>
                        <p class="text-sm font-medium text-brand-700">Invoice Total</p>
                        <p class="text-xs text-brand-600 mt-0.5">
                            <span v-if="parseFloat(initialPayment) > 0">
                                After payment: {{ formatCurrency(total - (parseFloat(initialPayment) || 0)) }} balance
                            </span>
                            <span v-else>No payment recorded yet</span>
                        </p>
                    </div>
                    <span class="text-2xl font-bold text-brand-700">{{ formatCurrency(total) }}</span>
                </div>
            </div>
        </div>
    </div>
</template>
