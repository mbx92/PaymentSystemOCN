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
  transactions: Object,
  filters: Object,
  filtersMeta: Object,
  statusOptions: Array,
  channelOptions: Array,
  paymentMethodOptions: Array,
});

const { format } = useCurrency();
const { formatDateTime } = useDateFormat();

const filters = ref({
  status: props.filters?.status ?? '',
  channel: props.filters?.channel ?? '',
  payment_method_id: props.filters?.payment_method_id ?? 'all',
  date_from: props.filters?.date_from ?? '',
  date_to: props.filters?.date_to ?? '',
  q: props.filters?.q ?? '',
  per_page: props.filtersMeta?.per_page ?? props.transactions?.per_page ?? 25,
});

let timer;
watch(filters, (val) => {
  clearTimeout(timer);
  timer = setTimeout(() => {
    router.get(route('reports.pos'), val, { preserveState: true, replace: true });
  }, 300);
}, { deep: true });
</script>

<template>
  <Head title="POS Report" />
  <AppLayout>
    <div class="space-y-5">
      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Laporan Penjualan</p>
              <h1 class="ocn-panel__title mt-1">POS Report</h1>
              <p class="ocn-panel__desc mt-1">Pantau omset POS, diskon, channel penjualan, metode bayar, dan status transaksi dalam satu report.</p>
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
          <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
            <select v-model="filters.status" class="select select-bordered select-sm w-full">
              <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
            <select v-model="filters.channel" class="select select-bordered select-sm w-full">
              <option v-for="opt in channelOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
            <select v-model="filters.payment_method_id" class="select select-bordered select-sm w-full">
              <option value="all">Semua Metode</option>
              <option v-for="opt in paymentMethodOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
            <input v-model="filters.date_from" type="date" class="input input-bordered input-sm w-full" />
            <input v-model="filters.date_to" type="date" class="input input-bordered input-sm w-full" />
            <input v-model="filters.q" type="text" class="input input-bordered input-sm w-full" placeholder="Cari transaksi / order / kasir..." />
          </div>
        </div>
      </div>

      <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
        <div class="ocn-panel"><div class="ocn-panel__head py-3"><h2 class="ocn-panel__title text-sm font-medium">Transaksi</h2></div><div class="card-body py-4"><p class="text-xl font-bold">{{ summary?.transaction_count ?? 0 }}</p></div></div>
        <div class="ocn-panel"><div class="ocn-panel__head py-3"><h2 class="ocn-panel__title text-sm font-medium">Gross</h2></div><div class="card-body py-4"><p class="text-xl font-bold">{{ format(summary?.gross_total ?? 0) }}</p></div></div>
        <div class="ocn-panel"><div class="ocn-panel__head py-3"><h2 class="ocn-panel__title text-sm font-medium">Discount</h2></div><div class="card-body py-4"><p class="text-xl font-bold text-warning">{{ format(summary?.discount_total ?? 0) }}</p></div></div>
        <div class="ocn-panel"><div class="ocn-panel__head py-3"><h2 class="ocn-panel__title text-sm font-medium">Grand Total</h2></div><div class="card-body py-4"><p class="text-xl font-bold text-primary">{{ format(summary?.grand_total ?? 0) }}</p></div></div>
        <div class="ocn-panel"><div class="ocn-panel__head py-3"><h2 class="ocn-panel__title text-sm font-medium">Refund</h2></div><div class="card-body py-4"><p class="text-xl font-bold text-error">{{ summary?.refund_count ?? 0 }}</p></div></div>
      </div>

      <div class="grid gap-5 xl:grid-cols-3">
        <div class="ocn-panel">
          <div class="ocn-panel__head"><h2 class="ocn-panel__title">Pivot Status</h2></div>
          <div class="overflow-x-auto">
            <table class="table table-sm">
              <thead><tr><th>Status</th><th class="text-right">Txn</th><th class="text-right">Grand Total</th></tr></thead>
              <tbody>
                <tr v-for="row in (pivot?.status || [])" :key="row.label">
                  <td class="font-medium">{{ row.label }}</td>
                  <td class="text-right">{{ row.count }}</td>
                  <td class="text-right">{{ format(row.grand_total) }}</td>
                </tr>
                <tr v-if="!(pivot?.status || []).length"><td colspan="3" class="text-center py-8 text-base-content/50">Tidak ada data status.</td></tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="ocn-panel">
          <div class="ocn-panel__head"><h2 class="ocn-panel__title">Pivot Channel</h2></div>
          <div class="overflow-x-auto">
            <table class="table table-sm">
              <thead><tr><th>Channel</th><th class="text-right">Txn</th><th class="text-right">Grand Total</th></tr></thead>
              <tbody>
                <tr v-for="row in (pivot?.channel || [])" :key="row.label">
                  <td class="font-medium">{{ row.label }}</td>
                  <td class="text-right">{{ row.count }}</td>
                  <td class="text-right">{{ format(row.grand_total) }}</td>
                </tr>
                <tr v-if="!(pivot?.channel || []).length"><td colspan="3" class="text-center py-8 text-base-content/50">Tidak ada data channel.</td></tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="ocn-panel">
          <div class="ocn-panel__head"><h2 class="ocn-panel__title">Pivot Metode Bayar</h2></div>
          <div class="overflow-x-auto">
            <table class="table table-sm">
              <thead><tr><th>Metode</th><th class="text-right">Txn</th><th class="text-right">Grand Total</th></tr></thead>
              <tbody>
                <tr v-for="row in (pivot?.payment_method || [])" :key="row.label">
                  <td class="font-medium">{{ row.label }}</td>
                  <td class="text-right">{{ row.count }}</td>
                  <td class="text-right">{{ format(row.grand_total) }}</td>
                </tr>
                <tr v-if="!(pivot?.payment_method || []).length"><td colspan="3" class="text-center py-8 text-base-content/50">Tidak ada data metode bayar.</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head"><h2 class="ocn-panel__title">Daftar Transaksi POS</h2></div>
        <div class="overflow-x-auto">
          <table class="table table-sm">
            <thead>
              <tr>
                <th>No</th>
                <th>Waktu</th>
                <th>Channel</th>
                <th>Kasir</th>
                <th>Metode</th>
                <th>Item</th>
                <th class="text-right">Gross</th>
                <th class="text-right">Discount</th>
                <th class="text-right">Grand Total</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in (transactions?.data || [])" :key="row.id">
                <td>
                  <div class="font-mono text-xs">{{ row.number }}</div>
                  <div class="text-xs text-base-content/60">{{ row.marketplace_order_code || '-' }}</div>
                </td>
                <td class="whitespace-nowrap">{{ formatDateTime(row.sold_at) }}</td>
                <td>{{ row.sales_channel_label }}</td>
                <td>{{ row.cashier }}</td>
                <td>{{ row.payment_method }}</td>
                <td class="text-right">{{ row.items_count }}</td>
                <td class="text-right">{{ format(row.gross_total) }}</td>
                <td class="text-right text-warning">{{ format(row.discount_total) }}</td>
                <td class="text-right font-semibold text-primary">{{ format(row.grand_total) }}</td>
                <td><StatusBadge :status="row.status" /></td>
              </tr>
              <tr v-if="!(transactions?.data || []).length"><td colspan="10" class="text-center py-10 text-base-content/50">Tidak ada transaksi POS pada filter ini.</td></tr>
            </tbody>
          </table>
        </div>
        <DataTablePagination :paginator="transactions" @update:per-page="(n) => { filters.per_page = n; }" />
      </div>
    </div>
  </AppLayout>
</template>
