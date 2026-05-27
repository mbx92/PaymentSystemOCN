<script setup>
import { computed } from 'vue';
import { Line } from 'vue-chartjs';
import {
    CategoryScale,
    Chart as ChartJS,
    Filler,
    Legend,
    LineElement,
    LinearScale,
    PointElement,
    Title,
    Tooltip,
} from 'chart.js';

ChartJS.register(Title, Tooltip, Legend, LineElement, PointElement, CategoryScale, LinearScale, Filler);

const props = defineProps({
    monthlyData: {
        type: Array,
        default: () => [],
    },
});

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

const chartData = computed(() => ({
    labels: (props.monthlyData ?? []).map((row) => MONTHS[(row.month ?? 1) - 1] ?? '-'),
    datasets: [
        {
            label: 'Pendapatan',
            data: (props.monthlyData ?? []).map((row) => Number(row.income ?? 0)),
            borderColor: 'rgba(37, 99, 235, 0.96)',
            backgroundColor: 'rgba(37, 99, 235, 0.10)',
            fill: true,
            tension: 0.34,
            pointRadius: 3,
            pointHoverRadius: 5,
            borderWidth: 3,
        },
        {
            label: 'Pengeluaran',
            data: (props.monthlyData ?? []).map((row) => Number(row.expense ?? 0)),
            borderColor: 'rgba(239, 68, 68, 0.95)',
            backgroundColor: 'rgba(239, 68, 68, 0.07)',
            fill: true,
            tension: 0.34,
            pointRadius: 3,
            pointHoverRadius: 5,
            borderWidth: 3,
        },
        {
            label: 'Net Cashflow',
            data: (props.monthlyData ?? []).map((row) => Number(row.net ?? 0)),
            borderColor: 'rgba(22, 163, 74, 0.95)',
            backgroundColor: 'rgba(22, 163, 74, 0)',
            fill: false,
            tension: 0.28,
            pointRadius: 2,
            pointHoverRadius: 4,
            borderDash: [8, 4],
            borderWidth: 2,
        },
    ],
}));

const options = {
    responsive: true,
    maintainAspectRatio: false,
    interaction: {
        intersect: false,
        mode: 'index',
    },
    plugins: {
        legend: {
            position: 'top',
            labels: {
                boxWidth: 10,
                boxHeight: 10,
                useBorderRadius: true,
            },
        },
        tooltip: {
            callbacks: {
                label: (ctx) => `${ctx.dataset.label}: Rp ${Number(ctx.parsed.y ?? 0).toLocaleString('id-ID')}`,
            },
        },
    },
    scales: {
        x: {
            grid: { display: false },
        },
        y: {
            grid: {
                color: 'rgba(148, 163, 184, 0.18)',
                drawBorder: false,
            },
            ticks: {
                callback: (value) => `Rp ${(Number(value) / 1000000).toFixed(0)}jt`,
            },
        },
    },
};
</script>

<template>
    <div class="relative h-[320px] w-full">
        <Line :data="chartData" :options="options" />
    </div>
</template>
