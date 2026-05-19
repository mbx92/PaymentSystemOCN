<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTablePagination from '@/Components/DataTablePagination.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { reactive, ref, watch } from 'vue';
import { useCurrency } from '@/composables/useCurrency';
import { useDateFormat } from '@/composables/useDateFormat';

const props = defineProps({
  leads: Object,
  users: Array,
  filters: Object,
});

const { formatDate, formatDateTime } = useDateFormat();

const { format } = useCurrency();

const filters = reactive({
  q: props.filters?.q ?? '',
  status: props.filters?.status ?? '',
  source: props.filters?.source ?? '',
  per_page: props.filters?.per_page ?? props.leads?.per_page ?? 25,
});

let timer;
watch(
  filters,
  (val) => {
    clearTimeout(timer);
    timer = setTimeout(() => {
      router.get(route('erp.crm.leads'), val, { preserveState: true, replace: true });
    }, 250);
  },
  { deep: true },
);

const statusOptions = [
  { value: 'new', label: 'New' },
  { value: 'contacted', label: 'Contacted' },
  { value: 'qualified', label: 'Qualified' },
  { value: 'proposal', label: 'Proposal' },
  { value: 'won', label: 'Won' },
  { value: 'lost', label: 'Lost' },
];

const sourceOptions = [
  { value: 'manual', label: 'Manual' },
  { value: 'website', label: 'Website' },
  { value: 'referral', label: 'Referral' },
  { value: 'social_media', label: 'Social Media' },
  { value: 'event', label: 'Event' },
  { value: 'cold_call', label: 'Cold Call' },
  { value: 'other', label: 'Lainnya' },
];

const statusBadge = (status) => {
  const map = {
    new: 'badge-info',
    contacted: 'badge-warning',
    qualified: 'badge-accent',
    proposal: 'badge-primary',
    won: 'badge-success',
    lost: 'badge-error',
  };
  return map[status] ?? 'badge-ghost';
};

const form = useForm({
  name: '',
  company: '',
  email: '',
  phone: '',
  source: 'manual',
  status: 'new',
  estimated_value: 0,
  pic_user_id: '',
  notes: '',
});

const editForm = useForm({
  name: '',
  company: '',
  email: '',
  phone: '',
  source: 'manual',
  status: 'new',
  estimated_value: 0,
  pic_user_id: '',
  notes: '',
});

const selected = ref(null);

const resetAddForm = () => {
  form.clearErrors();
  form.reset();
  form.source = 'manual';
  form.status = 'new';
  form.estimated_value = 0;
  form.pic_user_id = '';
};

const openAdd = () => {
  resetAddForm();
  document.getElementById('modal-add-lead')?.showModal();
};

