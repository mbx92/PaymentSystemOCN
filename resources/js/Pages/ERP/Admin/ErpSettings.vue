<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { ref } from 'vue';

const props = defineProps({
  setting: Object,
});

const form = useForm({
  app_name: props.setting?.app_name ?? 'OCN ERP Suite',
  app_tagline: props.setting?.app_tagline ?? 'Integrated Business Platform',
  app_logo: null,
  remove_logo: false,
  module_menu_layout: props.setting?.module_menu_layout ?? 'grid',
  screen_mode: props.setting?.screen_mode ?? 'auto',
  screen_density: props.setting?.screen_density ?? 'comfortable',
  object_storage_enabled: props.setting?.object_storage_enabled ?? false,
  object_storage_access_key: props.setting?.object_storage_access_key ?? '',
  object_storage_secret_key: '',
  object_storage_bucket: props.setting?.object_storage_bucket ?? '',
  object_storage_region: props.setting?.object_storage_region ?? 'us-east-1',
  object_storage_endpoint: props.setting?.object_storage_endpoint ?? '',
  object_storage_use_path_style: props.setting?.object_storage_use_path_style ?? false,
  object_storage_prefix: props.setting?.object_storage_prefix ?? 'erp-archive',
  object_storage_archive_pdf: props.setting?.object_storage_archive_pdf ?? true,
  object_storage_archive_excel: props.setting?.object_storage_archive_excel ?? true,
  object_storage_archive_database: props.setting?.object_storage_archive_database ?? true,
});

const objectStorageTestLoading = ref(false);
const objectStorageTestMessage = ref('');
const objectStorageTestSuccess = ref(null);

const testObjectStorageConnection = async () => {
  objectStorageTestLoading.value = true;
  objectStorageTestMessage.value = '';
  objectStorageTestSuccess.value = null;

  try {
    const raw = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/)?.[1];
    const token = raw ? decodeURIComponent(raw) : '';
    const res = await fetch(route('erp.admin.erp-settings.object-storage.test'), {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-XSRF-TOKEN': token,
      },
      body: JSON.stringify({
        object_storage_access_key: form.object_storage_access_key,
        object_storage_secret_key: form.object_storage_secret_key,
        object_storage_bucket: form.object_storage_bucket,
        object_storage_region: form.object_storage_region,
        object_storage_endpoint: form.object_storage_endpoint,
        object_storage_use_path_style: form.object_storage_use_path_style,
        object_storage_prefix: form.object_storage_prefix,
      }),
    });

    const payload = await res.json();
    objectStorageTestSuccess.value = !!payload.success;
    objectStorageTestMessage.value = payload.message || (payload.success ? 'Koneksi berhasil.' : 'Koneksi gagal.');
  } catch {
    objectStorageTestSuccess.value = false;
    objectStorageTestMessage.value = 'Gagal menguji koneksi object storage.';
  } finally {
    objectStorageTestLoading.value = false;
  }
};

const onFileChange = (event) => {
  form.app_logo = event.target.files?.[0] ?? null;
  if (form.app_logo) form.remove_logo = false;
};

const submit = () => {
  form.post(route('erp.admin.erp-settings.update'), {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => {
      form.app_logo = null;
      form.remove_logo = false;
    },
  });
};
</script>

