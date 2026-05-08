<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
  setting: Object,
});

const form = useForm({
  app_name: props.setting?.app_name ?? 'OCN ERP Suite',
  app_tagline: props.setting?.app_tagline ?? 'Integrated Business Platform',
  app_logo: null,
  remove_logo: false,
});

const onFileChange = (event) => {
  form.app_logo = event.target.files?.[0] ?? null;
  if (form.app_logo) form.remove_logo = false;
};

const submit = () => {
  form.post(route('erp.admin.erp-settings.update'), {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => {
      form.app_logo = null;
      form.remove_logo = false;
    },
  });
};
</script>

<template>
  <Head title="Administration - ERP Setting" />
  <AppLayout>
    <div class="space-y-5">
      <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Administration Workspace</p>
        <div class="mt-2 flex items-center justify-between gap-3">
          <div>
            <h1 class="text-3xl font-bold tracking-tight">ERP Setting</h1>
            <p class="mt-2 text-sm text-base-content/70">Atur branding aplikasi: nama app, tagline, dan logo.</p>
          </div>
          <Link class="btn btn-ghost btn-sm" :href="route('erp.administration')">Back</Link>
        </div>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Identitas Aplikasi</h2>
          <p class="ocn-panel__desc">Perubahan akan tampil di sidebar ERP.</p>
        </div>
        <div class="card-body space-y-4">
          <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-2">
              <label class="label p-0"><span class="label-text">Nama Aplikasi</span></label>
              <input v-model="form.app_name" type="text" class="input input-bordered w-full" placeholder="OCN ERP Suite">
              <p v-if="form.errors.app_name" class="text-xs text-error">{{ form.errors.app_name }}</p>
            </div>
            <div class="space-y-2">
              <label class="label p-0"><span class="label-text">Tagline</span></label>
              <input v-model="form.app_tagline" type="text" class="input input-bordered w-full" placeholder="Integrated Business Platform">
              <p v-if="form.errors.app_tagline" class="text-xs text-error">{{ form.errors.app_tagline }}</p>
            </div>
          </div>

          <div class="grid gap-4 md:grid-cols-2">
            <div class="space-y-2">
              <label class="label p-0"><span class="label-text">Logo Aplikasi</span></label>
              <input type="file" accept="image/*" class="file-input file-input-bordered w-full" @change="onFileChange">
              <p class="text-xs text-base-content/60">Format gambar (png/jpg/webp), maksimal 2MB.</p>
              <p v-if="form.errors.app_logo" class="text-xs text-error">{{ form.errors.app_logo }}</p>
            </div>
            <div class="space-y-2">
              <label class="label p-0"><span class="label-text">Preview</span></label>
              <div class="flex min-h-24 items-center gap-3 rounded-xl border border-base-300 bg-base-100 p-3">
                <div v-if="setting?.app_logo_url && !form.remove_logo" class="h-12 w-12 overflow-hidden rounded-lg border border-base-300 bg-white">
                  <img :src="setting.app_logo_url" alt="Logo App" class="h-full w-full object-contain">
                </div>
                <div v-else class="flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10 text-xs font-bold text-primary">ERP</div>
                <div>
                  <p class="text-sm font-semibold">{{ form.app_name || 'OCN ERP Suite' }}</p>
                  <p class="text-xs text-base-content/60">{{ form.app_tagline || 'Integrated Business Platform' }}</p>
                </div>
              </div>
              <label class="label cursor-pointer justify-start gap-2 p-0">
                <input v-model="form.remove_logo" type="checkbox" class="checkbox checkbox-sm" :disabled="!setting?.app_logo_url">
                <span class="label-text">Hapus logo saat ini</span>
              </label>
            </div>
          </div>

          <div class="flex justify-end">
            <button class="btn btn-primary" :disabled="form.processing" @click="submit">
              Simpan ERP Setting
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

