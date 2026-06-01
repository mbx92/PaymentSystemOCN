<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import LandingBuilderRenderer from '@/Components/CMS/LandingBuilderRenderer.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
  ArrowLeftIcon,
  ArrowsUpDownIcon,
  PencilSquareIcon,
  DocumentDuplicateIcon,
  TrashIcon,
} from '@heroicons/vue/24/outline';
import { computed, ref, watch } from 'vue';

const props = defineProps({
  landingSite: Object,
  pageContent: Object,
  cmsModule: { type: Boolean, default: false },
  availableTemplates: { type: Array, default: () => [] },
  availableThemes: { type: Array, default: () => [] },
  draftVersion: { type: Object, required: true },
  publishedVersion: { type: Object, default: null },
  mediaLibrarySummary: { type: Array, default: () => [] },
  previewUrl: { type: String, required: true },
});

const tabs = [
  { key: 'structure', label: 'Structure' },
  { key: 'content', label: 'Content' },
  { key: 'design', label: 'Design' },
  { key: 'seo', label: 'SEO' },
  { key: 'preview', label: 'Preview' },
];

const activeTab = ref('structure');
const previewDevice = ref('desktop');
const saveTemplateDialog = ref(null);
const saveTemplateName = ref('');
const saveTemplateScope = ref('private');
const saveTemplateErrors = ref({});
const saveTemplateProcessing = ref(false);

const clone = (value) => JSON.parse(JSON.stringify(value));
const startingDocument = clone(props.draftVersion?.document || { seo: {}, sections: [] });

const form = useForm({
  template_key: props.draftVersion?.template_key || props.availableTemplates?.find((item) => item.family_layout_key === props.landingSite?.layout_key)?.key || '',
  theme_key: props.draftVersion?.theme_key || props.availableThemes?.[0]?.key || '',
  settings: {
    full_width: startingDocument?.settings?.full_width === true,
  },
  theme_overrides: clone(props.draftVersion?.theme_overrides || {}),
  seo: {
    title: startingDocument?.seo?.title || props.pageContent?.seo_title || '',
    description: startingDocument?.seo?.description || props.pageContent?.seo_description || '',
  },
  sections: clone(startingDocument?.sections || []),
});

const selectedSectionId = ref(form.sections[0]?.id || null);
const draggedItem = ref(null);
const dragOverIndex = ref(null);

watch(
  () => form.sections.map((section) => section.id),
  (ids) => {
    if (!ids.length) {
      selectedSectionId.value = null;
      return;
    }

    if (!ids.includes(selectedSectionId.value)) {
      selectedSectionId.value = ids[0];
    }
  },
  { deep: true }
);

const familyTemplates = computed(() =>
  (props.availableTemplates || []).filter((item) =>
    item.family_layout_key === props.landingSite?.layout_key || item.scope !== 'system'
  )
);

const selectedThemePreset = computed(() =>
  (props.availableThemes || []).find((item) => item.key === form.theme_key) || props.availableThemes?.[0] || { tokens: {} }
);

const mergedPreviewTheme = computed(() => ({
  ...(selectedThemePreset.value?.tokens || {}),
  ...(form.theme_overrides || {}),
}));

const previewDocument = computed(() => ({
  template_key: form.template_key,
  theme_key: form.theme_key,
  settings: form.settings,
  seo: form.seo,
  sections: form.sections,
}));

const previewLanding = computed(() => ({
  name: props.landingSite?.name,
  domain: props.landingSite?.domain,
  layout_key: props.landingSite?.layout_key,
  warehouse: props.landingSite?.warehouse,
  content: {
    seo_title: form.seo.title,
    seo_description: form.seo.description,
  },
}));

const selectedSection = computed(() =>
  form.sections.find((section) => section.id === selectedSectionId.value) || null
);

const mediaOptions = computed(() => props.mediaLibrarySummary || []);

const buildSection = (type) => {
  const base = {
    id: `${type}-${Math.random().toString(36).slice(2, 8)}`,
    type,
    layout: { width: 'full', variant: 'default' },
    visibility: { enabled: true },
    props: {},
    assets: {},
  };

  switch (type) {
    case 'hero':
      return {
        ...base,
        layout: { width: 'full', variant: 'split' },
        props: {
          eyebrow: 'OCNetworks',
          headline: 'Headline utama landing',
          subheadline: 'Subheadline singkat yang menjelaskan value utama.',
          body: 'Jelaskan layanan, produk, atau penawaran utama Anda di sini.',
          contact_text: '',
          primary_cta_text: 'Hubungi Kami',
          primary_cta_url: 'https://wa.me/',
          secondary_cta_text: 'Lihat Penawaran',
          secondary_cta_url: 'https://',
        },
        assets: { hero_media_url: '' },
      };
    case 'text':
      return {
        ...base,
        props: {
          title: 'Section title',
          body: 'Konten teks pendukung dalam beberapa paragraf singkat.',
        },
      };
    case 'cta_group':
      return {
        ...base,
        props: {
          title: 'Siap lanjut?',
          body: 'Arahkan pengunjung ke langkah berikutnya.',
          primary_cta_text: 'Hubungi sekarang',
          primary_cta_url: 'https://wa.me/',
          secondary_cta_text: 'Pelajari dulu',
          secondary_cta_url: 'https://',
        },
      };
    case 'feature_list':
      return {
        ...base,
        props: {
          title: 'Keunggulan utama',
          body: 'Sorot 3-6 poin paling penting.',
          items: ['Respons cepat', 'Tim berpengalaman', 'Harga transparan'],
        },
      };
    case 'image_gallery':
      return {
        ...base,
        props: {
          title: 'Galeri',
          body: 'Tampilkan dokumentasi, produk, atau hasil project.',
          images: [],
        },
      };
    case 'contact_card':
      return {
        ...base,
        props: {
          title: 'Hubungi tim kami',
          body: 'Sertakan jam operasional, kontak, atau CTA langsung.',
          primary_cta_text: 'Chat WhatsApp',
          primary_cta_url: 'https://wa.me/',
          secondary_cta_text: '',
          secondary_cta_url: '',
        },
      };
    case 'countdown':
      return {
        ...base,
        props: {
          title: 'Menuju peluncuran',
          subtitle: 'Hitung mundur ke tanggal penting.',
          target_at: props.pageContent?.countdown_at ? new Date(props.pageContent.countdown_at).toISOString() : '',
        },
      };
    default:
      return {
        ...base,
        props: {
          title: 'Info bisnis',
          body: 'Tampilkan domain dan warehouse jika relevan.',
          show_domain: true,
          show_warehouse: true,
        },
      };
  }
};

