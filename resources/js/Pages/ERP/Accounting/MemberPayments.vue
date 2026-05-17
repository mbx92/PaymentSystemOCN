<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CurrencyInput from '@/Components/CurrencyInput.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
  ArrowLeftIcon,
  ArrowDownTrayIcon,
  BanknotesIcon,
  CheckCircleIcon,
  ClockIcon,
  UserGroupIcon,
} from '@heroicons/vue/24/outline';
import { computed, ref, watch } from 'vue';
import { useCurrency } from '@/composables/useCurrency';
import { useDateFormat } from '@/composables/useDateFormat';

const props = defineProps({
  members: Array,
  distributions: Array,
  summary: Object,
  filters: Object,
  years: Array,
  cashAccounts: Array,
});

const { formatDate } = useDateFormat();

const { format } = useCurrency();

const userId = ref(props.filters?.user_id ?? '');
const year = ref(props.filters?.year ?? '');
const status = ref(props.filters?.status ?? '');

watch([userId, year, status], ([u, y, s]) => {
  router.get(route('erp.accounting.payments.member'), {
    user_id: u || undefined,
    year: y || undefined,
    status: s || undefined,
  }, { preserveState: false, preserveScroll: true });
});

const exportExcel = () => {
  window.location.href = route('export.member-payments', {
    user_id: userId.value,
    year: year.value,
    status: status.value,
  });
};

const selectedDistribution = ref(null);

const paymentForm = useForm({
  payment_date: new Date().toISOString().slice(0, 10),
  amount: 0,
  cash_account_id: '',
  note: '',
});

const openPayment = (distribution) => {
  if (distribution.is_paid) return;
  selectedDistribution.value = distribution;
  paymentForm.reset();
  paymentForm.payment_date = new Date().toISOString().slice(0, 10);
  paymentForm.amount = Number(distribution.total_pay || 0);
  paymentForm.cash_account_id = props.cashAccounts?.[0]?.id ?? '';
  paymentForm.note = '';
  document.getElementById('modal-pay-member')?.showModal();
};

const submitPayment = () => {
  if (!selectedDistribution.value) return;
  paymentForm.post(route('erp.accounting.payments.member.store', selectedDistribution.value.id), {
    preserveScroll: true,
    onSuccess: () => {
      paymentForm.reset();
      selectedDistribution.value = null;
      document.getElementById('modal-pay-member')?.close();
    },
  });
};

const unpaidDistributions = computed(() => (props.distributions ?? []).filter((row) => !row.is_paid));
const paidDistributions = computed(() => (props.distributions ?? []).filter((row) => row.is_paid));

const totalDistributions = computed(() => (props.distributions ?? []).length);
const paidCount = computed(() => paidDistributions.value.length);
const unpaidCount = computed(() => unpaidDistributions.value.length);
const paymentProgress = computed(() => {
  if (totalDistributions.value === 0) return 0;
  return Math.round((paidCount.value / totalDistributions.value) * 100);
});
const totalPayable = computed(() => (
  Number(props.summary?.outstanding_total ?? 0) + Number(props.summary?.paid_total ?? 0)
));
</script>

