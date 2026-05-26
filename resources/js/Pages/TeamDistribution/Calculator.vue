<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CurrencyInput from '@/Components/CurrencyInput.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
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
const saveForm = useForm({});
const selectedProjectId = computed(() => String(props.selectedProjectId || ''));
const statusToneMap = {
  negosiasi: 'badge-ghost',
  berjalan: 'badge-warning',
  selesai: 'badge-success',
  dibatalkan: 'badge-error',
};

const defaultRole = computed(() => props.teamRoles?.[0]?.name ?? 'developer');
const distributionRate = ref(Number(props.selectedProject?.distribution_rate ?? 30));

const makeRow = (overrides = {}) => ({
  user_id: '',
  role_in_project: defaultRole.value,
  percentage: 0,
  bonus: 0,
  ...overrides,
});

const normalizeRow = (row = {}) => makeRow({
  user_id: row.user_id ? Number(row.user_id) : '',
  role_in_project: row.role_in_project || defaultRole.value,
  percentage: Number(row.percentage ?? 0),
  bonus: Number(row.bonus ?? 0),
});

const rows = ref(
  props.existingDistributions.length
    ? props.existingDistributions.map(normalizeRow)
    : [makeRow()],
);

watch(
  () => [props.selectedProject?.id, props.existingDistributions],
  () => {
    distributionRate.value = Number(props.selectedProject?.distribution_rate ?? 30);
    rows.value = props.existingDistributions.length
      ? props.existingDistributions.map(normalizeRow)
      : [makeRow()];
  },
  { deep: true },
);

const selectProject = (projectId) => {
  router.get(
    route('team-distribution.calculator'),
    { project_id: projectId },
    { preserveScroll: true, preserveState: false },
  );
};

const addRow = () => rows.value.push(makeRow());
const removeRow = (index) => {
  rows.value.splice(index, 1);
  if (!rows.value.length) {
    addRow();
  }
};

const applyPreset = () => {
  if (!props.selectedProject) return;

  const roleNames = props.teamRoles.map((role) => role.name);
  const leadRole = roleNames.includes('lead') ? 'lead' : defaultRole.value;
  const developerRole = roleNames.includes('developer') ? 'developer' : defaultRole.value;

  rows.value = [
    makeRow({ role_in_project: leadRole, percentage: 45 }),
    makeRow({ role_in_project: developerRole, percentage: 27.5 }),
    makeRow({ role_in_project: developerRole, percentage: 27.5 }),
  ];
};

const marginAmount = computed(() => Number(props.selectedProject?.margin_amount ?? 0));
const companyReserveAmount = computed(() => Math.max(marginAmount.value * (Number(distributionRate.value || 0) / 100), 0));
const distributableAmount = computed(() => Math.max(marginAmount.value - companyReserveAmount.value, 0));

const calculateBasePay = (row) => Math.round(distributableAmount.value * Number(row.percentage || 0) / 100);
const rowTotalPay = (row) => calculateBasePay(row) + Number(row.bonus || 0);

const totalPercentage = computed(() => rows.value.reduce((sum, row) => sum + Number(row.percentage || 0), 0));
const totalBasePay = computed(() => rows.value.reduce((sum, row) => sum + calculateBasePay(row), 0));
const totalBonus = computed(() => rows.value.reduce((sum, row) => sum + Number(row.bonus || 0), 0));
const totalPay = computed(() => totalBasePay.value + totalBonus.value);
const remainingPool = computed(() => distributableAmount.value - totalPay.value);

const percentageValid = computed(() => Math.abs(totalPercentage.value - 100) < 0.01);
const budgetValid = computed(() => !props.selectedProject || totalPay.value <= distributableAmount.value + 0.01);
const rowsValid = computed(() => rows.value.every((row) => row.user_id && row.role_in_project));
const rateValid = computed(() => Number(distributionRate.value) >= 0 && Number(distributionRate.value) <= 100);
const canSave = computed(() => (
  props.selectedProject
  && percentageValid.value
  && budgetValid.value
  && rowsValid.value
  && rateValid.value
  && !saveForm.processing
));

