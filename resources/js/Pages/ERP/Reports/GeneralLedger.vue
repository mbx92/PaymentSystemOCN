<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTablePagination from '@/Components/DataTablePagination.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { ref, watch } from 'vue';
import { useCurrency } from '@/composables/useCurrency';
import { useDateFormat } from '@/composables/useDateFormat';

const props = defineProps({
  entries: Object,
  totals: Object,
  filters: Object,
  sourceOptions: Array,
  accountOptions: Array,
  pivot: Object,
});

const { format } = useCurrency();
const { formatDate } = useDateFormat();

const page = usePage();
const erpCompanyContext = () => page.props.erpCompanyContext ?? null;

const filters = ref({
  date_from: props.filters?.date_from ?? '',
  date_to: props.filters?.date_to ?? '',
  q: props.filters?.q ?? '',
  source: props.filters?.source ?? '',
  account_id: props.filters?.account_id ?? '',
  company_id: props.filters?.company_id ?? erpCompanyContext()?.current_company_id ?? '',
  per_page: props.filters?.per_page ?? props.entries?.per_page ?? 25,
});

let timer;
watch(filters, (val) => {
  clearTimeout(timer);
  timer = setTimeout(() => {
    router.get(route('reports.general-ledger'), val, { preserveState: true, replace: true });
  }, 400);
}, { deep: true });

</script>

