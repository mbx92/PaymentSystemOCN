<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTablePagination from '@/Components/DataTablePagination.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { reactive, ref, watch } from 'vue';
import { useDateFormat } from '@/composables/useDateFormat';

const props = defineProps({
  activities: Object,
  leads: Array,
  customers: Array,
  pipelines: Array,
  filters: Object,
});

const { formatDate, formatDateTime } = useDateFormat();

const filters = reactive({
  q: props.filters?.q ?? '',
  type: props.filters?.type ?? '',
  status: props.filters?.status ?? '',
  per_page: props.filters?.per_page ?? props.activities?.per_page ?? 25,
});

let timer;
watch(
  filters,
  (val) => {
    clearTimeout(timer);
    timer = setTimeout(() => {
      router.get(route('erp.crm.activities'), val, { preserveState: true, replace: true });
    }, 250);
  },
  { deep: true },
);

const typeOptions = [
  { value: 'call', label: 'Call', icon: '📞' },
  { value: 'email', label: 'Email', icon: '📧' },
  { value: 'chat', label: 'Chat / WA', icon: '💬' },
  { value: 'meeting', label: 'Meeting', icon: '🤝' },
  { value: 'visit', label: 'Kunjungan', icon: '🚗' },
  { value: 'demo', label: 'Demo / Presentasi', icon: '📊' },
  { value: 'other', label: 'Lainnya', icon: '📋' },
];

const statusOptions = [
  { value: 'planned', label: 'Planned', color: 'badge-info' },
  { value: 'in_progress', label: 'In Progress', color: 'badge-warning' },
  { value: 'completed', label: 'Completed', color: 'badge-success' },
  { value: 'cancelled', label: 'Cancelled', color: 'badge-error' },
];

const statusBadge = (status) => statusOptions.find((s) => s.value === status)?.color ?? 'badge-ghost';
const statusLabel = (status) => statusOptions.find((s) => s.value === status)?.label ?? status;
const typeIcon = (type) => typeOptions.find((t) => t.value === type)?.icon ?? '📋';
const typeLabel = (type) => typeOptions.find((t) => t.value === type)?.label ?? type;

const nowDatetime = () => {
  const d = new Date();
  d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
  return d.toISOString().slice(0, 16);
};

const form = useForm({
  type: 'call',
  subject: '',
  description: '',
  activity_date: nowDatetime(),
  next_action_date: '',
  next_action_note: '',
  status: 'planned',
  crm_lead_id: '',
  crm_customer_id: '',
  crm_pipeline_id: '',
});

const editForm = useForm({
  type: 'call',
  subject: '',
  description: '',
  activity_date: '',
  next_action_date: '',
  next_action_note: '',
  status: 'planned',
  crm_lead_id: '',
  crm_customer_id: '',
  crm_pipeline_id: '',
});

const selected = ref(null);

const resetAddForm = () => {
  form.clearErrors();
  form.reset();
  form.type = 'call';
  form.status = 'planned';
  form.activity_date = nowDatetime();
  form.crm_lead_id = '';
  form.crm_customer_id = '';
  form.crm_pipeline_id = '';
};

const openAdd = () => {
  resetAddForm();
  document.getElementById('modal-add-activity')?.showModal();
};

const submitAdd = () => {
  form.post(route('erp.crm.activities.store'), {
    preserveScroll: true,
    onSuccess: () => {
      resetAddForm();
      document.getElementById('modal-add-activity')?.close();
    },
  });
};

const openEdit = (row) => {
  selected.value = row;
  editForm.clearErrors();
  editForm.type = row.type;
  editForm.subject = row.subject;
  editForm.description = row.description || '';
  editForm.activity_date = row.activity_date?.replace(' ', 'T') || '';
  editForm.next_action_date = row.next_action_date?.replace(' ', 'T') || '';
  editForm.next_action_note = row.next_action_note || '';
  editForm.status = row.status;
  editForm.crm_lead_id = row.crm_lead_id ?? '';
  editForm.crm_customer_id = row.crm_customer_id ?? '';
  editForm.crm_pipeline_id = row.crm_pipeline_id ?? '';
  document.getElementById('modal-edit-activity')?.showModal();
};

const submitEdit = () => {
  if (!selected.value) return;
  editForm.patch(route('erp.crm.activities.update', selected.value.id), {
    preserveScroll: true,
    onSuccess: () => document.getElementById('modal-edit-activity')?.close(),
  });
};

const remove = (row) => {
  if (!confirm(`Hapus aktivitas "${row.subject}"?`)) return;
  router.delete(route('erp.crm.activities.destroy', row.id), { preserveScroll: true });
};
</script>

