<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
  landing: {
    type: Object,
    required: true,
  },
});
</script>

<template>
  <Head :title="`${landing.name} — CCTV`" />
  <div class="min-h-screen bg-base-200">
    <div class="navbar bg-base-100 shadow-sm">
      <div class="flex-1 px-4 font-semibold">{{ landing.name }}</div>
      <div class="flex-none gap-2 pr-2">
        <Link :href="route('login')" class="btn btn-ghost btn-sm">Login ERP</Link>
      </div>
    </div>

    <div class="hero min-h-[70vh] bg-gradient-to-br from-secondary/15 to-base-200">
      <div class="hero-content text-center">
        <div class="max-w-xl">
          <h1 class="text-4xl font-bold tracking-tight">CCTV & solusi jaringan</h1>
          <p class="mt-4 text-base-content/80">
            Landing untuk bisnis instalasi / project. Warehouse default untuk stok material:
            <span v-if="landing.warehouse" class="font-mono text-sm">{{ landing.warehouse.code }}</span>
            <span v-else class="text-base-content/60">(belum dipilih di pengaturan)</span>
          </p>
          <p class="mt-2 text-xs text-base-content/60 font-mono">Host: {{ landing.domain }}</p>
        </div>
      </div>
    </div>
  </div>
</template>
