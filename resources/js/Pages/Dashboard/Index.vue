<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import RevenueBarChart from '@/Components/Charts/RevenueBarChart.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Link, router } from '@inertiajs/vue3';
import { useCurrency } from '@/composables/useCurrency';

const props = defineProps({
    stats: Object,
    monthlyData: Array,
    recentProjects: Array,
    selectedYear: Number,
    years: Array,
});

const { format } = useCurrency();

const changeYear = (year) => {
    router.get(route('dashboard'), { year }, { preserveState: true });
};
</script>

<template>
    <AppLayout>
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-black tracking-tight text-base-content">Dashboard</h1>
                    <p class="text-sm text-base-content/60 mt-1">Ringkasan kesehatan keuangan dan aktivitas project.</p>
                </div>
                <select class="select select-bordered select-sm" :value="selectedYear" @change="changeYear($event.target.value)">
                    <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                </select>
            </div>

            <!-- Stat Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="card ocn-stat-card bg-base-100 shadow">
                    <div class="card-body p-5">
                        <h3 class="text-sm font-medium text-base-content/70">Total Pendapatan</h3>
                        <p class="text-2xl font-bold text-success mt-1">{{ format(stats.total_income) }}</p>
                        <p class="text-xs text-base-content/50 mt-2">Akumulasi semua kas masuk</p>
                    </div>
                </div>
                <div class="card ocn-stat-card bg-base-100 shadow">
                    <div class="card-body p-5">
                        <h3 class="text-sm font-medium text-base-content/70">Total Pengeluaran</h3>
                        <p class="text-2xl font-bold text-error mt-1">{{ format(stats.total_expense) }}</p>
                        <p class="text-xs text-base-content/50 mt-2">Biaya tim, referral, operasional</p>
                    </div>
                </div>
                <div class="card ocn-stat-card bg-base-100 shadow">
                    <div class="card-body p-5">
                        <h3 class="text-sm font-medium text-base-content/70">Laba Bersih</h3>
                        <p :class="['text-2xl font-bold mt-1', stats.net_profit >= 0 ? 'text-primary' : 'text-error']">
                            {{ format(stats.net_profit) }}
                        </p>
                        <p class="text-xs text-base-content/50 mt-2">Selisih kas masuk dan keluar</p>
                    </div>
                </div>
                <div class="card ocn-stat-card bg-base-100 shadow">
                    <div class="card-body p-5">
                        <h3 class="text-sm font-medium text-base-content/70">Project Aktif</h3>
                        <p class="text-2xl font-bold text-info mt-1">{{ stats.active_count }}</p>
                        <p class="text-xs text-base-content/50 mt-2">Project dengan status berjalan</p>
                    </div>
                </div>
            </div>

            <!-- Chart -->
            <div class="card bg-base-100 shadow">
                <div class="card-body">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="card-title text-lg">Pendapatan vs Pengeluaran</h2>
                            <p class="text-sm text-base-content/60">Perbandingan bulanan sepanjang {{ selectedYear }}</p>
                        </div>
                    </div>
                    <RevenueBarChart :monthly-data="monthlyData" />
                </div>
            </div>

            <!-- Recent Projects -->
            <div class="card bg-base-100 shadow">
                <div class="card-body">
                    <div class="flex items-center justify-between mb-2">
                        <div>
                            <h2 class="card-title text-lg">Project Terbaru</h2>
                            <p class="text-sm text-base-content/60">Pantau status dan progress termin terbaru</p>
                        </div>
                        <Link :href="route('projects.index')" class="btn btn-primary btn-sm">Lihat semua</Link>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="table table-zebra table-sm">
                            <thead>
                                <tr>
                                    <th>Project</th>
                                    <th>Klien</th>
                                    <th>Status</th>
                                    <th>Nilai</th>
                                    <th>Termin</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="p in recentProjects" :key="p.id">
                                    <td>
                                        <Link :href="route('projects.show', p.id)" class="link link-hover font-medium">
                                            {{ p.name }}
                                        </Link>
                                    </td>
                                    <td class="text-base-content/70">{{ p.client_name }}</td>
                                    <td><StatusBadge :status="p.status" /></td>
                                    <td class="font-medium">{{ format(p.total_value) }}</td>
                                    <td>
                                        <div class="flex items-center gap-2">
                                            <progress
                                                class="progress progress-success w-16"
                                                :value="p.paid_terms"
                                                :max="p.total_terms || 3"
                                            />
                                            <span class="text-xs text-base-content/60">{{ p.paid_terms }}/{{ p.total_terms }}</span>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!recentProjects.length">
                                    <td colspan="5" class="text-center text-base-content/50 py-8">Belum ada project</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
