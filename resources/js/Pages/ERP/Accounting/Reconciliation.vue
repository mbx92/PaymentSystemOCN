<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { useCurrency } from '@/composables/useCurrency';

const props = defineProps({
  period: String,
  rows: Array,
});

const { format } = useCurrency();
const period = ref(props.period || 'daily');

watch(period, (value) => {
  router.get(route('erp.accounting.reconciliation'), { period: value }, { preserveState: true, replace: true });
});
</script>

<template>
  <Head title="Accounting - Rekonsiliasi" />
  <AppLayout>
    <div class="space-y-5">
      <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Accounting Workspace</p>
        <div class="mt-2 flex items-center justify-between gap-3">
          <h1 class="text-3xl font-bold tracking-tight">Rekonsiliasi Kas</h1>
          <Link class="btn btn-ghost btn-sm" :href="route('erp.accounting')">Back</Link>
        </div>
        <p class="mt-2 text-sm text-base-content/70">Ringkasan mutasi kas per akun sumber dana untuk kontrol harian/mingguan.</p>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Periode rekonsiliasi</h2>
        </div>
        <div class="card-body">
          <div class="join">
            <button class="btn btn-sm join-item" :class="period === 'daily' ? 'btn-primary' : 'btn-outline'" @click="period = 'daily'">Harian</button>
            <button class="btn btn-sm join-item" :class="period === 'weekly' ? 'btn-primary' : 'btn-outline'" @click="period = 'weekly'">Mingguan</button>
          </div>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Data rekonsiliasi</h2>
        </div>
        <div class="overflow-x-auto">
          <table class="table table-zebra">
            <thead>
              <tr>
                <th>Periode</th>
                <th>Akun Kas/Bank</th>
                <th>Kas Masuk</th>
                <th>Kas Keluar</th>
                <th>Net</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in rows" :key="`${row.bucket}-${row.cash_account_id}`">
                <td>{{ row.bucket }}</td>
                <td class="font-medium">{{ row.cash_account_name }}</td>
                <td class="font-semibold text-success">{{ format(row.cash_in) }}</td>
                <td class="font-semibold text-error">{{ format(row.cash_out) }}</td>
                <td :class="['font-semibold', row.net >= 0 ? 'text-primary' : 'text-error']">{{ format(row.net) }}</td>
              </tr>
              <tr v-if="!rows.length">
                <td colspan="5" class="py-10 text-center text-base-content/50">Belum ada data rekonsiliasi.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

