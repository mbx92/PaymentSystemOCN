<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
  paymentMethods: Array,
});

const form = useForm({
  code: '',
  name: '',
  description: '',
  status: 'active',
});

const submit = () => {
  form.status = form.status === 'active' ? 'active' : 'inactive';
  form.post(route('erp.admin.payment-methods.store'), {
    preserveScroll: true,
    onSuccess: () => {
      form.reset('code', 'name', 'description');
      form.status = 'active';
      document.getElementById('modal-add-payment-method')?.close();
    },
  });
};

const openAddModal = () => {
  form.clearErrors();
  form.reset('code', 'name', 'description');
  form.status = 'active';
  document.getElementById('modal-add-payment-method')?.showModal();
};

const filterKeyword = ref('');
const filterStatus = ref('');
const filteredPaymentMethods = computed(() => {
  const term = filterKeyword.value.trim().toLowerCase();
  return (props.paymentMethods ?? []).filter((method) => {
    const matchStatus = !filterStatus.value || method.status === filterStatus.value;
    const matchKeyword = !term
      || method.code?.toLowerCase().includes(term)
      || method.name?.toLowerCase().includes(term)
      || (method.description || '').toLowerCase().includes(term);
    return matchStatus && matchKeyword;
  });
});

const editingMethod = ref(null);
const editForm = useForm({
  code: '',
  name: '',
  description: '',
  status: 'active',
});

const openEditModal = (method) => {
  editingMethod.value = method;
  editForm.code = method.code;
  editForm.name = method.name;
  editForm.description = method.description || '';
  editForm.status = method.status;
  document.getElementById('modal-edit-payment-method')?.showModal();
};

const submitEdit = () => {
  if (!editingMethod.value) return;
  editForm.status = editForm.status === 'active' ? 'active' : 'inactive';
  editForm.patch(route('erp.admin.payment-methods.update', editingMethod.value.id), {
    preserveScroll: true,
    onSuccess: () => document.getElementById('modal-edit-payment-method')?.close(),
  });
};

const toggleStatus = (method) => {
  router.patch(route('erp.admin.payment-methods.update', method.id), {
    code: method.code,
    name: method.name,
    description: method.description || '',
    status: method.status === 'active' ? 'inactive' : 'active',
  }, {
    preserveScroll: true,
  });
};
</script>

