<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import DataTablePagination from '@/Components/DataTablePagination.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useCurrency } from '@/composables/useCurrency';
import { ArrowLeftIcon, MagnifyingGlassIcon, PlusIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    projects: Object,
    filters: Object,
    crm_customers: { type: Array, default: () => [] },
});
const { format } = useCurrency();

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');
const projectType = ref(props.filters.project_type ?? '');
const perPage = ref(Number(props.filters.per_page ?? props.projects.per_page ?? 25));
const projectForm = useForm({
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
    props.crm_customers.find((customer) => Number(customer.id) === Number(projectForm.crm_customer_id)),
);

const syncSelectedCustomer = () => {
    projectForm.client_name = selectedCustomer.value?.display_name ?? '';
    projectForm.client_contact = selectedCustomer.value?.contact ?? '';
};

watch(() => projectForm.crm_customer_id, syncSelectedCustomer);

let timer;
watch([search, status, projectType, perPage], () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(route('projects.index'), {
            search: search.value,
            status: status.value,
            project_type: projectType.value,
            per_page: perPage.value,
        }, { preserveState: true, replace: true });
    }, 400);
});

const projectTypeLabel = (value) => {
    if (value === 'cctv_installation') return 'CCTV Installation';
    if (value === 'system_website_development') return 'System/Website Development';
    return value;
};

const openAddProjectModal = () => {
    projectForm.clearErrors();
    document.getElementById('modal-add-project')?.showModal();
};

const closeAddProjectModal = () => {
    document.getElementById('modal-add-project')?.close();
};

const submitProject = () => {
    projectForm.post(route('projects.store'), {
        preserveScroll: true,
        onSuccess: () => {
            projectForm.reset();
            closeAddProjectModal();
        },
    });
};
</script>

