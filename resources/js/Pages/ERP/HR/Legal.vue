<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
  contracts: Array,
  templates: Array,
  contractTypes: Array,
});

const deleting = ref(null);

const typeLabel = (key) =>
  props.contractTypes.find((t) => t.key === key)?.label ?? key;

const statusClass = (status) =>
  status === 'final'
    ? 'badge badge-success badge-sm'
    : 'badge badge-ghost badge-sm';

const confirmDelete = (contract) => {
  deleting.value = contract;
  document.getElementById('modal-delete-contract')?.showModal();
};

const doDelete = () => {
  if (!deleting.value) return;
  router.delete(route('erp.hr.legal.destroy', deleting.value.id), {
    preserveScroll: true,
    onFinish: () => {
      deleting.value = null;
      document.getElementById('modal-delete-contract')?.close();
    },
  });
};
</script>

<template>
  <Head title="HR – Legal" />
  <AppLayout>
    <div class="space-y-5">
      <!-- PAGE HEADER -->
      <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">HR Workspace</p>
        <div class="mt-2 flex items-center justify-between gap-3">
          <h1 class="text-3xl font-bold tracking-tight">Legal & Kontrak</h1>
          <div class="flex items-center gap-2">
            <Link :href="route('erp.hr.legal.create')" class="btn btn-primary btn-sm">+ Buat Kontrak</Link>
            <Link :href="route('erp.hr')" class="btn btn-ghost btn-sm">Back</Link>
          </div>
        </div>
        <p class="mt-2 text-sm text-base-content/70">
          Kelola kontrak perjanjian jasa. Edit pasal per pasal, simpan draft, dan unduh PDF siap tanda tangan.
        </p>
      </div>

      <!-- CONTRACTS TABLE -->
      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Daftar Kontrak</h2>
          <p class="ocn-panel__desc">{{ contracts.length }} kontrak tersimpan</p>
        </div>
        <div class="overflow-x-auto">
          <table class="table table-zebra">
            <thead>
              <tr>
                <th>Nomor</th>
                <th>Judul Kontrak</th>
                <th>Tipe</th>
                <th>Tanggal</th>
                <th>Status</th>
                <th>Dibuat oleh</th>
                <th class="text-right">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="contracts.length === 0">
                <td colspan="7" class="py-10 text-center text-base-content/50">
                  Belum ada kontrak. Klik <strong>+ Buat Kontrak</strong> untuk memulai.
                </td>
              </tr>
              <tr v-for="c in contracts" :key="c.id">
                <td class="font-mono text-xs font-semibold">{{ c.contract_number }}</td>
                <td class="max-w-[220px] truncate font-medium">{{ c.title }}</td>
                <td class="text-xs text-base-content/70">{{ typeLabel(c.contract_type) }}</td>
                <td class="text-xs">{{ c.contract_date }}</td>
                <td><span :class="statusClass(c.status)">{{ c.status }}</span></td>
                <td class="text-xs text-base-content/70">{{ c.creator_name }}</td>
                <td class="text-right">
                  <div class="flex items-center justify-end gap-1">
                    <a
                      :href="route('erp.hr.legal.pdf', c.id)"
                      target="_blank"
                      class="btn btn-primary btn-xs"
                    >
                      PDF
                    </a>
                    <Link
                      :href="route('erp.hr.legal.edit', c.id)"
                      class="btn btn-outline btn-xs"
                    >
                      Edit
                    </Link>
                    <button class="btn btn-error btn-xs btn-outline" @click="confirmDelete(c)">Hapus</button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- REFERENCE TEMPLATES -->
      <div v-if="templates.length" class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Template Referensi</h2>
          <p class="ocn-panel__desc">Dokumen asli di folder docs – untuk acuan redaksi hukum</p>
        </div>
        <div class="overflow-x-auto">
          <table class="table table-zebra">
            <thead>
              <tr>
                <th>Nama File</th>
                <th>Ukuran</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="file in templates" :key="file.name">
                <td class="font-medium">{{ file.name }}</td>
                <td class="text-xs">{{ file.size_kb }} KB</td>
                <td class="text-right">
                  <a :href="file.download_url" class="btn btn-outline btn-sm">Download</a>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- DELETE CONFIRM MODAL -->
    <dialog id="modal-delete-contract" class="modal">
      <div class="modal-box">
        <h3 class="text-lg font-bold text-error">Hapus Kontrak</h3>
        <p class="py-3 text-sm">
          Yakin ingin menghapus kontrak
          <strong>{{ deleting?.contract_number }}</strong>?
          Tindakan ini tidak dapat dibatalkan.
        </p>
        <div class="modal-action">
          <form method="dialog">
            <button class="btn btn-ghost btn-sm">Batal</button>
          </form>
          <button class="btn btn-error btn-sm" @click="doDelete">Hapus</button>
        </div>
      </div>
      <form method="dialog" class="modal-backdrop"><button>close</button></form>
    </dialog>
  </AppLayout>
</template>
