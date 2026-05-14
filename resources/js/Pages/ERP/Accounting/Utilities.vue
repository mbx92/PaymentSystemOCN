<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTablePagination from '@/Components/DataTablePagination.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { computed, reactive, ref, watch } from 'vue';

const props = defineProps({
  companies: Array,
  entries: Object,
  companySummaries: Array,
  filters: Object,
});

const filters = reactive({
  company_id: props.filters?.company_id ?? '',
  date_from: props.filters?.date_from ?? '',
  date_to: props.filters?.date_to ?? '',
  q: props.filters?.q ?? '',
});

const selectedEntryIds = ref([]);
const moveForm = useForm({
  target_company_id: '',
  journal_entry_ids: [],
});

const format = (n) =>
  new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n || 0);

const entryRows = computed(() => props.entries?.data ?? []);
const selectableIds = computed(() => entryRows.value.map((entry) => entry.id));
const allVisibleSelected = computed(() =>
  selectableIds.value.length > 0 && selectableIds.value.every((id) => selectedEntryIds.value.includes(id)),
);
const selectedCount = computed(() => selectedEntryIds.value.length);

const selectedCurrentCompanies = computed(() => {
  const selectedSet = new Set(selectedEntryIds.value);
  return [...new Set(entryRows.value.filter((entry) => selectedSet.has(entry.id)).map((entry) => entry.company_name))];
});

const toggleVisible = (checked) => {
  const ids = selectableIds.value;
  if (checked) {
    selectedEntryIds.value = [...new Set([...selectedEntryIds.value, ...ids])];
    return;
  }
  selectedEntryIds.value = selectedEntryIds.value.filter((id) => !ids.includes(id));
};

const toggleEntry = (id, checked) => {
  if (checked) {
    selectedEntryIds.value = [...new Set([...selectedEntryIds.value, id])];
    return;
  }
  selectedEntryIds.value = selectedEntryIds.value.filter((entryId) => entryId !== id);
};

const applyFilters = () => {
  router.get(route('erp.accounting.utilities'), filters, { preserveState: true, replace: true });
};

let timer;
watch(filters, () => {
  clearTimeout(timer);
  timer = setTimeout(applyFilters, 300);
}, { deep: true });

watch(() => props.entries?.data, () => {
  const visible = new Set(selectableIds.value);
  selectedEntryIds.value = selectedEntryIds.value.filter((id) => visible.has(id));
});

const resetFilters = () => {
  filters.company_id = '';
  filters.date_from = '';
  filters.date_to = '';
  filters.q = '';
};

const submitMove = () => {
  moveForm.journal_entry_ids = selectedEntryIds.value;
  moveForm.post(route('erp.accounting.utilities.move-journals'), {
    preserveScroll: true,
    onSuccess: () => {
      selectedEntryIds.value = [];
      moveForm.reset('target_company_id', 'journal_entry_ids');
    },
  });
};
</script>

