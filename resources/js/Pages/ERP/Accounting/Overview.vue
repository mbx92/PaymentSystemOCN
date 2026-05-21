<script setup>
import AccountingBalanceDoughnutChart from '@/Components/Charts/AccountingBalanceDoughnutChart.vue';
import AccountingTransactionCategoryChart from '@/Components/Charts/AccountingTransactionCategoryChart.vue';
import AccountingOverviewLineChart from '@/Components/Charts/AccountingOverviewLineChart.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useCurrency } from '@/composables/useCurrency';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { ref, watch } from 'vue';

const props = defineProps({
  selected_year: Number,
  filters: Object,
  stats: Object,
  monthly_data: Array,
  cash_balance_chart: Array,
  cash_accounts: Array,
  company_summaries: Array,
  transaction_breakdown: Object,
  transaction_highlights: Array,
});

const { format } = useCurrency();
const page = usePage();
const erpCompanyContext = () => page.props.erpCompanyContext ?? null;
const selectedYear = ref(props.selected_year ?? new Date().getFullYear());
const companyId = ref(props.filters?.company_id ?? erpCompanyContext()?.current_company_id ?? 'all');
const dateFrom = ref(props.filters?.date_from ?? '');
const dateTo = ref(props.filters?.date_to ?? '');

const yearOptions = Array.from({ length: 5 }, (_, idx) => new Date().getFullYear() - idx);

watch([selectedYear, companyId, dateFrom, dateTo], ([yearValue, companyValue, fromValue, toValue]) => {
  router.get(route('erp.accounting.overview'), {
    year: yearValue,
    company_id: companyValue || undefined,
    date_from: fromValue || undefined,
    date_to: toValue || undefined,
  }, { preserveState: true, replace: true });
});

const percentNetClass = (value) => (Number(value ?? 0) >= 0 ? 'text-primary' : 'text-error');
</script>

