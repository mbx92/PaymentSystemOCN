<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
  settings: Array,
  accounts: Array,
  categoryMappings: Object,
});

const saving = useForm({
  key: '',
  account_id: '',
});

const mappingForm = useForm({
  domain: 'cash_in',
  category: '',
  account_id: '',
});

const categoryForm = useForm({
  domain: 'cash_in',
  key: '',
  label: '',
  is_active: true,
});

const saveSetting = (key, accountId) => {
  saving.key = key;
  saving.account_id = accountId || '';
  saving.post(route('erp.accounting.coa-settings.upsert'), { preserveScroll: true });
};

const saveCategoryMapping = (domain, category, accountId) => {
  mappingForm.domain = domain;
  mappingForm.category = category;
  mappingForm.account_id = accountId || '';
  mappingForm.post(route('erp.accounting.coa-settings.category-mappings.upsert'), { preserveScroll: true });
};

const openCategoryModal = (domain = 'cash_in') => {
  categoryForm.reset();
  categoryForm.domain = domain;
  categoryForm.is_active = true;
  document.getElementById('modal-add-cash-category')?.showModal();
};

const submitCategory = () => {
  categoryForm.post(route('erp.accounting.coa-settings.categories.store'), {
    preserveScroll: true,
    onSuccess: () => {
      categoryForm.reset();
      categoryForm.domain = 'cash_in';
      categoryForm.is_active = true;
      document.getElementById('modal-add-cash-category')?.close();
    },
  });
};

const defaultsForm = useForm({});
const openDefaultsModal = () => document.getElementById('modal-apply-defaults')?.showModal();
const applyDefaults = () => {
  defaultsForm.post(route('erp.accounting.coa-settings.apply-defaults'), {
    preserveScroll: true,
    onSuccess: () => document.getElementById('modal-apply-defaults')?.close(),
  });
};

const domainTitle = (domain) => (domain === 'cash_in' ? 'Kas Masuk' : 'Kas Keluar');
</script>

