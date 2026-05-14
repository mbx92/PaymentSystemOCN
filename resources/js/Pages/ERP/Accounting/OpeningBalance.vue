<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ArrowLeftIcon, PlusIcon, TrashIcon } from '@heroicons/vue/24/outline';
import { computed } from 'vue';
import { useCurrency } from '@/composables/useCurrency';

const { format } = useCurrency();

const blankLine = () => ({
  account_id: '',
  debit: 0,
  credit: 0,
  description: '',
});

const props = defineProps({
  accounts: Array,
  openingEntries: Array,
  companies: Array,
  selected_company_id: [Number, null],
});

const page = usePage();

const isAdmin = computed(() => page.props.auth?.user?.role === 'admin');

const selectedCompany = computed(() => {
  const id = form.company_id;
  if (id === '' || id == null) return null;
  return props.companies?.find((c) => Number(c.id) === Number(id)) ?? null;
});

const companyIdForForm = () => {
  const fromProps = props.selected_company_id ?? null;
  if (fromProps != null && fromProps !== '') return Number(fromProps);
  const ctx = page.props.erpCompanyContext;
  if (ctx?.current_company_id) return Number(ctx.current_company_id);
  const first = props.companies?.[0]?.id;
  return first != null ? Number(first) : '';
};

const form = useForm({
  company_id: companyIdForForm(),
  entry_date: new Date().toISOString().slice(0, 10),
  description: '',
  lines: [
    blankLine(),
    blankLine(),
  ],
});

const toNumber = (value) => {
  const parsed = Number(value ?? 0);
  return Number.isFinite(parsed) ? parsed : 0;
};

const totalDebit = computed(() => form.lines.reduce((sum, line) => sum + toNumber(line.debit), 0));
const totalCredit = computed(() => form.lines.reduce((sum, line) => sum + toNumber(line.credit), 0));
const difference = computed(() => totalDebit.value - totalCredit.value);
const isBalanced = computed(() => totalDebit.value > 0 && Math.abs(difference.value) < 0.01);

const addLine = () => form.lines.push(blankLine());

const removeLine = (index) => {
  if (form.lines.length <= 2) return;
  form.lines.splice(index, 1);
};

const submit = () => {
  form.post(route('erp.accounting.opening-balance.store'), {
    preserveScroll: true,
    onSuccess: () => {
      form.reset();
      form.company_id = companyIdForForm();
      form.entry_date = new Date().toISOString().slice(0, 10);
      form.lines = [blankLine(), blankLine()];
    },
  });
};
</script>

