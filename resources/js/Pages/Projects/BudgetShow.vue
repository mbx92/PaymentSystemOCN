<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CurrencyInput from '@/Components/CurrencyInput.vue';
import ProductPickerModal from '@/Components/ProductPickerModal.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useCurrency } from '@/composables/useCurrency';

const props = defineProps({
    budget: Object,
    cctv_products: Array,
});
const { format } = useCurrency();

function normalizeCctvItems(raw) {
    const list = Array.isArray(raw) && raw.length
        ? raw.map((i) => ({ name: i.name ?? '', qty: i.qty ?? 1, unit_price: i.unit_price ?? 0 }))
        : [{ name: '', qty: 1, unit_price: 0 }];
    return list;
}

const budgetForm = useForm({
    name: props.budget.name,
    client_name: props.budget.client_name,
    client_contact: props.budget.client_contact ?? '',
    project_type: props.budget.project_type,
    estimated_value: props.budget.estimated_value,
    cctv_items: normalizeCctvItems(props.budget.cctv_items),
    description: props.budget.description ?? '',
});

watch(
    () => props.budget,
    (b) => {
        if (budgetForm.processing) return;
        budgetForm.name = b.name;
        budgetForm.client_name = b.client_name;
        budgetForm.client_contact = b.client_contact ?? '';
        budgetForm.project_type = b.project_type;
        budgetForm.estimated_value = b.estimated_value;
        budgetForm.description = b.description ?? '';
        budgetForm.cctv_items = normalizeCctvItems(b.cctv_items);
    },
    { deep: true },
);

const isCctv = computed(() => budgetForm.project_type === 'cctv_installation');
const totalCctvItems = computed(() => (budgetForm.cctv_items ?? []).reduce((s, r) => s + ((Number(r.qty) || 0) * (Number(r.unit_price) || 0)), 0));

/** Estimasi tampilan: untuk CCTV aktif, ikuti total item secara live; jika belum ada subtotal, pakai nilai form/server. */
const displayedEstimated = computed(() => {
    if (props.budget.status === 'converted') {
        return Number(props.budget.estimated_value);
    }
    if (!isCctv.value) {
        return Number(props.budget.estimated_value);
    }
    const t = totalCctvItems.value;
    if (t > 0) return t;
    return Number(budgetForm.estimated_value);
});

const canEditCctvItems = computed(() => props.budget.project_type === 'cctv_installation' && props.budget.status !== 'converted');
const showProductPicker = ref(false);

const openEditModal = () => document.getElementById('modal-edit-budget')?.showModal();
const addCctvItem = () => budgetForm.cctv_items.push({ name: '', qty: 1, unit_price: 0 });
const addCctvItemFromProduct = (product) => {
    budgetForm.cctv_items.push({
        name: product.name ?? '',
        qty: 1,
        unit_price: Number(product.selling_price ?? product.price ?? 0),
    });
    showProductPicker.value = false;
};
const removeCctvItem = (idx) => {
    if (budgetForm.cctv_items.length > 1) budgetForm.cctv_items.splice(idx, 1);
};

const submitBudgetPut = (opts = {}) => {
    if (isCctv.value && totalCctvItems.value > 0) {
        budgetForm.estimated_value = totalCctvItems.value;
    }
    budgetForm.put(route('erp.projects.budgets.update', props.budget.id), {
        preserveScroll: true,
        ...opts,
    });
};

const submitEdit = () => {
    submitBudgetPut({
        onSuccess: () => document.getElementById('modal-edit-budget')?.close(),
    });
};

