<script setup>
import BudgetGridTable from '@/Components/BudgetBuilder/BudgetGridTable.vue';
import {
    applyCatalogToItem,
    applyMasterProductToItem,
    createSlotCell,
    emptyBudgetItem,
    findSlotByItemKey,
    gridRowCount,
    itemMatchKey,
    itemsToSlotMap,
    nextEmptySlotIndex,
    normalizeProductQty,
    slotMapToItems,
} from '@/composables/useBudgetBuilderItems';
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowLeftIcon,
    ArrowPathIcon,
    ChevronDownIcon,
    MagnifyingGlassIcon,
} from '@heroicons/vue/24/outline';
import { computed, onBeforeUnmount, onMounted, provide, ref, watch } from 'vue';
import { useCurrency } from '@/composables/useCurrency';

const props = defineProps({
    budget: { type: Object, required: true },
    cctv_products: { type: Array, default: () => [] },
    catalog_sheets: { type: Array, default: () => [] },
    can_edit: { type: Boolean, default: true },
});

const PALETTE_CARD = { width: 168, height: 108 };

const { format } = useCurrency();
const slotMap = ref(itemsToSlotMap(props.budget.cctv_items ?? []));
const paletteTab = ref('master');
const paletteSearch = ref('');
const catalogSheet = ref(props.catalog_sheets[0]?.key ?? '');
const catalogItems = ref([]);
const catalogLoading = ref(false);
const catalogError = ref('');
const dragPayload = ref(null);
const dropHighlight = ref(null);

const budgetForm = useForm({
    name: props.budget.name,
    crm_customer_id: props.budget.crm_customer_id ?? '',
    client_name: props.budget.client_name,
    client_contact: props.budget.client_contact ?? '',
    project_type: props.budget.project_type,
    estimated_value: props.budget.estimated_value,
    description: props.budget.description ?? '',
    cctv_items: props.budget.cctv_items ?? [],
});

const filteredMasterProducts = computed(() => {
    const term = paletteSearch.value.trim().toLowerCase();
    const list = props.cctv_products ?? [];
    if (!term) return list;
    return list.filter((product) => {
        const haystack = [product.sku, product.barcode, product.name].filter(Boolean).join(' ').toLowerCase();
        return haystack.includes(term);
    });
});

const paletteItems = computed(() => {
    if (paletteTab.value === 'master') {
        return filteredMasterProducts.value.map((product) => ({
            key: `master-${product.id}`,
            kind: 'master',
            product,
        }));
    }
    return catalogItems.value.map((product) => ({
        key: product.ref,
        kind: 'catalog',
        product,
    }));
});

const rowCount = computed(() => gridRowCount(slotMap.value));

const totals = computed(() => {
    const items = slotMapToItems(slotMap.value);
    const totalPrice = items.reduce((sum, row) => sum + ((Number(row.qty) || 0) * (Number(row.unit_price) || 0)), 0);
    const totalCost = items.reduce((sum, row) => sum + ((Number(row.qty) || 0) * (Number(row.unit_cost) || 0)), 0);
    return {
        count: items.length,
        totalPrice,
        totalCost,
        margin: totalPrice - totalCost,
    };
});

function itemFromPayload(payload) {
    const item = emptyBudgetItem();
    if (payload.kind === 'master') {
        applyMasterProductToItem(item, payload.product);
    } else if (payload.kind === 'catalog') {
        applyCatalogToItem(item, payload.product);
    }
    return item;
}

function placeItemAtSlot(item, slotIndex) {
    const key = itemMatchKey(item);
    const existingIndex = findSlotByItemKey(slotMap.value, key);

    if (existingIndex !== null) {
        slotMap.value = {
            ...slotMap.value,
            [existingIndex]: {
                ...slotMap.value[existingIndex],
                qty: normalizeProductQty((Number(slotMap.value[existingIndex].qty) || 0) + (Number(item.qty) || 1)),
            },
        };
        return;
    }

    const targetIndex = slotIndex ?? nextEmptySlotIndex(slotMap.value);
    slotMap.value = {
        ...slotMap.value,
        [targetIndex]: createSlotCell(item),
    };
}

