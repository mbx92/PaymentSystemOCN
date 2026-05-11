<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import {
  UserPlusIcon,
  BuildingOffice2Icon,
  ClipboardDocumentListIcon,
  ChatBubbleBottomCenterTextIcon,
} from '@heroicons/vue/24/outline';

const cards = [
  {
    title: 'Lead Management',
    description: 'Pusat data calon customer, sumber lead, status prospek, dan PIC follow-up.',
    icon: UserPlusIcon,
  },
  {
    title: 'Customer Database',
    description: 'Master customer lintas sub usaha agar histori komunikasi dan transaksi tetap terhubung.',
    icon: BuildingOffice2Icon,
  },
  {
    title: 'Pipeline Penjualan',
    description: 'Tahapan penawaran, deal value, dan peluang closing yang bisa dipantau tim.',
    icon: ClipboardDocumentListIcon,
  },
  {
    title: 'Aktivitas Follow-up',
    description: 'Log call, chat, meeting, reminder, dan next action untuk setiap prospek/customer.',
    icon: ChatBubbleBottomCenterTextIcon,
  },
];
</script>

<template>
  <Head title="ERP - CRM" />
  <AppLayout>
    <div class="space-y-5">
      <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">ERP Module</p>
        <div class="mt-2 flex items-center justify-between gap-3">
          <h1 class="text-3xl font-bold tracking-tight">CRM</h1>
          <Link class="btn btn-ghost btn-sm" :href="route('dashboard')">Back</Link>
        </div>
        <p class="mt-2 text-sm text-base-content/70">
          Modul CRM disiapkan sebagai pusat lead, customer, pipeline, dan aktivitas follow-up lintas usaha.
        </p>
      </div>

      <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div
          v-for="card in cards"
          :key="card.title"
          class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
        >
          <component :is="card.icon" class="absolute right-3 top-3 h-10 w-10 text-primary/15" />
          <h2 class="text-lg font-semibold">{{ card.title }}</h2>
          <p class="mt-2 text-sm text-base-content/65">{{ card.description }}</p>
          <div class="mt-5">
            <span class="badge badge-outline badge-primary">Planned</span>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
