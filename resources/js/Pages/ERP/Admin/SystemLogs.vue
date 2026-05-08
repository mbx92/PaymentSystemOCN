<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps({
  logs: Object,
  filters: Object,
  levels: Array,
  channels: Array,
});

const filters = reactive({
  level: props.filters?.level ?? '',
  channel: props.filters?.channel ?? '',
  event: props.filters?.event ?? '',
  method: props.filters?.method ?? '',
  q: props.filters?.q ?? '',
  date_from: props.filters?.date_from ?? '',
  date_to: props.filters?.date_to ?? '',
});

let timer;
watch(
  filters,
  (val) => {
    clearTimeout(timer);
    timer = setTimeout(() => {
      router.get(route('erp.admin.system-logs.index'), val, {
        preserveState: true,
        replace: true,
      });
    }, 300);
  },
  { deep: true },
);

const badgeForLevel = (level) => {
  if (level === 'error') return 'badge-error';
  if (level === 'warning') return 'badge-warning';
  if (level === 'info') return 'badge-info';
  return 'badge-ghost';
};

const methodBadgeClass = (method) => {
  const m = (method || '').toUpperCase();
  if (m === 'GET') return 'badge-info';
  if (m === 'POST') return 'badge-success';
  if (m === 'PUT' || m === 'PATCH') return 'badge-warning';
  if (m === 'DELETE') return 'badge-error';
  return 'badge-ghost';
};

const hasPrev = computed(() => !!props.logs?.prev_page_url);
const hasNext = computed(() => !!props.logs?.next_page_url);

const gotoPage = (url) => {
  if (!url) return;
  router.visit(url, { preserveState: true, replace: true });
};

const showModal = ref(false);
const selectedLog = ref(null);

const openLog = (log) => {
  selectedLog.value = log;
  showModal.value = true;
};

const closeModal = () => {
  showModal.value = false;
  selectedLog.value = null;
};
</script>

