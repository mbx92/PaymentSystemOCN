<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTablePagination from '@/Components/DataTablePagination.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps({
    types: { type: Object, default: () => ({ data: [] }) },
    filters: { type: Object, default: () => ({}) },
});

const badgeColorOptions = [
    { value: 'ghost', label: 'Netral' },
    { value: 'primary', label: 'Primary' },
    { value: 'secondary', label: 'Secondary' },
    { value: 'accent', label: 'Accent' },
    { value: 'info', label: 'Info' },
    { value: 'success', label: 'Success' },
    { value: 'warning', label: 'Warning' },
    { value: 'error', label: 'Error' },
];

const addForm = useForm({
    key: '',
    label: '',
    badge_color: 'ghost',
    description: '',
    supports_budget_items: false,
    supports_project_board: false,
    is_active: true,
    is_default: false,
    sort_order: 0,
});

const resetAddForm = () => {
    addForm.clearErrors();
    addForm.reset();
    addForm.is_active = true;
    addForm.is_default = false;
    addForm.sort_order = 0;
    addForm.badge_color = 'ghost';
};

const openAddModal = () => {
    resetAddForm();
    document.getElementById('modal-add-project-type')?.showModal();
};

const submitAdd = () => {
    addForm.post(route('erp.projects.project-types.store'), {
        preserveScroll: true,
        onSuccess: () => {
            resetAddForm();
            document.getElementById('modal-add-project-type')?.close();
        },
    });
};

const editing = ref(null);
const editForm = useForm({
    label: '',
    badge_color: 'ghost',
    description: '',
    supports_budget_items: false,
    supports_project_board: false,
    is_active: true,
    is_default: false,
    sort_order: 0,
});

const openEditModal = (row) => {
    editing.value = row;
    editForm.clearErrors();
    editForm.label = row.label ?? '';
    editForm.badge_color = row.badge_color ?? 'ghost';
    editForm.description = row.description ?? '';
    editForm.supports_budget_items = !!row.supports_budget_items;
    editForm.supports_project_board = !!row.supports_project_board;
    editForm.is_active = !!row.is_active;
    editForm.is_default = !!row.is_default;
    editForm.sort_order = Number(row.sort_order ?? 0);
    document.getElementById('modal-edit-project-type')?.showModal();
};

const submitEdit = () => {
    if (!editing.value) return;
    editForm.patch(route('erp.projects.project-types.update', editing.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            editing.value = null;
            document.getElementById('modal-edit-project-type')?.close();
        },
    });
};

const badgeClass = (color) => `badge-${color || 'ghost'}`;
const selectedAddBadgeClass = computed(() => badgeClass(addForm.badge_color));
const selectedEditBadgeClass = computed(() => badgeClass(editForm.badge_color));
const filters = reactive({
    q: props.filters?.q ?? '',
    status: props.filters?.status ?? '',
    supports_budget_items: props.filters?.supports_budget_items ?? '',
    per_page: Number(props.filters?.per_page ?? props.types?.per_page ?? 25),
});
const typeRows = computed(() => props.types?.data ?? []);

let timer;
watch(filters, (val) => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(route('erp.projects.project-types.index'), val, { preserveState: true, replace: true });
    }, 250);
}, { deep: true });
</script>

