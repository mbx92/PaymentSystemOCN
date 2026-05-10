<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import LabelProfilesPanel from '@/Components/ERP/LabelProfilesPanel.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
  activeTab: String,
  printer: Object,
  thermalTemplateDefaults: Object,
  labelSmb: Object,
  labelLan: Object,
  labelProfiles: Array,
  serverIsWindows: Boolean,
});

const tabIds = ['thermal', 'label-smb', 'label-lan', 'label-profiles'];

const tab = ref(tabIds.includes(props.activeTab) ? props.activeTab : 'thermal');

watch(
  () => props.activeTab,
  (v) => {
    if (v && tabIds.includes(v)) {
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

const d = props.thermalTemplateDefaults ?? {};

const form = useForm({
  thermal_printer_enabled: props.printer?.thermal_printer_enabled ?? false,
  thermal_printer_host: props.printer?.thermal_printer_host ?? '',
  thermal_printer_port: props.printer?.thermal_printer_port ?? 9100,
  thermal_paper_width: props.printer?.thermal_paper_width === '58' ? '58' : '80',
  thermal_pos_margin_left_mm: Number(props.printer?.thermal_pos_margin_left_mm ?? 2) || 0,
  thermal_pos_header_align: props.printer?.thermal_pos_header_align ?? 'center',
  thermal_pos_item_align: props.printer?.thermal_pos_item_align ?? 'left',
  thermal_pos_footer_align: props.printer?.thermal_pos_footer_align ?? 'right',
  thermal_pos_section_gap: Number(props.printer?.thermal_pos_section_gap ?? 0) || 0,
  thermal_pos_header_emphasis: props.printer?.thermal_pos_header_emphasis !== false,
  thermal_pos_header_template: props.printer?.thermal_pos_header_template ?? d.header ?? '',
  thermal_pos_item_line_template: props.printer?.thermal_pos_item_line_template ?? d.item_line ?? '',
  thermal_pos_footer_template: props.printer?.thermal_pos_footer_template ?? d.footer ?? '',
});

const testForm = useForm({
  thermal_printer_host: props.printer?.thermal_printer_host ?? '',
  thermal_printer_port: props.printer?.thermal_printer_port ?? 9100,
  thermal_paper_width: props.printer?.thermal_paper_width === '58' ? '58' : '80',
});

const posTestForm = useForm({
  thermal_printer_host: props.printer?.thermal_printer_host ?? '',
  thermal_printer_port: props.printer?.thermal_printer_port ?? 9100,
  thermal_paper_width: props.printer?.thermal_paper_width === '58' ? '58' : '80',
});

/** IP sudah pernah disimpan ke DB (boleh test print) */
const hasSavedPrinter = computed(() => String(props.printer?.thermal_printer_host ?? '').trim().length > 0);

/** Kolom teks Font A mengikuti lebar kertas (ESC/POS umum) */
const thermalCols = computed(() => (form.thermal_paper_width === '58' ? 32 : 48));

const submit = () => {
  form.post(route('erp.admin.thermal-printer.update'), { preserveScroll: true });
};

const testPrint = () => {
  if (!hasSavedPrinter.value) return;
  testForm.thermal_printer_host = props.printer.thermal_printer_host;
  testForm.thermal_printer_port = props.printer.thermal_printer_port ?? 9100;
  testForm.thermal_paper_width = props.printer.thermal_paper_width === '58' ? '58' : '80';
  testForm.post(route('erp.admin.thermal-printer.test'), { preserveScroll: true });
};

const testPosReceipt = () => {
  if (!hasSavedPrinter.value) return;
  posTestForm.thermal_printer_host = props.printer.thermal_printer_host;
  posTestForm.thermal_printer_port = props.printer.thermal_printer_port ?? 9100;
  posTestForm.thermal_paper_width = props.printer.thermal_paper_width === '58' ? '58' : '80';
  posTestForm.post(route('erp.admin.thermal-printer.test-pos-receipt'), { preserveScroll: true });
};

const applyDefaultTemplates = () => {
  form.thermal_pos_header_template = d.header || '';
  form.thermal_pos_item_line_template = d.item_line || '';
  form.thermal_pos_footer_template = d.footer || '';
};

const previewData = ref(null);
const previewLoading = ref(false);
const previewError = ref('');

async function fetchThermalPreview() {
  previewLoading.value = true;
  previewError.value = '';
  try {
    const raw = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/)?.[1];
    const token = raw ? decodeURIComponent(raw) : '';
    const res = await fetch(route('erp.admin.thermal-printer.preview'), {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-XSRF-TOKEN': token,
      },
      body: JSON.stringify({
        thermal_paper_width: form.thermal_paper_width,
        thermal_pos_header_template: form.thermal_pos_header_template,
        thermal_pos_item_line_template: form.thermal_pos_item_line_template,
        thermal_pos_footer_template: form.thermal_pos_footer_template,
        thermal_pos_margin_left_mm: form.thermal_pos_margin_left_mm,
        thermal_pos_header_align: form.thermal_pos_header_align,
        thermal_pos_item_align: form.thermal_pos_item_align,
        thermal_pos_footer_align: form.thermal_pos_footer_align,
        thermal_pos_section_gap: form.thermal_pos_section_gap,
        thermal_pos_header_emphasis: form.thermal_pos_header_emphasis,
      }),
    });
    const body = await res.json().catch(() => ({}));
    if (!res.ok) {
      const msg = body?.message
        || (body?.errors && Object.values(body.errors).flat().join(' '))
        || `HTTP ${res.status}`;
      previewError.value = msg;
      previewData.value = null;
      return;
    }
    previewData.value = body;
  } catch (e) {
    previewError.value = e?.message || 'Gagal memuat pratinjau';
    previewData.value = null;
  } finally {
    previewLoading.value = false;
  }
}

const smbForm = useForm({
  label_smb_enabled: props.labelSmb?.label_smb_enabled ?? false,
  label_smb_unc: props.labelSmb?.label_smb_unc ?? '',
  label_smb_profile_id: props.labelSmb?.label_smb_profile_id ?? '',
});

const smbTestForm = useForm({
  label_smb_unc: props.labelSmb?.label_smb_unc ?? '',
  label_smb_profile_id: props.labelSmb?.label_smb_profile_id ?? '',
});

const hasSavedUnc = computed(() => String(props.labelSmb?.label_smb_unc ?? '').trim().length > 0);

const hasSavedProfile = computed(() => {
  const id = props.labelSmb?.label_smb_profile_id;
  return id != null && id !== '' && Number(id) > 0;
});

const canTestSmb = computed(() => hasSavedUnc.value && hasSavedProfile.value && props.serverIsWindows);

const selectedProfileLabel = computed(() => {
  const id = props.labelSmb?.label_smb_profile_id;
  if (id == null || id === '') return null;
  const numId = Number(id);
  const p = (props.labelProfiles ?? []).find((x) => Number(x.id) === numId);
  return p ? `${p.name} (${String(p.width_mm)}×${String(p.height_mm)} mm, ${p.dpi} dpi, ${String(p.protocol).toUpperCase()})` : null;
});

const submitSmb = () => {
  smbForm.post(route('erp.admin.label-printer-smb.update'), { preserveScroll: true });
};

const testPrintSmb = () => {
  if (!canTestSmb.value) return;
  smbTestForm.label_smb_unc = props.labelSmb.label_smb_unc;
  smbTestForm.label_smb_profile_id = props.labelSmb.label_smb_profile_id;
  smbTestForm.post(route('erp.admin.label-printer-smb.test'), { preserveScroll: true });
};

const lanForm = useForm({
  label_lan_enabled: props.labelLan?.label_lan_enabled ?? false,
  label_lan_host: props.labelLan?.label_lan_host ?? '',
  label_lan_port: props.labelLan?.label_lan_port ?? 9100,
  label_lan_profile_id: props.labelLan?.label_lan_profile_id ?? '',
});

const lanTestForm = useForm({});

const hasSavedHost = computed(() => String(props.labelLan?.label_lan_host ?? '').trim().length > 0);

const hasAnyLanProfile = computed(() => {
  const lan = props.labelLan?.label_lan_profile_id;
  const smb = props.labelLan?.label_smb_profile_id;
  return (lan != null && lan !== '' && Number(lan) > 0) || (smb != null && smb !== '' && Number(smb) > 0);
});

const canTestLan = computed(() =>
  Boolean(props.labelLan?.label_lan_enabled) &&
  hasSavedHost.value &&
  hasAnyLanProfile.value,
);

const submitLan = () => {
  lanForm.post(route('erp.admin.label-printer-lan.update'), { preserveScroll: true });
};

const testPrintLan = () => {
  if (!canTestLan.value) return;
  lanTestForm.post(route('erp.admin.label-printer-lan.test'), { preserveScroll: true });
};
</script>

<template>
  <Head title="Administration - Printer & label" />
  <AppLayout>
    <div class="space-y-5">
      <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Administration Workspace</p>
        <div class="mt-2 flex items-center justify-between gap-3">
          <div>
            <h1 class="text-3xl font-bold tracking-tight">Printer &amp; label</h1>
            <p class="mt-2 text-sm text-base-content/70">
              Thermal LAN untuk struk POS, label Windows (SMB), label jaringan (TSPL), dan master profil label (ZPL/EPL). Pilih tab di bawah.
            </p>
          </div>
          <Link class="btn btn-ghost btn-sm" :href="route('erp.administration')">Back</Link>
        </div>
      </div>

      <div role="tablist" class="tabs tabs-boxed flex-wrap gap-1 bg-base-200/60 p-1 rounded-xl">
        <button type="button" role="tab" class="tab" :class="{ 'tab-active': tab === 'thermal' }" @click="selectTab('thermal')">
          Thermal struk
        </button>
        <button type="button" role="tab" class="tab" :class="{ 'tab-active': tab === 'label-smb' }" @click="selectTab('label-smb')">
          Label Windows (SMB)
        </button>
        <button type="button" role="tab" class="tab" :class="{ 'tab-active': tab === 'label-lan' }" @click="selectTab('label-lan')">
          Label LAN (TSPL)
        </button>
        <button type="button" role="tab" class="tab" :class="{ 'tab-active': tab === 'label-profiles' }" @click="selectTab('label-profiles')">
          Profil label
        </button>
      </div>

      <div v-show="tab === 'thermal'" class="space-y-5">
      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Koneksi jaringan</h2>
          <p class="ocn-panel__desc">Data dikirim sebagai ESC/POS mentah lewat TCP dari server PHP (bukan dari browser pengguna).</p>
        </div>
        <div class="card-body space-y-4">
          <label class="label cursor-pointer justify-start gap-3 border border-base-200 rounded-xl p-3">
            <input v-model="form.thermal_printer_enabled" type="checkbox" class="checkbox checkbox-primary">
            <div>
              <span class="label-text font-semibold">Aktifkan printer thermal LAN</span>
              <p class="text-xs text-base-content/60">Jika mati, modul lain tidak akan mengirim ke printer (nanti).</p>
            </div>
          </label>

          <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-2">
              <label class="label p-0"><span class="label-text">Alamat IP printer</span></label>
              <input
                v-model="form.thermal_printer_host"
                type="text"
                class="input input-bordered w-full font-mono"
                placeholder="192.168.1.50"
                autocomplete="off"
              >
              <p v-if="form.errors.thermal_printer_host" class="text-xs text-error">{{ form.errors.thermal_printer_host }}</p>
            </div>
            <div class="space-y-2">
              <label class="label p-0"><span class="label-text">Port RAW (JetDirect)</span></label>
              <input
                v-model.number="form.thermal_printer_port"
                type="number"
                min="1"
                max="65535"
                class="input input-bordered w-full font-mono"
              >
              <p v-if="form.errors.thermal_printer_port" class="text-xs text-error">{{ form.errors.thermal_printer_port }}</p>
            </div>
          </div>

          <div class="space-y-2">
            <label class="label p-0"><span class="label-text font-medium">Lebar kertas thermal</span></label>
            <p class="text-xs text-base-content/60">
              Mengatur lebar kolom teks struk (Font A): <strong>58 mm ≈ 32 kolom</strong>, <strong>80 mm ≈ 48 kolom</strong>. Margin kiri (mm) di template dihitung dari lebar nominal ini agar sejajar dengan area cetak kertas.
            </p>
            <div class="flex flex-wrap gap-4 pt-1">
              <label class="label cursor-pointer justify-start gap-2 border border-base-200 rounded-xl px-4 py-3 has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                <input v-model="form.thermal_paper_width" type="radio" name="thermal_paper_width" class="radio radio-primary" value="58">
                <span class="label-text">58 mm</span>
              </label>
              <label class="label cursor-pointer justify-start gap-2 border border-base-200 rounded-xl px-4 py-3 has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                <input v-model="form.thermal_paper_width" type="radio" name="thermal_paper_width" class="radio radio-primary" value="80">
                <span class="label-text">80 mm</span>
              </label>
            </div>
            <p v-if="form.errors.thermal_paper_width" class="text-xs text-error">{{ form.errors.thermal_paper_width }}</p>
          </div>

          <div class="rounded-xl border border-base-200 bg-base-200/40 p-4 text-sm text-base-content/80">
            <p class="font-medium text-base-content">Uji cetak koneksi</p>
            <p class="mt-1 text-xs">
              Menggunakan <strong>IP, port, dan lebar kertas yang sudah disimpan</strong>. Simpan pengaturan dulu agar tombol aktif.
            </p>
            <div class="mt-3 flex flex-wrap gap-2">
              <button
                type="button"
                class="btn min-w-[9rem]"
                :class="hasSavedPrinter ? 'btn-primary text-white' : 'btn-outline border-base-300 text-base-content/50'"
                :disabled="!hasSavedPrinter || testForm.processing || form.processing"
                @click="testPrint"
              >
                {{ testForm.processing ? 'Mengirim…' : 'Test koneksi' }}
              </button>
            </div>
            <p v-if="testForm.errors.thermal_printer_host" class="mt-2 text-xs text-error">{{ testForm.errors.thermal_printer_host }}</p>
            <p v-if="testForm.errors.thermal_printer_port" class="mt-2 text-xs text-error">{{ testForm.errors.thermal_printer_port }}</p>
            <p v-if="testForm.errors.thermal_paper_width" class="mt-2 text-xs text-error">{{ testForm.errors.thermal_paper_width }}</p>
          </div>

          <div class="flex justify-end gap-2">
            <button class="btn btn-primary" :disabled="form.processing" @click="submit">
              {{ form.processing ? 'Menyimpan…' : 'Simpan pengaturan' }}
            </button>
          </div>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Template struk POS</h2>
          <p class="ocn-panel__desc">
            Tiga blok teks dengan <strong>placeholder</strong>. Baris item diulang per produk di keranjang. Kosongkan blok untuk memakai bawaan sistem. Perataan dan margin diterapkan saat cetak ESC/POS.
            Pada header, <strong>baris kosong pertama</strong> memisahkan blok judul (mengikuti rata header) dan blok label seperti nomor transaksi (selalu kiri).
            Placeholder item: <code v-pre>{{item_padded_line}}</code> (satu baris qty × nama … total),
            <code v-pre>{{uom}}</code> / <code v-pre>{{satuan}}</code> (satuan produk, sama dengan master produk),
            plus <code v-pre>{{qty}}</code>, <code v-pre>{{name}}</code>, <code v-pre>{{sku}}</code>, <code v-pre>{{unit_price}}</code>, <code v-pre>{{line_total}}</code>.
            Header mendukung <code v-pre>{{cashier}}</code>.
          </p>
        </div>
        <div class="card-body space-y-4">
          <div class="rounded-xl border border-base-200 bg-base-100/80 p-4 space-y-4">
            <div>
              <p class="text-sm font-semibold text-base-content">Layout struk</p>
              <p class="mt-1 text-xs text-base-content/60">
                Lebar kertas saat ini: <strong>{{ form.thermal_paper_width }} mm</strong> → sekitar <strong>{{ thermalCols }} kolom</strong> teks per baris.
              </p>
            </div>
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
              <div class="space-y-2">
                <label class="label p-0"><span class="label-text font-medium">Margin kiri (mm)</span></label>
                <input
                  v-model.number="form.thermal_pos_margin_left_mm"
                  type="number"
                  min="0"
                  max="25"
                  step="0.5"
                  class="input input-bordered w-full font-mono"
                >
                <p class="text-xs text-base-content/50">Dikonversi ke spasi di kiri baris sesuai lebar kertas.</p>
                <p v-if="form.errors.thermal_pos_margin_left_mm" class="text-xs text-error">{{ form.errors.thermal_pos_margin_left_mm }}</p>
              </div>
              <div class="space-y-2">
                <label class="label p-0"><span class="label-text font-medium">Jarak antar blok</span></label>
                <select v-model.number="form.thermal_pos_section_gap" class="select select-bordered w-full">
                  <option :value="0">Tanpa baris kosong</option>
                  <option :value="1">1 baris</option>
                  <option :value="2">2 baris</option>
                  <option :value="3">3 baris</option>
                </select>
                <p class="text-xs text-base-content/50">Antara header, garis, item, dan footer.</p>
                <p v-if="form.errors.thermal_pos_section_gap" class="text-xs text-error">{{ form.errors.thermal_pos_section_gap }}</p>
              </div>
              <div class="space-y-2 md:col-span-2 lg:col-span-1">
                <label class="label cursor-pointer justify-start gap-3 rounded-xl border border-base-200 p-3">
                  <input v-model="form.thermal_pos_header_emphasis" type="checkbox" class="checkbox checkbox-primary checkbox-sm">
                  <span class="label-text text-sm">Baris pertama header <strong>tinggi ganda</strong> (penekanan)</span>
                </label>
                <p v-if="form.errors.thermal_pos_header_emphasis" class="text-xs text-error">{{ form.errors.thermal_pos_header_emphasis }}</p>
              </div>
            </div>
            <div class="grid gap-4 md:grid-cols-3">
              <div class="space-y-2">
                <label class="label p-0"><span class="label-text font-medium">Rata header</span></label>
                <select v-model="form.thermal_pos_header_align" class="select select-bordered w-full">
                  <option value="left">Kiri</option>
                  <option value="center">Tengah</option>
                  <option value="right">Kanan</option>
                </select>
                <p v-if="form.errors.thermal_pos_header_align" class="text-xs text-error">{{ form.errors.thermal_pos_header_align }}</p>
              </div>
              <div class="space-y-2">
                <label class="label p-0"><span class="label-text font-medium">Rata baris item</span></label>
                <select v-model="form.thermal_pos_item_align" class="select select-bordered w-full">
                  <option value="left">Kiri</option>
                  <option value="center">Tengah</option>
                  <option value="right">Kanan</option>
                </select>
                <p v-if="form.errors.thermal_pos_item_align" class="text-xs text-error">{{ form.errors.thermal_pos_item_align }}</p>
              </div>
              <div class="space-y-2">
                <label class="label p-0"><span class="label-text font-medium">Rata footer</span></label>
                <select v-model="form.thermal_pos_footer_align" class="select select-bordered w-full">
                  <option value="left">Kiri</option>
                  <option value="center">Tengah</option>
                  <option value="right">Kanan</option>
                </select>
                <p v-if="form.errors.thermal_pos_footer_align" class="text-xs text-error">{{ form.errors.thermal_pos_footer_align }}</p>
                <p class="text-xs text-base-content/50">
                  Jika memakai placeholder <code class="rounded bg-base-200 px-0.5">footer_row_*</code> (lihat kotak bantuan di bawah), pilih rata <strong>Kiri</strong> agar jarak kolom tidak diubah lagi saat cetak.
                </p>
              </div>
            </div>
          </div>

          <div v-pre class="rounded-xl border border-base-200 bg-base-100 p-3 text-xs text-base-content/80 space-y-1">
            <p class="font-semibold text-base-content">Placeholder header &amp; footer</p>
            <p>
              <code class="rounded bg-base-200 px-1">{{app_name}}</code>
              <code class="rounded bg-base-200 px-1">{{transaction_number}}</code>
              <code class="rounded bg-base-200 px-1">{{date}}</code>
              <code class="rounded bg-base-200 px-1">{{time}}</code>
              <code class="rounded bg-base-200 px-1">{{payment_method}}</code>
            </p>
            <p>
              <code class="rounded bg-base-200 px-1">{{gross_total}}</code>
              <code class="rounded bg-base-200 px-1">{{discount_total}}</code>
              <code class="rounded bg-base-200 px-1">{{grand_total}}</code>
              <code class="rounded bg-base-200 px-1">{{cash_paid}}</code>
              <code class="rounded bg-base-200 px-1">{{change}}</code>
            </p>
            <p class="pt-1 text-base-content/90">
              Untuk kolom nominal rapi (label kiri, <strong>Rp</strong> dan angka rata kanan), pakai satu placeholder per baris:
            </p>
            <p>
              <code class="rounded bg-base-200 px-1">{{footer_row_subtotal}}</code>
              <code class="rounded bg-base-200 px-1">{{footer_row_discount}}</code>
              <code class="rounded bg-base-200 px-1">{{footer_row_grand_total}}</code>
              <code class="rounded bg-base-200 px-1">{{footer_row_cash_paid}}</code>
              <code class="rounded bg-base-200 px-1">{{footer_row_change}}</code>
            </p>
            <p class="font-semibold text-base-content pt-2">Placeholder baris item (diulang)</p>
            <p>
              <code class="rounded bg-base-200 px-1">{{sku}}</code>
              <code class="rounded bg-base-200 px-1">{{name}}</code>
              <code class="rounded bg-base-200 px-1">{{qty}}</code>
              <code class="rounded bg-base-200 px-1">{{unit_price}}</code>
              <code class="rounded bg-base-200 px-1">{{line_total}}</code>
              <code class="rounded bg-base-200 px-1">{{discount_percent}}</code>
            </p>
          </div>

          <div class="space-y-2">
            <div class="flex flex-wrap items-center justify-between gap-2">
              <label class="label p-0"><span class="label-text font-medium">Header struk</span></label>
              <button type="button" class="btn btn-ghost btn-xs" @click="applyDefaultTemplates">Isi semua dengan default</button>
            </div>
            <textarea v-model="form.thermal_pos_header_template" class="textarea textarea-bordered w-full font-mono text-sm" rows="5" placeholder="Judul, alamat, no struk…" />
            <p v-if="form.errors.thermal_pos_header_template" class="text-xs text-error">{{ form.errors.thermal_pos_header_template }}</p>
          </div>

          <div class="space-y-2">
            <label class="label p-0"><span class="label-text font-medium">Format satu baris item</span></label>
            <textarea v-model="form.thermal_pos_item_line_template" class="textarea textarea-bordered w-full font-mono text-sm" rows="4" placeholder="Qty, nama, harga…" />
            <p v-if="form.errors.thermal_pos_item_line_template" class="text-xs text-error">{{ form.errors.thermal_pos_item_line_template }}</p>
          </div>

          <div class="space-y-2">
            <label class="label p-0"><span class="label-text font-medium">Footer struk</span></label>
            <textarea v-model="form.thermal_pos_footer_template" class="textarea textarea-bordered w-full font-mono text-sm" rows="5" placeholder="Total, bayar, kembali…" />
            <p v-if="form.errors.thermal_pos_footer_template" class="text-xs text-error">{{ form.errors.thermal_pos_footer_template }}</p>
          </div>

          <div class="rounded-xl border border-primary/25 bg-base-100 p-4 space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
              <div>
                <p class="font-medium text-base-content">Pratinjau struk (browser)</p>
                <p class="text-xs text-base-content/60 mt-0.5">
                  Menggunakan isian form di atas + data contoh POS. Lebar mengikuti pilihan kertas:
                  <strong>{{ thermalCols }} kolom</strong> ({{ form.thermal_paper_width }} mm), sama dengan Font A umum di printer thermal.
                </p>
              </div>
              <button type="button" class="btn btn-outline btn-sm" :disabled="previewLoading" @click="fetchThermalPreview">
                {{ previewLoading ? 'Memuat…' : 'Perbarui pratinjau' }}
              </button>
            </div>
            <p v-if="previewError" class="text-xs text-error">{{ previewError }}</p>
            <div
              v-if="previewData?.rows?.length"
              class="mx-auto rounded-lg border-2 border-dashed border-base-300 bg-[#f6f5f0] p-4 shadow-inner overflow-x-auto"
              :style="{ width: 'min(100%, calc(' + previewData.cols + 'ch + 2.5rem))' }"
            >
              <p class="text-[10px] uppercase tracking-wider text-base-content/50 mb-2 font-sans">
                Simulasi {{ previewData.paper_mm }} mm · monospace {{ previewData.cols }}ch
              </p>
              <div class="font-mono text-[11px] leading-[1.25] text-base-content tabular-nums">
                <template v-for="(row, ri) in previewData.rows" :key="ri">
                  <div
                    v-if="row.kind === 'text'"
                    :class="row.double ? 'text-sm font-bold leading-snug py-0.5' : ''"
                    class="whitespace-pre"
                  >
                    {{ row.text }}
                  </div>
                  <div v-else-if="row.kind === 'rule'" class="whitespace-pre text-base-content/55">{{ row.text }}</div>
                  <div v-else-if="row.kind === 'blank'" class="h-2" />
                </template>
              </div>
            </div>
            <p v-else class="text-xs text-base-content/50">
              Klik «Perbarui pratinjau» untuk melihat tampilan tanpa mengirim ke printer.
            </p>
          </div>

          <div class="rounded-xl border border-base-200 bg-base-200/40 p-4 text-sm text-base-content/80">
            <p class="font-medium text-base-content">Uji cetak struk POS (template)</p>
            <p class="mt-1 text-xs">
              Mengirim data <strong>contoh</strong> ke printer yang sudah disimpan, dengan template yang <strong>tersimpan di database</strong> (bukan teks di form yang belum disimpan). Simpan dulu bila mengubah template.
            </p>
            <div class="mt-3 flex flex-wrap gap-2">
              <button
                type="button"
                class="btn min-w-[10rem]"
                :class="hasSavedPrinter ? 'btn-primary text-white' : 'btn-outline border-base-300 text-base-content/50'"
                :disabled="!hasSavedPrinter || posTestForm.processing || form.processing"
                @click="testPosReceipt"
              >
                {{ posTestForm.processing ? 'Mengirim…' : 'Test struk POS' }}
              </button>
            </div>
            <p v-if="posTestForm.errors.thermal_printer_host" class="mt-2 text-xs text-error">{{ posTestForm.errors.thermal_printer_host }}</p>
            <p v-if="posTestForm.errors.thermal_paper_width" class="mt-2 text-xs text-error">{{ posTestForm.errors.thermal_paper_width }}</p>
          </div>

          <div class="flex justify-end gap-2">
            <button class="btn btn-primary" :disabled="form.processing" @click="submit">
              {{ form.processing ? 'Menyimpan…' : 'Simpan pengaturan' }}
            </button>
          </div>
        </div>
      </div>
      </div>

      <div v-show="tab === 'label-smb'" class="space-y-5">
        <div v-if="!serverIsWindows" role="alert" class="alert alert-warning text-sm">
          <span>Server PHP Anda bukan Windows: path UNC lewat <code class="mx-0.5 rounded bg-base-200 px-1">fopen</code> biasanya tidak didukung. Untuk cetak dari Linux gunakan tab <strong>Label LAN (TSPL)</strong>, atau jalankan PHP di Windows untuk SMB.</span>
        </div>

        <div class="rounded-xl border border-base-200 bg-base-100 p-4 text-sm">
          <p class="font-medium text-base-content">Ukuran kertas</p>
          <p class="mt-1 text-xs text-base-content/70">
            Buat satu profil per ukuran stok. Uji cetak memakai profil yang Anda pilih di bawah.
          </p>
          <button type="button" class="btn btn-outline btn-sm mt-3" @click="selectTab('label-profiles')">Kelola profil label</button>
        </div>

        <div class="ocn-panel">
          <div class="ocn-panel__head">
            <h2 class="ocn-panel__title">Printer share (UNC)</h2>
            <p class="ocn-panel__desc">Antrian printer harus menerima data RAW. Akun yang menjalankan PHP harus punya akses ke share tersebut.</p>
          </div>
          <div class="card-body space-y-4">
            <label class="label cursor-pointer justify-start gap-3 border border-base-200 rounded-xl p-3">
              <input v-model="smbForm.label_smb_enabled" type="checkbox" class="checkbox checkbox-primary">
              <div>
                <span class="label-text font-semibold">Aktifkan penggunaan label SMB</span>
                <p class="text-xs text-base-content/60">Flag untuk modul cetak label.</p>
              </div>
            </label>

            <div class="space-y-2">
              <label class="label p-0"><span class="label-text">Path UNC printer</span></label>
              <input
                v-model="smbForm.label_smb_unc"
                type="text"
                class="input input-bordered w-full font-mono text-sm"
                placeholder="smb://NAMA-PC/NamaPrinterShare"
                autocomplete="off"
              >
              <p class="text-xs text-base-content/60">Boleh memakai prefiks <code class="rounded bg-base-200 px-1">smb://</code>.</p>
              <p v-if="smbForm.errors.label_smb_unc" class="text-xs text-error">{{ smbForm.errors.label_smb_unc }}</p>
            </div>

            <div class="space-y-2">
              <label class="label p-0"><span class="label-text">Profil label</span></label>
              <select v-model="smbForm.label_smb_profile_id" class="select select-bordered w-full font-mono text-sm">
                <option value="" disabled>
                  — pilih profil —
                </option>
                <option v-for="p in labelProfiles" :key="p.id" :value="p.id">
                  {{ p.name }} — {{ p.width_mm }}×{{ p.height_mm }} mm, {{ p.dpi }} dpi, {{ String(p.protocol).toUpperCase() }}
                </option>
              </select>
              <p v-if="smbForm.errors.label_smb_profile_id" class="text-xs text-error">{{ smbForm.errors.label_smb_profile_id }}</p>
              <p v-if="!labelProfiles?.length" class="text-xs text-warning">
                Belum ada profil.
              </p>
            </div>

            <div class="rounded-xl border border-base-200 bg-base-200/40 p-4 text-sm text-base-content/80">
              <p class="font-medium text-base-content">Uji cetak</p>
              <p v-if="selectedProfileLabel" class="mt-2 text-xs text-base-content/70">
                Profil tersimpan: {{ selectedProfileLabel }}
              </p>
              <div class="mt-3 flex flex-wrap gap-2">
                <button
                  type="button"
                  class="btn min-w-[9rem]"
                  :class="canTestSmb ? 'btn-primary text-white' : 'btn-outline border-base-300 text-base-content/50'"
                  :disabled="!canTestSmb || smbTestForm.processing || smbForm.processing"
                  @click="testPrintSmb"
                >
                  {{ smbTestForm.processing ? 'Mengirim…' : 'Test cetak' }}
                </button>
              </div>
              <p v-if="smbTestForm.errors.label_smb_unc" class="mt-2 text-xs text-error">{{ smbTestForm.errors.label_smb_unc }}</p>
              <p v-if="smbTestForm.errors.label_smb_profile_id" class="mt-2 text-xs text-error">{{ smbTestForm.errors.label_smb_profile_id }}</p>
            </div>

            <div class="flex justify-end gap-2">
              <button class="btn btn-primary" :disabled="smbForm.processing" @click="submitSmb">
                {{ smbForm.processing ? 'Menyimpan…' : 'Simpan pengaturan' }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <div v-show="tab === 'label-lan'" class="space-y-5">
        <div class="rounded-xl border border-base-200 bg-base-100 p-4 text-sm">
          <p class="font-medium text-base-content">Profil label</p>
          <p class="mt-1 text-xs text-base-content/70">
            Untuk TSPL, ukuran &amp; margin dari profil dipakai ke perintah SIZE/GAP.
          </p>
          <button type="button" class="btn btn-outline btn-sm mt-3" @click="selectTab('label-profiles')">Kelola profil label</button>
        </div>

        <div class="ocn-panel">
          <div class="ocn-panel__head">
            <h2 class="ocn-panel__title">Printer label (IP)</h2>
            <p class="ocn-panel__desc">Jika profil LAN dikosongkan, dipakai profil yang sama dengan pengaturan Label SMB.</p>
          </div>
          <div class="card-body space-y-4">
            <label class="label cursor-pointer justify-start gap-3 border border-base-200 rounded-xl p-3">
              <input v-model="lanForm.label_lan_enabled" type="checkbox" class="checkbox checkbox-primary">
              <div>
                <span class="label-text font-semibold">Aktifkan Label LAN (TSPL)</span>
                <p class="text-xs text-base-content/60">Cetak barcode produk memakai saluran ini jika diaktifkan.</p>
              </div>
            </label>

            <div class="grid gap-4 md:grid-cols-2">
              <div class="space-y-2">
                <label class="label p-0"><span class="label-text">Alamat IP atau hostname</span></label>
                <input
                  v-model="lanForm.label_lan_host"
                  type="text"
                  class="input input-bordered w-full font-mono text-sm"
                  placeholder="192.168.1.80"
                  autocomplete="off"
                >
                <p v-if="lanForm.errors.label_lan_host" class="text-xs text-error">{{ lanForm.errors.label_lan_host }}</p>
              </div>
              <div class="space-y-2">
                <label class="label p-0"><span class="label-text">Port RAW</span></label>
                <input
                  v-model.number="lanForm.label_lan_port"
                  type="number"
                  min="1"
                  max="65535"
                  class="input input-bordered w-full font-mono"
                >
                <p v-if="lanForm.errors.label_lan_port" class="text-xs text-error">{{ lanForm.errors.label_lan_port }}</p>
              </div>
            </div>

            <div class="space-y-2">
              <label class="label p-0"><span class="label-text">Profil label (opsional)</span></label>
              <select v-model="lanForm.label_lan_profile_id" class="select select-bordered w-full font-mono text-sm">
                <option value="">
                  — pakai profil dari Label SMB (jika ada) —
                </option>
                <option v-for="p in labelProfiles" :key="p.id" :value="p.id">
                  {{ p.name }} — {{ p.width_mm }}×{{ p.height_mm }} mm, {{ p.dpi }} dpi
                </option>
              </select>
              <p v-if="lanForm.errors.label_lan_profile_id" class="text-xs text-error">{{ lanForm.errors.label_lan_profile_id }}</p>
              <p v-if="!labelProfiles?.length" class="text-xs text-warning">
                Belum ada profil label di master.
              </p>
            </div>

            <div class="rounded-xl border border-base-200 bg-base-200/40 p-4 text-sm text-base-content/80">
              <p class="font-medium text-base-content">Uji cetak TSPL</p>
              <div class="mt-3 flex flex-wrap gap-2">
                <button
                  type="button"
                  class="btn min-w-[9rem]"
                  :class="canTestLan ? 'btn-primary text-white' : 'btn-outline border-base-300 text-base-content/50'"
                  :disabled="!canTestLan || lanTestForm.processing || lanForm.processing"
                  @click="testPrintLan"
                >
                  {{ lanTestForm.processing ? 'Mengirim…' : 'Test cetak TSPL' }}
                </button>
              </div>
            </div>

            <div class="flex justify-end gap-2">
              <button class="btn btn-primary" :disabled="lanForm.processing" @click="submitLan">
                {{ lanForm.processing ? 'Menyimpan…' : 'Simpan pengaturan' }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <div v-show="tab === 'label-profiles'">
        <LabelProfilesPanel :profiles="labelProfiles" />
      </div>
    </div>
  </AppLayout>
</template>
