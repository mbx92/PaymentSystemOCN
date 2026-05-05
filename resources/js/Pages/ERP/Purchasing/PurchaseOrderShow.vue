<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps({
  detail: Object,
});

const formatIdr = (n) =>
  new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n ?? 0);

const advanceForm = useForm({ action: 'submit' });

const submitForApproval = () => {
  advanceForm.action = 'submit';
  advanceForm.post(route('erp.purchasing.purchase-orders.advance', props.detail.number), { preserveScroll: true });
};

const voidPo = () => {
  advanceForm.action = 'void';
  advanceForm.post(route('erp.purchasing.purchase-orders.advance', props.detail.number), { preserveScroll: true });
};

const grnListUrl = () =>
  `${route('erp.purchasing.goods-receipts')}?po=${encodeURIComponent(props.detail.number)}`;

const goBack = () => {
  router.visit(route('erp.purchasing.purchase-orders'));
};
</script>

<template>
  <Head :title="`PO — ${detail.number}`" />
  <AppLayout>
    <div class="space-y-5">
      <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Purchasing · Purchase Order</p>
        <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
          <div>
            <h1 class="text-3xl font-bold tracking-tight font-mono">{{ detail.number }}</h1>
            <p class="mt-1 text-sm text-base-content/70">
              {{ detail.supplier_name }}
              <Link
                class="link link-primary ml-2 text-xs font-semibold"
                :href="route('erp.purchasing.suppliers.show', detail.supplier_code)"
              >
                Profil supplier
              </Link>
            </p>
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <StatusBadge :status="detail.status" />
            <button type="button" class="btn btn-ghost btn-sm" @click="goBack">Back</button>
          </div>
        </div>
        <p class="mt-3 text-sm text-base-content/70">
          Dibuat {{ detail.created_at }} · ETA {{ detail.eta }} · Total {{ formatIdr(detail.amount) }}
        </p>
      </div>

      <div class="grid gap-5 lg:grid-cols-3">
        <div class="card bg-base-100 shadow lg:col-span-2">
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
                    <th class="text-right">Subtotal</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(line, idx) in detail.lines" :key="idx">
                    <td class="font-mono text-xs">{{ line.sku }}</td>
                    <td>{{ line.name }}</td>
                    <td class="text-right">{{ line.qty }}</td>
                    <td>{{ line.uom }}</td>
                    <td class="text-right">{{ formatIdr(line.unit_price) }}</td>
                    <td class="text-right font-medium">{{ formatIdr(line.subtotal) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="card border border-primary/20 bg-primary/5 shadow">
          <div class="card-body">
            <h2 class="card-title text-lg">Lanjutkan proses</h2>
            <p class="text-sm text-base-content/70">Sesuai status dokumen PO.</p>
            <div class="card-actions mt-4 flex-col items-stretch gap-2">
              <template v-if="detail.status === 'draft'">
                <button
                  type="button"
                  class="btn btn-primary btn-sm"
                  :disabled="advanceForm.processing"
                  @click="submitForApproval"
                >
                  Ajukan & setujui PO (simulasi)
                </button>
                <button
                  type="button"
                  class="btn btn-outline btn-error btn-sm"
                  :disabled="advanceForm.processing"
                  @click="voidPo"
                >
                  Batalkan PO
                </button>
              </template>
              <template v-else-if="detail.status === 'approved'">
                <Link class="btn btn-primary btn-sm" :href="grnListUrl()">Input / lihat penerimaan barang</Link>
                <button
                  type="button"
                  class="btn btn-outline btn-error btn-sm"
                  :disabled="advanceForm.processing"
                  @click="voidPo"
                >
                  Batalkan PO
                </button>
              </template>
              <template v-else-if="detail.status === 'void'">
                <p class="text-sm text-base-content/60">PO void — tidak ada langkah lanjutan.</p>
              </template>
              <template v-else>
                <Link class="btn btn-ghost btn-sm" :href="grnListUrl()">Riwayat penerimaan terkait</Link>
              </template>
              <Link class="btn btn-ghost btn-sm" :href="route('erp.inventory.stock-management')">Manajemen stok</Link>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
