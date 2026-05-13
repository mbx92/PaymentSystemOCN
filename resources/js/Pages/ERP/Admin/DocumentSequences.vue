<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTablePagination from '@/Components/DataTablePagination.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, watch, nextTick } from 'vue';

const props = defineProps({
  sequences: Object,
  moduleOptions: { type: Array, default: () => [] },
  typeOptions: { type: Array, default: () => [] },
  filters: Object,
});

const form = useForm({
  module: '',
  document_type: '',
  prefix: '',
  padding_length: 6,
  running_number: 0,
});

const submit = () => {
  form.post(route('erp.admin.document-sequences.store'), {
    preserveScroll: true,
    onSuccess: () => {
      form.reset('module', 'document_type', 'prefix');
      form.padding_length = 6;
      form.running_number = 0;
      document.getElementById('modal-add-sequence')?.close();
    },
  });
};

const openAddModal = () => {
  form.clearErrors();
  document.getElementById('modal-add-sequence')?.showModal();
};

const editing = ref(null);
const editForm = useForm({
  prefix: '',
  padding_length: 6,
  running_number: 0,
});

const openEdit = (seq) => {
  editing.value = seq;
  editForm.prefix = seq.prefix;
  editForm.padding_length = seq.padding_length;
  editForm.running_number = seq.running_number;
  document.getElementById('modal-edit-sequence')?.showModal();
};

const submitEdit = () => {
  if (!editing.value) return;
  editForm.patch(route('erp.admin.document-sequences.update', editing.value.id), {
    preserveScroll: true,
    onSuccess: () => document.getElementById('modal-edit-sequence')?.close(),
  });
};

const q = ref(props.filters?.q ?? '');
const module = ref(props.filters?.module ?? '');
const documentType = ref(props.filters?.document_type ?? '');
const perPage = ref(Number(props.filters?.per_page ?? props.sequences?.per_page ?? 25));

let ignoreFilterEmit = false;

watch(
  () => props.filters,
  async (next) => {
    if (!next) return;
    ignoreFilterEmit = true;
    q.value = next.q ?? '';
    module.value = next.module ?? '';
    documentType.value = next.document_type ?? '';
    if (next.per_page != null) {
      perPage.value = Number(next.per_page);
    }
    await nextTick();
    ignoreFilterEmit = false;
  },
  { deep: true },
);

let timer;
watch([q, module, documentType], () => {
  if (ignoreFilterEmit) return;
  clearTimeout(timer);
  timer = setTimeout(() => {
    router.get(route('erp.admin.document-sequences'), {
      q: q.value,
      module: module.value,
      document_type: documentType.value,
      per_page: perPage.value,
    }, { preserveState: true, replace: true });
  }, 400);
});

const onPerPage = (n) => {
  perPage.value = n;
  router.get(route('erp.admin.document-sequences'), {
    q: q.value,
    module: module.value,
    document_type: documentType.value,
    per_page: n,
  }, { preserveState: true, replace: true });
};
</script>

