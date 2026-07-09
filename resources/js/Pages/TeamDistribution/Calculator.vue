<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CurrencyInput from '@/Components/CurrencyInput.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useCurrency } from '@/composables/useCurrency';
import {
  ArrowLeftIcon,
  ExclamationTriangleIcon,
  PlusIcon,
  TrashIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
  projects: { type: Object, default: () => ({ data: [] }) },
  members: { type: Array, default: () => [] },
  teamRoles: { type: Array, default: () => [] },
  selectedProject: { type: Object, default: null },
  existingDistributions: { type: Array, default: () => [] },
  selectedProjectId: { type: String, default: null },
  filters: { type: Object, default: () => ({}) },
});

const { format } = useCurrency();
const saveForm = useForm({});
const selectedProjectId = computed(() => String(props.selectedProjectId || ''));
const filters = reactive({
  q: props.filters?.q ?? '',
  status: props.filters?.status ?? '',
  per_page: Number(props.filters?.per_page ?? props.projects?.per_page ?? 25),
});
const statusToneMap = {
  negosiasi: 'badge-ghost',
  berjalan: 'badge-warning',
  selesai: 'badge-success',
  dibatalkan: 'badge-error',
};

const searchInput = ref(null);

onMounted(() => {
  nextTick(() => searchInput.value?.focus());
});

const sentinel = ref(null);
const scrollContainer = ref(null);
const allProjects = ref([]);
const loadingMore = ref(false);
const loadedPage = ref(0);

const loadNextPage = () => {
  if (loadingMore.value) return;
  const nextPage = loadedPage.value + 1;
  if (!props.projects || nextPage > props.projects.last_page) return;
  loadingMore.value = true;
  router.get(route('team-distribution.calculator'), {
    project_id: selectedProjectId.value || undefined,
    q: filters.q || undefined,
    status: filters.status || undefined,
    per_page: filters.per_page,
    page: nextPage,
  }, { preserveState: true, preserveScroll: true, replace: true });
};

const onScroll = () => {
  if (loadingMore.value) return;
  const el = scrollContainer.value;
  if (!el) return;
  if (el.scrollTop + el.clientHeight >= el.scrollHeight - 300) {
    loadNextPage();
  }
};

onMounted(() => {
  scrollContainer.value?.addEventListener('scroll', onScroll, { passive: true });
});

onBeforeUnmount(() => {
  scrollContainer.value?.removeEventListener('scroll', onScroll);
});

watch(() => props.projects, (paginator) => {
  if (!paginator?.data || !paginator.current_page) return;
  loadedPage.value = Math.max(loadedPage.value, paginator.current_page);
  const existingIds = new Set(allProjects.value.map((p) => p.id));
  const newItems = paginator.data.filter((p) => !existingIds.has(p.id));
  if (newItems.length) allProjects.value.push(...newItems);
  loadingMore.value = false;
  nextTick(() => {
    if (loadedPage.value < props.projects.last_page
      && scrollContainer.value
      && scrollContainer.value.scrollHeight <= scrollContainer.value.clientHeight + 1
    ) {
      loadNextPage();
    }
  });
}, { deep: true, immediate: true });

const defaultRole = computed(() => props.teamRoles?.[0]?.name ?? 'developer');
const distributionRate = ref(Number(props.selectedProject?.distribution_rate ?? 30));

const makeRow = (overrides = {}) => ({
  user_id: '',
  user_name: '',
  role_in_project: defaultRole.value,
  percentage: 0,
  bonus: 0,
  ...overrides,
});

const normalizeRow = (row = {}) => makeRow({
  user_id: row.user_id ? Number(row.user_id) : '',
  user_name: row.user_name ?? '',
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
    { project_id: projectId, q: filters.q || undefined, status: filters.status || undefined, per_page: filters.per_page },
    { preserveScroll: true, preserveState: true },
  );
};

let filterTimer;
watch(filters, (val) => {
  clearTimeout(filterTimer);
  filterTimer = setTimeout(() => {
    router.get(route('team-distribution.calculator'), {
      project_id: selectedProjectId.value || undefined,
      q: val.q || undefined,
      status: val.status || undefined,
      per_page: val.per_page,
    }, { preserveState: true, preserveScroll: true, replace: true });
  }, 250);
}, { deep: true });

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

const duplicateMemberIds = computed(() => {
  const ids = rows.value.map((r) => r.user_id).filter((id) => id !== '' && id !== undefined);
  return ids.filter((id, i) => ids.indexOf(id) !== i);
});

const hasDuplicates = computed(() => duplicateMemberIds.value.length > 0);

