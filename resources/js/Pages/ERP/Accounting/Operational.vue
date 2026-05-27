<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CurrencyInput from '@/Components/CurrencyInput.vue';
import DataTablePagination from '@/Components/DataTablePagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { ref, watch } from 'vue';
import { useCurrency } from '@/composables/useCurrency';
import { useDateFormat } from '@/composables/useDateFormat';

const props = defineProps({
  rows: Object,
  total: Number,
  projects: Array,
  cashAccounts: Array,
  filters: Object,
});

const { formatDate } = useDateFormat();

const { format } = useCurrency();
const page = usePage();
const erpCompanyContext = () => page.props.erpCompanyContext ?? null;
const rowItems = () => props.rows?.data ?? [];
const filters = ref({
  ...props.filters,
  company_id: props.filters?.company_id ?? erpCompanyContext()?.current_company_id ?? '',
  per_page: Number(props.filters?.per_page ?? props.rows?.per_page ?? 25),
});

let timer;
watch(filters, (val) => {
  clearTimeout(timer);
  timer = setTimeout(() => router.get(route('erp.accounting.operational'), val, { preserveState: true, replace: true }), 350);
}, { deep: true });

const form = useForm({
  project_id: '',
  cash_account_id: '',
  amount: 0,
  date: new Date().toISOString().slice(0, 10),
  recipient_name: '',
  note: '',
});

const resetAddForm = () => {
  form.reset();
  form.project_id = '';
  form.cash_account_id = props.cashAccounts?.[0]?.id ?? '';
  form.amount = 0;
  form.date = new Date().toISOString().slice(0, 10);
  form.recipient_name = '';
  form.note = '';
};

const openModal = () => {
  resetAddForm();
  document.getElementById('modal-operational')?.showModal();
};

const submit = () => form.post(route('erp.accounting.operational.store'), {
  preserveScroll: true,
  onSuccess: () => {
    resetAddForm();
    document.getElementById('modal-operational')?.close();
  },
});

const editForm = useForm({
  id: '',
  project_id: '',
  cash_account_id: '',
  amount: 0,
  date: '',
  recipient_name: '',
  note: '',
});

const deleting = useForm({});

const openEditModal = (row) => {
  editForm.id = row.id;
  editForm.project_id = row.project_id || '';
  editForm.cash_account_id = row.cash_account_id || props.cashAccounts?.[0]?.id || '';
  editForm.amount = row.amount;
  editForm.date = row.date;
  editForm.recipient_name = row.recipient_name || '';
  editForm.note = row.note || '';
  document.getElementById('modal-operational-edit')?.showModal();
};

const submitEdit = () => editForm.patch(route('erp.accounting.operational.update', editForm.id), {
  preserveScroll: true,
  onSuccess: () => document.getElementById('modal-operational-edit')?.close(),
});

const destroyRow = (row) => {
  if (!confirm('Hapus biaya operasional ini?')) return;
  deleting.delete(route('erp.accounting.operational.destroy', row.id), { preserveScroll: true });
};
</script>

