<script setup>
import { computed } from 'vue';

const props = defineProps({
  widthMm:      { type: Number, default: 76.2 },
  heightMm:     { type: Number, default: 127 },
  marginLeftMm: { type: Number, default: 4 },
  marginTopMm:  { type: Number, default: 4 },
  gapMm:        { type: Number, default: 3 },
  rows:         { type: Number, default: 1 },
});

const FEED_SETS = 2;
const MAX_PREVIEW_WIDTH = 480;

const s = computed(() => {
  const r = Math.max(1, Math.min(3, props.rows));
  const totalW = props.widthMm * r + props.gapMm * Math.max(0, r - 1);
  return Math.min(3, MAX_PREVIEW_WIDTH / totalW);
});

const px = (mm) => mm * s.value;

const barPattern = [85, 95, 80, 100, 88, 92, 82, 97, 86, 93,
  81, 98, 84, 91, 87, 96, 83, 94, 89, 99,
  82, 90, 85, 93, 88, 97, 81, 95, 86, 92,
  84, 98, 87, 91, 83, 96];

const labelSets = computed(() => {
  const r = Math.max(1, Math.min(3, props.rows));
  const sets = [];
  for (let setIdx = 0; setIdx < FEED_SETS; setIdx++) {
    const labels = [];
    for (let col = 0; col < r; col++) {
      labels.push({ col });
    }
    sets.push({ setIdx, labels });
  }
  return sets;
});

const printAreaWidth = computed(() => Math.max(0, props.widthMm - props.marginLeftMm * 2));
const printAreaHeight = computed(() => Math.max(0, props.heightMm - props.marginTopMm * 2));

const barcodeH = computed(() => {
  const available = printAreaHeight.value - 14;
  return Math.max(8, Math.min(40, available * 0.4));
});
</script>

<template>
  <div class="inline-block rounded-xl border-2 border-dashed border-base-300 bg-[#f6f5f0] p-4 shadow-inner overflow-x-auto">
    <p class="text-[10px] uppercase tracking-wider text-base-content/50 mb-3 font-sans">
      Preview label roll · {{ rows }} row{{ rows > 1 ? 's' : '' }} ·
      {{ widthMm }}×{{ heightMm }} mm · gap {{ gapMm }} mm
    </p>

    <div class="flex flex-col items-center" :style="{ gap: px(gapMm) + 'px' }">
      <div v-for="set in labelSets" :key="set.setIdx"
        class="flex" :style="{ gap: px(gapMm) + 'px' }"
      >
        <div v-for="label in set.labels" :key="label.col"
          class="relative bg-white border-2 border-base-content/25 rounded-sm overflow-hidden"
          :style="{
            width: px(widthMm) + 'px',
            height: px(heightMm) + 'px',
          }"
        >
          <div class="absolute inset-0 pointer-events-none border border-dashed border-primary/20"
            :style="{
              top: px(marginTopMm) + 'px',
              left: px(marginLeftMm) + 'px',
              right: px(marginLeftMm) + 'px',
              bottom: px(marginTopMm) + 'px',
              width: 'auto',
              height: 'auto',
            }"
          />

          <div class="absolute flex flex-col justify-between"
            :style="{
              top: px(marginTopMm) + 'px',
              left: px(marginLeftMm) + 'px',
              right: px(marginLeftMm) + 'px',
              bottom: px(marginTopMm) + 'px',
            }"
          >
            <div class="space-y-[2px]">
              <div class="bg-base-content/80 rounded-[1px]"
                :style="{ height: Math.max(4, px(2.5)) + 'px', width: Math.min(100, px(printAreaWidth * 0.7)) + 'px' }"
              />
              <div class="bg-base-content/50 rounded-[1px]"
                :style="{ height: Math.max(3, px(2)) + 'px', width: Math.min(70, px(printAreaWidth * 0.45)) + 'px' }"
              />
            </div>

            <div class="flex flex-col items-start gap-px">
              <div class="w-full flex gap-px items-end" :style="{ height: px(barcodeH) + 'px' }">
                <div v-for="i in Math.max(4, Math.min(36, Math.floor(px(printAreaWidth) / 3.5)))" :key="i"
                  class="bg-base-content/75"
                  :style="{
                    width: (i % 3 === 0 ? 2 : 1) + 'px',
                    height: barPattern[(i - 1) % barPattern.length] + '%',
                    flexShrink: 0,
                  }"
                />
              </div>
              <div class="bg-base-content/40 rounded-[1px] mt-px"
                :style="{ height: Math.max(3, px(1.5)) + 'px', width: Math.min(80, px(printAreaWidth * 0.5)) + 'px' }"
              />
            </div>
          </div>

          <div v-if="marginLeftMm > 0"
            class="absolute top-1/2 left-0 -translate-y-1/2 border-l border-dashed border-error/30"
            :style="{ width: px(marginLeftMm) + 'px', height: '60%' }"
          />
          <div v-if="marginTopMm > 0"
            class="absolute top-0 left-1/2 -translate-x-1/2 border-t border-dashed border-error/30"
            :style="{ height: px(marginTopMm) + 'px', width: '60%' }"
          />
        </div>
      </div>

      <div v-if="gapMm > 0" class="w-full flex items-center justify-center -my-1">
        <span class="text-[8px] text-base-content/40 bg-[#f6f5f0] px-1">↕ gap {{ gapMm }} mm</span>
      </div>
    </div>

    <div class="mt-3 flex items-center gap-3 text-[10px] text-base-content/50">
      <span class="flex items-center gap-1">
        <span class="inline-block w-3 h-2 border border-dashed border-primary/30 rounded-[1px]"></span>
        Area cetak
      </span>
      <span class="flex items-center gap-1">
        <span class="inline-block w-3 h-px border-t border-dashed border-error/40"></span>
        Margin
      </span>
    </div>
  </div>
</template>
