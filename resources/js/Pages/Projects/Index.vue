<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { useCurrency } from '@/composables/useCurrency';
import { MagnifyingGlassIcon, PlusIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ projects: Object, filters: Object });
const { format } = useCurrency();

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');
const projectType = ref(props.filters.project_type ?? '');

let timer;
watch([search, status, projectType], () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(route('projects.index'), { search: search.value, status: status.value, project_type: projectType.value }, { preserveState: true, replace: true });
    }, 400);
});

const projectTypeLabel = (value) => {
    if (value === 'cctv_installation') return 'CCTV Installation';
    if (value === 'system_website_development') return 'System/Website Development';
    return value;
};
</script>

<template>
    <AppLayout>
        <div class="space-y-5">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Projects Workspace</p>
                        <h1 class="mt-2 text-3xl font-bold tracking-tight">Projects</h1>
                        <p class="mt-2 text-sm text-base-content/70">Kelola daftar project aktif dari tahap negosiasi sampai selesai.</p>
                    </div>
                    <Link :href="route('erp.projects')" class="btn btn-ghost btn-sm">Back</Link>
                </div>
            </div>

            <!-- Utility Card -->
            <div class="card bg-base-100 shadow">
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
                            <Link :href="route('projects.create')" class="btn btn-primary btn-sm gap-2">
                                <PlusIcon class="w-4 h-4" /> Tambah Project
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="card bg-base-100 shadow">
                <div class="card-body p-0">
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

                    <!-- Pagination -->
                    <div v-if="projects.last_page > 1" class="flex justify-center p-4 gap-2">
                        <Link
                            v-for="link in projects.links" :key="link.label"
                            :href="link.url ?? '#'"
                            v-html="link.label"
                            :class="['btn btn-sm', link.active ? 'btn-primary' : 'btn-ghost', !link.url ? 'btn-disabled' : '']"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
