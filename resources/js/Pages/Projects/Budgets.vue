<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CurrencyInput from '@/Components/CurrencyInput.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useCurrency } from '@/composables/useCurrency';
import { MagnifyingGlassIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ budgets: Array });
const { format } = useCurrency();
const search = ref('');
const statusFilter = ref('');
const projectTypeFilter = ref('');

const filteredBudgets = computed(() => {
    const keyword = search.value.trim().toLowerCase();

    return props.budgets.filter((budget) => {
        const matchKeyword = !keyword
            || budget.name?.toLowerCase().includes(keyword)
            || budget.client_name?.toLowerCase().includes(keyword);

        const matchStatus = !statusFilter.value || budget.status === statusFilter.value;
        const matchType = !projectTypeFilter.value || budget.project_type === projectTypeFilter.value;

        return matchKeyword && matchStatus && matchType;
    });
});

const summary = computed(() => ({
    total: props.budgets.length,
    draft: props.budgets.filter((b) => b.status === 'draft').length,
    deal: props.budgets.filter((b) => b.status === 'deal').length,
    converted: props.budgets.filter((b) => b.status === 'converted').length,
    totalValue: props.budgets.reduce((sum, b) => sum + (Number(b.estimated_value) || 0), 0),
}));

const form = useForm({
    name: '',
    client_name: '',
    client_contact: '',
    project_type: 'system_website_development',
    estimated_value: 0,
    description: '',
});

const openAddModal = () => document.getElementById('modal-add-budget')?.showModal();
const submit = () => {
    form.post(route('erp.projects.budgets.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('name', 'client_name', 'client_contact', 'estimated_value', 'description');
            form.project_type = 'system_website_development';
            document.getElementById('modal-add-budget')?.close();
        },
    });
};
</script>

<template>
    <Head title="Budgeting Project" />
    <AppLayout>
        <div class="space-y-5">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Projects Workspace</p>
                        <h1 class="mt-2 text-3xl font-bold tracking-tight">Budgeting Project</h1>
                        <p class="mt-2 text-sm text-base-content/70">Klik baris untuk lihat detail budget, edit, dan aksi deal/convert.</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
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
                            <input v-model="search" type="text" placeholder="Cari nama project / klien..." class="grow" />
                        </label>
                        <select v-model="statusFilter" class="select select-bordered select-sm">
                            <option value="">Semua Status</option>
                            <option value="draft">Draft</option>
                            <option value="deal">Deal</option>
                            <option value="converted">Converted</option>
                        </select>
                        <select v-model="projectTypeFilter" class="select select-bordered select-sm">
                            <option value="">Semua Tipe</option>
                            <option value="system_website_development">System/Website Development</option>
                            <option value="cctv_installation">CCTV Installation</option>
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
                                v-for="budget in filteredBudgets"
                                :key="budget.id"
                                class="cursor-pointer hover"
                                tabindex="0"
                                @click="router.visit(route('erp.projects.budgets.show', budget.id))"
                                @keydown.enter.prevent="router.visit(route('erp.projects.budgets.show', budget.id))"
                            >
                                <td class="font-medium">{{ budget.name }}</td>
                                <td>{{ budget.client_name }}</td>
                                <td>{{ budget.project_type === 'system_website_development' ? 'System/Website Development' : 'CCTV Installation' }}</td>
                                <td>{{ budget.project_type === 'cctv_installation' ? (budget.cctv_items?.length ?? 0) : '-' }}</td>
                                <td>{{ format(budget.estimated_value) }}</td>
                                <td><span class="badge badge-ghost badge-sm">{{ budget.status }}</span></td>
                            </tr>
                            <tr v-if="!filteredBudgets.length"><td colspan="6" class="text-center py-6 text-base-content/50">Tidak ada budget yang cocok dengan filter.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <dialog id="modal-add-budget" class="modal">
            <div class="modal-box max-w-4xl">
                <h3 class="font-bold text-lg">Tambah Budget Project</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-3">
                    <div><label class="label"><span class="label-text">Nama Project</span></label><input v-model="form.name" type="text" class="input input-bordered w-full" /><p v-if="form.errors.name" class="text-error text-xs mt-1">{{ form.errors.name }}</p></div>
                    <div><label class="label"><span class="label-text">Nama Klien</span></label><input v-model="form.client_name" type="text" class="input input-bordered w-full" /><p v-if="form.errors.client_name" class="text-error text-xs mt-1">{{ form.errors.client_name }}</p></div>
                    <div><label class="label"><span class="label-text">Kontak Klien</span></label><input v-model="form.client_contact" type="text" class="input input-bordered w-full" /></div>
                    <div><label class="label"><span class="label-text">Tipe Project</span></label><select v-model="form.project_type" class="select select-bordered w-full"><option value="system_website_development">System/Website Development</option><option value="cctv_installation">CCTV Installation</option></select></div>
                    <div>
                        <CurrencyInput v-model="form.estimated_value" label="Estimasi Nilai Budget" :required="true" :error="form.errors.estimated_value" />
                        <p class="text-xs text-base-content/60 mt-1">Untuk CCTV, rincian item produk diisi setelah budget tersimpan, di halaman detail.</p>
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

