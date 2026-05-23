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
  projectOptions: Array,
  sourceOptions: Array,
  groupByOptions: Array,
});

const { format } = useCurrency();
const { formatDate } = useDateFormat();

const filters = ref({
  date_from: props.filters?.date_from ?? '',
  date_to: props.filters?.date_to ?? '',
  source: props.filters?.source ?? 'all',
  project_id: props.filters?.project_id ?? 'all',
  group_by: props.filters?.group_by ?? 'day',
  per_page: props.filtersMeta?.per_page ?? props.transactions?.per_page ?? 25,
});

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
          <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
            <input v-model="filters.date_from" type="date" class="input input-bordered input-sm w-full" />
            <input v-model="filters.date_to" type="date" class="input input-bordered input-sm w-full" />
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
        <div class="ocn-panel">
          <div class="ocn-panel__head">
            <h2 class="ocn-panel__title">Pivot Timeline</h2>
            <p class="ocn-panel__desc mt-1">Dikelompokkan berdasarkan pilihan periode di filter.</p>
          </div>
          <div class="overflow-x-auto">
            <table class="table table-sm">
              <thead>
                <tr>
                  <th>Bucket</th>
                  <th class="text-right">Kas Masuk</th>
                  <th class="text-right">Kas Keluar</th>
                  <th class="text-right">Net</th>
                  <th class="text-right">Txn</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in (pivot?.timeline || [])" :key="row.bucket">
                  <td class="font-medium">{{ row.bucket }}</td>
                  <td class="text-right text-success">{{ format(row.total_in) }}</td>
                  <td class="text-right text-error">{{ format(row.total_out) }}</td>
                  <td class="text-right font-semibold" :class="row.net >= 0 ? 'text-primary' : 'text-error'">{{ format(row.net) }}</td>
                  <td class="text-right">{{ row.count }}</td>
                </tr>
                <tr v-if="!(pivot?.timeline || []).length">
                  <td colspan="5" class="text-center py-8 text-base-content/50">Tidak ada data pada periode ini.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="ocn-panel">
          <div class="ocn-panel__head">
            <h2 class="ocn-panel__title">Pivot per Sumber</h2>
            <p class="ocn-panel__desc mt-1">Membandingkan transaksi project vs manual/umum.</p>
          </div>
          <div class="overflow-x-auto">
            <table class="table table-sm">
              <thead>
                <tr>
                  <th>Sumber</th>
                  <th class="text-right">Kas Masuk</th>
                  <th class="text-right">Kas Keluar</th>
                  <th class="text-right">Net</th>
                  <th class="text-right">Txn</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in (pivot?.sources || [])" :key="row.label">
                  <td class="font-medium">{{ row.label }}</td>
                  <td class="text-right text-success">{{ format(row.total_in) }}</td>
                  <td class="text-right text-error">{{ format(row.total_out) }}</td>
                  <td class="text-right font-semibold" :class="row.net >= 0 ? 'text-primary' : 'text-error'">{{ format(row.net) }}</td>
                  <td class="text-right">{{ row.count }}</td>
                </tr>
                <tr v-if="!(pivot?.sources || []).length">
                  <td colspan="5" class="text-center py-8 text-base-content/50">Belum ada data sumber.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="grid gap-5 xl:grid-cols-2">
        <div class="ocn-panel">
          <div class="ocn-panel__head">
            <h2 class="ocn-panel__title">Pivot per Kategori</h2>
          </div>
          <div class="overflow-x-auto">
            <table class="table table-sm">
              <thead>
                <tr>
                  <th>Kategori</th>
                  <th class="text-right">Kas Masuk</th>
                  <th class="text-right">Kas Keluar</th>
                  <th class="text-right">Net</th>
                  <th class="text-right">Txn</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in (pivot?.categories || [])" :key="row.label">
                  <td class="font-medium">{{ row.label }}</td>
                  <td class="text-right text-success">{{ format(row.total_in) }}</td>
                  <td class="text-right text-error">{{ format(row.total_out) }}</td>
                  <td class="text-right font-semibold" :class="row.net >= 0 ? 'text-primary' : 'text-error'">{{ format(row.net) }}</td>
                  <td class="text-right">{{ row.count }}</td>
                </tr>
                <tr v-if="!(pivot?.categories || []).length">
                  <td colspan="5" class="text-center py-8 text-base-content/50">Belum ada data kategori.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="ocn-panel">
          <div class="ocn-panel__head">
            <h2 class="ocn-panel__title">Pivot per Project</h2>
          </div>
          <div class="overflow-x-auto">
            <table class="table table-sm">
              <thead>
                <tr>
                  <th>Project</th>
                  <th class="text-right">Kas Masuk</th>
                  <th class="text-right">Kas Keluar</th>
                  <th class="text-right">Net</th>
                  <th class="text-right">Txn</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in (pivot?.projects || [])" :key="row.label">
                  <td class="font-medium">{{ row.label }}</td>
                  <td class="text-right text-success">{{ format(row.total_in) }}</td>
                  <td class="text-right text-error">{{ format(row.total_out) }}</td>
                  <td class="text-right font-semibold" :class="row.net >= 0 ? 'text-primary' : 'text-error'">{{ format(row.net) }}</td>
                  <td class="text-right">{{ row.count }}</td>
                </tr>
                <tr v-if="!(pivot?.projects || []).length">
                  <td colspan="5" class="text-center py-8 text-base-content/50">Belum ada data project.</td>
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
          <table class="table table-sm">
            <thead>
              <tr>
                <th>Tanggal</th>
                <th>Arah</th>
                <th>Project</th>
                <th>Kategori</th>
                <th>Sumber</th>
                <th>Metode / Penerima</th>
                <th>Catatan</th>
                <th class="text-right">Nominal</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in (transactions?.data || [])" :key="`${row.direction}-${row.id}`">
                <td class="whitespace-nowrap">{{ formatDate(row.date) }}</td>
                <td>
                  <span class="badge badge-sm" :class="row.direction === 'in' ? 'badge-success' : 'badge-error'">
                    {{ row.direction === 'in' ? 'Masuk' : 'Keluar' }}
                  </span>
                </td>
                <td class="font-medium">{{ row.project_name }}</td>
                <td>{{ row.category }}</td>
                <td>{{ row.source }}</td>
                <td>{{ row.direction === 'in' ? row.payment_method : row.counterparty }}</td>
                <td class="max-w-xs truncate">{{ row.note || '-' }}</td>
                <td class="text-right font-semibold" :class="row.direction === 'in' ? 'text-success' : 'text-error'">
                  {{ format(row.amount) }}
                </td>
              </tr>
              <tr v-if="!(transactions?.data || []).length">
                <td colspan="8" class="text-center py-10 text-base-content/50">Tidak ada transaksi pada filter ini.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <DataTablePagination :paginator="transactions" @update:per-page="(n) => { filters.per_page = n; }" />
      </div>
    </div>
  </AppLayout>
</template>
