<script setup>
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

defineProps({
    profiles: { type: Array, default: () => [] },
});

const form = useForm({
    name: '',
    width_mm: 76.2,
    height_mm: 127,
    dpi: 203,
    margin_left_mm: 4,
    margin_top_mm: 4,
    gap_mm: 3,
    protocol: 'zpl',
});

const submit = () => {
    form.post(route('erp.admin.label-profiles.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            form.width_mm = 76.2;
            form.height_mm = 127;
            form.dpi = 203;
            form.margin_left_mm = 4;
            form.margin_top_mm = 4;
            form.gap_mm = 3;
            form.protocol = 'zpl';
            document.getElementById('modal-add-label-profile')?.close();
        },
    });
};

const openAddModal = () => {
    form.clearErrors();
    form.reset();
    form.width_mm = 76.2;
    form.height_mm = 127;
    form.dpi = 203;
    form.margin_left_mm = 4;
    form.margin_top_mm = 4;
    form.gap_mm = 3;
    form.protocol = 'zpl';
    document.getElementById('modal-add-label-profile')?.showModal();
};

const editing = ref(null);
const editForm = useForm({
    name: '',
    width_mm: 0,
    height_mm: 0,
    dpi: 203,
    margin_left_mm: 0,
    margin_top_mm: 0,
    gap_mm: 0,
    protocol: 'zpl',
});

const openEditModal = (row) => {
    editing.value = row;
    editForm.name = row.name;
    editForm.width_mm = Number(row.width_mm);
    editForm.height_mm = Number(row.height_mm);
    editForm.dpi = row.dpi;
    editForm.margin_left_mm = Number(row.margin_left_mm);
    editForm.margin_top_mm = Number(row.margin_top_mm);
    editForm.gap_mm = Number(row.gap_mm);
    editForm.protocol = row.protocol;
    document.getElementById('modal-edit-label-profile')?.showModal();
};

const submitEdit = () => {
    if (!editing.value) return;
    editForm.patch(route('erp.admin.label-profiles.update', editing.value.id), {
        preserveScroll: true,
        onSuccess: () => document.getElementById('modal-edit-label-profile')?.close(),
    });
};

const deletingRow = ref(null);
const deleteMessage = computed(() => (deletingRow.value
    ? `Yakin hapus profil “${deletingRow.value.name}”?`
    : ''));

const confirmDelete = (row) => {
    deletingRow.value = row;
    document.getElementById('modal-delete-label-profile')?.showModal();
};

const doDelete = () => {
    if (!deletingRow.value) return;
    router.delete(route('erp.admin.label-profiles.destroy', deletingRow.value.id), {
        preserveScroll: true,
        onFinish: () => { deletingRow.value = null; },
    });
};
</script>

