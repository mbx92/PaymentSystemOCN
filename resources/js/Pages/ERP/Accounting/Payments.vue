<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CurrencyInput from '@/Components/CurrencyInput.vue';
import DataTablePagination from '@/Components/DataTablePagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { computed, ref, watch } from 'vue';
import { useCurrency } from '@/composables/useCurrency';
import { useDateFormat } from '@/composables/useDateFormat';

const props = defineProps({
  payables: Array,
  paidPayables: Object,
  summary: Object,
  filters: Object,
  cashAccounts: Array,
});

const { formatDate } = useDateFormat();

const { format } = useCurrency();
const selectedPayable = ref(null);
/** Nilai nominal modal (terpisah dari useForm agar format ribuan sinkron saat buka). */
const modalAmount = ref(0);
const paidHistoryQ = ref(props.filters?.paid_history_q ?? '');
const paidHistoryPerPage = ref(Number(props.filters?.paid_history_per_page ?? props.paidPayables?.per_page ?? 25));

let paidHistorySearchTimer;

const paymentForm = useForm({
  payment_date: new Date().toISOString().slice(0, 10),
  amount: 0,
  cash_account_id: '',
  note: '',
});

const openPayablePayment = (payable) => {
  const amount = Number(payable.outstanding_amount || 0);
  modalAmount.value = amount;
  paymentForm.reset();
  paymentForm.payment_date = payable.is_legacy_procurement && payable.legacy_payment_date
    ? payable.legacy_payment_date
    : new Date().toISOString().slice(0, 10);
  paymentForm.amount = amount;
  paymentForm.cash_account_id = props.cashAccounts?.[0]?.id ?? '';
  paymentForm.note = '';
  selectedPayable.value = payable;
  document.getElementById('modal-pay-supplier')?.showModal();
};

const submitSupplierPayment = () => {
  if (!selectedPayable.value) return;
  paymentForm.amount = Number(modalAmount.value) || 0;
  paymentForm.post(route('erp.accounting.payments.supplier.store', selectedPayable.value.id), {
    preserveScroll: true,
    onSuccess: () => {
      paymentForm.reset();
      selectedPayable.value = null;
      modalAmount.value = 0;
      document.getElementById('modal-pay-supplier')?.close();
    },
  });
};

const openPayables = computed(() => (props.payables ?? []).filter((row) => Number(row.outstanding_amount || 0) > 0));
const paidPayablePaginator = computed(() => props.paidPayables ?? { data: [], links: [], total: 0, per_page: paidHistoryPerPage.value });
const paidPayableRows = computed(() => props.paidPayables?.data ?? []);

watch(paidHistoryQ, (value) => {
  clearTimeout(paidHistorySearchTimer);
  paidHistorySearchTimer = setTimeout(() => {
    router.get(route('erp.accounting.payments'), {
      paid_history_q: value || undefined,
      paid_history_per_page: paidHistoryPerPage.value,
    }, { preserveState: true, preserveScroll: true, replace: true });
  }, 300);
});

watch(paidHistoryPerPage, (value) => {
  router.get(route('erp.accounting.payments'), {
    paid_history_q: paidHistoryQ.value || undefined,
    paid_history_per_page: value,
  }, { preserveState: true, preserveScroll: true, replace: true });
});
</script>

