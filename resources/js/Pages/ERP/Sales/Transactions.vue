<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useCurrency } from '@/composables/useCurrency';
import { reactive, watch } from 'vue';

const props = defineProps({
  transactions: Array,
  filters: Object,
});

const { format } = useCurrency();
const filters = reactive({
  q: props.filters?.q ?? '',
  status: props.filters?.status ?? '',
  date_from: props.filters?.date_from ?? '',
  date_to: props.filters?.date_to ?? '',
});

let timer;
watch(filters, (val) => {
  clearTimeout(timer);
  timer = setTimeout(() => {
    router.get(route('erp.sales.pos.transactions'), val, {
      preserveState: true,
      replace: true,
    });
  }, 250);
}, { deep: true });

const openTransaction = (trxId) => {
  router.visit(route('erp.sales.pos.transactions.show', trxId));
};
</script>

<template>
  <Head title="Sales - Transaksi POS" />
  <AppLayout>
    <div class="space-y-4">
      <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex items-center justify-between gap-3">
          <div>
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Sales Workspace</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight">Transaksi POS</h1>
            <p class="mt-1 text-sm text-base-content/70">Riwayat transaksi POS yang sudah diproses.</p>
          </div>
          <div class="flex items-center gap-2">
            <Link class="btn btn-ghost btn-sm" :href="route('erp.sales')">Back</Link>
            <Link class="btn btn-primary btn-sm" :href="route('erp.sales.pos', { fullscreen: 1 })" target="_blank">Buka POS</Link>
          </div>
        </div>
      </div>

      <div class="card bg-base-100 shadow">
        <div class="card-body">
          <h2 class="card-title text-lg">Utilitas Filter</h2>
          <div class="grid grid-cols-1 gap-3 md:grid-cols-5">
            <div class="md:col-span-2">
              <label class="label"><span class="label-text text-xs uppercase tracking-wide">Search</span></label>
              <input v-model="filters.q" type="text" class="input input-sm input-bordered w-full" placeholder="No transaksi / nama kasir" />
            </div>
            <div>
              <label class="label"><span class="label-text text-xs uppercase tracking-wide">Status</span></label>
              <select v-model="filters.status" class="select select-sm select-bordered w-full">
                <option value="">Semua</option>
                <option value="paid">paid</option>
                <option value="refunded">refunded</option>
                <option value="reopened">reopened</option>
              </select>
            </div>
            <div>
              <label class="label"><span class="label-text text-xs uppercase tracking-wide">Dari Tanggal</span></label>
              <input v-model="filters.date_from" type="date" class="input input-sm input-bordered w-full" />
            </div>
            <div>
              <label class="label"><span class="label-text text-xs uppercase tracking-wide">Sampai Tanggal</span></label>
              <input v-model="filters.date_to" type="date" class="input input-sm input-bordered w-full" />
            </div>
          </div>
        </div>
      </div>

      <div class="card bg-base-100 shadow">
        <div class="card-body border-b border-base-300">
          <h2 class="card-title text-lg">Data Transaksi POS</h2>
        </div>
        <div class="card-body p-0">
          <div class="overflow-x-auto">
            <table class="table table-zebra">
              <thead>
                <tr>
                  <th>No Transaksi</th>
                  <th>Waktu</th>
                  <th>Item</th>
                  <th>Total Qty</th>
                  <th>Grand Total</th>
                  <th>Metode</th>
                  <th>Kasir</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="trx in transactions"
                  :key="trx.id"
                  class="cursor-pointer transition-colors hover:bg-primary/5"
                  @click="openTransaction(trx.id)"
                >
                  <td class="font-mono text-xs">{{ trx.number }}</td>
                  <td>{{ trx.sold_at || '-' }}</td>
                  <td>{{ trx.items_count }}</td>
                  <td>{{ trx.total_qty }}</td>
                  <td class="font-semibold text-primary">{{ format(trx.grand_total) }}</td>
                  <td>{{ trx.payment_method || '-' }}</td>
                  <td>{{ trx.cashier || '-' }}</td>
                  <td @click.stop><StatusBadge :status="trx.status" /></td>
                </tr>
                <tr v-if="!transactions?.length">
                  <td colspan="8" class="py-10 text-center text-base-content/50">Belum ada transaksi POS.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

