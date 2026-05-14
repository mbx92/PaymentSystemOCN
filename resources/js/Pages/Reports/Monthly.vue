<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import ExpensePieChart from '@/Components/Charts/ExpensePieChart.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { useCurrency } from '@/composables/useCurrency';
import { ArrowDownTrayIcon, ArrowLeftIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    totalIn: Number, totalOut: Number, netProfit: Number,
    expenseByCategory: Object, cashIns: Array, cashOuts: Array,
    selectedMonth: Number, selectedYear: Number, years: Array,
});

const { format } = useCurrency();
const month = ref(props.selectedMonth);
const year  = ref(props.selectedYear);

watch([month, year], ([m, y]) => {
    router.get(route('reports.monthly'), { month: m, year: y }, { preserveState: false });
});

const MONTHS = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
const exportExcel = () => window.location.href = route('export.monthly', { month: month.value, year: year.value });
</script>

<template>
    <AppLayout>
        <div class="space-y-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-2xl font-bold">Rekap Bulanan</h1>
                <div class="flex flex-wrap items-center gap-2">
                    <button class="btn btn-success btn-sm gap-2" @click="exportExcel">
                        <ArrowDownTrayIcon class="w-4 h-4" /> Export Excel
                    </button>
                    <Link class="btn btn-ghost btn-sm shrink-0 gap-1.5" :href="route('erp.reporting')">
                        <ArrowLeftIcon class="h-4 w-4" />
                        Back
                    </Link>
                </div>
            </div>

            <div class="flex gap-3">
                <select v-model.number="month" class="select select-bordered select-sm">
                    <option v-for="(m, i) in MONTHS" :key="i+1" :value="i+1">{{ m }}</option>
                </select>
                <select v-model.number="year" class="select select-bordered select-sm">
                    <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                </select>
            </div>

            <div class="stats stats-vertical sm:stats-horizontal shadow w-full">
                <div class="stat"><div class="stat-title">Total Masuk</div><div class="stat-value text-xl text-success">{{ format(totalIn) }}</div></div>
                <div class="stat"><div class="stat-title">Total Keluar</div><div class="stat-value text-xl text-error">{{ format(totalOut) }}</div></div>
                <div class="stat">
                    <div class="stat-title">Net Profit</div>
                    <div :class="['stat-value text-xl', netProfit >= 0 ? 'text-primary' : 'text-error']">{{ format(netProfit) }}</div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                <div class="ocn-panel">
                    <div class="ocn-panel__head">
                        <h2 class="ocn-panel__title">Breakdown pengeluaran</h2>
                    </div>
                    <div class="card-body">
                        <ExpensePieChart :data="expenseByCategory" />
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="ocn-panel">
                        <div class="ocn-panel__head">
                            <h2 class="ocn-panel__title text-success">Kas masuk</h2>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="table table-sm">
                                <thead><tr><th>Project</th><th>Kategori</th><th class="text-right">Jumlah</th></tr></thead>
                                <tbody>
                                    <tr v-for="(c, i) in cashIns" :key="i">
                                        <td>{{ c.project_name }}</td>
                                        <td>{{ c.category }}</td>
                                        <td class="text-right text-success">{{ format(c.amount) }}</td>
                                    </tr>
                                    <tr v-if="!cashIns.length"><td colspan="3" class="text-center py-4 text-base-content/50">Tidak ada data</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="ocn-panel">
                        <div class="ocn-panel__head">
                            <h2 class="ocn-panel__title text-error">Kas keluar</h2>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="table table-sm">
                                <thead><tr><th>Project</th><th>Kategori</th><th class="text-right">Jumlah</th></tr></thead>
                                <tbody>
                                    <tr v-for="(c, i) in cashOuts" :key="i">
                                        <td>{{ c.project_name }}</td>
                                        <td>{{ c.category }}</td>
                                        <td class="text-right text-error">{{ format(c.amount) }}</td>
                                    </tr>
                                    <tr v-if="!cashOuts.length"><td colspan="3" class="text-center py-4 text-base-content/50">Tidak ada data</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
