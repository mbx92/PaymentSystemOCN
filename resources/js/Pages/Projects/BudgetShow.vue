<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import CurrencyInput from '@/Components/CurrencyInput.vue';
import ProductPickerModal from '@/Components/ProductPickerModal.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { computed, reactive, ref, watch } from 'vue';
import { useCurrency } from '@/composables/useCurrency';
import { useDateFormat } from '@/composables/useDateFormat';

const props = defineProps({
    budget: Object,
    cctv_products: Array,
    project_types: { type: Array, default: () => [] },
    crm_customers: { type: Array, default: () => [] },
});
const projectTypeByKey = computed(() => Object.fromEntries((props.project_types ?? []).map((type) => [type.key, type])));
const projectTypeSupportsBudgetItems = (value) => !!projectTypeByKey.value[value]?.supports_budget_items;

const { formatDate } = useDateFormat();
const { format } = useCurrency();

function emptyItemRow() {
    return {
        master_product_id: null,
        catalog_sheet: null,
        catalog_ref: null,
        catalog_category: null,
        item_type: 'material',
        name: '',
        uom: 'unit',
        qty: 1,
        unit_cost: 0,
        unit_price: 0,
        notes: '',
    };
}

function normalizeCctvItems(raw) {
    const list = Array.isArray(raw) && raw.length
        ? raw.map((i) => ({
            master_product_id: i.master_product_id ?? null,
            catalog_sheet: i.catalog_sheet ?? null,
            catalog_ref: i.catalog_ref ?? null,
            catalog_category: i.catalog_category ?? null,
            item_type: i.item_type ?? 'material',
            name: i.name ?? '',
            uom: i.uom ?? 'unit',
            qty: i.qty ?? 1,
            unit_cost: i.unit_cost ?? 0,
            unit_price: i.unit_price ?? 0,
            notes: i.notes ?? '',
        }))
        : [emptyItemRow()];
    return list;
}

const budgetForm = useForm({
    name: props.budget.name,
    crm_customer_id: props.budget.crm_customer_id ?? '',
    client_name: props.budget.client_name,
    client_contact: props.budget.client_contact ?? '',
    project_type: props.budget.project_type,
    estimated_value: props.budget.estimated_value,
    cctv_items: normalizeCctvItems(props.budget.cctv_items),
    description: props.budget.description ?? '',
});

const syncSelectedCustomer = () => {
    if (!budgetForm.crm_customer_id) return;
    const customer = props.crm_customers.find((row) => Number(row.id) === Number(budgetForm.crm_customer_id));
    if (!customer) return;
    budgetForm.client_name = customer.display_name ?? '';
    budgetForm.client_contact = customer.contact ?? '';
};

watch(() => budgetForm.crm_customer_id, syncSelectedCustomer);

watch(
    () => props.budget,
    (b) => {
        if (budgetForm.processing) return;
        budgetForm.name = b.name;
        budgetForm.crm_customer_id = b.crm_customer_id ?? '';
        budgetForm.client_name = b.client_name;
        budgetForm.client_contact = b.client_contact ?? '';
        budgetForm.project_type = b.project_type;
        budgetForm.estimated_value = b.estimated_value;
        budgetForm.description = b.description ?? '';
        budgetForm.cctv_items = normalizeCctvItems(b.cctv_items);
    },
    { deep: true },
);

const isItemizedBudget = computed(() => projectTypeSupportsBudgetItems(budgetForm.project_type));
const totalCctvItems = computed(() => (budgetForm.cctv_items ?? []).reduce((s, r) => s + ((Number(r.qty) || 0) * (Number(r.unit_price) || 0)), 0));
const totalCctvCost = computed(() => (budgetForm.cctv_items ?? []).reduce((s, r) => s + ((Number(r.qty) || 0) * (Number(r.unit_cost) || 0)), 0));
const totalCctvMargin = computed(() => totalCctvItems.value - totalCctvCost.value);

/** Estimasi tampilan: untuk budget itemized, selalu dari total item/jasa. */
const displayedEstimated = computed(() => {
    if (props.budget.status === 'converted') {
        return Number(props.budget.estimated_value);
    }
    if (isItemizedBudget.value) {
        return totalCctvItems.value > 0 ? totalCctvItems.value : Number(props.budget.estimated_value);
    }
    return Number(budgetForm.estimated_value);
});

