<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTablePagination from '@/Components/DataTablePagination.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeftIcon, PencilSquareIcon } from '@heroicons/vue/24/outline';
import { reactive, ref, watch } from 'vue';

const props = defineProps({
  companies: Object,
  filters: Object,
});

const listFilters = reactive({
  q: props.filters?.q ?? '',
});

let searchTimer;
watch(listFilters, (val) => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    router.get(route('erp.admin.companies'), { q: val.q, per_page: props.companies?.per_page ?? 25 }, { preserveState: true, replace: true });
  }, 350);
}, { deep: true });

const addForm = useForm({
  name: '',
  legal_name: '',
  tax_id: '',
  email: '',
  phone: '',
  address: '',
});

const openAddModal = () => {
  addForm.clearErrors();
  addForm.reset();
  document.getElementById('modal-add-company')?.showModal();
};

const submitAdd = () => {
  addForm.post(route('erp.admin.companies.store'), {
    preserveScroll: true,
    onSuccess: () => document.getElementById('modal-add-company')?.close(),
  });
};

const editing = ref(null);
const editForm = useForm({
  name: '',
  legal_name: '',
  tax_id: '',
  email: '',
  phone: '',
  address: '',
  is_active: true,
});

const openEdit = (row) => {
  editing.value = row;
  editForm.name = row.name;
  editForm.legal_name = row.legal_name || '';
  editForm.tax_id = row.tax_id || '';
  editForm.email = row.email || '';
  editForm.phone = row.phone || '';
  editForm.address = row.address || '';
  editForm.is_active = row.is_active;
  editForm.clearErrors();
  document.getElementById('modal-edit-company')?.showModal();
};

const submitEdit = () => {
  if (!editing.value) return;
  editForm.patch(route('erp.admin.companies.update', editing.value.id), {
    preserveScroll: true,
    onSuccess: () => document.getElementById('modal-edit-company')?.close(),
  });
};

const toggleActive = (row) => {
  router.patch(route('erp.admin.companies.update', row.id), {
    name: row.name,
    legal_name: row.legal_name || '',
    tax_id: row.tax_id || '',
    email: row.email || '',
    phone: row.phone || '',
    address: row.address || '',
    is_active: !row.is_active,
  }, { preserveScroll: true });
};
</script>

