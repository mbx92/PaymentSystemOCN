<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import {
  FolderIcon,
  DocumentIcon,
  DocumentTextIcon,
  PhotoIcon,
  ArchiveBoxIcon,
  CodeBracketIcon,
  FilmIcon,
  MusicalNoteIcon,
  TrashIcon,
  ArrowUpTrayIcon,
  ArrowLeftIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
  currentPath: String,
  breadcrumbs: Array,
  folders: Array,
  files: Array,
});

const folderForm = useForm({
  path: props.currentPath ?? '',
  name: '',
});

const uploadBusy = ref(false);
const uploadError = ref('');
const dragDepth = ref(0);
const isDropActive = ref(false);
const fileInputRef = ref(null);

const deleting = ref(null);

const openFolderModal = () => {
  folderForm.clearErrors();
  folderForm.path = props.currentPath ?? '';
  folderForm.name = '';
  document.getElementById('modal-new-folder')?.showModal();
};

const submitFolder = () => {
  folderForm.path = props.currentPath ?? '';
  folderForm.post(route('erp.hr.legal.folders.store'), {
    preserveScroll: true,
    onSuccess: () => {
      folderForm.reset('name');
      document.getElementById('modal-new-folder')?.close();
    },
  });
};

const uploadFiles = (fileList) => {
  const list = [...fileList].filter((f) => f && f.size >= 0);
  if (!list.length) return;

  uploadBusy.value = true;
  uploadError.value = '';

  const runNext = (idx) => {
    if (idx >= list.length) {
      uploadBusy.value = false;
      router.reload({ preserveScroll: true });
      return;
    }
    const file = list[idx];
    router.post(
      route('erp.hr.legal.uploads.store'),
      { path: props.currentPath ?? '', file },
      {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => runNext(idx + 1),
        onError: (errs) => {
          uploadBusy.value = false;
          uploadError.value = errs?.file?.[0] || Object.values(errs || {})[0]?.[0] || 'Upload gagal.';
        },
      },
    );
  };

  runNext(0);
};

const onFileInputChange = (e) => {
  const files = e.target.files;
  if (files?.length) uploadFiles(files);
  e.target.value = '';
};

const openFilePicker = () => {
  fileInputRef.value?.click();
};

const onDragOver = (e) => {
  e.preventDefault();
  if (e.dataTransfer) e.dataTransfer.dropEffect = 'copy';
};

const onDragEnter = (e) => {
  e.preventDefault();
  dragDepth.value += 1;
  if (dragDepth.value === 1) {
    isDropActive.value = true;
  }
};

const onDragLeave = (e) => {
  e.preventDefault();
  dragDepth.value = Math.max(0, dragDepth.value - 1);
  if (dragDepth.value === 0) {
    isDropActive.value = false;
  }
};

const onDrop = (e) => {
  e.preventDefault();
  dragDepth.value = 0;
  isDropActive.value = false;
  const dt = e.dataTransfer?.files;
  if (dt?.length) uploadFiles(dt);
};

const goFolder = (path) => {
  router.get(route('erp.hr.legal'), { path: path || undefined }, { preserveScroll: true });
};

const goUp = () => {
  const current = props.currentPath || '';
  if (!current) return;
  const parts = current.split('/').filter(Boolean);
  parts.pop();
  goFolder(parts.join('/'));
};

const goFolderDblClick = (path) => {
  window.getSelection()?.removeAllRanges();
  goFolder(path);
};

const onFileDblClick = (file) => {
  window.getSelection()?.removeAllRanges();
  if (file.is_pdf && file.view_url) {
    window.open(file.view_url, '_blank', 'noopener,noreferrer');
  } else if (file.download_url) {
    window.open(file.download_url, '_blank', 'noopener,noreferrer');
  }
};

const getFileExt = (name) => {
  const idx = name?.lastIndexOf('.') ?? -1;
  return idx > -1 ? name.slice(idx + 1).toLowerCase() : '';
};