<template>
  <Head title="Administration - Metode Pembayaran" />
  <AppLayout>
    <div class="space-y-5">
      <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Administration Workspace</p>
        <div class="mt-2 flex items-center justify-between gap-3">
          <div>
            <h1 class="text-3xl font-bold tracking-tight">Metode Pembayaran</h1>
            <p class="mt-2 text-sm text-base-content/70">Master metode pembayaran untuk POS dan Invoice Project.</p>
          </div>
          <Link class="btn btn-ghost btn-sm" :href="route('erp.administration')">Back</Link>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Filter metode pembayaran</h2>
        </div>
        <div class="card-body">
          <div class="flex flex-wrap items-end gap-3">
            <div class="min-w-[220px] grow">
              <label class="label"><span class="label-text">Cari</span></label>
              <input v-model="filterKeyword" type="text" class="input input-bordered w-full" placeholder="Cari code / nama / deskripsi" />
            </div>
            <div class="w-full sm:w-48">
              <label class="label"><span class="label-text">Status</span></label>
              <select v-model="filterStatus" class="select select-bordered w-full">
                <option value="">Semua</option>
                <option value="active">active</option>
                <option value="inactive">inactive</option>
              </select>
            </div>
            <button class="btn btn-primary" @click="openAddModal">+ Tambah Metode</button>
          </div>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Daftar metode pembayaran</h2>
          <p class="ocn-panel__desc">Digunakan di POS dan invoice project.</p>
        </div>
        <div class="overflow-x-auto">
          <table class="table table-zebra">
            <thead>
              <tr><th>Code</th><th>Nama</th><th>Deskripsi</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
              <tr v-for="method in filteredPaymentMethods" :key="method.id">
                <td class="font-mono text-xs">{{ method.code }}</td>
                <td class="font-semibold">{{ method.name }}</td>
                <td>{{ method.description || '-' }}</td>
                <td><span class="badge badge-sm" :class="method.status === 'active' ? 'badge-success' : 'badge-ghost'">{{ method.status }}</span></td>
                <td class="text-right">
                  <div class="flex justify-end gap-2">
                    <button class="btn btn-ghost btn-xs" @click="openEditModal(method)">Edit</button>
                    <button
                      class="btn btn-xs"
                      :class="method.status === 'active' ? 'btn-warning' : 'btn-success'"
                      @click="toggleStatus(method)"
                    >
                      {{ method.status === 'active' ? 'Nonaktifkan' : 'Aktifkan' }}
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="!filteredPaymentMethods.length">
                <td colspan="5" class="py-8 text-center text-base-content/50">Belum ada metode pembayaran.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <dialog id="modal-edit-payment-method" class="modal">
      <div class="modal-box max-w-xl">
        <h3 class="font-bold text-lg">Edit Metode Pembayaran</h3>
        <div class="mt-4 space-y-3">
          <div>
            <label class="label"><span class="label-text">Code</span></label>
            <input v-model="editForm.code" type="text" class="input input-bordered w-full" />
            <p v-if="editForm.errors.code" class="text-error text-xs mt-1">{{ editForm.errors.code }}</p>
          </div>
          <div>
            <label class="label"><span class="label-text">Nama</span></label>
            <input v-model="editForm.name" type="text" class="input input-bordered w-full" />
            <p v-if="editForm.errors.name" class="text-error text-xs mt-1">{{ editForm.errors.name }}</p>
          </div>
          <div>
            <label class="label"><span class="label-text">Deskripsi</span></label>
            <input v-model="editForm.description" type="text" class="input input-bordered w-full" />
            <p v-if="editForm.errors.description" class="text-error text-xs mt-1">{{ editForm.errors.description }}</p>
          </div>
          <div>
            <label class="label cursor-pointer justify-start gap-3 mt-1">
              <input
                :checked="editForm.status === 'active'"
                type="checkbox"
                class="toggle toggle-success"
                @change="editForm.status = $event.target.checked ? 'active' : 'inactive'"
              />
              <span class="label-text">{{ editForm.status === 'active' ? 'active' : 'inactive' }}</span>
            </label>
            <p v-if="editForm.errors.status" class="text-error text-xs mt-1">{{ editForm.errors.status }}</p>
          </div>
        </div>
        <div class="modal-action">
          <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
          <button class="btn btn-primary" :disabled="editForm.processing" @click="submitEdit">Simpan Perubahan</button>
        </div>
      </div>
    </dialog>

    <dialog id="modal-add-payment-method" class="modal">
      <div class="modal-box max-w-xl">
        <h3 class="font-bold text-lg">Tambah Metode Pembayaran</h3>
        <div class="mt-4 space-y-3">
          <div>
            <label class="label"><span class="label-text">Code</span></label>
            <input v-model="form.code" type="text" class="input input-bordered w-full" placeholder="cash / transfer / qris" />
            <p v-if="form.errors.code" class="text-error text-xs mt-1">{{ form.errors.code }}</p>
          </div>
          <div>
            <label class="label"><span class="label-text">Nama</span></label>
            <input v-model="form.name" type="text" class="input input-bordered w-full" placeholder="Tunai / Transfer Bank / QRIS" />
            <p v-if="form.errors.name" class="text-error text-xs mt-1">{{ form.errors.name }}</p>
          </div>
          <div>
            <label class="label"><span class="label-text">Deskripsi</span></label>
            <input v-model="form.description" type="text" class="input input-bordered w-full" placeholder="Opsional" />
            <p v-if="form.errors.description" class="text-error text-xs mt-1">{{ form.errors.description }}</p>
          </div>
          <div>
            <label class="label cursor-pointer justify-start gap-3 mt-1">
              <input
                :checked="form.status === 'active'"
                type="checkbox"
                class="toggle toggle-success"
                @change="form.status = $event.target.checked ? 'active' : 'inactive'"
              />
              <span class="label-text">{{ form.status === 'active' ? 'active' : 'inactive' }}</span>
            </label>
            <p v-if="form.errors.status" class="text-error text-xs mt-1">{{ form.errors.status }}</p>
          </div>
        </div>
        <div class="modal-action">
          <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
          <button class="btn btn-primary" :disabled="form.processing" @click="submit">Tambah Metode</button>
        </div>
      </div>
    </dialog>
  </AppLayout>
</template>

