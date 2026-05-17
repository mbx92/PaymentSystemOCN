<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CurrencyInput from '@/Components/CurrencyInput.vue';
import ProductPickerModal from '@/Components/ProductPickerModal.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { computed, reactive, ref, watch } from 'vue';
import { useCurrency } from '@/composables/useCurrency';

const props = defineProps({
    budget: Object,
    cctv_products: Array,
});
const { format } = useCurrency();

function normalizeCctvItems(raw) {
    const list = Array.isArray(raw) && raw.length
        ? raw.map((i) => ({
            master_product_id: i.master_product_id ?? null,
            item_type: i.item_type ?? 'material',
            name: i.name ?? '',
            uom: i.uom ?? 'unit',
            qty: i.qty ?? 1,
            unit_cost: i.unit_cost ?? 0,
            unit_price: i.unit_price ?? 0,
            notes: i.notes ?? '',
        }))
        : [{ master_product_id: null, item_type: 'material', name: '', uom: 'unit', qty: 1, unit_cost: 0, unit_price: 0, notes: '' }];
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
const totalCctvCost = computed(() => (budgetForm.cctv_items ?? []).reduce((s, r) => s + ((Number(r.qty) || 0) * (Number(r.unit_cost) || 0)), 0));
const totalCctvMargin = computed(() => totalCctvItems.value - totalCctvCost.value);

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
const productPicker = reactive({ lineIndex: null });
const suppressProductPickerOpen = ref(false);

const openEditModal = () => document.getElementById('modal-edit-budget')?.showModal();
const addCctvItem = () => budgetForm.cctv_items.push({ master_product_id: null, item_type: 'material', name: '', uom: 'unit', qty: 1, unit_cost: 0, unit_price: 0, notes: '' });
const productLabel = (item) => {
    if (item.master_product_id && item.notes) return `${item.notes.replace(/^SKU:\s*/, '')} - ${item.name}`.trim();
    return item.name ?? '';
};
const applyProductToItem = (item, product) => {
    if (!item || !product) return;
    item.master_product_id = product.id ?? null;
    item.item_type = product.product_type === 'service' ? 'service' : 'material';
    item.name = product.name ?? '';
    item.uom = product.uom ?? 'unit';
    item.unit_price = Number(product.selling_price ?? product.price ?? 0);
    item.notes = product.sku ? `SKU: ${product.sku}` : '';
};
const openProductPickerForLine = (idx) => {
    if (suppressProductPickerOpen.value) return;
    productPicker.lineIndex = idx;
    showProductPicker.value = true;
};
const addCctvItemFromProduct = (product) => {
    const row = {
        master_product_id: product.id ?? null,
        item_type: product.product_type === 'service' ? 'service' : 'material',
        name: product.name ?? '',
        uom: product.uom ?? 'unit',
        qty: 1,
        unit_cost: 0,
        unit_price: Number(product.selling_price ?? product.price ?? 0),
        notes: product.sku ? `SKU: ${product.sku}` : '',
    };
    budgetForm.cctv_items.push(row);
};
const chooseProduct = (product) => {
    suppressProductPickerOpen.value = true;
    if (productPicker.lineIndex !== null) {
        applyProductToItem(budgetForm.cctv_items[productPicker.lineIndex], product);
        productPicker.lineIndex = null;
    } else {
        addCctvItemFromProduct(product);
    }
    showProductPicker.value = false;
    window.setTimeout(() => {
        suppressProductPickerOpen.value = false;
    }, 250);
};
const openAddProductPicker = () => {
    if (suppressProductPickerOpen.value) return;
    productPicker.lineIndex = null;
    showProductPicker.value = true;
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
            <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Projects Workspace</p>
              <h1 class="ocn-panel__title mt-1">{{ budget.name }}</h1>
              <p class="text-sm text-base-content/60 mt-1">{{ budget.client_name }}</p>
                        <p class="ocn-panel__desc mt-1">Tinjau detail budget, lakukan revisi, lalu lanjutkan proses deal atau convert.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
              <div class="flex flex-wrap justify-end gap-2">
                        <span class="badge badge-ghost">{{ budget.status }}</span>
                        <button class="btn btn-outline btn-sm" @click="downloadPdf">PDF</button>
                        <button v-if="budget.status === 'draft'" class="btn btn-outline btn-sm" @click="markDeal">Tandai Deal</button>
                        <button v-if="budget.status === 'deal'" class="btn btn-primary btn-sm" @click="convert">Convert ke Project</button>
                        <Link v-if="budget.converted_project_id" :href="route('projects.show', budget.converted_project_id)" class="btn btn-ghost btn-sm">Lihat Project</Link>
                        <button v-if="budget.status !== 'converted'" class="btn btn-primary btn-sm" @click="openEditModal">Edit</button>
                        <Link class="btn btn-ghost btn-sm shrink-0 gap-1.5" :href="route('erp.projects.budgets.index')">
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
                    <h2 class="ocn-panel__title">Ringkasan budget</h2>
                </div>
                <div class="card-body grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                    <div><span class="text-base-content/60">Kontak</span><div>{{ budget.client_contact || '-' }}</div></div>
                    <div><span class="text-base-content/60">Tipe</span><div>{{ budget.project_type === 'system_website_development' ? 'System/Website Development' : 'CCTV Installation' }}</div></div>
                    <div>
                        <span class="text-base-content/60">Estimasi</span>
                        <div class="font-semibold">{{ format(displayedEstimated) }}</div>
                        <p v-if="canEditCctvItems && totalCctvItems > 0" class="text-xs text-base-content/60 mt-0.5">Total dihitung otomatis dari item CCTV di bawah.</p>
                    </div>
                    <div v-if="budget.project_type === 'cctv_installation'"><span class="text-base-content/60">Estimasi HPP</span><div>{{ format(budget.total_cost ?? totalCctvCost) }}</div></div>
                    <div v-if="budget.project_type === 'cctv_installation'"><span class="text-base-content/60">Estimasi Margin</span><div class="font-semibold text-success">{{ format(budget.total_margin ?? totalCctvMargin) }}</div></div>
                    <div><span class="text-base-content/60">Dibuat</span><div>{{ budget.created_at || '-' }}</div></div>
                    <div class="md:col-span-2"><span class="text-base-content/60">Deskripsi</span><div>{{ budget.description || '-' }}</div></div>
                </div>
            </div>

            <div v-if="budget.project_type === 'cctv_installation'" class="ocn-panel">
                <div class="ocn-panel__head flex flex-wrap items-center justify-between gap-2">
                    <h2 class="ocn-panel__title">Item CCTV</h2>
                    <div v-if="canEditCctvItems" class="flex flex-wrap items-center gap-2 shrink-0">
                        <button type="button" class="btn btn-ghost btn-sm gap-1" @click="openAddProductPicker">Pilih dari master</button>
                        <button type="button" class="btn btn-outline btn-sm gap-1" @click="addCctvItem">+ Tambah item</button>
                        <button type="button" class="btn btn-primary btn-sm" :disabled="budgetForm.processing" @click="saveCctvItemsFromDetail">Simpan item &amp; estimasi</button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <template v-if="canEditCctvItems">
                        <p v-if="budgetForm.errors.cctv_items" class="text-error text-xs px-4 pt-2">{{ budgetForm.errors.cctv_items }}</p>
                        <div class="overflow-x-auto">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Tipe</th>
                                        <th>Item</th>
                                        <th>UOM</th>
                                        <th>Qty</th>
                                        <th>HPP</th>
                                        <th>Harga Jual</th>
                                        <th>Subtotal</th>
                                        <th>Margin</th>
                                        <th class="w-16"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(item, idx) in budgetForm.cctv_items" :key="idx">
                                        <td>
                                            <select v-model="item.item_type" class="select select-bordered select-sm w-28">
                                                <option value="material">Material</option>
                                                <option value="product">Produk</option>
                                                <option value="service">Jasa</option>
                                            </select>
                                        </td>
                                        <td>
                                            <input
                                                :value="productLabel(item)"
                                                type="text"
                                                class="input input-bordered input-sm w-full min-w-[12rem] cursor-pointer"
                                                placeholder="Klik untuk pilih produk"
                                                readonly
                                                @click="openProductPickerForLine(idx)"
                                                @focus="openProductPickerForLine(idx)"
                                            />
                                            <p v-if="budgetForm.errors[`cctv_items.${idx}.name`]" class="text-error text-xs mt-0.5">{{ budgetForm.errors[`cctv_items.${idx}.name`] }}</p>
                                        </td>
                                        <td><input v-model="item.uom" type="text" class="input input-bordered input-sm w-24" /></td>
                                        <td>
                                            <input v-model.number="item.qty" type="number" min="0.01" step="0.01" class="input input-bordered input-sm w-24" />
                                            <p v-if="budgetForm.errors[`cctv_items.${idx}.qty`]" class="text-error text-xs mt-0.5">{{ budgetForm.errors[`cctv_items.${idx}.qty`] }}</p>
                                        </td>
                                        <td><input v-model.number="item.unit_cost" type="number" min="0" step="1000" class="input input-bordered input-sm w-32" /></td>
                                        <td>
                                            <input v-model.number="item.unit_price" type="number" min="0" step="1000" class="input input-bordered input-sm w-36" />
                                            <p v-if="budgetForm.errors[`cctv_items.${idx}.unit_price`]" class="text-error text-xs mt-0.5">{{ budgetForm.errors[`cctv_items.${idx}.unit_price`] }}</p>
                                        </td>
                                        <td class="font-medium">{{ format((Number(item.qty) || 0) * (Number(item.unit_price) || 0)) }}</td>
                                        <td class="font-medium text-success">{{ format(((Number(item.qty) || 0) * (Number(item.unit_price) || 0)) - ((Number(item.qty) || 0) * (Number(item.unit_cost) || 0))) }}</td>
                                        <td>
                                            <button type="button" class="btn btn-ghost btn-xs text-error" :disabled="budgetForm.cctv_items.length <= 1" @click="removeCctvItem(idx)">Hapus</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="flex flex-wrap gap-4 px-4 pb-4 text-sm">
                            <span>Total jual: <strong>{{ format(totalCctvItems) }}</strong></span>
                            <span>Total HPP: <strong>{{ format(totalCctvCost) }}</strong></span>
                            <span>Margin: <strong class="text-success">{{ format(totalCctvMargin) }}</strong></span>
                        </div>
                    </template>

                    <template v-else>
                        <table class="table table-sm">
                            <thead><tr><th>Tipe</th><th>Item</th><th>Qty</th><th>HPP</th><th>Harga Jual</th><th>Subtotal</th><th>Margin</th></tr></thead>
                            <tbody>
                                <tr v-for="(item, idx) in budget.cctv_items" :key="idx">
                                    <td>{{ item.item_type ?? 'material' }}</td>
                                    <td>{{ item.name }}</td>
                                    <td>{{ item.qty }} {{ item.uom ?? 'unit' }}</td>
                                    <td>{{ format(item.unit_cost ?? 0) }}</td>
                                    <td>{{ format(item.unit_price) }}</td>
                                    <td class="font-medium">{{ format((Number(item.qty) || 0) * (Number(item.unit_price) || 0)) }}</td>
                                    <td class="font-medium text-success">{{ format(item.margin_amount ?? (((Number(item.qty) || 0) * (Number(item.unit_price) || 0)) - ((Number(item.qty) || 0) * (Number(item.unit_cost) || 0)))) }}</td>
                                </tr>
                                <tr v-if="!budget.cctv_items?.length"><td colspan="7" class="text-center py-4 text-base-content/50">Tidak ada item.</td></tr>
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
                    <div class="flex items-center justify-between"><h3 class="font-semibold">Item CCTV</h3><div class="flex items-center gap-2"><button class="btn btn-ghost btn-xs" type="button" @click="openAddProductPicker">Pilih dari master</button><button class="btn btn-outline btn-xs" type="button" @click="addCctvItem">+ Tambah item</button></div></div>
                    <p v-if="budgetForm.errors.cctv_items" class="text-error text-xs">{{ budgetForm.errors.cctv_items }}</p>
                    <div class="overflow-x-auto rounded-xl border border-base-300"><table class="table table-sm"><thead><tr><th>Produk</th><th>Qty</th><th>Harga Satuan</th><th>Subtotal</th><th></th></tr></thead><tbody><tr v-for="(item, idx) in budgetForm.cctv_items" :key="idx"><td><input :value="productLabel(item)" type="text" class="input input-bordered input-sm w-full cursor-pointer" placeholder="Klik untuk pilih produk" readonly @click="openProductPickerForLine(idx)" @focus="openProductPickerForLine(idx)" /></td><td><input v-model.number="item.qty" type="number" min="0.01" step="0.01" class="input input-bordered input-sm w-24" /></td><td><input v-model.number="item.unit_price" type="number" min="0" step="1000" class="input input-bordered input-sm w-36" /></td><td>{{ format((Number(item.qty) || 0) * (Number(item.unit_price) || 0)) }}</td><td><button type="button" class="btn btn-ghost btn-xs text-error" @click="removeCctvItem(idx)">Hapus</button></td></tr></tbody></table></div>
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
            confirm-text="Pilih Produk"
            radio-name="selected_product_budget"
            @close="showProductPicker = false; productPicker.lineIndex = null"
            @confirm="chooseProduct"
        />
    </AppLayout>
</template>
