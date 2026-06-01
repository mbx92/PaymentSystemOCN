<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useLandingTracking } from '@/composables/useLandingTracking';

const props = defineProps({
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
});

const themeVars = computed(() => ({
  '--landing-primary': props.theme?.primary || '#1d4ed8',
  '--landing-secondary': props.theme?.secondary || '#0f172a',
  '--landing-accent': props.theme?.accent || '#0ea5e9',
  '--landing-surface': props.theme?.surface || '#f8fafc',
  '--landing-surface-alt': props.theme?.surface_alt || '#e2e8f0',
  '--landing-text': props.theme?.text || '#0f172a',
  '--landing-muted': props.theme?.muted || '#475569',
  '--landing-success': props.theme?.success || '#15803d',
  '--landing-warning': props.theme?.warning || '#b45309',
  '--landing-danger': props.theme?.danger || '#b91c1c',
  '--landing-radius': props.theme?.radius || '24px',
  '--landing-shadow': props.theme?.shadow || '0 30px 80px rgba(15, 23, 42, 0.12)',
  '--landing-font-heading': props.theme?.font_heading || 'Figtree, sans-serif',
  '--landing-font-body': props.theme?.font_body || 'Figtree, sans-serif',
}));

const sections = computed(() => props.document?.sections ?? []);
const isFullWidth = computed(() => props.document?.settings?.full_width === true);

const tracking = props.previewMode ? { trackCtaClick: () => {} } : useLandingTracking();

const nowTs = ref(Date.now());
let timer = null;

const countdownParts = (targetAt) => {
  const parsed = Date.parse(targetAt || '');
  const targetTs = Number.isNaN(parsed) ? Date.now() : parsed;
  const totalSeconds = Math.max(0, Math.floor((targetTs - nowTs.value) / 1000));

  return [
    { label: 'Hari', value: String(Math.floor(totalSeconds / 86400)).padStart(2, '0') },
    { label: 'Jam', value: String(Math.floor((totalSeconds % 86400) / 3600)).padStart(2, '0') },
    { label: 'Menit', value: String(Math.floor((totalSeconds % 3600) / 60)).padStart(2, '0') },
    { label: 'Detik', value: String(totalSeconds % 60).padStart(2, '0') },
  ];
};

const asArray = (value) => (Array.isArray(value) ? value : []);
const sectionWidthClass = (section) => ({
  full: 'md:col-span-12',
  half: 'md:col-span-6',
  third: 'md:col-span-4',
  'two-thirds': 'md:col-span-8',
}[section?.layout?.width || 'full'] || 'md:col-span-12');

onMounted(() => {
  timer = window.setInterval(() => {
    nowTs.value = Date.now();
  }, 1000);
});

onBeforeUnmount(() => {
  if (timer) {
    window.clearInterval(timer);
  }
});
</script>

