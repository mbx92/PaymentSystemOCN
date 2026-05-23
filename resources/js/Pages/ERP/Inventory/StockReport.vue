<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { computed, reactive } from 'vue';
import { Bar, Doughnut, Line } from 'vue-chartjs';
import { Chart as ChartJS, Title, Tooltip, Legend, BarElement, ArcElement, CategoryScale, LinearScale, PointElement, LineElement } from 'chart.js';

ChartJS.register(Title, Tooltip, Legend, BarElement, ArcElement, CategoryScale, LinearScale, PointElement, LineElement);

const props = defineProps({
  summary: Object,
  stockChart: Array,
  lowStockAlerts: Array,
  topSelling: Array,
  monthlyTrend: Array,
  reorderSuggestions: Array,
  movementBreakdown: Array,
  stockHealth: Array,
  stockRisk: Array,
  filters: Object,
  years: Array,
  companies: Array,
  products: Array,
});

const monthLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

const filterState = reactive({
  year: props.filters?.year ?? new Date().getFullYear(),
  company_id: props.filters?.company_id ?? 'all',
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
    {
      label: 'Minimum Stok',
      data: props.stockChart.map((item) => item.min_stock),
      backgroundColor: 'rgba(245, 158, 11, 0.72)',
      borderRadius: 6,
    },
  ],
}));

const stockBarOptions = {
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

const monthlyStockLevelData = computed(() => {
  const running = [];
  let cumulative = 0;

  props.monthlyTrend.forEach((item) => {
    cumulative += Number(item.net);
    running.push(cumulative);
  });

  return {
    labels: monthLabels,
    datasets: [
      {
        label: 'Level Stok (Net Running)',
        data: running,
        borderColor: 'rgba(37, 99, 235, 0.95)',
        backgroundColor: 'rgba(37, 99, 235, 0.18)',
        tension: 0.3,
        pointRadius: 4,
        pointHoverRadius: 6,
        fill: true,
      },
      {
        label: 'Net Bulanan',
        data: props.monthlyTrend.map((item) => Number(item.net)),
        borderColor: 'rgba(13, 148, 136, 0.95)',
        backgroundColor: 'rgba(13, 148, 136, 0.18)',
        tension: 0.3,
        pointRadius: 4,
        pointHoverRadius: 6,
        fill: false,
      },
    ],
  };
});

const monthlyStockStatus = computed(() => props.monthlyTrend.map((item, idx) => {
  const incoming = Number(item.in);
  const outgoing = Number(item.out);
  const net = Number(item.net);

  let status = 'Aman';
  let badgeClass = 'badge-success';

  if (net < 0) {
    status = 'Kritis';
    badgeClass = 'badge-error';
  } else if (outgoing > incoming * 0.8 && outgoing > 0) {
    status = 'Waspada';
    badgeClass = 'badge-warning';
  }

  return {
    month: monthLabels[idx],
    incoming,
    outgoing,
    net,
    status,
    badgeClass,
  };
}));

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

const monthlyTrendData = computed(() => ({
  labels: monthLabels,
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
    {
      type: 'line',
      label: 'Net',
      data: props.monthlyTrend.map((item) => item.net),
      borderColor: 'rgba(13, 148, 136, 0.95)',
      backgroundColor: 'rgba(13, 148, 136, 0.16)',
      tension: 0.35,
      pointRadius: 4,
      pointHoverRadius: 6,
    },
  ],
}));

const movementBreakdownData = computed(() => ({
  labels: props.movementBreakdown.map((item) => item.label),
  datasets: [
    {
      data: props.movementBreakdown.map((item) => item.value),
      backgroundColor: ['rgba(37, 99, 235, 0.82)', 'rgba(239, 68, 68, 0.82)', 'rgba(245, 158, 11, 0.82)'],
      borderWidth: 0,
    },
  ],
}));

const stockHealthData = computed(() => ({
  labels: props.stockHealth.map((item) => item.label),
  datasets: [
    {
      data: props.stockHealth.map((item) => item.value),
      backgroundColor: ['rgba(16, 185, 129, 0.82)', 'rgba(245, 158, 11, 0.82)', 'rgba(239, 68, 68, 0.82)'],
      borderWidth: 0,
    },
  ],
}));

const doughnutOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { position: 'bottom' },
  },
};

const applyFilters = () => {
  router.get(
    route('erp.inventory.stock-report'),
    {
      year: filterState.year,
      company_id: filterState.company_id || 'all',
      product_id: filterState.product_id || '',
    },
    { preserveState: true, replace: true },
  );
};

