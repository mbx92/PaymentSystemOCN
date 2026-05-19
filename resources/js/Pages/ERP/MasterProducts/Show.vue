<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { computed, ref, watch } from 'vue';
import { useCurrency } from '@/composables/useCurrency';
import { BoltIcon } from '@heroicons/vue/20/solid';

const props = defineProps({
  product: Object,
  /** @type {{ available: boolean, hint: string|null }} */
  barcodePrint: {
    type: Object,
    default: () => ({ available: false, hint: null }),
  },
  uomMappings: Array,
  channelPrices: Array,
  priceChannels: Array,
  uoms: Array,
  categories: Array,
  warehouses: Array,
});

const { format } = useCurrency();

const confirmDeleteProduct = () => {
  document.getElementById('modal-delete-product')?.showModal();
};

const deleteProduct = () => {
  router.delete(route('erp.master-products.destroy', props.product.id));
};

const productForm = useForm({
  sku: '',
  barcode: '',
  name: '',
  category: '',
  uom: '',
  warehouse_id: '',
  sales_channel: 'pos',
  product_type: 'finished_goods',
  status: 'active',
  description: '',
  selling_price: 0,
  stock: 0,
});

const channelLabel = (value) => {
  if (value === 'pos') return 'POS';
  if (value === 'project') return 'PROJECT';
  if (value === 'both') return 'POS + PROJECT';
  return value;
};

const typeLabel = (value) => {
  if (value === 'project_material') return 'Material Project';
  if (value === 'service') return 'Jasa / Non Stok';
  return 'Barang Jual';
};

watch(() => productForm.product_type, (type) => {
  if (type === 'service') {
    productForm.stock = 0;
  }
});

const mappingForm = useForm({
  uom_code: '',
  multiplier: 1,
  price_operation: 'multiply',
  selling_price: 0,
  use_auto_price: true,
  status: 'active',
});
const editMappingForm = useForm({
  multiplier: 1,
  price_operation: 'multiply',
  selling_price: 0,
  use_auto_price: true,
  status: 'active',
});
const currentEditingMappingId = ref(null);
const editingMapping = computed(() => (props.uomMappings ?? []).find((item) => item.id === currentEditingMappingId.value) ?? null);
const channelPriceForm = useForm({
  sales_channel: 'retail',
  selling_price: 0,
  status: 'active',
});
const editChannelPriceForm = useForm({
  selling_price: 0,
  status: 'active',
});
const currentEditingChannelPriceId = ref(null);
const editingChannelPrice = computed(() => (props.channelPrices ?? []).find((item) => item.id === currentEditingChannelPriceId.value) ?? null);

const usedUomCodes = computed(() => new Set([props.product.uom, ...(props.uomMappings ?? []).map((m) => m.uom_code)]));
const availableUoms = computed(() => (props.uoms ?? []).filter((uom) => !usedUomCodes.value.has(uom.code)));
const usedPriceChannels = computed(() => new Set((props.channelPrices ?? []).map((price) => price.sales_channel)));
const availablePriceChannels = computed(() => (props.priceChannels ?? []).filter((channel) => !usedPriceChannels.value.has(channel.key)));
const totalVariants = computed(() => 1 + (props.uomMappings?.length || 0));
const basePrice = computed(() => Number(props.product?.selling_price || 0));
const addPreviewPrice = computed(() => {
  if (mappingForm.use_auto_price) {
    if (mappingForm.price_operation === 'divide') {
      return basePrice.value / Math.max(Number(mappingForm.multiplier || 0), 0.0001);
    }
    return basePrice.value * Number(mappingForm.multiplier || 0);
  }
  return Number(mappingForm.selling_price || 0);
});
const editPreviewPrice = computed(() => {
  if (editMappingForm.use_auto_price) {
    if (editMappingForm.price_operation === 'divide') {
      return basePrice.value / Math.max(Number(editMappingForm.multiplier || 0), 0.0001);
    }
    return basePrice.value * Number(editMappingForm.multiplier || 0);
  }
  return Number(editMappingForm.selling_price || 0);
});

const resetAddMappingForm = () => {
  mappingForm.reset('uom_code', 'multiplier', 'price_operation', 'selling_price', 'use_auto_price', 'status');
  mappingForm.multiplier = 1;
  mappingForm.price_operation = 'multiply';
  mappingForm.selling_price = Number(props.product.selling_price || 0);
  mappingForm.use_auto_price = true;
  mappingForm.status = 'active';
  if (availableUoms.value.length) {
    mappingForm.uom_code = availableUoms.value[0].code;
  }
};

const openAddMappingModal = () => {
  resetAddMappingForm();
  document.getElementById('modal-add-uom-mapping')?.showModal();
};

const submitMapping = () => {
  mappingForm.post(route('erp.master-products.uom-mappings.store', props.product.id), {
    preserveScroll: true,
    onSuccess: () => {
      resetAddMappingForm();
      document.getElementById('modal-add-uom-mapping')?.close();
    },
  });
};

