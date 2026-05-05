<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CurrencyInput from '@/Components/CurrencyInput.vue';
import { useForm, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import { useCurrency } from '@/composables/useCurrency';
import { PlusIcon, TrashIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    projects: Array,
    members: Array,
    selectedProject: Object,
    existingDistributions: Array,
    selectedProjectId: { type: String, default: null },
});

const { format } = useCurrency();

const selectedProjectId = ref(
    props.selectedProjectId != null && props.selectedProjectId !== '' ? String(props.selectedProjectId) : '',
);

watch(selectedProjectId, (val) => {
    if (val) router.get(route('team-distribution.calculator'), { project_id: val }, { preserveState: false });
});

// Form state
const rows = ref(
    props.existingDistributions.length > 0
        ? props.existingDistributions.map(d => ({ ...d }))
        : [{ user_id: '', role_in_project: 'developer', percentage: 0, base_pay: 0, bonus: 0 }]
);

const addRow = () => rows.value.push({ user_id: '', role_in_project: 'developer', percentage: 0, base_pay: 0, bonus: 0 });
const removeRow = (i) => rows.value.splice(i, 1);

// Presets
const applyPreset = () => {
    if (!props.selectedProject) return;
    const net = props.selectedProject.net_value;
    rows.value = [
        { user_id: '', role_in_project: 'lead',      percentage: 45,   base_pay: Math.round(net * 0.45), bonus: 0 },
        { user_id: '', role_in_project: 'developer', percentage: 27.5, base_pay: Math.round(net * 0.275), bonus: 0 },
        { user_id: '', role_in_project: 'developer', percentage: 27.5, base_pay: Math.round(net * 0.275), bonus: 0 },
    ];
};

// Computed totals
const totalPercentage = computed(() => rows.value.reduce((s, r) => s + Number(r.percentage), 0));
const totalPay = computed(() => rows.value.reduce((s, r) => s + Number(r.base_pay) + Number(r.bonus), 0));

const percentageValid = computed(() => Math.abs(totalPercentage.value - 100) < 0.01);
const budgetValid = computed(() => !props.selectedProject || totalPay.value <= props.selectedProject.net_value);

// Auto-calculate base_pay from percentage
const autoCalc = (row) => {
    if (props.selectedProject && row.percentage > 0) {
        row.base_pay = Math.round(props.selectedProject.net_value * row.percentage / 100);
    }
};

const saveForm = useForm({});
const save = () => {
    saveForm.transform(() => ({
        project_id: selectedProjectId.value,
        distributions: rows.value,
    })).post(route('team-distribution.save'));
};
</script>

