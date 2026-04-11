<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import ContactForm from '@/Components/Contacts/ContactForm.vue';
import MeasurementForm from '@/Components/Measurements/MeasurementForm.vue';
import MeasurementCard from '@/Components/Measurements/MeasurementCard.vue';
import Modal from '@/Components/Modal.vue';
import { useGarmentTypes } from '@/composables/useGarmentTypes';

const props = defineProps({
    contact: Object,
});

const showEditContact = ref(false);
const showMeasurementModal = ref(false);
const editingMeasurement = ref(null);
const revisionParent = ref(null);
const showHistoryModal = ref(false);
const historySeries = ref([]);

const { garmentTypes, getColor, getLabel, getFields } = useGarmentTypes();

// Group measurements into revision series, showing only the latest of each series
const groupedMeasurements = computed(() => {
    const groups = {};
    if (!props.contact.measurements?.length) return groups;

    // Build series map: rootId => [measurements]
    const seriesMap = {};
    for (const m of props.contact.measurements) {
        const rootId = m.parent_id || m.id;
        if (!seriesMap[rootId]) seriesMap[rootId] = [];
        seriesMap[rootId].push(m);
    }
    // Sort each series by revision desc (latest first)
    for (const rootId of Object.keys(seriesMap)) {
        seriesMap[rootId].sort((a, b) => b.revision - a.revision);
    }

    // Group the latest measurement of each series by garment type
    for (const [rootId, series] of Object.entries(seriesMap)) {
        const latest = series[0];
        if (!groups[latest.garment_type]) groups[latest.garment_type] = [];
        groups[latest.garment_type].push({
            ...latest,
            _seriesCount: series.length,
            _series: series,
        });
    }

    // Sort within each garment type by date_taken desc
    for (const type of Object.keys(groups)) {
        groups[type].sort((a, b) => new Date(b.date_taken) - new Date(a.date_taken));
    }

    return groups;
});

// Ordered by configured sort_order, then any unknown types appended
const orderedTypes = computed(() => {
    const known = garmentTypes.value.map(t => t.slug).filter(s => groupedMeasurements.value[s]);
    const unknown = Object.keys(groupedMeasurements.value).filter(s => !known.includes(s));
    return [...known, ...unknown];
});

// Total unique measurement series count
const seriesCount = computed(() => {
    return Object.values(groupedMeasurements.value).reduce((sum, arr) => sum + arr.length, 0);
});

const relationshipLabels = {
    self: 'Self', son: 'Son', daughter: 'Daughter', brother: 'Brother',
    sister: 'Sister', wife: 'Wife', husband: 'Husband', employee: 'Employee', other: 'Other',
};

function openAddMeasurement() {
    editingMeasurement.value = null;
    revisionParent.value = null;
    showMeasurementModal.value = true;
}

function openEditMeasurement(measurement) {
    editingMeasurement.value = measurement;
    revisionParent.value = null;
    showMeasurementModal.value = true;
}

function openNewRevision(measurement) {
    editingMeasurement.value = null;
    revisionParent.value = measurement;
    showMeasurementModal.value = true;
}

function closeMeasurementModal() {
    showMeasurementModal.value = false;
    editingMeasurement.value = null;
    revisionParent.value = null;
}

function deleteMeasurement(measurement) {
    if (confirm(`Delete "${measurement.label || measurement.garment_type}" measurement?`)) {
        router.delete(route('measurements.destroy', measurement.id));
    }
}

function openHistory(measurement) {
    historySeries.value = measurement._series || [measurement];
    showHistoryModal.value = true;
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    return new Date(dateStr).toLocaleDateString('en-KE', { day: 'numeric', month: 'short', year: 'numeric' });
}
</script>

