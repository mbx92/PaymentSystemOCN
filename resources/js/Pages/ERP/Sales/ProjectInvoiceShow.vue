<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CurrencyInput from '@/Components/CurrencyInput.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { computed, ref } from 'vue';
import { useCurrency } from '@/composables/useCurrency';
import { useDateFormat } from '@/composables/useDateFormat';

const props = defineProps({
  invoice: Object,
  paymentMethods: Array,
  cashAccounts: Array,
});

const { format } = useCurrency();
const { formatDate } = useDateFormat();
const paymentSearch = ref('');
const editingPayment = ref(null);

const paymentProgress = computed(() => {
  const amount = Number(props.invoice?.amount || 0);
  if (!amount) return 0;
  return Math.min((Number(props.invoice?.paid_amount || 0) / amount) * 100, 100);
});

const paymentItems = computed(() => {
  const term = paymentSearch.value.trim().toLowerCase();
  const rows = [...(props.invoice?.cash_ins || [])];
  const sorted = rows.sort((a, b) => (b.date || '').localeCompare(a.date || ''));
  if (!term) return sorted;
  return sorted.filter((payment) =>
    (payment.payment_method_name || '').toLowerCase().includes(term)
    || (payment.creator_name || '').toLowerCase().includes(term)
    || (payment.note || '').toLowerCase().includes(term)
    || (payment.date || '').toLowerCase().includes(term)
  );
});
const paymentForm = useForm({
  amount: props.invoice.remaining_amount || 0,
  date: new Date().toISOString().slice(0, 10),
  payment_method_id: props.paymentMethods?.[0]?.id ?? '',
  cash_account_id: props.cashAccounts?.[0]?.id ?? '',
  note: '',
});
const submitPayment = () => {
  paymentForm.post(route('erp.sales.project-invoices.payments.store', props.invoice.id), {
    preserveScroll: true,
    onSuccess: () => {
      paymentForm.reset('note');
      paymentForm.amount = 0;
      document.getElementById('modal-add-invoice-payment')?.close();
    },
  });
};

const editPaymentForm = useForm({
  amount: 0,
  date: new Date().toISOString().slice(0, 10),
  payment_method_id: '',
  cash_account_id: '',
  note: '',
});

const openEditPaymentModal = (payment) => {
  editingPayment.value = payment;
  editPaymentForm.amount = Number(payment.amount || 0);
  editPaymentForm.date = payment.date || new Date().toISOString().slice(0, 10);
  editPaymentForm.payment_method_id = payment.payment_method_id || props.paymentMethods?.[0]?.id || '';
  editPaymentForm.cash_account_id = payment.cash_account_id || props.cashAccounts?.[0]?.id || '';
  editPaymentForm.note = payment.note || '';
  document.getElementById('modal-edit-invoice-payment')?.showModal();
};

const submitEditPayment = () => {
  if (!editingPayment.value) return;
  editPaymentForm.patch(route('erp.sales.project-invoices.payments.update', {
    project: props.invoice.id,
    cashIn: editingPayment.value.id,
  }), {
    preserveScroll: true,
    onSuccess: () => {
      document.getElementById('modal-edit-invoice-payment')?.close();
      editingPayment.value = null;
    },
  });
};

const openPaymentModal = () => {
  paymentForm.amount = props.invoice.remaining_amount || props.invoice.amount || 0;
  paymentForm.date = new Date().toISOString().slice(0, 10);
  paymentForm.payment_method_id = props.paymentMethods?.[0]?.id ?? '';
  paymentForm.cash_account_id = props.cashAccounts?.[0]?.id ?? '';
  document.getElementById('modal-add-invoice-payment')?.showModal();
};

const downloadInvoice = () => window.open(route('erp.sales.project-invoices.download', props.invoice.id), '_blank');
const downloadSalesNote = () => window.open(route('erp.sales.project-invoices.sales-note', props.invoice.id), '_blank');
const downloadReceipt = (payment) => window.open(route('erp.sales.project-invoices.receipt', {
  project: props.invoice.id,
  cashIn: payment.id,
}), '_blank');
</script>

