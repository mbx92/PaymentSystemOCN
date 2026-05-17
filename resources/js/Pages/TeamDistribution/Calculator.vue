<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CurrencyInput from '@/Components/CurrencyInput.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useCurrency } from '@/composables/useCurrency';
import {
    ArrowLeftIcon,
    ExclamationTriangleIcon,
    PlusIcon,
    TrashIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    projects: { type: Array, default: () => [] },
    members: { type: Array, default: () => [] },
    teamRoles: { type: Array, default: () => [] },
    selectedProject: { type: Object, default: null },
    existingDistributions: { type: Array, default: () => [] },
    selectedProjectId: { type: String, default: null },
});

const { format } = useCurrency();

const selectedProjectId = ref(props.selectedProjectId ? String(props.selectedProjectId) : '');
const saveForm = useForm({});

const defaultRole = computed(() => props.teamRoles?.[0]?.name ?? 'developer');
const netValue = computed(() => Number(props.selectedProject?.net_value ?? 0));

const makeRow = (overrides = {}) => ({
    user_id: '',
    role_in_project: defaultRole.value,
    percentage: 0,
    base_pay: 0,
    bonus: 0,
    ...overrides,
});

const normalizeRow = (row = {}) => makeRow({
    user_id: row.user_id ? Number(row.user_id) : '',
    role_in_project: row.role_in_project || defaultRole.value,
    percentage: Number(row.percentage ?? 0),
    base_pay: Number(row.base_pay ?? 0),
    bonus: Number(row.bonus ?? 0),
});

const rows = ref(
    props.existingDistributions.length
        ? props.existingDistributions.map(normalizeRow)
        : [makeRow()],
);

watch(selectedProjectId, (value) => {
    router.get(
        route('team-distribution.calculator'),
        value ? { project_id: value } : {},
        { preserveState: false },
    );
});

const addRow = () => rows.value.push(makeRow());
const removeRow = (index) => {
    rows.value.splice(index, 1);
    if (! rows.value.length) {
        addRow();
    }
};

const calculateBasePay = (row) => {
    row.base_pay = Math.round(netValue.value * Number(row.percentage || 0) / 100);
};

const applyPreset = () => {
    if (! props.selectedProject) return;

    const roleNames = props.teamRoles.map((role) => role.name);
    const leadRole = roleNames.includes('lead') ? 'lead' : defaultRole.value;
    const developerRole = roleNames.includes('developer') ? 'developer' : defaultRole.value;

    rows.value = [
        makeRow({ role_in_project: leadRole, percentage: 45 }),
        makeRow({ role_in_project: developerRole, percentage: 27.5 }),
        makeRow({ role_in_project: developerRole, percentage: 27.5 }),
    ];
    rows.value.forEach(calculateBasePay);
};

const totalPercentage = computed(() => rows.value.reduce((sum, row) => sum + Number(row.percentage || 0), 0));
const totalBasePay = computed(() => rows.value.reduce((sum, row) => sum + Number(row.base_pay || 0), 0));
const totalBonus = computed(() => rows.value.reduce((sum, row) => sum + Number(row.bonus || 0), 0));
const totalPay = computed(() => totalBasePay.value + totalBonus.value);
const remainingBudget = computed(() => netValue.value - totalPay.value);

const percentageValid = computed(() => Math.abs(totalPercentage.value - 100) < 0.01);
const budgetValid = computed(() => ! props.selectedProject || totalPay.value <= netValue.value);
const rowsValid = computed(() => rows.value.every((row) => row.user_id && row.role_in_project));
const canSave = computed(() => (
    props.selectedProject
    && percentageValid.value
    && budgetValid.value
    && rowsValid.value
    && ! saveForm.processing
));

const save = () => {
    saveForm
        .transform(() => ({
            project_id: selectedProjectId.value,
            distributions: rows.value.map((row) => ({
                user_id: row.user_id,
                role_in_project: row.role_in_project,
                percentage: Number(row.percentage || 0),
                base_pay: Number(row.base_pay || 0),
                bonus: Number(row.bonus || 0),
            })),
        }))
        .post(route('team-distribution.save'), {
            preserveScroll: true,
        });
};
</script>

