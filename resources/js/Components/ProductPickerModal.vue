<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import Modal from '@/Components/Modal.vue';
import { useCurrency } from '@/composables/useCurrency';

const props = defineProps({
  show: { type: Boolean, default: false },
  products: { type: Array, default: () => [] },
  title: { type: String, default: 'Pilih Produk' },
  subtitle: { type: String, default: 'Cari produk lalu pilih untuk ditambahkan.' },
  searchLabel: { type: String, default: 'Cari Produk' },
  searchPlaceholder: { type: String, default: 'Cari SKU / barcode / nama produk...' },
  confirmText: { type: String, default: 'Tambah' },
  radioName: { type: String, default: 'selected_product' },
});

const emit = defineEmits(['close', 'confirm']);
const { format } = useCurrency();

const keyword = ref('');
const selectedId = ref('');
const searchInputRef = ref(null);
const page = ref(1);
const perPage = 10;

const normalizedProducts = computed(() => props.products.map((product, index) => ({
  _id: String(product.id ?? product.sku ?? index),
  sku: product.sku ?? '-',
  barcode: product.barcode ?? '',
  name: product.name ?? '',
  price: Number(product.price ?? product.selling_price ?? 0),
  stock: product.stock,
  uom: product.uom ?? '',
  raw: product,
})));

const filteredProducts = computed(() => {
  const term = keyword.value.trim().toLowerCase();
  if (!term) return normalizedProducts.value;
  return normalizedProducts.value.filter((product) =>
    product.sku.toLowerCase().includes(term)
    || product.barcode.toLowerCase().includes(term)
    || product.name.toLowerCase().includes(term)
  );
});
const totalPages = computed(() => Math.max(1, Math.ceil(filteredProducts.value.length / perPage)));
const visibleProducts = computed(() => {
  const start = (page.value - 1) * perPage;
  return filteredProducts.value.slice(start, start + perPage);
});

watch(
  () => props.show,
  (open) => {
    if (!open) return;
    keyword.value = '';
    selectedId.value = '';
    page.value = 1;
    nextTick(() => searchInputRef.value?.focus());
  },
);

watch(keyword, () => {
  page.value = 1;
});

watch(totalPages, (pages) => {
  if (page.value > pages) page.value = pages;
});

const confirmSelection = () => {
  if (!selectedId.value) return;
  const selected = normalizedProducts.value.find((product) => product._id === selectedId.value);
  if (!selected) return;
  emit('confirm', selected.raw);
};

const pickFirstByEnter = () => {
  if (!filteredProducts.value.length) return;
  selectedId.value = filteredProducts.value[0]._id;
  confirmSelection();
};

const prevPage = () => {
  page.value = Math.max(1, page.value - 1);
};

const nextPage = () => {
  page.value = Math.min(totalPages.value, page.value + 1);
};

const hasStockColumn = computed(() => normalizedProducts.value.some((item) => item.stock !== undefined && item.stock !== null));
</script>

<template>
  <Modal :show="show" max-width="4xl" @close="emit('close')">
    <div class="flex max-h-[min(85vh,640px)] flex-col p-5 sm:p-6">
      <div class="shrink-0">
        <h3 class="text-lg font-bold">{{ title }}</h3>
        <p class="text-sm text-base-content/60 mt-1">{{ subtitle }}</p>
      </div>

      <div class="mt-3 shrink-0">
        <label class="label py-0"><span class="label-text text-xs font-medium">{{ searchLabel }}</span></label>
        <input
          ref="searchInputRef"
          v-model="keyword"
          type="text"
          class="input input-bordered input-sm w-full"
          :placeholder="searchPlaceholder"
          @keydown.enter.prevent="pickFirstByEnter"
        />
      </div>

      <div class="mt-3 min-h-0 flex-1 overflow-x-auto overflow-y-auto rounded-lg border border-base-300">
        <table class="table table-sm table-zebra table-pin-rows">
          <thead>
            <tr>
              <th class="w-10"></th>
              <th>SKU</th>
              <th>Barcode</th>
              <th>Produk</th>
              <th class="w-20">UOM</th>
              <th class="w-28 text-right">Harga</th>
              <th v-if="hasStockColumn" class="w-20 text-right">Stok</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="product in visibleProducts"
              :key="product._id"
              class="cursor-pointer hover"
              :class="selectedId === product._id ? 'bg-primary/10' : ''"
              @click="selectedId = product._id"
            >
              <td class="text-center">
                <input
                  v-model="selectedId"
                  :value="product._id"
                  type="radio"
                  :name="radioName"
                  class="radio radio-sm radio-primary"
                  @click.stop
                />
              </td>
              <td class="font-mono text-xs">{{ product.sku }}</td>
              <td class="font-mono text-xs">{{ product.barcode || '-' }}</td>
              <td>{{ product.name }}</td>
              <td><span class="badge badge-ghost badge-xs">{{ product.uom || '-' }}</span></td>
              <td class="text-right tabular-nums">{{ format(product.price) }}</td>
              <td v-if="hasStockColumn" class="text-right tabular-nums">{{ product.stock ?? '-' }}</td>
            </tr>
            <tr v-if="filteredProducts.length === 0">
              <td :colspan="hasStockColumn ? 7 : 6" class="py-8 text-center text-base-content/50">Produk tidak ditemukan.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="mt-3 shrink-0 flex flex-col gap-2 text-xs text-base-content/60 sm:flex-row sm:items-center sm:justify-between">
        <p>
          {{ visibleProducts.length }} / {{ filteredProducts.length }} produk
          <span v-if="filteredProducts.length">· hal {{ page }}/{{ totalPages }}</span>
        </p>
        <div class="join">
          <button type="button" class="btn btn-xs join-item" :disabled="page <= 1" @click="prevPage">Prev</button>
          <button type="button" class="btn btn-xs join-item" :disabled="page >= totalPages" @click="nextPage">Next</button>
        </div>
      </div>

      <div class="mt-3 shrink-0 flex justify-end gap-2 border-t border-base-200 pt-3">
        <button type="button" class="btn btn-ghost btn-sm" @click="emit('close')">Batal</button>
        <button
          type="button"
          class="btn btn-primary btn-sm"
          :disabled="!selectedId"
          @click="confirmSelection"
        >
          {{ confirmText }}
        </button>
      </div>
    </div>
  </Modal>
</template>