const sectionCatalog = [
  { type: 'hero', label: 'Hero', description: 'Headline, subheadline, media, CTA utama.' },
  { type: 'text', label: 'Text', description: 'Teks bebas untuk penjelasan singkat.' },
  { type: 'cta_group', label: 'CTA Group', description: 'Ajakan aksi dengan tombol utama dan sekunder.' },
  { type: 'feature_list', label: 'Feature List', description: 'Daftar keunggulan atau poin layanan.' },
  { type: 'image_gallery', label: 'Image Gallery', description: 'Grid visual untuk foto produk atau proyek.' },
  { type: 'contact_card', label: 'Contact Card', description: 'Kontak dan CTA untuk konversi cepat.' },
  { type: 'countdown', label: 'Countdown', description: 'Hitung mundur untuk promo atau peluncuran.' },
  { type: 'warehouse_highlight', label: 'Warehouse Highlight', description: 'Info domain, gudang, dan profil bisnis.' },
];

const sectionCatalogMap = Object.fromEntries(sectionCatalog.map((item) => [item.type, item]));

const sectionLabel = (section) =>
  section?.props?.headline || section?.props?.title || sectionCatalogMap[section?.type]?.label || section?.id || 'Section';

const sectionSummary = (section) => {
  if (!section) return '';

  if (section.type === 'hero') {
    return section.props?.subheadline || section.props?.body || 'Hero banner utama.';
  }

  if (section.type === 'feature_list') {
    return Array.isArray(section.props?.items) && section.props.items.length
      ? `${section.props.items.length} item keunggulan`
      : 'Belum ada item fitur.';
  }

  if (section.type === 'image_gallery') {
    return Array.isArray(section.props?.images) && section.props.images.length
      ? `${section.props.images.length} gambar terpasang`
      : 'Galeri masih kosong.';
  }

  return section.props?.body || section.props?.subtitle || 'Atur konten section ini dari panel Content.';
};

const addSection = (type) => {
  const section = buildSection(type);
  form.sections.push(section);
  selectedSectionId.value = section.id;
  activeTab.value = 'content';
};

const insertSectionAt = (type, index) => {
  const section = buildSection(type);
  const safeIndex = Math.max(0, Math.min(index, form.sections.length));
  form.sections.splice(safeIndex, 0, section);
  selectedSectionId.value = section.id;
  activeTab.value = 'content';
};

const removeSection = (id) => {
  const idx = form.sections.findIndex((section) => section.id === id);
  if (idx >= 0) {
    form.sections.splice(idx, 1);
  }
};

const duplicateSection = (id) => {
  const idx = form.sections.findIndex((section) => section.id === id);
  if (idx < 0) return;

  const duplicated = clone(form.sections[idx]);
  duplicated.id = `${duplicated.type}-${Math.random().toString(36).slice(2, 8)}`;
  form.sections.splice(idx + 1, 0, duplicated);
  selectedSectionId.value = duplicated.id;
};

const moveSection = (id, dir) => {
  const idx = form.sections.findIndex((section) => section.id === id);
  const target = idx + dir;
  if (idx < 0 || target < 0 || target >= form.sections.length) return;

  const [item] = form.sections.splice(idx, 1);
  form.sections.splice(target, 0, item);
};

const selectSection = (id, nextTab = null) => {
  selectedSectionId.value = id;
  if (nextTab) {
    activeTab.value = nextTab;
  }
};

const startLibraryDrag = (type, event) => {
  draggedItem.value = { kind: 'library', type };
  activeTab.value = 'structure';
  event.dataTransfer.effectAllowed = 'copy';
  event.dataTransfer.setData('text/plain', JSON.stringify(draggedItem.value));
};

const startSectionDrag = (id, event) => {
  draggedItem.value = { kind: 'section', id };
  selectSection(id, 'structure');
  event.dataTransfer.effectAllowed = 'move';
  event.dataTransfer.setData('text/plain', JSON.stringify(draggedItem.value));
};

const resolveDraggedItem = (event) => {
  if (draggedItem.value) {
    return draggedItem.value;
  }

  try {
    const payload = JSON.parse(event.dataTransfer.getData('text/plain'));
    return payload && typeof payload === 'object' ? payload : null;
  } catch {
    return null;
  }
};

const allowCanvasDrop = (index, event) => {
  event.preventDefault();
  dragOverIndex.value = index;

  const payload = resolveDraggedItem(event);
  event.dataTransfer.dropEffect = payload?.kind === 'library' ? 'copy' : 'move';
};