const openEditMappingModal = (mapping) => {
  currentEditingMappingId.value = mapping.id;
  editMappingForm.reset('multiplier', 'price_operation', 'selling_price', 'use_auto_price', 'status');
  editMappingForm.multiplier = Number(mapping.multiplier);
  editMappingForm.price_operation = mapping.price_operation || 'multiply';
  editMappingForm.selling_price = Number(mapping.selling_price);
  editMappingForm.use_auto_price = Boolean(mapping.use_auto_price);
  editMappingForm.status = mapping.status;
  document.getElementById('modal-edit-uom-mapping')?.showModal();
};

const submitEditMapping = () => {
  if (!currentEditingMappingId.value) return;
  editMappingForm.patch(route('erp.master-products.uom-mappings.update', {
    masterProduct: props.product.id,
    mapping: currentEditingMappingId.value,
  }), {
    preserveScroll: true,
    onSuccess: () => document.getElementById('modal-edit-uom-mapping')?.close(),
  });
};

const removeMapping = (mappingId) => {
  router.delete(route('erp.master-products.uom-mappings.destroy', {
    masterProduct: props.product.id,
    mapping: mappingId,
  }), {
    preserveScroll: true,
  });
};

const resetAddChannelPriceForm = () => {
  channelPriceForm.reset('sales_channel', 'selling_price', 'status');
  channelPriceForm.sales_channel = availablePriceChannels.value[0]?.key ?? props.priceChannels?.[0]?.key ?? 'retail';
  channelPriceForm.selling_price = Number(props.product.selling_price || 0);
  channelPriceForm.status = 'active';
};

const openAddChannelPriceModal = () => {
  resetAddChannelPriceForm();
  document.getElementById('modal-add-channel-price')?.showModal();
};

const submitChannelPrice = () => {
  channelPriceForm.post(route('erp.master-products.channel-prices.store', props.product.id), {
    preserveScroll: true,
    onSuccess: () => {
      resetAddChannelPriceForm();
      document.getElementById('modal-add-channel-price')?.close();
    },
  });
};

const openEditChannelPriceModal = (channelPrice) => {
  currentEditingChannelPriceId.value = channelPrice.id;
  editChannelPriceForm.reset('selling_price', 'status');
  editChannelPriceForm.selling_price = Number(channelPrice.selling_price || 0);
  editChannelPriceForm.status = channelPrice.status || 'active';
  document.getElementById('modal-edit-channel-price')?.showModal();
};

const submitEditChannelPrice = () => {
  if (!currentEditingChannelPriceId.value) return;
  editChannelPriceForm.patch(route('erp.master-products.channel-prices.update', {
    masterProduct: props.product.id,
    channelPrice: currentEditingChannelPriceId.value,
  }), {
    preserveScroll: true,
    onSuccess: () => document.getElementById('modal-edit-channel-price')?.close(),
  });
};

const removeChannelPrice = (channelPriceId) => {
  router.delete(route('erp.master-products.channel-prices.destroy', {
    masterProduct: props.product.id,
    channelPrice: channelPriceId,
  }), {
    preserveScroll: true,
  });
};

const openEditProductModal = () => {
  productForm.reset();
  productForm.sku = props.product.sku ?? '';
  productForm.barcode = props.product.barcode ?? '';
  productForm.name = props.product.name ?? '';
  productForm.category = props.product.category ?? '';
  productForm.uom = props.product.uom ?? '';
  productForm.warehouse_id = props.product.warehouse_id ?? '';
  productForm.sales_channel = props.product.sales_channel ?? 'pos';
  productForm.product_type = props.product.product_type ?? 'finished_goods';
  productForm.status = props.product.status ?? 'active';
  productForm.description = props.product.description ?? '';
  productForm.selling_price = Number(props.product.selling_price ?? 0);
  productForm.stock = Number(props.product.stock ?? 0);
  document.getElementById('modal-edit-product')?.showModal();
};

const submitEditProduct = () => {
  productForm.patch(route('erp.master-products.update', props.product.id), {
    preserveScroll: true,
    onSuccess: () => document.getElementById('modal-edit-product')?.close(),
  });
};

const printBarcodeForm = useForm({
  copies: 1,
});

const hasBarcodeOrSku = computed(() => String(props.product?.barcode || props.product?.sku || '').trim().length > 0);

const openPrintBarcodeModal = () => {
  if (!hasBarcodeOrSku.value) return;
  printBarcodeForm.clearErrors();
  printBarcodeForm.copies = 1;
  document.getElementById('modal-print-barcode')?.showModal();
};

const submitPrintBarcode = () => {
  printBarcodeForm.post(route('erp.master-products.print-barcode', props.product.id), {
    preserveScroll: true,
    onSuccess: () => document.getElementById('modal-print-barcode')?.close(),
  });
};

const setBarcodeCopies = (n) => {
  printBarcodeForm.copies = Math.min(999, Math.max(1, Number(n) || 1));
};

const generatingEditCodes = ref(false);

const regenerateEditSku = async () => {
  generatingEditCodes.value = true;
  try {
    const cat = productForm.category || props.product.category;
    const res = await fetch(route('erp.master-products.preview-codes') + '?category=' + encodeURIComponent(cat), {
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    });
    if (res.ok) {
      const data = await res.json();
      if (data.sku) productForm.sku = data.sku;
    }
  } catch { /* ignore */ } finally {
    generatingEditCodes.value = false;
  }
};

