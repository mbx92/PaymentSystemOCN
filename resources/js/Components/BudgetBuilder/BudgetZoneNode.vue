<script setup>
import { computed, inject } from 'vue';
import { RACK_LAYOUT, zoneDimensions } from '@/composables/useBudgetBuilderItems';
import { useCurrency } from '@/composables/useCurrency';

const props = defineProps({
    id: { type: String, required: true },
    data: { type: Object, default: () => ({}) },
});

const zoneStats = inject('zoneStats', null);
const { format } = useCurrency();

const stats = computed(() => zoneStats?.value?.[props.data.zoneId ?? props.id] ?? { itemCount: 0, subtotal: 0, rows: RACK_LAYOUT.minRows });
const itemCount = computed(() => stats.value.itemCount);
const subtotal = computed(() => stats.value.subtotal);
const rows = computed(() => stats.value.rows ?? props.data.rows ?? RACK_LAYOUT.minRows);
const slots = computed(() => Array.from({ length: RACK_LAYOUT.cols * rows.value }, (_, index) => index));
const accentClass = computed(() => ({
    primary: 'rack-accent-primary',
    secondary: 'rack-accent-secondary',
    accent: 'rack-accent-accent',
    info: 'rack-accent-info',
    warning: 'rack-accent-warning',
    neutral: 'rack-accent-neutral',
}[props.data.accent ?? 'primary'] ?? 'rack-accent-primary'));

const rackDims = computed(() => zoneDimensions(itemCount.value));
</script>

<template>
    <div class="rack-frame h-full rounded-xl border border-base-300 bg-base-200/80 shadow-md overflow-hidden" :class="accentClass">
        <div
            class="rack-header flex items-center justify-between gap-2 px-3 py-2 border-b border-base-300/80 bg-base-100/90"
            :style="{ height: `${RACK_LAYOUT.headerHeight}px` }"
        >
            <div class="min-w-0">
                <p class="text-sm font-bold uppercase tracking-wide truncate">{{ data.label }}</p>
                <p class="text-xs text-base-content/50">Rak · {{ RACK_LAYOUT.cols }} kolom</p>
            </div>
            <div class="text-right shrink-0">
                <span class="badge badge-md badge-outline tabular-nums">{{ itemCount }}</span>
                <p v-if="itemCount > 0" class="text-xs font-medium tabular-nums mt-0.5 opacity-80">{{ format(subtotal) }}</p>
            </div>
        </div>

        <div
            class="rack-grid"
            :style="{
                padding: `${RACK_LAYOUT.padding}px`,
                gridTemplateColumns: `repeat(${RACK_LAYOUT.cols}, ${RACK_LAYOUT.slotWidth}px)`,
                gridAutoRows: `${RACK_LAYOUT.slotHeight}px`,
                gap: `${RACK_LAYOUT.gap}px`,
                minHeight: `${rackDims.height - RACK_LAYOUT.headerHeight}px`,
            }"
        >
            <div
                v-for="slot in slots"
                :key="slot"
                class="rack-slot rounded-lg border border-dashed border-base-content/15 bg-base-100/50 flex items-center justify-center"
            >
                <span class="text-[10px] text-base-content/25 font-mono select-none">{{ slot + 1 }}</span>
            </div>
        </div>
    </div>
</template>

<style scoped>
.rack-frame {
    pointer-events: none;
}

.rack-header {
    box-sizing: border-box;
}

.rack-grid {
    display: grid;
}

.rack-accent-primary { box-shadow: inset 0 3px 0 rgb(var(--color-primary) / 0.55); }
.rack-accent-secondary { box-shadow: inset 0 3px 0 rgb(var(--color-secondary) / 0.55); }
.rack-accent-accent { box-shadow: inset 0 3px 0 rgb(var(--color-accent) / 0.55); }
.rack-accent-info { box-shadow: inset 0 3px 0 rgb(var(--color-info) / 0.55); }
.rack-accent-warning { box-shadow: inset 0 3px 0 rgb(var(--color-warning) / 0.55); }
.rack-accent-neutral { box-shadow: inset 0 3px 0 rgb(var(--color-neutral) / 0.35); }
</style>
