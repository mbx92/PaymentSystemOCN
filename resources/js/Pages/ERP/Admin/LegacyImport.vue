<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import { ArrowLeftIcon, ArrowPathIcon } from '@heroicons/vue/24/outline';
import { showGlobalAlert } from '@/utils/globalAlert';

const props = defineProps({
  legacyQcReport: { type: Object, default: null },
  procurementVendors: { type: Array, default: () => [] },
  procurementImportStagings: { type: Array, default: () => [] },
});

const legacyQcForm = useForm({});
const legacyImportForm = useForm({ import_keys: [] });
const procurementStagingSaveForm = useForm({ procurement_date: '', notes: '', lines: [] });
const procurementStagingConvertForm = useForm({});
const reconcileStagingForm = useForm({});

const legacyQcReport = computed(() => props.legacyQcReport);
const legacyQcProjects = computed(() => legacyQcReport.value?.projects ?? []);
const legacyImportStatusCounts = computed(() => legacyQcReport.value?.summary?.import_status_counts ?? {});
const procurementVendorOptions = computed(() => props.procurementVendors ?? []);

const importChecklistStorageKey = 'legacy-import-page-checklist-v1';
const selectedLegacyImportKeys = ref([]);
const selectedLegacyProjects = computed(() => legacyQcProjects.value.filter((project) => selectedLegacyImportKeys.value.includes(project.import_key)));
const selectableLegacyProjects = computed(() => legacyQcProjects.value.filter((project) => project.readiness !== 'blocked' && project.is_importable));
const selectedLegacyProjectsWithCompareIssues = computed(() => selectedLegacyProjects.value.filter((project) => {
  const summary = project.compare_summary || {};
  return Number(summary.items_unresolved || 0) > 0
    || Number(summary.technicians_unresolved || 0) > 0
    || Number(summary.technician_payments_unresolved || 0) > 0;
}));

const procurementStagingDrafts = reactive({});
const procurementStagingSavingId = ref(null);
const procurementStagingConvertingId = ref(null);

function runLegacyQc() {
  legacyQcForm.post(route('erp.admin.data-import.legacy-sales.qc'), {
    preserveScroll: true,
  });
}

function isLegacyProjectChecked(importKey) {
  return selectedLegacyImportKeys.value.includes(importKey);
}

function toggleLegacyProjectChecklist(importKey, checked) {
  if (checked) {
    if (!selectedLegacyImportKeys.value.includes(importKey)) {
      selectedLegacyImportKeys.value = [...selectedLegacyImportKeys.value, importKey];
    }
    return;
  }

  selectedLegacyImportKeys.value = selectedLegacyImportKeys.value.filter((key) => key !== importKey);
}

function selectAllNonBlockedLegacyProjects() {
  selectedLegacyImportKeys.value = selectableLegacyProjects.value.map((project) => project.import_key);
}

function selectReadyLegacyProjectsOnly() {
  selectedLegacyImportKeys.value = legacyQcProjects.value
    .filter((project) => project.readiness === 'ready' && project.is_importable)
    .map((project) => project.import_key);
}

function clearLegacyProjectChecklist() {
  selectedLegacyImportKeys.value = [];
}

function importStatusClass(importStatus) {
  if (!importStatus?.badge) return 'badge-ghost';
  return importStatus.badge;
}

function attemptLegacyImportSelected() {
  if (!selectedLegacyProjects.value.length) {
    showGlobalAlert('Pilih minimal satu project dari checklist terlebih dahulu.', 'warning');
    return;
  }

  legacyImportForm.import_keys = selectedLegacyProjects.value.map((project) => project.import_key);
  legacyImportForm.post(route('erp.admin.data-import.legacy-sales.import-selected'), {
    preserveScroll: true,
  });
}

function reconcileProcurementStagings() {
  reconcileStagingForm.post(route('erp.admin.data-import.procurement-stagings.reconcile'), {
    preserveScroll: true,
  });
}

function buildProcurementStagingDraft(staging) {
  return {
    procurement_date: staging.procurement_date || '',
    notes: staging.notes || '',
    bulk_vendor_id: '',
    lines: (staging.lines || []).map((line) => ({
      id: line.id,
      vendor_id: line.vendor_id ? String(line.vendor_id) : '',
      qty: Number(line.qty || 0).toFixed(2),
      unit_cost: Number(line.unit_cost || 0).toFixed(2),
    })),
  };
}

