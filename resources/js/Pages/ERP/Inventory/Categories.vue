<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import DataTablePagination from '@/Components/DataTablePagination.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps({
  categories: Object,
});

const form = useForm({
  name: '',
  description: '',
  status: 'active',
});

const submit = () => {
  form.status = form.status === 'active' ? 'active' : 'inactive';
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
      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Form tambah kategori</h2>
        </div>
        <div class="card-body grid gap-3 md:grid-cols-4">
          <input v-model="form.name" class="input input-bordered" placeholder="Nama kategori" />
          <input v-model="form.description" class="input input-bordered" placeholder="Deskripsi" />
          <label class="label cursor-pointer justify-start gap-3 rounded-lg border border-base-300 px-3">
            <input
              :checked="form.status === 'active'"
              type="checkbox"
              class="toggle toggle-success"
              @change="form.status = $event.target.checked ? 'active' : 'inactive'"
            />
            <span class="label-text">{{ form.status === 'active' ? 'Active' : 'Inactive' }}</span>
          </label>
          <button class="btn btn-primary" @click="submit">Tambah Kategori</button>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Daftar kategori</h2>
        </div>
        <div class="overflow-x-auto">
          <table class="table table-zebra">
            <thead><tr><th>Nama</th><th>Deskripsi</th><th>Status</th></tr></thead>
            <tbody>
              <tr v-for="category in (categories?.data || [])" :key="category.id">
                <td class="font-semibold">{{ category.name }}</td>
                <td>{{ category.description || '-' }}</td>
                <td><StatusBadge :status="category.status" /></td>
              </tr>
              <tr v-if="!(categories?.data || []).length">
                <td colspan="3" class="py-8 text-center text-base-content/50">Belum ada kategori.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <DataTablePagination
          :paginator="categories"
          @update:per-page="(n) => router.get(route('erp.inventory.categories'), { per_page: n }, { preserveState: true, replace: true })"
        />
      </div>
    </div>
  </AppLayout>
</template>