<template>
  <Head title="Administration - Monitoring Log ERP" />
  <AppLayout>
    <div class="space-y-5">
      <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Administration Workspace</p>
        <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
          <div>
            <h1 class="text-3xl font-bold tracking-tight">Monitoring Log Sistem ERP</h1>
            <p class="mt-2 text-sm text-base-content/70">
              Audit trail terpusat untuk aktivitas user, transaksi ERP, dan error aplikasi.
            </p>
          </div>
          <Link class="btn btn-ghost btn-sm" :href="route('erp.administration')">Back</Link>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Log aktivitas & error</h2>
          <p class="ocn-panel__desc">Saring berdasarkan level, channel, lalu klik baris untuk detail.</p>
        </div>
        <div class="card-body space-y-4">
          <div class="flex flex-nowrap items-end gap-3 overflow-x-auto pb-1">
            <div class="min-w-[150px]">
              <label class="label">
                <span class="label-text text-xs font-semibold uppercase tracking-wide">Level</span>
              </label>
              <select v-model="filters.level" class="select select-sm select-bordered w-full">
                <option value="">Semua</option>
                <option v-for="level in levels" :key="level" :value="level">{{ level }}</option>
              </select>
            </div>
            <div class="min-w-[150px]">
              <label class="label">
                <span class="label-text text-xs font-semibold uppercase tracking-wide">Channel</span>
              </label>
              <select v-model="filters.channel" class="select select-sm select-bordered w-full">
                <option value="">Semua</option>
                <option v-for="channel in channels" :key="channel" :value="channel">{{ channel }}</option>
              </select>
            </div>
            <div class="min-w-[170px]">
              <label class="label">
                <span class="label-text text-xs font-semibold uppercase tracking-wide">Event</span>
              </label>
              <input v-model="filters.event" type="text" class="input input-sm input-bordered w-full" placeholder="purchasing.*, activity.http, errors" />
            </div>
            <div class="min-w-[130px]">
              <label class="label">
                <span class="label-text text-xs font-semibold uppercase tracking-wide">Method</span>
              </label>
              <select v-model="filters.method" class="select select-sm select-bordered w-full">
                <option value="">Semua</option>
                <option value="GET">GET</option>
                <option value="POST">POST</option>
                <option value="PUT">PUT</option>
                <option value="PATCH">PATCH</option>
                <option value="DELETE">DELETE</option>
              </select>
            </div>
            <div class="min-w-[170px]">
              <label class="label">
                <span class="label-text text-xs font-semibold uppercase tracking-wide">Cari</span>
              </label>
              <input v-model="filters.q" type="text" class="input input-sm input-bordered w-full" placeholder="message, path, dll" />
            </div>
            <div class="min-w-[170px]">
              <label class="label">
                <span class="label-text text-xs font-semibold uppercase tracking-wide">Dari Tanggal</span>
              </label>
              <input v-model="filters.date_from" type="date" class="input input-sm input-bordered w-full" />
            </div>
            <div class="min-w-[170px]">
              <label class="label">
                <span class="label-text text-xs font-semibold uppercase tracking-wide">Sampai</span>
              </label>
              <input v-model="filters.date_to" type="date" class="input input-sm input-bordered w-full" />
            </div>
          </div>

          <div class="overflow-x-auto">
            <table class="table table-xs">
              <thead>
                <tr>
                  <th class="w-44">Waktu</th>
                  <th>Level</th>
                  <th>Event</th>
                  <th>Message</th>
                  <th>User</th>
                  <th>Route</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="log in logs.data"
                  :key="log.id"
                  class="hover:bg-primary/5 cursor-pointer"
                  @click="openLog(log)"
                >
                  <td class="font-mono text-[11px]">{{ log.created_at }}</td>
                  <td>
                    <span class="badge badge-xs font-mono uppercase" :class="badgeForLevel(log.level)">
                      {{ log.level }}
                    </span>
                    <span class="ml-1 text-[10px] uppercase text-slate-400">{{ log.channel }}</span>
                  </td>
                  <td class="text-xs font-medium">{{ log.event }}</td>
                  <td class="max-w-xs truncate text-xs">{{ log.message }}</td>
                  <td class="text-xs">
                    <span v-if="log.user">{{ log.user.name }}</span>
                    <span v-else class="text-slate-400">system</span>
                  </td>
                  <td class="text-[11px]">
                    <div class="flex items-center gap-2">
                      <span
                        v-if="log.method"
                        class="badge badge-xs font-mono uppercase"
                        :class="methodBadgeClass(log.method)"
                      >
                        {{ log.method }}
                      </span>
                      <span v-if="log.path" class="font-mono text-[11px]">{{ log.path }}</span>
                    </div>
                  </td>
                  <td class="text-xs">
                    <span v-if="log.status_code" class="badge badge-ghost badge-xs">
                      {{ log.status_code }}
                    </span>
                  </td>
                </tr>
                <tr v-if="!logs.data.length">
                  <td colspan="7" class="py-6 text-center text-sm text-base-content/60">
                    Belum ada log yang cocok dengan filter.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="flex items-center justify-between text-xs text-base-content/60">
            <div>
              Menampilkan
              <span class="font-semibold">{{ logs.from ?? 0 }}</span>
              -
              <span class="font-semibold">{{ logs.to ?? 0 }}</span>
              dari
              <span class="font-semibold">{{ logs.total }}</span>
              log.
            </div>
            <div class="flex items-center gap-2">
              <button class="btn btn-xs" :disabled="!hasPrev" @click="gotoPage(logs.prev_page_url)">Prev</button>
              <button class="btn btn-xs" :disabled="!hasNext" @click="gotoPage(logs.next_page_url)">Next</button>
            </div>
          </div>
        </div>
      </div>
      <div v-if="showModal && selectedLog" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
        <div class="w-full max-w-3xl rounded-2xl bg-white p-5 shadow-2xl">
          <div class="mb-3 flex items-center justify-between gap-3">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Detail Log</p>
              <h2 class="text-lg font-semibold">
                {{ selectedLog.event }}
              </h2>
              <p class="mt-1 text-xs text-base-content/60">
                {{ selectedLog.created_at }} ·
                <span class="font-mono">{{ selectedLog.channel }}</span>
              </p>
            </div>
            <button type="button" class="btn btn-ghost btn-sm" @click="closeModal">Tutup</button>
          </div>
          <div class="mb-4 grid gap-3 md:grid-cols-2">
            <div class="space-y-1 text-xs">
              <p><span class="font-semibold">Level:</span> {{ selectedLog.level }}</p>
              <p>
                <span class="font-semibold">User:</span>
                <span v-if="selectedLog.user">{{ selectedLog.user.name }} ({{ selectedLog.user.email }})</span>
                <span v-else class="text-slate-500">system</span>
              </p>
              <p>
                <span class="font-semibold">Route:</span>
                <span class="font-mono">
                  {{ selectedLog.method }} {{ selectedLog.path }}
                </span>
              </p>
              <p v-if="selectedLog.status_code">
                <span class="font-semibold">Status:</span> {{ selectedLog.status_code }}
              </p>
            </div>
            <div class="space-y-1 text-xs">
              <p class="font-semibold">Message</p>
              <p class="rounded border bg-slate-50 p-2 text-[11px]">
                {{ selectedLog.message || '-' }}
              </p>
            </div>
          </div>
          <div>
            <p class="mb-1 text-xs font-semibold">Context</p>
            <pre class="max-h-72 overflow-auto rounded border bg-slate-50 p-3 text-[11px]">
{{ JSON.stringify(selectedLog.context, null, 2) }}
            </pre>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

