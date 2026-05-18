<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTablePagination from '@/Components/DataTablePagination.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { computed, ref } from 'vue';

const props = defineProps({
  landingSites: Object,
  warehouses: Array,
  filters: Object,
  cmsModule: { type: Boolean, default: false },
});

const filterKeyword = ref('');
const filterStatus = ref('');

const filteredLandingSites = computed(() => {
  const list = props.landingSites?.data ?? [];
  const term = filterKeyword.value.trim().toLowerCase();
  return list.filter((site) => {
    const matchStatus = !filterStatus.value || (filterStatus.value === 'active' ? !!site.is_active : !site.is_active);
    const matchKeyword = !term
      || site.name?.toLowerCase().includes(term)
      || site.domain?.toLowerCase().includes(term)
      || (site.layout_key || '').toLowerCase().includes(term)
      || (site.warehouse?.name || '').toLowerCase().includes(term)
      || (site.warehouse?.code || '').toLowerCase().includes(term);
    return matchStatus && matchKeyword;
  });
});

const warehouseOptions = computed(() => (props.warehouses ?? []).map((w) => ({
  id: w.id,
  label: `${w.code} — ${w.name}`,
})));

const form = useForm({
  name: '',
  domain: '',
  layout_key: 'toko',
  warehouse_id: '',
  is_active: true,
});

const openAddModal = () => {
  form.clearErrors();
  form.reset('name', 'domain', 'warehouse_id');
  form.layout_key = 'toko';
  form.is_active = true;
  document.getElementById('modal-add-landing-site')?.showModal();
};

const submit = () => {
  form.transform((data) => ({
    name: data.name,
    domain: data.domain,
    layout_key: data.layout_key,
    warehouse_id: data.warehouse_id ? Number(data.warehouse_id) : null,
    is_active: !!data.is_active,
  })).post(route('erp.admin.landing-sites.store'), {
    preserveScroll: true,
    onSuccess: () => {
      form.reset('name', 'domain', 'warehouse_id');
      form.layout_key = 'toko';
      form.is_active = true;
      document.getElementById('modal-add-landing-site')?.close();
    },
  });
};

const editingSite = ref(null);
const editForm = useForm({
  name: '',
  domain: '',
  layout_key: 'toko',
  warehouse_id: '',
  is_active: true,
});

const openEditModal = (site) => {
  editingSite.value = site;
  editForm.clearErrors();
  editForm.name = site.name;
  editForm.domain = site.domain;
  editForm.layout_key = site.layout_key || 'toko';
  editForm.warehouse_id = site.warehouse_id ?? '';
  editForm.is_active = !!site.is_active;
  document.getElementById('modal-edit-landing-site')?.showModal();
};

const submitEdit = () => {
  if (!editingSite.value) return;
  editForm.transform((data) => ({
    name: data.name,
    domain: data.domain,
    layout_key: data.layout_key,
    warehouse_id: data.warehouse_id ? Number(data.warehouse_id) : null,
    is_active: !!data.is_active,
  })).patch(route('erp.admin.landing-sites.update', editingSite.value.id), {
    preserveScroll: true,
    onSuccess: () => document.getElementById('modal-edit-landing-site')?.close(),
  });
};

const toggleStatus = (site) => {
  router.patch(route('erp.admin.landing-sites.update', site.id), {
    name: site.name,
    domain: site.domain,
    layout_key: site.layout_key || 'toko',
    warehouse_id: site.warehouse_id,
    is_active: !site.is_active,
  }, {
    preserveScroll: true,
  });
};

const onPerPage = (n) => {
  router.get(route('erp.admin.landing-sites'), { per_page: n }, {
    preserveState: true,
    replace: true,
  });
};
</script>

