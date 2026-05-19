<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import DataTablePagination from '@/Components/DataTablePagination.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { computed, nextTick, reactive, ref, watch } from 'vue';

const props = defineProps({
  products: Object,
  warehouses: Array,
  filters: Object,
  reserved_alert: Object,
  reserved_breakdown_by_product: Object,
  batch_low_stock_alerts: { type: String, default: 'all_off' },
  stock_movement_mismatch: Object,
});

const filters = reactive({
  warehouse_id: props.filters?.warehouse_id ?? '',
  q: props.filters?.q ?? '',
  status: props.filters?.status ?? '',
  low_stock_only: props.filters?.low_stock_only === true || props.filters?.low_stock_only === '1' || props.filters?.low_stock_only === 1,
  per_page: props.filters?.per_page ?? props.products?.per_page ?? 25,
});

const selectedWarehouseId = computed(() => filters.warehouse_id || props.filters?.warehouse_id || '');
const hasReservedStock = computed(() => (props.reserved_alert?.count ?? 0) > 0);
const hasMovementMismatch = computed(() => (props.stock_movement_mismatch?.count ?? 0) > 0);
const selectedReservedProduct = ref(null);
const selectedProductDetail = ref(null);
const batchAlertForm = useForm({
  enabled: true,
});
const batchLowStockToggleRef = ref(null);
let filterTimer;

watch(
  () => props.batch_low_stock_alerts,
  (v) => {
    nextTick(() => {
      const el = batchLowStockToggleRef.value;
      if (el) {
        el.indeterminate = v === 'mixed';
      }
    });
  },
  { immediate: true },
);

const stockManagementParams = () => ({
  warehouse_id: filters.warehouse_id,
  q: filters.q,
  status: filters.status,
  low_stock_only: filters.low_stock_only ? 1 : undefined,
  per_page: filters.per_page,
});

const resetFilters = () => {
  filters.q = '';
  filters.status = '';
  filters.low_stock_only = false;
};

watch(
  filters,
  () => {
    clearTimeout(filterTimer);
    filterTimer = setTimeout(() => {
      router.get(route('erp.inventory.stock-management'), { ...stockManagementParams(), page: 1 }, {
        preserveState: true,
        replace: true,
      });
    }, 250);
  },
  { deep: true },
);

const forms = reactive({});

const getForm = (product) => {
  if (!forms[product.id]) {
    forms[product.id] = useForm({
      min_stock: product.min_stock,
      low_stock_alert_enabled: product.low_stock_alert_enabled,
      note: '',
    });
  }
  return forms[product.id];
};

/** Simpan draft catatan; min_stock & notifikasi disamakan dengan server setelah Inertia refresh. */
watch(
  () => props.products?.data,
  (rows) => {
    if (!rows?.length) return;
    for (const product of rows) {
      const f = forms[product.id];
      if (!f || f.processing) continue;
      f.min_stock = product.min_stock;
      f.low_stock_alert_enabled = product.low_stock_alert_enabled;
    }
  },
  { deep: true },
);

const saveRow = (product) => {
  const form = getForm(product);
  form.put(route('erp.inventory.stock-management.update', product.id), {
    preserveScroll: true,
  });
};

const submitBatchAlert = (enabled) => {
  batchAlertForm.enabled = enabled;
  batchAlertForm.patch(route('erp.inventory.stock-management.low-stock-alerts.batch'), {
    preserveScroll: true,
  });
};

const availabilityBadgeClass = (product) => {
  if (product.available_qty <= 0) return 'badge-error';
  if (product.available_qty < product.min_stock) return 'badge-warning';
  return 'badge-success';
};

const movementBadgeClass = (product) => (product.movement_mismatch ? 'badge-error' : 'badge-success');

const movementBadgeText = (product) => (product.movement_mismatch ? `Selisih ${product.movement_delta_qty}` : 'Sinkron');

const movementBadgeTitle = (product) => (
  product.movement_mismatch
    ? `Qty movement ${product.movement_expected_qty}, selisih ${product.movement_delta_qty}`
    : 'Sinkron dengan stock movement'
);

const selectedProductForm = computed(() => {
  const product = selectedProductDetail.value;
  return product ? getForm(product) : null;
});

const openProductDetailModal = (product) => {
  selectedProductDetail.value = product;
  document.getElementById('modal-product-stock-detail')?.showModal();
};

