<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { useDateFormat } from '@/composables/useDateFormat';
import { computed } from 'vue';

const props = defineProps({
  detail: Object,
  stock_check: Object,
  warehouses: Array,
});

const { formatDate } = useDateFormat();

const stockCheckSummary = computed(() => props.stock_check?.summary ?? {
  line_count: 0,
  warning_count: 0,
  total_gr_qty: 0,
  total_gr_net: 0,
  total_warehouse_qty: 0,
});

const stockCheckLines = computed(() => props.stock_check?.lines ?? []);
const stockCheckWarnings = computed(() => props.stock_check?.warnings ?? []);

const formatQty = (value) => Number(value ?? 0).toLocaleString('id-ID', {
  minimumFractionDigits: 0,
  maximumFractionDigits: 2,
});

const advanceForm = useForm({ action: 'post_stock', warehouse_id: props.detail?.warehouse_id ?? '' });

const openModal = (id) => {
  globalThis.document?.getElementById(id)?.showModal?.();
};

const closeModal = (id) => {
  globalThis.document?.getElementById(id)?.close?.();
};

const postToStock = () => {
  advanceForm.action = 'post_stock';
  advanceForm.post(route('erp.purchasing.goods-receipts.advance', props.detail.number), { preserveScroll: true });
};

const reopenReceipt = () => {
  advanceForm.action = 'reopen';
  advanceForm.post(route('erp.purchasing.goods-receipts.advance', props.detail.number), { preserveScroll: true });
  closeModal('modal-confirm-reopen-gr');
};

const goBack = () => {
  router.visit(route('erp.purchasing'));
};
</script>

