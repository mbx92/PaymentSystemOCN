<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import {
  ArrowLeftIcon,
  DocumentTextIcon,
  ReceiptRefundIcon,
  ClipboardDocumentListIcon,
  PencilSquareIcon,
  DocumentDuplicateIcon,
  TrashIcon,
  CheckCircleIcon,
  PlusIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
  templates: Array,
});

const typeLabels = { invoice: 'Invoice', sales_note: 'Nota Penjualan', pos_receipt: 'Struk POS' };
const typeIcons  = { invoice: DocumentTextIcon, sales_note: ClipboardDocumentListIcon, pos_receipt: ReceiptRefundIcon };
const typeBadge  = { invoice: 'badge-primary', sales_note: 'badge-secondary', pos_receipt: 'badge-accent' };

const confirmDeleteId = ref(null);
const deleteForm      = useForm({});

function activate(id) {
  router.post(route('erp.settings.document-templates.activate', id), {}, { preserveScroll: true });
}

function duplicate(id) {
  router.post(route('erp.settings.document-templates.duplicate', id), {}, { preserveScroll: true });
}

function doDelete() {
  deleteForm.delete(route('erp.settings.document-templates.destroy', confirmDeleteId.value), {
    preserveScroll: true,
    onFinish: () => { confirmDeleteId.value = null; },
  });
}
</script>

<template>
  <Head title="Administration - Template Dokumen" />
  <AppLayout>
    <div class="space-y-5">

      <!-- Page header -->
      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Administration Workspace</p>
              <h1 class="ocn-panel__title mt-1">Template Dokumen</h1>
              <p class="ocn-panel__desc mt-1">
                Rancang template invoice, nota penjualan, dan struk POS. Template aktif akan digunakan saat mencetak dokumen.
              </p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
              <Link
                v-for="type in ['invoice', 'sales_note', 'pos_receipt']"
                :key="type"
                :href="route('erp.settings.document-templates.create') + '?type=' + type"
                class="btn btn-sm btn-primary gap-1.5"
              >
                <PlusIcon class="h-4 w-4" />
                {{ typeLabels[type] }}
              </Link>
              <Link class="btn btn-ghost btn-sm shrink-0 gap-1.5" :href="route('erp.administration')">
                <ArrowLeftIcon class="h-4 w-4" />
                Back
              </Link>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty state -->
      <div v-if="!templates.length" class="ocn-panel">
        <div class="card-body py-16 text-center text-base-content/50">
          <ClipboardDocumentListIcon class="mx-auto mb-3 h-10 w-10 opacity-30" />
          <p class="text-sm">Belum ada template. Buat template baru menggunakan tombol di atas.</p>
        </div>
      </div>

      <!-- Template groups per type -->
      <template v-for="type in ['invoice', 'sales_note', 'pos_receipt']" :key="type">
        <div
          v-if="templates.some(t => t.type === type)"
          class="ocn-panel"
        >
          <div class="ocn-panel__head">
            <div class="flex items-center justify-between gap-3">
              <div class="flex items-center gap-2">
                <component :is="typeIcons[type]" class="h-5 w-5 text-primary" />
                <h2 class="ocn-panel__title">{{ typeLabels[type] }}</h2>
              </div>
              <Link
                :href="route('erp.settings.document-templates.create') + '?type=' + type"
                class="btn btn-outline btn-sm gap-1.5"
              >
                <PlusIcon class="h-3.5 w-3.5" />
                Baru
              </Link>
            </div>
          </div>

          <div class="card-body">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
              <div
                v-for="tpl in templates.filter(t => t.type === type)"
                :key="tpl.id"
                class="rounded-xl border bg-base-100 p-4 transition-all"
                :class="tpl.is_active
                  ? 'border-primary ring-2 ring-primary/20 shadow-sm'
                  : 'border-base-200 hover:border-base-300'"
              >
                <div class="flex items-start justify-between gap-2">
                  <div class="min-w-0 flex-1">
                    <p class="truncate font-semibold text-sm leading-tight">{{ tpl.name }}</p>
                    <p class="mt-0.5 text-xs text-base-content/50">
                      Diperbarui {{ new Date(tpl.updated_at).toLocaleDateString('id-ID') }}
                    </p>
                  </div>
                  <span v-if="tpl.is_active" class="badge badge-success badge-sm shrink-0 gap-1">
                    <CheckCircleIcon class="h-3 w-3" /> Aktif
                  </span>
                </div>

                <div class="mt-3 flex flex-wrap gap-1.5">
                  <Link
                    :href="route('erp.settings.document-templates.edit', tpl.id)"
                    class="btn btn-xs btn-outline gap-1"
                  >
                    <PencilSquareIcon class="h-3 w-3" /> Edit
                  </Link>
                  <button
                    v-if="!tpl.is_active"
                    class="btn btn-xs btn-success gap-1"
                    @click="activate(tpl.id)"
                  >
                    <CheckCircleIcon class="h-3 w-3" /> Aktifkan
                  </button>
                  <button class="btn btn-xs btn-ghost gap-1" @click="duplicate(tpl.id)">
                    <DocumentDuplicateIcon class="h-3 w-3" /> Duplikat
                  </button>
                  <button
                    v-if="!tpl.is_active"
                    class="btn btn-xs btn-error btn-outline gap-1 ml-auto"
                    @click="confirmDeleteId = tpl.id"
                  >
                    <TrashIcon class="h-3 w-3" />
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </template>

    </div>

    <!-- Delete confirm dialog -->
    <dialog class="modal" :class="confirmDeleteId ? 'modal-open' : ''">
      <div class="modal-box max-w-sm">
        <h3 class="font-bold">Hapus template?</h3>
        <p class="mt-2 text-sm text-base-content/70">Tindakan ini tidak bisa dibatalkan.</p>
        <div class="modal-action gap-2">
          <button class="btn btn-sm btn-ghost" @click="confirmDeleteId = null">Batal</button>
          <button class="btn btn-sm btn-error" :disabled="deleteForm.processing" @click="doDelete">Hapus</button>
        </div>
      </div>
      <div class="modal-backdrop" @click="confirmDeleteId = null" />
    </dialog>
  </AppLayout>
</template>
