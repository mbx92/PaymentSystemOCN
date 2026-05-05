<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
  detail: Object,
});

const poListUrl = () =>
  `${route('erp.purchasing.purchase-orders')}?supplier=${encodeURIComponent(props.detail.code)}`;

const goBack = () => {
  router.visit(route('erp.purchasing.suppliers'));
};
</script>

<template>
  <Head :title="`Supplier — ${detail.name}`" />
  <AppLayout>
    <div class="space-y-5">
      <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Purchasing · Supplier</p>
        <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
          <div>
            <h1 class="text-3xl font-bold tracking-tight">{{ detail.name }}</h1>
            <p class="mt-1 font-mono text-sm text-base-content/70">{{ detail.code }}</p>
          </div>
          <button type="button" class="btn btn-ghost btn-sm" @click="goBack">Back</button>
        </div>
      </div>

      <div class="grid gap-5 lg:grid-cols-3">
        <div class="card bg-base-100 shadow lg:col-span-2">
          <div class="card-body">
            <h2 class="card-title text-lg">Informasi</h2>
            <dl class="grid gap-3 sm:grid-cols-2">
              <div>
                <dt class="text-xs font-semibold uppercase text-base-content/50">Telepon</dt>
                <dd>{{ detail.phone }}</dd>
              </div>
              <div>
                <dt class="text-xs font-semibold uppercase text-base-content/50">Email</dt>
                <dd>{{ detail.email }}</dd>
              </div>
              <div class="sm:col-span-2">
                <dt class="text-xs font-semibold uppercase text-base-content/50">Alamat</dt>
                <dd>{{ detail.address }}</dd>
              </div>
              <div>
                <dt class="text-xs font-semibold uppercase text-base-content/50">NPWP</dt>
                <dd class="font-mono text-sm">{{ detail.tax_id }}</dd>
              </div>
              <div>
                <dt class="text-xs font-semibold uppercase text-base-content/50">Termin bayar</dt>
                <dd>{{ detail.payment_terms }}</dd>
              </div>
              <div>
                <dt class="text-xs font-semibold uppercase text-base-content/50">Lead time</dt>
                <dd>{{ detail.lead_time_days }} hari</dd>
              </div>
              <div>
                <dt class="text-xs font-semibold uppercase text-base-content/50">Status</dt>
                <dd><StatusBadge :status="detail.status" /></dd>
              </div>
              <div class="sm:col-span-2">
                <dt class="text-xs font-semibold uppercase text-base-content/50">Catatan</dt>
                <dd class="text-sm text-base-content/80">{{ detail.notes }}</dd>
              </div>
            </dl>
          </div>
        </div>

        <div class="card border border-primary/20 bg-primary/5 shadow">
          <div class="card-body">
            <h2 class="card-title text-lg">Lanjutkan proses</h2>
            <p class="text-sm text-base-content/70">Hubungkan supplier dengan alur pembelian.</p>
            <div class="card-actions mt-4 flex-col items-stretch gap-2">
              <Link class="btn btn-primary btn-sm" :href="poListUrl()">Purchase Order untuk supplier ini</Link>
              <Link class="btn btn-outline btn-sm" :href="route('erp.purchasing.reorder-planning')">
                Perencanaan reorder
              </Link>
              <Link class="btn btn-ghost btn-sm" :href="route('erp.purchasing.goods-receipts')">Penerimaan barang</Link>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
