<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CurrencyInput from '@/Components/CurrencyInput.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useCurrency } from '@/composables/useCurrency';

const props = defineProps({ budget: Object });
const { format } = useCurrency();

const editForm = useForm({
    name: props.budget.name,
    client_name: props.budget.client_name,
    client_contact: props.budget.client_contact ?? '',
    project_type: props.budget.project_type,
    estimated_value: props.budget.estimated_value,
    cctv_items: (props.budget.cctv_items?.length ? props.budget.cctv_items : [{ name: '', qty: 1, unit_price: 0 }]).map((i) => ({ ...i })),
    description: props.budget.description ?? '',
});

const isCctv = computed(() => editForm.project_type === 'cctv_installation');
const totalCctvItems = computed(() => (editForm.cctv_items ?? []).reduce((s, r) => s + ((Number(r.qty) || 0) * (Number(r.unit_price) || 0)), 0));

const openEditModal = () => document.getElementById('modal-edit-budget')?.showModal();
const addCctvItem = () => editForm.cctv_items.push({ name: '', qty: 1, unit_price: 0 });
const removeCctvItem = (idx) => { if (editForm.cctv_items.length > 1) editForm.cctv_items.splice(idx, 1); };

const submitEdit = () => {
    if (isCctv.value) editForm.estimated_value = totalCctvItems.value;
    editForm.put(route('erp.projects.budgets.update', props.budget.id), {
        preserveScroll: true,
        onSuccess: () => document.getElementById('modal-edit-budget')?.close(),
    });
};

const markDeal = () => router.patch(route('erp.projects.budgets.deal', props.budget.id), {}, { preserveScroll: true });
const convert = () => router.post(route('erp.projects.budgets.convert', props.budget.id), {}, { preserveScroll: true });
const downloadPdf = () => window.open(route('erp.projects.budgets.pdf', props.budget.id), '_blank');
</script>

