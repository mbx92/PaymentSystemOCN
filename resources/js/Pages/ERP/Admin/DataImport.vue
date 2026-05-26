<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref, reactive, watch } from 'vue';
import {ArrowLeftIcon,
  ArrowPathIcon,
  CheckCircleIcon,
  ExclamationCircleIcon,
  ServerStackIcon,} from '@heroicons/vue/24/outline';
import { showGlobalAlert } from '@/utils/globalAlert';

const props = defineProps({
  activeTab: String,
  seeders: { type: Array, default: () => [] },
  warehouses: { type: Array, default: () => [] },
  projectMaterialProducts: { type: Array, default: () => [] },
  backupMeta: { type: Object, default: () => ({}) },
  legacyQcReport: { type: Object, default: null },
  procurementVendors: { type: Array, default: () => [] },
  procurementImportStagings: { type: Array, default: () => [] },
});

const page = usePage();

const tab = ref(['products', 'projects', 'customers', 'seeders', 'backup'].includes(props.activeTab) ? props.activeTab : 'products');
const pageTitle = computed(() => (tab.value === 'backup' ? 'Administration - Backup Database' : 'Administration - Impor & Seeder Data'));

watch(
  () => props.activeTab,
  (v) => {
    if (v && ['products', 'projects', 'customers', 'seeders', 'backup'].includes(v)) {
      tab.value = v;
    }
  },
);

function selectTab(k) {
  tab.value = k;
  const u = new URL(window.location.href);
  u.searchParams.set('tab', k);
  window.history.replaceState({}, '', u);
}

const productFileInput = ref(null);
const projectFileInput = ref(null);
const customerFileInput = ref(null);

const productForm = useForm({ file: null });
const projectForm = useForm({ file: null });
const customerForm = useForm({ file: null });
const legacyQcForm = useForm({});
const legacyImportForm = useForm({ import_keys: [] });
const clearWarehouseForm = useForm({ warehouse_id: '' });
const syncOriginWarehouseForm = useForm({});
const syncProjectMaterialWarehouseForm = useForm({});
const relocateProjectMaterialForm = useForm({
  master_product_id: '',
  source_warehouse_id: '',
  destination_warehouse_id: '',
});

const clearWarehouseDialogEl = ref(null);
const clearWarehouseDeletePhrase = ref('');
const clearWarehousePhraseInput = ref(null);
const actionConfirmDialogEl = ref(null);
const actionConfirmPhrase = ref('');
const actionConfirmPhraseInput = ref(null);
const pendingAction = ref(null);

const warehouseClearTargetLabel = computed(() => {
  const w = props.warehouses.find((x) => String(x.id) === String(clearWarehouseForm.warehouse_id));
  return w ? `${w.name} (${w.code})` : '';
});

const canConfirmWarehouseClear = computed(() => clearWarehouseDeletePhrase.value.trim().toUpperCase() === 'CONFRIM');
const canConfirmAction = computed(() => actionConfirmPhrase.value.trim().toUpperCase() === 'CONFRIM');

const flash = computed(() => page.props.flash ?? {});
const importErrors = computed(() => flash.value?.import_errors ?? []);
const importedCount = computed(() => flash.value?.imported_count);
const importKind = computed(() => flash.value?.import_kind ?? null);
const projectFlowSeeder = computed(() => props.seeders.find((s) => s.class === 'ProjectFlowSeeder'));
const legacyQcReport = computed(() => props.legacyQcReport);
const legacyQcProjects = computed(() => legacyQcReport.value?.projects ?? []);
const legacyImportStatusCounts = computed(() => legacyQcReport.value?.summary?.import_status_counts ?? {});
const procurementVendorOptions = computed(() => props.procurementVendors ?? []);
const importChecklistStorageKey = 'legacy-qc-import-checklist-v1';
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
const procurementStagingSaveForm = useForm({ procurement_date: '', notes: '', lines: [] });
const procurementStagingConvertForm = useForm({});
const procurementStagingSavingId = ref(null);
const procurementStagingConvertingId = ref(null);

function pickProductFile() {
  productFileInput.value?.click();
}
function onProductFile(e) {
  const f = e.target?.files?.[0];
  productForm.file = f || null;
}
function submitProducts() {
  if (!productForm.file) return;
  productForm.post(route('erp.admin.data-import.products.store'), {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => {
      productForm.reset('file');
      if (productFileInput.value) productFileInput.value.value = '';
    },
  });
}

function pickProjectFile() {
  projectFileInput.value?.click();
}
function onProjectFile(e) {
  const f = e.target?.files?.[0];
  projectForm.file = f || null;
}
function submitProjects() {
  if (!projectForm.file) return;
  projectForm.post(route('erp.admin.data-import.projects.store'), {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => {
      projectForm.reset('file');
      if (projectFileInput.value) projectFileInput.value.value = '';
    },
  });
}

function pickCustomerFile() {
  customerFileInput.value?.click();
}
function onCustomerFile(e) {
  const f = e.target?.files?.[0];
  customerForm.file = f || null;
}
function submitCustomers() {
  if (!customerForm.file) return;
  customerForm.post(route('erp.admin.data-import.customers.store'), {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => {
      customerForm.reset('file');
      if (customerFileInput.value) customerFileInput.value.value = '';
    },
  });
}

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

function compareStatusClass(status) {
  if (status === 'already_in_erp_project' || status === 'already_paid_in_erp' || status === 'matched_existing_distribution') return 'badge-info';
  if (status === 'matched_master_product' || status === 'matched_erp_user') return 'badge-success';
  return 'badge-warning';
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
  const qty = Number(line.qty || 0);
  const unitCost = Number(line.unit_cost || 0);
  return qty * unitCost;
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
  } catch (error) {
    selectedLegacyImportKeys.value = [];
  }
}

function onClearWarehouseModalClose() {
  clearWarehouseDeletePhrase.value = '';
}

function closeClearWarehouseModal() {
  clearWarehouseDialogEl.value?.close();
}

function onActionConfirmModalClose() {
  actionConfirmPhrase.value = '';
  pendingAction.value = null;
}

function closeActionConfirmModal() {
  actionConfirmDialogEl.value?.close();
}

async function openClearWarehouseModal() {
  if (!clearWarehouseForm.warehouse_id) {
    return;
  }
  clearWarehouseDeletePhrase.value = '';
  clearWarehouseDialogEl.value?.showModal();
  await nextTick();
  clearWarehousePhraseInput.value?.focus();
}

const actionConfirmTitle = computed(() => {
  if (!pendingAction.value) return 'Konfirmasi tindakan';

  if (pendingAction.value.type === 'import-products') return 'Konfirmasi impor produk';
  if (pendingAction.value.type === 'import-projects') return 'Konfirmasi impor project';
  if (pendingAction.value.type === 'import-customers') return 'Konfirmasi impor customer';
  if (pendingAction.value.type === 'run-all-seeders') return 'Konfirmasi jalankan semua seeder';

  return 'Konfirmasi jalankan seeder';
});

