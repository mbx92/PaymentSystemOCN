<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
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
} from 'chart.js';

ChartJS.register(Title, Tooltip, Legend, LineElement, PointElement, CategoryScale, LinearScale);

const props = defineProps({
  metrics: Object,
});

const db = () => props.metrics?.database ?? {};
const net = () => props.metrics?.network ?? {};
const app = () => props.metrics?.app ?? {};
const system = () => props.metrics?.system ?? {};
const storage = () => props.metrics?.storage ?? {};
const glitchtip = () => props.metrics?.glitchtip ?? {};
const history = () => props.metrics?.history ?? {};

const memoryHistoryChart = computed(() => ({
  labels: history().labels ?? [],
  datasets: [
    {
      label: 'Memory terpakai (%)',
      data: history().memory_usage_pct ?? [],
      borderColor: '#0ea5e9',
      backgroundColor: 'rgba(14,165,233,0.12)',
      tension: 0.3,
      fill: true,
      pointRadius: 2,
    },
  ],
}));

const processHistoryChart = computed(() => ({
  labels: history().labels ?? [],
  datasets: [
    {
      label: 'Jumlah process',
      data: history().process_count ?? [],
      borderColor: '#8b5cf6',
      backgroundColor: 'rgba(139,92,246,0.12)',
      tension: 0.25,
      fill: true,
      pointRadius: 2,
    },
  ],
}));

const lineOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: false },
  },
  scales: {
    x: { grid: { display: false } },
    y: {
      beginAtZero: true,
      grid: { color: 'rgba(148,163,184,0.16)', drawBorder: false },
    },
  },
};

const refresh = () => {
  router.visit(route('erp.admin.server-monitoring'), { preserveScroll: true });
};
</script>

