<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import DataTablePagination from '@/Components/DataTablePagination.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, reactive, watch } from 'vue';

const props = defineProps({
  receipts: Object,
  poFilter: String,
  filters: Object,
  purchaseOrders: Array,
  warehouses: Array,
});

const openRow = (number) => {
  router.visit(route('erp.purchasing.goods-receipts.show', number));
};

const rowClass = () => 'cursor-pointer transition-colors hover:bg-primary/5';

const filters = reactive({
  po: props.filters?.po ?? '',
  status: props.filters?.status ?? '',
  q: props.filters?.q ?? '',
  per_page: props.filters?.per_page ?? props.receipts?.per_page ?? 25,
});

let timer;
watch(
  filters,
  (val) => {
    clearTimeout(timer);
    timer = setTimeout(() => {
      router.get(route('erp.purchasing.goods-receipts'), val, { preserveState: true, replace: true });
    }, 250);
  },
  { deep: true },
);

const addForm = useForm({
  purchase_order_number: '',
  received_date: new Date().toISOString().slice(0, 10),
  warehouse_id: '',
  status: 'approved',
  lines: [],
});

const selectedPo = computed(() => props.purchaseOrders.find((po) => po.number === addForm.purchase_order_number) ?? null);

watch(
  () => addForm.purchase_order_number,
  (poNumber) => {
    const po = props.purchaseOrders.find((item) => item.number === poNumber);
    addForm.lines = (po?.lines ?? []).map((line) => ({
      product_id: line.product_id,
      qty_received: line.remaining_qty > 0 ? line.remaining_qty : 0,
    }));
  },
);

const submitAdd = () => {
  addForm.post(route('erp.purchasing.goods-receipts.store'), {
    preserveScroll: true,
    onSuccess: () => {
      addForm.reset();
      addForm.received_date = new Date().toISOString().slice(0, 10);
      addForm.warehouse_id = '';
      addForm.status = 'approved';
      addForm.lines = [];
      document.getElementById('modal-add-grn')?.close();
    },
  });
};

const openAddModal = () => {
  document.getElementById('modal-add-grn')?.showModal();
};
</script>

