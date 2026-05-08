<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { reactive, watch } from 'vue';

const props = defineProps({
  suppliers: Array,
  highlight: String,
  filters: Object,
});

const filters = reactive({
  q: props.filters?.q ?? '',
  status: props.filters?.status ?? '',
});

let timer;
watch(
  filters,
  (val) => {
    clearTimeout(timer);
    timer = setTimeout(() => {
      router.get(route('erp.purchasing.suppliers'), val, { preserveState: true, replace: true });
    }, 250);
  },
  { deep: true },
);

const addForm = useForm({
  name: '',
  phone: '',
  email: '',
  lead_time_days: 7,
});

const openRow = (code) => {
  router.visit(route('erp.purchasing.suppliers.show', code));
};

const rowClass = (code) => {
  const base = 'cursor-pointer transition-colors hover:bg-primary/5';
  if (props.highlight && props.highlight === code) {
    return `${base} bg-primary/10 ring-1 ring-primary/30`;
  }
  return base;
};

const submitAdd = () => {
  addForm.post(route('erp.purchasing.suppliers.store'), {
    preserveScroll: true,
    onSuccess: () => {
      addForm.reset();
      document.getElementById('modal-add-supplier')?.close();
    },
  });
};

const openAddModal = () => {
  document.getElementById('modal-add-supplier')?.showModal();
};
</script>

<template>
  <Head title="Purchasing - Manajemen Supplier" />
  <AppLayout>
    <div class="space-y-5">
      <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Purchasing Workspace</p>
        <div class="mt-2 flex items-center justify-between gap-3">
          <h1 class="text-3xl font-bold tracking-tight">Manajemen Supplier</h1>
          <Link class="btn btn-ghost btn-sm" :href="route('erp.purchasing')">Back</Link>
        </div>
        <p class="mt-2 text-sm text-base-content/70">
          Klik baris untuk detail dan langkah berikutnya. Data supplier untuk proses pembelian dan evaluasi lead time.
        </p>
      </div>

      <div v-if="highlight" class="alert alert-info text-sm">
        <span>Supplier <span class="font-mono font-semibold">{{ highlight }}</span> disorot — lanjutkan dari PO atau profil.</span>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Filter supplier</h2>
        </div>
        <div class="card-body">
          <div class="flex flex-wrap items-end gap-3">
            <div class="min-w-[220px] flex-1">
              <label class="label"><span class="label-text text-xs uppercase tracking-wide">Search</span></label>
              <input v-model="filters.q" type="text" class="input input-sm input-bordered w-full" placeholder="Kode / nama / kontak" />
            </div>
            <div class="min-w-[160px]">
              <label class="label"><span class="label-text text-xs uppercase tracking-wide">Status</span></label>
              <select v-model="filters.status" class="select select-sm select-bordered w-full">
                <option value="">Semua</option>
                <option value="active">Active</option>
                <option value="void">Non Active</option>
              </select>
            </div>
            <button class="btn btn-primary btn-sm ml-auto" @click="openAddModal">+ Add Supplier</button>
          </div>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Daftar supplier</h2>
        </div>
        <div class="overflow-x-auto">
          <table class="table table-zebra">
            <thead><tr><th>Kode</th><th>Nama Supplier</th><th>Kontak</th><th>Lead Time</th><th>Status</th></tr></thead>
            <tbody>
              <tr
                v-for="supplier in suppliers"
                :key="supplier.code"
                :class="rowClass(supplier.code)"
                tabindex="0"
                role="button"
                @click="openRow(supplier.code)"
                @keydown.enter.prevent="openRow(supplier.code)"
              >
                <td class="font-mono text-xs">{{ supplier.code }}</td>
                <td class="font-semibold">{{ supplier.name }}</td>
                <td>{{ supplier.phone }}</td>
                <td>{{ supplier.lead_time_days }} hari</td>
                <td @click.stop><StatusBadge :status="supplier.status" /></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <dialog id="modal-add-supplier" class="modal">
        <div class="modal-box max-w-xl">
          <h3 class="font-bold text-lg">Tambah Supplier</h3>
          <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
            <div class="md:col-span-2">
              <label class="label"><span class="label-text">Nama Supplier</span></label>
              <input v-model="addForm.name" class="input input-bordered w-full" />
            </div>
            <div>
              <label class="label"><span class="label-text">Telepon</span></label>
              <input v-model="addForm.phone" class="input input-bordered w-full" />
            </div>
            <div>
              <label class="label"><span class="label-text">Email</span></label>
              <input v-model="addForm.email" class="input input-bordered w-full" />
            </div>
            <div>
              <label class="label"><span class="label-text">Lead Time (hari)</span></label>
              <input v-model="addForm.lead_time_days" type="number" min="1" class="input input-bordered w-full" />
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
