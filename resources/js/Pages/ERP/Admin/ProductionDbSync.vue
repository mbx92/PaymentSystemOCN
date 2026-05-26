<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { computed } from 'vue';

const props = defineProps({
  syncConfig: {
    type: Object,
    required: true,
  },
  lastResult: {
    type: Object,
    default: null,
  },
  lastFilters: {
    type: Object,
    default: () => ({
      mode: 'dry_run',
      module_keys: [],
      table_names: [],
      chunk_size: 500,
    }),
  },
});

const modules = computed(() => props.syncConfig?.modules ?? []);
const allTables = computed(() => props.syncConfig?.all_tables ?? []);
const targetConfigured = computed(() => Boolean(props.syncConfig?.target_configured));
const sourceConfigured = computed(() => Boolean(props.syncConfig?.source_configured));
const totalModuleTables = computed(() => modules.value.reduce((sum, item) => sum + (item.table_count ?? 0), 0));

const form = useForm({
  mode: props.lastFilters?.mode ?? 'dry_run',
  module_keys: Array.isArray(props.lastFilters?.module_keys) ? [...props.lastFilters.module_keys] : [],
  table_names: Array.isArray(props.lastFilters?.table_names) ? [...props.lastFilters.table_names] : [],
  chunk_size: Number(props.lastFilters?.chunk_size ?? 500),
});

const selectedTableCount = computed(() => form.table_names.length);
const selectedModuleCount = computed(() => form.module_keys.length);

const toggleModule = (moduleKey) => {
  if (form.module_keys.includes(moduleKey)) {
    form.module_keys = form.module_keys.filter((key) => key !== moduleKey);
    return;
  }

  form.module_keys = [...form.module_keys, moduleKey];
};

const toggleTable = (tableName) => {
  if (form.table_names.includes(tableName)) {
    form.table_names = form.table_names.filter((name) => name !== tableName);
    return;
  }

  form.table_names = [...form.table_names, tableName];
};

const selectAllModules = () => {
  form.module_keys = modules.value.map((item) => item.key);
};

const clearModules = () => {
  form.module_keys = [];
};

const selectAllTables = () => {
  form.table_names = [...allTables.value];
};

const clearTables = () => {
  form.table_names = [];
};

const runSync = (mode) => {
  form.mode = mode;
  form.post(route('erp.admin.production-db-sync.run'), {
    preserveScroll: true,
  });
};

const resultModules = computed(() => props.lastResult?.modules ?? {});
const resultTotals = computed(() => props.lastResult?.totals ?? null);
const resultModeLabel = computed(() => {
  if (!props.lastResult) return null;
  return props.lastResult.dry_run ? 'Dry run' : 'Execute import';
});
</script>

