<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTablePagination from '@/Components/DataTablePagination.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { ref, watch } from 'vue';
import { useCurrency } from '@/composables/useCurrency';
import { useDateFormat } from '@/composables/useDateFormat';

const props = defineProps({
  summary: Object,
  pivot: Object,
  transactions: Object,
  filters: Object,
  filtersMeta: Object,
  companyOptions: Array,
  projectOptions: Array,
  sourceOptions: Array,
  groupByOptions: Array,
});

const { format } = useCurrency();
const { formatDate } = useDateFormat();

const now = new Date();
const currentYear = now.getFullYear();
const yearOptions = Array.from({ length: 7 }, (_, i) => currentYear - 5 + i);

const filters = ref({
  date_from: props.filters?.date_from ?? '',
  date_to: props.filters?.date_to ?? '',
  year: props.filters?.year ?? String(currentYear),
  quarter: props.filters?.quarter ?? 'all',
  source: props.filters?.source ?? 'all',
  project_id: props.filters?.project_id ?? 'all',
  group_by: props.filters?.group_by ?? 'day',
  company_id: props.filters?.company_id ?? 'all',
  per_page: props.filtersMeta?.per_page ?? props.transactions?.per_page ?? 25,
});

const quarterRange = (year, q) => {
  if (q === 'all') return null;
  const qNum = parseInt(q, 10);
  const startMonth = (qNum - 1) * 3;
  return {
    from: `${year}-${String(startMonth + 1).padStart(2, '0')}-01`,
    to: `${year}-${String(startMonth + 3).padStart(2, '0')}-${new Date(year, startMonth + 3, 0).getDate()}`,
  };
};

const applyYearQuarter = () => {
  const y = filters.value.year;
  const q = filters.value.quarter;
  if (q === 'all') {
    filters.value.date_from = `${y}-01-01`;
    filters.value.date_to = `${y}-12-31`;
  } else {
    const range = quarterRange(y, q);
    if (range) {
      filters.value.date_from = range.from;
      filters.value.date_to = range.to;
    }
  }
};

watch(() => filters.value.year, () => { filters.value.quarter = 'all'; applyYearQuarter(); });
watch(() => filters.value.quarter, applyYearQuarter);

let timer;
watch(filters, (val) => {
  clearTimeout(timer);
  timer = setTimeout(() => {
    router.get(route('reports.cashflow'), val, { preserveState: true, replace: true });
  }, 300);
}, { deep: true });
</script>

