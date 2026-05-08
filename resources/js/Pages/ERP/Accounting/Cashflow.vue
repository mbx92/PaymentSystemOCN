<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CurrencyInput from '@/Components/CurrencyInput.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useCurrency } from '@/composables/useCurrency';

const props = defineProps({
  entries: Array,
  totals: Object,
  projects: Array,
  paymentMethods: Array,
  cashAccounts: Array,
  filters: Object,
  categoryOptions: Object,
});

const { format } = useCurrency();
const filters = ref({
  type: props.filters?.type ?? '',
  project_id: props.filters?.project_id ?? '',
  category: props.filters?.category ?? '',
  date_from: props.filters?.date_from ?? '',
  date_to: props.filters?.date_to ?? '',
  q: props.filters?.q ?? '',
});

let timer;
watch(filters, (val) => {
  clearTimeout(timer);
  timer = setTimeout(() => {
    router.get(route('erp.accounting.cashflow'), val, { preserveState: true, replace: true });
  }, 350);
}, { deep: true });

const typeBadgeClass = (type) => (type === 'in' ? 'badge-success' : 'badge-error');
const typeLabel = (type) => (type === 'in' ? 'Kas Masuk' : 'Kas Keluar');

const categoryLabelMap = computed(() => {
  const labels = {};
  for (const opt of props.categoryOptions?.in ?? []) labels[opt.value] = opt.label;
  for (const opt of props.categoryOptions?.out ?? []) labels[opt.value] = opt.label;
  return labels;
});

const form = useForm({
  type: 'in',
  project_id: '',
  cash_account_id: '',
  payment_method_id: '',
  category: 'pendapatan_jasa',
  amount: 0,
  date: new Date().toISOString().slice(0, 10),
  recipient_name: '',
  note: '',
});

const dynamicCategoryOptions = computed(() => (
  form.type === 'in' ? (props.categoryOptions?.in ?? []) : (props.categoryOptions?.out ?? [])
));

watch(() => form.type, (type) => {
  if (type === 'in') {
    form.category = 'pendapatan_jasa';
    form.recipient_name = '';
  } else {
    form.category = 'biaya_tim';
    form.payment_method_id = '';
  }
});

const openAddModal = () => {
  form.reset();
  form.type = 'in';
  form.project_id = '';
  form.cash_account_id = props.cashAccounts?.[0]?.id ?? '';
  form.payment_method_id = '';
  form.category = 'pendapatan_jasa';
  form.amount = 0;
  form.date = new Date().toISOString().slice(0, 10);
  form.recipient_name = '';
  form.note = '';
  document.getElementById('modal-cashflow-entry')?.showModal();
};

const submitEntry = () => {
  form.post(route('erp.accounting.cashflow.store'), {
    preserveScroll: true,
    onSuccess: () => document.getElementById('modal-cashflow-entry')?.close(),
  });
};

const editForm = useForm({
  id: '',
  type: 'in',
  project_id: '',
  cash_account_id: '',
  payment_method_id: '',
  category: '',
  amount: 0,
  date: '',
  recipient_name: '',
  note: '',
});

const deleting = useForm({});
const deletingEntry = ref(null);
const selectedEntry = ref(null);

const openDetailModal = (entry) => {
  selectedEntry.value = entry;
  document.getElementById('modal-cashflow-detail')?.showModal();
};

const openEditModal = (entry) => {
  editForm.id = entry.id;
  editForm.type = entry.type;
  editForm.project_id = entry.project_id || '';
  editForm.cash_account_id = entry.cash_account_id || props.cashAccounts?.[0]?.id || '';
  editForm.payment_method_id = entry.payment_method_id || '';
  editForm.category = entry.category;
  editForm.amount = entry.amount;
  editForm.date = entry.date;
  editForm.recipient_name = entry.recipient_name || '';
  editForm.note = entry.note || '';
  document.getElementById('modal-cashflow-edit')?.showModal();
};

const submitEdit = () => {
  const routeName = editForm.type === 'in'
    ? route('erp.accounting.cashflow.cash-in.update', editForm.id)
    : route('erp.accounting.cashflow.cash-out.update', editForm.id);
  editForm.patch(routeName, {
    preserveScroll: true,
    onSuccess: () => document.getElementById('modal-cashflow-edit')?.close(),
  });
};

const destroyEntry = (entry) => {
  deletingEntry.value = entry;
  document.getElementById('modal-delete-cashflow-entry')?.showModal();
};