const handleCanvasDrop = (index, event) => {
  event.preventDefault();

  const payload = resolveDraggedItem(event);
  if (!payload) {
    clearDragState();
    return;
  }

  if (payload.kind === 'library' && payload.type) {
    insertSectionAt(payload.type, index);
    clearDragState();
    return;
  }

  if (payload.kind === 'section' && payload.id) {
    const currentIndex = form.sections.findIndex((section) => section.id === payload.id);
    if (currentIndex < 0) {
      clearDragState();
      return;
    }

    const [section] = form.sections.splice(currentIndex, 1);
    const targetIndex = currentIndex < index ? index - 1 : index;
    form.sections.splice(Math.max(0, targetIndex), 0, section);
    selectedSectionId.value = section.id;
  }

  clearDragState();
};

const clearDragState = () => {
  draggedItem.value = null;
  dragOverIndex.value = null;
};

const setFeatureItems = (value) => {
  if (!selectedSection.value) return;
  selectedSection.value.props.items = value
    .split('\n')
    .map((item) => item.trim())
    .filter(Boolean);
};

const featureItemsText = computed({
  get: () => (selectedSection.value?.props?.items || []).join('\n'),
  set: setFeatureItems,
});

const gallerySelectId = ref('');

const addGalleryImage = () => {
  if (!selectedSection.value || selectedSection.value.type !== 'image_gallery') return;
  const media = mediaOptions.value.find((item) => String(item.id) === String(gallerySelectId.value));
  if (!media) return;

  if (!Array.isArray(selectedSection.value.props.images)) {
    selectedSection.value.props.images = [];
  }

  selectedSection.value.props.images.push({
    url: media.public_url || media.url,
    alt: media.alt_text || media.name,
  });
  gallerySelectId.value = '';
};

const removeGalleryImage = (idx) => {
  if (!selectedSection.value || selectedSection.value.type !== 'image_gallery') return;
  selectedSection.value.props.images.splice(idx, 1);
};

const assignHeroMedia = (mediaId) => {
  if (!selectedSection.value || selectedSection.value.type !== 'hero') return;
  const media = mediaOptions.value.find((item) => String(item.id) === String(mediaId));
  selectedSection.value.assets.hero_media_url = media ? (media.public_url || media.url) : '';
};

const saveDraft = () => {
  form.post(route('erp.admin.landing-sites.cms.update', props.landingSite.id), {
    preserveScroll: true,
  });
};

const publishDraft = () => {
  router.post(route('erp.admin.landing-sites.cms.publish', props.landingSite.id), {}, {
    preserveScroll: true,
  });
};

const openPreview = () => {
  window.open(props.previewUrl, '_blank', 'noopener,noreferrer');
};

const openSaveTemplateModal = () => {
  saveTemplateName.value = '';
  saveTemplateScope.value = 'private';
  saveTemplateErrors.value = {};
  saveTemplateDialog.value?.showModal();
};

const closeSaveTemplateModal = () => {
  saveTemplateDialog.value?.close();
};

const saveAsTemplate = () => {
  if (!saveTemplateName.value.trim()) {
    saveTemplateErrors.value = { name: 'Nama template wajib diisi.' };
    return;
  }

  saveTemplateProcessing.value = true;
  saveTemplateErrors.value = {};
  router.post(route('erp.admin.landing-sites.cms.templates.store', props.landingSite.id), {
    name: saveTemplateName.value.trim(),
    scope: saveTemplateScope.value,
  }, {
    preserveScroll: true,
    onSuccess: () => {
      closeSaveTemplateModal();
    },
    onError: (errors) => {
      saveTemplateErrors.value = errors;
    },
    onFinish: () => {
      saveTemplateProcessing.value = false;
    },
  });
};

const previewFrameClass = computed(() =>
  previewDevice.value === 'mobile' ? 'mx-auto max-w-[420px]' : 'w-full'
);

const activeTabMeta = computed(() =>
  tabs.find((tab) => tab.key === activeTab.value) || tabs[0]
);

const canvasSections = computed(() => form.sections || []);

const selectedSectionIndex = computed(() =>
  form.sections.findIndex((section) => section.id === selectedSectionId.value)
);
</script>

