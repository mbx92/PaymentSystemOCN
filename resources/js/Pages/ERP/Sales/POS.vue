<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import ProductPickerModal from '@/Components/ProductPickerModal.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { useCurrency } from '@/composables/useCurrency';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
  products: Array,
  price_channels: Array,
  payment_methods: Array,
  fullscreen: Boolean,
});

const { format, parse, formatInput } = useCurrency();
const showProductModal = ref(false);
const selectedSalesChannel = ref(props.price_channels?.[0]?.key ?? 'retail');
const priceForChannel = (item, channel) => Number(item.channel_prices?.[channel] ?? item.price ?? item.selling_price ?? 0);
const productCatalog = ref((props.products ?? []).map((item) => ({
  ...item,
  price: priceForChannel(item, selectedSalesChannel.value),
})));
const cart = ref([]);
const cashPaidInput = ref('0');
const defaultPaymentMethodId = props.payment_methods?.[0]?.id ?? null;
const paymentMethodId = ref(defaultPaymentMethodId);
const heldCart = ref([]);
const heldAdditionalCharges = ref([]);
const heldMarketplaceOrderCode = ref('');
const isOnHold = ref(false);
const cashInputRef = ref(null);
const processingPayment = ref(false);
const checkoutError = ref('');
const receiptPrintError = ref('');
const receiptPrintSuccess = ref('');
const lastReceipt = ref(null);
const successToast = ref('');
let toastTimer = null;
const printingReceipt = ref(false);
const additionalCharges = ref([]);
const marketplaceOrderCode = ref('');
const CHARGE_ADD = 'add_to_total';
const CHARGE_ADMIN = 'journal_admin';
const chargeForm = ref({
  name: '',
  amount: '0',
  kind: CHARGE_ADD,
});

/** Modal pengganti alert() — stok, validasi biaya, draft, dll. */
const posAlertDialogEl = ref(null);
const posAlertTitle = ref('Informasi');
const posAlertMessage = ref('');
const posAlertVariant = ref('info');

const posAlertTitleClass = computed(() => {
  if (posAlertVariant.value === 'error') {
    return 'text-error';
  }
  if (posAlertVariant.value === 'warning') {
    return 'text-warning';
  }
  if (posAlertVariant.value === 'success') {
    return 'text-success';
  }
  return '';
});

function showPosAlertModal(title, message, variant = 'info') {
  posAlertTitle.value = title;
  posAlertMessage.value = message;
  posAlertVariant.value = variant;
  posAlertDialogEl.value?.showModal();
}

function closePosAlertModal() {
  posAlertDialogEl.value?.close();
}

function onPosAlertDialogClose() {
  posAlertMessage.value = '';
  posAlertVariant.value = 'info';
}
const selectedSalesChannelOption = computed(() => props.price_channels?.find((channel) => channel.key === selectedSalesChannel.value) ?? props.price_channels?.[0] ?? { key: 'retail', label: 'Retail' });
const selectedSalesChannelLabel = computed(() => selectedSalesChannelOption.value?.label ?? 'Retail');
const isMarketplaceChannel = computed(() => selectedSalesChannel.value === 'marketplace');

const applySalesChannelPricesToCatalog = () => {
  productCatalog.value = productCatalog.value.map((item) => ({
    ...item,
    price: priceForChannel(item, selectedSalesChannel.value),
  }));
};

const repriceCartForSelectedChannel = () => {
  cart.value.forEach((line) => {
    const catalogItem = productCatalog.value.find((item) => item.id === line.productId);
    if (!catalogItem) return;
    line.price = Number(catalogItem.price);
    line.salesChannel = selectedSalesChannel.value;
    line.salesChannelLabel = selectedSalesChannelLabel.value;
  });
};

watch(selectedSalesChannel, () => {
  if (!isMarketplaceChannel.value) marketplaceOrderCode.value = '';
});

const openProductModal = () => {
  showProductModal.value = true;
};

const addProductToCart = (selected) => {
  if (Number(selected.stock ?? 0) <= 0) {
    showPosAlertModal('Stok habis', 'Stok produk habis.', 'error');
    return;
  }

  const existing = cart.value.find((line) => line.productId === selected.id);
  if (existing) {
    if (existing.qty + 1 > existing.availableStock) {
      showPosAlertModal(
        'Stok tidak mencukupi',
        `Stok tidak mencukupi. Maksimal ${existing.availableStock} ${existing.uom}.`,
        'error',
      );
      return;
    }
    existing.qty += 1;
  } else {
    cart.value.push({
      productId: selected.id,
      masterProductId: selected.master_product_id ?? null,
      sku: selected.sku,
      name: selected.name,
      uom: selected.uom,
      priceOperation: selected.price_operation ?? 'multiply',
      multiplier: Number(selected.multiplier ?? 1),
      availableStock: Number(selected.stock ?? 0),
      price: priceForChannel(selected, selectedSalesChannel.value),
      salesChannel: selectedSalesChannel.value,
      salesChannelLabel: selectedSalesChannelLabel.value,
      qty: 1,
      discountPercent: 0,
    });
  }
  showProductModal.value = false;
};

const showSuccessToast = (message) => {
  successToast.value = message;
  if (toastTimer) window.clearTimeout(toastTimer);
  toastTimer = window.setTimeout(() => {
    successToast.value = '';
    toastTimer = null;
  }, 2500);
};

