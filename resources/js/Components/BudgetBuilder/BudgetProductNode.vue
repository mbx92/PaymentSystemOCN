<script setup>
import { computed, inject } from 'vue';
import { normalizeProductQty } from '@/composables/useBudgetBuilderItems';
import { useCurrency } from '@/composables/useCurrency';

const props = defineProps({
    id: { type: String, required: true },
    data: { type: Object, default: () => ({}) },
});

const actions = inject('budgetBuilderActions', null);
const { format } = useCurrency();

const displayQty = computed(() => normalizeProductQty(props.data.qty));
const subtotal = computed(() => displayQty.value * (Number(props.data.unit_price) || 0));
const sourceLabel = computed(() => {
    if (props.data.catalog_ref) return 'Katalog';
    if (props.data.master_product_id) return 'Master';
    return 'Manual';
});
</script>

<template>
    <div class="rack-product-card h-full w-full rounded-lg border border-base-300 bg-base-100 shadow-sm p-2 cursor-grab active:cursor-grabbing flex flex-col">
        <div class="flex items-start justify-between gap-1 min-h-0">
            <div class="min-w-0 flex-1">
                <p class="text-[11px] font-semibold leading-tight line-clamp-3" :title="data.name">{{ data.name }}</p>
                <p class="text-[9px] uppercase tracking-wide text-base-content/45 mt-0.5">{{ sourceLabel }}</p>
            </div>
            <button
                type="button"
                class="btn btn-ghost btn-xs btn-square min-h-0 h-5 w-5 text-error shrink-0"
                title="Hapus"
                @click.stop="actions?.removeNode?.(id)"
            >
                ×
            </button>
        </div>

        <div class="mt-auto flex items-end justify-between gap-1 pt-1">
            <div class="join join-horizontal scale-95 origin-left">
                <button type="button" class="btn btn-xs join-item min-h-0 h-6 px-2" @click.stop="actions?.updateQty?.(id, -1)">−</button>
                <span class="btn btn-xs join-item btn-ghost pointer-events-none tabular-nums min-h-0 h-6 px-2 min-w-[1.75rem]">{{ displayQty }}</span>
                <button type="button" class="btn btn-xs join-item min-h-0 h-6 px-2" @click.stop="actions?.updateQty?.(id, 1)">+</button>
            </div>
            <span class="text-[10px] font-semibold tabular-nums leading-none">{{ format(subtotal) }}</span>
        </div>
    </div>
</template>

<style scoped>
.rack-product-card {
    box-sizing: border-box;
    box-shadow: 0 1px 0 rgb(0 0 0 / 0.04), 0 4px 10px rgb(0 0 0 / 0.06);
}

.rack-product-card:active {
    box-shadow: 0 8px 18px rgb(0 0 0 / 0.12);
}
</style>
