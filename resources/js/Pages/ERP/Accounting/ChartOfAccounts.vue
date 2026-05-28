<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import DataTablePagination from '@/Components/DataTablePagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { computed, reactive, ref, watch } from 'vue';
import { useCurrency } from '@/composables/useCurrency';

const props = defineProps({
  accounts: Object,
  filters: Object,
  types: Array,
});

const { format } = useCurrency();

const filters = reactive({
  q: props.filters?.q ?? '',
  type: props.filters?.type ?? '',
  status: props.filters?.status ?? '',
  per_page: Number(props.filters?.per_page ?? props.accounts?.per_page ?? 25),
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
    rows: (props.accounts?.data ?? []).filter((account) => account.type === type),
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
  is_cash_bank: false,
});

const resetAddForm = () => {
  coaForm.reset();
  coaForm.type = 'asset';
  coaForm.normal_balance = 'debit';
  coaForm.is_active = true;
  coaForm.is_cash_bank = false;
};

const openAddModal = () => {
  resetAddForm();
  document.getElementById('modal-add-coa')?.showModal();
};

const submitAdd = () => {
  coaForm.post(route('erp.accounting.coa.store'), {
    preserveScroll: true,
    onSuccess: () => {
      resetAddForm();
      document.getElementById('modal-add-coa')?.close();
    },
  });
};

const selectedAccount = ref(null);

const editForm = useForm({
  code: '',
  name: '',
  type: 'asset',
  normal_balance: 'debit',
  is_active: true,
  is_cash_bank: false,
});

const openEditModal = (account) => {
  selectedAccount.value = account;
  editForm.clearErrors();
  editForm.code = account.code;
  editForm.name = account.name;
  editForm.type = account.type;
  editForm.normal_balance = account.normal_balance;
  editForm.is_active = account.status === 'active';
  editForm.is_cash_bank = Boolean(account.is_cash_bank);
  document.getElementById('modal-edit-coa')?.showModal();
};

watch(() => coaForm.type, (type) => {
  if (type !== 'asset') {
    coaForm.is_cash_bank = false;
  }
});

watch(() => editForm.type, (type) => {
  if (type !== 'asset') {
    editForm.is_cash_bank = false;
  }
});

const submitEdit = () => {
  if (!selectedAccount.value) return;
  editForm.patch(route('erp.accounting.coa.update', selectedAccount.value.id), {
    preserveScroll: true,
    onSuccess: () => document.getElementById('modal-edit-coa')?.close(),
  });
};

const deleteCoaConfirmMessage = computed(() => {
  const a = selectedAccount.value;
  if (!a) {
    return 'Apakah Anda yakin ingin menghapus akun CoA ini?';
  }
  return `Anda akan menghapus permanen akun ${a.code} — ${a.name}. Tindakan ini tidak dapat dibatalkan.`;
});

const openDeleteCoaConfirm = () => {
  if (!selectedAccount.value?.can_delete) return;
  document.getElementById('modal-confirm-delete-coa')?.showModal();
};

const executeDeleteCoa = () => {
  if (!selectedAccount.value?.can_delete) return;
  router.delete(route('erp.accounting.coa.destroy', selectedAccount.value.id), {
    preserveScroll: true,
    onSuccess: () => {
      document.getElementById('modal-edit-coa')?.close();
      selectedAccount.value = null;
    },
  });
};
</script>