const applyCompanyFilter = () => {
  filterState.product_id = '';
  applyFilters();
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
              <p class="ocn-panel__desc mt-1">Pantau kesehatan stok, mutasi bulanan, area risiko, dan rekomendasi reorder dalam satu halaman analitik.</p>
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

      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
        <div class="ocn-panel">
          <div class="ocn-panel__head py-3"><h2 class="ocn-panel__title text-sm font-medium">Total produk</h2></div>
          <div class="card-body p-5 pt-2"><p class="text-2xl font-bold">{{ summary.total_products }}</p></div>
        </div>
        <div class="ocn-panel">
          <div class="ocn-panel__head py-3"><h2 class="ocn-panel__title text-sm font-medium">Alert stok rendah</h2></div>
          <div class="card-body p-5 pt-2"><p class="text-2xl font-bold text-warning">{{ summary.low_stock_count }}</p></div>
        </div>
        <div class="ocn-panel">
          <div class="ocn-panel__head py-3"><h2 class="ocn-panel__title text-sm font-medium">Produk kosong</h2></div>
          <div class="card-body p-5 pt-2"><p class="text-2xl font-bold text-error">{{ summary.out_of_stock_count }}</p></div>
        </div>
        <div class="ocn-panel">
          <div class="ocn-panel__head py-3"><h2 class="ocn-panel__title text-sm font-medium">Butuh reorder</h2></div>
          <div class="card-body p-5 pt-2"><p class="text-2xl font-bold text-secondary">{{ summary.reorder_count }}</p></div>
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
          <p class="ocn-panel__desc">Pilih tahun dan produk untuk mempersempit analisis mutasi stok.</p>
        </div>
        <div class="card-body">
          <div class="flex flex-wrap items-center gap-3">
            <label class="text-xs font-semibold uppercase tracking-[0.12em] text-base-content/55">Periode &amp; produk</label>
            <select v-model="filterState.company_id" class="select select-bordered select-sm w-56" @change="applyCompanyFilter">
              <option value="all">Semua Perusahaan</option>
              <option v-for="company in companies" :key="company.id" :value="company.id">{{ company.name }}</option>
            </select>
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

      <div class="grid gap-4 xl:grid-cols-[1.5fr,1fr]">
        <div class="ocn-panel">
          <div class="ocn-panel__head">
            <h2 class="ocn-panel__title">Grafik level stok bulanan</h2>
            <p class="ocn-panel__desc">Menunjukkan akumulasi perubahan stok serta net movement tiap bulan.</p>
          </div>
          <div class="card-body">
            <div class="relative h-[320px] w-full">
              <Line :data="monthlyStockLevelData" :options="lineOptions" />
            </div>
          </div>
        </div>

        <div class="ocn-panel">
          <div class="ocn-panel__head">
            <h2 class="ocn-panel__title">Status mutasi bulanan</h2>
            <p class="ocn-panel__desc">Ringkasan cepat untuk mendeteksi tekanan stok per bulan.</p>
          </div>
          <div class="card-body">
            <div class="overflow-x-auto">
              <table class="table table-zebra table-sm">
                <thead><tr><th>Bulan</th><th>Masuk</th><th>Keluar</th><th>Net</th><th>Status</th></tr></thead>
                <tbody>
                  <tr v-for="row in monthlyStockStatus" :key="row.month">
                    <td>{{ row.month }}</td>
                    <td>{{ row.incoming }}</td>
                    <td>{{ row.outgoing }}</td>
                    <td :class="row.net < 0 ? 'text-error font-semibold' : 'text-success font-semibold'">{{ row.net }}</td>
                    <td><span class="badge badge-sm" :class="row.badgeClass">{{ row.status }}</span></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Trend mutasi stok (transaksi)</h2>
          <p class="ocn-panel__desc">Bandingkan stok masuk, stok keluar, dan net movement dalam satu grafik.</p>
        </div>
        <div class="card-body">
          <div class="relative h-[320px] w-full">
            <Bar :data="monthlyTrendData" :options="stockBarOptions" />
          </div>
        </div>
      </div>

      <div class="grid gap-4 xl:grid-cols-3">
        <div class="ocn-panel">
          <div class="ocn-panel__head">
            <h2 class="ocn-panel__title">Komparasi stok vs minimum</h2>
            <p class="ocn-panel__desc">Bandingkan stok aktual dengan batas minimum untuk produk penting.</p>
          </div>
          <div class="card-body">
            <div class="relative h-[320px] w-full">
              <Bar :data="stockBarData" :options="stockBarOptions" />
            </div>
          </div>
        </div>

        <div class="ocn-panel">
          <div class="ocn-panel__head">
            <h2 class="ocn-panel__title">Komposisi kesehatan stok</h2>
            <p class="ocn-panel__desc">Pisahkan produk aman, menipis, dan kosong agar prioritas cepat terlihat.</p>
          </div>
          <div class="card-body">
            <div class="relative h-[320px] w-full">
              <Doughnut :data="stockHealthData" :options="doughnutOptions" />
            </div>
          </div>
        </div>

        <div class="ocn-panel">
          <div class="ocn-panel__head">
            <h2 class="ocn-panel__title">Komposisi tipe mutasi</h2>
            <p class="ocn-panel__desc">Lihat distribusi volume stok masuk, keluar, dan adjustment.</p>
          </div>
          <div class="card-body">
            <div class="relative h-[320px] w-full">
              <Doughnut :data="movementBreakdownData" :options="doughnutOptions" />
            </div>
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
          <h2 class="ocn-panel__title text-error">Produk berisiko</h2>
          <p class="ocn-panel__desc">Gabungkan gap minimum stok dan tekanan penjualan agar prioritas reorder lebih jelas.</p>
        </div>
        <div class="card-body">
          <div class="overflow-x-auto">
            <table class="table table-zebra">
              <thead><tr><th>SKU</th><th>Produk</th><th>Stok</th><th>Min</th><th>Gap</th><th>Total Sold</th></tr></thead>
              <tbody>
                <tr v-for="item in stockRisk" :key="item.id">
                  <td class="font-mono text-xs">{{ item.sku }}</td>
                  <td>{{ item.name }}</td>
                  <td class="font-semibold" :class="item.stock <= 0 ? 'text-error' : 'text-warning'">{{ item.stock }}</td>
                  <td>{{ item.min_stock }}</td>
                  <td class="font-semibold text-error">{{ item.gap }}</td>
                  <td>{{ item.total_sold }}</td>
                </tr>
                <tr v-if="stockRisk.length === 0"><td colspan="6" class="text-center text-base-content/50">Tidak ada produk berisiko saat ini.</td></tr>
              </tbody>
            </table>
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
