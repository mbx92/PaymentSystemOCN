<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useCurrency } from '@/composables/useCurrency';

const props = defineProps({
  balances: Array,
  totals: Object,
});

const { format } = useCurrency();

const typeLabel = (type) => {
  const map = {
    asset: 'Aset',
    liability: 'Liabilitas',
    equity: 'Ekuitas',
    revenue: 'Pendapatan',
    expense: 'Beban',
  };
  return map[type] ?? type;
};

const typeBadgeClass = (type) => {
  const map = {
    asset: 'badge-info',
    liability: 'badge-warning',
    equity: 'badge-primary',
    revenue: 'badge-success',
    expense: 'badge-error',
  };
  return map[type] ?? 'badge-ghost';
};
</script>

<template>
  <Head title="Neraca Saldo" />
  <AppLayout>
    <div class="space-y-5">
      <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Laporan Akuntansi</p>
        <div class="mt-2 flex items-center justify-between gap-3">
          <h1 class="text-3xl font-bold tracking-tight">Neraca Saldo</h1>
          <Link class="btn btn-ghost btn-sm" :href="route('erp.accounting')">Back</Link>
        </div>
        <p class="mt-2 text-sm text-base-content/70">Ringkasan saldo debit dan kredit seluruh akun dalam buku besar.</p>
      </div>

      <div class="grid gap-3 md:grid-cols-3">
        <div class="ocn-panel">
          <div class="ocn-panel__head py-3"><h2 class="ocn-panel__title text-sm font-medium">Total debit</h2></div>
          <div class="card-body py-4">
            <p class="text-xl font-bold tabular-nums">{{ format(totals?.debit ?? 0) }}</p>
          </div>
        </div>
        <div class="ocn-panel">
          <div class="ocn-panel__head py-3"><h2 class="ocn-panel__title text-sm font-medium">Total kredit</h2></div>
          <div class="card-body py-4">
            <p class="text-xl font-bold tabular-nums">{{ format(totals?.credit ?? 0) }}</p>
          </div>
        </div>
        <div class="ocn-panel">
          <div class="ocn-panel__head py-3"><h2 class="ocn-panel__title text-sm font-medium">Status</h2></div>
          <div class="card-body py-4">
            <p v-if="totals?.balanced" class="text-xl font-bold text-success">Balance</p>
            <p v-else class="text-xl font-bold text-error">Tidak Balance</p>
          </div>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head flex items-center justify-between gap-2">
          <h2 class="ocn-panel__title">Daftar akun</h2>
          <span class="text-xs text-base-content/60">{{ balances?.length ?? 0 }} akun</span>
        </div>
        <div class="overflow-x-auto">
          <table class="table">
            <thead class="sticky top-0 z-10">
              <tr>
                <th>Kode</th>
                <th>Nama Akun</th>
                <th>Tipe</th>
                <th class="text-right">Debit</th>
                <th class="text-right">Kredit</th>
                <th class="text-right">Saldo</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in balances"
                :key="row.code"
                class="transition-colors hover:bg-primary/5"
              >
                <td class="font-mono text-xs">{{ row.code }}</td>
                <td class="font-medium">{{ row.name }}</td>
                <td>
                  <span class="badge badge-sm" :class="typeBadgeClass(row.type)">{{ typeLabel(row.type) }}</span>
                </td>
                <td class="text-right tabular-nums" :class="Number(row.debit_total) > 0 ? 'font-semibold' : 'text-base-content/30'">
                  {{ format(Number(row.debit_total)) }}
                </td>
                <td class="text-right tabular-nums" :class="Number(row.credit_total) > 0 ? 'font-semibold' : 'text-base-content/30'">
                  {{ format(Number(row.credit_total)) }}
                </td>
                <td class="text-right tabular-nums font-semibold" :class="Number(row.debit_total) - Number(row.credit_total) >= 0 ? 'text-primary' : 'text-error'">
                  {{ format(Math.abs(Number(row.debit_total) - Number(row.credit_total))) }}
                  <span class="text-xs font-normal text-base-content/40 ml-1">{{ Number(row.debit_total) - Number(row.credit_total) >= 0 ? 'D' : 'K' }}</span>
                </td>
              </tr>
              <tr v-if="!balances?.length">
                <td colspan="6" class="py-16 text-center">
                  <svg class="mx-auto h-12 w-12 text-base-content/20" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                  </svg>
                  <p class="mt-3 text-sm font-medium text-base-content/50">Belum ada data</p>
                  <p class="mt-1 text-xs text-base-content/40">Neraca saldo akan terisi setelah ada jurnal yang diposting.</p>
                </td>
              </tr>
            </tbody>
            <tfoot v-if="balances?.length">
              <tr class="border-t-2 border-base-content/20 font-bold">
                <td colspan="3" class="text-right">Total</td>
                <td class="text-right tabular-nums">{{ format(totals?.debit ?? 0) }}</td>
                <td class="text-right tabular-nums">{{ format(totals?.credit ?? 0) }}</td>
                <td class="text-right tabular-nums" :class="totals?.balanced ? 'text-success' : 'text-error'">
                  {{ format(Math.abs((totals?.debit ?? 0) - (totals?.credit ?? 0))) }}
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
