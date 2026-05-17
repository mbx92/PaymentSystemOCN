<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { computed, ref } from 'vue';
import { useCurrency } from '@/composables/useCurrency';

const props = defineProps({
  detail: Object,
  payment_methods: Array,
});

const { format } = useCurrency();

const paymentForm = useForm({
  payment_method_id: props.detail.payment_method_id ?? '',
  authorization_email: '',
  authorization_password: '',
});

const updatePaymentMethod = () => {
  paymentForm.patch(route('erp.sales.pos.transactions.payment-method.update', props.detail.id), {
    preserveScroll: true,
  });
};

const refundForm = useForm({});
const reopenForm = useForm({});
const authForm = useForm({
  authorization_email: '',
  authorization_password: '',
});

const refundTransaction = () => {
  refundForm.post(route('erp.sales.pos.transactions.refund', props.detail.id), {
    ...authPayload(),
    preserveScroll: true,
  });
};

const reopenTransaction = () => {
  reopenForm.post(route('erp.sales.pos.transactions.reopen', props.detail.id), {
    ...authPayload(),
    preserveScroll: true,
  });
};

const canRefund = computed(() => props.detail.status !== 'refunded');
const canReopen = computed(() => props.detail.status !== 'reopened');
const printingReceipt = ref(false);
const printReceiptError = ref('');
const printReceiptSuccess = ref('');

const authPayload = () => ({
  data: {
    authorization_email: authForm.authorization_email,
    authorization_password: authForm.authorization_password,
  },
});

const submitPaymentMethodUpdate = () => {
  paymentForm.authorization_email = authForm.authorization_email;
  paymentForm.authorization_password = authForm.authorization_password;
  updatePaymentMethod();
};

const openConfirmModal = (id) => document.getElementById(id)?.showModal();

const getCookieValue = (name) => {
  const value = `; ${document.cookie}`;
  const parts = value.split(`; ${name}=`);
  if (parts.length === 2) return parts.pop()?.split(';').shift() || '';
  return '';
};

const printReceipt = async () => {
  if (printingReceipt.value) return;

  printingReceipt.value = true;
  printReceiptError.value = '';
  printReceiptSuccess.value = '';

  try {
    const response = await fetch(route('erp.sales.pos.print-receipt'), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        'X-XSRF-TOKEN': decodeURIComponent(getCookieValue('XSRF-TOKEN') || ''),
      },
      credentials: 'same-origin',
      body: JSON.stringify({
        transaction_number: props.detail.number,
      }),
    });

    const payload = await response.json();
    if (!response.ok) {
      const firstError = payload?.errors ? Object.values(payload.errors)[0]?.[0] : null;
      throw new Error(firstError || payload?.message || 'Gagal mencetak struk ke printer thermal.');
    }

    printReceiptSuccess.value = payload?.message || `Struk ${props.detail.number} berhasil dikirim ke printer.`;
  } catch (error) {
    printReceiptError.value = error?.message || 'Gagal mencetak struk ke printer thermal.';
  } finally {
    printingReceipt.value = false;
  }
};
</script>

