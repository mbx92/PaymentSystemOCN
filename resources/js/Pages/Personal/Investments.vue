<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import CurrencyInput from '@/Components/CurrencyInput.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { computed, ref } from 'vue';
import { useDateFormat } from '@/composables/useDateFormat';

const { formatDate } = useDateFormat();

defineProps({
    investments: Array,
    assetTypes: Array,
});

const money = (n) => `Rp ${Number(n ?? 0).toLocaleString('id-ID')}`;

const flowLabel = (f) => ({
    deposit: 'Setoran',
    withdrawal: 'Penarikan',
    dividend: 'Dividen',
}[f] ?? f);

const addForm = useForm({
    name: '',
    asset_type: 'reksadana',
    institution: '',
    notes: '',
    opened_at: '',
    is_active: true,
});

const resetAddInvestmentForm = () => {
    addForm.clearErrors();
    addForm.reset('name', 'institution', 'notes', 'opened_at');
    addForm.asset_type = 'reksadana';
    addForm.is_active = true;
};

const openAddInv = () => {
    resetAddInvestmentForm();
    document.getElementById('modal-add-investment')?.showModal();
};

const submitAdd = () => {
    addForm.post(route('personal.investments.store'), {
        preserveScroll: true,
        onSuccess: () => {
            resetAddInvestmentForm();
            document.getElementById('modal-add-investment')?.close();
        },
    });
};

const editingInv = ref(null);
const editForm = useForm({
    name: '',
    asset_type: 'reksadana',
    institution: '',
    notes: '',
    opened_at: '',
    is_active: true,
});

const openEditInv = (inv) => {
    editingInv.value = inv;
    editForm.clearErrors();
    editForm.name = inv.name;
    editForm.asset_type = inv.asset_type;
    editForm.institution = inv.institution ?? '';
    editForm.notes = inv.notes ?? '';
    editForm.opened_at = inv.opened_at ?? '';
    editForm.is_active = !!inv.is_active;
    document.getElementById('modal-edit-investment')?.showModal();
};

const submitEditInv = () => {
    if (!editingInv.value) return;
    editForm.patch(route('personal.investments.update', editingInv.value.id), {
        preserveScroll: true,
        onSuccess: () => document.getElementById('modal-edit-investment')?.close(),
    });
};

const deletingInvestment = ref(null);
const deleteInvMessage = computed(() => (deletingInvestment.value
    ? `Yakin hapus investasi “${deletingInvestment.value.name}” beserta semua mutasinya?`
    : ''));

const confirmDeleteInv = (inv) => {
    deletingInvestment.value = inv;
    document.getElementById('modal-delete-personal-investment')?.showModal();
};

const doDeleteInv = () => {
    if (!deletingInvestment.value) return;
    router.delete(route('personal.investments.destroy', deletingInvestment.value.id), {
        preserveScroll: true,
        onFinish: () => { deletingInvestment.value = null; },
    });
};

const movementInv = ref(null);
const movForm = useForm({
    flow: 'deposit',
    amount: '',
    occurred_on: new Date().toISOString().slice(0, 10),
    note: '',
});

const resetMovementForm = () => {
    movForm.clearErrors();
    movForm.flow = 'deposit';
    movForm.amount = '';
    movForm.occurred_on = new Date().toISOString().slice(0, 10);
    movForm.note = '';
};

const openMovement = (inv) => {
    movementInv.value = inv;
    resetMovementForm();
    document.getElementById('modal-movement')?.showModal();
};

const submitMovement = () => {
    if (!movementInv.value) return;
    movForm.transform((d) => ({
        ...d,
        amount: Number(d.amount),
    })).post(route('personal.investments.movements.store', movementInv.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            resetMovementForm();
            movementInv.value = null;
            document.getElementById('modal-movement')?.close();
        },
    });
};

const assetLabel = (types, value) => types?.find((t) => t.value === value)?.label ?? value;
</script>

