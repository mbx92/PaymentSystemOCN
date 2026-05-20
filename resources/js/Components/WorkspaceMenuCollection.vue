<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ArrowTopRightOnSquareIcon, ChevronRightIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    menus: {
        type: Array,
        default: () => [],
    },
    layout: {
        type: String,
        default: 'grid',
    },
    emptyMessage: {
        type: String,
        default: 'Belum ada menu yang bisa ditampilkan.',
    },
    actionLabel: {
        type: String,
        default: 'Open menu',
    },
    actionNewTabLabel: {
        type: String,
        default: 'Open menu (New Tab)',
    },
    reorderable: {
        type: Boolean,
        default: false,
    },
});
const emit = defineEmits(['reorder']);

const resolvedLayout = computed(() => (props.layout === 'list' ? 'list' : 'grid'));
const draggedKey = ref(null);

const normalizedMenus = computed(() =>
    (props.menus ?? [])
        .map((menu) => ({
            ...menu,
            href: menu.href ?? menu.url ?? (menu.route ? route(menu.route) : '#'),
        }))
        .filter((menu) => menu.href && menu.href !== '#'),
);

const handleDragStart = (event, menuKey) => {
    if (!props.reorderable || !menuKey) return;

    draggedKey.value = menuKey;
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', menuKey);
};

const handleDrop = (targetKey) => {
    if (!props.reorderable || !draggedKey.value || draggedKey.value === targetKey) {
        draggedKey.value = null;
        return;
    }

    const currentOrder = normalizedMenus.value.map((menu) => menu.key);
    const fromIndex = currentOrder.indexOf(draggedKey.value);
    const toIndex = currentOrder.indexOf(targetKey);

    if (fromIndex === -1 || toIndex === -1) {
        draggedKey.value = null;
        return;
    }

    const reordered = [...currentOrder];
    const [moved] = reordered.splice(fromIndex, 1);
    reordered.splice(toIndex, 0, moved);
    emit('reorder', reordered);
    draggedKey.value = null;
};

const linkAttrs = (menu) => (
    menu.newTab
        ? {
            href: menu.href,
            target: '_blank',
            rel: 'noopener noreferrer',
        }
        : {
            href: menu.href,
        }
);
</script>

<template>
    <div v-if="!normalizedMenus.length" class="ocn-panel">
        <div class="card-body py-8 text-center text-sm text-base-content/60">
            {{ emptyMessage }}
        </div>
    </div>

    <div v-else-if="resolvedLayout === 'grid'" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <component
            :is="menu.newTab ? 'a' : Link"
            v-for="menu in normalizedMenus"
            :key="menu.key ?? `${menu.title}-${menu.href}`"
            v-bind="linkAttrs(menu)"
            :draggable="reorderable"
            class="group relative flex min-h-[210px] flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-md"
            @dragstart="handleDragStart($event, menu.key)"
            @dragover.prevent
            @drop.prevent="handleDrop(menu.key)"
        >
            <span class="absolute left-0 top-4 h-0 w-1 rounded-r bg-primary/70 transition-all duration-300 group-hover:h-16" />
            <component
                :is="menu.iconComponent"
                class="pointer-events-none absolute bottom-1 right-2 h-24 w-24 text-primary/10 transition duration-300 group-hover:scale-125 group-hover:text-primary/20"
            />
            <h2 class="text-lg font-semibold group-hover:text-primary">{{ menu.title }}</h2>
            <p class="mt-2 max-w-[85%] text-sm text-base-content/65">{{ menu.description }}</p>
            <p class="mt-auto pt-4 text-xs font-semibold uppercase tracking-[0.12em] text-primary/70">
                {{ menu.newTab ? actionNewTabLabel : actionLabel }}
            </p>
        </component>
    </div>

    <div v-else class="ocn-panel overflow-hidden">
        <div class="divide-y divide-base-200">
            <component
            :is="menu.newTab ? 'a' : Link"
            v-for="menu in normalizedMenus"
            :key="menu.key ?? `${menu.title}-${menu.href}`"
            v-bind="linkAttrs(menu)"
            class="group flex items-center gap-4 px-4 py-4 transition hover:bg-base-200/30 md:px-5"
            :draggable="reorderable"
            @dragstart="handleDragStart($event, menu.key)"
            @dragover.prevent
            @drop.prevent="handleDrop(menu.key)"
        >
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                    <component :is="menu.iconComponent" class="h-6 w-6" />
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-base font-semibold text-base-content group-hover:text-primary">{{ menu.title }}</h2>
                        <span v-if="menu.newTab" class="badge badge-ghost badge-xs">New Tab</span>
                    </div>
                    <p class="mt-1 text-sm text-base-content/65">{{ menu.description }}</p>
                </div>

                <div class="hidden shrink-0 items-center gap-2 text-xs font-semibold uppercase tracking-[0.12em] text-primary/70 sm:flex">
                    <span>{{ menu.newTab ? actionNewTabLabel : actionLabel }}</span>
                    <ArrowTopRightOnSquareIcon v-if="menu.newTab" class="h-4 w-4" />
                    <ChevronRightIcon v-else class="h-4 w-4" />
                </div>
            </component>
        </div>
    </div>
</template>
