<script setup>
import WorkspaceMenuCollection from '@/Components/WorkspaceMenuCollection.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import {ArrowLeftIcon,
  ChartBarIcon,
  ArrowsRightLeftIcon,
  ClipboardDocumentListIcon,
  ArrowTrendingUpIcon,
  Squares2X2Icon,
  TagIcon,
  WalletIcon,} from '@heroicons/vue/24/outline';
import { computed } from 'vue';

const props = defineProps({
  menus: Array,
});

const page = usePage();

const iconMap = {
  'chart-bar': ChartBarIcon,
  'wallet': WalletIcon,
  'tag': TagIcon,
  'arrows-right-left': ArrowsRightLeftIcon,
  'clipboard-list': ClipboardDocumentListIcon,
  'arrow-trending-up': ArrowTrendingUpIcon,
};

const iconFor = (menu) => iconMap[menu.icon] ?? Squares2X2Icon;
const menuLayout = computed(() => page.props.erpSetting?.module_menu_layout ?? 'grid');
const workspaceMenus = computed(() =>
  (props.menus ?? []).map((menu) => ({
    ...menu,
    href: route(menu.route),
    iconComponent: iconFor(menu),
  })),
);
</script>

<template>
  <Head title="Personal — Keuangan pribadi" />
  <AppLayout>
    <div class="space-y-6">
      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Personal</p>
              <h1 class="ocn-panel__title mt-1">Keuangan pribadi & keluarga</h1>
              <p class="ocn-panel__desc mt-1">Modul terpisah dari ERP bisnis: untuk pencatatan keuangan rumah tangga Anda.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
              <Link class="btn btn-ghost btn-sm shrink-0 gap-1.5" :href="route('dashboard')">
              <ArrowLeftIcon class="h-4 w-4" />
              Back
            </Link>
            </div>
          </div>
        </div>
      </div>

      <WorkspaceMenuCollection
        :menus="workspaceMenus"
        :layout="menuLayout"
        empty-message="Belum ada submenu personal yang bisa ditampilkan."
        action-label="Buka"
        action-new-tab-label="Buka"
      />
    </div>
  </AppLayout>
</template>
