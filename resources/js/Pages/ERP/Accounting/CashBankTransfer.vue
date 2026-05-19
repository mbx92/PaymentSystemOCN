<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CurrencyInput from '@/Components/CurrencyInput.vue';
import DataTablePagination from '@/Components/DataTablePagination.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ArrowLeftIcon, ArrowsRightLeftIcon } from '@heroicons/vue/24/outline';
import { ref, watch } from 'vue';
import { useCurrency } from '@/composables/useCurrency';
import { useDateFormat } from '@/composables/useDateFormat';

const props = defineProps({
  transfers: Object,
  total: Number,
  cashAccounts: Array,
  projects: Array,
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
  from_account_id: props.filters?.from_account_id ?? '',
  to_account_id: props.filters?.to_account_id ?? '',
  project_id: props.filters?.project_id ?? '',
});

let timer;
watch(filters, () => {
  clearTimeout(timer);
  timer = setTimeout(() => {
    router.get(route('erp.accounting.cash-bank-transfer'), filters.value, { preserveState: true, replace: true });
  }, 300);
}, { deep: true });

const form = useForm({
  from_account_id: '',
  to_account_id: '',
  amount: 0,
  transfer_date: new Date().toISOString().slice(0, 10),
  project_id: '',
  note: '',
});

const resetForm = () => {
  const accounts = props.cashAccounts ?? [];
  form.reset();
  form.from_account_id = accounts.find((a) => /bank/i.test(a.name))?.id ?? accounts[0]?.id ?? '';
  form.to_account_id = accounts.find((a) => /kas kecil|petty/i.test(a.name))?.id
    ?? accounts.find((a) => a.id !== form.from_account_id)?.id
    ?? '';
  form.amount = 0;
  form.transfer_date = new Date().toISOString().slice(0, 10);
  form.project_id = '';
  form.note = '';
};

const openModal = () => {
  resetForm();
  document.getElementById('modal-cash-bank-transfer')?.showModal();
};

const submit = () => {
  form.post(route('erp.accounting.cash-bank-transfer.store'), {
    preserveScroll: true,
    onSuccess: () => {
      resetForm();
      document.getElementById('modal-cash-bank-transfer')?.close();
    },
  });
};

const rows = () => props.transfers?.data ?? [];
</script>

