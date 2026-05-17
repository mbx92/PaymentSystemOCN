<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { computed, reactive } from 'vue';
import { Bar, Line } from 'vue-chartjs';
import { Chart as ChartJS, Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale, PointElement, LineElement } from 'chart.js';

ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale, PointElement, LineElement);

const props = defineProps({
  summary: Object,
  stockChart: Array,
  lowStockAlerts: Array,
  topSelling: Array,
  monthlyTrend: Array,
  reorderSuggestions: Array,
  filters: Object,
  years: Array,
  products: Array,
});

const filterState = reactive({
  year: props.filters?.year ?? new Date().getFullYear(),
  product_id: props.filters?.product_id ?? '',
});

const stockBarData = computed(() => ({
  labels: props.stockChart.map((item) => item.label),
  datasets: [
    {
      label: 'Stok Saat Ini',
      data: props.stockChart.map((item) => item.stock),
      backgroundColor: 'rgba(37, 99, 235, 0.75)',
      borderRadius: 6,
    },
  ],
}));

const stockBarOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
  },
  scales: {
    y: {
      beginAtZero: true,
      grid: { color: 'rgba(148,163,184,0.18)' },
    },
  },
};

const monthlyStockLevelData = computed(() => {
  const labels = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
  const running = [];
  let cumulative = 0;

  props.monthlyTrend.forEach((item) => {
    cumulative += Number(item.in) - Number(item.out);
    running.push(cumulative);
  });

  return {
    labels,
    datasets: [
      {
        label: 'Level Stok (Net Running)',
        data: running,
        borderColor: 'rgba(37, 99, 235, 0.95)',
        backgroundColor: 'rgba(37, 99, 235, 0.2)',
        tension: 0.3,
        pointRadius: 4,
        pointHoverRadius: 6,
        fill: true,
      },
    ],
  };
});

const monthlyStockStatus = computed(() => {
  const labels = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
  return props.monthlyTrend.map((item, idx) => {
    const incoming = Number(item.in);
    const outgoing = Number(item.out);

    let status = 'Aman';
    let badgeClass = 'badge-success';

    if (outgoing > incoming) {
      status = 'Kritis';
      badgeClass = 'badge-error';
    } else if (incoming > 0 && outgoing > incoming * 0.8) {
      status = 'Waspada';
      badgeClass = 'badge-warning';
    }

    return {
      month: labels[idx],
      incoming,
      outgoing,
      status,
      badgeClass,
    };
  });
});

const lineOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { position: 'top' },
  },
  scales: {
    y: {
      beginAtZero: true,
      grid: { color: 'rgba(148,163,184,0.18)' },
    },
  },
};

const monthlyTrendData = computed(() => {
  const labels = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
  return {
    labels,
    datasets: [
      {
        label: 'Stok Masuk',
        data: props.monthlyTrend.map((item) => item.in),
        backgroundColor: 'rgba(16, 185, 129, 0.75)',
        borderRadius: 6,
      },
      {
        label: 'Stok Keluar',
        data: props.monthlyTrend.map((item) => item.out),
        backgroundColor: 'rgba(239, 68, 68, 0.75)',
        borderRadius: 6,
      },
    ],
  };
});

const applyFilters = () => {
  router.get(
    route('erp.inventory.stock-report'),
    {
      year: filterState.year,
      product_id: filterState.product_id || '',
    },
    { preserveState: true, replace: true },
  );
};
</script>

