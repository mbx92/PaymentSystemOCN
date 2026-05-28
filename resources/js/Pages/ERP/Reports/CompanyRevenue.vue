<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { ref, watch } from 'vue';
import { useCurrency } from '@/composables/useCurrency';

const props = defineProps({
  selected_year: Number,
  filters: Object,
  totals: Object,
  rows: Array,
  source_pivot: Array,
  account_breakdown: Array,
  sourceOptions: Array,
});

const { format } = useCurrency();
const page = usePage();
const erpCompanyContext = () => page.props.erpCompanyContext ?? null;
const yearRange = (year) => ({
  from: `${year}-01-01`,
  to: `${year}-12-31`,
});

const selectedYear = ref(props.selected_year ?? new Date().getFullYear());
const companyId = ref(props.filters?.company_id ?? 'all');
const dateFrom = ref(props.filters?.date_from ?? yearRange(selectedYear.value).from);
const dateTo = ref(props.filters?.date_to ?? yearRange(selectedYear.value).to);
const source = ref(props.filters?.source ?? '');
const syncingYearRange = ref(false);
const yearOptions = Array.from({ length: 5 }, (_, idx) => new Date().getFullYear() - idx);

const reload = () => {
  router.get(route('reports.company-revenue'), {
    year: selectedYear.value,
    company_id: companyId.value || undefined,
    date_from: dateFrom.value || undefined,
    date_to: dateTo.value || undefined,
    source: source.value || undefined,
  }, { preserveState: true, replace: true });
};

const resetFilters = () => {
  syncingYearRange.value = true;
  selectedYear.value = new Date().getFullYear();
  companyId.value = 'all';
  const range = yearRange(selectedYear.value);
  dateFrom.value = range.from;
  dateTo.value = range.to;
  source.value = '';
  syncingYearRange.value = false;
  reload();
};

watch(selectedYear, (yearValue) => {
  syncingYearRange.value = true;
  const range = yearRange(yearValue);
  dateFrom.value = range.from;
  dateTo.value = range.to;
  syncingYearRange.value = false;
  reload();
});

watch([companyId, source], reload);

watch([dateFrom, dateTo], () => {
  if (syncingYearRange.value) {
    return;
  }

  reload();
});
</script>

<template>
  <Head title="Revenue per Usaha" />
  <AppLayout>
    <div class="space-y-5">
      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Laporan Akuntansi</p>
              <h1 class="ocn-panel__title mt-1">Revenue per Usaha</h1>
              <p class="ocn-panel__desc mt-1">Pendapatan tiap usaha berdasarkan jurnal revenue yang sudah diposting ke General Ledger.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
              <button type="button" class="btn btn-outline btn-sm" @click="resetFilters">Reset Filter</button>
              <Link class="btn btn-ghost btn-sm shrink-0 gap-1.5" :href="route('erp.reporting')">
                <ArrowLeftIcon class="h-4 w-4" />
                Back
              </Link>
            </div>
          </div>
        </div>
      </div>

      <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
        <div class="ocn-panel">
          <div class="ocn-panel__head py-3"><h2 class="ocn-panel__title text-sm font-medium">Total revenue</h2></div>
          <div class="card-body py-4"><p class="text-xl font-bold text-success">{{ format(totals?.revenue ?? 0) }}</p></div>
        </div>
        <div class="ocn-panel">
          <div class="ocn-panel__head py-3"><h2 class="ocn-panel__title text-sm font-medium">Jumlah usaha</h2></div>
          <div class="card-body py-4"><p class="text-xl font-bold">{{ totals?.company_count ?? 0 }}</p></div>
        </div>
        <div class="ocn-panel">
          <div class="ocn-panel__head py-3"><h2 class="ocn-panel__title text-sm font-medium">Jurnal revenue</h2></div>
          <div class="card-body py-4"><p class="text-xl font-bold">{{ totals?.entry_count ?? 0 }}</p></div>
        </div>
        <div class="ocn-panel">
          <div class="ocn-panel__head py-3"><h2 class="ocn-panel__title text-sm font-medium">Akun revenue</h2></div>
          <div class="card-body py-4"><p class="text-xl font-bold">{{ totals?.account_count ?? 0 }}</p></div>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Filter</h2>
        </div>
        <div class="card-body">
          <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
            <select v-model.number="selectedYear" class="select select-bordered select-sm w-full">
              <option v-for="year in yearOptions" :key="year" :value="year">{{ year }}</option>
            </select>
            <input v-model="dateFrom" type="date" class="input input-bordered input-sm w-full" />
            <input v-model="dateTo" type="date" class="input input-bordered input-sm w-full" />
            <select v-if="erpCompanyContext()?.companies?.length" v-model="companyId" class="select select-bordered select-sm w-full">
              <option value="all">Semua Usaha</option>
              <option v-for="company in erpCompanyContext().companies" :key="company.id" :value="company.id">{{ company.name }}</option>
            </select>
            <select v-model="source" class="select select-bordered select-sm w-full">
              <option v-for="opt in sourceOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
          </div>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Revenue per usaha</h2>
        </div>
        <div class="overflow-x-auto">
          <table class="table">
            <thead>
              <tr>
                <th>Usaha</th>
                <th class="text-right">Jurnal</th>
                <th class="text-right">Akun</th>
                <th class="text-right">Revenue</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in rows ?? []" :key="row.company_id ?? row.company_name">
                <td class="font-medium">{{ row.company_name }}</td>
                <td class="text-right">{{ row.entry_count }}</td>
                <td class="text-right">{{ row.account_count }}</td>
                <td class="text-right font-semibold text-success">{{ format(row.revenue_total) }}</td>
              </tr>
              <tr v-if="!((rows ?? []).length)">
                <td colspan="4" class="py-10 text-center text-base-content/50">Belum ada revenue pada periode ini.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="grid gap-5 xl:grid-cols-2">
        <div class="ocn-panel">
          <div class="ocn-panel__head">
            <h2 class="ocn-panel__title">Pivot per sumber</h2>
          </div>
          <div class="overflow-x-auto">
            <table class="table table-sm">
              <thead>
                <tr>
                  <th>Usaha</th>
                  <th>Sumber</th>
                  <th class="text-right">Jurnal</th>
                  <th class="text-right">Revenue</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in source_pivot ?? []" :key="`${row.company_name}-${row.source_label}`">
                  <td>{{ row.company_name }}</td>
                  <td>{{ row.source_label }}</td>
                  <td class="text-right">{{ row.entry_count }}</td>
                  <td class="text-right font-semibold text-success">{{ format(row.revenue_total) }}</td>
                </tr>
                <tr v-if="!((source_pivot ?? []).length)">
                  <td colspan="4" class="py-8 text-center text-base-content/50">Belum ada data sumber.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="ocn-panel">
          <div class="ocn-panel__head">
            <h2 class="ocn-panel__title">Breakdown akun revenue</h2>
          </div>
          <div class="overflow-x-auto">
            <table class="table table-sm">
              <thead>
                <tr>
                  <th>Usaha</th>
                  <th>Akun</th>
                  <th class="text-right">Revenue</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in account_breakdown ?? []" :key="`${row.company_name}-${row.account_code}`">
                  <td>{{ row.company_name }}</td>
                  <td>
                    <div class="font-mono text-xs">{{ row.account_code }}</div>
                    <div>{{ row.account_name }}</div>
                  </td>
                  <td class="text-right font-semibold text-success">{{ format(row.revenue_total) }}</td>
                </tr>
                <tr v-if="!((account_breakdown ?? []).length)">
                  <td colspan="3" class="py-8 text-center text-base-content/50">Belum ada breakdown akun.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
