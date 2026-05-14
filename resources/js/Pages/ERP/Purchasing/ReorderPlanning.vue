<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { computed, reactive, ref, watch } from 'vue';

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

/** @type {import('vue').Ref<number[]>} */
const selectedIds = ref([]);

const selectedRows = computed(() => {
  const list = props.reorderSuggestions ?? [];
  const set = new Set(selectedIds.value);
  return list.filter((r) => set.has(r.id));
});

const allSelected = computed(() => {
  const list = props.reorderSuggestions ?? [];
  return list.length > 0 && list.every((r) => selectedIds.value.includes(r.id));
});

const toggleSelectAll = () => {
  const list = props.reorderSuggestions ?? [];
  if (list.length === 0) return;
  if (allSelected.value) {
    selectedIds.value = [];
  } else {
    selectedIds.value = list.map((r) => r.id);
  }
};

const toggleSelect = (row) => {
  const id = row.id;
  if (selectedIds.value.includes(id)) {
    selectedIds.value = selectedIds.value.filter((x) => x !== id);
  } else {
    selectedIds.value = [...selectedIds.value, id];
  }
};

const isSelected = (row) => selectedIds.value.includes(row.id);

watch(
  () => props.reorderSuggestions,
  (list) => {
    const ids = new Set((list ?? []).map((r) => r.id));
    selectedIds.value = selectedIds.value.filter((id) => ids.has(id));
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
      unit_price: 0.01,
    },
  ],
});

const lineMeta = (productId) => {
  const r = (props.reorderSuggestions ?? []).find((x) => x.id === productId);
  return r ? { sku: r.sku, name: r.name } : { sku: '', name: '' };
};

const defaultUnitPrice = (row) => Math.max(Number(row.selling_price ?? 0), 0.01);

const openAddPlan = (row) => {
  selectedIds.value = [];
  addPlanForm.vendor_code = '';
  addPlanForm.order_date = new Date().toISOString().slice(0, 10);
  addPlanForm.eta_date = '';
  addPlanForm.notes = 'Generated from reorder planning';
  addPlanForm.lines = [
    {
      product_id: row.id,
      qty: Number(row.suggested_qty || 1),
      unit_price: defaultUnitPrice(row),
    },
  ];
  document.getElementById('modal-add-plan-po')?.showModal();
};

const openAddPlanFromSelection = () => {
  const rows = selectedRows.value;
  if (rows.length === 0) return;
  addPlanForm.vendor_code = '';
  addPlanForm.order_date = new Date().toISOString().slice(0, 10);
  addPlanForm.eta_date = '';
  addPlanForm.notes = `Generated from reorder planning (${rows.length} item)`;
  addPlanForm.lines = rows.map((row) => ({
    product_id: row.id,
    qty: Number(row.suggested_qty || 1),
    unit_price: defaultUnitPrice(row),
  }));
  document.getElementById('modal-add-plan-po')?.showModal();
};

const submitPlan = () => {
  addPlanForm.post(route('erp.purchasing.purchase-orders.store'), {
    preserveScroll: true,
    onSuccess: () => {
      addPlanForm.reset();
      addPlanForm.lines = [{ product_id: '', qty: 1, unit_price: 0.01 }];
      selectedIds.value = [];
      document.getElementById('modal-add-plan-po')?.close();
      router.visit(route('erp.purchasing.purchase-orders'));
    },
  });
};

const canSubmitPlan = computed(
  () =>
    !!addPlanForm.vendor_code
    && (addPlanForm.lines?.length ?? 0) > 0
    && addPlanForm.lines.every(
      (l) => l.product_id && Number(l.qty) >= 0.01 && Number(l.unit_price) >= 0.01,
    ),
);
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
              <p class="ocn-panel__desc mt-1">Klik baris untuk detail angka reorder, master produk, dan alur PO. Saran dari min stock, lead time, penjualan 30 hari, dan kekurangan material project (termasuk barang jadi / finished_goods dan material project).</p>
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
          <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <h2 class="ocn-panel__title">Saran reorder</h2>
              <p class="ocn-panel__desc">Produk di bawah minimum, masih kurang untuk project (material project atau finished_goods), atau mendekati lead time.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <button type="button" class="btn btn-outline btn-xs" :disabled="!(reorderSuggestions?.length)" @click="toggleSelectAll">
                {{ allSelected ? 'Batal pilih semua' : 'Pilih semua' }}
              </button>
              <button
                type="button"
                class="btn btn-primary btn-sm"
                :disabled="selectedRows.length === 0"
                @click="openAddPlanFromSelection"
              >
                Buat PO dari pilihan ({{ selectedRows.length }})
              </button>
            </div>
          </div>
        </div>
        <div class="overflow-x-auto">
          <table class="table table-zebra">
            <thead>
              <tr>
                <th class="w-12">
                  <span class="sr-only">Pilih</span>
                </th>
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
                <td @click.stop>
                  <input
                    type="checkbox"
                    class="checkbox checkbox-sm checkbox-primary"
                    :checked="isSelected(row)"
                    :aria-label="`Pilih ${row.sku}`"
                    @change="toggleSelect(row)"
                  />
                </td>
                <td class="font-mono text-xs">{{ row.sku }}</td>
                <td class="font-medium">{{ row.name }}</td>
                <td>{{ row.stock }}</td>
                <td>{{ row.min_stock }}</td>
                <td>{{ row.lead_time_days }}</td>
                <td>{{ row.project_shortage_qty }}</td>
                <td>{{ row.on_order_qty }}</td>
                <td @click.stop>
                  <button type="button" class="badge badge-primary badge-lg font-mono" @click.stop="openAddPlan(row)">
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
        <div class="modal-box max-w-3xl">
          <h3 class="font-bold text-lg">Buat PO dari planning</h3>
          <p class="mt-1 text-sm text-base-content/70">Satu supplier untuk semua baris di bawah. Sesuaikan qty dan harga satuan bila perlu.</p>
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
            <div class="overflow-x-auto rounded-lg border border-base-300 max-h-[min(50vh,22rem)] overflow-y-auto">
              <table class="table table-sm">
                <thead class="sticky top-0 z-1 bg-base-200">
                  <tr>
                    <th>Produk</th>
                    <th class="w-28 text-right">Qty</th>
                    <th class="w-36 text-right">Harga satuan</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(line, idx) in addPlanForm.lines" :key="`${line.product_id}-${idx}`">
                    <td>
                      <p class="font-mono text-xs text-base-content/70">{{ lineMeta(line.product_id).sku }}</p>
                      <p class="text-sm font-medium">{{ lineMeta(line.product_id).name }}</p>
                    </td>
                    <td>
                      <input v-model.number="line.qty" type="number" min="0.01" step="0.01" class="input input-bordered input-sm w-full text-right" />
                    </td>
                    <td>
                      <input v-model.number="line.unit_price" type="number" min="0.01" step="0.01" class="input input-bordered input-sm w-full text-right" />
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          <div class="modal-action">
            <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
            <button type="button" class="btn btn-primary" :disabled="addPlanForm.processing || !canSubmitPlan" @click="submitPlan">Buat PO</button>
          </div>
        </div>
      </dialog>
    </div>
  </AppLayout>
</template>
