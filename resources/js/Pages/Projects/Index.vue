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

let timer;
watch([search, status], () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(route('projects.index'), { search: search.value, status: status.value }, { preserveState: true, replace: true });
    }, 400);
});
</script>

<template>
    <AppLayout>
        <div class="space-y-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-2xl font-bold">Projects</h1>
                <Link :href="route('projects.create')" class="btn btn-primary btn-sm gap-2">
                    <PlusIcon class="w-4 h-4" /> Tambah Project
                </Link>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap gap-3">
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
                                    <th>Status</th>
                                    <th>Nilai Kontrak</th>
                                    <th>Mulai</th>
                                    <th>Termin</th>
                                    <th class="text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="p in projects.data" :key="p.id">
                                    <td>
                                        <Link :href="route('projects.show', p.id)" class="link link-hover font-medium">
                                            {{ p.name }}
                                        </Link>
                                    </td>
                                    <td>{{ p.client_name }}</td>
                                    <td><StatusBadge :status="p.status" /></td>
                                    <td class="font-medium">{{ format(p.total_value) }}</td>
                                    <td class="text-sm text-base-content/70">{{ p.started_at ?? '-' }}</td>
                                    <td>
                                        <div class="flex items-center gap-2">
                                            <progress class="progress progress-success w-16" :value="p.paid_terms" :max="p.total_terms || 3" />
                                            <span class="text-xs text-base-content/60">{{ p.paid_terms }}/{{ p.total_terms }}</span>
                                        </div>
                                    </td>
                                    <td class="text-right">
                                        <div class="flex justify-end gap-1">
                                            <Link :href="route('projects.edit', p.id)" class="btn btn-ghost btn-xs">Edit</Link>
                                            <Link :href="route('projects.show', p.id)" class="btn btn-ghost btn-xs">Detail</Link>
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
