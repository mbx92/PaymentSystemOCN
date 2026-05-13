<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { reactive, watch } from 'vue';

const props = defineProps({
  reorderSuggestions: Array,
  filters: Object,
  suppliers: Array,
});

const openRow = (id) => {
  router.visit(route('erp.purchasing.reorder-planning.show', id));
};

const rowClass = () => 'cursor-pointer transition-colors hover:bg-primary/5';

const filters = reactive({
  q: props.filters?.q ?? '',
});

let timer;
watch(
  filters,
  (val) => {
    clearTimeout(timer);
    timer = setTimeout(() => {
      router.get(route('erp.purchasing.reorder-planning'), val, { preserveState: true, replace: true });
    }, 250);
  },
  { deep: true },
);

const addPlanForm = useForm({
  vendor_code: '',
  order_date: new Date().toISOString().slice(0, 10),
  eta_date: '',
  notes: 'Generated from reorder planning',
  lines: [
    {
      product_id: '',
      qty: 1,
      unit_price: 0,
    },
  ],
});

const openAddPlan = (row) => {
  addPlanForm.vendor_code = '';
  addPlanForm.order_date = new Date().toISOString().slice(0, 10);
  addPlanForm.eta_date = '';
  addPlanForm.notes = 'Generated from reorder planning';
  addPlanForm.lines = [
    {
      product_id: row.id,
      qty: Number(row.suggested_qty || 1),
      unit_price: Number(row.selling_price || 0),
    },
  ];
  document.getElementById('modal-add-plan-po')?.showModal();
};

const submitPlan = () => {
  addPlanForm.post(route('erp.purchasing.purchase-orders.store'), {
    preserveScroll: true,
    onSuccess: () => {
      addPlanForm.reset();
      addPlanForm.lines = [{ product_id: '', qty: 1, unit_price: 0 }];
      document.getElementById('modal-add-plan-po')?.close();
      router.visit(route('erp.purchasing.purchase-orders'));
    },
  });
};
</script>

<template>
  <Head title="Purchasing - Perencanaan Reorder" />
  <AppLayout>
    <div class="space-y-5">
      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Purchasing Workspace</p>
              <h1 class="ocn-panel__title mt-1">Perencanaan Reorder</h1>
              <p class="ocn-panel__desc mt-1">Klik baris untuk detail angka reorder, master produk, dan alur PO. Saran dari min stock, lead time, penjualan 30 hari, dan kekurangan material project.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
              <Link class="btn btn-ghost btn-sm shrink-0 gap-1.5" :href="route('erp.purchasing')">
              <ArrowLeftIcon class="h-4 w-4" />
              Back
            </Link>
            </div>
          </div>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Filter reorder</h2>
        </div>
        <div class="card-body">
          <div class="flex flex-wrap items-end gap-3">
            <div class="min-w-[260px] flex-1">
              <label class="label"><span class="label-text text-xs uppercase tracking-wide">Search</span></label>
              <input v-model="filters.q" type="text" class="input input-sm input-bordered w-full" placeholder="SKU / nama produk" />
            </div>
            <Link class="btn btn-primary btn-sm ml-auto" :href="route('erp.purchasing.purchase-orders')">+ Add PO</Link>
          </div>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Saran reorder</h2>
          <p class="ocn-panel__desc">Produk di bawah minimum, masih kurang untuk project, atau mendekati lead time.</p>
        </div>
        <div class="overflow-x-auto">
          <table class="table table-zebra">
            <thead>
              <tr>
                <th>SKU</th>
                <th>Produk</th>
                <th>Stok</th>
                <th>Min</th>
                <th>Lead (hari)</th>
                <th>Kurang Project</th>
                <th>On Order</th>
                <th>Saran Qty</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in reorderSuggestions"
                :key="row.id"
                :class="rowClass()"
                tabindex="0"
                role="button"
                @click="openRow(row.id)"
                @keydown.enter.prevent="openRow(row.id)"
              >
                <td class="font-mono text-xs">{{ row.sku }}</td>
                <td class="font-medium">{{ row.name }}</td>
                <td>{{ row.stock }}</td>
                <td>{{ row.min_stock }}</td>
                <td>{{ row.lead_time_days }}</td>
                <td>{{ row.project_shortage_qty }}</td>
                <td>{{ row.on_order_qty }}</td>
                <td @click.stop>
                  <button class="badge badge-primary badge-lg font-mono" @click.stop="openAddPlan(row)">
                    {{ row.suggested_qty }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <p v-if="!reorderSuggestions?.length" class="rounded-2xl border border-slate-200 bg-white p-6 text-center text-sm text-base-content/60 shadow-sm">
        Tidak ada saran reorder saat ini (stok di atas target).
      </p>

      <dialog id="modal-add-plan-po" class="modal">
        <div class="modal-box max-w-xl">
          <h3 class="font-bold text-lg">Buat PO dari Planning</h3>
          <div class="mt-4 grid grid-cols-1 gap-3">
            <div>
              <label class="label"><span class="label-text">Supplier</span></label>
              <select v-model="addPlanForm.vendor_code" class="select select-bordered w-full">
                <option value="">Pilih supplier</option>
                <option v-for="supplier in suppliers" :key="supplier.code" :value="supplier.code">
                  {{ supplier.code }} - {{ supplier.name }}
                </option>
              </select>
            </div>
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
              <div>
                <label class="label"><span class="label-text">Tanggal PO</span></label>
                <input v-model="addPlanForm.order_date" type="date" class="input input-bordered w-full" />
              </div>
              <div>
                <label class="label"><span class="label-text">ETA</span></label>
                <input v-model="addPlanForm.eta_date" type="date" class="input input-bordered w-full" />
              </div>
            </div>
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
              <div>
                <label class="label"><span class="label-text">Qty</span></label>
                <input v-model.number="addPlanForm.lines[0].qty" type="number" min="0.01" step="0.01" class="input input-bordered w-full" />
              </div>
              <div>
                <label class="label"><span class="label-text">Harga Satuan</span></label>
                <input v-model.number="addPlanForm.lines[0].unit_price" type="number" min="0.01" step="0.01" class="input input-bordered w-full" />
              </div>
            </div>
          </div>
          <div class="modal-action">
            <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
            <button class="btn btn-primary" :disabled="addPlanForm.processing" @click="submitPlan">Buat PO</button>
          </div>
        </div>
      </dialog>
    </div>
  </AppLayout>
</template>