<template>
  <Head title="Accounting - Pembayaran" />
  <AppLayout>
    <div class="space-y-5">
      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Accounting Workspace</p>
              <h1 class="ocn-panel__title mt-1">Pembayaran</h1>
              <p class="ocn-panel__desc mt-1">Kelola penerimaan invoice project, pembayaran anggota, dan pelunasan hutang supplier dari PO.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
              <Link class="btn btn-ghost btn-sm shrink-0 gap-1.5" :href="route('erp.accounting')">
                <ArrowLeftIcon class="h-4 w-4" />
                Back
              </Link>
            </div>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
        <div class="rounded-xl border border-base-300 bg-base-100 p-3 shadow-sm">
          <p class="text-[11px] uppercase text-base-content/50">Hutang Supplier</p>
          <p class="mt-1 font-semibold tabular-nums text-warning">{{ format(summary?.outstanding_total ?? 0) }}</p>
        </div>
        <div class="rounded-xl border border-base-300 bg-base-100 p-3 shadow-sm">
          <p class="text-[11px] uppercase text-base-content/50">Sudah Dibayar</p>
          <p class="mt-1 font-semibold tabular-nums text-success">{{ format(summary?.paid_total ?? 0) }}</p>
        </div>
        <div class="rounded-xl border border-base-300 bg-base-100 p-3 shadow-sm col-span-2 sm:col-span-1">
          <p class="text-[11px] uppercase text-base-content/50">Bill Terbuka</p>
          <p class="mt-1 font-semibold tabular-nums">{{ summary?.open_count ?? 0 }}</p>
        </div>
      </div>

      <div class="grid items-stretch gap-4 lg:grid-cols-3">
        <article class="ocn-panel flex h-full flex-col">
          <header class="ocn-panel__head shrink-0">
            <h2 class="ocn-panel__title min-h-[2.75rem] line-clamp-2">Pembayaran invoice project</h2>
            <p class="ocn-panel__desc min-h-[2.5rem] line-clamp-2">Kelola penerimaan pembayaran dari client dan status pelunasan invoice.</p>
          </header>
          <div class="card-body flex min-h-0 flex-1 flex-col">
            <p class="flex-1 text-sm leading-relaxed text-base-content/70">
              Input pembayaran, edit nominal, dan cetak kwitansi invoice project.
            </p>
            <footer class="mt-4 shrink-0 border-t border-base-200/80 pt-4">
              <Link :href="route('erp.sales.project-invoices')" class="btn btn-primary btn-sm w-full">Buka Invoice Project</Link>
            </footer>
          </div>
        </article>

        <article class="ocn-panel flex h-full flex-col">
          <header class="ocn-panel__head shrink-0">
            <h2 class="ocn-panel__title min-h-[2.75rem] line-clamp-2">Pembayaran anggota tim</h2>
            <p class="ocn-panel__desc min-h-[2.5rem] line-clamp-2">Pantau distribusi pembayaran anggota berdasarkan project dan periode.</p>
          </header>
          <div class="card-body flex min-h-0 flex-1 flex-col">
            <p class="flex-1 text-sm leading-relaxed text-base-content/70">
              Akses laporan pembayaran anggota untuk validasi pembagian tim dan proses approval internal.
            </p>
            <footer class="mt-4 shrink-0 border-t border-base-200/80 pt-4">
              <Link :href="route('erp.accounting.payments.member')" class="btn btn-primary btn-sm w-full">Buka Pembayaran Anggota</Link>
            </footer>
          </div>
        </article>

        <article class="ocn-panel flex h-full flex-col">
          <header class="ocn-panel__head shrink-0">
            <h2 class="ocn-panel__title min-h-[2.75rem] line-clamp-2">Pembayaran supplier</h2>
            <p class="ocn-panel__desc min-h-[2.5rem] line-clamp-2">Pelunasan PO yang sudah menjadi hutang usaha.</p>
          </header>
          <div class="card-body flex min-h-0 flex-1 flex-col">
            <p class="flex-1 text-sm leading-relaxed text-base-content/70">
              Pembayaran akan menjurnal debit Hutang Usaha dan kredit Kas/Bank.
            </p>
            <footer class="mt-4 shrink-0 border-t border-base-200/80 pt-4">
              <a href="#supplier-payables" class="btn btn-primary btn-sm w-full">Lihat Hutang Supplier</a>
            </footer>
          </div>
        </article>
      </div>

      <div id="supplier-payables" class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Hutang supplier dari PO</h2>
          <p class="ocn-panel__desc">Bill dibuat otomatis saat penerimaan barang diposting ke stok dan Hutang Usaha.</p>
        </div>
        <div class="overflow-x-auto">
          <table class="table table-zebra">
            <thead>
              <tr>
                <th>Bill</th>
                <th>Supplier</th>
                <th>PO / GRN</th>
                <th>Jatuh Tempo</th>
                <th class="text-right">Total</th>
                <th class="text-right">Dibayar</th>
                <th class="text-right">Sisa</th>
                <th>Status</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="payable in openPayables" :key="payable.id">
                <td class="font-mono text-xs">{{ payable.bill_no }}</td>
                <td>
                  <p class="font-medium">{{ payable.vendor_name ?? '-' }}</p>
                  <p class="text-xs text-base-content/50">{{ payable.vendor_code ?? '-' }}</p>
                </td>
                <td>
                  <p class="font-mono text-xs">{{ payable.po_number ?? '-' }}</p>
                  <p class="font-mono text-xs text-base-content/60">{{ payable.grn_number ?? '-' }}</p>
                </td>
                <td class="whitespace-nowrap">{{ formatDate(payable.due_date) }}</td>
                <td class="text-right">{{ format(payable.amount) }}</td>
                <td class="text-right text-success">{{ format(payable.paid_amount) }}</td>
                <td class="text-right font-semibold text-warning">{{ format(payable.outstanding_amount) }}</td>
                <td><StatusBadge :status="payable.status" /></td>
                <td class="text-right">
                  <button class="btn btn-primary btn-xs" @click="openPayablePayment(payable)">Bayar</button>
                </td>
              </tr>
              <tr v-if="openPayables.length === 0">
                <td colspan="9" class="py-8 text-center text-base-content/50">Tidak ada hutang supplier yang masih terbuka.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Riwayat bill lunas</h2>
        </div>
        <div class="card-body border-b border-base-200 pb-4">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <label class="input input-bordered input-sm flex w-full items-center gap-2 sm:max-w-md">
              <input
                v-model="paidHistoryQ"
                type="search"
                class="grow"
                placeholder="Cari bill, supplier, PO, atau GR..."
              />
            </label>
            <p class="text-xs text-base-content/60">
              Pencarian riwayat bill lunas diproses di server.
            </p>
          </div>
        </div>
        <div class="overflow-x-auto">
          <table class="table table-sm">
            <thead>
              <tr>
                <th>Bill</th>
                <th>Supplier</th>
                <th>PO / GRN</th>
                <th>Tgl Bill</th>
                <th class="text-right">Total</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="payable in paidPayableRows" :key="payable.id">
                <td class="font-mono text-xs">{{ payable.bill_no }}</td>
                <td>
                  <p class="font-medium">{{ payable.vendor_name ?? '-' }}</p>
                  <p class="text-xs text-base-content/50">{{ payable.vendor_code ?? '-' }}</p>
                </td>
                <td>
                  <p class="font-mono text-xs">{{ payable.po_number ?? '-' }}</p>
                  <p class="font-mono text-xs text-base-content/60">{{ payable.grn_number ?? '-' }}</p>
                </td>
                <td class="whitespace-nowrap">{{ formatDate(payable.bill_date) }}</td>
                <td class="text-right">{{ format(payable.amount) }}</td>
                <td><StatusBadge :status="payable.status" /></td>
              </tr>
              <tr v-if="paidPayableRows.length === 0">
                <td colspan="6" class="py-8 text-center text-base-content/50">Tidak ada bill lunas yang cocok dengan pencarian saat ini.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <DataTablePagination
          :paginator="paidPayablePaginator"
          @update:per-page="(n) => { paidHistoryPerPage = n; }"
        />
      </div>

      <dialog id="modal-pay-supplier" class="modal">
        <div class="modal-box max-w-xl">
          <h3 class="text-lg font-bold">Bayar Hutang Supplier</h3>
          <p v-if="selectedPayable" class="mt-1 text-sm text-base-content/70">
            {{ selectedPayable.bill_no }} - {{ selectedPayable.vendor_name }}. Sisa: {{ format(selectedPayable.outstanding_amount) }}
          </p>

          <div class="mt-4 grid gap-3">
            <div>
              <label class="label"><span class="label-text">Tanggal Bayar</span></label>
              <input v-model="paymentForm.payment_date" type="date" class="input input-bordered w-full" />
              <p v-if="selectedPayable?.is_legacy_procurement && selectedPayable?.legacy_payment_date" class="mt-1 text-xs text-base-content/60">
                Bill hasil import legacy akan memakai tanggal procurement: {{ formatDate(selectedPayable.legacy_payment_date) }}
              </p>
              <p v-if="paymentForm.errors.payment_date" class="mt-1 text-xs text-error">{{ paymentForm.errors.payment_date }}</p>
            </div>
            <div>
              <label class="label"><span class="label-text">Akun Kas/Bank</span></label>
              <select v-model="paymentForm.cash_account_id" class="select select-bordered w-full" :disabled="!(cashAccounts || []).length">
                <option value="" disabled>{{ (cashAccounts || []).length ? 'Pilih akun kas/bank' : 'Belum ada akun kas/bank aktif' }}</option>
                <option v-for="account in cashAccounts" :key="account.id" :value="account.id">
                  {{ account.code }} - {{ account.name }}
                </option>
              </select>
              <p v-if="!(cashAccounts || []).length" class="mt-1 text-xs text-warning">
                Tidak ada akun kas/bank. Centang &quot;Kas/Bank (penerimaan &amp; pembayaran)&quot; pada akun asset di Chart of Accounts.
              </p>
              <p v-if="paymentForm.errors.cash_account_id" class="mt-1 text-xs text-error">{{ paymentForm.errors.cash_account_id }}</p>
            </div>
            <div>
              <CurrencyInput
                v-if="selectedPayable"
                :key="selectedPayable.id"
                v-model="modalAmount"
                label="Nominal"
                :required="true"
                :error="paymentForm.errors.amount"
              />
            </div>
            <div>
              <label class="label"><span class="label-text">Catatan</span></label>
              <textarea v-model="paymentForm.note" class="textarea textarea-bordered w-full" rows="3"></textarea>
              <p v-if="paymentForm.errors.note" class="mt-1 text-xs text-error">{{ paymentForm.errors.note }}</p>
            </div>
          </div>

          <div class="modal-action">
            <form method="dialog">
              <button class="btn btn-ghost">Batal</button>
            </form>
            <button class="btn btn-primary" :disabled="paymentForm.processing" @click="submitSupplierPayment">
              Posting Pembayaran
            </button>
          </div>
        </div>
      </dialog>
    </div>
  </AppLayout>
</template>
