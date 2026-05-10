<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { ArrowUpTrayIcon, DocumentTextIcon } from '@heroicons/vue/24/outline';

defineProps({
    media: Array,
});

const bytes = (n) => {
    const v = Number(n ?? 0);
    if (v < 1024) return `${v} B`;
    if (v < 1024 * 1024) return `${(v / 1024).toFixed(1)} KB`;
    return `${(v / (1024 * 1024)).toFixed(2)} MB`;
};

const form = useForm({
    file: null,
    alt_text: '',
});

const dropActive = ref(false);
const dragDepth = ref(0);
const fileInputRef = ref(null);

const selectedName = computed(() => (form.file?.name ? form.file.name : null));

const acceptMime = 'image/jpeg,image/png,image/webp,image/gif,application/pdf';

const setFileFromList = (fileList) => {
    const f = fileList?.[0];
    if (!f) {
        form.file = null;
        return;
    }
    form.clearErrors('file');
    form.file = f;
};

const onFileInput = (e) => {
    setFileFromList(e.target.files);
};

const onDrop = (e) => {
    dragDepth.value = 0;
    dropActive.value = false;
    const files = e.dataTransfer?.files;
    if (files?.length) setFileFromList(files);
};

const onDragEnter = (e) => {
    e.preventDefault();
    dragDepth.value += 1;
    dropActive.value = true;
};

const onDragOver = (e) => {
    e.preventDefault();
    if (e.dataTransfer) e.dataTransfer.dropEffect = 'copy';
};

const onDragLeave = (e) => {
    e.preventDefault();
    dragDepth.value = Math.max(0, dragDepth.value - 1);
    if (dragDepth.value === 0) dropActive.value = false;
};

const openPicker = () => fileInputRef.value?.click();

const clearFile = () => {
    form.file = null;
    form.clearErrors('file');
    if (fileInputRef.value) fileInputRef.value.value = '';
};

const submit = () => {
    form.post(route('erp.cms.media.store'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset('file', 'alt_text');
            clearFile();
        },
    });
};

const deletingItem = ref(null);
const deleteMessage = computed(() => (deletingItem.value
    ? `Yakin hapus “${deletingItem.value.original_name}” dari server? File tidak bisa dikembalikan.`
    : ''));

const confirmDeleteMedia = (item) => {
    deletingItem.value = item;
    document.getElementById('modal-delete-cms-media')?.showModal();
};

const doDeleteMedia = () => {
    if (!deletingItem.value) return;
    router.delete(route('erp.cms.media.destroy', deletingItem.value.id), {
        preserveScroll: true,
        onFinish: () => { deletingItem.value = null; },
    });
};

const isImage = (mime) => typeof mime === 'string' && mime.startsWith('image/');

/** Same-origin path when URL host matches the app (avoids APP_URL vs browser host mismatch). */
const thumbSrc = (item) => {
    const u = item?.url;
    if (!u || typeof u !== 'string') return '';
    if (u.startsWith('/')) return u;
    try {
        const parsed = new URL(u);
        if (parsed.origin === window.location.origin) {
            return `${parsed.pathname}${parsed.search}${parsed.hash}`;
        }
        return u;
    } catch {
        return u;
    }
};
</script>