const fileVisual = (file) => {
  const ext = getFileExt(file?.name ?? '');

  if (ext === 'pdf') return { icon: DocumentTextIcon, boxClass: 'bg-red-50 text-red-600', badge: 'PDF' };
  if (['doc', 'docx', 'txt', 'rtf', 'odt'].includes(ext)) return { icon: DocumentTextIcon, boxClass: 'bg-blue-50 text-blue-600', badge: ext.toUpperCase() || 'DOC' };
  if (['xls', 'xlsx', 'csv'].includes(ext)) return { icon: DocumentIcon, boxClass: 'bg-emerald-50 text-emerald-600', badge: ext.toUpperCase() || 'XLS' };
  if (['ppt', 'pptx'].includes(ext)) return { icon: DocumentIcon, boxClass: 'bg-orange-50 text-orange-600', badge: ext.toUpperCase() || 'PPT' };
  if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'].includes(ext)) return { icon: PhotoIcon, boxClass: 'bg-fuchsia-50 text-fuchsia-600', badge: ext.toUpperCase() || 'IMG' };
  if (['zip', 'rar', '7z', 'tar', 'gz'].includes(ext)) return { icon: ArchiveBoxIcon, boxClass: 'bg-amber-50 text-amber-700', badge: ext.toUpperCase() || 'ZIP' };
  if (['mp4', 'mov', 'avi', 'mkv', 'webm'].includes(ext)) return { icon: FilmIcon, boxClass: 'bg-purple-50 text-purple-600', badge: ext.toUpperCase() || 'VID' };
  if (['mp3', 'wav', 'm4a', 'flac', 'ogg'].includes(ext)) return { icon: MusicalNoteIcon, boxClass: 'bg-indigo-50 text-indigo-600', badge: ext.toUpperCase() || 'AUD' };
  if (['js', 'ts', 'vue', 'php', 'json', 'html', 'css', 'md', 'xml', 'yml', 'yaml'].includes(ext)) return { icon: CodeBracketIcon, boxClass: 'bg-slate-100 text-slate-700', badge: ext.toUpperCase() || 'CODE' };

  return { icon: DocumentIcon, boxClass: 'bg-slate-100 text-slate-600', badge: ext ? ext.toUpperCase() : 'FILE' };
};

const confirmDelete = (row) => {
  deleting.value = row;
  document.getElementById('modal-delete-legal-item')?.showModal();
};

const doDelete = () => {
  if (!deleting.value) return;
  router.delete(route('erp.hr.legal.items.destroy'), {
    data: { path: deleting.value.path, type: deleting.value.type },
    preserveScroll: true,
    onFinish: () => {
      deleting.value = null;
      document.getElementById('modal-delete-legal-item')?.close();
    },
  });
};
</script>

