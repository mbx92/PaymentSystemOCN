<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useCurrency } from '@/composables/useCurrency';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
  products: Array,
  fullscreen: Boolean,
});

const { format } = useCurrency();
const selectedProductId = ref('');
const barcodeQuery = ref('');
const cart = ref([]);
const cashPaid = ref(0);
const paymentMethod = ref('cash');
const heldCart = ref([]);
const isOnHold = ref(false);
const cashInputRef = ref(null);
const modalBarcodeRef = ref(null);

const openProductModal = () => {
  barcodeQuery.value = '';
  document.getElementById('modal-pos-products').showModal();
  nextTick(() => modalBarcodeRef.value?.focus());
};

const addSelectedProductToCart = () => {
  if (!selectedProductId.value) return;
  const selected = props.products.find((p) => p.sku === selectedProductId.value);
  if (!selected) return;

  const existing = cart.value.find((line) => line.sku === selected.sku);
  if (existing) {
    existing.qty += 1;
  } else {
    cart.value.push({
      sku: selected.sku,
      name: selected.name,
      price: Number(selected.price),
      qty: 1,
      discountPercent: 0,
    });
  }

  selectedProductId.value = '';
  document.getElementById('modal-pos-products').close();
};

const addProductBySku = (sku) => {
  selectedProductId.value = sku;
  addSelectedProductToCart();
};

const removeLine = (sku) => {
  cart.value = cart.value.filter((line) => line.sku !== sku);
};

const lineSubtotal = (line) => {
  const gross = line.price * line.qty;
  const discount = gross * (Number(line.discountPercent || 0) / 100);
  return gross - discount;
};

const grossTotal = computed(() => cart.value.reduce((sum, line) => sum + (line.price * line.qty), 0));
const discountTotal = computed(() => cart.value.reduce((sum, line) => sum + ((line.price * line.qty) * (Number(line.discountPercent || 0) / 100)), 0));
const grandTotal = computed(() => grossTotal.value - discountTotal.value);
const changeAmount = computed(() => Math.max(Number(cashPaid.value || 0) - grandTotal.value, 0));
const canProcessPayment = computed(() => {
  if (cart.value.length === 0) return false;
  if (paymentMethod.value !== 'cash') return true;
  return Number(cashPaid.value || 0) >= grandTotal.value;
});

const filteredProducts = computed(() => {
  const term = barcodeQuery.value.toLowerCase().trim();
  if (!term) return props.products;

  return props.products.filter((product) =>
    product.sku.toLowerCase().includes(term) ||
    (product.barcode || '').toLowerCase().includes(term) ||
    product.name.toLowerCase().includes(term),
  );
});

const processPayment = () => {
  if (!canProcessPayment.value) return;
  openReceiptPreview();
};

const saveDraft = () => {
  if (cart.value.length === 0) return;
  alert(`Draft transaksi disimpan (${cart.value.length} item).`);
};

const voidTransaction = () => {
  cart.value = [];
  cashPaid.value = 0;
  heldCart.value = [];
  isOnHold.value = false;
};

const toggleHoldResume = () => {
  if (!isOnHold.value) {
    if (cart.value.length === 0) return;
    heldCart.value = JSON.parse(JSON.stringify(cart.value));
    cart.value = [];
    cashPaid.value = 0;
    isOnHold.value = true;
    return;
  }

  cart.value = JSON.parse(JSON.stringify(heldCart.value));
  heldCart.value = [];
  isOnHold.value = false;
};

const openReceiptPreview = () => {
  document.getElementById('modal-pos-receipt').showModal();
};

const printReceipt = () => {
  window.print();
};

const handleCashierShortcuts = (event) => {
  const isInput = ['INPUT', 'TEXTAREA'].includes(event.target?.tagName);

  // F2: open product modal
  if (event.key === 'F2') {
    event.preventDefault();
    openProductModal();
    return;
  }

  // F4: focus nominal bayar
  if (event.key === 'F4') {
    event.preventDefault();
    cashInputRef.value?.focus();
    return;
  }

  // Ctrl+Enter: process payment
  if (event.ctrlKey && event.key === 'Enter') {
    event.preventDefault();
    processPayment();
    return;
  }

  // Ctrl+K: open modal and focus barcode
  if (event.ctrlKey && event.key.toLowerCase() === 'k') {
    event.preventDefault();
    openProductModal();
    return;
  }

  // Esc: close modal if open
  if (event.key === 'Escape') {
    const modal = document.getElementById('modal-pos-products');
    if (modal?.open) modal.close();
    return;
  }

  if (isInput) return;
};