<template>
  <Head title="Accounting - Pengaturan COA" />
  <AppLayout>
    <div class="space-y-5">
      <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Accounting Workspace</p>
        <div class="mt-2 flex items-center justify-between gap-3">
          <h1 class="text-3xl font-bold tracking-tight">Pengaturan COA</h1>
          <div class="flex items-center gap-2">
            <button class="btn btn-outline btn-sm" @click="openDefaultsModal">
              Terapkan Standar Akuntansi
            </button>
            <Link class="btn btn-ghost btn-sm" :href="route('erp.accounting')">Back</Link>
          </div>
        </div>
        <p class="mt-2 text-sm text-base-content/70">
          Satu halaman untuk mengatur posting otomatis sistem dan mapping kategori cashflow. Setiap baris menjelaskan transaksi masuk ke akun mana dan mengambil nilai dari field apa.
        </p>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Posting otomatis sistem</h2>
          <p class="ocn-panel__desc">Dipakai oleh transaksi yang dibuat otomatis oleh sistem, seperti POS dan invoice project.</p>
        </div>
        <div class="overflow-x-auto">
          <table class="table table-zebra">
            <thead>
              <tr>
                <th>Setting</th>
                <th>Source Module</th>
                <th>Sumber Nilai</th>
                <th>Akun COA</th>
                <th class="w-36"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in settings" :key="row.key">
                <td>
                  <p class="font-medium">{{ row.label }}</p>
                  <p class="mt-1 text-xs text-base-content/60">{{ row.description }}</p>
                  <p class="mt-1 text-xs text-base-content/50">Default kode: {{ row.default_account_code }}</p>
                </td>
                <td class="font-mono text-xs">{{ row.source_module }}</td>
                <td class="font-mono text-xs">{{ row.amount_source }}</td>
                <td>
                  <select
                    class="select select-bordered select-sm w-full max-w-xl"
                    :value="row.account_id || ''"
                    @change="saveSetting(row.key, $event.target.value)"
                  >
                    <option value="">-- (Kosong) gunakan default --</option>
                    <option v-for="acc in accounts" :key="acc.id" :value="acc.id">
                      {{ acc.code }} - {{ acc.name }} ({{ acc.type }})
                    </option>
                  </select>
                </td>
                <td class="text-right">
                  <span v-if="saving.processing && saving.key === row.key" class="text-xs text-base-content/60">Menyimpan...</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="grid gap-5 xl:grid-cols-2">
        <div v-for="domain in ['cash_in', 'cash_out']" :key="domain" class="ocn-panel">
          <div class="ocn-panel__head flex items-start justify-between gap-3">
            <div>
              <h2 class="ocn-panel__title">Mapping kategori {{ domainTitle(domain) }}</h2>
              <p class="ocn-panel__desc">Dipakai saat transaksi cashflow manual diposting, dan untuk klasifikasi transaksi sistem yang masuk ke cashflow.</p>
            </div>
            <button class="btn btn-primary btn-sm shrink-0" @click="openCategoryModal(domain)">+ Tambah Kategori</button>
          </div>
          <div class="overflow-x-auto">
            <table class="table table-zebra">
              <thead>
                <tr>
                  <th>Kategori</th>
                  <th>Field Nilai</th>
                  <th>Akun COA</th>
                  <th class="w-28"></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in categoryMappings?.[domain] ?? []" :key="`${domain}-${row.category}`">
                  <td>
                    <p class="font-medium">{{ row.label }}</p>
                    <p class="mt-1 text-xs text-base-content/60">{{ row.used_by }}</p>
                    <p class="mt-1 text-xs" :class="row.is_active ? 'text-success' : 'text-warning'">
                      {{ row.is_active ? 'Aktif' : 'Nonaktif' }} · key: {{ row.category }}
                    </p>
                  </td>
                  <td class="font-mono text-xs">{{ row.amount_source }}</td>
                  <td>
                    <select
                      class="select select-bordered select-sm w-full"
                      :value="row.account_id || ''"
                      @change="saveCategoryMapping(domain, row.category, $event.target.value)"
                    >
                      <option value="">-- Belum di-mapping --</option>
                      <option v-for="acc in accounts" :key="acc.id" :value="acc.id">
                        {{ acc.code }} - {{ acc.name }} ({{ acc.type }})
                      </option>
                    </select>
                  </td>
                  <td class="text-right">
                    <span
                      v-if="mappingForm.processing && mappingForm.domain === domain && mappingForm.category === row.category"
                      class="text-xs text-base-content/60"
                    >Menyimpan...</span>
                  </td>
                </tr>
                <tr v-if="!(categoryMappings?.[domain]?.length)">
                  <td colspan="4" class="py-10 text-center text-base-content/50">Belum ada kategori.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <dialog id="modal-apply-defaults" class="modal">
      <div class="modal-box max-w-lg">
        <h3 class="font-bold text-lg">Terapkan Standar Akuntansi</h3>
        <div class="mt-4 space-y-3 text-sm">
          <p class="text-base-content/80">
            Tindakan ini akan mengatur <strong>semua</strong> pengaturan COA ke konfigurasi standar akuntansi yang benar:
          </p>
          <ul class="list-disc pl-5 space-y-1 text-base-content/70">
            <li>POS Penjualan → <span class="font-mono text-xs">4002</span> Pendapatan Penjualan POS</li>
            <li>POS Biaya Tambahan → <span class="font-mono text-xs">4004</span> Pendapatan Lain-lain</li>
            <li>Invoice Project → <span class="font-mono text-xs">4003</span> Pendapatan Project</li>
            <li>Semua akun Kas/Bank → <span class="font-mono text-xs">1001</span> Kas</li>
            <li>Semua mapping kategori cashflow ke akun yang sesuai</li>
          </ul>
          <div class="rounded-lg bg-warning/10 border border-warning/30 p-3">
            <p class="text-warning text-xs font-medium">Pengaturan yang sudah Anda ubah secara manual akan di-overwrite.</p>
          </div>
        </div>
        <div class="modal-action">
          <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
          <button class="btn btn-primary" :disabled="defaultsForm.processing" @click="applyDefaults">
            <span v-if="defaultsForm.processing" class="loading loading-spinner loading-sm"></span>
            Terapkan Semua
          </button>
        </div>
      </div>
    </dialog>

    <dialog id="modal-add-cash-category" class="modal">
      <div class="modal-box max-w-xl">
        <h3 class="font-bold text-lg">Tambah Kategori Cashflow</h3>
        <div class="mt-4 space-y-3">
          <div>
            <label class="label"><span class="label-text text-xs uppercase tracking-wide">Domain</span></label>
            <select v-model="categoryForm.domain" class="select select-bordered select-sm w-full">
              <option value="cash_in">Kas Masuk</option>
              <option value="cash_out">Kas Keluar</option>
            </select>
            <p v-if="categoryForm.errors.domain" class="mt-1 text-xs text-error">{{ categoryForm.errors.domain }}</p>
          </div>
          <div>
            <label class="label"><span class="label-text text-xs uppercase tracking-wide">Key</span></label>
            <input v-model="categoryForm.key" class="input input-bordered input-sm w-full" placeholder="contoh: penjualan_marketplace" />
            <p v-if="categoryForm.errors.key" class="mt-1 text-xs text-error">{{ categoryForm.errors.key }}</p>
            <p class="mt-1 text-xs text-base-content/60">Huruf kecil, angka, underscore saja.</p>
          </div>
          <div>
            <label class="label"><span class="label-text text-xs uppercase tracking-wide">Label</span></label>
            <input v-model="categoryForm.label" class="input input-bordered input-sm w-full" placeholder="Contoh: Penjualan Marketplace" />
            <p v-if="categoryForm.errors.label" class="mt-1 text-xs text-error">{{ categoryForm.errors.label }}</p>
          </div>
          <div>
            <label class="flex items-center gap-2 text-sm">
              <input v-model="categoryForm.is_active" type="checkbox" class="toggle toggle-sm toggle-primary" />
              <span>Aktif</span>
            </label>
          </div>
        </div>
        <div class="modal-action">
          <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
          <button class="btn btn-primary" :disabled="categoryForm.processing" @click="submitCategory">Simpan</button>
        </div>
      </div>
    </dialog>
  </AppLayout>
</template>

