<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
  rules: Array,
  filters: Object,
});

const filterSearch = ref(props.filters?.search || '');
const filterStatus = ref(props.filters?.status || '');

const applyFilters = () => {
  router.get(route('erp.admin.parser-rules'), {
    search: filterSearch.value,
    status: filterStatus.value,
  }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const filteredRules = computed(() => props.rules ?? []);

const form = useForm({
  name: '',
  intent_key: '',
  keywords_text: '',
  priority: 100,
  is_active: true,
  notes: '',
  response_text: '',
});

const editForm = useForm({
  name: '',
  intent_key: '',
  keywords_text: '',
  priority: 100,
  is_active: true,
  notes: '',
  response_text: '',
});

const parserTestForm = useForm({
  message: '',
});

const selectedRule = ref(null);

const toKeywordsArray = (value) => value
  .split(',')
  .map((item) => item.trim().toLowerCase())
  .filter((item) => item.length > 0);

const openAddModal = () => {
  form.clearErrors();
  form.reset();
  form.priority = 100;
  form.is_active = true;
  document.getElementById('modal-add-parser-rule')?.showModal();
};

const submitAdd = () => {
  form.transform((data) => ({
    name: data.name,
    intent_key: data.intent_key,
    keywords: toKeywordsArray(data.keywords_text),
    priority: data.priority,
    is_active: !!data.is_active,
    notes: data.notes,
    response_text: data.response_text,
  })).post(route('erp.admin.parser-rules.store'), {
    preserveScroll: true,
    onSuccess: () => document.getElementById('modal-add-parser-rule')?.close(),
  });
};

const openEditModal = (rule) => {
  selectedRule.value = rule;
  editForm.clearErrors();
  editForm.name = rule.name;
  editForm.intent_key = rule.intent_key;
  editForm.keywords_text = (rule.keywords || []).join(', ');
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
    priority: data.priority,
    is_active: !!data.is_active,
    notes: data.notes,
    response_text: data.response_text,
  })).patch(route('erp.admin.parser-rules.update', selectedRule.value.id), {
    preserveScroll: true,
    onSuccess: () => document.getElementById('modal-edit-parser-rule')?.close(),
  });
};

const submitParserTest = () => {
  parserTestForm.post(route('erp.admin.parser-rules.test'), {
    preserveScroll: true,
  });
};
</script>

