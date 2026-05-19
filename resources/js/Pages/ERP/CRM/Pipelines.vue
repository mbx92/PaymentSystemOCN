<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTablePagination from '@/Components/DataTablePagination.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { reactive, ref, watch } from 'vue';
import { useCurrency } from '@/composables/useCurrency';
import { useDateFormat } from '@/composables/useDateFormat';

const props = defineProps({
  pipelines: Object,
  customers: Array,
  leads: Array,
  users: Array,
  filters: Object,
});

const { formatDate } = useDateFormat();

const { format } = useCurrency();

const filters = reactive({
  q: props.filters?.q ?? '',
  stage: props.filters?.stage ?? '',
  per_page: props.filters?.per_page ?? props.pipelines?.per_page ?? 25,
});

let timer;
watch(
  filters,
  (val) => {
    clearTimeout(timer);
    timer = setTimeout(() => {
      router.get(route('erp.crm.pipelines'), val, { preserveState: true, replace: true });
    }, 250);
  },
  { deep: true },
);

const stageOptions = [
  { value: 'prospecting', label: 'Prospecting', color: 'badge-info' },
  { value: 'qualification', label: 'Qualification', color: 'badge-warning' },
  { value: 'proposal', label: 'Proposal', color: 'badge-accent' },
  { value: 'negotiation', label: 'Negotiation', color: 'badge-primary' },
  { value: 'closed_won', label: 'Closed Won', color: 'badge-success' },
  { value: 'closed_lost', label: 'Closed Lost', color: 'badge-error' },
];

const stageBadge = (stage) => stageOptions.find((s) => s.value === stage)?.color ?? 'badge-ghost';
const stageLabel = (stage) => stageOptions.find((s) => s.value === stage)?.label ?? stage;

const form = useForm({
  title: '',
  crm_customer_id: '',
  crm_lead_id: '',
  stage: 'prospecting',
  deal_value: 0,
  win_probability: 10,
  expected_close_date: '',
  pic_user_id: '',
  notes: '',
});

const editForm = useForm({
  title: '',
  crm_customer_id: '',
  crm_lead_id: '',
  stage: 'prospecting',
  deal_value: 0,
  win_probability: 10,
  expected_close_date: '',
  pic_user_id: '',
  notes: '',
});

const selected = ref(null);

const resetAddForm = () => {
  form.clearErrors();
  form.reset();
  form.stage = 'prospecting';
  form.deal_value = 0;
  form.win_probability = 10;
  form.crm_customer_id = '';
  form.crm_lead_id = '';
  form.pic_user_id = '';
};

const openAdd = () => {
  resetAddForm();
  document.getElementById('modal-add-pipeline')?.showModal();
};

const submitAdd = () => {
  form.post(route('erp.crm.pipelines.store'), {
    preserveScroll: true,
    onSuccess: () => {
      resetAddForm();
      document.getElementById('modal-add-pipeline')?.close();
    },
  });
};

const openEdit = (row) => {
  selected.value = row;
  editForm.clearErrors();
  editForm.title = row.title;
  editForm.crm_customer_id = row.crm_customer_id ?? '';
  editForm.crm_lead_id = row.crm_lead_id ?? '';
  editForm.stage = row.stage;
  editForm.deal_value = row.deal_value ?? 0;
  editForm.win_probability = row.win_probability ?? 0;
  editForm.expected_close_date = row.expected_close_date || '';
  editForm.pic_user_id = row.pic_user_id ?? '';
  editForm.notes = row.notes || '';
  document.getElementById('modal-edit-pipeline')?.showModal();
};

const submitEdit = () => {
  if (!selected.value) return;
  editForm.patch(route('erp.crm.pipelines.update', selected.value.id), {
    preserveScroll: true,
    onSuccess: () => document.getElementById('modal-edit-pipeline')?.close(),
  });
};

const remove = (row) => {
  if (!confirm(`Hapus pipeline "${row.title}" (${row.code})?`)) return;
  router.delete(route('erp.crm.pipelines.destroy', row.id), { preserveScroll: true });
};
</script>

