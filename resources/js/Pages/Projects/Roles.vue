<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTablePagination from '@/Components/DataTablePagination.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { reactive, watch } from 'vue';

const props = defineProps({
    roles: Object,
    filters: { type: Object, default: () => ({}) },
});

const form = useForm({
    name: '',
});
const filters = reactive({
    q: props.filters?.q ?? '',
    status: props.filters?.status ?? '',
    per_page: Number(props.filters?.per_page ?? props.roles?.per_page ?? 25),
});
const roleRows = () => props.roles?.data ?? [];

let timer;
watch(filters, (val) => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(route('erp.projects.team-roles.index'), val, { preserveState: true, replace: true });
    }, 250);
}, { deep: true });

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
            <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Projects Workspace</p>
              <h1 class="ocn-panel__title mt-1">Master Role Tim Project</h1>
              <p class="ocn-panel__desc mt-1">Role global untuk assign anggota tim pada semua project.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
              <Link :href="route('erp.projects')" class="btn btn-ghost btn-sm shrink-0 gap-1.5"><ArrowLeftIcon class="h-4 w-4" />
                            Back</Link>
            </div>
          </div>
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
                    <div class="flex flex-wrap items-center gap-3">
                        <input v-model="filters.q" type="search" class="input input-bordered input-sm w-full max-w-sm" placeholder="Cari nama role..." />
                        <select v-model="filters.status" class="select select-bordered select-sm">
                            <option value="">Semua Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
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
                            <tr v-for="role in roleRows()" :key="role.id">
                                <td class="font-medium">{{ role.name }}</td>
                                <td class="text-right">
                                    <button class="btn btn-ghost btn-xs text-error" @click="removeRole(role.id)">Hapus</button>
                                </td>
                            </tr>
                            <tr v-if="!roleRows().length">
                                <td colspan="2" class="text-center py-6 text-base-content/50">Belum ada role.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <DataTablePagination :paginator="roles" @update:per-page="(n) => { filters.per_page = n; }" />
            </div>
        </div>
    </AppLayout>
</template>