<template>
    <Head title="Master Tipe Project" />
    <AppLayout>
        <div class="space-y-5">
            <div class="ocn-panel">
                <div class="ocn-panel__head">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Projects Workspace</p>
                            <h1 class="ocn-panel__title mt-1">Master Tipe Project</h1>
                            <p class="ocn-panel__desc mt-1">Kelola label, tipe default, serta capability seperti board task dan budget item.</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 shrink-0">
                            <button type="button" class="btn btn-primary btn-sm" @click="openAddModal">+ Tipe Project</button>
                            <Link :href="route('erp.projects')" class="btn btn-ghost btn-sm shrink-0 gap-1.5">
                                <ArrowLeftIcon class="h-4 w-4" />
                                Back
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <div class="ocn-panel">
                <div class="ocn-panel__head">
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
                        <input v-model="filters.q" type="search" class="input input-bordered input-sm w-full md:col-span-2" placeholder="Cari label, key, atau deskripsi..." />
                        <select v-model="filters.status" class="select select-bordered select-sm w-full">
                            <option value="">Semua Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                        <select v-model="filters.supports_budget_items" class="select select-bordered select-sm w-full">
                            <option value="">Semua Capability</option>
                            <option value="yes">Pakai budget item</option>
                            <option value="no">Tanpa budget item</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="ocn-panel">
                <div class="ocn-panel__head">
                    <h2 class="ocn-panel__title">Daftar tipe</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Label</th>
                                <th>Key</th>
                                <th>Capability</th>
                                <th>Status</th>
                                <th>Pemakaian</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in typeRows"
                                :key="row.id"
                                class="cursor-pointer hover"
                                tabindex="0"
                                @click="openEditModal(row)"
                                @keydown.enter.prevent="openEditModal(row)"
                            >
                                <td>
                                    <div class="flex items-center gap-2">
                                        <span class="badge badge-sm border-0" :class="badgeClass(row.badge_color)">{{ row.label }}</span>
                                    </div>
                                    <div v-if="row.description" class="text-xs text-base-content/60">{{ row.description }}</div>
                                </td>
                                <td class="font-mono text-xs">{{ row.key }}</td>
                                <td>
                                    <div class="flex flex-wrap gap-1">
                                        <span class="badge badge-ghost badge-sm">Budget Item: {{ row.supports_budget_items ? 'On' : 'Off' }}</span>
                                        <span class="badge badge-ghost badge-sm">Board Task: {{ row.supports_project_board ? 'On' : 'Off' }}</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="flex flex-wrap gap-1">
                                        <span class="badge badge-sm" :class="row.is_active ? 'badge-success' : 'badge-ghost'">{{ row.is_active ? 'Active' : 'Inactive' }}</span>
                                        <span v-if="row.is_default" class="badge badge-primary badge-sm">Default</span>
                                    </div>
                                </td>
                                <td class="text-sm">
                                    <div>{{ row.project_count }} project</div>
                                    <div class="text-base-content/60">{{ row.budget_count }} budget</div>
                                </td>
                            </tr>
                            <tr v-if="!typeRows.length">
                                <td colspan="5" class="py-8 text-center text-base-content/50">Belum ada tipe project.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <DataTablePagination :paginator="types" @update:per-page="(n) => { filters.per_page = n; }" />
            </div>
        </div>

        <dialog id="modal-add-project-type" class="modal">
            <div class="modal-box max-w-2xl">
                <h3 class="text-lg font-bold">Tambah Tipe Project</h3>
                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label class="label"><span class="label-text">Key</span></label>
                        <input v-model="addForm.key" type="text" class="input input-bordered w-full" placeholder="network_installation" />
                        <p v-if="addForm.errors.key" class="mt-1 text-xs text-error">{{ addForm.errors.key }}</p>
                    </div>
                    <div>
                        <label class="label"><span class="label-text">Label</span></label>
                        <input v-model="addForm.label" type="text" class="input input-bordered w-full" placeholder="Network Installation" />
                        <p v-if="addForm.errors.label" class="mt-1 text-xs text-error">{{ addForm.errors.label }}</p>
                    </div>
                    <div>
                        <label class="label"><span class="label-text">Warna badge</span></label>
                        <select v-model="addForm.badge_color" class="select select-bordered w-full">
                            <option v-for="option in badgeColorOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                        <div class="mt-2">
                            <span class="badge badge-sm border-0" :class="selectedAddBadgeClass">{{ addForm.label || 'Preview badge' }}</span>
                        </div>
                    </div>
                    <div>
                        <label class="label"><span class="label-text">Urutan</span></label>
                        <input v-model.number="addForm.sort_order" type="number" min="0" class="input input-bordered w-full" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="label"><span class="label-text">Deskripsi</span></label>
                        <textarea v-model="addForm.description" class="textarea textarea-bordered w-full" rows="3" />
                    </div>
                    <div class="md:col-span-2 flex flex-wrap gap-x-5 gap-y-3 pt-1">
                        <label class="inline-flex items-center gap-2 text-sm"><input v-model="addForm.is_active" type="checkbox" class="toggle toggle-sm toggle-success" /> Active</label>
                        <label class="inline-flex items-center gap-2 text-sm"><input v-model="addForm.is_default" type="checkbox" class="toggle toggle-sm toggle-primary" /> Jadikan default</label>
                        <label class="inline-flex items-center gap-2 text-sm"><input v-model="addForm.supports_budget_items" type="checkbox" class="toggle toggle-sm toggle-info" /> Pakai budget item</label>
                        <label class="inline-flex items-center gap-2 text-sm"><input v-model="addForm.supports_project_board" type="checkbox" class="toggle toggle-sm toggle-secondary" /> Aktifkan board task</label>
                    </div>
                </div>
                <div class="modal-action">
                    <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
                    <button type="button" class="btn btn-primary" :disabled="addForm.processing" @click="submitAdd">Simpan</button>
                </div>
            </div>
        </dialog>

        <dialog id="modal-edit-project-type" class="modal">
            <div class="modal-box max-w-2xl">
                <h3 class="text-lg font-bold">Edit Tipe Project</h3>
                <p v-if="editing" class="mt-1 text-sm text-base-content/60">Key: <span class="font-mono">{{ editing.key }}</span></p>
                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label class="label"><span class="label-text">Label</span></label>
                        <input v-model="editForm.label" type="text" class="input input-bordered w-full" />
                        <p v-if="editForm.errors.label" class="mt-1 text-xs text-error">{{ editForm.errors.label }}</p>
                    </div>
                    <div>
                        <label class="label"><span class="label-text">Warna badge</span></label>
                        <select v-model="editForm.badge_color" class="select select-bordered w-full">
                            <option v-for="option in badgeColorOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                        <div class="mt-2">
                            <span class="badge badge-sm border-0" :class="selectedEditBadgeClass">{{ editForm.label || editing?.label || 'Preview badge' }}</span>
                        </div>
                    </div>
                    <div>
                        <label class="label"><span class="label-text">Urutan</span></label>
                        <input v-model.number="editForm.sort_order" type="number" min="0" class="input input-bordered w-full" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="label"><span class="label-text">Deskripsi</span></label>
                        <textarea v-model="editForm.description" class="textarea textarea-bordered w-full" rows="3" />
                    </div>
                    <div class="md:col-span-2 flex flex-wrap gap-x-5 gap-y-3 pt-1">
                        <label class="inline-flex items-center gap-2 text-sm"><input v-model="editForm.is_active" type="checkbox" class="toggle toggle-sm toggle-success" /> Active</label>
                        <label class="inline-flex items-center gap-2 text-sm"><input v-model="editForm.is_default" type="checkbox" class="toggle toggle-sm toggle-primary" /> Jadikan default</label>
                        <label class="inline-flex items-center gap-2 text-sm"><input v-model="editForm.supports_budget_items" type="checkbox" class="toggle toggle-sm toggle-info" /> Pakai budget item</label>
                        <label class="inline-flex items-center gap-2 text-sm"><input v-model="editForm.supports_project_board" type="checkbox" class="toggle toggle-sm toggle-secondary" /> Aktifkan board task</label>
                    </div>
                </div>
                <div class="modal-action">
                    <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
                    <button type="button" class="btn btn-primary" :disabled="editForm.processing" @click="submitEdit">Simpan perubahan</button>
                </div>
            </div>
        </dialog>
    </AppLayout>
</template>
