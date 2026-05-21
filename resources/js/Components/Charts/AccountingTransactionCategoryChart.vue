<script setup>
import { computed } from 'vue';
import { Bar } from 'vue-chartjs';
import {
  BarElement,
  CategoryScale,
  Chart as ChartJS,
  Legend,
  LinearScale,
  Title,
  Tooltip,
} from 'chart.js';

ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale);

const props = defineProps({
  data: {
    type: Object,
    default: () => ({ labels: [], datasets: [] }),
  },
});

const chartData = computed(() => ({
  labels: props.data?.labels ?? [],
  datasets: [
    {
      label: props.data?.datasets?.[0]?.label ?? 'Pemasukan',
      data: props.data?.datasets?.[0]?.data ?? [],
      backgroundColor: 'rgba(22, 163, 74, 0.85)',
      borderRadius: 8,
      borderSkipped: false,
      barThickness: 16,
    },
    {
      label: props.data?.datasets?.[1]?.label ?? 'Pengeluaran',
      data: props.data?.datasets?.[1]?.data ?? [],
      backgroundColor: 'rgba(220, 38, 38, 0.82)',
      borderRadius: 8,
      borderSkipped: false,
      barThickness: 16,
    },
  ],
}));

const options = {
  responsive: true,
  maintainAspectRatio: false,
  indexAxis: 'y',
  interaction: {
    intersect: false,
    mode: 'index',
  },
  plugins: {
    legend: {
      position: 'top',
    },
    tooltip: {
      callbacks: {
        label: (ctx) => `${ctx.dataset.label}: Rp ${Number(ctx.parsed.x ?? 0).toLocaleString('id-ID')}`,
      },
    },
  },
  scales: {
    x: {
      grid: {
        color: 'rgba(148, 163, 184, 0.18)',
        drawBorder: false,
      },
      ticks: {
        callback: (value) => `Rp ${(Number(value) / 1000000).toFixed(0)}jt`,
      },
    },
    y: {
      grid: {
        display: false,
      },
    },
  },
};
</script>

<template>
  <div class="relative h-[340px] w-full">
    <Bar :data="chartData" :options="options" />
  </div>
</template>