const submitAdd = () => {
  form.post(route('erp.crm.leads.store'), {
    preserveScroll: true,
    onSuccess: () => {
      resetAddForm();
      document.getElementById('modal-add-lead')?.close();
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
  editForm.source = row.source;
  editForm.status = row.status;
  editForm.estimated_value = row.estimated_value ?? 0;
  editForm.pic_user_id = row.pic_user_id ?? '';
  editForm.notes = row.notes || '';
  document.getElementById('modal-edit-lead')?.showModal();
};

const submitEdit = () => {
  if (!selected.value) return;
  editForm.patch(route('erp.crm.leads.update', selected.value.id), {
    preserveScroll: true,
    onSuccess: () => document.getElementById('modal-edit-lead')?.close(),
  });
};

const remove = (row) => {
  if (!confirm(`Hapus lead "${row.name}"?`)) return;
  router.delete(route('erp.crm.leads.destroy', row.id), { preserveScroll: true });
};
</script>

<template>
  <Head title="CRM — Lead Management" />
  <AppLayout>
    <div class="space-y-5">
      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">CRM Workspace</p>
              <h1 class="ocn-panel__title mt-1">Lead Management</h1>
              <p class="ocn-panel__desc mt-1">Pusat data calon customer, sumber lead, status prospek, dan PIC follow-up.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
              <div class="flex flex-wrap items-center gap-2">
            <button type="button" class="btn btn-primary btn-sm" @click="openAdd">+ Tambah lead</button>
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
          <h2 class="ocn-panel__title">Filter lead</h2>
        </div>
        <div class="card-body">
          <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
            <div class="md:col-span-2">
              <label class="label"><span class="label-text text-xs uppercase tracking-wide">Search</span></label>
              <input v-model="filters.q" type="text" class="input input-sm input-bordered w-full" placeholder="Nama, perusahaan, email, telepon..." />
            </div>
            <div>
              <label class="label"><span class="label-text text-xs uppercase tracking-wide">Status</span></label>
              <select v-model="filters.status" class="select select-sm select-bordered w-full">
                <option value="">Semua</option>
                <option v-for="s in statusOptions" :key="s.value" :value="s.value">{{ s.label }}</option>
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
          <h2 class="ocn-panel__title">Daftar lead</h2>
          <p class="ocn-panel__desc">{{ leads?.total ?? 0 }} lead ditemukan. Klik Edit untuk ubah data.</p>
        </div>
        <div class="overflow-x-auto">
          <table class="table table-zebra table-sm">
            <thead>
              <tr>
                <th>Nama</th>
                <th>Perusahaan</th>
                <th>Kontak</th>
                <th>Sumber</th>
                <th>Status</th>
                <th class="text-right">Est. Value</th>
                <th>PIC</th>
                <th>Tanggal</th>
                <th />
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in (leads?.data || [])" :key="row.id">
                <td class="font-medium">{{ row.name }}</td>
                <td>{{ row.company || '—' }}</td>
                <td class="text-xs text-base-content/70">
                  <span v-if="row.email">{{ row.email }}</span>
                  <span v-if="row.phone"><br>{{ row.phone }}</span>
                  <span v-if="!row.email && !row.phone">—</span>
                </td>
                <td>
                  <span class="badge badge-ghost badge-sm capitalize">{{ row.source?.replace('_', ' ') }}</span>
                </td>
                <td>
                  <span class="badge badge-sm capitalize" :class="statusBadge(row.status)">{{ row.status }}</span>
                </td>
                <td class="text-right tabular-nums">{{ format(row.estimated_value) }}</td>
                <td class="text-sm">{{ row.pic_name || '—' }}</td>
                <td class="text-xs text-base-content/60">{{ formatDateTime(row.created_at) }}</td>
                <td class="text-right">
                  <button type="button" class="btn btn-ghost btn-xs" @click="openEdit(row)">Edit</button>
                  <button type="button" class="btn btn-ghost btn-xs text-error" @click="remove(row)">Hapus</button>
                </td>
              </tr>
              <tr v-if="!(leads?.data || []).length">
                <td colspan="9" class="py-8 text-center text-base-content/50">Belum ada data lead.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <DataTablePagination :paginator="leads" @update:per-page="(n) => { filters.per_page = n; }" />
      </div>
    </div>

    <!-- Modal Tambah Lead -->
    <dialog id="modal-add-lead" class="modal">
      <div class="modal-box max-w-lg">
        <h3 class="text-lg font-bold">Tambah Lead</h3>
        <div class="mt-4 grid grid-cols-1 gap-3">
          <div>
            <label class="label"><span class="label-text">Nama kontak <span class="text-error">*</span></span></label>
            <input v-model="form.name" type="text" class="input input-bordered w-full" placeholder="Nama calon customer" />
            <p v-if="form.errors.name" class="mt-1 text-xs text-error">{{ form.errors.name }}</p>
          </div>
          <div>
            <label class="label"><span class="label-text">Perusahaan</span></label>
            <input v-model="form.company" type="text" class="input input-bordered w-full" placeholder="Nama perusahaan" />
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
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <label class="label"><span class="label-text">Sumber <span class="text-error">*</span></span></label>
              <select v-model="form.source" class="select select-bordered w-full">
                <option v-for="s in sourceOptions" :key="s.value" :value="s.value">{{ s.label }}</option>
              </select>
            </div>
            <div>
              <label class="label"><span class="label-text">Status <span class="text-error">*</span></span></label>
              <select v-model="form.status" class="select select-bordered w-full">
                <option v-for="s in statusOptions" :key="s.value" :value="s.value">{{ s.label }}</option>
              </select>
            </div>
          </div>
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <label class="label"><span class="label-text">Estimasi value</span></label>
              <input v-model.number="form.estimated_value" type="number" min="0" step="1000" class="input input-bordered w-full" />
            </div>
            <div>
              <label class="label"><span class="label-text">PIC follow-up</span></label>
              <select v-model="form.pic_user_id" class="select select-bordered w-full">
                <option value="">— Pilih PIC —</option>
                <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
              </select>
            </div>
          </div>
          <div>
            <label class="label"><span class="label-text">Catatan</span></label>
            <textarea v-model="form.notes" class="textarea textarea-bordered w-full" rows="3" placeholder="Catatan tambahan..." />
          </div>
        </div>
        <div class="modal-action">
          <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
          <button type="button" class="btn btn-primary" :disabled="form.processing" @click="submitAdd">Simpan</button>
        </div>
      </div>
    </dialog>

    <!-- Modal Edit Lead -->
    <dialog id="modal-edit-lead" class="modal">
      <div class="modal-box max-w-lg">
        <h3 class="text-lg font-bold">Edit Lead</h3>
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
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <label class="label"><span class="label-text">Sumber <span class="text-error">*</span></span></label>
              <select v-model="editForm.source" class="select select-bordered w-full">
                <option v-for="s in sourceOptions" :key="s.value" :value="s.value">{{ s.label }}</option>
              </select>
            </div>
            <div>
              <label class="label"><span class="label-text">Status <span class="text-error">*</span></span></label>
              <select v-model="editForm.status" class="select select-bordered w-full">
                <option v-for="s in statusOptions" :key="s.value" :value="s.value">{{ s.label }}</option>
              </select>
            </div>
          </div>
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <label class="label"><span class="label-text">Estimasi value</span></label>
              <input v-model.number="editForm.estimated_value" type="number" min="0" step="1000" class="input input-bordered w-full" />
            </div>
            <div>
              <label class="label"><span class="label-text">PIC follow-up</span></label>
              <select v-model="editForm.pic_user_id" class="select select-bordered w-full">
                <option value="">— Pilih PIC —</option>
                <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
              </select>
            </div>
          </div>
          <div>
            <label class="label"><span class="label-text">Catatan</span></label>
            <textarea v-model="editForm.notes" class="textarea textarea-bordered w-full" rows="3" />
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
