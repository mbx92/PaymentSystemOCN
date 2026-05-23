<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTablePagination from '@/Components/DataTablePagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { ref, watch } from 'vue';
import { useCurrency } from '@/composables/useCurrency';
import { useDateFormat } from '@/composables/useDateFormat';

const props = defineProps({
  summary: Object,
  pivot: Object,
  projects: Object,
  filters: Object,
  filtersMeta: Object,
  statusOptions: Array,
  projectTypeOptions: Array,
});

const { format } = useCurrency();
const { formatDate } = useDateFormat();

const filters = ref({
  status: props.filters?.status ?? '',
  project_type: props.filters?.project_type ?? '',
  date_from: props.filters?.date_from ?? '',
  date_to: props.filters?.date_to ?? '',
  q: props.filters?.q ?? '',
  per_page: props.filtersMeta?.per_page ?? props.projects?.per_page ?? 25,
});

let timer;
watch(filters, (val) => {
  clearTimeout(timer);
  timer = setTimeout(() => {
    router.get(route('reports.projects'), val, { preserveState: true, replace: true });
  }, 300);
}, { deep: true });
</script>

<template>
  <Head title="Project Report" />
  <AppLayout>
    <div class="space-y-5">
      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Laporan Management</p>
              <h1 class="ocn-panel__title mt-1">Project Report</h1>
              <p class="ocn-panel__desc mt-1">Monitor nilai project, cash in/out, profit, serta distribusi status dan tipe project.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
              <Link class="btn btn-ghost btn-sm shrink-0 gap-1.5" :href="route('erp.reporting')">
                <ArrowLeftIcon class="h-4 w-4" />
                Back
              </Link>
            </div>
          </div>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head"><h2 class="ocn-panel__title">Filter</h2></div>
        <div class="card-body">
          <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
            <select v-model="filters.status" class="select select-bordered select-sm w-full">
              <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
            <select v-model="filters.project_type" class="select select-bordered select-sm w-full">
              <option value="">Semua Tipe Project</option>
              <option v-for="opt in projectTypeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
            <input v-model="filters.date_from" type="date" class="input input-bordered input-sm w-full" />
            <input v-model="filters.date_to" type="date" class="input input-bordered input-sm w-full" />
            <input v-model="filters.q" type="text" class="input input-bordered input-sm w-full" placeholder="Cari project / client / invoice..." />
          </div>
        </div>
      </div>

      <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
        <div class="ocn-panel xl:col-span-2"><div class="ocn-panel__head py-3"><h2 class="ocn-panel__title text-sm font-medium">Jumlah Project</h2></div><div class="card-body py-4"><p class="text-xl font-bold">{{ summary?.project_count ?? 0 }}</p></div></div>
        <div class="ocn-panel xl:col-span-2"><div class="ocn-panel__head py-3"><h2 class="ocn-panel__title text-sm font-medium">Nilai Kontrak</h2></div><div class="card-body py-4"><p class="text-xl font-bold">{{ format(summary?.contract_value ?? 0) }}</p></div></div>
        <div class="ocn-panel xl:col-span-2"><div class="ocn-panel__head py-3"><h2 class="ocn-panel__title text-sm font-medium">Cash In</h2></div><div class="card-body py-4"><p class="text-xl font-bold text-success">{{ format(summary?.cash_in ?? 0) }}</p></div></div>
        <div class="ocn-panel xl:col-span-1">
          <div class="ocn-panel__head py-3"><h2 class="ocn-panel__title text-sm font-medium">Biaya Lain</h2></div>
          <div class="card-body py-4">
            <p class="text-xl font-bold text-error">{{ format(summary?.operational_cash_out ?? 0) }}</p>
          </div>
        </div>
        <div class="ocn-panel xl:col-span-1">
          <div class="ocn-panel__head py-3"><h2 class="ocn-panel__title text-sm font-medium">Pembelian Material</h2></div>
          <div class="card-body py-4">
            <p class="text-xl font-bold text-warning">{{ format(summary?.purchase_cost ?? 0) }}</p>
          </div>
        </div>
        <div class="ocn-panel xl:col-span-2">
          <div class="ocn-panel__head py-3"><h2 class="ocn-panel__title text-sm font-medium">Cash Out</h2></div>
          <div class="card-body py-4">
            <p class="text-xl font-bold text-error">{{ format(summary?.cash_out ?? 0) }}</p>
          </div>
        </div>
        <div class="ocn-panel xl:col-span-2"><div class="ocn-panel__head py-3"><h2 class="ocn-panel__title text-sm font-medium">Profit</h2></div><div class="card-body py-4"><p class="text-xl font-bold" :class="(summary?.profit ?? 0) >= 0 ? 'text-primary' : 'text-error'">{{ format(summary?.profit ?? 0) }}</p></div></div>
      </div>

      <div class="grid gap-5 xl:grid-cols-2">
        <div class="ocn-panel">
          <div class="ocn-panel__head"><h2 class="ocn-panel__title">Pivot Status</h2></div>
          <div class="overflow-x-auto">
            <table class="table table-sm">
              <thead><tr><th>Status</th><th class="text-right">Project</th><th class="text-right">Nilai</th><th class="text-right">Cash In</th><th class="text-right">Profit</th></tr></thead>
              <tbody>
                <tr v-for="row in (pivot?.status || [])" :key="row.label">
                  <td class="font-medium">{{ row.label }}</td>
                  <td class="text-right">{{ row.count }}</td>
                  <td class="text-right">{{ format(row.contract_value) }}</td>
                  <td class="text-right text-success">{{ format(row.cash_in) }}</td>
                  <td class="text-right font-semibold" :class="row.profit >= 0 ? 'text-primary' : 'text-error'">{{ format(row.profit) }}</td>
                </tr>
                <tr v-if="!(pivot?.status || []).length"><td colspan="5" class="text-center py-8 text-base-content/50">Tidak ada data status.</td></tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="ocn-panel">
          <div class="ocn-panel__head"><h2 class="ocn-panel__title">Pivot Tipe Project</h2></div>
          <div class="overflow-x-auto">
            <table class="table table-sm">
              <thead><tr><th>Tipe</th><th class="text-right">Project</th><th class="text-right">Nilai</th><th class="text-right">Cash In</th><th class="text-right">Profit</th></tr></thead>
              <tbody>
                <tr v-for="row in (pivot?.project_type || [])" :key="row.label">
                  <td class="font-medium">{{ row.label }}</td>
                  <td class="text-right">{{ row.count }}</td>
                  <td class="text-right">{{ format(row.contract_value) }}</td>
                  <td class="text-right text-success">{{ format(row.cash_in) }}</td>
                  <td class="text-right font-semibold" :class="row.profit >= 0 ? 'text-primary' : 'text-error'">{{ format(row.profit) }}</td>
                </tr>
                <tr v-if="!(pivot?.project_type || []).length"><td colspan="5" class="text-center py-8 text-base-content/50">Tidak ada data tipe project.</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head"><h2 class="ocn-panel__title">Daftar Project</h2></div>
        <div class="overflow-x-auto">
          <table class="table table-xs">
            <thead>
              <tr>
                <th>Project</th>
                <th>Klien</th>
                <th>Tipe</th>
                <th>Status</th>
                <th>Tanggal</th>
                <th class="text-right">Nilai</th>
                <th class="text-right">Cash In</th>
                <th class="text-right">Biaya Lain</th>
                <th class="text-right">Cash Out</th>
                <th class="text-right">Profit</th>
                <th class="text-right">Collection</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in (projects?.data || [])" :key="row.id">
                <td class="max-w-[220px]">
                  <div class="truncate font-medium" :title="row.name">{{ row.name }}</div>
                  <div class="truncate text-[11px] text-base-content/60 font-mono" :title="row.invoice_number || '-'">{{ row.invoice_number || '-' }}</div>
                </td>
                <td class="max-w-[160px] truncate" :title="row.client_name">{{ row.client_name }}</td>
                <td class="whitespace-nowrap">{{ row.project_type_label }}</td>
                <td class="whitespace-nowrap"><StatusBadge :status="row.status" /></td>
                <td class="whitespace-nowrap">{{ formatDate(row.finished_at || row.started_at || row.created_at) }}</td>
                <td class="whitespace-nowrap text-right">{{ format(row.contract_value) }}</td>
                <td class="whitespace-nowrap text-right text-success">{{ format(row.cash_in) }}</td>
                <td class="whitespace-nowrap text-right text-error">{{ format(row.operational_cash_out) }}</td>
                <td class="whitespace-nowrap text-right text-error">
                  <div>{{ format(row.cash_out) }}</div>
                  <div class="text-[10px] text-base-content/60">Beli {{ format(row.purchase_cost) }}</div>
                </td>
                <td class="whitespace-nowrap text-right font-semibold" :class="row.profit >= 0 ? 'text-primary' : 'text-error'">{{ format(row.profit) }}</td>
                <td class="whitespace-nowrap text-right">{{ row.collection_rate }}%</td>
              </tr>
              <tr v-if="!(projects?.data || []).length"><td colspan="11" class="text-center py-10 text-base-content/50">Tidak ada project pada filter ini.</td></tr>
            </tbody>
          </table>
        </div>
        <DataTablePagination :paginator="projects" @update:per-page="(n) => { filters.per_page = n; }" />
      </div>
    </div>
  </AppLayout>
</template>
