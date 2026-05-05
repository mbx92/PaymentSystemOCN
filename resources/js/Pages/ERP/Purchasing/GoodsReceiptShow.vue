<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps({
  detail: Object,
});

const advanceForm = useForm({ action: 'post_stock' });

const postToStock = () => {
  advanceForm.action = 'post_stock';
  advanceForm.post(route('erp.purchasing.goods-receipts.advance', props.detail.number), { preserveScroll: true });
};

const goBack = () => {
  router.visit(route('erp.purchasing.goods-receipts'));
};
</script>

<template>
  <Head :title="`GRN — ${detail.number}`" />
  <AppLayout>
    <div class="space-y-5">
      <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Purchasing · Penerimaan barang</p>
        <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
          <div>
            <h1 class="text-3xl font-bold tracking-tight font-mono">{{ detail.number }}</h1>
            <p class="mt-1 text-sm text-base-content/70">
              PO
              <Link
                class="link link-primary font-mono font-semibold"
                :href="route('erp.purchasing.purchase-orders.show', detail.po_number)"
              >
                {{ detail.po_number }}
              </Link>
              · {{ detail.warehouse }}
            </p>
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <StatusBadge :status="detail.status" />
            <button type="button" class="btn btn-ghost btn-sm" @click="goBack">Back</button>
          </div>
        </div>
        <p class="mt-3 text-sm text-base-content/70">Tanggal terima {{ detail.received_date }}</p>
      </div>

      <div class="grid gap-5 lg:grid-cols-3">
        <div class="card bg-base-100 shadow lg:col-span-2">
          <div class="card-body p-0">
            <div class="overflow-x-auto">
              <table class="table table-zebra">
                <thead>
                  <tr>
                    <th>SKU</th>
                    <th>Produk</th>
                    <th class="text-right">Diterima</th>
                    <th>UoM</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(line, idx) in detail.lines" :key="idx">
                    <td class="font-mono text-xs">{{ line.sku }}</td>
                    <td>{{ line.name }}</td>
                    <td class="text-right">{{ line.qty_received }}</td>
                    <td>{{ line.uom }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="card border border-primary/20 bg-primary/5 shadow">
          <div class="card-body">
            <h2 class="card-title text-lg">Lanjutkan proses</h2>
            <p class="text-sm text-base-content/70">Setelah posting, stok dapat diperbarui (integrasi penuh menyusul).</p>
            <div class="card-actions mt-4 flex-col items-stretch gap-2">
              <template v-if="detail.status === 'approved'">
                <button
                  type="button"
                  class="btn btn-primary btn-sm"
                  :disabled="advanceForm.processing"
                  @click="postToStock"
                >
                  Posting ke stok (simulasi)
                </button>
              </template>
              <template v-else-if="detail.status === 'posted'">
                <p class="text-sm text-base-content/60">Sudah diposting — cek stok &amp; movement di inventory.</p>
              </template>
              <Link class="btn btn-outline btn-sm" :href="route('erp.purchasing.purchase-orders.show', detail.po_number)">
                Kembali ke PO
              </Link>
              <Link class="btn btn-ghost btn-sm" :href="route('erp.inventory.stock-management')">Manajemen stok</Link>
              <Link class="btn btn-ghost btn-sm" :href="route('erp.inventory.stock-opname')">Stok opname</Link>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