const actionConfirmDescription = computed(() => {
  if (!pendingAction.value) return '';

  if (pendingAction.value.type === 'import-products') {
    return `Anda akan mengimpor file produk${productForm.file?.name ? `: ${productForm.file.name}` : ''}. Pastikan template dan datanya sudah benar sebelum melanjutkan.`;
  }

  if (pendingAction.value.type === 'import-projects') {
    return `Anda akan mengimpor file project${projectForm.file?.name ? `: ${projectForm.file.name}` : ''}. Proses ini dapat membuat atau memperbarui data project dan termin terkait.`;
  }

  if (pendingAction.value.type === 'import-customers') {
    return `Anda akan mengimpor file customer${customerForm.file?.name ? `: ${customerForm.file.name}` : ''}. Baris dengan code yang sama akan diperbarui, sedangkan baris tanpa code akan dibuat sebagai customer baru.`;
  }

  if (pendingAction.value.type === 'run-all-seeders') {
    return 'Anda akan menjalankan seluruh daftar seeder pada halaman ini secara berurutan. Gunakan hanya bila Anda memang ingin mengisi data master awal secara massal.';
  }

  return `Anda akan menjalankan seeder ${pendingAction.value.seeder?.label ?? pendingAction.value.seeder?.class ?? ''}. Pastikan aksi ini memang diperlukan untuk data saat ini.`;
});

function openActionConfirmModal(action) {
  pendingAction.value = action;
  actionConfirmPhrase.value = '';
  actionConfirmDialogEl.value?.showModal();
  nextTick(() => actionConfirmPhraseInput.value?.focus());
}

async function submitPendingAction() {
  if (!pendingAction.value || !canConfirmAction.value) {
    return;
  }

  const action = pendingAction.value;
  closeActionConfirmModal();

  if (action.type === 'import-products') {
    submitProducts();
    return;
  }

  if (action.type === 'import-projects') {
    submitProjects();
    return;
  }

  if (action.type === 'import-customers') {
    submitCustomers();
    return;
  }

  if (action.type === 'run-all-seeders') {
    await runAllSeeders();
    return;
  }

  if (action.type === 'run-seeder' && action.seeder) {
    await runSeeder(action.seeder);
  }
}

function submitClearWarehouseProductsFromModal() {
  if (!clearWarehouseForm.warehouse_id || !canConfirmWarehouseClear.value) {
    return;
  }
  clearWarehouseForm.post(route('erp.admin.data-import.warehouse-clear-products'), {
    preserveScroll: true,
    onSuccess: () => {
      closeClearWarehouseModal();
    },
  });
}

function syncOriginWarehouses() {
  syncOriginWarehouseForm.post(route('erp.admin.data-import.master-products.sync-origin-warehouses'), {
    preserveScroll: true,
  });
}

function syncProjectMaterialWarehouses() {
  syncProjectMaterialWarehouseForm.post(route('erp.admin.data-import.project-materials.sync-origin-warehouses'), {
    preserveScroll: true,
  });
}

function relocateProjectMaterialWarehouse() {
  if (!relocateProjectMaterialForm.master_product_id || !relocateProjectMaterialForm.source_warehouse_id || !relocateProjectMaterialForm.destination_warehouse_id) {
    return;
  }

  relocateProjectMaterialForm.post(route('erp.admin.data-import.project-materials.relocate-warehouse'), {
    preserveScroll: true,
    onSuccess: () => {
      relocateProjectMaterialForm.reset();
    },
  });
}

const productTemplateUrl = route('erp.admin.data-import.products.template');
const projectTemplateUrl = route('erp.admin.data-import.projects.template');
const customerTemplateUrl = route('erp.admin.data-import.customers.template');
const backupUrl = route('erp.admin.data-import.backup');

const seederState = reactive({});
props.seeders.forEach((s) => {
  seederState[s.key] = { loading: false, success: null, message: '' };
});

async function runSeeder(seeder) {
  const state = seederState[seeder.key];
  state.loading = true;
  state.success = null;
  state.message = '';

  try {
    const res = await fetch(route('erp.admin.data-import.run-seeder'), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        Accept: 'application/json',
      },
      body: JSON.stringify({ seeder: seeder.class }),
    });

    const data = await res.json();
    state.success = data.success;
    state.message = data.message || (data.success ? 'Berhasil' : 'Gagal');
  } catch (err) {
    state.success = false;
    state.message = 'Network error: ' + (err.message || 'Gagal menghubungi server');
  } finally {
    state.loading = false;
  }
}

const runAllLoading = ref(false);

async function runAllSeeders() {
  runAllLoading.value = true;
  for (const seeder of props.seeders) {
    await runSeeder(seeder);
  }
  runAllLoading.value = false;
}
</script>

