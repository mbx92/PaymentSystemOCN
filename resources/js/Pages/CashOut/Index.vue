<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CurrencyInput from '@/Components/CurrencyInput.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import DataTablePagination from '@/Components/DataTablePagination.vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useCurrency } from '@/composables/useCurrency';
import { ArrowLeftIcon, PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline';
import { useDateFormat } from '@/composables/useDateFormat';

const props = defineProps({ cashOuts: Object, total: Number, projects: Array, cashAccounts: Array, categoryOptions: Array, filters: Object });

const { formatDate } = useDateFormat();
const { format } = useCurrency();

const filters = ref({ ...props.filters });
let timer;
watch(filters, (val) => {
    clearTimeout(timer);
    timer = setTimeout(() => router.get(route('cash-out.index'), val, { preserveState: true, replace: true }), 400);
}, { deep: true });

const defaultCategory = (preferred) => props.categoryOptions?.some((category) => category.value === preferred)
    ? preferred
    : (props.categoryOptions?.[0]?.value ?? preferred);
const categoryLabelMap = computed(() => Object.fromEntries((props.categoryOptions ?? []).map((category) => [category.value, category.label])));
const categoryLabel = (value) => categoryLabelMap.value[value] ?? value;

const form = useForm({ project_id: '', cash_account_id: '', category: defaultCategory('biaya_tim'), amount: 0, date: new Date().toISOString().slice(0,10), note: '', recipient_name: '' });
const editingId = ref(null);
const editForm = useForm({ project_id: '', cash_account_id: '', category: defaultCategory('biaya_tim'), amount: 0, date: '', note: '', recipient_name: '' });

const submitAdd = () => form.post(route('cash-out.store'), {
    onSuccess: () => { form.reset(); document.getElementById('modal-add-cash-out').close(); }
});
const openEdit = (c) => {
    editingId.value = c.id;
    Object.assign(editForm, { project_id: c.project_id ?? '', cash_account_id: c.cash_account_id ?? '', category: c.category, amount: c.amount, date: c.date, note: c.note ?? '', recipient_name: c.recipient_name ?? '' });
    document.getElementById('modal-edit-cash-out').showModal();
};
const submitEdit = () => editForm.put(route('cash-out.update', editingId.value), {
    onSuccess: () => document.getElementById('modal-edit-cash-out').close()
});
const selected = ref(null);
const openDetail = (c) => { selected.value = c; document.getElementById('modal-detail-cash-out')?.showModal(); };
const deletingId = ref(null);
const confirmDelete = () => {
    deletingId.value = selected.value.id;
    document.getElementById('modal-detail-cash-out')?.close();
    document.getElementById('modal-delete-cash-out')?.showModal();
};
const doDelete = () => {
    router.delete(route('cash-out.destroy', deletingId.value), {
        onSuccess: () => { deletingId.value = null; selected.value = null; }
    });
    document.getElementById('modal-delete-cash-out')?.close();
};
</script>

<template>
    <AppLayout>
        <div class="space-y-5">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">Expenses Tim / Project</h1>
                <div class="flex flex-wrap items-center gap-2">
                    <button class="btn btn-error btn-sm" onclick="document.getElementById('modal-add-cash-out').showModal()">+ Input Expense</button>
                    <Link class="btn btn-ghost btn-sm shrink-0 gap-1.5" :href="route('erp.accounting.cashflow')">
                        <ArrowLeftIcon class="h-4 w-4" />
                        Back
                    </Link>
                </div>
            </div>
            <div class="flex flex-wrap gap-3">
                <select v-model="filters.project_id" class="select select-bordered select-sm">
                    <option value="">Semua Project</option>
                    <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
                <select v-model="filters.category" class="select select-bordered select-sm">
                    <option value="">Semua Kategori</option>
                    <option v-for="category in categoryOptions" :key="category.value" :value="category.value">{{ category.label }}</option>
                </select>
                <input v-model="filters.date_from" type="date" class="input input-bordered input-sm" />
                <input v-model="filters.date_to" type="date" class="input input-bordered input-sm" />
            </div>
            <div class="stats shadow">
                <div class="stat py-3">
                    <div class="stat-title text-sm">Total (filter aktif)</div>
                    <div class="stat-value text-xl text-error">{{ format(total) }}</div>
                </div>
            </div>
            <div class="ocn-panel">
                <div class="ocn-panel__head">
                    <h2 class="ocn-panel__title">Daftar expenses</h2>
                    <p class="ocn-panel__desc">Biaya tim, operasional, referral, dan pengeluaran project sesuai filter.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="table table-xs table-zebra">
                        <thead>
                            <tr class="text-[11px] uppercase tracking-wide text-base-content/55">
                                <th>Tanggal</th><th>Project</th><th>Kategori</th><th>Jumlah</th><th>Status</th><th>Jurnal</th><th>Penerima</th><th>Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="c in cashOuts.data" :key="c.id" class="cursor-pointer transition-colors hover:bg-primary/5 border-l-2 border-l-error" @click="openDetail(c)">
                                <td class="whitespace-nowrap tabular-nums">{{ formatDate(c.date) }}</td>
                                <td class="font-medium max-w-[9rem] truncate" :title="c.project_name">{{ c.project_name }}</td>
                                <td><span class="badge badge-ghost">{{ categoryLabel(c.category) }}</span></td>
                                <td class="font-semibold text-error tabular-nums whitespace-nowrap">{{ format(c.amount) }}</td>
                                <td><StatusBadge :status="c.document_status" size="badge-xs" /></td>
                                <td class="font-mono text-[10px]">{{ c.journal_entry_id ? String(c.journal_entry_id).slice(0, 8) + '…' : '-' }}</td>
                                <td class="max-w-[7rem] truncate" :title="c.recipient_name">{{ c.recipient_name ?? '-' }}</td>
                                <td class="text-base-content/70 max-w-[10rem] truncate" :title="c.note">{{ c.note ?? '-' }}</td>
                            </tr>
                            <tr v-if="!cashOuts.data.length"><td colspan="8" class="text-center py-10 text-base-content/50">Tidak ada data</td></tr>
                        </tbody>
                    </table>
                </div>
                <DataTablePagination :paginator="cashOuts" @update:per-page="(n) => { filters.per_page = n; }" />
            </div>
        </div>

        <!-- Modal: Detail -->
        <dialog id="modal-detail-cash-out" class="modal">
            <div class="modal-box max-w-lg p-0 overflow-hidden">
                <div v-if="selected" class="bg-base-200 px-6 py-4 border-b border-base-300 flex items-start justify-between gap-4">
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-base-content/40">Bukti Kas Keluar</p>
                        <h3 class="text-lg font-bold mt-0.5">Voucher Pengeluaran</h3>
                    </div>
                    <div class="flex items-center gap-1.5 shrink-0">
                        <button class="btn btn-ghost btn-xs btn-square" title="Edit" @click="openEdit(selected); document.getElementById('modal-detail-cash-out')?.close()">
                            <PencilSquareIcon class="h-4 w-4" />
                        </button>
                        <button class="btn btn-ghost btn-xs btn-square text-error" title="Hapus" @click="confirmDelete">
                            <TrashIcon class="h-4 w-4" />
                        </button>
                        <StatusBadge :status="selected.document_status" />
                    </div>
                </div>
                <div v-if="selected" class="px-6 pt-5 pb-3 space-y-5">
                    <div class="grid grid-cols-[9rem_1fr] gap-x-4 gap-y-2.5 text-sm">
                        <span class="text-base-content/50">Tanggal</span>
                        <span class="font-medium">{{ formatDate(selected.date) }}</span>
                        <span class="text-base-content/50">No. Jurnal</span>
                        <span class="font-mono text-xs">{{ selected.journal_entry_id ?? '—' }}</span>
                        <span class="text-base-content/50">Project</span>
                        <span class="font-medium">{{ selected.project_name }}</span>
                        <span class="text-base-content/50">Kategori</span>
                        <span><span class="badge badge-ghost badge-sm">{{ categoryLabel(selected.category) }}</span></span>
                        <span class="text-base-content/50">Sumber Dana</span>
                        <span>{{ selected.cash_account_name ?? '—' }}</span>
                        <span class="text-base-content/50">Penerima</span>
                        <span class="font-medium">{{ selected.recipient_name ?? '—' }}</span>
                    </div>
                    <div class="divider my-0" />
                    <div>
                        <p class="text-xs text-base-content/40 mb-1">Keterangan</p>
                        <p class="text-sm">{{ selected.note || '—' }}</p>
                    </div>
                    <div class="divider my-0" />
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-base-content/40">Total Pengeluaran</span>
                        <span class="text-2xl font-bold text-error tabular-nums">{{ format(selected.amount) }}</span>
                    </div>
                    <div class="divider my-0" />
                    <div class="flex items-center justify-between text-xs text-base-content/40">
                        <span>Dicatat oleh: <span class="font-medium text-base-content/60">{{ selected.creator_name }}</span></span>
                        <span v-if="selected.cash_account_id">ID: {{ selected.cash_account_id }}</span>
                    </div>
                </div>
                <div class="px-6 py-4 bg-base-200 border-t border-base-300 flex justify-end gap-2">
                    <form method="dialog"><button class="btn btn-ghost btn-sm">Tutup</button></form>
                </div>
            </div>
        </dialog>

        <!-- Modal: Add -->
        <dialog id="modal-add-cash-out" class="modal">
            <div class="modal-box">
                <h3 class="font-bold text-lg">Input Expense</h3>
                <div class="space-y-3 mt-4">
                    <div>
                        <label class="label"><span class="label-text">Project (Opsional)</span></label>
                        <select v-model="form.project_id" class="select select-bordered w-full">
                            <option value="">Operasional umum (tanpa project)</option>
                            <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="label"><span class="label-text">Sumber Dana Kas/Bank</span></label>
                        <select v-model="form.cash_account_id" class="select select-bordered w-full">
                            <option value="" disabled>-- Pilih Akun Kas/Bank --</option>
                            <option v-for="acc in cashAccounts" :key="acc.id" :value="acc.id">{{ acc.code }} - {{ acc.name }}</option>
                        </select>
                        <p v-if="form.errors.cash_account_id" class="text-error text-xs mt-1">{{ form.errors.cash_account_id }}</p>
                    </div>
                    <div>
                        <label class="label"><span class="label-text">Kategori</span></label>
                        <select v-model="form.category" class="select select-bordered w-full">
                            <option v-for="category in categoryOptions" :key="category.value" :value="category.value">{{ category.label }}</option>
                        </select>
                    </div>
                    <CurrencyInput v-model="form.amount" label="Jumlah" :required="true" :error="form.errors.amount" />
                    <div>
                        <label class="label"><span class="label-text">Tanggal</span></label>
                        <input v-model="form.date" type="date" class="input input-bordered w-full" />
                        <p v-if="form.errors.date" class="text-error text-xs mt-1">{{ form.errors.date }}</p>
                    </div>
                    <div>
                        <label class="label"><span class="label-text">Penerima</span></label>
                        <input v-model="form.recipient_name" type="text" class="input input-bordered w-full" placeholder="Nama penerima (opsional)" />
                    </div>
                    <div>
                        <label class="label"><span class="label-text">Keterangan</span></label>
                        <input v-model="form.note" type="text" class="input input-bordered w-full" />
                    </div>
                </div>
                <div class="modal-action">
                    <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
                    <button class="btn btn-error" :disabled="form.processing" @click="submitAdd">Simpan Expense</button>
                </div>
            </div>
        </dialog>

        <!-- Modal: Edit -->
        <dialog id="modal-edit-cash-out" class="modal">
            <div class="modal-box">
                <h3 class="font-bold text-lg">Edit Expense</h3>
                <div class="space-y-3 mt-4">
                    <div>
                        <label class="label"><span class="label-text">Project (Opsional)</span></label>
                        <select v-model="editForm.project_id" class="select select-bordered w-full">
                            <option value="">Operasional umum (tanpa project)</option>
                            <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="label"><span class="label-text">Sumber Dana Kas/Bank</span></label>
                        <select v-model="editForm.cash_account_id" class="select select-bordered w-full">
                            <option value="" disabled>-- Pilih Akun Kas/Bank --</option>
                            <option v-for="acc in cashAccounts" :key="acc.id" :value="acc.id">{{ acc.code }} - {{ acc.name }}</option>
                        </select>
                        <p v-if="editForm.errors.cash_account_id" class="text-error text-xs mt-1">{{ editForm.errors.cash_account_id }}</p>
                    </div>
                    <div>
                        <label class="label"><span class="label-text">Kategori</span></label>
                        <select v-model="editForm.category" class="select select-bordered w-full">
                            <option v-for="category in categoryOptions" :key="category.value" :value="category.value">{{ category.label }}</option>
                        </select>
                    </div>
                    <CurrencyInput v-model="editForm.amount" label="Jumlah" :required="true" />
                    <div>
                        <label class="label"><span class="label-text">Tanggal</span></label>
                        <input v-model="editForm.date" type="date" class="input input-bordered w-full" />
                        <p v-if="editForm.errors.date" class="text-error text-xs mt-1">{{ editForm.errors.date }}</p>
                    </div>
                    <div>
                        <label class="label"><span class="label-text">Penerima</span></label>
                        <input v-model="editForm.recipient_name" type="text" class="input input-bordered w-full" />
                    </div>
                    <div>
                        <label class="label"><span class="label-text">Keterangan</span></label>
                        <input v-model="editForm.note" type="text" class="input input-bordered w-full" />
                    </div>
                </div>
                <div class="modal-action">
                    <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
                    <button class="btn btn-primary" :disabled="editForm.processing" @click="submitEdit">Simpan</button>
                </div>
            </div>
        </dialog>

        <ConfirmModal id="modal-delete-cash-out" title="Hapus Expense" message="Hapus data expense ini?" @confirm="doDelete" />
    </AppLayout>
</template>
