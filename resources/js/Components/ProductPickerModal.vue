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

watch(
  () => props.show,
  (open) => {
    if (!open) return;
    keyword.value = '';
    selectedId.value = '';
    nextTick(() => searchInputRef.value?.focus());
  }
);

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

const hasStockColumn = computed(() => normalizedProducts.value.some((item) => item.stock !== undefined && item.stock !== null));
</script>

<template>
  <Modal :show="show" max-width="6xl" @close="emit('close')">
    <div class="p-6">
      <h3 class="font-bold text-xl">{{ title }}</h3>
      <p class="text-sm text-base-content/60 mt-2">{{ subtitle }}</p>

      <div class="mt-5">
        <label class="label pb-1"><span class="label-text text-sm text-base-content/70">{{ searchLabel }}</span></label>
        <input
          ref="searchInputRef"
          v-model="keyword"
          type="text"
          class="input input-bordered w-full h-12 text-base"
          :placeholder="searchPlaceholder"
          @keydown.enter.prevent="pickFirstByEnter"
        />
      </div>

      <div class="mt-5 rounded-2xl border border-base-300 overflow-hidden">
        <div class="max-h-[60vh] overflow-y-auto">
          <table class="table table-zebra">
          <thead>
            <tr>
              <th class="w-14 bg-base-200"></th>
              <th class="bg-base-200">SKU</th>
              <th class="bg-base-200">BARCODE</th>
              <th class="bg-base-200">PRODUK</th>
              <th class="bg-base-200 w-24">UOM</th>
              <th class="bg-base-200 w-40">HARGA</th>
              <th v-if="hasStockColumn" class="bg-base-200 w-28">STOK</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="product in filteredProducts"
              :key="product._id"
              :class="[
                'cursor-pointer',
                selectedId === product._id ? 'bg-primary/10' : '',
              ]"
              @click="selectedId = product._id"
            >
              <td class="text-center">
                <input
                  :value="product._id"
                  v-model="selectedId"
                  type="radio"
                  :name="radioName"
                  class="radio radio-sm"
                />
              </td>
              <td class="font-mono text-xs">{{ product.sku }}</td>
              <td class="font-mono text-xs">{{ product.barcode || '-' }}</td>
              <td class="font-medium">{{ product.name }}</td>
              <td><span class="badge badge-ghost badge-sm">{{ product.uom || '-' }}</span></td>
              <td class="tabular-nums">{{ format(product.price) }}</td>
              <td v-if="hasStockColumn" class="tabular-nums">{{ product.stock ?? '-' }}</td>
            </tr>
            <tr v-if="filteredProducts.length === 0">
              <td :colspan="hasStockColumn ? 7 : 6" class="py-10 text-center text-base-content/50">Produk tidak ditemukan.</td>
            </tr>
          </tbody>
        </table>
        </div>
      </div>

      <div class="modal-action mt-4">
        <button type="button" class="btn btn-ghost" @click="emit('close')">Batal</button>
        <button
          type="button"
          class="btn btn-primary border-0"
          :disabled="!selectedId"
          @click="confirmSelection"
        >
          {{ confirmText }}
        </button>
      </div>
    </div>
  </Modal>
</template>
