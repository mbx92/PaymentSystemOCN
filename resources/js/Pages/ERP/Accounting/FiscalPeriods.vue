<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeftIcon, LockClosedIcon, LockOpenIcon } from '@heroicons/vue/24/outline';
import { computed, ref, watch } from 'vue';
import { useDateFormat } from '@/composables/useDateFormat';

const props = defineProps({
  companies: Array,
  selected_company_id: [Number, null],
  selected_year: Number,
  periods: Object,
});

const { formatDateTime } = useDateFormat();

const selectedCompanyId = ref(props.selected_company_id ?? props.companies?.[0]?.id ?? '');
const selectedYear = ref(props.selected_year ?? new Date().getFullYear());
const yearOptions = Array.from({ length: 8 }, (_, index) => new Date().getFullYear() - index);

watch([selectedCompanyId, selectedYear], ([companyId, year]) => {
  router.get(route('erp.accounting.fiscal-periods'), {
    company_id: companyId || undefined,
    year,
  }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
});

const closeForm = useForm({
  company_id: props.selected_company_id ?? props.companies?.[0]?.id ?? '',
  period_type: 'monthly',
  period_year: props.selected_year ?? new Date().getFullYear(),
  period_month: new Date().getMonth() + 1,
  notes: '',
});

watch(selectedCompanyId, (value) => {
  closeForm.company_id = value || '';
});

watch(selectedYear, (value) => {
  closeForm.period_year = value;
});

const selectedCompany = computed(() =>
  (props.companies ?? []).find((company) => Number(company.id) === Number(selectedCompanyId.value)) ?? null,
);

const monthlyRows = computed(() => props.periods?.monthly ?? []);
const yearlyRow = computed(() => props.periods?.yearly ?? null);
const closedMonthCount = computed(() => monthlyRows.value.filter((row) => row.is_closed).length);

const closeYear = () => {
  closeForm.period_type = 'yearly';
  closeForm.period_month = null;
  closeForm.post(route('erp.accounting.fiscal-periods.store'), { preserveScroll: true });
};

const closeMonth = (month) => {
  closeForm.period_type = 'monthly';
  closeForm.period_month = month;
  closeForm.post(route('erp.accounting.fiscal-periods.store'), { preserveScroll: true });
};

const reopen = (periodId) => {
  useForm({}).post(route('erp.accounting.fiscal-periods.reopen', periodId), { preserveScroll: true });
};
</script>

<template>
  <Head title="Accounting - Tutup Buku" />

  <AppLayout>
    <div class="space-y-5">
      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Accounting Workspace</p>
              <h1 class="ocn-panel__title mt-1">Tutup Buku</h1>
              <p class="ocn-panel__desc mt-1">Kunci periode bulanan atau tahunan agar transaksi yang sudah final tidak berubah lagi.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
              <select v-model.number="selectedCompanyId" class="select select-bordered select-sm w-full sm:w-auto">
                <option v-for="company in companies ?? []" :key="company.id" :value="company.id">{{ company.name }}</option>
              </select>
              <select v-model.number="selectedYear" class="select select-bordered select-sm w-full sm:w-auto">
                <option v-for="year in yearOptions" :key="year" :value="year">{{ year }}</option>
              </select>
              <Link class="btn btn-ghost btn-sm shrink-0 gap-1.5" :href="route('erp.accounting')">
                <ArrowLeftIcon class="h-4 w-4" />
                Back
              </Link>
            </div>
          </div>
        </div>
      </div>

      <div v-if="!selectedCompany" class="alert alert-warning">
        <span>Pilih perusahaan aktif terlebih dahulu untuk mengelola tutup buku.</span>
      </div>

      <template v-else>
        <div class="grid gap-4 xl:grid-cols-[1.4fr_0.8fr]">
          <div class="ocn-panel">
            <div class="ocn-panel__head">
              <h2 class="ocn-panel__title">Tutup buku tahunan</h2>
              <p class="ocn-panel__desc">Penutupan tahunan akan mengunci seluruh tanggal dalam tahun {{ selectedYear }} untuk {{ selectedCompany.name }}.</p>
            </div>
            <div class="card-body space-y-4">
              <div class="rounded-2xl border border-base-300 bg-base-100 p-4">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                  <div>
                    <p class="text-sm font-semibold">{{ yearlyRow?.label }}</p>
                    <p class="mt-1 text-xs text-base-content/60">{{ yearlyRow?.journal_count ?? 0 }} jurnal di tahun ini</p>
                    <p v-if="yearlyRow?.closed_at" class="mt-2 text-xs text-base-content/60">
                      Ditutup {{ formatDateTime(yearlyRow.closed_at) }} oleh {{ yearlyRow.closed_by_name || 'sistem' }}
                    </p>
                    <p v-if="yearlyRow?.notes" class="mt-1 text-xs text-base-content/70">{{ yearlyRow.notes }}</p>
                  </div>
                  <div class="flex items-center gap-2">
                    <span class="badge" :class="yearlyRow?.is_closed ? 'badge-error' : 'badge-success'">
                      {{ yearlyRow?.is_closed ? 'Closed' : 'Open' }}
                    </span>
                    <button
                      v-if="!yearlyRow?.is_closed"
                      type="button"
                      class="btn btn-error btn-sm gap-1.5"
                      @click="closeYear"
                    >
                      <LockClosedIcon class="h-4 w-4" />
                      Tutup Tahun
                    </button>
                    <button
                      v-else-if="yearlyRow?.id"
                      type="button"
                      class="btn btn-outline btn-sm gap-1.5"
                      @click="reopen(yearlyRow.id)"
                    >
                      <LockOpenIcon class="h-4 w-4" />
                      Buka Lagi
                    </button>
                  </div>
                </div>
              </div>

              <label class="form-control">
                <span class="label-text text-xs font-semibold uppercase tracking-wide text-base-content/70">Catatan tutup buku</span>
                <textarea
                  v-model="closeForm.notes"
                  class="textarea textarea-bordered h-24"
                  placeholder="Opsional: contoh rekonsiliasi selesai, siap audit, atau closing final manajemen."
                />
                <span v-if="closeForm.errors.notes" class="mt-1 text-xs text-error">{{ closeForm.errors.notes }}</span>
              </label>
            </div>
          </div>

          <div class="grid gap-4 sm:grid-cols-3 xl:grid-cols-1">
            <div class="rounded-2xl border border-emerald-100 bg-white p-5 shadow-sm">
              <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">Perusahaan</p>
              <p class="mt-2 text-lg font-bold">{{ selectedCompany.name }}</p>
            </div>
            <div class="rounded-2xl border border-sky-100 bg-white p-5 shadow-sm">
              <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">Bulan Closed</p>
              <p class="mt-2 text-2xl font-bold text-primary">{{ closedMonthCount }}/12</p>
            </div>
            <div class="rounded-2xl border border-amber-100 bg-white p-5 shadow-sm">
              <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">Status Tahun</p>
              <p class="mt-2 text-lg font-bold" :class="yearlyRow?.is_closed ? 'text-error' : 'text-success'">
                {{ yearlyRow?.is_closed ? 'Closed' : 'Open' }}
              </p>
            </div>
          </div>
        </div>

        <div class="ocn-panel">
          <div class="ocn-panel__head">
            <h2 class="ocn-panel__title">Tutup buku bulanan</h2>
            <p class="ocn-panel__desc">Setiap kartu mewakili satu bulan. Gunakan ini untuk mengunci bulan yang sudah selesai direkonsiliasi.</p>
          </div>
          <div class="card-body">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
              <div
                v-for="row in monthlyRows"
                :key="`${row.period_year}-${row.period_month}`"
                class="rounded-2xl border p-4 shadow-sm"
                :class="row.is_closed ? 'border-error/30 bg-error/5' : 'border-base-300 bg-base-100'"
              >
                <div class="flex items-start justify-between gap-3">
                  <div>
                    <p class="text-sm font-semibold">{{ row.label }}</p>
                    <p class="mt-1 text-xs text-base-content/60">{{ row.journal_count }} jurnal</p>
                  </div>
                  <span class="badge" :class="row.is_closed ? 'badge-error' : 'badge-success'">
                    {{ row.is_closed ? 'Closed' : 'Open' }}
                  </span>
                </div>

                <p class="mt-3 text-xs text-base-content/60">{{ row.start_date }} s/d {{ row.end_date }}</p>
                <p v-if="row.closed_at" class="mt-2 text-xs text-base-content/60">
                  Ditutup {{ formatDateTime(row.closed_at) }} oleh {{ row.closed_by_name || 'sistem' }}
                </p>
                <p v-if="row.notes" class="mt-1 text-xs text-base-content/70">{{ row.notes }}</p>

                <div class="mt-4 flex gap-2">
                  <button
                    v-if="!row.is_closed"
                    type="button"
                    class="btn btn-error btn-sm flex-1 gap-1.5"
                    @click="closeMonth(row.period_month)"
                  >
                    <LockClosedIcon class="h-4 w-4" />
                    Tutup
                  </button>
                  <button
                    v-else-if="row.id"
                    type="button"
                    class="btn btn-outline btn-sm flex-1 gap-1.5"
                    @click="reopen(row.id)"
                  >
                    <LockOpenIcon class="h-4 w-4" />
                    Buka Lagi
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </template>
    </div>
  </AppLayout>
</template>