<template>
  <Head title="Accounting - Mutasi Kas/Bank" />
  <AppLayout>
    <div class="space-y-5">
      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Accounting Workspace</p>
              <h1 class="ocn-panel__title mt-1">Mutasi Kas/Bank</h1>
              <p class="ocn-panel__desc mt-1">
                Pindahkan dana antar akun kas/bank (mis. tarik tunai bank ke kas kecil). Tidak mempengaruhi laba rugi.
              </p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
              <button type="button" class="btn btn-primary btn-sm gap-1.5" @click="openModal">
                <ArrowsRightLeftIcon class="h-4 w-4" />
                Mutasi Baru
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
          <div class="grid gap-3 md:grid-cols-6">
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
              <label class="label py-0"><span class="label-text text-xs uppercase tracking-wide">Dari akun</span></label>
              <select v-model="filters.from_account_id" class="select select-sm select-bordered w-full">
                <option value="">Semua</option>
                <option v-for="acc in cashAccounts" :key="`f-${acc.id}`" :value="acc.id">{{ acc.code }} - {{ acc.name }}</option>
              </select>
            </div>
            <div>
              <label class="label py-0"><span class="label-text text-xs uppercase tracking-wide">Ke akun</span></label>
              <select v-model="filters.to_account_id" class="select select-sm select-bordered w-full">
                <option value="">Semua</option>
                <option v-for="acc in cashAccounts" :key="`t-${acc.id}`" :value="acc.id">{{ acc.code }} - {{ acc.name }}</option>
              </select>
            </div>
            <div>
              <label class="label py-0"><span class="label-text text-xs uppercase tracking-wide">Project (referensi)</span></label>
              <select v-model="filters.project_id" class="select select-sm select-bordered w-full">
                <option value="">Semua</option>
                <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <div class="ocn-stat-card rounded-xl border border-base-300 bg-base-100 p-4 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-wide text-base-content/50">Total mutasi (filter)</p>
        <p class="mt-1 text-2xl font-bold">{{ format(total) }}</p>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Riwayat mutasi</h2>
        </div>
        <div class="overflow-x-auto">
          <table class="table table-zebra">
            <thead>
              <tr>
                <th>Tanggal</th>
                <th>Usaha</th>
                <th>Dari</th>
                <th>Ke</th>
                <th class="text-right">Nominal</th>
                <th>Project</th>
                <th>Catatan</th>
                <th>Jurnal</th>
                <th>Oleh</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in rows()" :key="row.id">
                <td class="whitespace-nowrap">{{ formatDate(row.transfer_date) }}</td>
                <td class="text-sm">{{ row.company_name }}</td>
                <td class="text-sm">{{ row.from_account_label }}</td>
                <td class="text-sm">{{ row.to_account_label }}</td>
                <td class="text-right font-semibold">{{ format(row.amount) }}</td>
                <td>{{ row.project_name || '—' }}</td>
                <td class="max-w-xs truncate">{{ row.note || '—' }}</td>
                <td class="font-mono text-xs">{{ row.journal_entry_no || '—' }}</td>
                <td class="text-sm">{{ row.creator_name || '—' }}</td>
              </tr>
              <tr v-if="!rows().length">
                <td colspan="9" class="py-8 text-center text-base-content/50">Belum ada mutasi kas/bank.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <DataTablePagination
          :paginator="transfers"
          @update:per-page="(n) => router.get(route('erp.accounting.cash-bank-transfer'), { ...filters, per_page: n }, { preserveState: true, replace: true })"
        />
      </div>
    </div>

    <dialog id="modal-cash-bank-transfer" class="modal">
      <div class="modal-box max-w-xl">
        <h3 class="font-bold text-lg">Mutasi Kas/Bank</h3>
        <p class="mt-1 text-sm text-base-content/60">
          Debit akun tujuan, kredit akun sumber. Project hanya referensi catatan, bukan pendapatan/beban.
        </p>
        <div class="mt-4 space-y-3">
          <div class="grid gap-3 md:grid-cols-2">
            <div>
              <label class="label py-0"><span class="label-text">Dari akun <span class="text-error">*</span></span></label>
              <select v-model="form.from_account_id" class="select select-bordered w-full">
                <option value="" disabled>Pilih akun sumber</option>
                <option v-for="acc in cashAccounts" :key="acc.id" :value="acc.id">{{ acc.code }} - {{ acc.name }}</option>
              </select>
              <p v-if="form.errors.from_account_id" class="text-error text-xs mt-1">{{ form.errors.from_account_id }}</p>
            </div>
            <div>
              <label class="label py-0"><span class="label-text">Ke akun <span class="text-error">*</span></span></label>
              <select v-model="form.to_account_id" class="select select-bordered w-full">
                <option value="" disabled>Pilih akun tujuan</option>
                <option
                  v-for="acc in cashAccounts"
                  :key="`to-${acc.id}`"
                  :value="acc.id"
                  :disabled="String(acc.id) === String(form.from_account_id)"
                >
                  {{ acc.code }} - {{ acc.name }}
                </option>
              </select>
              <p v-if="form.errors.to_account_id" class="text-error text-xs mt-1">{{ form.errors.to_account_id }}</p>
            </div>
          </div>
          <CurrencyInput v-model="form.amount" label="Nominal" :required="true" :error="form.errors.amount" />
          <div>
            <label class="label py-0"><span class="label-text">Tanggal <span class="text-error">*</span></span></label>
            <input v-model="form.transfer_date" type="date" class="input input-bordered w-full">
            <p v-if="form.errors.transfer_date" class="text-error text-xs mt-1">{{ form.errors.transfer_date }}</p>
          </div>
          <div>
            <label class="label py-0"><span class="label-text">Project (opsional, referensi saja)</span></label>
            <select v-model="form.project_id" class="select select-bordered w-full">
              <option value="">Tidak terkait project</option>
              <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
          </div>
          <div>
            <label class="label py-0"><span class="label-text">Catatan</span></label>
            <textarea v-model="form.note" class="textarea textarea-bordered w-full" rows="2" placeholder="Contoh: Tarik tunai untuk operasional lapangan" />
          </div>
        </div>
        <div class="modal-action">
          <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
          <button type="button" class="btn btn-primary" :disabled="form.processing" @click="submit">
            {{ form.processing ? 'Menyimpan...' : 'Simpan mutasi' }}
          </button>
        </div>
      </div>
    </dialog>
  </AppLayout>
</template>
