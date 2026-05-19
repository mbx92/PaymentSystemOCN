<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import DataTablePagination from '@/Components/DataTablePagination.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { reactive, ref, watch } from 'vue';

const props = defineProps({
  warehouses: Object,
  filters: Object,
});

const filters = reactive({
  q: props.filters?.q ?? '',
  status: props.filters?.status ?? '',
  per_page: props.filters?.per_page ?? props.warehouses?.per_page ?? 25,
});

let timer;
watch(filters, (val) => {
  clearTimeout(timer);
  timer = setTimeout(() => {
    router.get(route('erp.inventory.warehouses'), val, {
      preserveState: true,
      replace: true,
    });
  }, 250);
}, { deep: true });

const form = useForm({
  code: '',
  name: '',
  address: '',
  status: 'active',
});

const editForm = useForm({
  code: '',
  name: '',
  address: '',
  status: 'active',
});
const editingWarehouseId = ref(null);

const resetAddForm = () => {
  form.reset();
  form.status = 'active';
};

const openAddModal = () => {
  resetAddForm();
  document.getElementById('modal-add-warehouse')?.showModal();
};

const submitAdd = () => {
  form.post(route('erp.inventory.warehouses.store'), {
    preserveScroll: true,
    onSuccess: () => {
      resetAddForm();
      document.getElementById('modal-add-warehouse')?.close();
    },
  });
};

const openEditModal = (warehouse) => {
  editingWarehouseId.value = warehouse.id;
  editForm.code = warehouse.code;
  editForm.name = warehouse.name;
  editForm.address = warehouse.address || '';
  editForm.status = warehouse.status;
  document.getElementById('modal-edit-warehouse')?.showModal();
};

const submitEdit = () => {
  if (!editingWarehouseId.value) return;
  editForm.patch(route('erp.inventory.warehouses.update', editingWarehouseId.value), {
    preserveScroll: true,
    onSuccess: () => document.getElementById('modal-edit-warehouse')?.close(),
  });
};
</script>

<template>
  <Head title="Inventory - Warehouse" />
  <AppLayout>
    <div class="space-y-5">
      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Inventory Workspace</p>
              <h1 class="ocn-panel__title mt-1">Warehouse</h1>
              <p class="ocn-panel__desc mt-1">Master warehouse untuk operasional stok, POS, dan purchasing.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
              <Link class="btn btn-ghost btn-sm shrink-0 gap-1.5" :href="route('erp.inventory')">
              <ArrowLeftIcon class="h-4 w-4" />
              Back
            </Link>
            </div>
          </div>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Filter warehouse</h2>
        </div>
        <div class="card-body">
          <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
            <div class="md:col-span-2">
              <label class="label"><span class="label-text text-xs uppercase tracking-wide">Search</span></label>
              <input v-model="filters.q" type="text" class="input input-sm input-bordered w-full" placeholder="Code / nama / alamat warehouse" />
            </div>
            <div>
              <label class="label"><span class="label-text text-xs uppercase tracking-wide">Status</span></label>
              <select v-model="filters.status" class="select select-sm select-bordered w-full">
                <option value="">Semua</option>
                <option value="active">active</option>
                <option value="inactive">inactive</option>
              </select>
            </div>
            <div class="flex items-end">
              <button class="btn btn-primary btn-sm w-full" @click="openAddModal">+ Tambah Warehouse</button>
            </div>
          </div>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Daftar warehouse</h2>
        </div>
        <div class="overflow-x-auto">
          <table class="table table-zebra">
            <thead>
              <tr>
                <th>Code</th>
                <th>Nama Warehouse</th>
                <th>Alamat</th>
                <th>Status</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="warehouse in (warehouses?.data || [])" :key="warehouse.id">
                <td class="font-mono text-xs">{{ warehouse.code }}</td>
                <td class="font-semibold">{{ warehouse.name }}</td>
                <td>{{ warehouse.address || '-' }}</td>
                <td><StatusBadge :status="warehouse.status" /></td>
                <td class="text-right">
                  <button class="btn btn-ghost btn-xs" @click="openEditModal(warehouse)">Edit</button>
                </td>
              </tr>
              <tr v-if="!(warehouses?.data || []).length">
                <td colspan="5" class="py-8 text-center text-base-content/50">Belum ada warehouse.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <DataTablePagination :paginator="warehouses" @update:per-page="(n) => { filters.per_page = n; }" />
      </div>

      <dialog id="modal-add-warehouse" class="modal">
        <div class="modal-box max-w-xl">
          <h3 class="font-bold text-lg">Tambah Warehouse</h3>
          <div class="mt-4 space-y-3">
            <input v-model="form.code" type="text" class="input input-bordered w-full" placeholder="Code (WH-01)" />
            <input v-model="form.name" type="text" class="input input-bordered w-full" placeholder="Nama warehouse" />
            <textarea v-model="form.address" class="textarea textarea-bordered w-full" rows="3" placeholder="Alamat (opsional)"></textarea>
            <label class="label cursor-pointer justify-start gap-3 rounded-lg border border-base-300 px-3 mt-1">
              <input
                :checked="form.status === 'active'"
                type="checkbox"
                class="toggle toggle-success"
                @change="form.status = $event.target.checked ? 'active' : 'inactive'"
              />
              <span class="label-text">{{ form.status === 'active' ? 'active' : 'inactive' }}</span>
            </label>
          </div>
          <div class="modal-action">
            <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
            <button class="btn btn-primary" :disabled="form.processing" @click="submitAdd">Simpan</button>
          </div>
        </div>
      </dialog>

      <dialog id="modal-edit-warehouse" class="modal">
        <div class="modal-box max-w-xl">
          <h3 class="font-bold text-lg">Edit Warehouse</h3>
          <div class="mt-4 space-y-3">
            <input v-model="editForm.code" type="text" class="input input-bordered w-full" placeholder="Code (WH-01)" />
            <input v-model="editForm.name" type="text" class="input input-bordered w-full" placeholder="Nama warehouse" />
            <textarea v-model="editForm.address" class="textarea textarea-bordered w-full" rows="3" placeholder="Alamat (opsional)"></textarea>
            <label class="label cursor-pointer justify-start gap-3 rounded-lg border border-base-300 px-3 mt-1">
              <input
                :checked="editForm.status === 'active'"
                type="checkbox"
                class="toggle toggle-success"
                @change="editForm.status = $event.target.checked ? 'active' : 'inactive'"
              />
              <span class="label-text">{{ editForm.status === 'active' ? 'active' : 'inactive' }}</span>
            </label>
          </div>
          <div class="modal-action">
            <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
            <button class="btn btn-primary" :disabled="editForm.processing" @click="submitEdit">Simpan Perubahan</button>
          </div>
        </div>
      </dialog>
    </div>
  </AppLayout>
</template>