<template>
  <Head title="Accounting - Overview" />

  <AppLayout>
    <div class="space-y-5">
      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Accounting Workspace</p>
              <h1 class="ocn-panel__title mt-1">Overview</h1>
              <p class="ocn-panel__desc mt-1">
                Dashboard accounting khusus arus kas, saldo kas per akun, dan persebaran pemasukan serta pengeluaran berdasarkan COA.
              </p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
              <select v-model.number="selectedYear" class="select select-bordered select-sm w-full sm:w-auto">
                <option v-for="year in yearOptions" :key="year" :value="year">{{ year }}</option>
              </select>
              <input v-model="dateFrom" type="date" class="input input-bordered input-sm w-full sm:w-auto" />
              <input v-model="dateTo" type="date" class="input input-bordered input-sm w-full sm:w-auto" />
              <select v-if="erpCompanyContext()?.companies?.length" v-model="companyId" class="select select-bordered select-sm w-full sm:w-auto">
                <option value="all">Semua Usaha</option>
                <option v-for="company in erpCompanyContext().companies" :key="company.id" :value="company.id">{{ company.name }}</option>
              </select>
              <Link class="btn btn-ghost btn-sm shrink-0 gap-1.5" :href="route('erp.accounting')">
                <ArrowLeftIcon class="h-4 w-4" />
                Back
              </Link>
            </div>
          </div>
        </div>
      </div>

      <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-emerald-100 bg-white p-5 shadow-sm">
          <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">Kas masuk {{ selected_year }}</p>
          <p class="mt-2 text-2xl font-bold text-success">{{ format(stats?.cash_in_year) }}</p>
          <p class="mt-2 text-xs text-base-content/55">{{ stats?.company_count ?? 0 }} usaha terpantau</p>
        </div>
        <div class="rounded-2xl border border-rose-100 bg-white p-5 shadow-sm">
          <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">Kas keluar {{ selected_year }}</p>
          <p class="mt-2 text-2xl font-bold text-error">{{ format(stats?.cash_out_year) }}</p>
          <p class="mt-2 text-xs text-base-content/55">Kontrol biaya dari seluruh transaksi accounting</p>
        </div>
        <div class="rounded-2xl border border-sky-100 bg-white p-5 shadow-sm">
          <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">Net cashflow {{ selected_year }}</p>
          <p class="mt-2 text-2xl font-bold" :class="percentNetClass(stats?.net_year)">{{ format(stats?.net_year) }}</p>
          <p class="mt-2 text-xs text-base-content/55">Selisih pemasukan dan pengeluaran tahun berjalan</p>
        </div>
        <div class="rounded-2xl border border-amber-100 bg-white p-5 shadow-sm">
          <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">Saldo kas terpantau</p>
          <p class="mt-2 text-2xl font-bold text-primary">{{ format(stats?.cash_balance) }}</p>
          <p class="mt-2 text-xs text-base-content/55">{{ stats?.cash_account_count ?? 0 }} akun kas/bank aktif di dashboard</p>
        </div>
      </div>

      <div class="grid gap-4 xl:grid-cols-3">
        <div class="ocn-panel xl:col-span-2">
          <div class="ocn-panel__head">
            <h2 class="ocn-panel__title">Tren cashflow {{ selected_year }}</h2>
            <p class="ocn-panel__desc">Line chart untuk membaca ritme kas masuk, kas keluar, dan net cashflow per bulan.</p>
          </div>
          <div class="card-body">
            <AccountingOverviewLineChart :monthly-data="monthly_data ?? []" />
          </div>
        </div>

        <div class="ocn-panel">
          <div class="ocn-panel__head">
            <h2 class="ocn-panel__title">Saldo per akun kas/bank</h2>
            <p class="ocn-panel__desc">Donat chart ini memudahkan melihat akun mana yang paling dominan menampung saldo kas.</p>
          </div>
          <div class="card-body">
            <AccountingBalanceDoughnutChart :rows="cash_balance_chart ?? []" />
          </div>
        </div>
      </div>

      <div class="grid gap-4 xl:grid-cols-3">
        <div class="ocn-panel xl:col-span-2">
          <div class="ocn-panel__head">
            <h2 class="ocn-panel__title">Pemasukan vs pengeluaran per kategori transaksi</h2>
            <p class="ocn-panel__desc">Grouped horizontal bar membandingkan pemasukan dan pengeluaran berdasarkan kategori transaksi bisnis.</p>
          </div>
          <div class="card-body">
            <AccountingTransactionCategoryChart :data="transaction_breakdown ?? { labels: [], datasets: [] }" />
          </div>
        </div>

        <div class="ocn-panel">
          <div class="ocn-panel__head">
            <h2 class="ocn-panel__title">Kategori transaksi teratas</h2>
            <p class="ocn-panel__desc">Ringkasan cepat kategori yang paling besar kontribusinya dalam arus kas accounting.</p>
          </div>
          <div class="card-body space-y-3">
            <div
              v-for="row in transaction_highlights ?? []"
              :key="row.label"
              class="rounded-xl border border-slate-200 p-3"
            >
              <p class="text-sm font-semibold leading-tight">{{ row.label }}</p>
              <div class="mt-2 flex items-center justify-between gap-3 text-xs">
                <span class="text-success">In {{ format(row.income) }}</span>
                <span class="text-error">Out {{ format(row.expense) }}</span>
              </div>
            </div>
            <div v-if="!(transaction_highlights?.length)" class="rounded-xl border border-dashed border-slate-200 p-6 text-center text-sm text-base-content/50">
              Belum ada kategori transaksi yang cukup untuk divisualkan.
            </div>
          </div>
        </div>
      </div>

      <div class="grid gap-4 xl:grid-cols-2">
        <div class="ocn-panel">
          <div class="ocn-panel__head">
            <h2 class="ocn-panel__title">Total cash per usaha</h2>
            <p class="ocn-panel__desc">Rekap per entitas usaha untuk memantau usaha mana yang paling agresif menghasilkan cashflow.</p>
          </div>
          <div class="overflow-x-auto">
            <table class="table table-zebra">
              <thead>
                <tr>
                  <th>Usaha</th>
                  <th>Kas Masuk</th>
                  <th>Kas Keluar</th>
                  <th>Net</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in company_summaries ?? []" :key="row.company_id ?? row.company_name">
                  <td class="font-medium">{{ row.company_name }}</td>
                  <td class="text-success">{{ format(row.cash_in_year) }}</td>
                  <td class="text-error">{{ format(row.cash_out_year) }}</td>
                  <td :class="['font-semibold', percentNetClass(row.net_year)]">{{ format(row.net_year) }}</td>
                </tr>
                <tr v-if="!((company_summaries ?? []).length)">
                  <td colspan="4" class="py-10 text-center text-base-content/50">Belum ada data usaha yang bisa dirangkum.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="ocn-panel">
          <div class="ocn-panel__head">
            <h2 class="ocn-panel__title">Akun kas/bank teratas</h2>
            <p class="ocn-panel__desc">Pantau saldo, arus masuk, dan arus keluar per akun kas/bank yang dipakai di transaksi accounting.</p>
          </div>
          <div class="overflow-x-auto">
            <table class="table table-zebra">
              <thead>
                <tr>
                  <th>Akun</th>
                  <th>Masuk</th>
                  <th>Keluar</th>
                  <th>Saldo</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in cash_accounts ?? []" :key="row.account_id">
                  <td class="font-medium">{{ row.account_label }}</td>
                  <td class="text-success">{{ format(row.income) }}</td>
                  <td class="text-error">{{ format(row.expense) }}</td>
                  <td :class="['font-semibold', percentNetClass(row.balance)]">{{ format(row.balance) }}</td>
                </tr>
                <tr v-if="!((cash_accounts ?? []).length)">
                  <td colspan="4" class="py-10 text-center text-base-content/50">Belum ada akun kas/bank yang bisa dirangkum.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
