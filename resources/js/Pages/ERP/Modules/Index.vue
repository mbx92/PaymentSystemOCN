<script setup>
import WorkspaceMenuCollection from '@/Components/WorkspaceMenuCollection.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
  ArrowDownCircleIcon,
  ArrowLeftIcon,
  ArrowUpCircleIcon,
  ArrowUpTrayIcon,
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
  CreditCardIcon,
  PrinterIcon,
  ShareIcon,
  Cog6ToothIcon,
  WrenchScrewdriverIcon,
} from '@heroicons/vue/24/outline';
import { computed } from 'vue';

const props = defineProps({
  module: String,
  menus: Array,
});

const page = usePage();

const iconMap = {
  'arrow-down-circle': ArrowDownCircleIcon,
  'arrow-up-circle': ArrowUpCircleIcon,
  'arrow-up-tray': ArrowUpTrayIcon,
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
  'credit-card': CreditCardIcon,
  printer: PrinterIcon,
  share: ShareIcon,
  'cog-6-tooth': Cog6ToothIcon,
  wrench: WrenchScrewdriverIcon,
};

const iconFor = (menu) => iconMap[menu.icon] ?? Squares2X2Icon;
const menuLayout = computed(() => page.props.erpSetting?.module_menu_layout ?? 'grid');
const workspaceMenus = computed(() =>
  (props.menus ?? []).map((menu) => ({
    ...menu,
    href: menu.url ?? route(menu.route),
    iconComponent: iconFor(menu),
  })),
);
</script>

<template>
  <Head :title="`ERP - ${module}`" />
  <AppLayout>
    <div class="space-y-6">
      <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">ERP Module</p>
        <div class="mt-2 flex items-center justify-between gap-3">
          <h1 class="text-3xl font-bold tracking-tight">{{ module }}</h1>
          <Link class="btn btn-ghost btn-sm gap-1.5" :href="route('dashboard')">
            <ArrowLeftIcon class="h-4 w-4" />
            Back
          </Link>
        </div>
        <p class="mt-2 text-sm text-base-content/70">
          Pilih submenu {{ module }} untuk lanjut ke workflow operasional.
        </p>
      </div>

      <WorkspaceMenuCollection
        :menus="workspaceMenus"
        :layout="menuLayout"
        empty-message="Belum ada submenu yang tersedia untuk modul ini."
        action-label="Open menu"
        action-new-tab-label="Open menu (New Tab)"
      />
    </div>
  </AppLayout>
</template>
