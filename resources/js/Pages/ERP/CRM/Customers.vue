<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTablePagination from '@/Components/DataTablePagination.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { reactive, ref, watch } from 'vue';

const props = defineProps({
  customers: Object,
  users: Array,
  filters: Object,
});

const filters = reactive({
  q: props.filters?.q ?? '',
  is_active: props.filters?.is_active ?? '',
  source: props.filters?.source ?? '',
  per_page: props.filters?.per_page ?? props.customers?.per_page ?? 25,
});

let timer;
watch(
  filters,
  (val) => {
    clearTimeout(timer);
    timer = setTimeout(() => {
      router.get(route('erp.crm.customers'), val, { preserveState: true, replace: true });
    }, 250);
  },
  { deep: true },
);

const sourceOptions = [
  { value: 'manual', label: 'Manual' },
  { value: 'website', label: 'Website' },
  { value: 'referral', label: 'Referral' },
  { value: 'social_media', label: 'Social Media' },
  { value: 'event', label: 'Event' },
  { value: 'cold_call', label: 'Cold Call' },
  { value: 'lead_conversion', label: 'Konversi Lead' },
  { value: 'other', label: 'Lainnya' },
];

const businessTypes = [
  { value: 'perorangan', label: 'Perorangan' },
  { value: 'cv', label: 'CV' },
  { value: 'pt', label: 'PT' },
  { value: 'ud', label: 'UD' },
  { value: 'yayasan', label: 'Yayasan' },
  { value: 'instansi', label: 'Instansi Pemerintah' },
  { value: 'other', label: 'Lainnya' },
];

const form = useForm({
  name: '',
  company: '',
  email: '',
  phone: '',
  address: '',
  business_type: '',
  tax_id: '',
  source: 'manual',
  pic_user_id: '',
  is_active: true,
  notes: '',
});

const editForm = useForm({
  name: '',
  company: '',
  email: '',
  phone: '',
  address: '',
  business_type: '',
  tax_id: '',
  source: 'manual',
  pic_user_id: '',
  is_active: true,
  notes: '',
});

const selected = ref(null);

const resetAddForm = () => {
  form.clearErrors();
  form.reset();
  form.source = 'manual';
  form.is_active = true;
  form.pic_user_id = '';
  form.business_type = '';
};

const openAdd = () => {
  resetAddForm();
  document.getElementById('modal-add-customer')?.showModal();
};

const submitAdd = () => {
  form.post(route('erp.crm.customers.store'), {
    preserveScroll: true,
    onSuccess: () => {
      resetAddForm();
      document.getElementById('modal-add-customer')?.close();
    },
  });
};

const openEdit = (row) => {
  selected.value = row;
  editForm.clearErrors();
  editForm.name = row.name;
  editForm.company = row.company || '';
  editForm.email = row.email || '';
  editForm.phone = row.phone || '';
  editForm.address = row.address || '';
  editForm.business_type = row.business_type || '';
  editForm.tax_id = row.tax_id || '';
  editForm.source = row.source;
  editForm.pic_user_id = row.pic_user_id ?? '';
  editForm.is_active = row.is_active;
  editForm.notes = row.notes || '';
  document.getElementById('modal-edit-customer')?.showModal();
};

const submitEdit = () => {
  if (!selected.value) return;
  editForm.patch(route('erp.crm.customers.update', selected.value.id), {
    preserveScroll: true,
    onSuccess: () => document.getElementById('modal-edit-customer')?.close(),
  });
};

const remove = (row) => {
  if (!confirm(`Hapus customer "${row.name}" (${row.code})?`)) return;
  router.delete(route('erp.crm.customers.destroy', row.id), { preserveScroll: true });
};
</script>

