<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTablePagination from '@/Components/DataTablePagination.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { computed, reactive, watch } from 'vue';
import { Bar, Doughnut, Line } from 'vue-chartjs';
import {
  Chart as ChartJS,
  Title,
  Tooltip,
  Legend,
  BarElement,
  CategoryScale,
  LinearScale,
  LineElement,
  PointElement,
  ArcElement,
} from 'chart.js';

ChartJS.register(
  Title,
  Tooltip,
  Legend,
  BarElement,
  CategoryScale,
  LinearScale,
  LineElement,
  PointElement,
  ArcElement,
);

const props = defineProps({
  stats: Object,
  visitAnalytics: { type: Object, default: () => ({}) },
  recentAccess: Object,
  recentAccessFilters: Object,
  recentAccessOptions: Object,
});

const recentFilters = reactive({
  kind: props.recentAccessFilters?.kind ?? '',
  device_type: props.recentAccessFilters?.device_type ?? '',
  q: props.recentAccessFilters?.q ?? '',
  per_page: props.recentAccessFilters?.per_page ?? props.recentAccess?.per_page ?? 25,
});

let recentFilterTimer;
watch(
  recentFilters,
  (val) => {
    clearTimeout(recentFilterTimer);
    recentFilterTimer = setTimeout(() => {
      router.get(route('erp.cms'), val, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
      });
    }, 300);
  },
  { deep: true },
);

const bytes = (n) => {
  const v = Number(n ?? 0);
  if (v < 1024) return `${v} B`;
  if (v < 1024 * 1024) return `${(v / 1024).toFixed(1)} KB`;
  return `${(v / (1024 * 1024)).toFixed(2)} MB`;
};

const deviceLabel = (k) =>
  ({
    desktop: 'Desktop',
    mobile: 'Mobile',
    tablet: 'Tablet',
    bot: 'Bot / crawler',
    unknown: 'Tidak diketahui',
  }[k] ?? k);

const visitLineChart = computed(() => {
  const ts = props.visitAnalytics?.timeseries ?? {};
  return {
    labels: ts.labels ?? [],
    datasets: [
      {
        label: 'Kunjungan landing',
        data: ts.landing_hits ?? [],
        borderColor: '#0ea5e9',
        backgroundColor: 'rgba(14,165,233,0.12)',
        tension: 0.3,
        fill: true,
        pointRadius: 2,
      },
      {
        label: 'IP unik (landing)',
        data: ts.landing_unique_ips ?? [],
        borderColor: '#10b981',
        backgroundColor: 'rgba(16,185,129,0.08)',
        tension: 0.3,
        fill: false,
        pointRadius: 2,
      },
      {
        label: 'Akses panel CMS',
        data: ts.admin_hits ?? [],
        borderColor: '#a855f7',
        backgroundColor: 'rgba(168,85,247,0.1)',
        tension: 0.25,
        fill: false,
        pointRadius: 2,
      },
    ],
  };
});

const lineOptions = {
  responsive: true,
  maintainAspectRatio: false,
  interaction: { mode: 'index', intersect: false },
  plugins: {
    legend: { position: 'bottom' },
  },
  scales: {
    x: { grid: { display: false } },
    y: {
      beginAtZero: true,
      ticks: { precision: 0 },
      grid: { color: 'rgba(148,163,184,0.16)', drawBorder: false },
    },
  },
};

const DEVICE_COLORS = ['#0ea5e9', '#10b981', '#f59e0b', '#a855f7', '#64748b', '#ef4444'];

const deviceDoughnut = computed(() => {
  const rows = props.visitAnalytics?.devices ?? [];
  return {
    labels: rows.map((r) => deviceLabel(r.label)),
    datasets: [
      {
        data: rows.map((r) => r.value),
        backgroundColor: rows.map((_, i) => DEVICE_COLORS[i % DEVICE_COLORS.length]),
        borderWidth: 0,
      },
    ],
  };
});

const doughnutOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { position: 'bottom' } },
};

const countryBar = computed(() => {
  const rows = props.visitAnalytics?.countries ?? [];
  return {
    labels: rows.map((r) => r.label),
    datasets: [
      {
        label: 'Kunjungan',
        data: rows.map((r) => r.value),
        backgroundColor: 'rgba(14,165,233,0.65)',
        borderRadius: 6,
      },
    ],
  };
});

const barOptions = {
  responsive: true,
  maintainAspectRatio: false,
  indexAxis: 'y',
  plugins: { legend: { display: false } },
  scales: {
    x: {
      beginAtZero: true,
      ticks: { precision: 0 },
      grid: { color: 'rgba(148,163,184,0.16)', drawBorder: false },
    },
    y: { grid: { display: false } },
  },
};

