<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTablePagination from '@/Components/DataTablePagination.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps({
  warehouses: Array,
  products: Object,
  warehouseStocks: Object,
  filters: Object,
});

const page = usePage();

const filters = reactive({ q: props.filters?.q ?? '', per_page: props.filters?.per_page ?? props.products?.per_page ?? 25 });
let timer;
watch(filters, (val) => {
  clearTimeout(timer);
  const payload = { ...val, warehouse_id: sourceWarehouseId.value || undefined };
  timer = setTimeout(() => router.get(route('erp.inventory.stock-transfer'), payload, { preserveState: true, replace: true }), 250);
}, { deep: true });

const sourceWarehouseId = ref(props.filters?.warehouse_id ?? '');
const destWarehouseId = ref('');
const note = ref('');
const processing = ref(false);

const selectedIds = reactive(new Set());

const destWarehouses = computed(() =>
  props.warehouses.filter((wh) => String(wh.id) !== String(sourceWarehouseId.value)),
);

watch(sourceWarehouseId, (val) => {
  if (String(destWarehouseId.value) === String(val)) {
    destWarehouseId.value = '';
  }
  selectedIds.clear();
});

const getAvailable = (productId) => {
  if (!sourceWarehouseId.value) return 0;
  const productStocks = props.warehouseStocks?.[productId];
  if (!productStocks) return 0;
  const entry = productStocks[sourceWarehouseId.value];
  return entry ? entry.available : 0;
};

const sourceProducts = computed(() => {
  if (!sourceWarehouseId.value) return [];
  const items = props.products?.data ?? [];
  return items
    .map((p) => ({ ...p, available: getAvailable(p.id) }))
    .filter((p) => p.available > 0);
});

const toggleAll = () => {
  const current = sourceProducts.value;
  if (current.length > 0 && current.every((p) => selectedIds.has(p.id))) {
    current.forEach((p) => selectedIds.delete(p.id));
  } else {
    current.forEach((p) => selectedIds.add(p.id));
  }
};

const toggleOne = (id) => {
  if (selectedIds.has(id)) {
    selectedIds.delete(id);
  } else {
    selectedIds.add(id);
  }
};

const allChecked = computed(() => {
  const current = sourceProducts.value;
  return current.length > 0 && current.every((p) => selectedIds.has(p.id));
});

const canSubmit = computed(() =>
  sourceWarehouseId.value && destWarehouseId.value && selectedIds.size > 0 && !processing.value,
);

const submit = () => {
  const items = sourceProducts.value
    .filter((p) => selectedIds.has(p.id))
    .map((p) => ({ product_id: p.id, qty: p.available }));

  processing.value = true;
  router.post(route('erp.inventory.stock-transfer.store'), {
    source_warehouse_id: sourceWarehouseId.value,
    destination_warehouse_id: destWarehouseId.value,
    note: note.value,
    items,
  }, {
    preserveScroll: true,
    onSuccess: () => {
      selectedIds.clear();
      note.value = '';
    },
    onFinish: () => {
      processing.value = false;
    },
  });
};
</script>

<template>
  <Head title="Inventory - Mutasi Stok" />
  <AppLayout>
    <div class="space-y-5">
      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Inventory Workspace</p>
              <h1 class="ocn-panel__title mt-1">Mutasi Stok</h1>
              <p class="ocn-panel__desc mt-1">Transfer stok produk antar gudang/warehouse. Pilih warehouse, centang produk, lalu transfer.</p>
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
          <h2 class="ocn-panel__title">Warehouse & catatan</h2>
        </div>
        <div class="card-body grid gap-4 md:grid-cols-3">
          <div>
            <label class="label"><span class="label-text">Warehouse Asal <span class="text-error">*</span></span></label>
            <select v-model="sourceWarehouseId" class="select select-bordered w-full">
              <option value="">Pilih warehouse asal</option>
              <option v-for="wh in warehouses" :key="wh.id" :value="wh.id">
                {{ wh.code }} — {{ wh.name }}
              </option>
            </select>
          </div>
          <div>
            <label class="label"><span class="label-text">Warehouse Tujuan <span class="text-error">*</span></span></label>
            <select v-model="destWarehouseId" class="select select-bordered w-full">
              <option value="">Pilih warehouse tujuan</option>
              <option v-for="wh in destWarehouses" :key="wh.id" :value="wh.id">
                {{ wh.code }} — {{ wh.name }}
              </option>
            </select>
          </div>
          <div>
            <label class="label"><span class="label-text">Catatan</span></label>
            <input v-model="note" type="text" class="input input-bordered w-full" placeholder="Opsional" />
          </div>
        </div>
      </div>

      <div v-if="sourceWarehouseId" class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="ocn-panel__title">Pilih produk untuk ditransfer</h2>
            <div class="flex items-center gap-2">
              <span class="text-sm text-base-content/60">{{ selectedIds.size }} dipilih</span>
              <input v-model="filters.q" type="text" class="input input-bordered input-sm w-48" placeholder="Cari SKU atau nama..." />
            </div>
          </div>
        </div>
        <div class="overflow-x-auto">
          <table class="table table-zebra">
            <thead>
              <tr>
                <th class="w-10">
                  <input
                    type="checkbox"
                    class="checkbox checkbox-sm"
                    :checked="allChecked"
                    :disabled="sourceProducts.length === 0"
                    @change="toggleAll"
                  />
                </th>
                <th>SKU</th>
                <th>Produk</th>
                <th>UoM</th>
                <th class="text-right">Stok Tersedia</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="product in sourceProducts"
                :key="product.id"
                class="cursor-pointer hover"
                @click="toggleOne(product.id)"
              >
                <td>
                  <input
                    type="checkbox"
                    class="checkbox checkbox-sm"
                    :checked="selectedIds.has(product.id)"
                    @click.stop="toggleOne(product.id)"
                  />
                </td>
                <td class="font-mono text-xs">{{ product.sku }}</td>
                <td>
                  <span class="font-semibold">{{ product.name }}</span>
                </td>
                <td class="uppercase">{{ product.uom }}</td>
                <td class="text-right tabular-nums">{{ product.available }}</td>
              </tr>
              <tr v-if="sourceProducts.length === 0">
                <td colspan="5" class="py-10 text-center text-base-content/50">Tidak ada produk dengan stok tersedia di warehouse ini.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <DataTablePagination :paginator="products" @update:per-page="(n) => { filters.per_page = n; }" />
        <div class="card-body flex items-center justify-end gap-3 border-t border-base-200 pt-4">
          <button
            class="btn"
            :class="canSubmit ? 'btn-primary' : 'btn-secondary btn-disabled'"
            :disabled="!canSubmit"
            @click="submit"
          >
            <span v-if="processing" class="loading loading-spinner loading-sm"></span>
            Transfer {{ selectedIds.size }} Produk
          </button>
        </div>
      </div>

      <div v-else class="ocn-panel">
        <div class="card-body py-10 text-center text-base-content/50">
          Pilih warehouse asal untuk melihat produk yang bisa ditransfer.
        </div>
      </div>
    </div>
  </AppLayout>
</template>