const recalculateStocksForMasterProduct = (masterProductId) => {
  const related = productCatalog.value.filter((item) => item.master_product_id === masterProductId);
  const baseVariant = related.find((item) => Number(item.multiplier || 1) === 1);
  if (!baseVariant) return;

  const baseStock = Math.max(Number(baseVariant.stock || 0), 0);
  related.forEach((item) => {
    const multiplier = Math.max(Number(item.multiplier || 1), 0.0001);
    const operation = item.price_operation || 'multiply';
    if (Number(item.multiplier || 1) === 1) {
      item.stock = baseStock;
      return;
    }

    item.stock = operation === 'divide'
      ? Math.floor(baseStock * multiplier)
      : Math.floor(baseStock / multiplier);
  });
};

const applyLocalStockMutationAfterCheckout = (lines) => {
  const consumedByMaster = new Map();
  lines.forEach((line) => {
    const multiplier = Math.max(Number(line.multiplier || 1), 0.0001);
    const qty = Number(line.qty || 0);
    const operation = line.priceOperation || 'multiply';
    const consumedBase = operation === 'divide'
      ? Math.ceil(qty / multiplier)
      : Math.ceil(qty * multiplier);
    const current = consumedByMaster.get(line.masterProductId) || 0;
    consumedByMaster.set(line.masterProductId, current + consumedBase);
  });

  consumedByMaster.forEach((consumed, masterProductId) => {
    const baseVariant = productCatalog.value.find((item) => item.master_product_id === masterProductId && Number(item.multiplier || 1) === 1);
    if (!baseVariant) return;
    baseVariant.stock = Math.max(Number(baseVariant.stock || 0) - consumed, 0);
    recalculateStocksForMasterProduct(masterProductId);
  });
};

const removeLine = (sku) => {
  cart.value = cart.value.filter((line) => line.productId !== sku);
};

const sanitizeLineQty = (line) => {
  const maxQty = Math.max(Number(line.availableStock || 0), 0);
  let qty = Number(line.qty || 0);
  if (!Number.isFinite(qty) || qty < 1) qty = 1;
  if (maxQty > 0 && qty > maxQty) {
    qty = maxQty;
    showPosAlertModal(
      'Stok tidak mencukupi',
      `Qty melebihi stok. Maksimal ${maxQty} ${line.uom}.`,
      'warning',
    );
  }
  line.qty = qty;
};

const lineSubtotal = (line) => {
  const gross = line.price * line.qty;
  const discount = gross * (Number(line.discountPercent || 0) / 100);
  return gross - discount;
};

const sanitizeChargeAmount = (value) => parse(formatInput(value));

const additionalFeeAddToTotal = computed(() => additionalCharges.value
  .filter((c) => (c.kind ?? CHARGE_ADD) === CHARGE_ADD)
  .reduce((sum, c) => sum + Number(c.amount || 0), 0));

const adminChannelFeeTotal = computed(() => additionalCharges.value
  .filter((c) => (c.kind ?? CHARGE_ADD) === CHARGE_ADMIN)
  .reduce((sum, c) => sum + Number(c.amount || 0), 0));

const grossTotal = computed(() => cart.value.reduce((sum, line) => sum + (line.price * line.qty), 0));
const discountTotal = computed(() => cart.value.reduce((sum, line) => sum + ((line.price * line.qty) * (Number(line.discountPercent || 0) / 100)), 0));
const grandTotal = computed(() => (grossTotal.value - discountTotal.value) + Number(additionalFeeAddToTotal.value || 0));
const selectedPaymentMethod = computed(() => props.payment_methods?.find((method) => method.id === paymentMethodId.value) ?? null);
const isCashPayment = computed(() => selectedPaymentMethod.value?.code === 'cash');
const cashPaidValue = computed(() => parse(cashPaidInput.value));
const changeAmount = computed(() => Math.max(Number(cashPaidValue.value || 0) - grandTotal.value, 0));
const hasStockViolation = computed(() => cart.value.some((line) => Number(line.qty) > Number(line.availableStock || 0)));
const canProcessPayment = computed(() => {
  if (cart.value.length === 0) return false;
  if (hasStockViolation.value) return false;
  if (isMarketplaceChannel.value && !marketplaceOrderCode.value.trim()) return false;
  if (!isCashPayment.value) return true;
  return Number(cashPaidValue.value || 0) >= grandTotal.value;
});

const onCashInput = (event) => {
  cashPaidInput.value = formatInput(event.target.value);
};

const onAdditionalChargeAmountInput = (event) => {
  chargeForm.value.amount = formatInput(event.target.value);
};

const openAdditionalChargeModal = () => {
  chargeForm.value = { name: '', amount: '0', kind: CHARGE_ADD };
  document.getElementById('modal-additional-charges')?.showModal();
};

const addAdditionalCharge = () => {
  const name = chargeForm.value.name.trim();
  const amount = sanitizeChargeAmount(chargeForm.value.amount);

  if (!name) {
    showPosAlertModal('Biaya lainnya', 'Nama biaya wajib diisi.', 'warning');
    return;
  }
  if (Number(amount || 0) <= 0) {
    showPosAlertModal('Biaya lainnya', 'Nominal biaya harus lebih dari 0.', 'warning');
    return;
  }

  additionalCharges.value.push({
    id: `${Date.now()}-${Math.random().toString(16).slice(2)}`,
    name,
    amount: Number(amount),
    kind: chargeForm.value.kind === CHARGE_ADMIN ? CHARGE_ADMIN : CHARGE_ADD,
  });

  chargeForm.value = { name: '', amount: '0', kind: CHARGE_ADD };
};

const removeAdditionalCharge = (chargeId) => {
  additionalCharges.value = additionalCharges.value.filter((charge) => charge.id !== chargeId);
};

const getCookieValue = (name) => {
  const value = `; ${document.cookie}`;
  const parts = value.split(`; ${name}=`);
  if (parts.length === 2) return parts.pop()?.split(';').shift() || '';
  return '';
};