<template>
  <Head title="Administration - Monitoring server" />
  <AppLayout>
    <div class="space-y-5">
      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Administration Workspace</p>
              <h1 class="ocn-panel__title mt-1">Monitoring server</h1>
              <p class="ocn-panel__desc mt-1">
              Metrik diambil di sisi <strong>server PHP</strong> saat halaman dimuat: waktu kueri ke basis data, perkiraan ukuran DB, koneksi TCP ke host DB (jika bukan SQLite), dan satu permintaan HTTP keluar untuk mengukur jaringan keluar.
            </p>
            <p class="text-xs text-base-content/60 mt-1">
              Diukur: {{ metrics?.collected_at_display ?? metrics?.collected_at ?? '—' }}
              <span v-if="metrics?.timezone">({{ metrics.timezone }} {{ metrics?.timezone_offset ?? '' }})</span>
              <span v-if="metrics?.collected_at_human">· {{ metrics.collected_at_human }}</span>
            </p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
              <div class="flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline btn-sm" @click="refresh">Muat ulang metrik</button>
            <Link class="btn btn-ghost btn-sm shrink-0 gap-1.5" :href="route('erp.administration')">
            <ArrowLeftIcon class="h-4 w-4" />
            Back
          </Link>
          </div>
            </div>
          </div>
        </div>
      </div>

      <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <article class="rounded-2xl border border-base-200 bg-base-100 p-5 shadow-sm">
          <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">Aplikasi</p>
          <dl class="mt-3 space-y-2 text-sm">
            <div class="flex justify-between gap-2"><dt class="text-base-content/60">PHP</dt><dd class="font-mono">{{ app().php_version ?? '—' }}</dd></div>
            <div class="flex justify-between gap-2"><dt class="text-base-content/60">Laravel</dt><dd class="font-mono">{{ app().laravel_version ?? '—' }}</dd></div>
            <div class="flex justify-between gap-2">
              <dt class="text-base-content/60">GlitchTip</dt>
              <dd class="font-mono flex items-center gap-1.5">
                <template v-if="glitchtip().configured">
                  <span class="size-2 rounded-full bg-success" />
                  <span>Terhubung</span>
                  <span v-if="glitchtip().reachable_ms != null" class="text-xs text-base-content/50">({{ glitchtip().reachable_ms }} ms)</span>
                </template>
                <template v-else>
                  <span class="size-2 rounded-full bg-base-300" />
                  <span class="text-base-content/50">Tidak dikonfigurasi</span>
                </template>
              </dd>
            </div>
          </dl>
        </article>

        <article class="rounded-2xl border border-primary/20 bg-primary/5 p-5 shadow-sm md:col-span-2 xl:col-span-2">
          <p class="text-xs font-semibold uppercase tracking-wide text-primary/80">Basis data (koneksi default)</p>
          <div v-if="db().query_error" role="alert" class="alert alert-error mt-3 text-xs">{{ db().query_error }}</div>
          <div v-if="db().size_error" role="alert" class="alert alert-warning mt-3 text-xs">{{ db().size_error }}</div>
          <dl class="mt-3 grid gap-3 sm:grid-cols-2 text-sm">
            <div class="rounded-xl border border-base-200 bg-base-100/80 p-3">
              <dt class="text-xs text-base-content/60">Driver</dt>
              <dd class="mt-1 font-mono font-semibold">{{ db().driver ?? '—' }}</dd>
            </div>
            <div class="rounded-xl border border-base-200 bg-base-100/80 p-3">
              <dt class="text-xs text-base-content/60">Koneksi</dt>
              <dd class="mt-1 font-mono text-xs break-all">{{ db().connection ?? '—' }}</dd>
            </div>
            <div class="rounded-xl border border-base-200 bg-base-100/80 p-3 sm:col-span-2">
              <dt class="text-xs text-base-content/60">Host &amp; basis data</dt>
              <dd class="mt-1 font-mono text-xs break-all">
                <span v-if="db().host">{{ db().host }}<span v-if="db().port">:{{ db().port }}</span></span>
                <span v-else>—</span>
                <span v-if="db().database" class="text-base-content/70"> / {{ db().database }}</span>
              </dd>
            </div>
            <div class="rounded-xl border border-base-200 bg-base-100/80 p-3">
              <dt class="text-xs text-base-content/60">Latensi kueri (SELECT 1)</dt>
              <dd class="mt-1 text-2xl font-bold tabular-nums text-primary">
                {{ db().query_latency_ms != null ? `${db().query_latency_ms} ms` : '—' }}
              </dd>
              <p class="mt-1 text-[11px] text-base-content/55">Termasuk jaringan app→DB + eksekusi ringan.</p>
            </div>
            <div class="rounded-xl border border-base-200 bg-base-100/80 p-3">
              <dt class="text-xs text-base-content/60">TCP ke host DB</dt>
              <dd class="mt-1 text-2xl font-bold tabular-nums">
                <span v-if="db().tcp_connect_ms != null">{{ db().tcp_connect_ms }} ms</span>
                <span v-else class="text-base-content/50 text-base font-normal">N/A (SQLite / tanpa host)</span>
              </dd>
              <p v-if="db().tcp_error" class="mt-1 text-xs text-error">{{ db().tcp_error }}</p>
            </div>
            <div class="rounded-xl border border-base-200 bg-base-100/80 p-3 sm:col-span-2">
              <dt class="text-xs text-base-content/60">Perkiraan ukuran basis data</dt>
              <dd class="mt-1 text-2xl font-bold tabular-nums">
                {{ db().size_human ?? '—' }}
                <span v-if="db().size_bytes != null" class="ml-2 text-sm font-normal text-base-content/60">({{ db().size_bytes.toLocaleString() }} byte)</span>
              </dd>
              <p class="mt-1 text-[11px] text-base-content/55">PostgreSQL: pg_database_size · MySQL: information_schema · SQLite: ukuran file.</p>
            </div>
          </dl>
        </article>

        <article class="rounded-2xl border border-sky-200/60 bg-sky-50/80 p-5 shadow-sm dark:border-sky-900/40 dark:bg-sky-950/30 md:col-span-2 xl:col-span-3">
          <p class="text-xs font-semibold uppercase tracking-wide text-sky-800/80 dark:text-sky-200/80">Jaringan keluar (dari server)</p>
          <div v-if="net().outbound_error" role="alert" class="alert alert-warning mt-3 text-xs">{{ net().outbound_error }}</div>
          <dl class="mt-3 flex flex-wrap items-end gap-6 text-sm">
            <div>
              <dt class="text-xs text-base-content/60">HTTP GET (waktu total)</dt>
              <dd class="mt-1 text-2xl font-bold tabular-nums text-sky-700 dark:text-sky-300">
                {{ net().outbound_http_ms != null ? `${net().outbound_http_ms} ms` : '—' }}
              </dd>
            </div>
            <div class="min-w-0 flex-1">
              <dt class="text-xs text-base-content/60">Target</dt>
              <dd class="mt-1 font-mono text-xs break-all text-base-content/80">{{ net().outbound_target ?? '—' }}</dd>
            </div>
          </dl>
          <p class="mt-3 text-xs text-base-content/60">
            Firewall atau proxy dapat memperlambat atau memblokir; ini bukan latensi browser pengguna.
          </p>
        </article>

        <article class="rounded-2xl border border-emerald-200/60 bg-emerald-50/70 p-5 shadow-sm md:col-span-2 xl:col-span-3">
          <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700/80">Server memory & process</p>
          <div v-if="system().memory_error" role="alert" class="alert alert-warning mt-3 text-xs">{{ system().memory_error }}</div>
          <div v-if="system().process_error" role="alert" class="alert alert-warning mt-3 text-xs">{{ system().process_error }}</div>
          <dl class="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-4 text-sm">
            <div class="rounded-xl border border-base-200 bg-base-100/80 p-3">
              <dt class="text-xs text-base-content/60">Memory terpakai</dt>
              <dd class="mt-1 text-2xl font-bold tabular-nums text-emerald-700">
                {{ system().memory_usage_pct != null ? `${system().memory_usage_pct}%` : '—' }}
              </dd>
            </div>
            <div class="rounded-xl border border-base-200 bg-base-100/80 p-3">
              <dt class="text-xs text-base-content/60">Jumlah process</dt>
              <dd class="mt-1 text-2xl font-bold tabular-nums text-emerald-700">
                {{ system().process_count != null ? system().process_count.toLocaleString() : '—' }}
              </dd>
            </div>
            <div class="rounded-xl border border-base-200 bg-base-100/80 p-3">
              <dt class="text-xs text-base-content/60">Memory total / used</dt>
              <dd class="mt-1 text-sm font-semibold">
                {{ system().memory?.total_human ?? '—' }} / {{ system().memory?.used_human ?? '—' }}
              </dd>
              <p class="mt-1 text-[11px] text-base-content/55">Available: {{ system().memory?.available_human ?? '—' }}</p>
            </div>
            <div class="rounded-xl border border-base-200 bg-base-100/80 p-3">
              <dt class="text-xs text-base-content/60">PHP memory (request ini)</dt>
              <dd class="mt-1 text-sm font-semibold">
                {{ system().memory?.php_usage_human ?? '—' }} / peak {{ system().memory?.php_peak_human ?? '—' }}
              </dd>
              <p class="mt-1 text-[11px] text-base-content/55">Load avg 1m: {{ system().load_avg_1m ?? '—' }}</p>
            </div>
          </dl>

          <div class="mt-4 grid gap-4 lg:grid-cols-2">
            <div class="rounded-xl border border-base-200 bg-base-100 p-3">
              <p class="text-xs text-base-content/60 mb-2">Tren memory usage (%)</p>
              <div class="h-44">
                <Line :data="memoryHistoryChart" :options="lineOptions" />
              </div>
            </div>
            <div class="rounded-xl border border-base-200 bg-base-100 p-3">
              <p class="text-xs text-base-content/60 mb-2">Tren jumlah process</p>
              <div class="h-44">
                <Line :data="processHistoryChart" :options="lineOptions" />
              </div>
            </div>
          </div>
        </article>

        <article class="rounded-2xl border border-amber-200/70 bg-amber-50/70 p-5 shadow-sm md:col-span-2 xl:col-span-3">
          <p class="text-xs font-semibold uppercase tracking-wide text-amber-800/80">Storage upload documents</p>
          <div v-if="storage().upload_documents_error" role="alert" class="alert alert-warning mt-3 text-xs">{{ storage().upload_documents_error }}</div>
          <dl class="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-4 text-sm">
            <div class="rounded-xl border border-base-200 bg-base-100/80 p-3 md:col-span-2 xl:col-span-2">
              <dt class="text-xs text-base-content/60">Path direktori upload dokumen</dt>
              <dd class="mt-1 font-mono text-xs break-all">{{ storage().upload_documents_path ?? '—' }}</dd>
            </div>
            <div class="rounded-xl border border-base-200 bg-base-100/80 p-3">
              <dt class="text-xs text-base-content/60">Ukuran terpakai</dt>
              <dd class="mt-1 text-2xl font-bold tabular-nums text-amber-700">{{ storage().upload_documents_human ?? '—' }}</dd>
              <p v-if="storage().upload_documents_bytes != null" class="mt-1 text-[11px] text-base-content/55">{{ storage().upload_documents_bytes.toLocaleString() }} byte</p>
            </div>
            <div class="rounded-xl border border-base-200 bg-base-100/80 p-3">
              <dt class="text-xs text-base-content/60">Jumlah file / folder</dt>
              <dd class="mt-1 text-sm font-semibold">{{ storage().upload_documents_files ?? 0 }} file · {{ storage().upload_documents_dirs ?? 0 }} folder</dd>
            </div>
            <div class="rounded-xl border border-base-200 bg-base-100/80 p-3">
              <dt class="text-xs text-base-content/60">Disk terpakai (volume storage)</dt>
              <dd class="mt-1 text-sm font-semibold">
                {{ storage().disk_used_human ?? '—' }} / {{ storage().disk_total_human ?? '—' }}
              </dd>
              <p class="mt-1 text-[11px] text-base-content/55">Free: {{ storage().disk_free_human ?? '—' }} · {{ storage().disk_used_pct ?? '—' }}%</p>
            </div>
          </dl>
        </article>
      </div>
    </div>
  </AppLayout>
</template>
