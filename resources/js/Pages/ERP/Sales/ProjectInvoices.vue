<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import DataTablePagination from '@/Components/DataTablePagination.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useCurrency } from '@/composables/useCurrency';

const props = defineProps({
  invoices: Object,
  filters: Object,
});

const { format } = useCurrency();

const openInvoice = (invoice) => {
  router.visit(route('erp.sales.project-invoices.show', invoice.id));
};
</script>

<template>
  <Head title="Sales - Invoice Project" />
  <AppLayout>
    <div class="space-y-6">
      <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Sales Workspace</p>
        <div class="mt-2 flex items-center justify-between gap-3">
          <h1 class="text-3xl font-bold tracking-tight">Invoice Project</h1>
          <Link class="btn btn-ghost btn-sm" :href="route('erp.sales')">Back</Link>
        </div>
        <p class="mt-2 text-sm text-base-content/70">Invoice otomatis dari project yang sudah selesai.</p>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Daftar invoice project</h2>
          <p class="ocn-panel__desc">Project selesai beserta status pembayaran invoice.</p>
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
                <td>{{ invoice.finished_at || '-' }}</td>
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
          @update:per-page="(n) => router.get(route('erp.sales.project-invoices'), { per_page: n }, { preserveState: true, replace: true })"
        />
      </div>
    </div>
  </AppLayout>
</template>
