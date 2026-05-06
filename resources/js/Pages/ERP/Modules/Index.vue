<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import {
  ArrowDownCircleIcon,
  ArrowUpCircleIcon,
  BookOpenIcon,
  ScaleIcon,
  ChartBarIcon,
  CalendarDaysIcon,
  ShoppingCartIcon,
  DocumentTextIcon,
  ArchiveBoxIcon,
  CubeIcon,
  CodeBracketIcon,
  UsersIcon,
  UserCircleIcon,
  TagIcon,
  ArrowsRightLeftIcon,
  ClipboardDocumentCheckIcon,
  PresentationChartLineIcon,
  TruckIcon,
  ClipboardDocumentListIcon,
  InboxArrowDownIcon,
  SparklesIcon,
  Squares2X2Icon,
  IdentificationIcon,
} from '@heroicons/vue/24/outline';

defineProps({
  module: String,
  menus: Array,
});

const iconMap = {
  'arrow-down-circle': ArrowDownCircleIcon,
  'arrow-up-circle': ArrowUpCircleIcon,
  'book-open': BookOpenIcon,
  scale: ScaleIcon,
  'chart-bar': ChartBarIcon,
  'calendar-days': CalendarDaysIcon,
  'shopping-cart': ShoppingCartIcon,
  'document-text': DocumentTextIcon,
  'archive-box': ArchiveBoxIcon,
  cube: CubeIcon,
  'git-branch': CodeBracketIcon,
  users: UsersIcon,
  'user-circle': UserCircleIcon,
  tag: TagIcon,
  'arrows-right-left': ArrowsRightLeftIcon,
  'clipboard-check': ClipboardDocumentCheckIcon,
  'presentation-chart-line': PresentationChartLineIcon,
  truck: TruckIcon,
  'clipboard-list': ClipboardDocumentListIcon,
  'inbox-arrow-down': InboxArrowDownIcon,
  sparkles: SparklesIcon,
  identification: IdentificationIcon,
};

const iconFor = (menu) => iconMap[menu.icon] ?? Squares2X2Icon;
</script>

<template>
  <Head :title="`ERP - ${module}`" />
  <AppLayout>
    <div class="space-y-6">
      <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">ERP Module</p>
        <h1 class="mt-2 text-3xl font-bold tracking-tight">{{ module }}</h1>
        <p class="mt-2 text-sm text-base-content/70">
          Pilih submenu {{ module }} untuk lanjut ke workflow operasional.
        </p>
      </div>

      <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <a
          v-for="menu in menus.filter((m) => m.newTab)"
          :key="`newtab-${menu.title}`"
          :href="menu.url ?? route(menu.route)"
          target="_blank"
          rel="noopener noreferrer"
          class="group relative flex min-h-[210px] flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-md"
        >
          <span class="absolute left-0 top-4 h-0 w-1 rounded-r bg-primary/70 transition-all duration-300 group-hover:h-16"></span>
          <component
            :is="iconFor(menu)"
            class="pointer-events-none absolute right-2 bottom-1 h-24 w-24 text-primary/10 transition duration-300 group-hover:scale-125 group-hover:text-primary/20"
          />
          <h2 class="text-lg font-semibold group-hover:text-primary">{{ menu.title }}</h2>
          <p class="mt-2 max-w-[85%] text-sm text-base-content/65">{{ menu.description }}</p>
          <p class="mt-auto pt-4 text-xs font-semibold uppercase tracking-[0.12em] text-primary/70">Open Menu (New Tab)</p>
        </a>

        <Link
          v-for="menu in menus.filter((m) => !m.newTab)"
          :key="`normal-${menu.title}`"
          :href="menu.url ?? route(menu.route)"
          class="group relative flex min-h-[210px] flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-md"
        >
          <span class="absolute left-0 top-4 h-0 w-1 rounded-r bg-primary/70 transition-all duration-300 group-hover:h-16"></span>
          <component
            :is="iconFor(menu)"
            class="pointer-events-none absolute right-2 bottom-1 h-24 w-24 text-primary/10 transition duration-300 group-hover:scale-125 group-hover:text-primary/20"
          />
          <h2 class="text-lg font-semibold group-hover:text-primary">{{ menu.title }}</h2>
          <p class="mt-2 max-w-[85%] text-sm text-base-content/65">{{ menu.description }}</p>
          <p class="mt-auto pt-4 text-xs font-semibold uppercase tracking-[0.12em] text-primary/70">Open Menu</p>
        </Link>
      </div>
    </div>
  </AppLayout>
</template>