<template>
  <Head :title="`Sales - Transaksi ${detail.number}`" />
  <AppLayout>
    <div class="space-y-5">
      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Sales Workspace</p>
              <h1 class="ocn-panel__title mt-1">{{ detail.number }}</h1>
              <p class="ocn-panel__desc mt-1">Waktu transaksi: {{ detail.sold_at || '-' }} · Kasir: {{ detail.cashier || '-' }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
              <div class="flex items-center gap-2">
            <StatusBadge :status="detail.status" />
            <Link class="btn btn-ghost btn-sm shrink-0 gap-1.5" :href="route('erp.sales.pos.transactions')">
            <ArrowLeftIcon class="h-4 w-4" />
            Back
          </Link>
          </div>
            </div>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-3 lg:grid-cols-9">
        <div class="rounded-xl border border-base-300 bg-base-100 p-3 shadow-sm">
          <p class="text-[11px] uppercase text-base-content/50">Channel</p>
          <p class="mt-1 font-semibold">{{ detail.sales_channel_label || detail.sales_channel || '-' }}</p>
        </div>
        <div class="rounded-xl border border-base-300 bg-base-100 p-3 shadow-sm">
          <p class="text-[11px] uppercase text-base-content/50">Kode Pesanan</p>
          <p class="mt-1 font-mono text-sm font-semibold">{{ detail.marketplace_order_code || '-' }}</p>
        </div>
        <div class="rounded-xl border border-base-300 bg-base-100 p-3 shadow-sm">
          <p class="text-[11px] uppercase text-base-content/50">Gross</p>
          <p class="mt-1 font-semibold">{{ format(detail.gross_total) }}</p>
        </div>
        <div class="rounded-xl border border-base-300 bg-base-100 p-3 shadow-sm">
          <p class="text-[11px] uppercase text-base-content/50">Diskon</p>
          <p class="mt-1 font-semibold text-warning">- {{ format(detail.discount_total) }}</p>
        </div>
        <div class="rounded-xl border border-base-300 bg-base-100 p-3 shadow-sm">
          <p class="text-[11px] uppercase text-base-content/50">Biaya lain (ditagih)</p>
          <p class="mt-1 font-semibold">{{ format(detail.additional_fee) }}</p>
        </div>
        <div class="rounded-xl border border-base-300 bg-base-100 p-3 shadow-sm">
          <p class="text-[11px] uppercase text-base-content/50">Biaya admin channel (jurnal)</p>
          <p class="mt-1 font-semibold">{{ format(detail.sales_channel_admin_fee ?? 0) }}</p>
        </div>
        <div class="rounded-xl border border-base-300 bg-base-100 p-3 shadow-sm">
          <p class="text-[11px] uppercase text-base-content/50">Grand Total</p>
          <p class="mt-1 font-semibold text-primary">{{ format(detail.grand_total) }}</p>
        </div>
        <div class="rounded-xl border border-base-300 bg-base-100 p-3 shadow-sm">
          <p class="text-[11px] uppercase text-base-content/50">Bayar</p>
          <p class="mt-1 font-semibold">{{ format(detail.cash_paid) }}</p>
        </div>
        <div class="rounded-xl border border-base-300 bg-base-100 p-3 shadow-sm">
          <p class="text-[11px] uppercase text-base-content/50">Kembalian</p>
          <p class="mt-1 font-semibold text-success">{{ format(detail.change_amount) }}</p>
        </div>
      </div>

      <div class="grid gap-5 xl:grid-cols-[1fr_340px]">
        <div class="space-y-5">
          <div class="ocn-panel">
            <div class="ocn-panel__head">
              <h2 class="ocn-panel__title">Item transaksi</h2>
            </div>
            <div class="card-body p-0">
              <div class="overflow-x-auto">
                <table class="table table-zebra">
                  <thead>
                    <tr>
                      <th>SKU</th>
                      <th>Produk</th>
                      <th class="text-right">Qty</th>
                      <th>UoM</th>
                      <th class="text-right">Harga</th>
                      <th class="text-right">Disc %</th>
                      <th class="text-right">Subtotal</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="item in detail.items" :key="item.id">
                      <td class="font-mono text-xs">{{ item.sku }}</td>
                      <td>{{ item.product_name }}</td>
                      <td class="text-right">{{ item.qty }}</td>
                      <td>{{ item.uom }}</td>
                      <td class="text-right">{{ format(item.unit_price) }}</td>
                      <td class="text-right">{{ item.discount_percent }}</td>
                      <td class="text-right font-semibold">{{ format(item.line_total) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <div class="ocn-panel">
            <div class="ocn-panel__head">
              <h2 class="ocn-panel__title">Biaya lainnya</h2>
            </div>
            <div class="card-body p-0">
              <div class="overflow-x-auto">
                <table class="table table-zebra">
                  <thead>
                    <tr>
                      <th>Nama</th>
                      <th>Jenis</th>
                      <th class="text-right">Nominal</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="charge in detail.additional_charges" :key="charge.id">
                      <td>{{ charge.charge_name }}</td>
                      <td><span class="badge badge-ghost badge-sm">{{ charge.kind === 'journal_admin' ? 'Jurnal (admin channel)' : 'Ditagih ke total' }}</span></td>
                      <td class="text-right font-semibold">{{ format(charge.amount) }}</td>
                    </tr>
                    <tr v-if="!detail.additional_charges?.length">
                      <td colspan="3" class="py-8 text-center text-base-content/50">Tidak ada biaya lainnya.</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <div class="card border border-primary/20 bg-primary/5 shadow xl:sticky xl:top-24 xl:self-start">
          <div class="card-body">
            <h2 class="card-title text-lg">Quick Actions</h2>
            <p class="text-sm text-base-content/70">Utilitas transaksi untuk operasional kasir.</p>

            <button class="btn btn-primary btn-sm" type="button" :disabled="printingReceipt" @click="printReceipt">{{ printingReceipt ? 'Mencetak...' : 'Cetak Ulang Receipt' }}</button>
            <p v-if="printReceiptSuccess" class="text-xs text-success">{{ printReceiptSuccess }}</p>
            <p v-if="printReceiptError" class="text-xs text-error">{{ printReceiptError }}</p>

            <div class="rounded-lg border border-base-300 bg-base-100 p-3">
              <p class="text-xs uppercase tracking-wide text-base-content/60">Metode Pembayaran Saat Ini</p>
              <p class="mt-1 font-semibold">{{ detail.payment_method_name || '-' }}</p>
              <button class="btn btn-outline btn-sm mt-2 w-full" :disabled="paymentForm.processing" @click="openConfirmModal('modal-change-payment-method')">
                Rubah Metode Pembayaran
              </button>
            </div>

            <div v-if="detail.requires_high_authorization" class="rounded-lg border border-warning/40 bg-warning/10 p-3">
              <p class="text-xs font-semibold uppercase tracking-wide text-warning-content">Otorisasi Admin Diperlukan</p>
              <div class="mt-2 space-y-2">
                <input v-model="authForm.authorization_email" type="email" class="input input-bordered input-sm w-full" placeholder="Email admin" />
                <input v-model="authForm.authorization_password" type="password" class="input input-bordered input-sm w-full" placeholder="Password admin" />
              </div>
              <p class="mt-2 text-xs text-base-content/70">Wajib diisi untuk user non-admin sebelum aksi utilitas.</p>
            </div>

            <button class="btn btn-outline btn-warning btn-sm" :disabled="!canRefund || refundForm.processing" @click="openConfirmModal('modal-confirm-refund')">
              Cancel / Refund Transaksi
            </button>
            <button class="btn btn-outline btn-success btn-sm" :disabled="!canReopen || reopenForm.processing" @click="openConfirmModal('modal-confirm-reopen')">
              Reopen Transaksi
            </button>
            <p v-if="paymentForm.errors.authorization || refundForm.errors.authorization || reopenForm.errors.authorization" class="text-xs text-error">
              {{ paymentForm.errors.authorization || refundForm.errors.authorization || reopenForm.errors.authorization }}
            </p>
          </div>
        </div>
      </div>

      <dialog id="modal-confirm-payment-method" class="modal">
        <div class="modal-box">
          <h3 class="font-bold text-lg">Konfirmasi Perubahan Metode Bayar</h3>
          <p class="py-3 text-sm text-base-content/70">Yakin ingin mengubah metode pembayaran transaksi ini?</p>
          <div class="modal-action">
            <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
            <button class="btn btn-primary" @click="submitPaymentMethodUpdate">Ya, Ubah</button>
          </div>
        </div>
      </dialog>

      <dialog id="modal-change-payment-method" class="modal">
        <div class="modal-box max-w-md">
          <h3 class="font-bold text-lg">Rubah Metode Pembayaran</h3>
          <div class="mt-3 space-y-3">
            <select v-model="paymentForm.payment_method_id" class="select select-bordered w-full">
              <option v-for="method in payment_methods" :key="method.id" :value="method.id">{{ method.name }}</option>
            </select>
            <p v-if="paymentForm.errors.payment_method_id" class="text-xs text-error">{{ paymentForm.errors.payment_method_id }}</p>
          </div>
          <div class="modal-action">
            <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
            <button class="btn btn-primary" :disabled="paymentForm.processing" @click="openConfirmModal('modal-confirm-payment-method')">Lanjut Konfirmasi</button>
          </div>
        </div>
      </dialog>

      <dialog id="modal-confirm-refund" class="modal">
        <div class="modal-box">
          <h3 class="font-bold text-lg">Konfirmasi Refund</h3>
          <p class="py-3 text-sm text-base-content/70">Transaksi akan dibatalkan dan stok dikembalikan. Lanjutkan?</p>
          <div class="modal-action">
            <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
            <button class="btn btn-warning" @click="refundTransaction">Ya, Refund</button>
          </div>
        </div>
      </dialog>

      <dialog id="modal-confirm-reopen" class="modal">
        <div class="modal-box">
          <h3 class="font-bold text-lg">Konfirmasi Reopen</h3>
          <p class="py-3 text-sm text-base-content/70">Transaksi akan diaktifkan lagi dan stok dipotong ulang. Lanjutkan?</p>
          <div class="modal-action">
            <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
            <button class="btn btn-success" @click="reopenTransaction">Ya, Reopen</button>
          </div>
        </div>
      </dialog>
    </div>
  </AppLayout>
</template>
