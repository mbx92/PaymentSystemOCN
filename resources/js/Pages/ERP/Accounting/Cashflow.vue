<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CurrencyInput from '@/Components/CurrencyInput.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { computed, ref, watch } from 'vue';
import { useCurrency } from '@/composables/useCurrency';
import { showGlobalAlert } from '@/utils/globalAlert';

const props = defineProps({
  entries: Array,
  totals: Object,
  projects: Array,
  paymentMethods: Array,
  cashAccounts: Array,
  filters: Object,
  sourceOptions: Array,
  categoryOptions: Object,
  canMutate: Boolean,
});

const { format } = useCurrency();
const page = usePage();
const erpCompanyContext = () => page.props.erpCompanyContext ?? null;
const firstBackendError = (errors, fallback) => {
  const first = Object.values(errors ?? {})[0];
  if (Array.isArray(first)) return first[0] ?? fallback;
  return typeof first === 'string' && first ? first : fallback;
};

const filters = ref({
  type: props.filters?.type ?? '',
  source: props.filters?.source ?? '',
  project_id: props.filters?.project_id ?? '',
  category: props.filters?.category ?? '',
  company_id: props.filters?.company_id ?? erpCompanyContext()?.current_company_id ?? '',
  date_from: props.filters?.date_from ?? '',
  date_to: props.filters?.date_to ?? '',
  q: props.filters?.q ?? '',
});

let timer;
watch(filters, (val) => {
  clearTimeout(timer);
  timer = setTimeout(() => {
    router.get(route('erp.accounting.cashflow'), val, {
      preserveState: true,
      replace: true,
      onError: (errors) => showGlobalAlert(firstBackendError(errors, 'Gagal memuat data cashflow.'), 'error'),
    });
  }, 350);
}, { deep: true });

const typeBadgeClass = (type) => (type === 'in' ? 'badge-success' : 'badge-error');
const typeLabel = (type) => (type === 'in' ? 'Masuk' : 'Keluar');
const sourceLabel = (source) => {
  const found = (props.sourceOptions ?? []).find((opt) => opt.value === source);
  return found?.label ?? (source || 'Manual / Umum');
};

const formatDate = (dateStr) => {
  if (!dateStr) return '-';
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
  const d = new Date(dateStr);
  return `${d.getDate()} ${months[d.getMonth()]} ${d.getFullYear()}`;
};

const categoryLabelMap = computed(() => {
  const labels = {};
  for (const opt of props.categoryOptions?.in ?? []) labels[opt.value] = opt.label;
  for (const opt of props.categoryOptions?.out ?? []) labels[opt.value] = opt.label;
  return labels;
});

const defaultInCategory = computed(() => props.categoryOptions?.in?.[0]?.value ?? '');
const defaultOutCategory = computed(() => props.categoryOptions?.out?.[0]?.value ?? '');
const cashAccountLabel = (type) => (type === 'in' ? 'Tujuan Dana Kas/Bank' : 'Sumber Dana Kas/Bank');

const form = useForm({
  type: 'in',
  cash_account_id: '',
  payment_method_id: '',
  category: defaultInCategory.value,
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
    form.category = defaultInCategory.value;
    form.recipient_name = '';
  } else {
    form.category = defaultOutCategory.value;
    form.payment_method_id = '';
  }
});

const openAddModal = () => {
  if (!props.canMutate) {
    showGlobalAlert('Anda tidak memiliki izin untuk menambah transaksi cashflow.', 'warning');
    return;
  }

  form.reset();
  form.type = 'out';
  form.cash_account_id = props.cashAccounts?.[0]?.id ?? '';
  form.payment_method_id = '';
  form.category = defaultOutCategory.value;
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
    onError: (errors) => showGlobalAlert(firstBackendError(errors, 'Transaksi cashflow gagal disimpan.'), 'error'),
  });
};

const editForm = useForm({
  id: '',
  type: 'in',
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
  if (!props.canMutate || entry.mutable === false) {
    showGlobalAlert('Transaksi ini tidak bisa diubah dari halaman cashflow.', 'warning');
    return;
  }

  editForm.id = entry.id;
  editForm.type = entry.type;
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
    onError: (errors) => showGlobalAlert(firstBackendError(errors, 'Transaksi cashflow gagal diperbarui.'), 'error'),
  });
};

