<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
  detail: Object,
});

const poListUrl = () =>
  `${route('erp.purchasing.purchase-orders')}?supplier=${encodeURIComponent(props.detail.code)}`;

const goBack = () => {
  router.visit(route('erp.purchasing'));
};

const editForm = useForm({
  name: props.detail.name ?? '',
  phone: props.detail.phone ?? '',
  email: props.detail.email ?? '',
  address: props.detail.address ?? '',
  tax_id: props.detail.tax_id ?? '',
  payment_terms: props.detail.payment_terms ?? 'Net 14',
  lead_time_days: props.detail.lead_time_days ?? 7,
  notes: props.detail.notes ?? '',
  is_active: props.detail.is_active ?? true,
});

const openEditModal = () => {
  editForm.defaults({
    name: props.detail.name ?? '',
    phone: props.detail.phone ?? '',
    email: props.detail.email ?? '',
    address: props.detail.address ?? '',
    tax_id: props.detail.tax_id ?? '',
    payment_terms: props.detail.payment_terms ?? 'Net 14',
    lead_time_days: props.detail.lead_time_days ?? 7,
    notes: props.detail.notes ?? '',
    is_active: props.detail.is_active ?? true,
  });
  editForm.reset();
  document.getElementById('modal-edit-supplier')?.showModal();
};

const submitEdit = () => {
  editForm.patch(route('erp.purchasing.suppliers.update', props.detail.code), {
    preserveScroll: true,
    onSuccess: () => {
      document.getElementById('modal-edit-supplier')?.close();
    },
  });
};
</script>

<template>
  <Head :title="`Supplier — ${detail.name}`" />
  <AppLayout>
    <div class="space-y-5">
      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Purchasing · Supplier</p>
              <h1 class="ocn-panel__title mt-1">{{ detail.name }}</h1>
              <p class="mt-1 font-mono text-sm text-base-content/70">{{ detail.code }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
              <button type="button" class="btn btn-outline btn-sm" @click="openEditModal">Edit Supplier</button>
              <button type="button" class="btn btn-ghost btn-sm shrink-0 gap-1.5" @click="goBack"><ArrowLeftIcon class="h-4 w-4" />
            Back</button>
            </div>
          </div>
        </div>
      </div>

      <div class="grid gap-5 lg:grid-cols-3">
        <div class="ocn-panel lg:col-span-2">
          <div class="ocn-panel__head">
            <h2 class="ocn-panel__title">Informasi supplier</h2>
          </div>
          <div class="card-body">
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

      <dialog id="modal-edit-supplier" class="modal">
        <div class="modal-box max-w-2xl">
          <h3 class="font-bold text-lg">Edit Supplier</h3>
          <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
            <div class="md:col-span-2">
              <label class="label"><span class="label-text">Nama Supplier</span></label>
              <input v-model="editForm.name" class="input input-bordered w-full" />
              <p v-if="editForm.errors.name" class="mt-1 text-xs text-error">{{ editForm.errors.name }}</p>
            </div>
            <div>
              <label class="label"><span class="label-text">Telepon</span></label>
              <input v-model="editForm.phone" class="input input-bordered w-full" />
              <p v-if="editForm.errors.phone" class="mt-1 text-xs text-error">{{ editForm.errors.phone }}</p>
            </div>
            <div>
              <label class="label"><span class="label-text">Email</span></label>
              <input v-model="editForm.email" class="input input-bordered w-full" />
              <p v-if="editForm.errors.email" class="mt-1 text-xs text-error">{{ editForm.errors.email }}</p>
            </div>
            <div class="md:col-span-2">
              <label class="label"><span class="label-text">Alamat</span></label>
              <textarea v-model="editForm.address" class="textarea textarea-bordered w-full" rows="3" />
              <p v-if="editForm.errors.address" class="mt-1 text-xs text-error">{{ editForm.errors.address }}</p>
            </div>
            <div>
              <label class="label"><span class="label-text">NPWP</span></label>
              <input v-model="editForm.tax_id" class="input input-bordered w-full" />
              <p v-if="editForm.errors.tax_id" class="mt-1 text-xs text-error">{{ editForm.errors.tax_id }}</p>
            </div>
            <div>
              <label class="label"><span class="label-text">Termin Bayar</span></label>
              <input v-model="editForm.payment_terms" class="input input-bordered w-full" />
              <p v-if="editForm.errors.payment_terms" class="mt-1 text-xs text-error">{{ editForm.errors.payment_terms }}</p>
            </div>
            <div>
              <label class="label"><span class="label-text">Lead Time (hari)</span></label>
              <input v-model="editForm.lead_time_days" type="number" min="1" class="input input-bordered w-full" />
              <p v-if="editForm.errors.lead_time_days" class="mt-1 text-xs text-error">{{ editForm.errors.lead_time_days }}</p>
            </div>
            <div>
              <label class="label"><span class="label-text">Status</span></label>
              <select v-model="editForm.is_active" class="select select-bordered w-full">
                <option :value="true">Active</option>
                <option :value="false">Non Active</option>
              </select>
              <p v-if="editForm.errors.is_active" class="mt-1 text-xs text-error">{{ editForm.errors.is_active }}</p>
            </div>
            <div class="md:col-span-2">
              <label class="label"><span class="label-text">Catatan</span></label>
              <textarea v-model="editForm.notes" class="textarea textarea-bordered w-full" rows="4" />
              <p v-if="editForm.errors.notes" class="mt-1 text-xs text-error">{{ editForm.errors.notes }}</p>
            </div>
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