<template>
  <Head title="Administration - Production DB Sync" />
  <AppLayout>
    <div class="space-y-5">
      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Administration Workspace</p>
              <h1 class="ocn-panel__title mt-1">Production DB Sync</h1>
              <p class="ocn-panel__desc mt-1">
                Jalankan dry run atau execute import bertahap dari `ocn_erp` kerja ke koneksi production dengan seleksi modul dan tabel.
              </p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
              <button type="button" class="btn btn-outline btn-sm" :disabled="form.processing" @click="runSync('dry_run')">Dry run</button>
              <button type="button" class="btn btn-primary btn-sm" :disabled="form.processing || !targetConfigured || !sourceConfigured" @click="runSync('execute')">
                Execute import
              </button>
              <Link class="btn btn-ghost btn-sm shrink-0 gap-1.5" :href="route('erp.administration')">
                <ArrowLeftIcon class="h-4 w-4" />
                Back
              </Link>
            </div>
          </div>
        </div>
      </div>

      <div v-if="!targetConfigured || !sourceConfigured" class="alert alert-warning">
        <span>
          Koneksi belum lengkap.
          Source: <strong>{{ sourceConfigured ? 'siap' : 'belum dikonfigurasi' }}</strong>.
          Target: <strong>{{ targetConfigured ? 'siap' : 'belum dikonfigurasi' }}</strong>.
        </span>
      </div>

      <div class="grid gap-4 xl:grid-cols-3">
        <article class="rounded-2xl border border-base-200 bg-base-100 p-5 shadow-sm">
          <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">Source</p>
          <p class="mt-2 font-mono text-sm">{{ syncConfig.source_connection }}</p>
          <span class="badge mt-3" :class="sourceConfigured ? 'badge-success' : 'badge-warning'">
            {{ sourceConfigured ? 'Configured' : 'Belum siap' }}
          </span>
        </article>
        <article class="rounded-2xl border border-base-200 bg-base-100 p-5 shadow-sm">
          <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">Target</p>
          <p class="mt-2 font-mono text-sm">{{ syncConfig.target_connection }}</p>
          <span class="badge mt-3" :class="targetConfigured ? 'badge-success' : 'badge-warning'">
            {{ targetConfigured ? 'Configured' : 'Belum siap' }}
          </span>
        </article>
        <article class="rounded-2xl border border-primary/20 bg-primary/5 p-5 shadow-sm">
          <p class="text-xs font-semibold uppercase tracking-wide text-primary/70">Plan</p>
          <dl class="mt-3 space-y-2 text-sm">
            <div class="flex items-center justify-between gap-3">
              <dt>Module tersedia</dt>
              <dd class="font-semibold">{{ modules.length }}</dd>
            </div>
            <div class="flex items-center justify-between gap-3">
              <dt>Total tabel</dt>
              <dd class="font-semibold">{{ totalModuleTables }}</dd>
            </div>
            <div class="flex items-center justify-between gap-3">
              <dt>Chunk size</dt>
              <dd class="font-semibold">{{ form.chunk_size }}</dd>
            </div>
          </dl>
        </article>
      </div>

      <div class="grid gap-5 xl:grid-cols-[1.1fr_1.2fr]">
        <section class="ocn-panel">
          <div class="ocn-panel__head">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <h2 class="ocn-panel__title">Seleksi modul</h2>
                <p class="ocn-panel__desc mt-1">Pilih modul usaha yang ingin diuji atau diimport lebih dulu.</p>
              </div>
              <div class="flex flex-wrap gap-2 text-xs">
                <button type="button" class="btn btn-ghost btn-xs" @click="selectAllModules">Pilih semua</button>
                <button type="button" class="btn btn-ghost btn-xs" @click="clearModules">Kosongkan</button>
              </div>
            </div>
          </div>
          <div class="card-body space-y-3">
            <label
              v-for="module in modules"
              :key="module.key"
              class="flex cursor-pointer items-start gap-3 rounded-2xl border border-base-200 bg-base-100 p-4"
            >
              <input
                :checked="form.module_keys.includes(module.key)"
                type="checkbox"
                class="checkbox checkbox-sm mt-0.5"
                @change="toggleModule(module.key)"
              >
              <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                  <p class="font-semibold">{{ module.label }}</p>
                  <span class="badge badge-ghost badge-sm">{{ module.table_count }} tabel</span>
                </div>
                <p class="mt-2 text-xs font-mono text-base-content/55 break-all">{{ module.tables.join(', ') }}</p>
              </div>
            </label>
            <p class="text-xs text-base-content/60">Terpilih: {{ selectedModuleCount }} modul.</p>
          </div>
        </section>

        <section class="ocn-panel">
          <div class="ocn-panel__head">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <h2 class="ocn-panel__title">Seleksi tabel</h2>
                <p class="ocn-panel__desc mt-1">Opsional. Jika diisi, sync hanya jalan untuk tabel yang dicentang dan cocok dengan modul yang dipilih.</p>
              </div>
              <div class="flex flex-wrap gap-2 text-xs">
                <button type="button" class="btn btn-ghost btn-xs" @click="selectAllTables">Pilih semua</button>
                <button type="button" class="btn btn-ghost btn-xs" @click="clearTables">Kosongkan</button>
              </div>
            </div>
          </div>
          <div class="card-body space-y-4">
            <div>
              <label class="label">
                <span class="label-text">Chunk size</span>
              </label>
              <input v-model.number="form.chunk_size" type="number" min="1" max="5000" class="input input-bordered input-sm w-full max-w-xs">
              <p v-if="form.errors.chunk_size" class="mt-1 text-xs text-error">{{ form.errors.chunk_size }}</p>
            </div>

            <div class="max-h-[26rem] space-y-2 overflow-y-auto rounded-2xl border border-base-200 p-3">
              <label
                v-for="table in allTables"
                :key="table"
                class="flex cursor-pointer items-center gap-3 rounded-xl border border-base-200 px-3 py-2"
              >
                <input
                  :checked="form.table_names.includes(table)"
                  type="checkbox"
                  class="checkbox checkbox-xs"
                  @change="toggleTable(table)"
                >
                <span class="font-mono text-xs">{{ table }}</span>
              </label>
            </div>
            <p class="text-xs text-base-content/60">Terpilih: {{ selectedTableCount }} tabel.</p>
            <div class="flex flex-wrap gap-2">
              <button type="button" class="btn btn-outline btn-sm" :disabled="form.processing" @click="runSync('dry_run')">Jalankan dry run</button>
              <button type="button" class="btn btn-primary btn-sm" :disabled="form.processing || !targetConfigured || !sourceConfigured" @click="runSync('execute')">
                Jalankan execute import
              </button>
            </div>
          </div>
        </section>
      </div>

      <section v-if="lastResult" class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <h2 class="ocn-panel__title">Hasil terakhir</h2>
              <p class="ocn-panel__desc mt-1">
                Mode: <strong>{{ resultModeLabel }}</strong>
                <span class="ml-1">| Source {{ lastResult.source_connection }} -> Target {{ lastResult.target_connection }}</span>
              </p>
            </div>
            <span class="badge badge-outline">{{ resultTotals?.tables ?? 0 }} tabel</span>
          </div>
        </div>
        <div class="card-body space-y-5">
          <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            <article class="rounded-2xl border border-base-200 bg-base-100 p-4 shadow-sm">
              <p class="text-xs text-base-content/55">Source rows</p>
              <p class="mt-2 text-2xl font-bold">{{ resultTotals?.source_rows ?? 0 }}</p>
            </article>
            <article class="rounded-2xl border border-base-200 bg-base-100 p-4 shadow-sm">
              <p class="text-xs text-base-content/55">Target before</p>
              <p class="mt-2 text-2xl font-bold">{{ resultTotals?.target_rows_before ?? 0 }}</p>
            </article>
            <article class="rounded-2xl border border-base-200 bg-base-100 p-4 shadow-sm">
              <p class="text-xs text-base-content/55">Target after</p>
              <p class="mt-2 text-2xl font-bold">{{ resultTotals?.target_rows_after ?? 0 }}</p>
            </article>
            <article class="rounded-2xl border border-base-200 bg-base-100 p-4 shadow-sm">
              <p class="text-xs text-base-content/55">Rows synced</p>
              <p class="mt-2 text-2xl font-bold">{{ resultTotals?.rows_synced ?? 0 }}</p>
            </article>
            <article class="rounded-2xl border border-base-200 bg-base-100 p-4 shadow-sm">
              <p class="text-xs text-base-content/55">Chunk size</p>
              <p class="mt-2 text-2xl font-bold">{{ lastResult.chunk_size ?? 0 }}</p>
            </article>
          </div>

          <div class="space-y-4">
            <details
              v-for="(tables, moduleName) in resultModules"
              :key="moduleName"
              class="rounded-2xl border border-base-200 bg-base-100 p-4"
            >
              <summary class="cursor-pointer text-sm font-semibold">
                {{ moduleName }} ({{ Object.keys(tables || {}).length }} tabel)
              </summary>
              <div class="mt-4 overflow-x-auto">
                <table class="table table-sm">
                  <thead>
                    <tr>
                      <th>Tabel</th>
                      <th>Mode</th>
                      <th class="text-right">Source</th>
                      <th class="text-right">Target before</th>
                      <th class="text-right">Target after</th>
                      <th class="text-right">Synced</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(row, tableName) in tables" :key="tableName">
                      <td class="font-mono text-xs">{{ tableName }}</td>
                      <td><span class="badge badge-ghost badge-sm">{{ row.mode }}</span></td>
                      <td class="text-right">{{ row.source_rows }}</td>
                      <td class="text-right">{{ row.target_rows_before }}</td>
                      <td class="text-right">{{ row.target_rows_after }}</td>
                      <td class="text-right">{{ row.rows_synced }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </details>
          </div>
        </div>
      </section>
    </div>
  </AppLayout>
</template>