<template>
  <Head title="Administration - Landing CMS Builder" />
  <AppLayout :full-width="true">
    <div class="cms-builder-page space-y-5">
      <header class="ocn-panel cms-builder-page__header">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">{{ cmsModule ? 'Website CMS' : 'Administration Workspace' }}</p>
              <h1 class="ocn-panel__title mt-1">Landing CMS Builder</h1>
              <p class="ocn-panel__desc mt-1">
                Domain: <span class="font-mono text-xs">{{ landingSite?.domain }}</span>
                <span class="mx-2">·</span>
                Family: <span class="font-semibold">{{ landingSite?.layout_key }}</span>
              </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <Link class="btn btn-ghost btn-sm gap-1.5" :href="cmsModule ? route('erp.cms.sites') : route('erp.admin.landing-sites')">
                <ArrowLeftIcon class="h-4 w-4" />
                Back
              </Link>
              <button class="btn btn-outline btn-sm" @click="openSaveTemplateModal">Save as Template</button>
              <button class="btn btn-outline btn-sm" @click="openPreview">Open Preview</button>
              <button class="btn btn-primary btn-sm" :disabled="form.processing" @click="saveDraft">
                {{ form.processing ? 'Saving...' : 'Save Draft' }}
              </button>
              <button class="btn btn-success btn-sm" @click="publishDraft">Publish</button>
            </div>
          </div>
        </div>
      </header>

      <main class="cms-builder-layout">
        <aside class="ocn-panel cms-builder-layout__sidebar">
          <div class="ocn-panel__head">
            <h2 class="ocn-panel__title">Structure</h2>
            <p class="ocn-panel__desc">Pilih, urutkan, dan tambah section.</p>
          </div>
          <div class="card-body space-y-4">
            <section class="space-y-3">
              <div class="cms-builder-block__label">Section Library</div>
              <div class="grid gap-2">
                <button
                  v-for="item in sectionCatalog"
                  :key="item.type"
                  class="cms-builder-library-item"
                  draggable="true"
                  @dragstart="startLibraryDrag(item.type, $event)"
                  @dragend="clearDragState"
                  @click="addSection(item.type)"
                >
                  <span class="cms-builder-library-item__title">+ {{ item.label }}</span>
                  <span class="cms-builder-library-item__desc">{{ item.description }}</span>
                </button>
              </div>
            </section>

            <section class="space-y-3">
              <div class="cms-builder-block__label">Page Structure</div>
              <div class="space-y-2">
              <button
                v-for="(section, index) in form.sections"
                :key="section.id"
                class="cms-builder-section-chip w-full rounded-2xl border p-3 text-left transition"
                :class="selectedSectionId === section.id ? 'border-primary bg-primary/6' : 'border-base-300 bg-white hover:border-primary/30'"
                draggable="true"
                @dragstart="startSectionDrag(section.id, $event)"
                @dragend="clearDragState"
                @click="selectSection(section.id)"
              >
                <div class="flex items-start justify-between gap-2 overflow-hidden">
                  <div class="min-w-0 flex-1">
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-base-content/50">{{ section.type }}</p>
                    <p class="mt-1 truncate text-sm font-semibold text-base-content">{{ sectionLabel(section) }}</p>
                    <p class="mt-1 text-[11px] text-base-content/60">#{{ index + 1 }} · {{ section.layout?.width || 'full' }}</p>
                  </div>
                  <span class="cms-builder-badge badge badge-outline badge-sm shrink-0">{{ section.visibility?.enabled === false ? 'hidden' : 'show' }}</span>
                </div>
              </button>
              </div>
            </section>
          </div>
        </aside>

        <section class="cms-builder-layout__workspace">
          <article class="ocn-panel cms-builder-workspace">
            <div class="card-body pb-0">
              <div class="cms-builder-workspace__bar">
                <div class="tabs tabs-boxed w-fit">
                <button
                  v-for="tab in tabs"
                  :key="tab.key"
                  class="tab"
                  :class="{ 'tab-active': activeTab === tab.key }"
                  @click="activeTab = tab.key"
                >
                  {{ tab.label }}
                </button>
                </div>
                <div class="cms-builder-workspace__status min-w-0">
                  <span class="cms-builder-badge badge badge-outline badge-sm">{{ activeTabMeta.label }}</span>
                  <span class="truncate text-xs text-base-content/50">Draft v{{ draftVersion?.version_no }}</span>
                </div>
              </div>
            </div>

            <section v-if="activeTab === 'structure'" class="card-body space-y-4">
              <section class="space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-3">
                  <div>
                    <div class="cms-builder-block__label">Drag & Drop Canvas</div>
                    <p class="mt-1 text-sm text-base-content/60">Tarik section dari library ke canvas, atau pindahkan antar section langsung di area ini.</p>
                  </div>
                  <span class="cms-builder-badge badge badge-outline badge-sm">{{ canvasSections.length }} sections</span>
                </div>
                <div class="cms-builder-canvas">
                  <div v-if="!canvasSections.length" class="cms-builder-empty-state">
                    <div class="cms-builder-empty-state__icon">
                      <ArrowsUpDownIcon class="h-5 w-5" />
                    </div>
                    <div>
                      <p class="text-sm font-semibold text-base-content">Canvas masih kosong</p>
                      <p class="mt-1 text-sm text-base-content/60">Klik atau drag section dari panel kiri untuk mulai menyusun layout landing page.</p>
                    </div>
                  </div>

                  <div
                    class="cms-builder-dropzone"
                    :class="{ 'cms-builder-dropzone--active': dragOverIndex === 0 }"
                    @dragover="allowCanvasDrop(0, $event)"
                    @dragleave="dragOverIndex === 0 ? dragOverIndex = null : null"
                    @drop="handleCanvasDrop(0, $event)"
                  >
                    Drop section here
                  </div>

                  <template v-for="(section, index) in canvasSections" :key="`canvas-${section.id}`">
                    <article
                      class="cms-builder-canvas-card"
                      :class="{ 'cms-builder-canvas-card--selected': selectedSectionId === section.id }"
                      draggable="true"
                      @dragstart="startSectionDrag(section.id, $event)"
                      @dragend="clearDragState"
                      @click="selectSection(section.id)"
                      @dblclick="selectSection(section.id, 'content')"
                    >
                      <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                          <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">{{ section.type }}</p>
                          <p class="mt-1 truncate text-lg font-semibold text-base-content">{{ sectionLabel(section) }}</p>
                          <p class="mt-2 line-clamp-2 text-sm text-base-content/60">{{ sectionSummary(section) }}</p>
                          <p class="mt-2 text-sm text-base-content/60">
                            Width: {{ section.layout?.width || 'full' }} · Variant: {{ section.layout?.variant || 'default' }}
                          </p>
                        </div>
                        <div class="flex flex-col items-end gap-2 shrink-0">
                          <span class="cms-builder-badge badge badge-outline badge-sm">{{ section.visibility?.enabled === false ? 'hidden' : 'visible' }}</span>
                          <span class="cms-builder-canvas-card__order">#{{ index + 1 }}</span>
                        </div>
                      </div>
                      <div class="cms-builder-canvas-card__actions">
                        <button class="btn btn-ghost btn-xs gap-1" @click.stop="selectSection(section.id, 'content')">
                          <PencilSquareIcon class="h-3.5 w-3.5" />
                          Edit
                        </button>
                        <button class="btn btn-ghost btn-xs gap-1" @click.stop="duplicateSection(section.id)">
                          <DocumentDuplicateIcon class="h-3.5 w-3.5" />
                          Duplicate
                        </button>
                        <button class="btn btn-ghost btn-xs gap-1 text-error" @click.stop="removeSection(section.id)">
                          <TrashIcon class="h-3.5 w-3.5" />
                          Remove
                        </button>
                      </div>
                    </article>

                    <div
                      class="cms-builder-dropzone"
                      :class="{ 'cms-builder-dropzone--active': dragOverIndex === index + 1 }"
                      @dragover="allowCanvasDrop(index + 1, $event)"
                      @dragleave="dragOverIndex === index + 1 ? dragOverIndex = null : null"
                      @drop="handleCanvasDrop(index + 1, $event)"
                    >
                      Drop section here
                    </div>
                  </template>
                </div>
              </section>

              <article v-if="selectedSection" class="rounded-2xl border border-base-300 bg-base-100 p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                  <div>
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">{{ selectedSection.type }}</p>
                    <p class="mt-1 text-lg font-semibold">{{ sectionLabel(selectedSection) }}</p>
                  </div>
                  <div class="flex gap-2">
                    <button class="btn btn-outline btn-xs" :disabled="selectedSectionIndex <= 0" @click="moveSection(selectedSection.id, -1)">Move Up</button>
                    <button class="btn btn-outline btn-xs" :disabled="selectedSectionIndex === -1 || selectedSectionIndex >= form.sections.length - 1" @click="moveSection(selectedSection.id, 1)">Move Down</button>
                    <button class="btn btn-outline btn-xs" @click="duplicateSection(selectedSection.id)">Duplicate</button>
                    <button class="btn btn-error btn-xs" @click="removeSection(selectedSection.id)">Remove</button>
                  </div>
                </div>
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                  <div>
                    <label class="label"><span class="label-text">Width</span></label>
                    <select v-model="selectedSection.layout.width" class="select select-bordered w-full">
                      <option value="full">Full</option>
                      <option value="half">Half</option>
                      <option value="third">Third</option>
                      <option value="two-thirds">Two-thirds</option>
                    </select>
                  </div>
                  <div>
                    <label class="label"><span class="label-text">Variant</span></label>
                    <input v-model="selectedSection.layout.variant" type="text" class="input input-bordered w-full" />
                  </div>
                </div>
                <label class="label cursor-pointer justify-start gap-3 mt-4">
                  <input v-model="selectedSection.visibility.enabled" type="checkbox" class="toggle toggle-success" />
                  <span class="label-text">Section visible</span>
                </label>
              </article>
              <p v-else class="px-1 text-sm text-base-content/60">Pilih section dari panel kiri untuk mengatur struktur.</p>
            </section>

            <section v-else-if="activeTab === 'content'" class="card-body space-y-4">
              <article v-if="selectedSection" class="space-y-4">
                <div v-if="selectedSection.type === 'hero'" class="grid gap-4">
                  <div>
                    <label class="label"><span class="label-text">Eyebrow</span></label>
                    <input v-model="selectedSection.props.eyebrow" type="text" class="input input-bordered w-full" />
                  </div>
                  <div>
                    <label class="label"><span class="label-text">Headline</span></label>
                    <input v-model="selectedSection.props.headline" type="text" class="input input-bordered w-full" />
                  </div>
                  <div>
                    <label class="label"><span class="label-text">Subheadline</span></label>
                    <input v-model="selectedSection.props.subheadline" type="text" class="input input-bordered w-full" />
                  </div>
                  <div>
                    <label class="label"><span class="label-text">Body</span></label>
                    <textarea v-model="selectedSection.props.body" class="textarea textarea-bordered w-full" rows="4" />
                  </div>
                  <div>
                    <label class="label"><span class="label-text">Contact text</span></label>
                    <input v-model="selectedSection.props.contact_text" type="text" class="input input-bordered w-full" />
                  </div>
                  <div class="grid gap-4 md:grid-cols-2">
                    <div>
                      <label class="label"><span class="label-text">Primary CTA text</span></label>
                      <input v-model="selectedSection.props.primary_cta_text" type="text" class="input input-bordered w-full" />
                    </div>
                    <div>
                      <label class="label"><span class="label-text">Primary CTA URL</span></label>
                      <input v-model="selectedSection.props.primary_cta_url" type="text" class="input input-bordered w-full" />
                    </div>
                    <div>
                      <label class="label"><span class="label-text">Secondary CTA text</span></label>
                      <input v-model="selectedSection.props.secondary_cta_text" type="text" class="input input-bordered w-full" />
                    </div>
                    <div>
                      <label class="label"><span class="label-text">Secondary CTA URL</span></label>
                      <input v-model="selectedSection.props.secondary_cta_url" type="text" class="input input-bordered w-full" />
                    </div>
                  </div>
                  <div>
                    <label class="label"><span class="label-text">Hero image</span></label>
                    <select class="select select-bordered w-full" @change="assignHeroMedia($event.target.value)">
                      <option value="">Pilih dari media library</option>
                      <option v-for="media in mediaOptions" :key="media.id" :value="media.id">{{ media.name }}</option>
                    </select>
                    <p v-if="selectedSection.assets?.hero_media_url" class="mt-2 text-xs text-base-content/60 break-all">{{ selectedSection.assets.hero_media_url }}</p>
                  </div>
                </div>

                <div v-else-if="selectedSection.type === 'feature_list'" class="space-y-4">
                  <div>
                    <label class="label"><span class="label-text">Title</span></label>
                    <input v-model="selectedSection.props.title" type="text" class="input input-bordered w-full" />
                  </div>
                  <div>
                    <label class="label"><span class="label-text">Body</span></label>
                    <textarea v-model="selectedSection.props.body" class="textarea textarea-bordered w-full" rows="3" />
                  </div>
                  <div>
                    <label class="label"><span class="label-text">Items</span></label>
                    <textarea v-model="featureItemsText" class="textarea textarea-bordered w-full" rows="6" placeholder="Satu poin per baris" />
                  </div>
                </div>

                <div v-else-if="selectedSection.type === 'image_gallery'" class="space-y-4">
                  <div>
                    <label class="label"><span class="label-text">Title</span></label>
                    <input v-model="selectedSection.props.title" type="text" class="input input-bordered w-full" />
                  </div>
                  <div>
                    <label class="label"><span class="label-text">Body</span></label>
                    <textarea v-model="selectedSection.props.body" class="textarea textarea-bordered w-full" rows="3" />
                  </div>
                  <div class="grid gap-3 md:grid-cols-[1fr_auto]">
                    <select v-model="gallerySelectId" class="select select-bordered w-full">
                      <option value="">Pilih media</option>
                      <option v-for="media in mediaOptions" :key="media.id" :value="media.id">{{ media.name }}</option>
                    </select>
                    <button class="btn btn-outline" @click="addGalleryImage">Add</button>
                  </div>
                  <div class="grid gap-3 md:grid-cols-2">
                    <div v-for="(image, idx) in selectedSection.props.images || []" :key="`${selectedSection.id}-${idx}`" class="rounded-2xl border border-base-300 bg-base-100 p-3">
                      <img :src="image.url" alt="" class="h-28 w-full rounded-xl object-cover" />
                      <input v-model="image.alt" type="text" class="input input-bordered input-sm mt-3 w-full" placeholder="Alt text" />
                      <button class="btn btn-error btn-xs mt-3" @click="removeGalleryImage(idx)">Remove</button>
                    </div>
                  </div>
                </div>

                <div v-else-if="selectedSection.type === 'countdown'" class="space-y-4">
                  <div>
                    <label class="label"><span class="label-text">Title</span></label>
                    <input v-model="selectedSection.props.title" type="text" class="input input-bordered w-full" />
                  </div>
                  <div>
                    <label class="label"><span class="label-text">Subtitle</span></label>
                    <input v-model="selectedSection.props.subtitle" type="text" class="input input-bordered w-full" />
                  </div>
                  <div>
                    <label class="label"><span class="label-text">Target datetime</span></label>
                    <input v-model="selectedSection.props.target_at" type="datetime-local" class="input input-bordered w-full" />
                  </div>
                </div>

                <div v-else-if="selectedSection.type === 'warehouse_highlight'" class="space-y-4">
                  <div>
                    <label class="label"><span class="label-text">Title</span></label>
                    <input v-model="selectedSection.props.title" type="text" class="input input-bordered w-full" />
                  </div>
                  <div>
                    <label class="label"><span class="label-text">Body</span></label>
                    <textarea v-model="selectedSection.props.body" class="textarea textarea-bordered w-full" rows="3" />
                  </div>
                  <label class="label cursor-pointer justify-start gap-3">
                    <input v-model="selectedSection.props.show_domain" type="checkbox" class="checkbox checkbox-sm" />
                    <span class="label-text">Show domain</span>
                  </label>
                  <label class="label cursor-pointer justify-start gap-3">
                    <input v-model="selectedSection.props.show_warehouse" type="checkbox" class="checkbox checkbox-sm" />
                    <span class="label-text">Show warehouse</span>
                  </label>
                </div>

                <div v-else class="space-y-4">
                  <div>
                    <label class="label"><span class="label-text">Title / Headline</span></label>
                    <input v-model="selectedSection.props.title" type="text" class="input input-bordered w-full" />
                  </div>
                  <div v-if="selectedSection.props.headline !== undefined">
                    <label class="label"><span class="label-text">Headline</span></label>
                    <input v-model="selectedSection.props.headline" type="text" class="input input-bordered w-full" />
                  </div>
                  <div>
                    <label class="label"><span class="label-text">Body</span></label>
                    <textarea v-model="selectedSection.props.body" class="textarea textarea-bordered w-full" rows="4" />
                  </div>
                  <div class="grid gap-4 md:grid-cols-2" v-if="selectedSection.props.primary_cta_text !== undefined">
                    <div>
                      <label class="label"><span class="label-text">Primary CTA text</span></label>
                      <input v-model="selectedSection.props.primary_cta_text" type="text" class="input input-bordered w-full" />
                    </div>
                    <div>
                      <label class="label"><span class="label-text">Primary CTA URL</span></label>
                      <input v-model="selectedSection.props.primary_cta_url" type="text" class="input input-bordered w-full" />
                    </div>
                    <div>
                      <label class="label"><span class="label-text">Secondary CTA text</span></label>
                      <input v-model="selectedSection.props.secondary_cta_text" type="text" class="input input-bordered w-full" />
                    </div>
                    <div>
                      <label class="label"><span class="label-text">Secondary CTA URL</span></label>
                      <input v-model="selectedSection.props.secondary_cta_url" type="text" class="input input-bordered w-full" />
                    </div>
                  </div>
                </div>
              </article>
              <p v-else class="text-sm text-base-content/60">Tambahkan lalu pilih section untuk mulai mengedit konten.</p>
            </section>

            <section v-else-if="activeTab === 'design'" class="card-body space-y-5">
              <article class="cms-builder-form-grid">
                <div>
                <label class="label"><span class="label-text">Template</span></label>
                <select v-model="form.template_key" class="select select-bordered w-full">
                  <option v-for="template in familyTemplates" :key="template.key" :value="template.key">
                    {{ template.name }} · {{ template.scope }}
                  </option>
                </select>
                </div>
                <div>
                <label class="label"><span class="label-text">Theme preset</span></label>
                <select v-model="form.theme_key" class="select select-bordered w-full">
                  <option v-for="theme in availableThemes" :key="theme.key" :value="theme.key">
                    {{ theme.name }} · {{ theme.scope }}
                  </option>
                </select>
                </div>
              </article>
              <label class="label cursor-pointer justify-start gap-3 rounded-2xl border border-base-300 bg-base-100 p-4">
                <input v-model="form.settings.full_width" type="checkbox" class="toggle toggle-primary" />
                <span class="label-text">
                  Full width mode untuk landing publik dan preview
                </span>
              </label>
              <article class="grid gap-4 md:grid-cols-2">
                <div>
                  <label class="label"><span class="label-text">Primary</span></label>
                  <input v-model="form.theme_overrides.primary" type="color" class="input input-bordered h-12 w-full p-1" />
                </div>
                <div>
                  <label class="label"><span class="label-text">Accent</span></label>
                  <input v-model="form.theme_overrides.accent" type="color" class="input input-bordered h-12 w-full p-1" />
                </div>
                <div>
                  <label class="label"><span class="label-text">Surface</span></label>
                  <input v-model="form.theme_overrides.surface" type="color" class="input input-bordered h-12 w-full p-1" />
                </div>
                <div>
                  <label class="label"><span class="label-text">Text</span></label>
                  <input v-model="form.theme_overrides.text" type="color" class="input input-bordered h-12 w-full p-1" />
                </div>
              </article>
            </section>

            <section v-else-if="activeTab === 'seo'" class="card-body space-y-4">
              <div>
                <label class="label"><span class="label-text">SEO Title</span></label>
                <input v-model="form.seo.title" type="text" class="input input-bordered w-full" />
              </div>
              <div>
                <label class="label"><span class="label-text">SEO Description</span></label>
                <textarea v-model="form.seo.description" class="textarea textarea-bordered w-full" rows="4" />
              </div>
            </section>

            <section v-else class="card-body space-y-4">
              <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="join">
                  <button class="btn btn-sm join-item" :class="previewDevice === 'desktop' ? 'btn-primary' : 'btn-outline'" @click="previewDevice = 'desktop'">Desktop</button>
                  <button class="btn btn-sm join-item" :class="previewDevice === 'mobile' ? 'btn-primary' : 'btn-outline'" @click="previewDevice = 'mobile'">Mobile</button>
                </div>
                <div class="text-xs text-base-content/60">
                  Draft v{{ draftVersion?.version_no }}<span v-if="publishedVersion"> · Published v{{ publishedVersion.version_no }}</span>
                </div>
              </div>
              <div :class="previewFrameClass" class="transition-all">
                <LandingBuilderRenderer :landing="previewLanding" :document="previewDocument" :theme="mergedPreviewTheme" :preview-mode="true" />
              </div>
            </section>
          </article>

          <div v-if="form.errors.sections || form.errors.template_key || form.errors.theme_key || form.errors.seo" class="alert alert-error">
            <span>Validasi draft gagal. Periksa field editor dan coba simpan lagi.</span>
          </div>
        </section>

        <aside class="ocn-panel cms-builder-layout__inspector">
          <div class="ocn-panel__head">
            <h2 class="ocn-panel__title">Inspector</h2>
            <p class="ocn-panel__desc">Ringkasan draft dan publish.</p>
          </div>
          <div class="card-body space-y-4">
            <div class="rounded-2xl border border-base-300 bg-base-100 p-4">
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-base-content/50">Draft</p>
              <p class="mt-2 text-lg font-semibold">v{{ draftVersion?.version_no }}</p>
              <p class="mt-1 text-sm text-base-content/70">{{ form.sections.length }} sections</p>
              <p class="mt-1 text-xs text-base-content/60">{{ form.template_key }} · {{ form.theme_key }}</p>
            </div>
            <div class="rounded-2xl border border-base-300 bg-base-100 p-4">
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-base-content/50">Published</p>
              <template v-if="publishedVersion">
                <p class="mt-2 text-lg font-semibold">v{{ publishedVersion.version_no }}</p>
                <p class="mt-1 text-sm text-base-content/70">{{ publishedVersion.template_key }} · {{ publishedVersion.theme_key }}</p>
                <p v-if="publishedVersion.published_at" class="mt-1 text-xs text-base-content/60">{{ publishedVersion.published_at }}</p>
              </template>
              <p v-else class="mt-2 text-sm text-base-content/60">Belum ada versi published.</p>
            </div>
            <div class="rounded-2xl border border-base-300 bg-base-100 p-4">
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-base-content/50">Media Library</p>
              <p class="mt-2 text-lg font-semibold">{{ mediaLibrarySummary.length }} item</p>
              <p class="mt-1 text-sm text-base-content/70">Dipakai untuk hero image dan gallery.</p>
            </div>
          </div>
        </aside>
      </main>
    </div>

    <dialog ref="saveTemplateDialog" class="modal">
      <div class="modal-box max-w-lg">
        <h3 class="text-lg font-semibold">Save as Template</h3>
        <p class="mt-1 text-sm text-base-content/60">Simpan draft builder ini sebagai template reusable untuk landing site lain.</p>

        <div class="mt-5 space-y-4">
          <div>
            <label class="label"><span class="label-text">Template Name</span></label>
            <input
              v-model="saveTemplateName"
              type="text"
              class="input input-bordered w-full"
              placeholder="Contoh: Promo Gudang Modern"
              :disabled="saveTemplateProcessing"
              @keydown.enter.prevent="saveAsTemplate"
            />
            <p v-if="saveTemplateErrors.name" class="mt-2 text-sm text-error">{{ saveTemplateErrors.name }}</p>
          </div>

          <div>
            <label class="label"><span class="label-text">Scope</span></label>
            <select v-model="saveTemplateScope" class="select select-bordered w-full" :disabled="saveTemplateProcessing">
              <option value="private">Private</option>
              <option value="shared-internal">Shared Internal</option>
            </select>
            <p v-if="saveTemplateErrors.scope" class="mt-2 text-sm text-error">{{ saveTemplateErrors.scope }}</p>
          </div>
        </div>

        <div class="modal-action">
          <form method="dialog">
            <button class="btn btn-ghost" :disabled="saveTemplateProcessing">Batal</button>
          </form>
          <button class="btn btn-primary" :disabled="saveTemplateProcessing" @click="saveAsTemplate">
            {{ saveTemplateProcessing ? 'Saving...' : 'Save Template' }}
          </button>
        </div>
      </div>
      <form method="dialog" class="modal-backdrop">
        <button>close</button>
      </form>
    </dialog>
  </AppLayout>