<template>
  <Head title="Accounting - Saldo Awal" />
  <AppLayout>
    <div class="space-y-5">
      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Accounting Workspace</p>
              <h1 class="ocn-panel__title mt-1">Saldo Awal</h1>
              <p class="ocn-panel__desc mt-1">Jurnal pembuka awal periode yang langsung masuk General Ledger.</p>
            </div>
            <Link class="btn btn-ghost btn-sm shrink-0 gap-1.5" :href="route('erp.accounting')">
              <ArrowLeftIcon class="h-4 w-4" />
              Back
            </Link>
          </div>
        </div>
      </div>

      <form class="ocn-panel" @submit.prevent="submit">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
              <h2 class="ocn-panel__title">Input saldo awal</h2>
              <p class="ocn-panel__desc">Total debit dan kredit wajib seimbang sebelum diposting.</p>
            </div>
            <div class="grid grid-cols-1 gap-2 text-sm sm:grid-cols-3 lg:min-w-[520px]">
              <div class="rounded-lg border border-base-300 bg-base-100 p-3">
                <p class="text-xs uppercase tracking-wide text-base-content/60">Debit</p>
                <p class="mt-1 font-semibold">{{ format(totalDebit) }}</p>
              </div>
              <div class="rounded-lg border border-base-300 bg-base-100 p-3">
                <p class="text-xs uppercase tracking-wide text-base-content/60">Kredit</p>
                <p class="mt-1 font-semibold">{{ format(totalCredit) }}</p>
              </div>
              <div class="rounded-lg border border-base-300 bg-base-100 p-3">
                <p class="text-xs uppercase tracking-wide text-base-content/60">Selisih</p>
                <p class="mt-1 font-semibold" :class="isBalanced ? 'text-success' : 'text-warning'">{{ format(Math.abs(difference)) }}</p>
              </div>
            </div>
          </div>
        </div>

        <div class="card-body space-y-4">
          <div v-if="!companies?.length" class="rounded-xl border border-warning/40 bg-warning/10 p-4 text-sm">
            <p class="font-medium text-base-content">Belum ada perusahaan aktif.</p>
            <p class="mt-1 text-base-content/70">Tambahkan perusahaan di master administrasi agar saldo awal bisa dipilih dan diposting.</p>
            <Link
              v-if="isAdmin"
              class="btn btn-warning btn-sm mt-3"
              :href="route('erp.admin.companies')"
            >
              Buka master perusahaan
            </Link>
          </div>

          <div v-else class="rounded-xl border border-base-300 bg-base-200/25 p-4 sm:p-5">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
              <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-base-content/60">Konteks jurnal</p>
                <p class="mt-0.5 text-sm text-base-content/70">Perusahaan, tanggal, dan keterangan mengikat satu batch posting saldo awal ke GL.</p>
              </div>
              <Link
                v-if="isAdmin"
                class="btn btn-ghost btn-xs shrink-0 gap-1 normal-case"
                :href="route('erp.admin.companies')"
              >
                Kelola perusahaan
              </Link>
            </div>

            <div class="mt-4 grid gap-4 md:grid-cols-12 md:items-end">
              <div class="md:col-span-5">
                <label class="label py-1"><span class="label-text text-xs font-semibold uppercase tracking-wide text-base-content/80">Perusahaan</span></label>
                <select v-model.number="form.company_id" class="select select-bordered w-full" required>
                  <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
                <p v-if="form.errors.company_id" class="mt-1 text-xs text-error">{{ form.errors.company_id }}</p>
              </div>
              <div class="md:col-span-3">
                <label class="label py-1"><span class="label-text text-xs font-semibold uppercase tracking-wide text-base-content/80">Tanggal</span></label>
                <input v-model="form.entry_date" type="date" class="input input-bordered w-full" />
                <p v-if="form.errors.entry_date" class="mt-1 text-xs text-error">{{ form.errors.entry_date }}</p>
              </div>
              <div class="md:col-span-4">
                <label class="label py-1"><span class="label-text text-xs font-semibold uppercase tracking-wide text-base-content/80">Keterangan</span></label>
                <input v-model="form.description" type="text" class="input input-bordered w-full" placeholder="Contoh: Saldo awal 1 Januari" />
                <p v-if="form.errors.description" class="mt-1 text-xs text-error">{{ form.errors.description }}</p>
              </div>
            </div>
          </div>

          <div v-if="!accounts?.length" class="alert alert-warning">
            <span>CoA aktif belum tersedia. Tambahkan akun aktif lebih dulu.</span>
          </div>

          <div class="overflow-x-auto">
            <table class="table table-zebra">
              <thead>
                <tr>
                  <th class="min-w-[260px]">Akun</th>
                  <th class="min-w-[140px] text-right">Debit</th>
                  <th class="min-w-[140px] text-right">Kredit</th>
                  <th class="min-w-[220px]">Catatan baris</th>
                  <th class="w-12"></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(line, index) in form.lines" :key="index">
                  <td>
                    <select v-model="line.account_id" class="select select-bordered select-sm w-full">
                      <option value="">Pilih akun</option>
                      <option v-for="account in accounts" :key="account.id" :value="account.id">
                        {{ account.code }} - {{ account.name }}
                      </option>
                    </select>
                  </td>
                  <td>
                    <input v-model.number="line.debit" type="number" min="0" step="0.01" class="input input-bordered input-sm w-full text-right" />
                  </td>
                  <td>
                    <input v-model.number="line.credit" type="number" min="0" step="0.01" class="input input-bordered input-sm w-full text-right" />
                  </td>
                  <td>
                    <input v-model="line.description" type="text" class="input input-bordered input-sm w-full" placeholder="Opsional" />
                  </td>
                  <td class="text-right">
                    <button type="button" class="btn btn-ghost btn-xs btn-square" :disabled="form.lines.length <= 2" @click="removeLine(index)">
                      <TrashIcon class="h-4 w-4" />
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <p v-if="form.errors.lines" class="text-sm text-error">{{ form.errors.lines }}</p>

          <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <button type="button" class="btn btn-outline btn-sm gap-1.5" @click="addLine">
              <PlusIcon class="h-4 w-4" />
              Tambah baris
            </button>
            <button type="submit" class="btn btn-primary btn-sm" :disabled="form.processing || !isBalanced || !accounts?.length || !form.company_id">
              Posting saldo awal
            </button>
          </div>
        </div>
      </form>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Riwayat saldo awal</h2>
        </div>
        <div class="overflow-x-auto">
          <table class="table table-zebra">
            <thead>
              <tr>
                <th>Perusahaan</th>
                <th>Tanggal</th>
                <th>No. Jurnal</th>
                <th>Keterangan</th>
                <th class="text-right">Debit</th>
                <th class="text-right">Kredit</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="entry in openingEntries" :key="entry.id">
                <td class="text-sm">{{ entry.company_name ?? '-' }}</td>
                <td>{{ entry.entry_date }}</td>
                <td class="font-mono text-xs">{{ entry.entry_no }}</td>
                <td>
                  <p class="font-medium">{{ entry.description ?? '-' }}</p>
                  <p class="font-mono text-xs text-base-content/50">{{ entry.source_reference }}</p>
                </td>
                <td class="text-right">{{ format(entry.total_debit) }}</td>
                <td class="text-right">{{ format(entry.total_credit) }}</td>
              </tr>
              <tr v-if="!openingEntries?.length">
                <td colspan="6" class="py-6 text-center text-sm text-base-content/60">Belum ada saldo awal yang diposting.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
