<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({ users: Array, roles: Array });

const form = useForm({ name: '', email: '', password: '', password_confirmation: '', role: 'anggota' });
const editingId = ref(null);
const editForm = useForm({ name: '', email: '', password: '', password_confirmation: '', role: 'anggota' });

const submitAdd = () => form.post(route('users.store'), {
    onSuccess: () => { form.reset(); document.getElementById('modal-add-user').close(); }
});

const openEdit = (u) => {
    editingId.value = u.id;
    editForm.name  = u.name;
    editForm.email = u.email;
    editForm.role  = u.role;
    editForm.password = '';
    editForm.password_confirmation = '';
    document.getElementById('modal-edit-user').showModal();
};

const submitEdit = () => editForm.put(route('users.update', editingId.value), {
    onSuccess: () => document.getElementById('modal-edit-user').close()
});

const deletingId = ref(null);
const confirmDelete = (id) => { deletingId.value = id; document.getElementById('modal-delete-user').showModal(); };
const doDelete = () => { router.delete(route('users.destroy', deletingId.value)); };
</script>

<template>
    <AppLayout>
        <div class="space-y-5">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">Kelola User</h1>
                <button class="btn btn-primary btn-sm" onclick="document.getElementById('modal-add-user').showModal()">+ Tambah User</button>
            </div>

            <div class="ocn-panel">
                <div class="ocn-panel__head">
                    <h2 class="ocn-panel__title">Daftar pengguna</h2>
                    <p class="ocn-panel__desc">Akun yang dapat mengakses ERP beserta perannya.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="table table-zebra">
                        <thead><tr><th>Nama</th><th>Email</th><th>Role</th><th></th></tr></thead>
                        <tbody>
                            <tr v-for="u in users" :key="u.id">
                                <td class="font-medium">{{ u.name }}</td>
                                <td>{{ u.email }}</td>
                                <td><StatusBadge :status="u.role" /></td>
                                <td>
                                    <div class="flex gap-1">
                                        <button class="btn btn-ghost btn-xs" @click="openEdit(u)">Edit</button>
                                        <button class="btn btn-ghost btn-xs text-error" @click="confirmDelete(u.id)">Hapus</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
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
                            <option value="admin">Admin</option>
                            <option value="manajer">Manajer</option>
                            <option value="anggota">Anggota</option>
                        </select>
                    </div>
                </div>
                <div class="modal-action">
                    <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
                    <button class="btn btn-primary" :disabled="form.processing" @click="submitAdd">Simpan</button>
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
                            <option value="admin">Admin</option>
                            <option value="manajer">Manajer</option>
                            <option value="anggota">Anggota</option>
                        </select>
                    </div>
                </div>
                <div class="modal-action">
                    <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
                    <button class="btn btn-primary" :disabled="editForm.processing" @click="submitEdit">Simpan</button>
                </div>
            </div>
        </dialog>

        <ConfirmModal id="modal-delete-user" title="Hapus User" message="Yakin hapus user ini?" @confirm="doDelete" />
    </AppLayout>
</template>
