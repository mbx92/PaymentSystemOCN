<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { useCurrency } from '@/composables/useCurrency';
import { ArrowDownTrayIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ members: Array, distributions: Array, totalPay: Number, filters: Object, years: Array });
const { format } = useCurrency();

const userId = ref(props.filters.user_id ?? '');
const year = ref(props.filters.year ?? '');

watch([userId, year], ([u, y]) => {
  router.get(route('reports.member-payments'), { user_id: u, year: y }, { preserveState: false, preserveScroll: true });
});

const exportExcel = () => window.location.href = route('export.member-payments', { user_id: userId.value, year: year.value });
</script>

<template>
  <Head title="Laporan - Pembayaran Anggota" />
  <AppLayout>
    <div class="space-y-5">
      <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Accounting Workspace</p>
        <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
          <div>
            <h1 class="text-3xl font-bold tracking-tight">Laporan Pembayaran Anggota</h1>
            <p class="mt-2 text-sm text-base-content/70">Distribusi pembayaran anggota per project sesuai filter anggota dan tahun.</p>
          </div>
          <div class="flex items-center gap-2">
            <button class="btn btn-success btn-sm gap-2" @click="exportExcel">
              <ArrowDownTrayIcon class="h-4 w-4" /> Export Excel
            </button>
            <Link class="btn btn-ghost btn-sm" :href="route('erp.accounting.payments')">Back</Link>
          </div>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Filter</h2>
          <p class="ocn-panel__desc">Pilih anggota dan tahun untuk mempersempit data.</p>
        </div>
        <div class="card-body">
          <div class="flex flex-wrap gap-3">
            <select v-model="userId" class="select select-bordered select-sm">
              <option value="">Semua Anggota</option>
              <option v-for="m in members" :key="m.id" :value="m.id">{{ m.name }}</option>
            </select>
            <select v-model="year" class="select select-bordered select-sm">
              <option value="">Semua Tahun</option>
              <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
            </select>
          </div>
        </div>
      </div>

      <div class="stats shadow w-full">
        <div class="stat py-3">
          <div class="stat-title">Total Pembayaran</div>
          <div class="stat-value text-xl text-primary">{{ format(totalPay) }}</div>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Distribusi pembayaran anggota</h2>
          <p class="ocn-panel__desc">Per project sesuai filter anggota dan tahun.</p>
        </div>
        <div class="overflow-x-auto">
          <table class="table table-zebra text-sm">
            <thead>
              <tr>
                <th>Nama</th>
                <th>Project</th>
                <th>Status</th>
                <th>Peran</th>
                <th class="text-right">%</th>
                <th class="text-right">Base Pay</th>
                <th class="text-right">Bonus</th>
                <th class="text-right">Total</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(d, i) in distributions" :key="i">
                <td class="font-medium">{{ d.user_name }}</td>
                <td>{{ d.project_name }}</td>
                <td><StatusBadge :status="d.project_status" /></td>
                <td class="capitalize">{{ d.role_in_project }}</td>
                <td class="text-right">{{ d.percentage }}%</td>
                <td class="text-right">{{ format(d.base_pay) }}</td>
                <td class="text-right">{{ format(d.bonus) }}</td>
                <td class="text-right font-semibold text-primary">{{ format(d.total_pay) }}</td>
              </tr>
              <tr v-if="!distributions.length">
                <td colspan="8" class="py-10 text-center text-base-content/50">Tidak ada data</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