<template>
  <Head :title="`Invoice - ${invoice.number}`" />
  <AppLayout>
    <div class="space-y-5">
      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Sales Workspace</p>
              <h1 class="ocn-panel__title mt-1">{{ invoice.number }}</h1>
              <p class="text-sm text-base-content/60 mt-1">{{ invoice.project }} · {{ invoice.client }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
              <div class="flex flex-wrap items-center gap-2">
            <StatusBadge :status="invoice.status" />
            <button class="btn btn-outline btn-sm" @click="downloadInvoice">Download Invoice</button>
            <button class="btn btn-outline btn-sm" @click="downloadSalesNote">Download Nota Penjualan</button>
            <button class="btn btn-primary btn-sm" :disabled="invoice.remaining_amount <= 0" @click="openPaymentModal">Tambah Pembayaran</button>
            <Link class="btn btn-ghost btn-sm shrink-0 gap-1.5" :href="route('erp.sales')">
            <ArrowLeftIcon class="h-4 w-4" />
            Back
          </Link>
          </div>
            </div>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
        <article class="rounded-2xl border border-slate-700/40 bg-gradient-to-br from-slate-800 to-slate-950 p-5 text-white shadow-xl">
          <p class="text-xs font-semibold uppercase tracking-wide text-white/55">Nilai Invoice</p>
          <p class="mt-3 text-xl font-bold">{{ format(invoice.amount) }}</p>
        </article>
        <article class="rounded-2xl border border-emerald-900/50 bg-gradient-to-br from-emerald-900 to-emerald-950 p-5 text-white shadow-xl">
          <p class="text-xs font-semibold uppercase tracking-wide text-emerald-100/70">Terbayar</p>
          <p class="mt-3 text-xl font-bold">{{ format(invoice.paid_amount) }}</p>
        </article>
        <article class="rounded-2xl border border-amber-900/50 bg-gradient-to-br from-amber-900 to-amber-950 p-5 text-white shadow-xl">
          <p class="text-xs font-semibold uppercase tracking-wide text-amber-100/70">Sisa Tagihan</p>
          <p class="mt-3 text-xl font-bold">{{ format(invoice.remaining_amount) }}</p>
        </article>
        <article class="rounded-2xl border border-indigo-800/50 bg-gradient-to-br from-indigo-800 to-violet-950 p-5 text-white shadow-xl">
          <p class="text-xs font-semibold uppercase tracking-wide text-indigo-100/70">Tanggal Selesai</p>
          <p class="mt-3 text-xl font-bold whitespace-nowrap">{{ formatDate(invoice.finished_at) }}</p>
        </article>
      </div>

      <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div class="ocn-panel">
          <div class="ocn-panel__head">
            <h2 class="ocn-panel__title">Detail project</h2>
          </div>
          <div class="card-body">
            <div class="grid grid-cols-2 gap-2 text-sm">
              <div class="text-base-content/60">Client</div><div>{{ invoice.client }}</div>
              <div class="text-base-content/60">Kontak</div><div>{{ invoice.client_contact || '-' }}</div>
              <div class="text-base-content/60">Tipe</div><div>{{ invoice.project_type_label || invoice.project_type }}</div>
              <div class="text-base-content/60">Mulai</div><div>{{ formatDate(invoice.started_at) }}</div>
              <div class="text-base-content/60">Selesai</div><div class="whitespace-nowrap">{{ formatDate(invoice.finished_at) }}</div>
              <div class="text-base-content/60">Deskripsi</div><div>{{ invoice.description || '-' }}</div>
            </div>
          </div>
        </div>

        <div class="ocn-panel">
          <div class="ocn-panel__head">
            <h2 class="ocn-panel__title">Termin project</h2>
          </div>
          <div class="card-body">
            <div class="space-y-2">
              <div v-for="term in invoice.payments" :key="term.id" class="rounded-lg border border-base-300 p-3 text-sm">
                <div class="flex items-center justify-between gap-3">
                  <span class="font-medium">Termin {{ term.term_number }} · {{ term.percentage }}%</span>
                  <span class="font-semibold">{{ format(term.amount) }}</span>
                </div>
                <p class="text-xs text-base-content/60">{{ term.note || '-' }}</p>
              </div>
              <div v-if="!invoice.payments.length" class="rounded-lg border border-base-300 p-4 text-center text-sm text-base-content/50">Tidak ada termin.</div>
            </div>
          </div>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Item tagihan</h2>
        </div>
        <div class="card-body">
          <div class="overflow-x-auto">
            <table class="table table-sm min-w-[760px]">
              <thead>
                <tr>
                  <th>Item</th>
                  <th class="text-right">Qty</th>
                  <th>UoM</th>
                  <th class="w-40 text-right whitespace-nowrap">Harga</th>
                  <th class="w-44 text-right whitespace-nowrap">Subtotal</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(item, index) in (invoice.line_items || [])" :key="index">
                  <td>
                    <p class="font-medium">{{ item.name }}</p>
                    <p v-if="item.description" class="text-xs text-base-content/60">{{ item.description }}</p>
                  </td>
                  <td class="text-right whitespace-nowrap">{{ item.qty }}</td>
                  <td class="whitespace-nowrap">{{ item.uom }}</td>
                  <td class="w-40 text-right whitespace-nowrap">{{ format(item.unit_price) }}</td>
                  <td class="w-44 text-right font-semibold whitespace-nowrap">{{ format(item.subtotal) }}</td>
                </tr>
                <tr v-if="!(invoice.line_items || []).length">
                  <td colspan="5" class="text-center text-sm text-base-content/50">Belum ada item.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head flex flex-wrap items-center justify-between gap-2">
          <h2 class="ocn-panel__title">Pembayaran invoice</h2>
          <button class="btn btn-primary btn-sm shrink-0" :disabled="invoice.remaining_amount <= 0" @click="openPaymentModal">+ Pembayaran</button>
        </div>
        <div class="card-body space-y-4">
          <div class="rounded-xl border border-base-300 bg-base-200/40 p-4">
            <div class="flex items-center justify-between gap-3 text-sm">
              <span class="text-base-content/70">Progress pembayaran</span>
              <span class="font-semibold">{{ paymentProgress.toFixed(1) }}%</span>
            </div>
            <progress class="progress progress-success mt-2 w-full" :value="paymentProgress" max="100" />
            <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-xs text-base-content/70">
              <span>Total bayar: <strong class="text-success">{{ format(invoice.paid_amount) }}</strong></span>
              <span>Sisa: <strong class="text-warning">{{ format(invoice.remaining_amount) }}</strong></span>
            </div>
          </div>

          <div class="flex flex-wrap items-center gap-3">
            <label class="input input-bordered input-sm flex items-center gap-2 w-full md:w-80">
              <input v-model="paymentSearch" type="text" class="grow" placeholder="Cari metode / catatan / pencatat..." />
            </label>
            <span class="text-xs text-base-content/60">{{ paymentItems.length }} pembayaran</span>
          </div>

          <div class="space-y-3">
            <article
              v-for="payment in paymentItems"
              :key="payment.id"
              class="rounded-xl border border-base-300 bg-base-100 p-4 transition hover:border-primary/40 hover:shadow-sm"
            >
              <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="space-y-1">
                  <div class="flex flex-wrap items-center gap-2">
                    <span class="badge badge-ghost badge-sm">{{ payment.payment_method_name || 'Metode N/A' }}</span>
                    <span class="text-xs text-base-content/60">{{ formatDate(payment.date) }}</span>
                  </div>
                  <p class="text-sm text-base-content/80">{{ payment.note || 'Tanpa catatan pembayaran.' }}</p>
                  <p class="text-xs text-base-content/60">Dicatat oleh: {{ payment.creator_name || '-' }}</p>
                </div>
                <div class="text-right">
                  <p class="text-lg font-bold text-success">{{ format(payment.amount) }}</p>
                  <button class="btn btn-ghost btn-xs mt-1" @click="downloadReceipt(payment)">Download Kwitansi</button>
                  <button class="btn btn-ghost btn-xs mt-1" @click="openEditPaymentModal(payment)">Edit Pembayaran</button>
                </div>
              </div>
            </article>
          </div>

          <div v-if="!paymentItems.length" class="rounded-xl border border-dashed border-base-300 p-8 text-center text-base-content/50">
            Belum ada pembayaran yang cocok.
          </div>
        </div>
      </div>
    </div>

    <dialog id="modal-add-invoice-payment" class="modal">
      <div class="modal-box max-w-xl">
        <h3 class="font-bold text-lg">Tambah Pembayaran Invoice</h3>
        <div class="mt-4 space-y-3">
          <div>
            <label class="label"><span class="label-text">Tanggal Bayar</span></label>
            <input v-model="paymentForm.date" type="date" class="input input-bordered w-full" />
            <p v-if="paymentForm.errors.date" class="text-error text-xs mt-1">{{ paymentForm.errors.date }}</p>
          </div>
          <CurrencyInput
            v-model="paymentForm.amount"
            label="Jumlah"
            :required="true"
            :error="paymentForm.errors.amount"
          />
          <p class="text-xs text-base-content/60 -mt-1">Sisa tagihan: {{ format(invoice.remaining_amount) }}</p>
          <div>
            <label class="label"><span class="label-text">Akun Kas/Bank</span></label>
            <select v-model="paymentForm.cash_account_id" class="select select-bordered w-full" :disabled="!(cashAccounts || []).length">
              <option value="" disabled>{{ (cashAccounts || []).length ? 'Pilih akun kas/bank' : 'Belum ada akun kas/bank aktif' }}</option>
              <option v-for="account in (cashAccounts || [])" :key="account.id" :value="account.id">
                {{ account.code }} - {{ account.name }}
              </option>
            </select>
            <p v-if="!(cashAccounts || []).length" class="text-xs text-warning mt-1">Centang &quot;Kas/Bank&quot; pada akun asset di Chart of Accounts.</p>
            <p v-if="paymentForm.errors.cash_account_id" class="text-error text-xs mt-1">{{ paymentForm.errors.cash_account_id }}</p>
          </div>
          <div>
            <label class="label"><span class="label-text">Metode Pembayaran</span></label>
            <select v-model="paymentForm.payment_method_id" class="select select-bordered w-full">
              <option value="" disabled>Pilih metode</option>
              <option v-for="method in paymentMethods" :key="method.id" :value="method.id">{{ method.name }}</option>
            </select>
            <p v-if="paymentForm.errors.payment_method_id" class="text-error text-xs mt-1">{{ paymentForm.errors.payment_method_id }}</p>
          </div>
          <div>
            <label class="label"><span class="label-text">Catatan</span></label>
            <textarea v-model="paymentForm.note" class="textarea textarea-bordered w-full" rows="3" placeholder="Contoh: Pelunasan invoice / transfer BCA" />
            <p v-if="paymentForm.errors.note" class="text-error text-xs mt-1">{{ paymentForm.errors.note }}</p>
          </div>
        </div>
        <div class="modal-action">
          <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
          <button class="btn btn-primary" :disabled="paymentForm.processing" @click="submitPayment">Simpan Pembayaran</button>
        </div>
      </div>
    </dialog>

    <dialog id="modal-edit-invoice-payment" class="modal">
      <div class="modal-box max-w-xl">
        <h3 class="font-bold text-lg">Edit Pembayaran Invoice</h3>
        <div class="mt-4 space-y-3">
          <div>
            <label class="label"><span class="label-text">Tanggal Bayar</span></label>
            <input v-model="editPaymentForm.date" type="date" class="input input-bordered w-full" />
            <p v-if="editPaymentForm.errors.date" class="text-error text-xs mt-1">{{ editPaymentForm.errors.date }}</p>
          </div>
          <div>
            <CurrencyInput
              v-model="editPaymentForm.amount"
              label="Jumlah"
              :required="true"
              :error="editPaymentForm.errors.amount"
            />
          </div>
          <div>
            <label class="label"><span class="label-text">Akun Kas/Bank</span></label>
            <select v-model="editPaymentForm.cash_account_id" class="select select-bordered w-full" :disabled="!(cashAccounts || []).length">
              <option value="" disabled>{{ (cashAccounts || []).length ? 'Pilih akun kas/bank' : 'Belum ada akun kas/bank aktif' }}</option>
              <option v-for="account in (cashAccounts || [])" :key="account.id" :value="account.id">
                {{ account.code }} - {{ account.name }}
              </option>
            </select>
            <p v-if="editPaymentForm.errors.cash_account_id" class="text-error text-xs mt-1">{{ editPaymentForm.errors.cash_account_id }}</p>
          </div>
          <div>
            <label class="label"><span class="label-text">Metode Pembayaran</span></label>
            <select v-model="editPaymentForm.payment_method_id" class="select select-bordered w-full">
              <option value="" disabled>Pilih metode</option>
              <option v-for="method in paymentMethods" :key="method.id" :value="method.id">{{ method.name }}</option>
            </select>
            <p v-if="editPaymentForm.errors.payment_method_id" class="text-error text-xs mt-1">{{ editPaymentForm.errors.payment_method_id }}</p>
          </div>
          <div>
            <label class="label"><span class="label-text">Catatan</span></label>
            <textarea v-model="editPaymentForm.note" class="textarea textarea-bordered w-full" rows="3" />
            <p v-if="editPaymentForm.errors.note" class="text-error text-xs mt-1">{{ editPaymentForm.errors.note }}</p>
          </div>
        </div>
        <div class="modal-action">
          <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
          <button class="btn btn-primary" :disabled="editPaymentForm.processing" @click="submitEditPayment">Simpan Perubahan</button>
        </div>
      </div>
    </dialog>
  </AppLayout>
</template>
