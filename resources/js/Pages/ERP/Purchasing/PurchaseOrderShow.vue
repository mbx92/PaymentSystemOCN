<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import ProductPickerModal from '@/Components/ProductPickerModal.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { computed, reactive } from 'vue';

const props = defineProps({
  detail: Object,
  suppliers: Array,
  products: Array,
});

const formatIdr = (n) =>
  new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n ?? 0);

const advanceForm = useForm({ action: 'submit' });

const submitPo = () => {
  advanceForm.action = 'submit';
  advanceForm.post(route('erp.purchasing.purchase-orders.advance', props.detail.number), { preserveScroll: true });
};

const approvePo = () => {
  advanceForm.action = 'approve';
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

const openEditModal = () => {
  const dialog = document.getElementById('modal-edit-po');
  if (dialog && typeof dialog.showModal === 'function') dialog.showModal();
};

const editForm = useForm({
  vendor_code: props.detail.supplier_code,
  order_date: props.detail.created_at,
  eta_date: props.detail.eta,
  notes: '',
  lines: props.detail.lines.map((line) => ({
    product_id: line.product_id,
    product_search: `${line.sku || ''} - ${line.name || ''}`.trim(),
    qty: Number(line.qty || 0),
    unit_price: Number(line.unit_price || 0),
  })),
});
const editProductPicker = reactive({ lineIndex: null, show: false });

const canEdit = computed(() => ['draft', 'submitted'].includes(props.detail.status));
const lineSubtotal = (line) => Number(line.qty || 0) * Number(line.unit_price || 0);
const editGrandTotal = computed(() => editForm.lines.reduce((sum, line) => sum + lineSubtotal(line), 0));
const receiptStatus = (line) => {
  const ordered = Number(line.qty || 0);
  const received = Number(line.received_qty || 0);
  if (received <= 0) return 'open';
  if (received >= ordered) return 'complete';
  return 'partial';
};
const receiptBadgeClass = (status) => {
  if (status === 'complete') return 'badge-success';
  if (status === 'partial') return 'badge-warning';
  return 'badge-ghost';
};

const addLine = () => {
  editForm.lines.push({ product_id: '', product_search: '', qty: 1, unit_price: 0 });
};

const removeLine = (idx) => {
  if (editForm.lines.length <= 1) return;
  editForm.lines.splice(idx, 1);
};

const selectProductForEditLine = (idx, product) => {
  const line = editForm.lines[idx];
  if (!line || !product) return;
  line.product_id = product.id;
  line.product_search = `${product.sku} - ${product.name}`;
  line.unit_price = Number(product.selling_price || 0);
};

const openEditProductPicker = (idx) => {
  editProductPicker.lineIndex = idx;
  editProductPicker.show = true;
};

const chooseEditProduct = (product) => {
  if (editProductPicker.lineIndex === null) return;
  selectProductForEditLine(editProductPicker.lineIndex, product);
  editProductPicker.lineIndex = null;
  editProductPicker.show = false;
};

const submitEdit = () => {
  editForm.put(route('erp.purchasing.purchase-orders.update', props.detail.number), {
    preserveScroll: true,
    onSuccess: () => {
      document.getElementById('modal-edit-po')?.close();
    },
  });
};
</script>

<template>
  <Head :title="`PO — ${detail.number}`" />
  <AppLayout>
    <div class="space-y-5">
      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Purchasing · Purchase Order</p>
              <h1 class="ocn-panel__title mt-1">{{ detail.number }}</h1>
              <p class="ocn-panel__desc mt-1">
              {{ detail.supplier_name }}
              <Link
                class="link link-primary ml-2 text-xs font-semibold"
                :href="route('erp.purchasing.suppliers.show', detail.supplier_code)"
              >
                Profil supplier
              </Link>
            </p>
              <p class="ocn-panel__desc mt-1">Dibuat {{ detail.created_at }} · ETA {{ detail.eta }} · Total {{ formatIdr(detail.amount) }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
              <div class="flex flex-wrap items-center gap-2">
            <StatusBadge :status="detail.status" />
            <button type="button" class="btn btn-ghost btn-sm shrink-0 gap-1.5" @click="goBack"><ArrowLeftIcon class="h-4 w-4" />
            Back</button>
          </div>
            </div>
          </div>
        </div>
      </div>

      <div class="grid gap-5 lg:grid-cols-3">
        <div class="ocn-panel lg:col-span-2">
          <div class="ocn-panel__head">
            <h2 class="ocn-panel__title">Baris item purchase order</h2>
          </div>
          <div class="card-body p-0">
            <div class="overflow-x-auto">
              <table class="table table-zebra">
                <thead>
                  <tr>
                    <th>SKU</th>
                    <th>Produk</th>
                    <th class="text-right">Qty</th>
                    <th class="text-right">Received</th>
                    <th class="text-right">Sisa</th>
                    <th>Status Receive</th>
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
                    <td class="text-right">{{ line.received_qty }}</td>
                    <td class="text-right font-semibold">{{ line.remaining_qty }}</td>
                    <td>
                      <span class="badge badge-xs uppercase" :class="receiptBadgeClass(receiptStatus(line))">
                        {{ receiptStatus(line) }}
                      </span>
                    </td>
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
              <button v-if="canEdit" type="button" class="btn btn-outline btn-sm" @click="openEditModal">
                Edit Data PO
              </button>
              <template v-if="detail.status === 'draft'">
                <button
                  type="button"
                  class="btn btn-primary btn-sm"
                  :disabled="advanceForm.processing"
                  @click="submitPo"
                >
                  Ajukan PO
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
              <template v-else-if="detail.status === 'submitted'">
                <button
                  type="button"
                  class="btn btn-primary btn-sm"
                  :disabled="advanceForm.processing"
                  @click="approvePo"
                >
                  Setujui PO
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

      <dialog id="modal-edit-po" class="modal">
        <div class="modal-box max-w-3xl">
          <h3 class="text-lg font-bold">Edit Purchase Order</h3>
          <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
            <div class="md:col-span-2">
              <label class="label"><span class="label-text">Supplier</span></label>
              <select v-model="editForm.vendor_code" class="select select-bordered w-full">
                <option value="">Pilih supplier</option>
                <option v-for="supplier in suppliers" :key="supplier.code" :value="supplier.code">
                  {{ supplier.code }} - {{ supplier.name }}
                </option>
              </select>
            </div>
            <div>
              <label class="label"><span class="label-text">Tanggal PO</span></label>
              <input v-model="editForm.order_date" type="date" class="input input-bordered w-full" />
            </div>
            <div>
              <label class="label"><span class="label-text">ETA</span></label>
              <input v-model="editForm.eta_date" type="date" class="input input-bordered w-full" />
            </div>
          </div>

          <div class="mt-4 rounded-xl border border-base-200">
            <div class="flex items-center justify-between border-b border-base-200 px-4 py-3">
              <p class="font-semibold">Item PO</p>
              <button class="btn btn-outline btn-xs" type="button" @click="addLine">+ Tambah Baris</button>
            </div>
            <div class="overflow-x-auto">
              <table class="table table-sm">
                <thead>
                  <tr>
                    <th>Produk</th>
                    <th>UoM</th>
                    <th>Qty</th>
                    <th>Harga</th>
                    <th>Subtotal</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(line, idx) in editForm.lines" :key="idx">
                    <td>
                      <input
                        :value="line.product_search || `${products.find((p) => String(p.id) === String(line.product_id))?.sku || ''} - ${products.find((p) => String(p.id) === String(line.product_id))?.name || ''}`"
                        type="text"
                        class="input input-bordered input-sm w-full"
                        placeholder="Klik untuk pilih produk"
                        readonly
                        @click="openEditProductPicker(idx)"
                      />
                    </td>
                    <td class="text-xs font-semibold uppercase text-base-content/70">
                      {{ products.find((p) => String(p.id) === String(line.product_id))?.uom || '-' }}
                    </td>
                    <td><input v-model.number="line.qty" type="number" min="0.01" step="0.01" class="input input-bordered input-sm w-24" /></td>
                    <td><input v-model.number="line.unit_price" type="number" min="0.01" step="0.01" class="input input-bordered input-sm w-32" /></td>
                    <td class="font-medium">{{ formatIdr(lineSubtotal(line)) }}</td>
                    <td><button class="btn btn-ghost btn-xs text-error" type="button" @click="removeLine(idx)">Hapus</button></td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div class="flex justify-end border-t border-base-200 px-4 py-3 text-sm">
              <span class="font-semibold">Grand Total: {{ formatIdr(editGrandTotal) }}</span>
            </div>
          </div>

          <div class="modal-action">
            <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
            <button class="btn btn-primary" :disabled="editForm.processing" @click="submitEdit">Simpan Perubahan</button>
          </div>
        </div>
      </dialog>

      <ProductPickerModal
        :show="editProductPicker.show"
        :products="products"
        title="Pilih Produk untuk Edit PO"
        subtitle="Pilih dari katalog produk global."
        search-label="Cari SKU / Barcode / Nama Produk"
        search-placeholder="Contoh: PKG-SP-12X20"
        confirm-text="Pilih Produk"
        radio-name="selected_product_po_edit"
        @close="editProductPicker.show = false"
        @confirm="chooseEditProduct"
      />
    </div>
  </AppLayout>
</template>