</template>

<style scoped>
.cms-builder-layout {
  display: grid;
  gap: 1rem;
}

.cms-builder-workspace__bar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
}

.cms-builder-workspace__status {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.cms-builder-block__label {
  font-size: 0.72rem;
  font-weight: 800;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: color-mix(in srgb, var(--color-base-content) 52%, transparent);
}

.cms-builder-library-item {
  display: grid;
  gap: 0.2rem;
  justify-items: start;
  border: 1px solid rgba(148, 163, 184, 0.24);
  border-radius: 1rem;
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.98) 0%, rgba(248, 250, 252, 0.94) 100%);
  padding: 0.8rem 0.9rem;
  text-align: left;
  transition: border-color 120ms ease, transform 120ms ease, box-shadow 120ms ease;
}

.cms-builder-library-item:hover {
  transform: translateY(-1px);
  border-color: rgba(37, 99, 235, 0.28);
  box-shadow: 0 12px 28px rgba(15, 23, 42, 0.08);
}

.cms-builder-library-item__title {
  font-size: 0.9rem;
  font-weight: 700;
  color: rgba(15, 23, 42, 0.94);
}

.cms-builder-library-item__desc {
  font-size: 0.72rem;
  line-height: 1.45;
  color: rgba(71, 85, 105, 0.92);
}

