<script setup>
import { computed } from 'vue';
import { Doughnut } from 'vue-chartjs';
import { Chart as ChartJS, Title, Tooltip, Legend, ArcElement } from 'chart.js';

ChartJS.register(Title, Tooltip, Legend, ArcElement);

const props = defineProps({ data: Object });

const LABELS = {
    biaya_tim: 'Biaya Tim',
    komisi_referral: 'Komisi Referral',
    operasional: 'Operasional',
    lainnya: 'Lainnya',
};

const COLORS = ['#3b82f6', '#f59e0b', '#10b981', '#6366f1'];

const chartData = computed(() => {
    const entries = Object.entries(props.data ?? {});
    return {
        labels: entries.map(([k]) => LABELS[k] ?? k),
        datasets: [{
            data: entries.map(([, v]) => v),
            backgroundColor: COLORS,
            borderWidth: 0,
        }],
    };
});

const options = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { position: 'bottom' } },
};
</script>

<template>
    <div class="relative h-[260px] w-full">
        <Doughnut :data="chartData" :options="options" />
    </div>
</template>
