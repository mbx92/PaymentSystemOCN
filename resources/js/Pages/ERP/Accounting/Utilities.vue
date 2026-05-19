<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import DataTablePagination from '@/Components/DataTablePagination.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { computed, reactive, ref, watch } from 'vue';
import { useDateFormat } from '@/composables/useDateFormat';

const props = defineProps({
  companies: Array,
  entries: Object,
  companySummaries: Array,
  filters: Object,
  posChannelCorrection: Object,
  cashAccountBackfill: Object,
  cashBankAccounts: Array,
  cashAccountUsage: Array,
  cashAccountReassignment: Object,
  inventoryReservationSync: Object,
  inventoryStockRebuild: Object,
});

const { formatDate } = useDateFormat();

const filters = reactive({
  company_id: props.filters?.company_id ?? '',
  date_from: props.filters?.date_from ?? '',
  date_to: props.filters?.date_to ?? '',
  q: props.filters?.q ?? '',
});
const activeTab = ref('journals');

const selectedEntryIds = ref([]);
const moveForm = useForm({
  target_company_id: '',
  journal_entry_ids: [],
});
const correctionForm = useForm({
  journal_entry_ids: [],
});
const backfillForm = useForm({});
const inventoryReservationForm = useForm({});
const inventoryStockRebuildForm = useForm({});
const reassignForm = useForm({
  from_account_id: '',
  to_account_id: '',
  date_from: '',
  date_to: '',
});

const backfillReadyTotal = computed(
  () => (props.cashAccountBackfill?.cash_in_ready ?? 0) + (props.cashAccountBackfill?.cash_out_ready ?? 0),
);
const backfillPendingTotal = computed(
  () => (props.cashAccountBackfill?.cash_in_pending ?? 0) + (props.cashAccountBackfill?.cash_out_pending ?? 0),
);
const backfillConfirmMessage = computed(
  () =>
    `Lengkapi akun kas pada ${backfillReadyTotal.value} transaksi dari jurnal yang sudah diposting? Jurnal GL tidak diubah.`,
);
const inventoryReservationSummary = computed(() => props.inventoryReservationSync ?? {});
const inventoryReservationConfirmMessage = computed(() =>
  `Sinkronkan ulang reserved stock? ${inventoryReservationSummary.value.warehouse_rows_updated ?? 0} baris gudang terdeteksi perlu diperbaiki.`,
);
const inventoryStockRebuildSummary = computed(() => props.inventoryStockRebuild ?? {});
const inventoryStockRebuildConfirmMessage = computed(() =>
  `Rebuild stok warehouse dari stock movement? ${inventoryStockRebuildSummary.value.warehouse_rows_updated ?? 0} baris akan diperbarui dan ${inventoryStockRebuildSummary.value.warehouse_rows_created ?? 0} baris akan dibuat.`,
);

const reassignPreview = computed(() => props.cashAccountReassignment ?? null);
const reassignTotal = computed(
  () => (reassignPreview.value?.cash_in_count ?? 0) + (reassignPreview.value?.cash_out_count ?? 0),
);
const reassignConfirmMessage = computed(() => {
  const from = reassignPreview.value?.from_account_label ?? 'akun sumber';
  const to = cashBankAccounts.value.find((a) => String(a.id) === String(reassignForm.to_account_id))?.label ?? 'akun tujuan';
  return `Pindahkan ${reassignTotal.value} transaksi dari ${from} ke ${to}? Baris jurnal kas/bank ikut diperbarui.`;
});
const cashBankAccounts = computed(() => props.cashBankAccounts ?? []);

const format = (n) =>
  new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n || 0);