<template>
  <Head title="CRM — Aktivitas Follow-up" />
  <AppLayout>
    <div class="space-y-5">
      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">CRM Workspace</p>
              <h1 class="ocn-panel__title mt-1">Aktivitas Follow-up</h1>
              <p class="ocn-panel__desc mt-1">Log call, chat, meeting, reminder, dan next action untuk setiap prospek/customer.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
              <div class="flex flex-wrap items-center gap-2">
            <button type="button" class="btn btn-primary btn-sm" @click="openAdd">+ Catat aktivitas</button>
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
          <h2 class="ocn-panel__title">Filter aktivitas</h2>
        </div>
        <div class="card-body">
          <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
            <div class="md:col-span-2">
              <label class="label"><span class="label-text text-xs uppercase tracking-wide">Search</span></label>
              <input v-model="filters.q" type="text" class="input input-sm input-bordered w-full" placeholder="Subjek atau deskripsi..." />
            </div>
            <div>
              <label class="label"><span class="label-text text-xs uppercase tracking-wide">Tipe</span></label>
              <select v-model="filters.type" class="select select-sm select-bordered w-full">
                <option value="">Semua</option>
                <option v-for="t in typeOptions" :key="t.value" :value="t.value">{{ t.icon }} {{ t.label }}</option>
              </select>
            </div>
            <div>
              <label class="label"><span class="label-text text-xs uppercase tracking-wide">Status</span></label>
              <select v-model="filters.status" class="select select-sm select-bordered w-full">
                <option value="">Semua</option>
                <option v-for="s in statusOptions" :key="s.value" :value="s.value">{{ s.label }}</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Log aktivitas</h2>
          <p class="ocn-panel__desc">{{ activities?.total ?? 0 }} aktivitas ditemukan.</p>
        </div>
        <div class="overflow-x-auto">
          <table class="table table-zebra table-sm">
            <thead>
              <tr>
                <th>Tipe</th>
                <th>Subjek</th>
                <th>Lead / Customer</th>
                <th>Pipeline</th>
                <th>Tanggal</th>
                <th>Next Action</th>
                <th>Status</th>
                <th>Oleh</th>
                <th />
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in (activities?.data || [])" :key="row.id">
                <td>
                  <span class="text-base" :title="typeLabel(row.type)">{{ typeIcon(row.type) }}</span>
                </td>
                <td class="font-medium max-w-[14rem] truncate">{{ row.subject }}</td>
                <td class="text-sm">
                  <span v-if="row.customer_name">{{ row.customer_name }}</span>
                  <span v-else-if="row.lead_name" class="text-base-content/60">{{ row.lead_name }}</span>
                  <span v-else>—</span>
                </td>
                <td class="text-xs text-base-content/70">{{ row.pipeline_title || '—' }}</td>
                <td class="text-xs text-base-content/70 whitespace-nowrap">{{ formatDateTime(row.activity_date) }}</td>
                <td class="text-xs">
                  <template v-if="row.next_action_date">
                    <span class="font-medium text-warning">{{ formatDate(row.next_action_date) }}</span>
                    <span v-if="row.next_action_note" class="block text-base-content/50 truncate max-w-[10rem]">{{ row.next_action_note }}</span>
                  </template>
                  <span v-else>—</span>
                </td>
                <td>
                  <span class="badge badge-sm" :class="statusBadge(row.status)">{{ statusLabel(row.status) }}</span>
                </td>
                <td class="text-sm">{{ row.user_name }}</td>
                <td class="text-right">
                  <button type="button" class="btn btn-ghost btn-xs" @click="openEdit(row)">Edit</button>
                  <button type="button" class="btn btn-ghost btn-xs text-error" @click="remove(row)">Hapus</button>
                </td>
              </tr>
              <tr v-if="!(activities?.data || []).length">
                <td colspan="9" class="py-8 text-center text-base-content/50">Belum ada aktivitas.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <DataTablePagination :paginator="activities" @update:per-page="(n) => { filters.per_page = n; }" />
      </div>
    </div>

    <!-- Modal Tambah Aktivitas -->
    <dialog id="modal-add-activity" class="modal">
      <div class="modal-box max-w-lg">
        <h3 class="text-lg font-bold">Catat Aktivitas</h3>
        <div class="mt-4 grid grid-cols-1 gap-3">
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <label class="label"><span class="label-text">Tipe <span class="text-error">*</span></span></label>
              <select v-model="form.type" class="select select-bordered w-full">
                <option v-for="t in typeOptions" :key="t.value" :value="t.value">{{ t.icon }} {{ t.label }}</option>
              </select>
            </div>
            <div>
              <label class="label"><span class="label-text">Status <span class="text-error">*</span></span></label>
              <select v-model="form.status" class="select select-bordered w-full">
                <option v-for="s in statusOptions" :key="s.value" :value="s.value">{{ s.label }}</option>
              </select>
            </div>
          </div>
          <div>
            <label class="label"><span class="label-text">Subjek <span class="text-error">*</span></span></label>
            <input v-model="form.subject" type="text" class="input input-bordered w-full" placeholder="Ringkasan aktivitas" />
            <p v-if="form.errors.subject" class="mt-1 text-xs text-error">{{ form.errors.subject }}</p>
          </div>
          <div>
            <label class="label"><span class="label-text">Tanggal aktivitas <span class="text-error">*</span></span></label>
            <input v-model="form.activity_date" type="datetime-local" class="input input-bordered w-full" />
            <p v-if="form.errors.activity_date" class="mt-1 text-xs text-error">{{ form.errors.activity_date }}</p>
          </div>
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div>
              <label class="label"><span class="label-text">Lead</span></label>
              <select v-model="form.crm_lead_id" class="select select-bordered w-full">
                <option value="">— Pilih —</option>
                <option v-for="l in leads" :key="l.id" :value="l.id">{{ l.name }}</option>
              </select>
            </div>
            <div>
              <label class="label"><span class="label-text">Customer</span></label>
              <select v-model="form.crm_customer_id" class="select select-bordered w-full">
                <option value="">— Pilih —</option>
                <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.company || c.name }}</option>
              </select>
            </div>
            <div>
              <label class="label"><span class="label-text">Pipeline</span></label>
              <select v-model="form.crm_pipeline_id" class="select select-bordered w-full">
                <option value="">— Pilih —</option>
                <option v-for="p in pipelines" :key="p.id" :value="p.id">{{ p.code }}</option>
              </select>
            </div>
          </div>
          <div>
            <label class="label"><span class="label-text">Deskripsi</span></label>
            <textarea v-model="form.description" class="textarea textarea-bordered w-full" rows="3" placeholder="Detail aktivitas..." />
          </div>
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <label class="label"><span class="label-text">Next action date</span></label>
              <input v-model="form.next_action_date" type="datetime-local" class="input input-bordered w-full" />
            </div>
            <div>
              <label class="label"><span class="label-text">Catatan next action</span></label>
              <input v-model="form.next_action_note" type="text" class="input input-bordered w-full" placeholder="Follow-up berikutnya..." />
            </div>
          </div>
        </div>
        <div class="modal-action">
          <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
          <button type="button" class="btn btn-primary" :disabled="form.processing" @click="submitAdd">Simpan</button>
        </div>
      </div>
    </dialog>

    <!-- Modal Edit Aktivitas -->
    <dialog id="modal-edit-activity" class="modal">
      <div class="modal-box max-w-lg">
        <h3 class="text-lg font-bold">Edit Aktivitas</h3>
        <div class="mt-4 grid grid-cols-1 gap-3">
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <label class="label"><span class="label-text">Tipe <span class="text-error">*</span></span></label>
              <select v-model="editForm.type" class="select select-bordered w-full">
                <option v-for="t in typeOptions" :key="t.value" :value="t.value">{{ t.icon }} {{ t.label }}</option>
              </select>
            </div>
            <div>
              <label class="label"><span class="label-text">Status <span class="text-error">*</span></span></label>
              <select v-model="editForm.status" class="select select-bordered w-full">
                <option v-for="s in statusOptions" :key="s.value" :value="s.value">{{ s.label }}</option>
              </select>
            </div>
          </div>
          <div>
            <label class="label"><span class="label-text">Subjek <span class="text-error">*</span></span></label>
            <input v-model="editForm.subject" type="text" class="input input-bordered w-full" />
            <p v-if="editForm.errors.subject" class="mt-1 text-xs text-error">{{ editForm.errors.subject }}</p>
          </div>
          <div>
            <label class="label"><span class="label-text">Tanggal aktivitas <span class="text-error">*</span></span></label>
            <input v-model="editForm.activity_date" type="datetime-local" class="input input-bordered w-full" />
          </div>
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div>
              <label class="label"><span class="label-text">Lead</span></label>
              <select v-model="editForm.crm_lead_id" class="select select-bordered w-full">
                <option value="">— Pilih —</option>
                <option v-for="l in leads" :key="l.id" :value="l.id">{{ l.name }}</option>
              </select>
            </div>
            <div>
              <label class="label"><span class="label-text">Customer</span></label>
              <select v-model="editForm.crm_customer_id" class="select select-bordered w-full">
                <option value="">— Pilih —</option>
                <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.company || c.name }}</option>
              </select>
            </div>
            <div>
              <label class="label"><span class="label-text">Pipeline</span></label>
              <select v-model="editForm.crm_pipeline_id" class="select select-bordered w-full">
                <option value="">— Pilih —</option>
                <option v-for="p in pipelines" :key="p.id" :value="p.id">{{ p.code }}</option>
              </select>
            </div>
          </div>
          <div>
            <label class="label"><span class="label-text">Deskripsi</span></label>
            <textarea v-model="editForm.description" class="textarea textarea-bordered w-full" rows="3" />
          </div>
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <label class="label"><span class="label-text">Next action date</span></label>
              <input v-model="editForm.next_action_date" type="datetime-local" class="input input-bordered w-full" />
            </div>
            <div>
              <label class="label"><span class="label-text">Catatan next action</span></label>
              <input v-model="editForm.next_action_note" type="text" class="input input-bordered w-full" />
            </div>
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
