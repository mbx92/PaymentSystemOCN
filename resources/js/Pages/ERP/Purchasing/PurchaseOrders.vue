<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import DataTablePagination from '@/Components/DataTablePagination.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { reactive, ref, watch } from 'vue';
import { showGlobalAlert } from '@/utils/globalAlert';

const props = defineProps({
  purchaseOrders: Object,
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
  per_page: props.filters?.per_page ?? props.purchaseOrders?.per_page ?? 25,
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
  notes: '',
});

const attemptedSubmit = ref(false);

const mandatoryInputClass = (isInvalid) => ({
  'input-error border-error hover:border-error focus:border-error focus:outline-error': isInvalid,
});

const mandatorySelectClass = (isInvalid) => ({
  'select-error border-error hover:border-error focus:border-error focus:outline-error': isInvalid,
});

const openAddModal = () => {
  document.getElementById('modal-add-po')?.showModal();
};

const submitAdd = () => {
  attemptedSubmit.value = true;

  if (!String(addForm.vendor_code || '').trim()) {
    showGlobalAlert('Supplier wajib dipilih sebelum menyimpan Purchase Order.', 'warning');
    return;
  }
  if (!String(addForm.order_date || '').trim()) {
    showGlobalAlert('Tanggal PO wajib diisi.', 'warning');
    return;
  }

  addForm.post(route('erp.purchasing.purchase-orders.store'), {
    preserveScroll: true,
    onSuccess: () => {
      attemptedSubmit.value = false;
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
      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Purchasing Workspace</p>
              <h1 class="ocn-panel__title mt-1">Purchase Order</h1>
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

      <div v-if="supplierFilter" class="alert alert-info text-sm">
        <span>Menyaring PO untuk supplier <span class="font-mono font-semibold">{{ supplierFilter }}</span>.</span>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Filter purchase order</h2>
        </div>
        <div class="card-body">
          <div class="flex flex-wrap items-end gap-3">
            <div class="min-w-[180px]">
              <label class="label"><span class="label-text text-xs uppercase tracking-wide">Supplier</span></label>
              <select v-model="filters.supplier" class="select select-sm select-bordered w-full">
                <option value="">Semua</option>
                <option v-for="supplier in suppliers" :key="supplier.code" :value="supplier.code">{{ supplier.code }} - {{ supplier.name }}</option>
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
            <button class="btn btn-primary btn-sm ml-auto" @click="openAddModal">+ Add PO</button>
          </div>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Daftar purchase order</h2>
        </div>
        <div class="overflow-x-auto">
          <table class="table table-zebra">
            <thead><tr><th>Nomor PO</th><th>Supplier</th><th>ETA</th><th>Nilai</th><th>Status</th></tr></thead>
            <tbody>
              <tr v-for="po in (purchaseOrders?.data || [])" :key="po.number" :class="rowClass()" tabindex="0" role="button" @click="openRow(po.number)" @keydown.enter.prevent="openRow(po.number)">
                <td class="font-mono text-xs font-semibold">{{ po.number }}</td>
                <td>{{ po.supplier }}</td>
                <td>{{ po.eta }}</td>
                <td>{{ formatIdr(po.amount) }}</td>
                <td @click.stop><StatusBadge :status="po.status" /></td>
              </tr>
              <tr v-if="!(purchaseOrders?.data || []).length">
                <td colspan="5" class="py-8 text-center text-base-content/50">Tidak ada purchase order.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <DataTablePagination :paginator="purchaseOrders" @update:per-page="(n) => { filters.per_page = n; }" />
      </div>

      <dialog id="modal-add-po" class="modal">
        <div class="modal-box max-w-2xl">
          <h3 class="font-bold text-lg">Tambah Purchase Order</h3>
          <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
            <div class="md:col-span-2">
              <label class="label"><span class="label-text">Supplier</span></label>
              <select
                v-model="addForm.vendor_code"
                class="select select-bordered w-full"
                :class="mandatorySelectClass(attemptedSubmit && !String(addForm.vendor_code || '').trim())"
              >
                <option value="">Pilih supplier</option>
                <option v-for="supplier in suppliers" :key="supplier.code" :value="supplier.code">{{ supplier.code }} - {{ supplier.name }}</option>
              </select>
            </div>
            <div>
              <label class="label"><span class="label-text">Tanggal PO</span></label>
              <input
                v-model="addForm.order_date"
                type="date"
                class="input input-bordered w-full"
                :class="mandatoryInputClass(attemptedSubmit && !String(addForm.order_date || '').trim())"
              />
            </div>
            <div>
              <label class="label"><span class="label-text">ETA</span></label>
              <input v-model="addForm.eta_date" type="date" class="input input-bordered w-full" />
            </div>
            <div class="md:col-span-2">
              <label class="label"><span class="label-text">Catatan</span></label>
              <input v-model="addForm.notes" class="input input-bordered w-full" placeholder="Opsional" />
            </div>
          </div>

          <div class="mt-4 rounded-xl border border-info/20 bg-info/5 p-3 text-sm text-base-content/70">
            Item PO diinput setelah PO dibuat. Buka detail PO, pilih item, lalu simpan ke database setelah draft item sudah benar.
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
