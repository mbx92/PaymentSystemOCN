<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed, watch, nextTick, onMounted } from 'vue';
import draggable from 'vuedraggable';
import {
  ArrowLeftIcon,
  EyeIcon,
  Squares2X2Icon,
  CheckIcon,
  XMarkIcon,
  Bars3Icon,
  TrashIcon,
  PlusIcon,
  ArrowPathIcon,
  MagnifyingGlassPlusIcon,
  MagnifyingGlassMinusIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
  template: Object,
  type: String,
  defaultBlocks: Array,
  blockMeta: Array,
});

const typeLabels = { invoice: 'Invoice', sales_note: 'Nota Penjualan', pos_receipt: 'Struk POS' };
const docTitle   = computed(() => typeLabels[props.type] ?? props.type);
const isEdit     = computed(() => !!props.template);

// ─── Page sizes ──────────────────────────────────────────────────────────────
const PAGE_SIZES = [
  { key: 'a4',     label: 'A4',           w: 794,  h: 1123 },
  { key: 'a5',     label: 'A5 Landscape', w: 794,  h: 559  },
  { key: 'letter', label: 'Letter',       w: 816,  h: 1056 },
  { key: 'legal',  label: 'Legal',        w: 816,  h: 1344 },
];
const pageSize = ref(props.template?.settings?.page_size ?? 'a4');
const currentPageSize = computed(() => PAGE_SIZES.find(p => p.key === pageSize.value) ?? PAGE_SIZES[0]);

// ─── Zoom ────────────────────────────────────────────────────────────────────
const ZOOM_LEVELS = [0.4, 0.5, 0.6, 0.75, 1.0];
const zoom = ref(0.6);
const zoomPercent = computed(() => Math.round(zoom.value * 100) + '%');
function zoomIn()  { const i = ZOOM_LEVELS.indexOf(zoom.value); if (i < ZOOM_LEVELS.length - 1) zoom.value = ZOOM_LEVELS[i + 1]; }
function zoomOut() { const i = ZOOM_LEVELS.indexOf(zoom.value); if (i > 0) zoom.value = ZOOM_LEVELS[i - 1]; }

// ─── State ───────────────────────────────────────────────────────────────────
const blocks   = ref(JSON.parse(JSON.stringify(props.defaultBlocks)));
const selected = ref(null);
const tab      = ref('canvas');
const previewLoading = ref(false);
const iframeRef = ref(null);
const flash = ref('');

const form = useForm({
  name: props.template?.name ?? `Template ${docTitle.value} Baru`,
  blocks: blocks.value,
  settings: { page_size: pageSize.value, ...(props.template?.settings ?? {}) },
});

// sync blocks → form
watch(blocks, (val) => { form.blocks = JSON.parse(JSON.stringify(val)); }, { deep: true });
// sync page size → form settings
watch(pageSize, (val) => { form.settings = { ...form.settings, page_size: val }; });

// ─── Block meta ───────────────────────────────────────────────────────────────
const metaMap = computed(() => Object.fromEntries(props.blockMeta.map(m => [m.type, m])));
const metaFor = (type) => metaMap.value[type] ?? { label: type, fields: [] };

// ─── Palette ──────────────────────────────────────────────────────────────────
const paletteBlocks = computed(() =>
  props.blockMeta.filter(m => !blocks.value.some(b => b.type === m.type))
);

function addBlock(meta) {
  const cfg = {};
  for (const f of (meta.fields ?? [])) {
    cfg[f.key] = f.type === 'toggle' ? true : f.type === 'color' ? '#1E3A5F' : '';
  }
  blocks.value.push({ id: meta.type + '_' + Date.now(), type: meta.type, enabled: true, config: cfg });
  selected.value = blocks.value.length - 1;
}

function removeBlock(index) {
  if (selected.value === index) selected.value = null;
  else if (selected.value !== null && selected.value > index) selected.value--;
  blocks.value.splice(index, 1);
}