<template>
    <Head :title="`Budget - ${budget.name}`" />
    <AppLayout>
        <div class="space-y-5">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <Link :href="route('erp.projects.budgets.index')" class="btn btn-ghost btn-xs">← Budgeting Project</Link>
                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70 mt-1">Projects Workspace</p>
                        <h1 class="text-3xl font-bold tracking-tight mt-2">{{ budget.name }}</h1>
                        <p class="text-base-content/60">{{ budget.client_name }}</p>
                        <p class="mt-1 text-sm text-base-content/70">Tinjau detail budget, lakukan revisi, lalu lanjutkan proses deal atau convert.</p>
                    </div>
                    <div class="flex gap-2">
                        <Link :href="route('erp.projects')" class="btn btn-ghost btn-sm">Back</Link>
                        <span class="badge badge-ghost">{{ budget.status }}</span>
                        <button class="btn btn-outline btn-sm" @click="downloadPdf">PDF</button>
                        <button v-if="budget.status === 'draft'" class="btn btn-outline btn-sm" @click="markDeal">Tandai Deal</button>
                        <button v-if="budget.status === 'deal'" class="btn btn-primary btn-sm" @click="convert">Convert ke Project</button>
                        <Link v-if="budget.converted_project_id" :href="route('projects.show', budget.converted_project_id)" class="btn btn-ghost btn-sm">Lihat Project</Link>
                        <button v-if="budget.status !== 'converted'" class="btn btn-primary btn-sm" @click="openEditModal">Edit</button>
                    </div>
                </div>
            </div>

            <div class="card bg-base-100 shadow">
                <div class="card-body grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                    <div><span class="text-base-content/60">Kontak</span><div>{{ budget.client_contact || '-' }}</div></div>
                    <div><span class="text-base-content/60">Tipe</span><div>{{ budget.project_type === 'system_website_development' ? 'System/Website Development' : 'CCTV Installation' }}</div></div>
                    <div><span class="text-base-content/60">Estimasi</span><div class="font-semibold">{{ format(budget.estimated_value) }}</div></div>
                    <div><span class="text-base-content/60">Dibuat</span><div>{{ budget.created_at || '-' }}</div></div>
                    <div class="md:col-span-2"><span class="text-base-content/60">Deskripsi</span><div>{{ budget.description || '-' }}</div></div>
                </div>
            </div>

            <div v-if="budget.project_type === 'cctv_installation'" class="card bg-base-100 shadow">
                <div class="card-body p-0">
                    <div class="p-4 border-b border-base-300"><h2 class="font-semibold">Item CCTV</h2></div>
                    <table class="table table-sm">
                        <thead><tr><th>Produk</th><th>Qty</th><th>Harga Satuan</th><th>Subtotal</th></tr></thead>
                        <tbody>
                            <tr v-for="(item, idx) in budget.cctv_items" :key="idx">
                                <td>{{ item.name }}</td>
                                <td>{{ item.qty }}</td>
                                <td>{{ format(item.unit_price) }}</td>
                                <td class="font-medium">{{ format((Number(item.qty) || 0) * (Number(item.unit_price) || 0)) }}</td>
                            </tr>
                            <tr v-if="!budget.cctv_items?.length"><td colspan="4" class="text-center py-4 text-base-content/50">Tidak ada item.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <dialog id="modal-edit-budget" class="modal">
            <div class="modal-box max-w-4xl">
                <h3 class="font-bold text-lg">Edit Budget</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-3">
                    <div><label class="label"><span class="label-text">Nama Project</span></label><input v-model="editForm.name" type="text" class="input input-bordered w-full" /><p v-if="editForm.errors.name" class="text-error text-xs mt-1">{{ editForm.errors.name }}</p></div>
                    <div><label class="label"><span class="label-text">Nama Klien</span></label><input v-model="editForm.client_name" type="text" class="input input-bordered w-full" /></div>
                    <div><label class="label"><span class="label-text">Kontak Klien</span></label><input v-model="editForm.client_contact" type="text" class="input input-bordered w-full" /></div>
                    <div><label class="label"><span class="label-text">Tipe Project</span></label><select v-model="editForm.project_type" class="select select-bordered w-full"><option value="system_website_development">System/Website Development</option><option value="cctv_installation">CCTV Installation</option></select></div>
                    <div>
                        <CurrencyInput v-if="!isCctv" v-model="editForm.estimated_value" label="Estimasi Nilai Project" :required="true" :error="editForm.errors.estimated_value" />
                        <div v-else><label class="label"><span class="label-text">Total Item CCTV (Auto)</span></label><div class="input input-bordered w-full flex items-center bg-base-200">{{ format(totalCctvItems) }}</div></div>
                    </div>
                    <div class="md:col-span-2"><label class="label"><span class="label-text">Deskripsi</span></label><textarea v-model="editForm.description" class="textarea textarea-bordered w-full" rows="3" /></div>
                </div>
                <div v-if="isCctv" class="mt-4 space-y-2">
                    <div class="flex items-center justify-between"><h3 class="font-semibold">Item CCTV</h3><button class="btn btn-outline btn-xs" type="button" @click="addCctvItem">+ Item</button></div>
                    <div class="overflow-x-auto rounded-xl border border-base-300"><table class="table table-sm"><thead><tr><th>Produk</th><th>Qty</th><th>Harga Satuan</th><th>Subtotal</th><th></th></tr></thead><tbody><tr v-for="(item, idx) in editForm.cctv_items" :key="idx"><td><input v-model="item.name" type="text" class="input input-bordered input-sm w-full" /></td><td><input v-model.number="item.qty" type="number" min="0.01" step="0.01" class="input input-bordered input-sm w-24" /></td><td><input v-model.number="item.unit_price" type="number" min="0" step="1000" class="input input-bordered input-sm w-36" /></td><td>{{ format((Number(item.qty) || 0) * (Number(item.unit_price) || 0)) }}</td><td><button type="button" class="btn btn-ghost btn-xs text-error" @click="removeCctvItem(idx)">Hapus</button></td></tr></tbody></table></div>
                </div>
                <div class="modal-action">
                    <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
                    <button class="btn btn-primary" :disabled="editForm.processing" @click="submitEdit">Simpan Perubahan</button>
                </div>
            </div>
        </dialog>
    </AppLayout>
</template>

