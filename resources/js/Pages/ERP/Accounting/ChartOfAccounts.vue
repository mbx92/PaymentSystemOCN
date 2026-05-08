<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, reactive, watch } from 'vue';

const props = defineProps({
  accounts: Array,
  filters: Object,
  types: Array,
});

const filters = reactive({
  q: props.filters?.q ?? '',
  type: props.filters?.type ?? '',
  status: props.filters?.status ?? '',
});

const TYPE_LABELS = {
  asset: 'Assets',
  liability: 'Liabilities',
  equity: 'Equity',
  revenue: 'Revenue',
  expense: 'Expense',
};

const groupedAccounts = computed(() => {
  const groups = props.types.map((type) => ({
    type,
    label: TYPE_LABELS[type] ?? type,
    rows: props.accounts.filter((account) => account.type === type),
  }));

  return groups.filter((group) => group.rows.length > 0);
});

let timer;
watch(filters, (val) => {
  clearTimeout(timer);
  timer = setTimeout(() => {
    router.get(route('erp.accounting.coa'), val, {
      preserveState: true,
      replace: true,
    });
  }, 250);
}, { deep: true });

const coaForm = useForm({
  code: '',
  name: '',
  type: 'asset',
  normal_balance: 'debit',
  is_active: true,
});

const openAddModal = () => {
  coaForm.reset();
  coaForm.type = 'asset';
  coaForm.normal_balance = 'debit';
  coaForm.is_active = true;
  document.getElementById('modal-add-coa')?.showModal();
};

const submitAdd = () => {
  coaForm.post(route('erp.accounting.coa.store'), {
    preserveScroll: true,
    onSuccess: () => document.getElementById('modal-add-coa')?.close(),
  });
};
</script>

<template>
  <Head title="Accounting - CoA / Chart Of Account" />
  <AppLayout>
    <div class="space-y-5">
      <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Accounting Workspace</p>
        <div class="mt-2 flex items-center justify-between gap-3">
          <h1 class="text-3xl font-bold tracking-tight">CoA / Chart Of Account</h1>
          <div class="flex items-center gap-2">
            <button class="btn btn-primary btn-sm" @click="openAddModal">+ Tambah CoA</button>
            <Link class="btn btn-ghost btn-sm" :href="route('erp.accounting')">Back</Link>
          </div>
        </div>
        <p class="mt-2 text-sm text-base-content/70">Daftar chart of accounts untuk kebutuhan posting jurnal seluruh modul ERP.</p>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Filter chart of accounts</h2>
        </div>
        <div class="card-body">
          <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
            <div class="md:col-span-2">
              <label class="label"><span class="label-text text-xs uppercase tracking-wide">Search</span></label>
              <input v-model="filters.q" type="text" class="input input-sm input-bordered w-full" placeholder="Kode / nama akun" />
            </div>
            <div>
              <label class="label"><span class="label-text text-xs uppercase tracking-wide">Type</span></label>
              <select v-model="filters.type" class="select select-sm select-bordered w-full">
                <option value="">Semua</option>
                <option v-for="type in types" :key="type" :value="type">{{ type }}</option>
              </select>
            </div>
            <div>
              <label class="label"><span class="label-text text-xs uppercase tracking-wide">Status</span></label>
              <select v-model="filters.status" class="select select-sm select-bordered w-full">
                <option value="">Semua</option>
                <option value="active">active</option>
                <option value="inactive">inactive</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Daftar akun (struktur root CoA)</h2>
        </div>
        <div class="overflow-x-auto">
          <table class="table table-zebra">
            <thead>
              <tr>
                <th>Kode Akun</th>
                <th>Nama Akun</th>
                <th>Type</th>
                <th>Normal Balance</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <template v-for="group in groupedAccounts" :key="group.type">
                <tr class="bg-base-200/80">
                  <td colspan="5" class="py-3">
                    <div class="flex items-center justify-between">
                      <div class="font-semibold uppercase tracking-wide text-sm">{{ group.label }}</div>
                      <div class="text-xs text-base-content/60">{{ group.rows.length }} akun</div>
                    </div>
                  </td>
                </tr>
                <tr v-for="account in group.rows" :key="account.id">
                  <td class="font-mono text-xs">{{ account.code }}</td>
                  <td class="font-semibold">{{ account.name }}</td>
                  <td class="uppercase text-xs">{{ account.type }}</td>
                  <td class="uppercase text-xs">{{ account.normal_balance }}</td>
                  <td><StatusBadge :status="account.status" /></td>
                </tr>
              </template>
              <tr v-if="!groupedAccounts.length">
                <td colspan="5" class="py-8 text-center text-base-content/50">Tidak ada akun ditemukan.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <dialog id="modal-add-coa" class="modal">
      <div class="modal-box max-w-xl">
        <h3 class="font-bold text-lg">Tambah Akun CoA</h3>
        <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
          <div>
            <label class="label"><span class="label-text">Kode Akun</span></label>
            <input v-model="coaForm.code" class="input input-bordered w-full" placeholder="Contoh: 1102" />
            <p v-if="coaForm.errors.code" class="text-error text-xs mt-1">{{ coaForm.errors.code }}</p>
          </div>
          <div>
            <label class="label"><span class="label-text">Nama Akun</span></label>
            <input v-model="coaForm.name" class="input input-bordered w-full" placeholder="Contoh: Bank BCA" />
            <p v-if="coaForm.errors.name" class="text-error text-xs mt-1">{{ coaForm.errors.name }}</p>
          </div>
          <div>
            <label class="label"><span class="label-text">Type</span></label>
            <select v-model="coaForm.type" class="select select-bordered w-full">
              <option v-for="type in types" :key="type" :value="type">{{ type }}</option>
            </select>
            <p v-if="coaForm.errors.type" class="text-error text-xs mt-1">{{ coaForm.errors.type }}</p>
          </div>
          <div>
            <label class="label"><span class="label-text">Normal Balance</span></label>
            <select v-model="coaForm.normal_balance" class="select select-bordered w-full">
              <option value="debit">debit</option>
              <option value="credit">credit</option>
            </select>
            <p v-if="coaForm.errors.normal_balance" class="text-error text-xs mt-1">{{ coaForm.errors.normal_balance }}</p>
          </div>
          <div class="md:col-span-2">
            <label class="flex items-center gap-2 text-sm">
              <input v-model="coaForm.is_active" type="checkbox" class="toggle toggle-primary toggle-sm" />
              <span>Aktif</span>
            </label>
          </div>
        </div>
        <div class="modal-action">
          <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
          <button class="btn btn-primary" :disabled="coaForm.processing" @click="submitAdd">Simpan</button>
        </div>
      </div>
    </dialog>
  </AppLayout>
</template>

