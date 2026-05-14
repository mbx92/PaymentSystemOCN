<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { computed, ref } from 'vue';
import { useCurrency } from '@/composables/useCurrency';

const props = defineProps({
  payables: Array,
  summary: Object,
  cashAccounts: Array,
});

const { format } = useCurrency();
const selectedPayable = ref(null);

const paymentForm = useForm({
  payment_date: new Date().toISOString().slice(0, 10),
  amount: 0,
  cash_account_id: '',
  note: '',
});

const openPayablePayment = (payable) => {
  selectedPayable.value = payable;
  paymentForm.reset();
  paymentForm.payment_date = new Date().toISOString().slice(0, 10);
  paymentForm.amount = Number(payable.outstanding_amount || 0);
  paymentForm.cash_account_id = props.cashAccounts?.[0]?.id ?? '';
  paymentForm.note = '';
  document.getElementById('modal-pay-supplier')?.showModal();
};

const submitSupplierPayment = () => {
  if (!selectedPayable.value) return;
  paymentForm.post(route('erp.accounting.payments.supplier.store', selectedPayable.value.id), {
    preserveScroll: true,
    onSuccess: () => {
      paymentForm.reset();
      selectedPayable.value = null;
      document.getElementById('modal-pay-supplier')?.close();
    },
  });
};

const openPayables = computed(() => (props.payables ?? []).filter((row) => Number(row.outstanding_amount || 0) > 0));
const paidPayables = computed(() => (props.payables ?? []).filter((row) => Number(row.outstanding_amount || 0) <= 0));
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

      <div class="grid gap-4 md:grid-cols-3">
        <div class="stats shadow">
          <div class="stat py-3">
            <div class="stat-title text-sm">Hutang Supplier</div>
            <div class="stat-value text-xl text-warning">{{ format(summary?.outstanding_total ?? 0) }}</div>
          </div>
        </div>
        <div class="stats shadow">
          <div class="stat py-3">
            <div class="stat-title text-sm">Sudah Dibayar</div>
            <div class="stat-value text-xl text-success">{{ format(summary?.paid_total ?? 0) }}</div>
          </div>
        </div>
        <div class="stats shadow">
          <div class="stat py-3">
            <div class="stat-title text-sm">Bill Terbuka</div>
            <div class="stat-value text-xl">{{ summary?.open_count ?? 0 }}</div>
          </div>
        </div>
      </div>

      <div class="grid gap-4 lg:grid-cols-3">
        <div class="ocn-panel">
          <div class="ocn-panel__head">
            <h2 class="ocn-panel__title">Pembayaran invoice project</h2>
            <p class="ocn-panel__desc">Kelola penerimaan pembayaran dari client dan status pelunasan invoice.</p>
          </div>
          <div class="card-body">
            <p class="text-sm text-base-content/70">
              Input pembayaran, edit nominal, dan cetak kwitansi invoice project.
            </p>
            <div class="mt-4">
              <Link :href="route('erp.sales.project-invoices')" class="btn btn-primary btn-sm">Buka Invoice Project</Link>
            </div>
          </div>
        </div>

        <div class="ocn-panel">
          <div class="ocn-panel__head">
            <h2 class="ocn-panel__title">Pembayaran anggota tim</h2>
            <p class="ocn-panel__desc">Pantau distribusi pembayaran anggota berdasarkan project dan periode.</p>
          </div>
          <div class="card-body">
            <p class="text-sm text-base-content/70">
              Akses laporan pembayaran anggota untuk validasi pembagian tim dan proses approval internal.
            </p>
            <div class="mt-4">
              <Link :href="route('reports.member-payments')" class="btn btn-primary btn-sm">Buka Pembayaran Anggota</Link>
            </div>
          </div>
        </div>

        <div class="ocn-panel">
          <div class="ocn-panel__head">
            <h2 class="ocn-panel__title">Pembayaran supplier</h2>
            <p class="ocn-panel__desc">Pelunasan PO yang sudah menjadi hutang usaha.</p>
          </div>
          <div class="card-body">
            <p class="text-sm text-base-content/70">
              Pembayaran akan menjurnal debit Hutang Usaha dan kredit Kas/Bank.
            </p>
            <div class="mt-4">
              <a href="#supplier-payables" class="btn btn-primary btn-sm">Lihat Hutang Supplier</a>
            </div>
          </div>
        </div>
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
                <td>{{ payable.due_date ?? '-' }}</td>
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

      <div v-if="paidPayables.length" class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Riwayat bill lunas</h2>
        </div>
        <div class="overflow-x-auto">
          <table class="table table-sm">
            <thead>
              <tr>
                <th>Bill</th>
                <th>Supplier</th>
                <th>PO</th>
                <th class="text-right">Total</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="payable in paidPayables" :key="payable.id">
                <td class="font-mono text-xs">{{ payable.bill_no }}</td>
                <td>{{ payable.vendor_name ?? '-' }}</td>
                <td class="font-mono text-xs">{{ payable.po_number ?? '-' }}</td>
                <td class="text-right">{{ format(payable.amount) }}</td>
                <td><StatusBadge :status="payable.status" /></td>
              </tr>
            </tbody>
          </table>
        </div>
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
              <p v-if="paymentForm.errors.payment_date" class="mt-1 text-xs text-error">{{ paymentForm.errors.payment_date }}</p>
            </div>
            <div>
              <label class="label"><span class="label-text">Akun Kas/Bank</span></label>
              <select v-model="paymentForm.cash_account_id" class="select select-bordered w-full">
                <option value="" disabled>Pilih akun kas/bank</option>
                <option v-for="account in cashAccounts" :key="account.id" :value="account.id">
                  {{ account.code }} - {{ account.name }}
                </option>
              </select>
              <p v-if="paymentForm.errors.cash_account_id" class="mt-1 text-xs text-error">{{ paymentForm.errors.cash_account_id }}</p>
            </div>
            <div>
              <label class="label"><span class="label-text">Nominal</span></label>
              <input
                v-model.number="paymentForm.amount"
                type="number"
                min="0.01"
                step="0.01"
                class="input input-bordered w-full"
              />
              <p v-if="paymentForm.errors.amount" class="mt-1 text-xs text-error">{{ paymentForm.errors.amount }}</p>
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