<template>
  <div class="landing-builder min-h-screen" :style="themeVars">
    <div class="landing-builder__bg"></div>

    <div class="relative">
      <header :class="['flex w-full items-center justify-between px-6 py-5', isFullWidth ? 'landing-builder__container--fluid' : 'landing-builder__container']">
        <div>
          <p class="text-xs font-bold uppercase tracking-[0.18em] text-[var(--landing-primary)]">Website CMS</p>
          <h1 class="text-xl font-black text-[var(--landing-text)]" style="font-family: var(--landing-font-heading)">
            {{ landing?.name }}
          </h1>
        </div>
        <div class="rounded-full border border-black/5 bg-white/70 px-4 py-2 text-xs text-[var(--landing-muted)] backdrop-blur">
          Host: <span class="font-mono">{{ landing?.domain }}</span>
        </div>
      </header>

      <main :class="['grid w-full grid-cols-1 gap-5 px-6 pb-16 md:grid-cols-12', isFullWidth ? 'landing-builder__container--fluid' : 'landing-builder__container']">
        <section
          v-for="section in sections"
          :key="section.id"
          class="landing-builder__section"
          :class="sectionWidthClass(section)"
          v-show="section?.visibility?.enabled !== false"
        >
          <template v-if="section.type === 'hero'">
            <div class="grid gap-6 lg:grid-cols-[1.1fr_0.9fr] lg:items-center">
              <div>
                <p v-if="section.props?.eyebrow" class="text-xs font-bold uppercase tracking-[0.22em] text-[var(--landing-primary)]">
                  {{ section.props.eyebrow }}
                </p>
                <h2 class="mt-3 text-4xl font-black tracking-tight text-[var(--landing-text)] md:text-5xl" style="font-family: var(--landing-font-heading)">
                  {{ section.props?.headline || landing?.name }}
                </h2>
                <p v-if="section.props?.subheadline" class="mt-3 text-sm font-semibold text-[var(--landing-muted)] md:text-base">
                  {{ section.props.subheadline }}
                </p>
                <p v-if="section.props?.body" class="mt-4 max-w-2xl text-sm leading-7 text-[var(--landing-muted)] md:text-base">
                  {{ section.props.body }}
                </p>
                <p v-if="section.props?.contact_text" class="mt-4 text-sm text-[var(--landing-muted)]">
                  {{ section.props.contact_text }}
                </p>
                <div v-if="section.props?.primary_cta_text || section.props?.secondary_cta_text" class="mt-7 flex flex-wrap gap-3">
                  <a
                    v-if="section.props?.primary_cta_text && section.props?.primary_cta_url"
                    :href="section.props.primary_cta_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="landing-builder__btn landing-builder__btn--primary"
                    @click="tracking.trackCtaClick('primary', section.props.primary_cta_text, section.props.primary_cta_url)"
                  >
                    {{ section.props.primary_cta_text }}
                  </a>
                  <a
                    v-if="section.props?.secondary_cta_text && section.props?.secondary_cta_url"
                    :href="section.props.secondary_cta_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="landing-builder__btn landing-builder__btn--secondary"
                    @click="tracking.trackCtaClick('secondary', section.props.secondary_cta_text, section.props.secondary_cta_url)"
                  >
                    {{ section.props.secondary_cta_text }}
                  </a>
                </div>
              </div>
              <div class="landing-builder__media-card">
                <img
                  v-if="section.assets?.hero_media_url"
                  :src="section.assets.hero_media_url"
                  alt=""
                  class="h-full min-h-[260px] w-full rounded-[calc(var(--landing-radius)-6px)] object-cover"
                />
                <div v-else class="flex min-h-[260px] items-center justify-center rounded-[calc(var(--landing-radius)-6px)] bg-[var(--landing-surface-alt)] text-center text-sm text-[var(--landing-muted)]">
                  Tambahkan hero image dari CMS media library
                </div>
              </div>
            </div>
          </template>

          <template v-else-if="section.type === 'text'">
            <div>
              <h3 v-if="section.props?.title" class="text-2xl font-black text-[var(--landing-text)]" style="font-family: var(--landing-font-heading)">
                {{ section.props.title }}
              </h3>
              <p v-if="section.props?.body" class="mt-3 whitespace-pre-line text-sm leading-7 text-[var(--landing-muted)] md:text-base">
                {{ section.props.body }}
              </p>
            </div>
          </template>

          <template v-else-if="section.type === 'cta_group'">
            <div class="rounded-[calc(var(--landing-radius)-4px)] bg-[var(--landing-secondary)] px-6 py-7 text-white">
              <h3 v-if="section.props?.title" class="text-2xl font-black">{{ section.props.title }}</h3>
              <p v-if="section.props?.body" class="mt-3 max-w-2xl text-sm leading-7 text-white/75">{{ section.props.body }}</p>
              <div class="mt-5 flex flex-wrap gap-3">
                <a
                  v-if="section.props?.primary_cta_text && section.props?.primary_cta_url"
                  :href="section.props.primary_cta_url"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="landing-builder__btn border-0 bg-white text-[var(--landing-secondary)]"
                  @click="tracking.trackCtaClick('primary', section.props.primary_cta_text, section.props.primary_cta_url)"
                >
                  {{ section.props.primary_cta_text }}
                </a>
                <a
                  v-if="section.props?.secondary_cta_text && section.props?.secondary_cta_url"
                  :href="section.props.secondary_cta_url"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="landing-builder__btn border border-white/20 bg-white/5 text-white"
                  @click="tracking.trackCtaClick('secondary', section.props.secondary_cta_text, section.props.secondary_cta_url)"
                >
                  {{ section.props.secondary_cta_text }}
                </a>
              </div>
            </div>
          </template>

          <template v-else-if="section.type === 'feature_list'">
            <div>
              <h3 v-if="section.props?.title" class="text-2xl font-black text-[var(--landing-text)]" style="font-family: var(--landing-font-heading)">
                {{ section.props.title }}
              </h3>
              <p v-if="section.props?.body" class="mt-3 text-sm leading-7 text-[var(--landing-muted)]">{{ section.props.body }}</p>
              <div class="mt-5 grid gap-3 md:grid-cols-2">
                <div
                  v-for="(item, idx) in asArray(section.props?.items)"
                  :key="`${section.id}-${idx}`"
                  class="rounded-2xl border border-black/5 bg-white/70 p-4"
                >
                  <p class="text-sm font-semibold text-[var(--landing-text)]">{{ item }}</p>
                </div>
              </div>
            </div>
          </template>

          <template v-else-if="section.type === 'image_gallery'">
            <div>
              <h3 v-if="section.props?.title" class="text-2xl font-black text-[var(--landing-text)]" style="font-family: var(--landing-font-heading)">
                {{ section.props.title }}
              </h3>
              <p v-if="section.props?.body" class="mt-3 text-sm leading-7 text-[var(--landing-muted)]">{{ section.props.body }}</p>
              <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                <div
                  v-for="(item, idx) in asArray(section.props?.images)"
                  :key="`${section.id}-image-${idx}`"
                  class="overflow-hidden rounded-2xl border border-black/5 bg-white/70"
                >
                  <img :src="item.url" :alt="item.alt || ''" class="h-48 w-full object-cover" />
                  <p v-if="item.alt" class="px-4 py-3 text-xs text-[var(--landing-muted)]">{{ item.alt }}</p>
                </div>
              </div>
            </div>
          </template>

          <template v-else-if="section.type === 'contact_card'">
            <div class="rounded-[calc(var(--landing-radius)-4px)] border border-black/5 bg-white/70 p-6">
              <h3 v-if="section.props?.title" class="text-2xl font-black text-[var(--landing-text)]" style="font-family: var(--landing-font-heading)">
                {{ section.props.title }}
              </h3>
              <p v-if="section.props?.body" class="mt-3 whitespace-pre-line text-sm leading-7 text-[var(--landing-muted)]">{{ section.props.body }}</p>
              <div class="mt-5 flex flex-wrap gap-3">
                <a
                  v-if="section.props?.primary_cta_text && section.props?.primary_cta_url"
                  :href="section.props.primary_cta_url"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="landing-builder__btn landing-builder__btn--primary"
                  @click="tracking.trackCtaClick('primary', section.props.primary_cta_text, section.props.primary_cta_url)"
                >
                  {{ section.props.primary_cta_text }}
                </a>
                <a
                  v-if="section.props?.secondary_cta_text && section.props?.secondary_cta_url"
                  :href="section.props.secondary_cta_url"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="landing-builder__btn landing-builder__btn--secondary"
                  @click="tracking.trackCtaClick('secondary', section.props.secondary_cta_text, section.props.secondary_cta_url)"
                >
                  {{ section.props.secondary_cta_text }}
                </a>
              </div>
            </div>
          </template>

          <template v-else-if="section.type === 'countdown'">
            <div class="rounded-[calc(var(--landing-radius)-4px)] bg-[var(--landing-secondary)] px-6 py-7 text-white">
              <div class="flex items-start justify-between gap-4">
                <div>
                  <p class="text-xs font-bold uppercase tracking-[0.22em] text-white/70">Countdown</p>
                  <h3 class="mt-2 text-2xl font-black">{{ section.props?.title || 'Menuju peluncuran' }}</h3>
                  <p v-if="section.props?.subtitle" class="mt-2 text-sm text-white/70">{{ section.props.subtitle }}</p>
                </div>
                <div class="rounded-full bg-white/10 px-3 py-1 text-xs font-semibold">
                  {{ section.props?.target_at ? new Date(section.props.target_at).toLocaleString('id-ID') : 'Belum diatur' }}
                </div>
              </div>
              <div class="mt-6 grid grid-cols-2 gap-3 md:grid-cols-4">
                <div v-for="part in countdownParts(section.props?.target_at)" :key="part.label" class="rounded-2xl bg-white/8 px-4 py-5 text-center">
                  <div class="text-3xl font-black">{{ part.value }}</div>
                  <div class="mt-1 text-[11px] uppercase tracking-[0.2em] text-white/65">{{ part.label }}</div>
                </div>
              </div>
            </div>
          </template>

          <template v-else-if="section.type === 'warehouse_highlight'">
            <div>
              <h3 v-if="section.props?.title" class="text-2xl font-black text-[var(--landing-text)]" style="font-family: var(--landing-font-heading)">
                {{ section.props.title }}
              </h3>
              <p v-if="section.props?.body" class="mt-3 text-sm leading-7 text-[var(--landing-muted)]">{{ section.props.body }}</p>
              <div class="mt-5 grid gap-3 md:grid-cols-2">
                <div v-if="section.props?.show_domain !== false" class="rounded-2xl border border-black/5 bg-white/70 p-4">
                  <p class="text-xs uppercase tracking-[0.16em] text-[var(--landing-muted)]">Domain</p>
                  <p class="mt-2 font-mono text-sm text-[var(--landing-text)]">{{ landing?.domain }}</p>
                </div>
                <div v-if="section.props?.show_warehouse !== false" class="rounded-2xl border border-black/5 bg-white/70 p-4">
                  <p class="text-xs uppercase tracking-[0.16em] text-[var(--landing-muted)]">Warehouse</p>
                  <p class="mt-2 text-sm font-semibold text-[var(--landing-text)]">
                    {{ landing?.warehouse ? `${landing.warehouse.code} - ${landing.warehouse.name}` : 'Belum ditentukan' }}
                  </p>
                </div>
              </div>
            </div>
          </template>
        </section>
      </main>
    </div>
  </div>
