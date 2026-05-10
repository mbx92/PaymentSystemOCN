<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, reactive, watch } from 'vue';
import { useCurrency } from '@/composables/useCurrency';

const props = defineProps({
  products: Array,
  filters: Object,
  categories: Array,
  uoms: Array,
});
const { parse, formatInput } = useCurrency();
const page = usePage();
const isAdmin = computed(() => page.props.auth?.user?.role === 'admin');

const filters = reactive({
  q: props.filters?.q ?? '',
  sales_channel: props.filters?.sales_channel ?? '',
  product_type: props.filters?.product_type ?? '',
});

const form = useForm({
  sku: '',
  barcode: '',
  name: '',
  category: '',
  uom: 'pcs',
  sales_channel: 'pos',
  product_type: 'finished_goods',
  status: 'active',
  description: '',
  selling_price: 0,
  stock: 0,
  lead_time_days: 7,
});

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

const typeLabel = (value) => (value === 'project_material' ? 'Material Project' : 'Barang Jual');

const submitAddProduct = () => {
  form.selling_price = parse(sellingPriceInput.value);
  form.post(route('erp.master-products.store'), {
    preserveScroll: true,
    onSuccess: () => {
      form.reset();
      sellingPriceInput.value = '';
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
    <div class="space-y-6">
      <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Product Governance</p>
        <div class="mt-2 flex items-center justify-between gap-3">
          <h1 class="text-3xl font-bold tracking-tight">Master Produk</h1>
          <Link class="btn btn-ghost btn-sm" :href="route('erp.inventory')">Back</Link>
        </div>
        <p class="mt-2 text-sm text-base-content/70">
          Satu master produk untuk membedakan barang yang dijual di POS dan material untuk project CCTV/jaringan.
        </p>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Filter produk</h2>
        </div>
        <div class="card-body">
          <div class="overflow-x-auto">
            <div class="flex min-w-max items-center gap-3">
              <input v-model="filters.q" type="text" placeholder="Cari SKU / nama produk" class="input input-bordered input-sm w-80" />
              <select v-model="filters.sales_channel" class="select select-bordered select-sm w-44">
              <option value="">Semua Channel</option>
              <option value="pos">POS</option>
              <option value="project">Project</option>
              <option value="both">POS + Project</option>
              </select>
              <select v-model="filters.product_type" class="select select-bordered select-sm w-48">
              <option value="">Semua Tipe</option>
              <option value="finished_goods">Barang Jual</option>
              <option value="project_material">Material Project</option>
              </select>
              <div class="ml-auto flex flex-wrap items-center gap-2">
                <Link
                  v-if="isAdmin"
                  :href="route('erp.admin.data-import', { tab: 'products' })"
                  class="btn btn-outline btn-sm whitespace-nowrap"
                >
                  Impor Excel
                </Link>
                <button class="btn btn-primary btn-sm whitespace-nowrap" onclick="document.getElementById('modal-add-product').showModal()">
                  + Add Product
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Daftar master produk</h2>
        </div>
        <div class="overflow-x-auto">
          <table class="table table-zebra">
            <thead>
              <tr>
                <th>SKU</th>
                <th>Barcode</th>
                <th>Produk</th>
                <th>Kategori</th>
                <th>UoM</th>
                <th>Channel</th>
                <th>Tipe</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="product in products"
                :key="product.id"
                class="cursor-pointer hover"
                @click="goToDetail(product.id)"
              >
                <td class="font-mono text-xs">{{ product.sku }}</td>
                <td class="font-mono text-xs">{{ product.barcode || '-' }}</td>
                <td class="font-semibold">{{ product.name }}</td>
                <td>{{ product.category }}</td>
                <td class="uppercase">{{ product.uom }}</td>
                <td>
                  <span class="badge badge-sm badge-info">{{ channelLabel(product.sales_channel) }}</span>
                </td>
                <td>
                  <span class="badge badge-sm badge-ghost">{{ typeLabel(product.product_type) }}</span>
                </td>
                <td><StatusBadge :status="product.status" /></td>
              </tr>
              <tr v-if="products.length === 0">
                <td colspan="8" class="py-10 text-center text-base-content/50">Tidak ada produk sesuai filter.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <dialog id="modal-add-product" class="modal">
      <div class="modal-box max-w-2xl">
        <h3 class="font-bold text-lg">Add Product</h3>
        <div class="mt-4 grid gap-3 md:grid-cols-2">
          <div>
            <label class="label"><span class="label-text">SKU <span class="text-error">*</span></span></label>
            <input v-model="form.sku" type="text" class="input input-bordered w-full" />
          </div>
          <div>
            <label class="label"><span class="label-text">Barcode</span></label>
            <input v-model="form.barcode" type="text" class="input input-bordered w-full" placeholder="Opsional tapi disarankan untuk POS scan" />
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
            <input v-model.number="form.stock" type="number" min="0" class="input input-bordered w-full" />
          </div>
          <div>
            <label class="label"><span class="label-text">Lead Time (hari) <span class="text-error">*</span></span></label>
            <input v-model.number="form.lead_time_days" type="number" min="1" class="input input-bordered w-full" />
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
