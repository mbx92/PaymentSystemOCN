<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { XMarkIcon, TrashIcon, PlusIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    data: { type: Object, default: null },
    loading: { type: Boolean, default: false },
    saving: { type: Boolean, default: false },
    products: { type: Array, default: () => [] },
    productsLoading: { type: Boolean, default: false },
    onCreateSlot: { type: Function, default: null },
    onDeleteSlot: { type: Function, default: null },
    onMoveSlot: { type: Function, default: null },
    onFocusProducts: { type: Function, default: null },
    onClose: { type: Function, default: null },
});

const phantomCounts = reactive({});
const slotSpans = reactive({});

watch(() => props.data, (newData) => {
    if (!newData?.tiers) return;
    for (const tier of newData.tiers) {
        // Max 6 slots per tier; phantoms fill remaining positions
        phantomCounts[tier.tier] = Math.max(0, 6 - tier.slots.length);
        for (const slot of tier.slots) {
            if (!(slot.id in slotSpans)) slotSpans[slot.id] = 1;
        }
    }
}, { immediate: true, deep: true });

function phantoms(tierNum) { return Math.max(0, phantomCounts[tierNum] ?? 0); }
function spanOf(slotId) { return slotSpans[slotId] ?? 1; }

// Add slot modal
const showAddSlot = ref(false);
const slotForm = ref({ tier: 4, slotPosition: 0, productId: null, qty: 0, minQty: 5 });
const productSearch = ref('');
const slotError = ref('');

// Drag state
const dragSlot = ref(null);
const dragOverTier = ref(null);
const dragOverPos = ref(null);

// Resize state
const resizing = ref(null); // { slotId, tier, startX, startSpan }
const resizeOver = ref(null); // 'right' | 'left' | null

const filteredProducts = computed(() => {
    if (!productSearch.value.trim()) return props.products;
    const term = productSearch.value.toLowerCase();
    return props.products.filter(p => p.name.toLowerCase().includes(term) || p.sku.toLowerCase().includes(term));
});

function tierSlots(tierNum) {
    if (!props.data) return [];
    return props.data.tiers.find(t => t.tier === tierNum)?.slots ?? [];
}

function maxSlotsReached(tierNum) {
    return tierSlots(tierNum).length >= 6;
}

function onFilledCardClick(tierNum) {
    if (maxSlotsReached(tierNum)) return;
    openAddSlotFor(tierNum);
}

function onPhantomClick(tierNum) {
    openAddSlotFor(tierNum);
}

function openAddSlotFor(tierNum) {
    if (props.onFocusProducts) props.onFocusProducts();
    if (!props.data) return;
    const slots = tierSlots(tierNum);
    const maxPos = slots.length > 0 ? Math.max(...slots.map(s => s.slot_position)) : -1;
    slotForm.value = { tier: tierNum, slotPosition: maxPos + 1, productId: null, qty: 0, minQty: 5 };
    productSearch.value = '';
    slotError.value = '';
    showAddSlot.value = true;
}

function selectProduct(product) {
    slotForm.value.productId = product.id;
    productSearch.value = product.name;
}

async function handleCreateSlot() {
    slotError.value = '';
    if (!slotForm.value.productId) { slotError.value = 'Pilih produk terlebih dahulu.'; return; }
    if (!props.onCreateSlot) return;
    try {
        await props.onCreateSlot({
            tier: slotForm.value.tier,
            slotPosition: slotForm.value.slotPosition,
            productId: slotForm.value.productId,
            qty: slotForm.value.qty,
            minQty: slotForm.value.minQty,
        });
        showAddSlot.value = false;
    } catch { slotError.value = 'Gagal menambahkan slot.'; }
}

// --- Resize (extend) ---
function onResizeDown(event, slot, tierNum) {
    event.preventDefault();
    event.stopPropagation();
    resizing.value = { slotId: slot.id, tier: tierNum, startX: event.clientX, startSpan: spanOf(slot.id) };
    document.addEventListener('pointermove', onResizeMove);
    document.addEventListener('pointerup', onResizeUp);
}