const marginSourceLabel = computed(() => {
  switch (props.selectedProject?.margin_source) {
    case 'paid_minus_material_service_operational':
      return 'Nilai terbayarkan dikurangi modal material, modal jasa, dan operational';
    default:
      return '-';
  }
});

const save = () => {
  saveForm
    .transform(() => ({
      project_id: props.selectedProject.id,
      distribution_rate: Number(distributionRate.value || 0),
      distributions: rows.value.map((row) => ({
        user_id: row.user_id,
        role_in_project: row.role_in_project,
        percentage: Number(row.percentage || 0),
        base_pay: calculateBasePay(row),
        bonus: Number(row.bonus || 0),
      })),
    }))
    .post(route('team-distribution.save'), {
      preserveScroll: true,
    });
};
</script>

<template>
  <Head title="Kalkulator Pembagian Tim" />
  <AppLayout>
    <div class="space-y-4">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Project Workspace</p>
          <h1 class="ocn-panel__title mt-1">Kalkulator Pembagian Tim</h1>
          <p class="ocn-panel__desc mt-1">Pilih project dari daftar, lalu atur pembagian dari sisa margin setelah potongan persentase cadangan.</p>
        </div>
        <Link class="btn btn-ghost btn-sm shrink-0 gap-1.5" :href="route('erp.projects')">
          <ArrowLeftIcon class="h-4 w-4" />
          Back
        </Link>
      </div>

      <div class="grid gap-5 xl:grid-cols-[360px_minmax(0,1fr)]">
        <section class="ocn-panel min-w-0">
          <div class="ocn-panel__head">
            <h2 class="ocn-panel__title">Daftar Project</h2>
            <p class="ocn-panel__desc">Semua project ditampilkan di sini. Klik salah satu project untuk membuka detail pembagiannya.</p>
          </div>
          <div class="card-body max-h-[calc(100vh-14rem)] space-y-3 overflow-y-auto">
            <button
              v-for="project in projects"
              :key="project.id"
              type="button"
              class="w-full rounded-2xl border p-4 text-left transition"
              :class="project.id === selectedProjectId ? 'border-primary bg-primary/5 shadow-sm' : 'border-base-200 bg-base-100 hover:border-primary/30'"
              @click="selectProject(project.id)"
            >
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <p class="truncate font-semibold text-base-content">{{ project.name }}</p>
                  <p class="mt-1 truncate text-xs text-base-content/60">{{ project.client_name || '-' }} - {{ project.status }}</p>
                </div>
                <span class="badge badge-sm shrink-0 capitalize" :class="statusToneMap[project.status_key] || 'badge-ghost'">
                  {{ project.status }}
                </span>
              </div>
              <div class="mt-3 grid gap-2 text-xs sm:grid-cols-2">
                <div>
                  <p class="text-base-content/50">Margin</p>
                  <p class="font-semibold">{{ format(project.margin_amount) }}</p>
                </div>
                <div>
                  <p class="text-base-content/50">Pool dibagi</p>
                  <p class="font-semibold text-primary">{{ format(project.distributable_amount) }}</p>
                </div>
                <div>
                  <p class="text-base-content/50">Sudah dibagi</p>
                  <p class="font-semibold">{{ format(project.distributed_total) }}</p>
                </div>
                <div>
                  <p class="text-base-content/50">Sisa pool</p>
                  <p class="font-semibold" :class="project.remaining_distributable >= 0 ? 'text-success' : 'text-error'">
                    {{ format(project.remaining_distributable) }}
                  </p>
                </div>
              </div>
            </button>
          </div>
        </section>

        <section v-if="selectedProject" class="min-w-0 space-y-4">
          <div class="grid gap-3 sm:grid-cols-2 2xl:grid-cols-4">
            <div class="ocn-panel">
              <div class="card-body py-4">
                <p class="text-xs font-semibold uppercase text-base-content/50">Nilai Terbayarkan</p>
                <p class="mt-1 text-xl font-bold">{{ format(selectedProject.total_value) }}</p>
              </div>
            </div>
            <div class="ocn-panel">
              <div class="card-body py-4">
                <p class="text-xs font-semibold uppercase text-base-content/50">Margin Project</p>
                <p class="mt-1 text-xl font-bold">{{ format(selectedProject.margin_amount) }}</p>
              </div>
            </div>
            <div class="ocn-panel">
              <div class="card-body py-4">
                <p class="text-xs font-semibold uppercase text-base-content/50">Cadangan Margin</p>
                <p class="mt-1 text-xl font-bold text-warning">{{ format(companyReserveAmount) }}</p>
              </div>
            </div>
            <div class="ocn-panel border-primary/30 bg-primary/5">
              <div class="card-body py-4">
                <p class="text-xs font-semibold uppercase text-primary/70">Pool Dibagi</p>
                <p class="mt-1 text-xl font-bold text-primary">{{ format(distributableAmount) }}</p>
              </div>
            </div>
          </div>

          <div class="ocn-panel">
            <div class="ocn-panel__head flex flex-col gap-2 lg:flex-row lg:items-start lg:justify-between">
              <div class="min-w-0">
                <h2 class="ocn-panel__title">{{ selectedProject.name }}</h2>
                <p class="ocn-panel__desc">Sumber margin: {{ marginSourceLabel }}. Persentase cadangan diambil dari margin, lalu sisanya menjadi pool pembagian tim.</p>
              </div>
              <span class="badge badge-sm shrink-0 capitalize" :class="statusToneMap[selectedProject.status_key] || 'badge-ghost'">
                {{ selectedProject.status }}
              </span>
            </div>
            <div class="card-body grid gap-4 xl:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
              <div class="space-y-3">
                <div class="rounded-2xl border border-base-200 bg-base-100 p-4">
                  <label class="label px-0 pt-0">
                    <span class="label-text font-semibold">Persentase cadangan margin</span>
                  </label>
                  <input v-model.number="distributionRate" type="number" min="0" max="100" step="0.5" class="input input-bordered w-full max-w-xs">
                  <p class="mt-2 text-xs text-base-content/60">Default 30%. Nilai ini disimpan per project dan mengurangi margin sebelum pembagian tim dihitung.</p>
                </div>
                <div class="rounded-2xl border border-base-200 bg-base-100 p-4 text-sm">
                  <dl class="space-y-2">
                    <div class="flex items-center justify-between gap-3">
                      <dt class="text-base-content/60">Client</dt>
                      <dd class="text-right font-medium">{{ selectedProject.client_name || '-' }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                      <dt class="text-base-content/60">Mulai</dt>
                      <dd class="text-right font-medium">{{ selectedProject.started_at || '-' }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                      <dt class="text-base-content/60">Team saat ini</dt>
                      <dd class="text-right font-medium">{{ selectedProject.team_member_count }} anggota</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                      <dt class="text-base-content/60">Sudah dibagi</dt>
                      <dd class="text-right font-medium">{{ format(selectedProject.distributed_total) }}</dd>
                    </div>
                  </dl>
                </div>
              </div>

              <div class="grid gap-3">
                <div class="rounded-2xl border border-base-200 bg-base-100 p-4 text-sm">
                  <p class="font-semibold">Formula pembagian</p>
                  <div class="mt-3 space-y-2 text-base-content/75">
                    <p>Nilai terbayarkan: <strong>{{ format(selectedProject.paid_amount) }}</strong></p>
                    <p>Modal material + jasa: <strong>{{ format(selectedProject.direct_cost_total) }}</strong></p>
                    <p>Operational: <strong>{{ format(selectedProject.operational_total) }}</strong></p>
                    <p>Margin project: <strong>{{ format(selectedProject.margin_amount) }}</strong></p>
                    <p>Cadangan {{ Number(distributionRate || 0).toFixed(2) }}%: <strong>{{ format(companyReserveAmount) }}</strong></p>
                    <p>Sisa margin yang dibagi: <strong class="text-primary">{{ format(distributableAmount) }}</strong></p>
                  </div>
                </div>
                <div class="rounded-2xl border border-base-200 bg-base-100 p-4 text-sm">
                  <p class="font-semibold">Sumber angka margin</p>
                  <dl class="mt-3 space-y-2">
                    <div class="flex items-center justify-between gap-3">
                      <dt class="text-base-content/60">Nilai terbayarkan</dt>
                      <dd class="text-right font-medium">{{ format(selectedProject.paid_amount) }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                      <dt class="text-base-content/60">Modal material</dt>
                      <dd class="text-right font-medium">{{ format(selectedProject.material_cost_total) }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                      <dt class="text-base-content/60">Modal jasa</dt>
                      <dd class="text-right font-medium">{{ format(selectedProject.service_cost_total) }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                      <dt class="text-base-content/60">Operational</dt>
                      <dd class="text-right font-medium">{{ format(selectedProject.operational_total) }}</dd>
                    </div>
                  </dl>
                </div>
              </div>
            </div>
          </div>

          <div v-if="!rateValid || !percentageValid || !budgetValid || !rowsValid || saveForm.errors.distributions" class="space-y-2">
            <div v-if="!rateValid" class="alert alert-warning">
              <ExclamationTriangleIcon class="h-5 w-5" />
              <span>Persentase cadangan margin harus antara 0% sampai 100%.</span>
            </div>
            <div v-if="!percentageValid" class="alert alert-warning">
              <ExclamationTriangleIcon class="h-5 w-5" />
              <span>Total persentase pembagian saat ini {{ totalPercentage.toFixed(1) }}%. Total harus tepat 100%.</span>
            </div>
            <div v-if="!budgetValid" class="alert alert-error">
              <ExclamationTriangleIcon class="h-5 w-5" />
              <span>Total bayar {{ format(totalPay) }} melebihi pool pembagian {{ format(distributableAmount) }}.</span>
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

          <div class="ocn-panel min-w-0 overflow-hidden">
            <div class="ocn-panel__head flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
              <div>
                <h2 class="ocn-panel__title">Detail Pembagian</h2>
                <p class="ocn-panel__desc">Base pay dihitung otomatis dari pool pembagian. Bonus tetap bisa ditambah manual per anggota.</p>
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
                        <input v-model.number="row.percentage" type="number" min="0" max="100" step="0.5" class="input input-bordered input-sm w-full text-right">
                      </td>
                      <td class="text-right font-semibold text-primary">
                        {{ format(calculateBasePay(row)) }}
                      </td>
                      <td>
                        <CurrencyInput v-model="row.bonus" />
                      </td>
                      <td class="text-right font-semibold">
                        {{ format(rowTotalPay(row)) }}
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
                  <p class="text-xs uppercase text-base-content/50">Sisa Pool</p>
                  <p :class="['mt-1 text-lg font-bold', remainingPool >= 0 ? 'text-primary' : 'text-error']">
                    {{ format(remainingPool) }}
                  </p>
                </div>
              </div>

              <div class="flex flex-col gap-3 border-t border-base-300 pt-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                  <p class="text-sm font-semibold">Total bayar: {{ format(totalPay) }}</p>
                  <p class="text-xs text-base-content/60">Saat disimpan, pembagian lama untuk project ini akan diganti dengan baris di tabel dan rate cadangan margin terbaru.</p>
                </div>
                <button type="button" class="btn btn-primary" :disabled="!canSave" @click="save">
                  <span v-if="saveForm.processing" class="loading loading-spinner loading-sm" />
                  Simpan Pembagian
                </button>
              </div>
            </div>
          </div>
        </section>

        <section v-else class="ocn-panel min-w-0">
          <div class="card-body py-10 text-center text-base-content/60">
            Klik salah satu project pada daftar untuk membuka detail pembagian timnya.
          </div>
        </section>
      </div>
    </div>
  </AppLayout>
</template>