<template>
  <Head :title="pageTitle" />
  <AppLayout>
    <div class="space-y-5">
      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Administration Workspace</p>
              <h1 class="ocn-panel__title mt-1">Impor & Seeder Data</h1>
              <p class="ocn-panel__desc mt-1">Impor data dari file Excel atau jalankan database seeder untuk mengisi data master awal.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
              <Link class="btn btn-ghost btn-sm shrink-0 gap-1.5" :href="route('erp.administration')">
              <ArrowLeftIcon class="h-4 w-4" />
              Back
            </Link>
              <Link class="btn btn-outline btn-sm" :href="route('erp.admin.legacy-import')">
                Legacy Import OCN
              </Link>
            </div>
          </div>
        </div>
      </div>

      <div role="tablist" class="tabs tabs-boxed w-fit">
        <button
          type="button"
          role="tab"
          class="tab"
          :class="tab === 'products' ? 'tab-active' : ''"
          @click="selectTab('products')"
        >
          Data produk
        </button>
        <button
          type="button"
          role="tab"
          class="tab"
          :class="tab === 'projects' ? 'tab-active' : ''"
          @click="selectTab('projects')"
        >
          Data project
        </button>
        <button
          type="button"
          role="tab"
          class="tab"
          :class="tab === 'customers' ? 'tab-active' : ''"
          @click="selectTab('customers')"
        >
          Data customer
        </button>
        <button
          type="button"
          role="tab"
          class="tab gap-1.5"
          :class="tab === 'seeders' ? 'tab-active' : ''"
          @click="selectTab('seeders')"
        >
          <ServerStackIcon class="h-4 w-4" />
          Database Seeder
        </button>
        <button
          type="button"
          role="tab"
          class="tab gap-1.5"
          :class="tab === 'backup' ? 'tab-active' : ''"
          @click="selectTab('backup')"
        >
          <ArrowPathIcon class="h-4 w-4" />
          Backup Database
        </button>
      </div>

      <div class="rounded-xl border border-info/30 bg-info/10 p-4 text-sm text-base-content">
        Workflow import data legacy sekarang sudah dipindahkan ke workspace khusus agar QC, checklist import, dan procurement staging tidak bercampur dengan import file umum.
        <Link class="link link-primary ml-1" :href="route('erp.admin.legacy-import')">Buka Legacy Import OCN</Link>
      </div>

      <!-- Tab: produk -->
      <div v-show="tab === 'products'" class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Impor master produk</h2>
          <p class="ocn-panel__desc">
            Format: .xlsx, .xls, atau .csv (maks. 10 MB). Baris pertama = header seperti template.
          </p>
        </div>
        <div class="card-body space-y-4">
          <div class="flex flex-wrap gap-2">
            <a :href="productTemplateUrl" class="btn btn-outline btn-sm gap-2">Unduh template produk (.xlsx)</a>
            <Link :href="route('erp.master-products.index')" class="btn btn-ghost btn-sm">Ke daftar produk</Link>
          </div>

          <ul class="list-disc space-y-1 pl-5 text-sm text-base-content/80">
            <li><strong>sku</strong>, <strong>name</strong>, <strong>category</strong>, <strong>uom</strong> wajib (kategori &amp; UOM harus sudah ada di master).</li>
            <li><strong>sales_channel</strong>: pos, project, both (default both).</li>
            <li><strong>product_type</strong>: finished_goods, project_material, service.</li>
            <li><strong>status</strong>: active, inactive.</li>
            <li><strong>low_stock_alert_enabled</strong>: 1/0 untuk aktif/nonaktif notifikasi stok rendah.</li>
            <li><strong>warehouse_code</strong>: kode gudang (mis. TOKO). Kosong = gudang aktif pertama.</li>
          </ul>

          <input
            ref="productFileInput"
            type="file"
            class="hidden"
            accept=".xlsx,.xls,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,text/csv"
            @change="onProductFile"
          >

          <div class="flex flex-wrap items-center gap-3">
            <button type="button" class="btn btn-sm" @click="pickProductFile">Pilih file</button>
            <span v-if="productForm.file" class="text-sm font-mono text-base-content/80">{{ productForm.file.name }}</span>
            <span v-else class="text-sm text-base-content/50">Belum ada file</span>
          </div>
          <p v-if="productForm.errors.file" class="text-xs text-error">{{ productForm.errors.file }}</p>

          <div class="flex justify-end gap-2">
            <button class="btn btn-primary" :disabled="!productForm.file || productForm.processing" @click="openActionConfirmModal({ type: 'import-products' })">
              {{ productForm.processing ? 'Mengimpor…' : 'Impor produk' }}
            </button>
          </div>

          <div v-if="importedCount != null && importKind === 'products'" class="rounded-xl border border-base-200 bg-base-200/40 px-4 py-3 text-sm">
            Baris tersimpan: <strong>{{ importedCount }}</strong>
          </div>

          <div v-if="importErrors.length && importKind === 'products'" class="rounded-xl border border-warning/40 bg-warning/10 p-4">
            <p class="font-medium text-base-content">Baris dilewati — produk ({{ importErrors.length }})</p>
            <ul class="mt-2 max-h-64 overflow-y-auto space-y-1 text-xs font-mono">
              <li v-for="(err, i) in importErrors" :key="i">
                Baris {{ err.row }}: {{ err.message }}
              </li>
            </ul>
          </div>

          <div class="divider my-2" />

          <div class="rounded-xl border border-base-200 bg-base-200/30 p-4 space-y-3">
            <div>
              <h3 class="font-semibold text-sm">Kosongkan penempatan produk per gudang</h3>
              <p class="text-xs text-base-content/70 mt-1">
                Menghapus baris stok per gudang (termasuk jika qty/reservasi masih ada). Produk yang <strong>hanya</strong> terdaftar di gudang ini akan <strong>ikut terhapus dari master</strong> bila tidak ada PO, penerimaan barang, material project, POS, atau riwayat stok yang menaut. Produk yang masih ada di gudang lain hanya kehilangan penempatan di gudang ini.
              </p>
            </div>
            <div v-if="warehouses.length === 0" class="text-xs text-base-content/60">
              Belum ada gudang di master data.
            </div>
            <div v-else class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
              <label class="form-control w-full max-w-xs">
                <span class="label-text text-xs font-medium">Gudang</span>
                <select v-model="clearWarehouseForm.warehouse_id" class="select select-bordered select-sm w-full">
                  <option value="" disabled>Pilih gudang</option>
                  <option v-for="wh in warehouses" :key="wh.id" :value="wh.id">
                    {{ wh.name }} ({{ wh.code }})
                  </option>
                </select>
              </label>
              <button
                type="button"
                class="btn btn-outline btn-error btn-sm"
                :disabled="!clearWarehouseForm.warehouse_id || clearWarehouseForm.processing"
                @click="openClearWarehouseModal"
              >
                {{ clearWarehouseForm.processing ? 'Memproses…' : 'Kosongkan produk di gudang' }}
              </button>
            </div>
            <p v-if="clearWarehouseForm.errors.warehouse_id" class="text-xs text-error">
              {{ clearWarehouseForm.errors.warehouse_id }}
            </p>
          </div>

          <div class="rounded-xl border border-base-200 bg-base-200/30 p-4 space-y-3">
            <div>
              <h3 class="font-semibold text-sm">Sync warehouse asal item</h3>
              <p class="text-xs text-base-content/70 mt-1">
                Menyelaraskan <code>master_products.warehouse_id</code> dari data <code>master_product_warehouse_stocks</code>.
                Item stok akan memakai warehouse dengan qty terbesar. Item service akan dikosongkan warehouse asalnya.
              </p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
              <button
                type="button"
                class="btn btn-outline btn-sm"
                :disabled="syncOriginWarehouseForm.processing"
                @click="syncOriginWarehouses"
              >
                {{ syncOriginWarehouseForm.processing ? 'Memproses…' : 'Sync warehouse asal item' }}
              </button>
            </div>
          </div>

          <div class="rounded-xl border border-base-200 bg-base-200/30 p-4 space-y-3">
            <div>
              <h3 class="font-semibold text-sm">Sync material project ke warehouse asal item</h3>
              <p class="text-xs text-base-content/70 mt-1">
                Memperbarui <code>project_materials.warehouse_id</code> agar mengikuti <code>master_products.warehouse_id</code> terbaru.
                Sesudah itu reserved stok tiap gudang akan dihitung ulang otomatis.
              </p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
              <button
                type="button"
                class="btn btn-outline btn-sm"
                :disabled="syncProjectMaterialWarehouseForm.processing"
                @click="syncProjectMaterialWarehouses"
              >
                {{ syncProjectMaterialWarehouseForm.processing ? 'Memproses…' : 'Sync material project' }}
              </button>
            </div>
          </div>

          <div class="rounded-xl border border-base-200 bg-base-200/30 p-4 space-y-3">
            <div>
              <h3 class="font-semibold text-sm">Pindahkan material project per item</h3>
              <p class="text-xs text-base-content/70 mt-1">
                Pakai ini untuk merapikan item yang sudah terlanjur direlasikan ke gudang yang salah setelah proses mutasi.
                Hanya material project untuk item dan gudang sumber yang dipilih yang akan dipindahkan.
              </p>
            </div>
            <div class="grid gap-3 md:grid-cols-3">
              <label class="form-control">
                <span class="label-text text-xs font-medium">Item</span>
                <select v-model="relocateProjectMaterialForm.master_product_id" class="select select-bordered select-sm w-full">
                  <option value="" disabled>Pilih item</option>
                  <option v-for="product in projectMaterialProducts" :key="product.id" :value="product.id">
                    {{ product.sku }} - {{ product.name }}
                  </option>
                </select>
                <p v-if="relocateProjectMaterialForm.errors.master_product_id" class="mt-1 text-xs text-error">
                  {{ relocateProjectMaterialForm.errors.master_product_id }}
                </p>
              </label>
              <label class="form-control">
                <span class="label-text text-xs font-medium">Gudang sumber</span>
                <select v-model="relocateProjectMaterialForm.source_warehouse_id" class="select select-bordered select-sm w-full">
                  <option value="" disabled>Pilih gudang sumber</option>
                  <option v-for="wh in warehouses" :key="`source-${wh.id}`" :value="wh.id">
                    {{ wh.name }} ({{ wh.code }})
                  </option>
                </select>
                <p v-if="relocateProjectMaterialForm.errors.source_warehouse_id" class="mt-1 text-xs text-error">
                  {{ relocateProjectMaterialForm.errors.source_warehouse_id }}
                </p>
              </label>
              <label class="form-control">
                <span class="label-text text-xs font-medium">Gudang tujuan</span>
                <select v-model="relocateProjectMaterialForm.destination_warehouse_id" class="select select-bordered select-sm w-full">
                  <option value="" disabled>Pilih gudang tujuan</option>
                  <option v-for="wh in warehouses" :key="`destination-${wh.id}`" :value="wh.id">
                    {{ wh.name }} ({{ wh.code }})
                  </option>
                </select>
                <p v-if="relocateProjectMaterialForm.errors.destination_warehouse_id" class="mt-1 text-xs text-error">
                  {{ relocateProjectMaterialForm.errors.destination_warehouse_id }}
                </p>
              </label>
            </div>
            <div class="flex justify-end">
              <button
                type="button"
                class="btn btn-outline btn-sm"
                :disabled="relocateProjectMaterialForm.processing || !relocateProjectMaterialForm.master_product_id || !relocateProjectMaterialForm.source_warehouse_id || !relocateProjectMaterialForm.destination_warehouse_id"
                @click="relocateProjectMaterialWarehouse"
              >
                {{ relocateProjectMaterialForm.processing ? 'Memproses…' : 'Pindahkan material project' }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Tab: customer -->
      <div v-show="tab === 'customers'" class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Impor customer CRM</h2>
          <p class="ocn-panel__desc">
            Format: .xlsx, .xls, atau .csv (maks. 10 MB). Gunakan <strong>code</strong> bila ingin memperbarui customer yang sudah ada.
          </p>
        </div>
        <div class="card-body space-y-4">
          <div class="flex flex-wrap gap-2">
            <a :href="customerTemplateUrl" class="btn btn-outline btn-sm gap-2">Unduh template customer (.xlsx)</a>
            <Link :href="route('erp.crm.customers')" class="btn btn-ghost btn-sm">Ke daftar customer</Link>
          </div>

          <ul class="list-disc space-y-1 pl-5 text-sm text-base-content/80">
            <li><strong>name</strong> wajib diisi.</li>
            <li><strong>code</strong> opsional. Jika cocok dengan data existing, customer akan <strong>diperbarui</strong>. Jika kosong, sistem membuat kode customer otomatis.</li>
            <li><strong>email</strong> opsional, tetapi bila diisi harus valid dan unik.</li>
            <li><strong>source</strong> opsional. Kosong = <code class="rounded bg-base-200 px-1">import_excel</code>.</li>
            <li><strong>pic_email</strong> atau <strong>pic_name</strong> opsional. Jika diisi, user harus sudah ada di sistem.</li>
            <li><strong>is_active</strong>: 1/0, true/false, active/inactive.</li>
          </ul>

          <input
            ref="customerFileInput"
            type="file"
            class="hidden"
            accept=".xlsx,.xls,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,text/csv"
            @change="onCustomerFile"
          >

          <div class="flex flex-wrap items-center gap-3">
            <button type="button" class="btn btn-sm" @click="pickCustomerFile">Pilih file</button>
            <span v-if="customerForm.file" class="text-sm font-mono text-base-content/80">{{ customerForm.file.name }}</span>
            <span v-else class="text-sm text-base-content/50">Belum ada file</span>
          </div>
          <p v-if="customerForm.errors.file" class="text-xs text-error">{{ customerForm.errors.file }}</p>

          <div class="flex justify-end gap-2">
            <button class="btn btn-primary" :disabled="!customerForm.file || customerForm.processing" @click="openActionConfirmModal({ type: 'import-customers' })">
              {{ customerForm.processing ? 'Mengimpor…' : 'Impor customer' }}
            </button>
          </div>

          <div v-if="importedCount != null && importKind === 'customers'" class="rounded-xl border border-base-200 bg-base-200/40 px-4 py-3 text-sm">
            Baris tersimpan: <strong>{{ importedCount }}</strong>
          </div>

          <div v-if="importErrors.length && importKind === 'customers'" class="rounded-xl border border-warning/40 bg-warning/10 p-4">
            <p class="font-medium text-base-content">Baris dilewati — customer ({{ importErrors.length }})</p>
            <ul class="mt-2 max-h-64 overflow-y-auto space-y-1 text-xs font-mono">
              <li v-for="(err, i) in importErrors" :key="i">
                Baris {{ err.row }}: {{ err.message }}
              </li>
            </ul>
          </div>
        </div>
      </div>

      <div v-show="tab === 'backup'" class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Backup Database</h2>
          <p class="ocn-panel__desc">
            Unduh dump PostgreSQL asli dari server melalui <code>pg_dump</code> langsung dari frontend.
          </p>
        </div>
        <div class="card-body space-y-4">
          <div class="rounded-xl border border-base-300 bg-base-200/40 p-4">
            <div class="grid gap-2 text-sm md:grid-cols-2">
              <div><span class="text-base-content/60">Koneksi</span><div class="font-medium">{{ backupMeta.connection || '-' }}</div></div>
              <div><span class="text-base-content/60">Driver</span><div class="font-medium">{{ backupMeta.driver || '-' }}</div></div>
              <div><span class="text-base-content/60">Database</span><div class="font-medium break-all">{{ backupMeta.database || '-' }}</div></div>
              <div><span class="text-base-content/60">Host</span><div class="font-medium break-all">{{ backupMeta.host || '-' }}</div></div>
              <div><span class="text-base-content/60">Port</span><div class="font-medium">{{ backupMeta.port || '-' }}</div></div>
              <div><span class="text-base-content/60">Schema</span><div class="font-medium">{{ backupMeta.schema || '-' }}</div></div>
              <div><span class="text-base-content/60">Binary</span><div class="font-medium break-all">{{ backupMeta.binary || '-' }}</div></div>
              <div class="md:col-span-2"><span class="text-base-content/60">Format</span><div class="font-medium">{{ backupMeta.format || 'PostgreSQL pg_dump (.sql)' }}</div></div>
            </div>
          </div>

          <div
            class="rounded-xl border p-3 text-sm"
            :class="backupMeta.available ? 'border-success/30 bg-success/10 text-success-content' : 'border-warning/30 bg-warning/10 text-warning-content'"
          >
            {{ backupMeta.message || 'Status backup server tidak tersedia.' }}
          </div>

          <ul class="list-disc space-y-1 pl-5 text-sm text-base-content/80">
            <li>File backup diunduh dalam format <strong>.sql</strong> hasil <strong>pg_dump</strong>.</li>
            <li>Dump ini cocok untuk restore PostgreSQL dan lebih dekat dengan backup production sebenarnya.</li>
            <li>Fitur ini membutuhkan koneksi database PostgreSQL dan binary <code>pg_dump</code> tersedia di server aplikasi.</li>
          </ul>

          <div class="flex flex-wrap gap-2">
            <a
              :href="backupMeta.available ? backupUrl : undefined"
              class="btn btn-primary btn-sm gap-2"
              :class="{ 'btn-disabled pointer-events-none opacity-60': !backupMeta.available }"
            >
              Unduh Backup Database
            </a>
          </div>
        </div>
      </div>

      <!-- Tab: project -->
      <div v-show="tab === 'projects'" class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Impor project</h2>
          <p class="ocn-panel__desc">
            Untuk migrasi dari sistem lain. Kolom wajib: <strong>name</strong>, <strong>client_name</strong>, <strong>total_value</strong>. Termin dari kolom <strong>term_percentages</strong> (jumlah harus 100%). Item project (BOM) bisa diisi lewat kolom <strong>item_*</strong>.
          </p>
        </div>
        <div class="card-body space-y-4">
          <div class="flex flex-wrap gap-2">
            <a :href="projectTemplateUrl" class="btn btn-outline btn-sm gap-2">Unduh template project (.xlsx)</a>
            <Link :href="route('projects.index')" class="btn btn-ghost btn-sm">Ke daftar project</Link>
            <button
              v-if="projectFlowSeeder"
              type="button"
              class="btn btn-outline btn-sm gap-1.5"
              :disabled="seederState[projectFlowSeeder.key]?.loading"
              @click="openActionConfirmModal({ type: 'run-seeder', seeder: projectFlowSeeder })"
            >
              <ArrowPathIcon class="h-4 w-4" :class="seederState[projectFlowSeeder.key]?.loading ? 'animate-spin' : ''" />
              {{ seederState[projectFlowSeeder.key]?.loading ? 'Menjalankan…' : 'Seeder alur project' }}
            </button>
          </div>

          <ul class="list-disc space-y-1 pl-5 text-sm text-base-content/80">
            <li><strong>import_key</strong>: ID unik di sistem lama (opsional). Jika diisi dan sudah ada di database, project <strong>diperbarui</strong> dan jadwal termin diganti — kecuali ada termin yang sudah ditandai lunas (<code class="rounded bg-base-200 px-1 text-xs">paid_at</code>).</li>
            <li><strong>project_type</strong>: isi dengan <code class="rounded bg-base-200 px-1">key</code> yang terdaftar di master tipe project.</li>
            <li><strong>status</strong>: negosiasi, berjalan, selesai, dibatalkan.</li>
            <li><strong>invoice_number</strong>: opsional; harus unik jika diisi.</li>
            <li><strong>started_at</strong> / <strong>finished_at</strong>: tanggal (YYYY-MM-DD) atau tanggal Excel.</li>
            <li><strong>term_percentages</strong>: persen tiap termin dipisah koma, total 100 (contoh <code class="rounded bg-base-200 px-1">40,35,25</code>). Kosongkan = satu termin 100%.</li>
            <li><strong>term_notes</strong>: opsional, catatan per termin dipisah <strong>|</strong> (contoh <code class="rounded bg-base-200 px-1">DP|Progress|Final</code>).</li>
            <li><strong>item_sku</strong> (opsional): isi SKU <code class="rounded bg-base-200 px-1">project_material</code> untuk membuat/memperbarui item project pada baris tersebut.</li>
            <li><strong>item_warehouse_code</strong>: opsional; kosong = gudang aktif pertama.</li>
            <li><strong>item_planned_qty</strong>: wajib jika <strong>item_sku</strong> diisi (harus &gt; 0). <strong>item_reserved_qty</strong> dan <strong>item_issued_qty</strong> opsional (default 0).</li>
            <li>Untuk import banyak item di project CCTV, ulangi baris project yang sama (umumnya pakai <code class="rounded bg-base-200 px-1">import_key</code> sama), lalu beda di kolom <strong>item_*</strong>.</li>
          </ul>

          <input
            ref="projectFileInput"
            type="file"
            class="hidden"
            accept=".xlsx,.xls,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,text/csv"
            @change="onProjectFile"
          >

          <div class="flex flex-wrap items-center gap-3">
            <button type="button" class="btn btn-sm" @click="pickProjectFile">Pilih file</button>
            <span v-if="projectForm.file" class="text-sm font-mono text-base-content/80">{{ projectForm.file.name }}</span>
            <span v-else class="text-sm text-base-content/50">Belum ada file</span>
          </div>
          <p v-if="projectForm.errors.file" class="text-xs text-error">{{ projectForm.errors.file }}</p>

          <div class="flex justify-end gap-2">
            <button class="btn btn-primary" :disabled="!projectForm.file || projectForm.processing" @click="openActionConfirmModal({ type: 'import-projects' })">
              {{ projectForm.processing ? 'Mengimpor…' : 'Impor project' }}
            </button>
          </div>

          <div v-if="importedCount != null && importKind === 'projects'" class="rounded-xl border border-base-200 bg-base-200/40 px-4 py-3 text-sm">
            Baris tersimpan: <strong>{{ importedCount }}</strong>
          </div>

          <div v-if="importErrors.length && importKind === 'projects'" class="rounded-xl border border-warning/40 bg-warning/10 p-4">
            <p class="font-medium text-base-content">Baris dilewati — project ({{ importErrors.length }})</p>
            <ul class="mt-2 max-h-64 overflow-y-auto space-y-1 text-xs font-mono">
              <li v-for="(err, i) in importErrors" :key="i">
                Baris {{ err.row }}: {{ err.message }}
              </li>
            </ul>
          </div>
        </div>
      </div>

      <!-- Tab: legacy QC -->
      <div v-show="tab === 'legacy-qc'" class="ocn-panel">
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
                <button
                  type="button"
                  class="btn btn-primary"
                  :disabled="legacyQcForm.processing"
                  @click="runLegacyQc"
                >
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
                <div class="text-xs text-base-content/60">
                  Generated at {{ legacyQcReport.generated_at }}
                </div>
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
                <button type="button" class="btn btn-outline btn-sm" @click="selectReadyLegacyProjectsOnly">
                  Pilih semua ready
                </button>
                <button type="button" class="btn btn-outline btn-sm" @click="selectAllNonBlockedLegacyProjects">
                  Pilih semua non-blocked
                </button>
                <button type="button" class="btn btn-ghost btn-sm" @click="clearLegacyProjectChecklist">
                  Kosongkan checklist
                </button>
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
                  <span
                    v-for="project in selectedLegacyProjects"
                    :key="`selected-${project.import_key}`"
                    class="badge badge-outline badge-sm"
                  >
                    {{ project.project_number }}
                  </span>
                </div>
              </div>
            </div>

            <div class="rounded-xl border border-base-200 bg-base-200/30 p-4">
              <div class="flex items-center justify-between gap-3">
                <p class="font-medium text-base-content">Procurement staging terbaru</p>
                <p class="text-xs text-base-content/60">Draft procurement untuk OC Networks / WH-OCN sebelum dikonversi ke PO/GR real</p>
              </div>
              <div v-if="!procurementImportStagings.length" class="mt-3 rounded-lg border border-dashed border-base-300 bg-base-100/50 p-4 text-sm text-base-content/65">
                Belum ada procurement staging. Setelah import selected dijalankan, item stok/material project akan ditampung di sini untuk review supplier.
              </div>
              <div v-else class="mt-3 space-y-3">
                <details
                  v-for="staging in procurementImportStagings"
                  :key="staging.id"
                  class="rounded-xl border border-base-200 bg-base-100/80 p-4"
                >
                  <summary class="cursor-pointer">
                    <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                      <div>
                        <div class="font-medium">{{ staging.legacy_project_number }} · {{ staging.legacy_project_name }}</div>
                        <div class="text-xs text-base-content/60">
                          {{ staging.company_name }} · {{ staging.warehouse_code }} · {{ staging.procurement_date || '-' }}
                        </div>
                      </div>
                      <div class="flex flex-wrap items-center gap-2 text-xs">
                        <span class="badge badge-outline badge-sm">{{ staging.status }}</span>
                        <span class="badge badge-outline badge-sm">{{ staging.lines.length }} line</span>
                        <span v-if="staging.conversion_summary?.purchase_orders?.length" class="badge badge-outline badge-sm">
                          {{ staging.conversion_summary.purchase_orders.length }} PO
                        </span>
                        <span v-if="staging.conversion_summary?.goods_receipts?.length" class="badge badge-outline badge-sm">
                          {{ staging.conversion_summary.goods_receipts.length }} GR
                        </span>
                      </div>
                    </div>
                  </summary>
                  <div class="mt-4 space-y-4">
                    <div class="grid gap-3 lg:grid-cols-[1.1fr_1fr]">
                      <label class="form-control">
                        <span class="label-text text-xs font-medium uppercase tracking-[0.12em] text-base-content/60">Tanggal procurement</span>
                        <input
                          v-model="procurementStagingDraft(staging).procurement_date"
                          type="date"
                          class="input input-sm input-bordered"
                          :disabled="staging.status === 'converted'"
                        >
                      </label>
                      <label class="form-control">
                        <span class="label-text text-xs font-medium uppercase tracking-[0.12em] text-base-content/60">Supplier semua line</span>
                        <div class="flex gap-2">
                          <select
                            v-model="procurementStagingDraft(staging).bulk_vendor_id"
                            class="select select-sm select-bordered flex-1"
                            :disabled="staging.status === 'converted'"
                          >
                            <option value="">Pilih supplier</option>
                            <option v-for="vendor in procurementVendorOptions" :key="vendor.id" :value="String(vendor.id)">
                              {{ vendor.code }} · {{ vendor.name }}
                            </option>
                          </select>
                          <button
                            type="button"
                            class="btn btn-outline btn-sm"
                            :disabled="staging.status === 'converted' || !procurementStagingDraft(staging).bulk_vendor_id"
                            @click="applyVendorToAllProcurementLines(staging)"
                          >
                            Terapkan
                          </button>
                        </div>
                      </label>
                    </div>

                    <label class="form-control">
                      <span class="label-text text-xs font-medium uppercase tracking-[0.12em] text-base-content/60">Catatan procurement</span>
                      <textarea
                        v-model="procurementStagingDraft(staging).notes"
                        class="textarea textarea-bordered textarea-sm min-h-24"
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
                          <tr
                            v-for="(line, lineIndex) in staging.lines"
                            :key="line.id"
                          >
                            <td>
                              <div>{{ line.product_name }}</div>
                              <div class="text-[11px] text-base-content/60">{{ line.sku || '-' }} · {{ line.unit || '-' }}</div>
                            </td>
                            <td class="min-w-24">
                              <input
                                v-model="procurementStagingDraft(staging).lines[lineIndex].qty"
                                type="number"
                                min="0.01"
                                step="0.01"
                                class="input input-xs input-bordered w-24"
                                :disabled="staging.status === 'converted'"
                              >
                            </td>
                            <td class="min-w-28">
                              <input
                                v-model="procurementStagingDraft(staging).lines[lineIndex].unit_cost"
                                type="number"
                                min="0"
                                step="0.01"
                                class="input input-xs input-bordered w-28"
                                :disabled="staging.status === 'converted'"
                              >
                            </td>
                            <td>
                              {{ procurementLineTotal(procurementStagingDraft(staging).lines[lineIndex]).toLocaleString('id-ID') }}
                            </td>
                            <td class="min-w-56">
                              <select
                                v-model="procurementStagingDraft(staging).lines[lineIndex].vendor_id"
                                class="select select-xs select-bordered w-full"
                                :disabled="staging.status === 'converted'"
                              >
                                <option value="">Belum dipilih</option>
                                <option v-for="vendor in procurementVendorOptions" :key="vendor.id" :value="String(vendor.id)">
                                  {{ vendor.code }} · {{ vendor.name }}
                                </option>
                              </select>
                            </td>
                            <td>
                              <span
                                class="badge badge-xs"
                                :class="line.status === 'converted' ? 'badge-success' : (procurementStagingDraft(staging).lines[lineIndex].vendor_id ? 'badge-info' : 'badge-warning')"
                              >
                                {{ line.status === 'converted' ? 'converted' : (procurementStagingDraft(staging).lines[lineIndex].vendor_id ? 'ready' : 'draft') }}
                              </span>
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>

                    <div class="flex flex-wrap gap-2">
                      <button
                        type="button"
                        class="btn btn-outline btn-sm"
                        :disabled="staging.status === 'converted' || procurementStagingSaveForm.processing"
                        @click="saveProcurementStaging(staging)"
                      >
                        {{ procurementStagingSavingId === staging.id && procurementStagingSaveForm.processing ? 'Menyimpan…' : 'Simpan draft staging' }}
                      </button>
                      <button
                        type="button"
                        class="btn btn-primary btn-sm"
                        :disabled="!procurementStagingReadyToConvert(staging) || procurementStagingConvertForm.processing || staging.status === 'converted'"
                        @click="convertProcurementStaging(staging)"
                      >
                        {{ procurementStagingConvertingId === staging.id && procurementStagingConvertForm.processing ? 'Mengonversi…' : 'Convert ke PO + GR' }}
                      </button>
                      <span v-if="staging.status !== 'converted'" class="text-xs text-base-content/60">
                        Convert akan membuat PO dan GR real dengan status approved. GR belum diposting ke stok.
                      </span>
                    </div>

                    <div
                      v-if="staging.conversion_summary?.purchase_orders?.length || staging.conversion_summary?.goods_receipts?.length"
                      class="rounded-lg border border-success/30 bg-success/10 p-3 text-sm"
                    >
                      <div class="font-medium text-base-content">Dokumen hasil konversi</div>
                      <div v-if="staging.converted_at" class="mt-1 text-xs text-base-content/65">
                        Dikonversi {{ staging.converted_at }}<span v-if="staging.converted_by_name"> oleh {{ staging.converted_by_name }}</span>
                      </div>
                      <div v-if="staging.conversion_summary?.purchase_orders?.length" class="mt-3">
                        <div class="text-xs uppercase tracking-[0.12em] text-base-content/60">Purchase Order</div>
                        <div class="mt-1 flex flex-wrap gap-2">
                          <Link
                            v-for="po in staging.conversion_summary.purchase_orders"
                            :key="po.number"
                            class="link link-primary text-sm"
                            :href="route('erp.purchasing.purchase-orders.show', po.number)"
                          >
                            {{ po.number }}
                          </Link>
                        </div>
                      </div>
                      <div v-if="staging.conversion_summary?.goods_receipts?.length" class="mt-3">
                        <div class="text-xs uppercase tracking-[0.12em] text-base-content/60">Goods Receipt</div>
                        <div class="mt-1 flex flex-wrap gap-2">
                          <Link
                            v-for="gr in staging.conversion_summary.goods_receipts"
                            :key="gr.number"
                            class="link link-primary text-sm"
                            :href="route('erp.purchasing.goods-receipts.show', gr.number)"
                          >
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
                <div
                  v-for="group in legacyQcReport.issue_groups"
                  :key="`${group.severity}-${group.code}`"
                  class="rounded-xl border border-base-200 bg-base-100/80 p-4"
                >
                  <div class="flex flex-wrap items-center gap-2">
                    <span
                      class="badge badge-sm"
                      :class="group.severity === 'error' ? 'badge-error' : 'badge-warning'"
                    >
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
                <li
                  v-for="(issue, i) in legacyQcReport.issues"
                  :key="`${issue.project_number}-${i}`"
                  class="rounded-lg border border-base-200 bg-base-100/80 px-3 py-2"
                >
                  <div class="flex flex-wrap items-center gap-2">
                    <span
                      class="badge badge-sm"
                      :class="issue.severity === 'error' ? 'badge-error' : 'badge-warning'"
                    >
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
                        <input
                          type="checkbox"
                          class="checkbox checkbox-sm"
                          :checked="isLegacyProjectChecked(project.import_key)"
                          :disabled="project.readiness === 'blocked' || !project.is_importable"
                          @change="toggleLegacyProjectChecklist(project.import_key, $event.target.checked)"
                        >
                      </td>
                      <td>
                        <div class="font-medium">{{ project.project_number }}</div>
                        <div class="text-xs text-base-content/60">{{ project.title }}</div>
                      </td>
                      <td>
                        <div>{{ project.customer_name || '-' }}</div>
                        <div
                          v-if="project.crm_customer_match"
                          class="mt-1 text-xs text-success"
                        >
                          Match CRM: {{ project.crm_customer_match.name }}
                        </div>
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
                        <span
                          class="badge badge-sm"
                          :class="importStatusClass(project.import_status)"
                        >
                          {{ project.import_status?.label || 'Belum diimport' }}
                        </span>
                        <div class="mt-1 text-xs text-base-content/60">
                          {{ project.import_status?.description || '-' }}
                        </div>
                        <div v-if="project.existing_erp_project" class="mt-1 text-xs text-base-content/60">
                          ERP: {{ project.existing_erp_project.name }}
                        </div>
                      </td>
                      <td>
                        <span
                          class="badge badge-sm"
                          :class="project.readiness === 'ready' ? 'badge-success' : (project.readiness === 'warning' ? 'badge-warning' : 'badge-error')"
                        >
                          {{ project.readiness }}
                        </span>
                        <div class="mt-1 text-xs text-base-content/60">{{ project.issues_count }} issue</div>
                        <div
                          v-if="project.issues?.length"
                          class="mt-2 space-y-1 text-xs text-base-content/75"
                        >
                          <div
                            v-for="issue in project.issues"
                            :key="`${project.import_key}-${issue.code}`"
                            class="rounded border border-base-200 bg-base-100/80 px-2 py-1"
                          >
                            <span class="font-medium">{{ issue.label }}</span>: {{ issue.message }}
                          </div>
                        </div>
                        <details class="mt-3 rounded border border-base-200 bg-base-100/80 p-2">
                          <summary class="cursor-pointer text-xs font-medium text-base-content/80">
                            Lihat detail compare import
                          </summary>
                          <div class="mt-3 space-y-3 text-xs">
                            <div class="grid gap-2 md:grid-cols-3">
                              <div class="rounded border border-base-200 bg-base-200/40 p-2">
                                <div class="font-medium">Item</div>
                                <div>Total: {{ project.compare_summary?.items_total ?? 0 }}</div>
                                <div>Unresolved: {{ project.compare_summary?.items_unresolved ?? 0 }}</div>
                              </div>
                              <div class="rounded border border-base-200 bg-base-200/40 p-2">
                                <div class="font-medium">Teknisi</div>
                                <div>Total: {{ project.compare_summary?.technicians_total ?? 0 }}</div>
                                <div>Unresolved: {{ project.compare_summary?.technicians_unresolved ?? 0 }}</div>
                              </div>
                              <div class="rounded border border-base-200 bg-base-200/40 p-2">
                                <div class="font-medium">Pembayaran teknisi</div>
                                <div>Total: {{ project.compare_summary?.technician_payments_total ?? 0 }}</div>
                                <div>Unresolved: {{ project.compare_summary?.technician_payments_unresolved ?? 0 }}</div>
                              </div>
                            </div>

                            <div v-if="project.details?.items?.length" class="space-y-2">
                              <div class="font-medium">Item project</div>
                              <div class="overflow-x-auto">
                                <table class="table table-xs">
                                  <thead>
                                    <tr>
                                      <th>Legacy item</th>
                                      <th>Qty</th>
                                      <th>Match ERP</th>
                                      <th>Status</th>
                                    </tr>
                                  </thead>
                                  <tbody>
                                    <tr v-for="item in project.details.items" :key="item.legacy_item_id">
                                      <td>
                                        <div>{{ item.name }}</div>
                                        <div class="text-[11px] text-base-content/60">{{ item.sku || '-' }} · {{ item.unit || '-' }}</div>
                                      </td>
                                      <td>{{ Number(item.quantity || 0).toLocaleString('id-ID') }}</td>
                                      <td>
                                        <div v-if="item.matched_product">{{ item.matched_product.name }}</div>
                                        <div v-if="item.matched_product" class="text-[11px] text-base-content/60">{{ item.matched_product.sku }}</div>
                                        <div v-else class="text-base-content/50">Belum ada</div>
                                      </td>
                                      <td>
                                        <span class="badge badge-xs" :class="compareStatusClass(item.status)">{{ item.status }}</span>
                                      </td>
                                    </tr>
                                  </tbody>
                                </table>
                              </div>
                            </div>

                            <div v-if="project.details?.technicians?.length" class="space-y-2">
                              <div class="font-medium">Teknisi project</div>
                              <div class="overflow-x-auto">
                                <table class="table table-xs">
                                  <thead>
                                    <tr>
                                      <th>Legacy teknisi</th>
                                      <th>Fee</th>
                                      <th>Match ERP</th>
                                      <th>Status</th>
                                    </tr>
                                  </thead>
                                  <tbody>
                                    <tr v-for="technician in project.details.technicians" :key="technician.legacy_assignment_id">
                                      <td>
                                        <div>{{ technician.technician_name }}</div>
                                        <div class="text-[11px] text-base-content/60">{{ technician.legacy_user_email || technician.technician_phone || '-' }}</div>
                                      </td>
                                      <td>{{ Number(technician.fee || 0).toLocaleString('id-ID') }} <span class="text-[11px] text-base-content/60">{{ technician.fee_type }}</span></td>
                                      <td>
                                        <div v-if="technician.matched_user">{{ technician.matched_user.name }}</div>
                                        <div v-if="technician.matched_user" class="text-[11px] text-base-content/60">{{ technician.matched_user.email }}</div>
                                        <div v-else class="text-base-content/50">Belum ada</div>
                                      </td>
                                      <td>
                                        <span class="badge badge-xs" :class="compareStatusClass(technician.status)">{{ technician.status }}</span>
                                      </td>
                                    </tr>
                                  </tbody>
                                </table>
                              </div>
                            </div>

                            <div v-if="project.details?.technician_payments?.length" class="space-y-2">
                              <div class="font-medium">Pembayaran teknisi</div>
                              <div class="overflow-x-auto">
                                <table class="table table-xs">
                                  <thead>
                                    <tr>
                                      <th>Payment</th>
                                      <th>Teknisi</th>
                                      <th>Jumlah</th>
                                      <th>Status</th>
                                    </tr>
                                  </thead>
                                  <tbody>
                                    <tr v-for="payment in project.details.technician_payments" :key="payment.legacy_payment_id">
                                      <td>
                                        <div>{{ payment.payment_number }}</div>
                                        <div class="text-[11px] text-base-content/60">{{ payment.paid_date || '-' }} · {{ payment.period || '-' }}</div>
                                      </td>
                                      <td>
                                        <div>{{ payment.technician_name }}</div>
                                        <div v-if="payment.matched_user" class="text-[11px] text-base-content/60">ERP: {{ payment.matched_user.name }}</div>
                                      </td>
                                      <td>{{ Number(payment.amount || 0).toLocaleString('id-ID') }}</td>
                                      <td>
                                        <span class="badge badge-xs" :class="compareStatusClass(payment.status)">{{ payment.status }}</span>
                                      </td>
                                    </tr>
                                  </tbody>
                                </table>
                              </div>
                            </div>
                          </div>
                        </details>
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

      <!-- Tab: seeders -->
      <div v-show="tab === 'seeders'" class="space-y-4">
        <div class="ocn-panel">
          <div class="ocn-panel__head">
            <h2 class="ocn-panel__title">Database Seeder</h2>
            <p class="ocn-panel__desc">
              Jalankan seeder untuk mengisi data master awal. Seeder menggunakan <code class="rounded bg-base-200 px-1 text-xs">firstOrCreate</code> / <code class="rounded bg-base-200 px-1 text-xs">updateOrCreate</code> sehingga <strong>tidak akan menimpa</strong> data yang sudah Anda ubah.
            </p>
          </div>
          <div class="card-body space-y-3">
            <div class="flex justify-end">
              <button
                class="btn btn-primary btn-sm gap-1.5"
                :disabled="runAllLoading"
                @click="openActionConfirmModal({ type: 'run-all-seeders' })"
              >
                <ArrowPathIcon class="h-4 w-4" :class="runAllLoading ? 'animate-spin' : ''" />
                {{ runAllLoading ? 'Menjalankan semua…' : 'Jalankan Semua Seeder' }}
              </button>
            </div>

            <div class="divide-y divide-base-200 rounded-xl border border-base-200">
              <div
                v-for="seeder in seeders"
                :key="seeder.key"
                class="flex flex-wrap items-center gap-3 px-4 py-3"
              >
                <div class="min-w-0 flex-1">
                  <p class="font-semibold text-sm">{{ seeder.label }}</p>
                  <p class="text-xs text-base-content/60">{{ seeder.description }}</p>
                </div>

                <div class="flex items-center gap-2">
                  <transition name="fade" mode="out-in">
                    <span
                      v-if="seederState[seeder.key]?.success === true"
                      class="flex items-center gap-1 text-xs text-success font-medium"
                    >
                      <CheckCircleIcon class="h-4 w-4" />
                      Berhasil
                    </span>
                    <span
                      v-else-if="seederState[seeder.key]?.success === false"
                      class="flex items-center gap-1 text-xs text-error font-medium max-w-xs truncate"
                      :title="seederState[seeder.key]?.message"
                    >
                      <ExclamationCircleIcon class="h-4 w-4 shrink-0" />
                      {{ seederState[seeder.key]?.message }}
                    </span>
                  </transition>

                  <button
                    class="btn btn-outline btn-sm gap-1.5"
                    :disabled="seederState[seeder.key]?.loading"
                    @click="openActionConfirmModal({ type: 'run-seeder', seeder })"
                  >
                    <ArrowPathIcon
                      class="h-4 w-4"
                      :class="seederState[seeder.key]?.loading ? 'animate-spin' : ''"
                    />
                    {{ seederState[seeder.key]?.loading ? 'Running…' : 'Jalankan' }}
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <dialog
        ref="clearWarehouseDialogEl"
        class="modal"
        @close="onClearWarehouseModalClose"
      >
        <div class="modal-box max-w-lg">
          <h3 class="font-bold text-lg text-error">Konfirmasi kosongkan gudang</h3>
          <p class="mt-2 text-sm text-base-content/80">
            Anda akan menghapus semua penempatan produk di
            <strong class="text-base-content">{{ warehouseClearTargetLabel }}</strong>.
            Stok di gudang ini boleh tidak nol. Produk yang hanya terdaftar di gudang ini dapat ikut terhapus dari master
            jika tidak ada relasi pembelian, penerimaan, project, POS, atau riwayat stok.
          </p>
          <div class="alert alert-warning mt-3 text-sm">
            <span>Tindakan ini tidak dapat dibatalkan dari layar ini. Pastikan Anda memilih gudang yang benar.</span>
          </div>
          <div class="mt-4 space-y-2">
            <label class="label py-0" for="clear-warehouse-delete-phrase">
              <span class="label-text font-medium">Ketik <kbd class="kbd kbd-sm font-mono">CONFRIM</kbd> untuk melanjutkan</span>
            </label>
            <input
              id="clear-warehouse-delete-phrase"
              ref="clearWarehousePhraseInput"
              v-model="clearWarehouseDeletePhrase"
              type="text"
              class="input input-bordered w-full font-mono text-sm"
              placeholder="CONFRIM"
              autocomplete="off"
              autocapitalize="characters"
              spellcheck="false"
              @keydown.enter.prevent="canConfirmWarehouseClear && submitClearWarehouseProductsFromModal()"
            >
          </div>
          <div class="modal-action mt-4 flex w-full flex-wrap items-center justify-end gap-2">
            <button type="button" class="btn btn-ghost btn-sm" @click="closeClearWarehouseModal">
              Batal
            </button>
            <button
              type="button"
              class="btn btn-error btn-sm"
              :disabled="!canConfirmWarehouseClear || clearWarehouseForm.processing"
              @click="submitClearWarehouseProductsFromModal"
            >
              {{ clearWarehouseForm.processing ? 'Memproses…' : 'Hapus penempatan & produk (sesuai aturan)' }}
            </button>
          </div>
        </div>
        <form method="dialog" class="modal-backdrop">
          <button type="submit" aria-label="Tutup">close</button>
        </form>
      </dialog>

      <dialog
        ref="actionConfirmDialogEl"
        class="modal"
        @close="onActionConfirmModalClose"
      >
        <div class="modal-box max-w-lg">
          <h3 class="font-bold text-lg text-warning">Konfirmasi tindakan sensitif</h3>
          <p class="mt-2 text-sm font-medium text-base-content">
            {{ actionConfirmTitle }}
          </p>
          <p class="mt-2 text-sm text-base-content/80">
            {{ actionConfirmDescription }}
          </p>
          <div class="alert alert-warning mt-3 text-sm">
            <span>Tindakan ini sebaiknya hanya dijalankan bila Anda yakin file atau seeder yang dipilih memang benar.</span>
          </div>
          <div class="mt-4 space-y-2">
            <label class="label py-0" for="action-confirm-phrase">
              <span class="label-text font-medium">Ketik <kbd class="kbd kbd-sm font-mono">CONFRIM</kbd> untuk melanjutkan</span>
            </label>
            <input
              id="action-confirm-phrase"
              ref="actionConfirmPhraseInput"
              v-model="actionConfirmPhrase"
              type="text"
              class="input input-bordered w-full font-mono text-sm"
              placeholder="CONFRIM"
              autocomplete="off"
              autocapitalize="characters"
              spellcheck="false"
              @keydown.enter.prevent="canConfirmAction && submitPendingAction()"
            >
          </div>
          <div class="modal-action mt-4 flex w-full flex-wrap items-center justify-end gap-2">
            <button type="button" class="btn btn-ghost btn-sm" @click="closeActionConfirmModal">
              Batal
            </button>
            <button
              type="button"
              class="btn btn-warning btn-sm"
              :disabled="!canConfirmAction"
              @click="submitPendingAction"
            >
              CONFRIM
            </button>
          </div>
        </div>
        <form method="dialog" class="modal-backdrop">
          <button type="submit" aria-label="Tutup">close</button>
        </form>
      </dialog>

      <div v-if="flash?.message" class="alert text-sm" :class="flash.type === 'error' ? 'alert-error' : flash.type === 'warning' ? 'alert-warning' : 'alert-success'">
        {{ flash.message }}
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