function onResizeMove(event) {
    if (!resizing.value) return;
    const dx = event.clientX - resizing.value.startX;
    const threshold = 40;
    const step = Math.round(dx / threshold);
    const newSpan = Math.max(1, Math.min(resizing.value.startSpan + step, resizing.value.startSpan + phantomCounts[resizing.value.tier]));
    const spanDelta = newSpan - resizing.value.startSpan;

    if (spanDelta !== 0) {
        resizeOver.value = spanDelta > 0 ? 'right' : 'left';
    } else {
        resizeOver.value = null;
    }
}

function onResizeUp() {
    document.removeEventListener('pointermove', onResizeMove);
    document.removeEventListener('pointerup', onResizeUp);

    if (!resizing.value) return;

    const dx = (resizeOver.value === 'right' ? 1 : resizeOver.value === 'left' ? -1 : 0);
    if (dx === 0) { resizing.value = null; resizeOver.value = null; return; }

    const slotId = resizing.value.slotId;
    const tier = resizing.value.tier;
    const current = spanOf(slotId);

    if (dx > 0 && phantomCounts[tier] > 0) {
        // Extend: increase span, decrease phantom
        slotSpans[slotId] = current + 1;
        phantomCounts[tier] -= 1;
    } else if (dx < 0 && current > 1) {
        // Shrink: decrease span, increase phantom
        slotSpans[slotId] = current - 1;
        phantomCounts[tier] += 1;
    }

    resizing.value = null;
    resizeOver.value = null;
}

// --- Drag & Drop ---
function onDragStart(event, slot) {
    if (event.target.closest('.resize-handle')) {
        event.preventDefault();
        return;
    }
    dragSlot.value = slot;
    event.dataTransfer.effectAllowed = 'move';
}

function onDragOver(event, tier, position) {
    if (!dragSlot.value) return;
    event.preventDefault();
    event.dataTransfer.dropEffect = 'move';
    dragOverTier.value = tier;
    dragOverPos.value = position;
}

function onDragLeave() { dragOverTier.value = null; dragOverPos.value = null; }

function onDrop(event, targetTier, targetPos) {
    event.preventDefault();
    dragOverTier.value = null;
    dragOverPos.value = null;
    if (!dragSlot.value) return;
    const slot = dragSlot.value;
    if (slot.tier !== targetTier) { dragSlot.value = null; return; }
    const slots = tierSlots(slot.tier);
    const sourcePos = slots.indexOf(slot);
    if (sourcePos === -1 || sourcePos === targetPos) { dragSlot.value = null; return; }
    if (props.onMoveSlot) {
        props.onMoveSlot({ slotId: slot.id, tier: slot.tier, fromPosition: sourcePos, toPosition: targetPos });
    }
    dragSlot.value = null;
}

function onDragEnd() {
    dragSlot.value = null;
    dragOverTier.value = null;
    dragOverPos.value = null;
}

function slotStatus(slot) {
    if (slot.qty === 0) return 'empty';
    if (slot.qty < slot.min_qty) return 'low';
    return 'normal';
}

function statusBadge(status) {
    switch (status) {
        case 'empty': return { text: 'Habis', class: 'badge-error' };
        case 'low': return { text: 'Menipis', class: 'badge-warning' };
        case 'normal': return { text: 'Aman', class: 'badge-success' };
        default: return { text: '-', class: 'badge-ghost' };
    }
}

function slotBgColor(status) {
    switch (status) {
        case 'empty': return 'border-red-300 bg-red-50 ';
        case 'low': return 'border-yellow-300 bg-yellow-50 ';
        case 'normal': return 'border-green-300 bg-green-50 ';
        default: return 'border-base-300 bg-base-100 ';
    }
}
</script>