function syncProcurementStagingDrafts(stagings) {
  const nextKeys = new Set(stagings.map((staging) => staging.id));

  for (const key of Object.keys(procurementStagingDrafts)) {
    if (!nextKeys.has(key)) {
      delete procurementStagingDrafts[key];
    }
  }

  for (const staging of stagings) {
    procurementStagingDrafts[staging.id] = buildProcurementStagingDraft(staging);
  }
}

function procurementStagingDraft(staging) {
  if (!procurementStagingDrafts[staging.id]) {
    procurementStagingDrafts[staging.id] = buildProcurementStagingDraft(staging);
  }

  return procurementStagingDrafts[staging.id];
}

function applyVendorToAllProcurementLines(staging) {
  const draft = procurementStagingDraft(staging);
  if (!draft.bulk_vendor_id) return;

  draft.lines = draft.lines.map((line) => ({
    ...line,
    vendor_id: String(draft.bulk_vendor_id),
  }));
}

function procurementLineTotal(line) {
  return Number(line.qty || 0) * Number(line.unit_cost || 0);
}

function procurementStagingReadyToConvert(staging) {
  if (staging.status === 'converted') return false;

  const draft = procurementStagingDraft(staging);
  return draft.lines.length > 0 && draft.lines.every((line) => Number(line.qty || 0) > 0 && !!String(line.vendor_id || '').trim());
}

function saveProcurementStaging(staging) {
  const draft = procurementStagingDraft(staging);

  procurementStagingSaveForm.procurement_date = draft.procurement_date;
  procurementStagingSaveForm.notes = draft.notes;
  procurementStagingSaveForm.lines = draft.lines.map((line) => ({
    id: line.id,
    vendor_id: line.vendor_id ? Number(line.vendor_id) : null,
    qty: Number(line.qty || 0),
    unit_cost: Number(line.unit_cost || 0),
  }));

  procurementStagingSavingId.value = staging.id;
  procurementStagingSaveForm.post(route('erp.admin.data-import.procurement-stagings.update', staging.id), {
    preserveScroll: true,
    onFinish: () => {
      procurementStagingSavingId.value = null;
    },
  });
}

function convertProcurementStaging(staging) {
  if (!procurementStagingReadyToConvert(staging)) {
    showGlobalAlert('Pilih supplier untuk semua line dan pastikan qty > 0 sebelum convert.', 'warning');
    return;
  }

  procurementStagingConvertingId.value = staging.id;
  procurementStagingConvertForm.post(route('erp.admin.data-import.procurement-stagings.convert', staging.id), {
    preserveScroll: true,
    onFinish: () => {
      procurementStagingConvertingId.value = null;
    },
  });
}

watch(
  legacyQcProjects,
  (projects) => {
    const validKeys = new Set(projects.map((project) => project.import_key));
    selectedLegacyImportKeys.value = selectedLegacyImportKeys.value.filter((key) => validKeys.has(key));
  },
  { immediate: true },
);

watch(
  selectedLegacyImportKeys,
  (keys) => {
    if (typeof window === 'undefined') {
      return;
    }

    window.localStorage.setItem(importChecklistStorageKey, JSON.stringify(keys));
  },
  { deep: true },
);

watch(
  () => props.procurementImportStagings,
  (stagings) => {
    syncProcurementStagingDrafts(stagings || []);
  },
  { deep: true, immediate: true },
);

if (typeof window !== 'undefined') {
  try {
    const raw = window.localStorage.getItem(importChecklistStorageKey);
    selectedLegacyImportKeys.value = raw ? JSON.parse(raw) : [];
  } catch {
    selectedLegacyImportKeys.value = [];
  }
}
</script>

