<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CurrencyInput from '@/Components/CurrencyInput.vue';
import DataTablePagination from '@/Components/DataTablePagination.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { computed, reactive, watch } from 'vue';
import { useCurrency } from '@/composables/useCurrency';
import {ArrowLeftIcon,
  MagnifyingGlassIcon} from '@heroicons/vue/24/outline';

const props = defineProps({
    budgets: Object,
    project_types: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});
const { format } = useCurrency();
const filters = reactive({
    q: props.filters?.q ?? '',
    status: props.filters?.status ?? '',
    project_type: props.filters?.project_type ?? '',
    per_page: Number(props.filters?.per_page ?? props.budgets?.per_page ?? 25),
});
const defaultProjectTypeKey = computed(() => props.project_types.find((type) => type.is_default)?.key ?? props.project_types[0]?.key ?? '');
const projectTypeByKey = computed(() => Object.fromEntries((props.project_types ?? []).map((type) => [type.key, type])));
const projectTypeLabel = (value) => projectTypeByKey.value[value]?.label ?? value;
const projectTypeSupportsBudgetItems = (value) => !!projectTypeByKey.value[value]?.supports_budget_items;
const selectedFilterType = computed(() => projectTypeByKey.value[filters.project_type] ?? null);
const budgetRows = computed(() => props.budgets?.data ?? []);

let timer;
watch(filters, (val) => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(route('erp.projects.budgets.index'), val, { preserveState: true, replace: true });
    }, 250);
}, { deep: true });

const summary = computed(() => ({
    total: props.budgets?.total ?? budgetRows.value.length,
    draft: budgetRows.value.filter((b) => b.status === 'draft').length,
    deal: budgetRows.value.filter((b) => b.status === 'deal').length,
    converted: budgetRows.value.filter((b) => b.status === 'converted').length,
    itemCount: budgetRows.value.reduce((sum, b) => sum + (b.supports_budget_items ? (b.cctv_items?.length ?? 0) : 0), 0),
    totalCost: budgetRows.value.reduce((sum, b) => sum + (Number(b.total_cost) || 0), 0),
    totalMargin: budgetRows.value.reduce((sum, b) => sum + (Number(b.total_margin) || 0), 0),
    totalValue: budgetRows.value.reduce((sum, b) => sum + (Number(b.estimated_value) || 0), 0),
}));

const isItemizedSummary = computed(() => !!selectedFilterType.value?.supports_budget_items);

const statusBadgeClass = (status) => ({
    draft: 'badge-info',
    deal: 'badge-warning',
    converted: 'badge-success',
}[status] ?? 'badge-ghost');

const form = useForm({
    name: '',
    client_name: '',
    client_contact: '',
    project_type: defaultProjectTypeKey.value,
    estimated_value: 0,
    description: '',
});

watch(
    () => form.project_type,
    (type) => {
        if (projectTypeSupportsBudgetItems(type)) {
            form.estimated_value = 0;
        }
    },
);

const openAddModal = () => document.getElementById('modal-add-budget')?.showModal();
const submit = () => {
    form.post(route('erp.projects.budgets.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('name', 'client_name', 'client_contact', 'estimated_value', 'description');
            form.project_type = defaultProjectTypeKey.value;
            document.getElementById('modal-add-budget')?.close();
        },
    });
};
</script>