<template>
  <Head title="Accounting - Operational" />
  <AppLayout>
    <div class="space-y-5">
      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Accounting Workspace</p>
              <h1 class="ocn-panel__title mt-1">Operational</h1>
              <p class="ocn-panel__desc mt-1">Pencatatan biaya operasional umum (tanpa project) atau per project.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
              <div class="flex items-center gap-2">
            <button class="btn btn-primary btn-sm" @click="openModal">+ Input Operasional</button>
            <Link class="btn btn-ghost btn-sm shrink-0 gap-1.5" :href="route('erp.accounting')">
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
          <h2 class="ocn-panel__title">Ringkasan</h2>
        </div>
        <div class="card-body py-4">
          <p class="text-xs uppercase tracking-wide text-base-content/60">Total (filter aktif)</p>
          <p class="text-xl font-bold text-error">{{ format(total || 0) }}</p>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Filter operasional</h2>
        </div>
        <div class="card-body">
          <div class="grid gap-3 md:grid-cols-5">
            <select v-if="erpCompanyContext()?.companies?.length" v-model="filters.company_id" class="select select-bordered select-sm w-full">
              <option value="all">Semua Usaha</option>
              <option v-for="c in erpCompanyContext().companies" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
            <select v-model="filters.project_id" class="select select-bordered select-sm w-full">
              <option value="">Semua (termasuk operasional umum)</option>
              <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
            <input v-model="filters.date_from" type="date" class="input input-bordered input-sm w-full" />
            <input v-model="filters.date_to" type="date" class="input input-bordered input-sm w-full" />
            <input v-model="filters.q" type="text" class="input input-bordered input-sm w-full" placeholder="Cari catatan/penerima/project..." />
          </div>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head flex items-center justify-between gap-2">
          <h2 class="ocn-panel__title">Daftar operasional</h2>
          <span class="text-xs text-base-content/60">{{ rows?.total ?? rowItems().length }} transaksi</span>
        </div>
        <div class="overflow-x-auto">
          <table class="table table-zebra">
            <thead>
              <tr>
                <th>Tanggal</th>
                <th>Project</th>
                <th>Penerima</th>
                <th>Jumlah</th>
                <th>Status</th>
                <th>Jurnal</th>
                <th>Keterangan</th>
                <th>Oleh</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in rowItems()" :key="row.id">
                <td class="whitespace-nowrap">{{ formatDate(row.date) }}</td>
                <td class="font-medium">{{ row.project_name }}</td>
                <td>{{ row.recipient_name || '-' }}</td>
                <td class="font-semibold text-error">{{ format(row.amount) }}</td>
                <td><StatusBadge :status="row.document_status" /></td>
                <td class="font-mono text-xs">{{ row.journal_entry_id ?? '-' }}</td>
                <td class="max-w-xs truncate text-sm text-base-content/70">{{ row.note || '-' }}</td>
                <td class="text-sm text-base-content/70">{{ row.creator_name }}</td>
                <td>
                  <div class="flex gap-1">
                    <button class="btn btn-ghost btn-xs" @click="openEditModal(row)">Edit</button>
                    <button class="btn btn-ghost btn-xs text-error" @click="destroyRow(row)">Hapus</button>
                  </div>
                </td>
              </tr>
              <tr v-if="!rowItems().length">
                <td colspan="9" class="py-10 text-center text-base-content/50">Tidak ada data.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <DataTablePagination :paginator="rows" @update:per-page="(n) => { filters.per_page = n; }" />
      </div>
    </div>

    <dialog id="modal-operational" class="modal">
      <div class="modal-box max-w-xl">
        <h3 class="font-bold text-lg">Input Biaya Operasional</h3>
        <div class="mt-4 space-y-3">
          <div>
            <label class="label"><span class="label-text">Project (Opsional)</span></label>
            <select v-model="form.project_id" class="select select-bordered w-full">
              <option value="">Operasional Umum (tanpa project)</option>
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
          <CurrencyInput v-model="form.amount" label="Jumlah" :required="true" :error="form.errors.amount" />
          <div>
            <label class="label"><span class="label-text">Tanggal</span></label>
            <input v-model="form.date" type="date" class="input input-bordered w-full" />
            <p v-if="form.errors.date" class="text-error text-xs mt-1">{{ form.errors.date }}</p>
          </div>
          <div>
            <label class="label"><span class="label-text">Penerima</span></label>
            <input v-model="form.recipient_name" type="text" class="input input-bordered w-full" placeholder="Nama penerima (opsional)" />
            <p v-if="form.errors.recipient_name" class="text-error text-xs mt-1">{{ form.errors.recipient_name }}</p>
          </div>
          <div>
            <label class="label"><span class="label-text">Keterangan</span></label>
            <textarea v-model="form.note" class="textarea textarea-bordered w-full" rows="3" />
            <p v-if="form.errors.note" class="text-error text-xs mt-1">{{ form.errors.note }}</p>
          </div>
        </div>
        <div class="modal-action">
          <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
          <button class="btn btn-primary" :disabled="form.processing" @click="submit">Simpan</button>
        </div>
      </div>
    </dialog>

    <dialog id="modal-operational-edit" class="modal">
      <div class="modal-box max-w-xl">
        <h3 class="font-bold text-lg">Edit Biaya Operasional</h3>
        <div class="mt-4 space-y-3">
          <div>
            <label class="label"><span class="label-text">Project (Opsional)</span></label>
            <select v-model="editForm.project_id" class="select select-bordered w-full">
              <option value="">Operasional Umum (tanpa project)</option>
              <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
          </div>
          <div>
            <label class="label"><span class="label-text">Sumber Dana Kas/Bank</span></label>
            <select v-model="editForm.cash_account_id" class="select select-bordered w-full">
              <option v-for="acc in cashAccounts" :key="acc.id" :value="acc.id">{{ acc.code }} - {{ acc.name }}</option>
            </select>
          </div>
          <CurrencyInput v-model="editForm.amount" label="Jumlah" :required="true" :error="editForm.errors.amount" />
          <div>
            <label class="label"><span class="label-text">Tanggal</span></label>
            <input v-model="editForm.date" type="date" class="input input-bordered w-full" />
          </div>
          <div>
            <label class="label"><span class="label-text">Penerima</span></label>
            <input v-model="editForm.recipient_name" type="text" class="input input-bordered w-full" />
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
