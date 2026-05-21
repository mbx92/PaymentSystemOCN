<script setup>
import { Head } from '@inertiajs/vue3';
import { RocketLaunchIcon } from '@heroicons/vue/24/outline';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useLandingTracking } from '@/composables/useLandingTracking';

const props = defineProps({
  landing: {
    type: Object,
    required: true,
  },
  countdownAt: {
    type: String,
    required: true,
  },
});

const { trackCtaClick } = useLandingTracking();

const nowTs = ref(Date.now());
let timer = null;

const targetTs = computed(() => {
  const parsed = Date.parse(props.countdownAt);
  return Number.isNaN(parsed) ? Date.now() : parsed;
});

const remainingMs = computed(() => Math.max(0, targetTs.value - nowTs.value));

const countdown = computed(() => {
  const totalSeconds = Math.floor(remainingMs.value / 1000);
  const days = Math.floor(totalSeconds / 86400);
  const hours = Math.floor((totalSeconds % 86400) / 3600);
  const minutes = Math.floor((totalSeconds % 3600) / 60);
  const seconds = totalSeconds % 60;

  return [
    { label: 'Hari', value: String(days).padStart(2, '0') },
    { label: 'Jam', value: String(hours).padStart(2, '0') },
    { label: 'Menit', value: String(minutes).padStart(2, '0') },
    { label: 'Detik', value: String(seconds).padStart(2, '0') },
  ];
});

const launchLabel = computed(() => new Intl.DateTimeFormat('id-ID', {
  dateStyle: 'full',
  timeStyle: 'short',
}).format(new Date(targetTs.value)));

const isLive = computed(() => remainingMs.value <= 0);

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
  <Head :title="landing?.content?.seo_title || `${landing?.name || 'OCNetworks'} — Launch Countdown`" />
  <div class="min-h-screen overflow-hidden bg-[#07111f] text-white">
    <div class="absolute inset-0">
      <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,_rgba(14,165,233,0.22),_transparent_30%),radial-gradient(circle_at_bottom_right,_rgba(34,197,94,0.18),_transparent_28%),linear-gradient(135deg,_#07111f_0%,_#0f172a_52%,_#081018_100%)]" />
      <div class="absolute inset-0 opacity-20 [background-image:linear-gradient(rgba(255,255,255,0.07)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.07)_1px,transparent_1px)] [background-size:32px_32px]" />
    </div>

    <div class="relative mx-auto flex min-h-screen w-full max-w-6xl items-center px-6 py-16">
      <section class="grid w-full gap-8 lg:grid-cols-[1.15fr_0.85fr] lg:items-center">
        <div>
          <p class="text-xs font-bold uppercase tracking-[0.22em] text-cyan-200/80">OCNetworks</p>
          <h1 class="mt-4 max-w-3xl text-4xl font-black tracking-tight text-white md:text-6xl">
            {{ landing?.content?.headline || 'Website resmi baru sedang menuju peluncuran.' }}
          </h1>
          <p class="mt-4 max-w-2xl text-base leading-7 text-slate-300 md:text-lg">
            {{ landing?.content?.body || 'Kami sedang menyiapkan halaman publik OCN dengan presentasi layanan, profil perusahaan, dan kanal kontak yang lebih rapi.' }}
          </p>

          <div class="mt-8 flex flex-wrap items-center gap-3 text-sm text-slate-300">
            <div class="rounded-full border border-white/10 bg-white/5 px-4 py-2">
              Host: <span class="font-mono text-cyan-200">{{ landing?.domain }}</span>
            </div>
            <div class="rounded-full border border-white/10 bg-white/5 px-4 py-2">
              Launch: <span class="font-semibold text-white">{{ launchLabel }}</span>
            </div>
          </div>

          <p v-if="landing?.content?.contact_text" class="mt-6 text-sm text-slate-300/90">
            {{ landing.content.contact_text }}
          </p>

          <div v-if="landing?.content?.primary_cta_text || landing?.content?.secondary_cta_text" class="mt-8 flex flex-wrap gap-3">
            <a
              v-if="landing?.content?.primary_cta_text && landing?.content?.primary_cta_url"
              :href="landing.content.primary_cta_url"
              target="_blank"
              rel="noopener noreferrer"
              class="btn btn-primary border-0 bg-cyan-400 text-slate-950 hover:bg-cyan-300"
              @click="trackCtaClick('primary', landing.content.primary_cta_text, landing.content.primary_cta_url)"
            >
              {{ landing.content.primary_cta_text }}
            </a>
            <a
              v-if="landing?.content?.secondary_cta_text && landing?.content?.secondary_cta_url"
              :href="landing.content.secondary_cta_url"
              target="_blank"
              rel="noopener noreferrer"
              class="btn btn-outline border-white/20 text-white hover:bg-white/10"
              @click="trackCtaClick('secondary', landing.content.secondary_cta_text, landing.content.secondary_cta_url)"
            >
              {{ landing.content.secondary_cta_text }}
            </a>
          </div>
        </div>

        <div class="rounded-[28px] border border-white/10 bg-white/8 p-5 shadow-2xl backdrop-blur-xl md:p-7">
          <div class="flex items-center justify-between gap-4">
            <div>
              <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-200/80">Countdown</p>
              <p class="mt-2 text-2xl font-bold text-white md:text-3xl">
                {{ isLive ? 'Sudah live' : (landing?.content?.subheadline || 'Menuju hari peluncuran') }}
              </p>
            </div>
            <div class="flex h-14 w-14 items-center justify-center rounded-2xl border border-cyan-300/30 bg-cyan-300/10 text-cyan-200 shadow-[0_0_24px_rgba(34,211,238,0.16)]">
              <RocketLaunchIcon class="h-7 w-7" />
            </div>
          </div>

          <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div
              v-for="item in countdown"
              :key="item.label"
              class="rounded-2xl border border-white/10 bg-slate-950/45 px-4 py-5 text-center"
            >
              <div class="text-3xl font-black tracking-tight text-white md:text-4xl">{{ item.value }}</div>
              <div class="mt-2 text-[11px] uppercase tracking-[0.2em] text-slate-400">{{ item.label }}</div>
            </div>
          </div>

          <div class="mt-6 rounded-2xl border border-white/10 bg-slate-950/35 p-4 text-sm leading-6 text-slate-300">
            <p v-if="isLive">
              Countdown selesai. Halaman ini siap diarahkan ke website utama kapan pun diperlukan.
            </p>
            <p v-else>
              Halaman countdown ini aktif khusus untuk domain publik dan akan terus berjalan sampai waktu peluncuran yang ditentukan.
            </p>
          </div>
        </div>
      </section>
    </div>
  </div>
</template>