<template>
  <Head title="Administration - Perusahaan" />
  <AppLayout>
    <div class="space-y-5">
      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Administration Workspace</p>
              <h1 class="ocn-panel__title mt-1">Master perusahaan</h1>
              <p class="ocn-panel__desc mt-1">Entitas usaha untuk pemisahan pembukuan, saldo awal, dan konteks perusahaan aktif di ERP.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
              <button type="button" class="btn btn-primary btn-sm" @click="openAddModal">+ Tambah perusahaan</button>
              <Link class="btn btn-ghost btn-sm shrink-0 gap-1.5" :href="route('erp.administration')">
                <ArrowLeftIcon class="h-4 w-4" />
                Back
              </Link>
            </div>
          </div>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Daftar perusahaan</h2>
        </div>
        <div class="card-body space-y-4">
          <input
            v-model="listFilters.q"
            type="search"
            class="input input-bordered input-sm w-full max-w-md"
            placeholder="Cari nama, nama legal, NPWP…"
          >
          <div class="overflow-x-auto">
            <table class="table table-zebra">
              <thead>
                <tr>
                  <th>Nama usaha</th>
                  <th>Nama legal</th>
                  <th>NPWP</th>
                  <th>Kontak</th>
                  <th class="text-center">Status</th>
                  <th class="w-28 text-right">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in (companies?.data || [])" :key="row.id">
                  <td class="font-medium">{{ row.name }}</td>
                  <td class="text-sm text-base-content/80">{{ row.legal_name || '—' }}</td>
                  <td class="font-mono text-xs">{{ row.tax_id || '—' }}</td>
                  <td class="text-xs">
                    <span v-if="row.email">{{ row.email }}</span>
                    <span v-if="row.phone" class="block text-base-content/70">{{ row.phone }}</span>
                    <span v-if="!row.email && !row.phone">—</span>
                  </td>
                  <td class="text-center">
                    <button
                      type="button"
                      class="badge badge-sm cursor-pointer"
                      :class="row.is_active ? 'badge-success' : 'badge-ghost'"
                      :title="row.is_active ? 'Klik untuk menonaktifkan' : 'Klik untuk mengaktifkan'"
                      @click="toggleActive(row)"
                    >
                      {{ row.is_active ? 'Aktif' : 'Nonaktif' }}
                    </button>
                  </td>
                  <td class="text-right">
                    <button type="button" class="btn btn-ghost btn-xs gap-1" @click="openEdit(row)">
                      <PencilSquareIcon class="h-4 w-4" />
                      Ubah
                    </button>
                  </td>
                </tr>
                <tr v-if="!(companies?.data || []).length">
                  <td colspan="6" class="py-8 text-center text-sm text-base-content/60">Belum ada data perusahaan.</td>
                </tr>
              </tbody>
            </table>
          </div>
          <DataTablePagination
            :paginator="companies"
            @update:per-page="(n) => router.get(route('erp.admin.companies'), { q: listFilters.q, per_page: n }, { preserveState: true, replace: true })"
          />
        </div>
      </div>
    </div>

    <dialog id="modal-add-company" class="modal">
      <div class="modal-box max-w-lg">
        <h3 class="font-bold text-lg">Tambah perusahaan</h3>
        <p class="text-sm text-base-content/60 py-1">Nama usaha wajib; field lain membantu identitas legal dan kontak.</p>
        <div class="space-y-3 mt-4">
          <div>
            <label class="label"><span class="label-text">Nama usaha <span class="text-error">*</span></span></label>
            <input v-model="addForm.name" type="text" class="input input-bordered input-sm w-full" placeholder="Contoh: Toko Kemasan Jaya">
            <p v-if="addForm.errors.name" class="text-xs text-error mt-1">{{ addForm.errors.name }}</p>
          </div>
          <div>
            <label class="label"><span class="label-text">Nama legal</span></label>
            <input v-model="addForm.legal_name" type="text" class="input input-bordered input-sm w-full" placeholder="PT … (opsional)">
            <p v-if="addForm.errors.legal_name" class="text-xs text-error mt-1">{{ addForm.errors.legal_name }}</p>
          </div>
          <div>
            <label class="label"><span class="label-text">NPWP</span></label>
            <input v-model="addForm.tax_id" type="text" class="input input-bordered input-sm w-full">
            <p v-if="addForm.errors.tax_id" class="text-xs text-error mt-1">{{ addForm.errors.tax_id }}</p>
          </div>
          <div class="grid gap-3 sm:grid-cols-2">
            <div>
              <label class="label"><span class="label-text">Email</span></label>
              <input v-model="addForm.email" type="email" class="input input-bordered input-sm w-full">
              <p v-if="addForm.errors.email" class="text-xs text-error mt-1">{{ addForm.errors.email }}</p>
            </div>
            <div>
              <label class="label"><span class="label-text">Telepon</span></label>
              <input v-model="addForm.phone" type="text" class="input input-bordered input-sm w-full">
              <p v-if="addForm.errors.phone" class="text-xs text-error mt-1">{{ addForm.errors.phone }}</p>
            </div>
          </div>
          <div>
            <label class="label"><span class="label-text">Alamat</span></label>
            <textarea v-model="addForm.address" class="textarea textarea-bordered textarea-sm w-full" rows="2" />
            <p v-if="addForm.errors.address" class="text-xs text-error mt-1">{{ addForm.errors.address }}</p>
          </div>
        </div>
        <div class="modal-action">
          <form method="dialog">
            <button class="btn btn-ghost btn-sm">Batal</button>
          </form>
          <button type="button" class="btn btn-primary btn-sm" :disabled="addForm.processing" @click="submitAdd">Simpan</button>
        </div>
      </div>
      <form method="dialog" class="modal-backdrop">
        <button>close</button>
      </form>
    </dialog>

    <dialog id="modal-edit-company" class="modal">
      <div class="modal-box max-w-lg">
        <h3 class="font-bold text-lg">Ubah perusahaan</h3>
        <p v-if="editing" class="text-xs font-mono text-base-content/50 mt-1">ID {{ editing.id }}</p>
        <div class="space-y-3 mt-4">
          <div>
            <label class="label"><span class="label-text">Nama usaha <span class="text-error">*</span></span></label>
            <input v-model="editForm.name" type="text" class="input input-bordered input-sm w-full">
            <p v-if="editForm.errors.name" class="text-xs text-error mt-1">{{ editForm.errors.name }}</p>
          </div>
          <div>
            <label class="label"><span class="label-text">Nama legal</span></label>
            <input v-model="editForm.legal_name" type="text" class="input input-bordered input-sm w-full">
            <p v-if="editForm.errors.legal_name" class="text-xs text-error mt-1">{{ editForm.errors.legal_name }}</p>
          </div>
          <div>
            <label class="label"><span class="label-text">NPWP</span></label>
            <input v-model="editForm.tax_id" type="text" class="input input-bordered input-sm w-full">
            <p v-if="editForm.errors.tax_id" class="text-xs text-error mt-1">{{ editForm.errors.tax_id }}</p>
          </div>
          <div class="grid gap-3 sm:grid-cols-2">
            <div>
              <label class="label"><span class="label-text">Email</span></label>
              <input v-model="editForm.email" type="email" class="input input-bordered input-sm w-full">
              <p v-if="editForm.errors.email" class="text-xs text-error mt-1">{{ editForm.errors.email }}</p>
            </div>
            <div>
              <label class="label"><span class="label-text">Telepon</span></label>
              <input v-model="editForm.phone" type="text" class="input input-bordered input-sm w-full">
              <p v-if="editForm.errors.phone" class="text-xs text-error mt-1">{{ editForm.errors.phone }}</p>
            </div>
          </div>
          <div>
            <label class="label"><span class="label-text">Alamat</span></label>
            <textarea v-model="editForm.address" class="textarea textarea-bordered textarea-sm w-full" rows="2" />
            <p v-if="editForm.errors.address" class="text-xs text-error mt-1">{{ editForm.errors.address }}</p>
          </div>
          <label class="label cursor-pointer justify-start gap-3">
            <input v-model="editForm.is_active" type="checkbox" class="checkbox checkbox-sm">
            <span class="label-text">Perusahaan aktif (bisa dipilih untuk pembukuan)</span>
          </label>
          <p v-if="editForm.errors.is_active" class="text-xs text-error">{{ editForm.errors.is_active }}</p>
        </div>
        <div class="modal-action">
          <form method="dialog">
            <button class="btn btn-ghost btn-sm">Tutup</button>
          </form>
          <button type="button" class="btn btn-primary btn-sm" :disabled="editForm.processing" @click="submitEdit">Simpan perubahan</button>
        </div>
      </div>
      <form method="dialog" class="modal-backdrop">
        <button>close</button>
      </form>
    </dialog>
  </AppLayout>
</template>
