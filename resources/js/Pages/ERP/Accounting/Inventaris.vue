<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CurrencyInput from '@/Components/CurrencyInput.vue';
import DataTablePagination from '@/Components/DataTablePagination.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ArchiveBoxIcon, ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { ref, watch } from 'vue';
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
});

let timer;
watch(filters, () => {
  clearTimeout(timer);
  timer = setTimeout(() => {
    router.get(route('erp.accounting.inventaris'), filters.value, { preserveState: true, replace: true });
  }, 300);
}, { deep: true });

const form = useForm({
  item_name: '',
  qty: 1,
  amount: 0,
  acquisition_date: new Date().toISOString().slice(0, 10),
  asset_account_id: props.defaultAssetAccountId ?? props.assetAccounts?.[0]?.id ?? '',
  cash_account_id: props.cashAccounts?.[0]?.id ?? '',
  note: '',
});

const openModal = () => {
  form.reset();
  form.item_name = '';
  form.qty = 1;
  form.amount = 0;
  form.acquisition_date = new Date().toISOString().slice(0, 10);
  form.asset_account_id = props.defaultAssetAccountId ?? props.assetAccounts?.[0]?.id ?? '';
  form.cash_account_id = props.cashAccounts?.[0]?.id ?? '';
  form.note = '';
  document.getElementById('modal-inventaris')?.showModal();
};

const submit = () => {
  form.post(route('erp.accounting.inventaris.store'), {
    preserveScroll: true,
    onSuccess: () => document.getElementById('modal-inventaris')?.close(),
  });
};

const rows = () => props.records?.data ?? [];
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
              <input v-model="filters.q" type="text" class="input input-sm input-bordered w-full" placeholder="Nama barang / catatan">
            </div>
          </div>
        </div>
      </div>

      <div class="ocn-stat-card rounded-xl border border-base-300 bg-base-100 p-4 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-wide text-base-content/50">Total pembelian (filter)</p>
        <p class="mt-1 text-2xl font-bold">{{ format(total) }}</p>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Riwayat pencatatan</h2>
        </div>
        <div class="overflow-x-auto">
          <table class="table table-zebra">
            <thead>
              <tr>
                <th>Tanggal</th>
                <th>Usaha</th>
                <th>Nama inventaris</th>
                <th class="text-right">Qty</th>
                <th class="text-right">Nominal</th>
                <th>Akun aset</th>
                <th>Kas/Bank</th>
                <th>Catatan</th>
                <th>Jurnal</th>
                <th>Oleh</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in rows()" :key="row.id">
                <td class="whitespace-nowrap">{{ formatDate(row.acquisition_date) }}</td>
                <td class="text-sm">{{ row.company_name }}</td>
                <td class="font-medium">{{ row.item_name }}</td>
                <td class="text-right">{{ row.qty }}</td>
                <td class="text-right font-semibold">{{ format(row.amount) }}</td>
                <td class="text-sm">{{ row.asset_account_label }}</td>
                <td class="text-sm">{{ row.cash_account_label }}</td>
                <td class="max-w-xs truncate">{{ row.note || '—' }}</td>
                <td class="font-mono text-xs">{{ row.journal_entry_no || '—' }}</td>
                <td class="text-sm">{{ row.creator_name || '—' }}</td>
              </tr>
              <tr v-if="!rows().length">
                <td colspan="10" class="py-8 text-center text-base-content/50">Belum ada pencatatan inventaris.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <DataTablePagination
          :paginator="records"
          @update:per-page="(n) => router.get(route('erp.accounting.inventaris'), { ...filters, per_page: n }, { preserveState: true, replace: true })"
        />
      </div>
    </div>

    <dialog id="modal-inventaris" class="modal">
      <div class="modal-box max-w-xl">
        <h3 class="font-bold text-lg">Catat Inventaris</h3>
        <p class="mt-1 text-sm text-base-content/60">
          Jurnal: debit akun aset inventaris, kredit akun kas/bank. Akun aset default ke Peralatan (bisa diubah).
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
              <label class="label py-0"><span class="label-text">Qty</span></label>
              <input v-model.number="form.qty" type="number" min="0.01" step="0.01" class="input input-bordered w-full">
              <p v-if="form.errors.qty" class="text-error text-xs mt-1">{{ form.errors.qty }}</p>
            </div>
            <div>
              <label class="label py-0"><span class="label-text">Tanggal <span class="text-error">*</span></span></label>
              <input v-model="form.acquisition_date" type="date" class="input input-bordered w-full">
              <p v-if="form.errors.acquisition_date" class="text-error text-xs mt-1">{{ form.errors.acquisition_date }}</p>
            </div>
          </div>
          <CurrencyInput v-model="form.amount" label="Total nominal" :required="true" :error="form.errors.amount" />
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
  </AppLayout>
</template>