const percentageValid = computed(() => totalPercentage.value <= 100.01);
const budgetValid = computed(() => !props.selectedProject || totalPay.value <= distributableAmount.value + 0.01);
const rowsValid = computed(() => rows.value.every((row) => row.user_id && row.role_in_project));
const rateValid = computed(() => Number(distributionRate.value) >= 0 && Number(distributionRate.value) <= 100);
const canSave = computed(() => (
  props.selectedProject
  && percentageValid.value
  && budgetValid.value
  && rowsValid.value
  && !hasDuplicates.value
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
    <div class="space-y-5">
      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Project Workspace</p>
              <h1 class="ocn-panel__title mt-1">Kalkulator Pembagian Tim</h1>
              <p class="ocn-panel__desc mt-1">Pilih project dari daftar, lalu atur pembagian dari sisa margin setelah potongan persentase cadangan.</p>
            </div>
            <div class="flex shrink-0 flex-wrap items-center gap-2">
              <Link class="btn btn-ghost btn-sm shrink-0 gap-1.5" :href="route('erp.projects')">
                <ArrowLeftIcon class="h-4 w-4" />
                Back
              </Link>
            </div>
          </div>
        </div>
      </div>

      <div class="grid gap-5 lg:grid-cols-[360px_minmax(0,1fr)]">
        <section class="ocn-panel min-w-0 flex flex-col sticky top-24 self-start max-h-[calc(100vh-8rem)] overflow-hidden">
          <div class="ocn-panel__head shrink-0">
            <h2 class="ocn-panel__title">Daftar Project</h2>
            <p class="ocn-panel__desc">Pilih project untuk mengatur pembagian tim.</p>
          </div>
          <div class="card-body flex min-h-0 flex-1 flex-col gap-3 overflow-hidden p-4">
            <div class="grid shrink-0 gap-3">
              <input ref="searchInput" v-model="filters.q" type="search" class="input input-bordered input-sm w-full" placeholder="Cari project / klien..." />
              <select v-model="filters.status" class="select select-bordered select-sm w-full">
                <option value="">Semua status</option>
                <option value="negosiasi">Negosiasi</option>
                <option value="berjalan">Berjalan</option>
                <option value="selesai">Selesai</option>
                <option value="dibatalkan">Dibatalkan</option>
              </select>
            </div>
            <div ref="scrollContainer" class="-mx-4 flex-1 space-y-3 overflow-y-scroll px-4">
            <button
              v-for="project in allProjects"
              :key="project.id"
              type="button"
              class="w-full rounded-xl border px-4 py-3 text-left transition flex items-center gap-3"
              :class="project.id === selectedProjectId ? 'border-primary bg-primary/5 shadow-sm' : 'border-base-200 bg-base-100 hover:border-primary/30'"
              @click="selectProject(project.id)"
            >
              <div class="flex-1 min-w-0">
                <p class="truncate text-sm font-semibold text-base-content">{{ project.name }}</p>
                <p class="truncate text-xs text-base-content/60">{{ project.client_name || '-' }}</p>
              </div>
              <div class="flex shrink-0 items-center gap-2">
                <span v-if="project.distributed_total > 0" class="badge badge-soft badge-success badge-xs">dibagi</span>
                <span class="badge badge-sm capitalize" :class="statusToneMap[project.status_key] || 'badge-ghost'">
                  {{ project.status }}
                </span>
              </div>
            </button>
            <div v-if="loadingMore" class="flex justify-center py-2">
              <span class="loading loading-spinner loading-sm text-primary" />
            </div>
            <div ref="sentinel" class="h-1" />
            <div v-if="!(allProjects?.length) && !loadingMore" class="rounded-2xl border border-dashed border-base-300 p-4 text-sm text-base-content/60">
              Tidak ada project yang cocok dengan filter.
            </div>
            </div>
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

          <div v-if="!rateValid || !percentageValid || !budgetValid || !rowsValid || hasDuplicates || saveForm.errors.distributions" class="space-y-2">
            <div v-if="!rateValid" class="alert alert-warning">
              <ExclamationTriangleIcon class="h-5 w-5" />
              <span>Persentase cadangan margin harus antara 0% sampai 100%.</span>
            </div>
            <div v-if="!percentageValid" class="alert alert-warning">
              <ExclamationTriangleIcon class="h-5 w-5" />
              <span>Total persentase pembagian saat ini {{ totalPercentage.toFixed(1) }}%. Total tidak boleh melebihi 100%.</span>
            </div>
            <div v-if="!budgetValid" class="alert alert-error">
              <ExclamationTriangleIcon class="h-5 w-5" />
              <span>Total bayar {{ format(totalPay) }} melebihi pool pembagian {{ format(distributableAmount) }}.</span>
            </div>
            <div v-if="!rowsValid" class="alert alert-info">
              <ExclamationTriangleIcon class="h-5 w-5" />
              <span>Setiap baris perlu anggota dan peran sebelum disimpan.</span>
            </div>
            <div v-if="hasDuplicates" class="alert alert-warning">
              <ExclamationTriangleIcon class="h-5 w-5" />
              <span>Anggota terpilih lebih dari satu baris: {{ duplicateMemberIds.map(id => members.find(m => m.id == id)?.name).filter(Boolean).join(', ') }}.</span>
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
                        <div class="flex items-center gap-2">
                          <select v-model="row.user_id" class="select select-bordered select-sm w-full">
                            <option value="">Pilih anggota</option>
                            <option v-for="member in members" :key="member.id" :value="member.id">
                              {{ member.name }}
                            </option>
                          </select>
                        </div>
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
                  <p :class="['mt-1 text-lg font-bold', percentageValid ? 'text-primary' : 'text-error']">
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
          <div class="card-body flex flex-col items-center justify-center gap-4 py-16 text-center">
            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-primary/10">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-primary/60" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
              </svg>
            </div>
            <div>
              <p class="font-semibold text-base-content/80">Belum ada project dipilih</p>
              <p class="mt-1 text-sm text-base-content/50">Klik salah satu project pada daftar di samping untuk mengatur pembagian tim.</p>
            </div>
          </div>
        </section>
      </div>
    </div>
  </AppLayout>
</template>