<template>
  <div class="space-y-5">
    <div class="ocn-panel">
      <div class="ocn-panel__head flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 class="ocn-panel__title">Profil label</h2>
          <p class="ocn-panel__desc">
            Satu profil = kombinasi <strong>lebar × tinggi (mm)</strong>, <strong>DPI</strong>, margin, gap, dan <strong>ZPL / EPL</strong>.
            Dipakai untuk uji cetak SMB/LAN dan cetak label dari modul lain.
          </p>
        </div>
        <button type="button" class="btn btn-primary btn-sm" @click="openAddModal">Tambah profil</button>
      </div>
      <div class="overflow-x-auto">
        <table class="table table-sm">
          <thead>
            <tr>
              <th>Nama</th>
              <th>Lebar mm</th>
              <th>Tinggi mm</th>
              <th>DPI</th>
              <th>Margin L/T mm</th>
              <th>Gap mm</th>
              <th>ZPL/EPL</th>
              <th class="w-28" />
            </tr>
          </thead>
          <tbody>
            <tr v-for="p in profiles" :key="p.id">
              <td class="font-medium">{{ p.name }}</td>
              <td>{{ p.width_mm }}</td>
              <td>{{ p.height_mm }}</td>
              <td>{{ p.dpi }}</td>
              <td>{{ p.margin_left_mm }} / {{ p.margin_top_mm }}</td>
              <td>{{ p.gap_mm }}</td>
              <td class="uppercase">{{ p.protocol }}</td>
              <td>
                <button type="button" class="btn btn-ghost btn-xs" @click="openEditModal(p)">Edit</button>
                <button type="button" class="btn btn-ghost btn-xs text-error" @click="confirmDelete(p)">Hapus</button>
              </td>
            </tr>
            <tr v-if="!profiles?.length">
              <td colspan="8" class="text-center text-sm text-base-content/60">Belum ada profil.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <p class="text-xs text-base-content/60">
      Contoh: label 3×5 inch ≈ 76,2 × 127 mm. Sesuaikan DPI dengan resolusi head printer (umum 203 atau 300).
    </p>

    <dialog id="modal-add-label-profile" class="modal">
      <div class="modal-box max-w-lg">
        <h3 class="font-bold text-lg">Profil label baru</h3>
        <div class="mt-4 grid gap-3">
          <div class="space-y-1">
            <label class="label-text text-xs">Nama</label>
            <input v-model="form.name" type="text" class="input input-bordered input-sm w-full">
            <p v-if="form.errors.name" class="text-xs text-error">{{ form.errors.name }}</p>
          </div>
          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="label-text text-xs">Lebar (mm)</label>
              <input v-model.number="form.width_mm" type="number" step="0.01" min="10" class="input input-bordered input-sm w-full">
              <p v-if="form.errors.width_mm" class="text-xs text-error">{{ form.errors.width_mm }}</p>
            </div>
            <div>
              <label class="label-text text-xs">Tinggi (mm)</label>
              <input v-model.number="form.height_mm" type="number" step="0.01" min="10" class="input input-bordered input-sm w-full">
              <p v-if="form.errors.height_mm" class="text-xs text-error">{{ form.errors.height_mm }}</p>
            </div>
          </div>
          <div>
            <label class="label-text text-xs">DPI</label>
            <select v-model.number="form.dpi" class="select select-bordered select-sm w-full">
              <option :value="203">203</option>
              <option :value="300">300</option>
              <option :value="600">600</option>
            </select>
            <p v-if="form.errors.dpi" class="text-xs text-error">{{ form.errors.dpi }}</p>
          </div>
          <div class="grid grid-cols-3 gap-2">
            <div>
              <label class="label-text text-xs">Margin kiri</label>
              <input v-model.number="form.margin_left_mm" type="number" step="0.1" min="0" class="input input-bordered input-sm w-full">
            </div>
            <div>
              <label class="label-text text-xs">Margin atas</label>
              <input v-model.number="form.margin_top_mm" type="number" step="0.1" min="0" class="input input-bordered input-sm w-full">
            </div>
            <div>
              <label class="label-text text-xs">Gap</label>
              <input v-model.number="form.gap_mm" type="number" step="0.1" min="0" class="input input-bordered input-sm w-full">
            </div>
          </div>
          <div class="flex gap-4">
            <label class="label cursor-pointer gap-2">
              <input v-model="form.protocol" type="radio" class="radio radio-sm" value="zpl"> ZPL
            </label>
            <label class="label cursor-pointer gap-2">
              <input v-model="form.protocol" type="radio" class="radio radio-sm" value="epl"> EPL
            </label>
          </div>
        </div>
        <div class="modal-action">
          <form method="dialog"><button class="btn">Batal</button></form>
          <button
            class="btn"
            :class="form.processing ? 'btn-secondary' : 'btn-primary'"
            :disabled="form.processing"
            @click="submit"
          >Simpan</button>
        </div>
      </div>
      <form method="dialog" class="modal-backdrop"><button>close</button></form>
    </dialog>

    <dialog id="modal-edit-label-profile" class="modal">
      <div class="modal-box max-w-lg">
        <h3 class="font-bold text-lg">Edit profil</h3>
        <div class="mt-4 grid gap-3">
          <div>
            <label class="label-text text-xs">Nama</label>
            <input v-model="editForm.name" type="text" class="input input-bordered input-sm w-full">
            <p v-if="editForm.errors.name" class="text-xs text-error">{{ editForm.errors.name }}</p>
          </div>
          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="label-text text-xs">Lebar (mm)</label>
              <input v-model.number="editForm.width_mm" type="number" step="0.01" min="10" class="input input-bordered input-sm w-full">
            </div>
            <div>
              <label class="label-text text-xs">Tinggi (mm)</label>
              <input v-model.number="editForm.height_mm" type="number" step="0.01" min="10" class="input input-bordered input-sm w-full">
            </div>
          </div>
          <div>
            <label class="label-text text-xs">DPI</label>
            <select v-model.number="editForm.dpi" class="select select-bordered select-sm w-full">
              <option :value="203">203</option>
              <option :value="300">300</option>
              <option :value="600">600</option>
            </select>
          </div>
          <div class="grid grid-cols-3 gap-2">
            <div>
              <label class="label-text text-xs">Margin kiri</label>
              <input v-model.number="editForm.margin_left_mm" type="number" step="0.1" min="0" class="input input-bordered input-sm w-full">
            </div>
            <div>
              <label class="label-text text-xs">Margin atas</label>
              <input v-model.number="editForm.margin_top_mm" type="number" step="0.1" min="0" class="input input-bordered input-sm w-full">
            </div>
            <div>
              <label class="label-text text-xs">Gap</label>
              <input v-model.number="editForm.gap_mm" type="number" step="0.1" min="0" class="input input-bordered input-sm w-full">
            </div>
          </div>
          <div class="flex gap-4">
            <label class="label cursor-pointer gap-2">
              <input v-model="editForm.protocol" type="radio" class="radio radio-sm" value="zpl"> ZPL
            </label>
            <label class="label cursor-pointer gap-2">
              <input v-model="editForm.protocol" type="radio" class="radio radio-sm" value="epl"> EPL
            </label>
          </div>
        </div>
        <div class="modal-action">
          <form method="dialog"><button class="btn">Tutup</button></form>
          <button
            class="btn"
            :class="editForm.processing ? 'btn-secondary' : 'btn-primary'"
            :disabled="editForm.processing"
            @click="submitEdit"
          >Simpan</button>
        </div>
      </div>
      <form method="dialog" class="modal-backdrop"><button>close</button></form>
    </dialog>

    <ConfirmModal
      id="modal-delete-label-profile"
      title="Hapus profil label"
      :message="deleteMessage"
      @confirm="doDelete"
    />
  </div>
</template>