const destroyEntry = (entry) => {
  if (!props.canMutate || entry.mutable === false) {
    showGlobalAlert('Transaksi ini tidak bisa dihapus dari halaman cashflow.', 'warning');
    return;
  }

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
    onError: (errors) => showGlobalAlert(firstBackendError(errors, 'Transaksi cashflow gagal dihapus.'), 'error'),
  });
};
</script>

<template>
  <Head title="Accounting - Cashflow" />
  <AppLayout>
    <div class="space-y-5">
      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Accounting Workspace</p>
              <h1 class="ocn-panel__title mt-1">Cashflow Accounting</h1>
              <p class="ocn-panel__desc mt-1">Ringkasan arus kas dari invoice/termin, POS, pembayaran supplier, pembayaran anggota, dan expenses.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
              <div class="flex items-center gap-2">
            <button v-if="canMutate" class="btn btn-primary btn-sm" @click="openAddModal">+ Input Manual Accounting</button>
            <Link class="btn btn-ghost btn-sm shrink-0 gap-1.5" :href="route('erp.accounting')">
            <ArrowLeftIcon class="h-4 w-4" />
            Back
          </Link>
          </div>
            </div>
          </div>
        </div>
      </div>

      <div class="grid gap-3 md:grid-cols-3">
        <div class="ocn-panel">
          <div class="ocn-panel__head py-3"><h2 class="ocn-panel__title text-sm font-medium">Total masuk</h2></div>
          <div class="card-body py-4">
            <p class="text-xl font-bold text-success">{{ format(totals.cash_in || 0) }}</p>
          </div>
        </div>
        <div class="ocn-panel">
          <div class="ocn-panel__head py-3"><h2 class="ocn-panel__title text-sm font-medium">Total keluar / expenses</h2></div>
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
          <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-8">
            <select v-if="erpCompanyContext()?.companies?.length" v-model="filters.company_id" class="select select-bordered select-sm w-full">
              <option value="all">Semua Usaha</option>
              <option v-for="c in erpCompanyContext().companies" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
            <select v-model="filters.type" class="select select-bordered select-sm w-full">
              <option value="">Semua Jenis</option>
              <option value="in">Kas Masuk</option>
              <option value="out">Kas Keluar</option>
            </select>
            <select v-model="filters.source" class="select select-bordered select-sm w-full">
              <option v-for="opt in sourceOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
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
          <h2 class="ocn-panel__title">Daftar cashflow accounting</h2>
          <span class="text-xs text-base-content/60">{{ entries.length }} transaksi</span>
        </div>
        <div class="overflow-x-auto">
          <table class="table">
            <thead class="sticky top-0 z-10">
              <tr>
                <th class="w-10 text-center">#</th>
                <th>Tanggal</th>
                <th>Jenis</th>
                <th>Peruntukan</th>
                <th>Sumber Dana</th>
                <th>Project</th>
                <th>Kategori</th>
                <th class="text-right">Jumlah</th>
                <th>Status</th>
                <th>Keterangan</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(entry, index) in entries"
                :key="`${entry.type}-${entry.id}`"
                :class="['cursor-pointer transition-colors hover:bg-primary/5',
                  entry.type === 'in' ? 'border-l-4 border-l-success' : 'border-l-4 border-l-error']"
                @click="openDetailModal(entry)"
              >
                <td class="text-center text-xs text-base-content/40 font-mono">{{ index + 1 }}</td>
                <td class="whitespace-nowrap">
                  <span class="font-medium">{{ formatDate(entry.date) }}</span>
                </td>
                <td>
                  <span class="badge" :class="typeBadgeClass(entry.type)">{{ typeLabel(entry.type) }}</span>
                </td>
                <td>
                  <div class="font-medium">{{ entry.source_name || '-' }}</div>
                  <div class="mt-0.5 text-xs text-base-content/40">{{ sourceLabel(entry.source) }}</div>
                  <div v-if="entry.type === 'in' ? entry.payment_method_name : entry.recipient_name"
                       class="text-xs text-base-content/40 mt-0.5">
                    {{ entry.type === 'in' ? entry.payment_method_name : entry.recipient_name }}
                  </div>
                </td>
                <td>
                  <div class="font-medium">{{ entry.cash_account_name || '-' }}</div>
                  <div v-if="entry.payment_method_name" class="mt-0.5 text-xs text-base-content/40">{{ entry.payment_method_name }}</div>
                </td>
                <td class="font-medium">{{ entry.project_name }}</td>
                <td><span class="badge badge-ghost">{{ categoryLabelMap[entry.category] ?? entry.category }}</span></td>
                <td class="text-right tabular-nums whitespace-nowrap">
                  <span :class="['font-semibold', entry.type === 'in' ? 'text-success' : 'text-error']">
                    {{ entry.type === 'in' ? '+' : '-' }}{{ format(entry.amount) }}
                  </span>
                </td>
                <td><StatusBadge :status="entry.document_status" size="" /></td>
                <td class="max-w-xs">
                  <div class="truncate text-sm">{{ entry.note || '-' }}</div>
                  <div class="text-xs text-base-content/40 mt-0.5">{{ entry.creator_name }}</div>
                </td>
              </tr>
              <tr v-if="!entries.length">
                <td colspan="10" class="py-16 text-center">
                  <svg class="mx-auto h-12 w-12 text-base-content/20" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" />
                  </svg>
                  <p class="mt-3 text-sm font-medium text-base-content/50">Belum ada transaksi</p>
                  <p class="mt-1 text-xs text-base-content/40">Transaksi muncul dari invoice, POS, supplier payment, expenses, atau input manual accounting.</p>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <dialog id="modal-cashflow-entry" class="modal">
      <div class="modal-box max-w-xl">
        <h3 class="font-bold text-lg">Input Manual Accounting</h3>
        <div class="mt-4 space-y-3">
          <div>
            <label class="label"><span class="label-text">Jenis Transaksi</span></label>
            <select v-model="form.type" class="select select-bordered w-full">
              <option value="out">Keluar / Expense</option>
              <option value="in">Masuk Manual</option>
            </select>
          </div>
          <div>
            <label class="label"><span class="label-text">{{ cashAccountLabel(form.type) }} <span class="text-error">*</span></span></label>
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
        <h3 class="font-bold text-lg">Detail Cashflow Accounting</h3>
        <div v-if="selectedEntry" class="mt-4 grid grid-cols-2 gap-3 text-sm">
          <div class="text-base-content/60">Tanggal</div><div>{{ selectedEntry.date }}</div>
          <div class="text-base-content/60">Jenis</div><div>{{ typeLabel(selectedEntry.type) }}</div>
          <div class="text-base-content/60">Peruntukan</div><div>{{ selectedEntry.source_name || '-' }}</div>
          <div class="text-base-content/60">Referensi</div><div class="font-mono text-xs">{{ selectedEntry.reference_no || '-' }}</div>
          <div class="text-base-content/60">Project</div><div>{{ selectedEntry.project_name }}</div>
          <div class="text-base-content/60">Kategori</div><div>{{ categoryLabelMap[selectedEntry.category] ?? selectedEntry.category }}</div>
          <div class="text-base-content/60">Sumber Dana</div><div>{{ selectedEntry.cash_account_name || '-' }}</div>
          <div class="text-base-content/60">Metode/Penerima</div><div>{{ selectedEntry.type === 'in' ? (selectedEntry.payment_method_name || '-') : (selectedEntry.recipient_name || '-') }}</div>
          <div class="text-base-content/60">Jumlah</div><div :class="selectedEntry.type === 'in' ? 'text-success font-semibold' : 'text-error font-semibold'">{{ format(selectedEntry.amount) }}</div>
          <div class="text-base-content/60">Status</div><div><StatusBadge :status="selectedEntry.document_status" size="" /></div>
          <div class="text-base-content/60">Jurnal</div><div class="font-mono text-xs">{{ selectedEntry.journal_entry_id || '-' }}</div>
          <div class="text-base-content/60">Keterangan</div><div>{{ selectedEntry.note || '-' }}</div>
          <div class="text-base-content/60">Dicatat Oleh</div><div>{{ selectedEntry.creator_name }}</div>
        </div>
        <div class="modal-action">
          <button v-if="canMutate && selectedEntry?.mutable !== false" class="btn btn-outline" @click="selectedEntry && openEditModal(selectedEntry)">Edit</button>
          <button v-if="canMutate && selectedEntry?.mutable !== false" class="btn btn-error" @click="selectedEntry && destroyEntry(selectedEntry)">Hapus</button>
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
        <h3 class="font-bold text-lg">Edit Cashflow Accounting</h3>
        <div class="mt-4 space-y-3">
          <div>
            <label class="label"><span class="label-text">Jenis Transaksi</span></label>
            <select v-model="editForm.type" class="select select-bordered w-full" disabled>
              <option value="in">Masuk Manual</option>
              <option value="out">Keluar / Expense</option>
            </select>
          </div>
          <div>
            <label class="label"><span class="label-text">{{ cashAccountLabel(editForm.type) }}</span></label>
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
