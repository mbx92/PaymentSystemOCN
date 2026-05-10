<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import {
  ChartBarIcon,
  ArrowsRightLeftIcon,
  ClipboardDocumentListIcon,
  ArrowTrendingUpIcon,
  Squares2X2Icon,
} from '@heroicons/vue/24/outline';

defineProps({
  menus: Array,
});

const iconMap = {
  'chart-bar': ChartBarIcon,
  'arrows-right-left': ArrowsRightLeftIcon,
  'clipboard-list': ClipboardDocumentListIcon,
  'arrow-trending-up': ArrowTrendingUpIcon,
};

const iconFor = (menu) => iconMap[menu.icon] ?? Squares2X2Icon;
</script>

<template>
  <Head title="Personal — Keuangan pribadi" />
  <AppLayout>
    <div class="space-y-6">
      <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Personal</p>
        <div class="mt-2 flex items-center justify-between gap-3">
          <h1 class="text-3xl font-bold tracking-tight">Keuangan pribadi & keluarga</h1>
          <Link class="btn btn-ghost btn-sm" :href="route('dashboard')">Back</Link>
        </div>
        <p class="mt-2 text-sm text-base-content/70">
          Modul terpisah dari ERP bisnis: untuk pencatatan keuangan rumah tangga Anda.
        </p>
      </div>

      <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <Link
          v-for="menu in menus"
          :key="menu.title"
          :href="route(menu.route)"
          class="group relative flex min-h-[210px] flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-md"
        >
          <span class="absolute left-0 top-4 h-0 w-1 rounded-r bg-primary/70 transition-all duration-300 group-hover:h-16" />
          <component
            :is="iconFor(menu)"
            class="pointer-events-none absolute right-2 bottom-1 h-24 w-24 text-primary/10 transition duration-300 group-hover:scale-125 group-hover:text-primary/20"
          />
          <h2 class="text-lg font-semibold group-hover:text-primary">{{ menu.title }}</h2>
          <p class="mt-2 max-w-[85%] text-sm text-base-content/65">{{ menu.description }}</p>
          <p class="mt-auto pt-4 text-xs font-semibold uppercase tracking-[0.12em] text-primary/70">Buka</p>
        </Link>
      </div>
    </div>
  </AppLayout>
</template>