<template>
  <Head title="Administration - Parser Rules Chatbot" />
  <AppLayout>
    <div class="space-y-5">
      <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Administration Workspace</p>
        <div class="mt-2 flex items-center justify-between gap-3">
          <div>
            <h1 class="text-3xl font-bold tracking-tight">Parser Rules Chatbot</h1>
            <p class="mt-2 text-sm text-base-content/70">Atur rule berbasis keyword untuk intent chatbot ERP tanpa API LLM.</p>
          </div>
          <Link class="btn btn-ghost btn-sm" :href="route('erp.administration')">Back</Link>
        </div>
      </div>

      <div class="grid gap-4 lg:grid-cols-3">
        <div class="card bg-base-100 shadow lg:col-span-2">
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

        <div class="card bg-base-100 shadow">
          <div class="card-body">
            <h3 class="card-title text-base">Uji Parser</h3>
            <textarea
              v-model="parserTestForm.message"
              class="textarea textarea-bordered min-h-[110px]"
              placeholder="Contoh: tampilkan invoice yang belum dibayar"
            />
            <p v-if="parserTestForm.errors.message" class="text-error text-xs">{{ parserTestForm.errors.message }}</p>
            <button class="btn btn-secondary btn-sm mt-2" :disabled="parserTestForm.processing" @click="submitParserTest">
              Test Match Rule
            </button>
          </div>
        </div>
      </div>

      <div class="card bg-base-100 shadow">
        <div class="overflow-x-auto">
          <table class="table table-zebra">
            <thead>
              <tr>
                <th>Rule</th>
                <th>Intent Key</th>
                <th>Keywords</th>
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
                <td class="font-mono">{{ rule.priority }}</td>
                <td class="max-w-[280px]">
                  <p class="text-xs text-base-content/70 whitespace-pre-line break-words">{{ rule.response_text || '-' }}</p>
                </td>
                <td>
                  <span class="badge badge-sm" :class="rule.is_active ? 'badge-success' : 'badge-ghost'">
                    {{ rule.is_active ? 'active' : 'inactive' }}
                  </span>
                </td>
                <td class="text-right">
                  <button class="btn btn-ghost btn-xs" @click="openEditModal(rule)">Edit</button>
                </td>
              </tr>
              <tr v-if="!filteredRules.length">
                <td colspan="7" class="py-8 text-center text-base-content/50">Belum ada parser rule.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

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
            <label class="label"><span class="label-text">Priority</span></label>
            <input v-model.number="form.priority" type="number" min="1" class="input input-bordered w-full" />
            <p class="text-xs text-base-content/60 mt-1">Semakin kecil, semakin diprioritaskan.</p>
            <p v-if="form.errors.priority" class="text-error text-xs mt-1">{{ form.errors.priority }}</p>
          </div>
          <div>
            <label class="label cursor-pointer justify-start gap-3 mt-7">
              <input
                :checked="form.is_active"
                type="checkbox"
                class="toggle toggle-success"
                @change="form.is_active = $event.target.checked"
              />
              <span class="label-text">{{ form.is_active ? 'active' : 'inactive' }}</span>
            </label>
            <p v-if="form.errors.is_active" class="text-error text-xs mt-1">{{ form.errors.is_active }}</p>
          </div>
          <div class="md:col-span-2">
            <fieldset class="fieldset">
              <legend class="fieldset-legend">Catatan</legend>
              <textarea
                v-model="form.notes"
                class="textarea textarea-bordered textarea-sm w-full min-h-[90px] resize-y"
                placeholder="Opsional: digunakan untuk intent stok produk di chatbot."
              />
              <p v-if="form.errors.notes" class="label text-error">{{ form.errors.notes }}</p>
            </fieldset>
          </div>
          <div class="md:col-span-2 rounded-xl border border-base-300 bg-base-200/50 p-3">
            <fieldset class="fieldset">
              <legend class="fieldset-legend">Custom Reply (Handler)</legend>
              <textarea
                v-model="form.response_text"
                class="textarea textarea-bordered textarea-sm w-full min-h-[110px] resize-y"
                placeholder="Contoh: Sama-sama, senang bisa bantu."
              />
              <p class="label">Jika diisi, chatbot akan memakai balasan ini langsung saat rule match.</p>
              <p v-if="form.errors.response_text" class="label text-error">{{ form.errors.response_text }}</p>
            </fieldset>
          </div>
        </div>
        <div class="modal-action">
          <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
          <button class="btn btn-primary" :disabled="form.processing" @click="submitAdd">Simpan Rule</button>
        </div>
      </div>
    </dialog>

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
            <label class="label"><span class="label-text">Priority</span></label>
            <input v-model.number="editForm.priority" type="number" min="1" class="input input-bordered w-full" />
            <p v-if="editForm.errors.priority" class="text-error text-xs mt-1">{{ editForm.errors.priority }}</p>
          </div>
          <div>
            <label class="label cursor-pointer justify-start gap-3 mt-7">
              <input
                :checked="editForm.is_active"
                type="checkbox"
                class="toggle toggle-success"
                @change="editForm.is_active = $event.target.checked"
              />
              <span class="label-text">{{ editForm.is_active ? 'active' : 'inactive' }}</span>
            </label>
            <p v-if="editForm.errors.is_active" class="text-error text-xs mt-1">{{ editForm.errors.is_active }}</p>
          </div>
          <div class="md:col-span-2">
            <fieldset class="fieldset">
              <legend class="fieldset-legend">Catatan</legend>
              <textarea v-model="editForm.notes" class="textarea textarea-bordered textarea-sm w-full min-h-[90px] resize-y" />
              <p v-if="editForm.errors.notes" class="label text-error">{{ editForm.errors.notes }}</p>
            </fieldset>
          </div>
          <div class="md:col-span-2 rounded-xl border border-base-300 bg-base-200/50 p-3">
            <fieldset class="fieldset">
              <legend class="fieldset-legend">Custom Reply (Handler)</legend>
              <textarea v-model="editForm.response_text" class="textarea textarea-bordered textarea-sm w-full min-h-[110px] resize-y" />
              <p class="label">Jika diisi, chatbot akan memakai balasan ini langsung saat rule match.</p>
              <p v-if="editForm.errors.response_text" class="label text-error">{{ editForm.errors.response_text }}</p>
            </fieldset>
          </div>
        </div>
        <div class="modal-action">
          <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
          <button class="btn btn-primary" :disabled="editForm.processing" @click="submitEdit">Simpan Perubahan</button>
        </div>
      </div>
    </dialog>
  </AppLayout>
</template>