<template>
  <Head title="Purchasing - Penerimaan Barang" />
  <AppLayout>
    <div class="space-y-5">
      <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Purchasing Workspace</p>
        <div class="mt-2 flex items-center justify-between gap-3">
          <h1 class="text-3xl font-bold tracking-tight">Penerimaan Barang (GRN)</h1>
          <Link class="btn btn-ghost btn-sm" :href="route('erp.purchasing')">Back</Link>
        </div>
        <p class="mt-2 text-sm text-base-content/70">
          Klik baris untuk detail barang diterima, posting stok, dan kembali ke PO.
        </p>
      </div>

      <div v-if="poFilter" class="alert alert-info text-sm">
        <span>
          Menyaring GRN untuk PO
          <span class="font-mono font-semibold">{{ poFilter }}</span>.
          <Link class="link link-primary ml-1" :href="route('erp.purchasing.goods-receipts')">Hapus filter</Link>
        </span>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Filter penerimaan barang</h2>
        </div>
        <div class="card-body">
          <div class="flex flex-wrap items-end gap-3">
            <div class="min-w-[200px]">
              <label class="label"><span class="label-text text-xs uppercase tracking-wide">Purchase Order</span></label>
              <select v-model="filters.po" class="select select-sm select-bordered w-full">
                <option value="">Semua</option>
                <option v-for="po in purchaseOrders" :key="po.number" :value="po.number">
                  {{ po.number }}
                </option>
              </select>
            </div>
            <div class="min-w-[150px]">
              <label class="label"><span class="label-text text-xs uppercase tracking-wide">Status</span></label>
              <select v-model="filters.status" class="select select-sm select-bordered w-full">
                <option value="">Semua</option>
                <option value="approved">Approved</option>
                <option value="posted">Posted</option>
              </select>
            </div>
            <div class="min-w-[220px] flex-1">
              <label class="label"><span class="label-text text-xs uppercase tracking-wide">Search</span></label>
              <input v-model="filters.q" type="text" class="input input-sm input-bordered w-full" placeholder="Nomor GRN / nomor PO" />
            </div>
            <button class="btn btn-primary btn-sm ml-auto" @click="openAddModal">+ Add GRN</button>
          </div>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Daftar goods receipt (GRN)</h2>
        </div>
        <div class="overflow-x-auto">
          <table class="table table-zebra">
            <thead>
              <tr>
                <th>No. GRN</th>
                <th>Referensi PO</th>
                <th>Tanggal Terima</th>
                <th>Item</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="r in (receipts?.data || [])"
                :key="r.number"
                :class="rowClass()"
                tabindex="0"
                role="button"
                @click="openRow(r.number)"
                @keydown.enter.prevent="openRow(r.number)"
              >
                <td class="font-mono text-xs font-semibold">{{ r.number }}</td>
                <td class="font-mono text-xs">{{ r.po_number }}</td>
                <td>{{ r.received_date }}</td>
                <td>{{ r.items }}</td>
                <td @click.stop><StatusBadge :status="r.status" /></td>
              </tr>
              <tr v-if="!(receipts?.data || []).length">
                <td colspan="5" class="py-8 text-center text-base-content/50">Tidak ada goods receipt.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <DataTablePagination :paginator="receipts" @update:per-page="(n) => { filters.per_page = n; }" />
      </div>

      <dialog id="modal-add-grn" class="modal">
        <div class="modal-box max-w-3xl">
          <h3 class="font-bold text-lg">Tambah Penerimaan Barang (GRN)</h3>
          <div class="mt-4 grid grid-cols-1 gap-3">
            <div>
              <label class="label"><span class="label-text">Nomor PO</span></label>
              <select v-model="addForm.purchase_order_number" class="select select-bordered w-full">
                <option value="">Pilih PO</option>
                <option v-for="po in purchaseOrders" :key="po.number" :value="po.number">{{ po.number }}</option>
              </select>
            </div>
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
              <div>
                <label class="label"><span class="label-text">Tanggal Terima</span></label>
                <input v-model="addForm.received_date" type="date" class="input input-bordered w-full" />
              </div>
              <div>
                <label class="label"><span class="label-text">Status Awal</span></label>
                <select v-model="addForm.status" class="select select-bordered w-full">
                  <option value="approved">Approved</option>
                </select>
              </div>
            </div>
            <div>
              <label class="label"><span class="label-text">Warehouse</span></label>
              <select v-model="addForm.warehouse_id" class="select select-bordered w-full">
                <option value="">Pilih warehouse</option>
                <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.code }} - {{ w.name }}</option>
              </select>
            </div>
            <div class="rounded-xl border border-base-200">
              <div class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-base-content/60">
                Detail Qty Terima (Partial GRN)
              </div>
              <div class="overflow-x-auto">
                <table class="table table-sm">
                  <thead>
                    <tr>
                      <th>SKU</th>
                      <th>Produk</th>
                      <th class="text-right">Ordered</th>
                      <th class="text-right">Received</th>
                      <th class="text-right">Sisa</th>
                      <th class="text-right">Qty Terima</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(line, idx) in selectedPo?.lines ?? []" :key="`${line.product_id}-${idx}`">
                      <td class="font-mono text-xs">{{ line.sku }}</td>
                      <td>{{ line.name }}</td>
                      <td class="text-right">{{ line.ordered_qty }}</td>
                      <td class="text-right">{{ line.received_qty }}</td>
                      <td class="text-right font-semibold">{{ line.remaining_qty }}</td>
                      <td class="text-right">
                        <input
                          v-model.number="addForm.lines[idx].qty_received"
                          type="number"
                          min="0"
                          :max="line.remaining_qty"
                          step="0.01"
                          class="input input-bordered input-sm w-28 text-right"
                        />
                      </td>
                    </tr>
                    <tr v-if="!(selectedPo?.lines?.length)">
                      <td colspan="6" class="py-4 text-center text-sm text-base-content/60">Pilih PO untuk isi qty terima per item.</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          <div class="modal-action">
            <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
            <button class="btn btn-primary" :disabled="addForm.processing" @click="submitAdd">Simpan</button>
          </div>
        </div>
      </dialog>
    </div>
  </AppLayout>
</template>
