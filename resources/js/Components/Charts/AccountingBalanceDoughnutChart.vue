<script setup>
import { computed } from 'vue';
import { Doughnut } from 'vue-chartjs';
import { ArcElement, Chart as ChartJS, Legend, Title, Tooltip } from 'chart.js';

ChartJS.register(Title, Tooltip, Legend, ArcElement);

const props = defineProps({
  rows: {
    type: Array,
    default: () => [],
  },
});

const palette = ['#2563eb', '#0891b2', '#0f766e', '#16a34a', '#ca8a04', '#ea580c', '#dc2626', '#7c3aed'];

const chartData = computed(() => ({
  labels: (props.rows ?? []).map((row) => `${row.label} - Rp ${Number(row.value ?? 0).toLocaleString('id-ID')}`),
  datasets: [{
    data: (props.rows ?? []).map((row) => Number(row.value ?? 0)),
    backgroundColor: palette,
    borderWidth: 0,
    hoverOffset: 8,
  }],
}));

const options = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      position: 'bottom',
    },
    tooltip: {
      callbacks: {
        label: (ctx) => `${ctx.label}: Rp ${Number(ctx.parsed ?? 0).toLocaleString('id-ID')}`,
      },
    },
  },
};
</script>

<template>
  <div class="relative h-[300px] w-full">
    <Doughnut :data="chartData" :options="options" />
  </div>
</template>