const entryRows = computed(() => props.entries?.data ?? []);
const correctionCandidates = computed(() => props.posChannelCorrection?.candidates ?? []);
const selectableIds = computed(() => entryRows.value.map((entry) => entry.id));
const correctionCandidateIds = computed(() => correctionCandidates.value.map((entry) => entry.id));
const allVisibleSelected = computed(() =>
  selectableIds.value.length > 0 && selectableIds.value.every((id) => selectedEntryIds.value.includes(id)),
);
const allCorrectionCandidatesSelected = computed(() =>
  correctionCandidateIds.value.length > 0 && correctionCandidateIds.value.every((id) => selectedEntryIds.value.includes(id)),
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

const toggleCorrectionCandidates = (checked) => {
  const ids = correctionCandidateIds.value;
  if (checked) {
    selectedEntryIds.value = [...new Set([...selectedEntryIds.value, ...ids])];
    return;
  }
  selectedEntryIds.value = selectedEntryIds.value.filter((id) => !ids.includes(id));
};

const applyFilters = () => {
  const params = { ...filters };
  if (reassignForm.from_account_id) {
    params.reassign_from = reassignForm.from_account_id;
  }
  router.get(route('erp.accounting.utilities'), params, { preserveState: true, replace: true });
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

watch(
  () => props.cashAccountReassignment?.from_account_id,
  (fromId) => {
    if (fromId) {
      reassignForm.from_account_id = fromId;
    }
  },
  { immediate: true },
);

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

const submitPosChannelCorrection = () => {
  correctionForm.journal_entry_ids = selectedEntryIds.value;
  correctionForm.post(route('erp.accounting.utilities.correct-pos-channel-payable'), {
    preserveScroll: true,
    onSuccess: () => {
      selectedEntryIds.value = [];
      correctionForm.reset('journal_entry_ids');
    },
  });
};

const openBackfillModal = () => {
  document.getElementById('modal-confirm-backfill-cash-accounts')?.showModal();
};

const confirmCashAccountBackfill = () => {
  backfillForm.post(route('erp.accounting.utilities.backfill-cash-accounts'), { preserveScroll: true });
};

const openInventoryReservationModal = () => {
  document.getElementById('modal-confirm-sync-inventory-reservations')?.showModal();
};

const confirmInventoryReservationSync = () => {
  inventoryReservationForm.post(route('erp.accounting.utilities.sync-inventory-reservations'), { preserveScroll: true });
};

const openInventoryStockRebuildModal = () => {
  document.getElementById('modal-confirm-rebuild-inventory-stocks')?.showModal();
};

const confirmInventoryStockRebuild = () => {
  inventoryStockRebuildForm.post(route('erp.accounting.utilities.rebuild-inventory-stocks'), { preserveScroll: true });
};

const loadReassignPreview = () => {
  if (!reassignForm.from_account_id) {
    return;
  }
  router.get(
    route('erp.accounting.utilities'),
    {
      ...filters,
      reassign_from: reassignForm.from_account_id,
    },
    { preserveState: true, replace: true },
  );
};

const openReassignModal = () => {
  reassignForm.date_from = filters.date_from;
  reassignForm.date_to = filters.date_to;
  document.getElementById('modal-confirm-reassign-cash-accounts')?.showModal();
};

const confirmCashAccountReassign = () => {
  reassignForm.post(route('erp.accounting.utilities.reassign-cash-accounts'), {
    preserveScroll: true,
    onSuccess: () => {
      reassignForm.reset('from_account_id', 'to_account_id', 'date_from', 'date_to');
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
              <p class="ocn-panel__desc mt-1">Perbaikan data accounting dari browser: pindah jurnal antar usaha, koreksi akun kas/bank salah, koreksi COA POS, dan lengkapi akun kas transaksi lama.</p>
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

      <div class="rounded-2xl border border-base-300 bg-base-100 p-2 shadow-sm">
        <div class="tabs tabs-boxed grid grid-cols-2 gap-2 md:grid-cols-4">
          <button type="button" class="tab tab-sm" :class="{ 'tab-active': activeTab === 'journals' }" @click="activeTab = 'journals'">Jurnal</button>
          <button type="button" class="tab tab-sm" :class="{ 'tab-active': activeTab === 'cash' }" @click="activeTab = 'cash'">Kas/Bank</button>
          <button type="button" class="tab tab-sm" :class="{ 'tab-active': activeTab === 'inventory' }" @click="activeTab = 'inventory'">Inventory</button>
          <button type="button" class="tab tab-sm" :class="{ 'tab-active': activeTab === 'pos' }" @click="activeTab = 'pos'">POS COA</button>
        </div>
      </div>

      <div v-show="activeTab === 'journals'" class="ocn-panel">
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

      <div v-show="activeTab === 'journals'" class="ocn-panel">
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
                <td class="whitespace-nowrap">{{ formatDate(entry.entry_date) }}</td>
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

      <div v-show="activeTab === 'cash'" class="ocn-panel">
        <div class="ocn-panel__head flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <h2 class="ocn-panel__title">Lengkapi akun kas transaksi lama</h2>
            <p class="ocn-panel__desc mt-1">
              Untuk kas masuk/keluar yang sudah punya jurnal tetapi kolom akun kas masih kosong (mis. pembayaran invoice lama).
              Nilai diambil dari baris debit/kredit jurnal — jurnal GL tidak diubah.
            </p>
          </div>
          <button
            type="button"
            class="btn btn-primary btn-sm"
            :disabled="backfillReadyTotal === 0 || backfillForm.processing"
            @click="openBackfillModal"
          >
            {{ backfillForm.processing ? 'Memproses...' : `Perbaiki ${backfillReadyTotal} transaksi` }}
          </button>
        </div>
        <div class="card-body pt-0">
          <div class="grid gap-3 md:grid-cols-4">
            <div class="rounded-lg border border-base-300 bg-base-100 p-3">
              <p class="text-xs uppercase tracking-wide text-base-content/50">Siap diperbaiki</p>
              <p class="mt-1 text-sm font-semibold">{{ backfillReadyTotal }} transaksi</p>
              <p class="mt-1 text-xs text-base-content/60">{{ cashAccountBackfill?.cash_in_ready ?? 0 }} masuk · {{ cashAccountBackfill?.cash_out_ready ?? 0 }} keluar</p>
            </div>
            <div class="rounded-lg border border-base-300 bg-base-100 p-3">
              <p class="text-xs uppercase tracking-wide text-base-content/50">Masih kosong</p>
              <p class="mt-1 text-sm font-semibold">{{ backfillPendingTotal }} baris</p>
            </div>
            <div class="rounded-lg border border-base-300 bg-base-100 p-3">
              <p class="text-xs uppercase tracking-wide text-base-content/50">Tanpa jurnal</p>
              <p class="mt-1 text-sm font-semibold">
                {{ cashAccountBackfill?.cash_in_without_journal ?? 0 }} masuk ·
                {{ cashAccountBackfill?.cash_out_without_journal ?? 0 }} keluar
              </p>
              <p class="mt-1 text-xs text-base-content/60">Perlu input manual di cashflow</p>
            </div>
            <div class="rounded-lg border border-base-300 bg-base-100 p-3">
              <p class="text-xs uppercase tracking-wide text-base-content/50">Tidak bisa dari jurnal</p>
              <p class="mt-1 text-sm font-semibold">{{ (cashAccountBackfill?.cash_in_skipped ?? 0) + (cashAccountBackfill?.cash_out_skipped ?? 0) }}</p>
            </div>
          </div>
          <p v-if="backfillReadyTotal === 0 && backfillPendingTotal === 0" class="mt-3 rounded-lg border border-success/30 bg-success/10 p-3 text-sm text-base-content/70">
            Semua transaksi kas masuk/keluar sudah memiliki akun kas.
          </p>
          <p v-else-if="backfillReadyTotal === 0 && backfillPendingTotal > 0" class="mt-3 rounded-lg border border-warning/30 bg-warning/10 p-3 text-sm text-base-content/70">
            Ada transaksi tanpa akun kas, tetapi belum bisa diisi otomatis (belum ada jurnal atau struktur jurnal tidak standar).
          </p>
          <div v-if="cashAccountBackfill?.samples?.length" class="mt-4 overflow-x-auto rounded-lg border border-base-300">
            <table class="table table-sm">
              <thead>
                <tr>
                  <th>Jenis</th>
                  <th>Tanggal</th>
                  <th>Kategori</th>
                  <th>Catatan</th>
                  <th class="text-right">Nominal</th>
                  <th>Akun dari jurnal</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in cashAccountBackfill.samples" :key="`${row.domain}-${row.id}`">
                  <td><span class="badge badge-ghost badge-sm">{{ row.domain === 'cash_in' ? 'Masuk' : 'Keluar' }}</span></td>
                  <td class="whitespace-nowrap">{{ formatDate(row.date) }}</td>
                  <td>{{ row.category || '-' }}</td>
                  <td class="max-w-xs truncate">{{ row.note || '-' }}</td>
                  <td class="text-right font-semibold">{{ format(row.amount) }}</td>
                  <td class="text-sm">{{ row.resolved_account }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div v-show="activeTab === 'inventory'" class="ocn-panel">
        <div class="ocn-panel__head flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <h2 class="ocn-panel__title">Re-sync reserved stock inventory</h2>
            <p class="ocn-panel__desc mt-1">
              Sinkronkan ulang `reserved_qty` warehouse dari material project aktif saja. Berguna untuk membersihkan reserve lama yang tertinggal setelah project selesai atau data lama drift.
            </p>
          </div>
          <button
            type="button"
            class="btn btn-primary btn-sm"
            :disabled="(inventoryReservationSummary.warehouse_rows_updated ?? 0) === 0 || inventoryReservationForm.processing"
            @click="openInventoryReservationModal"
          >
            {{ inventoryReservationForm.processing ? 'Memproses...' : `Re-sync ${inventoryReservationSummary.warehouse_rows_updated ?? 0} baris` }}
          </button>
        </div>
        <div class="card-body pt-0">
          <div class="grid gap-3 md:grid-cols-4">
            <div class="rounded-lg border border-base-300 bg-base-100 p-3">
              <p class="text-xs uppercase tracking-wide text-base-content/50">Baris gudang dicek</p>
              <p class="mt-1 text-sm font-semibold">{{ inventoryReservationSummary.warehouse_rows_checked ?? 0 }}</p>
            </div>
            <div class="rounded-lg border border-base-300 bg-base-100 p-3">
              <p class="text-xs uppercase tracking-wide text-base-content/50">Perlu diperbarui</p>
              <p class="mt-1 text-sm font-semibold">{{ inventoryReservationSummary.warehouse_rows_updated ?? 0 }}</p>
            </div>
            <div class="rounded-lg border border-base-300 bg-base-100 p-3">
              <p class="text-xs uppercase tracking-wide text-base-content/50">Reserve lama bisa dibersihkan</p>
              <p class="mt-1 text-sm font-semibold">{{ inventoryReservationSummary.warehouse_rows_cleared ?? 0 }}</p>
            </div>
            <div class="rounded-lg border border-base-300 bg-base-100 p-3">
              <p class="text-xs uppercase tracking-wide text-base-content/50">Total reserved</p>
              <p class="mt-1 text-sm font-semibold">
                {{ inventoryReservationSummary.total_reserved_before ?? 0 }} -> {{ inventoryReservationSummary.total_reserved_after ?? 0 }}
              </p>
            </div>
          </div>
          <p v-if="(inventoryReservationSummary.warehouse_rows_updated ?? 0) === 0" class="mt-3 rounded-lg border border-success/30 bg-success/10 p-3 text-sm text-base-content/70">
            Reserved stock warehouse sudah sinkron.
          </p>
        </div>
      </div>

      <div v-show="activeTab === 'inventory'" class="ocn-panel">
        <div class="ocn-panel__head flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <h2 class="ocn-panel__title">Rebuild qty stock dari movement</h2>
            <p class="ocn-panel__desc mt-1">
              Bangun ulang `qty` warehouse dari histori `stock movement`. Gunakan jika movement sudah ada tetapi angka stok di management stock masih salah.
            </p>
          </div>
          <button
            type="button"
            class="btn btn-warning btn-sm"
            :disabled="((inventoryStockRebuildSummary.warehouse_rows_updated ?? 0) === 0 && (inventoryStockRebuildSummary.warehouse_rows_created ?? 0) === 0) || inventoryStockRebuildForm.processing"
            @click="openInventoryStockRebuildModal"
          >
            {{ inventoryStockRebuildForm.processing ? 'Memproses...' : `Rebuild ${inventoryStockRebuildSummary.warehouse_rows_updated ?? 0} update` }}
          </button>
        </div>
        <div class="card-body pt-0">
          <div class="grid gap-3 md:grid-cols-4">
            <div class="rounded-lg border border-base-300 bg-base-100 p-3">
              <p class="text-xs uppercase tracking-wide text-base-content/50">Pair dicek</p>
              <p class="mt-1 text-sm font-semibold">{{ inventoryStockRebuildSummary.warehouse_rows_checked ?? 0 }}</p>
            </div>
            <div class="rounded-lg border border-base-300 bg-base-100 p-3">
              <p class="text-xs uppercase tracking-wide text-base-content/50">Perlu update</p>
              <p class="mt-1 text-sm font-semibold">{{ inventoryStockRebuildSummary.warehouse_rows_updated ?? 0 }}</p>
            </div>
            <div class="rounded-lg border border-base-300 bg-base-100 p-3">
              <p class="text-xs uppercase tracking-wide text-base-content/50">Row baru</p>
              <p class="mt-1 text-sm font-semibold">{{ inventoryStockRebuildSummary.warehouse_rows_created ?? 0 }}</p>
            </div>
            <div class="rounded-lg border border-base-300 bg-base-100 p-3">
              <p class="text-xs uppercase tracking-wide text-base-content/50">Total qty</p>
              <p class="mt-1 text-sm font-semibold">
                {{ inventoryStockRebuildSummary.total_qty_before ?? 0 }} -> {{ inventoryStockRebuildSummary.total_qty_after ?? 0 }}
              </p>
            </div>
          </div>
          <p class="mt-3 rounded-lg border border-warning/30 bg-warning/10 p-3 text-sm text-base-content/70">
            Gunakan hanya jika histori stock movement sudah lengkap. Utility ini menjadikan stock movement sebagai sumber kebenaran qty warehouse.
          </p>
        </div>
      </div>

      <div v-show="activeTab === 'cash'" class="ocn-panel">
        <div class="ocn-panel__head flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <h2 class="ocn-panel__title">Pindahkan akun kas/bank salah</h2>
            <p class="ocn-panel__desc mt-1">
              Untuk kas masuk/keluar yang tercatat di akun kas padahal seharusnya bank (atau sebaliknya).
              Memperbarui kolom sumber dana dan baris jurnal GL pada sisi kas/bank.
            </p>
          </div>
          <button
            type="button"
            class="btn btn-primary btn-sm"
            :disabled="!reassignForm.from_account_id || !reassignForm.to_account_id || reassignTotal === 0 || reassignForm.processing"
            @click="openReassignModal"
          >
            {{ reassignForm.processing ? 'Memindahkan...' : `Pindahkan ${reassignTotal} transaksi` }}
          </button>
        </div>
        <div class="card-body pt-0">
          <div class="grid gap-3 md:grid-cols-3">
            <div>
              <label class="label py-0"><span class="label-text text-xs uppercase tracking-wide">Akun salah (dari)</span></label>
              <select v-model="reassignForm.from_account_id" class="select select-sm select-bordered w-full" @change="loadReassignPreview">
                <option value="">Pilih akun sumber</option>
                <option v-for="account in cashBankAccounts" :key="account.id" :value="account.id">{{ account.label }}</option>
              </select>
            </div>
            <div>
              <label class="label py-0"><span class="label-text text-xs uppercase tracking-wide">Akun benar (ke)</span></label>
              <select v-model="reassignForm.to_account_id" class="select select-sm select-bordered w-full">
                <option value="">Pilih akun tujuan</option>
                <option
                  v-for="account in cashBankAccounts"
                  :key="`to-${account.id}`"
                  :value="account.id"
                  :disabled="String(account.id) === String(reassignForm.from_account_id)"
                >
                  {{ account.label }}
                </option>
              </select>
            </div>
            <div class="flex items-end">
              <button type="button" class="btn btn-ghost btn-sm" :disabled="!reassignForm.from_account_id" @click="loadReassignPreview">
                Muat ulang pratinjau
              </button>
            </div>
          </div>
          <p class="mt-3 text-xs text-base-content/50">
            Pratinjau memakai filter tanggal di atas. Untuk satu pembayaran invoice project, Anda juga bisa mengubah akun lewat halaman invoice project.
          </p>
          <div v-if="cashAccountUsage?.length" class="mt-4 flex flex-wrap gap-2">
            <span
              v-for="row in cashAccountUsage"
              :key="row.account_id"
              class="badge badge-ghost badge-sm cursor-pointer"
              :class="{ 'badge-primary': String(reassignForm.from_account_id) === String(row.account_id) }"
              @click="reassignForm.from_account_id = row.account_id; loadReassignPreview()"
            >
              {{ row.label }}: {{ row.cash_in_count }} masuk · {{ row.cash_out_count }} keluar
            </span>
          </div>
          <div v-if="reassignPreview" class="mt-4 grid gap-3 md:grid-cols-3">
            <div class="rounded-lg border border-base-300 bg-base-100 p-3">
              <p class="text-xs uppercase tracking-wide text-base-content/50">Kas masuk</p>
              <p class="mt-1 text-sm font-semibold">{{ reassignPreview.cash_in_count }}</p>
            </div>
            <div class="rounded-lg border border-base-300 bg-base-100 p-3">
              <p class="text-xs uppercase tracking-wide text-base-content/50">Kas keluar</p>
              <p class="mt-1 text-sm font-semibold">{{ reassignPreview.cash_out_count }}</p>
            </div>
            <div class="rounded-lg border border-base-300 bg-base-100 p-3">
              <p class="text-xs uppercase tracking-wide text-base-content/50">Baris jurnal</p>
              <p class="mt-1 text-sm font-semibold">{{ reassignPreview.journal_lines_count }}</p>
            </div>
          </div>
          <p v-else-if="reassignForm.from_account_id" class="mt-3 rounded-lg border border-base-300 bg-base-100 p-3 text-sm text-base-content/60">
            Pilih akun sumber untuk melihat pratinjau transaksi yang akan dipindahkan.
          </p>
          <div v-if="reassignPreview?.samples?.length" class="mt-4 overflow-x-auto rounded-lg border border-base-300">
            <table class="table table-sm">
              <thead>
                <tr>
                  <th>Jenis</th>
                  <th>Tanggal</th>
                  <th>Kategori</th>
                  <th>Catatan</th>
                  <th class="text-right">Nominal</th>
                  <th>Jurnal</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in reassignPreview.samples" :key="`${row.domain}-${row.id}`">
                  <td><span class="badge badge-ghost badge-sm">{{ row.domain === 'cash_in' ? 'Masuk' : 'Keluar' }}</span></td>
                  <td class="whitespace-nowrap">{{ formatDate(row.date) }}</td>
                  <td>{{ row.category || '-' }}</td>
                  <td class="max-w-xs truncate">{{ row.note || '-' }}</td>
                  <td class="text-right font-semibold">{{ format(row.amount) }}</td>
                  <td>{{ row.has_journal ? 'Ada' : 'Tidak ada' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div v-show="activeTab === 'pos'" class="ocn-panel">
        <div class="ocn-panel__head flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <h2 class="ocn-panel__title">Koreksi COA POS admin channel</h2>
            <p class="ocn-panel__desc mt-1">
              Mengganti baris kredit biaya admin channel lama ke akun hutang estimasi sesuai Pengaturan COA terakhir.
            </p>
          </div>
          <button
            type="button"
            class="btn btn-warning btn-sm"
            :disabled="!posChannelCorrection?.can_correct || selectedCount === 0 || correctionForm.processing"
            @click="submitPosChannelCorrection"
          >
            {{ correctionForm.processing ? 'Mengoreksi...' : 'Koreksi jurnal dipilih' }}
          </button>
        </div>
        <div class="card-body pt-0">
          <div v-if="posChannelCorrection?.can_correct" class="grid gap-3 md:grid-cols-3">
            <div class="rounded-lg border border-base-300 bg-base-100 p-3">
              <p class="text-xs uppercase tracking-wide text-base-content/50">Akun beban</p>
              <p class="mt-1 text-sm font-semibold">{{ posChannelCorrection.expense_account }}</p>
            </div>
            <div class="rounded-lg border border-base-300 bg-base-100 p-3">
              <p class="text-xs uppercase tracking-wide text-base-content/50">Akun hutang tujuan</p>
              <p class="mt-1 text-sm font-semibold">{{ posChannelCorrection.payable_account }}</p>
            </div>
            <div class="rounded-lg border border-base-300 bg-base-100 p-3">
              <p class="text-xs uppercase tracking-wide text-base-content/50">Kandidat sesuai filter</p>
              <p class="mt-1 text-sm font-semibold">{{ posChannelCorrection.candidate_count ?? 0 }} baris kredit</p>
            </div>
          </div>
          <div v-else class="rounded-lg border border-warning/30 bg-warning/10 p-3 text-sm text-base-content/70">
            {{ posChannelCorrection?.message || 'Pengaturan COA belum siap untuk koreksi.' }}
          </div>
          <p class="mt-3 text-xs text-base-content/50">
            Koreksi hanya memproses jurnal POS yang dicentang dan memiliki debit serta kredit pada akun beban admin channel dengan nominal sama.
          </p>
          <div v-if="posChannelCorrection?.can_correct" class="mt-4 overflow-x-auto rounded-lg border border-base-300">
            <table class="table table-sm">
              <thead>
                <tr>
                  <th class="w-10">
                    <input
                      type="checkbox"
                      class="checkbox checkbox-sm"
                      :checked="allCorrectionCandidatesSelected"
                      @change="toggleCorrectionCandidates($event.target.checked)"
                    >
                  </th>
                  <th>No Jurnal</th>
                  <th>Tanggal</th>
                  <th>Usaha</th>
                  <th>Source</th>
                  <th>Deskripsi</th>
                  <th class="text-right">Baris</th>
                  <th class="text-right">Nominal</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="entry in correctionCandidates" :key="`correction-${entry.id}`">
                  <td>
                    <input
                      type="checkbox"
                      class="checkbox checkbox-sm"
                      :checked="selectedEntryIds.includes(entry.id)"
                      @change="toggleEntry(entry.id, $event.target.checked)"
                    >
                  </td>
                  <td class="font-mono text-xs">{{ entry.entry_no }}</td>
                  <td class="whitespace-nowrap">{{ formatDate(entry.entry_date) }}</td>
                  <td>{{ entry.company_name }}</td>
                  <td>
                    <span class="badge badge-ghost badge-sm">{{ entry.source_module || '-' }}</span>
                    <span v-if="entry.source_reference" class="ml-1 font-mono text-[11px] text-base-content/50">{{ entry.source_reference }}</span>
                  </td>
                  <td class="max-w-md">{{ entry.description || '-' }}</td>
                  <td class="text-right">{{ entry.candidate_count }}</td>
                  <td class="text-right font-semibold">{{ format(entry.candidate_amount) }}</td>
                </tr>
                <tr v-if="!correctionCandidates.length">
                  <td colspan="8" class="py-8 text-center text-base-content/50">
                    Tidak ada jurnal kandidat sesuai filter saat ini.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <ConfirmModal
      id="modal-confirm-reassign-cash-accounts"
      title="Pindahkan akun kas/bank"
      :message="reassignConfirmMessage"
      confirm-text="Pindahkan"
      confirm-class="btn-primary"
      @confirm="confirmCashAccountReassign"
    />
    <ConfirmModal
      id="modal-confirm-backfill-cash-accounts"
      title="Lengkapi akun kas transaksi lama"
      :message="backfillConfirmMessage"
      confirm-text="Perbaiki"
      confirm-class="btn-primary"
      @confirm="confirmCashAccountBackfill"
    />
    <ConfirmModal
      id="modal-confirm-sync-inventory-reservations"
      title="Re-sync reserved stock"
      :message="inventoryReservationConfirmMessage"
      confirm-text="Re-sync"
      confirm-class="btn-primary"
      @confirm="confirmInventoryReservationSync"
    />
    <ConfirmModal
      id="modal-confirm-rebuild-inventory-stocks"
      title="Rebuild qty stock dari movement"
      :message="inventoryStockRebuildConfirmMessage"
      confirm-text="Rebuild"
      confirm-class="btn-warning"
      @confirm="confirmInventoryStockRebuild"
    />
  </AppLayout>
</template>
