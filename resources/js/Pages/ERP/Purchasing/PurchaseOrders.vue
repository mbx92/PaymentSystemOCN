<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import ProductPickerModal from '@/Components/ProductPickerModal.vue';
import DataTablePagination from '@/Components/DataTablePagination.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import { useCurrency } from '@/composables/useCurrency';
import { showGlobalAlert } from '@/utils/globalAlert';

const props = defineProps({
  purchaseOrders: Object,
  supplierFilter: String,
  filters: Object,
  suppliers: Array,
  products: Array,
});

const formatIdr = (n) =>
  new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n ?? 0);
const { parse, formatInput } = useCurrency();

const openRow = (number) => {
  router.visit(route('erp.purchasing.purchase-orders.show', number));
};

const rowClass = () => 'cursor-pointer transition-colors hover:bg-primary/5';

const filters = reactive({
  supplier: props.filters?.supplier ?? '',
  status: props.filters?.status ?? '',
  q: props.filters?.q ?? '',
  per_page: props.filters?.per_page ?? props.purchaseOrders?.per_page ?? 25,
});

let timer;
watch(
  filters,
  (val) => {
    clearTimeout(timer);
    timer = setTimeout(() => {
      router.get(route('erp.purchasing.purchase-orders'), val, { preserveState: true, replace: true });
    }, 250);
  },
  { deep: true },
);

const addForm = useForm({
  vendor_code: '',
  order_date: new Date().toISOString().slice(0, 10),
  eta_date: '',
  notes: '',
  lines: [{ product_id: '', product_search: '', qty: 1, unit_price: 0 }],
});

const productPicker = reactive({ lineIndex: null, show: false });

const attemptedSubmit = ref(false);

const mandatoryInputClass = (isInvalid) => ({
  'input-error border-error hover:border-error focus:border-error focus:outline-error': isInvalid,
});

const mandatorySelectClass = (isInvalid) => ({
  'select-error border-error hover:border-error focus:border-error focus:outline-error': isInvalid,
});

const openAddModal = () => {
  document.getElementById('modal-add-po')?.showModal();
};

const openProductPicker = (index) => {
  productPicker.lineIndex = index;
  productPicker.show = true;
};

const mergeDuplicateLine = (index) => {
  const line = addForm.lines[index];
  if (!line?.product_id) return;
  const duplicateIdx = addForm.lines.findIndex((candidate, idx) => idx !== index && String(candidate.product_id) === String(line.product_id));
  if (duplicateIdx >= 0) {
    addForm.lines[duplicateIdx].qty = Number(addForm.lines[duplicateIdx].qty || 0) + Number(line.qty || 0);
    if (!Number(addForm.lines[duplicateIdx].unit_price || 0)) {
      addForm.lines[duplicateIdx].unit_price = Number(line.unit_price || 0);
    }
    addForm.lines.splice(index, 1);
  }
};

const selectProductForLine = (index, product) => {
  const line = addForm.lines[index];
  if (!line) return;
  line.product_id = product.id;
  line.product_search = `${product.sku} - ${product.name}`;
  line.unit_price = Number(product.selling_price ?? 0);
  mergeDuplicateLine(index);
};

const chooseProduct = (product) => {
  if (productPicker.lineIndex === null) return;
  selectProductForLine(productPicker.lineIndex, product);
  productPicker.lineIndex = null;
  productPicker.show = false;
};

const onUnitPriceInput = (event, line) => {
  line.unit_price = parse(event.target.value);
  event.target.value = formatInput(event.target.value);
};

const addLine = () => {
  addForm.lines.push({ product_id: '', product_search: '', qty: 1, unit_price: 0 });
};

const removeLine = (index) => {
  if (addForm.lines.length <= 1) return;
  addForm.lines.splice(index, 1);
};

const lineSubtotal = (line) => Number(line.qty || 0) * Number(line.unit_price || 0);
const poGrandTotal = computed(() => addForm.lines.reduce((sum, line) => sum + lineSubtotal(line), 0));