<template>
    <AppLayout>
        <div class="space-y-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Project Workspace</p>
                    <h1 class="ocn-panel__title mt-1">Kalkulator Pembagian Tim</h1>
                    <p class="ocn-panel__desc mt-1">Hitung dan simpan pembagian payout anggota berdasarkan nilai bersih project.</p>
                </div>
                <Link class="btn btn-ghost btn-sm shrink-0 gap-1.5" :href="route('erp.projects')">
                    <ArrowLeftIcon class="h-4 w-4" />
                    Back
                </Link>
            </div>

            <div class="ocn-panel">
                <div class="ocn-panel__head">
                    <h2 class="ocn-panel__title">Project</h2>
                    <p class="ocn-panel__desc">Pilih project berjalan atau selesai untuk memuat nilai bersih dan pembagian yang sudah tersimpan.</p>
                </div>
                <div class="card-body">
                    <select v-model="selectedProjectId" class="select select-bordered w-full max-w-xl">
                        <option value="">Pilih project</option>
                        <option v-for="project in projects" :key="project.id" :value="project.id">
                            {{ project.name }} ({{ project.status }})
                        </option>
                    </select>
                </div>
            </div>

            <template v-if="selectedProject">
                <div class="grid gap-3 md:grid-cols-4">
                    <div class="ocn-panel">
                        <div class="card-body py-4">
                            <p class="text-xs font-semibold uppercase text-base-content/50">Nilai Project</p>
                            <p class="mt-1 text-xl font-bold">{{ format(selectedProject.total_value) }}</p>
                        </div>
                    </div>
                    <div class="ocn-panel">
                        <div class="card-body py-4">
                            <p class="text-xs font-semibold uppercase text-base-content/50">Komisi Referral</p>
                            <p class="mt-1 text-xl font-bold text-warning">{{ format(selectedProject.referral_total) }}</p>
                        </div>
                    </div>
                    <div class="ocn-panel">
                        <div class="card-body py-4">
                            <p class="text-xs font-semibold uppercase text-base-content/50">Operasional</p>
                            <p class="mt-1 text-xl font-bold text-error">{{ format(selectedProject.operational_total) }}</p>
                        </div>
                    </div>
                    <div class="ocn-panel border-primary/30 bg-primary/5">
                        <div class="card-body py-4">
                            <p class="text-xs font-semibold uppercase text-primary/70">Nilai Bersih Tim</p>
                            <p class="mt-1 text-xl font-bold text-primary">{{ format(selectedProject.net_value) }}</p>
                        </div>
                    </div>
                </div>

                <div v-if="!percentageValid || !budgetValid || !rowsValid || saveForm.errors.distributions" class="space-y-2">
                    <div v-if="!percentageValid" class="alert alert-warning">
                        <ExclamationTriangleIcon class="h-5 w-5" />
                        <span>Total persentase saat ini {{ totalPercentage.toFixed(1) }}%. Total harus tepat 100%.</span>
                    </div>
                    <div v-if="!budgetValid" class="alert alert-error">
                        <ExclamationTriangleIcon class="h-5 w-5" />
                        <span>Total bayar {{ format(totalPay) }} melebihi nilai bersih {{ format(selectedProject.net_value) }}.</span>
                    </div>
                    <div v-if="!rowsValid" class="alert alert-info">
                        <ExclamationTriangleIcon class="h-5 w-5" />
                        <span>Setiap baris perlu anggota dan peran sebelum disimpan.</span>
                    </div>
                    <div v-if="saveForm.errors.distributions" class="alert alert-error">
                        <ExclamationTriangleIcon class="h-5 w-5" />
                        <span>{{ saveForm.errors.distributions }}</span>
                    </div>
                </div>

                <div class="ocn-panel">
                    <div class="ocn-panel__head flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h2 class="ocn-panel__title">Pembagian Anggota</h2>
                            <p class="ocn-panel__desc">Nominal base pay bisa otomatis mengikuti persentase, lalu bonus ditambahkan terpisah.</p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" class="btn btn-outline btn-sm" @click="applyPreset">Preset 1 Lead + 2 Dev</button>
                            <button type="button" class="btn btn-primary btn-sm gap-1" @click="addRow">
                                <PlusIcon class="h-4 w-4" />
                                Tambah
                            </button>
                        </div>
                    </div>
                    <div class="card-body space-y-4">
                        <div class="overflow-x-auto">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th class="min-w-52">Anggota</th>
                                        <th class="min-w-40">Peran</th>
                                        <th class="w-28 text-right">%</th>
                                        <th class="min-w-44 text-right">Base Pay</th>
                                        <th class="min-w-44 text-right">Bonus</th>
                                        <th class="min-w-36 text-right">Total</th>
                                        <th class="w-12"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(row, index) in rows" :key="index">
                                        <td>
                                            <select v-model="row.user_id" class="select select-bordered select-sm w-full">
                                                <option value="">Pilih anggota</option>
                                                <option v-for="member in members" :key="member.id" :value="member.id">
                                                    {{ member.name }}
                                                </option>
                                            </select>
                                        </td>
                                        <td>
                                            <select v-model="row.role_in_project" class="select select-bordered select-sm w-full">
                                                <option v-for="role in teamRoles" :key="role.id" :value="role.name">
                                                    {{ role.name }}
                                                </option>
                                            </select>
                                        </td>
                                        <td>
                                            <input
                                                v-model.number="row.percentage"
                                                type="number"
                                                min="0"
                                                max="100"
                                                step="0.5"
                                                class="input input-bordered input-sm w-full text-right"
                                                @change="calculateBasePay(row)"
                                            />
                                        </td>
                                        <td>
                                            <CurrencyInput v-model="row.base_pay" />
                                        </td>
                                        <td>
                                            <CurrencyInput v-model="row.bonus" />
                                        </td>
                                        <td class="text-right font-semibold text-primary">
                                            {{ format(Number(row.base_pay || 0) + Number(row.bonus || 0)) }}
                                        </td>
                                        <td class="text-right">
                                            <button type="button" class="btn btn-ghost btn-xs text-error" @click="removeRow(index)">
                                                <TrashIcon class="h-4 w-4" />
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="grid gap-3 border-t border-base-300 pt-4 md:grid-cols-4">
                            <div>
                                <p class="text-xs uppercase text-base-content/50">Total Persentase</p>
                                <p :class="['mt-1 text-lg font-bold', percentageValid ? 'text-success' : 'text-error']">
                                    {{ totalPercentage.toFixed(1) }}%
                                </p>
                            </div>
                            <div>
                                <p class="text-xs uppercase text-base-content/50">Base Pay</p>
                                <p class="mt-1 text-lg font-bold">{{ format(totalBasePay) }}</p>
                            </div>
                            <div>
                                <p class="text-xs uppercase text-base-content/50">Bonus</p>
                                <p class="mt-1 text-lg font-bold">{{ format(totalBonus) }}</p>
                            </div>
                            <div>
                                <p class="text-xs uppercase text-base-content/50">Sisa Nilai Bersih</p>
                                <p :class="['mt-1 text-lg font-bold', remainingBudget >= 0 ? 'text-primary' : 'text-error']">
                                    {{ format(remainingBudget) }}
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-col gap-3 border-t border-base-300 pt-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm font-semibold">Total bayar: {{ format(totalPay) }}</p>
                                <p class="text-xs text-base-content/60">Saat disimpan, pembagian lama untuk project ini akan diganti dengan baris di tabel.</p>
                            </div>
                            <button type="button" class="btn btn-primary" :disabled="!canSave" @click="save">
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