<template>
  <Head :title="`GRN — ${detail.number}`" />
  <AppLayout>
    <div class="space-y-5">
      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Purchasing · Penerimaan barang</p>
              <h1 class="ocn-panel__title mt-1">{{ detail.number }}</h1>
              <p class="ocn-panel__desc mt-1">
              PO
              <Link
                class="link link-primary font-mono font-semibold"
                :href="route('erp.purchasing.purchase-orders.show', detail.po_number)"
              >
                {{ detail.po_number }}
              </Link>
              · {{ detail.warehouse }}
            </p>
              <p class="ocn-panel__desc mt-1">Tanggal terima {{ formatDate(detail.received_date) }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
              <StatusBadge :status="detail.status" />
              <button type="button" class="btn btn-ghost btn-sm shrink-0 gap-1.5" @click="goBack">
                <ArrowLeftIcon class="h-4 w-4" />
                Back
              </button>
            </div>
          </div>
        </div>
      </div>

      <div class="grid gap-5 lg:grid-cols-3">
        <div class="ocn-panel lg:col-span-2">
          <div class="ocn-panel__head">
            <h2 class="ocn-panel__title">Baris penerimaan barang</h2>
          </div>
          <div class="card-body p-0">
            <div class="overflow-x-auto">
              <table class="table table-zebra">
                <thead>
                  <tr>
                    <th>SKU</th>
                    <th>Produk</th>
                    <th class="text-right">Diterima</th>
                    <th>UoM</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(line, idx) in detail.lines" :key="idx">
                    <td class="font-mono text-xs">{{ line.sku }}</td>
                    <td>{{ line.name }}</td>
                    <td class="text-right">{{ line.qty_received }}</td>
                    <td>{{ line.uom }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="lg:sticky lg:top-20 lg:z-10 lg:self-start">
          <div class="card border border-primary/20 bg-primary/5 shadow">
            <div class="card-body">
              <h2 class="card-title text-lg">Lanjutkan proses</h2>
              <p class="text-sm text-base-content/70">Setelah posting, stok dapat diperbarui (integrasi penuh menyusul).</p>
              <p v-if="advanceForm.errors.action" class="mt-2 text-sm text-error">{{ advanceForm.errors.action }}</p>
              <div class="card-actions mt-4 flex-col items-stretch gap-2">
                <template v-if="detail.status === 'approved'">
                  <div>
                    <label class="label"><span class="label-text text-xs uppercase tracking-wide">Posting ke Warehouse</span></label>
                    <select v-model="advanceForm.warehouse_id" class="select select-bordered select-sm w-full">
                      <option value="">Pilih warehouse</option>
                      <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.code }} - {{ w.name }}</option>
                    </select>
                  </div>
                  <button
                    type="button"
                    class="btn btn-primary btn-sm"
                    :disabled="advanceForm.processing"
                    @click="postToStock"
                  >
                    Posting ke stok
                  </button>
                </template>
                <template v-else-if="detail.status === 'posted'">
                  <p class="text-sm text-base-content/60">Sudah diposting. Bisa di-reopen selama stoknya masih tersedia dan hutang suppliernya belum dibayar.</p>
                  <button
                    v-if="detail.can_reopen"
                    type="button"
                    class="btn btn-outline btn-warning btn-sm"
                    :disabled="advanceForm.processing"
                    @click="openModal('modal-confirm-reopen-gr')"
                  >
                    Reopen GR
                  </button>
                </template>
                <Link class="btn btn-outline btn-sm gap-1.5" :href="route('erp.purchasing.purchase-orders.show', detail.po_number)">
                  <ArrowLeftIcon class="h-4 w-4" />
                  Kembali ke PO
                </Link>
                <Link class="btn btn-ghost btn-sm" :href="route('erp.inventory.stock-management')">Manajemen stok</Link>
                <Link class="btn btn-ghost btn-sm" :href="route('erp.inventory.stock-opname')">Stok opname</Link>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head flex flex-wrap items-center justify-between gap-2">
          <div>
            <h2 class="ocn-panel__title">Stock Check</h2>
            <p class="ocn-panel__desc mt-1">Bandingkan dampak GR ini terhadap movement, stok warehouse, stok master, dan received qty PO.</p>
          </div>
          <span class="badge" :class="stockCheckWarnings.length ? 'badge-warning' : 'badge-success'">
            {{ stockCheckWarnings.length ? `${stockCheckWarnings.length} warning` : 'Konsisten' }}
          </span>
        </div>

        <div class="card-body space-y-4">
          <div class="grid gap-3 md:grid-cols-4">
            <div class="rounded-xl border border-base-300 bg-base-100 p-4">
              <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">Baris dicek</p>
              <p class="mt-2 text-lg font-semibold">{{ stockCheckSummary.line_count }}</p>
            </div>
            <div class="rounded-xl border border-base-300 bg-base-100 p-4">
              <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">Total Qty GR</p>
              <p class="mt-2 text-lg font-semibold">{{ formatQty(stockCheckSummary.total_gr_qty) }}</p>
            </div>
            <div class="rounded-xl border border-base-300 bg-base-100 p-4">
              <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">Net Movement GR</p>
              <p class="mt-2 text-lg font-semibold">{{ formatQty(stockCheckSummary.total_gr_net) }}</p>
            </div>
            <div class="rounded-xl border border-base-300 bg-base-100 p-4">
              <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">Qty Warehouse Saat Ini</p>
              <p class="mt-2 text-lg font-semibold">{{ formatQty(stockCheckSummary.total_warehouse_qty) }}</p>
            </div>
          </div>

          <div v-if="stockCheckWarnings.length" class="alert alert-warning">
            <div class="space-y-1">
              <p class="font-semibold">Potensi mismatch terdeteksi</p>
              <p v-for="(warning, idx) in stockCheckWarnings" :key="idx" class="text-sm">
                {{ warning }}
              </p>
            </div>
          </div>
          <div v-else class="alert alert-success">
            <span class="text-sm">Tidak ada mismatch yang terdeteksi untuk GR ini.</span>
          </div>

          <div class="overflow-x-auto rounded-xl border border-base-300">
            <table class="table table-sm">
              <thead>
                <tr>
                  <th>SKU</th>
                  <th>Produk</th>
                  <th class="text-right">GR Qty</th>
                  <th class="text-right">Expected Net</th>
                  <th class="text-right">GR In</th>
                  <th class="text-right">Reopen Out</th>
                  <th class="text-right">GR Net</th>
                  <th class="text-right">WH Qty</th>
                  <th class="text-right">WH Reserved</th>
                  <th class="text-right">All Move Exp</th>
                  <th class="text-right">Master Stock</th>
                  <th class="text-right">All WH Qty</th>
                  <th class="text-right">PO Recv</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(line, idx) in stockCheckLines" :key="`${line.sku}-${idx}`">
                  <td class="font-mono text-xs">{{ line.sku }}</td>
                  <td>{{ line.name }}</td>
                  <td class="text-right tabular-nums">{{ formatQty(line.gr_qty) }}</td>
                  <td class="text-right tabular-nums">{{ formatQty(line.status_expected_net) }}</td>
                  <td class="text-right tabular-nums">{{ formatQty(line.gr_in) }}</td>
                  <td class="text-right tabular-nums">{{ formatQty(line.gr_reopen_out) }}</td>
                  <td class="text-right tabular-nums font-medium">{{ formatQty(line.gr_net) }}</td>
                  <td class="text-right tabular-nums">{{ formatQty(line.warehouse_qty) }}</td>
                  <td class="text-right tabular-nums">{{ formatQty(line.warehouse_reserved) }}</td>
                  <td class="text-right tabular-nums">{{ formatQty(line.all_movement_expected) }}</td>
                  <td class="text-right tabular-nums">{{ formatQty(line.master_stock) }}</td>
                  <td class="text-right tabular-nums">{{ formatQty(line.all_warehouse_qty) }}</td>
                  <td class="text-right tabular-nums">{{ formatQty(line.po_received_qty) }}</td>
                </tr>
                <tr v-if="!stockCheckLines.length">
                  <td colspan="13" class="py-6 text-center text-base-content/50">Belum ada baris untuk dicek.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <dialog id="modal-confirm-reopen-gr" class="modal">
        <div class="modal-box">
          <h3 class="font-bold text-lg">Konfirmasi Reopen GR</h3>
          <p class="py-3 text-sm text-base-content/70">Posting stok, hutang supplier, dan jurnal pembelian akan dibalik. Lanjutkan reopen GR ini?</p>
          <div class="modal-action">
            <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
            <button class="btn btn-warning" :disabled="advanceForm.processing" @click="reopenReceipt">Ya, Reopen</button>
          </div>
        </div>
      </dialog>
    </div>
  </AppLayout>
</template>
