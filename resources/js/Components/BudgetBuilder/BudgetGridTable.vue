<script setup>
import { computed, inject } from 'vue';
import { normalizeProductAmount, normalizeProductQty } from '@/composables/useBudgetBuilderItems';
import { useCurrency } from '@/composables/useCurrency';

const props = defineProps({
    slotMap: { type: Object, required: true },
    rowCount: { type: Number, required: true },
    canEdit: { type: Boolean, default: true },
    dropHighlight: { type: Number, default: null },
});

const emit = defineEmits(['drop-slot', 'dragover-slot', 'dragleave-slot']);

const actions = inject('budgetBuilderActions', null);
const { format } = useCurrency();

const rows = computed(() => Array.from({ length: props.rowCount }, (_, index) => index));

function cellFor(index) {
    return props.slotMap[index] ?? null;
}

function sourceLabel(cell) {
    if (cell.catalog_ref) return 'Katalog';
    if (cell.master_product_id) return 'Master';
    return 'Manual';
}

function subtotal(cell) {
    return normalizeProductQty(cell.qty) * (Number(cell.unit_price) || 0);
}

function onDragOver(index, event) {
    if (!props.canEdit) return;
    event.preventDefault();
    emit('dragover-slot', index);
}

function onDrop(index, event) {
    if (!props.canEdit) return;
    event.preventDefault();
    emit('drop-slot', index);
}

function onDragLeave(index) {
    emit('dragleave-slot', index);
}
</script>

<template>
    <div class="overflow-auto h-full bg-base-100">
        <table class="table table-sm table-pin-rows">
            <thead>
                <tr class="bg-base-200 text-xs uppercase tracking-wide">
                    <th class="w-12 text-center">#</th>
                    <th>Produk</th>
                    <th class="w-28 text-center">Qty</th>
                    <th class="w-40 text-right">Harga Beli</th>
                    <th class="w-40 text-right">Harga Jual</th>
                    <th class="w-36 text-right">Subtotal</th>
                    <th class="w-12" />
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="index in rows"
                    :key="index"
                    class="transition-colors"
                    :class="dropHighlight === index ? 'bg-primary/10 ring-1 ring-inset ring-primary/30' : ''"
                    @dragover="onDragOver(index, $event)"
                    @drop="onDrop(index, $event)"
                    @dragleave="onDragLeave(index)"
                >
                    <td class="text-center text-base-content/45 font-mono text-xs">{{ index + 1 }}</td>
                    <td class="min-w-[220px]">
                        <template v-if="cellFor(index)">
                            <p class="font-medium text-sm leading-tight">{{ cellFor(index).name }}</p>
                            <p class="text-[10px] text-base-content/50 mt-0.5">
                                {{ sourceLabel(cellFor(index)) }}
                                <span v-if="cellFor(index).catalog_ref" class="font-mono">· {{ cellFor(index).catalog_ref }}</span>
                            </p>
                        </template>
                        <span v-else class="text-xs text-base-content/35 italic">Kosong — drop produk di sini</span>
                    </td>
                    <td class="text-center">
                        <div v-if="cellFor(index)" class="join join-horizontal justify-center">
                            <button
                                type="button"
                                class="btn btn-xs join-item"
                                :disabled="!canEdit"
                                @click="actions?.updateQty?.(index, -1)"
                            >
                                −
                            </button>
                            <span class="btn btn-xs join-item btn-ghost pointer-events-none tabular-nums min-w-[2rem]">
                                {{ normalizeProductQty(cellFor(index).qty) }}
                            </span>
                            <button
                                type="button"
                                class="btn btn-xs join-item"
                                :disabled="!canEdit"
                                @click="actions?.updateQty?.(index, 1)"
                            >
                                +
                            </button>
                        </div>
                        <span v-else class="text-base-content/25">—</span>
                    </td>
                    <td class="text-right tabular-nums text-sm">
                        <template v-if="cellFor(index)">
                            <label
                                v-if="canEdit"
                                class="input input-bordered input-xs flex items-center w-32 ml-auto"
                            >
                                <input
                                    type="number"
                                    min="0"
                                    step="1"
                                    class="w-full text-right tabular-nums"
                                    :value="normalizeProductAmount(cellFor(index).unit_cost)"
                                    @change="actions?.updateAmount?.(index, 'unit_cost', $event.target.value)"
                                >
                            </label>
                            <span v-else>{{ format(cellFor(index).unit_cost) }}</span>
                        </template>
                        <span v-else>—</span>
                    </td>
                    <td class="text-right tabular-nums text-sm">
                        <template v-if="cellFor(index)">
                            <label
                                v-if="canEdit"
                                class="input input-bordered input-xs flex items-center w-32 ml-auto"
                            >
                                <input
                                    type="number"
                                    min="0"
                                    step="1"
                                    class="w-full text-right tabular-nums"
                                    :value="normalizeProductAmount(cellFor(index).unit_price)"
                                    @change="actions?.updateAmount?.(index, 'unit_price', $event.target.value)"
                                >
                            </label>
                            <span v-else>{{ format(cellFor(index).unit_price) }}</span>
                        </template>
                        <span v-else>—</span>
                    </td>
                    <td class="text-right tabular-nums text-sm font-medium">
                        {{ cellFor(index) ? format(subtotal(cellFor(index))) : '—' }}
                    </td>
                    <td class="text-center">
                        <button
                            v-if="cellFor(index) && canEdit"
                            type="button"
                            class="btn btn-ghost btn-xs btn-square text-error"
                            title="Hapus"
                            @click="actions?.removeSlot?.(index)"
                        >
                            ×
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
