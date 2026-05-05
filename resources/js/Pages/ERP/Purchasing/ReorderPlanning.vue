<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive, watch } from 'vue';

const props = defineProps({
  reorderSuggestions: Array,
  filters: Object,
});

const openRow = (id) => {
  router.visit(route('erp.purchasing.reorder-planning.show', id));
};

const rowClass = () => 'cursor-pointer transition-colors hover:bg-primary/5';

const filters = reactive({
  q: props.filters?.q ?? '',
});

let timer;
watch(
  filters,
  (val) => {
    clearTimeout(timer);
    timer = setTimeout(() => {
      router.get(route('erp.purchasing.reorder-planning'), val, { preserveState: true, replace: true });
    }, 250);
  },
  { deep: true },
);
</script>

<template>
  <Head title="Purchasing - Perencanaan Reorder" />
  <AppLayout>
    <div class="space-y-5">
      <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Purchasing Workspace</p>
        <div class="mt-2 flex items-center justify-between gap-3">
          <h1 class="text-3xl font-bold tracking-tight">Perencanaan Reorder</h1>
          <Link class="btn btn-ghost btn-sm" :href="route('erp.purchasing')">Back</Link>
        </div>
        <p class="mt-2 text-sm text-base-content/70">
          Klik baris untuk detail angka reorder, master produk, dan alur PO. Saran dari min stock, lead time, dan penjualan
          30 hari.
        </p>
      </div>

      <div class="card bg-base-100 shadow">
        <div class="card-body">
          <div class="flex flex-wrap items-end gap-3">
            <div class="min-w-[260px] flex-1">
              <label class="label"><span class="label-text text-xs uppercase tracking-wide">Search</span></label>
              <input v-model="filters.q" type="text" class="input input-sm input-bordered w-full" placeholder="SKU / nama produk" />
            </div>
            <Link class="btn btn-primary btn-sm ml-auto" :href="route('erp.purchasing.purchase-orders')">+ Add PO</Link>
          </div>
        </div>
      </div>

      <div class="card bg-base-100 shadow">
        <div class="overflow-x-auto">
          <table class="table table-zebra">
            <thead>
              <tr>
                <th>SKU</th>
                <th>Produk</th>
                <th>Stok</th>
                <th>Min</th>
                <th>Lead (hari)</th>
                <th>Saran Qty</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in reorderSuggestions"
                :key="row.id"
                :class="rowClass()"
                tabindex="0"
                role="button"
                @click="openRow(row.id)"
                @keydown.enter.prevent="openRow(row.id)"
              >
                <td class="font-mono text-xs">{{ row.sku }}</td>
                <td class="font-medium">{{ row.name }}</td>
                <td>{{ row.stock }}</td>
                <td>{{ row.min_stock }}</td>
                <td>{{ row.lead_time_days }}</td>
                <td @click.stop>
                  <span class="badge badge-primary badge-lg font-mono">{{ row.suggested_qty }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <p v-if="!reorderSuggestions?.length" class="rounded-2xl border border-slate-200 bg-white p-6 text-center text-sm text-base-content/60 shadow-sm">
        Tidak ada saran reorder saat ini (stok di atas target).
      </p>
    </div>
  </AppLayout>
</template>