</template>

<style scoped>
.landing-builder {
  background:
    radial-gradient(circle at top left, color-mix(in srgb, var(--landing-primary) 14%, transparent), transparent 28rem),
    radial-gradient(circle at bottom right, color-mix(in srgb, var(--landing-accent) 14%, transparent), transparent 24rem),
    linear-gradient(180deg, var(--landing-surface) 0%, color-mix(in srgb, var(--landing-surface-alt) 55%, white) 100%);
  color: var(--landing-text);
  font-family: var(--landing-font-body);
}

.landing-builder__bg {
  position: absolute;
  inset: 0;
  opacity: 0.28;
  background-image:
    linear-gradient(rgba(255,255,255,0.5) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255,255,255,0.45) 1px, transparent 1px);
  background-size: 34px 34px;
  pointer-events: none;
}

.landing-builder__section {
  position: relative;
  overflow: hidden;
  border: 1px solid rgba(15, 23, 42, 0.06);
  background: rgba(255, 255, 255, 0.78);
  backdrop-filter: blur(12px);
  border-radius: var(--landing-radius);
  box-shadow: var(--landing-shadow);
  padding: 1.5rem;
}

.landing-builder__container {
  margin-inline: auto;
  max-width: 72rem;
}

.landing-builder__container--fluid {
  margin-inline: auto;
  max-width: min(100%, 1440px);
}

.landing-builder__btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 2.75rem;
  border-radius: 999px;
  padding: 0.75rem 1rem;
  font-size: 0.875rem;
  font-weight: 700;
  transition: transform 120ms ease, opacity 120ms ease;
}

.landing-builder__btn:hover {
  transform: translateY(-1px);
  opacity: 0.95;
}

.landing-builder__btn--primary {
  background: var(--landing-primary);
  color: white;
}

.landing-builder__btn--secondary {
  border: 1px solid rgba(15, 23, 42, 0.1);
  background: white;
  color: var(--landing-secondary);
}

.landing-builder__media-card {
  min-height: 260px;
  border-radius: calc(var(--landing-radius) - 4px);
  background: linear-gradient(135deg, color-mix(in srgb, var(--landing-primary) 14%, white), color-mix(in srgb, var(--landing-accent) 16%, white));
  padding: 0.4rem;
}
</style>