// ─── Save ─────────────────────────────────────────────────────────────────────
function save() {
  const method = isEdit.value ? 'put' : 'post';
  const url    = isEdit.value
    ? route('erp.settings.document-templates.update', props.template.id)
    : route('erp.settings.document-templates.store');

  form.transform(d => ({ ...d, type: props.type }))[method](url, {
    preserveScroll: true,
    onSuccess: () => { flash.value = 'Tersimpan!'; setTimeout(() => { flash.value = ''; }, 2500); },
  });
}

// ─── Preview ──────────────────────────────────────────────────────────────────
async function loadPreview() {
  previewLoading.value = true;
  try {
    const raw   = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/)?.[1];
    const token = raw ? decodeURIComponent(raw) : '';
    const res   = await fetch(route('erp.settings.document-templates.preview'), {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', Accept: 'text/html', 'X-XSRF-TOKEN': token },
      body: JSON.stringify({ type: props.type, blocks: blocks.value, settings: form.settings, page_size: pageSize.value }),
    });
    const html = await res.text();
    await nextTick();
    if (iframeRef.value) {
      const doc = iframeRef.value.contentDocument ?? iframeRef.value.contentWindow?.document;
      if (doc) { doc.open(); doc.write(html); doc.close(); }
    }
  } catch {
    // silent fail — user can click refresh
  } finally {
    previewLoading.value = false;
  }
}

function switchTab(t) {
  tab.value = t;
  if (t === 'preview') loadPreview();
}

// iframe dimensions (unscaled)
const iframeW = computed(() => currentPageSize.value.w + 48); // +padding
const iframeH = computed(() => currentPageSize.value.h + 48);
// scaled container size (what the DOM actually takes up)
const scaledW = computed(() => Math.round(iframeW.value * zoom.value));
const scaledH = computed(() => Math.round(iframeH.value * zoom.value));

// ─── Block label ──────────────────────────────────────────────────────────────
const blockSubtexts = {
  header:           () => 'Logo, judul & info perusahaan',
  doc_meta:         () => 'Nomor, tanggal, status',
  client_info:      (b) => `Customer — "${b.config?.label ?? 'Customer'}"`,
  items_table:      () => 'Tabel item: qty, harga, subtotal',
  totals:           () => 'Subtotal, diskon, total',
  payment_terms:    () => 'Termin pembayaran',
  notes:            (b) => b.config?.text ? `"${b.config.text.slice(0, 40)}"` : 'Catatan teks',
  signature:        (b) => `TTD — "${b.config?.name_placeholder ?? ''}"`,
  footer:           () => 'Footer & tanggal cetak',
  store_header:     () => 'Toko: nama, alamat, telp',
  transaction_info: () => 'No. transaksi, kasir',
  items:            () => 'Item transaksi POS',
  payment_info:     () => 'Metode bayar, kembalian',
  footer_message:   (b) => b.config?.text ? `"${b.config.text}"` : 'Pesan terima kasih',
};
const blockSubtext = (b) => (blockSubtexts[b.type] ?? (() => ''))(b);
</script>