onMounted(() => {
  window.addEventListener('keydown', handleCashierShortcuts);
});

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleCashierShortcuts);
});
</script>

<template>
  <Head title="Sales - POS Produk" />
  <AppLayout v-if="!fullscreen">
    <div class="space-y-4">
      <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Sales Workspace</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight">POS Produk - Fullscreen Mode</h1>
            <p class="mt-1 text-sm text-base-content/70">Kasir profesional untuk produk kemasan plastik dan makanan.</p>
          </div>
          <div class="flex items-center gap-2">
            <Link class="btn btn-ghost btn-sm" :href="route('erp.sales')">Back</Link>
            <button class="btn btn-primary" @click="openProductModal">+ Tambah Produk</button>
          </div>
        </div>
      </div>

      <div class="grid gap-4 xl:grid-cols-[1fr_320px]">
        <div class="card bg-base-100 shadow">
          <div class="overflow-x-auto">
            <table class="table table-zebra">
              <thead>
                <tr>
                  <th>SKU</th>
                  <th>Produk</th>
                  <th class="w-36">Harga</th>
                  <th class="w-28">Qty</th>
                  <th class="w-32">Diskon %</th>
                  <th class="w-40">Subtotal</th>
                  <th class="w-16"></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="line in cart" :key="line.sku">
                  <td class="font-mono text-xs">{{ line.sku }}</td>
                  <td class="font-medium">{{ line.name }}</td>
                  <td>{{ format(line.price) }}</td>
                  <td>
                    <input v-model.number="line.qty" type="number" min="1" class="input input-bordered input-sm w-20" />
                  </td>
                  <td>
                    <input v-model.number="line.discountPercent" type="number" min="0" max="100" class="input input-bordered input-sm w-24" />
                  </td>
                  <td class="font-semibold text-primary">{{ format(lineSubtotal(line)) }}</td>
                  <td>
                    <button class="btn btn-ghost btn-xs text-error" @click="removeLine(line.sku)">Hapus</button>
                  </td>
                </tr>
                <tr v-if="cart.length === 0">
                  <td colspan="7" class="py-10 text-center text-base-content/50">
                    Keranjang masih kosong. Klik "Tambah Produk" untuk mulai transaksi.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="card bg-base-100 shadow">
          <div class="card-body">
            <h2 class="card-title text-lg">Ringkasan Transaksi</h2>
            <div class="space-y-2 text-sm">
              <div class="flex justify-between"><span>Gross Total</span><span>{{ format(grossTotal) }}</span></div>
              <div class="flex justify-between"><span>Total Diskon</span><span class="text-warning">- {{ format(discountTotal) }}</span></div>
              <div class="divider my-1"></div>
              <div class="flex justify-between text-base font-bold"><span>Grand Total</span><span class="text-primary">{{ format(grandTotal) }}</span></div>
            </div>
            <div class="mt-4 space-y-3">
              <div>
                <label class="label py-1"><span class="label-text text-xs uppercase tracking-wide">Metode Pembayaran</span></label>
                <select v-model="paymentMethod" class="select select-bordered w-full">
                  <option value="cash">Cash</option>
                  <option value="transfer">Transfer Bank</option>
                  <option value="qris">QRIS</option>
                  <option value="debit">Kartu Debit</option>
                </select>
              </div>
              <div>
                <label class="label py-1"><span class="label-text text-xs uppercase tracking-wide">Nominal Bayar</span></label>
                <input
                  ref="cashInputRef"
                  v-model.number="cashPaid"
                  type="number"
                  min="0"
                  class="input input-bordered w-full"
                  :disabled="paymentMethod !== 'cash'"
                  placeholder="Masukkan nominal bayar"
                />
              </div>
              <div class="rounded-xl bg-base-200 px-3 py-2">
                <p class="text-xs uppercase tracking-wide text-base-content/60">Kembalian</p>
                <p class="text-lg font-bold text-success">{{ format(changeAmount) }}</p>
              </div>
            </div>
            <div class="mt-3 grid grid-cols-2 gap-2">
              <button class="btn btn-outline btn-sm" :disabled="cart.length === 0" @click="saveDraft">Simpan Draft</button>
              <button class="btn btn-outline btn-sm" :disabled="cart.length === 0 && !isOnHold" @click="toggleHoldResume">
                {{ isOnHold ? 'Resume' : 'Hold' }}
              </button>
              <button class="btn btn-outline btn-sm text-error" :disabled="cart.length === 0 && !isOnHold" @click="voidTransaction">Void</button>
              <button class="btn btn-outline btn-sm" :disabled="cart.length === 0" @click="openReceiptPreview">Preview Struk</button>
            </div>
            <button
              class="btn mt-4 w-full border-0 bg-primary text-primary-content disabled:!bg-slate-300 disabled:!text-slate-500"
              :disabled="!canProcessPayment"
              @click="processPayment"
            >
              Proses Pembayaran
            </button>
            <p class="mt-2 text-[11px] text-base-content/55">Shortcut: F2 (produk), F4 (bayar), Ctrl+Enter (proses), Ctrl+K (scan), Esc (tutup modal)</p>
          </div>
        </div>
      </div>
    </div>

    <dialog id="modal-pos-products" class="modal">
      <div class="modal-box max-w-2xl">
        <h3 class="font-bold text-lg">Pilih Produk untuk Ditambahkan</h3>
        <p class="text-sm text-base-content/60 mt-1">Scan barcode / cari produk, pilih produk, lalu tambahkan ke keranjang.</p>

        <div class="mt-3">
          <label class="label"><span class="label-text">Scan Barcode / Cari SKU</span></label>
          <input
            ref="modalBarcodeRef"
            v-model="barcodeQuery"
            type="text"
            class="input input-bordered w-full"
            placeholder="Contoh: PKG-SP-12X20"
            @keydown.enter.prevent="filteredProducts[0] && addProductBySku(filteredProducts[0].sku)"
          />
        </div>

        <div class="mt-4 border rounded-xl overflow-hidden">
          <table class="table table-zebra">
            <thead>
              <tr>
                <th></th>
                <th>SKU</th>
                <th>Barcode</th>
                <th>Produk</th>
                <th>Harga</th>
                <th>Stok</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="product in filteredProducts" :key="product.sku">
                <td>
                  <input
                    :value="product.sku"
                    v-model="selectedProductId"
                    type="radio"
                    name="selected_product"
                    class="radio radio-sm"
                  />
                </td>
                <td class="font-mono text-xs">{{ product.sku }}</td>
                <td class="font-mono text-xs">{{ product.barcode || '-' }}</td>
                <td class="font-medium">{{ product.name }}</td>
                <td>{{ format(product.price) }}</td>
                <td>{{ product.stock }}</td>
              </tr>
              <tr v-if="filteredProducts.length === 0">
                <td colspan="6" class="py-8 text-center text-base-content/50">Produk tidak ditemukan.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="modal-action">
          <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
          <button
            class="btn border-0 bg-primary text-primary-content disabled:!bg-slate-300 disabled:!text-slate-500"
            :disabled="!selectedProductId"
            @click="addSelectedProductToCart"
          >
            Tambah ke Keranjang
          </button>
        </div>
      </div>
    </dialog>

    <dialog id="modal-pos-receipt" class="modal">
      <div class="modal-box max-w-md">
        <h3 class="font-bold text-lg">Preview Struk</h3>
        <div class="mt-4 space-y-2 text-sm">
          <div class="flex justify-between"><span>Total Item</span><span>{{ cart.length }}</span></div>
          <div class="flex justify-between"><span>Metode Bayar</span><span class="uppercase">{{ paymentMethod }}</span></div>
          <div class="flex justify-between"><span>Grand Total</span><span>{{ format(grandTotal) }}</span></div>
          <div class="flex justify-between"><span>Bayar</span><span>{{ format(cashPaid) }}</span></div>
          <div class="flex justify-between"><span>Kembalian</span><span>{{ format(changeAmount) }}</span></div>
          <div class="divider my-1"></div>
          <div v-for="line in cart" :key="`receipt-${line.sku}`" class="flex justify-between gap-2">
            <span class="truncate">{{ line.name }} x{{ line.qty }}</span>
            <span>{{ format(lineSubtotal(line)) }}</span>
          </div>
        </div>
        <div class="modal-action">
          <form method="dialog"><button class="btn btn-ghost">Tutup</button></form>
          <button class="btn btn-primary" @click="printReceipt">Print</button>
        </div>
      </div>
    </dialog>
  </AppLayout>

  <div v-else class="min-h-screen bg-base-200 p-4 md:p-5">
    <div class="space-y-4">
      <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Sales Workspace</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight">POS Produk - Fullscreen Mode</h1>
            <p class="mt-1 text-sm text-base-content/70">Kasir profesional untuk produk kemasan plastik dan makanan.</p>
          </div>
          <div class="flex items-center gap-2">
            <a class="btn btn-ghost btn-sm" :href="route('erp.sales')">Back</a>
            <button class="btn btn-primary" @click="openProductModal">+ Tambah Produk</button>
          </div>
        </div>
      </div>

      <div class="grid gap-4 xl:grid-cols-[1fr_320px]">
        <div class="card bg-base-100 shadow">
          <div class="overflow-x-auto">
            <table class="table table-zebra">
              <thead>
                <tr>
                  <th>SKU</th>
                  <th>Produk</th>
                  <th class="w-36">Harga</th>
                  <th class="w-28">Qty</th>
                  <th class="w-32">Diskon %</th>
                  <th class="w-40">Subtotal</th>
                  <th class="w-16"></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="line in cart" :key="line.sku">
                  <td class="font-mono text-xs">{{ line.sku }}</td>
                  <td class="font-medium">{{ line.name }}</td>
                  <td>{{ format(line.price) }}</td>
                  <td><input v-model.number="line.qty" type="number" min="1" class="input input-bordered input-sm w-20" /></td>
                  <td><input v-model.number="line.discountPercent" type="number" min="0" max="100" class="input input-bordered input-sm w-24" /></td>
                  <td class="font-semibold text-primary">{{ format(lineSubtotal(line)) }}</td>
                  <td><button class="btn btn-ghost btn-xs text-error" @click="removeLine(line.sku)">Hapus</button></td>
                </tr>
                <tr v-if="cart.length === 0">
                  <td colspan="7" class="py-10 text-center text-base-content/50">Keranjang masih kosong. Klik "Tambah Produk" untuk mulai transaksi.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="card bg-base-100 shadow">
          <div class="card-body">
            <h2 class="card-title text-lg">Ringkasan Transaksi</h2>
            <div class="space-y-2 text-sm">
              <div class="flex justify-between"><span>Gross Total</span><span>{{ format(grossTotal) }}</span></div>
              <div class="flex justify-between"><span>Total Diskon</span><span class="text-warning">- {{ format(discountTotal) }}</span></div>
              <div class="divider my-1"></div>
              <div class="flex justify-between text-base font-bold"><span>Grand Total</span><span class="text-primary">{{ format(grandTotal) }}</span></div>
            </div>
            <div class="mt-4 space-y-3">
              <div>
                <label class="label py-1"><span class="label-text text-xs uppercase tracking-wide">Metode Pembayaran</span></label>
                <select v-model="paymentMethod" class="select select-bordered w-full">
                  <option value="cash">Cash</option>
                  <option value="transfer">Transfer Bank</option>
                  <option value="qris">QRIS</option>
                  <option value="debit">Kartu Debit</option>
                </select>
              </div>
              <div>
                <label class="label py-1"><span class="label-text text-xs uppercase tracking-wide">Nominal Bayar</span></label>
                <input
                  ref="cashInputRef"
                  v-model.number="cashPaid"
                  type="number"
                  min="0"
                  class="input input-bordered w-full"
                  :disabled="paymentMethod !== 'cash'"
                  placeholder="Masukkan nominal bayar"
                />
              </div>
              <div class="rounded-xl bg-base-200 px-3 py-2">
                <p class="text-xs uppercase tracking-wide text-base-content/60">Kembalian</p>
                <p class="text-lg font-bold text-success">{{ format(changeAmount) }}</p>
              </div>
            </div>
            <div class="mt-3 grid grid-cols-2 gap-2">
              <button class="btn btn-outline btn-sm" :disabled="cart.length === 0" @click="saveDraft">Simpan Draft</button>
              <button class="btn btn-outline btn-sm" :disabled="cart.length === 0 && !isOnHold" @click="toggleHoldResume">
                {{ isOnHold ? 'Resume' : 'Hold' }}
              </button>
              <button class="btn btn-outline btn-sm text-error" :disabled="cart.length === 0 && !isOnHold" @click="voidTransaction">Void</button>
              <button class="btn btn-outline btn-sm" :disabled="cart.length === 0" @click="openReceiptPreview">Preview Struk</button>
            </div>
            <button
              class="btn mt-4 w-full border-0 bg-primary text-primary-content disabled:!bg-slate-300 disabled:!text-slate-500"
              :disabled="!canProcessPayment"
              @click="processPayment"
            >
              Proses Pembayaran
            </button>
            <p class="mt-2 text-[11px] text-base-content/55">Shortcut: F2 (produk), F4 (bayar), Ctrl+Enter (proses), Ctrl+K (scan), Esc (tutup modal)</p>
          </div>
        </div>
      </div>
    </div>

    <dialog id="modal-pos-products" class="modal">
      <div class="modal-box max-w-2xl">
        <h3 class="font-bold text-lg">Pilih Produk untuk Ditambahkan</h3>
        <p class="text-sm text-base-content/60 mt-1">Scan barcode / cari produk, pilih produk, lalu tambahkan ke keranjang.</p>
        <div class="mt-3">
          <label class="label"><span class="label-text">Scan Barcode / Cari SKU</span></label>
          <input
            ref="modalBarcodeRef"
            v-model="barcodeQuery"
            type="text"
            class="input input-bordered w-full"
            placeholder="Contoh: PKG-SP-12X20"
            @keydown.enter.prevent="filteredProducts[0] && addProductBySku(filteredProducts[0].sku)"
          />
        </div>
        <div class="mt-4 border rounded-xl overflow-hidden">
          <table class="table table-zebra">
            <thead>
              <tr><th></th><th>SKU</th><th>Barcode</th><th>Produk</th><th>Harga</th><th>Stok</th></tr>
            </thead>
            <tbody>
              <tr v-for="product in filteredProducts" :key="product.sku">
                <td><input :value="product.sku" v-model="selectedProductId" type="radio" name="selected_product_fullscreen" class="radio radio-sm" /></td>
                <td class="font-mono text-xs">{{ product.sku }}</td>
                <td class="font-mono text-xs">{{ product.barcode || '-' }}</td>
                <td class="font-medium">{{ product.name }}</td>
                <td>{{ format(product.price) }}</td>
                <td>{{ product.stock }}</td>
              </tr>
              <tr v-if="filteredProducts.length === 0">
                <td colspan="6" class="py-8 text-center text-base-content/50">Produk tidak ditemukan.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="modal-action">
          <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
          <button
            class="btn border-0 bg-primary text-primary-content disabled:!bg-slate-300 disabled:!text-slate-500"
            :disabled="!selectedProductId"
            @click="addSelectedProductToCart"
          >
            Tambah ke Keranjang
          </button>
        </div>
      </div>
    </dialog>

    <dialog id="modal-pos-receipt" class="modal">
      <div class="modal-box max-w-md">
        <h3 class="font-bold text-lg">Preview Struk</h3>
        <div class="mt-4 space-y-2 text-sm">
          <div class="flex justify-between"><span>Total Item</span><span>{{ cart.length }}</span></div>
          <div class="flex justify-between"><span>Metode Bayar</span><span class="uppercase">{{ paymentMethod }}</span></div>
          <div class="flex justify-between"><span>Grand Total</span><span>{{ format(grandTotal) }}</span></div>
          <div class="flex justify-between"><span>Bayar</span><span>{{ format(cashPaid) }}</span></div>
          <div class="flex justify-between"><span>Kembalian</span><span>{{ format(changeAmount) }}</span></div>
          <div class="divider my-1"></div>
          <div v-for="line in cart" :key="`receipt-full-${line.sku}`" class="flex justify-between gap-2">
            <span class="truncate">{{ line.name }} x{{ line.qty }}</span>
            <span>{{ format(lineSubtotal(line)) }}</span>
          </div>
        </div>
        <div class="modal-action">
          <form method="dialog"><button class="btn btn-ghost">Tutup</button></form>
          <button class="btn btn-primary" @click="printReceipt">Print</button>
        </div>
      </div>
    </dialog>
  </div>
</template>
