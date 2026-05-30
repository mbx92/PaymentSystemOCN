<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import PersonalFinanceBarChart from '@/Components/Personal/PersonalFinanceBarChart.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';

defineProps({
    wallets: Array,
    month: Object,
    chart: Object,
    investments: Object,
});

const money = (n) => `Rp ${Number(n ?? 0).toLocaleString('id-ID')}`;
const priceClass = (change) => {
    if (!change || change === 0) return 'text-base-content';
    return change > 0 ? 'text-success' : 'text-error';
};
</script>

<template>
  <Head title="Personal — Ringkasan" />
  <AppLayout>
    <div class="space-y-5">
      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Personal</p>
              <h1 class="ocn-panel__title mt-1">Ringkasan keuangan</h1>
              <p class="ocn-panel__desc mt-1">{{ month?.label }} — pemasukan, pengeluaran, dan saldo per dompet.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
              <Link class="btn btn-ghost btn-sm shrink-0 gap-1.5" :href="route('personal')">
              <ArrowLeftIcon class="h-4 w-4" />
              Back
            </Link>
            </div>
          </div>
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

      <div class="grid gap-4 md:grid-cols-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">Nilai portofolio</p>
          <p class="mt-2 text-2xl font-bold text-primary">{{ money(investments?.summary?.portfolio_value) }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">Instrumen investasi</p>
          <p class="mt-2 text-2xl font-bold text-base-content">{{ investments?.summary?.count ?? 0 }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">Investasi aktif</p>
          <p class="mt-2 text-2xl font-bold text-success">{{ investments?.summary?.active_count ?? 0 }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">Arus kas investasi</p>
          <p class="mt-2 text-2xl font-bold" :class="(investments?.summary?.net_flow ?? 0) >= 0 ? 'text-primary' : 'text-warning'">
            {{ money(investments?.summary?.net_flow) }}
          </p>
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
          <h2 class="ocn-panel__title">Investasi</h2>
          <p class="ocn-panel__desc">Arus kas investasi dihitung dari setoran - penarikan + dividen pada tiap instrumen.</p>
        </div>
        <div class="overflow-x-auto">
          <table class="table table-zebra">
            <thead>
              <tr>
                <th>Instrumen</th>
                <th>Ticker</th>
                <th>Jenis aset</th>
                <th>Status</th>
                <th class="text-right">Harga</th>
                <th class="text-right">Nilai pasar</th>
                <th class="text-right">Arus kas</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="investment in investments?.items ?? []" :key="investment.id">
                <td class="font-medium">{{ investment.name }}</td>
                <td><span v-if="investment.ticker" class="badge badge-sm badge-info">{{ investment.ticker }}</span><span v-else class="text-base-content/30">—</span></td>
                <td><span class="badge badge-ghost badge-sm">{{ investment.asset_label }}</span></td>
                <td>
                  <span class="badge badge-sm" :class="investment.is_active ? 'badge-success' : 'badge-ghost'">
                    {{ investment.is_active ? 'aktif' : 'ditutup' }}
                  </span>
                </td>
                <td class="text-right font-mono">
                  <template v-if="investment.current_price !== null">
                    {{ money(investment.current_price) }}
                    <span v-if="investment.price_change !== null" class="text-xs" :class="priceClass(investment.price_change)">
                      ({{ investment.price_change >= 0 ? '+' : '' }}{{ (investment.price_change_percent ?? 0).toFixed(2) }}%)
                    </span>
                  </template>
                  <span v-else class="text-base-content/30">—</span>
                </td>
                <td class="text-right font-mono">
                  <span v-if="investment.market_value !== null" class="text-primary">{{ money(investment.market_value) }}</span>
                  <span v-else class="text-base-content/30">—</span>
                </td>
                <td class="text-right font-mono" :class="investment.net_flow >= 0 ? 'text-primary' : 'text-error'">
                  {{ money(investment.net_flow) }}
                </td>
              </tr>
              <tr v-if="!((investments?.items ?? []).length)">
                <td colspan="7" class="py-8 text-center text-base-content/50">Belum ada investasi.</td>
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