<template>
  <Head title="Inventory - Report Stok" />
  <AppLayout>
    <div class="space-y-6">
      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Inventory Workspace</p>
              <h1 class="ocn-panel__title mt-1">Report Stok</h1>
              <p class="ocn-panel__desc mt-1">Pantau performa stok, alert stok rendah, trend mutasi, dan saran reorder otomatis.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
              <Link class="btn btn-ghost btn-sm shrink-0 gap-1.5" :href="route('erp.inventory')">
              <ArrowLeftIcon class="h-4 w-4" />
              Back
            </Link>
            </div>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="ocn-panel">
          <div class="ocn-panel__head py-3"><h2 class="ocn-panel__title text-sm font-medium">Total produk</h2></div>
          <div class="card-body p-5 pt-2"><p class="text-2xl font-bold">{{ summary.total_products }}</p></div>
        </div>
        <div class="ocn-panel">
          <div class="ocn-panel__head py-3"><h2 class="ocn-panel__title text-sm font-medium">Alert stok rendah</h2></div>
          <div class="card-body p-5 pt-2"><p class="text-2xl font-bold text-warning">{{ summary.low_stock_count }}</p></div>
        </div>
        <div class="ocn-panel">
          <div class="ocn-panel__head py-3"><h2 class="ocn-panel__title text-sm font-medium">Total unit in stock</h2></div>
          <div class="card-body p-5 pt-2"><p class="text-2xl font-bold text-info">{{ summary.total_units_in_stock }}</p></div>
        </div>
        <div class="ocn-panel">
          <div class="ocn-panel__head py-3"><h2 class="ocn-panel__title text-sm font-medium">Total unit sold</h2></div>
          <div class="card-body p-5 pt-2"><p class="text-2xl font-bold text-success">{{ summary.total_units_sold }}</p></div>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Filter grafik</h2>
        </div>
        <div class="card-body">
          <div class="flex flex-wrap items-center gap-3">
            <label class="text-xs font-semibold uppercase tracking-[0.12em] text-base-content/55">Periode &amp; produk</label>
            <select v-model="filterState.year" class="select select-bordered select-sm w-32" @change="applyFilters">
              <option v-for="year in years" :key="year" :value="year">{{ year }}</option>
            </select>
            <select v-model="filterState.product_id" class="select select-bordered select-sm w-72" @change="applyFilters">
              <option value="">Semua Produk</option>
              <option v-for="product in products" :key="product.id" :value="product.id">{{ product.sku }} - {{ product.name }}</option>
            </select>
          </div>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Grafik level stok bulanan</h2>
        </div>
        <div class="card-body">
          <div class="relative h-[320px] w-full">
            <Line :data="monthlyStockLevelData" :options="lineOptions" />
          </div>
          <div class="mt-4 overflow-x-auto">
            <table class="table table-zebra table-sm">
              <thead><tr><th>Bulan</th><th>Masuk</th><th>Keluar</th><th>Status</th></tr></thead>
              <tbody>
                <tr v-for="row in monthlyStockStatus" :key="row.month">
                  <td>{{ row.month }}</td>
                  <td>{{ row.incoming }}</td>
                  <td>{{ row.outgoing }}</td>
                  <td><span class="badge badge-sm" :class="row.badgeClass">{{ row.status }}</span></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Trend mutasi stok (transaksi)</h2>
        </div>
        <div class="card-body">
          <div class="relative h-[320px] w-full">
            <Bar :data="monthlyTrendData" :options="stockBarOptions" />
          </div>
        </div>
      </div>

      <div class="grid gap-4 lg:grid-cols-2">
        <div class="ocn-panel">
          <div class="ocn-panel__head">
            <h2 class="ocn-panel__title text-warning">Alert stok rendah</h2>
          </div>
          <div class="card-body">
            <div class="overflow-x-auto">
              <table class="table table-zebra">
                <thead><tr><th>SKU</th><th>Produk</th><th>Stok</th><th>Min</th></tr></thead>
                <tbody>
                  <tr v-for="item in lowStockAlerts" :key="item.id">
                    <td class="font-mono text-xs">{{ item.sku }}</td>
                    <td>{{ item.name }}</td>
                    <td class="font-semibold text-error">{{ item.stock }}</td>
                    <td>{{ item.min_stock }}</td>
                  </tr>
                  <tr v-if="lowStockAlerts.length === 0"><td colspan="4" class="text-center text-base-content/50">Tidak ada stok rendah.</td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="ocn-panel">
          <div class="ocn-panel__head">
            <h2 class="ocn-panel__title text-success">Produk terlaris</h2>
          </div>
          <div class="card-body">
            <div class="overflow-x-auto">
              <table class="table table-zebra">
                <thead><tr><th>SKU</th><th>Produk</th><th>Total Terjual</th></tr></thead>
                <tbody>
                  <tr v-for="item in topSelling" :key="item.id">
                    <td class="font-mono text-xs">{{ item.sku }}</td>
                    <td>{{ item.name }}</td>
                    <td class="font-semibold text-success">{{ item.total_sold }}</td>
                  </tr>
                  <tr v-if="topSelling.length === 0"><td colspan="3" class="text-center text-base-content/50">Belum ada data penjualan.</td></tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Saran reorder otomatis</h2>
          <p class="ocn-panel__desc">Berdasarkan minimum stock.</p>
        </div>
        <div class="card-body">
          <div class="overflow-x-auto">
            <table class="table table-zebra">
              <thead><tr><th>SKU</th><th>Produk</th><th>Stock</th><th>Min</th><th>Saran Reorder</th></tr></thead>
              <tbody>
                <tr v-for="item in reorderSuggestions" :key="item.id">
                  <td class="font-mono text-xs">{{ item.sku }}</td>
                  <td>{{ item.name }}</td>
                  <td>{{ item.stock }}</td>
                  <td>{{ item.min_stock }}</td>
                  <td class="font-semibold text-primary">{{ item.suggested_qty }}</td>
                </tr>
                <tr v-if="reorderSuggestions.length === 0"><td colspan="5" class="text-center text-base-content/50">Tidak ada saran reorder saat ini.</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
