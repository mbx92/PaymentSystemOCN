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
    projectStatusSummary: Object,
    overduePayments: Array,
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

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <div class="card bg-base-100 shadow lg:col-span-2">
                    <div class="card-body">
                        <div class="flex items-center justify-between mb-1">
                            <h2 class="card-title text-lg">Piutang Project (Interaktif)</h2>
                            <Link :href="route('erp.sales.project-invoices')" class="btn btn-outline btn-xs">Buka Invoice</Link>
                        </div>
                        <p class="text-sm text-base-content/60">Project selesai yang belum lunas sepenuhnya.</p>
                        <div class="mt-3 space-y-3">
                            <div
                                v-for="row in overduePayments"
                                :key="row.id"
                                class="rounded-xl border border-base-300 p-3 hover:border-primary/40"
                            >
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <Link :href="route('projects.show', row.id)" class="font-semibold link link-hover">{{ row.name }}</Link>
                                        <p class="text-xs text-base-content/60">{{ row.client_name }}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-xs text-base-content/60">Sisa</p>
                                        <p class="font-bold text-warning">{{ format(row.remaining_amount) }}</p>
                                    </div>
                                </div>
                                <progress class="progress progress-success w-full mt-2" :value="row.paid_amount" :max="row.total_value || 1" />
                                <div class="text-[11px] text-base-content/60 mt-1">
                                    {{ format(row.paid_amount) }} / {{ format(row.total_value) }}
                                </div>
                            </div>
                            <div v-if="!overduePayments.length" class="rounded-xl border border-dashed border-base-300 p-6 text-center text-base-content/50">
                                Tidak ada piutang project. Semua invoice project sudah lunas.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card bg-base-100 shadow">
                    <div class="card-body">
                        <h2 class="card-title text-lg">Status Project</h2>
                        <div class="space-y-2 text-sm">
                            <div class="flex items-center justify-between rounded-lg bg-base-200 px-3 py-2">
                                <span>Negosiasi</span>
                                <strong>{{ projectStatusSummary.negosiasi || 0 }}</strong>
                            </div>
                            <div class="flex items-center justify-between rounded-lg bg-info/10 px-3 py-2">
                                <span>Berjalan</span>
                                <strong>{{ projectStatusSummary.berjalan || 0 }}</strong>
                            </div>
                            <div class="flex items-center justify-between rounded-lg bg-success/10 px-3 py-2">
                                <span>Selesai</span>
                                <strong>{{ projectStatusSummary.selesai || 0 }}</strong>
                            </div>
                            <div class="flex items-center justify-between rounded-lg bg-error/10 px-3 py-2">
                                <span>Dibatalkan</span>
                                <strong>{{ projectStatusSummary.dibatalkan || 0 }}</strong>
                            </div>
                        </div>
                    </div>
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
                                    <th>Pembayaran</th>
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
                                                :value="p.paid_amount"
                                                :max="p.total_value || 1"
                                            />
                                            <span class="text-xs text-base-content/60">{{ format(p.paid_amount) }}</span>
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
