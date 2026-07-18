<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import RevenueLineChart from '@/Components/Charts/RevenueLineChart.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { useCurrency } from '@/composables/useCurrency';

const props = defineProps({
    stats: Object,
    monthlyData: Array,
    recentProjects: Array,
    projectStatusSummary: Object,
    overduePayments: Array,
    selectedYear: Number,
    years: Array,
    filters: Object,
});

const { format } = useCurrency();
const page = usePage();
const erpCompanyContext = () => page.props.erpCompanyContext ?? null;

const normalizeCompanyId = (value) => {
    if (value === null || value === undefined || value === '' || value === 'all') {
        return 'all';
    }

    return String(value);
};

const selectedYear = ref(props.selectedYear ?? new Date().getFullYear());
const companyId = ref(
    normalizeCompanyId(props.filters?.company_id ?? erpCompanyContext()?.current_company_id ?? 'all'),
);

const reloadDashboard = () => {
    router.get(route('dashboard'), {
        year: selectedYear.value,
        company_id: companyId.value,
    }, { preserveState: true, replace: true });
};

watch(selectedYear, reloadDashboard);
watch(companyId, reloadDashboard);
</script>

<template>
    <AppLayout>
        <div class="space-y-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-3xl font-black tracking-tight text-base-content">Dashboard</h1>
                    <p class="text-sm text-base-content/60 mt-1">Ringkasan kesehatan keuangan dan aktivitas project.</p>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <select
                        v-if="erpCompanyContext()?.companies?.length"
                        v-model="companyId"
                        class="select select-bordered select-sm w-full sm:w-auto"
                    >
                        <option value="all">Semua Usaha</option>
                        <option
                            v-for="company in erpCompanyContext().companies"
                            :key="company.id"
                            :value="String(company.id)"
                        >
                            {{ company.name }}
                        </option>
                    </select>
                    <select v-model.number="selectedYear" class="select select-bordered select-sm w-full sm:w-auto">
                        <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                    </select>
                </div>
            </div>

            <!-- Stat Cards -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <div class="ocn-panel ocn-stat-card">
                    <div class="card-body p-5">
                        <h3 class="text-sm font-medium text-base-content/70">Total Pendapatan</h3>
                        <p class="text-2xl font-bold text-success mt-1">{{ format(stats.total_income) }}</p>
                        <p class="text-xs text-base-content/50 mt-2">Kas masuk sepanjang {{ selectedYear }}</p>
                    </div>
                </div>
                <div class="ocn-panel ocn-stat-card">
                    <div class="card-body p-5">
                        <h3 class="text-sm font-medium text-base-content/70">Total Pengeluaran</h3>
                        <p class="text-2xl font-bold text-error mt-1">{{ format(stats.total_expense) }}</p>
                        <p class="text-xs text-base-content/50 mt-2">Kas keluar sepanjang {{ selectedYear }}</p>
                    </div>
                </div>
                <div class="ocn-panel ocn-stat-card">
                    <div class="card-body p-5">
                        <h3 class="text-sm font-medium text-base-content/70">Net Cashflow</h3>
                        <p :class="['text-2xl font-bold mt-1', stats.net_cashflow >= 0 ? 'text-primary' : 'text-error']">
                            {{ format(stats.net_cashflow) }}
                        </p>
                        <p class="text-xs text-base-content/50 mt-2">Disamakan dengan accounting overview untuk periode {{ selectedYear }}</p>
                    </div>
                </div>
                <div class="ocn-panel ocn-stat-card">
                    <div class="card-body p-5">
                        <h3 class="text-sm font-medium text-base-content/70">Saldo Awal Kas</h3>
                        <p :class="['text-2xl font-bold mt-1', stats.opening_cash_balance >= 0 ? 'text-primary' : 'text-error']">
                            {{ format(stats.opening_cash_balance) }}
                        </p>
                        <p class="text-xs text-base-content/50 mt-2">Saldo buku kas sebelum {{ selectedYear }} dimulai</p>
                    </div>
                </div>
                <div class="ocn-panel ocn-stat-card">
                    <div class="card-body p-5">
                        <h3 class="text-sm font-medium text-base-content/70">Saldo Akhir Kas</h3>
                        <p :class="['text-2xl font-bold mt-1', stats.ending_cash_balance >= 0 ? 'text-primary' : 'text-error']">
                            {{ format(stats.ending_cash_balance) }}
                        </p>
                        <p class="text-xs text-base-content/50 mt-2">Saldo awal ditambah net cashflow periode {{ selectedYear }}</p>
                    </div>
                </div>
                <div class="ocn-panel ocn-stat-card">
                    <div class="card-body p-5">
                        <h3 class="text-sm font-medium text-base-content/70">Project Aktif</h3>
                        <p class="text-2xl font-bold text-info mt-1">{{ stats.active_count }}</p>
                        <p class="text-xs text-base-content/50 mt-2">Project dengan status berjalan</p>
                    </div>
                </div>
            </div>

            <!-- Chart -->
            <div class="ocn-panel">
                <div class="ocn-panel__head">
                    <h2 class="ocn-panel__title">Pendapatan vs pengeluaran</h2>
                    <p class="ocn-panel__desc">Line chart bulanan sepanjang {{ selectedYear }}</p>
                </div>
                <div class="card-body">
                    <RevenueLineChart :monthly-data="monthlyData" />
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <div class="ocn-panel lg:col-span-2">
                    <div class="ocn-panel__head flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h2 class="ocn-panel__title">Piutang project</h2>
                            <p class="ocn-panel__desc">Project selesai yang belum lunas sepenuhnya. Daftar project masih global karena master project belum punya flag usaha.</p>
                        </div>
                        <Link :href="route('erp.sales.project-invoices')" class="btn btn-outline btn-xs shrink-0">Buka invoice</Link>
                    </div>
                    <div class="card-body">
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

                <div class="ocn-panel">
                    <div class="ocn-panel__head">
                        <h2 class="ocn-panel__title">Status project</h2>
                        <p class="ocn-panel__desc">Ringkasan project masih global lintas usaha.</p>
                    </div>
                    <div class="card-body">
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
            <div class="ocn-panel">
                <div class="ocn-panel__head flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h2 class="ocn-panel__title">Project terbaru</h2>
                        <p class="ocn-panel__desc">Diurutkan berdasarkan tanggal project terbaru. Daftar ini masih global lintas usaha.</p>
                    </div>
                    <Link :href="route('projects.index')" class="btn btn-primary btn-sm shrink-0">Lihat semua</Link>
                </div>
                <div class="card-body p-0">
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
