<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import PersonalFinanceBarChart from '@/Components/Personal/PersonalFinanceBarChart.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    wallets: Array,
    month: Object,
    chart: Object,
});

const money = (n) => `Rp ${Number(n ?? 0).toLocaleString('id-ID')}`;
</script>

<template>
  <Head title="Personal — Ringkasan" />
  <AppLayout>
    <div class="space-y-5">
      <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Personal</p>
        <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
          <div>
            <h1 class="text-3xl font-bold tracking-tight">Ringkasan keuangan</h1>
            <p class="mt-2 text-sm text-base-content/70">{{ month?.label }} — pemasukan, pengeluaran, dan saldo per dompet.</p>
          </div>
          <Link class="btn btn-ghost btn-sm" :href="route('personal')">Back</Link>
        </div>
      </div>

      <div class="grid gap-4 md:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">Pemasukan bulan ini</p>
          <p class="mt-2 text-2xl font-bold text-success">{{ money(month?.income) }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">Pengeluaran bulan ini</p>
          <p class="mt-2 text-2xl font-bold text-error">{{ money(month?.expense) }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">Sisa (net)</p>
          <p class="mt-2 text-2xl font-bold" :class="(month?.net ?? 0) >= 0 ? 'text-primary' : 'text-warning'">{{ money(month?.net) }}</p>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Saldo per dompet</h2>
          <p class="ocn-panel__desc">Saldo dihitung dari total pemasukan dikurangi pengeluaran pada dompet yang sama.</p>
        </div>
        <div class="overflow-x-auto">
          <table class="table table-zebra">
            <thead>
              <tr>
                <th>Dompet</th>
                <th>Mata uang</th>
                <th class="text-right">Saldo</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="w in wallets" :key="w.id">
                <td class="font-medium">{{ w.name }}</td>
                <td><span class="badge badge-ghost badge-sm">{{ w.currency }}</span></td>
                <td class="text-right font-mono">{{ money(w.balance) }}</td>
              </tr>
              <tr v-if="!(wallets && wallets.length)">
                <td colspan="3" class="py-8 text-center text-base-content/50">Belum ada dompet.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Tren 6 bulan terakhir</h2>
        </div>
        <div class="card-body">
          <PersonalFinanceBarChart
            :labels="chart?.labels ?? []"
            :income="chart?.income ?? []"
            :expense="chart?.expense ?? []"
          />
        </div>
      </div>
    </div>
  </AppLayout>
</template>
