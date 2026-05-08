<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive, ref, nextTick } from 'vue';

const props = defineProps({
  contract: Object,    // null when creating
  contractTypes: Array,
  defaultPasals: Object, // { website: [...], software_server: [...] }
});

const isEdit = !!props.contract;
const processing = ref(false);
const errors = ref({});

// ─── Form state ───────────────────────────────────────────────────────────────
const form = reactive({
  title: props.contract?.title ?? '',
  contract_number: props.contract?.contract_number ?? '',
  contract_date: props.contract?.contract_date ?? new Date().toISOString().slice(0, 10),
  contract_type: props.contract?.contract_type ?? 'website',
  status: props.contract?.status ?? 'draft',
  pihak_pertama: {
    name:    props.contract?.pihak_pertama?.name    ?? '',
    address: props.contract?.pihak_pertama?.address ?? '',
    phone:   props.contract?.pihak_pertama?.phone   ?? '',
    email:   props.contract?.pihak_pertama?.email   ?? '',
    bank:    props.contract?.pihak_pertama?.bank    ?? '',
  },
  pihak_kedua: {
    name:    props.contract?.pihak_kedua?.name    ?? '',
    address: props.contract?.pihak_kedua?.address ?? '',
    phone:   props.contract?.pihak_kedua?.phone   ?? '',
    email:   props.contract?.pihak_kedua?.email   ?? '',
    pic:     props.contract?.pihak_kedua?.pic     ?? '',
  },
  pasals: props.contract?.pasals
    ? props.contract.pasals.map((p) => ({ ...p }))
    : (props.defaultPasals[props.contract?.contract_type ?? 'website'] ?? []).map((p) => ({ ...p })),
});

// ─── Reset pasals when contract type changes (with confirmation) ───────────────
const pendingType = ref(null);

const onTypeChange = (newType) => {
  if (form.pasals.length === 0) {
    applyTypeChange(newType);
    return;
  }
  pendingType.value = newType;
  document.getElementById('modal-confirm-reset-pasals')?.showModal();
};

const applyTypeChange = (type) => {
  form.contract_type = type;
  form.pasals = (props.defaultPasals[type] ?? []).map((p) => ({ ...p }));
  pendingType.value = null;
};

const confirmTypeReset = () => {
  if (pendingType.value) applyTypeChange(pendingType.value);
  document.getElementById('modal-confirm-reset-pasals')?.close();
};

const cancelTypeReset = () => {
  pendingType.value = null;
  document.getElementById('modal-confirm-reset-pasals')?.close();
};

// ─── Pasal CRUD ───────────────────────────────────────────────────────────────
const addPasal = () => {
  form.pasals.push({ title: `PASAL ${form.pasals.length + 1} – `, content: '' });
  nextTick(() => {
    const textareas = document.querySelectorAll('.pasal-content-area');
    textareas[textareas.length - 1]?.focus();
  });
};

const removePasal = (idx) => {
  form.pasals.splice(idx, 1);
};

const movePasal = (idx, dir) => {
  const target = idx + dir;
  if (target < 0 || target >= form.pasals.length) return;
  [form.pasals[idx], form.pasals[target]] = [form.pasals[target], form.pasals[idx]];
};

// ─── Auto-grow textarea ───────────────────────────────────────────────────────
const autoGrow = (el) => {
  el.style.height = 'auto';
  el.style.height = el.scrollHeight + 'px';
};

const onTextareaInput = (e) => autoGrow(e.target);

const initTextarea = (el) => {
  if (el) nextTick(() => autoGrow(el));
};

// ─── Submit ───────────────────────────────────────────────────────────────────
const submit = () => {
  errors.value = {};
  processing.value = true;

  const payload = { ...form };

  if (isEdit) {
    router.put(route('erp.hr.legal.update', props.contract.id), payload, {
      preserveScroll: true,
      onError: (e) => { errors.value = e; },
      onFinish: () => { processing.value = false; },
    });
  } else {
    router.post(route('erp.hr.legal.store'), payload, {
      onError: (e) => { errors.value = e; },
      onFinish: () => { processing.value = false; },
    });
  }
};

// ─── Field error helper ───────────────────────────────────────────────────────
const err = (field) => errors.value[field];
</script>

