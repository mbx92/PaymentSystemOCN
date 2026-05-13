<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTablePagination from '@/Components/DataTablePagination.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive, watch } from 'vue';

const props = defineProps({
  movements: Object,
  filters: Object,
  warehouses: Array,
  products: Array,
  types: Array,
});

const filters = reactive({
  warehouse_id: props.filters?.warehouse_id ?? '',
  product_id: props.filters?.product_id ?? '',
  type: props.filters?.type ?? '',
  from: props.filters?.from ?? '',
  to: props.filters?.to ?? '',
  q: props.filters?.q ?? '',
  per_page: props.filters?.per_page ?? props.movements?.per_page ?? 25,
});

let timer;
watch(
  filters,
  (val) => {
    clearTimeout(timer);
    timer = setTimeout(() => {
      router.get(route('erp.inventory.stock-movements'), val, { preserveState: true, replace: true });
    }, 250);
  },
  { deep: true },
);
</script>

<template>
  <Head title="Inventory - Stock Movement" />
  <AppLayout>
    <div class="space-y-5">
      <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Inventory Workspace</p>
        <div class="mt-2 flex items-center justify-between gap-3">
          <h1 class="text-3xl font-bold tracking-tight">Stock Movement</h1>
          <Link class="btn btn-ghost btn-sm" :href="route('erp.inventory')">Back</Link>
        </div>
        <p class="mt-2 text-sm text-base-content/70">Lihat histori pergerakan stok per produk dan per warehouse.</p>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Filter pergerakan stok</h2>
        </div>
        <div class="card-body">
          <div class="flex flex-wrap items-end gap-3">
            <div class="min-w-[220px]">
              <label class="label"><span class="label-text text-xs uppercase tracking-wide">Warehouse</span></label>
              <select v-model="filters.warehouse_id" class="select select-sm select-bordered w-full">
                <option value="">Semua</option>
                <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.code }} - {{ w.name }}</option>
              </select>
            </div>
            <div class="min-w-[260px] flex-1">
              <label class="label"><span class="label-text text-xs uppercase tracking-wide">Produk</span></label>
              <select v-model="filters.product_id" class="select select-sm select-bordered w-full">
                <option value="">Semua</option>
                <option v-for="p in products" :key="p.id" :value="p.id">{{ p.sku }} - {{ p.name }}</option>
              </select>
            </div>
            <div class="min-w-[200px]">
              <label class="label"><span class="label-text text-xs uppercase tracking-wide">Tipe</span></label>
              <select v-model="filters.type" class="select select-sm select-bordered w-full">
                <option value="">Semua</option>
                <option v-for="t in types" :key="t" :value="t">{{ t }}</option>
              </select>
            </div>
            <div class="min-w-[150px]">
              <label class="label"><span class="label-text text-xs uppercase tracking-wide">From</span></label>
              <input v-model="filters.from" type="date" class="input input-sm input-bordered w-full" />
            </div>
            <div class="min-w-[150px]">
              <label class="label"><span class="label-text text-xs uppercase tracking-wide">To</span></label>
              <input v-model="filters.to" type="date" class="input input-sm input-bordered w-full" />
            </div>
            <div class="min-w-[220px]">
              <label class="label"><span class="label-text text-xs uppercase tracking-wide">Search</span></label>
              <input v-model="filters.q" type="text" class="input input-sm input-bordered w-full" placeholder="SKU / produk / note" />
            </div>
          </div>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Riwayat stock movement</h2>
        </div>
        <div class="overflow-x-auto">
          <table class="table table-zebra">
            <thead>
              <tr>
                <th>Tanggal</th>
                <th>Tipe</th>
                <th>SKU</th>
                <th>Produk</th>
                <th>Warehouse</th>
                <th class="text-right">Qty</th>
                <th>Note</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="m in movements.data" :key="m.id">
                <td class="font-mono text-xs">{{ m.date }}</td>
                <td><span class="badge badge-sm badge-ghost">{{ m.type }}</span></td>
                <td class="font-mono text-xs">{{ m.sku }}</td>
                <td class="font-semibold">{{ m.product }}</td>
                <td>{{ m.warehouse }}</td>
                <td class="text-right font-mono text-xs">{{ m.qty }}</td>
                <td class="text-sm text-base-content/70">{{ m.note }}</td>
              </tr>
              <tr v-if="!movements.data?.length">
                <td colspan="7" class="text-center text-sm text-base-content/60 py-8">Tidak ada data.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <DataTablePagination :paginator="movements" @update:per-page="(n) => { filters.per_page = n; }" />
      </div>
    </div>
  </AppLayout>
</template>

