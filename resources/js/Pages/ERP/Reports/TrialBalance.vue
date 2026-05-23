<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTablePagination from '@/Components/DataTablePagination.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { ref, watch } from 'vue';
import { useCurrency } from '@/composables/useCurrency';

const props = defineProps({
  balances: Object,
  totals: Object,
  filters: Object,
  sourceOptions: Array,
  typeOptions: Array,
  pivot: Object,
});

const { format } = useCurrency();
const page = usePage();
const erpCompanyContext = () => page.props.erpCompanyContext ?? null;

const filters = ref({
  source: props.filters?.source ?? '',
  type: props.filters?.type ?? '',
  date_from: props.filters?.date_from ?? '',
  date_to: props.filters?.date_to ?? '',
  q: props.filters?.q ?? '',
  company_id: props.filters?.company_id ?? erpCompanyContext()?.current_company_id ?? '',
  per_page: props.filters?.per_page ?? props.balances?.per_page ?? 25,
});

watch(filters, (val) => {
  router.get(route('reports.trial-balance'), {
    source: val.source,
    type: val.type,
    date_from: val.date_from || undefined,
    date_to: val.date_to || undefined,
    q: val.q || undefined,
    company_id: val.company_id || undefined,
    per_page: val.per_page,
  }, { preserveState: true, replace: true });
}, { deep: true });

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
      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Laporan Akuntansi</p>
              <h1 class="ocn-panel__title mt-1">Neraca Saldo</h1>
              <p class="ocn-panel__desc mt-1">Report neraca saldo dengan filter periode, sumber, tipe akun, dan ringkasan saldo per kelompok akun.</p>
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

      <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
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
        <div class="ocn-panel">
          <div class="ocn-panel__head py-3"><h2 class="ocn-panel__title text-sm font-medium">Akun aktif</h2></div>
          <div class="card-body py-4">
            <p class="text-xl font-bold tabular-nums">{{ totals?.account_count ?? 0 }}</p>
            <p class="mt-1 text-xs text-base-content/60">{{ totals?.line_count ?? 0 }} baris jurnal</p>
          </div>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Filter</h2>
        </div>
        <div class="card-body">
          <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
            <div v-if="erpCompanyContext()?.companies?.length" class="xl:col-span-2">
              <select v-model="filters.company_id" class="select select-bordered select-sm w-full">
                <option value="all">Semua Usaha</option>
                <option v-for="c in erpCompanyContext().companies" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>
            <input v-model="filters.date_from" type="date" class="input input-bordered input-sm w-full" />
            <input v-model="filters.date_to" type="date" class="input input-bordered input-sm w-full" />
            <select v-model="filters.source" class="select select-bordered select-sm w-full">
              <option v-for="opt in sourceOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
            <select v-model="filters.type" class="select select-bordered select-sm w-full">
              <option v-for="opt in typeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
            <input v-model="filters.q" type="text" class="input input-bordered input-sm w-full" placeholder="Cari kode akun / nama / jurnal..." />
          </div>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div>
            <h2 class="ocn-panel__title">Pivot per Tipe Akun</h2>
            <p class="ocn-panel__desc mt-1">Memudahkan review saldo berdasarkan kelompok akun utama.</p>
          </div>
        </div>
        <div class="overflow-x-auto">
          <table class="table table-sm">
            <thead>
              <tr>
                <th>Tipe Akun</th>
                <th class="text-right">Jumlah Akun</th>
                <th class="text-right">Debit</th>
                <th class="text-right">Kredit</th>
                <th class="text-right">Saldo</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in (pivot?.types || [])" :key="row.type">
                <td>
                  <span class="badge badge-sm" :class="typeBadgeClass(row.type)">{{ typeLabel(row.type) }}</span>
                </td>
                <td class="text-right">{{ row.account_count }}</td>
                <td class="text-right">{{ format(row.total_debit) }}</td>
                <td class="text-right">{{ format(row.total_credit) }}</td>
                <td class="text-right font-semibold" :class="row.balance >= 0 ? 'text-primary' : 'text-error'">{{ format(row.balance) }}</td>
              </tr>
              <tr v-if="!(pivot?.types || []).length">
                <td colspan="5" class="text-center py-8 text-base-content/50">Belum ada data pivot.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head flex items-center justify-between gap-2">
          <div>
            <h2 class="ocn-panel__title">Daftar akun</h2>
            <p class="ocn-panel__desc mt-1">Gunakan filter di atas untuk menyaring saldo per periode, sumber, dan tipe akun.</p>
          </div>
          <span class="text-xs text-base-content/60">{{ balances?.total ?? 0 }} akun</span>
        </div>
        <div class="overflow-x-auto">
          <table class="table">
            <thead class="sticky top-0 z-10">
              <tr>
                <th>Kode</th>
                <th>Nama Akun</th>
                <th>Tipe</th>
                <th class="text-right">Baris</th>
                <th class="text-right">Debit</th>
                <th class="text-right">Kredit</th>
                <th class="text-right">Saldo</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in (balances?.data || [])"
                :key="row.code"
                class="transition-colors hover:bg-primary/5"
              >
                <td class="font-mono text-xs">{{ row.code }}</td>
                <td class="font-medium">{{ row.name }}</td>
                <td>
                  <span class="badge badge-sm" :class="typeBadgeClass(row.type)">{{ typeLabel(row.type) }}</span>
                </td>
                <td class="text-right">{{ row.line_count }}</td>
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
              <tr v-if="!(balances?.data || []).length">
                <td colspan="7" class="py-16 text-center">
                  <svg class="mx-auto h-12 w-12 text-base-content/20" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                  </svg>
                  <p class="mt-3 text-sm font-medium text-base-content/50">Belum ada data</p>
                  <p class="mt-1 text-xs text-base-content/40">Neraca saldo akan terisi setelah ada jurnal yang diposting.</p>
                </td>
              </tr>
            </tbody>
            <tfoot v-if="(balances?.data || []).length">
              <tr class="border-t-2 border-base-content/20 font-bold">
                <td colspan="4" class="text-right">Total</td>
                <td class="text-right tabular-nums">{{ format(totals?.debit ?? 0) }}</td>
                <td class="text-right tabular-nums">{{ format(totals?.credit ?? 0) }}</td>
                <td class="text-right tabular-nums" :class="totals?.balanced ? 'text-success' : 'text-error'">
                  {{ format(Math.abs((totals?.debit ?? 0) - (totals?.credit ?? 0))) }}
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
        <DataTablePagination
          :paginator="balances"
          @update:per-page="(n) => { filters.per_page = n; }"
        />
      </div>
    </div>
  </AppLayout>
</template>
