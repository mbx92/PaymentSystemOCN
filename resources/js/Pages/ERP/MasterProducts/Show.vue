<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useCurrency } from '@/composables/useCurrency';

const props = defineProps({
  product: Object,
});

const { format } = useCurrency();

const confirmDeleteProduct = () => {
  document.getElementById('modal-delete-product')?.showModal();
};

const deleteProduct = () => {
  router.delete(route('erp.master-products.destroy', props.product.id));
};

const channelLabel = (value) => {
  if (value === 'pos') return 'POS';
  if (value === 'project') return 'PROJECT';
  if (value === 'both') return 'POS + PROJECT';
  return value;
};

const typeLabel = (value) => (value === 'project_material' ? 'Material Project' : 'Barang Jual');
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

      <div class="card bg-base-100 shadow">
        <div class="card-body flex flex-wrap items-center justify-between gap-3">
          <div>
            <p class="text-xs uppercase text-base-content/50">Nama Produk</p>
            <p class="text-lg font-semibold">{{ product.name }}</p>
          </div>
          <div class="flex gap-2">
            <StatusBadge :status="product.status" />
            <button class="btn btn-error btn-sm" @click="confirmDeleteProduct">Delete Product</button>
          </div>
        </div>
      </div>

      <div class="card bg-base-100 shadow">
        <div class="card-body grid gap-4 md:grid-cols-2">
          <div>
            <p class="text-xs uppercase text-base-content/50">SKU</p>
            <p class="font-mono font-semibold">{{ product.sku }}</p>
          </div>
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
            <span class="badge badge-info">{{ channelLabel(product.sales_channel) }}</span>
          </div>
          <div>
            <p class="text-xs uppercase text-base-content/50">Tipe Produk</p>
            <span class="badge badge-ghost">{{ typeLabel(product.product_type) }}</span>
          </div>
          <div>
            <p class="text-xs uppercase text-base-content/50">Harga Jual</p>
            <p class="font-semibold">{{ format(product.selling_price) }}</p>
          </div>
          <div>
            <p class="text-xs uppercase text-base-content/50">Stok</p>
            <p class="font-semibold">{{ product.stock }} {{ product.uom }}</p>
          </div>
          <div>
            <p class="text-xs uppercase text-base-content/50">Status</p>
            <StatusBadge :status="product.status" />
          </div>
          <div class="md:col-span-2">
            <p class="text-xs uppercase text-base-content/50">Deskripsi</p>
            <p class="text-sm">{{ product.description || '-' }}</p>
          </div>
        </div>
      </div>

      <ConfirmModal
        id="modal-delete-product"
        title="Hapus Produk"
        :message="`Yakin hapus produk \\\"${product.name}\\\"?`"
        confirm-text="Hapus"
        @confirm="deleteProduct"
      />
    </div>
  </AppLayout>
</template>
