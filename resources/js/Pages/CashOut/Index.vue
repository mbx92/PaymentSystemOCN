<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CurrencyInput from '@/Components/CurrencyInput.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { useForm, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { useCurrency } from '@/composables/useCurrency';

const props = defineProps({ cashOuts: Object, total: Number, projects: Array, filters: Object });
const { format } = useCurrency();

const filters = ref({ ...props.filters });
let timer;
watch(filters, (val) => {
    clearTimeout(timer);
    timer = setTimeout(() => router.get(route('cash-out.index'), val, { preserveState: true, replace: true }), 400);
}, { deep: true });

const CATEGORIES = ['biaya_tim', 'komisi_referral', 'operasional', 'lainnya'];
const CATEGORY_LABELS = { biaya_tim: 'Biaya Tim', komisi_referral: 'Komisi Referral', operasional: 'Operasional', lainnya: 'Lainnya' };

const form = useForm({ project_id: '', category: 'biaya_tim', amount: 0, date: new Date().toISOString().slice(0,10), note: '', recipient_name: '' });
const editingId = ref(null);
const editForm = useForm({ category: 'biaya_tim', amount: 0, date: '', note: '', recipient_name: '' });

const submitAdd = () => form.post(route('cash-out.store'), {
    onSuccess: () => { form.reset(); document.getElementById('modal-add-cash-out').close(); }
});
const openEdit = (c) => {
    editingId.value = c.id;
    Object.assign(editForm, { category: c.category, amount: c.amount, date: c.date, note: c.note ?? '', recipient_name: c.recipient_name ?? '' });
    document.getElementById('modal-edit-cash-out').showModal();
};
const submitEdit = () => editForm.put(route('cash-out.update', editingId.value), {
    onSuccess: () => document.getElementById('modal-edit-cash-out').close()
});
const deletingId = ref(null);
const confirmDelete = (id) => { deletingId.value = id; document.getElementById('modal-delete-cash-out').showModal(); };
const doDelete = () => { router.delete(route('cash-out.destroy', deletingId.value)); document.getElementById('modal-delete-cash-out').close(); };
</script>

<template>
    <AppLayout>
        <div class="space-y-5">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold">Kas Keluar</h1>
                <button class="btn btn-error btn-sm" onclick="document.getElementById('modal-add-cash-out').showModal()">+ Tambah</button>
            </div>
            <div class="flex flex-wrap gap-3">
                <select v-model="filters.project_id" class="select select-bordered select-sm">
                    <option value="">Semua Project</option>
                    <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
                <select v-model="filters.category" class="select select-bordered select-sm">
                    <option value="">Semua Kategori</option>
                    <option v-for="cat in CATEGORIES" :key="cat" :value="cat">{{ CATEGORY_LABELS[cat] }}</option>
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
            <div class="card bg-base-100 shadow">
                <div class="overflow-x-auto">
                    <table class="table table-zebra">
                        <thead>
                            <tr><th>Tanggal</th><th>Project</th><th>Kategori</th><th>Jumlah</th><th>Penerima</th><th>Keterangan</th><th></th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="c in cashOuts.data" :key="c.id">
                                <td>{{ c.date }}</td>
                                <td class="font-medium">{{ c.project_name }}</td>
                                <td><span class="badge badge-sm badge-ghost">{{ CATEGORY_LABELS[c.category] ?? c.category }}</span></td>
                                <td class="font-semibold text-error">{{ format(c.amount) }}</td>
                                <td>{{ c.recipient_name ?? '-' }}</td>
                                <td class="text-sm text-base-content/70 max-w-xs truncate">{{ c.note ?? '-' }}</td>
                                <td>
                                    <div class="flex gap-1">
                                        <button class="btn btn-ghost btn-xs" @click="openEdit(c)">Edit</button>
                                        <button class="btn btn-ghost btn-xs text-error" @click="confirmDelete(c.id)">Hapus</button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!cashOuts.data.length"><td colspan="7" class="text-center py-10 text-base-content/50">Tidak ada data</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal: Add -->
        <dialog id="modal-add-cash-out" class="modal">
            <div class="modal-box">
                <h3 class="font-bold text-lg">Tambah Kas Keluar</h3>
                <div class="space-y-3 mt-4">
                    <div>
                        <label class="label"><span class="label-text">Project <span class="text-error">*</span></span></label>
                        <select v-model="form.project_id" class="select select-bordered w-full">
                            <option value="">-- Pilih Project --</option>
                            <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="label"><span class="label-text">Kategori</span></label>
                        <select v-model="form.category" class="select select-bordered w-full">
                            <option v-for="cat in CATEGORIES" :key="cat" :value="cat">{{ CATEGORY_LABELS[cat] }}</option>
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
                    <button class="btn btn-error" :disabled="form.processing" @click="submitAdd">Simpan</button>
                </div>
            </div>
        </dialog>

        <!-- Modal: Edit -->
        <dialog id="modal-edit-cash-out" class="modal">
            <div class="modal-box">
                <h3 class="font-bold text-lg">Edit Kas Keluar</h3>
                <div class="space-y-3 mt-4">
                    <div>
                        <label class="label"><span class="label-text">Kategori</span></label>
                        <select v-model="editForm.category" class="select select-bordered w-full">
                            <option v-for="cat in CATEGORIES" :key="cat" :value="cat">{{ CATEGORY_LABELS[cat] }}</option>
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

        <ConfirmModal id="modal-delete-cash-out" title="Hapus Kas Keluar" message="Hapus data kas keluar ini?" @confirm="doDelete" />
    </AppLayout>
</template>
