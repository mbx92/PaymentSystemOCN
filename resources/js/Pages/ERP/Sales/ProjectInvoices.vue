<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useCurrency } from '@/composables/useCurrency';

defineProps({
  invoices: Array,
});

const { format } = useCurrency();
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
        <p class="mt-2 text-sm text-base-content/70">Kelola invoice untuk project software, CCTV, dan jaringan.</p>
      </div>

      <div class="card bg-base-100 shadow">
        <div class="overflow-x-auto">
          <table class="table table-zebra">
            <thead>
              <tr>
                <th>No Invoice</th>
                <th>Project</th>
                <th>Client</th>
                <th>Nilai</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="invoice in invoices" :key="invoice.number">
                <td class="font-mono text-xs">{{ invoice.number }}</td>
                <td class="font-semibold">{{ invoice.project }}</td>
                <td>{{ invoice.client }}</td>
                <td>{{ format(invoice.amount) }}</td>
                <td><StatusBadge :status="invoice.status" /></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
