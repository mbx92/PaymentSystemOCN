<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import DataTablePagination from '@/Components/DataTablePagination.vue';
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { computed, ref } from 'vue';

const props = defineProps({ users: Object, roles: Array, companies: Array, filters: Object });

const roleOptions = computed(() => {
    if (Array.isArray(props.roles) && props.roles.length) {
        return props.roles;
    }

    return [
        { id: 'admin', name: 'admin' },
        { id: 'manajer', name: 'manajer' },
        { id: 'anggota', name: 'anggota' },
    ];
});

const defaultCompanyId = computed(() => props.companies?.[0]?.id ?? '');

const form = useForm({ name: '', email: '', password: '', password_confirmation: '', role: 'anggota', company_id: defaultCompanyId.value });
const editingId = ref(null);
const editForm = useForm({ name: '', email: '', password: '', password_confirmation: '', role: 'anggota', company_id: '' });

const openAddUser = () => {
    form.clearErrors();
    document.getElementById('modal-add-user')?.showModal();
};

const submitAdd = () => form.post(route('users.store'), {
    onSuccess: () => {
        form.reset();
        form.role = 'anggota';
        form.company_id = defaultCompanyId.value;
        document.getElementById('modal-add-user')?.close();
    },
});

const openEdit = (u) => {
    editingId.value = u.id;
    editForm.name  = u.name;
    editForm.email = u.email;
    editForm.role  = u.role;
    editForm.company_id = u.company_id ?? defaultCompanyId.value;
    editForm.password = '';
    editForm.password_confirmation = '';
    document.getElementById('modal-edit-user')?.showModal();
};

const submitEdit = () => editForm.put(route('users.update', editingId.value), {
    onSuccess: () => document.getElementById('modal-edit-user')?.close(),
});

const deletingId = ref(null);
const confirmDelete = (id) => {
    deletingId.value = id;
    document.getElementById('modal-delete-user')?.showModal();
};
const doDelete = () => { router.delete(route('users.destroy', deletingId.value)); };
</script>

