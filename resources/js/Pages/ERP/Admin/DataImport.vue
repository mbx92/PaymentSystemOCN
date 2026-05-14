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
});

const page = usePage();

const tab = ref(['products', 'projects', 'seeders'].includes(props.activeTab) ? props.activeTab : 'products');

watch(
  () => props.activeTab,
  (v) => {
    if (v && ['products', 'projects', 'seeders'].includes(v)) {
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

const productForm = useForm({ file: null });
const projectForm = useForm({ file: null });
const clearWarehouseForm = useForm({ warehouse_id: '' });

const clearWarehouseDialogEl = ref(null);
const clearWarehouseDeletePhrase = ref('');
const clearWarehousePhraseInput = ref(null);

const warehouseClearTargetLabel = computed(() => {
  const w = props.warehouses.find((x) => String(x.id) === String(clearWarehouseForm.warehouse_id));
  return w ? `${w.name} (${w.code})` : '';
});

const canConfirmWarehouseClear = computed(() => clearWarehouseDeletePhrase.value.trim() === 'DELETE');

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

function onClearWarehouseModalClose() {
  clearWarehouseDeletePhrase.value = '';
}

function closeClearWarehouseModal() {
  clearWarehouseDialogEl.value?.close();
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

const productTemplateUrl = route('erp.admin.data-import.products.template');
const projectTemplateUrl = route('erp.admin.data-import.projects.template');

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
  <Head title="Administration - Impor & Seeder Data" />
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
          class="tab gap-1.5"
          :class="tab === 'seeders' ? 'tab-active' : ''"
          @click="selectTab('seeders')"
        >
          <ServerStackIcon class="h-4 w-4" />
          Database Seeder
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
            <button class="btn btn-primary" :disabled="!productForm.file || productForm.processing" @click="submitProducts">
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
              @click="runSeeder(projectFlowSeeder)"
            >
              <ArrowPathIcon class="h-4 w-4" :class="seederState[projectFlowSeeder.key]?.loading ? 'animate-spin' : ''" />
              {{ seederState[projectFlowSeeder.key]?.loading ? 'Menjalankan…' : 'Seeder alur project' }}
            </button>
          </div>

          <ul class="list-disc space-y-1 pl-5 text-sm text-base-content/80">
            <li><strong>import_key</strong>: ID unik di sistem lama (opsional). Jika diisi dan sudah ada di database, project <strong>diperbarui</strong> dan jadwal termin diganti — kecuali ada termin yang sudah ditandai lunas (<code class="rounded bg-base-200 px-1 text-xs">paid_at</code>).</li>
            <li><strong>project_type</strong>: <code class="rounded bg-base-200 px-1">system_website_development</code> atau <code class="rounded bg-base-200 px-1">cctv_installation</code>.</li>
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
            <button class="btn btn-primary" :disabled="!projectForm.file || projectForm.processing" @click="submitProjects">
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
                @click="runAllSeeders"
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
                    @click="runSeeder(seeder)"
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
