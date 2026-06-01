<script setup>
import ProductPickerModal from '@/Components/ProductPickerModal.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useCurrency } from '@/composables/useCurrency';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
  products: { type: Array, default: () => [] },
  warehouses: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
  defaultStockOpnameDate: { type: String, default: '' },
  postingPreview: { type: Object, default: () => ({}) },
});

const { format } = useCurrency();
const showProductModal = ref(false);

const form = useForm({
  warehouse_id: props.filters.warehouse_id ?? '',
  product_id: '',
  physical_stock: 0,
  stock_opname_date: props.defaultStockOpnameDate || new Date().toISOString().slice(0, 10),
  note: '',
});

const selectedProduct = computed(() => (
  props.products.find((product) => String(product.id) === String(form.product_id)) ?? null
));
const currentWarehouseStock = computed(() => Number(selectedProduct.value?.warehouse_stock ?? 0));
const stockDiff = computed(() => Number(form.physical_stock ?? 0) - currentWarehouseStock.value);
const absoluteStockDiff = computed(() => Math.abs(stockDiff.value));
const stockOpnameDirection = computed(() => {
  if (stockDiff.value > 0) return 'increase';
  if (stockDiff.value < 0) return 'decrease';
  return 'same';
});
const unitCost = computed(() => Number(selectedProduct.value?.unit_cost ?? 0));
const adjustmentAmount = computed(() => absoluteStockDiff.value * unitCost.value);
const inventoryAccountLabel = computed(() => props.postingPreview?.inventory_account_label ?? 'Akun persediaan belum diatur');
const adjustmentAccountLabel = computed(() => props.postingPreview?.adjustment_account_label ?? 'Akun selisih opname belum diatur');
const previewRows = computed(() => {
  if (!selectedProduct.value || stockOpnameDirection.value === 'same' || adjustmentAmount.value <= 0) {
    return [];
  }

  if (stockOpnameDirection.value === 'increase') {
    return [
      { side: 'Debit', account: inventoryAccountLabel.value, amount: adjustmentAmount.value },
      { side: 'Kredit', account: adjustmentAccountLabel.value, amount: adjustmentAmount.value },
    ];
  }

  return [
    { side: 'Debit', account: adjustmentAccountLabel.value, amount: adjustmentAmount.value },
    { side: 'Kredit', account: inventoryAccountLabel.value, amount: adjustmentAmount.value },
  ];
});

const pickerProducts = computed(() => props.products.map((product) => ({
  ...product,
  stock: product.warehouse_stock,
})));

function syncWarehouseProducts() {
  router.get(route('erp.inventory.stock-opname'), {
    warehouse_id: form.warehouse_id || undefined,
  }, {
    preserveScroll: true,
    replace: true,
    onSuccess: () => {
      form.product_id = '';
      form.physical_stock = 0;
    },
  });
}

watch(() => form.product_id, () => {
  if (!selectedProduct.value) {
    form.physical_stock = 0;
    return;
  }

  form.physical_stock = Number(selectedProduct.value.warehouse_stock ?? 0);
});

const submit = () => {
  form.post(route('erp.inventory.stock-opname.store'), {
    preserveScroll: true,
    onSuccess: () => {
      form.reset('product_id', 'physical_stock', 'note');
      form.stock_opname_date = props.defaultStockOpnameDate || new Date().toISOString().slice(0, 10);
      form.warehouse_id = props.filters.warehouse_id ?? form.warehouse_id;
    },
  });
};

function openProductModal() {
  if (!form.warehouse_id) {
    return;
  }

  showProductModal.value = true;
}

function selectProduct(product) {
  form.product_id = product.id;
  form.physical_stock = Number(product.warehouse_stock ?? 0);
  showProductModal.value = false;
}
</script>

