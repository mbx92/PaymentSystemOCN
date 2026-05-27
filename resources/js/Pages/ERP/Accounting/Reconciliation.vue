<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTablePagination from '@/Components/DataTablePagination.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { ref, watch } from 'vue';
import { useCurrency } from '@/composables/useCurrency';

const props = defineProps({
  period: String,
  rows: Object,
  filters: Object,
});

const { format } = useCurrency();
const page = usePage();
const erpCompanyContext = () => page.props.erpCompanyContext ?? null;
const period = ref(props.period || 'daily');
const companyId = ref(props.filters?.company_id ?? erpCompanyContext()?.current_company_id ?? '');
const q = ref(props.filters?.q ?? '');
const perPage = ref(Number(props.filters?.per_page ?? props.rows?.per_page ?? 25));
const rowItems = () => props.rows?.data ?? [];

let timer;
watch([period, companyId, q, perPage], ([periodValue, companyValue, queryValue, perPageValue]) => {
  clearTimeout(timer);
  timer = setTimeout(() => {
  router.get(route('erp.accounting.reconciliation'), {
    period: periodValue,
    company_id: companyValue || undefined,
    q: queryValue || undefined,
    per_page: perPageValue,
  }, { preserveState: true, replace: true });
  }, 250);
});
</script>

<template>
  <Head title="Accounting - Rekonsiliasi" />
  <AppLayout>
    <div class="space-y-5">
      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Accounting Workspace</p>
              <h1 class="ocn-panel__title mt-1">Rekonsiliasi Kas</h1>
              <p class="ocn-panel__desc mt-1">Ringkasan mutasi kas per akun sumber dana untuk kontrol harian/mingguan.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
              <Link class="btn btn-ghost btn-sm shrink-0 gap-1.5" :href="route('erp.accounting')">
              <ArrowLeftIcon class="h-4 w-4" />
              Back
            </Link>
            </div>
          </div>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Periode rekonsiliasi</h2>
        </div>
        <div class="card-body">
          <div class="flex flex-wrap items-center gap-3">
            <select v-if="erpCompanyContext()?.companies?.length" v-model="companyId" class="select select-bordered select-sm w-full max-w-xs">
              <option value="all">Semua Usaha</option>
              <option v-for="c in erpCompanyContext().companies" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
            <div class="join">
              <button class="btn btn-sm join-item" :class="period === 'daily' ? 'btn-primary' : 'btn-outline'" @click="period = 'daily'">Harian</button>
              <button class="btn btn-sm join-item" :class="period === 'weekly' ? 'btn-primary' : 'btn-outline'" @click="period = 'weekly'">Mingguan</button>
            </div>
            <input v-model="q" type="search" class="input input-bordered input-sm w-full max-w-sm" placeholder="Cari periode / akun kas..." />
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
              <tr v-for="row in rowItems()" :key="`${row.bucket}-${row.cash_account_id}`">
                <td>{{ row.bucket }}</td>
                <td class="font-medium">{{ row.cash_account_name }}</td>
                <td class="font-semibold text-success">{{ format(row.cash_in) }}</td>
                <td class="font-semibold text-error">{{ format(row.cash_out) }}</td>
                <td :class="['font-semibold', row.net >= 0 ? 'text-primary' : 'text-error']">{{ format(row.net) }}</td>
              </tr>
              <tr v-if="!rowItems().length">
                <td colspan="5" class="py-10 text-center text-base-content/50">Belum ada data rekonsiliasi.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <DataTablePagination :paginator="rows" @update:per-page="(n) => { perPage = n; }" />
      </div>
    </div>
  </AppLayout>
</template>
