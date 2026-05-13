<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
  detail: Object,
});

const goBack = () => {
  router.visit(route('erp.purchasing.reorder-planning'));
};
</script>

<template>
  <Head :title="`Reorder — ${detail.sku}`" />
  <AppLayout>
    <div class="space-y-5">
      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Purchasing · Perencanaan reorder</p>
              <h1 class="ocn-panel__title mt-1">{{ detail.name }}</h1>
              <p class="mt-1 font-mono text-sm text-base-content/70">{{ detail.sku }} · UoM {{ detail.uom }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
              <button type="button" class="btn btn-ghost btn-sm shrink-0 gap-1.5" @click="goBack"><ArrowLeftIcon class="h-4 w-4" />
            Back</button>
            </div>
          </div>
        </div>
      </div>

      <div class="grid gap-5 lg:grid-cols-3">
        <div class="ocn-panel lg:col-span-2">
          <div class="ocn-panel__head">
            <h2 class="ocn-panel__title">Ringkasan kebutuhan</h2>
          </div>
          <div class="card-body">
            <dl class="mt-2 grid gap-4 sm:grid-cols-2">
              <div>
                <dt class="text-xs font-semibold uppercase text-base-content/50">Stok saat ini</dt>
                <dd class="text-2xl font-bold">{{ detail.stock }}</dd>
              </div>
              <div>
                <dt class="text-xs font-semibold uppercase text-base-content/50">Minimum stok</dt>
                <dd class="text-2xl font-bold">{{ detail.min_stock }}</dd>
              </div>
              <div>
                <dt class="text-xs font-semibold uppercase text-base-content/50">Terjual (angka mentah)</dt>
                <dd>{{ detail.total_sold }}</dd>
              </div>
              <div>
                <dt class="text-xs font-semibold uppercase text-base-content/50">Est. pemakaian/hari (30 hari)</dt>
                <dd>{{ detail.daily_usage_est }}</dd>
              </div>
              <div>
                <dt class="text-xs font-semibold uppercase text-base-content/50">Lead time</dt>
                <dd>{{ detail.lead_time_days }} hari</dd>
              </div>
              <div>
                <dt class="text-xs font-semibold uppercase text-base-content/50">Target stok (min + kebutuhan lead)</dt>
                <dd class="font-semibold">{{ detail.target_stock }}</dd>
              </div>
              <div>
                <dt class="text-xs font-semibold uppercase text-base-content/50">Kurang material project</dt>
                <dd class="font-semibold">{{ detail.project_shortage_qty }}</dd>
              </div>
              <div>
                <dt class="text-xs font-semibold uppercase text-base-content/50">Masih on order</dt>
                <dd class="font-semibold">{{ detail.on_order_qty }}</dd>
              </div>
              <div class="sm:col-span-2">
                <dt class="text-xs font-semibold uppercase text-base-content/50">Saran qty pembelian</dt>
                <dd><span class="badge badge-primary badge-lg font-mono">{{ detail.suggested_qty }}</span></dd>
              </div>
            </dl>
          </div>
        </div>

        <div class="card border border-primary/20 bg-primary/5 shadow">
          <div class="card-body">
            <h2 class="card-title text-lg">Lanjutkan proses</h2>
            <p class="text-sm text-base-content/70">Tindak lanjut ke master data dan pembuatan PO.</p>
            <div class="card-actions mt-4 flex-col items-stretch gap-2">
              <Link class="btn btn-primary btn-sm" :href="route('erp.master-products.show', detail.id)">
                Detail master produk
              </Link>
              <Link class="btn btn-outline btn-sm" :href="route('erp.purchasing.purchase-orders')">Buat / kelola PO</Link>
              <Link class="btn btn-ghost btn-sm" :href="route('erp.purchasing.suppliers')">Pilih supplier</Link>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