const processPayment = async () => {
  if (!canProcessPayment.value || processingPayment.value) return;

  processingPayment.value = true;
  checkoutError.value = '';

  try {
    const response = await fetch(route('erp.sales.pos.checkout'), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        'X-XSRF-TOKEN': decodeURIComponent(getCookieValue('XSRF-TOKEN') || ''),
      },
      credentials: 'same-origin',
      body: JSON.stringify({
        sales_channel: selectedSalesChannel.value,
        marketplace_order_code: isMarketplaceChannel.value ? marketplaceOrderCode.value.trim() : null,
        payment_method_id: paymentMethodId.value,
        cash_paid: isCashPayment.value ? cashPaidValue.value : grandTotal.value,
        additional_charges: additionalCharges.value.map((charge) => ({
          name: charge.name,
          amount: Number(charge.amount),
          kind: charge.kind ?? CHARGE_ADD,
        })),
        items: cart.value.map((line) => ({
          master_product_id: line.masterProductId,
          sku: line.sku,
          uom: line.uom,
          qty: Number(line.qty),
          unit_price: Number(line.price),
          discount_percent: Number(line.discountPercent || 0),
          multiplier: Number(line.multiplier || 1),
          price_operation: line.priceOperation || 'multiply',
        })),
      }),
    });

    const payload = await response.json();
    if (!response.ok) {
      const firstError = payload?.errors ? Object.values(payload.errors)[0]?.[0] : null;
      throw new Error(firstError || payload?.message || 'Gagal memproses pembayaran POS.');
    }

    lastReceipt.value = {
      number: payload.transaction_number,
      marketplaceOrderCode: payload.marketplace_order_code || marketplaceOrderCode.value.trim(),
      paymentMethodName: payload.payment_method_name,
      salesChannelLabel: payload.sales_channel_label || selectedSalesChannelLabel.value,
      grandTotal: payload.grand_total,
      cashPaid: payload.cash_paid,
      change: payload.change,
      additionalFee: additionalFeeAddToTotal.value,
      adminChannelFee: adminChannelFeeTotal.value,
      additionalCharges: additionalCharges.value.map((charge) => ({ ...charge, kind: charge.kind ?? CHARGE_ADD })),
      lines: cart.value.map((line) => ({ ...line })),
    };

    applyLocalStockMutationAfterCheckout(cart.value);
    showSuccessToast(`Transaksi ${payload.transaction_number} berhasil disimpan.`);

    cart.value = [];
    heldCart.value = [];
    heldAdditionalCharges.value = [];
    heldMarketplaceOrderCode.value = '';
    isOnHold.value = false;
    cashPaidInput.value = '0';
    additionalCharges.value = [];
    marketplaceOrderCode.value = '';

    openReceiptPreview();
  } catch (error) {
    checkoutError.value = error?.message || 'Gagal memproses pembayaran.';
  } finally {
    processingPayment.value = false;
  }
};

const saveDraft = () => {
  if (cart.value.length === 0) return;
  showPosAlertModal('Draft', `Draft transaksi disimpan (${cart.value.length} item).`, 'success');
};

const voidTransaction = () => {
  cart.value = [];
  cashPaidInput.value = '0';
  additionalCharges.value = [];
  marketplaceOrderCode.value = '';
  heldCart.value = [];
  heldAdditionalCharges.value = [];
  heldMarketplaceOrderCode.value = '';
  isOnHold.value = false;
};

const toggleHoldResume = () => {
  if (!isOnHold.value) {
    if (cart.value.length === 0) return;
    heldCart.value = JSON.parse(JSON.stringify(cart.value));
    heldAdditionalCharges.value = JSON.parse(JSON.stringify(additionalCharges.value));
    heldMarketplaceOrderCode.value = marketplaceOrderCode.value;
    cart.value = [];
    cashPaidInput.value = '0';
    additionalCharges.value = [];
    marketplaceOrderCode.value = '';
    marketplaceOrderCode.value = '';
    isOnHold.value = true;
    return;
  }

  cart.value = JSON.parse(JSON.stringify(heldCart.value));
  heldCart.value = [];
  additionalCharges.value = JSON.parse(JSON.stringify(heldAdditionalCharges.value));
  heldAdditionalCharges.value = [];
  marketplaceOrderCode.value = heldMarketplaceOrderCode.value;
  heldMarketplaceOrderCode.value = '';
  isOnHold.value = false;
};

const openReceiptPreview = () => {
  receiptPrintError.value = '';
  receiptPrintSuccess.value = '';
  document.getElementById('modal-pos-receipt').showModal();
};
const receiptLines = computed(() => (lastReceipt.value?.lines?.length ? lastReceipt.value.lines : cart.value));
const canDirectPrintFromPreview = computed(() => !lastReceipt.value?.number);

const printReceipt = async () => {
  if (!lastReceipt.value?.number || printingReceipt.value) {
    receiptPrintError.value = 'Struk belum tersimpan. Proses transaksi dulu sebelum cetak ulang.';
    return;
  }

  printingReceipt.value = true;
  receiptPrintError.value = '';
  receiptPrintSuccess.value = '';

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
        transaction_number: lastReceipt.value.number,
      }),
    });

    const payload = await response.json();
    if (!response.ok) {
      const firstError = payload?.errors ? Object.values(payload.errors)[0]?.[0] : null;
      throw new Error(firstError || payload?.message || 'Gagal mencetak struk ke printer thermal.');
    }

    receiptPrintSuccess.value = payload?.message || `Struk ${lastReceipt.value.number} berhasil dikirim ke printer.`;
  } catch (error) {
    receiptPrintError.value = error?.message || 'Gagal mencetak struk ke printer thermal.';
  } finally {
    printingReceipt.value = false;
  }
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
    if (showProductModal.value) showProductModal.value = false;
    return;
  }

  if (isInput) return;
};

