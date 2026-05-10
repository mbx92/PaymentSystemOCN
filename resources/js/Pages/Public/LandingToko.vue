<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
  landing: {
    type: Object,
    required: true,
  },
});
</script>

<template>
  <Head :title="landing?.content?.seo_title || `${landing.name} — Toko`" />
  <div class="min-h-screen bg-base-200">
    <div class="navbar bg-base-100 shadow-sm">
      <div class="flex-1 px-4 font-semibold">{{ landing.name }}</div>
    </div>

    <div class="hero min-h-[70vh] bg-gradient-to-br from-primary/15 to-base-200">
      <div class="hero-content text-center">
        <div class="max-w-xl">
          <h1 class="text-4xl font-bold tracking-tight">
            {{ landing?.content?.headline || 'Toko & produk retail' }}
          </h1>
          <p v-if="landing?.content?.subheadline" class="mt-2 text-sm font-medium text-base-content/70">
            {{ landing.content.subheadline }}
          </p>
          <p class="mt-4 text-base-content/80">
            {{ landing?.content?.body || 'Landing untuk penjualan toko. Stok yang ditampilkan nanti bisa difilter ke warehouse:' }}
            <template v-if="!landing?.content?.body">
            <span v-if="landing.warehouse" class="font-mono text-sm">{{ landing.warehouse.code }}</span>
            <span v-else class="text-base-content/60">(belum dipilih di pengaturan)</span>
            </template>
          </p>
          <p v-if="landing?.content?.contact_text" class="mt-3 text-sm text-base-content/70">
            {{ landing.content.contact_text }}
          </p>
          <div v-if="landing?.content?.primary_cta_text || landing?.content?.secondary_cta_text" class="mt-6 flex flex-wrap justify-center gap-2">
            <a v-if="landing?.content?.primary_cta_text && landing?.content?.primary_cta_url" :href="landing.content.primary_cta_url" target="_blank" rel="noopener noreferrer" class="btn btn-primary btn-sm">
              {{ landing.content.primary_cta_text }}
            </a>
            <a v-if="landing?.content?.secondary_cta_text && landing?.content?.secondary_cta_url" :href="landing.content.secondary_cta_url" target="_blank" rel="noopener noreferrer" class="btn btn-outline btn-sm">
              {{ landing.content.secondary_cta_text }}
            </a>
          </div>
          <p class="mt-2 text-xs text-base-content/60 font-mono">Host: {{ landing.domain }}</p>
        </div>
      </div>
    </div>
  </div>
</template>