const canEditBudget = computed(() => !['converted', 'cancelled'].includes(props.budget.status));
const canEditCctvItems = computed(() => props.budget.supports_budget_items && canEditBudget.value);
const canCancelBudget = computed(() => ['draft', 'deal'].includes(props.budget.status));
const statusLabel = (status) => ({
    draft: 'Draft',
    deal: 'Deal',
    converted: 'Converted',
    cancelled: 'Dibatalkan',
}[status] ?? status);
const statusBadgeClass = (status) => ({
    draft: 'badge-info',
    deal: 'badge-warning',
    converted: 'badge-success',
    cancelled: 'badge-error',
}[status] ?? 'badge-ghost');
const showProductPicker = ref(false);
const productPicker = reactive({ lineIndex: null });
const suppressProductPickerOpen = ref(false);

const openEditModal = () => document.getElementById('modal-edit-budget')?.showModal();
const addCctvItem = () => budgetForm.cctv_items.push(emptyItemRow());
const productLabel = (item) => {
    if (item.catalog_ref) return `[${item.catalog_ref}] ${item.name ?? ''}`.trim();
    if (item.master_product_id && item.notes) return `${item.notes.replace(/^SKU:\s*/, '')} - ${item.name}`.trim();
    return item.name ?? '';
};
const applyProductToItem = (item, product) => {
    if (!item || !product) return;
    item.master_product_id = product.id ?? null;
    item.catalog_sheet = null;
    item.catalog_ref = null;
    item.catalog_category = null;
    item.item_type = product.product_type === 'service' ? 'service' : 'material';
    item.name = product.name ?? '';
    item.uom = product.uom ?? 'unit';
    item.unit_cost = Number(product.unit_cost ?? 0);
    item.unit_price = Number(product.selling_price ?? product.price ?? 0);
    item.notes = product.sku ? `SKU: ${product.sku}` : '';
};
const findMatchingItemIndex = (candidate, excludeIndex = null) => {
    const items = budgetForm.cctv_items ?? [];
    if (candidate.catalog_ref) {
        return items.findIndex(
            (row, idx) => idx !== excludeIndex
                && row.catalog_ref === candidate.catalog_ref
                && row.catalog_sheet === candidate.catalog_sheet,
        );
    }
    if (candidate.master_product_id) {
        return items.findIndex(
            (row, idx) => idx !== excludeIndex && row.master_product_id === candidate.master_product_id,
        );
    }
    const name = String(candidate.name ?? '').trim().toLowerCase();
    if (!name) return -1;

    return items.findIndex(
        (row, idx) => idx !== excludeIndex
            && !row.catalog_ref
            && !row.master_product_id
            && String(row.name ?? '').trim().toLowerCase() === name,
    );
};
const cleanupPickerLine = (lineIndex, mergedIndex) => {
    if (lineIndex === null || lineIndex === mergedIndex) return;
    const line = budgetForm.cctv_items[lineIndex];
    const isBlank = !String(line.name ?? '').trim() && !line.catalog_ref && !line.master_product_id;
    if (isBlank && budgetForm.cctv_items.length > 1) {
        budgetForm.cctv_items.splice(lineIndex, 1);
        return;
    }
    Object.assign(line, emptyItemRow());
};
const mergeProductItem = (product, lineIndex = null) => {
    if (lineIndex !== null) {
        const current = budgetForm.cctv_items[lineIndex];
        if (current?.master_product_id && current.master_product_id === product.id) {
            current.qty = (Number(current.qty) || 0) + 1;
            return;
        }
    }

    const probe = { ...emptyItemRow(), master_product_id: product.id ?? null };
    const existingIdx = findMatchingItemIndex(probe, lineIndex);
    if (existingIdx >= 0) {
        budgetForm.cctv_items[existingIdx].qty = (Number(budgetForm.cctv_items[existingIdx].qty) || 0) + 1;
        cleanupPickerLine(lineIndex, existingIdx);
        return;
    }
    if (lineIndex !== null) {
        applyProductToItem(budgetForm.cctv_items[lineIndex], product);
        return;
    }
    const row = emptyItemRow();
    applyProductToItem(row, product);
    row.qty = 1;
    budgetForm.cctv_items.push(row);
};
const openProductPickerForLine = (idx) => {
    if (suppressProductPickerOpen.value) return;
    productPicker.lineIndex = idx;
    showProductPicker.value = true;
};
const chooseProduct = (product) => {
    suppressProductPickerOpen.value = true;
    mergeProductItem(product, productPicker.lineIndex);
    productPicker.lineIndex = null;
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
    if (isItemizedBudget.value) {
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

const markDeal = () => router.patch(route('erp.projects.budgets.deal', props.budget.id), {}, { preserveScroll: true });
const cancelBudget = () => {
    if (!window.confirm('Batalkan budget ini? Status draft/deal akan diakhiri dan budget tidak bisa diedit lagi.')) return;
    router.patch(route('erp.projects.budgets.cancel', props.budget.id), {}, { preserveScroll: true });
};
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
                        <span class="badge" :class="statusBadgeClass(budget.status)">{{ statusLabel(budget.status) }}</span>
                        <button class="btn btn-outline btn-sm" @click="downloadPdf">PDF</button>
                        <Link
                            v-if="budget.supports_budget_items"
                            :href="route('erp.projects.budgets.builder', budget.id)"
                            class="btn btn-secondary btn-sm"
                        >
                            RAB Builder
                        </Link>
                        <Link
                            v-if="budget.supports_budget_items"
                            :href="route('erp.projects.budgets.customer-view', budget.id)"
                            target="_blank"
                            class="btn btn-accent btn-sm"
                        >
                            Tampilan Customer
                        </Link>
                        <button v-if="budget.status === 'draft'" class="btn btn-outline btn-sm" title="Setujui customer — item katalog dipromosikan ke master produk" @click="markDeal">Tandai Deal</button>
                        <button v-if="canCancelBudget" class="btn btn-error btn-outline btn-sm" @click="cancelBudget">Cancel Budget</button>
                        <button v-if="budget.status === 'deal'" class="btn btn-primary btn-sm" @click="convert">Convert ke Project</button>
                        <Link v-if="budget.converted_project_id" :href="route('projects.show', budget.converted_project_id)" class="btn btn-ghost btn-sm">Lihat Project</Link>
                        <button v-if="canEditBudget" class="btn btn-primary btn-sm" @click="openEditModal">Edit</button>
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
                    <div><span class="text-base-content/60">Tipe</span><div>{{ budget.project_type_label }}</div></div>
                    <div>
                        <span class="text-base-content/60">Estimasi</span>
                        <div class="font-semibold">{{ format(displayedEstimated) }}</div>
                        <p v-if="canEditCctvItems && totalCctvItems > 0" class="text-xs text-base-content/60 mt-0.5">Total dihitung otomatis dari item dan jasa di bawah.</p>
                        <p v-else-if="canEditCctvItems" class="text-xs text-base-content/60 mt-0.5">Tambahkan item/jasa untuk membentuk nilai estimasi.</p>
                    </div>
                    <div v-if="budget.supports_budget_items"><span class="text-base-content/60">Estimasi HPP</span><div>{{ format(budget.total_cost ?? totalCctvCost) }}</div></div>
                    <div v-if="budget.supports_budget_items"><span class="text-base-content/60">Estimasi Margin</span><div class="font-semibold text-success">{{ format(budget.total_margin ?? totalCctvMargin) }}</div></div>
                    <div><span class="text-base-content/60">Dibuat</span><div>{{ formatDate(budget.created_at) }}</div></div>
                    <div class="md:col-span-2"><span class="text-base-content/60">Deskripsi</span><div>{{ budget.description || '-' }}</div></div>
                </div>
            </div>

            <div v-if="budget.supports_budget_items" class="ocn-panel">
                <div class="ocn-panel__head flex flex-wrap items-center justify-between gap-2">
                    <h2 class="ocn-panel__title">Item Budget</h2>
                    <div class="flex flex-wrap items-center gap-2 shrink-0">
                        <Link :href="route('erp.projects.budgets.builder', budget.id)" class="btn btn-secondary btn-sm">Buka RAB Builder</Link>
                        <Link :href="route('erp.projects.budgets.customer-view', budget.id)" target="_blank" class="btn btn-ghost btn-sm">Preview Customer</Link>
                    </div>
                </div>
                <div class="card-body space-y-4">
                    <p class="text-sm text-base-content/70">
                        Item budget tidak ditampilkan di halaman detail ini. Kelola rincian item di <strong>RAB Builder</strong> atau buka versi <strong>Tampilan Customer</strong> untuk preview penawaran.
                    </p>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div class="rounded-xl border border-base-200 bg-base-200/30 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">Estimasi Penawaran</p>
                            <p class="mt-2 text-lg font-semibold">{{ format(displayedEstimated) }}</p>
                        </div>
                        <div class="rounded-xl border border-base-200 bg-base-200/30 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">Estimasi HPP</p>
                            <p class="mt-2 text-lg font-semibold">{{ format(budget.total_cost ?? totalCctvCost) }}</p>
                        </div>
                        <div class="rounded-xl border border-base-200 bg-base-200/30 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">Estimasi Margin</p>
                            <p class="mt-2 text-lg font-semibold text-success">{{ format(budget.total_margin ?? totalCctvMargin) }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <dialog id="modal-edit-budget" class="modal">
            <div class="modal-box max-w-4xl">
                <h3 class="font-bold text-lg">Edit Budget</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-3">
                    <div><label class="label"><span class="label-text">Nama Project</span></label><input v-model="budgetForm.name" type="text" class="input input-bordered w-full" /><p v-if="budgetForm.errors.name" class="text-error text-xs mt-1">{{ budgetForm.errors.name }}</p></div>
                    <div>
                        <label class="label"><span class="label-text">Customer CRM</span></label>
                        <select v-model="budgetForm.crm_customer_id" class="select select-bordered w-full" :class="budgetForm.errors.crm_customer_id ? 'select-error' : ''">
                            <option value="">Input manual</option>
                            <option v-for="customer in crm_customers" :key="customer.id" :value="customer.id">
                                {{ customer.code }} - {{ customer.display_name }}
                            </option>
                        </select>
                        <p v-if="budgetForm.errors.crm_customer_id" class="text-error text-xs mt-1">{{ budgetForm.errors.crm_customer_id }}</p>
                    </div>
                    <div><label class="label"><span class="label-text">Nama Klien</span></label><input v-model="budgetForm.client_name" type="text" class="input input-bordered w-full" :class="budgetForm.crm_customer_id ? 'bg-base-200' : ''" :readonly="!!budgetForm.crm_customer_id" placeholder="Pilih customer atau ketik manual" /><p v-if="budgetForm.errors.client_name" class="text-error text-xs mt-1">{{ budgetForm.errors.client_name }}</p></div>
                    <div><label class="label"><span class="label-text">Kontak Klien</span></label><input v-model="budgetForm.client_contact" type="text" class="input input-bordered w-full" :class="budgetForm.crm_customer_id ? 'bg-base-200' : ''" :readonly="!!budgetForm.crm_customer_id" /></div>
                    <div><label class="label"><span class="label-text">Tipe Project</span></label><select v-model="budgetForm.project_type" class="select select-bordered w-full"><option v-for="type in project_types" :key="type.key" :value="type.key">{{ type.label }}</option></select></div>
                    <div>
                        <CurrencyInput v-if="!isItemizedBudget" v-model="budgetForm.estimated_value" label="Estimasi Nilai Project" :required="true" :error="budgetForm.errors.estimated_value" />
                        <div v-else>
                            <label class="label"><span class="label-text">Total Estimasi (otomatis dari item &amp; jasa)</span></label>
                            <div class="input input-bordered w-full flex items-center bg-base-200">{{ format(totalCctvItems) }}</div>
                            <p class="text-xs text-base-content/60 mt-1">Nilai terbentuk saat item dan jasa diinput di bawah.</p>
                        </div>
                    </div>
                    <div class="md:col-span-2"><label class="label"><span class="label-text">Deskripsi</span></label><textarea v-model="budgetForm.description" class="textarea textarea-bordered w-full" rows="3" /></div>
                </div>
                <div v-if="isItemizedBudget" class="mt-4 space-y-2">
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
