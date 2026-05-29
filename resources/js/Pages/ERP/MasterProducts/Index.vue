<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { computed, reactive, ref, watch } from 'vue';
import { useCurrency } from '@/composables/useCurrency';
import { BoltIcon } from '@heroicons/vue/20/solid';

const props = defineProps({
  products: Object,
  filters: Object,
  categories: Array,
  uoms: Array,
  warehouses: Array,
});

const perPageOptions = [25, 50, 75, 100, 125, 150, 175, 200, 225, 250];

const productRows = computed(() => (Array.isArray(props.products?.data) ? props.products.data : []));

const paginationSummary = computed(() => {
  const p = props.products;
  if (!p?.total) {
    return 'Tidak ada produk';
  }
  if (p.from != null && p.to != null) {
    return `Menampilkan ${p.from}–${p.to} dari ${p.total}`;
  }
  return `Total ${p.total} produk`;
});
const { parse, formatInput } = useCurrency();

const filters = reactive({
  q: props.filters?.q ?? '',
  sales_channel: props.filters?.sales_channel ?? '',
  product_type: props.filters?.product_type ?? '',
  warehouse_id: props.filters?.warehouse_id ?? '',
  per_page: props.filters?.per_page ?? props.products?.per_page ?? 25,
});

const form = useForm({
  sku: '',
  barcode: '',
  name: '',
  category: '',
  uom: 'pcs',
  warehouse_id: '',
  sales_channel: 'pos',
  product_type: 'finished_goods',
  status: 'active',
  description: '',
  selling_price: 0,
  stock: 0,
});

const autoSku = ref(true);
const autoBarcode = ref(true);
const previewSku = ref('');
const previewBarcode = ref('');
const loadingPreview = ref(false);

const sellingPriceInput = reactive({
  value: '',
});

let timer;
watch(
  filters,
  (val) => {
    clearTimeout(timer);
    timer = setTimeout(() => {
      router.get(route('erp.master-products.index'), val, { preserveState: true, replace: true });
    }, 250);
  },
  { deep: true },
);

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

watch(() => form.product_type, (type) => {
  if (type === 'service') {
    form.stock = 0;
  }
});

const fetchPreviewCodes = async () => {
  loadingPreview.value = true;
  try {
    const res = await fetch(route('erp.master-products.preview-codes') + '?category=' + encodeURIComponent(form.category), {
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    });
    if (res.ok) {
      const data = await res.json();
      previewSku.value = data.sku || '';
      previewBarcode.value = data.barcode || '';
      if (autoSku.value) form.sku = previewSku.value;
      if (autoBarcode.value) form.barcode = previewBarcode.value;
    }
  } catch { /* ignore */ } finally {
    loadingPreview.value = false;
  }
};

watch(() => form.category, (cat) => {
  if (cat && (autoSku.value || autoBarcode.value)) fetchPreviewCodes();
});

const toggleAutoSku = () => {
  autoSku.value = !autoSku.value;
  if (autoSku.value && previewSku.value) form.sku = previewSku.value;
  else if (autoSku.value && form.category) fetchPreviewCodes();
  if (!autoSku.value) form.sku = '';
};

const toggleAutoBarcode = () => {
  autoBarcode.value = !autoBarcode.value;
  if (autoBarcode.value && previewBarcode.value) form.barcode = previewBarcode.value;
  else if (autoBarcode.value) fetchPreviewCodes();
  if (!autoBarcode.value) form.barcode = '';
};

const submitAddProduct = () => {
  form.selling_price = parse(sellingPriceInput.value);
  form.post(route('erp.master-products.store'), {
    preserveScroll: true,
    onSuccess: () => {
      form.reset();
      sellingPriceInput.value = '';
      autoSku.value = true;
      autoBarcode.value = true;
      previewSku.value = '';
      previewBarcode.value = '';
      document.getElementById('modal-add-product').close();
    },
  });
};

const goToDetail = (id) => {
  router.visit(route('erp.master-products.show', id));
};
</script>

