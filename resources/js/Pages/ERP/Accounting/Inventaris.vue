<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CurrencyInput from '@/Components/CurrencyInput.vue';
import DataTablePagination from '@/Components/DataTablePagination.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ArchiveBoxIcon, ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { computed, ref, watch } from 'vue';
import { useCurrency } from '@/composables/useCurrency';
import { useDateFormat } from '@/composables/useDateFormat';

const props = defineProps({
  records: Object,
  total: Number,
  assetAccounts: Array,
  cashAccounts: Array,
  defaultAssetAccountId: Number,
  filters: Object,
});

const { formatDate } = useDateFormat();
const { format } = useCurrency();
const page = usePage();
const erpCompanyContext = () => page.props.erpCompanyContext ?? null;

const filters = ref({
  company_id: props.filters?.company_id ?? erpCompanyContext()?.current_company_id ?? '',
  date_from: props.filters?.date_from ?? '',
  date_to: props.filters?.date_to ?? '',
  asset_account_id: props.filters?.asset_account_id ?? '',
  q: props.filters?.q ?? '',
  per_page: Number(props.filters?.per_page ?? props.records?.per_page ?? 10),
});

let timer;
watch(filters, (val) => {
  clearTimeout(timer);
  timer = setTimeout(() => {
    router.get(route('erp.accounting.inventaris'), { ...val, page: 1 }, {
      preserveState: true,
      preserveScroll: true,
      replace: true,
    });
  }, 300);
}, { deep: true });

const calcAmount = (qty, unitPrice) => {
  const q = Number(qty) || 0;
  const p = Number(unitPrice) || 0;
  return Math.round(q * p);
};

const form = useForm({
  item_name: '',
  qty: 1,
  unit_price: 0,
  amount: 0,
  acquisition_date: new Date().toISOString().slice(0, 10),
  asset_account_id: props.defaultAssetAccountId ?? props.assetAccounts?.[0]?.id ?? '',
  cash_account_id: props.cashAccounts?.[0]?.id ?? '',
  note: '',
});

watch(
  () => [form.qty, form.unit_price],
  ([qty, unitPrice]) => {
    form.amount = calcAmount(qty, unitPrice);
  },
);

const resetForm = () => {
  form.reset();
  form.item_name = '';
  form.qty = 1;
  form.unit_price = 0;
  form.amount = 0;
  form.acquisition_date = new Date().toISOString().slice(0, 10);
  form.asset_account_id = props.defaultAssetAccountId ?? props.assetAccounts?.[0]?.id ?? '';
  form.cash_account_id = props.cashAccounts?.[0]?.id ?? '';
  form.note = '';
};

const openModal = () => {
  resetForm();
  document.getElementById('modal-inventaris')?.showModal();
};

const submit = () => {
  form.amount = calcAmount(form.qty, form.unit_price);
  form.transform((data) => ({
    item_name: data.item_name,
    qty: data.qty,
    unit_price: data.unit_price,
    acquisition_date: data.acquisition_date,
    asset_account_id: data.asset_account_id,
    cash_account_id: data.cash_account_id,
    note: data.note,
  })).post(route('erp.accounting.inventaris.store'), {
    preserveScroll: true,
    onSuccess: () => {
      resetForm();
      document.getElementById('modal-inventaris')?.close();
    },
  });
};

const editForm = useForm({
  id: '',
  item_name: '',
  qty: 1,
  unit_price: 0,
  amount: 0,
  acquisition_date: '',
  asset_account_id: '',
  cash_account_id: '',
  note: '',
});

watch(
  () => [editForm.qty, editForm.unit_price],
  ([qty, unitPrice]) => {
    editForm.amount = calcAmount(qty, unitPrice);
  },
);

const deleting = useForm({});

