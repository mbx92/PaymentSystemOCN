<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTablePagination from '@/Components/DataTablePagination.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { ref } from 'vue';
import { useCurrency } from '@/composables/useCurrency';

const props = defineProps({
  employees: Object,
  filters: Object,
});

const { format } = useCurrency();

const form = useForm({
  employee_no: '',
  name: '',
  email: '',
  phone: '',
  position: '',
  base_salary: 0,
  is_active: true,
});

const editForm = useForm({
  employee_no: '',
  name: '',
  email: '',
  phone: '',
  position: '',
  base_salary: 0,
  is_active: true,
});

const selected = ref(null);

const resetAddForm = () => {
  form.clearErrors();
  form.reset();
  form.is_active = true;
  form.base_salary = 0;
};

const openAdd = () => {
  resetAddForm();
  document.getElementById('modal-add-employee')?.showModal();
};

const submitAdd = () => {
  form.post(route('erp.hr.employees.store'), {
    preserveScroll: true,
    onSuccess: () => {
      resetAddForm();
      document.getElementById('modal-add-employee')?.close();
    },
  });
};

const openEdit = (row) => {
  selected.value = row;
  editForm.clearErrors();
  editForm.employee_no = row.employee_no;
  editForm.name = row.name;
  editForm.email = row.email || '';
  editForm.phone = row.phone || '';
  editForm.position = row.position || '';
  editForm.base_salary = row.base_salary ?? 0;
  editForm.is_active = !!row.is_active;
  document.getElementById('modal-edit-employee')?.showModal();
};

const submitEdit = () => {
  if (!selected.value) return;
  editForm.patch(route('erp.hr.employees.update', selected.value.id), {
    preserveScroll: true,
    onSuccess: () => document.getElementById('modal-edit-employee')?.close(),
  });
};

const remove = (row) => {
  if (!confirm(`Hapus karyawan ${row.name}?`)) return;
  router.delete(route('erp.hr.employees.destroy', row.id), { preserveScroll: true });
};
</script>