const regenerateEditBarcode = async () => {
  generatingEditCodes.value = true;
  try {
    const res = await fetch(route('erp.master-products.preview-codes') + '?category=', {
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    });
    if (res.ok) {
      const data = await res.json();
      if (data.barcode) productForm.barcode = data.barcode;
    }
  } catch { /* ignore */ } finally {
    generatingEditCodes.value = false;
  }
};
</script>

<template>
  <Head :title="`Master Produk - ${product.name}`" />
  <AppLayout>
    <div class="space-y-5">
      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Inventory Workspace</p>
              <h1 class="ocn-panel__title mt-1">Master Produk Detail</h1>
              <p class="ocn-panel__desc mt-1">Detail informasi produk master untuk kebutuhan POS dan material project.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
              <Link class="btn btn-ghost btn-sm shrink-0 gap-1.5" :href="route('erp.master-products.index')">
              <ArrowLeftIcon class="h-4 w-4" />
              Back
            </Link>
            </div>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article class="rounded-2xl border border-slate-700/40 bg-gradient-to-br from-slate-800 to-slate-950 p-5 text-white shadow-xl">
          <p class="text-xs font-semibold uppercase tracking-wide text-white/55">Harga Dasar</p>
          <p class="mt-3 text-xl font-bold">{{ format(product.selling_price) }}</p>
        </article>
        <article class="rounded-2xl border border-blue-900/50 bg-gradient-to-br from-blue-900 to-blue-950 p-5 text-white shadow-xl">
          <p class="text-xs font-semibold uppercase tracking-wide text-blue-100/70">Stok</p>
          <p class="mt-3 text-xl font-bold">{{ product.stock }} {{ product.uom }}</p>
        </article>
        <article class="rounded-2xl border border-indigo-800/50 bg-gradient-to-br from-indigo-800 to-violet-950 p-5 text-white shadow-xl">
          <p class="text-xs font-semibold uppercase tracking-wide text-indigo-100/70">Varian UoM</p>
          <p class="mt-3 text-xl font-bold">{{ totalVariants }}</p>
        </article>
        <article class="rounded-2xl border border-emerald-900/50 bg-gradient-to-br from-emerald-900 to-emerald-950 p-5 text-white shadow-xl">
          <p class="text-xs font-semibold uppercase tracking-wide text-emerald-100/70">Status Produk</p>
          <div class="mt-3"><StatusBadge :status="product.status" /></div>
        </article>
      </div>

      <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <div class="ocn-panel">
          <div class="ocn-panel__head">
            <h2 class="ocn-panel__title">Identitas produk</h2>
          </div>
          <div class="card-body gap-4">
            <div>
              <p class="text-xs uppercase text-base-content/50">Nama &amp; SKU</p>
              <p class="text-xl font-semibold leading-tight">{{ product.name }}</p>
              <p class="mt-1 text-sm text-base-content/60">SKU: <span class="font-mono">{{ product.sku }}</span></p>
            </div>

            <div class="grid gap-2 rounded-xl border border-base-300 p-3 text-sm sm:grid-cols-2">
              <div class="sm:col-span-2 flex flex-wrap items-end justify-between gap-3 border-b border-base-200 pb-3">
                <div class="min-w-0 flex-1">
                  <p class="text-xs uppercase text-base-content/50">Barcode</p>
                  <p class="font-mono font-semibold">{{ product.barcode || '-' }}</p>
                  <p v-if="product.barcode" class="mt-1 text-xs text-base-content/50">Data barcode untuk cetak label.</p>
                  <p v-else class="mt-1 text-xs text-amber-700/90">Kosong — cetak label memakai <span class="font-mono">SKU</span> sebagai isi barcode.</p>
                </div>
                <button
                  type="button"
                  class="btn btn-outline btn-sm shrink-0"
                  :disabled="!hasBarcodeOrSku"
                  :title="!hasBarcodeOrSku ? 'Isi barcode atau SKU dulu' : ''"
                  @click="openPrintBarcodeModal"
                >
                  Cetak barcode
                </button>
              </div>
              <div>
                <p class="text-xs uppercase text-base-content/50">Kategori</p>
                <p class="font-semibold">{{ product.category }}</p>
              </div>
              <div>
                <p class="text-xs uppercase text-base-content/50">Sales Channel</p>
                <span class="badge badge-info badge-sm mt-1">{{ channelLabel(product.sales_channel) }}</span>
              </div>
              <div>
                <p class="text-xs uppercase text-base-content/50">Warehouse Asal</p>
                <p class="font-semibold">{{ product.warehouse?.code ? `${product.warehouse.code} - ${product.warehouse.name}` : '-' }}</p>
              </div>
              <div>
                <p class="text-xs uppercase text-base-content/50">Tipe Produk</p>
                <span class="badge badge-ghost badge-sm mt-1">{{ typeLabel(product.product_type) }}</span>
              </div>
            </div>

            <div class="rounded-xl border border-base-300 p-3">
              <p class="text-xs uppercase text-base-content/50">Deskripsi</p>
              <p class="mt-1 text-sm leading-relaxed">{{ product.description || '-' }}</p>
            </div>
          </div>
        </div>

        <div class="ocn-panel">
          <div class="ocn-panel__head">
            <h2 class="ocn-panel__title">Komersial &amp; operasional</h2>
          </div>
          <div class="card-body gap-4">
            <div class="flex items-start justify-between gap-3">
              <div>
                <p class="text-xs uppercase text-base-content/50">Ringkasan</p>
                <p class="text-sm text-base-content/70">Data transaksi utama untuk penjualan dan stok.</p>
              </div>
              <StatusBadge :status="product.status" />
            </div>

            <div class="grid grid-cols-2 gap-2">
              <div class="rounded-lg border border-base-300 bg-base-200/40 p-3">
                <p class="text-[11px] uppercase text-base-content/50">Harga Jual</p>
                <p class="mt-1 font-semibold">{{ format(product.selling_price) }}</p>
              </div>
              <div class="rounded-lg border border-base-300 bg-base-200/40 p-3">
                <p class="text-[11px] uppercase text-base-content/50">Stok Saat Ini</p>
                <p class="mt-1 font-semibold">{{ product.stock }} {{ product.uom }}</p>
              </div>
              <div class="rounded-lg border border-base-300 bg-base-200/40 p-3">
                <p class="text-[11px] uppercase text-base-content/50">UoM Dasar</p>
                <p class="mt-1 font-semibold">{{ product.uom }}</p>
              </div>
            </div>

            <div class="flex flex-wrap gap-2 pt-1">
              <button class="btn btn-secondary btn-sm" @click="openEditProductModal">Edit Product</button>
              <button class="btn btn-outline btn-sm" @click="openAddMappingModal">+ Mapping UoM</button>
              <button class="btn btn-error btn-sm" @click="confirmDeleteProduct">Delete Product</button>
            </div>
          </div>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head flex flex-wrap items-center justify-between gap-2">
          <div>
            <h2 class="ocn-panel__title">Multi harga sales channel</h2>
            <p class="ocn-panel__desc">Harga POS berdasarkan channel transaksi kasir.</p>
          </div>
          <button class="btn btn-primary btn-sm shrink-0" :disabled="availablePriceChannels.length === 0" @click="openAddChannelPriceModal">Tambah harga</button>
        </div>
        <div class="card-body p-0">
          <div class="overflow-x-auto">
            <table class="table table-sm">
              <thead><tr><th>Sales Channel</th><th>Harga Jual</th><th>Status</th><th></th></tr></thead>
              <tbody>
                <tr v-for="price in channelPrices" :key="price.id">
                  <td class="font-semibold">{{ price.label }}</td>
                  <td>{{ format(price.selling_price) }}</td>
                  <td><StatusBadge :status="price.status" /></td>
                  <td class="text-right">
                    <button class="btn btn-ghost btn-xs" @click="openEditChannelPriceModal(price)">Edit</button>
                    <button class="btn btn-ghost btn-xs text-error" @click="removeChannelPrice(price.id)">Hapus</button>
                  </td>
                </tr>
                <tr v-if="!channelPrices?.length">
                  <td colspan="4" class="py-6 text-center text-base-content/50">Belum ada multi harga. POS memakai harga dasar produk.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head flex flex-wrap items-center justify-between gap-2">
          <h2 class="ocn-panel__title">Mapping UoM per produk</h2>
          <button class="btn btn-primary btn-sm shrink-0" @click="openAddMappingModal">Tambah mapping</button>
        </div>
        <div class="card-body p-0">
          <div class="overflow-x-auto">
            <table class="table table-sm">
              <thead><tr><th>UoM</th><th>Multiplier</th><th>Rumus</th><th>Harga Jual</th><th>Status</th><th></th></tr></thead>
              <tbody>
                <tr>
                  <td class="font-semibold">{{ product.uom }} <span class="text-xs text-base-content/60">(dasar)</span></td>
                  <td>1</td>
                  <td>-</td>
                  <td>{{ format(product.selling_price) }}</td>
                  <td><StatusBadge :status="product.status" /></td>
                  <td></td>
                </tr>
                <tr v-for="mapping in uomMappings" :key="mapping.id">
                  <td class="font-semibold">{{ mapping.uom_code }}</td>
                  <td>{{ mapping.multiplier }}</td>
                  <td>{{ mapping.price_operation === 'divide' ? 'Harga dasar / multiplier' : 'Harga dasar x multiplier' }}</td>
                  <td>{{ format(mapping.selling_price) }}</td>
                  <td><StatusBadge :status="mapping.status" /></td>
                  <td class="text-right">
                    <button class="btn btn-ghost btn-xs" @click="openEditMappingModal(mapping)">Edit</button>
                    <button class="btn btn-ghost btn-xs text-error" @click="removeMapping(mapping.id)">Hapus</button>
                  </td>
                </tr>
                <tr v-if="!uomMappings?.length">
                  <td colspan="6" class="py-6 text-center text-base-content/50">Belum ada mapping UoM tambahan.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <ConfirmModal
        id="modal-delete-product"
        title="Hapus Produk"
        :message="'Yakin hapus produk &quot;' + product.name + '&quot;?'"
        confirm-text="Hapus"
        @confirm="deleteProduct"
      />

      <dialog id="modal-add-channel-price" class="modal">
        <div class="modal-box max-w-lg">
          <h3 class="font-bold text-lg">Tambah Harga Sales Channel</h3>
          <div class="mt-4 space-y-3">
            <div>
              <label class="label"><span class="label-text">Sales Channel</span></label>
              <select v-model="channelPriceForm.sales_channel" class="select select-bordered w-full">
                <option value="" disabled>Pilih sales channel</option>
                <option v-for="channel in availablePriceChannels" :key="channel.key" :value="channel.key">{{ channel.label }}</option>
              </select>
              <p v-if="channelPriceForm.errors.sales_channel" class="text-error text-xs mt-1">{{ channelPriceForm.errors.sales_channel }}</p>
            </div>
            <div>
              <label class="label"><span class="label-text">Harga Jual</span></label>
              <input v-model.number="channelPriceForm.selling_price" type="number" min="0" step="100" class="input input-bordered w-full" />
              <p v-if="channelPriceForm.errors.selling_price" class="text-error text-xs mt-1">{{ channelPriceForm.errors.selling_price }}</p>
            </div>
            <div>
              <label class="label cursor-pointer justify-start gap-3 mt-1">
                <input
                  :checked="channelPriceForm.status === 'active'"
                  type="checkbox"
                  class="toggle toggle-success"
                  @change="channelPriceForm.status = $event.target.checked ? 'active' : 'inactive'"
                />
                <span class="label-text">{{ channelPriceForm.status === 'active' ? 'active' : 'inactive' }}</span>
              </label>
              <p v-if="channelPriceForm.errors.status" class="text-error text-xs mt-1">{{ channelPriceForm.errors.status }}</p>
            </div>
          </div>
          <div class="modal-action">
            <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
            <button class="btn btn-primary" :disabled="channelPriceForm.processing || !availablePriceChannels.length" @click="submitChannelPrice">Simpan Harga</button>
          </div>
        </div>
      </dialog>

      <dialog id="modal-edit-channel-price" class="modal">
        <div class="modal-box max-w-lg">
          <h3 class="font-bold text-lg">Edit Harga Sales Channel</h3>
          <div class="mt-4 space-y-3">
            <div class="rounded-xl border border-base-300 bg-base-200/50 p-3 text-sm">
              Sales Channel: <span class="font-semibold">{{ editingChannelPrice?.label || '-' }}</span>
            </div>
            <div>
              <label class="label"><span class="label-text">Harga Jual</span></label>
              <input v-model.number="editChannelPriceForm.selling_price" type="number" min="0" step="100" class="input input-bordered w-full" />
              <p v-if="editChannelPriceForm.errors.selling_price" class="text-error text-xs mt-1">{{ editChannelPriceForm.errors.selling_price }}</p>
            </div>
            <div>
              <label class="label cursor-pointer justify-start gap-3 mt-1">
                <input
                  :checked="editChannelPriceForm.status === 'active'"
                  type="checkbox"
                  class="toggle toggle-success"
                  @change="editChannelPriceForm.status = $event.target.checked ? 'active' : 'inactive'"
                />
                <span class="label-text">{{ editChannelPriceForm.status === 'active' ? 'active' : 'inactive' }}</span>
              </label>
              <p v-if="editChannelPriceForm.errors.status" class="text-error text-xs mt-1">{{ editChannelPriceForm.errors.status }}</p>
            </div>
          </div>
          <div class="modal-action">
            <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
            <button class="btn btn-primary" :disabled="editChannelPriceForm.processing" @click="submitEditChannelPrice">Update Harga</button>
          </div>
        </div>
      </dialog>

      <dialog id="modal-add-uom-mapping" class="modal">
        <div class="modal-box max-w-xl">
          <h3 class="font-bold text-lg">Tambah Mapping UoM Produk</h3>
          <div class="mt-4 space-y-3">
            <div>
              <label class="label"><span class="label-text">UoM Target</span></label>
              <select v-model="mappingForm.uom_code" class="select select-bordered w-full">
                <option value="" disabled>Pilih UoM</option>
                <option v-for="uom in availableUoms" :key="uom.code" :value="uom.code">{{ uom.code }} - {{ uom.name }}</option>
              </select>
              <p v-if="mappingForm.errors.uom_code" class="text-error text-xs mt-1">{{ mappingForm.errors.uom_code }}</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
              <div>
                <label class="label"><span class="label-text">Multiplier</span></label>
                <input v-model.number="mappingForm.multiplier" type="number" min="0.0001" step="0.0001" class="input input-bordered w-full" />
                <p class="text-xs text-base-content/60 mt-1">Contoh: 1 pack = 100 pcs -> multiplier 100.</p>
                <p v-if="mappingForm.errors.multiplier" class="text-error text-xs mt-1">{{ mappingForm.errors.multiplier }}</p>
              </div>
              <div>
                <label class="label"><span class="label-text">Operasi Konversi Harga</span></label>
                <select v-model="mappingForm.price_operation" class="select select-bordered w-full">
                  <option value="multiply">Kali (harga dasar x multiplier)</option>
                  <option value="divide">Bagi (harga dasar / multiplier)</option>
                </select>
                <p class="mt-1 text-xs text-base-content/60">
                  Hint: jika dasar <span class="font-semibold">{{ product.uom }}</span> dan 1 {{ product.uom }} = 100 pcs, pilih
                  <span class="font-semibold">Bagi</span> + multiplier 100 agar harga pcs = harga dasar / 100.
                </p>
              </div>
              <div v-if="!mappingForm.use_auto_price">
                <label class="label"><span class="label-text">Harga Jual (opsional)</span></label>
                <input v-model.number="mappingForm.selling_price" type="number" min="0" step="100" class="input input-bordered w-full" />
                <p v-if="mappingForm.errors.selling_price" class="text-error text-xs mt-1">{{ mappingForm.errors.selling_price }}</p>
              </div>
            </div>
            <div class="rounded-xl border border-primary/30 bg-primary/5 p-3">
              <p class="text-xs uppercase tracking-wide text-base-content/60">Preview Harga Jual UoM Ini</p>
              <p class="text-lg font-semibold text-primary">{{ format(addPreviewPrice) }}</p>
            </div>
            <div>
              <label class="label cursor-pointer justify-start gap-3">
                <input
                  :checked="mappingForm.use_auto_price"
                  type="checkbox"
                  class="toggle toggle-primary"
                  @change="mappingForm.use_auto_price = $event.target.checked"
                />
                <span class="label-text">Harga otomatis (harga dasar x multiplier)</span>
              </label>
            </div>
            <div>
              <label class="label cursor-pointer justify-start gap-3 mt-1">
                <input
                  :checked="mappingForm.status === 'active'"
                  type="checkbox"
                  class="toggle toggle-success"
                  @change="mappingForm.status = $event.target.checked ? 'active' : 'inactive'"
                />
                <span class="label-text">{{ mappingForm.status === 'active' ? 'active' : 'inactive' }}</span>
              </label>
              <p v-if="mappingForm.errors.status" class="text-error text-xs mt-1">{{ mappingForm.errors.status }}</p>
            </div>
          </div>
          <div class="modal-action">
            <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
            <button class="btn btn-primary" :disabled="mappingForm.processing || !availableUoms.length" @click="submitMapping">Simpan Mapping</button>
          </div>
        </div>
      </dialog>

      <dialog id="modal-edit-uom-mapping" class="modal">
        <div class="modal-box max-w-xl">
          <h3 class="font-bold text-lg">Edit Mapping UoM Produk</h3>
          <div class="mt-4 space-y-3">
            <div class="rounded-xl border border-base-300 bg-base-200/50 p-3 text-sm">
              UoM: <span class="font-semibold">{{ editingMapping?.uom_code || '-' }}</span>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
              <div>
                <label class="label"><span class="label-text">Multiplier</span></label>
                <input v-model.number="editMappingForm.multiplier" type="number" min="0.0001" step="0.0001" class="input input-bordered w-full" />
                <p v-if="editMappingForm.errors.multiplier" class="text-error text-xs mt-1">{{ editMappingForm.errors.multiplier }}</p>
              </div>
              <div>
                <label class="label"><span class="label-text">Operasi Konversi Harga</span></label>
                <select v-model="editMappingForm.price_operation" class="select select-bordered w-full">
                  <option value="multiply">Kali (harga dasar x multiplier)</option>
                  <option value="divide">Bagi (harga dasar / multiplier)</option>
                </select>
                <p class="mt-1 text-xs text-base-content/60">
                  Hint: gunakan <span class="font-semibold">Kali</span> untuk unit lebih besar, dan
                  <span class="font-semibold">Bagi</span> untuk unit lebih kecil dari satuan dasar.
                </p>
              </div>
              <div v-if="!editMappingForm.use_auto_price">
                <label class="label"><span class="label-text">Harga Jual</span></label>
                <input v-model.number="editMappingForm.selling_price" type="number" min="0" step="100" class="input input-bordered w-full" />
                <p v-if="editMappingForm.errors.selling_price" class="text-error text-xs mt-1">{{ editMappingForm.errors.selling_price }}</p>
              </div>
            </div>
            <div class="rounded-xl border border-primary/30 bg-primary/5 p-3">
              <p class="text-xs uppercase tracking-wide text-base-content/60">Preview Harga Jual UoM Ini</p>
              <p class="text-lg font-semibold text-primary">{{ format(editPreviewPrice) }}</p>
            </div>
            <div>
              <label class="label cursor-pointer justify-start gap-3">
                <input
                  :checked="editMappingForm.use_auto_price"
                  type="checkbox"
                  class="toggle toggle-primary"
                  @change="editMappingForm.use_auto_price = $event.target.checked"
                />
                <span class="label-text">Harga otomatis (harga dasar x multiplier)</span>
              </label>
            </div>
            <div>
              <label class="label cursor-pointer justify-start gap-3 mt-1">
                <input
                  :checked="editMappingForm.status === 'active'"
                  type="checkbox"
                  class="toggle toggle-success"
                  @change="editMappingForm.status = $event.target.checked ? 'active' : 'inactive'"
                />
                <span class="label-text">{{ editMappingForm.status === 'active' ? 'active' : 'inactive' }}</span>
              </label>
              <p v-if="editMappingForm.errors.status" class="text-error text-xs mt-1">{{ editMappingForm.errors.status }}</p>
            </div>
          </div>
          <div class="modal-action">
            <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
            <button class="btn btn-primary" :disabled="editMappingForm.processing" @click="submitEditMapping">Update Mapping</button>
          </div>
        </div>
      </dialog>

      <dialog id="modal-print-barcode" class="modal">
        <div class="modal-box max-w-sm overflow-hidden rounded-2xl p-0 shadow-xl">
          <div class="border-b border-base-200 bg-gradient-to-br from-primary/[0.08] to-base-100 px-5 pb-4 pt-5">
            <div class="flex items-start gap-3">
              <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/15 text-primary">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                  <path d="M4 5h2v14H4V5zm4 0h1v14H8V5zm3 0h4v14h-4V5zm6 0h1v14h-1V5zm3 0h3v14h-3V5z" />
                </svg>
              </div>
              <div class="min-w-0 flex-1">
                <h3 class="text-base font-bold leading-tight">Cetak barcode</h3>
                <p class="mt-1 line-clamp-2 text-xs leading-snug text-base-content/65" :title="product.name">{{ product.name }}</p>
              </div>
            </div>
          </div>

          <div class="px-5 py-4">
            <div v-if="!barcodePrint?.available" role="alert" class="alert alert-warning border-amber-200/80 bg-amber-50 text-amber-950 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-100">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 stroke-current" fill="none" viewBox="0 0 24 24" stroke-width="1.5" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
              </svg>
              <span class="text-xs leading-relaxed">{{ barcodePrint?.hint || 'Cetak label tidak tersedia.' }}</span>
            </div>

            <div v-else class="rounded-xl border border-base-200 bg-base-200/40 p-4">
              <div class="flex items-center justify-between gap-2 text-xs">
                <span class="font-medium uppercase tracking-wide text-base-content/50">Isi barcode</span>
                <span class="truncate font-mono text-sm font-semibold text-base-content" :title="product.barcode || product.sku">{{ product.barcode || product.sku }}</span>
              </div>
              <div class="divider my-3 before:h-px after:h-px" />
              <div class="form-control">
                <label for="print-barcode-copies" class="label cursor-default px-0 py-0 pb-1 pt-0">
                  <span class="label-text text-xs font-medium text-base-content/70">Jumlah label</span>
                </label>
                <div class="flex flex-wrap items-center gap-2">
                  <input
                    id="print-barcode-copies"
                    v-model.number="printBarcodeForm.copies"
                    type="number"
                    min="1"
                    max="999"
                    class="input input-bordered input-sm w-[5.5rem] text-center font-mono text-sm tabular-nums"
                  >
                  <div class="flex flex-wrap gap-1">
                    <button type="button" class="btn btn-xs border border-base-300 bg-base-100 px-2 font-mono" @click="setBarcodeCopies(1)">1</button>
                    <button type="button" class="btn btn-xs border border-base-300 bg-base-100 px-2 font-mono" @click="setBarcodeCopies(5)">5</button>
                    <button type="button" class="btn btn-xs border border-base-300 bg-base-100 px-2 font-mono" @click="setBarcodeCopies(10)">10</button>
                    <button type="button" class="btn btn-xs border border-base-300 bg-base-100 px-2 font-mono" @click="setBarcodeCopies(25)">25</button>
                  </div>
                </div>
                <p v-if="printBarcodeForm.errors.copies" class="mt-2 text-xs text-error">{{ printBarcodeForm.errors.copies }}</p>
              </div>
            </div>
          </div>

          <div class="modal-action mt-0 border-t border-base-200 bg-base-200/25 px-5 py-3">
            <form method="dialog">
              <button class="btn btn-ghost btn-sm">Tutup</button>
            </form>
            <button
              type="button"
              class="btn btn-primary btn-sm min-w-[5.5rem]"
              :disabled="!barcodePrint?.available || printBarcodeForm.processing"
              @click="submitPrintBarcode"
            >
              {{ printBarcodeForm.processing ? 'Mengirim…' : 'Cetak' }}
            </button>
          </div>
        </div>
      </dialog>

      <dialog id="modal-edit-product" class="modal">
        <div class="modal-box max-w-3xl">
          <h3 class="text-lg font-bold">Edit Master Produk</h3>
          <div class="mt-4 grid gap-3 md:grid-cols-2">
            <div>
              <label class="label"><span class="label-text">SKU</span></label>
              <div class="flex items-center gap-1.5">
                <input v-model="productForm.sku" type="text" class="input input-bordered w-full font-mono" />
                <button
                  type="button"
                  class="btn btn-square btn-sm btn-ghost border border-base-300 shrink-0"
                  title="Generate SKU baru dari kategori"
                  :disabled="generatingEditCodes"
                  @click="regenerateEditSku"
                >
                  <BoltIcon class="h-4 w-4" />
                </button>
              </div>
              <p v-if="productForm.errors.sku" class="text-xs text-error mt-1">{{ productForm.errors.sku }}</p>
            </div>
            <div>
              <label class="label"><span class="label-text">Barcode</span></label>
              <div class="flex items-center gap-1.5">
                <input v-model="productForm.barcode" type="text" class="input input-bordered w-full font-mono" />
                <button
                  type="button"
                  class="btn btn-square btn-sm btn-ghost border border-base-300 shrink-0"
                  title="Generate barcode EAN-13 baru"
                  :disabled="generatingEditCodes"
                  @click="regenerateEditBarcode"
                >
                  <BoltIcon class="h-4 w-4" />
                </button>
              </div>
              <p v-if="productForm.errors.barcode" class="text-xs text-error mt-1">{{ productForm.errors.barcode }}</p>
            </div>
            <div>
              <label class="label"><span class="label-text">Nama Produk</span></label>
              <input v-model="productForm.name" type="text" class="input input-bordered w-full" />
              <p v-if="productForm.errors.name" class="text-xs text-error mt-1">{{ productForm.errors.name }}</p>
            </div>
            <div>
              <label class="label"><span class="label-text">Kategori</span></label>
              <select v-model="productForm.category" class="select select-bordered w-full">
                <option value="" disabled>Pilih kategori</option>
                <option v-for="category in categories" :key="category.name" :value="category.name">{{ category.name }}</option>
              </select>
              <p v-if="productForm.errors.category" class="text-xs text-error mt-1">{{ productForm.errors.category }}</p>
            </div>
            <div>
              <label class="label"><span class="label-text">UoM Dasar</span></label>
              <select v-model="productForm.uom" class="select select-bordered w-full">
                <option value="" disabled>Pilih UoM</option>
                <option v-for="uom in uoms" :key="uom.code" :value="uom.code">{{ uom.code }} - {{ uom.name }}</option>
              </select>
              <p v-if="productForm.errors.uom" class="text-xs text-error mt-1">{{ productForm.errors.uom }}</p>
            </div>
            <div>
              <label class="label"><span class="label-text">Warehouse Asal</span></label>
              <select v-model="productForm.warehouse_id" class="select select-bordered w-full">
                <option value="">Pilih warehouse asal</option>
                <option v-for="warehouse in warehouses" :key="warehouse.id" :value="warehouse.id">{{ warehouse.code }} - {{ warehouse.name }}</option>
              </select>
              <p v-if="productForm.errors.warehouse_id" class="text-xs text-error mt-1">{{ productForm.errors.warehouse_id }}</p>
            </div>
            <div>
              <label class="label"><span class="label-text">Sales Channel</span></label>
              <select v-model="productForm.sales_channel" class="select select-bordered w-full">
                <option value="pos">POS</option>
                <option value="project">PROJECT</option>
                <option value="both">POS + PROJECT</option>
              </select>
            </div>
            <div>
              <label class="label"><span class="label-text">Tipe Produk</span></label>
              <select v-model="productForm.product_type" class="select select-bordered w-full">
                <option value="finished_goods">Barang Jual</option>
                <option value="project_material">Material Project</option>
                <option value="service">Jasa / Non Stok</option>
              </select>
            </div>
            <div>
              <label class="label"><span class="label-text">Harga Jual</span></label>
              <input v-model.number="productForm.selling_price" type="number" min="0" class="input input-bordered w-full" />
              <p v-if="productForm.errors.selling_price" class="text-xs text-error mt-1">{{ productForm.errors.selling_price }}</p>
            </div>
            <div>
              <label class="label"><span class="label-text">Stok</span></label>
              <input v-model.number="productForm.stock" type="number" min="0" class="input input-bordered w-full" :disabled="productForm.product_type === 'service'" />
              <p v-if="productForm.errors.stock" class="text-xs text-error mt-1">{{ productForm.errors.stock }}</p>
            </div>
            <div class="md:col-span-2">
              <label class="label"><span class="label-text">Deskripsi</span></label>
              <textarea v-model="productForm.description" class="textarea textarea-bordered w-full" rows="3"></textarea>
              <p v-if="productForm.errors.description" class="text-xs text-error mt-1">{{ productForm.errors.description }}</p>
            </div>
            <div class="md:col-span-2">
              <label class="label cursor-pointer justify-start gap-3 mt-1">
                <input
                  :checked="productForm.status === 'active'"
                  type="checkbox"
                  class="toggle toggle-success"
                  @change="productForm.status = $event.target.checked ? 'active' : 'inactive'"
                />
                <span class="label-text">{{ productForm.status === 'active' ? 'active' : 'inactive' }}</span>
              </label>
            </div>
          </div>
          <div class="modal-action">
            <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
            <button class="btn btn-primary" :disabled="productForm.processing" @click="submitEditProduct">Simpan Perubahan</button>
          </div>
        </div>
      </dialog>
    </div>
  </AppLayout>
</template>