<template>
    <Head :title="`${contact.name} — Measurements`" />
    <AuthenticatedLayout>
        <template #breadcrumb>
            <nav class="flex items-center gap-2 text-sm text-gray-500">
                <Link :href="route('clients.index')" class="hover:text-brand-600">Clients</Link>
                <i class="pi pi-angle-right text-xs"></i>
                <Link :href="route('clients.show', contact.client.id)" class="hover:text-brand-600">{{ contact.client.name }}</Link>
                <i class="pi pi-angle-right text-xs"></i>
                <span class="text-gray-900 font-medium">{{ contact.name }}</span>
            </nav>
        </template>

        <!-- Contact Header -->
        <div class="mb-6 rounded-xl bg-white p-6 shadow-sm border border-gray-100">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                <div class="flex items-start gap-4">
                    <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl bg-brand-100 text-brand-700 text-xl font-bold">
                        {{ contact.name.charAt(0).toUpperCase() }}
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">{{ contact.name }}</h1>
                        <div class="mt-1 flex flex-wrap items-center gap-3 text-sm text-gray-500">
                            <span v-if="contact.relationship" class="flex items-center gap-1">
                                <i class="pi pi-users text-xs"></i>
                                {{ relationshipLabels[contact.relationship] || contact.relationship }}
                                <span class="text-gray-400">of</span>
                                <Link :href="route('clients.show', contact.client.id)" class="text-brand-600 hover:underline">{{ contact.client.name }}</Link>
                            </span>
                            <span v-if="contact.phone" class="flex items-center gap-1"><i class="pi pi-phone text-xs"></i> {{ contact.phone }}</span>
                            <span v-if="contact.gender" class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium capitalize">{{ contact.gender }}</span>
                            <span v-if="contact.age_group" class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium capitalize">{{ contact.age_group }}</span>
                        </div>
                    </div>
                </div>
                <div class="flex gap-2">
                    <button @click="showEditContact = true" class="inline-flex items-center gap-1 rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        <i class="pi pi-pencil text-xs"></i> Edit
                    </button>
                    <button @click="openAddMeasurement" class="inline-flex items-center gap-1 rounded-lg bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700">
                        <i class="pi pi-plus text-xs"></i> Add Measurement
                    </button>
                </div>
            </div>

            <!-- Stats -->
            <div class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-4 border-t border-gray-100 pt-4">
                <div class="text-center">
                    <p class="text-2xl font-bold text-gray-900">{{ seriesCount }}</p>
                    <p class="text-xs text-gray-500">Profiles</p>
                </div>
                <template v-for="type in orderedTypes.slice(0, 3)" :key="type">
                    <div class="text-center">
                        <p :class="[getColor(type).text, 'text-2xl font-bold']">{{ groupedMeasurements[type].length }}</p>
                        <p class="text-xs text-gray-500">{{ getLabel(type) }}</p>
                    </div>
                </template>
            </div>
        </div>

        <!-- Measurements grouped by garment type -->
        <div v-if="contact.measurements?.length" class="space-y-6">
            <div v-for="type in orderedTypes" :key="type">
                <!-- Garment type header -->
                <div class="flex items-center gap-3 mb-3">
                    <span
                        :class="[getColor(type).bg, getColor(type).text]"
                        class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-sm font-semibold"
                    >
                        <i class="pi pi-bookmark text-xs"></i>
                        {{ getLabel(type) }}
                    </span>
                    <span class="text-sm text-gray-400">{{ groupedMeasurements[type].length }} profile{{ groupedMeasurements[type].length !== 1 ? 's' : '' }}</span>
                    <div class="flex-1 border-t border-gray-200"></div>
                </div>

                <!-- Measurement cards — shows latest revision of each series -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <MeasurementCard
                        v-for="measurement in groupedMeasurements[type]"
                        :key="measurement.id"
                        :measurement="measurement"
                        :revision-count="measurement._seriesCount"
                        :is-latest="true"
                        @edit="openEditMeasurement(measurement)"
                        @delete="deleteMeasurement(measurement)"
                        @new-revision="openNewRevision(measurement)"
                        @show-history="openHistory(measurement)"
                    />
                </div>
            </div>
        </div>

        <!-- Empty state -->
        <div v-else class="rounded-xl bg-white p-12 shadow-sm border border-gray-100 text-center text-gray-400">
            <i class="pi pi-sliders-h text-5xl mb-4 block"></i>
            <p class="text-lg font-medium">No measurements yet</p>
            <p class="text-sm mt-1">Take the first measurement for {{ contact.name }}.</p>
            <button @click="openAddMeasurement" class="mt-4 inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
                <i class="pi pi-plus text-xs"></i> Add Measurement
            </button>
        </div>

        <!-- Modals -->
        <ContactForm :show="showEditContact" :client-id="contact.client_id" :contact="contact" @close="showEditContact = false" />
        <MeasurementForm
            :show="showMeasurementModal"
            :contact-id="contact.id"
            :measurement="editingMeasurement"
            :parent-measurement="revisionParent"
            @close="closeMeasurementModal"
        />

        <!-- Revision History Modal -->
        <Modal :show="showHistoryModal" @close="showHistoryModal = false" max-width="3xl">
            <div class="p-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-semibold text-gray-900">
                        <i class="pi pi-history mr-2"></i>Revision History
                    </h3>
                    <button @click="showHistoryModal = false" class="p-1.5 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100">
                        <i class="pi pi-times"></i>
                    </button>
                </div>

                <div v-if="historySeries.length" class="space-y-4">
                    <div
                        v-for="(rev, idx) in historySeries"
                        :key="rev.id"
                        :class="[
                            'rounded-lg border p-4',
                            idx === 0 ? 'border-brand-200 bg-brand-50/50' : 'border-gray-200 bg-white',
                        ]"
                    >
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center gap-2">
                                <span v-if="idx === 0" class="inline-flex rounded-full bg-brand-100 text-brand-700 px-2 py-0.5 text-xs font-semibold">
                                    Latest
                                </span>
                                <span class="inline-flex rounded-full bg-amber-100 text-amber-700 px-2 py-0.5 text-xs font-semibold">
                                    Rev {{ rev.revision }}
                                </span>
                                <span class="text-sm font-medium text-gray-900">{{ rev.label || rev.garment_type }}</span>
                            </div>
                            <span class="text-xs text-gray-400">{{ formatDate(rev.date_taken) }}</span>
                        </div>

                        <!-- Values grid -->
                        <div class="grid grid-cols-3 sm:grid-cols-4 gap-x-4 gap-y-1">
                            <template v-for="field in getFields(rev.garment_type)" :key="field.slug">
                                <div v-if="rev.values?.[field.slug] != null" class="flex justify-between items-baseline py-0.5">
                                    <span class="text-xs text-gray-500">{{ field.name }}</span>
                                    <span class="text-sm font-medium text-gray-900">
                                        {{ rev.values[field.slug] }}
                                        <span class="text-xs text-gray-400">{{ rev.unit }}</span>
                                        <!-- Show diff from previous revision -->
                                        <template v-if="idx < historySeries.length - 1">
                                            <span
                                                v-if="historySeries[idx + 1]?.values?.[field.slug] != null && rev.values[field.slug] !== historySeries[idx + 1].values[field.slug]"
                                                :class="[
                                                    'text-[10px] ml-1',
                                                    rev.values[field.slug] > historySeries[idx + 1].values[field.slug] ? 'text-red-500' : 'text-blue-500',
                                                ]"
                                            >
                                                {{ rev.values[field.slug] > historySeries[idx + 1].values[field.slug] ? '+' : '' }}{{ Math.round((rev.values[field.slug] - historySeries[idx + 1].values[field.slug]) * 10) / 10 }}
                                            </span>
                                        </template>
                                    </span>
                                </div>
                            </template>
                        </div>

                        <p v-if="rev.notes" class="mt-2 text-xs text-gray-500 border-t border-gray-100 pt-2">
                            {{ rev.notes }}
                        </p>
                    </div>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
