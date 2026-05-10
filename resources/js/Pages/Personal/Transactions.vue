<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    wallets: Array,
    categories: Array,
    transactions: Array,
});

const money = (n) => `Rp ${Number(n ?? 0).toLocaleString('id-ID')}`;

const incomeCategories = computed(() => (props.categories ?? []).filter((c) => c.type === 'income'));
const expenseCategories = computed(() => (props.categories ?? []).filter((c) => c.type === 'expense'));

const categoryForm = useForm({
    name: '',
    type: 'expense',
    color: '',
});

const submitCategory = () => {
    categoryForm.post(route('personal.categories.store'), {
        preserveScroll: true,
        onSuccess: () => {
            categoryForm.reset('name', 'color');
            categoryForm.type = 'expense';
            document.getElementById('modal-add-category')?.close();
        },
    });
};

const txForm = useForm({
    wallet_id: '',
    category_id: '',
    type: 'expense',
    amount: '',
    occurred_on: new Date().toISOString().slice(0, 10),
    note: '',
});

const openAddTx = () => {
    txForm.clearErrors();
    txForm.reset('wallet_id', 'category_id', 'amount', 'note');
    txForm.type = 'expense';
    txForm.occurred_on = new Date().toISOString().slice(0, 10);
    document.getElementById('modal-add-transaction')?.showModal();
};

const submitTx = () => {
    txForm.transform((d) => ({
        ...d,
        wallet_id: d.wallet_id ? Number(d.wallet_id) : '',
        category_id: d.category_id ? Number(d.category_id) : null,
        amount: d.amount === '' ? '' : Number(d.amount),
    })).post(route('personal.transactions.store'), {
        preserveScroll: true,
        onSuccess: () => {
            document.getElementById('modal-add-transaction')?.close();
        },
    });
};

const editing = ref(null);
const editForm = useForm({
    wallet_id: '',
    category_id: '',
    type: 'expense',
    amount: '',
    occurred_on: '',
    note: '',
});

const openEdit = (row) => {
    editing.value = row;
    editForm.clearErrors();
    editForm.wallet_id = row.wallet_id != null ? String(row.wallet_id) : '';
    editForm.category_id = row.category_id != null ? String(row.category_id) : '';
    editForm.type = row.type;
    editForm.amount = String(row.amount);
    editForm.occurred_on = row.occurred_on;
    editForm.note = row.note ?? '';
    document.getElementById('modal-edit-transaction')?.showModal();
};

const submitEdit = () => {
    if (!editing.value) return;
    editForm.transform((d) => ({
        ...d,
        wallet_id: d.wallet_id ? Number(d.wallet_id) : '',
        category_id: d.category_id ? Number(d.category_id) : null,
        amount: d.amount === '' ? '' : Number(d.amount),
    })).patch(route('personal.transactions.update', editing.value.id), {
        preserveScroll: true,
        onSuccess: () => document.getElementById('modal-edit-transaction')?.close(),
    });
};

const deletingTransaction = ref(null);
const deleteTxMessage = computed(() => {
    const row = deletingTransaction.value;
    if (!row) return '';
    return `Yakin hapus transaksi ${row.type} ${money(row.amount)} tanggal ${row.occurred_on}?`;
});

const confirmDeleteTx = (row) => {
    deletingTransaction.value = row;
    document.getElementById('modal-delete-personal-transaction')?.showModal();
};

const doDeleteTx = () => {
    if (!deletingTransaction.value) return;
    router.delete(route('personal.transactions.destroy', deletingTransaction.value.id), {
        preserveScroll: true,
        onFinish: () => { deletingTransaction.value = null; },
    });
};

const categoryOptions = computed(() => (txForm.type === 'income' ? incomeCategories.value : expenseCategories.value));
const editCategoryOptions = computed(() => (editForm.type === 'income' ? incomeCategories.value : expenseCategories.value));
</script>

