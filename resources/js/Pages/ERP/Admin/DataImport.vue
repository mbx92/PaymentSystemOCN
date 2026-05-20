<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref, reactive, watch } from 'vue';
import {ArrowLeftIcon,
  ArrowPathIcon,
  CheckCircleIcon,
  ExclamationCircleIcon,
  ServerStackIcon,} from '@heroicons/vue/24/outline';

const props = defineProps({
  activeTab: String,
  seeders: { type: Array, default: () => [] },
  warehouses: { type: Array, default: () => [] },
  projectMaterialProducts: { type: Array, default: () => [] },
  backupMeta: { type: Object, default: () => ({}) },
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

const canConfirmWarehouseClear = computed(() => clearWarehouseDeletePhrase.value.trim() === 'DELETE');
const canConfirmAction = computed(() => actionConfirmPhrase.value.trim().toUpperCase() === 'JALANKAN');

const flash = computed(() => page.props.flash ?? {});
const importErrors = computed(() => flash.value?.import_errors ?? []);
const importedCount = computed(() => flash.value?.imported_count);
const importKind = computed(() => flash.value?.import_kind ?? null);
const projectFlowSeeder = computed(() => props.seeders.find((s) => s.class === 'ProjectFlowSeeder'));

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
              <span class="label-text font-medium">Ketik <kbd class="kbd kbd-sm font-mono">DELETE</kbd> untuk melanjutkan</span>
            </label>
            <input
              id="clear-warehouse-delete-phrase"
              ref="clearWarehousePhraseInput"
              v-model="clearWarehouseDeletePhrase"
              type="text"
              class="input input-bordered w-full font-mono text-sm"
              placeholder="DELETE"
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
              <span class="label-text font-medium">Ketik <kbd class="kbd kbd-sm font-mono">JALANKAN</kbd> untuk melanjutkan</span>
            </label>
            <input
              id="action-confirm-phrase"
              ref="actionConfirmPhraseInput"
              v-model="actionConfirmPhrase"
              type="text"
              class="input input-bordered w-full font-mono text-sm"
              placeholder="JALANKAN"
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
              Lanjutkan tindakan
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