<template>
  <Head title="Accounting - Pembayaran Anggota" />
  <AppLayout>
    <div class="space-y-5">
      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Accounting Workspace</p>
              <h1 class="ocn-panel__title mt-1">Pembayaran Anggota Tim</h1>
              <p class="ocn-panel__desc mt-1">
                Distribusi honor tim per project. Pembayaran diposting ke kas keluar (biaya tim) dan muncul di cashflow.
              </p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
              <button class="btn btn-success btn-sm gap-2" @click="exportExcel">
                <ArrowDownTrayIcon class="h-4 w-4" />
                Export Excel
              </button>
              <Link class="btn btn-ghost btn-sm shrink-0 gap-1.5" :href="route('erp.accounting.payments')">
                <ArrowLeftIcon class="h-4 w-4" />
                Back
              </Link>
            </div>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article class="ocn-panel ocn-stat-card member-stat-card member-stat-card--warning">
          <div class="card-body p-5">
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="text-xs font-bold uppercase tracking-wide text-base-content/50">Belum dibayar</p>
                <p class="mt-2 text-2xl font-bold tabular-nums text-warning">{{ format(summary?.outstanding_total ?? 0) }}</p>
                <p class="mt-2 text-xs text-base-content/60">
                  {{ unpaidCount }} distribusi · {{ summary?.open_count ?? 0 }} slot terbuka
                </p>
              </div>
              <div class="member-stat-card__icon bg-warning/15 text-warning">
                <ClockIcon class="h-6 w-6 shrink-0" />
              </div>
            </div>
          </div>
        </article>

        <article class="ocn-panel ocn-stat-card member-stat-card member-stat-card--success">
          <div class="card-body p-5">
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="text-xs font-bold uppercase tracking-wide text-base-content/50">Sudah dibayar</p>
                <p class="mt-2 text-2xl font-bold tabular-nums text-success">{{ format(summary?.paid_total ?? 0) }}</p>
                <p class="mt-2 text-xs text-base-content/60">{{ paidCount }} distribusi lunas</p>
              </div>
              <div class="member-stat-card__icon bg-success/15 text-success">
                <CheckCircleIcon class="h-6 w-6 shrink-0" />
              </div>
            </div>
          </div>
        </article>

        <article class="ocn-panel ocn-stat-card member-stat-card member-stat-card--primary">
          <div class="card-body p-5">
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0 flex-1">
                <p class="text-xs font-bold uppercase tracking-wide text-base-content/50">Progress pelunasan</p>
                <p class="mt-2 text-2xl font-bold tabular-nums text-primary">{{ paymentProgress }}%</p>
                <progress class="progress progress-primary mt-3 h-2 w-full" :value="paymentProgress" max="100" />
                <p class="mt-2 text-xs text-base-content/60">
                  {{ paidCount }} dari {{ totalDistributions }} distribusi
                </p>
              </div>
              <div class="member-stat-card__icon bg-primary/15 text-primary">
                <UserGroupIcon class="h-6 w-6 shrink-0" />
              </div>
            </div>
          </div>
        </article>

        <article class="ocn-panel ocn-stat-card member-stat-card member-stat-card--info">
          <div class="card-body p-5">
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="text-xs font-bold uppercase tracking-wide text-base-content/50">Total honor</p>
                <p class="mt-2 text-2xl font-bold tabular-nums text-info">{{ format(totalPayable) }}</p>
                <p class="mt-2 text-xs text-base-content/60">Akumulasi sesuai filter aktif</p>
                <Link
                  :href="route('erp.accounting.cashflow', { source: 'member_payment' })"
                  class="btn btn-ghost btn-xs mt-3 gap-1 px-0 text-info hover:bg-info/10"
                >
                  Lihat di cashflow →
                </Link>
              </div>
              <div class="member-stat-card__icon bg-info/15 text-info">
                <BanknotesIcon class="h-6 w-6 shrink-0" />
              </div>
            </div>
          </div>
        </article>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Filter</h2>
          <p class="ocn-panel__desc">Persempit daftar distribusi berdasarkan anggota, tahun, dan status.</p>
        </div>
        <div class="card-body">
          <div class="grid gap-3 sm:grid-cols-3">
            <div>
              <label class="label py-1"><span class="label-text text-xs font-medium text-base-content/60">Anggota</span></label>
              <select v-model="userId" class="select select-bordered select-sm w-full">
                <option value="">Semua anggota</option>
                <option v-for="m in members" :key="m.id" :value="m.id">{{ m.name }}</option>
              </select>
            </div>
            <div>
              <label class="label py-1"><span class="label-text text-xs font-medium text-base-content/60">Tahun</span></label>
              <select v-model="year" class="select select-bordered select-sm w-full">
                <option value="">Semua tahun</option>
                <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
              </select>
            </div>
            <div>
              <label class="label py-1"><span class="label-text text-xs font-medium text-base-content/60">Status</span></label>
              <select v-model="status" class="select select-bordered select-sm w-full">
                <option value="">Semua status</option>
                <option value="unpaid">Belum dibayar</option>
                <option value="paid">Sudah dibayar</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head flex flex-wrap items-center justify-between gap-2">
          <div>
            <h2 class="ocn-panel__title">Distribusi belum dibayar</h2>
            <p class="ocn-panel__desc">Posting pembayaran akan tercatat di cashflow sebagai kas keluar biaya tim.</p>
          </div>
          <span v-if="unpaidCount" class="badge badge-warning badge-outline">{{ unpaidCount }} menunggu</span>
        </div>
        <div class="overflow-x-auto">
          <table class="table table-zebra">
            <thead>
              <tr>
                <th>Anggota</th>
                <th>Project</th>
                <th>Status Project</th>
                <th>Peran</th>
                <th class="text-right">%</th>
                <th class="text-right">Base Pay</th>
                <th class="text-right">Bonus</th>
                <th class="text-right">Total</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="d in unpaidDistributions" :key="d.id">
                <td class="font-medium">{{ d.user_name }}</td>
                <td>{{ d.project_name }}</td>
                <td><StatusBadge :status="d.project_status" /></td>
                <td class="capitalize">{{ d.role_in_project }}</td>
                <td class="text-right">{{ d.percentage }}%</td>
                <td class="text-right">{{ format(d.base_pay) }}</td>
                <td class="text-right">{{ format(d.bonus) }}</td>
                <td class="text-right font-semibold text-warning">{{ format(d.total_pay) }}</td>
                <td class="text-right">
                  <button class="btn btn-primary btn-xs" @click="openPayment(d)">Bayar</button>
                </td>
              </tr>
              <tr v-if="unpaidDistributions.length === 0">
                <td colspan="9" class="py-8 text-center text-base-content/50">Tidak ada distribusi yang menunggu pembayaran.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div v-if="paidDistributions.length" class="ocn-panel">
        <div class="ocn-panel__head flex flex-wrap items-center justify-between gap-2">
          <div>
            <h2 class="ocn-panel__title">Riwayat pembayaran anggota</h2>
            <p class="ocn-panel__desc">Sudah diposting ke cashflow accounting.</p>
          </div>
          <span class="badge badge-success badge-outline">{{ paidCount }} lunas</span>
        </div>
        <div class="overflow-x-auto">
          <table class="table table-sm">
            <thead>
              <tr>
                <th>Anggota</th>
                <th>Project</th>
                <th>Peran</th>
                <th class="text-right">Total</th>
                <th>Tgl Bayar</th>
                <th>Status</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="d in paidDistributions" :key="d.id">
                <td class="font-medium">{{ d.user_name }}</td>
                <td>{{ d.project_name }}</td>
                <td class="capitalize">{{ d.role_in_project }}</td>
                <td class="text-right">{{ format(d.total_pay) }}</td>
                <td class="whitespace-nowrap">{{ formatDate(d.payment_date ?? d.paid_at) }}</td>
                <td><StatusBadge status="paid" /></td>
                <td class="text-right">
                  <Link
                    :href="route('erp.accounting.cashflow', { source: 'member_payment', q: d.user_name })"
                    class="btn btn-ghost btn-xs"
                  >
                    Cashflow
                  </Link>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <dialog id="modal-pay-member" class="modal">
        <div class="modal-box max-w-xl">
          <h3 class="text-lg font-bold">Bayar Anggota Tim</h3>
          <p v-if="selectedDistribution" class="mt-1 text-sm text-base-content/70">
            {{ selectedDistribution.user_name }} — {{ selectedDistribution.project_name }}.
            Total: {{ format(selectedDistribution.total_pay) }}
          </p>

          <div class="mt-4 grid gap-3">
            <div>
              <label class="label"><span class="label-text">Tanggal Bayar</span></label>
              <input v-model="paymentForm.payment_date" type="date" class="input input-bordered w-full" />
              <p v-if="paymentForm.errors.payment_date" class="mt-1 text-xs text-error">{{ paymentForm.errors.payment_date }}</p>
            </div>
            <div>
              <label class="label"><span class="label-text">Akun Kas/Bank</span></label>
              <select v-model="paymentForm.cash_account_id" class="select select-bordered w-full">
                <option value="" disabled>Pilih akun kas/bank</option>
                <option v-for="account in cashAccounts" :key="account.id" :value="account.id">
                  {{ account.code }} - {{ account.name }}
                </option>
              </select>
              <p v-if="paymentForm.errors.cash_account_id" class="mt-1 text-xs text-error">{{ paymentForm.errors.cash_account_id }}</p>
            </div>
            <div>
              <CurrencyInput
                v-model="paymentForm.amount"
                label="Nominal"
                :required="true"
                :error="paymentForm.errors.amount"
              />
            </div>
            <div>
              <label class="label"><span class="label-text">Catatan</span></label>
              <textarea v-model="paymentForm.note" class="textarea textarea-bordered w-full" rows="3" />
              <p v-if="paymentForm.errors.note" class="mt-1 text-xs text-error">{{ paymentForm.errors.note }}</p>
            </div>
          </div>

          <div class="modal-action">
            <form method="dialog">
              <button class="btn btn-ghost">Batal</button>
            </form>
            <button class="btn btn-primary" :disabled="paymentForm.processing" @click="submitPayment">
              Posting Pembayaran
            </button>
          </div>
        </div>
      </dialog>
    </div>
  </AppLayout>
</template>

<style scoped>
.member-stat-card::before {
  height: 3px;
}

.member-stat-card--warning::before {
  background: linear-gradient(90deg, #f59e0b, #fbbf24);
}

.member-stat-card--success::before {
  background: linear-gradient(90deg, #16a34a, #4ade80);
}

.member-stat-card--primary::before {
  background: linear-gradient(90deg, #2563eb, #0ea5e9);
}

.member-stat-card--info::before {
  background: linear-gradient(90deg, #0891b2, #38bdf8);
}

.member-stat-card__icon {
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 0.75rem;
  padding: 0.625rem;
  flex-shrink: 0;
}
</style>