<template>
  <Head title="HR – Legal" />
  <AppLayout>
    <div class="space-y-4">
      <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div class="min-w-0 flex-1">
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">HR Workspace</p>
            <h1 class="mt-1 text-2xl font-bold tracking-tight md:text-3xl">Legal</h1>
            <nav class="mt-3 flex flex-wrap items-center gap-1 text-sm text-base-content/80">
              <template v-for="(crumb, idx) in breadcrumbs" :key="crumb.path + String(idx)">
                <button
                  v-if="idx < breadcrumbs.length - 1"
                  type="button"
                  class="rounded-md px-1.5 py-0.5 hover:bg-base-200 hover:text-primary"
                  @click="goFolder(crumb.path)"
                >
                  {{ crumb.name }}
                </button>
                <span v-else class="rounded-md bg-base-200 px-2 py-0.5 font-semibold text-base-content">{{ crumb.name }}</span>
                <span v-if="idx < breadcrumbs.length - 1" class="text-base-content/30">›</span>
              </template>
            </nav>
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <button type="button" class="btn btn-outline btn-sm gap-1" @click="openFolderModal">
              <FolderIcon class="h-4 w-4" />
              Folder baru
            </button>
            <button type="button" class="btn btn-primary btn-sm gap-1" @click="openFilePicker">
              <ArrowUpTrayIcon class="h-4 w-4" />
              Upload
            </button>
            <Link class="btn btn-ghost btn-sm gap-1.5" :href="route('erp.hr')">
              <ArrowLeftIcon class="h-4 w-4" />
              Back
            </Link>
          </div>
        </div>
        <input
          ref="fileInputRef"
          type="file"
          class="hidden"
          multiple
          @change="onFileInputChange"
        >
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Unggah file</h2>
          <p class="ocn-panel__desc">Seret file ke area di bawah atau klik untuk memilih — beberapa file sekaligus didukung.</p>
        </div>
        <div class="card-body pt-0">
          <div
            class="legal-dropzone rounded-xl border-2 border-dashed px-4 py-10 text-center transition-colors md:px-8"
            :class="isDropActive ? 'border-primary bg-primary/5' : 'border-base-300 bg-base-200/40'"
            @dragenter="onDragEnter"
            @dragover="onDragOver"
            @dragleave="onDragLeave"
            @drop="onDrop"
            @click="openFilePicker"
          >
            <ArrowUpTrayIcon class="mx-auto h-10 w-10 text-base-content/40" />
            <p class="mt-2 text-sm font-medium text-base-content">Letakkan file di sini</p>
            <p class="mt-1 text-xs text-base-content/60">atau klik area ini untuk memilih dari perangkat</p>
          </div>
          <p v-if="uploadBusy" class="mt-3 text-xs text-primary">Mengunggah…</p>
          <p v-if="uploadError" class="mt-1 text-xs text-error">{{ uploadError }}</p>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div>
            <h2 class="ocn-panel__title">Folder &amp; file</h2>
            <p class="ocn-panel__desc">{{ folders.length }} folder, {{ files.length }} file di lokasi ini</p>
          </div>
          <button
            type="button"
            class="btn btn-outline btn-xs"
            :disabled="!currentPath"
            @click="goUp"
          >
            Up
          </button>
        </div>
        <div class="card-body min-h-[200px]">
          <p v-if="folders.length === 0 && files.length === 0" class="py-16 text-center text-sm text-base-content/50">
            Folder ini kosong. Buat folder baru atau unggah lewat kartu di atas.
          </p>

          <div
            v-else
            class="grid grid-cols-[repeat(auto-fill,minmax(104px,1fr))] gap-3 sm:grid-cols-[repeat(auto-fill,minmax(120px,1fr))] sm:gap-4"
          >
            <div
              v-for="f in folders"
              :key="'d-' + f.path"
              class="group relative flex select-none flex-col items-center rounded-xl border border-transparent p-2 text-center transition hover:border-primary/20 hover:bg-base-200/80 hover:shadow-sm"
              @dblclick.prevent="goFolderDblClick(f.path)"
            >
              <div class="flex h-14 w-14 items-center justify-center rounded-lg bg-amber-100 text-amber-700">
                <FolderIcon class="h-9 w-9" />
              </div>
              <p class="mt-2 w-full truncate text-xs font-medium leading-tight" :title="f.name">{{ f.name }}</p>
              <p class="mt-0.5 text-[10px] text-base-content/50">Klik 2x untuk buka</p>
              <button
                type="button"
                class="btn btn-ghost btn-xs absolute right-0 top-0 opacity-0 group-hover:opacity-100"
                title="Hapus folder"
                @click.stop="confirmDelete({ ...f, type: 'folder' })"
              >
                <TrashIcon class="h-4 w-4 text-error" />
              </button>
            </div>

            <div
              v-for="file in files"
              :key="'f-' + file.path"
              class="group relative flex select-none flex-col items-center rounded-xl border border-transparent p-2 text-center transition hover:border-primary/20 hover:bg-base-200/80 hover:shadow-sm"
              @dblclick.prevent="onFileDblClick(file)"
            >
              <div
                class="flex h-14 w-14 items-center justify-center rounded-lg"
                :class="fileVisual(file).boxClass"
              >
                <component :is="fileVisual(file).icon" class="h-9 w-9" />
              </div>
              <p class="mt-2 w-full truncate text-xs font-medium leading-tight" :title="file.name">{{ file.name }}</p>
              <p class="mt-0.5 line-clamp-1 w-full text-[10px] text-base-content/50">
                {{ file.size_kb }} KB · {{ file.modified_at }}
              </p>
              <span class="mt-1 badge badge-ghost badge-xs">{{ fileVisual(file).badge }}</span>
              <div class="mt-1 flex w-full flex-wrap justify-center gap-0.5 opacity-0 transition group-hover:opacity-100">
                <a
                  v-if="file.is_pdf && file.view_url"
                  :href="file.view_url"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="btn btn-ghost btn-xs px-1"
                  title="Buka PDF"
                  @click.stop
                >PDF</a>
                <a
                  :href="file.download_url"
                  class="btn btn-ghost btn-xs px-1"
                  title="Download"
                  @click.stop
                >↓</a>
                <button
                  type="button"
                  class="btn btn-ghost btn-xs px-1 text-error"
                  title="Hapus"
                  @click.stop="confirmDelete({ ...file, type: 'file' })"
                >
                  <TrashIcon class="h-4 w-4" />
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <dialog id="modal-new-folder" class="modal">
      <div class="modal-box">
        <h3 class="text-lg font-bold">Folder baru</h3>
        <p class="py-2 text-sm text-base-content/70">Lokasi: <span class="font-mono text-xs">{{ currentPath || '(root)' }}</span></p>
        <label class="label"><span class="label-text">Nama folder</span></label>
        <input v-model="folderForm.name" type="text" class="input input-bordered w-full" placeholder="contoh: Kontrak-2026">
        <p v-if="folderForm.errors.name" class="mt-1 text-xs text-error">{{ folderForm.errors.name }}</p>
        <div class="modal-action">
          <form method="dialog">
            <button class="btn btn-ghost btn-sm">Batal</button>
          </form>
          <button type="button" class="btn btn-primary btn-sm" :disabled="folderForm.processing" @click="submitFolder">Simpan</button>
        </div>
      </div>
      <form method="dialog" class="modal-backdrop"><button>close</button></form>
    </dialog>

    <dialog id="modal-delete-legal-item" class="modal">
      <div class="modal-box">
        <h3 class="text-lg font-bold text-error">Hapus item</h3>
        <p class="py-3 text-sm">
          Yakin hapus
          <strong>{{ deleting?.name }}</strong>
          <span v-if="deleting?.type === 'folder'">beserta seluruh isinya</span>?
        </p>
        <div class="modal-action">
          <form method="dialog">
            <button class="btn btn-ghost btn-sm">Batal</button>
          </form>
          <button type="button" class="btn btn-error btn-sm" @click="doDelete">Hapus</button>
        </div>
      </div>
      <form method="dialog" class="modal-backdrop"><button>close</button></form>
    </dialog>
  </AppLayout>
</template>

<style scoped>
.legal-dropzone {
  cursor: pointer;
}
</style>
