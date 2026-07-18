<script setup>
import { ref, onBeforeUnmount } from 'vue';

const props = defineProps({
    shelves: { type: Array, required: true },
    loading: { type: Boolean, default: false },
});

const emit = defineEmits(['select', 'update-position']);

const CELL_SIZE = 120;
const GRID_COLS = 6;
const GRID_ROWS = 4;
const SVG_PADDING = 40;
const DRAG_THRESHOLD = 5;

const svgWidth = GRID_COLS * CELL_SIZE + SVG_PADDING * 2;
const svgHeight = GRID_ROWS * CELL_SIZE + SVG_PADDING * 2;

const draggingShelf = ref(null);
const hasDragged = ref(false);
const dragOffset = ref({ x: 0, y: 0 });
const dragPos = ref({ x: 0, y: 0 });
const dragStartPos = ref({ x: 0, y: 0 });

function gridToPixel(col, row) {
    return {
        x: SVG_PADDING + col * CELL_SIZE + CELL_SIZE / 2,
        y: SVG_PADDING + row * CELL_SIZE + CELL_SIZE / 2,
    };
}

function pixelToGrid(px, py) {
    const col = Math.round((px - SVG_PADDING - CELL_SIZE / 2) / CELL_SIZE);
    const row = Math.round((py - SVG_PADDING - CELL_SIZE / 2) / CELL_SIZE);
    return {
        col: Math.max(0, Math.min(GRID_COLS - 1, col)),
        row: Math.max(0, Math.min(GRID_ROWS - 1, row)),
    };
}

function onPointerDown(event, shelf) {
    if (event.button !== 0) return;
    event.preventDefault();
    event.stopPropagation();

    const svgEl = event.currentTarget.closest('svg');
    const pt = svgEl.createSVGPoint();
    pt.x = event.clientX;
    pt.y = event.clientY;
    const svgPt = pt.matrixTransform(svgEl.getScreenCTM().inverse());

    const { x, y } = gridToPixel(shelf.col, shelf.row);

    draggingShelf.value = shelf;
    hasDragged.value = false;
    dragStartPos.value = { x: svgPt.x, y: svgPt.y };
    dragOffset.value = { x: svgPt.x - x, y: svgPt.y - y };
    dragPos.value = { x: svgPt.x - dragOffset.value.x, y: svgPt.y - dragOffset.value.y };

    document.addEventListener('pointermove', onPointerMove);
    document.addEventListener('pointerup', onPointerUp);
}

function onPointerMove(event) {
    if (!draggingShelf.value) return;

    const svgEl = event.target.closest('svg');
    if (!svgEl) return;

    const pt = svgEl.createSVGPoint();
    pt.x = event.clientX;
    pt.y = event.clientY;

    try {
        const svgPt = pt.matrixTransform(svgEl.getScreenCTM().inverse());

        const dx = svgPt.x - dragStartPos.value.x;
        const dy = svgPt.y - dragStartPos.value.y;
        if (Math.abs(dx) > DRAG_THRESHOLD || Math.abs(dy) > DRAG_THRESHOLD) {
            hasDragged.value = true;
        }

        dragPos.value = {
            x: svgPt.x - dragOffset.value.x,
            y: svgPt.y - dragOffset.value.y,
        };
    } catch {}
}

function onPointerUp() {
    document.removeEventListener('pointermove', onPointerMove);
    document.removeEventListener('pointerup', onPointerUp);

    if (!draggingShelf.value) return;

    if (hasDragged.value) {
        const { col, row } = pixelToGrid(dragPos.value.x, dragPos.value.y);

        if (col !== draggingShelf.value.col || row !== draggingShelf.value.row) {
            emit('update-position', { id: draggingShelf.value.id, row, col });
        }
    } else {
        emit('select', draggingShelf.value);
    }

    draggingShelf.value = null;
}

onBeforeUnmount(() => {
    document.removeEventListener('pointermove', onPointerMove);
    document.removeEventListener('pointerup', onPointerUp);
});

function shelfBgColor(shelf) {
    if (!shelf._aggregated) return '#9ca3af';
    if (shelf._aggregated.hasEmpty) return '#ef4444';
    if (shelf._aggregated.hasLow) return '#eab308';
    return '#22c55e';
}

function getShelfPos(shelf) {
    if (draggingShelf.value && draggingShelf.value.id === shelf.id) {
        return dragPos.value;
    }
    return gridToPixel(shelf.col, shelf.row);
}

