<script setup>
import ConfirmModal from '@/Components/ConfirmModal.vue';
import LabelRollPreview from '@/Components/ERP/LabelRollPreview.vue';
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

defineProps({
    profiles: { type: Array, default: () => [] },
});

const formDefaults = {
    width_mm: 76.2, height_mm: 127, dpi: 203,
    margin_left_mm: 4, margin_top_mm: 4, gap_mm: 3, rows: 1, protocol: 'tspl',
};

const form = useForm({ name: '', ...formDefaults });

const applyDefaults = (f) => Object.assign(f, formDefaults);

const submit = () => {
    form.post(route('erp.admin.label-profiles.store'), {
        preserveScroll: true,
        onSuccess: () => { form.reset(); applyDefaults(form); document.getElementById('modal-add-label-profile')?.close(); },
    });
};

const openAddModal = () => {
    form.clearErrors();
    form.reset();
    applyDefaults(form);
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
    rows: 1,
    protocol: 'tspl',
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
    editForm.rows = row.rows ?? 1;
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

const previewRows = ref(1);
const quickPreviewRows = ref(1);
const quickPreviewProfile = ref(null);

const openQuickPreview = (row) => {
    quickPreviewProfile.value = row;
    quickPreviewRows.value = row.rows ?? 1;
    document.getElementById('modal-label-quick-preview')?.showModal();
};

const simulationLoading = ref(false);
const simulationError = ref('');
const simulationData = ref(null);

const openSimulationModal = async (row) => {
    simulationLoading.value = true;
    simulationError.value = '';
    simulationData.value = null;
    previewRows.value = row.rows ?? 1;
    document.getElementById('modal-label-profile-simulation')?.showModal();

    try {
        const response = await fetch(route('erp.admin.label-profiles.simulation', row.id), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        const payload = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(payload?.message || 'Gagal memuat simulasi label.');
        }

        simulationData.value = payload;
    } catch (error) {
        simulationError.value = error instanceof Error ? error.message : 'Gagal memuat simulasi label.';
    } finally {
        simulationLoading.value = false;
    }
};
</script>

<template>
  <div class="space-y-5">
    <div class="ocn-panel">
      <div class="ocn-panel__head flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 class="ocn-panel__title">Profil label</h2>
          <p class="ocn-panel__desc">
            Satu profil = kombinasi <strong>lebar × tinggi (mm)</strong>, <strong>DPI</strong>, margin, gap, dan <strong>ZPL / EPL / TSPL</strong>.
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
              <th>Row</th>
              <th>Protocol</th>
              <th class="w-40" />
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
              <td>{{ p.rows ?? 1 }}</td>
              <td class="uppercase">{{ p.protocol }}</td>
              <td>
                <button type="button" class="btn btn-ghost btn-xs" @click="openQuickPreview(p)">Preview</button>
                <button type="button" class="btn btn-ghost btn-xs" @click="openSimulationModal(p)">Simulasi</button>
                <button type="button" class="btn btn-ghost btn-xs" @click="openEditModal(p)">Edit</button>
                <button type="button" class="btn btn-ghost btn-xs text-error" @click="confirmDelete(p)">Hapus</button>
              </td>
            </tr>
            <tr v-if="!profiles?.length">
              <td colspan="9" class="text-center text-sm text-base-content/60">Belum ada profil.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <p class="text-xs text-base-content/60">
      Contoh: label 3×5 inch ˜ 76,2 × 127 mm. Sesuaikan DPI dengan resolusi head printer (umum 203 atau 300).
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
          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="label-text text-xs">Row pada roll</label>
              <select v-model.number="form.rows" class="select select-bordered select-sm w-full">
                <option :value="1">1</option>
                <option :value="2">2</option>
                <option :value="3">3</option>
              </select>
              <p class="text-[10px] text-base-content/50 mt-0.5">Jumlah label berdampingan pada roll.</p>
              <p v-if="form.errors.rows" class="text-xs text-error">{{ form.errors.rows }}</p>
            </div>
            <div>
              <label class="label-text text-xs">Protocol</label>
              <div class="flex gap-3 mt-1.5">
                <label class="label cursor-pointer gap-1.5 p-0">
                  <input v-model="form.protocol" type="radio" class="radio radio-sm" value="tspl"> <span class="label-text text-xs">TSPL</span>
                </label>
                <label class="label cursor-pointer gap-1.5 p-0">
                  <input v-model="form.protocol" type="radio" class="radio radio-sm" value="zpl"> <span class="label-text text-xs">ZPL</span>
                </label>
                <label class="label cursor-pointer gap-1.5 p-0">
                  <input v-model="form.protocol" type="radio" class="radio radio-sm" value="epl"> <span class="label-text text-xs">EPL</span>
                </label>
              </div>
            </div>
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
          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="label-text text-xs">Row pada roll</label>
              <select v-model.number="editForm.rows" class="select select-bordered select-sm w-full">
                <option :value="1">1</option>
                <option :value="2">2</option>
                <option :value="3">3</option>
              </select>
              <p class="text-[10px] text-base-content/50 mt-0.5">Jumlah label berdampingan pada roll.</p>
              <p v-if="editForm.errors.rows" class="text-xs text-error">{{ editForm.errors.rows }}</p>
            </div>
            <div>
              <label class="label-text text-xs">Protocol</label>
              <div class="flex gap-3 mt-1.5">
                <label class="label cursor-pointer gap-1.5 p-0">
                  <input v-model="editForm.protocol" type="radio" class="radio radio-sm" value="tspl"> <span class="label-text text-xs">TSPL</span>
                </label>
                <label class="label cursor-pointer gap-1.5 p-0">
                  <input v-model="editForm.protocol" type="radio" class="radio radio-sm" value="zpl"> <span class="label-text text-xs">ZPL</span>
                </label>
                <label class="label cursor-pointer gap-1.5 p-0">
                  <input v-model="editForm.protocol" type="radio" class="radio radio-sm" value="epl"> <span class="label-text text-xs">EPL</span>
                </label>
              </div>
            </div>
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

    <dialog id="modal-label-profile-simulation" class="modal">
      <div class="modal-box max-w-5xl">
        <h3 class="font-bold text-lg">Simulasi profil label</h3>
        <div class="mt-4 space-y-4">
          <div v-if="simulationLoading" class="rounded-xl border border-dashed border-base-300 p-6 text-sm text-base-content/60">
            Memuat simulasi...
          </div>
          <div v-else-if="simulationError" class="rounded-xl border border-error/30 bg-error/5 p-4 text-sm text-error">
            {{ simulationError }}
          </div>
          <template v-else-if="simulationData">
            <div class="rounded-xl border border-base-300 bg-base-100 p-4 text-sm">
              <p class="font-semibold">{{ simulationData.profile.name }}</p>
              <p class="mt-1 text-base-content/70">
                {{ simulationData.profile.width_mm }} × {{ simulationData.profile.height_mm }} mm · DPI {{ simulationData.profile.dpi }} ·
                Margin {{ simulationData.profile.margin_left_mm }}/{{ simulationData.profile.margin_top_mm }} mm · Gap {{ simulationData.profile.gap_mm }} mm ·
                Row {{ simulationData.profile.rows ?? 1 }}
              </p>
            </div>

            <div class="rounded-xl border border-primary/20 bg-base-200/30 p-4 space-y-3">
              <div class="flex items-center justify-between gap-2">
                <div>
                  <p class="text-sm font-semibold text-base-content/80">Preview visual</p>
                  <p class="text-[11px] text-base-content/50">Simulasi tata letak label pada roll printer. Pilih jumlah row sesuai stok label.</p>
                </div>
                <div class="flex items-center gap-1">
                  <span class="text-xs text-base-content/50 mr-1">Row:</span>
                  <button v-for="n in 3" :key="n" type="button"
                  class="btn btn-sm min-h-0 h-7 min-w-8"
                  :class="previewRows === n ? 'btn-primary' : 'btn-ghost'"
                    @click="previewRows = n"
                  >{{ n }}</button>
                </div>
              </div>
              <LabelRollPreview
                :width-mm="simulationData.profile.width_mm"
                :height-mm="simulationData.profile.height_mm"
                :margin-left-mm="simulationData.profile.margin_left_mm"
                :margin-top-mm="simulationData.profile.margin_top_mm"
                :gap-mm="simulationData.profile.gap_mm"
                :rows="previewRows"
              />
            </div>

            <div class="grid gap-4 xl:grid-cols-2">
              <div class="space-y-2">
                <div>
                  <p class="font-semibold">Simulasi {{ simulationData.simulation.native_protocol }}</p>
                  <p class="text-xs text-base-content/60">Payload sample untuk jalur printer SMB/raw sesuai protocol profil.</p>
                </div>
                <textarea
                  class="textarea textarea-bordered min-h-[320px] w-full font-mono text-xs"
                  :value="simulationData.simulation.native_payload"
                  readonly
                />
              </div>
              <div class="space-y-2">
                <div>
                  <p class="font-semibold">Simulasi TSPL</p>
                  <p class="text-xs text-base-content/60">Payload sample untuk label LAN TSPL dengan ukuran, gap, dan margin dari profil ini.</p>
                </div>
                <textarea
                  class="textarea textarea-bordered min-h-[320px] w-full font-mono text-xs"
                  :value="simulationData.simulation.tspl_payload"
                  readonly
                />
              </div>
            </div>
          </template>
        </div>
        <div class="modal-action">
          <form method="dialog"><button class="btn">Tutup</button></form>
        </div>
      </div>
      <form method="dialog" class="modal-backdrop"><button>close</button></form>
    </dialog>

    <dialog id="modal-label-quick-preview" class="modal">
      <div class="modal-box max-w-2xl">
        <h3 class="font-bold text-lg">Preview label — {{ quickPreviewProfile?.name }}</h3>
        <template v-if="quickPreviewProfile">
          <p class="mt-1 text-sm text-base-content/70">
            {{ quickPreviewProfile.width_mm }} × {{ quickPreviewProfile.height_mm }} mm · DPI {{ quickPreviewProfile.dpi }} ·
            Margin {{ quickPreviewProfile.margin_left_mm }}/{{ quickPreviewProfile.margin_top_mm }} mm · Gap {{ quickPreviewProfile.gap_mm }} mm ·
            Row {{ quickPreviewProfile.rows ?? 1 }} ·
            <span class="uppercase">{{ quickPreviewProfile.protocol }}</span>
          </p>
          <div class="mt-4 space-y-3">
            <div class="flex items-center gap-2">
              <span class="text-xs text-base-content/60">Row pada roll:</span>
              <button v-for="n in 3" :key="n" type="button"
                class="btn btn-sm min-h-0 h-7 min-w-8"
                :class="quickPreviewRows === n ? 'btn-primary' : 'btn-ghost'"
                @click="quickPreviewRows = n"
              >{{ n }}</button>
            </div>
            <LabelRollPreview
              :width-mm="Number(quickPreviewProfile.width_mm)"
              :height-mm="Number(quickPreviewProfile.height_mm)"
              :margin-left-mm="Number(quickPreviewProfile.margin_left_mm)"
              :margin-top-mm="Number(quickPreviewProfile.margin_top_mm)"
              :gap-mm="Number(quickPreviewProfile.gap_mm)"
              :rows="quickPreviewRows"
            />
          </div>
        </template>
        <div class="modal-action">
          <form method="dialog"><button class="btn">Tutup</button></form>
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
