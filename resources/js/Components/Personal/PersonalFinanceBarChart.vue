<script setup>
import { computed } from 'vue';
import { Bar } from 'vue-chartjs';
import {
    Chart as ChartJS, Title, Tooltip, Legend,
    BarElement, CategoryScale, LinearScale,
} from 'chart.js';

ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale);

const props = defineProps({
    labels: { type: Array, default: () => [] },
    income: { type: Array, default: () => [] },
    expense: { type: Array, default: () => [] },
});

const chartData = computed(() => ({
    labels: props.labels ?? [],
    datasets: [
        {
            label: 'Pemasukan',
            data: props.income ?? [],
            backgroundColor: 'rgba(59, 130, 246, 0.7)',
            borderRadius: 4,
        },
        {
            label: 'Pengeluaran',
            data: props.expense ?? [],
            backgroundColor: 'rgba(239, 68, 68, 0.7)',
            borderRadius: 4,
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
    <div class="relative h-[300px] w-full">
        <Bar :data="chartData" :options="options" />
    </div>
</template>