const SHELF_W = 80;
const SHELF_H = 60;
</script>

<template>
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body p-4">
            <h3 class="card-title text-base mb-0">Peta Toko (Top View)</h3>

            <div v-if="loading" class="flex items-center justify-center py-16">
                <span class="loading loading-spinner loading-lg text-primary"></span>
            </div>

            <div v-else class="overflow-auto">
                <svg
                    :width="svgWidth"
                    :height="svgHeight"
                    :viewBox="`0 0 ${svgWidth} ${svgHeight}`"
                    class="w-full max-w-4xl mx-auto select-none"
                >
                    <!-- Grid lines -->
                    <g v-for="r in GRID_ROWS" :key="'h-' + r">
                        <line
                            v-for="c in GRID_COLS"
                            :key="'grid-' + r + '-' + c"
                            :x1="SVG_PADDING + (c - 1) * CELL_SIZE"
                            :y1="SVG_PADDING + (r - 1) * CELL_SIZE"
                            :x2="SVG_PADDING + c * CELL_SIZE"
                            :y2="SVG_PADDING + (r - 1) * CELL_SIZE"
                            stroke="#e5e7eb"
                            stroke-width="1"
                        />
                    </g>
                    <g v-for="c in GRID_COLS" :key="'v-' + c">
                        <line
                            v-for="r in GRID_ROWS"
                            :key="'grid-v-' + c + '-' + r"
                            :x1="SVG_PADDING + (c - 1) * CELL_SIZE"
                            :y1="SVG_PADDING + (r - 1) * CELL_SIZE"
                            :x2="SVG_PADDING + (c - 1) * CELL_SIZE"
                            :y2="SVG_PADDING + r * CELL_SIZE"
                            stroke="#e5e7eb"
                            stroke-width="1"
                        />
                    </g>

                    <!-- Shelves -->
                    <g
                        v-for="shelf in shelves"
                        :key="shelf.id"
                        :transform="`translate(${getShelfPos(shelf).x}, ${getShelfPos(shelf).y})`"
                        :class="draggingShelf && draggingShelf.id === shelf.id ? 'cursor-grabbing' : 'cursor-pointer'"
                        @pointerdown.prevent.stop="onPointerDown($event, shelf)"
                    >
                        <!-- Shadow -->
                        <rect
                            :x="-SHELF_W / 2 + 2"
                            :y="-SHELF_H / 2 + 2"
                            :width="SHELF_W"
                            :height="SHELF_H"
                            rx="4"
                            fill="rgba(0,0,0,0.12)"
                        />
                        <!-- Shelf body -->
                        <rect
                            :x="-SHELF_W / 2"
                            :y="-SHELF_H / 2"
                            :width="SHELF_W"
                            :height="SHELF_H"
                            rx="4"
                            :fill="shelfBgColor(shelf)"
                            stroke="#fff"
                            stroke-width="2"
                            class="transition-colors duration-200"
                        />
                        <!-- Shelf code label -->
                        <text
                            x="0"
                            y="4"
                            text-anchor="middle"
                            dominant-baseline="central"
                            class="fill-white text-xs font-bold"
                            style="font-size: 11px; pointer-events: none;"
                        >
                            {{ shelf.code }}
                        </text>
                    </g>

                    <!-- Empty state message -->
                    <text
                        v-if="shelves.length === 0"
                        :x="svgWidth / 2"
                        :y="svgHeight / 2"
                        text-anchor="middle"
                        dominant-baseline="central"
                        class="fill-base-content/40 text-sm"
                    >
                        Belum ada rak yang tersedia
                    </text>
                </svg>

                <div class="flex items-center gap-4 justify-center mt-3 text-xs text-base-content/60">
                    <div class="flex items-center gap-1">
                        <span class="inline-block w-3 h-3 rounded-sm bg-green-500"></span>
                        Stok Aman
                    </div>
                    <div class="flex items-center gap-1">
                        <span class="inline-block w-3 h-3 rounded-sm bg-yellow-500"></span>
                        Stok Menipis
                    </div>
                    <div class="flex items-center gap-1">
                        <span class="inline-block w-3 h-3 rounded-sm bg-red-500"></span>
                        Stok Habis
                    </div>
                    <div class="flex items-center gap-1">
                        <span class="inline-block w-3 h-3 rounded-sm bg-gray-400"></span>
                        Tidak Ada Data
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