.cms-builder-section-chip {
  display: block;
  cursor: grab;
}

.cms-builder-section-chip:active {
  cursor: grabbing;
}

.cms-builder-badge {
  max-width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.cms-builder-form-grid {
  display: grid;
  gap: 1rem;
}

.cms-builder-canvas {
  display: grid;
  gap: 0.75rem;
}

.cms-builder-empty-state {
  display: flex;
  align-items: center;
  gap: 0.9rem;
  border: 1px dashed rgba(148, 163, 184, 0.42);
  border-radius: 1rem;
  background: rgba(248, 250, 252, 0.88);
  padding: 1rem 1.1rem;
}

.cms-builder-empty-state__icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 9999px;
  background: rgba(219, 234, 254, 0.9);
  color: rgba(29, 78, 216, 0.96);
}

.cms-builder-canvas-card {
  cursor: grab;
  border: 1px solid rgba(148, 163, 184, 0.22);
  border-radius: 1rem;
  background:
    linear-gradient(180deg, rgba(255, 255, 255, 0.96) 0%, rgba(248, 250, 252, 0.92) 100%);
  padding: 1rem 1.125rem;
  box-shadow: 0 14px 36px rgba(15, 23, 42, 0.07), 0 2px 8px rgba(15, 23, 42, 0.04);
  transition: border-color 120ms ease, transform 120ms ease, box-shadow 120ms ease;
}

