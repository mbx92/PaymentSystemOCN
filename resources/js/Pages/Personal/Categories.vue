<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { computed, ref } from 'vue';

const props = defineProps({
    categories: Array,
});

const typeLabel = (t) => (t === 'income' ? 'Pemasukan' : 'Pengeluaran');

const incomeRows = computed(() => (props.categories ?? []).filter((c) => c.type === 'income'));
const expenseRows = computed(() => (props.categories ?? []).filter((c) => c.type === 'expense'));

const addForm = useForm({
    name: '',
    type: 'expense',
    color: '',
});

const openAdd = (type = 'expense') => {
    addForm.clearErrors();
    addForm.reset('name', 'color');
    addForm.type = type;
    document.getElementById('modal-add-category')?.showModal();
};

const submitAdd = () => {
    addForm.post(route('personal.categories.store'), {
        preserveScroll: true,
        onSuccess: () => document.getElementById('modal-add-category')?.close(),
    });
};

const editing = ref(null);
const editForm = useForm({
    name: '',
    type: 'expense',
    color: '',
});

const openEdit = (row) => {
    editing.value = row;
    editForm.clearErrors();
    editForm.name = row.name;
    editForm.type = row.type;
    editForm.color = row.color ?? '';
    document.getElementById('modal-edit-category')?.showModal();
};

const submitEdit = () => {
    if (!editing.value) return;
    editForm.patch(route('personal.categories.update', editing.value.id), {
        preserveScroll: true,
        onSuccess: () => document.getElementById('modal-edit-category')?.close(),
    });
};

const deleting = ref(null);
const deleteMessage = computed(() => {
    const row = deleting.value;
    if (!row) return '';
    const extra = row.transaction_count > 0
        ? ` ${row.transaction_count} transaksi akan kehilangan kategori ini.`
        : '';
    return `Yakin hapus kategori “${row.name}”?${extra}`;
});

const confirmDelete = (row) => {
    deleting.value = row;
    document.getElementById('modal-delete-category')?.showModal();
};

const doDelete = () => {
    if (!deleting.value) return;
    router.delete(route('personal.categories.destroy', deleting.value.id), {
        preserveScroll: true,
        onFinish: () => { deleting.value = null; },
    });
};
</script>

