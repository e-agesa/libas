<script setup>
import { ref, computed, watch } from 'vue';

const props = defineProps({
    garmentTypes: Array,
    fabrics: Array,
    show: Boolean,
});

const emit = defineEmits(['close', 'add-to-cart']);

const selectedGarmentType = ref(null);
const selectedFabricId = ref(null);
const fabricQty = ref(2);
const unit = ref('cm');
const measurementValues = ref({});
const notes = ref('');

// Reset form when modal opens
watch(() => props.show, (val) => {
    if (val) {
        selectedGarmentType.value = null;
        selectedFabricId.value = null;
        fabricQty.value = 2;
        unit.value = 'cm';
        measurementValues.value = {};
        notes.value = '';
    }
});

// When garment type changes, reset measurements and set default fabric qty
watch(selectedGarmentType, (gt) => {
    measurementValues.value = {};
    if (gt) {
        fabricQty.value = Number(gt.default_fabric_qty) || 2;
    }
});

const selectedFabric = computed(() => {
    if (!selectedFabricId.value) return null;
    return props.fabrics.find(f => f.id === selectedFabricId.value);
});

const craftsmanshipFee = computed(() => {
    return selectedGarmentType.value ? Number(selectedGarmentType.value.base_price) : 0;
});

const fabricCost = computed(() => {
    if (!selectedFabric.value) return 0;
    return Number(selectedFabric.value.price_per_unit) * fabricQty.value;
});

const totalPrice = computed(() => craftsmanshipFee.value + fabricCost.value);

const fields = computed(() => {
    if (!selectedGarmentType.value) return [];
    return selectedGarmentType.value.fields || [];
});

const allFieldsFilled = computed(() => {
    if (fields.value.length === 0) return false;
    return fields.value.every(f => measurementValues.value[f.slug] && Number(measurementValues.value[f.slug]) > 0);
});

const canAdd = computed(() => {
    return selectedGarmentType.value && selectedFabricId.value && fabricQty.value > 0 && allFieldsFilled.value;
});

function addToCart() {
    if (!canAdd.value) return;

    const item = {
        type: 'custom',
        uid: crypto.randomUUID(),
        garment_type_slug: selectedGarmentType.value.slug,
        garment_type_name: selectedGarmentType.value.name,
        garment_type_color: selectedGarmentType.value.color,
        fabric_id: selectedFabricId.value,
        fabric_name: selectedFabric.value.name,
        fabric_color: selectedFabric.value.color,
        fabric_type: selectedFabric.value.type,
        fabric_qty: fabricQty.value,
        measurements: { ...measurementValues.value },
        unit: unit.value,
        craftsmanship_fee: craftsmanshipFee.value,
        fabric_cost: fabricCost.value,
        price: totalPrice.value,
        qty: 1,
        notes: notes.value,
    };

    emit('add-to-cart', item);
    emit('close');
}

function formatCurrency(v) {
    return 'KES ' + Number(v).toLocaleString('en-KE', { minimumFractionDigits: 0 });
}

// Step tracking
const step = ref(1); // 1=garment, 2=measurements, 3=fabric, 4=review

function nextStep() { step.value++; }
function prevStep() { step.value--; }

watch(selectedGarmentType, () => { step.value = 1; });

const canGoStep2 = computed(() => !!selectedGarmentType.value);
const canGoStep3 = computed(() => allFieldsFilled.value);
const canGoStep4 = computed(() => !!selectedFabricId.value && fabricQty.value > 0);
</script>