<template>
  <Head title="Accounting - CoA / Chart Of Account" />
  <AppLayout>
    <div class="space-y-5">
      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Accounting Workspace</p>
              <h1 class="ocn-panel__title mt-1">CoA / Chart Of Account</h1>
              <p class="ocn-panel__desc mt-1">Daftar chart of accounts untuk kebutuhan posting jurnal seluruh modul ERP. Klik baris untuk mengubah atau menghapus.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
              <div class="flex items-center gap-2">
                <button class="btn btn-primary btn-sm" @click="openAddModal">+ Tambah CoA</button>
                <Link class="btn btn-ghost btn-sm shrink-0 gap-1.5" :href="route('erp.accounting')">
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
                <th>Kas/Bank</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <template v-for="group in groupedAccounts" :key="group.type">
                <tr class="bg-base-200/80">
                  <td colspan="6" class="py-3">
                    <div class="flex items-center justify-between">
                      <div class="font-semibold uppercase tracking-wide text-sm">{{ group.label }}</div>
                      <div class="text-xs text-base-content/60">{{ group.rows.length }} akun</div>
                    </div>
                  </td>
                </tr>
                <tr
                  v-for="account in group.rows"
                  :key="account.id"
                  class="cursor-pointer transition-colors hover:bg-primary/5"
                  role="button"
                  tabindex="0"
                  @click="openEditModal(account)"
                  @keydown.enter.prevent="openEditModal(account)"
                >
                  <td class="font-mono text-xs">{{ account.code }}</td>
                  <td class="font-semibold">{{ account.name }}</td>
                  <td class="uppercase text-xs">{{ account.type }}</td>
                  <td class="uppercase text-xs">{{ account.normal_balance }}</td>
                  <td>
                    <span v-if="account.is_cash_bank" class="badge badge-primary badge-sm">Ya</span>
                    <span v-else class="text-base-content/40">—</span>
                  </td>
                  <td><StatusBadge :status="account.status" /></td>
                </tr>
              </template>
              <tr v-if="!groupedAccounts.length">
                <td colspan="6" class="py-8 text-center text-base-content/50">Tidak ada akun ditemukan.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <DataTablePagination :paginator="accounts" @update:per-page="(n) => { filters.per_page = n; }" />
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
          <div class="md:col-span-2 flex flex-wrap gap-6">
            <label class="flex items-center gap-2 text-sm">
              <input v-model="coaForm.is_active" type="checkbox" class="toggle toggle-primary toggle-sm" />
              <span>Aktif</span>
            </label>
            <label v-if="coaForm.type === 'asset'" class="flex items-center gap-2 text-sm">
              <input v-model="coaForm.is_cash_bank" type="checkbox" class="toggle toggle-secondary toggle-sm" />
              <span>Kas/Bank (penerimaan &amp; pembayaran)</span>
            </label>
          </div>
        </div>
        <div class="modal-action">
          <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
          <button class="btn btn-primary" :disabled="coaForm.processing" @click="submitAdd">Simpan</button>
        </div>
      </div>
    </dialog>

    <dialog id="modal-edit-coa" class="modal">
      <div class="modal-box max-w-xl">
        <h3 class="font-bold text-lg">Ubah Akun CoA</h3>
        <p v-if="selectedAccount" class="mt-1 text-sm text-base-content/60 font-mono">{{ selectedAccount.code }} — {{ selectedAccount.name }}</p>

        <div v-if="selectedAccount?.has_posting_value" class="alert alert-warning mt-4 text-sm">
          <div>
            <p class="font-semibold">Akun memiliki nilai di jurnal</p>
            <p class="mt-1 opacity-90">
              Terdapat {{ selectedAccount.journal_line_count }} baris jurnal dengan total debit {{ format(selectedAccount.total_debit) }} dan kredit {{ format(selectedAccount.total_credit) }}.
              Penghapusan akun dinonaktifkan agar integritas buku besar tetap terjaga.
            </p>
          </div>
        </div>

        <div v-if="selectedAccount && !selectedAccount.can_delete && selectedAccount.delete_blocked_summary" class="alert alert-error mt-3 text-sm">
          <div>
            <p class="font-semibold">Akun tidak dapat dihapus</p>
            <p class="mt-1">{{ selectedAccount.delete_blocked_summary }}</p>
          </div>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
          <div>
            <label class="label"><span class="label-text">Kode Akun</span></label>
            <input v-model="editForm.code" class="input input-bordered w-full" />
            <p v-if="editForm.errors.code" class="text-error text-xs mt-1">{{ editForm.errors.code }}</p>
          </div>
          <div>
            <label class="label"><span class="label-text">Nama Akun</span></label>
            <input v-model="editForm.name" class="input input-bordered w-full" />
            <p v-if="editForm.errors.name" class="text-error text-xs mt-1">{{ editForm.errors.name }}</p>
          </div>
          <div>
            <label class="label"><span class="label-text">Type</span></label>
            <select v-model="editForm.type" class="select select-bordered w-full">
              <option v-for="type in types" :key="type" :value="type">{{ type }}</option>
            </select>
            <p v-if="editForm.errors.type" class="text-error text-xs mt-1">{{ editForm.errors.type }}</p>
          </div>
          <div>
            <label class="label"><span class="label-text">Normal Balance</span></label>
            <select v-model="editForm.normal_balance" class="select select-bordered w-full">
              <option value="debit">debit</option>
              <option value="credit">credit</option>
            </select>
            <p v-if="editForm.errors.normal_balance" class="text-error text-xs mt-1">{{ editForm.errors.normal_balance }}</p>
          </div>
          <div class="md:col-span-2 flex flex-wrap gap-6">
            <label class="flex items-center gap-2 text-sm">
              <input v-model="editForm.is_active" type="checkbox" class="toggle toggle-primary toggle-sm" />
              <span>Aktif</span>
            </label>
            <label v-if="editForm.type === 'asset'" class="flex items-center gap-2 text-sm">
              <input v-model="editForm.is_cash_bank" type="checkbox" class="toggle toggle-secondary toggle-sm" />
              <span>Kas/Bank (penerimaan &amp; pembayaran)</span>
            </label>
          </div>
        </div>

        <div class="modal-action flex-wrap gap-2">
          <form method="dialog"><button class="btn btn-ghost">Tutup</button></form>
          <button type="button" class="btn btn-error btn-outline" :disabled="!selectedAccount?.can_delete || editForm.processing" @click="openDeleteCoaConfirm">
            Hapus CoA
          </button>
          <button type="button" class="btn btn-primary" :disabled="editForm.processing" @click="submitEdit">Simpan perubahan</button>
        </div>
      </div>
    </dialog>

    <ConfirmModal
      id="modal-confirm-delete-coa"
      title="Hapus Akun CoA"
      :message="deleteCoaConfirmMessage"
      confirm-text="Hapus permanen"
      confirm-class="btn-error"
      @confirm="executeDeleteCoa"
    />
  </AppLayout>
</template>
