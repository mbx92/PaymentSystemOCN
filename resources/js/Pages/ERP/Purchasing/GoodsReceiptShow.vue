<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { useDateFormat } from '@/composables/useDateFormat';

const props = defineProps({
  detail: Object,
  warehouses: Array,
});

const { formatDate } = useDateFormat();

const advanceForm = useForm({ action: 'post_stock', warehouse_id: props.detail?.warehouse_id ?? '' });

const postToStock = () => {
  advanceForm.action = 'post_stock';
  advanceForm.post(route('erp.purchasing.goods-receipts.advance', props.detail.number), { preserveScroll: true });
};

const goBack = () => {
  router.visit(route('erp.purchasing'));
};
</script>

<template>
  <Head :title="`GRN — ${detail.number}`" />
  <AppLayout>
    <div class="space-y-5">
      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Purchasing · Penerimaan barang</p>
              <h1 class="ocn-panel__title mt-1">{{ detail.number }}</h1>
              <p class="ocn-panel__desc mt-1">
              PO
              <Link
                class="link link-primary font-mono font-semibold"
                :href="route('erp.purchasing.purchase-orders.show', detail.po_number)"
              >
                {{ detail.po_number }}
              </Link>
              · {{ detail.warehouse }}
            </p>
              <p class="ocn-panel__desc mt-1">Tanggal terima {{ formatDate(detail.received_date) }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
              <StatusBadge :status="detail.status" />
              <button type="button" class="btn btn-ghost btn-sm shrink-0 gap-1.5" @click="goBack">
                <ArrowLeftIcon class="h-4 w-4" />
                Back
              </button>
            </div>
          </div>
        </div>
      </div>

      <div class="grid gap-5 lg:grid-cols-3">
        <div class="ocn-panel lg:col-span-2">
          <div class="ocn-panel__head">
            <h2 class="ocn-panel__title">Baris penerimaan barang</h2>
          </div>
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

        <div class="lg:sticky lg:top-20 lg:z-10 lg:self-start">
          <div class="card border border-primary/20 bg-primary/5 shadow">
            <div class="card-body">
              <h2 class="card-title text-lg">Lanjutkan proses</h2>
              <p class="text-sm text-base-content/70">Setelah posting, stok dapat diperbarui (integrasi penuh menyusul).</p>
              <p v-if="advanceForm.errors.action" class="mt-2 text-sm text-error">{{ advanceForm.errors.action }}</p>
              <div class="card-actions mt-4 flex-col items-stretch gap-2">
                <template v-if="detail.status === 'approved'">
                  <div>
                    <label class="label"><span class="label-text text-xs uppercase tracking-wide">Posting ke Warehouse</span></label>
                    <select v-model="advanceForm.warehouse_id" class="select select-bordered select-sm w-full">
                      <option value="">Pilih warehouse</option>
                      <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.code }} - {{ w.name }}</option>
                    </select>
                  </div>
                  <button
                    type="button"
                    class="btn btn-primary btn-sm"
                    :disabled="advanceForm.processing"
                    @click="postToStock"
                  >
                    Posting ke stok
                  </button>
                </template>
                <template v-else-if="detail.status === 'posted'">
                  <p class="text-sm text-base-content/60">Sudah diposting — cek stok &amp; movement di inventory.</p>
                </template>
                <Link class="btn btn-outline btn-sm gap-1.5" :href="route('erp.purchasing.purchase-orders.show', detail.po_number)">
                  <ArrowLeftIcon class="h-4 w-4" />
                  Kembali ke PO
                </Link>
                <Link class="btn btn-ghost btn-sm" :href="route('erp.inventory.stock-management')">Manajemen stok</Link>
                <Link class="btn btn-ghost btn-sm" :href="route('erp.inventory.stock-opname')">Stok opname</Link>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
