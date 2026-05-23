<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTablePagination from '@/Components/DataTablePagination.vue';
import ExpensePieChart from '@/Components/Charts/ExpensePieChart.vue';
import MonthlyCashflowLineChart from '@/Components/Charts/MonthlyCashflowLineChart.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { useCurrency } from '@/composables/useCurrency';
import { ArrowDownTrayIcon, ArrowLeftIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    totalIn: Number, totalOut: Number, netProfit: Number,
    expenseByCategory: Object, cashIns: Object, cashOuts: Object,
    selectedMonth: Number, selectedYear: Number, years: Array, filters: Object, trendData: Array,
    companyOptions: Array, incomeByCategory: Object, projectBreakdown: Array, expenseBreakdown: Array,
});

const { format } = useCurrency();
const page = usePage();
const month = ref(props.selectedMonth);
const year  = ref(props.selectedYear);
const companyId = ref(props.filters?.company_id ?? page.props.erpCompanyContext?.current_company_id ?? 'all');

watch([month, year, companyId], ([m, y, company]) => {
    router.get(route('reports.monthly'), { month: m, year: y, company_id: company || undefined }, { preserveState: false, replace: true });
});

const MONTHS = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
const exportExcel = () => window.location.href = route('export.monthly', { month: month.value, year: year.value });
</script>