<template>
  <Head title="Inventory - Stok Opname" />
  <AppLayout>
    <div class="space-y-5">
      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Inventory Workspace</p>
              <h1 class="ocn-panel__title mt-1">Stok Opname</h1>
              <p class="ocn-panel__desc mt-1">Sesuaikan stok fisik per gudang dan simpan tanggal opname untuk histori pergerakan stok.</p>
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
          <h2 class="ocn-panel__title">Penyesuaian stok opname</h2>
        </div>
        <div class="card-body grid gap-3 md:grid-cols-2">
          <div>
            <label class="label"><span class="label-text">Gudang Opname</span></label>
            <select v-model="form.warehouse_id" class="select select-bordered w-full" @change="syncWarehouseProducts">
              <option value="">Pilih gudang</option>
              <option v-for="warehouse in warehouses" :key="warehouse.id" :value="warehouse.id">
                {{ warehouse.code }} - {{ warehouse.name }}
              </option>
            </select>
            <p v-if="form.errors.warehouse_id" class="mt-1 text-xs text-error">{{ form.errors.warehouse_id }}</p>
          </div>
          <div>
            <label class="label"><span class="label-text">Tanggal Stock Opname</span></label>
            <input v-model="form.stock_opname_date" type="date" class="input input-bordered w-full" />
            <p v-if="form.errors.stock_opname_date" class="mt-1 text-xs text-error">{{ form.errors.stock_opname_date }}</p>
          </div>

          <div class="md:col-span-2">
            <label class="label"><span class="label-text">Produk</span></label>
            <div class="flex flex-col gap-2 sm:flex-row">
              <button
                type="button"
                class="btn btn-outline sm:w-auto"
                :disabled="!form.warehouse_id"
                @click="openProductModal"
              >
                Pilih Item
              </button>
              <div class="flex-1 rounded-xl border border-base-300 bg-base-200/40 px-4 py-3 text-sm">
                <template v-if="selectedProduct">
                  <div class="font-medium">{{ selectedProduct.sku }} - {{ selectedProduct.name }}</div>
                  <div class="text-base-content/60">
                    Stok gudang: {{ selectedProduct.warehouse_stock }} {{ selectedProduct.uom }}
                  </div>
                </template>
                <template v-else>
                  <div class="text-base-content/60">Belum ada item dipilih.</div>
                </template>
              </div>
            </div>
            <p v-if="form.errors.product_id" class="mt-1 text-xs text-error">{{ form.errors.product_id }}</p>
          </div>

          <div v-if="selectedProduct" class="rounded-xl border border-base-300 bg-base-200/50 p-4 md:col-span-2">
            <div class="grid gap-3 text-sm md:grid-cols-3">
              <div>
                <div class="text-base-content/60">Stok gudang saat ini</div>
                <div class="text-lg font-semibold">{{ selectedProduct.warehouse_stock }} {{ selectedProduct.uom }}</div>
              </div>
              <div>
                <div class="text-base-content/60">Reserved</div>
                <div class="text-lg font-semibold">{{ selectedProduct.reserved_qty }} {{ selectedProduct.uom }}</div>
              </div>
              <div>
                <div class="text-base-content/60">Total semua gudang</div>
                <div class="text-lg font-semibold">{{ selectedProduct.stock }} {{ selectedProduct.uom }}</div>
              </div>
            </div>
            <div class="mt-4 grid gap-3 border-t border-base-300/80 pt-4 text-sm md:grid-cols-3">
              <div>
                <div class="text-base-content/60">Selisih opname</div>
                <div class="text-lg font-semibold" :class="stockOpnameDirection === 'increase' ? 'text-success' : stockOpnameDirection === 'decrease' ? 'text-error' : ''">
                  {{ stockDiff > 0 ? '+' : '' }}{{ stockDiff }} {{ selectedProduct.uom }}
                </div>
              </div>
              <div>
                <div class="text-base-content/60">Unit cost</div>
                <div class="text-lg font-semibold">{{ format(unitCost) }}</div>
              </div>
              <div>
                <div class="text-base-content/60">Estimasi nilai jurnal</div>
                <div class="text-lg font-semibold text-primary">{{ format(adjustmentAmount) }}</div>
              </div>
            </div>
          </div>

          <div v-if="selectedProduct" class="rounded-xl border border-primary/20 bg-primary/5 p-4 md:col-span-2">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
              <div>
                <p class="text-sm font-semibold">Preview jurnal stock opname</p>
                <p class="mt-1 text-xs text-base-content/65">Estimasi ini mengikuti selisih qty dan `unit_cost` produk saat ini.</p>
              </div>
              <span
                class="badge"
                :class="stockOpnameDirection === 'increase' ? 'badge-success' : stockOpnameDirection === 'decrease' ? 'badge-error' : 'badge-ghost'"
              >
                {{
                  stockOpnameDirection === 'increase'
                    ? 'Opname Masuk'
                    : stockOpnameDirection === 'decrease'
                      ? 'Opname Keluar'
                      : 'Tanpa Selisih'
                }}
              </span>
            </div>

            <div v-if="stockOpnameDirection === 'same'" class="mt-4 rounded-lg border border-base-300 bg-base-100 px-4 py-3 text-sm text-base-content/70">
              Stok fisik sama dengan stok gudang saat ini, jadi tidak ada jurnal penyesuaian yang akan dibuat.
            </div>
            <div v-else-if="unitCost <= 0" class="mt-4 rounded-lg border border-warning/40 bg-warning/10 px-4 py-3 text-sm text-warning-content">
              Produk ini masih memiliki `unit_cost` 0, jadi stock opname yang mengubah stok akan ditolak sampai nilai unit cost diisi.
            </div>
            <div v-else class="mt-4 overflow-hidden rounded-lg border border-base-300 bg-base-100">
              <div class="grid grid-cols-[120px_minmax(0,1fr)_160px] gap-3 border-b border-base-300 bg-base-200/70 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-base-content/70">
                <div>Sisi</div>
                <div>Akun</div>
                <div class="text-right">Jumlah</div>
              </div>
              <div
                v-for="row in previewRows"
                :key="`${row.side}-${row.account}`"
                class="grid grid-cols-[120px_minmax(0,1fr)_160px] gap-3 px-4 py-3 text-sm"
              >
                <div class="font-semibold" :class="row.side === 'Debit' ? 'text-success' : 'text-error'">{{ row.side }}</div>
                <div class="truncate">{{ row.account }}</div>
                <div class="text-right font-semibold">{{ format(row.amount) }}</div>
              </div>
            </div>
          </div>

          <div>
            <label class="label"><span class="label-text">Stok Fisik</span></label>
            <input v-model.number="form.physical_stock" type="number" min="0" step="0.01" class="input input-bordered w-full" />
            <p v-if="form.errors.physical_stock" class="mt-1 text-xs text-error">{{ form.errors.physical_stock }}</p>
          </div>
          <div>
            <label class="label"><span class="label-text">Catatan</span></label>
            <input v-model="form.note" type="text" class="input input-bordered w-full" placeholder="Opsional" />
            <p v-if="form.errors.note" class="mt-1 text-xs text-error">{{ form.errors.note }}</p>
          </div>

          <div class="md:col-span-2 flex justify-end">
            <button class="btn btn-primary" :disabled="form.processing || !form.warehouse_id || !form.product_id" @click="submit">
              Simpan Opname
            </button>
          </div>
        </div>
      </div>
    </div>

    <ProductPickerModal
      :show="showProductModal"
      :products="pickerProducts"
      title="Pilih Item Stock Opname"
      subtitle="Cari dan pilih produk yang terdaftar pada gudang opname yang aktif."
      search-label="Cari Item"
      search-placeholder="Cari SKU / barcode / nama item..."
      confirm-text="Pilih Item"
      radio-name="stock-opname-product"
      @close="showProductModal = false"
      @confirm="selectProduct"
    />
  </AppLayout>
</template>