<template>
  <Head title="Personal — Kategori" />
  <AppLayout>
    <div class="space-y-5">
      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Personal</p>
              <h1 class="ocn-panel__title mt-1">Master kategori</h1>
              <p class="ocn-panel__desc mt-1">Kelola kategori pemasukan dan pengeluaran untuk transaksi serta anggaran keluarga.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
              <button type="button" class="btn btn-primary btn-sm" @click="openAdd('expense')">+ Kategori</button>
              <Link class="btn btn-ghost btn-sm shrink-0 gap-1.5" :href="route('personal')">
                <ArrowLeftIcon class="h-4 w-4" />
                Back
              </Link>
            </div>
          </div>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head flex items-center justify-between gap-2">
          <div>
            <h2 class="ocn-panel__title">Pemasukan</h2>
            <p class="ocn-panel__desc">Gaji, bonus, penghasilan lain.</p>
          </div>
          <button type="button" class="btn btn-outline btn-sm shrink-0" @click="openAdd('income')">+ Pemasukan</button>
        </div>
        <div class="overflow-x-auto">
          <table class="table table-zebra table-sm">
            <thead>
              <tr>
                <th>Nama</th>
                <th>Warna</th>
                <th>Transaksi</th>
                <th />
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in incomeRows" :key="row.id">
                <td class="font-medium">{{ row.name }}</td>
                <td>
                  <span v-if="row.color" class="inline-flex items-center gap-2">
                    <span class="inline-block h-4 w-4 rounded border border-base-300" :style="{ backgroundColor: row.color }" />
                    <span class="text-xs font-mono text-base-content/60">{{ row.color }}</span>
                  </span>
                  <span v-else class="text-base-content/40">—</span>
                </td>
                <td>{{ row.transaction_count }}</td>
                <td class="text-right whitespace-nowrap">
                  <button type="button" class="btn btn-ghost btn-xs" @click="openEdit(row)">Edit</button>
                  <button type="button" class="btn btn-ghost btn-xs text-error" @click="confirmDelete(row)">Hapus</button>
                </td>
              </tr>
              <tr v-if="!incomeRows.length">
                <td colspan="4" class="py-6 text-center text-base-content/50">Belum ada kategori pemasukan.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head flex items-center justify-between gap-2">
          <div>
            <h2 class="ocn-panel__title">Pengeluaran</h2>
            <p class="ocn-panel__desc">Belanja, tagihan, transport, dan lainnya.</p>
          </div>
          <button type="button" class="btn btn-outline btn-sm shrink-0" @click="openAdd('expense')">+ Pengeluaran</button>
        </div>
        <div class="overflow-x-auto">
          <table class="table table-zebra table-sm">
            <thead>
              <tr>
                <th>Nama</th>
                <th>Warna</th>
                <th>Transaksi</th>
                <th>Anggaran</th>
                <th />
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in expenseRows" :key="row.id">
                <td class="font-medium">{{ row.name }}</td>
                <td>
                  <span v-if="row.color" class="inline-flex items-center gap-2">
                    <span class="inline-block h-4 w-4 rounded border border-base-300" :style="{ backgroundColor: row.color }" />
                    <span class="text-xs font-mono text-base-content/60">{{ row.color }}</span>
                  </span>
                  <span v-else class="text-base-content/40">—</span>
                </td>
                <td>{{ row.transaction_count }}</td>
                <td>{{ row.budget_count }}</td>
                <td class="text-right whitespace-nowrap">
                  <button type="button" class="btn btn-ghost btn-xs" @click="openEdit(row)">Edit</button>
                  <button type="button" class="btn btn-ghost btn-xs text-error" @click="confirmDelete(row)">Hapus</button>
                </td>
              </tr>
              <tr v-if="!expenseRows.length">
                <td colspan="5" class="py-6 text-center text-base-content/50">Belum ada kategori pengeluaran.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <dialog id="modal-add-category" class="modal">
      <div class="modal-box max-w-md">
        <h3 class="font-bold text-lg">Tambah kategori</h3>
        <div class="mt-4 space-y-3">
          <div>
            <label class="label"><span class="label-text">Nama</span></label>
            <input v-model="addForm.name" type="text" class="input input-bordered w-full" />
            <p v-if="addForm.errors.name" class="text-error text-xs mt-1">{{ addForm.errors.name }}</p>
          </div>
          <div>
            <label class="label"><span class="label-text">Tipe</span></label>
            <select v-model="addForm.type" class="select select-bordered w-full">
              <option value="income">Pemasukan</option>
              <option value="expense">Pengeluaran</option>
            </select>
          </div>
          <div>
            <label class="label"><span class="label-text">Warna (opsional)</span></label>
            <input v-model="addForm.color" type="text" class="input input-bordered w-full" placeholder="#3b82f6" />
          </div>
        </div>
        <div class="modal-action">
          <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
          <button
            class="btn"
            :class="addForm.processing ? 'btn-secondary' : 'btn-primary'"
            :disabled="addForm.processing"
            @click="submitAdd"
          >Simpan</button>
        </div>
      </div>
    </dialog>

    <dialog id="modal-edit-category" class="modal">
      <div class="modal-box max-w-md">
        <h3 class="font-bold text-lg">Edit kategori</h3>
        <div class="mt-4 space-y-3">
          <div>
            <label class="label"><span class="label-text">Nama</span></label>
            <input v-model="editForm.name" type="text" class="input input-bordered w-full" />
            <p v-if="editForm.errors.name" class="text-error text-xs mt-1">{{ editForm.errors.name }}</p>
          </div>
          <div>
            <label class="label"><span class="label-text">Tipe</span></label>
            <select
              v-model="editForm.type"
              class="select select-bordered w-full"
              :disabled="editing?.transaction_count > 0 || editing?.budget_count > 0"
            >
              <option value="income">{{ typeLabel('income') }}</option>
              <option value="expense">{{ typeLabel('expense') }}</option>
            </select>
            <p v-if="editing?.transaction_count || editing?.budget_count" class="text-xs text-base-content/60 mt-1">
              Tipe tidak bisa diubah karena kategori sudah dipakai.
            </p>
          </div>
          <div>
            <label class="label"><span class="label-text">Warna (opsional)</span></label>
            <input v-model="editForm.color" type="text" class="input input-bordered w-full" placeholder="#3b82f6" />
          </div>
        </div>
        <div class="modal-action">
          <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
          <button
            class="btn"
            :class="editForm.processing ? 'btn-secondary' : 'btn-primary'"
            :disabled="editForm.processing"
            @click="submitEdit"
          >Simpan</button>
        </div>
      </div>
    </dialog>

    <ConfirmModal
      id="modal-delete-category"
      title="Hapus kategori"
      :message="deleteMessage"
      @confirm="doDelete"
    />
  </AppLayout>
</template>