<template>
  <Head title="Personal — Transaksi" />
  <AppLayout>
    <div class="space-y-5">
      <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Personal</p>
        <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
          <div>
            <h1 class="text-3xl font-bold tracking-tight">Pemasukan &amp; pengeluaran</h1>
            <p class="mt-2 text-sm text-base-content/70">Transaksi terbaru (200 entri) dan kategori kustom.</p>
          </div>
          <div class="flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline btn-sm" @click="document.getElementById('modal-add-category')?.showModal()">+ Kategori</button>
            <button type="button" class="btn btn-primary btn-sm" @click="openAddTx">+ Transaksi</button>
            <Link class="btn btn-ghost btn-sm" :href="route('personal')">Back</Link>
          </div>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Daftar transaksi</h2>
        </div>
        <div class="overflow-x-auto">
          <table class="table table-zebra table-sm">
            <thead>
              <tr>
                <th>Tanggal</th>
                <th>Tipe</th>
                <th>Jumlah</th>
                <th>Dompet</th>
                <th>Kategori</th>
                <th>Catatan</th>
                <th />
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in transactions" :key="row.id">
                <td class="whitespace-nowrap font-mono text-xs">{{ row.occurred_on }}</td>
                <td>
                  <span class="badge badge-sm" :class="row.type === 'income' ? 'badge-success' : 'badge-error'">{{ row.type }}</span>
                </td>
                <td class="font-mono">{{ money(row.amount) }}</td>
                <td>{{ row.wallet ?? '—' }}</td>
                <td>{{ row.category ?? '—' }}</td>
                <td class="max-w-[200px] truncate text-sm text-base-content/70">{{ row.note || '—' }}</td>
                <td class="text-right">
                  <button type="button" class="btn btn-ghost btn-xs" @click="openEdit(row)">Edit</button>
                  <button type="button" class="btn btn-ghost btn-xs text-error" @click="confirmDeleteTx(row)">Hapus</button>
                </td>
              </tr>
              <tr v-if="!(transactions && transactions.length)">
                <td colspan="7" class="py-8 text-center text-base-content/50">Belum ada transaksi.</td>
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
            <input v-model="categoryForm.name" type="text" class="input input-bordered w-full" />
            <p v-if="categoryForm.errors.name" class="text-error text-xs mt-1">{{ categoryForm.errors.name }}</p>
          </div>
          <div>
            <label class="label"><span class="label-text">Tipe</span></label>
            <select v-model="categoryForm.type" class="select select-bordered w-full">
              <option value="income">Pemasukan</option>
              <option value="expense">Pengeluaran</option>
            </select>
          </div>
          <div>
            <label class="label"><span class="label-text">Warna (opsional)</span></label>
            <input v-model="categoryForm.color" type="text" class="input input-bordered w-full" placeholder="#3b82f6" />
          </div>
        </div>
        <div class="modal-action">
          <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
          <button
            class="btn"
            :class="categoryForm.processing ? 'btn-secondary' : 'btn-primary'"
            :disabled="categoryForm.processing"
            @click="submitCategory"
          >Simpan</button>
        </div>
      </div>
    </dialog>

    <dialog id="modal-add-transaction" class="modal">
      <div class="modal-box max-w-lg">
        <h3 class="font-bold text-lg">Transaksi baru</h3>
        <div class="mt-4 grid gap-3 sm:grid-cols-2">
          <div class="sm:col-span-2">
            <label class="label"><span class="label-text">Tipe</span></label>
            <select v-model="txForm.type" class="select select-bordered w-full">
              <option value="income">Pemasukan</option>
              <option value="expense">Pengeluaran</option>
            </select>
          </div>
          <div>
            <label class="label"><span class="label-text">Dompet</span></label>
            <select v-model="txForm.wallet_id" class="select select-bordered w-full">
              <option value="" disabled>Pilih</option>
              <option v-for="w in wallets" :key="w.id" :value="String(w.id)">{{ w.name }}</option>
            </select>
            <p v-if="txForm.errors.wallet_id" class="text-error text-xs mt-1">{{ txForm.errors.wallet_id }}</p>
          </div>
          <div>
            <label class="label"><span class="label-text">Kategori</span></label>
            <select v-model="txForm.category_id" class="select select-bordered w-full">
              <option value="">(tanpa kategori)</option>
              <option v-for="c in categoryOptions" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
            </select>
          </div>
          <div>
            <label class="label"><span class="label-text">Jumlah</span></label>
            <input v-model="txForm.amount" type="number" min="0.01" step="0.01" class="input input-bordered w-full" />
            <p v-if="txForm.errors.amount" class="text-error text-xs mt-1">{{ txForm.errors.amount }}</p>
          </div>
          <div>
            <label class="label"><span class="label-text">Tanggal</span></label>
            <input v-model="txForm.occurred_on" type="date" class="input input-bordered w-full" />
          </div>
          <div class="sm:col-span-2">
            <label class="label"><span class="label-text">Catatan</span></label>
            <input v-model="txForm.note" type="text" class="input input-bordered w-full" />
          </div>
        </div>
        <div class="modal-action">
          <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
          <button
            class="btn"
            :class="txForm.processing ? 'btn-secondary' : 'btn-primary'"
            :disabled="txForm.processing"
            @click="submitTx"
          >Simpan</button>
        </div>
      </div>
    </dialog>

    <dialog id="modal-edit-transaction" class="modal">
      <div class="modal-box max-w-lg">
        <h3 class="font-bold text-lg">Edit transaksi</h3>
        <div class="mt-4 grid gap-3 sm:grid-cols-2">
          <div class="sm:col-span-2">
            <label class="label"><span class="label-text">Tipe</span></label>
            <select v-model="editForm.type" class="select select-bordered w-full">
              <option value="income">Pemasukan</option>
              <option value="expense">Pengeluaran</option>
            </select>
          </div>
          <div>
            <label class="label"><span class="label-text">Dompet</span></label>
            <select v-model="editForm.wallet_id" class="select select-bordered w-full">
              <option v-for="w in wallets" :key="w.id" :value="String(w.id)">{{ w.name }}</option>
            </select>
            <p v-if="editForm.errors.wallet_id" class="text-error text-xs mt-1">{{ editForm.errors.wallet_id }}</p>
          </div>
          <div>
            <label class="label"><span class="label-text">Kategori</span></label>
            <select v-model="editForm.category_id" class="select select-bordered w-full">
              <option value="">(tanpa kategori)</option>
              <option v-for="c in editCategoryOptions" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
            </select>
          </div>
          <div>
            <label class="label"><span class="label-text">Jumlah</span></label>
            <input v-model="editForm.amount" type="number" min="0.01" step="0.01" class="input input-bordered w-full" />
          </div>
          <div>
            <label class="label"><span class="label-text">Tanggal</span></label>
            <input v-model="editForm.occurred_on" type="date" class="input input-bordered w-full" />
          </div>
          <div class="sm:col-span-2">
            <label class="label"><span class="label-text">Catatan</span></label>
            <input v-model="editForm.note" type="text" class="input input-bordered w-full" />
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
      id="modal-delete-personal-transaction"
      title="Hapus transaksi"
      :message="deleteTxMessage"
      @confirm="doDeleteTx"
    />
  </AppLayout>
</template>