const openEditModal = (row) => {
  editForm.id = row.id;
  editForm.item_name = row.item_name || '';
  editForm.qty = row.qty ?? 1;
  editForm.unit_price = row.unit_price ?? (row.qty ? Math.round(row.amount / row.qty) : row.amount) ?? 0;
  editForm.amount = calcAmount(editForm.qty, editForm.unit_price);
  editForm.acquisition_date = row.acquisition_date || '';
  editForm.asset_account_id = row.asset_account_id || props.defaultAssetAccountId || '';
  editForm.cash_account_id = row.cash_account_id || props.cashAccounts?.[0]?.id || '';
  editForm.note = row.note || '';
  document.getElementById('modal-inventaris-edit')?.showModal();
};

const submitEdit = () => {
  editForm.amount = calcAmount(editForm.qty, editForm.unit_price);
  editForm.transform((data) => ({
    item_name: data.item_name,
    qty: data.qty,
    unit_price: data.unit_price,
    acquisition_date: data.acquisition_date,
    asset_account_id: data.asset_account_id,
    cash_account_id: data.cash_account_id,
    note: data.note,
  })).patch(route('erp.accounting.inventaris.update', editForm.id), {
    preserveScroll: true,
    onSuccess: () => document.getElementById('modal-inventaris-edit')?.close(),
  });
};

const destroyRow = (row) => {
  if (!confirm(`Hapus inventaris "${row.item_name}"? Jurnal terkait akan di-reverse.`)) return;
  deleting.delete(route('erp.accounting.inventaris.destroy', row.id), { preserveScroll: true });
};

const rows = () => props.records?.data ?? [];
const formTotal = computed(() => calcAmount(form.qty, form.unit_price));
const editTotal = computed(() => calcAmount(editForm.qty, editForm.unit_price));

const itemTitle = (row) => {
  const parts = [row.company_name, row.note, row.creator_name ? `Oleh: ${row.creator_name}` : null].filter(Boolean);
  return parts.join(' · ') || undefined;
};

/** Label "1401 - Peralatan" → "Peralatan" */
const accountName = (label) => {
  if (!label) return '—';
  const sep = label.indexOf(' - ');
  return sep === -1 ? label : label.slice(sep + 3);
};
</script>

