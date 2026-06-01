<script setup>
import { Head } from '@inertiajs/vue3';
import LandingBuilderRenderer from '@/Components/CMS/LandingBuilderRenderer.vue';

defineProps({
  landing: {
    type: Object,
    required: true,
  },
  document: {
    type: Object,
    required: true,
  },
  theme: {
    type: Object,
    default: () => ({}),
  },
  previewMode: {
    type: Boolean,
    default: false,
  },
  previewLabel: {
    type: String,
    default: null,
  },
});
</script>

<template>
  <Head :title="landing?.content?.seo_title || document?.seo?.title || landing?.name || 'Landing'" />
  <div>
    <div v-if="previewMode" class="sticky top-0 z-20 border-b border-amber-300 bg-amber-100 px-4 py-2 text-center text-xs font-bold uppercase tracking-[0.18em] text-amber-900">
      {{ previewLabel || 'Preview draft' }}
    </div>
    <LandingBuilderRenderer :landing="landing" :document="document" :theme="theme" :preview-mode="previewMode" />
  </div>
</template>
