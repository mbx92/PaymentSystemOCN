<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { useCurrency } from '@/composables/useCurrency';
import { ArrowDownTrayIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ projects: Array, filters: Object });
const { format } = useCurrency();

const search = ref(props.filters.search ?? '');
let timer;
watch(search, (v) => {
    clearTimeout(timer);
    timer = setTimeout(() => router.get(route('reports.project-profit'), { search: v }, { preserveState: true }), 400);
});

const marginClass = (margin) => {
    if (margin < 0) return 'text-error font-bold';
    if (margin < 20) return 'text-warning font-semibold';
    return 'text-success';
};

const exportExcel = () => window.location.href = route('export.project-profit', { search: search.value });
</script>

<template>
    <AppLayout>
        <div class="space-y-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-2xl font-bold">Laporan Laba per Project</h1>
                <button class="btn btn-success btn-sm gap-2" @click="exportExcel">
                    <ArrowDownTrayIcon class="w-4 h-4" /> Export Excel
                </button>
            </div>

            <input v-model="search" type="text" placeholder="Cari project…" class="input input-bordered input-sm max-w-xs" />

            <div class="ocn-panel">
                <div class="ocn-panel__head">
                    <h2 class="ocn-panel__title">Ringkasan laba per project</h2>
                    <p class="ocn-panel__desc">Data mengikuti kata kunci pencarian di atas.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="table table-zebra text-sm">
                        <thead>
                            <tr>
                                <th>Project</th><th>Klien</th><th>Status</th>
                                <th class="text-right">Nilai</th>
                                <th class="text-right">Kas Masuk</th>
                                <th class="text-right">Komisi</th>
                                <th class="text-right">Biaya Tim</th>
                                <th class="text-right">Operasional</th>
                                <th class="text-right">Laba</th>
                                <th class="text-right">Margin</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="p in projects" :key="p.id">
                                <td class="font-medium">{{ p.name }}</td>
                                <td>{{ p.client_name }}</td>
                                <td><StatusBadge :status="p.status" /></td>
                                <td class="text-right">{{ format(p.total_value) }}</td>
                                <td class="text-right text-success">{{ format(p.cash_in) }}</td>
                                <td class="text-right text-warning">{{ format(p.referral) }}</td>
                                <td class="text-right">{{ format(p.team_cost) }}</td>
                                <td class="text-right">{{ format(p.operational) }}</td>
                                <td class="text-right font-semibold" :class="p.profit >= 0 ? 'text-success' : 'text-error'">{{ format(p.profit) }}</td>
                                <td class="text-right" :class="marginClass(p.margin)">{{ p.margin }}%</td>
                            </tr>
                            <tr v-if="!projects.length">
                                <td colspan="10" class="text-center py-10 text-base-content/50">Tidak ada data</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
