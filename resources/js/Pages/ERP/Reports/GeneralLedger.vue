<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { useCurrency } from '@/composables/useCurrency';

const props = defineProps({
  entries: Object,
  totals: Object,
  filters: Object,
});

const { format } = useCurrency();

const filters = ref({
  date_from: props.filters?.date_from ?? '',
  date_to: props.filters?.date_to ?? '',
  q: props.filters?.q ?? '',
});

let timer;
watch(filters, (val) => {
  clearTimeout(timer);
  timer = setTimeout(() => {
    router.get(route('reports.general-ledger'), val, { preserveState: true, replace: true });
  }, 400);
}, { deep: true });

const formatDate = (dateStr) => {
  if (!dateStr) return '-';
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
  const d = new Date(dateStr);
  return `${d.getDate()} ${months[d.getMonth()]} ${d.getFullYear()}`;
};
</script>

<template>
  <Head title="General Ledger" />
  <AppLayout>
    <div class="space-y-5">
      <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Laporan Akuntansi</p>
        <div class="mt-2 flex items-center justify-between gap-3">
          <h1 class="text-3xl font-bold tracking-tight">General Ledger</h1>
          <Link class="btn btn-ghost btn-sm" :href="route('erp.accounting')">Back</Link>
        </div>
        <p class="mt-2 text-sm text-base-content/70">Catatan jurnal umum seluruh transaksi yang diposting ke buku besar.</p>
      </div>

      <div class="grid gap-3 md:grid-cols-3">
        <div class="ocn-panel">
          <div class="ocn-panel__head py-3"><h2 class="ocn-panel__title text-sm font-medium">Total jurnal</h2></div>
          <div class="card-body py-4">
            <p class="text-xl font-bold text-primary">{{ totals?.entry_count ?? 0 }}</p>
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
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Filter</h2>
        </div>
        <div class="card-body">
          <div class="grid gap-3 md:grid-cols-3">
            <input v-model="filters.date_from" type="date" class="input input-bordered input-sm w-full" />
            <input v-model="filters.date_to" type="date" class="input input-bordered input-sm w-full" />
            <input v-model="filters.q" type="text" class="input input-bordered input-sm w-full" placeholder="Cari no. jurnal / deskripsi..." />
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
            <span class="text-sm font-medium text-base-content/50 whitespace-nowrap">{{ formatDate(entry.entry_date) }}</span>
          </div>
          <div class="overflow-x-auto">
            <table class="table">
              <thead>
                <tr>
                  <th>Kode</th>
                  <th>Akun</th>
                  <th class="text-right">Debit</th>
                  <th class="text-right">Kredit</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="line in entry.lines" :key="line.id">
                  <td class="font-mono text-xs">{{ line.account?.code }}</td>
                  <td>{{ line.account?.name }}</td>
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

      <div v-if="entries.last_page > 1" class="flex justify-center">
        <div class="join">
          <Link
            v-for="link in entries.links"
            :key="link.label"
            :href="link.url"
            class="join-item btn btn-sm"
            :class="{ 'btn-active': link.active, 'btn-disabled': !link.url }"
            v-html="link.label"
            preserve-state
          />
        </div>
      </div>
    </div>
  </AppLayout>
</template>
