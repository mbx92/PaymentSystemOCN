<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { computed } from 'vue';

const props = defineProps({
  detail: Object,
  suppliers: Array,
});

const goBack = () => {
  router.visit(route('erp.purchasing.reorder-planning'));
};

const poForm = useForm({
  vendor_code: '',
  order_date: new Date().toISOString().slice(0, 10),
  eta_date: '',
  notes: 'Generated from reorder planning',
  lines: [
    {
      product_id: props.detail.id,
      qty: Number(props.detail.suggested_qty || 1),
      unit_price: Number(props.detail.selling_price || 0),
    },
  ],
});

const estimatedTotal = computed(() =>
  Number(poForm.lines[0].qty || 0) * Number(poForm.lines[0].unit_price || 0),
);

const formatIdr = (value) =>
  new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value ?? 0);

const submitPo = () => {
  poForm.lines[0].product_id = props.detail.id;
  poForm.post(route('erp.purchasing.purchase-orders.store'), {
    preserveScroll: true,
  });
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
                <dt class="text-xs font-semibold uppercase text-base-content/50">Target stok minimum</dt>
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
            <h2 class="card-title text-lg">Buat PO</h2>
            <p class="text-sm text-base-content/70">Pilih supplier dan buat Purchase Order langsung dari perencanaan ini.</p>
            <div class="mt-4 space-y-3">
              <div>
                <label class="label"><span class="label-text">Supplier</span></label>
                <select v-model="poForm.vendor_code" class="select select-bordered w-full" :class="poForm.errors.vendor_code ? 'select-error' : ''">
                  <option value="">Pilih supplier</option>
                  <option v-for="supplier in suppliers" :key="supplier.code" :value="supplier.code">
                    {{ supplier.code }} - {{ supplier.name }}
                  </option>
                </select>
                <p v-if="poForm.errors.vendor_code" class="mt-1 text-xs text-error">{{ poForm.errors.vendor_code }}</p>
              </div>
              <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                  <label class="label"><span class="label-text">Tanggal PO</span></label>
                  <input v-model="poForm.order_date" type="date" class="input input-bordered w-full" :class="poForm.errors.order_date ? 'input-error' : ''" />
                  <p v-if="poForm.errors.order_date" class="mt-1 text-xs text-error">{{ poForm.errors.order_date }}</p>
                </div>
                <div>
                  <label class="label"><span class="label-text">ETA</span></label>
                  <input v-model="poForm.eta_date" type="date" class="input input-bordered w-full" />
                </div>
              </div>
              <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                  <label class="label"><span class="label-text">Qty PO</span></label>
                  <input v-model.number="poForm.lines[0].qty" type="number" min="0.01" step="0.01" class="input input-bordered w-full" :class="poForm.errors['lines.0.qty'] ? 'input-error' : ''" />
                  <p v-if="poForm.errors['lines.0.qty']" class="mt-1 text-xs text-error">{{ poForm.errors['lines.0.qty'] }}</p>
                </div>
                <div>
                  <label class="label"><span class="label-text">Harga Satuan</span></label>
                  <input v-model.number="poForm.lines[0].unit_price" type="number" min="0.01" step="0.01" class="input input-bordered w-full" :class="poForm.errors['lines.0.unit_price'] ? 'input-error' : ''" />
                  <p v-if="poForm.errors['lines.0.unit_price']" class="mt-1 text-xs text-error">{{ poForm.errors['lines.0.unit_price'] }}</p>
                </div>
              </div>
              <div>
                <label class="label"><span class="label-text">Catatan</span></label>
                <textarea v-model="poForm.notes" class="textarea textarea-bordered w-full" rows="2" />
              </div>
              <div class="rounded-xl bg-base-100 p-3 text-sm">
                <span class="text-base-content/60">Estimasi nilai PO:</span>
                <span class="ml-2 font-semibold">{{ formatIdr(estimatedTotal) }}</span>
              </div>
            </div>
            <div class="card-actions mt-4 flex-col items-stretch gap-2">
              <button class="btn btn-primary btn-sm" :disabled="poForm.processing || !poForm.vendor_code" @click="submitPo">
                Buat PO Sekarang
              </button>
              <Link class="btn btn-primary btn-sm" :href="route('erp.master-products.show', detail.id)">
                Detail master produk
              </Link>
              <Link class="btn btn-ghost btn-sm" :href="route('erp.purchasing.suppliers')">Pilih supplier</Link>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