function addItemToSlot(item, slotIndex = null) {
    if (!props.can_edit) return;
    placeItemAtSlot(item, slotIndex);
}

function addMasterProduct(product) {
    const item = emptyBudgetItem();
    applyMasterProductToItem(item, product);
    addItemToSlot(item);
}

function addCatalogProduct(catalogItem) {
    const item = emptyBudgetItem();
    applyCatalogToItem(item, catalogItem);
    addItemToSlot(item);
}

function updateQty(slotIndex, delta) {
    const cell = slotMap.value[slotIndex];
    if (!cell) return;
    slotMap.value = {
        ...slotMap.value,
        [slotIndex]: {
            ...cell,
            qty: normalizeProductQty((Number(cell.qty) || 1) + delta),
        },
    };
}

function removeSlot(slotIndex) {
    const next = { ...slotMap.value };
    delete next[slotIndex];
    slotMap.value = next;
}

provide('budgetBuilderActions', {
    updateQty,
    removeSlot,
});

function onPaletteDragStart(event, payload) {
    if (!props.can_edit) return;
    dragPayload.value = payload;
    event.dataTransfer.effectAllowed = 'copy';
    event.dataTransfer.setData('application/budget-builder', JSON.stringify(payload));
}

function onSlotDragOver(index) {
    if (!props.can_edit) return;
    dropHighlight.value = index;
}

function onSlotDragLeave(index) {
    if (dropHighlight.value === index) {
        dropHighlight.value = null;
    }
}

function onSlotDrop(index) {
    if (!props.can_edit) return;
    dropHighlight.value = null;
    const payload = dragPayload.value;
    dragPayload.value = null;
    if (!payload?.kind) return;

    const item = itemFromPayload(payload);
    if (!String(item.name ?? '').trim()) return;

    addItemToSlot(item, index);
}

function paletteCardLabel(entry) {
    if (entry.kind === 'catalog') {
        return entry.product.code ?? 'Katalog';
    }
    return entry.product.sku || 'Master';
}

function paletteCardPrice(entry) {
    if (entry.kind === 'catalog') {
        return Math.round(Number(entry.product.supplier_price) || 0);
    }
    return Math.round(Number(entry.product.selling_price) || 0);
}

function onPaletteCardClick(entry) {
    if (!props.can_edit) return;
    if (entry.kind === 'master') {
        addMasterProduct(entry.product);
        return;
    }
    addCatalogProduct(entry.product);
}

async function fetchCatalogItems() {
    if (!catalogSheet.value) return;
    catalogLoading.value = true;
    catalogError.value = '';
    try {
        const params = paletteSearch.value.trim() ? { q: paletteSearch.value.trim() } : {};
        const { data } = await axios.get(`/api/supplier-catalog/${catalogSheet.value}/items`, { params });
        catalogItems.value = data.items ?? [];
    } catch (err) {
        catalogError.value = err?.response?.data?.message ?? 'Gagal memuat katalog supplier.';
        catalogItems.value = [];
    } finally {
        catalogLoading.value = false;
    }
}

let catalogTimer;
watch([paletteTab, catalogSheet, paletteSearch], () => {
    if (paletteTab.value !== 'catalog') return;
    clearTimeout(catalogTimer);
    catalogTimer = setTimeout(fetchCatalogItems, 250);
});

onMounted(() => {
    document.documentElement.classList.add('overflow-hidden');
    if (paletteTab.value === 'catalog') fetchCatalogItems();
});

onBeforeUnmount(() => {
    document.documentElement.classList.remove('overflow-hidden');
});

function resetCanvas() {
    slotMap.value = {};
}

