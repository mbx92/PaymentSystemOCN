<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import ProductPickerModal from '@/Components/ProductPickerModal.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { computed, reactive, watch } from 'vue';
import { showGlobalAlert } from '@/utils/globalAlert';

const props = defineProps({
  detail: Object,
  suppliers: Array,
  products: Array,
});

const formatIdr = (n) =>
  new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n ?? 0);

const advanceForm = useForm({ action: 'submit' });
const canEdit = computed(() => ['draft', 'submitted'].includes(props.detail.status));
const poLineDraftKey = `erp.purchase-order.${props.detail.number}.draft-lines`;
const databaseLines = () => props.detail.lines.map((line) => ({
  product_id: line.product_id,
  product_search: `${line.sku || ''} - ${line.name || ''}`.trim(),
  qty: Number(line.qty || 0),
  unit_price: Number(line.unit_price || 0),
}));
const readDraftLines = () => {
  if (typeof window === 'undefined') return null;

  try {
    const parsed = JSON.parse(window.localStorage.getItem(poLineDraftKey) || 'null');
    return Array.isArray(parsed) ? parsed : null;
  } catch {
    return null;
  }
};
const persistDraftLines = () => {
  if (typeof window === 'undefined' || !canEdit.value) return;

  window.localStorage.setItem(poLineDraftKey, JSON.stringify(editForm.lines));
};
const clearDraftLines = () => {
  if (typeof window === 'undefined') return;

  window.localStorage.removeItem(poLineDraftKey);
};

const submitPo = () => {
  if (hasUnsavedEdit.value) {
    showGlobalAlert('Simpan perubahan item PO terlebih dahulu sebelum mengajukan PO.', 'warning');
    return;
  }

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

const editForm = useForm({
  vendor_code: props.detail.supplier_code,
  order_date: props.detail.created_at,
  eta_date: props.detail.eta,
  notes: props.detail.notes ?? '',
  lines: readDraftLines() ?? databaseLines(),
});

const normalizedEditPayload = (payload) => ({
  vendor_code: String(payload.vendor_code ?? ''),
  order_date: String(payload.order_date ?? ''),
  eta_date: String(payload.eta_date ?? ''),
  notes: String(payload.notes ?? ''),
  lines: [...(payload.lines ?? [])]
    .map((line) => ({
      product_id: String(line.product_id ?? ''),
      qty: Number(line.qty || 0),
      unit_price: Number(line.unit_price || 0),
    }))
    .sort((a, b) => a.product_id.localeCompare(b.product_id)),
});
const savedEditPayload = computed(() => normalizedEditPayload({
  vendor_code: props.detail.supplier_code,
  order_date: props.detail.created_at,
  eta_date: props.detail.eta,
  notes: props.detail.notes ?? '',
  lines: databaseLines(),
}));
const currentEditPayload = computed(() => normalizedEditPayload(editForm));
const hasUnsavedEdit = computed(() => JSON.stringify(currentEditPayload.value) !== JSON.stringify(savedEditPayload.value));

const lineSubtotal = (line) => Number(line.qty || 0) * Number(line.unit_price || 0);
const editGrandTotal = computed(() => editForm.lines.reduce((sum, line) => sum + lineSubtotal(line), 0));
const productOptions = computed(() => props.products ?? []);
const productById = (id) => productOptions.value.find((product) => String(product.id) === String(id));
const productLabel = (id) => {
  const product = productById(id);
  return product ? `${product.sku} - ${product.name}` : '';
};
const productUom = (id) => productById(id)?.uom || '-';
const editProductPicker = reactive({ lineIndex: null, show: false });
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
  persistDraftLines();
};

const removeLine = (idx) => {
  editForm.lines.splice(idx, 1);
  persistDraftLines();
};

const selectProductForEditLine = (idx, product) => {
  const line = editForm.lines[idx];
  if (!line || !product) return;
  line.product_id = product.id;
  line.product_search = `${product.sku} - ${product.name}`;
  line.unit_price = Number(product.selling_price || 0);
  persistDraftLines();
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
  const hasInvalidLine = editForm.lines.length === 0 || editForm.lines.some(
    (line) =>
      !String(line.product_id || '').trim() ||
      Number(line.qty || 0) <= 0 ||
      Number(line.unit_price || 0) <= 0,
  );

  if (hasInvalidLine) {
    showGlobalAlert('Tambahkan minimal satu item valid sebelum menyimpan PO.', 'warning');
    return;
  }

  editForm.put(route('erp.purchasing.purchase-orders.update', props.detail.number), {
    preserveScroll: true,
    onSuccess: () => {
      clearDraftLines();
    },
  });
};