<template>
  <Head title="Administration - Legacy Import OCN" />
  <AppLayout>
    <div class="space-y-5">
      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Administration Workspace</p>
              <h1 class="ocn-panel__title mt-1">Legacy Import OCN</h1>
              <p class="ocn-panel__desc mt-1">Workspace khusus untuk QC data penjualan legacy, import project ke ERP, dan review procurement staging sebelum membuat PO/GR real.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
              <Link class="btn btn-ghost btn-sm gap-1.5" :href="route('erp.administration')">
                <ArrowLeftIcon class="h-4 w-4" />
                Back
              </Link>
              <Link class="btn btn-outline btn-sm" :href="route('erp.admin.data-import', { tab: 'projects' })">
                Data Import Umum
              </Link>
            </div>
          </div>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">QC legacy project & pembayaran</h2>
          <p class="ocn-panel__desc">
            Pemeriksaan read-only langsung ke database sistem lama. Fokus hanya ke <strong>Project</strong>, <strong>Customer</strong>, dan <strong>Payment</strong>; data <strong>Purchase Order</strong> diabaikan.
          </p>
        </div>
        <div class="card-body space-y-4">
          <div class="rounded-xl border border-base-200 bg-base-200/30 p-4 space-y-3">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
              <div class="space-y-2">
                <p class="text-sm text-base-content/80">
                  Aturan tanggal yang dipakai:
                  <strong>tanggal penjualan project = startDate, fallback ke createdAt</strong>,
                  dan <strong>tanggal pembayaran real = paymentDate</strong>.
                </p>
                <ul class="list-disc space-y-1 pl-5 text-sm text-base-content/75">
                  <li>Project tanpa payment atau paymentDate lebih awal dari tanggal jual akan ditandai.</li>
                  <li>Khusus kode <strong>MNT-</strong>, nilai project boleh mengikuti total payment real jika budget/final price memang kosong.</li>
                  <li>Customer yang phone/alamatnya kosong akan lolos bila nama customer yang sama sudah ada di modul CRM ERP.</li>
                  <li>Payment yang lebih besar dari nilai project dianggap valid karena bisa mewakili item/penjualan real tambahan.</li>
                  <li>Item atau teknisi yang belum match ke ERP akan tetap ditampilkan di compare, lalu dibuatkan master/user saat import selected dijalankan.</li>
                  <li>QC ini tidak menulis apa pun ke ERP.</li>
                </ul>
              </div>
              <div class="shrink-0">
                <button type="button" class="btn btn-primary" :disabled="legacyQcForm.processing" @click="runLegacyQc">
                  {{ legacyQcForm.processing ? 'Menjalankan QC…' : 'Jalankan QC Legacy' }}
                </button>
              </div>
            </div>
          </div>

          <div v-if="legacyQcReport" class="space-y-4">
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
              <div class="rounded-xl border border-base-200 bg-base-200/30 p-4">
                <div class="text-xs uppercase tracking-[0.14em] text-base-content/60">Project legacy</div>
                <div class="mt-2 text-2xl font-semibold">{{ legacyQcReport.summary?.total_projects ?? 0 }}</div>
              </div>
              <div class="rounded-xl border border-success/30 bg-success/10 p-4">
                <div class="text-xs uppercase tracking-[0.14em] text-base-content/60">Ready</div>
                <div class="mt-2 text-2xl font-semibold">{{ legacyQcReport.summary?.ready_projects ?? 0 }}</div>
              </div>
              <div class="rounded-xl border border-warning/30 bg-warning/10 p-4">
                <div class="text-xs uppercase tracking-[0.14em] text-base-content/60">Warning</div>
                <div class="mt-2 text-2xl font-semibold">{{ legacyQcReport.summary?.warning_projects ?? 0 }}</div>
              </div>
              <div class="rounded-xl border border-error/30 bg-error/10 p-4">
                <div class="text-xs uppercase tracking-[0.14em] text-base-content/60">Blocked</div>
                <div class="mt-2 text-2xl font-semibold">{{ legacyQcReport.summary?.blocked_projects ?? 0 }}</div>
              </div>
              <div class="rounded-xl border border-base-200 bg-base-200/30 p-4">
                <div class="text-xs uppercase tracking-[0.14em] text-base-content/60">Tanpa payment</div>
                <div class="mt-2 text-2xl font-semibold">{{ legacyQcReport.summary?.projects_without_payments ?? 0 }}</div>
              </div>
            </div>

            <div class="rounded-xl border border-base-200 bg-base-200/30 p-4">
              <div class="flex flex-col gap-2 md:flex-row md:flex-wrap md:items-center md:justify-between">
                <div class="text-sm text-base-content/80">
                  Sumber:
                  <strong>{{ legacyQcReport.source?.database || '-' }}</strong>
                  di <strong>{{ legacyQcReport.source?.host || '-' }}</strong>
                  schema <strong>{{ legacyQcReport.source?.schema || '-' }}</strong>
                </div>
                <div class="text-xs text-base-content/60">Generated at {{ legacyQcReport.generated_at }}</div>
              </div>
              <div class="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-4 text-sm">
                <div class="rounded-lg border border-base-200 bg-base-100/70 p-3">
                  <div class="text-xs uppercase tracking-[0.12em] text-base-content/60">Total payment</div>
                  <div class="mt-1 font-semibold">{{ legacyQcReport.summary?.total_payments ?? 0 }}</div>
                </div>
                <div class="rounded-lg border border-base-200 bg-base-100/70 p-3">
                  <div class="text-xs uppercase tracking-[0.12em] text-base-content/60">Issue error</div>
                  <div class="mt-1 font-semibold">{{ legacyQcReport.summary?.error_issues ?? 0 }}</div>
                </div>
                <div class="rounded-lg border border-base-200 bg-base-100/70 p-3">
                  <div class="text-xs uppercase tracking-[0.12em] text-base-content/60">Issue warning</div>
                  <div class="mt-1 font-semibold">{{ legacyQcReport.summary?.warning_issues ?? 0 }}</div>
                </div>
                <div class="rounded-lg border border-base-200 bg-base-100/70 p-3">
                  <div class="text-xs uppercase tracking-[0.12em] text-base-content/60">Mismatch payment</div>
                  <div class="mt-1 font-semibold">{{ legacyQcReport.summary?.payment_mismatch_projects ?? 0 }}</div>
                </div>
              </div>
            </div>

            <div class="rounded-xl border border-base-200 bg-base-200/30 p-4 space-y-3">
              <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                  <p class="font-medium text-base-content">Checklist kandidat import per project</p>
                  <p class="text-sm text-base-content/70">
                    Centang project dari hasil QC untuk menyiapkan batch import. Hanya project yang <strong>belum diimport</strong> dan tidak <strong>blocked</strong> yang bisa dicentang.
                  </p>
                </div>
                <div class="text-sm text-base-content/75">
                  Terpilih: <strong>{{ selectedLegacyProjects.length }}</strong> / {{ selectableLegacyProjects.length }}
                </div>
              </div>
              <div class="flex flex-wrap gap-2 text-xs">
                <span class="badge badge-ghost badge-sm">Belum diimport: {{ legacyImportStatusCounts.pending_import ?? 0 }}</span>
                <span class="badge badge-info badge-sm">Sudah diimport: {{ legacyImportStatusCounts.imported_project_only ?? 0 }}</span>
                <span class="badge badge-warning badge-sm">Draft procurement: {{ legacyImportStatusCounts.imported_with_procurement_staging ?? 0 }}</span>
                <span class="badge badge-success badge-sm">PO/GR dibuat: {{ legacyImportStatusCounts.imported_with_procurement_converted ?? 0 }}</span>
              </div>
              <div class="flex flex-wrap gap-2">
                <button type="button" class="btn btn-outline btn-sm" @click="selectReadyLegacyProjectsOnly">Pilih semua ready</button>
                <button type="button" class="btn btn-outline btn-sm" @click="selectAllNonBlockedLegacyProjects">Pilih semua non-blocked</button>
                <button type="button" class="btn btn-ghost btn-sm" @click="clearLegacyProjectChecklist">Kosongkan checklist</button>
                <button
                  type="button"
                  class="btn btn-primary btn-sm"
                  :disabled="selectedLegacyProjects.length === 0 || legacyImportForm.processing"
                  title="Import selected akan membuat project ERP, payment, team distribution, dan procurement staging untuk item stok."
                  @click="attemptLegacyImportSelected"
                >
                  {{ legacyImportForm.processing ? 'Mengimpor…' : 'Import selected projects' }}
                </button>
              </div>
              <div
                v-if="selectedLegacyProjects.length && selectedLegacyProjectsWithCompareIssues.length"
                class="rounded-lg border border-warning/30 bg-warning/10 p-3 text-sm"
              >
                {{ selectedLegacyProjectsWithCompareIssues.length }} project terpilih masih punya compare yang belum match sepenuhnya. Sistem akan mencoba membuat user/master product yang belum ada, jadi tetap cek detail compare sebelum import.
              </div>
              <div v-if="selectedLegacyProjects.length" class="rounded-lg border border-base-200 bg-base-100/80 p-3 text-sm">
                <div class="text-xs uppercase tracking-[0.12em] text-base-content/60">Project terpilih</div>
                <div class="mt-2 flex flex-wrap gap-2">
                  <span v-for="project in selectedLegacyProjects" :key="`selected-${project.import_key}`" class="badge badge-outline badge-sm">
                    {{ project.project_number }}
                  </span>
                </div>
              </div>
            </div>

            <div class="rounded-xl border border-base-200 bg-base-200/30 p-4">
              <div class="flex items-center justify-between gap-3">
                <div>
                  <p class="font-medium text-base-content">Procurement staging terbaru</p>
                  <p class="text-xs text-base-content/60">Draft procurement untuk OC Networks / WH-OCN sebelum dikonversi ke PO/GR real</p>
                </div>
                <button
                  type="button"
                  class="btn btn-outline btn-sm"
                  :disabled="reconcileStagingForm.processing"
                  @click="reconcileProcurementStagings"
                >
                  {{ reconcileStagingForm.processing ? 'Mengecek…' : 'Cek tabel staging' }}
                </button>
              </div>
              <div class="mt-2 text-xs text-base-content/65">
                Gunakan setelah master product diubah jadi <strong>service</strong> agar line jasa dibersihkan dari draft procurement.
              </div>
              <div v-if="!procurementImportStagings.length" class="mt-3 rounded-lg border border-dashed border-base-300 bg-base-100/50 p-4 text-sm text-base-content/65">
                Belum ada procurement staging. Setelah import selected dijalankan, item stok/material project akan ditampung di sini untuk review supplier.
              </div>
              <div v-else class="mt-3 space-y-3">
                <details v-for="staging in procurementImportStagings" :key="staging.id" class="rounded-xl border border-base-200 bg-base-100/80 p-4">
                  <summary class="cursor-pointer">
                    <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                      <div>
                        <div class="font-medium">{{ staging.legacy_project_number }} · {{ staging.legacy_project_name }}</div>
                        <div class="text-xs text-base-content/60">{{ staging.company_name }} · {{ staging.warehouse_code }} · {{ staging.procurement_date || '-' }}</div>
                      </div>
                      <div class="flex flex-wrap items-center gap-2 text-xs">
                        <span class="badge badge-outline badge-sm">{{ staging.status }}</span>
                        <span class="badge badge-outline badge-sm">{{ staging.lines.length }} line</span>
                        <span v-if="staging.conversion_summary?.purchase_orders?.length" class="badge badge-outline badge-sm">{{ staging.conversion_summary.purchase_orders.length }} PO</span>
                        <span v-if="staging.conversion_summary?.goods_receipts?.length" class="badge badge-outline badge-sm">{{ staging.conversion_summary.goods_receipts.length }} GR</span>
                      </div>
                    </div>
                  </summary>

                  <div class="mt-4 space-y-4">
                    <div class="grid gap-3 lg:grid-cols-[1.1fr_1fr]">
                      <label class="form-control">
                        <span class="label-text text-xs font-medium uppercase tracking-[0.12em] text-base-content/60">Tanggal procurement</span>
                        <input v-model="procurementStagingDraft(staging).procurement_date" type="date" class="input input-sm input-bordered" :disabled="staging.status === 'converted'">
                      </label>
                      <label class="form-control">
                        <span class="label-text text-xs font-medium uppercase tracking-[0.12em] text-base-content/60">Supplier semua line</span>
                        <div class="flex gap-2">
                          <select v-model="procurementStagingDraft(staging).bulk_vendor_id" class="select select-sm select-bordered flex-1" :disabled="staging.status === 'converted'">
                            <option value="">Pilih supplier</option>
                            <option v-for="vendor in procurementVendorOptions" :key="vendor.id" :value="String(vendor.id)">
                              {{ vendor.code }} · {{ vendor.name }}
                            </option>
                          </select>
                          <button type="button" class="btn btn-outline btn-sm" :disabled="staging.status === 'converted' || !procurementStagingDraft(staging).bulk_vendor_id" @click="applyVendorToAllProcurementLines(staging)">
                            Terapkan
                          </button>
                        </div>
                      </label>
                    </div>

                    <label class="form-control">
                      <span class="label-text text-xs font-medium uppercase tracking-[0.12em] text-base-content/60">Catatan procurement</span>
                      <textarea
                        v-model="procurementStagingDraft(staging).notes"
                        class="textarea textarea-bordered textarea-sm min-h-24 w-full"
                        :disabled="staging.status === 'converted'"
                        placeholder="Supplier dipilih belakangan, catatan negosiasi, atau instruksi procurement lainnya"
                      />
                    </label>

                    <div class="overflow-x-auto">
                      <table class="table table-xs">
                        <thead>
                          <tr>
                            <th>Produk</th>
                            <th>Qty</th>
                            <th>Unit cost</th>
                            <th>Line total</th>
                            <th>Vendor</th>
                            <th>Status</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr v-for="(line, lineIndex) in staging.lines" :key="line.id">
                            <td>
                              <div>{{ line.product_name }}</div>
                              <div class="text-[11px] text-base-content/60">{{ line.sku || '-' }} · {{ line.unit || '-' }}</div>
                            </td>
                            <td class="min-w-24">
                              <input v-model="procurementStagingDraft(staging).lines[lineIndex].qty" type="number" min="0.01" step="0.01" class="input input-xs input-bordered w-24" :disabled="staging.status === 'converted'">
                            </td>
                            <td class="min-w-28">
                              <input v-model="procurementStagingDraft(staging).lines[lineIndex].unit_cost" type="number" min="0" step="0.01" class="input input-xs input-bordered w-28" :disabled="staging.status === 'converted'">
                            </td>
                            <td>{{ procurementLineTotal(procurementStagingDraft(staging).lines[lineIndex]).toLocaleString('id-ID') }}</td>
                            <td class="min-w-56">
                              <select v-model="procurementStagingDraft(staging).lines[lineIndex].vendor_id" class="select select-xs select-bordered w-full" :disabled="staging.status === 'converted'">
                                <option value="">Belum dipilih</option>
                                <option v-for="vendor in procurementVendorOptions" :key="vendor.id" :value="String(vendor.id)">
                                  {{ vendor.code }} · {{ vendor.name }}
                                </option>
                              </select>
                            </td>
                            <td>
                              <span class="badge badge-xs" :class="line.status === 'converted' ? 'badge-success' : (procurementStagingDraft(staging).lines[lineIndex].vendor_id ? 'badge-info' : 'badge-warning')">
                                {{ line.status === 'converted' ? 'converted' : (procurementStagingDraft(staging).lines[lineIndex].vendor_id ? 'ready' : 'draft') }}
                              </span>
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>

                    <div class="flex flex-wrap gap-2">
                      <button type="button" class="btn btn-outline btn-sm" :disabled="staging.status === 'converted' || procurementStagingSaveForm.processing" @click="saveProcurementStaging(staging)">
                        {{ procurementStagingSavingId === staging.id && procurementStagingSaveForm.processing ? 'Menyimpan…' : 'Simpan draft staging' }}
                      </button>
                      <button type="button" class="btn btn-primary btn-sm" :disabled="!procurementStagingReadyToConvert(staging) || procurementStagingConvertForm.processing || staging.status === 'converted'" @click="convertProcurementStaging(staging)">
                        {{ procurementStagingConvertingId === staging.id && procurementStagingConvertForm.processing ? 'Mengonversi…' : 'Convert ke PO + GR' }}
                      </button>
                      <span v-if="staging.status !== 'converted'" class="text-xs text-base-content/60">
                        Convert akan membuat PO dan GR real dengan status approved. GR belum diposting ke stok.
                      </span>
                    </div>

                    <div v-if="staging.conversion_summary?.purchase_orders?.length || staging.conversion_summary?.goods_receipts?.length" class="rounded-lg border border-success/30 bg-success/10 p-3 text-sm">
                      <div class="font-medium text-base-content">Dokumen hasil konversi</div>
                      <div v-if="staging.converted_at" class="mt-1 text-xs text-base-content/65">
                        Dikonversi {{ staging.converted_at }}<span v-if="staging.converted_by_name"> oleh {{ staging.converted_by_name }}</span>
                      </div>
                      <div v-if="staging.conversion_summary?.purchase_orders?.length" class="mt-3">
                        <div class="text-xs uppercase tracking-[0.12em] text-base-content/60">Purchase Order</div>
                        <div class="mt-1 flex flex-wrap gap-2">
                          <Link v-for="po in staging.conversion_summary.purchase_orders" :key="po.number" class="link link-primary text-sm" :href="route('erp.purchasing.purchase-orders.show', po.number)">
                            {{ po.number }}
                          </Link>
                        </div>
                      </div>
                      <div v-if="staging.conversion_summary?.goods_receipts?.length" class="mt-3">
                        <div class="text-xs uppercase tracking-[0.12em] text-base-content/60">Goods Receipt</div>
                        <div class="mt-1 flex flex-wrap gap-2">
                          <Link v-for="gr in staging.conversion_summary.goods_receipts" :key="gr.number" class="link link-primary text-sm" :href="route('erp.purchasing.goods-receipts.show', gr.number)">
                            {{ gr.number }}
                          </Link>
                        </div>
                      </div>
                    </div>
                  </div>
                </details>
              </div>
            </div>

            <div v-if="legacyQcReport.issue_groups?.length" class="rounded-xl border border-base-200 bg-base-200/30 p-4">
              <div class="flex items-center justify-between gap-3">
                <p class="font-medium text-base-content">Detail alasan blocked & warning</p>
                <p class="text-xs text-base-content/60">Kelompok masalah yang perlu dibenahi sebelum import</p>
              </div>
              <div class="mt-3 grid gap-3 lg:grid-cols-2">
                <div v-for="group in legacyQcReport.issue_groups" :key="`${group.severity}-${group.code}`" class="rounded-xl border border-base-200 bg-base-100/80 p-4">
                  <div class="flex flex-wrap items-center gap-2">
                    <span class="badge badge-sm" :class="group.severity === 'error' ? 'badge-error' : 'badge-warning'">
                      {{ group.severity === 'error' ? 'blocked' : 'warning' }}
                    </span>
                    <span class="font-medium">{{ group.label }}</span>
                    <span class="text-xs text-base-content/60">{{ group.count }} project</span>
                  </div>
                  <p class="mt-2 text-sm text-base-content/80">{{ group.message }}</p>
                  <div class="mt-3 rounded-lg border border-base-200 bg-base-200/40 p-3 text-sm">
                    <div class="text-xs uppercase tracking-[0.12em] text-base-content/60">Perbaikan</div>
                    <div class="mt-1">{{ group.fix_hint }}</div>
                  </div>
                  <div class="mt-3 text-xs text-base-content/65">
                    Contoh project:
                    {{ (group.project_numbers || []).slice(0, 6).join(', ') }}
                    <span v-if="(group.project_numbers || []).length > 6">dan {{ group.project_numbers.length - 6 }} lainnya</span>
                  </div>
                </div>
              </div>
            </div>

            <div v-if="legacyQcReport.issues?.length" class="rounded-xl border border-warning/40 bg-warning/10 p-4">
              <div class="flex items-center justify-between gap-3">
                <p class="font-medium text-base-content">Temuan QC ({{ legacyQcReport.issues.length }})</p>
                <p class="text-xs text-base-content/70">Urutan prioritas: blocked lalu warning</p>
              </div>
              <ul class="mt-3 max-h-80 space-y-2 overflow-y-auto text-sm">
                <li v-for="(issue, i) in legacyQcReport.issues" :key="`${issue.project_number}-${i}`" class="rounded-lg border border-base-200 bg-base-100/80 px-3 py-2">
                  <div class="flex flex-wrap items-center gap-2">
                    <span class="badge badge-sm" :class="issue.severity === 'error' ? 'badge-error' : 'badge-warning'">
                      {{ issue.severity }}
                    </span>
                    <span class="text-xs font-medium">{{ issue.label }}</span>
                    <span class="font-mono text-xs">{{ issue.project_number }}</span>
                    <span class="font-medium">{{ issue.title }}</span>
                  </div>
                  <div class="mt-1 text-sm text-base-content/80">{{ issue.message }}</div>
                  <div class="mt-1 text-xs text-base-content/65">Perbaikan: {{ issue.fix_hint }}</div>
                </li>
              </ul>
            </div>

            <div class="rounded-xl border border-base-200 bg-base-200/30 p-4">
              <div class="flex items-center justify-between gap-3">
                <p class="font-medium text-base-content">Ringkasan per project</p>
                <p class="text-xs text-base-content/60">Import key target memakai format <code class="rounded bg-base-200 px-1">ocn1-project:&lt;legacyId&gt;</code></p>
              </div>
              <div class="mt-3 overflow-x-auto">
                <table class="table table-sm">
                  <thead>
                    <tr>
                      <th>Checklist</th>
                      <th>Project</th>
                      <th>Customer</th>
                      <th>Tanggal jual</th>
                      <th>Nilai</th>
                      <th>Payment</th>
                      <th>Status Import</th>
                      <th>Status QC</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="project in legacyQcReport.projects" :key="project.import_key">
                      <td>
                        <input type="checkbox" class="checkbox checkbox-sm" :checked="isLegacyProjectChecked(project.import_key)" :disabled="project.readiness === 'blocked' || !project.is_importable" @change="toggleLegacyProjectChecklist(project.import_key, $event.target.checked)">
                      </td>
                      <td>
                        <div class="font-medium">
                          <Link class="link link-primary" :href="route('erp.admin.legacy-import.projects.show', project.legacy_id)">
                            {{ project.project_number }}
                          </Link>
                        </div>
                        <div class="text-xs text-base-content/60">{{ project.title }}</div>
                      </td>
                      <td>
                        <div>{{ project.customer_name || '-' }}</div>
                        <div v-if="project.crm_customer_match" class="mt-1 text-xs text-success">Match CRM: {{ project.crm_customer_match.name }}</div>
                      </td>
                      <td>
                        <div>{{ project.sale_date || '-' }}</div>
                        <div class="text-xs text-base-content/60">{{ project.sale_date_source || '-' }}</div>
                      </td>
                      <td>
                        <div>{{ Number(project.expected_value || 0).toLocaleString('id-ID') }}</div>
                        <div class="text-xs text-base-content/60">sumber {{ project.expected_value_source || '-' }}</div>
                        <div class="text-xs text-base-content/60">paid {{ Number(project.paid_total || 0).toLocaleString('id-ID') }}</div>
                      </td>
                      <td>
                        <div>{{ project.payment_count }} transaksi</div>
                        <div class="text-xs text-base-content/60">{{ project.last_payment_date || '-' }}</div>
                      </td>
                      <td>
                        <span class="badge badge-sm" :class="importStatusClass(project.import_status)">
                          {{ project.import_status?.label || 'Belum diimport' }}
                        </span>
                        <div class="mt-1 text-xs text-base-content/60">{{ project.import_status?.description || '-' }}</div>
                        <div v-if="project.existing_erp_project" class="mt-1 text-xs text-base-content/60">ERP: {{ project.existing_erp_project.name }}</div>
                      </td>
                      <td>
                        <span class="badge badge-sm" :class="project.readiness === 'ready' ? 'badge-success' : (project.readiness === 'warning' ? 'badge-warning' : 'badge-error')">
                          {{ project.readiness }}
                        </span>
                        <div class="mt-1 text-xs text-base-content/60">{{ project.issues_count }} issue</div>
                        <div v-if="project.issues?.length" class="mt-2 text-xs text-base-content/75">
                          {{ project.issues[0]?.label }}<span v-if="project.issues_count > 1"> + {{ project.issues_count - 1 }} issue lain</span>
                        </div>
                        <div class="mt-2 grid gap-1 text-[11px] text-base-content/65">
                          <div>Item {{ project.compare_summary?.items_total ?? 0 }} / unresolved {{ project.compare_summary?.items_unresolved ?? 0 }}</div>
                          <div>Teknisi {{ project.compare_summary?.technicians_total ?? 0 }} / unresolved {{ project.compare_summary?.technicians_unresolved ?? 0 }}</div>
                          <div>Pay teknisi {{ project.compare_summary?.technician_payments_total ?? 0 }} / unresolved {{ project.compare_summary?.technician_payments_unresolved ?? 0 }}</div>
                        </div>
                        <Link class="link link-primary mt-2 inline-flex text-xs" :href="route('erp.admin.legacy-import.projects.show', project.legacy_id)">
                          Buka detail project
                        </Link>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <div v-else class="rounded-xl border border-dashed border-base-300 bg-base-200/20 p-6 text-sm text-base-content/70">
            Belum ada hasil QC. Jalankan pemeriksaan untuk membaca data legacy secara read-only dan melihat project mana yang siap diimport ke ERP.
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