const submitAdd = () => {
  attemptedSubmit.value = true;

  if (!String(addForm.vendor_code || '').trim()) {
    showGlobalAlert('Supplier wajib dipilih sebelum menyimpan Purchase Order.', 'warning');
    return;
  }
  if (!String(addForm.order_date || '').trim()) {
    showGlobalAlert('Tanggal PO wajib diisi.', 'warning');
    return;
  }
  const hasInvalidLine = addForm.lines.some(
    (line) =>
      !String(line.product_id || '').trim() ||
      Number(line.qty || 0) < 1 ||
      Number(line.unit_price || 0) <= 0,
  );
  if (hasInvalidLine) {
    showGlobalAlert('Lengkapi data wajib pada item PO (produk, qty, harga).', 'warning');
    return;
  }

  addForm.post(route('erp.purchasing.purchase-orders.store'), {
    preserveScroll: true,
    onSuccess: () => {
      attemptedSubmit.value = false;
      addForm.reset();
      addForm.order_date = new Date().toISOString().slice(0, 10);
      addForm.lines = [{ product_id: '', product_search: '', qty: 1, unit_price: 0 }];
      productPicker.lineIndex = null;
      productPicker.show = false;
      document.getElementById('modal-add-po')?.close();
    },
  });
};
</script>

