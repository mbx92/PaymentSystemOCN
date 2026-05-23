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
  trendData: {
    type: Array,
    default: () => [],
  },
});

const chartData = computed(() => ({
  labels: (props.trendData ?? []).map((row) => row.label),
  datasets: [
    {
      label: 'Kas Masuk',
      data: (props.trendData ?? []).map((row) => Number(row.income ?? 0)),
      borderColor: 'rgba(22, 163, 74, 0.95)',
      backgroundColor: 'rgba(22, 163, 74, 0.10)',
      tension: 0.35,
      fill: true,
      pointRadius: 2,
      pointHoverRadius: 4,
      borderWidth: 3,
    },
    {
      label: 'Kas Keluar',
      data: (props.trendData ?? []).map((row) => Number(row.expense ?? 0)),
      borderColor: 'rgba(239, 68, 68, 0.95)',
      backgroundColor: 'rgba(239, 68, 68, 0.08)',
      tension: 0.35,
      fill: true,
      pointRadius: 2,
      pointHoverRadius: 4,
      borderWidth: 3,
    },
    {
      label: 'Net',
      data: (props.trendData ?? []).map((row) => Number(row.net ?? 0)),
      borderColor: 'rgba(37, 99, 235, 0.95)',
      backgroundColor: 'rgba(37, 99, 235, 0)',
      tension: 0.3,
      fill: false,
      pointRadius: 1.5,
      pointHoverRadius: 3,
      borderDash: [6, 4],
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
      beginAtZero: true,
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