<template>
  <Head title="CMS — Media" />
  <AppLayout>
    <div class="space-y-5">
      <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Website CMS</p>
        <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
          <div>
            <h1 class="text-3xl font-bold tracking-tight">Media library</h1>
            <p class="mt-2 text-sm text-base-content/70">File di disk publik (folder cms-media). Maks. 5 MB per unggahan. Seret file ke area di bawah atau klik untuk memilih.</p>
          </div>
          <Link class="btn btn-ghost btn-sm" :href="route('erp.cms')">Back</Link>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Unggah</h2>
        </div>
        <div class="card-body space-y-4">
          <input
            ref="fileInputRef"
            type="file"
            class="hidden"
            :accept="acceptMime"
            @change="onFileInput"
          >
          <div
            role="button"
            tabindex="0"
            class="relative flex min-h-[200px] cursor-pointer flex-col items-center justify-center gap-3 rounded-2xl border-2 border-dashed px-6 py-10 text-center transition-colors outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
            :class="dropActive ? 'border-primary bg-primary/5' : 'border-base-300 bg-base-200/30 hover:border-primary/40 hover:bg-base-200/50'"
            @click="openPicker"
            @keydown.enter.prevent="openPicker"
            @keydown.space.prevent="openPicker"
            @dragenter="onDragEnter"
            @dragover="onDragOver"
            @dragleave="onDragLeave"
            @drop="onDrop"
          >
            <ArrowUpTrayIcon class="h-12 w-12 text-primary/50" aria-hidden="true" />
            <div>
              <p class="text-base font-semibold text-base-content">Dropzone</p>
              <p class="mt-1 text-sm text-base-content/65">
                Letakkan file di sini atau <span class="font-medium text-primary">klik untuk memilih</span>
              </p>
              <p class="mt-2 text-xs text-base-content/50">JPG, PNG, WebP, GIF, PDF — maks. 5 MB</p>
            </div>
            <div v-if="selectedName" class="mt-2 flex flex-wrap items-center justify-center gap-2">
              <span class="badge badge-primary badge-outline max-w-[90vw] truncate">{{ selectedName }}</span>
              <button type="button" class="btn btn-ghost btn-xs" @click.stop="clearFile">Hapus pilihan</button>
            </div>
          </div>
          <p v-if="form.errors.file" class="text-error text-sm">{{ form.errors.file }}</p>

          <div class="flex flex-wrap items-end gap-4">
            <div class="grow min-w-[200px] max-w-md">
              <label class="label"><span class="label-text">Alt text (opsional)</span></label>
              <input v-model="form.alt_text" type="text" class="input input-bordered w-full" placeholder="Deskripsi singkat untuk aksesibilitas">
            </div>
            <button
              type="button"
              class="btn"
              :class="(form.processing || !form.file) ? 'btn-secondary' : 'btn-primary'"
              :disabled="form.processing || !form.file"
              @click="submit"
            >
              {{ form.processing ? 'Mengunggah…' : 'Upload' }}
            </button>
          </div>
        </div>
      </div>

      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div
          v-for="item in media"
          :key="item.id"
          class="rounded-2xl border border-slate-200 bg-white overflow-hidden shadow-sm flex flex-col"
        >
          <!-- Ukuran pratinjau tetap per kartu; gambar di-scale object-contain di dalam kotak -->
          <div class="flex h-48 w-full shrink-0 items-center justify-center overflow-hidden bg-base-200 p-2">
            <img
              v-if="isImage(item.mime)"
              :src="thumbSrc(item)"
              :alt="item.alt_text || item.original_name"
              class="max-h-full max-w-full object-contain"
              loading="lazy"
              decoding="async"
            >
            <div v-else class="flex flex-col items-center gap-1 text-base-content/30">
              <DocumentTextIcon class="h-14 w-14 shrink-0" />
              <span class="text-[10px] font-bold uppercase tracking-[0.14em]">PDF</span>
            </div>
          </div>
          <div class="p-4 flex-1 flex flex-col gap-2">
            <p class="text-sm font-medium truncate" :title="item.original_name">{{ item.original_name }}</p>
            <p class="text-xs text-base-content/60 font-mono">{{ item.mime }} · {{ bytes(item.size_bytes) }}</p>
            <p v-if="item.alt_text" class="text-xs text-base-content/70 line-clamp-2">{{ item.alt_text }}</p>
            <div class="mt-auto flex flex-wrap gap-2 pt-2">
              <a :href="thumbSrc(item)" target="_blank" rel="noopener" class="btn btn-ghost btn-xs">Buka</a>
              <button type="button" class="btn btn-ghost btn-xs text-error" @click="confirmDeleteMedia(item)">Hapus</button>
            </div>
          </div>
        </div>
      </div>

      <p v-if="!(media && media.length)" class="text-center text-base-content/50 py-12">Belum ada media.</p>
    </div>

    <ConfirmModal
      id="modal-delete-cms-media"
      title="Hapus media"
      :message="deleteMessage"
      @confirm="doDeleteMedia"
    />
  </AppLayout>
</template>