<template>
  <Head :title="isEdit ? 'Edit Kontrak' : 'Buat Kontrak'" />
  <AppLayout>
    <div class="space-y-5">

      <!-- PAGE HEADER -->
      <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">HR Workspace &rsaquo; Legal</p>
        <div class="mt-2 flex items-center justify-between gap-3">
          <h1 class="text-3xl font-bold tracking-tight">
            {{ isEdit ? 'Edit Kontrak' : 'Buat Kontrak Baru' }}
          </h1>
          <div class="flex items-center gap-2">
            <a
              v-if="isEdit"
              :href="route('erp.hr.legal.pdf', contract.id)"
              target="_blank"
              class="btn btn-primary btn-sm"
            >
              Download PDF
            </a>
            <Link :href="route('erp.hr.legal')" class="btn btn-ghost btn-sm">Back</Link>
          </div>
        </div>
        <p class="mt-2 text-sm text-base-content/70">
          Isi data pihak, lalu edit tiap pasal sesuai kebutuhan. Simpan dulu sebelum download PDF.
        </p>
      </div>

      <!-- SECTION 1: HEADER INFO -->
      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Informasi Kontrak</h2>
          <p class="ocn-panel__desc">Judul, nomor, tanggal, dan tipe kontrak</p>
        </div>
        <div class="card-body">
          <div class="grid grid-cols-1 gap-4 md:grid-cols-2">

            <div class="md:col-span-2">
              <label class="label"><span class="label-text font-medium">Judul Kontrak <span class="text-error">*</span></span></label>
              <input
                v-model="form.title"
                class="input input-bordered w-full"
                :class="{ 'input-error': err('title') }"
                placeholder="contoh: Website Company Profile Sense Of Jewels"
              />
              <p v-if="err('title')" class="mt-1 text-xs text-error">{{ err('title') }}</p>
            </div>

            <div>
              <label class="label"><span class="label-text font-medium">Tipe Kontrak <span class="text-error">*</span></span></label>
              <select
                :value="form.contract_type"
                class="select select-bordered w-full"
                @change="onTypeChange($event.target.value)"
              >
                <option v-for="t in contractTypes" :key="t.key" :value="t.key">{{ t.label }}</option>
              </select>
            </div>

            <div>
              <label class="label"><span class="label-text font-medium">Nomor Kontrak <span class="text-error">*</span></span></label>
              <input
                v-model="form.contract_number"
                class="input input-bordered w-full"
                :class="{ 'input-error': err('contract_number') }"
                placeholder="PKP/2026/05/001"
              />
              <p v-if="err('contract_number')" class="mt-1 text-xs text-error">{{ err('contract_number') }}</p>
            </div>

            <div>
              <label class="label"><span class="label-text font-medium">Tanggal Perjanjian <span class="text-error">*</span></span></label>
              <input v-model="form.contract_date" type="date" class="input input-bordered w-full" />
            </div>

            <div>
              <label class="label"><span class="label-text font-medium">Status</span></label>
              <select v-model="form.status" class="select select-bordered w-full">
                <option value="draft">Draft</option>
                <option value="final">Final</option>
              </select>
            </div>

          </div>
        </div>
      </div>

      <!-- SECTION 2: PIHAK PERTAMA -->
      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Pihak Pertama</h2>
          <p class="ocn-panel__desc">Penyedia Jasa / Developer</p>
        </div>
        <div class="card-body">
          <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
            <div>
              <label class="label"><span class="label-text">Nama / Perusahaan <span class="text-error">*</span></span></label>
              <input
                v-model="form.pihak_pertama.name"
                class="input input-bordered w-full"
                :class="{ 'input-error': err('pihak_pertama.name') }"
                placeholder="PT. Contoh Karya Indonesia"
              />
            </div>
            <div>
              <label class="label"><span class="label-text">No. Telepon / WA</span></label>
              <input v-model="form.pihak_pertama.phone" class="input input-bordered w-full" placeholder="08xxxxxxxxxx" />
            </div>
            <div>
              <label class="label"><span class="label-text">Email</span></label>
              <input v-model="form.pihak_pertama.email" type="email" class="input input-bordered w-full" placeholder="email@perusahaan.com" />
            </div>
            <div>
              <label class="label"><span class="label-text">No. Rekening</span></label>
              <input v-model="form.pihak_pertama.bank" class="input input-bordered w-full" placeholder="BCA 1234567890 a.n. ..." />
            </div>
            <div class="md:col-span-2">
              <label class="label"><span class="label-text">Alamat</span></label>
              <textarea v-model="form.pihak_pertama.address" class="textarea textarea-bordered w-full" rows="2" placeholder="Jl. ..." />
            </div>
          </div>
        </div>
      </div>

      <!-- SECTION 3: PIHAK KEDUA -->
      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Pihak Kedua</h2>
          <p class="ocn-panel__desc">Klien / Pemberi Kerja</p>
        </div>
        <div class="card-body">
          <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
            <div>
              <label class="label"><span class="label-text">Nama / Perusahaan <span class="text-error">*</span></span></label>
              <input
                v-model="form.pihak_kedua.name"
                class="input input-bordered w-full"
                :class="{ 'input-error': err('pihak_kedua.name') }"
                placeholder="CV. Klien Contoh"
              />
            </div>
            <div>
              <label class="label"><span class="label-text">Nama PIC</span></label>
              <input v-model="form.pihak_kedua.pic" class="input input-bordered w-full" placeholder="Budi Santoso" />
            </div>
            <div>
              <label class="label"><span class="label-text">No. Telepon / WA</span></label>
              <input v-model="form.pihak_kedua.phone" class="input input-bordered w-full" placeholder="08xxxxxxxxxx" />
            </div>
            <div>
              <label class="label"><span class="label-text">Email</span></label>
              <input v-model="form.pihak_kedua.email" type="email" class="input input-bordered w-full" placeholder="klien@email.com" />
            </div>
            <div class="md:col-span-2">
              <label class="label"><span class="label-text">Alamat</span></label>
              <textarea v-model="form.pihak_kedua.address" class="textarea textarea-bordered w-full" rows="2" placeholder="Jl. ..." />
            </div>
          </div>
        </div>
      </div>

      <!-- SECTION 4: PASAL EDITOR -->
      <div class="ocn-panel">
        <div class="ocn-panel__head flex items-center justify-between">
          <div>
            <h2 class="ocn-panel__title">Pasal-Pasal</h2>
            <p class="ocn-panel__desc">Edit isi tiap pasal sesuai kesepakatan. Bisa tambah, hapus, atau ubah urutan.</p>
          </div>
          <button type="button" class="btn btn-outline btn-sm shrink-0" @click="addPasal">+ Tambah Pasal</button>
        </div>

        <div class="divide-y divide-base-200">
          <div
            v-for="(pasal, idx) in form.pasals"
            :key="idx"
            class="px-6 py-5"
          >
            <div class="mb-2 flex items-center gap-2">
              <span class="badge badge-primary badge-sm font-mono">{{ idx + 1 }}</span>
              <input
                v-model="pasal.title"
                class="input input-sm input-bordered flex-1 font-semibold uppercase"
                placeholder="PASAL X – JUDUL PASAL"
              />
              <div class="flex shrink-0 gap-1">
                <button
                  type="button"
                  class="btn btn-ghost btn-xs"
                  :disabled="idx === 0"
                  title="Pindah ke atas"
                  @click="movePasal(idx, -1)"
                >↑</button>
                <button
                  type="button"
                  class="btn btn-ghost btn-xs"
                  :disabled="idx === form.pasals.length - 1"
                  title="Pindah ke bawah"
                  @click="movePasal(idx, 1)"
                >↓</button>
                <button
                  type="button"
                  class="btn btn-ghost btn-xs text-error"
                  title="Hapus pasal ini"
                  @click="removePasal(idx)"
                >✕</button>
              </div>
            </div>
            <textarea
              v-model="pasal.content"
              class="pasal-content-area textarea textarea-bordered w-full font-mono text-xs leading-relaxed"
              rows="5"
              placeholder="Isi ketentuan pasal ini..."
              style="resize: vertical; min-height: 100px;"
              :ref="(el) => initTextarea(el)"
              @input="onTextareaInput"
            />
          </div>

          <div v-if="form.pasals.length === 0" class="px-6 py-10 text-center text-base-content/50">
            Belum ada pasal. Klik <strong>+ Tambah Pasal</strong> atau pilih tipe kontrak untuk load template.
          </div>
        </div>
      </div>

      <!-- SAVE ACTIONS -->
      <div class="flex flex-wrap items-center justify-end gap-3 pb-4">
        <span v-if="Object.keys(errors).length" class="text-sm text-error">
          Ada {{ Object.keys(errors).length }} kesalahan validasi. Periksa kembali isian.
        </span>
        <button
          type="button"
          class="btn btn-primary"
          :disabled="processing"
          @click="submit"
        >
          <span v-if="processing" class="loading loading-spinner loading-sm mr-1" />
          {{ isEdit ? 'Simpan Perubahan' : 'Simpan Kontrak' }}
        </button>
        <a
          v-if="isEdit"
          :href="route('erp.hr.legal.pdf', contract.id)"
          target="_blank"
          class="btn btn-outline"
        >
          Download PDF
        </a>
      </div>

    </div>

    <!-- MODAL: Confirm reset pasals -->
    <dialog id="modal-confirm-reset-pasals" class="modal">
      <div class="modal-box">
        <h3 class="text-lg font-bold">Ganti Template Pasal?</h3>
        <p class="py-3 text-sm text-base-content/70">
          Mengganti tipe kontrak akan <strong>mengganti semua pasal</strong> dengan template bawaan tipe baru.
          Perubahan yang belum disimpan pada pasal-pasal saat ini akan hilang.
        </p>
        <div class="modal-action">
          <button class="btn btn-ghost btn-sm" @click="cancelTypeReset">Batal</button>
          <button class="btn btn-warning btn-sm" @click="confirmTypeReset">Ya, Ganti Template</button>
        </div>
      </div>
      <form method="dialog" class="modal-backdrop"><button>close</button></form>
    </dialog>

  </AppLayout>
</template>