<template>
  <Head title="Accounting - Inventaris" />
  <AppLayout>
    <div class="space-y-5">
      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Accounting Workspace</p>
              <h1 class="ocn-panel__title mt-1">Inventaris</h1>
              <p class="ocn-panel__desc mt-1">
                Catat pembelian inventaris ke buku besar: debit akun aset (default Peralatan), kredit kas/bank.
              </p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
              <button type="button" class="btn btn-primary btn-sm gap-1.5" @click="openModal">
                <ArchiveBoxIcon class="h-4 w-4" />
                Catat Inventaris
              </button>
              <Link class="btn btn-ghost btn-sm shrink-0 gap-1.5" :href="route('erp.accounting')">
                <ArrowLeftIcon class="h-4 w-4" />
                Back
              </Link>
            </div>
          </div>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Filter</h2>
        </div>
        <div class="card-body">
          <div class="grid gap-3 md:grid-cols-5">
            <div v-if="erpCompanyContext()?.companies?.length">
              <label class="label py-0"><span class="label-text text-xs uppercase tracking-wide">Usaha</span></label>
              <select v-model="filters.company_id" class="select select-sm select-bordered w-full">
                <option value="all">Semua usaha</option>
                <option v-for="c in erpCompanyContext().companies" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>
            <div>
              <label class="label py-0"><span class="label-text text-xs uppercase tracking-wide">Dari tanggal</span></label>
              <input v-model="filters.date_from" type="date" class="input input-sm input-bordered w-full">
            </div>
            <div>
              <label class="label py-0"><span class="label-text text-xs uppercase tracking-wide">Sampai tanggal</span></label>
              <input v-model="filters.date_to" type="date" class="input input-sm input-bordered w-full">
            </div>
            <div>
              <label class="label py-0"><span class="label-text text-xs uppercase tracking-wide">Akun aset</span></label>
              <select v-model="filters.asset_account_id" class="select select-sm select-bordered w-full">
                <option value="">Semua</option>
                <option v-for="acc in assetAccounts" :key="`fa-${acc.id}`" :value="acc.id">{{ acc.code }} - {{ acc.name }}</option>
              </select>
            </div>
            <div>
              <label class="label py-0"><span class="label-text text-xs uppercase tracking-wide">Cari</span></label>
              <input
                v-model="filters.q"
                type="search"
                class="input input-sm input-bordered w-full"
                placeholder="Cari nama, catatan, akun, jurnal, pembuat..."
              >
            </div>
          </div>
        </div>
      </div>

      <div class="ocn-stat-card rounded-xl border border-base-300 bg-base-100 p-4 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-wide text-base-content/50">Total pembelian (filter)</p>
        <p class="mt-1 text-2xl font-bold">{{ format(total) }}</p>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head flex items-center justify-between gap-2">
          <h2 class="ocn-panel__title">Riwayat pencatatan</h2>
          <span class="text-xs text-base-content/60">{{ records?.total ?? 0 }} data</span>
        </div>
        <div class="w-full">
          <table class="table table-zebra table-sm w-full">
            <thead>
              <tr class="text-xs">
                <th class="w-[6.25rem] whitespace-nowrap">Tgl</th>
                <th>Nama</th>
                <th class="w-12 text-right">Qty</th>
                <th class="w-[6.5rem] text-right">Harga</th>
                <th class="w-[7rem] text-right">Total</th>
                <th class="w-[5.5rem]">Aset</th>
                <th class="w-[5.5rem]">Kas</th>
                <th class="w-[5.5rem]">Jurnal</th>
                <th class="w-[5.5rem]"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in rows()" :key="row.id">
                <td class="w-[6.25rem] whitespace-nowrap text-xs tabular-nums">{{ formatDate(row.acquisition_date) }}</td>
                <td class="min-w-0">
                  <p class="truncate font-medium text-sm" :title="itemTitle(row)">{{ row.item_name }}</p>
                  <p v-if="row.company_name" class="truncate text-[11px] text-base-content/50" :title="row.company_name">
                    {{ row.company_name }}
                  </p>
                </td>
                <td class="text-right text-xs tabular-nums">{{ row.qty }}</td>
                <td class="text-right text-xs tabular-nums">{{ format(row.unit_price) }}</td>
                <td class="text-right text-xs font-semibold tabular-nums">{{ format(row.amount) }}</td>
                <td class="w-[5.5rem] max-w-[5.5rem] truncate text-xs" :title="row.asset_account_label">{{ accountName(row.asset_account_label) }}</td>
                <td class="w-[5.5rem] max-w-[5.5rem] truncate text-xs" :title="row.cash_account_label">{{ accountName(row.cash_account_label) }}</td>
                <td class="font-mono text-[11px] truncate" :title="row.journal_entry_no">{{ row.journal_entry_no || '—' }}</td>
                <td>
                  <div class="flex justify-end gap-0.5">
                    <button type="button" class="btn btn-ghost btn-xs" @click="openEditModal(row)">Edit</button>
                    <button type="button" class="btn btn-ghost btn-xs text-error" @click="destroyRow(row)">Hapus</button>
                  </div>
                </td>
              </tr>
              <tr v-if="!rows().length">
                <td colspan="9" class="py-8 text-center text-base-content/50">Belum ada pencatatan inventaris.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <DataTablePagination
          :paginator="records"
          :per-page-options="[10, 15, 25, 50, 75, 100]"
          @update:per-page="(n) => { filters.per_page = n; }"
        />
      </div>
    </div>

    <dialog id="modal-inventaris" class="modal">
      <div class="modal-box max-w-xl">
        <h3 class="font-bold text-lg">Catat Inventaris</h3>
        <p class="mt-1 text-sm text-base-content/60">
          Total dihitung otomatis: qty × harga satuan. Jurnal mem-posting total tersebut.
        </p>
        <div class="mt-4 space-y-3">
          <div>
            <label class="label py-0"><span class="label-text">Nama inventaris <span class="text-error">*</span></span></label>
            <input v-model="form.item_name" type="text" class="input input-bordered w-full" placeholder="Contoh: Laptop admin, Kamera CCTV">
            <p v-if="form.errors.item_name" class="text-error text-xs mt-1">{{ form.errors.item_name }}</p>
          </div>
          <div class="grid gap-3 md:grid-cols-2">
            <div>
              <label class="label py-0"><span class="label-text">Akun aset (inventaris) <span class="text-error">*</span></span></label>
              <select v-model="form.asset_account_id" class="select select-bordered w-full">
                <option value="" disabled>Pilih akun aset</option>
                <option v-for="acc in assetAccounts" :key="acc.id" :value="acc.id">{{ acc.code }} - {{ acc.name }}</option>
              </select>
              <p v-if="form.errors.asset_account_id" class="text-error text-xs mt-1">{{ form.errors.asset_account_id }}</p>
            </div>
            <div>
              <label class="label py-0"><span class="label-text">Bayar dari (Kas/Bank) <span class="text-error">*</span></span></label>
              <select v-model="form.cash_account_id" class="select select-bordered w-full" :disabled="!(cashAccounts || []).length">
                <option value="" disabled>{{ (cashAccounts || []).length ? 'Pilih akun kas/bank' : 'Belum ada akun kas/bank' }}</option>
                <option v-for="acc in cashAccounts" :key="`cash-${acc.id}`" :value="acc.id">{{ acc.code }} - {{ acc.name }}</option>
              </select>
              <p v-if="form.errors.cash_account_id" class="text-error text-xs mt-1">{{ form.errors.cash_account_id }}</p>
            </div>
          </div>
          <div class="grid gap-3 md:grid-cols-2">
            <div>
              <label class="label py-0"><span class="label-text">Qty <span class="text-error">*</span></span></label>
              <input v-model.number="form.qty" type="number" min="0.01" step="0.01" class="input input-bordered w-full">
              <p v-if="form.errors.qty" class="text-error text-xs mt-1">{{ form.errors.qty }}</p>
            </div>
            <div>
              <label class="label py-0"><span class="label-text">Tanggal <span class="text-error">*</span></span></label>
              <input v-model="form.acquisition_date" type="date" class="input input-bordered w-full">
              <p v-if="form.errors.acquisition_date" class="text-error text-xs mt-1">{{ form.errors.acquisition_date }}</p>
            </div>
          </div>
          <CurrencyInput v-model="form.unit_price" label="Harga satuan" :required="true" :error="form.errors.unit_price" />
          <div class="rounded-lg border border-base-300 bg-base-200/50 px-3 py-2">
            <p class="text-xs uppercase tracking-wide text-base-content/50">Total (qty × harga)</p>
            <p class="mt-0.5 text-lg font-bold tabular-nums">{{ format(formTotal) }}</p>
          </div>
          <div>
            <label class="label py-0"><span class="label-text">Catatan</span></label>
            <textarea v-model="form.note" class="textarea textarea-bordered w-full" rows="2" placeholder="Opsional: merk, serial, lokasi" />
          </div>
        </div>
        <div class="modal-action">
          <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
          <button type="button" class="btn btn-primary" :disabled="form.processing" @click="submit">
            {{ form.processing ? 'Menyimpan...' : 'Posting jurnal' }}
          </button>
        </div>
      </div>
    </dialog>

    <dialog id="modal-inventaris-edit" class="modal">
      <div class="modal-box max-w-xl">
        <h3 class="font-bold text-lg">Edit Inventaris</h3>
        <p class="mt-1 text-sm text-base-content/60">
          Total dihitung otomatis. Perubahan akan me-reverse jurnal lama lalu memposting ulang.
        </p>
        <div class="mt-4 space-y-3">
          <div>
            <label class="label py-0"><span class="label-text">Nama inventaris <span class="text-error">*</span></span></label>
            <input v-model="editForm.item_name" type="text" class="input input-bordered w-full">
            <p v-if="editForm.errors.item_name" class="text-error text-xs mt-1">{{ editForm.errors.item_name }}</p>
          </div>
          <div class="grid gap-3 md:grid-cols-2">
            <div>
              <label class="label py-0"><span class="label-text">Akun aset (inventaris) <span class="text-error">*</span></span></label>
              <select v-model="editForm.asset_account_id" class="select select-bordered w-full">
                <option value="" disabled>Pilih akun aset</option>
                <option v-for="acc in assetAccounts" :key="`edit-asset-${acc.id}`" :value="acc.id">{{ acc.code }} - {{ acc.name }}</option>
              </select>
              <p v-if="editForm.errors.asset_account_id" class="text-error text-xs mt-1">{{ editForm.errors.asset_account_id }}</p>
            </div>
            <div>
              <label class="label py-0"><span class="label-text">Bayar dari (Kas/Bank) <span class="text-error">*</span></span></label>
              <select v-model="editForm.cash_account_id" class="select select-bordered w-full" :disabled="!(cashAccounts || []).length">
                <option value="" disabled>{{ (cashAccounts || []).length ? 'Pilih akun kas/bank' : 'Belum ada akun kas/bank' }}</option>
                <option v-for="acc in cashAccounts" :key="`edit-cash-${acc.id}`" :value="acc.id">{{ acc.code }} - {{ acc.name }}</option>
              </select>
              <p v-if="editForm.errors.cash_account_id" class="text-error text-xs mt-1">{{ editForm.errors.cash_account_id }}</p>
            </div>
          </div>
          <div class="grid gap-3 md:grid-cols-2">
            <div>
              <label class="label py-0"><span class="label-text">Qty <span class="text-error">*</span></span></label>
              <input v-model.number="editForm.qty" type="number" min="0.01" step="0.01" class="input input-bordered w-full">
              <p v-if="editForm.errors.qty" class="text-error text-xs mt-1">{{ editForm.errors.qty }}</p>
            </div>
            <div>
              <label class="label py-0"><span class="label-text">Tanggal <span class="text-error">*</span></span></label>
              <input v-model="editForm.acquisition_date" type="date" class="input input-bordered w-full">
              <p v-if="editForm.errors.acquisition_date" class="text-error text-xs mt-1">{{ editForm.errors.acquisition_date }}</p>
            </div>
          </div>
          <CurrencyInput v-model="editForm.unit_price" label="Harga satuan" :required="true" :error="editForm.errors.unit_price" />
          <div class="rounded-lg border border-base-300 bg-base-200/50 px-3 py-2">
            <p class="text-xs uppercase tracking-wide text-base-content/50">Total (qty × harga)</p>
            <p class="mt-0.5 text-lg font-bold tabular-nums">{{ format(editTotal) }}</p>
          </div>
          <div>
            <label class="label py-0"><span class="label-text">Catatan</span></label>
            <textarea v-model="editForm.note" class="textarea textarea-bordered w-full" rows="2" placeholder="Opsional: merk, serial, lokasi" />
            <p v-if="editForm.errors.note" class="text-error text-xs mt-1">{{ editForm.errors.note }}</p>
          </div>
        </div>
        <div class="modal-action">
          <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
          <button type="button" class="btn btn-primary" :disabled="editForm.processing" @click="submitEdit">
            {{ editForm.processing ? 'Menyimpan...' : 'Simpan perubahan' }}
          </button>
        </div>
      </div>
    </dialog>
  </AppLayout>
</template>