<template>
    <div class="ocn-panel">
        <div class="ocn-panel__head">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <h2 class="ocn-panel__title">Tampak Depan Rak</h2>
                    <span v-if="data" class="badge badge-primary badge-sm">{{ data.shelf.code }}</span>
                </div>
                <button class="btn btn-ghost btn-xs btn-square" @click="props.onClose?.()">
                    <XMarkIcon class="size-4" />
                </button>
            </div>
            <p v-if="data" class="ocn-panel__desc">{{ data.shelf.name }}</p>
        </div>

        <div v-if="loading" class="flex items-center justify-center py-16">
            <span class="loading loading-spinner loading-lg text-primary"></span>
        </div>

        <div v-else-if="!data" class="py-16 text-center text-base-content/40">
            <p class="text-sm">Klik salah satu rak di peta untuk melihat detail isinya</p>
        </div>

        <div v-else class="p-4 space-y-4">

            <div
                v-for="tier in data.tiers"
                :key="tier.tier"
                class="border border-base-300 rounded-lg overflow-hidden"
            >
                <div class="bg-base-200 px-3 py-1.5 flex items-center justify-between">
                    <span class="text-xs font-semibold">{{ tier.label }}</span>
                    <span class="text-xs text-base-content/50">{{ tier.slots.length }} slot</span>
                </div>

                <div class="p-2 flex gap-2 min-w-0 min-h-[72px] items-stretch">
                    <!-- Filled slots -->
                    <div
                        v-for="(slot, idx) in tier.slots"
                        :key="slot.id"
                        draggable="true"
                        :class="[
                            'min-w-0 border rounded-lg px-2 py-1.5 transition-all relative group cursor-pointer',
                            slotBgColor(slotStatus(slot)),
                            dragSlot?.id === slot.id ? 'opacity-50 scale-95' : '',
                            dragOverTier === tier.tier && dragOverPos === idx ? 'ring-2 ring-primary ring-offset-1' : '',
                            resizing?.slotId === slot.id && resizeOver === 'right' ? 'ring-2 ring-info ring-offset-1' : '',
                            resizing?.slotId === slot.id && resizeOver === 'left' ? 'ring-2 ring-warning ring-offset-1' : '',
                        ]"
                        :style="{ flex: spanOf(slot.id) }"
                        @dragstart="onDragStart($event, slot)"
                        @dragover="onDragOver($event, tier.tier, idx)"
                        @dragleave="onDragLeave"
                        @drop="onDrop($event, tier.tier, idx)"
                        @dragend="onDragEnd"
                        @click="onFilledCardClick(tier.tier)"
                    >
                        <!-- Resize handle (right edge) -->
                        <div
                            class="resize-handle absolute top-0 -right-1 w-2.5 h-full cursor-col-resize group/resize z-20 flex items-center"
                            :class="resizing?.slotId === slot.id ? 'opacity-100' : 'opacity-0 group-hover:opacity-100'"
                            @pointerdown.prevent.stop="onResizeDown($event, slot, tier.tier)"
                        >
                            <div class="w-1 h-6 mx-auto rounded-full bg-base-content/20 group-hover/resize:bg-base-content/40 transition-colors" />
                        </div>

                        <button
                            class="absolute top-0.5 right-4 btn btn-ghost btn-xs btn-square text-error opacity-0 group-hover:opacity-100 transition-opacity z-10"
                            title="Hapus slot"
                            @click.stop="props.onDeleteSlot?.(slot.id)"
                        >
                            <TrashIcon class="size-3" />
                        </button>
                        <div class="flex items-start justify-between mb-0.5">
                            <span class="badge text-[10px] leading-tight px-1" :class="statusBadge(slotStatus(slot)).class">
                                {{ statusBadge(slotStatus(slot)).text }}
                            </span>
                            <span v-if="spanOf(slot.id) > 1" class="text-[9px] text-base-content/30 italic">×{{ spanOf(slot.id) }}</span>
                        </div>
                        <p class="text-[11px] font-medium leading-tight line-clamp-2">{{ slot.product_name }}</p>
                        <p class="text-[9px] text-base-content/40 mt-0.5">{{ slot.sku }}</p>
                        <div class="flex items-baseline gap-1.5 mt-1">
                            <span class="text-xs font-bold">{{ slot.qty }}</span>
                            <span class="text-[9px] text-base-content/40">/{{ slot.min_qty }}</span>
                        </div>
                    </div>

                    <!-- Phantom slots -->
                    <div
                        v-for="p in phantoms(tier.tier)"
                        :key="'phantom-' + tier.tier + '-' + p"
                        :class="[
                            'flex-1 min-w-0 border-2 border-dashed rounded-lg flex items-center justify-center transition-all cursor-pointer',
                            tier.slots.length === 0 && phantoms(tier.tier) === 1 ? 'py-4' : 'py-1',
                            dragOverTier === tier.tier && dragOverPos === tier.slots.length + p - 1 ? 'ring-2 ring-primary ring-offset-1 bg-primary/5' : 'border-base-content/20 hover:bg-base-200 hover:border-base-content/30',
                            resizing?.tier === tier.tier && resizeOver === 'right' ? 'ring-2 ring-info ring-offset-1 bg-info/5' : '',
                            resizing?.tier === tier.tier && resizeOver === 'left' ? 'ring-2 ring-warning ring-offset-1 bg-warning/5' : '',
                        ]"
                        @click="onPhantomClick(tier.tier)"
                        @dragover="onDragOver($event, tier.tier, tier.slots.length + p - 1)"
                        @dragleave="onDragLeave"
                        @drop="onDrop($event, tier.tier, tier.slots.length + p - 1)"
                    >
                        <PlusIcon class="size-4 text-base-content/30" />
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Slot Modal -->
    <dialog :open="showAddSlot" class="modal" @click.self="showAddSlot = false">
        <div class="modal-box max-w-sm">
            <h3 class="text-lg font-bold mb-4">Tambah Slot Barang</h3>
            <div v-if="slotError" class="alert alert-error alert-soft mb-3 py-2 text-sm">{{ slotError }}</div>

            <div class="space-y-3">
                <div>
                    <label class="label py-1"><span class="label-text text-xs font-medium">Tingkat</span></label>
                    <div class="input input-bordered input-sm w-full flex items-center text-sm font-medium bg-base-200">
                        Tingkat {{ slotForm.tier }}
                    </div>
                </div>

                <div>
                    <label class="label py-1"><span class="label-text text-xs font-medium">Cari Produk</span></label>
                    <div v-if="slotForm.productId" class="flex items-center gap-2 mb-2 p-2 bg-base-200 rounded text-sm">
                        <span class="font-medium truncate flex-1">{{ productSearch }}</span>
                        <button class="btn btn-ghost btn-xs" @click="slotForm.productId = null; productSearch = ''">Ganti</button>
                    </div>
                    <div v-else>
                        <input v-model="productSearch" type="text" class="input input-bordered input-sm w-full"
                            placeholder="Ketik nama/SKU produk..." :disabled="productsLoading" />
                        <div v-if="productsLoading" class="flex items-center gap-2 py-2 text-xs text-base-content/40">
                            <span class="loading loading-spinner loading-xs"></span> Memuat...
                        </div>
                        <div v-else-if="productSearch.trim() && filteredProducts.length > 0"
                            class="mt-1 border border-base-300 rounded max-h-32 overflow-y-auto">
                            <button v-for="p in filteredProducts.slice(0, 20)" :key="p.id"
                                class="block w-full text-left px-2 py-1.5 text-xs hover:bg-base-200 transition-colors"
                                @click="selectProduct(p)">
                                <span class="font-mono text-base-content/50 mr-2">{{ p.sku }}</span>{{ p.name }}
                            </button>
                        </div>
                        <p v-else-if="productSearch.trim() && filteredProducts.length === 0" class="text-xs text-base-content/40 mt-1">
                            Tidak ada produk yang cocok.
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label py-1"><span class="label-text text-xs font-medium">Qty</span></label>
                        <input v-model.number="slotForm.qty" type="number" min="0" class="input input-bordered input-sm w-full" />
                    </div>
                    <div>
                        <label class="label py-1"><span class="label-text text-xs font-medium">Min Qty</span></label>
                        <input v-model.number="slotForm.minQty" type="number" min="0" class="input input-bordered input-sm w-full" />
                    </div>
                </div>
            </div>

            <div class="modal-action mt-4">
                <button class="btn btn-sm btn-ghost" @click="showAddSlot = false">Batal</button>
                <button class="btn btn-sm btn-primary" :disabled="saving" @click="handleCreateSlot">
                    <span v-if="saving" class="loading loading-spinner loading-xs"></span>
                    Simpan
                </button>
            </div>
        </div>
    </dialog>
</template>