<template>
  <Head title="CRM — Pipeline Penjualan" />
  <AppLayout>
    <div class="space-y-5">
      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">CRM Workspace</p>
              <h1 class="ocn-panel__title mt-1">Pipeline Penjualan</h1>
              <p class="ocn-panel__desc mt-1">Tahapan penawaran, deal value, dan peluang closing yang bisa dipantau tim.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
              <div class="flex flex-wrap items-center gap-2">
            <button type="button" class="btn btn-primary btn-sm" @click="openAdd">+ Tambah pipeline</button>
            <Link class="btn btn-ghost btn-sm shrink-0 gap-1.5" :href="route('erp.crm')">
            <ArrowLeftIcon class="h-4 w-4" />
            Back
          </Link>
          </div>
            </div>
          </div>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Filter pipeline</h2>
        </div>
        <div class="card-body">
          <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
            <div class="md:col-span-2">
              <label class="label"><span class="label-text text-xs uppercase tracking-wide">Search</span></label>
              <input v-model="filters.q" type="text" class="input input-sm input-bordered w-full" placeholder="Kode atau judul pipeline..." />
            </div>
            <div>
              <label class="label"><span class="label-text text-xs uppercase tracking-wide">Stage</span></label>
              <select v-model="filters.stage" class="select select-sm select-bordered w-full">
                <option value="">Semua</option>
                <option v-for="s in stageOptions" :key="s.value" :value="s.value">{{ s.label }}</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Daftar pipeline</h2>
          <p class="ocn-panel__desc">{{ pipelines?.total ?? 0 }} pipeline ditemukan.</p>
        </div>
        <div class="overflow-x-auto">
          <table class="table table-zebra table-sm">
            <thead>
              <tr>
                <th>Kode</th>
                <th>Judul</th>
                <th>Customer / Lead</th>
                <th>Stage</th>
                <th class="text-right">Deal Value</th>
                <th class="text-center">Win %</th>
                <th>Target Close</th>
                <th>PIC</th>
                <th />
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in (pipelines?.data || [])" :key="row.id">
                <td class="font-mono text-xs">{{ row.code }}</td>
                <td class="font-medium">{{ row.title }}</td>
                <td class="text-sm">
                  <span v-if="row.customer_name">{{ row.customer_name }}</span>
                  <span v-else-if="row.lead_name" class="text-base-content/60">Lead: {{ row.lead_name }}</span>
                  <span v-else>—</span>
                </td>
                <td>
                  <span class="badge badge-sm" :class="stageBadge(row.stage)">{{ stageLabel(row.stage) }}</span>
                </td>
                <td class="text-right tabular-nums">{{ format(row.deal_value) }}</td>
                <td class="text-center">
                  <div class="flex items-center gap-1.5 justify-center">
                    <progress class="progress progress-primary w-12" :value="row.win_probability" max="100" />
                    <span class="text-xs tabular-nums">{{ row.win_probability }}%</span>
                  </div>
                </td>
                <td class="text-xs text-base-content/70">{{ formatDate(row.expected_close_date) }}</td>
                <td class="text-sm">{{ row.pic_name || '—' }}</td>
                <td class="text-right">
                  <button type="button" class="btn btn-ghost btn-xs" @click="openEdit(row)">Edit</button>
                  <button type="button" class="btn btn-ghost btn-xs text-error" @click="remove(row)">Hapus</button>
                </td>
              </tr>
              <tr v-if="!(pipelines?.data || []).length">
                <td colspan="9" class="py-8 text-center text-base-content/50">Belum ada data pipeline.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <DataTablePagination :paginator="pipelines" @update:per-page="(n) => { filters.per_page = n; }" />
      </div>
    </div>

    <!-- Modal Tambah Pipeline -->
    <dialog id="modal-add-pipeline" class="modal">
      <div class="modal-box max-w-lg">
        <h3 class="text-lg font-bold">Tambah Pipeline</h3>
        <div class="mt-4 grid grid-cols-1 gap-3">
          <div>
            <label class="label"><span class="label-text">Judul deal <span class="text-error">*</span></span></label>
            <input v-model="form.title" type="text" class="input input-bordered w-full" placeholder="Contoh: Project CCTV Kantor ABC" />
            <p v-if="form.errors.title" class="mt-1 text-xs text-error">{{ form.errors.title }}</p>
          </div>
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <label class="label"><span class="label-text">Customer</span></label>
              <select v-model="form.crm_customer_id" class="select select-bordered w-full">
                <option value="">— Pilih —</option>
                <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.company || c.name }}</option>
              </select>
            </div>
            <div>
              <label class="label"><span class="label-text">Dari Lead</span></label>
              <select v-model="form.crm_lead_id" class="select select-bordered w-full">
                <option value="">— Pilih —</option>
                <option v-for="l in leads" :key="l.id" :value="l.id">{{ l.name }}</option>
              </select>
            </div>
          </div>
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <label class="label"><span class="label-text">Stage <span class="text-error">*</span></span></label>
              <select v-model="form.stage" class="select select-bordered w-full">
                <option v-for="s in stageOptions" :key="s.value" :value="s.value">{{ s.label }}</option>
              </select>
            </div>
            <div>
              <label class="label"><span class="label-text">PIC</span></label>
              <select v-model="form.pic_user_id" class="select select-bordered w-full">
                <option value="">— Pilih PIC —</option>
                <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
              </select>
            </div>
          </div>
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div>
              <label class="label"><span class="label-text">Deal value</span></label>
              <input v-model.number="form.deal_value" type="number" min="0" step="1000" class="input input-bordered w-full" />
            </div>
            <div>
              <label class="label"><span class="label-text">Win %</span></label>
              <input v-model.number="form.win_probability" type="number" min="0" max="100" class="input input-bordered w-full" />
            </div>
            <div>
              <label class="label"><span class="label-text">Target close</span></label>
              <input v-model="form.expected_close_date" type="date" class="input input-bordered w-full" />
            </div>
          </div>
          <div>
            <label class="label"><span class="label-text">Catatan</span></label>
            <textarea v-model="form.notes" class="textarea textarea-bordered w-full" rows="2" />
          </div>
        </div>
        <div class="modal-action">
          <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
          <button type="button" class="btn btn-primary" :disabled="form.processing" @click="submitAdd">Simpan</button>
        </div>
      </div>
    </dialog>

    <!-- Modal Edit Pipeline -->
    <dialog id="modal-edit-pipeline" class="modal">
      <div class="modal-box max-w-lg">
        <h3 class="text-lg font-bold">Edit Pipeline</h3>
        <p v-if="selected" class="mt-1 text-sm text-base-content/60">Kode: <span class="font-mono">{{ selected.code }}</span></p>
        <div class="mt-4 grid grid-cols-1 gap-3">
          <div>
            <label class="label"><span class="label-text">Judul deal <span class="text-error">*</span></span></label>
            <input v-model="editForm.title" type="text" class="input input-bordered w-full" />
            <p v-if="editForm.errors.title" class="mt-1 text-xs text-error">{{ editForm.errors.title }}</p>
          </div>
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <label class="label"><span class="label-text">Customer</span></label>
              <select v-model="editForm.crm_customer_id" class="select select-bordered w-full">
                <option value="">— Pilih —</option>
                <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.company || c.name }}</option>
              </select>
            </div>
            <div>
              <label class="label"><span class="label-text">Dari Lead</span></label>
              <select v-model="editForm.crm_lead_id" class="select select-bordered w-full">
                <option value="">— Pilih —</option>
                <option v-for="l in leads" :key="l.id" :value="l.id">{{ l.name }}</option>
              </select>
            </div>
          </div>
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <label class="label"><span class="label-text">Stage <span class="text-error">*</span></span></label>
              <select v-model="editForm.stage" class="select select-bordered w-full">
                <option v-for="s in stageOptions" :key="s.value" :value="s.value">{{ s.label }}</option>
              </select>
            </div>
            <div>
              <label class="label"><span class="label-text">PIC</span></label>
              <select v-model="editForm.pic_user_id" class="select select-bordered w-full">
                <option value="">— Pilih PIC —</option>
                <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
              </select>
            </div>
          </div>
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div>
              <label class="label"><span class="label-text">Deal value</span></label>
              <input v-model.number="editForm.deal_value" type="number" min="0" step="1000" class="input input-bordered w-full" />
            </div>
            <div>
              <label class="label"><span class="label-text">Win %</span></label>
              <input v-model.number="editForm.win_probability" type="number" min="0" max="100" class="input input-bordered w-full" />
            </div>
            <div>
              <label class="label"><span class="label-text">Target close</span></label>
              <input v-model="editForm.expected_close_date" type="date" class="input input-bordered w-full" />
            </div>
          </div>
          <div>
            <label class="label"><span class="label-text">Catatan</span></label>
            <textarea v-model="editForm.notes" class="textarea textarea-bordered w-full" rows="2" />
          </div>
        </div>
        <div class="modal-action">
          <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
          <button type="button" class="btn btn-primary" :disabled="editForm.processing" @click="submitEdit">Simpan</button>
        </div>
      </div>
    </dialog>
  </AppLayout>
</template>