<template>
  <Head title="General Ledger" />
  <AppLayout>
    <div class="space-y-5">
      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Laporan Akuntansi</p>
              <h1 class="ocn-panel__title mt-1">General Ledger</h1>
              <p class="ocn-panel__desc mt-1">Report buku besar dengan filter periode, sumber jurnal, akun, dan ringkasan pivot untuk review cepat.</p>
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
          <div class="ocn-panel__head py-3"><h2 class="ocn-panel__title text-sm font-medium">Total jurnal</h2></div>
          <div class="card-body py-4">
            <p class="text-xl font-bold text-primary">{{ totals?.entry_count ?? 0 }}</p>
            <p class="mt-1 text-xs text-base-content/60">{{ totals?.line_count ?? 0 }} baris jurnal</p>
          </div>
        </div>
        <div class="ocn-panel">
          <div class="ocn-panel__head py-3"><h2 class="ocn-panel__title text-sm font-medium">Total debit</h2></div>
          <div class="card-body py-4">
            <p class="text-xl font-bold tabular-nums">{{ format(totals?.total_debit ?? 0) }}</p>
          </div>
        </div>
        <div class="ocn-panel">
          <div class="ocn-panel__head py-3"><h2 class="ocn-panel__title text-sm font-medium">Total kredit</h2></div>
          <div class="card-body py-4">
            <p class="text-xl font-bold tabular-nums">{{ format(totals?.total_credit ?? 0) }}</p>
          </div>
        </div>
        <div class="ocn-panel">
          <div class="ocn-panel__head py-3"><h2 class="ocn-panel__title text-sm font-medium">Akun terpakai</h2></div>
          <div class="card-body py-4">
            <p class="text-xl font-bold tabular-nums">{{ totals?.account_count ?? 0 }}</p>
          </div>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Filter</h2>
        </div>
        <div class="card-body">
          <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
            <div v-if="erpCompanyContext()?.companies?.length" class="md:col-span-3 flex flex-col gap-1">
              <label class="text-xs font-medium uppercase tracking-wide text-base-content/60">Perusahaan</label>
              <select v-model="filters.company_id" class="select select-bordered select-sm w-full max-w-md">
                <option value="all">Semua Usaha</option>
                <option v-for="c in erpCompanyContext().companies" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>
            <input v-model="filters.date_from" type="date" class="input input-bordered input-sm w-full" />
            <input v-model="filters.date_to" type="date" class="input input-bordered input-sm w-full" />
            <select v-model="filters.source" class="select select-bordered select-sm w-full">
              <option v-for="opt in sourceOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
            <select v-model="filters.account_id" class="select select-bordered select-sm w-full">
              <option value="">Semua Akun</option>
              <option v-for="opt in accountOptions" :key="opt.value" :value="String(opt.value)">{{ opt.label }}</option>
            </select>
            <input v-model="filters.q" type="text" class="input input-bordered input-sm w-full" placeholder="Cari no. jurnal / deskripsi..." />
          </div>
        </div>
      </div>

      <div class="grid gap-5 xl:grid-cols-2">
        <div class="ocn-panel">
          <div class="ocn-panel__head">
            <h2 class="ocn-panel__title">Pivot per Sumber</h2>
          </div>
          <div class="overflow-x-auto">
            <table class="table table-sm">
              <thead>
                <tr>
                  <th>Sumber</th>
                  <th class="text-right">Jurnal</th>
                  <th class="text-right">Debit</th>
                  <th class="text-right">Kredit</th>
                  <th class="text-right">Selisih</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in (pivot?.sources || [])" :key="row.label">
                  <td class="font-medium">{{ row.label }}</td>
                  <td class="text-right">{{ row.entry_count }}</td>
                  <td class="text-right">{{ format(row.total_debit) }}</td>
                  <td class="text-right">{{ format(row.total_credit) }}</td>
                  <td class="text-right font-semibold" :class="row.balance >= 0 ? 'text-primary' : 'text-error'">{{ format(row.balance) }}</td>
                </tr>
                <tr v-if="!(pivot?.sources || []).length">
                  <td colspan="5" class="text-center py-8 text-base-content/50">Belum ada data sumber.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="ocn-panel">
          <div class="ocn-panel__head">
            <h2 class="ocn-panel__title">Pivot per Akun</h2>
          </div>
          <div class="overflow-x-auto">
            <table class="table table-sm">
              <thead>
                <tr>
                  <th>Akun</th>
                  <th class="text-right">Baris</th>
                  <th class="text-right">Debit</th>
                  <th class="text-right">Kredit</th>
                  <th class="text-right">Saldo</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in (pivot?.accounts || [])" :key="row.id">
                  <td>
                    <div class="font-medium">{{ row.name }}</div>
                    <div class="text-xs text-base-content/60 font-mono">{{ row.code }}</div>
                  </td>
                  <td class="text-right">{{ row.line_count }}</td>
                  <td class="text-right">{{ format(row.total_debit) }}</td>
                  <td class="text-right">{{ format(row.total_credit) }}</td>
                  <td class="text-right font-semibold" :class="row.balance >= 0 ? 'text-primary' : 'text-error'">{{ format(row.balance) }}</td>
                </tr>
                <tr v-if="!(pivot?.accounts || []).length">
                  <td colspan="5" class="text-center py-8 text-base-content/50">Belum ada data akun.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="space-y-4">
        <div
          v-for="entry in entries.data"
          :key="entry.id"
          class="ocn-panel"
        >
          <div class="ocn-panel__head flex items-center justify-between gap-3">
            <div>
              <h3 class="font-semibold">{{ entry.entry_no }}</h3>
              <p class="text-sm text-base-content/60 mt-0.5">{{ entry.description }}</p>
            </div>
            <div class="text-right">
              <div class="text-sm font-medium text-base-content/50 whitespace-nowrap">{{ formatDate(entry.entry_date) }}</div>
              <div class="text-xs text-base-content/40">{{ entry.source_module || '-' }}</div>
            </div>
          </div>
          <div class="overflow-x-auto">
            <table class="table">
              <thead>
                <tr>
                  <th>Kode</th>
                  <th>Akun</th>
                  <th>Deskripsi Line</th>
                  <th class="text-right">Debit</th>
                  <th class="text-right">Kredit</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="line in entry.lines" :key="line.id">
                  <td class="font-mono text-xs">{{ line.account?.code }}</td>
                  <td>{{ line.account?.name }}</td>
                  <td>{{ line.description || '-' }}</td>
                  <td class="text-right tabular-nums" :class="Number(line.debit) > 0 ? 'font-semibold' : 'text-base-content/30'">
                    {{ format(Number(line.debit)) }}
                  </td>
                  <td class="text-right tabular-nums" :class="Number(line.credit) > 0 ? 'font-semibold' : 'text-base-content/30'">
                    {{ format(Number(line.credit)) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div v-if="!entries.data?.length" class="ocn-panel">
          <div class="py-16 text-center">
            <svg class="mx-auto h-12 w-12 text-base-content/20" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
            </svg>
            <p class="mt-3 text-sm font-medium text-base-content/50">Belum ada jurnal</p>
            <p class="mt-1 text-xs text-base-content/40">Jurnal akan muncul setelah ada transaksi yang diposting.</p>
          </div>
        </div>
      </div>

      <DataTablePagination :paginator="entries" @update:per-page="(n) => { filters.per_page = n; }" />
    </div>
  </AppLayout>
</template>
