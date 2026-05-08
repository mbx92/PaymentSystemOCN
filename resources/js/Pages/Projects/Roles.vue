<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    roles: Array,
});

const form = useForm({
    name: '',
});

const submit = () => {
    form.post(route('erp.projects.team-roles.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset('name'),
    });
};

const removeRole = (id) => {
    form.delete(route('erp.projects.team-roles.destroy', id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Master Role Tim Project" />
    <AppLayout>
        <div class="space-y-5">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Projects Workspace</p>
                        <h1 class="mt-2 text-3xl font-bold tracking-tight">Master Role Tim Project</h1>
                        <p class="mt-2 text-sm text-base-content/70">Role global untuk assign anggota tim pada semua project.</p>
                    </div>
                    <Link :href="route('erp.projects')" class="btn btn-ghost btn-sm">Back</Link>
                </div>
            </div>

            <div class="ocn-panel">
                <div class="ocn-panel__head">
                    <h2 class="ocn-panel__title">Tambah role tim</h2>
                </div>
                <div class="card-body">
                    <div class="flex gap-2">
                        <input v-model="form.name" type="text" class="input input-bordered w-full max-w-md" placeholder="Contoh: Backend Engineer" />
                        <button class="btn btn-primary" :disabled="form.processing" @click="submit">Simpan</button>
                    </div>
                    <p v-if="form.errors.name" class="text-error text-xs mt-1">{{ form.errors.name }}</p>
                    <p v-if="form.errors.role" class="text-error text-xs mt-1">{{ form.errors.role }}</p>
                </div>
            </div>

            <div class="ocn-panel">
                <div class="ocn-panel__head">
                    <h2 class="ocn-panel__title">Daftar role</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Nama Role</th>
                                <th class="text-right"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="role in roles" :key="role.id">
                                <td class="font-medium">{{ role.name }}</td>
                                <td class="text-right">
                                    <button class="btn btn-ghost btn-xs text-error" @click="removeRole(role.id)">Hapus</button>
                                </td>
                            </tr>
                            <tr v-if="!roles.length">
                                <td colspan="2" class="text-center py-6 text-base-content/50">Belum ada role.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