const applyRecentPerPage = (n) => {
  recentFilters.per_page = n;
};
</script>

<template>
  <Head title="Website CMS" />
  <AppLayout>
    <div class="space-y-6">
      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Website CMS</p>
              <h1 class="ocn-panel__title mt-1">Dashboard konten</h1>
              <p class="ocn-panel__desc mt-1">Landing publik, halaman per domain, dan perpustakaan media.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
              <Link class="btn btn-ghost btn-sm shrink-0 gap-1.5" :href="route('erp.administration')">
                <ArrowLeftIcon class="h-4 w-4" />
                Back
              </Link>
            </div>
          </div>
        </div>
      </div>

      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-xs font-semibold uppercase text-base-content/50">Total situs</p>
          <p class="mt-2 text-3xl font-bold">{{ stats?.sites_total ?? 0 }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-xs font-semibold uppercase text-base-content/50">Situs aktif</p>
          <p class="mt-2 text-3xl font-bold text-success">{{ stats?.sites_active ?? 0 }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-xs font-semibold uppercase text-base-content/50">Halaman tayang</p>
          <p class="mt-2 text-3xl font-bold text-primary">{{ stats?.pages_published ?? 0 }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <p class="text-xs font-semibold uppercase text-base-content/50">Media</p>
          <p class="mt-2 text-3xl font-bold">{{ stats?.media_total ?? 0 }}</p>
          <p class="mt-1 text-xs text-base-content/60">{{ bytes(stats?.media_bytes) }}</p>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Statistik kunjungan</h2>
          <p class="ocn-panel__desc">
            Landing dicatat per permintaan ke domain aktif; akses panel CMS (halaman GET) dicatat untuk admin. Lokasi dari IP publik (opsional, bisa dimatikan lewat
            <code class="rounded bg-base-200 px-1 text-xs">CMS_ACCESS_LOG_GEO_LOOKUP</code>).
          </p>
        </div>
        <div class="card-body space-y-8">
        <div class="grid gap-4 sm:grid-cols-3">
          <div class="rounded-xl border border-slate-100 bg-slate-50/80 p-4">
            <p class="text-xs font-semibold uppercase text-base-content/50">Landing (7 hari)</p>
            <p class="mt-1 text-2xl font-bold text-sky-600">{{ visitAnalytics?.summary?.landing_hits_7d ?? 0 }}</p>
            <p class="mt-1 text-xs text-base-content/60">Tampilan halaman</p>
          </div>
          <div class="rounded-xl border border-slate-100 bg-slate-50/80 p-4">
            <p class="text-xs font-semibold uppercase text-base-content/50">IP unik landing (7 hari)</p>
            <p class="mt-1 text-2xl font-bold text-emerald-600">{{ visitAnalytics?.summary?.landing_unique_ip_7d ?? 0 }}</p>
            <p class="mt-1 text-xs text-base-content/60">Perkiraan pengunjung unik</p>
          </div>
          <div class="rounded-xl border border-slate-100 bg-slate-50/80 p-4">
            <p class="text-xs font-semibold uppercase text-base-content/50">Panel CMS (7 hari)</p>
            <p class="mt-1 text-2xl font-bold text-violet-600">{{ visitAnalytics?.summary?.admin_hits_7d ?? 0 }}</p>
            <p class="mt-1 text-xs text-base-content/60">Muat halaman dashboard / situs / media</p>
          </div>
        </div>
        <div class="relative mt-6 h-[280px] w-full">
          <Line :data="visitLineChart" :options="lineOptions" />
        </div>
        <div class="mt-8 grid gap-6 lg:grid-cols-2">
          <div>
            <h3 class="text-sm font-semibold text-base-content/80">Perangkat (landing)</h3>
            <div class="relative mt-3 h-[240px] w-full">
              <Doughnut v-if="(visitAnalytics?.devices ?? []).length" :data="deviceDoughnut" :options="doughnutOptions" />
              <p v-else class="py-16 text-center text-sm text-base-content/50">Belum ada data perangkat.</p>
            </div>
          </div>
          <div>
            <h3 class="text-sm font-semibold text-base-content/80">Negara (landing)</h3>
            <div class="relative mt-3 h-[240px] w-full">
              <Bar v-if="(visitAnalytics?.countries ?? []).length" :data="countryBar" :options="barOptions" />
              <p v-else class="py-16 text-center text-sm text-base-content/50">Lokasi muncul setelah lookup geo untuk IP publik.</p>
            </div>
          </div>
        </div>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Akses terbaru</h2>
          <p class="ocn-panel__desc">IP, lokasi perkiraan, perangkat, dan jalur.</p>
        </div>
        <div class="card-body pb-2">
          <div class="flex flex-nowrap items-end gap-3 overflow-x-auto pb-1">
            <div class="min-w-[180px]">
              <label class="label">
                <span class="label-text text-xs font-semibold uppercase tracking-wide">Jenis akses</span>
              </label>
              <select v-model="recentFilters.kind" class="select select-sm select-bordered w-full">
                <option value="">Semua</option>
                <option v-for="kind in recentAccessOptions?.kinds ?? []" :key="kind.value" :value="kind.value">{{ kind.label }}</option>
              </select>
            </div>
            <div class="min-w-[180px]">
              <label class="label">
                <span class="label-text text-xs font-semibold uppercase tracking-wide">Perangkat</span>
              </label>
              <select v-model="recentFilters.device_type" class="select select-sm select-bordered w-full">
                <option value="">Semua</option>
                <option v-for="device in recentAccessOptions?.device_types ?? []" :key="device" :value="device">{{ deviceLabel(device) }}</option>
              </select>
            </div>
            <div class="min-w-[260px] grow">
              <label class="label">
                <span class="label-text text-xs font-semibold uppercase tracking-wide">Cari</span>
              </label>
              <input
                v-model="recentFilters.q"
                type="text"
                class="input input-sm input-bordered w-full"
                placeholder="IP, lokasi, browser, user, domain, path"
              />
            </div>
          </div>
        </div>
        <div class="overflow-x-auto">
        <table class="table table-sm">
          <thead>
            <tr class="text-xs uppercase text-base-content/50">
              <th>Waktu</th>
              <th>Jenis</th>
              <th>IP</th>
              <th>Lokasi</th>
              <th>Perangkat</th>
              <th>Browser / OS</th>
              <th>Detail</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in recentAccess?.data ?? []" :key="row.at + row.ip + row.path">
              <td class="whitespace-nowrap text-sm">{{ row.at_display ?? '—' }}</td>
              <td class="whitespace-nowrap text-sm">
                <div>{{ row.kind_label }}</div>
                <div class="text-[11px] text-base-content/50">{{ row.event_label || 'Akses' }}</div>
              </td>
              <td class="font-mono text-xs">{{ row.ip }}</td>
              <td class="max-w-[200px] truncate text-sm" :title="row.location">{{ row.location }}</td>
              <td class="text-sm capitalize">{{ deviceLabel(row.device_type) }}</td>
              <td class="max-w-[180px] truncate text-xs" :title="`${row.browser} · ${row.os}`">
                {{ row.browser }} · {{ row.os }}
              </td>
              <td class="max-w-[220px] truncate text-xs" :title="row.event_summary || row.site || row.user || row.path">
                <span v-if="row.site">Situs: {{ row.site }}</span>
                <span v-else-if="row.user">{{ row.user }}</span>
                <span v-else>{{ row.path }}</span>
                <span v-if="row.event_summary" class="block text-[11px] text-base-content/60">{{ row.event_summary }}</span>
              </td>
            </tr>
            <tr v-if="!(recentAccess?.data ?? []).length">
              <td colspan="7" class="text-center text-sm text-base-content/50 py-8">Belum ada log akses.</td>
            </tr>
          </tbody>
        </table>
        </div>
        <DataTablePagination :paginator="recentAccess" @update:per-page="applyRecentPerPage" />
      </div>

      <div class="grid gap-4 md:grid-cols-2">
        <Link
          :href="route('erp.cms.sites')"
          class="card border border-base-200 bg-base-100 p-6 shadow-sm transition hover:border-primary/30 hover:shadow-md"
        >
          <h2 class="text-lg font-semibold">Landing sites</h2>
          <p class="mt-2 text-sm text-base-content/70">Kelola domain, layout, warehouse default, dan buka editor konten.</p>
          <p class="mt-4 text-xs font-bold uppercase tracking-wide text-primary/80">Buka →</p>
        </Link>
        <Link
          :href="route('erp.cms.media')"
          class="card border border-base-200 bg-base-100 p-6 shadow-sm transition hover:border-primary/30 hover:shadow-md"
        >
          <h2 class="text-lg font-semibold">Media library</h2>
          <p class="mt-2 text-sm text-base-content/70">Unggah gambar/PDF untuk dipakai di landing dan materi promosi.</p>
          <p class="mt-4 text-xs font-bold uppercase tracking-wide text-primary/80">Buka →</p>
        </Link>
      </div>
    </div>
  </AppLayout>
</template>