<template>
    <Head title="Kelola User - User Account" />
    <AppLayout>
        <div class="space-y-5">
            <div class="ocn-panel">
                <div class="ocn-panel__head">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Kelola User</p>
                            <h1 class="ocn-panel__title mt-1">User Account</h1>
                            <p class="ocn-panel__desc mt-1">Kelola akun pengguna ERP dan penugasan role admin, manajer, atau anggota.</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 shrink-0">
                            <Link class="btn btn-ghost btn-sm gap-1.5" :href="route('users.index')">
                                <ArrowLeftIcon class="h-4 w-4" />
                                Back
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <div class="ocn-panel">
                <div class="ocn-panel__head flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="ocn-panel__title">Daftar pengguna</h2>
                        <p class="ocn-panel__desc">Akun yang dapat mengakses ERP beserta role yang sedang dipakai.</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="badge badge-ghost badge-sm">{{ users.total ?? users.data?.length ?? 0 }} akun</span>
                        <button type="button" class="btn btn-primary btn-sm" @click="openAddUser">
                            + Tambah User
                        </button>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="table table-zebra">
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Email</th>
                                <th>Usaha</th>
                                <th>Role</th>
                                <th class="text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="u in users.data" :key="u.id">
                                <td class="font-medium">{{ u.name }}</td>
                                <td>{{ u.email }}</td>
                                <td>{{ u.company_name || '-' }}</td>
                                <td><StatusBadge :status="u.role" /></td>
                                <td class="text-right">
                                    <div class="flex justify-end gap-1">
                                        <button class="btn btn-ghost btn-xs" @click="openEdit(u)">Edit</button>
                                        <button class="btn btn-ghost btn-xs text-error" @click="confirmDelete(u.id)">Hapus</button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!users.data.length">
                                <td colspan="5" class="py-10 text-center text-base-content/50">Belum ada akun pengguna.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <DataTablePagination
                    :paginator="users"
                    @update:per-page="(n) => router.get(route('users.accounts'), { per_page: n }, { preserveState: true, replace: true })"
                />
            </div>
        </div>

        <!-- Modal: Add User -->
        <dialog id="modal-add-user" class="modal">
            <div class="modal-box">
                <h3 class="font-bold text-lg">Tambah User</h3>
                <div class="space-y-3 mt-4">
                    <div>
                        <label class="label"><span class="label-text">Nama <span class="text-error">*</span></span></label>
                        <input v-model="form.name" type="text" class="input input-bordered w-full" :class="form.errors.name ? 'input-error' : ''" />
                        <p v-if="form.errors.name" class="text-error text-xs mt-1">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label class="label"><span class="label-text">Email <span class="text-error">*</span></span></label>
                        <input v-model="form.email" type="email" class="input input-bordered w-full" :class="form.errors.email ? 'input-error' : ''" />
                        <p v-if="form.errors.email" class="text-error text-xs mt-1">{{ form.errors.email }}</p>
                    </div>
                    <div>
                        <label class="label"><span class="label-text">Password <span class="text-error">*</span></span></label>
                        <input v-model="form.password" type="password" class="input input-bordered w-full" />
                    </div>
                    <div>
                        <label class="label"><span class="label-text">Konfirmasi Password</span></label>
                        <input v-model="form.password_confirmation" type="password" class="input input-bordered w-full" />
                    </div>
                    <div>
                        <label class="label"><span class="label-text">Role</span></label>
                        <select v-model="form.role" class="select select-bordered w-full">
                            <option v-for="role in roleOptions" :key="role.id" :value="role.name">
                                {{ role.name }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="label"><span class="label-text">Usaha Default</span></label>
                        <select v-model="form.company_id" class="select select-bordered w-full">
                            <option value="">Default sistem</option>
                            <option v-for="company in companies" :key="company.id" :value="company.id">
                                {{ company.name }}
                            </option>
                        </select>
                        <p class="text-xs text-base-content/60 mt-1">Transaksi accounting dari user ini otomatis masuk ke usaha ini.</p>
                    </div>
                </div>
                <div class="modal-action">
                    <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
                    <button type="button" class="btn btn-primary" :disabled="form.processing" @click="submitAdd">Simpan</button>
                </div>
            </div>
        </dialog>

        <!-- Modal: Edit User -->
        <dialog id="modal-edit-user" class="modal">
            <div class="modal-box">
                <h3 class="font-bold text-lg">Edit User</h3>
                <div class="space-y-3 mt-4">
                    <div>
                        <label class="label"><span class="label-text">Nama</span></label>
                        <input v-model="editForm.name" type="text" class="input input-bordered w-full" />
                    </div>
                    <div>
                        <label class="label"><span class="label-text">Email</span></label>
                        <input v-model="editForm.email" type="email" class="input input-bordered w-full" />
                        <p v-if="editForm.errors.email" class="text-error text-xs mt-1">{{ editForm.errors.email }}</p>
                    </div>
                    <div>
                        <label class="label"><span class="label-text">Password Baru (kosongkan jika tidak diubah)</span></label>
                        <input v-model="editForm.password" type="password" class="input input-bordered w-full" />
                    </div>
                    <div>
                        <label class="label"><span class="label-text">Konfirmasi Password Baru</span></label>
                        <input v-model="editForm.password_confirmation" type="password" class="input input-bordered w-full" />
                    </div>
                    <div>
                        <label class="label"><span class="label-text">Role</span></label>
                        <select v-model="editForm.role" class="select select-bordered w-full">
                            <option v-for="role in roleOptions" :key="role.id" :value="role.name">
                                {{ role.name }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="label"><span class="label-text">Usaha Default</span></label>
                        <select v-model="editForm.company_id" class="select select-bordered w-full">
                            <option value="">Default sistem</option>
                            <option v-for="company in companies" :key="company.id" :value="company.id">
                                {{ company.name }}
                            </option>
                        </select>
                        <p class="text-xs text-base-content/60 mt-1">Jurnal baru yang diposting user ini mengikuti usaha default ini.</p>
                    </div>
                </div>
                <div class="modal-action">
                    <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
                    <button type="button" class="btn btn-primary" :disabled="editForm.processing" @click="submitEdit">Simpan</button>
                </div>
            </div>
        </dialog>

        <ConfirmModal id="modal-delete-user" title="Hapus User" message="Yakin hapus user ini?" @confirm="doDelete" />
    </AppLayout>
</template>
