<script setup>
import { computed, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm, Link } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    crm_customers: { type: Array, default: () => [] },
});

const form = useForm({
    name: '',
    crm_customer_id: '',
    client_name: '',
    client_contact: '',
    project_type: 'system_website_development',
    status: 'negosiasi',
    started_at: '',
    finished_at: '',
    description: '',
});

const selectedCustomer = computed(() =>
    props.crm_customers.find((customer) => Number(customer.id) === Number(form.crm_customer_id)),
);

const syncSelectedCustomer = () => {
    form.client_name = selectedCustomer.value?.display_name ?? '';
    form.client_contact = selectedCustomer.value?.contact ?? '';
};

watch(() => form.crm_customer_id, syncSelectedCustomer);

const submit = () => form.post(route('projects.store'));
</script>

<template>
    <AppLayout>
        <div class="mx-auto max-w-6xl xl:max-w-7xl space-y-5">
            <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Projects Workspace</p>
              <h1 class="ocn-panel__title mt-1">Tambah Project Baru</h1>
              <p class="ocn-panel__desc mt-1">Isi data awal project. Nilai kontrak diambil dari budget/deal bila tersedia.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
              <div class="flex flex-wrap items-center gap-2">
                        <Link :href="route('projects.index')" class="btn btn-ghost btn-sm gap-1.5">
                            <ArrowLeftIcon class="h-4 w-4" />
                            Kembali
                        </Link>
                    </div>
            </div>
          </div>
        </div>
      </div>

            <div class="max-w-3xl">
                <div class="ocn-panel">
                    <div class="ocn-panel__head">
                        <h2 class="ocn-panel__title">Data project baru</h2>
                    </div>
                    <div class="card-body space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="sm:col-span-2">
                                <label class="label"><span class="label-text font-medium">Nama Project <span class="text-error">*</span></span></label>
                                <input v-model="form.name" type="text" class="input input-bordered w-full" :class="form.errors.name ? 'input-error' : ''" placeholder="Sistem Pembukuan XYZ" />
                                <p v-if="form.errors.name" class="text-error text-xs mt-1">{{ form.errors.name }}</p>
                            </div>

                            <div class="sm:col-span-2">
                                <label class="label"><span class="label-text font-medium">Customer CRM <span class="text-error">*</span></span></label>
                                <select v-model="form.crm_customer_id" class="select select-bordered w-full" :class="form.errors.crm_customer_id ? 'select-error' : ''">
                                    <option value="">Pilih customer</option>
                                    <option v-for="customer in crm_customers" :key="customer.id" :value="customer.id">
                                        {{ customer.code }} - {{ customer.display_name }}
                                    </option>
                                </select>
                                <p v-if="form.errors.crm_customer_id" class="text-error text-xs mt-1">{{ form.errors.crm_customer_id }}</p>
                            </div>

                            <div>
                                <label class="label"><span class="label-text font-medium">Nama Klien</span></label>
                                <input v-model="form.client_name" type="text" class="input input-bordered w-full bg-base-200" readonly placeholder="Terisi dari CRM Customer" />
                            </div>

                            <div>
                                <label class="label"><span class="label-text font-medium">Kontak Klien</span></label>
                                <input v-model="form.client_contact" type="text" class="input input-bordered w-full bg-base-200" readonly placeholder="Terisi dari CRM Customer" />
                            </div>

                            <div>
                                <label class="label"><span class="label-text font-medium">Tipe Project <span class="text-error">*</span></span></label>
                                <select v-model="form.project_type" class="select select-bordered w-full" :class="form.errors.project_type ? 'select-error' : ''">
                                    <option value="system_website_development">System/Website Development</option>
                                    <option value="cctv_installation">CCTV Installation</option>
                                </select>
                                <p v-if="form.errors.project_type" class="text-error text-xs mt-1">{{ form.errors.project_type }}</p>
                            </div>

                            <div>
                                <label class="label"><span class="label-text font-medium">Status <span class="text-error">*</span></span></label>
                                <select v-model="form.status" class="select select-bordered w-full">
                                    <option value="negosiasi">Negosiasi</option>
                                    <option value="berjalan">Berjalan</option>
                                    <option value="selesai">Selesai</option>
                                    <option value="dibatalkan">Dibatalkan</option>
                                </select>
                            </div>

                            <div>
                                <label class="label"><span class="label-text font-medium">Tanggal Mulai</span></label>
                                <input v-model="form.started_at" type="date" class="input input-bordered w-full" />
                            </div>
                            <div>
                                <label class="label"><span class="label-text font-medium">Tanggal Selesai</span></label>
                                <input v-model="form.finished_at" type="date" class="input input-bordered w-full" />
                                <p v-if="form.errors.finished_at" class="text-error text-xs mt-1">{{ form.errors.finished_at }}</p>
                            </div>

                            <div class="sm:col-span-2">
                                <label class="label"><span class="label-text font-medium">Deskripsi</span></label>
                                <textarea v-model="form.description" class="textarea textarea-bordered w-full" rows="3" />
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <div class="flex flex-wrap justify-end gap-3 rounded-2xl border border-base-200 bg-base-100/80 px-4 py-4 sm:px-6">
                <Link :href="route('projects.index')" class="btn btn-ghost">Batal</Link>
                <button class="btn btn-primary" :disabled="form.processing" @click="submit">
                    <span v-if="form.processing" class="loading loading-spinner loading-sm" />
                    Simpan Project
                </button>
            </div>
        </div>
    </AppLayout>
</template>