<template>
  <Head :title="`Builder — ${docTitle}`" />
  <AppLayout :fullWidth="true">
    <!-- Total height: full viewport minus app topbar (~56px) -->
    <div class="flex flex-col" style="height: calc(100vh - 56px);">

      <!-- ─ Compact header bar ──────────────────────────────────────────── -->
      <div class="flex items-center gap-2 border-b border-base-200 bg-base-100 px-3 py-2 shrink-0">
        <Link
          :href="route('erp.settings.document-templates.index')"
          class="btn btn-ghost btn-xs btn-square shrink-0"
        >
          <ArrowLeftIcon class="h-3.5 w-3.5" />
        </Link>

        <div class="h-4 w-px bg-base-300 shrink-0" />

        <!-- Name + type -->
        <div class="flex items-center gap-2 flex-1 min-w-0">
          <input
            v-model="form.name"
            class="bg-transparent text-sm font-semibold outline-none border-b border-transparent focus:border-primary transition-colors min-w-0 w-56"
            placeholder="Nama template…"
          />
          <span class="badge badge-outline badge-sm shrink-0 text-xs">{{ typeLabels[type] }}</span>
        </div>

        <!-- Page size -->
        <div class="flex items-center gap-1.5 shrink-0">
          <span class="text-xs text-base-content/50">Ukuran:</span>
          <select v-model="pageSize" class="select select-xs select-bordered w-24">
            <option v-for="ps in PAGE_SIZES" :key="ps.key" :value="ps.key">{{ ps.label }}</option>
          </select>
        </div>

        <div class="h-4 w-px bg-base-300 shrink-0" />

        <!-- Save -->
        <span v-if="flash" class="flex items-center gap-1 text-xs text-success font-medium shrink-0">
          <CheckIcon class="h-3.5 w-3.5" /> {{ flash }}
        </span>
        <button
          class="btn btn-primary btn-xs gap-1 shrink-0"
          :disabled="form.processing"
          @click="save"
        >
          <CheckIcon class="h-3.5 w-3.5" />
          {{ isEdit ? 'Simpan' : 'Buat Template' }}
        </button>
      </div>

      <!-- ─ Three-panel body ────────────────────────────────────────────── -->
      <div class="flex flex-1 overflow-hidden">

        <!-- Left: Palette — tambah blok -->
        <aside class="w-44 shrink-0 border-r border-base-200 bg-base-200/40 flex flex-col">
          <p class="px-3 py-2 text-[10px] font-bold uppercase tracking-wider text-base-content/50 border-b border-base-200 bg-base-100">
            Tambah Blok
          </p>
          <div class="flex-1 overflow-y-auto p-2 space-y-1.5">
            <template v-if="paletteBlocks.length">
              <button
                v-for="meta in paletteBlocks"
                :key="meta.type"
                class="flex w-full items-center gap-2 rounded-lg border border-base-200 bg-base-100 px-2.5 py-2 text-left text-xs shadow-sm hover:border-primary/40 hover:bg-primary/5 hover:text-primary transition-colors"
                @click="addBlock(meta)"
              >
                <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded bg-base-200/80 text-base-content/50">
                  <PlusIcon class="h-3 w-3" />
                </span>
                <span class="truncate font-medium leading-tight">{{ meta.label }}</span>
              </button>
            </template>
            <p v-else class="rounded-lg border border-dashed border-base-300 bg-base-100/60 text-[10px] text-center text-base-content/40 py-6 px-2">
              Semua blok sudah ditambahkan
            </p>
          </div>
        </aside>

        <!-- Center: Canvas / Preview -->
        <main class="flex-1 overflow-hidden flex flex-col bg-base-200/30 min-w-0">

          <!-- Tab + zoom bar -->
          <div class="flex items-center border-b border-base-200 bg-base-100 px-3 shrink-0 gap-1">
            <button
              class="flex items-center gap-1 px-3 py-2 text-xs font-medium border-b-2 transition-colors"
              :class="tab === 'canvas' ? 'border-primary text-primary' : 'border-transparent text-base-content/55 hover:text-base-content'"
              @click="switchTab('canvas')"
            >
              <Squares2X2Icon class="h-3 w-3" /> Blok
            </button>
            <button
              class="flex items-center gap-1 px-3 py-2 text-xs font-medium border-b-2 transition-colors"
              :class="tab === 'preview' ? 'border-primary text-primary' : 'border-transparent text-base-content/55 hover:text-base-content'"
              @click="switchTab('preview')"
            >
              <EyeIcon class="h-3 w-3" /> Pratinjau
            </button>

            <!-- Zoom controls (preview only) -->
            <template v-if="tab === 'preview'">
              <div class="ml-auto flex items-center gap-1">
                <button class="btn btn-ghost btn-xs btn-square" :disabled="zoom <= ZOOM_LEVELS[0]" @click="zoomOut">
                  <MagnifyingGlassMinusIcon class="h-3.5 w-3.5" />
                </button>
                <span class="text-xs w-10 text-center font-mono">{{ zoomPercent }}</span>
                <button class="btn btn-ghost btn-xs btn-square" :disabled="zoom >= ZOOM_LEVELS[ZOOM_LEVELS.length-1]" @click="zoomIn">
                  <MagnifyingGlassPlusIcon class="h-3.5 w-3.5" />
                </button>
                <div class="h-3 w-px bg-base-300 mx-1" />
                <button
                  class="btn btn-ghost btn-xs gap-1"
                  :disabled="previewLoading"
                  @click="loadPreview"
                >
                  <ArrowPathIcon class="h-3 w-3" :class="previewLoading ? 'animate-spin' : ''" />
                  Refresh
                </button>
              </div>
            </template>
          </div>

          <!-- Canvas tab -->
          <div v-show="tab === 'canvas'" class="flex-1 overflow-y-auto p-3">
            <div class="max-w-lg mx-auto">
              <draggable
                v-model="blocks"
                item-key="id"
                handle=".drag-handle"
                ghost-class="opacity-25"
                animation="160"
                class="space-y-1.5"
              >
                <template #item="{ element: block, index }">
                  <div
                    class="flex items-center gap-2 rounded-lg border bg-base-100 px-2.5 py-2 cursor-pointer transition-all group"
                    :class="[
                      selected === index
                        ? 'border-primary ring-1 ring-primary/30'
                        : 'border-base-200 hover:border-base-300',
                      !block.enabled ? 'opacity-40' : '',
                    ]"
                    @click="selected = selected === index ? null : index"
                  >
                    <!-- Drag handle -->
                    <Bars3Icon
                      class="drag-handle h-3.5 w-3.5 text-base-content/20 cursor-grab active:cursor-grabbing shrink-0 group-hover:text-base-content/40"
                      @click.stop
                    />
                    <!-- Label -->
                    <div class="flex-1 min-w-0">
                      <span class="text-xs font-medium leading-none">{{ metaFor(block.type).label }}</span>
                      <span class="text-[10px] text-base-content/40 ml-1.5 truncate">{{ blockSubtext(block) }}</span>
                    </div>
                    <!-- Controls -->
                    <div class="flex items-center gap-1 shrink-0" @click.stop>
                      <input
                        type="checkbox"
                        :checked="block.enabled"
                        class="toggle toggle-xs toggle-primary"
                        @change="block.enabled = !block.enabled"
                      />
                      <button class="btn btn-ghost btn-xs btn-square text-error opacity-0 group-hover:opacity-100 transition-opacity" @click="removeBlock(index)">
                        <TrashIcon class="h-3 w-3" />
                      </button>
                    </div>
                  </div>
                </template>
              </draggable>

              <div
                v-if="!blocks.length"
                class="mt-3 rounded-lg border-2 border-dashed border-base-300 py-12 text-center text-xs text-base-content/40"
              >
                Klik blok di panel kiri untuk menambahkan
              </div>
            </div>
          </div>

          <!-- Preview tab -->
          <div v-show="tab === 'preview'" class="flex-1 overflow-auto relative bg-slate-200">
            <!-- Loading overlay -->
            <div v-if="previewLoading" class="absolute inset-0 flex items-center justify-center bg-slate-200/80 z-10">
              <span class="loading loading-spinner loading-md text-primary" />
            </div>

            <!-- Scaled page wrapper — centers the scaled page -->
            <div class="flex justify-center py-4 px-4 min-h-full">
              <div
                class="relative shrink-0"
                :style="{ width: scaledW + 'px', height: scaledH + 'px' }"
              >
                <iframe
                  ref="iframeRef"
                  class="absolute top-0 left-0 border-0 origin-top-left"
                  :style="{
                    width: iframeW + 'px',
                    height: iframeH + 'px',
                    transform: `scale(${zoom})`,
                    boxShadow: '0 4px 24px rgba(0,0,0,.18)',
                  }"
                  title="Preview"
                  sandbox="allow-same-origin"
                />
              </div>
            </div>
          </div>
        </main>

        <!-- Right: Properties panel (compact) -->
        <aside class="w-60 shrink-0 border-l border-base-200 bg-base-100 flex flex-col">
          <div class="flex items-center justify-between gap-1 px-3 py-1.5 border-b border-base-200 shrink-0">
            <p class="text-[10px] font-bold uppercase tracking-wider text-base-content/40">
              {{ selected !== null ? metaFor(blocks[selected]?.type).label : 'Properti' }}
            </p>
            <button v-if="selected !== null" class="btn btn-ghost btn-xs btn-square" @click="selected = null">
              <XMarkIcon class="h-3 w-3" />
            </button>
          </div>

          <div class="flex-1 overflow-y-auto">

            <!-- Empty state -->
            <div
              v-if="selected === null"
              class="flex flex-col items-center justify-center h-full gap-2 text-base-content/30 text-[10px] text-center px-4"
            >
              <EyeIcon class="h-8 w-8 opacity-25" />
              Pilih blok untuk mengedit
            </div>

            <!-- Properties -->
            <div v-else class="p-3 space-y-3">

              <!-- Enabled -->
              <label class="flex items-center justify-between gap-2 py-0.5">
                <span class="text-xs text-base-content/70">Tampilkan</span>
                <input
                  type="checkbox"
                  :checked="blocks[selected]?.enabled"
                  class="toggle toggle-xs toggle-primary"
                  @change="blocks[selected].enabled = !blocks[selected].enabled"
                />
              </label>

              <div v-if="metaFor(blocks[selected]?.type).fields?.length" class="divider my-0 text-[10px]" />

              <!-- Dynamic fields -->
              <template v-for="field in metaFor(blocks[selected]?.type).fields" :key="field.key">

                <label v-if="field.type === 'toggle'" class="flex items-center justify-between gap-2">
                  <span class="text-xs text-base-content/70 leading-tight">{{ field.label }}</span>
                  <input
                    type="checkbox"
                    :checked="blocks[selected].config[field.key]"
                    class="toggle toggle-xs toggle-primary shrink-0"
                    @change="blocks[selected].config[field.key] = !blocks[selected].config[field.key]"
                  />
                </label>

                <div v-else-if="field.type === 'text'" class="space-y-0.5">
                  <label class="text-[10px] font-medium text-base-content/50 uppercase tracking-wide">{{ field.label }}</label>
                  <input
                    v-model="blocks[selected].config[field.key]"
                    class="input input-xs input-bordered w-full"
                    :placeholder="field.label"
                  />
                </div>

                <div v-else-if="field.type === 'textarea'" class="space-y-0.5">
                  <label class="text-[10px] font-medium text-base-content/50 uppercase tracking-wide">{{ field.label }}</label>
                  <textarea
                    v-model="blocks[selected].config[field.key]"
                    class="textarea textarea-bordered textarea-xs w-full"
                    rows="3"
                    :placeholder="field.label"
                  />
                </div>

                <div v-else-if="field.type === 'color'" class="space-y-0.5">
                  <label class="text-[10px] font-medium text-base-content/50 uppercase tracking-wide">{{ field.label }}</label>
                  <div class="flex items-center gap-1.5">
                    <input
                      v-model="blocks[selected].config[field.key]"
                      type="color"
                      class="h-7 w-7 cursor-pointer rounded border border-base-300 p-0.5 shrink-0"
                    />
                    <input
                      v-model="blocks[selected].config[field.key]"
                      class="input input-xs input-bordered flex-1 font-mono"
                      placeholder="#1E3A5F"
                    />
                  </div>
                  <!-- Preset swatches -->
                  <div class="flex gap-1 pt-0.5">
                    <button
                      v-for="c in ['#1E3A5F','#1d4ed8','#15803d','#b91c1c','#374151','#7c3aed','#0369a1']"
                      :key="c"
                      class="h-4 w-4 rounded border border-base-300 shrink-0 hover:scale-110 transition-transform"
                      :style="{ background: c }"
                      :title="c"
                      @click="blocks[selected].config[field.key] = c"
                    />
                  </div>
                </div>

              </template>

              <div
                v-if="!metaFor(blocks[selected]?.type).fields?.length"
                class="text-[10px] text-center text-base-content/35 py-3"
              >
                Tidak ada properti untuk blok ini
              </div>
            </div>
          </div>
        </aside>

      </div>
    </div>
  </AppLayout>
</template>