<template>
  <Head title="Administration - Setting Nomor Dokumen" />
  <AppLayout>
    <div class="space-y-5">
      <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Administration Workspace</p>
        <div class="mt-2 flex items-center justify-between gap-3">
          <div>
            <h1 class="text-3xl font-bold tracking-tight">Setting Nomor Dokumen</h1>
            <p class="mt-2 text-sm text-base-content/70">Atur prefix, padding, dan sequence agar nomor dokumen formal dan konsisten.</p>
          </div>
          <Link class="btn btn-ghost btn-sm" :href="route('erp.administration')">Back</Link>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Filter sequence</h2>
          <p class="ocn-panel__desc">Saring berdasarkan module, tipe dokumen, atau kata kunci.</p>
        </div>
        <div class="card-body">
          <div class="flex flex-wrap items-end gap-3">
            <div class="min-w-[220px] grow">
              <label class="label"><span class="label-text">Search</span></label>
              <input v-model="q" type="text" class="input input-bordered w-full" placeholder="Cari module / document type / prefix" />
            </div>
            <div class="w-full sm:w-56">
              <label class="label"><span class="label-text">Module</span></label>
              <select v-model="module" class="select select-bordered w-full">
                <option value="">Semua Module</option>
                <option v-for="m in moduleOptions" :key="m" :value="m">{{ m }}</option>
              </select>
            </div>
            <div class="w-full sm:w-64">
              <label class="label"><span class="label-text">Document Type</span></label>
              <select v-model="documentType" class="select select-bordered w-full">
                <option value="">Semua Type</option>
                <option v-for="t in typeOptions" :key="t" :value="t">{{ t }}</option>
              </select>
            </div>
            <button class="btn btn-primary btn-sm" @click="openAddModal">+ Tambah Sequence</button>
          </div>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Daftar nomor dokumen</h2>
          <p class="ocn-panel__desc">Prefix, padding, dan angka berjalan per jenis dokumen.</p>
        </div>
        <div class="overflow-x-auto">
          <table class="table table-zebra">
            <thead><tr><th>Module</th><th>Document Type</th><th>Prefix</th><th>Padding</th><th>Next Number</th><th></th></tr></thead>
            <tbody>
              <tr v-for="seq in sequences.data" :key="seq.id">
                <td class="font-mono text-xs">{{ seq.module }}</td>
                <td class="font-mono text-xs">{{ seq.document_type }}</td>
                <td class="font-semibold">{{ seq.prefix }}</td>
                <td>{{ seq.padding_length }}</td>
                <td class="font-mono">{{ seq.prefix }}-{{ String((seq.running_number || 0) + 1).padStart(seq.padding_length || 6, '0') }}</td>
                <td class="text-right"><button class="btn btn-ghost btn-xs" @click="openEdit(seq)">Edit</button></td>
              </tr>
              <tr v-if="!sequences.data?.length">
                <td colspan="6" class="py-8 text-center text-base-content/50">Belum ada sequence nomor dokumen.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <DataTablePagination :paginator="sequences" @update:per-page="onPerPage" />
      </div>
    </div>

    <dialog id="modal-add-sequence" class="modal">
      <div class="modal-box max-w-xl">
        <h3 class="font-bold text-lg">Tambah Sequence Nomor Dokumen</h3>
        <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-3">
          <div>
            <label class="label"><span class="label-text">Module</span></label>
            <input v-model="form.module" type="text" class="input input-bordered w-full" placeholder="sales" />
            <p v-if="form.errors.module" class="text-error text-xs mt-1">{{ form.errors.module }}</p>
          </div>
          <div>
            <label class="label"><span class="label-text">Document Type</span></label>
            <input v-model="form.document_type" type="text" class="input input-bordered w-full" placeholder="project_invoice" />
            <p v-if="form.errors.document_type" class="text-error text-xs mt-1">{{ form.errors.document_type }}</p>
          </div>
          <div>
            <label class="label"><span class="label-text">Prefix</span></label>
            <input v-model="form.prefix" type="text" class="input input-bordered w-full" placeholder="INV-PRJ" />
            <p v-if="form.errors.prefix" class="text-error text-xs mt-1">{{ form.errors.prefix }}</p>
          </div>
          <div>
            <label class="label"><span class="label-text">Padding Length</span></label>
            <input v-model.number="form.padding_length" type="number" min="3" max="10" class="input input-bordered w-full" />
            <p v-if="form.errors.padding_length" class="text-error text-xs mt-1">{{ form.errors.padding_length }}</p>
          </div>
          <div class="md:col-span-2">
            <label class="label"><span class="label-text">Running Number Saat Ini</span></label>
            <input v-model.number="form.running_number" type="number" min="0" class="input input-bordered w-full" />
            <p v-if="form.errors.running_number" class="text-error text-xs mt-1">{{ form.errors.running_number }}</p>
          </div>
        </div>
        <div class="modal-action">
          <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
          <button class="btn btn-primary" :disabled="form.processing" @click="submit">Simpan</button>
        </div>
      </div>
    </dialog>

    <dialog id="modal-edit-sequence" class="modal">
      <div class="modal-box max-w-xl">
        <h3 class="font-bold text-lg">Edit Sequence</h3>
        <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-3">
          <div>
            <label class="label"><span class="label-text">Prefix</span></label>
            <input v-model="editForm.prefix" type="text" class="input input-bordered w-full" />
            <p v-if="editForm.errors.prefix" class="text-error text-xs mt-1">{{ editForm.errors.prefix }}</p>
          </div>
          <div>
            <label class="label"><span class="label-text">Padding Length</span></label>
            <input v-model.number="editForm.padding_length" type="number" min="3" max="10" class="input input-bordered w-full" />
            <p v-if="editForm.errors.padding_length" class="text-error text-xs mt-1">{{ editForm.errors.padding_length }}</p>
          </div>
          <div class="md:col-span-2">
            <label class="label"><span class="label-text">Running Number Saat Ini</span></label>
            <input v-model.number="editForm.running_number" type="number" min="0" class="input input-bordered w-full" />
            <p v-if="editForm.errors.running_number" class="text-error text-xs mt-1">{{ editForm.errors.running_number }}</p>
          </div>
        </div>
        <div class="modal-action">
          <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
          <button class="btn btn-primary" :disabled="editForm.processing" @click="submitEdit">Simpan</button>
        </div>
      </div>
    </dialog>
  </AppLayout>
</template>