<template>
    <AppLayout>
        <div class="space-y-5">
            <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Projects Workspace</p>
              <h1 class="ocn-panel__title mt-1">Projects</h1>
              <p class="ocn-panel__desc mt-1">Kelola daftar project aktif dari tahap negosiasi sampai selesai.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
              <Link :href="route('erp.projects')" class="btn btn-ghost btn-sm shrink-0 gap-1.5"><ArrowLeftIcon class="h-4 w-4" />
                            Back</Link>
            </div>
          </div>
        </div>
      </div>

            <!-- Utility Card -->
            <div class="ocn-panel">
                <div class="ocn-panel__head">
                    <h2 class="ocn-panel__title">Filter project</h2>
                </div>
                <div class="card-body">
                    <div class="flex flex-wrap gap-3 items-center">
                        <label class="input input-bordered input-sm flex items-center gap-2 max-w-xs">
                            <MagnifyingGlassIcon class="w-4 h-4 opacity-50" />
                            <input v-model="search" type="text" placeholder="Cari project / klien…" class="grow" />
                        </label>
                        <select v-model="status" class="select select-bordered select-sm">
                            <option value="">Semua Status</option>
                            <option value="negosiasi">Negosiasi</option>
                            <option value="berjalan">Berjalan</option>
                            <option value="selesai">Selesai</option>
                            <option value="dibatalkan">Dibatalkan</option>
                        </select>
                        <select v-model="projectType" class="select select-bordered select-sm">
                            <option value="">Semua Tipe</option>
                            <option value="cctv_installation">CCTV Installation</option>
                            <option value="system_website_development">System/Website Development</option>
                        </select>
                        <div class="ml-auto">
                            <button type="button" class="btn btn-primary btn-sm gap-2" @click="openAddProjectModal">
                                <PlusIcon class="w-4 h-4" /> Tambah Project
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="ocn-panel">
                <div class="ocn-panel__head">
                    <h2 class="ocn-panel__title">Daftar project</h2>
                </div>
                <div class="p-0">
                    <div class="overflow-x-auto">
                        <table class="table table-zebra">
                            <thead>
                                <tr>
                                    <th>Nama Project</th>
                                    <th>Klien</th>
                                    <th>Tipe</th>
                                    <th>Status</th>
                                    <th>Nilai Kontrak</th>
                                    <th>Mulai</th>
                                    <th>Pembayaran</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="p in projects.data"
                                    :key="p.id"
                                    class="cursor-pointer hover"
                                    tabindex="0"
                                    @click="router.visit(route('projects.show', p.id))"
                                    @keydown.enter.prevent="router.visit(route('projects.show', p.id))"
                                >
                                    <td class="font-medium">{{ p.name }}</td>
                                    <td>{{ p.client_name }}</td>
                                    <td>
                                        <span class="badge badge-ghost badge-sm">{{ projectTypeLabel(p.project_type) }}</span>
                                    </td>
                                    <td><StatusBadge :status="p.status" /></td>
                                    <td class="font-medium">{{ format(p.total_value) }}</td>
                                    <td class="text-sm text-base-content/70">{{ p.started_at ?? '-' }}</td>
                                    <td>
                                        <div class="flex items-center gap-2">
                                            <progress class="progress progress-success w-20" :value="p.paid_amount" :max="p.total_value || 1" />
                                            <span class="text-xs text-base-content/60">{{ format(p.paid_amount) }} / {{ format(p.total_value) }}</span>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!projects.data.length">
                                    <td colspan="7" class="text-center text-base-content/50 py-12">Tidak ada project ditemukan</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <DataTablePagination
                        :paginator="projects"
                        @update:per-page="(n) => { perPage.value = n; }"
                    />
                </div>
            </div>
        </div>

        <dialog id="modal-add-project" class="modal">
            <div class="modal-box max-w-3xl">
                <h3 class="font-bold text-lg">Tambah Project</h3>
                <p class="mt-1 text-sm text-base-content/60">Buat project operasional tanpa nilai kontrak. Nilai kontrak tetap berasal dari budget/deal bila tersedia.</p>

                <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="label"><span class="label-text font-medium">Nama Project <span class="text-error">*</span></span></label>
                        <input v-model="projectForm.name" type="text" class="input input-bordered w-full" :class="projectForm.errors.name ? 'input-error' : ''" placeholder="Sistem Pembukuan XYZ" />
                        <p v-if="projectForm.errors.name" class="text-error text-xs mt-1">{{ projectForm.errors.name }}</p>
                    </div>

                    <div class="sm:col-span-2">
                        <label class="label"><span class="label-text font-medium">Customer CRM <span class="text-error">*</span></span></label>
                        <select v-model="projectForm.crm_customer_id" class="select select-bordered w-full" :class="projectForm.errors.crm_customer_id ? 'select-error' : ''">
                            <option value="">Pilih customer</option>
                            <option v-for="customer in crm_customers" :key="customer.id" :value="customer.id">
                                {{ customer.code }} - {{ customer.display_name }}
                            </option>
                        </select>
                        <p v-if="projectForm.errors.crm_customer_id" class="text-error text-xs mt-1">{{ projectForm.errors.crm_customer_id }}</p>
                    </div>

                    <div>
                        <label class="label"><span class="label-text font-medium">Nama Klien</span></label>
                        <input v-model="projectForm.client_name" type="text" class="input input-bordered w-full bg-base-200" readonly placeholder="Terisi dari CRM Customer" />
                    </div>

                    <div>
                        <label class="label"><span class="label-text font-medium">Kontak Klien</span></label>
                        <input v-model="projectForm.client_contact" type="text" class="input input-bordered w-full bg-base-200" readonly placeholder="Terisi dari CRM Customer" />
                    </div>

                    <div>
                        <label class="label"><span class="label-text font-medium">Tipe Project <span class="text-error">*</span></span></label>
                        <select v-model="projectForm.project_type" class="select select-bordered w-full" :class="projectForm.errors.project_type ? 'select-error' : ''">
                            <option value="system_website_development">System/Website Development</option>
                            <option value="cctv_installation">CCTV Installation</option>
                        </select>
                        <p v-if="projectForm.errors.project_type" class="text-error text-xs mt-1">{{ projectForm.errors.project_type }}</p>
                    </div>

                    <div>
                        <label class="label"><span class="label-text font-medium">Status <span class="text-error">*</span></span></label>
                        <select v-model="projectForm.status" class="select select-bordered w-full">
                            <option value="negosiasi">Negosiasi</option>
                            <option value="berjalan">Berjalan</option>
                            <option value="selesai">Selesai</option>
                            <option value="dibatalkan">Dibatalkan</option>
                        </select>
                    </div>

                    <div>
                        <label class="label"><span class="label-text font-medium">Tanggal Mulai</span></label>
                        <input v-model="projectForm.started_at" type="date" class="input input-bordered w-full" />
                    </div>

                    <div>
                        <label class="label"><span class="label-text font-medium">Tanggal Selesai</span></label>
                        <input v-model="projectForm.finished_at" type="date" class="input input-bordered w-full" />
                        <p v-if="projectForm.errors.finished_at" class="text-error text-xs mt-1">{{ projectForm.errors.finished_at }}</p>
                    </div>

                    <div class="sm:col-span-2">
                        <label class="label"><span class="label-text font-medium">Deskripsi</span></label>
                        <textarea v-model="projectForm.description" class="textarea textarea-bordered w-full" rows="3" />
                    </div>
                </div>

                <div class="modal-action">
                    <button type="button" class="btn btn-ghost" @click="closeAddProjectModal">Batal</button>
                    <button type="button" class="btn btn-primary" :disabled="projectForm.processing" @click="submitProject">
                        <span v-if="projectForm.processing" class="loading loading-spinner loading-sm" />
                        Simpan Project
                    </button>
                </div>
            </div>
            <form method="dialog" class="modal-backdrop"><button>close</button></form>
        </dialog>
    </AppLayout>
</template>