watch(
  () => editForm.lines,
  () => persistDraftLines(),
  { deep: true },
);
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

      <div class="grid gap-4 lg:grid-cols-4 lg:items-start">
        <div class="ocn-panel lg:col-span-3">
          <div class="ocn-panel__head">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
              <div>
                <h2 class="ocn-panel__title">Baris item purchase order</h2>
                <p v-if="canEdit" class="ocn-panel__desc mt-1">
                  Item disimpan otomatis sebagai draft browser. Klik simpan untuk menulis ke database.
                </p>
              </div>
            </div>
          </div>
          <div class="card-body p-0">
            <div v-if="canEdit" class="border-b border-base-200 p-4">
              <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
                <div class="md:col-span-2">
                  <label class="label py-1"><span class="label-text">Supplier</span></label>
                  <select v-model="editForm.vendor_code" class="select select-bordered select-sm w-full">
                    <option value="">Pilih supplier</option>
                    <option v-for="supplier in suppliers" :key="supplier.code" :value="supplier.code">
                      {{ supplier.code }} - {{ supplier.name }}
                    </option>
                  </select>
                </div>
                <div>
                  <label class="label py-1"><span class="label-text">Tanggal PO</span></label>
                  <input v-model="editForm.order_date" type="date" class="input input-bordered input-sm w-full" />
                </div>
                <div>
                  <label class="label py-1"><span class="label-text">ETA</span></label>
                  <input v-model="editForm.eta_date" type="date" class="input input-bordered input-sm w-full" />
                </div>
                <div class="md:col-span-4">
                  <label class="label py-1"><span class="label-text">Catatan</span></label>
                  <input v-model="editForm.notes" class="input input-bordered input-sm w-full" placeholder="Opsional" />
                </div>
              </div>
            </div>

            <div v-if="canEdit" class="overflow-x-auto">
              <table class="table table-zebra table-sm">
                <thead>
                  <tr>
                    <th class="min-w-72">Produk</th>
                    <th>UoM</th>
                    <th class="text-right">Qty</th>
                    <th class="text-right">Harga</th>
                    <th class="text-right">Subtotal</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(line, idx) in editForm.lines" :key="idx">
                    <td>
                      <input
                        :value="line.product_search || productLabel(line.product_id)"
                        type="text"
                        class="input input-bordered input-sm w-full min-w-72 cursor-pointer"
                        placeholder="Klik untuk pilih produk"
                        readonly
                        @click="openEditProductPicker(idx)"
                      />
                      <p v-if="line.product_id && !productLabel(line.product_id)" class="mt-1 text-xs text-warning">
                        Produk lama tidak ada di katalog aktif.
                      </p>
                    </td>
                    <td class="text-xs font-semibold uppercase text-base-content/70">
                      {{ productUom(line.product_id) }}
                    </td>
                    <td class="text-right">
                      <input v-model.number="line.qty" type="number" min="0.01" step="0.01" class="input input-bordered input-sm w-24 text-right" />
                    </td>
                    <td class="text-right">
                      <input v-model.number="line.unit_price" type="number" min="0.01" step="0.01" class="input input-bordered input-sm w-32 text-right" />
                    </td>
                    <td class="text-right font-medium">{{ formatIdr(lineSubtotal(line)) }}</td>
                    <td class="text-right">
                      <button class="btn btn-ghost btn-xs text-error" type="button" @click="removeLine(idx)">Hapus</button>
                    </td>
                  </tr>
                  <tr v-if="!editForm.lines.length">
                    <td colspan="6" class="py-8 text-center text-base-content/50">
                      Belum ada item. Tambahkan baris untuk menyusun item PO.
                    </td>
                  </tr>
                </tbody>
              </table>
              <div class="flex flex-col gap-3 border-t border-base-200 px-4 py-3 text-sm lg:flex-row lg:items-center lg:justify-between">
                <div class="flex flex-wrap gap-2">
                  <button class="btn btn-outline btn-sm" type="button" @click="addLine">+ Tambah Baris</button>
                  <button class="btn btn-primary btn-sm" :disabled="editForm.processing" @click="submitEdit">
                    Simpan Item
                  </button>
                </div>
                <span class="font-semibold lg:ml-auto">Grand Total: {{ formatIdr(editGrandTotal) }}</span>
              </div>
            </div>

            <div v-else class="overflow-x-auto">
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
                  <tr v-if="!detail.lines.length">
                    <td colspan="9" class="py-8 text-center text-base-content/50">
                      Belum ada item. Gunakan tombol input item untuk menyusun draft item PO.
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="card border border-primary/20 bg-primary/5 shadow-sm">
          <div class="card-body p-4">
            <h2 class="card-title text-base">Lanjutkan proses</h2>
            <p class="text-xs text-base-content/70">Sesuai status dokumen PO.</p>
            <div class="card-actions mt-3 flex-col items-stretch gap-2">
              <template v-if="detail.status === 'draft'">
                <button
                  type="button"
                  class="btn btn-primary btn-sm"
                  :disabled="advanceForm.processing || hasUnsavedEdit"
                  @click="submitPo"
                >
                  Ajukan PO
                </button>
                <p v-if="hasUnsavedEdit" class="text-xs text-warning">
                  Simpan perubahan item PO sebelum mengajukan.
                </p>
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

      <ProductPickerModal
        :show="editProductPicker.show"
        :products="products"
        title="Pilih Produk untuk Item PO"
        subtitle="Cari produk lalu pilih untuk mengisi baris item."
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
