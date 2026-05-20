<script setup>
import { computed } from 'vue';
import { Line } from 'vue-chartjs';
import {
    Chart as ChartJS,
    Title,
    Tooltip,
    Legend,
    LineElement,
    PointElement,
    CategoryScale,
    LinearScale,
    Filler,
} from 'chart.js';

ChartJS.register(Title, Tooltip, Legend, LineElement, PointElement, CategoryScale, LinearScale, Filler);

const props = defineProps({ monthlyData: Array });

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

const chartData = computed(() => ({
    labels: (props.monthlyData ?? []).map((d) => MONTHS[d.month - 1]),
    datasets: [
        {
            label: 'Pendapatan',
            data: (props.monthlyData ?? []).map((d) => d.income),
            borderColor: 'rgba(37, 99, 235, 0.95)',
            backgroundColor: 'rgba(37, 99, 235, 0.12)',
            tension: 0.35,
            fill: true,
            pointRadius: 3,
            pointHoverRadius: 5,
            pointBackgroundColor: 'rgba(37, 99, 235, 1)',
            borderWidth: 3,
        },
        {
            label: 'Pengeluaran',
            data: (props.monthlyData ?? []).map((d) => d.expense),
            borderColor: 'rgba(239, 68, 68, 0.95)',
            backgroundColor: 'rgba(239, 68, 68, 0.08)',
            tension: 0.35,
            fill: true,
            pointRadius: 3,
            pointHoverRadius: 5,
            pointBackgroundColor: 'rgba(239, 68, 68, 1)',
            borderWidth: 3,
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
                label: (ctx) => `${ctx.dataset.label}: Rp ${Number(ctx.parsed.y).toLocaleString('id-ID')}`,
            },
        },
    },
    scales: {
        x: {
            grid: { display: false },
        },
        y: {
            beginAtZero: true,
            grid: {
                color: 'rgba(148, 163, 184, 0.18)',
                drawBorder: false,
            },
            ticks: {
                callback: (v) => 'Rp ' + (v / 1_000_000).toFixed(0) + 'jt',
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