const saveCctvItemsFromDetail = () => submitBudgetPut();

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
                    <div>
                        <span class="text-base-content/60">Estimasi</span>
                        <div class="font-semibold">{{ format(displayedEstimated) }}</div>
                        <p v-if="canEditCctvItems && totalCctvItems > 0" class="text-xs text-base-content/60 mt-0.5">Total dihitung otomatis dari item CCTV di bawah.</p>
                    </div>
                    <div><span class="text-base-content/60">Dibuat</span><div>{{ budget.created_at || '-' }}</div></div>
                    <div class="md:col-span-2"><span class="text-base-content/60">Deskripsi</span><div>{{ budget.description || '-' }}</div></div>
                </div>
            </div>

            <div v-if="budget.project_type === 'cctv_installation'" class="card bg-base-100 shadow">
                <div class="card-body p-0">
                    <div class="p-4 border-b border-base-300 flex flex-wrap items-center justify-between gap-2">
                        <h2 class="font-semibold">Item CCTV</h2>
                        <div v-if="canEditCctvItems" class="flex flex-wrap items-center gap-2">
                            <button type="button" class="btn btn-ghost btn-sm gap-1" @click="showProductPicker = true">Pilih dari master</button>
                            <button type="button" class="btn btn-outline btn-sm gap-1" @click="addCctvItem">+ Tambah item</button>
                            <button type="button" class="btn btn-primary btn-sm" :disabled="budgetForm.processing" @click="saveCctvItemsFromDetail">Simpan item &amp; estimasi</button>
                        </div>
                    </div>

                    <template v-if="canEditCctvItems">
                        <p v-if="budgetForm.errors.cctv_items" class="text-error text-xs px-4 pt-2">{{ budgetForm.errors.cctv_items }}</p>
                        <div class="overflow-x-auto">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Produk</th>
                                        <th>Qty</th>
                                        <th>Harga Satuan</th>
                                        <th>Subtotal</th>
                                        <th class="w-16"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(item, idx) in budgetForm.cctv_items" :key="idx">
                                        <td>
                                            <input v-model="item.name" type="text" class="input input-bordered input-sm w-full min-w-[8rem]" placeholder="Nama produk" />
                                            <p v-if="budgetForm.errors[`cctv_items.${idx}.name`]" class="text-error text-xs mt-0.5">{{ budgetForm.errors[`cctv_items.${idx}.name`] }}</p>
                                        </td>
                                        <td>
                                            <input v-model.number="item.qty" type="number" min="0.01" step="0.01" class="input input-bordered input-sm w-24" />
                                            <p v-if="budgetForm.errors[`cctv_items.${idx}.qty`]" class="text-error text-xs mt-0.5">{{ budgetForm.errors[`cctv_items.${idx}.qty`] }}</p>
                                        </td>
                                        <td>
                                            <input v-model.number="item.unit_price" type="number" min="0" step="1000" class="input input-bordered input-sm w-36" />
                                            <p v-if="budgetForm.errors[`cctv_items.${idx}.unit_price`]" class="text-error text-xs mt-0.5">{{ budgetForm.errors[`cctv_items.${idx}.unit_price`] }}</p>
                                        </td>
                                        <td class="font-medium">{{ format((Number(item.qty) || 0) * (Number(item.unit_price) || 0)) }}</td>
                                        <td>
                                            <button type="button" class="btn btn-ghost btn-xs text-error" :disabled="budgetForm.cctv_items.length <= 1" @click="removeCctvItem(idx)">Hapus</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p class="text-xs text-base-content/60 px-4 pb-4">Subtotal per baris menjumlah ke estimasi budget secara otomatis setelah disimpan.</p>
                    </template>

                    <template v-else>
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
                    </template>
                </div>
            </div>
        </div>

        <dialog id="modal-edit-budget" class="modal">
            <div class="modal-box max-w-4xl">
                <h3 class="font-bold text-lg">Edit Budget</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-3">
                    <div><label class="label"><span class="label-text">Nama Project</span></label><input v-model="budgetForm.name" type="text" class="input input-bordered w-full" /><p v-if="budgetForm.errors.name" class="text-error text-xs mt-1">{{ budgetForm.errors.name }}</p></div>
                    <div><label class="label"><span class="label-text">Nama Klien</span></label><input v-model="budgetForm.client_name" type="text" class="input input-bordered w-full" /></div>
                    <div><label class="label"><span class="label-text">Kontak Klien</span></label><input v-model="budgetForm.client_contact" type="text" class="input input-bordered w-full" /></div>
                    <div><label class="label"><span class="label-text">Tipe Project</span></label><select v-model="budgetForm.project_type" class="select select-bordered w-full"><option value="system_website_development">System/Website Development</option><option value="cctv_installation">CCTV Installation</option></select></div>
                    <div>
                        <CurrencyInput v-if="!isCctv" v-model="budgetForm.estimated_value" label="Estimasi Nilai Project" :required="true" :error="budgetForm.errors.estimated_value" />
                        <div v-else-if="totalCctvItems > 0">
                            <label class="label"><span class="label-text">Total Item CCTV (otomatis dari rincian)</span></label>
                            <div class="input input-bordered w-full flex items-center bg-base-200">{{ format(totalCctvItems) }}</div>
                        </div>
                        <div v-else>
                            <CurrencyInput v-model="budgetForm.estimated_value" label="Estimasi Nilai (belum ada item)" :required="true" :error="budgetForm.errors.estimated_value" />
                            <p class="text-xs text-base-content/60 mt-1">Tambahkan baris item di bawah untuk menghitung total dari produk &amp; qty.</p>
                        </div>
                    </div>
                    <div class="md:col-span-2"><label class="label"><span class="label-text">Deskripsi</span></label><textarea v-model="budgetForm.description" class="textarea textarea-bordered w-full" rows="3" /></div>
                </div>
                <div v-if="isCctv" class="mt-4 space-y-2">
                    <div class="flex items-center justify-between"><h3 class="font-semibold">Item CCTV</h3><div class="flex items-center gap-2"><button class="btn btn-ghost btn-xs" type="button" @click="showProductPicker = true">Pilih dari master</button><button class="btn btn-outline btn-xs" type="button" @click="addCctvItem">+ Tambah item</button></div></div>
                    <p v-if="budgetForm.errors.cctv_items" class="text-error text-xs">{{ budgetForm.errors.cctv_items }}</p>
                    <div class="overflow-x-auto rounded-xl border border-base-300"><table class="table table-sm"><thead><tr><th>Produk</th><th>Qty</th><th>Harga Satuan</th><th>Subtotal</th><th></th></tr></thead><tbody><tr v-for="(item, idx) in budgetForm.cctv_items" :key="idx"><td><input v-model="item.name" type="text" class="input input-bordered input-sm w-full" placeholder="Nama produk" /></td><td><input v-model.number="item.qty" type="number" min="0.01" step="0.01" class="input input-bordered input-sm w-24" /></td><td><input v-model.number="item.unit_price" type="number" min="0" step="1000" class="input input-bordered input-sm w-36" /></td><td>{{ format((Number(item.qty) || 0) * (Number(item.unit_price) || 0)) }}</td><td><button type="button" class="btn btn-ghost btn-xs text-error" @click="removeCctvItem(idx)">Hapus</button></td></tr></tbody></table></div>
                </div>
                <div class="modal-action">
                    <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
                    <button class="btn btn-primary" :disabled="budgetForm.processing" @click="submitEdit">Simpan Perubahan</button>
                </div>
            </div>
        </dialog>

        <ProductPickerModal
            :show="showProductPicker"
            :products="cctv_products"
            title="Pilih Product CCTV"
            subtitle="Pilih produk dari master product agar item budget selaras dengan modul lain."
            search-label="Cari SKU / Barcode / Nama Product"
            search-placeholder="Contoh: CAM-4MP-OUTDOOR"
            confirm-text="Tambah ke Item CCTV"
            radio-name="selected_product_budget"
            @close="showProductPicker = false"
            @confirm="addCctvItemFromProduct"
        />
    </AppLayout>
</template>