const confirmDestroyEntry = () => {
  if (!deletingEntry.value) return;
  const routeName = deletingEntry.value.type === 'in'
    ? route('erp.accounting.cashflow.cash-in.destroy', deletingEntry.value.id)
    : route('erp.accounting.cashflow.cash-out.destroy', deletingEntry.value.id);
  deleting.delete(routeName, {
    preserveScroll: true,
    onSuccess: () => { deletingEntry.value = null; selectedEntry.value = null; },
  });
};
</script>

<template>
  <Head title="Accounting - Cashflow" />
  <AppLayout>
    <div class="space-y-5">
      <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Accounting Workspace</p>
        <div class="mt-2 flex items-center justify-between gap-3">
          <h1 class="text-3xl font-bold tracking-tight">Cashflow</h1>
          <div class="flex items-center gap-2">
            <button class="btn btn-primary btn-sm" @click="openAddModal">+ Input Transaksi</button>
            <Link class="btn btn-ghost btn-sm" :href="route('erp.accounting')">Back</Link>
          </div>
        </div>
        <p class="mt-2 text-sm text-base-content/70">Kas masuk dan kas keluar dalam satu tabel interaktif.</p>
      </div>

      <div class="grid gap-3 md:grid-cols-3">
        <div class="ocn-panel">
          <div class="ocn-panel__head py-3"><h2 class="ocn-panel__title text-sm font-medium">Total kas masuk</h2></div>
          <div class="card-body py-4">
            <p class="text-xl font-bold text-success">{{ format(totals.cash_in || 0) }}</p>
          </div>
        </div>
        <div class="ocn-panel">
          <div class="ocn-panel__head py-3"><h2 class="ocn-panel__title text-sm font-medium">Total kas keluar</h2></div>
          <div class="card-body py-4">
            <p class="text-xl font-bold text-error">{{ format(totals.cash_out || 0) }}</p>
          </div>
        </div>
        <div class="ocn-panel">
          <div class="ocn-panel__head py-3"><h2 class="ocn-panel__title text-sm font-medium">Net cashflow</h2></div>
          <div class="card-body py-4">
            <p :class="['text-xl font-bold', (totals.net || 0) >= 0 ? 'text-primary' : 'text-error']">{{ format(totals.net || 0) }}</p>
          </div>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Filter cashflow</h2>
        </div>
        <div class="card-body">
          <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
            <select v-model="filters.type" class="select select-bordered select-sm w-full">
              <option value="">Semua Jenis</option>
              <option value="in">Kas Masuk</option>
              <option value="out">Kas Keluar</option>
            </select>
            <select v-model="filters.project_id" class="select select-bordered select-sm w-full">
              <option value="">Semua Project</option>
              <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
            <select v-model="filters.category" class="select select-bordered select-sm w-full">
              <option value="">Semua Kategori</option>
              <option v-for="(label, key) in categoryLabelMap" :key="key" :value="key">{{ label }}</option>
            </select>
            <input v-model="filters.date_from" type="date" class="input input-bordered input-sm w-full" />
            <input v-model="filters.date_to" type="date" class="input input-bordered input-sm w-full" />
            <input v-model="filters.q" type="text" class="input input-bordered input-sm w-full" placeholder="Cari project/catatan/penerima..." />
          </div>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head flex items-center justify-between gap-2">
          <h2 class="ocn-panel__title">Daftar cashflow</h2>
          <span class="text-xs text-base-content/60">{{ entries.length }} transaksi</span>
        </div>
        <div class="overflow-x-auto">
          <table class="table table-zebra">
            <thead>
              <tr>
                <th>Tanggal</th>
                <th>Jenis</th>
                <th>Project</th>
                <th>Kategori</th>
                <th>Metode / Penerima</th>
                <th>Jumlah</th>
                <th>Status</th>
                <th>Jurnal</th>
                <th>Keterangan</th>
                <th>Oleh</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="entry in entries"
                :key="`${entry.type}-${entry.id}`"
                class="cursor-pointer hover:bg-base-200/60"
                @click="openDetailModal(entry)"
              >
                <td>{{ entry.date }}</td>
                <td>
                  <span class="badge badge-sm" :class="typeBadgeClass(entry.type)">{{ typeLabel(entry.type) }}</span>
                </td>
                <td class="font-medium">{{ entry.project_name }}</td>
                <td><span class="badge badge-ghost badge-sm">{{ categoryLabelMap[entry.category] ?? entry.category }}</span></td>
                <td>{{ entry.type === 'in' ? (entry.payment_method_name || '-') : (entry.recipient_name || '-') }}</td>
                <td :class="['font-semibold', entry.type === 'in' ? 'text-success' : 'text-error']">{{ format(entry.amount) }}</td>
                <td><StatusBadge :status="entry.document_status" /></td>
                <td class="font-mono text-xs">{{ entry.journal_entry_id ?? '-' }}</td>
                <td class="max-w-xs truncate text-sm text-base-content/70">{{ entry.note || '-' }}</td>
                <td class="text-sm text-base-content/70">{{ entry.creator_name }}</td>
              </tr>
              <tr v-if="!entries.length">
                <td colspan="10" class="py-10 text-center text-base-content/50">Belum ada transaksi sesuai filter.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <dialog id="modal-cashflow-entry" class="modal">
      <div class="modal-box max-w-xl">
        <h3 class="font-bold text-lg">Input Transaksi Cashflow</h3>
        <div class="mt-4 space-y-3">
          <div>
            <label class="label"><span class="label-text">Jenis Transaksi</span></label>
            <select v-model="form.type" class="select select-bordered w-full">
              <option value="in">Kas Masuk</option>
              <option value="out">Kas Keluar</option>
            </select>
          </div>
          <div>
            <label class="label"><span class="label-text">Project <span class="text-error">*</span></span></label>
            <select v-model="form.project_id" class="select select-bordered w-full">
              <option value="">Operasional umum (tanpa project)</option>
              <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
            <p v-if="form.errors.project_id" class="text-error text-xs mt-1">{{ form.errors.project_id }}</p>
          </div>
          <div>
            <label class="label"><span class="label-text">Sumber Dana Kas/Bank <span class="text-error">*</span></span></label>
            <select v-model="form.cash_account_id" class="select select-bordered w-full">
              <option value="" disabled>-- Pilih Akun Kas/Bank --</option>
              <option v-for="acc in cashAccounts" :key="acc.id" :value="acc.id">{{ acc.code }} - {{ acc.name }}</option>
            </select>
            <p v-if="form.errors.cash_account_id" class="text-error text-xs mt-1">{{ form.errors.cash_account_id }}</p>
          </div>
          <div>
            <label class="label"><span class="label-text">Kategori</span></label>
            <select v-model="form.category" class="select select-bordered w-full">
              <option v-for="opt in dynamicCategoryOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
            <p v-if="form.errors.category" class="text-error text-xs mt-1">{{ form.errors.category }}</p>
          </div>
          <div v-if="form.type === 'in'">
            <label class="label"><span class="label-text">Metode Pembayaran</span></label>
            <select v-model="form.payment_method_id" class="select select-bordered w-full">
              <option value="">-- Pilih Metode --</option>
              <option v-for="method in paymentMethods" :key="method.id" :value="method.id">{{ method.name }}</option>
            </select>
            <p v-if="form.errors.payment_method_id" class="text-error text-xs mt-1">{{ form.errors.payment_method_id }}</p>
          </div>
          <div v-else>
            <label class="label"><span class="label-text">Penerima</span></label>
            <input v-model="form.recipient_name" type="text" class="input input-bordered w-full" placeholder="Nama penerima (opsional)" />
            <p v-if="form.errors.recipient_name" class="text-error text-xs mt-1">{{ form.errors.recipient_name }}</p>
          </div>
          <CurrencyInput v-model="form.amount" label="Jumlah" :required="true" :error="form.errors.amount" />
          <div>
            <label class="label"><span class="label-text">Tanggal</span></label>
            <input v-model="form.date" type="date" class="input input-bordered w-full" />
            <p v-if="form.errors.date" class="text-error text-xs mt-1">{{ form.errors.date }}</p>
          </div>
          <div>
            <label class="label"><span class="label-text">Keterangan</span></label>
            <textarea v-model="form.note" class="textarea textarea-bordered w-full" rows="3" placeholder="Catatan transaksi (opsional)" />
            <p v-if="form.errors.note" class="text-error text-xs mt-1">{{ form.errors.note }}</p>
          </div>
        </div>
        <div class="modal-action">
          <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
          <button class="btn btn-primary" :disabled="form.processing" @click="submitEntry">Simpan</button>
        </div>
      </div>
    </dialog>

    <dialog id="modal-cashflow-detail" class="modal">
      <div class="modal-box max-w-2xl">
        <h3 class="font-bold text-lg">Detail Transaksi Cashflow</h3>
        <div v-if="selectedEntry" class="mt-4 grid grid-cols-2 gap-3 text-sm">
          <div class="text-base-content/60">Tanggal</div><div>{{ selectedEntry.date }}</div>
          <div class="text-base-content/60">Jenis</div><div>{{ typeLabel(selectedEntry.type) }}</div>
          <div class="text-base-content/60">Project</div><div>{{ selectedEntry.project_name }}</div>
          <div class="text-base-content/60">Kategori</div><div>{{ categoryLabelMap[selectedEntry.category] ?? selectedEntry.category }}</div>
          <div class="text-base-content/60">Sumber Dana</div><div>{{ selectedEntry.cash_account_name || '-' }}</div>
          <div class="text-base-content/60">Metode/Penerima</div><div>{{ selectedEntry.type === 'in' ? (selectedEntry.payment_method_name || '-') : (selectedEntry.recipient_name || '-') }}</div>
          <div class="text-base-content/60">Jumlah</div><div :class="selectedEntry.type === 'in' ? 'text-success font-semibold' : 'text-error font-semibold'">{{ format(selectedEntry.amount) }}</div>
          <div class="text-base-content/60">Status</div><div><StatusBadge :status="selectedEntry.document_status" /></div>
          <div class="text-base-content/60">Jurnal</div><div class="font-mono text-xs">{{ selectedEntry.journal_entry_id || '-' }}</div>
          <div class="text-base-content/60">Keterangan</div><div>{{ selectedEntry.note || '-' }}</div>
          <div class="text-base-content/60">Dicatat Oleh</div><div>{{ selectedEntry.creator_name }}</div>
        </div>
        <div class="modal-action">
          <button class="btn btn-outline" @click="selectedEntry && openEditModal(selectedEntry)">Edit</button>
          <button class="btn btn-error" @click="selectedEntry && destroyEntry(selectedEntry)">Hapus</button>
          <form method="dialog"><button class="btn btn-ghost">Tutup</button></form>
        </div>
      </div>
    </dialog>

    <ConfirmModal
      id="modal-delete-cashflow-entry"
      title="Hapus transaksi cashflow"
      message="Data transaksi akan dihapus permanen. Lanjutkan?"
      confirm-text="Hapus"
      confirm-class="btn-error"
      @confirm="confirmDestroyEntry"
    />

    <dialog id="modal-cashflow-edit" class="modal">
      <div class="modal-box max-w-xl">
        <h3 class="font-bold text-lg">Edit Transaksi Cashflow</h3>
        <div class="mt-4 space-y-3">
          <div>
            <label class="label"><span class="label-text">Jenis Transaksi</span></label>
            <select v-model="editForm.type" class="select select-bordered w-full" disabled>
              <option value="in">Kas Masuk</option>
              <option value="out">Kas Keluar</option>
            </select>
          </div>
          <div>
            <label class="label"><span class="label-text">Project</span></label>
            <select v-model="editForm.project_id" class="select select-bordered w-full">
              <option value="">Operasional umum (tanpa project)</option>
              <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
          </div>
          <div>
            <label class="label"><span class="label-text">Sumber Dana Kas/Bank</span></label>
            <select v-model="editForm.cash_account_id" class="select select-bordered w-full">
              <option v-for="acc in cashAccounts" :key="acc.id" :value="acc.id">{{ acc.code }} - {{ acc.name }}</option>
            </select>
          </div>
          <div>
            <label class="label"><span class="label-text">Kategori</span></label>
            <select v-model="editForm.category" class="select select-bordered w-full">
              <option v-for="opt in (editForm.type === 'in' ? categoryOptions.in : categoryOptions.out)" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
          </div>
          <div v-if="editForm.type === 'in'">
            <label class="label"><span class="label-text">Metode Pembayaran</span></label>
            <select v-model="editForm.payment_method_id" class="select select-bordered w-full">
              <option value="">-- Pilih Metode --</option>
              <option v-for="method in paymentMethods" :key="method.id" :value="method.id">{{ method.name }}</option>
            </select>
          </div>
          <div v-else>
            <label class="label"><span class="label-text">Penerima</span></label>
            <input v-model="editForm.recipient_name" type="text" class="input input-bordered w-full" />
          </div>
          <CurrencyInput v-model="editForm.amount" label="Jumlah" :required="true" :error="editForm.errors.amount" />
          <div>
            <label class="label"><span class="label-text">Tanggal</span></label>
            <input v-model="editForm.date" type="date" class="input input-bordered w-full" />
          </div>
          <div>
            <label class="label"><span class="label-text">Keterangan</span></label>
            <textarea v-model="editForm.note" class="textarea textarea-bordered w-full" rows="3" />
          </div>
        </div>
        <div class="modal-action">
          <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
          <button class="btn btn-primary" :disabled="editForm.processing" @click="submitEdit">Simpan</button>
        </div>
      </div>
    </dialog>
  </AppLayout>
</template>