<template>
  <Head title="ERP - Master Produk" />
  <AppLayout>
    <div class="space-y-3">
      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Product Governance</p>
              <h1 class="ocn-panel__title mt-1">Master Produk</h1>
              <p class="ocn-panel__desc mt-1">Satu master produk untuk membedakan barang yang dijual di POS dan material untuk project CCTV/jaringan.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
              <Link class="btn btn-ghost btn-sm shrink-0 gap-1.5" :href="route('erp.inventory')">
              <ArrowLeftIcon class="h-4 w-4" />
              Back
            </Link>
            </div>
          </div>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Filter produk</h2>
        </div>
        <div class="card-body py-3">
          <div class="flex flex-wrap items-center gap-2">
            <input v-model="filters.q" type="text" placeholder="Cari SKU / nama produk" class="input input-bordered input-xs w-56" />
            <select v-model="filters.sales_channel" class="select select-bordered select-xs w-36">
            <option value="">Semua Channel</option>
            <option value="pos">POS</option>
            <option value="project">Project</option>
            <option value="both">POS + Project</option>
            </select>
            <select v-model="filters.product_type" class="select select-bordered select-xs w-40">
            <option value="">Semua Tipe</option>
            <option value="finished_goods">Barang Jual</option>
            <option value="project_material">Material Project</option>
            <option value="service">Jasa / Non Stok</option>
            </select>
            <select v-model="filters.warehouse_id" class="select select-bordered select-xs w-40">
            <option value="">Semua Warehouse</option>
            <option v-for="wh in warehouses" :key="wh.id" :value="wh.id">{{ wh.code }} — {{ wh.name }}</option>
            </select>
            <div class="ml-auto flex items-center gap-2">
              <button class="btn btn-primary btn-xs" onclick="document.getElementById('modal-add-product').showModal()">
                + Add Product
              </button>
            </div>
          </div>
        </div>
      </div>

      <div class="rounded-2xl border border-base-200 bg-base-100 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-base-200 px-4 py-2">
          <div class="flex items-center gap-2">
            <label class="flex items-center gap-1.5 text-xs text-base-content/70">
              <span>Per halaman</span>
              <select v-model.number="filters.per_page" class="select select-bordered select-xs w-20">
                <option v-for="n in perPageOptions" :key="n" :value="n">{{ n }}</option>
              </select>
            </label>
            <p class="text-xs text-base-content/50">{{ paginationSummary }}</p>
          </div>
        </div>
        <div class="overflow-x-auto">
          <table class="table table-xs table-zebra">
            <thead>
              <tr>
                <th>SKU</th>
                <th>Barcode</th>
                <th>Produk</th>
                <th>Kategori</th>
                <th>UoM</th>
                <th>Warehouse</th>
                <th>Channel</th>
                <th>Tipe</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="product in productRows"
                :key="product.id"
                class="cursor-pointer hover"
                @click="goToDetail(product.id)"
              >
                <td class="font-mono">{{ product.sku }}</td>
                <td class="font-mono text-base-content/60">{{ product.barcode || '-' }}</td>
                <td class="font-medium">{{ product.name }}</td>
                <td class="text-base-content/70">{{ product.category }}</td>
                <td class="uppercase text-base-content/70">{{ product.uom }}</td>
                <td class="text-xs text-base-content/60">{{ product.warehouse?.code ?? '-' }}</td>
                <td>
                  <span class="badge badge-xs badge-info">{{ channelLabel(product.sales_channel) }}</span>
                </td>
                <td>
                  <span class="badge badge-xs badge-ghost">{{ typeLabel(product.product_type) }}</span>
                </td>
                <td><StatusBadge :status="product.status" /></td>
              </tr>
              <tr v-if="productRows.length === 0">
                <td colspan="9" class="py-8 text-center text-xs text-base-content/50">Tidak ada produk sesuai filter.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="(products?.last_page ?? 1) > 1" class="flex flex-wrap justify-center gap-1 border-t border-base-200 px-4 py-2.5">
          <template v-for="link in products.links" :key="link.label">
            <Link
              v-if="link.url"
              :href="link.url"
              preserve-scroll
              class="btn btn-xs min-w-7 px-1.5"
              :class="link.active ? 'btn-primary' : 'btn-ghost'"
            >
              <span v-html="link.label" />
            </Link>
            <span
              v-else
              class="btn btn-xs btn-disabled pointer-events-none min-w-7 px-1.5"
              v-html="link.label"
            />
          </template>
        </div>
      </div>
    </div>

    <dialog id="modal-add-product" class="modal">
      <div class="modal-box max-w-2xl">
        <h3 class="font-bold text-lg">Add Product</h3>
        <div class="mt-4 grid gap-3 md:grid-cols-2">
          <div>
            <label class="label"><span class="label-text">SKU</span></label>
            <div class="flex items-center gap-1.5">
              <input
                v-model="form.sku"
                type="text"
                class="input input-bordered w-full font-mono"
                :class="autoSku ? 'bg-base-200 text-base-content/60' : ''"
                :readonly="autoSku"
                :placeholder="autoSku ? 'Otomatis dari kategori' : 'Masukkan SKU manual'"
              />
              <button
                type="button"
                class="btn btn-square btn-sm shrink-0"
                :class="autoSku ? 'btn-primary' : 'btn-ghost border border-base-300'"
                title="Toggle SKU otomatis"
                @click="toggleAutoSku"
              >
                <BoltIcon class="h-4 w-4" />
              </button>
            </div>
            <p v-if="autoSku" class="mt-1 text-xs text-primary/70">SKU otomatis berdasarkan kategori</p>
            <p v-if="form.errors.sku" class="mt-1 text-xs text-error">{{ form.errors.sku }}</p>
          </div>
          <div>
            <label class="label"><span class="label-text">Barcode</span></label>
            <div class="flex items-center gap-1.5">
              <input
                v-model="form.barcode"
                type="text"
                class="input input-bordered w-full font-mono"
                :class="autoBarcode ? 'bg-base-200 text-base-content/60' : ''"
                :readonly="autoBarcode"
                :placeholder="autoBarcode ? 'Otomatis EAN-13' : 'Masukkan barcode manual'"
              />
              <button
                type="button"
                class="btn btn-square btn-sm shrink-0"
                :class="autoBarcode ? 'btn-primary' : 'btn-ghost border border-base-300'"
                title="Toggle barcode otomatis"
                @click="toggleAutoBarcode"
              >
                <BoltIcon class="h-4 w-4" />
              </button>
            </div>
            <p v-if="autoBarcode" class="mt-1 text-xs text-primary/70">Barcode EAN-13 otomatis</p>
            <p v-if="form.errors.barcode" class="mt-1 text-xs text-error">{{ form.errors.barcode }}</p>
          </div>
          <div>
            <label class="label"><span class="label-text">Nama Produk <span class="text-error">*</span></span></label>
            <input v-model="form.name" type="text" class="input input-bordered w-full" />
          </div>
          <div>
            <label class="label"><span class="label-text">Kategori <span class="text-error">*</span></span></label>
            <select v-model="form.category" class="select select-bordered w-full">
              <option value="" disabled>Pilih kategori</option>
              <option v-for="category in categories" :key="category.name" :value="category.name">{{ category.name }}</option>
            </select>
          </div>
          <div>
            <label class="label"><span class="label-text">UoM <span class="text-error">*</span></span></label>
            <select v-model="form.uom" class="select select-bordered w-full">
              <option value="" disabled>Pilih UoM</option>
              <option v-for="uom in uoms" :key="uom.code" :value="uom.code">{{ uom.code }} - {{ uom.name }}</option>
            </select>
          </div>
          <div>
            <label class="label"><span class="label-text">Warehouse Asal</span></label>
            <select v-model="form.warehouse_id" class="select select-bordered w-full">
              <option value="">Pilih warehouse asal</option>
              <option v-for="warehouse in warehouses" :key="warehouse.id" :value="warehouse.id">{{ warehouse.code }} - {{ warehouse.name }}</option>
            </select>
            <p v-if="form.errors.warehouse_id" class="mt-1 text-xs text-error">{{ form.errors.warehouse_id }}</p>
          </div>
          <div>
            <label class="label"><span class="label-text">Sales Channel <span class="text-error">*</span></span></label>
            <select v-model="form.sales_channel" class="select select-bordered w-full">
              <option value="pos">POS</option>
              <option value="project">Project</option>
              <option value="both">POS + PROJECT</option>
            </select>
          </div>
          <div>
            <label class="label"><span class="label-text">Tipe Produk <span class="text-error">*</span></span></label>
            <select v-model="form.product_type" class="select select-bordered w-full">
              <option value="finished_goods">Barang Jual</option>
              <option value="project_material">Material Project</option>
              <option value="service">Jasa / Non Stok</option>
            </select>
          </div>
          <div>
            <label class="label"><span class="label-text">Harga Jual <span class="text-error">*</span></span></label>
            <input
              :value="sellingPriceInput.value"
              type="text"
              class="input input-bordered w-full"
              placeholder="Contoh: 15.000"
              @input="sellingPriceInput.value = formatInput($event.target.value)"
            />
          </div>
          <div>
            <label class="label"><span class="label-text">Stock <span class="text-error">*</span></span></label>
            <input v-model.number="form.stock" type="number" min="0" class="input input-bordered w-full" :disabled="form.product_type === 'service'" />
          </div>
          <div class="md:col-span-2">
            <label class="label"><span class="label-text">Deskripsi</span></label>
            <textarea v-model="form.description" class="textarea textarea-bordered w-full"></textarea>
          </div>
        </div>
        <div class="modal-action">
          <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
          <button class="btn btn-primary" :disabled="form.processing" @click="submitAddProduct">Simpan</button>
        </div>
      </div>
    </dialog>
  </AppLayout>
</template>
