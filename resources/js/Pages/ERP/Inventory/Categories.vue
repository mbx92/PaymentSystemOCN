<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
  categories: Array,
});

const form = useForm({
  name: '',
  description: '',
  status: 'active',
});

const submit = () => {
  form.post(route('erp.inventory.categories.store'), {
    preserveScroll: true,
    onSuccess: () => form.reset(),
  });
};
</script>

<template>
  <Head title="Inventory - Manajemen Kategori" />
  <AppLayout>
    <div class="space-y-5">
      <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Inventory Workspace</p>
        <div class="mt-2 flex items-center justify-between gap-3">
          <h1 class="text-3xl font-bold tracking-tight">Manajemen Kategori</h1>
          <Link class="btn btn-ghost btn-sm" :href="route('erp.inventory')">Back</Link>
        </div>
        <p class="mt-2 text-sm text-base-content/70">Atur daftar kategori standar untuk klasifikasi seluruh produk inventory.</p>
      </div>
      <div class="card bg-base-100 shadow">
        <div class="card-body grid gap-3 md:grid-cols-4">
          <input v-model="form.name" class="input input-bordered" placeholder="Nama kategori" />
          <input v-model="form.description" class="input input-bordered" placeholder="Deskripsi" />
          <select v-model="form.status" class="select select-bordered">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
          <button class="btn btn-primary" @click="submit">Tambah Kategori</button>
        </div>
      </div>

      <div class="card bg-base-100 shadow">
        <div class="overflow-x-auto">
          <table class="table table-zebra">
            <thead><tr><th>Nama</th><th>Deskripsi</th><th>Status</th></tr></thead>
            <tbody>
              <tr v-for="category in categories" :key="category.id">
                <td class="font-semibold">{{ category.name }}</td>
                <td>{{ category.description || '-' }}</td>
                <td><StatusBadge :status="category.status" /></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
