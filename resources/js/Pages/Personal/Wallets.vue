<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { computed, ref } from 'vue';

defineProps({
    wallets: Array,
    currencies: Array,
});

const money = (n) => `Rp ${Number(n ?? 0).toLocaleString('id-ID')}`;

const addForm = useForm({
    name: '',
    currency: 'IDR',
    sort_order: 0,
    is_default: false,
});

const openAdd = () => {
    addForm.clearErrors();
    addForm.reset('name', 'sort_order');
    addForm.currency = 'IDR';
    addForm.is_default = false;
    document.getElementById('modal-add-wallet')?.showModal();
};

const submitAdd = () => {
    addForm.transform((d) => ({
        ...d,
        sort_order: d.sort_order === '' ? 0 : Number(d.sort_order),
        is_default: !!d.is_default,
    })).post(route('personal.wallets.store'), {
        preserveScroll: true,
        onSuccess: () => document.getElementById('modal-add-wallet')?.close(),
    });
};

const editing = ref(null);
const editForm = useForm({
    name: '',
    currency: 'IDR',
    sort_order: 0,
    is_default: false,
});

const openEdit = (row) => {
    editing.value = row;
    editForm.clearErrors();
    editForm.name = row.name;
    editForm.currency = row.currency ?? 'IDR';
    editForm.sort_order = row.sort_order ?? 0;
    editForm.is_default = !!row.is_default;
    document.getElementById('modal-edit-wallet')?.showModal();
};

const submitEdit = () => {
    if (!editing.value) return;
    editForm.transform((d) => ({
        ...d,
        sort_order: d.sort_order === '' ? 0 : Number(d.sort_order),
        is_default: !!d.is_default,
    })).patch(route('personal.wallets.update', editing.value.id), {
        preserveScroll: true,
        onSuccess: () => document.getElementById('modal-edit-wallet')?.close(),
    });
};

const deleting = ref(null);
const deleteMessage = computed(() => {
    const row = deleting.value;
    if (!row) return '';
    if (!row.can_delete) {
        return `Dompet “${row.name}” masih memiliki ${row.transaction_count} transaksi dan tidak bisa dihapus.`;
    }
    return `Yakin hapus dompet “${row.name}”?`;
});

const confirmDelete = (row) => {
    if (!row.can_delete) return;
    deleting.value = row;
    document.getElementById('modal-delete-wallet')?.showModal();
};

const doDelete = () => {
    if (!deleting.value) return;
    router.delete(route('personal.wallets.destroy', deleting.value.id), {
        preserveScroll: true,
        onFinish: () => { deleting.value = null; },
    });
};
</script>

<template>
  <Head title="Personal — Dompet" />
  <AppLayout>
    <div class="space-y-5">
      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Personal</p>
              <h1 class="ocn-panel__title mt-1">Dompet</h1>
              <p class="ocn-panel__desc mt-1">
                Tentukan sumber dan tujuan uang: rekening bank, tunai, e-wallet. Setiap transaksi dicatat ke satu dompet.
              </p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
              <button type="button" class="btn btn-primary btn-sm" @click="openAdd">+ Dompet</button>
              <Link class="btn btn-ghost btn-sm shrink-0 gap-1.5" :href="route('personal')">
                <ArrowLeftIcon class="h-4 w-4" />
                Back
              </Link>
            </div>
          </div>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Daftar dompet</h2>
          <p class="ocn-panel__desc">Saldo = total pemasukan dikurangi pengeluaran pada dompet yang sama.</p>
        </div>
        <div class="overflow-x-auto">
          <table class="table table-zebra table-sm">
            <thead>
              <tr>
                <th>Nama</th>
                <th>Mata uang</th>
                <th>Saldo</th>
                <th>Transaksi</th>
                <th>Urutan</th>
                <th>Default</th>
                <th />
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in wallets" :key="row.id">
                <td class="font-medium">{{ row.name }}</td>
                <td>{{ row.currency }}</td>
                <td class="font-mono">{{ money(row.balance) }}</td>
                <td>{{ row.transaction_count }}</td>
                <td>{{ row.sort_order }}</td>
                <td>
                  <span v-if="row.is_default" class="badge badge-sm badge-primary">Utama</span>
                  <span v-else class="text-base-content/40">—</span>
                </td>
                <td class="text-right whitespace-nowrap">
                  <button type="button" class="btn btn-ghost btn-xs" @click="openEdit(row)">Edit</button>
                  <button
                    type="button"
                    class="btn btn-ghost btn-xs text-error"
                    :class="{ 'btn-disabled': !row.can_delete }"
                    :disabled="!row.can_delete"
                    :title="row.can_delete ? 'Hapus' : 'Masih ada transaksi'"
                    @click="confirmDelete(row)"
                  >Hapus</button>
                </td>
              </tr>
              <tr v-if="!(wallets && wallets.length)">
                <td colspan="7" class="py-8 text-center text-base-content/50">Belum ada dompet.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <dialog id="modal-add-wallet" class="modal">
      <div class="modal-box max-w-md">
        <h3 class="font-bold text-lg">Tambah dompet</h3>
        <div class="mt-4 space-y-3">
          <div>
            <label class="label"><span class="label-text">Nama</span></label>
            <input v-model="addForm.name" type="text" class="input input-bordered w-full" placeholder="BCA, Tunai, GoPay…" />
            <p v-if="addForm.errors.name" class="text-error text-xs mt-1">{{ addForm.errors.name }}</p>
          </div>
          <div>
            <label class="label"><span class="label-text">Mata uang</span></label>
            <select v-model="addForm.currency" class="select select-bordered w-full">
              <option v-for="c in currencies" :key="c.value" :value="c.value">{{ c.label }}</option>
            </select>
          </div>
          <div>
            <label class="label"><span class="label-text">Urutan tampil</span></label>
            <input v-model="addForm.sort_order" type="number" min="0" class="input input-bordered w-full" />
          </div>
          <label class="label cursor-pointer justify-start gap-3">
            <input v-model="addForm.is_default" type="checkbox" class="checkbox checkbox-sm" />
            <span class="label-text">Jadikan dompet utama (default)</span>
          </label>
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

    <dialog id="modal-edit-wallet" class="modal">
      <div class="modal-box max-w-md">
        <h3 class="font-bold text-lg">Edit dompet</h3>
        <div class="mt-4 space-y-3">
          <div>
            <label class="label"><span class="label-text">Nama</span></label>
            <input v-model="editForm.name" type="text" class="input input-bordered w-full" />
            <p v-if="editForm.errors.name" class="text-error text-xs mt-1">{{ editForm.errors.name }}</p>
          </div>
          <div>
            <label class="label"><span class="label-text">Mata uang</span></label>
            <select v-model="editForm.currency" class="select select-bordered w-full">
              <option v-for="c in currencies" :key="c.value" :value="c.value">{{ c.label }}</option>
            </select>
          </div>
          <div>
            <label class="label"><span class="label-text">Urutan tampil</span></label>
            <input v-model="editForm.sort_order" type="number" min="0" class="input input-bordered w-full" />
          </div>
          <label class="label cursor-pointer justify-start gap-3">
            <input v-model="editForm.is_default" type="checkbox" class="checkbox checkbox-sm" />
            <span class="label-text">Dompet utama (default)</span>
          </label>
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
      id="modal-delete-wallet"
      title="Hapus dompet"
      :message="deleteMessage"
      @confirm="doDelete"
    />
  </AppLayout>
</template>