<template>
    <Teleport to="body">
        <!-- Backdrop -->
        <transition name="fade">
            <div v-if="show" class="fixed inset-0 bg-black/60 z-50" @click="$emit('close')"></div>
        </transition>
        <!-- Modal -->
        <transition name="fade">
            <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.self="$emit('close')">
                <div class="bg-white rounded-2xl max-w-2xl w-full max-h-[90vh] overflow-hidden shadow-2xl flex flex-col">
                    <!-- Header -->
                    <div class="px-6 py-4 bg-gradient-to-r from-amber-500 to-orange-500 text-white flex items-center justify-between flex-none">
                        <div>
                            <h2 class="text-lg font-bold"><i class="pi pi-pencil mr-2"></i>Custom Tailoring Order</h2>
                            <p class="text-amber-100 text-xs">Design your perfect garment</p>
                        </div>
                        <button @click="$emit('close')" class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center hover:bg-white/30">
                            <i class="pi pi-times"></i>
                        </button>
                    </div>

                    <!-- Steps indicator -->
                    <div class="px-6 py-3 bg-gray-50 border-b border-gray-100 flex items-center justify-center gap-2 flex-none">
                        <template v-for="(label, idx) in ['Garment', 'Measurements', 'Fabric', 'Review']" :key="idx">
                            <div class="flex items-center gap-1.5">
                                <span :class="['flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold',
                                    step > idx + 1 ? 'bg-green-500 text-white' :
                                    step === idx + 1 ? 'bg-amber-500 text-white' :
                                    'bg-gray-200 text-gray-500']">
                                    <i v-if="step > idx + 1" class="pi pi-check text-[10px]"></i>
                                    <span v-else>{{ idx + 1 }}</span>
                                </span>
                                <span :class="['text-xs font-medium hidden sm:inline', step === idx + 1 ? 'text-amber-700' : 'text-gray-400']">{{ label }}</span>
                            </div>
                            <div v-if="idx < 3" :class="['w-6 sm:w-10 h-px', step > idx + 1 ? 'bg-green-300' : 'bg-gray-300']"></div>
                        </template>
                    </div>

                    <!-- Content -->
                    <div class="flex-1 overflow-y-auto p-6">
                        <!-- Step 1: Garment Type -->
                        <div v-if="step === 1">
                            <h3 class="text-sm font-semibold text-gray-900 mb-3">What would you like made?</h3>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                                <button
                                    v-for="gt in garmentTypes"
                                    :key="gt.id"
                                    @click="selectedGarmentType = gt"
                                    :class="['rounded-xl border-2 p-4 text-center transition-all',
                                        selectedGarmentType?.id === gt.id
                                            ? 'border-amber-500 bg-amber-50 shadow-md'
                                            : 'border-gray-200 hover:border-amber-300 hover:bg-amber-50/50']"
                                >
                                    <div class="w-10 h-10 rounded-full mx-auto mb-2 flex items-center justify-center" :style="{ background: gt.color + '20' }">
                                        <i class="pi pi-sparkles text-lg" :style="{ color: gt.color }"></i>
                                    </div>
                                    <span class="text-sm font-semibold text-gray-900 block">{{ gt.name }}</span>
                                    <span v-if="Number(gt.base_price) > 0" class="text-xs text-gray-500 mt-1 block">from {{ formatCurrency(gt.base_price) }}</span>
                                </button>
                            </div>
                        </div>

                        <!-- Step 2: Measurements -->
                        <div v-if="step === 2">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-sm font-semibold text-gray-900">
                                    <i class="pi pi-ruler mr-1 text-amber-500"></i> Measurements for {{ selectedGarmentType.name }}
                                </h3>
                                <div class="flex items-center gap-1 bg-gray-100 rounded-lg p-0.5">
                                    <button @click="unit = 'cm'" :class="['px-3 py-1 rounded-md text-xs font-medium transition-all', unit === 'cm' ? 'bg-white shadow text-amber-700' : 'text-gray-500']">cm</button>
                                    <button @click="unit = 'inches'" :class="['px-3 py-1 rounded-md text-xs font-medium transition-all', unit === 'inches' ? 'bg-white shadow text-amber-700' : 'text-gray-500']">inches</button>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div v-for="field in fields" :key="field.id">
                                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ field.name }} <span class="text-gray-400">({{ unit }})</span></label>
                                    <input
                                        v-model.number="measurementValues[field.slug]"
                                        type="number"
                                        step="0.1"
                                        min="0"
                                        :placeholder="field.name"
                                        class="w-full rounded-xl border-gray-200 text-sm focus:border-amber-500 focus:ring-amber-500 py-2.5"
                                    />
                                </div>
                            </div>
                            <div v-if="fields.length === 0" class="text-center py-8 text-gray-400">
                                <i class="pi pi-info-circle text-2xl mb-2 block"></i>
                                <p class="text-sm">No measurement fields configured for this garment type.</p>
                            </div>
                        </div>

                        <!-- Step 3: Fabric -->
                        <div v-if="step === 3">
                            <h3 class="text-sm font-semibold text-gray-900 mb-3"><i class="pi pi-palette mr-1 text-amber-500"></i> Choose your fabric</h3>
                            <div class="space-y-2 max-h-64 overflow-y-auto mb-4">
                                <button
                                    v-for="fabric in fabrics"
                                    :key="fabric.id"
                                    @click="selectedFabricId = fabric.id"
                                    :class="['w-full flex items-center gap-3 rounded-xl border-2 p-3 text-left transition-all',
                                        selectedFabricId === fabric.id
                                            ? 'border-amber-500 bg-amber-50'
                                            : 'border-gray-200 hover:border-amber-300']"
                                >
                                    <div class="w-10 h-10 rounded-lg flex-none flex items-center justify-center"
                                         :style="{ background: fabric.color || '#e5e7eb' }">
                                        <i v-if="!fabric.color" class="pi pi-stop text-gray-400"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <span class="text-sm font-medium text-gray-900 block truncate">{{ fabric.name }}</span>
                                        <span class="text-xs text-gray-500">{{ fabric.type }} · {{ fabric.color }}</span>
                                    </div>
                                    <div class="text-right flex-none">
                                        <span class="text-sm font-bold text-gray-900 block">{{ formatCurrency(fabric.price_per_unit) }}</span>
                                        <span class="text-[10px] text-gray-400">per meter</span>
                                    </div>
                                    <i v-if="selectedFabricId === fabric.id" class="pi pi-check-circle text-amber-500 flex-none"></i>
                                </button>
                            </div>

                            <div v-if="selectedFabricId" class="bg-gray-50 rounded-xl p-4 border border-gray-100">
                                <label class="block text-xs font-medium text-gray-600 mb-1">Fabric quantity (meters)</label>
                                <div class="flex items-center gap-3">
                                    <input v-model.number="fabricQty" type="number" step="0.5" min="0.5" max="20"
                                        class="w-32 rounded-xl border-gray-200 text-sm focus:border-amber-500 focus:ring-amber-500 py-2.5" />
                                    <span class="text-sm text-gray-500">= {{ formatCurrency(fabricCost) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Step 4: Review -->
                        <div v-if="step === 4">
                            <h3 class="text-sm font-semibold text-gray-900 mb-4"><i class="pi pi-eye mr-1 text-amber-500"></i> Review your custom order</h3>

                            <div class="space-y-4">
                                <!-- Garment -->
                                <div class="bg-gray-50 rounded-xl p-4 border border-gray-100">
                                    <div class="flex items-center gap-2 mb-2">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center" :style="{ background: selectedGarmentType.color + '20' }">
                                            <i class="pi pi-sparkles" :style="{ color: selectedGarmentType.color }"></i>
                                        </div>
                                        <span class="text-sm font-semibold text-gray-900">{{ selectedGarmentType.name }}</span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-500">Craftsmanship fee</span>
                                        <span class="font-medium">{{ formatCurrency(craftsmanshipFee) }}</span>
                                    </div>
                                </div>

                                <!-- Measurements -->
                                <div class="bg-gray-50 rounded-xl p-4 border border-gray-100">
                                    <p class="text-xs font-semibold text-gray-500 uppercase mb-2">Measurements ({{ unit }})</p>
                                    <div class="grid grid-cols-2 gap-x-4 gap-y-1">
                                        <div v-for="field in fields" :key="field.slug" class="flex justify-between text-sm">
                                            <span class="text-gray-500">{{ field.name }}</span>
                                            <span class="font-medium">{{ measurementValues[field.slug] || '-' }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Fabric -->
                                <div class="bg-gray-50 rounded-xl p-4 border border-gray-100">
                                    <div class="flex items-center gap-2 mb-2">
                                        <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-none"
                                             :style="{ background: selectedFabric?.color || '#e5e7eb' }">
                                        </div>
                                        <div>
                                            <span class="text-sm font-semibold text-gray-900 block">{{ selectedFabric?.name }}</span>
                                            <span class="text-xs text-gray-500">{{ fabricQty }} meters @ {{ formatCurrency(selectedFabric?.price_per_unit || 0) }}/m</span>
                                        </div>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-500">Fabric cost</span>
                                        <span class="font-medium">{{ formatCurrency(fabricCost) }}</span>
                                    </div>
                                </div>

                                <!-- Notes -->
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Special instructions (optional)</label>
                                    <textarea v-model="notes" rows="2" placeholder="e.g. embroidery style, pocket placement..."
                                        class="w-full rounded-xl border-gray-200 text-sm focus:border-amber-500 focus:ring-amber-500"></textarea>
                                </div>

                                <!-- Total -->
                                <div class="bg-amber-50 rounded-xl p-4 border border-amber-200">
                                    <div class="flex justify-between items-center">
                                        <span class="font-semibold text-gray-900">Total Price</span>
                                        <span class="text-xl font-bold text-amber-700">{{ formatCurrency(totalPrice) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="px-6 py-4 border-t border-gray-100 bg-gray-50 flex items-center justify-between flex-none">
                        <button v-if="step > 1" @click="prevStep()" class="text-sm text-gray-500 hover:text-gray-700 font-medium">
                            <i class="pi pi-arrow-left text-xs mr-1"></i> Back
                        </button>
                        <div v-else></div>

                        <div class="flex items-center gap-2">
                            <button v-if="step === 1 && canGoStep2" @click="nextStep()"
                                class="rounded-xl bg-amber-500 text-white px-6 py-2.5 text-sm font-semibold hover:bg-amber-600 transition-colors">
                                Next: Measurements <i class="pi pi-arrow-right text-xs ml-1"></i>
                            </button>
                            <button v-if="step === 2 && canGoStep3" @click="nextStep()"
                                class="rounded-xl bg-amber-500 text-white px-6 py-2.5 text-sm font-semibold hover:bg-amber-600 transition-colors">
                                Next: Fabric <i class="pi pi-arrow-right text-xs ml-1"></i>
                            </button>
                            <button v-if="step === 3 && canGoStep4" @click="nextStep()"
                                class="rounded-xl bg-amber-500 text-white px-6 py-2.5 text-sm font-semibold hover:bg-amber-600 transition-colors">
                                Review Order <i class="pi pi-arrow-right text-xs ml-1"></i>
                            </button>
                            <button v-if="step === 4" @click="addToCart()"
                                class="rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 text-white px-6 py-2.5 text-sm font-semibold hover:from-amber-600 hover:to-orange-600 transition-all shadow-sm">
                                <i class="pi pi-shopping-cart mr-1"></i> Add to Cart
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </transition>
    </Teleport>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
