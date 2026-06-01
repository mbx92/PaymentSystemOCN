<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import DataTablePagination from '@/Components/DataTablePagination.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { reactive, watch } from 'vue';
import { useCurrency } from '@/composables/useCurrency';
import { useDateFormat } from '@/composables/useDateFormat';

const props = defineProps({
  invoices: Object,
  filters: Object,
});

const { format } = useCurrency();
const { formatDate } = useDateFormat();
const filters = reactive({
  q: props.filters?.q ?? '',
  per_page: Number(props.filters?.per_page ?? props.invoices?.per_page ?? 25),
});

let timer;
watch(filters, (val) => {
  clearTimeout(timer);
  timer = setTimeout(() => {
    router.get(route('erp.sales.project-invoices'), val, { preserveState: true, replace: true });
  }, 250);
}, { deep: true });

const openInvoice = (invoice) => {
  router.visit(route('erp.sales.project-invoices.show', invoice.id));
};
</script>

<template>
  <Head title="Sales - Invoice Project" />
  <AppLayout>
    <div class="space-y-6">
      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Sales Workspace</p>
              <h1 class="ocn-panel__title mt-1">Invoice Project</h1>
              <p class="ocn-panel__desc mt-1">Invoice otomatis dari project yang sudah selesai.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
              <Link class="btn btn-ghost btn-sm shrink-0 gap-1.5" :href="route('erp.sales')">
              <ArrowLeftIcon class="h-4 w-4" />
              Back
            </Link>
            </div>
          </div>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Daftar invoice project</h2>
          <p class="ocn-panel__desc">Project selesai beserta status pembayaran invoice.</p>
        </div>
        <div class="card-body border-b border-base-200 pb-4">
          <label class="input input-bordered input-sm flex w-full items-center gap-2 sm:max-w-md">
            <input v-model="filters.q" type="search" class="grow" placeholder="Cari no invoice, project, atau client..." />
          </label>
        </div>
        <div class="overflow-x-auto">
          <table class="table table-zebra">
            <thead>
              <tr>
                <th>No Invoice</th>
                <th>Project</th>
                <th>Client</th>
                <th>Nilai</th>
                <th>Terbayar</th>
                <th>Sisa</th>
                <th>Selesai</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="invoice in (invoices?.data || [])"
                :key="invoice.number"
                class="cursor-pointer hover"
                tabindex="0"
                @click="openInvoice(invoice)"
                @keydown.enter.prevent="openInvoice(invoice)"
              >
                <td class="font-mono text-xs">{{ invoice.number }}</td>
                <td class="font-semibold">{{ invoice.project }}</td>
                <td>{{ invoice.client }}</td>
                <td>{{ format(invoice.amount) }}</td>
                <td>{{ format(invoice.paid_amount) }}</td>
                <td>{{ format(invoice.remaining_amount) }}</td>
                <td class="whitespace-nowrap">{{ formatDate(invoice.finished_at) }}</td>
                <td><StatusBadge :status="invoice.status" /></td>
              </tr>
              <tr v-if="!(invoices?.data || []).length">
                <td colspan="8" class="py-8 text-center text-base-content/50">Belum ada project selesai yang bisa dibuat invoice.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <DataTablePagination
          :paginator="invoices"
          @update:per-page="(n) => { filters.per_page = n; }"
        />
      </div>
    </div>
  </AppLayout>
</template>
