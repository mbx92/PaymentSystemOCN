<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import DataTablePagination from '@/Components/DataTablePagination.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps({
  products: Object,
  warehouses: Array,
  filters: Object,
  reserved_alert: Object,
  reserved_breakdown_by_product: Object,
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
const selectedReservedProduct = ref(null);
const batchAlertForm = useForm({
  enabled: true,
});
let filterTimer;

const stockManagementParams = () => ({
  warehouse_id: filters.warehouse_id,
  q: filters.q,
  status: filters.status,
  low_stock_only: filters.low_stock_only ? 1 : undefined,
  per_page: filters.per_page,
});

watch(
  filters,
  () => {
    clearTimeout(filterTimer);
    filterTimer = setTimeout(() => {
      router.get(route('erp.inventory.stock-management'), stockManagementParams(), {
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
            <div class="ml-auto flex flex-wrap gap-2">
              <button
                class="btn btn-outline btn-sm"
                :disabled="batchAlertForm.processing"
                @click="submitBatchAlert(true)"
              >
                Aktifkan Notif Semua
              </button>
              <button
                class="btn btn-outline btn-warning btn-sm"
                :disabled="batchAlertForm.processing"
                @click="submitBatchAlert(false)"
              >
                Nonaktifkan Notif Semua
              </button>
            </div>
            <div class="text-sm text-base-content/60">
              Stok yang ditampilkan adalah stok per warehouse terpilih.
            </div>
          </div>
        </div>
        <div class="overflow-x-auto">
          <table class="table table-zebra">
            <thead>
              <tr>
                <th>SKU</th>
                <th>Produk</th>
                <th>On Hand</th>
                <th>Reserved</th>
                <th>Available</th>
                <th>Min Stok</th>
                <th>Notif Rendah</th>
                <th>Total Terjual</th>
                <th>Catatan</th>
                <th>Status</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="product in (products?.data || [])"
                :key="product.id"
                :class="(product.reserved_qty ?? 0) > 0 ? 'cursor-pointer hover' : ''"
                @click="openReservedModal(product)"
              >
                <td class="font-mono text-xs">{{ product.sku }}</td>
                <td class="font-semibold">{{ product.name }}</td>
                <td><span class="badge badge-sm badge-ghost">{{ product.stock }}</span></td>
                <td><span class="badge badge-sm" :class="product.reserved_qty > 0 ? 'badge-warning' : 'badge-ghost'">{{ product.reserved_qty }}</span></td>
                <td><span class="badge badge-sm text-white" :class="availabilityBadgeClass(product)">{{ product.available_qty }}</span></td>
                <td>
                  <input
                    v-model.number="getForm(product).min_stock"
                    type="number"
                    min="0"
                    class="input input-bordered input-sm w-24"
                    @click.stop
                  />
                </td>
                <td @click.stop>
                  <label class="flex items-center gap-2">
                    <input v-model="getForm(product).low_stock_alert_enabled" type="checkbox" class="toggle toggle-warning toggle-sm" />
                    <span class="text-xs">{{ getForm(product).low_stock_alert_enabled ? 'Aktif' : 'Nonaktif' }}</span>
                  </label>
                </td>
                <td><span class="badge badge-sm badge-ghost">{{ product.total_sold }}</span></td>
                <td><input v-model="getForm(product).note" type="text" class="input input-bordered input-sm w-40" placeholder="Opsional" @click.stop /></td>
                <td><StatusBadge :status="product.status" /></td>
                <td><button class="btn btn-primary btn-xs" :disabled="getForm(product).processing" @click.stop="saveRow(product)">Simpan</button></td>
              </tr>
              <tr v-if="(products?.data || []).length === 0">
                <td colspan="11" class="py-8 text-center text-base-content/50">
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
    </div>
  </AppLayout>
</template>