<template>
  <Head title="Administration - Landing Sites" />
  <AppLayout>
    <div class="space-y-5">
      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">{{ cmsModule ? 'Website CMS' : 'Administration Workspace' }}</p>
              <h1 class="ocn-panel__title mt-1">Landing Sites</h1>
              <p class="ocn-panel__desc mt-1">Mapping domain landing page ke konfigurasi warehouse default.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
              <Link class="btn btn-ghost btn-sm shrink-0 gap-1.5" :href="cmsModule ? route('erp.cms') : route('erp.administration')">
              <ArrowLeftIcon class="h-4 w-4" />
              Back
            </Link>
            </div>
          </div>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Filter landing site</h2>
        </div>
        <div class="card-body">
          <div class="flex flex-wrap items-end gap-3">
            <div class="min-w-[220px] grow">
              <label class="label"><span class="label-text">Cari</span></label>
              <input v-model="filterKeyword" type="text" class="input input-bordered w-full" placeholder="Cari nama / domain / warehouse" />
            </div>
            <div class="w-full sm:w-48">
              <label class="label"><span class="label-text">Status</span></label>
              <select v-model="filterStatus" class="select select-bordered w-full">
                <option value="">Semua</option>
                <option value="active">active</option>
                <option value="inactive">inactive</option>
              </select>
            </div>
            <button class="btn btn-primary" @click="openAddModal">+ Tambah Landing</button>
          </div>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Daftar landing site</h2>
          <p class="ocn-panel__desc">Domain publik dan warehouse default per bisnis.</p>
        </div>
        <div class="overflow-x-auto">
          <table class="table table-zebra">
            <thead>
              <tr>
                <th>Nama</th>
                <th>Domain</th>
                <th>Layout</th>
                <th>Warehouse Default</th>
                <th>Status</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="site in filteredLandingSites" :key="site.id">
                <td class="font-semibold">{{ site.name }}</td>
                <td class="font-mono text-xs">{{ site.domain }}</td>
                <td><span class="badge badge-outline badge-sm">{{ site.layout_key || 'toko' }}</span></td>
                <td>
                  <span v-if="site.warehouse" class="badge badge-ghost badge-sm">
                    {{ site.warehouse.code }} — {{ site.warehouse.name }}
                  </span>
                  <span v-else class="text-base-content/60 text-sm">-</span>
                </td>
                <td>
                  <span class="badge badge-sm" :class="site.is_active ? 'badge-success' : 'badge-ghost'">
                    {{ site.is_active ? 'active' : 'inactive' }}
                  </span>
                </td>
                <td class="text-right">
                  <div class="flex justify-end gap-2">
                    <Link
                      class="btn btn-ghost btn-xs"
                      :href="`${route('erp.admin.landing-sites.cms', site.id)}${cmsModule ? '?cms=1' : ''}`"
                    >CMS</Link>
                    <button class="btn btn-ghost btn-xs" @click="openEditModal(site)">Edit</button>
                    <button
                      class="btn btn-xs"
                      :class="site.is_active ? 'btn-warning' : 'btn-success'"
                      @click="toggleStatus(site)"
                    >
                      {{ site.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="!filteredLandingSites.length">
                <td colspan="6" class="py-8 text-center text-base-content/50">Belum ada landing site.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <DataTablePagination :paginator="landingSites" @update:per-page="onPerPage" />
      </div>
    </div>

    <dialog id="modal-add-landing-site" class="modal">
      <div class="modal-box max-w-xl">
        <h3 class="font-bold text-lg">Tambah Landing Site</h3>
        <div class="mt-4 space-y-3">
          <div>
            <label class="label"><span class="label-text">Nama</span></label>
            <input v-model="form.name" type="text" class="input input-bordered w-full" placeholder="Bisnis A / Bisnis B" />
            <p v-if="form.errors.name" class="text-error text-xs mt-1">{{ form.errors.name }}</p>
          </div>
          <div>
            <label class="label"><span class="label-text">Domain</span></label>
            <input v-model="form.domain" type="text" class="input input-bordered w-full" placeholder="contoh: bisnis-a.com" />
            <p class="text-xs text-base-content/60 mt-1">Simpan tanpa protokol (tanpa https://). Domain akan disimpan lowercase.</p>
            <p v-if="form.errors.domain" class="text-error text-xs mt-1">{{ form.errors.domain }}</p>
          </div>
          <div>
            <label class="label"><span class="label-text">Jenis landing</span></label>
            <select v-model="form.layout_key" class="select select-bordered w-full">
              <option value="toko">Toko (retail)</option>
              <option value="cctv">CCTV & jaringan</option>
              <option value="coming_soon">Coming Soon (simple)</option>
              <option value="countdown">Countdown launch</option>
            </select>
            <p v-if="form.errors.layout_key" class="text-error text-xs mt-1">{{ form.errors.layout_key }}</p>
          </div>
          <div>
            <label class="label"><span class="label-text">Warehouse Default</span></label>
            <select v-model="form.warehouse_id" class="select select-bordered w-full">
              <option value="">(tidak ditentukan)</option>
              <option v-for="w in warehouseOptions" :key="w.id" :value="w.id">{{ w.label }}</option>
            </select>
            <p v-if="form.errors.warehouse_id" class="text-error text-xs mt-1">{{ form.errors.warehouse_id }}</p>
          </div>
          <div>
            <label class="label cursor-pointer justify-start gap-3 mt-1">
              <input
                :checked="form.is_active"
                type="checkbox"
                class="toggle toggle-success"
                @change="form.is_active = $event.target.checked"
              />
              <span class="label-text">{{ form.is_active ? 'active' : 'inactive' }}</span>
            </label>
            <p v-if="form.errors.is_active" class="text-error text-xs mt-1">{{ form.errors.is_active }}</p>
          </div>
        </div>
        <div class="modal-action">
          <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
          <button class="btn btn-primary" :disabled="form.processing" @click="submit">Tambah</button>
        </div>
      </div>
    </dialog>

    <dialog id="modal-edit-landing-site" class="modal">
      <div class="modal-box max-w-xl">
        <h3 class="font-bold text-lg">Edit Landing Site</h3>
        <div class="mt-4 space-y-3">
          <div>
            <label class="label"><span class="label-text">Nama</span></label>
            <input v-model="editForm.name" type="text" class="input input-bordered w-full" />
            <p v-if="editForm.errors.name" class="text-error text-xs mt-1">{{ editForm.errors.name }}</p>
          </div>
          <div>
            <label class="label"><span class="label-text">Domain</span></label>
            <input v-model="editForm.domain" type="text" class="input input-bordered w-full" />
            <p v-if="editForm.errors.domain" class="text-error text-xs mt-1">{{ editForm.errors.domain }}</p>
          </div>
          <div>
            <label class="label"><span class="label-text">Jenis landing</span></label>
            <select v-model="editForm.layout_key" class="select select-bordered w-full">
              <option value="toko">Toko (retail)</option>
              <option value="cctv">CCTV & jaringan</option>
              <option value="coming_soon">Coming Soon (simple)</option>
              <option value="countdown">Countdown launch</option>
            </select>
            <p v-if="editForm.errors.layout_key" class="text-error text-xs mt-1">{{ editForm.errors.layout_key }}</p>
          </div>
          <div>
            <label class="label"><span class="label-text">Warehouse Default</span></label>
            <select v-model="editForm.warehouse_id" class="select select-bordered w-full">
              <option value="">(tidak ditentukan)</option>
              <option v-for="w in warehouseOptions" :key="w.id" :value="w.id">{{ w.label }}</option>
            </select>
            <p v-if="editForm.errors.warehouse_id" class="text-error text-xs mt-1">{{ editForm.errors.warehouse_id }}</p>
          </div>
          <div>
            <label class="label cursor-pointer justify-start gap-3 mt-1">
              <input
                :checked="editForm.is_active"
                type="checkbox"
                class="toggle toggle-success"
                @change="editForm.is_active = $event.target.checked"
              />
              <span class="label-text">{{ editForm.is_active ? 'active' : 'inactive' }}</span>
            </label>
            <p v-if="editForm.errors.is_active" class="text-error text-xs mt-1">{{ editForm.errors.is_active }}</p>
          </div>
        </div>
        <div class="modal-action">
          <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
          <button class="btn btn-primary" :disabled="editForm.processing" @click="submitEdit">Simpan</button>
        </div>
      </div>
    </dialog>
  </AppLayout>
</template>
