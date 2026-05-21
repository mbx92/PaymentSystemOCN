<script setup>
import { Head } from '@inertiajs/vue3';
import { useLandingTracking } from '@/composables/useLandingTracking';

defineProps({
  landing: {
    type: Object,
    required: true,
  },
});

const { trackCtaClick } = useLandingTracking();
</script>

<template>
  <Head :title="landing?.content?.seo_title || `${landing.name} — Coming Soon`" />
  <div class="min-h-screen bg-gradient-to-br from-slate-950 via-slate-900 to-slate-950 text-slate-100">
    <div class="mx-auto flex min-h-screen w-full max-w-5xl flex-col justify-center px-6 py-14">
      <div class="rounded-3xl border border-white/10 bg-white/5 p-8 shadow-2xl backdrop-blur md:p-12">
        <p class="text-xs font-bold uppercase tracking-[0.16em] text-cyan-200/80">OCNetworks</p>
        <h1 class="mt-4 text-4xl font-bold tracking-tight md:text-5xl">
          {{ landing?.content?.headline || 'Website sedang disiapkan' }}
        </h1>
        <p v-if="landing?.content?.subheadline" class="mt-2 text-sm text-slate-300/80">
          {{ landing.content.subheadline }}
        </p>
        <p class="mt-4 max-w-2xl text-sm leading-relaxed text-slate-300/90 md:text-base">
          {{ landing?.content?.body || `Halaman resmi untuk ${landing.name} segera hadir. Kami sedang menyiapkan konten layanan, katalog produk, dan informasi kontak agar lebih lengkap.` }}
        </p>

        <div class="mt-8 grid gap-3 text-sm md:grid-cols-2">
          <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
            <p class="text-slate-300/70">Domain</p>
            <p class="mt-1 font-mono text-xs">{{ landing.domain }}</p>
          </div>
          <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
            <p class="text-slate-300/70">Status</p>
            <p class="mt-1 font-semibold text-cyan-200">Coming Soon</p>
          </div>
        </div>

        <div v-if="landing?.content?.primary_cta_text || landing?.content?.secondary_cta_text || landing?.content?.contact_text" class="mt-8 space-y-3">
          <p v-if="landing?.content?.contact_text" class="text-sm text-slate-300/90">{{ landing.content.contact_text }}</p>
          <div class="flex flex-wrap gap-2">
            <a v-if="landing?.content?.primary_cta_text && landing?.content?.primary_cta_url" :href="landing.content.primary_cta_url" target="_blank" rel="noopener noreferrer" class="btn btn-primary btn-sm" @click="trackCtaClick('primary', landing.content.primary_cta_text, landing.content.primary_cta_url)">
              {{ landing.content.primary_cta_text }}
            </a>
            <a v-if="landing?.content?.secondary_cta_text && landing?.content?.secondary_cta_url" :href="landing.content.secondary_cta_url" target="_blank" rel="noopener noreferrer" class="btn btn-outline btn-sm border-white/30 text-slate-100 hover:bg-white/10" @click="trackCtaClick('secondary', landing.content.secondary_cta_text, landing.content.secondary_cta_url)">
              {{ landing.content.secondary_cta_text }}
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