<template>
    <Head title="Rekap Bulanan" />
    <AppLayout>
        <div class="space-y-5">
            <div class="ocn-panel">
                <div class="ocn-panel__head">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Laporan Periodik</p>
                            <h1 class="ocn-panel__title mt-1">Rekap Bulanan</h1>
                            <p class="ocn-panel__desc mt-1">Ringkasan kas masuk, kas keluar, tren harian, dan breakdown pengeluaran pada bulan terpilih.</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 shrink-0">
                            <button class="btn btn-success btn-sm gap-2" @click="exportExcel">
                                <ArrowDownTrayIcon class="w-4 h-4" /> Export Excel
                            </button>
                            <Link class="btn btn-ghost btn-sm shrink-0 gap-1.5" :href="route('erp.reporting')">
                                <ArrowLeftIcon class="h-4 w-4" />
                                Back
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <div class="ocn-panel">
                <div class="ocn-panel__head">
                    <h2 class="ocn-panel__title">Filter Periode</h2>
                </div>
                <div class="card-body">
                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                        <select v-model="companyId" class="select select-bordered select-sm w-full">
                            <option value="all">Semua Perusahaan</option>
                            <option v-for="opt in companyOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                        </select>
                        <select v-model.number="month" class="select select-bordered select-sm w-full">
                            <option v-for="(m, i) in MONTHS" :key="i+1" :value="i+1">{{ m }}</option>
                        </select>
                        <select v-model.number="year" class="select select-bordered select-sm w-full">
                            <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="grid gap-3 md:grid-cols-3">
                <div class="ocn-panel">
                    <div class="ocn-panel__head py-3"><h2 class="ocn-panel__title text-sm font-medium">Kas Masuk</h2></div>
                    <div class="card-body py-4"><p class="text-xl font-bold text-success">{{ format(totalIn) }}</p></div>
                </div>
                <div class="ocn-panel">
                    <div class="ocn-panel__head py-3"><h2 class="ocn-panel__title text-sm font-medium">Kas Keluar</h2></div>
                    <div class="card-body py-4"><p class="text-xl font-bold text-error">{{ format(totalOut) }}</p></div>
                </div>
                <div class="ocn-panel">
                    <div class="ocn-panel__head py-3"><h2 class="ocn-panel__title text-sm font-medium">Net Cashflow</h2></div>
                    <div class="card-body py-4">
                        <p :class="['text-xl font-bold', netProfit >= 0 ? 'text-primary' : 'text-error']">{{ format(netProfit) }}</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-5 xl:grid-cols-[1.5fr_1fr]">
                <div class="ocn-panel">
                    <div class="ocn-panel__head">
                        <h2 class="ocn-panel__title">Tren Harian</h2>
                        <p class="ocn-panel__desc mt-1">Pergerakan kas masuk, kas keluar, dan net cashflow per hari dalam bulan terpilih.</p>
                    </div>
                    <div class="card-body">
                        <MonthlyCashflowLineChart :trend-data="trendData || []" />
                    </div>
                </div>

                <div class="ocn-panel">
                    <div class="ocn-panel__head">
                        <h2 class="ocn-panel__title">Breakdown pengeluaran</h2>
                        <p class="ocn-panel__desc mt-1">Distribusi kategori pengeluaran pada periode ini.</p>
                    </div>
                    <div class="card-body">
                        <ExpensePieChart :data="expenseByCategory" />
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-5 xl:grid-cols-3">
                <div class="ocn-panel">
                    <div class="ocn-panel__head">
                        <h2 class="ocn-panel__title">Kas Masuk per Kategori</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead><tr><th>Kategori</th><th class="text-right">Nominal</th></tr></thead>
                            <tbody>
                                <tr v-for="(amount, category) in (incomeByCategory || {})" :key="category">
                                    <td>{{ String(category).replaceAll('_', ' ') }}</td>
                                    <td class="text-right text-success">{{ format(amount) }}</td>
                                </tr>
                                <tr v-if="!Object.keys(incomeByCategory || {}).length"><td colspan="2" class="text-center py-8 text-base-content/50">Tidak ada kategori pemasukan.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="ocn-panel">
                    <div class="ocn-panel__head">
                        <h2 class="ocn-panel__title">Top Project</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead><tr><th>Project</th><th class="text-right">Cash In</th><th class="text-right">Net</th></tr></thead>
                            <tbody>
                                <tr v-for="row in (projectBreakdown || [])" :key="row.label">
                                    <td class="font-medium">{{ row.label }}</td>
                                    <td class="text-right text-success">{{ format(row.cash_in) }}</td>
                                    <td class="text-right font-semibold" :class="row.net >= 0 ? 'text-primary' : 'text-error'">{{ format(row.net) }}</td>
                                </tr>
                                <tr v-if="!(projectBreakdown || []).length"><td colspan="3" class="text-center py-8 text-base-content/50">Tidak ada data project.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="ocn-panel">
                    <div class="ocn-panel__head">
                        <h2 class="ocn-panel__title">Top Pengeluaran</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead><tr><th>Kategori</th><th class="text-right">Nominal</th></tr></thead>
                            <tbody>
                                <tr v-for="row in (expenseBreakdown || [])" :key="row.label">
                                    <td class="font-medium">{{ row.label }}</td>
                                    <td class="text-right text-error">{{ format(row.amount) }}</td>
                                </tr>
                                <tr v-if="!(expenseBreakdown || []).length"><td colspan="2" class="text-center py-8 text-base-content/50">Tidak ada data pengeluaran.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-5 xl:grid-cols-2">
                <div class="ocn-panel">
                    <div class="ocn-panel__head">
                        <h2 class="ocn-panel__title text-success">Kas Masuk</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead><tr><th>Project</th><th>Kategori</th><th class="text-right">Jumlah</th></tr></thead>
                            <tbody>
                                <tr v-for="(c, i) in (cashIns?.data || [])" :key="i">
                                    <td>{{ c.project_name }}</td>
                                    <td>{{ c.category }}</td>
                                    <td class="text-right text-success">{{ format(c.amount) }}</td>
                                </tr>
                                <tr v-if="!(cashIns?.data || []).length"><td colspan="3" class="text-center py-4 text-base-content/50">Tidak ada data</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <DataTablePagination :paginator="cashIns" :show-per-page="false" />
                </div>

                <div class="ocn-panel">
                    <div class="ocn-panel__head">
                        <h2 class="ocn-panel__title text-error">Kas Keluar</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead><tr><th>Project</th><th>Kategori</th><th class="text-right">Jumlah</th></tr></thead>
                            <tbody>
                                <tr v-for="(c, i) in (cashOuts?.data || [])" :key="i">
                                    <td>{{ c.project_name }}</td>
                                    <td>{{ c.category }}</td>
                                    <td class="text-right text-error">{{ format(c.amount) }}</td>
                                </tr>
                                <tr v-if="!(cashOuts?.data || []).length"><td colspan="3" class="text-center py-4 text-base-content/50">Tidak ada data</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <DataTablePagination :paginator="cashOuts" :show-per-page="false" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
