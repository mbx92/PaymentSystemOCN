<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useCurrency } from '@/composables/useCurrency';

const props = defineProps({
  product: Object,
  uomMappings: Array,
  uoms: Array,
  categories: Array,
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
  sales_channel: 'pos',
  product_type: 'finished_goods',
  status: 'active',
  description: '',
  selling_price: 0,
  stock: 0,
  lead_time_days: 7,
});

const channelLabel = (value) => {
  if (value === 'pos') return 'POS';
  if (value === 'project') return 'PROJECT';
  if (value === 'both') return 'POS + PROJECT';
  return value;
};

const typeLabel = (value) => (value === 'project_material' ? 'Material Project' : 'Barang Jual');

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

const usedUomCodes = computed(() => new Set([props.product.uom, ...(props.uomMappings ?? []).map((m) => m.uom_code)]));
const availableUoms = computed(() => (props.uoms ?? []).filter((uom) => !usedUomCodes.value.has(uom.code)));
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

const openAddMappingModal = () => {
  mappingForm.reset('uom_code', 'multiplier', 'price_operation', 'selling_price', 'use_auto_price', 'status');
  mappingForm.multiplier = 1;
  mappingForm.price_operation = 'multiply';
  mappingForm.selling_price = Number(props.product.selling_price || 0);
  mappingForm.use_auto_price = true;
  mappingForm.status = 'active';
  if (availableUoms.value.length) {
    mappingForm.uom_code = availableUoms.value[0].code;
  }
  document.getElementById('modal-add-uom-mapping')?.showModal();
};

const submitMapping = () => {
  mappingForm.post(route('erp.master-products.uom-mappings.store', props.product.id), {
    preserveScroll: true,
    onSuccess: () => document.getElementById('modal-add-uom-mapping')?.close(),
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

const openEditProductModal = () => {
  productForm.reset();
  productForm.sku = props.product.sku ?? '';
  productForm.barcode = props.product.barcode ?? '';
  productForm.name = props.product.name ?? '';
  productForm.category = props.product.category ?? '';
  productForm.uom = props.product.uom ?? '';
  productForm.sales_channel = props.product.sales_channel ?? 'pos';
  productForm.product_type = props.product.product_type ?? 'finished_goods';
  productForm.status = props.product.status ?? 'active';
  productForm.description = props.product.description ?? '';
  productForm.selling_price = Number(props.product.selling_price ?? 0);
  productForm.stock = Number(props.product.stock ?? 0);
  productForm.lead_time_days = Number(props.product.lead_time_days ?? 7);
  document.getElementById('modal-edit-product')?.showModal();
};

const submitEditProduct = () => {
  productForm.patch(route('erp.master-products.update', props.product.id), {
    preserveScroll: true,
    onSuccess: () => document.getElementById('modal-edit-product')?.close(),
  });
};
</script>

<template>
  <Head :title="`Master Produk - ${product.name}`" />
  <AppLayout>
    <div class="space-y-5">
      <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Inventory Workspace</p>
        <div class="mt-2 flex items-center justify-between gap-3">
          <h1 class="text-3xl font-bold tracking-tight">Master Produk Detail</h1>
          <Link class="btn btn-ghost btn-sm" :href="route('erp.master-products.index')">Back</Link>
        </div>
        <p class="mt-2 text-sm text-base-content/70">Detail informasi produk master untuk kebutuhan POS dan material project.</p>
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
        <div class="card bg-base-100 shadow">
          <div class="card-body gap-4">
            <div>
              <p class="text-xs uppercase text-base-content/50">Identitas Produk</p>
              <p class="text-xl font-semibold leading-tight">{{ product.name }}</p>
              <p class="mt-1 text-sm text-base-content/60">SKU: <span class="font-mono">{{ product.sku }}</span></p>
            </div>

            <div class="grid gap-2 rounded-xl border border-base-300 p-3 text-sm sm:grid-cols-2">
              <div>
                <p class="text-xs uppercase text-base-content/50">Barcode</p>
                <p class="font-mono font-semibold">{{ product.barcode || '-' }}</p>
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

        <div class="card bg-base-100 shadow">
          <div class="card-body gap-4">
            <div class="flex items-start justify-between gap-3">
              <div>
                <p class="text-xs uppercase text-base-content/50">Komersial & Operasional</p>
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
                <p class="text-[11px] uppercase text-base-content/50">Lead Time</p>
                <p class="mt-1 font-semibold">{{ product.lead_time_days || 7 }} hari</p>
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

      <div class="card bg-base-100 shadow">
        <div class="card-body p-0">
          <div class="flex items-center justify-between border-b border-base-300 p-4">
            <h2 class="font-semibold">Mapping UoM Per Produk</h2>
            <button class="btn btn-primary btn-sm" @click="openAddMappingModal">Tambah Mapping</button>
          </div>
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

      <dialog id="modal-edit-product" class="modal">
        <div class="modal-box max-w-3xl">
          <h3 class="text-lg font-bold">Edit Master Produk</h3>
          <div class="mt-4 grid gap-3 md:grid-cols-2">
            <div>
              <label class="label"><span class="label-text">SKU</span></label>
              <input v-model="productForm.sku" type="text" class="input input-bordered w-full" />
              <p v-if="productForm.errors.sku" class="text-xs text-error mt-1">{{ productForm.errors.sku }}</p>
            </div>
            <div>
              <label class="label"><span class="label-text">Barcode</span></label>
              <input v-model="productForm.barcode" type="text" class="input input-bordered w-full" />
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
              </select>
            </div>
            <div>
              <label class="label"><span class="label-text">Harga Jual</span></label>
              <input v-model.number="productForm.selling_price" type="number" min="0" class="input input-bordered w-full" />
              <p v-if="productForm.errors.selling_price" class="text-xs text-error mt-1">{{ productForm.errors.selling_price }}</p>
            </div>
            <div>
              <label class="label"><span class="label-text">Stok</span></label>
              <input v-model.number="productForm.stock" type="number" min="0" class="input input-bordered w-full" />
              <p v-if="productForm.errors.stock" class="text-xs text-error mt-1">{{ productForm.errors.stock }}</p>
            </div>
            <div>
              <label class="label"><span class="label-text">Lead Time (hari)</span></label>
              <input v-model.number="productForm.lead_time_days" type="number" min="1" max="365" class="input input-bordered w-full" />
              <p v-if="productForm.errors.lead_time_days" class="text-xs text-error mt-1">{{ productForm.errors.lead_time_days }}</p>
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