.cms-builder-canvas-card:hover {
  transform: translateY(-1px);
  border-color: rgba(37, 99, 235, 0.28);
}

.cms-builder-canvas-card:active {
  cursor: grabbing;
}

.cms-builder-canvas-card--selected {
  border-color: rgba(37, 99, 235, 0.42);
  box-shadow: 0 18px 40px rgba(37, 99, 235, 0.10), 0 2px 8px rgba(15, 23, 42, 0.05);
}

.cms-builder-canvas-card__order {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 2rem;
  padding: 0.18rem 0.5rem;
  border-radius: 9999px;
  background: rgba(226, 232, 240, 0.82);
  color: rgba(51, 65, 85, 0.92);
  font-size: 0.7rem;
  font-weight: 800;
}

.cms-builder-canvas-card__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
  margin-top: 0.85rem;
  padding-top: 0.85rem;
  border-top: 1px solid rgba(226, 232, 240, 0.9);
}

.cms-builder-dropzone {
  display: flex;
  min-height: 3rem;
  align-items: center;
  justify-content: center;
  border: 1px dashed rgba(148, 163, 184, 0.48);
  border-radius: 0.9rem;
  background: rgba(248, 250, 252, 0.85);
  color: rgba(71, 85, 105, 0.88);
  font-size: 0.78rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  transition: border-color 120ms ease, background-color 120ms ease, color 120ms ease;
}

.cms-builder-dropzone--active {
  border-color: rgba(37, 99, 235, 0.68);
  background: rgba(219, 234, 254, 0.8);
  color: rgba(29, 78, 216, 0.96);
}

@media (min-width: 768px) {
  .cms-builder-form-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (min-width: 1280px) {
  .cms-builder-layout {
    grid-template-columns: 280px minmax(0, 1fr) 320px;
    align-items: start;
  }

  .cms-builder-layout__sidebar,
  .cms-builder-layout__inspector {
    position: sticky;
    top: 5.75rem;
  }
}
</style>