const openReservedModal = (product) => {
  const rows = props.reserved_breakdown_by_product?.[product.id] ?? [];
  if (!rows.length) return;
  selectedReservedProduct.value = {
    ...product,
    rows,
  };
  document.getElementById('modal-reserved-projects')?.showModal();
};
</script>

<template>
  <Head title="Inventory - Manajemen Stok" />
  <AppLayout>
    <div class="space-y-5">
      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Inventory Workspace</p>
              <h1 class="ocn-panel__title mt-1">Manajemen Stok</h1>
              <p class="ocn-panel__desc mt-1">Atur parameter minimum stok. Stok aktual dan total terjual dikontrol dari transaksi nyata.</p>
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

      <div v-if="hasReservedStock" class="alert alert-warning shadow-sm">
        <div class="space-y-1">
          <p class="font-semibold">
            Ada {{ props.reserved_alert.count }} produk dengan stok ter-reserve (total {{ props.reserved_alert.total_reserved_qty }} unit).
          </p>
          <p class="text-xs opacity-80">
            Ringkasan: {{ props.reserved_alert.items.map((item) => `${item.sku} (${item.reserved_qty})`).join(', ') }}
          </p>
        </div>
      </div>

      <div v-if="hasMovementMismatch" class="alert alert-error shadow-sm">
        <div class="space-y-1">
          <p class="font-semibold">
            Ada {{ props.stock_movement_mismatch.count }} produk di halaman ini yang qty warehouse-nya tidak sinkron dengan stock movement.
          </p>
          <p class="text-xs opacity-80">
            Gunakan utility `Rebuild qty stock dari movement` di Accounting > Utilities > Inventory bila histori movement sudah lengkap.
          </p>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Manajemen stok per warehouse</h2>
          <p class="ocn-panel__desc">Pilih gudang, lalu sesuaikan minimum stok per produk.</p>
        </div>
        <div class="card-body border-b border-base-200">
          <div class="flex flex-wrap items-end gap-3">
            <div class="min-w-[240px]">
              <label class="label"><span class="label-text text-xs uppercase tracking-wide">Warehouse</span></label>
              <select
                v-model="filters.warehouse_id"
                class="select select-sm select-bordered w-full"
              >
                <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.code }} - {{ w.name }}</option>
              </select>
            </div>
            <div class="min-w-[260px] flex-1">
              <label class="label"><span class="label-text text-xs uppercase tracking-wide">Cari Produk</span></label>
              <input
                v-model="filters.q"
                type="search"
                class="input input-sm input-bordered w-full"
                placeholder="Cari SKU atau nama produk"
              />
            </div>
            <div class="min-w-[180px]">
              <label class="label"><span class="label-text text-xs uppercase tracking-wide">Status</span></label>
              <select v-model="filters.status" class="select select-sm select-bordered w-full">
                <option value="">Semua status</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>
            </div>
            <label class="flex min-h-8 items-center gap-3 rounded-lg border border-base-300 px-3 py-2 text-sm">
              <input v-model="filters.low_stock_only" type="checkbox" class="toggle toggle-warning toggle-sm" />
              <span>Hanya stok rendah</span>
            </label>
            <label
              class="flex min-h-8 items-center gap-2 rounded-lg border border-base-300 px-3 py-2 text-sm"
              title="Berlaku untuk semua produk stok (bukan jasa). Saat campuran, tampilan toggle netral sampai Anda pilih aktif atau nonaktif."
            >
              <span class="text-xs font-medium text-base-content/80 whitespace-nowrap">Notif semua produk</span>
              <input
                ref="batchLowStockToggleRef"
                type="checkbox"
                class="toggle toggle-warning toggle-sm"
                :checked="props.batch_low_stock_alerts === 'all_on'"
                :disabled="batchAlertForm.processing"
                @change="submitBatchAlert($event.target.checked)"
              />
            </label>
            <div class="text-sm text-base-content/60">
              Stok yang ditampilkan adalah stok per warehouse terpilih.
            </div>
            <div class="ml-auto">
              <button type="button" class="btn btn-ghost btn-sm" @click="resetFilters">Reset filter</button>
            </div>
          </div>
        </div>
        <div class="overflow-x-auto">
          <table class="table table-zebra table-xs text-xs">
            <thead>
              <tr>
                <th>SKU</th>
                <th>Produk</th>
                <th>On Hand</th>
                <th>Reserved</th>
                <th>Available</th>
                <th>Notif Rendah</th>
                <th>Total Terjual</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="product in (products?.data || [])"
                :key="product.id"
                class="cursor-pointer hover"
                @click="openProductDetailModal(product)"
              >
                <td class="font-mono text-xs">{{ product.sku }}</td>
                <td class="font-semibold leading-tight">
                  <div>{{ product.name }}</div>
                  <p class="mt-1 text-[11px] font-normal text-base-content/50">Klik untuk detail item</p>
                </td>
                <td><span class="badge badge-sm badge-ghost">{{ product.stock }}</span></td>
                <td @click.stop>
                  <button
                    type="button"
                    class="badge badge-sm"
                    :class="product.reserved_qty > 0 ? 'badge-warning' : 'badge-ghost'"
                    :disabled="product.reserved_qty <= 0"
                    @click="openReservedModal(product)"
                  >
                    {{ product.reserved_qty }}
                  </button>
                </td>
                <td><span class="badge badge-sm text-white" :class="availabilityBadgeClass(product)">{{ product.available_qty }}</span></td>
                <td>
                  <span class="badge badge-sm" :class="product.low_stock_alert_enabled ? 'badge-success' : 'badge-ghost'">
                    {{ product.low_stock_alert_enabled ? 'Aktif' : 'Nonaktif' }}
                  </span>
                </td>
                <td><span class="badge badge-sm badge-ghost">{{ product.total_sold }}</span></td>
                <td><StatusBadge :status="product.status" /></td>
              </tr>
              <tr v-if="(products?.data || []).length === 0">
                <td colspan="8" class="py-8 text-center text-base-content/50">
                  Tidak ada produk sesuai filter.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <DataTablePagination :paginator="products" @update:per-page="(n) => { filters.per_page = n; }" />
      </div>

      <dialog id="modal-reserved-projects" class="modal">
        <div class="modal-box max-w-3xl">
          <h3 class="text-lg font-bold">Detail Reserved by Project</h3>
          <p v-if="selectedReservedProduct" class="text-sm text-base-content/70 mt-1">
            {{ selectedReservedProduct.sku }} - {{ selectedReservedProduct.name }}
          </p>

          <div v-if="selectedReservedProduct?.rows?.length" class="overflow-x-auto mt-4">
            <table class="table table-sm">
              <thead>
                <tr>
                  <th>Project</th>
                  <th>Status</th>
                  <th>Planned</th>
                  <th>Reserved</th>
                  <th>Issued</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in selectedReservedProduct.rows" :key="row.project_id">
                  <td>
                    <Link class="link link-hover font-medium" :href="route('projects.show', row.project_id)">
                      {{ row.project_name ?? row.project_id }}
                    </Link>
                  </td>
                  <td><StatusBadge :status="row.project_status ?? 'negosiasi'" /></td>
                  <td>{{ row.planned_qty }}</td>
                  <td>{{ row.reserved_qty }}</td>
                  <td>{{ row.issued_qty }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="modal-action">
            <form method="dialog">
              <button class="btn">Tutup</button>
            </form>
          </div>
        </div>
      </dialog>

      <dialog id="modal-product-stock-detail" class="modal">
        <div class="modal-box max-w-4xl">
          <h3 class="text-lg font-bold">Detail Item Stok</h3>
          <p v-if="selectedProductDetail" class="mt-1 text-sm text-base-content/70">
            {{ selectedProductDetail.sku }} - {{ selectedProductDetail.name }}
          </p>

          <div v-if="selectedProductDetail" class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border border-base-300 bg-base-100 p-4">
              <p class="text-xs font-bold uppercase tracking-wide text-base-content/50">On hand</p>
              <p class="mt-2 text-2xl font-bold">{{ selectedProductDetail.stock }}</p>
            </div>
            <div class="rounded-xl border border-base-300 bg-base-100 p-4">
              <p class="text-xs font-bold uppercase tracking-wide text-base-content/50">Reserved</p>
              <div class="mt-2 flex items-center gap-2">
                <span class="badge badge-sm" :class="selectedProductDetail.reserved_qty > 0 ? 'badge-warning' : 'badge-ghost'">
                  {{ selectedProductDetail.reserved_qty }}
                </span>
                <button
                  v-if="selectedProductDetail.reserved_qty > 0"
                  type="button"
                  class="btn btn-ghost btn-xs"
                  @click="openReservedModal(selectedProductDetail)"
                >
                  Lihat proyek
                </button>
              </div>
            </div>
            <div class="rounded-xl border border-base-300 bg-base-100 p-4">
              <p class="text-xs font-bold uppercase tracking-wide text-base-content/50">Available</p>
              <div class="mt-2">
                <span class="badge badge-sm text-white" :class="availabilityBadgeClass(selectedProductDetail)">
                  {{ selectedProductDetail.available_qty }}
                </span>
              </div>
            </div>
            <div class="rounded-xl border border-base-300 bg-base-100 p-4">
              <p class="text-xs font-bold uppercase tracking-wide text-base-content/50">Total terjual</p>
              <p class="mt-2 text-2xl font-bold">{{ selectedProductDetail.total_sold }}</p>
            </div>
            <div class="rounded-xl border border-base-300 bg-base-100 p-4">
              <p class="text-xs font-bold uppercase tracking-wide text-base-content/50">Cek movement</p>
              <div class="mt-2">
                <span class="badge badge-sm text-white" :class="movementBadgeClass(selectedProductDetail)">
                  {{ movementBadgeText(selectedProductDetail) }}
                </span>
              </div>
              <p class="mt-2 text-xs text-base-content/60">
                {{ movementBadgeTitle(selectedProductDetail) }}
              </p>
            </div>
            <div class="rounded-xl border border-base-300 bg-base-100 p-4">
              <p class="text-xs font-bold uppercase tracking-wide text-base-content/50">Status</p>
              <div class="mt-2">
                <StatusBadge :status="selectedProductDetail.status" />
              </div>
            </div>
          </div>

          <div v-if="selectedProductDetail && selectedProductForm" class="mt-5 rounded-xl border border-base-300 bg-base-100 p-5">
            <div class="flex flex-col gap-1">
              <h4 class="text-sm font-semibold">Atur parameter item</h4>
              <p class="text-xs text-base-content/60">Semua field yang sebelumnya diedit langsung di tabel sekarang dipindahkan ke modal ini.</p>
            </div>

            <div class="mt-4 grid gap-4 md:grid-cols-2">
              <div>
                <label class="label"><span class="label-text text-xs uppercase tracking-wide">Minimum stok</span></label>
                <input
                  v-model.number="selectedProductForm.min_stock"
                  type="number"
                  min="0"
                  class="input input-bordered w-full"
                />
              </div>
              <div>
                <label class="label"><span class="label-text text-xs uppercase tracking-wide">Notif stok rendah</span></label>
                <label
                  class="flex min-h-12 items-center justify-between rounded-lg border border-base-300 px-4 py-3"
                  :title="selectedProductForm.low_stock_alert_enabled ? 'Notifikasi stok rendah aktif' : 'Notifikasi stok rendah nonaktif'"
                >
                  <span class="text-sm">{{ selectedProductForm.low_stock_alert_enabled ? 'Aktif' : 'Nonaktif' }}</span>
                  <input v-model="selectedProductForm.low_stock_alert_enabled" type="checkbox" class="toggle toggle-warning" />
                </label>
              </div>
            </div>

            <div class="mt-4">
              <label class="label"><span class="label-text text-xs uppercase tracking-wide">Catatan</span></label>
              <textarea
                v-model="selectedProductForm.note"
                class="textarea textarea-bordered min-h-28 w-full"
                placeholder="Opsional"
              />
              <p class="mt-2 text-xs text-base-content/60">
                Fallback catatan saat kosong: {{ selectedProductDetail.description || '-' }}
              </p>
            </div>

          </div>

          <div class="modal-action">
            <button
              v-if="selectedProductDetail && selectedProductForm"
              type="button"
              class="btn btn-primary"
              :disabled="selectedProductForm.processing"
              @click="saveRow(selectedProductDetail)"
            >
              {{ selectedProductForm.processing ? 'Menyimpan...' : 'Simpan perubahan' }}
            </button>
            <form method="dialog">
              <button class="btn">Tutup</button>
            </form>
          </div>
        </div>
      </dialog>
    </div>
  </AppLayout>
</template>
