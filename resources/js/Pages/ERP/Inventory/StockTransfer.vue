<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps({
  warehouses: Array,
  products: Array,
  warehouseStocks: Object,
});

const page = usePage();

const sourceWarehouseId = ref('');
const destWarehouseId = ref('');
const note = ref('');
const processing = ref(false);

const selectedIds = reactive(new Set());

const destWarehouses = computed(() =>
  props.warehouses.filter((wh) => String(wh.id) !== String(sourceWarehouseId.value)),
);

watch(sourceWarehouseId, () => {
  if (String(destWarehouseId.value) === String(sourceWarehouseId.value)) {
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
  return props.products
    .map((p) => ({ ...p, available: getAvailable(p.id) }))
    .filter((p) => p.available > 0);
});

const toggleAll = () => {
  if (selectedIds.size === sourceProducts.value.length) {
    selectedIds.clear();
  } else {
    sourceProducts.value.forEach((p) => selectedIds.add(p.id));
  }
};

const toggleOne = (id) => {
  if (selectedIds.has(id)) {
    selectedIds.delete(id);
  } else {
    selectedIds.add(id);
  }
};

const allChecked = computed(() => sourceProducts.value.length > 0 && selectedIds.size === sourceProducts.value.length);

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
      <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Inventory Workspace</p>
        <div class="mt-2 flex items-center justify-between gap-3">
          <h1 class="text-3xl font-bold tracking-tight">Mutasi Stok</h1>
          <Link class="btn btn-ghost btn-sm" :href="route('erp.inventory')">Back</Link>
        </div>
        <p class="mt-2 text-sm text-base-content/70">Transfer stok produk antar gudang/warehouse. Pilih warehouse, centang produk, lalu transfer.</p>
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
        <div class="ocn-panel__head flex items-center justify-between">
          <h2 class="ocn-panel__title">Pilih produk untuk ditransfer</h2>
          <span class="text-sm text-base-content/60">{{ selectedIds.size }} / {{ sourceProducts.length }} dipilih</span>
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
                <td class="font-semibold">{{ product.name }}</td>
                <td class="uppercase">{{ product.uom }}</td>
                <td class="text-right tabular-nums">{{ product.available }}</td>
              </tr>
              <tr v-if="sourceProducts.length === 0">
                <td colspan="5" class="py-10 text-center text-base-content/50">Tidak ada produk dengan stok tersedia di warehouse ini.</td>
              </tr>
            </tbody>
          </table>
        </div>
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
