<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { reactive, watch } from 'vue';

const props = defineProps({
  purchaseOrders: Array,
  supplierFilter: String,
  filters: Object,
  suppliers: Array,
});

const formatIdr = (n) =>
  new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n ?? 0);

const openRow = (number) => {
  router.visit(route('erp.purchasing.purchase-orders.show', number));
};

const rowClass = () => 'cursor-pointer transition-colors hover:bg-primary/5';

const filters = reactive({
  supplier: props.filters?.supplier ?? '',
  status: props.filters?.status ?? '',
  q: props.filters?.q ?? '',
});

let timer;
watch(
  filters,
  (val) => {
    clearTimeout(timer);
    timer = setTimeout(() => {
      router.get(route('erp.purchasing.purchase-orders'), val, { preserveState: true, replace: true });
    }, 250);
  },
  { deep: true },
);

const addForm = useForm({
  vendor_code: '',
  order_date: new Date().toISOString().slice(0, 10),
  eta_date: '',
  product_id: '',
  qty: 1,
  unit_price: 1,
});

const submitAdd = () => {
  addForm.post(route('erp.purchasing.purchase-orders.store'), {
    preserveScroll: true,
    onSuccess: () => {
      addForm.reset();
      addForm.order_date = new Date().toISOString().slice(0, 10);
      document.getElementById('modal-add-po')?.close();
    },
  });
};
</script>

<template>
  <Head title="Purchasing - Purchase Order" />
  <AppLayout>
    <div class="space-y-5">
      <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Purchasing Workspace</p>
        <div class="mt-2 flex items-center justify-between gap-3">
          <h1 class="text-3xl font-bold tracking-tight">Purchase Order</h1>
          <Link class="btn btn-ghost btn-sm" :href="route('erp.purchasing')">Back</Link>
        </div>
        <p class="mt-2 text-sm text-base-content/70">
          Klik baris untuk detail baris item, persetujuan (simulasi), dan penerimaan barang.
        </p>
      </div>

      <div v-if="supplierFilter" class="alert alert-info text-sm">
        <span>
          Menyaring PO untuk supplier
          <span class="font-mono font-semibold">{{ supplierFilter }}</span>.
          <Link class="link link-primary ml-1" :href="route('erp.purchasing.purchase-orders')">Hapus filter</Link>
        </span>
      </div>

      <div class="card bg-base-100 shadow">
        <div class="card-body">
          <div class="flex flex-wrap items-end gap-3">
            <div class="min-w-[180px]">
              <label class="label"><span class="label-text text-xs uppercase tracking-wide">Supplier</span></label>
              <select v-model="filters.supplier" class="select select-sm select-bordered w-full">
                <option value="">Semua</option>
                <option v-for="supplier in suppliers" :key="supplier.code" :value="supplier.code">
                  {{ supplier.code }} - {{ supplier.name }}
                </option>
              </select>
            </div>
            <div class="min-w-[150px]">
              <label class="label"><span class="label-text text-xs uppercase tracking-wide">Status</span></label>
              <select v-model="filters.status" class="select select-sm select-bordered w-full">
                <option value="">Semua</option>
                <option value="draft">Draft</option>
                <option value="approved">Approved</option>
                <option value="posted">Posted</option>
                <option value="void">Void</option>
              </select>
            </div>
            <div class="min-w-[220px] flex-1">
              <label class="label"><span class="label-text text-xs uppercase tracking-wide">Search</span></label>
              <input v-model="filters.q" type="text" class="input input-sm input-bordered w-full" placeholder="Nomor PO / nama supplier" />
            </div>
            <button class="btn btn-primary btn-sm ml-auto" onclick="modal-add-po.showModal()">+ Add PO</button>
          </div>
        </div>
      </div>

      <div class="card bg-base-100 shadow">
        <div class="overflow-x-auto">
          <table class="table table-zebra">
            <thead>
              <tr>
                <th>Nomor PO</th>
                <th>Supplier</th>
                <th>ETA</th>
                <th>Nilai</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="po in purchaseOrders"
                :key="po.number"
                :class="rowClass()"
                tabindex="0"
                role="button"
                @click="openRow(po.number)"
                @keydown.enter.prevent="openRow(po.number)"
              >
                <td class="font-mono text-xs font-semibold">{{ po.number }}</td>
                <td>{{ po.supplier }}</td>
                <td>{{ po.eta }}</td>
                <td>{{ formatIdr(po.amount) }}</td>
                <td @click.stop><StatusBadge :status="po.status" /></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <dialog id="modal-add-po" class="modal">
        <div class="modal-box max-w-2xl">
          <h3 class="font-bold text-lg">Tambah Purchase Order</h3>
          <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
            <div class="md:col-span-2">
              <label class="label"><span class="label-text">Supplier</span></label>
              <select v-model="addForm.vendor_code" class="select select-bordered w-full">
                <option value="">Pilih supplier</option>
                <option v-for="supplier in suppliers" :key="supplier.code" :value="supplier.code">
                  {{ supplier.code }} - {{ supplier.name }}
                </option>
              </select>
            </div>
            <div>
              <label class="label"><span class="label-text">Tanggal PO</span></label>
              <input v-model="addForm.order_date" type="date" class="input input-bordered w-full" />
            </div>
            <div>
              <label class="label"><span class="label-text">ETA</span></label>
              <input v-model="addForm.eta_date" type="date" class="input input-bordered w-full" />
            </div>
            <div>
              <label class="label"><span class="label-text">ID Produk</span></label>
              <input v-model="addForm.product_id" type="number" min="1" class="input input-bordered w-full" />
            </div>
            <div>
              <label class="label"><span class="label-text">Qty</span></label>
              <input v-model="addForm.qty" type="number" min="0.01" step="0.01" class="input input-bordered w-full" />
            </div>
            <div>
              <label class="label"><span class="label-text">Harga Satuan</span></label>
              <input v-model="addForm.unit_price" type="number" min="1" step="0.01" class="input input-bordered w-full" />
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