<template>
    <AppLayout>
        <div class="space-y-5">
            <h1 class="text-2xl font-bold">Kalkulator Pembagian Tim</h1>

            <!-- Project Selector -->
            <div class="card bg-base-100 shadow">
                <div class="card-body">
                    <label class="label"><span class="label-text font-medium">Pilih Project</span></label>
                    <select v-model="selectedProjectId" class="select select-bordered max-w-md">
                        <option value="">-- Pilih Project --</option>
                        <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }} ({{ p.status }})</option>
                    </select>
                </div>
            </div>

            <template v-if="selectedProject">
                <!-- Summary -->
                <div class="stats stats-vertical sm:stats-horizontal shadow w-full">
                    <div class="stat">
                        <div class="stat-title">Nilai Project</div>
                        <div class="stat-value text-xl">{{ format(selectedProject.total_value) }}</div>
                    </div>
                    <div class="stat">
                        <div class="stat-title">Komisi Referral</div>
                        <div class="stat-value text-xl text-warning">{{ format(selectedProject.referral_total) }}</div>
                    </div>
                    <div class="stat">
                        <div class="stat-title">Operasional</div>
                        <div class="stat-value text-xl text-error">{{ format(selectedProject.operational_total) }}</div>
                    </div>
                    <div class="stat">
                        <div class="stat-title">Nilai Bersih Tim</div>
                        <div class="stat-value text-xl text-primary">{{ format(selectedProject.net_value) }}</div>
                    </div>
                </div>

                <!-- Warnings -->
                <div v-if="!percentageValid" class="alert alert-warning">
                    <ExclamationTriangleIcon class="w-5 h-5" />
                    Total persentase = {{ totalPercentage.toFixed(1) }}% — harus tepat 100%
                </div>
                <div v-if="!budgetValid" class="alert alert-error">
                    <ExclamationTriangleIcon class="w-5 h-5" />
                    Total bayar {{ format(totalPay) }} melebihi nilai bersih {{ format(selectedProject.net_value) }}
                </div>

                <!-- Distribution Table -->
                <div class="card bg-base-100 shadow">
                    <div class="card-body">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="card-title text-base">Anggota Tim</h2>
                            <div class="flex gap-2">
                                <button class="btn btn-outline btn-sm" @click="applyPreset">Preset 1 Lead + 2 Dev</button>
                                <button class="btn btn-primary btn-sm gap-1" @click="addRow">
                                    <PlusIcon class="w-4 h-4" /> Tambah Anggota
                                </button>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div v-for="(row, i) in rows" :key="i"
                                class="grid grid-cols-1 sm:grid-cols-6 gap-3 p-4 border border-base-300 rounded-xl items-end"
                            >
                                <!-- Anggota -->
                                <div class="sm:col-span-2">
                                    <label class="label py-0"><span class="label-text text-xs">Anggota</span></label>
                                    <select v-model="row.user_id" class="select select-bordered select-sm w-full">
                                        <option value="">-- Pilih --</option>
                                        <option v-for="m in members" :key="m.id" :value="m.id">{{ m.name }}</option>
                                    </select>
                                </div>
                                <!-- Peran -->
                                <div>
                                    <label class="label py-0"><span class="label-text text-xs">Peran</span></label>
                                    <select v-model="row.role_in_project" class="select select-bordered select-sm w-full">
                                        <option value="lead">Lead</option>
                                        <option value="developer">Developer</option>
                                        <option value="designer">Designer</option>
                                        <option value="qa">QA</option>
                                    </select>
                                </div>
                                <!-- Persentase -->
                                <div>
                                    <label class="label py-0"><span class="label-text text-xs">% Bagi</span></label>
                                    <input v-model.number="row.percentage" type="number" min="0" max="100" step="0.5"
                                        class="input input-bordered input-sm w-full"
                                        @change="autoCalc(row)"
                                    />
                                </div>
                                <!-- Base Pay -->
                                <div>
                                    <label class="label py-0"><span class="label-text text-xs">Base Pay</span></label>
                                    <input v-model.number="row.base_pay" type="number" min="0"
                                        class="input input-bordered input-sm w-full" />
                                </div>
                                <!-- Bonus -->
                                <div class="flex gap-2 items-end">
                                    <div class="flex-1">
                                        <label class="label py-0"><span class="label-text text-xs">Bonus</span></label>
                                        <input v-model.number="row.bonus" type="number" min="0"
                                            class="input input-bordered input-sm w-full" />
                                    </div>
                                    <button class="btn btn-ghost btn-sm text-error" @click="removeRow(i)">
                                        <TrashIcon class="w-4 h-4" />
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Total Row -->
                        <div class="border-t border-base-300 pt-4 mt-2 flex flex-wrap justify-between items-center gap-4">
                            <div class="flex gap-6">
                                <div>
                                    <span class="text-sm text-base-content/60">Total %: </span>
                                    <span :class="['font-bold', percentageValid ? 'text-success' : 'text-error']">
                                        {{ totalPercentage.toFixed(1) }}%
                                    </span>
                                </div>
                                <div>
                                    <span class="text-sm text-base-content/60">Total Bayar: </span>
                                    <span :class="['font-bold', budgetValid ? 'text-primary' : 'text-error']">
                                        {{ format(totalPay) }}
                                    </span>
                                </div>
                            </div>
                            <button
                                class="btn btn-primary"
                                :disabled="!percentageValid || !budgetValid || saveForm.processing || rows.some(r => !r.user_id)"
                                @click="save"
                            >
                                <span v-if="saveForm.processing" class="loading loading-spinner loading-sm" />
                                Simpan Pembagian
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