<template>
    <Head title="Budgeting Project" />
    <AppLayout>
        <div class="space-y-5">
            <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Projects Workspace</p>
              <h1 class="ocn-panel__title mt-1">Budgeting Project</h1>
              <p class="ocn-panel__desc mt-1">Klik baris untuk lihat detail budget, edit, dan aksi deal/convert.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
              <Link class="btn btn-ghost btn-sm shrink-0 gap-1.5" :href="route('erp.projects')">
                <ArrowLeftIcon class="h-4 w-4" />
                Back
              </Link>
            </div>
          </div>
        </div>
      </div>

            <div v-if="isItemizedSummary" class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <article class="rounded-2xl border border-slate-700/40 bg-gradient-to-br from-slate-800 to-slate-950 p-5 text-white shadow-xl ring-1 ring-black/20">
                    <p class="text-xs font-semibold uppercase tracking-wide text-white/55">{{ selectedFilterType?.label || 'Budget Itemized' }}</p>
                    <p class="mt-3 text-2xl font-bold tabular-nums tracking-tight">{{ summary.total }}</p>
                </article>
                <article class="rounded-2xl border border-cyan-900/50 bg-gradient-to-br from-cyan-900 to-slate-950 p-5 text-white shadow-xl ring-1 ring-cyan-950/60">
                    <p class="text-xs font-semibold uppercase tracking-wide text-cyan-100/70">Jumlah Item</p>
                    <p class="mt-3 text-2xl font-bold tabular-nums tracking-tight">{{ summary.itemCount }}</p>
                </article>
                <article class="rounded-2xl border border-rose-900/50 bg-gradient-to-br from-rose-900 to-slate-950 p-5 text-white shadow-xl ring-1 ring-rose-950/60">
                    <p class="text-xs font-semibold uppercase tracking-wide text-rose-100/70">Estimasi HPP</p>
                    <p class="mt-3 text-xl font-bold tabular-nums tracking-tight">{{ format(summary.totalCost) }}</p>
                </article>
                <article class="rounded-2xl border border-emerald-900/50 bg-gradient-to-br from-emerald-900 to-slate-950 p-5 text-white shadow-xl ring-1 ring-emerald-950/60">
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-100/70">Estimasi Margin</p>
                    <p class="mt-3 text-xl font-bold tabular-nums tracking-tight">{{ format(summary.totalMargin) }}</p>
                </article>
                <article class="rounded-2xl border border-indigo-800/50 bg-gradient-to-br from-indigo-800 to-violet-950 p-5 text-white shadow-xl ring-1 ring-indigo-950/50">
                    <p class="text-xs font-semibold uppercase tracking-wide text-indigo-100/70">Total Nilai Budget</p>
                    <p class="mt-3 text-xl font-bold tabular-nums tracking-tight">{{ format(summary.totalValue) }}</p>
                </article>
            </div>

            <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <article class="rounded-2xl border border-slate-700/40 bg-gradient-to-br from-slate-800 to-slate-950 p-5 text-white shadow-xl ring-1 ring-black/20">
                    <p class="text-xs font-semibold uppercase tracking-wide text-white/55">Total Budget</p>
                    <p class="mt-3 text-2xl font-bold tabular-nums tracking-tight">{{ summary.total }}</p>
                </article>
                <article class="rounded-2xl border border-blue-900/50 bg-gradient-to-br from-blue-900 to-blue-950 p-5 text-white shadow-xl ring-1 ring-blue-950/60">
                    <p class="text-xs font-semibold uppercase tracking-wide text-blue-100/70">Draft</p>
                    <p class="mt-3 text-2xl font-bold tabular-nums tracking-tight">{{ summary.draft }}</p>
                </article>
                <article class="rounded-2xl border border-amber-900/50 bg-gradient-to-br from-amber-900 to-amber-950 p-5 text-white shadow-xl ring-1 ring-amber-950/60">
                    <p class="text-xs font-semibold uppercase tracking-wide text-amber-100/70">Deal</p>
                    <p class="mt-3 text-2xl font-bold tabular-nums tracking-tight">{{ summary.deal }}</p>
                </article>
                <article class="rounded-2xl border border-emerald-900/50 bg-gradient-to-br from-emerald-900 to-emerald-950 p-5 text-white shadow-xl ring-1 ring-emerald-950/60">
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-100/70">Converted</p>
                    <p class="mt-3 text-2xl font-bold tabular-nums tracking-tight">{{ summary.converted }}</p>
                </article>
                <article class="rounded-2xl border border-indigo-800/50 bg-gradient-to-br from-indigo-800 to-violet-950 p-5 text-white shadow-xl ring-1 ring-indigo-950/50">
                    <p class="text-xs font-semibold uppercase tracking-wide text-indigo-100/70">Total Nilai Budget</p>
                    <p class="mt-3 text-xl font-bold tabular-nums tracking-tight">{{ format(summary.totalValue) }}</p>
                </article>
            </div>

            <div class="ocn-panel">
                <div class="ocn-panel__head">
                    <h2 class="ocn-panel__title">Filter budget</h2>
                </div>
                <div class="card-body">
                    <div class="flex flex-wrap gap-3 items-center">
                        <label class="input input-bordered input-sm flex items-center gap-2 max-w-xs">
                            <MagnifyingGlassIcon class="w-4 h-4 opacity-50" />
                            <input v-model="filters.q" type="text" placeholder="Cari nama project / klien..." class="grow" />
                        </label>
                        <select v-model="filters.status" class="select select-bordered select-sm">
                            <option value="">Semua Status</option>
                            <option value="draft">Draft</option>
                            <option value="deal">Deal</option>
                            <option value="converted">Converted</option>
                        </select>
                        <select v-model="filters.project_type" class="select select-bordered select-sm">
                            <option value="">Semua Tipe</option>
                            <option v-for="type in project_types" :key="type.key" :value="type.key">{{ type.label }}</option>
                        </select>
                        <div class="ml-auto">
                            <button class="btn btn-primary btn-sm" @click="openAddModal">+ Tambah Budget</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="ocn-panel">
                <div class="ocn-panel__head">
                    <h2 class="ocn-panel__title">Daftar budget</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead><tr><th>Project</th><th>Klien</th><th>Tipe</th><th>Item</th><th>Estimasi</th><th>Status</th></tr></thead>
                        <tbody>
                            <tr
                                v-for="budget in budgetRows"
                                :key="budget.id"
                                class="cursor-pointer hover"
                                tabindex="0"
                                @click="router.visit(route('erp.projects.budgets.show', budget.id))"
                                @keydown.enter.prevent="router.visit(route('erp.projects.budgets.show', budget.id))"
                            >
                                <td class="font-medium">{{ budget.name }}</td>
                                <td>{{ budget.client_name }}</td>
                                <td>{{ budget.project_type_label }}</td>
                                <td>{{ budget.supports_budget_items ? (budget.cctv_items?.length ?? 0) : '-' }}</td>
                                <td>{{ format(budget.estimated_value) }}</td>
                                <td><span class="badge badge-sm capitalize" :class="statusBadgeClass(budget.status)">{{ budget.status }}</span></td>
                            </tr>
                            <tr v-if="!budgetRows.length"><td colspan="6" class="text-center py-6 text-base-content/50">Tidak ada budget yang cocok dengan filter.</td></tr>
                        </tbody>
                    </table>
                </div>
                <DataTablePagination :paginator="budgets" @update:per-page="(n) => { filters.per_page = n; }" />
            </div>
        </div>

        <dialog id="modal-add-budget" class="modal">
            <div class="modal-box max-w-4xl">
                <h3 class="font-bold text-lg">Tambah Budget Project</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-3">
                    <div><label class="label"><span class="label-text">Nama Project</span></label><input v-model="form.name" type="text" class="input input-bordered w-full" /><p v-if="form.errors.name" class="text-error text-xs mt-1">{{ form.errors.name }}</p></div>
                    <div><label class="label"><span class="label-text">Nama Klien</span></label><input v-model="form.client_name" type="text" class="input input-bordered w-full" /><p v-if="form.errors.client_name" class="text-error text-xs mt-1">{{ form.errors.client_name }}</p></div>
                    <div><label class="label"><span class="label-text">Kontak Klien</span></label><input v-model="form.client_contact" type="text" class="input input-bordered w-full" /></div>
                    <div><label class="label"><span class="label-text">Tipe Project</span></label><select v-model="form.project_type" class="select select-bordered w-full"><option v-for="type in project_types" :key="type.key" :value="type.key">{{ type.label }}</option></select></div>
                    <div v-if="!projectTypeSupportsBudgetItems(form.project_type)">
                        <CurrencyInput v-model="form.estimated_value" label="Estimasi Nilai Budget" :required="true" :error="form.errors.estimated_value" />
                    </div>
                    <div v-else class="rounded-lg border border-base-300 bg-base-200/40 p-3 text-sm text-base-content/70">
                        Nilai budget untuk tipe ini dihitung dari rincian item di halaman detail setelah budget dibuat.
                        <p v-if="form.errors.estimated_value" class="text-error text-xs mt-1">{{ form.errors.estimated_value }}</p>
                    </div>
                    <div class="md:col-span-2"><label class="label"><span class="label-text">Deskripsi</span></label><textarea v-model="form.description" class="textarea textarea-bordered w-full" rows="3" /></div>
                </div>
                <div class="modal-action">
                    <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
                    <button class="btn btn-primary" :disabled="form.processing" @click="submit">Simpan Budget</button>
                </div>
            </div>
        </dialog>
    </AppLayout>
</template>