<template>
  <Head title="Purchasing - Purchase Order" />
  <AppLayout>
    <div class="space-y-5">
      <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Purchasing Workspace</p>
        <div class="mt-2 flex items-center justify-between gap-3">
          <h1 class="text-3xl font-bold tracking-tight">Purchase Order</h1>
          <Link class="btn btn-ghost btn-sm" :href="route('erp.purchasing')">Back</Link>
        </div>
      </div>

      <div v-if="supplierFilter" class="alert alert-info text-sm">
        <span>Menyaring PO untuk supplier <span class="font-mono font-semibold">{{ supplierFilter }}</span>.</span>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Filter purchase order</h2>
        </div>
        <div class="card-body">
          <div class="flex flex-wrap items-end gap-3">
            <div class="min-w-[180px]">
              <label class="label"><span class="label-text text-xs uppercase tracking-wide">Supplier</span></label>
              <select v-model="filters.supplier" class="select select-sm select-bordered w-full">
                <option value="">Semua</option>
                <option v-for="supplier in suppliers" :key="supplier.code" :value="supplier.code">{{ supplier.code }} - {{ supplier.name }}</option>
              </select>
            </div>
            <div class="min-w-[150px]">
              <label class="label"><span class="label-text text-xs uppercase tracking-wide">Status</span></label>
              <select v-model="filters.status" class="select select-sm select-bordered w-full">
                <option value="">Semua</option>
                <option value="draft">Draft</option>
                <option value="approved">Approved</option>
                <option value="posted">Posted</option>
                <option value="void">Void</option>
              </select>
            </div>
            <div class="min-w-[220px] flex-1">
              <label class="label"><span class="label-text text-xs uppercase tracking-wide">Search</span></label>
              <input v-model="filters.q" type="text" class="input input-sm input-bordered w-full" placeholder="Nomor PO / nama supplier" />
            </div>
            <button class="btn btn-primary btn-sm ml-auto" @click="openAddModal">+ Add PO</button>
          </div>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Daftar purchase order</h2>
        </div>
        <div class="overflow-x-auto">
          <table class="table table-zebra">
            <thead><tr><th>Nomor PO</th><th>Supplier</th><th>ETA</th><th>Nilai</th><th>Status</th></tr></thead>
            <tbody>
              <tr v-for="po in (purchaseOrders?.data || [])" :key="po.number" :class="rowClass()" tabindex="0" role="button" @click="openRow(po.number)" @keydown.enter.prevent="openRow(po.number)">
                <td class="font-mono text-xs font-semibold">{{ po.number }}</td>
                <td>{{ po.supplier }}</td>
                <td>{{ po.eta }}</td>
                <td>{{ formatIdr(po.amount) }}</td>
                <td @click.stop><StatusBadge :status="po.status" /></td>
              </tr>
              <tr v-if="!(purchaseOrders?.data || []).length">
                <td colspan="5" class="py-8 text-center text-base-content/50">Tidak ada purchase order.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <DataTablePagination :paginator="purchaseOrders" @update:per-page="(n) => { filters.per_page = n; }" />
      </div>

      <dialog id="modal-add-po" class="modal">
        <div class="modal-box max-w-2xl">
          <h3 class="font-bold text-lg">Tambah Purchase Order</h3>
          <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
            <div class="md:col-span-2">
              <label class="label"><span class="label-text">Supplier</span></label>
              <select
                v-model="addForm.vendor_code"
                class="select select-bordered w-full"
                :class="mandatorySelectClass(attemptedSubmit && !String(addForm.vendor_code || '').trim())"
              >
                <option value="">Pilih supplier</option>
                <option v-for="supplier in suppliers" :key="supplier.code" :value="supplier.code">{{ supplier.code }} - {{ supplier.name }}</option>
              </select>
            </div>
            <div>
              <label class="label"><span class="label-text">Tanggal PO</span></label>
              <input
                v-model="addForm.order_date"
                type="date"
                class="input input-bordered w-full"
                :class="mandatoryInputClass(attemptedSubmit && !String(addForm.order_date || '').trim())"
              />
            </div>
            <div>
              <label class="label"><span class="label-text">ETA</span></label>
              <input v-model="addForm.eta_date" type="date" class="input input-bordered w-full" />
            </div>
            <div class="md:col-span-2">
              <label class="label"><span class="label-text">Catatan</span></label>
              <input v-model="addForm.notes" class="input input-bordered w-full" placeholder="Opsional" />
            </div>
          </div>

          <div class="mt-4 rounded-xl border border-base-200">
            <div class="flex items-center justify-between border-b border-base-200 px-4 py-3">
              <p class="font-semibold">Item Produk</p>
              <button class="btn btn-outline btn-xs" type="button" @click="addLine">+ Tambah Baris</button>
            </div>
            <div class="overflow-x-auto">
              <table class="table table-sm">
                <thead><tr><th class="w-[40%]">Produk</th><th>UoM</th><th>Qty</th><th>Harga</th><th>Subtotal</th><th></th></tr></thead>
                <tbody>
                  <tr v-for="(line, idx) in addForm.lines" :key="idx">
                    <td>
                      <input
                        v-model="line.product_search"
                        type="text"
                        class="input input-bordered input-sm w-full"
                        :class="mandatoryInputClass(attemptedSubmit && !String(line.product_id || '').trim())"
                        placeholder="Klik untuk pilih produk"
                        readonly
                        @click="openProductPicker(idx)"
                      />
                    </td>
                    <td class="text-xs font-semibold uppercase text-base-content/70">
                      {{ props.products.find((p) => String(p.id) === String(line.product_id))?.uom || '-' }}
                    </td>
                    <td>
                      <input
                        v-model.number="line.qty"
                        type="number"
                        min="1"
                        step="1"
                        class="input input-bordered input-sm w-24"
                        :class="mandatoryInputClass(attemptedSubmit && Number(line.qty || 0) < 1)"
                      />
                    </td>
                    <td>
                      <input
                        :value="formatInput(line.unit_price)"
                        type="text"
                        inputmode="numeric"
                        class="input input-bordered input-sm w-36"
                        :class="mandatoryInputClass(attemptedSubmit && Number(line.unit_price || 0) <= 0)"
                        @input="onUnitPriceInput($event, line)"
                      />
                    </td>
                    <td class="font-medium">{{ formatIdr(lineSubtotal(line)) }}</td>
                    <td><button class="btn btn-ghost btn-xs text-error" type="button" @click="removeLine(idx)">Hapus</button></td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div class="flex justify-end border-t border-base-200 px-4 py-3 text-sm">
              <span class="font-semibold">Grand Total: {{ formatIdr(poGrandTotal) }}</span>
            </div>
          </div>

          <div class="modal-action">
            <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
            <button class="btn btn-primary" :disabled="addForm.processing" @click="submitAdd">Simpan</button>
          </div>
        </div>
      </dialog>

      <ProductPickerModal
        :show="productPicker.show"
        :products="products"
        title="Pilih Produk untuk PO"
        subtitle="Gunakan katalog produk global agar PO konsisten dengan modul inventory dan POS."
        search-label="Cari SKU / Barcode / Nama Produk"
        search-placeholder="Contoh: PKG-SP-12X20"
        confirm-text="Pilih Produk"
        radio-name="selected_product_po_add"
        @close="productPicker.show = false"
        @confirm="chooseProduct"
      />
    </div>
  </AppLayout>
</template>

