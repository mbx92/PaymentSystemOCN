<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CurrencyInput from '@/Components/CurrencyInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { computed, ref } from 'vue';

const props = defineProps({
    period: Object,
    rows: Array,
});

const money = (n) => (n == null ? '—' : `Rp ${Number(n).toLocaleString('id-ID')}`);

const prev = computed(() => {
    let y = props.period?.year;
    let m = props.period?.month - 1;
    if (m < 1) { m = 12; y -= 1; }
    return { year: y, month: m };
});

const next = computed(() => {
    let y = props.period?.year;
    let m = props.period?.month + 1;
    if (m > 12) { m = 1; y += 1; }
    return { year: y, month: m };
});

const editingRow = ref(null);
const budgetForm = useForm({
    category_id: '',
    year: props.period?.year,
    month: props.period?.month,
    amount_limit: '',
});

const openBudget = (row) => {
    editingRow.value = row;
    budgetForm.clearErrors();
    budgetForm.category_id = String(row.category_id);
    budgetForm.year = props.period.year;
    budgetForm.month = props.period.month;
    budgetForm.amount_limit = row.amount_limit != null ? String(row.amount_limit) : '';
    document.getElementById('modal-budget')?.showModal();
};

const submitBudget = () => {
    budgetForm.transform((d) => ({
        category_id: Number(d.category_id),
        year: Number(d.year),
        month: Number(d.month),
        amount_limit: Number(d.amount_limit),
    })).post(route('personal.budgets.store'), {
        preserveScroll: true,
        onSuccess: () => document.getElementById('modal-budget')?.close(),
    });
};
</script>

<template>
  <Head title="Personal — Anggaran" />
  <AppLayout>
    <div class="space-y-5">
      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Personal</p>
              <h1 class="ocn-panel__title mt-1">Anggaran keluarga</h1>
              <p class="ocn-panel__desc mt-1">Plafon per kategori pengeluaran vs realisasi bulan {{ period?.label }}.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
              <div class="flex flex-wrap items-center gap-2">
            <Link
              class="btn btn-outline btn-sm"
              :href="route('personal.budgets', { year: prev.year, month: prev.month })"
            >← Bulan lalu</Link>
            <Link
              class="btn btn-outline btn-sm"
              :href="route('personal.budgets', { year: next.year, month: next.month })"
            >Bulan depan →</Link>
            <Link class="btn btn-ghost btn-sm shrink-0 gap-1.5" :href="route('personal')">
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
          <h2 class="ocn-panel__title">Realisasi vs anggaran</h2>
        </div>
        <div class="overflow-x-auto">
          <table class="table table-zebra">
            <thead>
              <tr>
                <th>Kategori</th>
                <th class="text-right">Plafon</th>
                <th class="text-right">Terpakai</th>
                <th class="text-right">Sisa</th>
                <th>Progres</th>
                <th />
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in rows" :key="row.category_id">
                <td class="font-medium">{{ row.category_name }}</td>
                <td class="text-right font-mono">{{ money(row.amount_limit) }}</td>
                <td class="text-right font-mono">{{ money(row.spent) }}</td>
                <td class="text-right font-mono" :class="row.remaining != null && row.remaining < 0 ? 'text-error' : ''">{{ money(row.remaining) }}</td>
                <td class="min-w-[140px]">
                  <progress
                    v-if="row.pct != null"
                    class="progress w-full"
                    :class="row.pct >= 100 ? 'progress-error' : 'progress-primary'"
                    :value="Math.min(100, row.pct)"
                    max="100"
                  />
                  <span v-else class="text-xs text-base-content/50">Atur plafon</span>
                </td>
                <td class="text-right">
                  <button type="button" class="btn btn-ghost btn-xs" @click="openBudget(row)">{{ row.amount_limit != null ? 'Ubah plafon' : 'Set plafon' }}</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <dialog id="modal-budget" class="modal">
      <div class="modal-box max-w-md">
        <h3 class="font-bold text-lg">Plafon anggaran</h3>
        <p v-if="editingRow" class="text-sm text-base-content/70 mt-1">{{ editingRow.category_name }} — {{ period?.label }}</p>
        <div class="mt-4 space-y-3">
          <div>
            <CurrencyInput
              v-model="budgetForm.amount_limit"
              label="Nominal plafon"
              placeholder="0"
              :error="budgetForm.errors.amount_limit"
            />
          </div>
        </div>
        <div class="modal-action">
          <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
          <button
            class="btn"
            :class="budgetForm.processing ? 'btn-secondary' : 'btn-primary'"
            :disabled="budgetForm.processing"
            @click="submitBudget"
          >Simpan</button>
        </div>
      </div>
    </dialog>
  </AppLayout>
</template>