<template>
  <Head title="Cashflow Report" />
  <AppLayout>
    <div class="space-y-5">
      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Laporan Management</p>
              <h1 class="ocn-panel__title mt-1">Cashflow Report</h1>
              <p class="ocn-panel__desc mt-1">Filter arus kas berdasarkan periode, lalu lihat pivot kategori, project, dan sumber transaksi.</p>
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
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Filter</h2>
        </div>
        <div class="card-body">
          <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            <select v-model="filters.year" class="select select-bordered select-sm w-full">
              <option v-for="y in yearOptions" :key="y" :value="String(y)">{{ y }}</option>
            </select>
            <select v-model="filters.quarter" class="select select-bordered select-sm w-full">
              <option value="all">Semua Kuartal</option>
              <option value="1">Q1 (Jan–Mar)</option>
              <option value="2">Q2 (Apr–Jun)</option>
              <option value="3">Q3 (Jul–Sep)</option>
              <option value="4">Q4 (Oct–Dec)</option>
            </select>
            <input v-model="filters.date_from" type="date" class="input input-bordered input-sm w-full" />
            <input v-model="filters.date_to" type="date" class="input input-bordered input-sm w-full" />
          </div>
          <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            <select v-model="filters.source" class="select select-bordered select-sm w-full">
              <option v-for="opt in sourceOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
            <select v-model="filters.project_id" class="select select-bordered select-sm w-full">
              <option value="all">Semua Project</option>
              <option v-for="opt in projectOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
            <select v-model="filters.group_by" class="select select-bordered select-sm w-full">
              <option v-for="opt in groupByOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
            <select v-model="filters.company_id" class="select select-bordered select-sm w-full">
              <option value="all">Semua Perusahaan</option>
              <option v-for="opt in companyOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
          </div>
        </div>
      </div>

      <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
        <div class="ocn-panel">
          <div class="ocn-panel__head py-3"><h2 class="ocn-panel__title text-sm font-medium">Kas masuk</h2></div>
          <div class="card-body py-4"><p class="text-xl font-bold text-success">{{ format(summary?.total_in ?? 0) }}</p></div>
        </div>
        <div class="ocn-panel">
          <div class="ocn-panel__head py-3"><h2 class="ocn-panel__title text-sm font-medium">Kas keluar</h2></div>
          <div class="card-body py-4"><p class="text-xl font-bold text-error">{{ format(summary?.total_out ?? 0) }}</p></div>
        </div>
        <div class="ocn-panel">
          <div class="ocn-panel__head py-3"><h2 class="ocn-panel__title text-sm font-medium">Net cashflow</h2></div>
          <div class="card-body py-4">
            <p class="text-xl font-bold" :class="(summary?.net_cashflow ?? 0) >= 0 ? 'text-primary' : 'text-error'">{{ format(summary?.net_cashflow ?? 0) }}</p>
          </div>
        </div>
        <div class="ocn-panel">
          <div class="ocn-panel__head py-3"><h2 class="ocn-panel__title text-sm font-medium">Transaksi</h2></div>
          <div class="card-body py-4">
            <p class="text-xl font-bold text-base-content">{{ summary?.transaction_count ?? 0 }}</p>
            <p class="mt-1 text-xs text-base-content/60">{{ summary?.cash_in_count ?? 0 }} masuk · {{ summary?.cash_out_count ?? 0 }} keluar</p>
          </div>
        </div>
      </div>

      <div class="grid gap-5 xl:grid-cols-2">
        <div class="ocn-panel flex flex-col min-w-0">
          <div class="ocn-panel__head shrink-0">
            <h2 class="ocn-panel__title">Pivot Timeline</h2>
            <p class="ocn-panel__desc mt-1">Dikelompokkan berdasarkan pilihan periode di filter.</p>
          </div>
          <div class="overflow-y-auto max-h-72">
            <table class="table table-xs">
              <thead class="sticky top-0 z-10 bg-base-100">
                <tr>
                  <th>Bucket</th>
                  <th class="text-right">Masuk</th>
                  <th class="text-right">Keluar</th>
                  <th class="text-right">Net</th>
                  <th class="w-10 text-right">Txn</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in (pivot?.timeline || [])" :key="row.bucket" class="text-xs">
                  <td class="font-medium">{{ row.bucket }}</td>
                  <td class="text-right text-success">{{ format(row.total_in) }}</td>
                  <td class="text-right text-error">{{ format(row.total_out) }}</td>
                  <td class="text-right font-semibold" :class="row.net >= 0 ? 'text-primary' : 'text-error'">{{ format(row.net) }}</td>
                  <td class="text-right">{{ row.count }}</td>
                </tr>
                <tr v-if="!(pivot?.timeline || []).length">
                  <td colspan="5" class="text-center py-6 text-xs text-base-content/50">Tidak ada data pada periode ini.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="ocn-panel flex flex-col min-w-0">
          <div class="ocn-panel__head shrink-0">
            <h2 class="ocn-panel__title">Pivot per Sumber</h2>
            <p class="ocn-panel__desc mt-1">Membandingkan transaksi project vs manual/umum.</p>
          </div>
          <div class="overflow-y-auto max-h-72">
            <table class="table table-xs">
              <thead class="sticky top-0 z-10 bg-base-100">
                <tr>
                  <th>Sumber</th>
                  <th class="text-right">Masuk</th>
                  <th class="text-right">Keluar</th>
                  <th class="text-right">Net</th>
                  <th class="w-10 text-right">Txn</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in (pivot?.sources || [])" :key="row.label" class="text-xs">
                  <td class="font-medium">{{ row.label }}</td>
                  <td class="text-right text-success">{{ format(row.total_in) }}</td>
                  <td class="text-right text-error">{{ format(row.total_out) }}</td>
                  <td class="text-right font-semibold" :class="row.net >= 0 ? 'text-primary' : 'text-error'">{{ format(row.net) }}</td>
                  <td class="text-right">{{ row.count }}</td>
                </tr>
                <tr v-if="!(pivot?.sources || []).length">
                  <td colspan="5" class="text-center py-6 text-xs text-base-content/50">Belum ada data sumber.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="grid gap-5 xl:grid-cols-2">
        <div class="ocn-panel flex flex-col min-w-0">
          <div class="ocn-panel__head shrink-0">
            <h2 class="ocn-panel__title">Pivot per Kategori</h2>
          </div>
          <div class="overflow-y-auto max-h-80">
            <table class="table table-xs">
              <thead class="sticky top-0 z-10 bg-base-100">
                <tr>
                  <th>Kategori</th>
                  <th class="text-right">Masuk</th>
                  <th class="text-right">Keluar</th>
                  <th class="text-right">Net</th>
                  <th class="w-10 text-right">Txn</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in (pivot?.categories || [])" :key="row.label" class="text-xs">
                  <td class="font-medium">{{ row.label }}</td>
                  <td class="text-right text-success">{{ format(row.total_in) }}</td>
                  <td class="text-right text-error">{{ format(row.total_out) }}</td>
                  <td class="text-right font-semibold" :class="row.net >= 0 ? 'text-primary' : 'text-error'">{{ format(row.net) }}</td>
                  <td class="text-right">{{ row.count }}</td>
                </tr>
                <tr v-if="!(pivot?.categories || []).length">
                  <td colspan="5" class="text-center py-6 text-xs text-base-content/50">Belum ada data kategori.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="ocn-panel flex flex-col min-w-0">
          <div class="ocn-panel__head shrink-0">
            <h2 class="ocn-panel__title">Pivot per Project</h2>
          </div>
          <div class="overflow-y-auto max-h-80">
            <table class="table table-xs">
              <thead class="sticky top-0 z-10 bg-base-100">
                <tr>
                  <th class="w-48">Project</th>
                  <th class="text-right">Masuk</th>
                  <th class="text-right">Keluar</th>
                  <th class="text-right">Net</th>
                  <th class="w-10 text-right">Txn</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in (pivot?.projects || [])" :key="row.label" class="text-xs">
                  <td class="max-w-44 truncate font-medium" :title="row.label">{{ row.label }}</td>
                  <td class="text-right text-success">{{ format(row.total_in) }}</td>
                  <td class="text-right text-error">{{ format(row.total_out) }}</td>
                  <td class="text-right font-semibold" :class="row.net >= 0 ? 'text-primary' : 'text-error'">{{ format(row.net) }}</td>
                  <td class="text-right">{{ row.count }}</td>
                </tr>
                <tr v-if="!(pivot?.projects || []).length">
                  <td colspan="5" class="text-center py-6 text-xs text-base-content/50">Belum ada data project.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Detail Transaksi</h2>
          <p class="ocn-panel__desc mt-1">Urutan terbaru di atas untuk audit cepat.</p>
        </div>
        <div class="overflow-x-auto">
          <table class="table table-xs">
            <thead>
              <tr>
                <th class="w-24">Tanggal</th>
                <th class="w-14">Arah</th>
                <th>Project</th>
                <th class="w-28">Kategori</th>
                <th class="w-28">Sumber</th>
                <th class="w-40">Metode / Penerima</th>
                <th class="w-48">Catatan</th>
                <th class="w-32 text-right">Nominal</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in (transactions?.data || [])" :key="`${row.direction}-${row.id}`" class="text-xs">
                <td class="whitespace-nowrap text-xs">{{ formatDate(row.date) }}</td>
                <td>
                  <span class="badge badge-xs" :class="row.direction === 'in' ? 'badge-success' : 'badge-error'">
                    {{ row.direction === 'in' ? 'Masuk' : 'Keluar' }}
                  </span>
                </td>
                <td class="max-w-36 truncate font-medium">{{ row.project_name }}</td>
                <td class="max-w-24 truncate">{{ row.category }}</td>
                <td class="max-w-24 truncate">{{ row.source }}</td>
                <td class="max-w-36 truncate">{{ row.direction === 'in' ? row.payment_method : row.counterparty }}</td>
                <td class="max-w-44 truncate text-base-content/70" :title="row.note">{{ row.note || '-' }}</td>
                <td class="text-right font-semibold" :class="row.direction === 'in' ? 'text-success' : 'text-error'">
                  {{ format(row.amount) }}
                </td>
              </tr>
              <tr v-if="!(transactions?.data || []).length">
                <td colspan="8" class="text-center py-6 text-xs text-base-content/50">Tidak ada transaksi pada filter ini.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <DataTablePagination :paginator="transactions" @update:per-page="(n) => { filters.per_page = n; }" />
      </div>
    </div>
  </AppLayout>
</template>
