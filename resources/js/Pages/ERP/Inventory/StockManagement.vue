<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({
  products: Array,
});

const forms = reactive({});

const getForm = (product) => {
  if (!forms[product.id]) {
    forms[product.id] = useForm({
      min_stock: product.min_stock,
      note: '',
    });
  }
  return forms[product.id];
};

const saveRow = (product) => {
  const form = getForm(product);
  form.put(route('erp.inventory.stock-management.update', product.id), {
    preserveScroll: true,
  });
};
</script>

<template>
  <Head title="Inventory - Manajemen Stok" />
  <AppLayout>
    <div class="space-y-5">
      <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Inventory Workspace</p>
        <div class="mt-2 flex items-center justify-between gap-3">
          <h1 class="text-3xl font-bold tracking-tight">Manajemen Stok</h1>
          <Link class="btn btn-ghost btn-sm" :href="route('erp.inventory')">Back</Link>
        </div>
        <p class="mt-2 text-sm text-base-content/70">Atur parameter minimum stok. Stok aktual dan total terjual dikontrol dari transaksi nyata.</p>
      </div>

      <div class="card bg-base-100 shadow">
        <div class="overflow-x-auto">
          <table class="table table-zebra">
            <thead>
              <tr>
                <th>SKU</th>
                <th>Produk</th>
                <th>Stok</th>
                <th>Min Stok</th>
                <th>Total Terjual</th>
                <th>Catatan</th>
                <th>Status</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="product in products" :key="product.id">
                <td class="font-mono text-xs">{{ product.sku }}</td>
                <td class="font-semibold">{{ product.name }}</td>
                <td><span class="badge badge-sm badge-ghost">{{ product.stock }}</span></td>
                <td><input v-model.number="getForm(product).min_stock" type="number" min="0" class="input input-bordered input-sm w-24" /></td>
                <td><span class="badge badge-sm badge-ghost">{{ product.total_sold }}</span></td>
                <td><input v-model="getForm(product).note" type="text" class="input input-bordered input-sm w-40" placeholder="Opsional" /></td>
                <td><StatusBadge :status="product.status" /></td>
                <td><button class="btn btn-primary btn-xs" :disabled="getForm(product).processing" @click="saveRow(product)">Simpan</button></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
