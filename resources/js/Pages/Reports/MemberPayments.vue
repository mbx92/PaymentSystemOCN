<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { useCurrency } from '@/composables/useCurrency';
import { ArrowDownTrayIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ members: Array, distributions: Array, totalPay: Number, filters: Object, years: Array });
const { format } = useCurrency();

const userId = ref(props.filters.user_id ?? '');
const year   = ref(props.filters.year ?? '');

watch([userId, year], ([u, y]) => {
    router.get(route('reports.member-payments'), { user_id: u, year: y }, { preserveState: false });
});

const exportExcel = () => window.location.href = route('export.member-payments', { user_id: userId.value, year: year.value });
</script>

<template>
    <AppLayout>
        <div class="space-y-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-2xl font-bold">Laporan Pembayaran Anggota</h1>
                <button class="btn btn-success btn-sm gap-2" @click="exportExcel">
                    <ArrowDownTrayIcon class="w-4 h-4" /> Export Excel
                </button>
            </div>

            <div class="flex gap-3">
                <select v-model="userId" class="select select-bordered select-sm">
                    <option value="">Semua Anggota</option>
                    <option v-for="m in members" :key="m.id" :value="m.id">{{ m.name }}</option>
                </select>
                <select v-model="year" class="select select-bordered select-sm">
                    <option value="">Semua Tahun</option>
                    <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                </select>
            </div>

            <div class="stats shadow">
                <div class="stat py-3">
                    <div class="stat-title">Total Pembayaran</div>
                    <div class="stat-value text-xl text-primary">{{ format(totalPay) }}</div>
                </div>
            </div>

            <div class="card bg-base-100 shadow">
                <div class="overflow-x-auto">
                    <table class="table table-zebra text-sm">
                        <thead>
                            <tr><th>Nama</th><th>Project</th><th>Status</th><th>Peran</th><th class="text-right">%</th><th class="text-right">Base Pay</th><th class="text-right">Bonus</th><th class="text-right">Total</th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="(d, i) in distributions" :key="i">
                                <td class="font-medium">{{ d.user_name }}</td>
                                <td>{{ d.project_name }}</td>
                                <td><StatusBadge :status="d.project_status" /></td>
                                <td class="capitalize">{{ d.role_in_project }}</td>
                                <td class="text-right">{{ d.percentage }}%</td>
                                <td class="text-right">{{ format(d.base_pay) }}</td>
                                <td class="text-right">{{ format(d.bonus) }}</td>
                                <td class="text-right font-semibold text-primary">{{ format(d.total_pay) }}</td>
                            </tr>
                            <tr v-if="!distributions.length">
                                <td colspan="8" class="text-center py-10 text-base-content/50">Tidak ada data</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