<template>
  <Head title="Administration - ERP Setting" />
  <AppLayout>
    <div class="space-y-5">
      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Administration Workspace</p>
              <h1 class="ocn-panel__title mt-1">ERP Setting</h1>
              <p class="ocn-panel__desc mt-1">Atur branding aplikasi dan layout global untuk halaman menu modul.</p>
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

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Identitas Aplikasi</h2>
          <p class="ocn-panel__desc">Perubahan akan tampil di sidebar ERP.</p>
        </div>
        <div class="card-body space-y-4">
          <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-2">
              <label class="label p-0"><span class="label-text">Nama Aplikasi</span></label>
              <input v-model="form.app_name" type="text" class="input input-bordered w-full" placeholder="OCN ERP Suite">
              <p v-if="form.errors.app_name" class="text-xs text-error">{{ form.errors.app_name }}</p>
            </div>
            <div class="space-y-2">
              <label class="label p-0"><span class="label-text">Tagline</span></label>
              <input v-model="form.app_tagline" type="text" class="input input-bordered w-full" placeholder="Integrated Business Platform">
              <p v-if="form.errors.app_tagline" class="text-xs text-error">{{ form.errors.app_tagline }}</p>
            </div>
          </div>

          <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-2">
              <label class="label p-0"><span class="label-text">Logo Aplikasi</span></label>
              <input type="file" accept="image/*" class="file-input file-input-bordered w-full" @change="onFileChange">
              <p class="text-xs text-base-content/60">Format gambar (png/jpg/webp), maksimal 2MB.</p>
              <p v-if="form.errors.app_logo" class="text-xs text-error">{{ form.errors.app_logo }}</p>
            </div>
            <div class="space-y-2">
              <label class="label p-0"><span class="label-text">Preview</span></label>
              <div class="flex min-h-24 items-center gap-3 rounded-xl border border-base-300 bg-base-100 p-3">
                <div v-if="setting?.app_logo_url && !form.remove_logo" class="h-12 w-12 overflow-hidden rounded-lg border border-base-300 bg-white">
                  <img :src="setting.app_logo_url" alt="Logo App" class="h-full w-full object-contain">
                </div>
                <div v-else class="flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10 text-xs font-bold text-primary">ERP</div>
                <div>
                  <p class="text-sm font-semibold">{{ form.app_name || 'OCN ERP Suite' }}</p>
                  <p class="text-xs text-base-content/60">{{ form.app_tagline || 'Integrated Business Platform' }}</p>
                </div>
              </div>
              <label class="label cursor-pointer justify-start gap-2 p-0">
                <input v-model="form.remove_logo" type="checkbox" class="checkbox checkbox-sm" :disabled="!setting?.app_logo_url">
                <span class="label-text">Hapus logo saat ini</span>
              </label>
            </div>
          </div>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Layout Menu Modul</h2>
          <p class="ocn-panel__desc">Global setting untuk tampilan submenu pada workspace ERP, Personal, dan Kelola User.</p>
        </div>
        <div class="card-body space-y-4">
          <div class="grid gap-4 lg:grid-cols-2">
            <label class="cursor-pointer rounded-2xl border p-4 transition"
              :class="form.module_menu_layout === 'grid' ? 'border-primary bg-primary/5' : 'border-base-300 bg-base-100'">
              <input v-model="form.module_menu_layout" type="radio" class="sr-only" value="grid">
              <div class="flex items-start justify-between gap-3">
                <div>
                  <p class="text-sm font-semibold text-base-content">Grid view</p>
                  <p class="mt-1 text-sm text-base-content/65">Memakai layout kartu seperti tampilan menu modul saat ini.</p>
                </div>
                <span class="badge" :class="form.module_menu_layout === 'grid' ? 'badge-primary' : 'badge-ghost'">Current style</span>
              </div>
              <div class="mt-4 grid grid-cols-2 gap-2">
                <div class="rounded-xl border border-base-300 bg-base-100 p-3">
                  <div class="h-3 w-3 rounded-full bg-primary/20" />
                  <div class="mt-3 h-3 w-2/3 rounded bg-base-300" />
                  <div class="mt-2 h-2 w-full rounded bg-base-200" />
                  <div class="mt-1 h-2 w-5/6 rounded bg-base-200" />
                </div>
                <div class="rounded-xl border border-base-300 bg-base-100 p-3">
                  <div class="h-3 w-3 rounded-full bg-primary/20" />
                  <div class="mt-3 h-3 w-3/4 rounded bg-base-300" />
                  <div class="mt-2 h-2 w-full rounded bg-base-200" />
                  <div class="mt-1 h-2 w-4/6 rounded bg-base-200" />
                </div>
              </div>
            </label>

            <label class="cursor-pointer rounded-2xl border p-4 transition"
              :class="form.module_menu_layout === 'list' ? 'border-primary bg-primary/5' : 'border-base-300 bg-base-100'">
              <input v-model="form.module_menu_layout" type="radio" class="sr-only" value="list">
              <div class="flex items-start justify-between gap-3">
                <div>
                  <p class="text-sm font-semibold text-base-content">List view</p>
                  <p class="mt-1 text-sm text-base-content/65">Tampilkan submenu modul sebagai daftar vertikal yang lebih ringkas.</p>
                </div>
                <span class="badge" :class="form.module_menu_layout === 'list' ? 'badge-primary' : 'badge-ghost'">List layout</span>
              </div>
              <div class="mt-4 rounded-xl border border-base-300 bg-base-100">
                <div class="flex items-center gap-3 border-b border-base-200 px-3 py-3">
                  <div class="h-10 w-10 rounded-xl bg-primary/10" />
                  <div class="min-w-0 flex-1">
                    <div class="h-3 w-1/3 rounded bg-base-300" />
                    <div class="mt-2 h-2 w-5/6 rounded bg-base-200" />
                  </div>
                </div>
                <div class="flex items-center gap-3 px-3 py-3">
                  <div class="h-10 w-10 rounded-xl bg-primary/10" />
                  <div class="min-w-0 flex-1">
                    <div class="h-3 w-2/5 rounded bg-base-300" />
                    <div class="mt-2 h-2 w-4/6 rounded bg-base-200" />
                  </div>
                </div>
              </div>
            </label>
          </div>

          <p v-if="form.errors.module_menu_layout" class="text-xs text-error">{{ form.errors.module_menu_layout }}</p>

          <div class="flex justify-end">
            <button class="btn btn-primary" :disabled="form.processing" @click="submit">
              Simpan ERP Setting
            </button>
          </div>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Setting Layar</h2>
          <p class="ocn-panel__desc">Atur profil tampilan berdasarkan device. Mode iPad 9 2021 menyiapkan layout khusus untuk layar 10.2 inch.</p>
        </div>
        <div class="card-body space-y-5">
          <div class="space-y-3">
            <p class="text-sm font-semibold text-base-content">Profil device</p>
            <div class="grid gap-4 xl:grid-cols-2">
              <label
                class="cursor-pointer rounded-2xl border p-4 transition"
                :class="form.screen_mode === 'auto' ? 'border-primary bg-primary/5' : 'border-base-300 bg-base-100'"
              >
                <input v-model="form.screen_mode" type="radio" class="sr-only" value="auto">
                <div class="flex items-start justify-between gap-3">
                  <div>
                    <p class="text-sm font-semibold text-base-content">Auto detect</p>
                    <p class="mt-1 text-sm text-base-content/65">Layout menyesuaikan ukuran layar aktual. Sistem akan memberi treatment khusus saat viewport cocok dengan iPad 9 2021.</p>
                  </div>
                  <span class="badge" :class="form.screen_mode === 'auto' ? 'badge-primary' : 'badge-ghost'">Recommended</span>
                </div>
              </label>

              <label
                class="cursor-pointer rounded-2xl border p-4 transition"
                :class="form.screen_mode === 'desktop' ? 'border-primary bg-primary/5' : 'border-base-300 bg-base-100'"
              >
                <input v-model="form.screen_mode" type="radio" class="sr-only" value="desktop">
                <div class="flex items-start justify-between gap-3">
                  <div>
                    <p class="text-sm font-semibold text-base-content">Desktop</p>
                    <p class="mt-1 text-sm text-base-content/65">Ruang kerja lebar untuk monitor kantor atau laptop besar.</p>
                  </div>
                  <span class="badge" :class="form.screen_mode === 'desktop' ? 'badge-primary' : 'badge-ghost'">Wide</span>
                </div>
              </label>

              <label
                class="cursor-pointer rounded-2xl border p-4 transition"
                :class="form.screen_mode === 'tablet' ? 'border-primary bg-primary/5' : 'border-base-300 bg-base-100'"
              >
                <input v-model="form.screen_mode" type="radio" class="sr-only" value="tablet">
                <div class="flex items-start justify-between gap-3">
                  <div>
                    <p class="text-sm font-semibold text-base-content">Tablet Umum</p>
                    <p class="mt-1 text-sm text-base-content/65">Spacing lebih rapat, sidebar dan konten lebih efisien untuk layar sentuh menengah.</p>
                  </div>
                  <span class="badge" :class="form.screen_mode === 'tablet' ? 'badge-primary' : 'badge-ghost'">Tablet</span>
                </div>
              </label>

              <label
                class="cursor-pointer rounded-2xl border p-4 transition"
                :class="form.screen_mode === 'ipad_9_2021' ? 'border-primary bg-primary/5' : 'border-base-300 bg-base-100'"
              >
                <input v-model="form.screen_mode" type="radio" class="sr-only" value="ipad_9_2021">
                <div class="flex items-start justify-between gap-3">
                  <div>
                    <p class="text-sm font-semibold text-base-content">iPad 9 2021 10.2"</p>
                    <p class="mt-1 text-sm text-base-content/65">Preset khusus untuk viewport iPad 9: sidebar diperkecil, area konten diperlebar, dan padding dibuat lebih nyaman untuk mode sentuh.</p>
                  </div>
                  <span class="badge" :class="form.screen_mode === 'ipad_9_2021' ? 'badge-primary' : 'badge-ghost'">Special</span>
                </div>
              </label>

              <label
                class="cursor-pointer rounded-2xl border p-4 transition"
                :class="form.screen_mode === 'mobile' ? 'border-primary bg-primary/5' : 'border-base-300 bg-base-100'"
              >
                <input v-model="form.screen_mode" type="radio" class="sr-only" value="mobile">
                <div class="flex items-start justify-between gap-3">
                  <div>
                    <p class="text-sm font-semibold text-base-content">Mobile</p>
                    <p class="mt-1 text-sm text-base-content/65">Fokus ke satu kolom dengan area sentuh lebih besar untuk smartphone.</p>
                  </div>
                  <span class="badge" :class="form.screen_mode === 'mobile' ? 'badge-primary' : 'badge-ghost'">Touch</span>
                </div>
              </label>
            </div>
            <p v-if="form.errors.screen_mode" class="text-xs text-error">{{ form.errors.screen_mode }}</p>
          </div>

          <div class="space-y-3">
            <p class="text-sm font-semibold text-base-content">Kerapatan layout</p>
            <div class="grid gap-4 lg:grid-cols-2">
              <label
                class="cursor-pointer rounded-2xl border p-4 transition"
                :class="form.screen_density === 'comfortable' ? 'border-primary bg-primary/5' : 'border-base-300 bg-base-100'"
              >
                <input v-model="form.screen_density" type="radio" class="sr-only" value="comfortable">
                <div class="flex items-start justify-between gap-3">
                  <div>
                    <p class="text-sm font-semibold text-base-content">Comfortable</p>
                    <p class="mt-1 text-sm text-base-content/65">Jarak elemen lebih lega, nyaman untuk presentasi dan penggunaan sentuh.</p>
                  </div>
                  <span class="badge" :class="form.screen_density === 'comfortable' ? 'badge-primary' : 'badge-ghost'">Default</span>
                </div>
              </label>

              <label
                class="cursor-pointer rounded-2xl border p-4 transition"
                :class="form.screen_density === 'compact' ? 'border-primary bg-primary/5' : 'border-base-300 bg-base-100'"
              >
                <input v-model="form.screen_density" type="radio" class="sr-only" value="compact">
                <div class="flex items-start justify-between gap-3">
                  <div>
                    <p class="text-sm font-semibold text-base-content">Compact</p>
                    <p class="mt-1 text-sm text-base-content/65">Lebih banyak informasi per layar untuk operator yang butuh kepadatan data.</p>
                  </div>
                  <span class="badge" :class="form.screen_density === 'compact' ? 'badge-primary' : 'badge-ghost'">Dense</span>
                </div>
              </label>
            </div>
            <p v-if="form.errors.screen_density" class="text-xs text-error">{{ form.errors.screen_density }}</p>
          </div>

          <div class="rounded-2xl border border-base-300 bg-base-200/60 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
              <div>
                <p class="text-sm font-semibold text-base-content">Preview penerapan</p>
                <p class="mt-1 text-sm text-base-content/65">
                  Profil aktif:
                  <span class="font-semibold text-base-content">{{ form.screen_mode }}</span>
                  · density:
                  <span class="font-semibold text-base-content">{{ form.screen_density }}</span>
                </p>
              </div>
              <span class="badge badge-outline">Global ERP layout</span>
            </div>
          </div>

          <div class="flex justify-end">
            <button class="btn btn-primary" :disabled="form.processing" @click="submit">
              Simpan ERP Setting
            </button>
          </div>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Object Storage (MinIO / S3)</h2>
          <p class="ocn-panel__desc">
            Archive otomatis file yang digenerate sistem ke bucket S3-compatible, termasuk PDF, Excel, dan backup database.
          </p>
        </div>
        <div class="card-body space-y-5">
          <label class="label cursor-pointer justify-start gap-3 rounded-xl border border-base-300 bg-base-100 p-4">
            <input v-model="form.object_storage_enabled" type="checkbox" class="toggle toggle-primary">
            <div>
              <span class="label-text font-semibold">Aktifkan archive ke bucket</span>
              <p class="mt-1 text-sm text-base-content/65">
                File tetap diunduh/dikirim seperti biasa, sekaligus disalin ke bucket sesuai kategori di bawah.
              </p>
            </div>
          </label>

          <div class="grid gap-4 md:grid-cols-2" :class="{ 'opacity-60': !form.object_storage_enabled }">
            <div class="space-y-2">
              <label class="label p-0"><span class="label-text">Access Key</span></label>
              <input
                v-model="form.object_storage_access_key"
                type="text"
                class="input input-bordered w-full"
                placeholder="minioadmin"
                :disabled="!form.object_storage_enabled"
              >
              <p v-if="form.errors.object_storage_access_key" class="text-xs text-error">{{ form.errors.object_storage_access_key }}</p>
            </div>
            <div class="space-y-2">
              <label class="label p-0"><span class="label-text">Secret Key</span></label>
              <input
                v-model="form.object_storage_secret_key"
                type="password"
                class="input input-bordered w-full"
                :placeholder="setting?.object_storage_secret_key_configured ? 'Sudah tersimpan — kosongkan jika tidak diubah' : 'Secret key bucket'"
                :disabled="!form.object_storage_enabled"
              >
              <p v-if="form.errors.object_storage_secret_key" class="text-xs text-error">{{ form.errors.object_storage_secret_key }}</p>
            </div>
            <div class="space-y-2">
              <label class="label p-0"><span class="label-text">Bucket</span></label>
              <input
                v-model="form.object_storage_bucket"
                type="text"
                class="input input-bordered w-full"
                placeholder="ocn-erp-archive"
                :disabled="!form.object_storage_enabled"
              >
              <p v-if="form.errors.object_storage_bucket" class="text-xs text-error">{{ form.errors.object_storage_bucket }}</p>
            </div>
            <div class="space-y-2">
              <label class="label p-0"><span class="label-text">Region</span></label>
              <input
                v-model="form.object_storage_region"
                type="text"
                class="input input-bordered w-full"
                placeholder="us-east-1"
                :disabled="!form.object_storage_enabled"
              >
            </div>
            <div class="space-y-2 md:col-span-2">
              <label class="label p-0"><span class="label-text">Endpoint (MinIO / S3 custom)</span></label>
              <input
                v-model="form.object_storage_endpoint"
                type="url"
                class="input input-bordered w-full"
                placeholder="https://minio.example.com"
                :disabled="!form.object_storage_enabled"
              >
              <p class="text-xs text-base-content/60">Kosongkan untuk AWS S3 standar. Untuk MinIO, isi URL server MinIO.</p>
            </div>
            <div class="space-y-2 md:col-span-2">
              <label class="label p-0"><span class="label-text">Prefix folder di bucket</span></label>
              <input
                v-model="form.object_storage_prefix"
                type="text"
                class="input input-bordered w-full"
                placeholder="erp-archive"
                :disabled="!form.object_storage_enabled"
              >
              <p class="text-xs text-base-content/60">Contoh path: <code>erp-archive/pdf/2026/06/27/invoice.pdf</code></p>
            </div>
          </div>

          <label
            class="label cursor-pointer justify-start gap-2 rounded-xl border border-base-300 bg-base-100 p-3"
            :class="{ 'opacity-60': !form.object_storage_enabled }"
          >
            <input
              v-model="form.object_storage_use_path_style"
              type="checkbox"
              class="checkbox checkbox-sm"
              :disabled="!form.object_storage_enabled"
            >
            <span class="label-text">Gunakan path-style endpoint (disarankan untuk MinIO)</span>
          </label>

          <div class="rounded-2xl border border-base-300 bg-base-200/40 p-4 space-y-3" :class="{ 'opacity-60': !form.object_storage_enabled }">
            <p class="text-sm font-semibold text-base-content">Kategori file yang di-archive</p>
            <div class="grid gap-2 md:grid-cols-3">
              <label class="label cursor-pointer justify-start gap-2 p-0">
                <input v-model="form.object_storage_archive_pdf" type="checkbox" class="checkbox checkbox-sm" :disabled="!form.object_storage_enabled">
                <span class="label-text">PDF (invoice, kwitansi, laporan)</span>
              </label>
              <label class="label cursor-pointer justify-start gap-2 p-0">
                <input v-model="form.object_storage_archive_excel" type="checkbox" class="checkbox checkbox-sm" :disabled="!form.object_storage_enabled">
                <span class="label-text">Excel export</span>
              </label>
              <label class="label cursor-pointer justify-start gap-2 p-0">
                <input v-model="form.object_storage_archive_database" type="checkbox" class="checkbox checkbox-sm" :disabled="!form.object_storage_enabled">
                <span class="label-text">Backup database</span>
              </label>
            </div>
          </div>

          <div
            v-if="objectStorageTestMessage"
            class="rounded-xl border p-3 text-sm"
            :class="objectStorageTestSuccess ? 'border-success/30 bg-success/10 text-success-content' : 'border-error/30 bg-error/10 text-error-content'"
          >
            {{ objectStorageTestMessage }}
          </div>

          <div class="flex flex-wrap justify-end gap-2">
            <button
              type="button"
              class="btn btn-outline"
              :disabled="!form.object_storage_enabled || objectStorageTestLoading"
              @click="testObjectStorageConnection"
            >
              {{ objectStorageTestLoading ? 'Menguji...' : 'Uji Koneksi Bucket' }}
            </button>
            <button class="btn btn-primary" :disabled="form.processing" @click="submit">
              Simpan ERP Setting
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
