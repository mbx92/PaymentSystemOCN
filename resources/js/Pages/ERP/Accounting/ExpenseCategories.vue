<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTablePagination from '@/Components/DataTablePagination.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps({
  categories: Object,
  accounts: Array,
  filters: Object,
});

const categoryForm = useForm({
  key: '',
  label: '',
  is_active: true,
});

const saving = useForm({
  category: '',
  account_id: '',
});

const saveMapping = (category, accountId) => {
  saving.category = category;
  saving.account_id = accountId || '';
  saving.post(route('erp.accounting.expense-categories.upsert'), { preserveScroll: true });
};

const submitCategory = () => {
  categoryForm.post(route('erp.accounting.expense-categories.store'), {
    preserveScroll: true,
    onSuccess: () => {
      categoryForm.reset();
      categoryForm.is_active = true;
      document.getElementById('modal-add-expense-category')?.close();
    },
  });
};

const openCategoryModal = () => {
  categoryForm.reset();
  categoryForm.is_active = true;
  document.getElementById('modal-add-expense-category')?.showModal();
};
</script>

<template>
  <Head title="Accounting - Kategori Pengeluaran" />
  <AppLayout>
    <div class="space-y-5">
      <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Accounting Workspace</p>
        <div class="mt-2 flex items-center justify-between gap-3">
          <h1 class="text-3xl font-bold tracking-tight">Kategori Pengeluaran</h1>
          <Link class="btn btn-ghost btn-sm" :href="route('erp.accounting')">Back</Link>
        </div>
        <p class="mt-2 text-sm text-base-content/70">
          Mapping kategori kas keluar ke akun CoA untuk memastikan jurnal accounting valid.
        </p>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head flex items-center justify-between gap-2">
          <div>
            <h2 class="ocn-panel__title">Mapping kategori → CoA</h2>
            <p class="ocn-panel__desc">Jika mapping kosong, sistem akan fallback ke akun default (sementara).</p>
          </div>
          <button class="btn btn-primary btn-sm shrink-0" @click="openCategoryModal">+ Tambah Kategori</button>
        </div>
        <div class="overflow-x-auto">
          <table class="table table-zebra">
            <thead>
              <tr>
                <th>Kategori</th>
                <th>Akun CoA</th>
                <th class="w-36"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in (categories?.data || [])" :key="row.category">
                <td class="font-medium">{{ row.label }}</td>
                <td>
                  <select
                    class="select select-bordered select-sm w-full max-w-xl"
                    :value="row.account_id || ''"
                    @change="saveMapping(row.category, $event.target.value)"
                  >
                    <option value="">-- (Kosong) gunakan default --</option>
                    <option v-for="acc in accounts" :key="acc.id" :value="acc.id">
                      {{ acc.code }} - {{ acc.name }} ({{ acc.type }})
                    </option>
                  </select>
                </td>
                <td class="text-right">
                  <span v-if="saving.processing && saving.category === row.category" class="text-xs text-base-content/60">Menyimpan…</span>
                </td>
              </tr>
              <tr v-if="!(categories?.data || []).length">
                <td colspan="3" class="py-10 text-center text-base-content/50">Tidak ada kategori.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <DataTablePagination
          :paginator="categories"
          @update:per-page="(n) => router.get(route('erp.accounting.expense-categories'), { per_page: n }, { preserveState: true, replace: true })"
        />
      </div>
    </div>

    <dialog id="modal-add-expense-category" class="modal">
      <div class="modal-box max-w-xl">
        <h3 class="font-bold text-lg">Tambah Kategori Pengeluaran</h3>
        <div class="mt-4 space-y-3">
          <div>
            <label class="label"><span class="label-text text-xs uppercase tracking-wide">Key</span></label>
            <input v-model="categoryForm.key" class="input input-bordered input-sm w-full" placeholder="contoh: listrik_kantor" />
            <p v-if="categoryForm.errors.key" class="text-error text-xs mt-1">{{ categoryForm.errors.key }}</p>
            <p class="text-xs text-base-content/60 mt-1">Huruf kecil, angka, underscore saja.</p>
          </div>
          <div>
            <label class="label"><span class="label-text text-xs uppercase tracking-wide">Label</span></label>
            <input v-model="categoryForm.label" class="input input-bordered input-sm w-full" placeholder="Contoh: Listrik Kantor" />
            <p v-if="categoryForm.errors.label" class="text-error text-xs mt-1">{{ categoryForm.errors.label }}</p>
          </div>
          <div>
            <label class="flex items-center gap-2 text-sm">
              <input v-model="categoryForm.is_active" type="checkbox" class="toggle toggle-sm toggle-primary" />
              <span>Aktif</span>
            </label>
          </div>
        </div>
        <div class="modal-action">
          <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
          <button class="btn btn-primary" :disabled="categoryForm.processing" @click="submitCategory">Simpan</button>
        </div>
      </div>
    </dialog>
  </AppLayout>
</template>