<template>
  <Head title="CRM — Customer Database" />
  <AppLayout>
    <div class="space-y-5">
      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">CRM Workspace</p>
              <h1 class="ocn-panel__title mt-1">Customer Database</h1>
              <p class="ocn-panel__desc mt-1">Master customer lintas sub usaha agar histori komunikasi dan transaksi tetap terhubung.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
              <div class="flex flex-wrap items-center gap-2">
            <button type="button" class="btn btn-primary btn-sm" @click="openAdd">+ Tambah customer</button>
            <Link class="btn btn-ghost btn-sm shrink-0 gap-1.5" :href="route('erp.crm')">
            <ArrowLeftIcon class="h-4 w-4" />
            Back
          </Link>
          </div>
            </div>
          </div>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Filter customer</h2>
        </div>
        <div class="card-body">
          <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
            <div class="md:col-span-2">
              <label class="label"><span class="label-text text-xs uppercase tracking-wide">Search</span></label>
              <input v-model="filters.q" type="text" class="input input-sm input-bordered w-full" placeholder="Kode, nama, perusahaan, email, telepon..." />
            </div>
            <div>
              <label class="label"><span class="label-text text-xs uppercase tracking-wide">Status</span></label>
              <select v-model="filters.is_active" class="select select-sm select-bordered w-full">
                <option value="">Semua</option>
                <option value="1">Aktif</option>
                <option value="0">Nonaktif</option>
              </select>
            </div>
            <div>
              <label class="label"><span class="label-text text-xs uppercase tracking-wide">Sumber</span></label>
              <select v-model="filters.source" class="select select-sm select-bordered w-full">
                <option value="">Semua</option>
                <option v-for="s in sourceOptions" :key="s.value" :value="s.value">{{ s.label }}</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Daftar customer</h2>
          <p class="ocn-panel__desc">{{ customers?.total ?? 0 }} customer ditemukan.</p>
        </div>
        <div class="overflow-x-auto">
          <table class="table table-zebra table-sm">
            <thead>
              <tr>
                <th>Kode</th>
                <th>Nama</th>
                <th>Perusahaan</th>
                <th>Kontak</th>
                <th>Tipe Bisnis</th>
                <th>Sumber</th>
                <th>PIC</th>
                <th>Status</th>
                <th />
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in (customers?.data || [])" :key="row.id">
                <td class="font-mono text-xs">{{ row.code }}</td>
                <td class="font-medium">{{ row.name }}</td>
                <td>{{ row.company || '—' }}</td>
                <td class="text-xs text-base-content/70">
                  <span v-if="row.email">{{ row.email }}</span>
                  <span v-if="row.phone"><br>{{ row.phone }}</span>
                  <span v-if="!row.email && !row.phone">—</span>
                </td>
                <td>
                  <span v-if="row.business_type" class="badge badge-ghost badge-sm capitalize">{{ row.business_type }}</span>
                  <span v-else>—</span>
                </td>
                <td>
                  <span class="badge badge-ghost badge-sm capitalize">{{ row.source?.replace('_', ' ') }}</span>
                </td>
                <td class="text-sm">{{ row.pic_name || '—' }}</td>
                <td>
                  <span class="badge badge-sm" :class="row.is_active ? 'badge-success' : 'badge-ghost'">
                    {{ row.is_active ? 'aktif' : 'nonaktif' }}
                  </span>
                </td>
                <td class="text-right">
                  <button type="button" class="btn btn-ghost btn-xs" @click="openEdit(row)">Edit</button>
                  <button type="button" class="btn btn-ghost btn-xs text-error" @click="remove(row)">Hapus</button>
                </td>
              </tr>
              <tr v-if="!(customers?.data || []).length">
                <td colspan="9" class="py-8 text-center text-base-content/50">Belum ada data customer.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <DataTablePagination :paginator="customers" @update:per-page="(n) => { filters.per_page = n; }" />
      </div>
    </div>

    <!-- Modal Tambah Customer -->
    <dialog id="modal-add-customer" class="modal">
      <div class="modal-box max-w-xl">
        <h3 class="text-lg font-bold">Tambah Customer</h3>
        <div class="mt-4 grid grid-cols-1 gap-3">
          <div>
            <label class="label"><span class="label-text">Nama kontak <span class="text-error">*</span></span></label>
            <input v-model="form.name" type="text" class="input input-bordered w-full" placeholder="Nama customer" />
            <p v-if="form.errors.name" class="mt-1 text-xs text-error">{{ form.errors.name }}</p>
          </div>
          <div>
            <label class="label"><span class="label-text">Perusahaan</span></label>
            <input v-model="form.company" type="text" class="input input-bordered w-full" placeholder="Nama perusahaan / badan usaha" />
          </div>
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <label class="label"><span class="label-text">Email</span></label>
              <input v-model="form.email" type="email" class="input input-bordered w-full" />
              <p v-if="form.errors.email" class="mt-1 text-xs text-error">{{ form.errors.email }}</p>
            </div>
            <div>
              <label class="label"><span class="label-text">Telepon</span></label>
              <input v-model="form.phone" type="text" class="input input-bordered w-full" />
            </div>
          </div>
          <div>
            <label class="label"><span class="label-text">Alamat</span></label>
            <textarea v-model="form.address" class="textarea textarea-bordered w-full" rows="2" placeholder="Alamat lengkap" />
          </div>
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <label class="label"><span class="label-text">Tipe bisnis</span></label>
              <select v-model="form.business_type" class="select select-bordered w-full">
                <option value="">— Pilih —</option>
                <option v-for="b in businessTypes" :key="b.value" :value="b.value">{{ b.label }}</option>
              </select>
            </div>
            <div>
              <label class="label"><span class="label-text">NPWP / Tax ID</span></label>
              <input v-model="form.tax_id" type="text" class="input input-bordered w-full" placeholder="00.000.000.0-000.000" />
            </div>
          </div>
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <label class="label"><span class="label-text">Sumber <span class="text-error">*</span></span></label>
              <select v-model="form.source" class="select select-bordered w-full">
                <option v-for="s in sourceOptions" :key="s.value" :value="s.value">{{ s.label }}</option>
              </select>
            </div>
            <div>
              <label class="label"><span class="label-text">PIC</span></label>
              <select v-model="form.pic_user_id" class="select select-bordered w-full">
                <option value="">— Pilih PIC —</option>
                <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
              </select>
            </div>
          </div>
          <label class="label cursor-pointer justify-start gap-3">
            <input v-model="form.is_active" type="checkbox" class="toggle toggle-success" />
            <span class="label-text">{{ form.is_active ? 'Aktif' : 'Nonaktif' }}</span>
          </label>
          <div>
            <label class="label"><span class="label-text">Catatan</span></label>
            <textarea v-model="form.notes" class="textarea textarea-bordered w-full" rows="2" placeholder="Catatan tambahan..." />
          </div>
        </div>
        <div class="modal-action">
          <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
          <button type="button" class="btn btn-primary" :disabled="form.processing" @click="submitAdd">Simpan</button>
        </div>
      </div>
    </dialog>

    <!-- Modal Edit Customer -->
    <dialog id="modal-edit-customer" class="modal">
      <div class="modal-box max-w-xl">
        <h3 class="text-lg font-bold">Edit Customer</h3>
        <p v-if="selected" class="mt-1 text-sm text-base-content/60">Kode: <span class="font-mono">{{ selected.code }}</span></p>
        <div class="mt-4 grid grid-cols-1 gap-3">
          <div>
            <label class="label"><span class="label-text">Nama kontak <span class="text-error">*</span></span></label>
            <input v-model="editForm.name" type="text" class="input input-bordered w-full" />
            <p v-if="editForm.errors.name" class="mt-1 text-xs text-error">{{ editForm.errors.name }}</p>
          </div>
          <div>
            <label class="label"><span class="label-text">Perusahaan</span></label>
            <input v-model="editForm.company" type="text" class="input input-bordered w-full" />
          </div>
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <label class="label"><span class="label-text">Email</span></label>
              <input v-model="editForm.email" type="email" class="input input-bordered w-full" />
              <p v-if="editForm.errors.email" class="mt-1 text-xs text-error">{{ editForm.errors.email }}</p>
            </div>
            <div>
              <label class="label"><span class="label-text">Telepon</span></label>
              <input v-model="editForm.phone" type="text" class="input input-bordered w-full" />
            </div>
          </div>
          <div>
            <label class="label"><span class="label-text">Alamat</span></label>
            <textarea v-model="editForm.address" class="textarea textarea-bordered w-full" rows="2" />
          </div>
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <label class="label"><span class="label-text">Tipe bisnis</span></label>
              <select v-model="editForm.business_type" class="select select-bordered w-full">
                <option value="">— Pilih —</option>
                <option v-for="b in businessTypes" :key="b.value" :value="b.value">{{ b.label }}</option>
              </select>
            </div>
            <div>
              <label class="label"><span class="label-text">NPWP / Tax ID</span></label>
              <input v-model="editForm.tax_id" type="text" class="input input-bordered w-full" />
            </div>
          </div>
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <label class="label"><span class="label-text">Sumber <span class="text-error">*</span></span></label>
              <select v-model="editForm.source" class="select select-bordered w-full">
                <option v-for="s in sourceOptions" :key="s.value" :value="s.value">{{ s.label }}</option>
              </select>
            </div>
            <div>
              <label class="label"><span class="label-text">PIC</span></label>
              <select v-model="editForm.pic_user_id" class="select select-bordered w-full">
                <option value="">— Pilih PIC —</option>
                <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
              </select>
            </div>
          </div>
          <label class="label cursor-pointer justify-start gap-3">
            <input v-model="editForm.is_active" type="checkbox" class="toggle toggle-success" />
            <span class="label-text">{{ editForm.is_active ? 'Aktif' : 'Nonaktif' }}</span>
          </label>
          <div>
            <label class="label"><span class="label-text">Catatan</span></label>
            <textarea v-model="editForm.notes" class="textarea textarea-bordered w-full" rows="2" />
          </div>
        </div>
        <div class="modal-action">
          <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
          <button type="button" class="btn btn-primary" :disabled="editForm.processing" @click="submitEdit">Simpan</button>
        </div>
      </div>
    </dialog>
  </AppLayout>
</template>
