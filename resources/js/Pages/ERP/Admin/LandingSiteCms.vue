<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
  landingSite: Object,
  pageContent: Object,
  cmsModule: { type: Boolean, default: false },
});

const form = useForm({
  headline: props.pageContent?.headline ?? '',
  subheadline: props.pageContent?.subheadline ?? '',
  body: props.pageContent?.body ?? '',
  countdown_at: props.pageContent?.countdown_at ?? '',
  primary_cta_text: props.pageContent?.primary_cta_text ?? '',
  primary_cta_url: props.pageContent?.primary_cta_url ?? '',
  secondary_cta_text: props.pageContent?.secondary_cta_text ?? '',
  secondary_cta_url: props.pageContent?.secondary_cta_url ?? '',
  contact_text: props.pageContent?.contact_text ?? '',
  seo_title: props.pageContent?.seo_title ?? '',
  seo_description: props.pageContent?.seo_description ?? '',
  is_published: props.pageContent?.is_published !== false,
});

const submit = () => {
  form.post(route('erp.admin.landing-sites.cms.update', props.landingSite.id), {
    preserveScroll: true,
  });
};
</script>

<template>
  <Head title="Administration - Landing CMS" />
  <AppLayout>
    <div class="space-y-5">
      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">{{ cmsModule ? 'Website CMS' : 'Administration Workspace' }}</p>
              <h1 class="ocn-panel__title mt-1">Landing CMS</h1>
              <p class="ocn-panel__desc mt-1">
              Domain: <span class="font-mono text-xs">{{ landingSite?.domain }}</span>
            </p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
              <Link class="btn btn-ghost btn-sm shrink-0 gap-1.5" :href="cmsModule ? route('erp.cms.sites') : route('erp.admin.landing-sites')">
              <ArrowLeftIcon class="h-4 w-4" />
              Back
            </Link>
            </div>
          </div>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Konten halaman</h2>
          <p class="ocn-panel__desc">Atur judul, deskripsi, CTA, dan SEO untuk landing domain ini.</p>
        </div>
        <div class="card-body space-y-4">
          <div class="grid gap-4 md:grid-cols-2">
            <div>
              <label class="label"><span class="label-text">Headline</span></label>
              <input v-model="form.headline" type="text" class="input input-bordered w-full" placeholder="Judul utama landing" />
              <p v-if="form.errors.headline" class="text-xs text-error mt-1">{{ form.errors.headline }}</p>
            </div>
            <div>
              <label class="label"><span class="label-text">Subheadline</span></label>
              <input v-model="form.subheadline" type="text" class="input input-bordered w-full" placeholder="Teks pendek di bawah headline" />
              <p v-if="form.errors.subheadline" class="text-xs text-error mt-1">{{ form.errors.subheadline }}</p>
            </div>
          </div>

          <div>
            <label class="label"><span class="label-text">Body</span></label>
            <textarea v-model="form.body" class="textarea textarea-bordered w-full" rows="4" placeholder="Deskripsi utama landing" />
            <p v-if="form.errors.body" class="text-xs text-error mt-1">{{ form.errors.body }}</p>
          </div>

          <div>
            <label class="label"><span class="label-text">Countdown At</span></label>
            <input v-model="form.countdown_at" type="datetime-local" class="input input-bordered w-full" />
            <p class="mt-1 text-xs text-base-content/60">Dipakai saat layout landing memakai mode countdown. Kosongkan untuk memakai default global aplikasi.</p>
            <p v-if="form.errors.countdown_at" class="text-xs text-error mt-1">{{ form.errors.countdown_at }}</p>
          </div>

          <div class="grid gap-4 md:grid-cols-2">
            <div>
              <label class="label"><span class="label-text">Primary CTA Text</span></label>
              <input v-model="form.primary_cta_text" type="text" class="input input-bordered w-full" placeholder="Contoh: Hubungi Kami" />
              <p v-if="form.errors.primary_cta_text" class="text-xs text-error mt-1">{{ form.errors.primary_cta_text }}</p>
            </div>
            <div>
              <label class="label"><span class="label-text">Primary CTA URL</span></label>
              <input v-model="form.primary_cta_url" type="text" class="input input-bordered w-full" placeholder="https://..." />
              <p v-if="form.errors.primary_cta_url" class="text-xs text-error mt-1">{{ form.errors.primary_cta_url }}</p>
            </div>
            <div>
              <label class="label"><span class="label-text">Secondary CTA Text</span></label>
              <input v-model="form.secondary_cta_text" type="text" class="input input-bordered w-full" placeholder="Contoh: Lihat Katalog" />
              <p v-if="form.errors.secondary_cta_text" class="text-xs text-error mt-1">{{ form.errors.secondary_cta_text }}</p>
            </div>
            <div>
              <label class="label"><span class="label-text">Secondary CTA URL</span></label>
              <input v-model="form.secondary_cta_url" type="text" class="input input-bordered w-full" placeholder="https://..." />
              <p v-if="form.errors.secondary_cta_url" class="text-xs text-error mt-1">{{ form.errors.secondary_cta_url }}</p>
            </div>
          </div>

          <div>
            <label class="label"><span class="label-text">Contact Text</span></label>
            <input v-model="form.contact_text" type="text" class="input input-bordered w-full" placeholder="Contoh: WhatsApp 0812xxxxxxx" />
            <p v-if="form.errors.contact_text" class="text-xs text-error mt-1">{{ form.errors.contact_text }}</p>
          </div>

          <div class="grid gap-4 md:grid-cols-2">
            <div>
              <label class="label"><span class="label-text">SEO Title</span></label>
              <input v-model="form.seo_title" type="text" class="input input-bordered w-full" placeholder="Judul tab browser" />
              <p v-if="form.errors.seo_title" class="text-xs text-error mt-1">{{ form.errors.seo_title }}</p>
            </div>
            <div>
              <label class="label"><span class="label-text">SEO Description</span></label>
              <input v-model="form.seo_description" type="text" class="input input-bordered w-full" placeholder="Deskripsi untuk search engine" />
              <p v-if="form.errors.seo_description" class="text-xs text-error mt-1">{{ form.errors.seo_description }}</p>
            </div>
          </div>

          <label class="label cursor-pointer justify-start gap-3">
            <input v-model="form.is_published" type="checkbox" class="toggle toggle-success" />
            <span class="label-text">Publish konten CMS ini ke landing page</span>
          </label>

          <div class="flex justify-end">
            <button class="btn btn-primary" :disabled="form.processing" @click="submit">
              {{ form.processing ? 'Menyimpan...' : 'Simpan Konten' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