<template>
  <Head title="Personal — Investasi" />
  <AppLayout>
    <div class="space-y-5">
      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Personal</p>
              <h1 class="ocn-panel__title mt-1">Investasi</h1>
              <p class="ocn-panel__desc mt-1">Instrumen, arus kas bersih (setoran − penarikan + dividen), dan riwayat mutasi.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
              <div class="flex gap-2">
            <button type="button" class="btn btn-primary btn-sm" @click="openAddInv">+ Instrumen</button>
            <Link class="btn btn-ghost btn-sm shrink-0 gap-1.5" :href="route('personal')">
            <ArrowLeftIcon class="h-4 w-4" />
            Back
          </Link>
          </div>
            </div>
          </div>
        </div>
      </div>

      <div class="space-y-4">
        <div
          v-for="inv in investments"
          :key="inv.id"
          class="ocn-panel"
        >
          <div class="ocn-panel__head flex flex-wrap items-start justify-between gap-3">
            <div>
              <h2 class="ocn-panel__title">{{ inv.name }}</h2>
              <p class="ocn-panel__desc">
                <span class="badge badge-outline badge-sm">{{ assetLabel(assetTypes, inv.asset_type) }}</span>
                <span v-if="inv.institution" class="ml-2 text-sm">{{ inv.institution }}</span>
                <span class="ml-2 badge badge-sm" :class="inv.is_active ? 'badge-success' : 'badge-ghost'">{{ inv.is_active ? 'aktif' : 'ditutup' }}</span>
              </p>
              <p class="mt-2 text-sm font-semibold">Arus kas tercatat: <span class="font-mono" :class="inv.net_flow >= 0 ? 'text-success' : 'text-error'">{{ money(inv.net_flow) }}</span></p>
            </div>
            <div class="flex flex-wrap gap-2">
              <button type="button" class="btn btn-outline btn-xs" @click="openMovement(inv)">+ Mutasi</button>
              <button type="button" class="btn btn-ghost btn-xs" @click="openEditInv(inv)">Edit</button>
              <button type="button" class="btn btn-ghost btn-xs text-error" @click="confirmDeleteInv(inv)">Hapus</button>
            </div>
          </div>
          <div v-if="inv.notes" class="px-6 pb-2 text-sm text-base-content/70">{{ inv.notes }}</div>
          <div class="overflow-x-auto">
            <table class="table table-sm">
              <thead>
                <tr>
                  <th>Tanggal</th>
                  <th>Alur</th>
                  <th class="text-right">Jumlah</th>
                  <th>Catatan</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="m in inv.movements" :key="m.id">
                  <td class="text-xs whitespace-nowrap">{{ formatDate(m.occurred_on) }}</td>
                  <td><span class="badge badge-ghost badge-sm">{{ flowLabel(m.flow) }}</span></td>
                  <td class="text-right font-mono">{{ money(m.amount) }}</td>
                  <td class="text-sm text-base-content/70">{{ m.note || '—' }}</td>
                </tr>
                <tr v-if="!(inv.movements && inv.movements.length)">
                  <td colspan="4" class="text-center text-base-content/50 py-4">Belum ada mutasi (tampilan 80 terbaru).</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <p v-if="!(investments && investments.length)" class="text-center text-base-content/50 py-12 rounded-2xl border border-dashed border-base-300">
          Belum ada instrumen investasi. Tambahkan untuk mulai mencatat setoran dan dividen.
        </p>
      </div>
    </div>

    <dialog id="modal-add-investment" class="modal">
      <div class="modal-box max-w-lg">
        <h3 class="font-bold text-lg">Instrumen baru</h3>
        <div class="mt-4 grid gap-3 sm:grid-cols-2">
          <div class="sm:col-span-2">
            <label class="label"><span class="label-text">Nama</span></label>
            <input v-model="addForm.name" type="text" class="input input-bordered w-full" />
            <p v-if="addForm.errors.name" class="text-error text-xs mt-1">{{ addForm.errors.name }}</p>
          </div>
          <div class="sm:col-span-2">
            <label class="label"><span class="label-text">Jenis aset</span></label>
            <select v-model="addForm.asset_type" class="select select-bordered w-full">
              <option v-for="t in assetTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
            </select>
          </div>
          <div class="sm:col-span-2">
            <label class="label"><span class="label-text">Institusi / platform</span></label>
            <input v-model="addForm.institution" type="text" class="input input-bordered w-full" />
          </div>
          <div class="sm:col-span-2">
            <label class="label"><span class="label-text">Catatan</span></label>
            <textarea v-model="addForm.notes" class="textarea textarea-bordered w-full" rows="2" />
          </div>
          <div>
            <label class="label"><span class="label-text">Mulai (opsional)</span></label>
            <input v-model="addForm.opened_at" type="date" class="input input-bordered w-full" />
          </div>
          <div class="flex items-end">
            <label class="label cursor-pointer justify-start gap-3 w-full">
              <input v-model="addForm.is_active" type="checkbox" class="toggle toggle-success" />
              <span class="label-text">Masih aktif</span>
            </label>
          </div>
        </div>
        <div class="modal-action">
          <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
          <button
            class="btn"
            :class="addForm.processing ? 'btn-secondary' : 'btn-primary'"
            :disabled="addForm.processing"
            @click="submitAdd"
          >Simpan</button>
        </div>
      </div>
    </dialog>

    <dialog id="modal-edit-investment" class="modal">
      <div class="modal-box max-w-lg">
        <h3 class="font-bold text-lg">Edit instrumen</h3>
        <div class="mt-4 grid gap-3 sm:grid-cols-2">
          <div class="sm:col-span-2">
            <label class="label"><span class="label-text">Nama</span></label>
            <input v-model="editForm.name" type="text" class="input input-bordered w-full" />
          </div>
          <div class="sm:col-span-2">
            <label class="label"><span class="label-text">Jenis aset</span></label>
            <select v-model="editForm.asset_type" class="select select-bordered w-full">
              <option v-for="t in assetTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
            </select>
          </div>
          <div class="sm:col-span-2">
            <label class="label"><span class="label-text">Institusi / platform</span></label>
            <input v-model="editForm.institution" type="text" class="input input-bordered w-full" />
          </div>
          <div class="sm:col-span-2">
            <label class="label"><span class="label-text">Catatan</span></label>
            <textarea v-model="editForm.notes" class="textarea textarea-bordered w-full" rows="2" />
          </div>
          <div>
            <label class="label"><span class="label-text">Mulai</span></label>
            <input v-model="editForm.opened_at" type="date" class="input input-bordered w-full" />
          </div>
          <div class="flex items-end">
            <label class="label cursor-pointer justify-start gap-3 w-full">
              <input v-model="editForm.is_active" type="checkbox" class="toggle toggle-success" />
              <span class="label-text">Aktif</span>
            </label>
          </div>
        </div>
        <div class="modal-action">
          <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
          <button
            class="btn"
            :class="editForm.processing ? 'btn-secondary' : 'btn-primary'"
            :disabled="editForm.processing"
            @click="submitEditInv"
          >Simpan</button>
        </div>
      </div>
    </dialog>

    <dialog id="modal-movement" class="modal">
      <div class="modal-box max-w-md">
        <h3 class="font-bold text-lg">Mutasi</h3>
        <p v-if="movementInv" class="text-sm text-base-content/70 mt-1">{{ movementInv.name }}</p>
        <div class="mt-4 space-y-3">
          <div>
            <label class="label"><span class="label-text">Alur</span></label>
            <select v-model="movForm.flow" class="select select-bordered w-full">
              <option value="deposit">Setoran / pembelian</option>
              <option value="withdrawal">Penarikan / pencairan</option>
              <option value="dividend">Dividen / bunga</option>
            </select>
          </div>
          <div>
            <CurrencyInput
              v-model="movForm.amount"
              label="Jumlah"
              placeholder="0"
              :error="movForm.errors.amount"
            />
          </div>
          <div>
            <label class="label"><span class="label-text">Tanggal</span></label>
            <input v-model="movForm.occurred_on" type="date" class="input input-bordered w-full" />
          </div>
          <div>
            <label class="label"><span class="label-text">Catatan</span></label>
            <input v-model="movForm.note" type="text" class="input input-bordered w-full" />
          </div>
        </div>
        <div class="modal-action">
          <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
          <button
            class="btn"
            :class="movForm.processing ? 'btn-secondary' : 'btn-primary'"
            :disabled="movForm.processing"
            @click="submitMovement"
          >Simpan</button>
        </div>
      </div>
    </dialog>

    <ConfirmModal
      id="modal-delete-personal-investment"
      title="Hapus investasi"
      :message="deleteInvMessage"
      @confirm="doDeleteInv"
    />
  </AppLayout>
</template>