watch(selectedSalesChannel, () => {
  applySalesChannelPricesToCatalog();
  repriceCartForSelectedChannel();
});

onMounted(() => {
  applySalesChannelPricesToCatalog();
  window.addEventListener('keydown', handleCashierShortcuts);
});

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleCashierShortcuts);
  if (toastTimer) window.clearTimeout(toastTimer);
});
</script>

<template>
  <Head title="Sales - POS Produk" />
  <div v-if="successToast" class="toast toast-top toast-end z-[10000]">
    <div class="alert alert-success text-sm">
      <span>{{ successToast }}</span>
    </div>
  </div>
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
            <Link class="btn btn-ghost btn-sm gap-1.5" :href="route('erp.sales')">
              <ArrowLeftIcon class="h-4 w-4" />
              Back
            </Link>
            <button class="btn btn-primary" @click="openProductModal">+ Tambah Produk</button>
          </div>
        </div>
      </div>

      <div class="grid gap-4 xl:grid-cols-[1fr_320px]">
        <div class="ocn-panel">
          <div class="ocn-panel__head">
            <h2 class="ocn-panel__title">Keranjang</h2>
            <p class="ocn-panel__desc">SKU, qty, diskon, dan subtotal per baris.</p>
          </div>
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
                <tr v-for="line in cart" :key="line.productId">
                  <td class="font-mono text-xs">{{ line.sku }}</td>
                  <td class="font-medium">{{ line.name }} <span class="text-xs text-base-content/60">({{ line.uom }})</span></td>
                  <td>{{ format(line.price) }}</td>
                  <td>
                    <div class="flex items-center gap-1 whitespace-nowrap">
                      <input v-model.number="line.qty" type="number" min="1" :max="line.availableStock" class="input input-bordered input-sm w-20" @change="sanitizeLineQty(line)" />
                      <span class="text-xs text-base-content/60">/ {{ line.availableStock }}</span>
                    </div>
                  </td>
                  <td>
                    <input v-model.number="line.discountPercent" type="number" min="0" max="100" class="input input-bordered input-sm w-24" />
                  </td>
                  <td class="font-semibold text-primary">{{ format(lineSubtotal(line)) }}</td>
                  <td>
                    <button class="btn btn-ghost btn-xs text-error" @click="removeLine(line.productId)">Hapus</button>
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

        <div class="ocn-panel">
          <div class="ocn-panel__head">
            <h2 class="ocn-panel__title">Ringkasan transaksi</h2>
            <p class="ocn-panel__desc">Total, pembayaran, dan tindakan kasir.</p>
          </div>
          <div class="card-body">
            <div class="space-y-2 text-sm">
              <div class="flex justify-between"><span>Gross Total</span><span>{{ format(grossTotal) }}</span></div>
              <div class="flex justify-between"><span>Total Diskon</span><span class="text-warning">- {{ format(discountTotal) }}</span></div>
              <div class="flex justify-between"><span>Biaya lainnya (ditagih)</span><span>{{ format(additionalFeeAddToTotal) }}</span></div>
              <div v-if="adminChannelFeeTotal > 0" class="flex justify-between text-xs text-base-content/65">
                <span>Biaya admin channel (hanya jurnal)</span>
                <span>{{ format(adminChannelFeeTotal) }}</span>
              </div>
              <div v-if="additionalCharges.length" class="rounded-lg bg-base-200/60 px-3 py-2 text-xs">
                <div v-for="charge in additionalCharges" :key="charge.id" class="flex justify-between gap-2">
                  <span class="truncate">{{ charge.name }} <span class="opacity-70">({{ (charge.kind ?? CHARGE_ADD) === CHARGE_ADMIN ? 'jurnal' : 'ke total' }})</span></span>
                  <span>{{ format(charge.amount) }}</span>
                </div>
              </div>
              <div class="divider my-1"></div>
              <div class="flex justify-between text-base font-bold"><span>Grand Total</span><span class="text-primary">{{ format(grandTotal) }}</span></div>
            </div>
            <div class="mt-4 space-y-3">
              <div>
                <label class="label py-1"><span class="label-text text-xs uppercase tracking-wide">Sales Channel</span></label>
                <select v-model="selectedSalesChannel" class="select select-bordered w-full">
                  <option v-for="channel in price_channels" :key="channel.key" :value="channel.key">{{ channel.label }}</option>
                </select>
              </div>
              <div v-if="isMarketplaceChannel">
                <label class="label py-1"><span class="label-text text-xs uppercase tracking-wide">Kode Pesanan</span></label>
                <input
                  v-model.trim="marketplaceOrderCode"
                  type="text"
                  class="input input-bordered w-full"
                  placeholder="Contoh: MP-20260516-001"
                  maxlength="100"
                />
                <p class="mt-1 text-[11px] text-base-content/55">Wajib untuk transaksi marketplace agar pesanan mudah dilacak.</p>
              </div>
              <div>
                <label class="label py-1"><span class="label-text text-xs uppercase tracking-wide">Metode Pembayaran</span></label>
                <select v-model="paymentMethodId" class="select select-bordered w-full">
                  <option v-for="method in payment_methods" :key="method.id" :value="method.id">{{ method.name }}</option>
                </select>
              </div>
              <div>
                <label class="label py-1"><span class="label-text text-xs uppercase tracking-wide">Biaya lainnya</span></label>
                <button class="btn btn-outline w-full justify-between" type="button" @click="openAdditionalChargeModal">
                  <span>{{ additionalCharges.length ? `${additionalCharges.length} biaya` : 'Tambah / atur biaya lainnya' }}</span>
                  <span>{{ format(additionalFeeAddToTotal) }}</span>
                </button>
                <p class="mt-1 text-[11px] text-base-content/55">Ongkir atau biaya lain menambah total bayar; biaya admin channel hanya tercatat di jurnal (tidak mengubah total bayar).</p>
              </div>
              <div>
                <label class="label py-1"><span class="label-text text-xs uppercase tracking-wide">Nominal Bayar</span></label>
                <input
                  ref="cashInputRef"
                  :value="cashPaidInput"
                  type="text"
                  class="input input-bordered w-full"
                  :disabled="!isCashPayment"
                  placeholder="Masukkan nominal bayar"
                  @input="onCashInput"
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
            <p v-if="checkoutError" class="mt-2 text-sm text-error">{{ checkoutError }}</p>
            <p v-if="hasStockViolation" class="mt-1 text-xs text-error">Ada qty melebihi stok tersedia. Perbaiki sebelum proses.</p>
            <button
              class="btn btn-primary mt-4 w-full border-0"
              :disabled="!canProcessPayment || processingPayment"
              @click="processPayment"
            >
              {{ processingPayment ? 'Memproses...' : 'Proses Pembayaran' }}
            </button>
            <p class="mt-2 text-[11px] text-base-content/55">Shortcut: F2 (produk), F4 (bayar), Ctrl+Enter (proses), Ctrl+K (scan), Esc (tutup modal)</p>
          </div>
        </div>
      </div>

    </div>

    <dialog id="modal-pos-receipt" class="modal">
      <div class="modal-box max-w-md">
        <h3 class="font-bold text-lg">Preview Struk</h3>
        <div class="mt-4 space-y-2 text-sm">
          <div class="flex justify-between"><span>No. Transaksi</span><span>{{ lastReceipt?.number || '-' }}</span></div>
          <div class="flex justify-between"><span>Total Item</span><span>{{ receiptLines.length }}</span></div>
          <div class="flex justify-between"><span>Sales Channel</span><span>{{ lastReceipt?.salesChannelLabel || selectedSalesChannelLabel }}</span></div>
          <div v-if="lastReceipt?.marketplaceOrderCode || marketplaceOrderCode" class="flex justify-between gap-3">
            <span>Kode Pesanan</span>
            <span class="font-mono text-right">{{ lastReceipt?.marketplaceOrderCode || marketplaceOrderCode }}</span>
          </div>
          <div class="flex justify-between"><span>Metode Bayar</span><span class="uppercase">{{ lastReceipt?.paymentMethodName || selectedPaymentMethod?.name || '-' }}</span></div>
          <div class="flex justify-between"><span>Biaya lainnya (ditagih)</span><span>{{ format(lastReceipt?.additionalFee ?? additionalFeeAddToTotal) }}</span></div>
          <div v-if="(lastReceipt?.adminChannelFee ?? 0) > 0 || adminChannelFeeTotal > 0" class="flex justify-between text-xs text-base-content/65">
            <span>Biaya admin channel (jurnal)</span>
            <span>{{ format(lastReceipt?.adminChannelFee ?? adminChannelFeeTotal) }}</span>
          </div>
          <div v-if="(lastReceipt?.additionalCharges?.length || additionalCharges.length)" class="rounded-lg bg-base-200/60 px-3 py-2 text-xs">
            <div
              v-for="charge in (lastReceipt?.additionalCharges?.length ? lastReceipt.additionalCharges : additionalCharges)"
              :key="charge.id"
              class="flex justify-between gap-2"
            >
              <span class="truncate">{{ charge.name }} <span class="opacity-70">({{ (charge.kind ?? CHARGE_ADD) === CHARGE_ADMIN ? 'jurnal' : 'ke total' }})</span></span>
              <span>{{ format(charge.amount) }}</span>
            </div>
          </div>
          <div class="flex justify-between"><span>Grand Total</span><span>{{ format(lastReceipt?.grandTotal ?? grandTotal) }}</span></div>
          <div class="flex justify-between"><span>Bayar</span><span>{{ format(lastReceipt?.cashPaid ?? cashPaidValue) }}</span></div>
          <div class="flex justify-between"><span>Kembalian</span><span>{{ format(lastReceipt?.change ?? changeAmount) }}</span></div>
          <div class="divider my-1"></div>
          <div v-for="line in receiptLines" :key="`receipt-${line.productId}`" class="flex justify-between gap-2">
            <span class="truncate">{{ line.name }} ({{ line.uom }}) x{{ line.qty }}</span>
            <span>{{ format(lineSubtotal(line)) }}</span>
          </div>
        </div>
        <p v-if="receiptPrintSuccess" class="mt-3 text-sm text-success">{{ receiptPrintSuccess }}</p>
        <p v-if="receiptPrintError" class="mt-3 text-sm text-error">{{ receiptPrintError }}</p>
        <div class="modal-action">
          <form method="dialog"><button class="btn btn-ghost">Tutup</button></form>
          <button v-if="canDirectPrintFromPreview" class="btn btn-primary" :disabled="printingReceipt" @click="printReceipt">{{ printingReceipt ? 'Mencetak...' : 'Print' }}</button>
        </div>
      </div>
    </dialog>

    <dialog id="modal-additional-charges" class="modal">
      <div class="modal-box max-w-lg">
        <h3 class="font-bold text-lg">Biaya lainnya</h3>
        <div class="mt-4 space-y-3">
          <div class="space-y-2 rounded-lg bg-base-200/50 p-3 text-sm">
            <label class="flex cursor-pointer items-start gap-2">
              <input v-model="chargeForm.kind" type="radio" class="radio radio-sm radio-primary mt-0.5" :value="CHARGE_ADD" />
              <span><span class="font-medium">Tambah ke total bayar</span> — misalnya ongkir atau layanan yang dibebankan ke pelanggan.</span>
            </label>
            <label class="flex cursor-pointer items-start gap-2">
              <input v-model="chargeForm.kind" type="radio" class="radio radio-sm radio-primary mt-0.5" :value="CHARGE_ADMIN" />
              <span><span class="font-medium">Biaya admin channel (jurnal saja)</span> — potongan channel; tidak mengubah total yang harus dibayar di POS.</span>
            </label>
          </div>
          <div class="grid gap-3 md:grid-cols-[1fr_180px_auto]">
            <input v-model="chargeForm.name" type="text" class="input input-bordered w-full" placeholder="Nama biaya, contoh: Ongkir / Komisi channel" />
            <input :value="chargeForm.amount" type="text" class="input input-bordered w-full" placeholder="Nominal" @input="onAdditionalChargeAmountInput" />
            <button class="btn btn-primary" type="button" @click="addAdditionalCharge">Tambah</button>
          </div>
          <div class="rounded-xl border border-base-300">
            <div v-if="additionalCharges.length" class="divide-y divide-base-300">
              <div v-for="charge in additionalCharges" :key="charge.id" class="flex items-center justify-between gap-3 px-4 py-3 text-sm">
                <div>
                  <p class="font-medium">{{ charge.name }}</p>
                  <p class="text-xs text-base-content/60">{{ format(charge.amount) }} · {{ (charge.kind ?? CHARGE_ADD) === CHARGE_ADMIN ? 'jurnal' : 'ke total' }}</p>
                </div>
                <button class="btn btn-ghost btn-xs text-error" type="button" @click="removeAdditionalCharge(charge.id)">Hapus</button>
              </div>
            </div>
            <div v-else class="px-4 py-6 text-center text-sm text-base-content/50">Belum ada biaya lainnya.</div>
          </div>
          <div class="space-y-1 text-sm">
            <div class="flex justify-between font-semibold">
              <span>Total ditagih ke pelanggan</span>
              <span>{{ format(additionalFeeAddToTotal) }}</span>
            </div>
            <div v-if="adminChannelFeeTotal > 0" class="flex justify-between text-xs text-base-content/65">
              <span>Total biaya admin (jurnal)</span>
              <span>{{ format(adminChannelFeeTotal) }}</span>
            </div>
          </div>
        </div>
        <div class="modal-action">
          <form method="dialog"><button class="btn btn-ghost">Tutup</button></form>
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
            <a class="btn btn-ghost btn-sm gap-1.5" :href="route('erp.sales')">
              <ArrowLeftIcon class="h-4 w-4" />
              Back
            </a>
            <button class="btn btn-primary" @click="openProductModal">+ Tambah Produk</button>
          </div>
        </div>
      </div>

      <div class="grid gap-4 xl:grid-cols-[1fr_320px]">
        <div class="ocn-panel">
          <div class="ocn-panel__head">
            <h2 class="ocn-panel__title">Keranjang</h2>
            <p class="ocn-panel__desc">SKU, qty, diskon, dan subtotal per baris.</p>
          </div>
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
                <tr v-for="line in cart" :key="line.productId">
                  <td class="font-mono text-xs">{{ line.sku }}</td>
                  <td class="font-medium">{{ line.name }} <span class="text-xs text-base-content/60">({{ line.uom }})</span></td>
                  <td>{{ format(line.price) }}</td>
                  <td>
                    <div class="flex items-center gap-1 whitespace-nowrap">
                      <input v-model.number="line.qty" type="number" min="1" :max="line.availableStock" class="input input-bordered input-sm w-20" @change="sanitizeLineQty(line)" />
                      <span class="text-xs text-base-content/60">/ {{ line.availableStock }}</span>
                    </div>
                  </td>
                  <td><input v-model.number="line.discountPercent" type="number" min="0" max="100" class="input input-bordered input-sm w-24" /></td>
                  <td class="font-semibold text-primary">{{ format(lineSubtotal(line)) }}</td>
                  <td><button class="btn btn-ghost btn-xs text-error" @click="removeLine(line.productId)">Hapus</button></td>
                </tr>
                <tr v-if="cart.length === 0">
                  <td colspan="7" class="py-10 text-center text-base-content/50">Keranjang masih kosong. Klik "Tambah Produk" untuk mulai transaksi.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="ocn-panel">
          <div class="ocn-panel__head">
            <h2 class="ocn-panel__title">Ringkasan transaksi</h2>
            <p class="ocn-panel__desc">Total, pembayaran, dan tindakan kasir.</p>
          </div>
          <div class="card-body">
            <div class="space-y-2 text-sm">
              <div class="flex justify-between"><span>Gross Total</span><span>{{ format(grossTotal) }}</span></div>
              <div class="flex justify-between"><span>Total Diskon</span><span class="text-warning">- {{ format(discountTotal) }}</span></div>
              <div class="flex justify-between"><span>Biaya lainnya (ditagih)</span><span>{{ format(additionalFeeAddToTotal) }}</span></div>
              <div v-if="adminChannelFeeTotal > 0" class="flex justify-between text-xs text-base-content/65">
                <span>Biaya admin channel (hanya jurnal)</span>
                <span>{{ format(adminChannelFeeTotal) }}</span>
              </div>
              <div v-if="additionalCharges.length" class="rounded-lg bg-base-200/60 px-3 py-2 text-xs">
                <div v-for="charge in additionalCharges" :key="charge.id" class="flex justify-between gap-2">
                  <span class="truncate">{{ charge.name }} <span class="opacity-70">({{ (charge.kind ?? CHARGE_ADD) === CHARGE_ADMIN ? 'jurnal' : 'ke total' }})</span></span>
                  <span>{{ format(charge.amount) }}</span>
                </div>
              </div>
              <div class="divider my-1"></div>
              <div class="flex justify-between text-base font-bold"><span>Grand Total</span><span class="text-primary">{{ format(grandTotal) }}</span></div>
            </div>
            <div class="mt-4 space-y-3">
              <div>
                <label class="label py-1"><span class="label-text text-xs uppercase tracking-wide">Sales Channel</span></label>
                <select v-model="selectedSalesChannel" class="select select-bordered w-full">
                  <option v-for="channel in price_channels" :key="channel.key" :value="channel.key">{{ channel.label }}</option>
                </select>
              </div>
              <div v-if="isMarketplaceChannel">
                <label class="label py-1"><span class="label-text text-xs uppercase tracking-wide">Kode Pesanan</span></label>
                <input
                  v-model.trim="marketplaceOrderCode"
                  type="text"
                  class="input input-bordered w-full"
                  placeholder="Contoh: MP-20260516-001"
                  maxlength="100"
                />
                <p class="mt-1 text-[11px] text-base-content/55">Wajib untuk transaksi marketplace agar pesanan mudah dilacak.</p>
              </div>
              <div>
                <label class="label py-1"><span class="label-text text-xs uppercase tracking-wide">Metode Pembayaran</span></label>
                <select v-model="paymentMethodId" class="select select-bordered w-full">
                  <option v-for="method in payment_methods" :key="method.id" :value="method.id">{{ method.name }}</option>
                </select>
              </div>
              <div>
                <label class="label py-1"><span class="label-text text-xs uppercase tracking-wide">Biaya lainnya</span></label>
                <button class="btn btn-outline w-full justify-between" type="button" @click="openAdditionalChargeModal">
                  <span>{{ additionalCharges.length ? `${additionalCharges.length} biaya` : 'Tambah / atur biaya lainnya' }}</span>
                  <span>{{ format(additionalFeeAddToTotal) }}</span>
                </button>
                <p class="mt-1 text-[11px] text-base-content/55">Ongkir atau biaya lain menambah total bayar; biaya admin channel hanya tercatat di jurnal (tidak mengubah total bayar).</p>
              </div>
              <div>
                <label class="label py-1"><span class="label-text text-xs uppercase tracking-wide">Nominal Bayar</span></label>
                <input
                  ref="cashInputRef"
                  :value="cashPaidInput"
                  type="text"
                  class="input input-bordered w-full"
                  :disabled="!isCashPayment"
                  placeholder="Masukkan nominal bayar"
                  @input="onCashInput"
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
            <p v-if="checkoutError" class="mt-2 text-sm text-error">{{ checkoutError }}</p>
            <p v-if="hasStockViolation" class="mt-1 text-xs text-error">Ada qty melebihi stok tersedia. Perbaiki sebelum proses.</p>
            <button
              class="btn btn-primary mt-4 w-full border-0"
              :disabled="!canProcessPayment || processingPayment"
              @click="processPayment"
            >
              {{ processingPayment ? 'Memproses...' : 'Proses Pembayaran' }}
            </button>
            <p class="mt-2 text-[11px] text-base-content/55">Shortcut: F2 (produk), F4 (bayar), Ctrl+Enter (proses), Ctrl+K (scan), Esc (tutup modal)</p>
          </div>
        </div>
      </div>

    </div>

    <dialog id="modal-pos-receipt" class="modal">
      <div class="modal-box max-w-md">
        <h3 class="font-bold text-lg">Preview Struk</h3>
        <div class="mt-4 space-y-2 text-sm">
          <div class="flex justify-between"><span>No. Transaksi</span><span>{{ lastReceipt?.number || '-' }}</span></div>
          <div class="flex justify-between"><span>Total Item</span><span>{{ receiptLines.length }}</span></div>
          <div class="flex justify-between"><span>Sales Channel</span><span>{{ lastReceipt?.salesChannelLabel || selectedSalesChannelLabel }}</span></div>
          <div v-if="lastReceipt?.marketplaceOrderCode || marketplaceOrderCode" class="flex justify-between gap-3">
            <span>Kode Pesanan</span>
            <span class="font-mono text-right">{{ lastReceipt?.marketplaceOrderCode || marketplaceOrderCode }}</span>
          </div>
          <div class="flex justify-between"><span>Metode Bayar</span><span class="uppercase">{{ lastReceipt?.paymentMethodName || selectedPaymentMethod?.name || '-' }}</span></div>
          <div class="flex justify-between"><span>Biaya lainnya (ditagih)</span><span>{{ format(lastReceipt?.additionalFee ?? additionalFeeAddToTotal) }}</span></div>
          <div v-if="(lastReceipt?.adminChannelFee ?? 0) > 0 || adminChannelFeeTotal > 0" class="flex justify-between text-xs text-base-content/65">
            <span>Biaya admin channel (jurnal)</span>
            <span>{{ format(lastReceipt?.adminChannelFee ?? adminChannelFeeTotal) }}</span>
          </div>
          <div v-if="(lastReceipt?.additionalCharges?.length || additionalCharges.length)" class="rounded-lg bg-base-200/60 px-3 py-2 text-xs">
            <div
              v-for="charge in (lastReceipt?.additionalCharges?.length ? lastReceipt.additionalCharges : additionalCharges)"
              :key="charge.id"
              class="flex justify-between gap-2"
            >
              <span class="truncate">{{ charge.name }} <span class="opacity-70">({{ (charge.kind ?? CHARGE_ADD) === CHARGE_ADMIN ? 'jurnal' : 'ke total' }})</span></span>
              <span>{{ format(charge.amount) }}</span>
            </div>
          </div>
          <div class="flex justify-between"><span>Grand Total</span><span>{{ format(lastReceipt?.grandTotal ?? grandTotal) }}</span></div>
          <div class="flex justify-between"><span>Bayar</span><span>{{ format(lastReceipt?.cashPaid ?? cashPaidValue) }}</span></div>
          <div class="flex justify-between"><span>Kembalian</span><span>{{ format(lastReceipt?.change ?? changeAmount) }}</span></div>
          <div class="divider my-1"></div>
          <div v-for="line in receiptLines" :key="`receipt-full-${line.productId}`" class="flex justify-between gap-2">
            <span class="truncate">{{ line.name }} ({{ line.uom }}) x{{ line.qty }}</span>
            <span>{{ format(lineSubtotal(line)) }}</span>
          </div>
        </div>
        <p v-if="receiptPrintSuccess" class="mt-3 text-sm text-success">{{ receiptPrintSuccess }}</p>
        <p v-if="receiptPrintError" class="mt-3 text-sm text-error">{{ receiptPrintError }}</p>
        <div class="modal-action">
          <form method="dialog"><button class="btn btn-ghost">Tutup</button></form>
          <button v-if="canDirectPrintFromPreview" class="btn btn-primary" :disabled="printingReceipt" @click="printReceipt">{{ printingReceipt ? 'Mencetak...' : 'Print' }}</button>
        </div>
      </div>
    </dialog>

    <dialog id="modal-additional-charges" class="modal">
      <div class="modal-box max-w-lg">
        <h3 class="font-bold text-lg">Biaya lainnya</h3>
        <div class="mt-4 space-y-3">
          <div class="space-y-2 rounded-lg bg-base-200/50 p-3 text-sm">
            <label class="flex cursor-pointer items-start gap-2">
              <input v-model="chargeForm.kind" type="radio" class="radio radio-sm radio-primary mt-0.5" :value="CHARGE_ADD" />
              <span><span class="font-medium">Tambah ke total bayar</span> — misalnya ongkir atau layanan yang dibebankan ke pelanggan.</span>
            </label>
            <label class="flex cursor-pointer items-start gap-2">
              <input v-model="chargeForm.kind" type="radio" class="radio radio-sm radio-primary mt-0.5" :value="CHARGE_ADMIN" />
              <span><span class="font-medium">Biaya admin channel (jurnal saja)</span> — potongan channel; tidak mengubah total yang harus dibayar di POS.</span>
            </label>
          </div>
          <div class="grid gap-3 md:grid-cols-[1fr_180px_auto]">
            <input v-model="chargeForm.name" type="text" class="input input-bordered w-full" placeholder="Nama biaya, contoh: Ongkir / Komisi channel" />
            <input :value="chargeForm.amount" type="text" class="input input-bordered w-full" placeholder="Nominal" @input="onAdditionalChargeAmountInput" />
            <button class="btn btn-primary" type="button" @click="addAdditionalCharge">Tambah</button>
          </div>
          <div class="rounded-xl border border-base-300">
            <div v-if="additionalCharges.length" class="divide-y divide-base-300">
              <div v-for="charge in additionalCharges" :key="charge.id" class="flex items-center justify-between gap-3 px-4 py-3 text-sm">
                <div>
                  <p class="font-medium">{{ charge.name }}</p>
                  <p class="text-xs text-base-content/60">{{ format(charge.amount) }} · {{ (charge.kind ?? CHARGE_ADD) === CHARGE_ADMIN ? 'jurnal' : 'ke total' }}</p>
                </div>
                <button class="btn btn-ghost btn-xs text-error" type="button" @click="removeAdditionalCharge(charge.id)">Hapus</button>
              </div>
            </div>
            <div v-else class="px-4 py-6 text-center text-sm text-base-content/50">Belum ada biaya lainnya.</div>
          </div>
          <div class="space-y-1 text-sm">
            <div class="flex justify-between font-semibold">
              <span>Total ditagih ke pelanggan</span>
              <span>{{ format(additionalFeeAddToTotal) }}</span>
            </div>
            <div v-if="adminChannelFeeTotal > 0" class="flex justify-between text-xs text-base-content/65">
              <span>Total biaya admin (jurnal)</span>
              <span>{{ format(adminChannelFeeTotal) }}</span>
            </div>
          </div>
        </div>
        <div class="modal-action">
          <form method="dialog"><button class="btn btn-ghost">Tutup</button></form>
        </div>
      </div>
    </dialog>
  </div>

  <ProductPickerModal
    :show="showProductModal"
    :products="productCatalog"
    title="Pilih Produk untuk Ditambahkan"
    subtitle="Scan barcode / cari produk, pilih produk, lalu tambahkan ke keranjang."
    search-label="Scan Barcode / Cari SKU"
    search-placeholder="Contoh: PKG-SP-12X20"
    confirm-text="Tambah ke Keranjang"
    radio-name="selected_product_pos"
    @close="showProductModal = false"
    @confirm="addProductToCart"
  />

  <dialog ref="posAlertDialogEl" class="modal" @close="onPosAlertDialogClose">
    <div class="modal-box max-w-md">
      <h3 class="font-bold text-lg" :class="posAlertTitleClass">{{ posAlertTitle }}</h3>
      <p class="mt-3 text-sm leading-relaxed text-base-content/85">{{ posAlertMessage }}</p>
      <div class="modal-action mt-2">
        <button type="button" class="btn btn-primary" @click="closePosAlertModal">Mengerti</button>
      </div>
    </div>
    <form method="dialog" class="modal-backdrop">
      <button type="submit" aria-label="Tutup">close</button>
    </form>
  </dialog>
</template>
