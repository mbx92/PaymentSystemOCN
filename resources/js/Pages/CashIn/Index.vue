<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CurrencyInput from '@/Components/CurrencyInput.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import DataTablePagination from '@/Components/DataTablePagination.vue';
import { useForm, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { useCurrency } from '@/composables/useCurrency';

const props = defineProps({ cashIns: Object, total: Number, projects: Array, paymentMethods: Array, cashAccounts: Array, filters: Object });
const { format } = useCurrency();

const filters = ref({ ...props.filters });
let timer;
watch(filters, (val) => {
    clearTimeout(timer);
    timer = setTimeout(() => router.get(route('cash-in.index'), val, { preserveState: true, replace: true }), 400);
}, { deep: true });

const form = useForm({
    project_id: '',
    payment_method_id: '',
    cash_account_id: '',
    category: 'pendapatan_jasa',
    amount: 0,
    date: new Date().toISOString().slice(0,10),
    note: '',
});
const editingId = ref(null);
const editForm = useForm({
    project_id: '',
    payment_method_id: '',
    cash_account_id: '',
    category: 'pendapatan_jasa',
    amount: 0,
    date: '',
    note: '',
});

const submitAdd = () => form.post(route('cash-in.store'), {
    onSuccess: () => { form.reset(); document.getElementById('modal-add-cash-in').close(); }
});

const openEdit = (c) => {
    editingId.value = c.id;
    editForm.project_id = c.project_id;
    editForm.payment_method_id = c.payment_method_id ?? '';
    editForm.cash_account_id = c.cash_account_id ?? '';
    editForm.category = c.category;
    editForm.amount = c.amount;
    editForm.date = c.date;
    editForm.note = c.note ?? '';
    document.getElementById('modal-edit-cash-in').showModal();
};
const submitEdit = () => editForm.put(route('cash-in.update', editingId.value), {
    onSuccess: () => document.getElementById('modal-edit-cash-in').close()
});

const deletingId = ref(null);
const confirmDelete = (id) => { deletingId.value = id; document.getElementById('modal-delete-cash-in').showModal(); };
const doDelete = () => { router.delete(route('cash-in.destroy', deletingId.value)); document.getElementById('modal-delete-cash-in').close(); };
</script>

<template>
    <AppLayout>
        <div class="space-y-5">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">Kas Masuk</h1>
                <button class="btn btn-success btn-sm" onclick="document.getElementById('modal-add-cash-in').showModal()">+ Tambah</button>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap gap-3">
                <select v-model="filters.project_id" class="select select-bordered select-sm">
                    <option value="">Semua Project</option>
                    <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
                <select v-model="filters.category" class="select select-bordered select-sm">
                    <option value="">Semua Kategori</option>
                    <option value="pendapatan_jasa">Pendapatan Jasa</option>
                    <option value="lainnya">Lainnya</option>
                </select>
                <input v-model="filters.date_from" type="date" class="input input-bordered input-sm" />
                <input v-model="filters.date_to" type="date" class="input input-bordered input-sm" />
            </div>

            <!-- Summary -->
            <div class="stats shadow">
                <div class="stat py-3">
                    <div class="stat-title text-sm">Total (filter aktif)</div>
                    <div class="stat-value text-xl text-success">{{ format(total) }}</div>
                </div>
            </div>

            <!-- Table -->
            <div class="ocn-panel">
                <div class="ocn-panel__head">
                    <h2 class="ocn-panel__title">Daftar kas masuk</h2>
                    <p class="ocn-panel__desc">Transaksi sesuai filter di atas.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="table table-zebra">
                        <thead>
                            <tr><th>Tanggal</th><th>Project</th><th>Metode</th><th>Kategori</th><th>Jumlah</th><th>Status</th><th>Jurnal</th><th>Keterangan</th><th>Oleh</th><th></th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="c in cashIns.data" :key="c.id">
                                <td>{{ c.date }}</td>
                                <td class="font-medium">{{ c.project_name }}</td>
                                <td>{{ c.payment_method_name || '-' }}</td>
                                <td><span class="badge badge-sm badge-ghost">{{ c.category }}</span></td>
                                <td class="font-semibold text-success">{{ format(c.amount) }}</td>
                                <td><StatusBadge :status="c.document_status" /></td>
                                <td class="font-mono text-xs">{{ c.journal_entry_id ?? '-' }}</td>
                                <td class="text-sm text-base-content/70 max-w-xs truncate">{{ c.note ?? '-' }}</td>
                                <td class="text-sm text-base-content/60">{{ c.creator_name }}</td>
                                <td>
                                    <div class="flex gap-1">
                                        <button class="btn btn-ghost btn-xs" @click="openEdit(c)">Edit</button>
                                        <button class="btn btn-ghost btn-xs text-error" @click="confirmDelete(c.id)">Hapus</button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!cashIns.data.length">
                                <td colspan="10" class="text-center py-10 text-base-content/50">Tidak ada data</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <DataTablePagination :paginator="cashIns" @update:per-page="(n) => { filters.per_page = n; }" />
            </div>
        </div>

        <!-- Modal: Add -->
        <dialog id="modal-add-cash-in" class="modal">
            <div class="modal-box">
                <h3 class="font-bold text-lg">Tambah Kas Masuk</h3>
                <div class="space-y-3 mt-4">
                    <div>
                        <label class="label"><span class="label-text">Project <span class="text-error">*</span></span></label>
                        <select v-model="form.project_id" class="select select-bordered w-full" :class="form.errors.project_id ? 'select-error' : ''">
                            <option value="">-- Pilih Project --</option>
                            <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="label"><span class="label-text">Kategori</span></label>
                        <select v-model="form.category" class="select select-bordered w-full">
                            <option value="pendapatan_jasa">Pendapatan Jasa</option>
                            <option value="lainnya">Lainnya</option>
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
                        <label class="label"><span class="label-text">Metode Pembayaran</span></label>
                        <select v-model="form.payment_method_id" class="select select-bordered w-full">
                            <option value="">-- Pilih Metode --</option>
                            <option v-for="method in paymentMethods" :key="method.id" :value="method.id">{{ method.name }}</option>
                        </select>
                        <p v-if="form.errors.payment_method_id" class="text-error text-xs mt-1">{{ form.errors.payment_method_id }}</p>
                    </div>
                    <CurrencyInput v-model="form.amount" label="Jumlah" :required="true" :error="form.errors.amount" />
                    <div>
                        <label class="label"><span class="label-text">Tanggal</span></label>
                        <input v-model="form.date" type="date" class="input input-bordered w-full" />
                        <p v-if="form.errors.date" class="text-error text-xs mt-1">{{ form.errors.date }}</p>
                    </div>
                    <div>
                        <label class="label"><span class="label-text">Keterangan</span></label>
                        <input v-model="form.note" type="text" class="input input-bordered w-full" />
                    </div>
                </div>
                <div class="modal-action">
                    <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
                    <button class="btn btn-success" :disabled="form.processing" @click="submitAdd">Simpan</button>
                </div>
            </div>
        </dialog>

        <!-- Modal: Edit -->
        <dialog id="modal-edit-cash-in" class="modal">
            <div class="modal-box">
                <h3 class="font-bold text-lg">Edit Kas Masuk</h3>
                <div class="space-y-3 mt-4">
                    <div>
                        <label class="label"><span class="label-text">Project <span class="text-error">*</span></span></label>
                        <select v-model="editForm.project_id" class="select select-bordered w-full" :class="editForm.errors.project_id ? 'select-error' : ''">
                            <option value="">-- Pilih Project --</option>
                            <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="label"><span class="label-text">Kategori</span></label>
                        <select v-model="editForm.category" class="select select-bordered w-full">
                            <option value="pendapatan_jasa">Pendapatan Jasa</option>
                            <option value="lainnya">Lainnya</option>
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
                        <label class="label"><span class="label-text">Metode Pembayaran</span></label>
                        <select v-model="editForm.payment_method_id" class="select select-bordered w-full">
                            <option value="">-- Pilih Metode --</option>
                            <option v-for="method in paymentMethods" :key="method.id" :value="method.id">{{ method.name }}</option>
                        </select>
                        <p v-if="editForm.errors.payment_method_id" class="text-error text-xs mt-1">{{ editForm.errors.payment_method_id }}</p>
                    </div>
                    <CurrencyInput v-model="editForm.amount" label="Jumlah" :required="true" />
                    <div>
                        <label class="label"><span class="label-text">Tanggal</span></label>
                        <input v-model="editForm.date" type="date" class="input input-bordered w-full" />
                        <p v-if="editForm.errors.date" class="text-error text-xs mt-1">{{ editForm.errors.date }}</p>
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

        <ConfirmModal id="modal-delete-cash-in" title="Hapus Kas Masuk" message="Hapus data kas masuk ini?" @confirm="doDelete" />
    </AppLayout>
</template>