<template>
  <Head title="Accounting - Utilitas" />
  <AppLayout>
    <div class="space-y-5">
      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Accounting Workspace</p>
              <h1 class="ocn-panel__title mt-1">Utilitas Accounting</h1>
              <p class="ocn-panel__desc mt-1">Pindahkan jurnal accounting dari satu usaha ke usaha lain tanpa mengubah debit-kredit.</p>
            </div>
            <Link class="btn btn-ghost btn-sm shrink-0 gap-1.5" :href="route('erp.accounting')">
              <ArrowLeftIcon class="h-4 w-4" />
              Back
            </Link>
          </div>
        </div>
      </div>

      <div class="grid gap-4 md:grid-cols-3">
        <article v-for="summary in companySummaries" :key="summary.company_id ?? 'null'" class="ocn-stat-card rounded-xl border border-base-300 bg-base-100 p-4 shadow-sm">
          <p class="text-xs font-bold uppercase tracking-wide text-base-content/50">{{ summary.company_name }}</p>
          <p class="mt-3 text-2xl font-bold">{{ summary.entry_count }}</p>
          <p class="text-xs text-base-content/60">jurnal accounting</p>
        </article>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Filter transaksi</h2>
        </div>
        <div class="card-body">
          <div class="grid gap-3 md:grid-cols-5">
            <div>
              <label class="label"><span class="label-text text-xs uppercase tracking-wide">Usaha asal</span></label>
              <select v-model="filters.company_id" class="select select-sm select-bordered w-full">
                <option value="">Semua usaha</option>
                <option v-for="company in companies" :key="company.id" :value="company.id">{{ company.name }}</option>
              </select>
            </div>
            <div>
              <label class="label"><span class="label-text text-xs uppercase tracking-wide">Dari tanggal</span></label>
              <input v-model="filters.date_from" type="date" class="input input-sm input-bordered w-full">
            </div>
            <div>
              <label class="label"><span class="label-text text-xs uppercase tracking-wide">Sampai tanggal</span></label>
              <input v-model="filters.date_to" type="date" class="input input-sm input-bordered w-full">
            </div>
            <div class="md:col-span-2">
              <label class="label"><span class="label-text text-xs uppercase tracking-wide">Search</span></label>
              <input v-model="filters.q" type="text" class="input input-sm input-bordered w-full" placeholder="No jurnal / deskripsi / source">
            </div>
          </div>
          <div class="mt-3 flex justify-end">
            <button type="button" class="btn btn-ghost btn-sm" @click="resetFilters">Reset filter</button>
          </div>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <h2 class="ocn-panel__title">Pindahkan transaksi accounting</h2>
            <p class="ocn-panel__desc">{{ selectedCount }} jurnal dipilih<span v-if="selectedCurrentCompanies.length"> dari {{ selectedCurrentCompanies.join(', ') }}</span>.</p>
          </div>
          <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
            <div>
              <label class="label py-0"><span class="label-text text-xs uppercase tracking-wide">Usaha tujuan</span></label>
              <select v-model="moveForm.target_company_id" class="select select-sm select-bordered w-full min-w-56">
                <option value="">Pilih usaha tujuan</option>
                <option v-for="company in companies" :key="company.id" :value="company.id">{{ company.name }}</option>
              </select>
            </div>
            <button
              type="button"
              class="btn btn-primary btn-sm"
              :disabled="!moveForm.target_company_id || selectedCount === 0 || moveForm.processing"
              @click="submitMove"
            >
              {{ moveForm.processing ? 'Memindahkan...' : 'Pindahkan' }}
            </button>
          </div>
        </div>
        <div class="overflow-x-auto">
          <table class="table table-zebra">
            <thead>
              <tr>
                <th>
                  <input
                    type="checkbox"
                    class="checkbox checkbox-sm"
                    :checked="allVisibleSelected"
                    @change="toggleVisible($event.target.checked)"
                  >
                </th>
                <th>No Jurnal</th>
                <th>Tanggal</th>
                <th>Usaha</th>
                <th>Source</th>
                <th>Deskripsi</th>
                <th class="text-right">Debit</th>
                <th class="text-right">Credit</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="entry in entryRows" :key="entry.id">
                <td>
                  <input
                    type="checkbox"
                    class="checkbox checkbox-sm"
                    :checked="selectedEntryIds.includes(entry.id)"
                    @change="toggleEntry(entry.id, $event.target.checked)"
                  >
                </td>
                <td class="font-mono text-xs">{{ entry.entry_no }}</td>
                <td>{{ entry.entry_date }}</td>
                <td>{{ entry.company_name }}</td>
                <td>
                  <span class="badge badge-ghost badge-sm">{{ entry.source_module || '-' }}</span>
                  <span v-if="entry.source_reference" class="ml-1 font-mono text-[11px] text-base-content/50">{{ entry.source_reference }}</span>
                </td>
                <td class="max-w-md">{{ entry.description || '-' }}</td>
                <td class="text-right">{{ format(entry.debit_total) }}</td>
                <td class="text-right">{{ format(entry.credit_total) }}</td>
              </tr>
              <tr v-if="!entryRows.length">
                <td colspan="8" class="py-8 text-center text-base-content/50">Belum ada jurnal sesuai filter.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <DataTablePagination
          :paginator="entries"
          @update:per-page="(n) => router.get(route('erp.accounting.utilities'), { ...filters, per_page: n }, { preserveState: true, replace: true })"
        />
      </div>
    </div>
  </AppLayout>
</template>