<template>
  <Head title="HR — Karyawan" />
  <AppLayout>
    <div class="space-y-5">
      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">HR Workspace</p>
              <h1 class="ocn-panel__title mt-1">Karyawan</h1>
              <p class="ocn-panel__desc mt-1">Master data karyawan untuk operasional HR (terpisah dari anggota tim project).</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
              <div class="flex flex-wrap items-center gap-2">
            <button type="button" class="btn btn-primary btn-sm" @click="openAdd">+ Tambah karyawan</button>
            <Link class="btn btn-ghost btn-sm shrink-0 gap-1.5" :href="route('erp.hr')">
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
          <h2 class="ocn-panel__title">Daftar karyawan</h2>
          <p class="ocn-panel__desc">Nomor pegawai unik, kontak, jabatan, status aktif, gaji pokok.</p>
        </div>
        <div class="overflow-x-auto">
          <table class="table table-zebra table-sm">
            <thead>
              <tr>
                <th>No. pegawai</th>
                <th>Nama</th>
                <th>Jabatan</th>
                <th>Kontak</th>
                <th class="text-right">Gaji pokok</th>
                <th>Status</th>
                <th />
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in (employees?.data || [])" :key="row.id">
                <td class="font-mono text-xs">{{ row.employee_no }}</td>
                <td class="font-medium">{{ row.name }}</td>
                <td>{{ row.position || '—' }}</td>
                <td class="text-xs text-base-content/70">
                  <span v-if="row.email">{{ row.email }}</span>
                  <span v-if="row.phone"><br>{{ row.phone }}</span>
                  <span v-if="!row.email && !row.phone">—</span>
                </td>
                <td class="text-right tabular-nums">{{ format(row.base_salary) }}</td>
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
              <tr v-if="!(employees?.data || []).length">
                <td colspan="7" class="py-8 text-center text-base-content/50">Belum ada data karyawan.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <DataTablePagination
          :paginator="employees"
          @update:per-page="(n) => router.get(route('erp.hr.employees'), { per_page: n }, { preserveState: true, replace: true })"
        />
      </div>
    </div>

    <dialog id="modal-add-employee" class="modal">
      <div class="modal-box max-w-lg">
        <h3 class="font-bold text-lg">Tambah karyawan</h3>
        <div class="mt-4 grid grid-cols-1 gap-3">
          <div>
            <label class="label"><span class="label-text">No. pegawai <span class="text-error">*</span></span></label>
            <input v-model="form.employee_no" type="text" class="input input-bordered w-full font-mono" placeholder="EMP-001" />
            <p v-if="form.errors.employee_no" class="text-error text-xs mt-1">{{ form.errors.employee_no }}</p>
          </div>
          <div>
            <label class="label"><span class="label-text">Nama <span class="text-error">*</span></span></label>
            <input v-model="form.name" type="text" class="input input-bordered w-full" />
            <p v-if="form.errors.name" class="text-error text-xs mt-1">{{ form.errors.name }}</p>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="label"><span class="label-text">Email</span></label>
              <input v-model="form.email" type="email" class="input input-bordered w-full" />
            </div>
            <div>
              <label class="label"><span class="label-text">Telepon</span></label>
              <input v-model="form.phone" type="text" class="input input-bordered w-full" />
            </div>
          </div>
          <div>
            <label class="label"><span class="label-text">Jabatan</span></label>
            <input v-model="form.position" type="text" class="input input-bordered w-full" />
          </div>
          <div>
            <label class="label"><span class="label-text">Gaji pokok</span></label>
            <input v-model.number="form.base_salary" type="number" min="0" step="1000" class="input input-bordered w-full" />
          </div>
          <label class="label cursor-pointer justify-start gap-3">
            <input v-model="form.is_active" type="checkbox" class="toggle toggle-success" />
            <span class="label-text">{{ form.is_active ? 'Aktif' : 'Nonaktif' }}</span>
          </label>
        </div>
        <div class="modal-action">
          <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
          <button type="button" class="btn btn-primary" :disabled="form.processing" @click="submitAdd">Simpan</button>
        </div>
      </div>
    </dialog>

    <dialog id="modal-edit-employee" class="modal">
      <div class="modal-box max-w-lg">
        <h3 class="font-bold text-lg">Edit karyawan</h3>
        <div class="mt-4 grid grid-cols-1 gap-3">
          <div>
            <label class="label"><span class="label-text">No. pegawai</span></label>
            <input v-model="editForm.employee_no" type="text" class="input input-bordered w-full font-mono" />
            <p v-if="editForm.errors.employee_no" class="text-error text-xs mt-1">{{ editForm.errors.employee_no }}</p>
          </div>
          <div>
            <label class="label"><span class="label-text">Nama</span></label>
            <input v-model="editForm.name" type="text" class="input input-bordered w-full" />
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="label"><span class="label-text">Email</span></label>
              <input v-model="editForm.email" type="email" class="input input-bordered w-full" />
            </div>
            <div>
              <label class="label"><span class="label-text">Telepon</span></label>
              <input v-model="editForm.phone" type="text" class="input input-bordered w-full" />
            </div>
          </div>
          <div>
            <label class="label"><span class="label-text">Jabatan</span></label>
            <input v-model="editForm.position" type="text" class="input input-bordered w-full" />
          </div>
          <div>
            <label class="label"><span class="label-text">Gaji pokok</span></label>
            <input v-model.number="editForm.base_salary" type="number" min="0" step="1000" class="input input-bordered w-full" />
          </div>
          <label class="label cursor-pointer justify-start gap-3">
            <input v-model="editForm.is_active" type="checkbox" class="toggle toggle-success" />
            <span class="label-text">{{ editForm.is_active ? 'Aktif' : 'Nonaktif' }}</span>
          </label>
        </div>
        <div class="modal-action">
          <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
          <button type="button" class="btn btn-primary" :disabled="editForm.processing" @click="submitEdit">Simpan</button>
        </div>
      </div>
    </dialog>
  </AppLayout>
</template>
