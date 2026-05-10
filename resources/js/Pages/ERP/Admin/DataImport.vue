<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
  activeTab: String,
});

const page = usePage();

const tab = ref(['products', 'projects'].includes(props.activeTab) ? props.activeTab : 'products');

watch(
  () => props.activeTab,
  (v) => {
    if (v && ['products', 'projects'].includes(v)) {
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

const flash = computed(() => page.props.flash ?? {});
const importErrors = computed(() => flash.value?.import_errors ?? []);
const importedCount = computed(() => flash.value?.imported_count);
const importKind = computed(() => flash.value?.import_kind ?? null);

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

const productTemplateUrl = route('erp.admin.data-import.products.template');
const projectTemplateUrl = route('erp.admin.data-import.projects.template');
</script>

<template>
  <Head title="Administration - Impor data" />
  <AppLayout>
    <div class="space-y-5">
      <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Administration Workspace</p>
        <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
          <div>
            <h1 class="text-3xl font-bold tracking-tight">Impor data (Excel)</h1>
            <p class="mt-2 text-sm text-base-content/70">
              Unduh template, sesuaikan kolom, lalu unggah. Master produk: baris dengan SKU sama akan <strong>diperbarui</strong>. Project: gunakan <code class="rounded bg-base-200 px-1 text-xs">import_key</code> unik dari sistem lama untuk mengisi ulang baris yang sama.
            </p>
          </div>
          <Link class="btn btn-ghost btn-sm" :href="route('erp.administration')">Back</Link>
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
            <li><strong>product_type</strong>: finished_goods, project_material.</li>
            <li><strong>status</strong>: active, inactive.</li>
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

      <div v-if="flash?.message" class="alert text-sm" :class="flash.type === 'error' ? 'alert-error' : flash.type === 'warning' ? 'alert-warning' : 'alert-success'">
        {{ flash.message }}
      </div>
    </div>
  </AppLayout>
</template>
