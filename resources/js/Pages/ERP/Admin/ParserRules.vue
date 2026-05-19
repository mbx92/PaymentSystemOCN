<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTablePagination from '@/Components/DataTablePagination.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { computed, ref } from 'vue';

const props = defineProps({
  rules: Object,
  filters: Object,
});

const filterSearch = ref(props.filters?.search || '');
const filterStatus = ref(props.filters?.status || '');

const applyFilters = () => {
  router.get(route('erp.admin.parser-rules'), {
    search: filterSearch.value,
    status: filterStatus.value,
    per_page: props.filters?.per_page ?? props.rules?.per_page ?? 25,
  }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const onPerPage = (n) => {
  router.get(route('erp.admin.parser-rules'), {
    search: filterSearch.value,
    status: filterStatus.value,
    per_page: n,
  }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const filteredRules = computed(() => props.rules?.data ?? []);

/** Contoh template custom reply (harga string — hindari {{ }} literal di atribut template Vue) */
const parserReplyPlaceholderExample = 'Contoh:\n**{{name}}**\nStok: {{stock}} {{uom}}\n{{stock_status}}';

const emptyForm = () => ({
  name: '',
  intent_key: '',
  keywords_text: '',
  match_mode: 'and',
  priority: 100,
  is_active: true,
  notes: '',
  response_text: '',
});

const form = useForm(emptyForm());
const editForm = useForm(emptyForm());
const selectedRule = ref(null);
const deleteTarget = ref(null);

const toKeywordsArray = (value) => value
  .split(',')
  .map((item) => item.trim().toLowerCase())
  .filter((item) => item.length > 0);

const resetAddForm = () => {
  form.clearErrors();
  form.reset();
  Object.assign(form, emptyForm());
};

const openAddModal = () => {
  resetAddForm();
  document.getElementById('modal-add-parser-rule')?.showModal();
};

const submitAdd = () => {
  form.transform((data) => ({
    name: data.name,
    intent_key: data.intent_key,
    keywords: toKeywordsArray(data.keywords_text),
    match_mode: data.match_mode,
    priority: data.priority,
    is_active: !!data.is_active,
    notes: data.notes,
    response_text: data.response_text,
  })).post(route('erp.admin.parser-rules.store'), {
    preserveScroll: true,
    onSuccess: () => {
      resetAddForm();
      document.getElementById('modal-add-parser-rule')?.close();
    },
  });
};

const openEditModal = (rule) => {
  selectedRule.value = rule;
  editForm.clearErrors();
  editForm.name = rule.name;
  editForm.intent_key = rule.intent_key;
  editForm.keywords_text = (rule.keywords || []).join(', ');
  editForm.match_mode = rule.match_mode || 'and';
  editForm.priority = rule.priority;
  editForm.is_active = !!rule.is_active;
  editForm.notes = rule.notes || '';
  editForm.response_text = rule.response_text || '';
  document.getElementById('modal-edit-parser-rule')?.showModal();
};

const submitEdit = () => {
  if (!selectedRule.value) return;
  editForm.transform((data) => ({
    name: data.name,
    intent_key: data.intent_key,
    keywords: toKeywordsArray(data.keywords_text),
    match_mode: data.match_mode,
    priority: data.priority,
    is_active: !!data.is_active,
    notes: data.notes,
    response_text: data.response_text,
  })).patch(route('erp.admin.parser-rules.update', selectedRule.value.id), {
    preserveScroll: true,
    onSuccess: () => document.getElementById('modal-edit-parser-rule')?.close(),
  });
};

const openDeleteModal = (rule) => {
  deleteTarget.value = rule;
  document.getElementById('modal-delete-parser-rule')?.showModal();
};

const confirmDelete = () => {
  if (!deleteTarget.value) return;
  router.delete(route('erp.admin.parser-rules.destroy', deleteTarget.value.id), {
    preserveScroll: true,
    onSuccess: () => {
      document.getElementById('modal-delete-parser-rule')?.close();
      deleteTarget.value = null;
    },
  });
};
</script>

<template>
  <Head title="Administration - Parser Rules Chatbot" />
  <AppLayout>
    <div class="space-y-5">
      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Administration Workspace</p>
              <h1 class="ocn-panel__title mt-1">Parser Rules Chatbot</h1>
              <p class="ocn-panel__desc mt-1">Atur rule berbasis keyword untuk intent chatbot ERP. Mode <span class="font-mono text-xs">AND</span> = semua keyword harus ada, <span class="font-mono text-xs">OR</span> = cukup salah satu.</p>
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
          <h2 class="ocn-panel__title">Filter parser rule</h2>
        </div>
        <div class="card-body">
          <div class="flex flex-wrap items-end gap-3">
            <div class="min-w-[220px] grow">
              <label class="label"><span class="label-text">Search</span></label>
              <input v-model="filterSearch" type="text" class="input input-bordered w-full" placeholder="Cari nama rule / intent / catatan" />
            </div>
            <div class="w-full sm:w-48">
              <label class="label"><span class="label-text">Status</span></label>
              <select v-model="filterStatus" class="select select-bordered w-full">
                <option value="">Semua</option>
                <option value="active">active</option>
                <option value="inactive">inactive</option>
              </select>
            </div>
            <button class="btn btn-outline" @click="applyFilters">Filter</button>
            <button class="btn btn-primary" @click="openAddModal">+ Tambah Rule</button>
          </div>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Daftar parser rule</h2>
          <p class="ocn-panel__desc">Keyword, intent, dan balasan untuk chatbot ERP.</p>
        </div>
        <div class="overflow-x-auto">
          <table class="table table-zebra">
            <thead>
              <tr>
                <th>Rule</th>
                <th>Intent Key</th>
                <th>Keywords</th>
                <th>Mode</th>
                <th>Priority</th>
                <th>Custom Reply</th>
                <th>Status</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="rule in filteredRules" :key="rule.id">
                <td>
                  <div class="font-semibold">{{ rule.name }}</div>
                  <div class="text-xs text-base-content/60">{{ rule.notes || '-' }}</div>
                </td>
                <td class="font-mono text-xs">{{ rule.intent_key }}</td>
                <td>
                  <div class="flex flex-wrap gap-1">
                    <span
                      v-for="keyword in (rule.keywords || [])"
                      :key="keyword"
                      class="badge badge-ghost badge-sm"
                    >
                      {{ keyword }}
                    </span>
                  </div>
                </td>
                <td>
                  <span
                    class="badge badge-sm font-mono"
                    :class="rule.match_mode === 'or' ? 'badge-warning' : 'badge-info'"
                  >{{ rule.match_mode || 'and' }}</span>
                </td>
                <td class="font-mono">{{ rule.priority }}</td>
                <td class="max-w-[240px]">
                  <p class="text-xs text-base-content/70 whitespace-pre-line break-words">{{ rule.response_text || '-' }}</p>
                </td>
                <td>
                  <span class="badge badge-sm" :class="rule.is_active ? 'badge-success' : 'badge-ghost'">
                    {{ rule.is_active ? 'active' : 'inactive' }}
                  </span>
                </td>
                <td class="text-right">
                  <div class="flex justify-end gap-1">
                    <button class="btn btn-ghost btn-xs" @click="openEditModal(rule)">Edit</button>
                    <button class="btn btn-ghost btn-xs text-error" @click="openDeleteModal(rule)">Hapus</button>
                  </div>
                </td>
              </tr>
              <tr v-if="!filteredRules.length">
                <td colspan="8" class="py-8 text-center text-base-content/50">Belum ada parser rule.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <DataTablePagination :paginator="rules" @update:per-page="onPerPage" />
      </div>
    </div>

    <!-- Modal: Tambah Rule -->
    <dialog id="modal-add-parser-rule" class="modal">
      <div class="modal-box max-w-2xl">
        <h3 class="font-bold text-lg">Tambah Parser Rule</h3>
        <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-3">
          <div>
            <label class="label"><span class="label-text">Nama Rule</span></label>
            <input v-model="form.name" type="text" class="input input-bordered w-full" placeholder="Cek Stok Produk" />
            <p v-if="form.errors.name" class="text-error text-xs mt-1">{{ form.errors.name }}</p>
          </div>
          <div>
            <label class="label"><span class="label-text">Intent Key</span></label>
            <input v-model="form.intent_key" type="text" class="input input-bordered w-full" placeholder="stock_lookup" />
            <p v-if="form.errors.intent_key" class="text-error text-xs mt-1">{{ form.errors.intent_key }}</p>
          </div>
          <div class="md:col-span-2">
            <label class="label"><span class="label-text">Keywords (pisahkan dengan koma)</span></label>
            <input v-model="form.keywords_text" type="text" class="input input-bordered w-full" placeholder="stok, produk, sisa" />
            <p v-if="form.errors.keywords" class="text-error text-xs mt-1">{{ form.errors.keywords }}</p>
          </div>
          <div>
            <label class="label"><span class="label-text">Match Mode</span></label>
            <select v-model="form.match_mode" class="select select-bordered w-full">
              <option value="and">AND – semua keyword harus ada</option>
              <option value="or">OR – cukup salah satu keyword</option>
            </select>
          </div>
          <div>
            <label class="label"><span class="label-text">Priority</span></label>
            <input v-model.number="form.priority" type="number" min="1" class="input input-bordered w-full" />
            <p class="text-xs text-base-content/60 mt-1">Semakin kecil, semakin diprioritaskan.</p>
            <p v-if="form.errors.priority" class="text-error text-xs mt-1">{{ form.errors.priority }}</p>
          </div>
          <div>
            <label class="label cursor-pointer justify-start gap-3 mt-2">
              <input
                :checked="form.is_active"
                type="checkbox"
                class="toggle toggle-success"
                @change="form.is_active = $event.target.checked"
              />
              <span class="label-text">{{ form.is_active ? 'active' : 'inactive' }}</span>
            </label>
          </div>
          <div class="md:col-span-2">
            <fieldset class="fieldset">
              <legend class="fieldset-legend">Catatan</legend>
              <textarea
                v-model="form.notes"
                class="textarea textarea-bordered textarea-sm w-full min-h-[80px] resize-y"
                placeholder="Opsional: digunakan untuk intent stok produk di chatbot."
              />
            </fieldset>
          </div>
          <div class="md:col-span-2 rounded-xl border border-base-300 bg-base-200/50 p-3">
            <fieldset class="fieldset">
              <legend class="fieldset-legend">Custom Reply (opsional)</legend>
              <p class="text-xs text-base-content/65 mb-2">
                Tanpa <code v-pre>{{...}}</code>: balasan tetap (semua intent). Untuk intent
                <span class="font-mono">stock_lookup</span> /
                <span class="font-mono">product_price_lookup</span>, Anda bisa memakai template, mis.
                <span v-pre class="font-mono text-[11px] break-all">**{{name}}** — Stok: {{stock}} {{uom}}</span> atau
                <span v-pre class="font-mono text-[11px] break-all">Harga: Rp {{price}} / {{uom}}</span>.
                Placeholder:
                <span v-pre class="font-mono text-[11px]">{{name}}</span>,
                <span v-pre class="font-mono text-[11px]">{{sku}}</span>,
                <span v-pre class="font-mono text-[11px]">{{barcode}}</span>,
                <span v-pre class="font-mono text-[11px]">{{uom}}</span> /
                <span v-pre class="font-mono text-[11px]">{{satuan}}</span>,
                <span v-pre class="font-mono text-[11px]">{{stock}}</span>,
                <span v-pre class="font-mono text-[11px]">{{min_stock}}</span>,
                <span v-pre class="font-mono text-[11px]">{{price}}</span> /
                <span v-pre class="font-mono text-[11px]">{{harga}}</span>,
                <span v-pre class="font-mono text-[11px]">{{stock_status}}</span>.
                Jika produk tidak unik / tidak ketemu, chatbot memakai balasan bawaan.
              </p>
              <textarea
                v-model="form.response_text"
                class="textarea textarea-bordered textarea-sm w-full min-h-[100px] resize-y"
                :placeholder="parserReplyPlaceholderExample"
              />
            </fieldset>
          </div>
        </div>
        <div class="modal-action">
          <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
          <button class="btn btn-primary" :disabled="form.processing" @click="submitAdd">Simpan Rule</button>
        </div>
      </div>
    </dialog>

    <!-- Modal: Edit Rule -->
    <dialog id="modal-edit-parser-rule" class="modal">
      <div class="modal-box max-w-2xl">
        <h3 class="font-bold text-lg">Edit Parser Rule</h3>
        <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-3">
          <div>
            <label class="label"><span class="label-text">Nama Rule</span></label>
            <input v-model="editForm.name" type="text" class="input input-bordered w-full" />
            <p v-if="editForm.errors.name" class="text-error text-xs mt-1">{{ editForm.errors.name }}</p>
          </div>
          <div>
            <label class="label"><span class="label-text">Intent Key</span></label>
            <input v-model="editForm.intent_key" type="text" class="input input-bordered w-full" />
            <p v-if="editForm.errors.intent_key" class="text-error text-xs mt-1">{{ editForm.errors.intent_key }}</p>
          </div>
          <div class="md:col-span-2">
            <label class="label"><span class="label-text">Keywords (pisahkan dengan koma)</span></label>
            <input v-model="editForm.keywords_text" type="text" class="input input-bordered w-full" />
            <p v-if="editForm.errors.keywords" class="text-error text-xs mt-1">{{ editForm.errors.keywords }}</p>
          </div>
          <div>
            <label class="label"><span class="label-text">Match Mode</span></label>
            <select v-model="editForm.match_mode" class="select select-bordered w-full">
              <option value="and">AND – semua keyword harus ada</option>
              <option value="or">OR – cukup salah satu keyword</option>
            </select>
          </div>
          <div>
            <label class="label"><span class="label-text">Priority</span></label>
            <input v-model.number="editForm.priority" type="number" min="1" class="input input-bordered w-full" />
          </div>
          <div>
            <label class="label cursor-pointer justify-start gap-3 mt-2">
              <input
                :checked="editForm.is_active"
                type="checkbox"
                class="toggle toggle-success"
                @change="editForm.is_active = $event.target.checked"
              />
              <span class="label-text">{{ editForm.is_active ? 'active' : 'inactive' }}</span>
            </label>
          </div>
          <div class="md:col-span-2">
            <fieldset class="fieldset">
              <legend class="fieldset-legend">Catatan</legend>
              <textarea v-model="editForm.notes" class="textarea textarea-bordered textarea-sm w-full min-h-[80px] resize-y" />
            </fieldset>
          </div>
          <div class="md:col-span-2 rounded-xl border border-base-300 bg-base-200/50 p-3">
            <fieldset class="fieldset">
              <legend class="fieldset-legend">Custom Reply (opsional)</legend>
              <p class="text-xs text-base-content/65 mb-2">
                Template produk (intent <span class="font-mono">stock_lookup</span> /
                <span class="font-mono">product_price_lookup</span>):
                <span v-pre class="font-mono text-[11px]">{{uom}}</span>,
                <span v-pre class="font-mono text-[11px]">{{stock}}</span>,
                <span v-pre class="font-mono text-[11px]">{{price}}</span>, dll. — lihat form tambah rule untuk daftar lengkap.
              </p>
              <textarea v-model="editForm.response_text" class="textarea textarea-bordered textarea-sm w-full min-h-[100px] resize-y" />
            </fieldset>
          </div>
        </div>
        <div class="modal-action">
          <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
          <button class="btn btn-primary" :disabled="editForm.processing" @click="submitEdit">Simpan Perubahan</button>
        </div>
      </div>
    </dialog>

    <!-- Modal: Hapus Rule -->
    <dialog id="modal-delete-parser-rule" class="modal">
      <div class="modal-box max-w-md">
        <h3 class="font-bold text-lg text-error">Hapus Parser Rule?</h3>
        <p class="mt-3 text-sm">
          Rule <span class="font-semibold">{{ deleteTarget?.name }}</span>
          (intent: <span class="font-mono text-xs">{{ deleteTarget?.intent_key }}</span>) akan dihapus permanen.
        </p>
        <div class="modal-action">
          <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
          <button class="btn btn-error" @click="confirmDelete">Hapus</button>
        </div>
      </div>
    </dialog>
  </AppLayout>
</template>