function loadFromBudget() {
    slotMap.value = itemsToSlotMap(props.budget.cctv_items ?? []);
}

function saveBudget() {
    const items = slotMapToItems(slotMap.value);
    budgetForm.cctv_items = items;
    budgetForm.estimated_value = totals.value.totalPrice;
    budgetForm.put(route('erp.projects.budgets.update', props.budget.id), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="`RAB Builder - ${budget.name}`" />

    <div class="rab-builder-shell fixed inset-0 z-[200] flex flex-col bg-base-200">
        <header class="shrink-0 flex flex-wrap items-center gap-3 border-b border-base-300 bg-base-100 px-4 py-2.5">
            <div class="flex min-w-0 flex-1 items-center gap-3">
                <Link
                    class="btn btn-ghost btn-sm btn-square shrink-0"
                    :href="route('erp.projects.budgets.show', budget.id)"
                    title="Kembali ke detail budget"
                >
                    <ArrowLeftIcon class="h-4 w-4" />
                </Link>
                <div class="min-w-0">
                    <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-primary/70">RAB Builder</p>
                    <h1 class="text-sm font-bold truncate">{{ budget.name }}</h1>
                    <p class="text-[11px] text-base-content/55 truncate">{{ budget.client_name }} · {{ budget.project_type_label }}</p>
                </div>
            </div>

            <div class="hidden md:flex items-center gap-4 text-xs tabular-nums">
                <span><span class="text-base-content/50">Item</span> <strong>{{ totals.count }}</strong></span>
                <span><span class="text-base-content/50">Total</span> <strong>{{ format(totals.totalPrice) }}</strong></span>
                <span><span class="text-base-content/50">HPP</span> <strong>{{ format(totals.totalCost) }}</strong></span>
                <span><span class="text-base-content/50">Margin</span> <strong class="text-success">{{ format(totals.margin) }}</strong></span>
            </div>

            <div class="flex items-center gap-2 shrink-0">
                <div class="dropdown dropdown-end md:hidden">
                    <button type="button" tabindex="0" class="btn btn-ghost btn-sm gap-1">
                        Ringkasan
                        <ChevronDownIcon class="h-3.5 w-3.5" />
                    </button>
                    <div tabindex="0" class="dropdown-content z-[300] mt-1 w-64 rounded-box border border-base-300 bg-base-100 p-3 shadow-lg text-xs space-y-2">
                        <div class="flex justify-between"><span>Item</span><strong>{{ totals.count }}</strong></div>
                        <div class="flex justify-between"><span>Total jual</span><strong>{{ format(totals.totalPrice) }}</strong></div>
                        <div class="flex justify-between"><span>HPP</span><strong>{{ format(totals.totalCost) }}</strong></div>
                        <div class="flex justify-between"><span>Margin</span><strong class="text-success">{{ format(totals.margin) }}</strong></div>
                    </div>
                </div>

                <template v-if="can_edit">
                    <button type="button" class="btn btn-ghost btn-sm" @click="loadFromBudget">Muat ulang</button>
                    <button type="button" class="btn btn-outline btn-sm" @click="resetCanvas">Kosongkan</button>
                    <button type="button" class="btn btn-primary btn-sm" :disabled="budgetForm.processing" @click="saveBudget">
                        Simpan RAB
                    </button>
                </template>
                <Link
                    :href="route('erp.projects.budgets.customer-view', budget.id)"
                    target="_blank"
                    class="btn btn-accent btn-sm"
                >
                    Tampilan Customer
                </Link>
            </div>
        </header>

        <div v-if="!can_edit" class="shrink-0 alert alert-warning alert-soft rounded-none py-2 min-h-0 text-xs">
            Budget ini sudah tidak bisa diedit.
        </div>

        <section class="shrink-0 border-b border-base-300 bg-base-100">
            <div class="flex flex-wrap items-center gap-2 px-4 py-2 border-b border-base-200/80">
                <div role="tablist" class="tabs tabs-boxed tabs-xs h-8">
                    <button type="button" role="tab" class="tab h-7 min-h-0" :class="{ 'tab-active': paletteTab === 'master' }" @click="paletteTab = 'master'">Master</button>
                    <button type="button" role="tab" class="tab h-7 min-h-0" :class="{ 'tab-active': paletteTab === 'catalog' }" @click="paletteTab = 'catalog'">Katalog</button>
                </div>
                <label class="input input-bordered input-xs flex items-center gap-2 w-44 sm:w-56">
                    <MagnifyingGlassIcon class="size-3.5 opacity-50 shrink-0" />
                    <input v-model="paletteSearch" type="search" placeholder="Cari produk..." class="grow min-w-0" />
                </label>
                <select
                    v-if="paletteTab === 'catalog'"
                    v-model="catalogSheet"
                    class="select select-bordered select-xs w-36"
                >
                    <option v-for="sheet in catalog_sheets" :key="sheet.key" :value="sheet.key">{{ sheet.label }}</option>
                </select>
                <button
                    v-if="paletteTab === 'catalog'"
                    type="button"
                    class="btn btn-ghost btn-xs gap-1"
                    :disabled="catalogLoading"
                    @click="fetchCatalogItems"
                >
                    <ArrowPathIcon class="size-3.5" />
                    Refresh
                </button>
                <p class="text-[11px] text-base-content/50 ml-auto hidden sm:block">Tarik ke baris tabel di bawah · posisi baris terkunci</p>
            </div>

            <div class="palette-strip px-4 py-3 overflow-x-auto">
                <div v-if="paletteTab === 'catalog' && catalogLoading" class="flex justify-center py-6">
                    <span class="loading loading-spinner loading-sm text-primary" />
                </div>
                <p v-else-if="paletteTab === 'catalog' && catalogError" class="text-error text-xs py-4">{{ catalogError }}</p>
                <div v-else class="flex gap-2.5 min-w-min pb-1">
                    <button
                        v-for="entry in paletteItems"
                        :key="entry.key"
                        type="button"
                        class="palette-card shrink-0 rounded-lg border border-base-300 bg-base-100 p-2 text-left shadow-sm hover:border-primary/50 hover:shadow-md transition-all cursor-grab active:cursor-grabbing flex flex-col"
                        :style="{ width: `${PALETTE_CARD.width}px`, height: `${PALETTE_CARD.height}px` }"
                        :class="entry.kind === 'catalog' ? 'hover:bg-secondary/5' : 'hover:bg-primary/5'"
                        :draggable="can_edit"
                        @dragstart="onPaletteDragStart($event, { kind: entry.kind, product: entry.product })"
                        @click="onPaletteCardClick(entry)"
                    >
                        <p class="text-[11px] font-semibold leading-tight line-clamp-3">{{ entry.product.name }}</p>
                        <p class="text-[9px] text-base-content/50 mt-0.5 truncate">{{ paletteCardLabel(entry) }}</p>
                        <p class="text-[10px] font-bold tabular-nums mt-auto">{{ format(paletteCardPrice(entry)) }}</p>
                    </button>
                    <p v-if="!paletteItems.length" class="text-sm text-base-content/50 py-6 px-2">Produk tidak ditemukan.</p>
                </div>
            </div>
        </section>

        <section class="flex-1 min-h-0 border-t border-base-300">
            <BudgetGridTable
                :slot-map="slotMap"
                :row-count="rowCount"
                :can-edit="can_edit"
                :drop-highlight="dropHighlight"
                @drop-slot="onSlotDrop"
                @dragover-slot="onSlotDragOver"
                @dragleave-slot="onSlotDragLeave"
            />
        </section>
    </div>
</template>

<style scoped>
.palette-strip {
    scrollbar-width: thin;
}

.palette-card {
    box-sizing: border-box;
}
</style>
