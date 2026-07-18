<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
  paginator: { type: Object, required: true },
  showPerPage: { type: Boolean, default: true },
  perPageOptions: {
    type: Array,
    default: () => [10, 15, 25, 50, 75, 100, 125, 150, 175, 200, 225, 250],
  },
});

const emit = defineEmits(['update:perPage']);

const resolvedPerPageOptions = computed(() => (
  props.perPageOptions?.length
    ? props.perPageOptions
    : [10, 15, 25, 50, 75, 100, 125, 150, 175, 200, 225, 250]
));

const summary = computed(() => {
  const p = props.paginator;
  if (!p?.total) {
    return 'Tidak ada data';
  }
  if (p.from != null && p.to != null) {
    return `Menampilkan ${p.from}–${p.to} dari ${p.total}`;
  }
  return `Total ${p.total}`;
});

const currentPerPage = computed(() => Number(props.paginator?.per_page ?? 25));

const onPerPageInput = (e) => {
  emit('update:perPage', Number(e.target.value));
};
</script>

<template>
  <div class="flex flex-col gap-3 border-t border-base-200 px-2 py-3 sm:flex-row sm:items-center sm:justify-between">
    <div class="flex flex-wrap items-center gap-2 text-sm text-base-content/70">
      <template v-if="showPerPage">
        <label class="flex items-center gap-2">
          <span class="whitespace-nowrap">Per halaman</span>
          <select
            class="select select-bordered select-sm w-min min-w-[7.5rem]"
            :value="currentPerPage"
            @change="onPerPageInput"
          >
            <option v-for="n in resolvedPerPageOptions" :key="n" :value="n">{{ n }}</option>
          </select>
        </label>
      </template>
      <span>{{ summary }}</span>
    </div>
    <div
      v-if="(paginator?.total ?? 0) > 0 && (paginator?.links?.length ?? 0) > 0"
      class="flex flex-wrap justify-end gap-1"
    >
      <template v-for="(link, index) in paginator.links" :key="`${index}-${link.label}`">
        <Link
          v-if="link.url"
          :href="link.url"
          preserve-scroll
          class="btn btn-sm min-h-9 min-w-9 px-2"
          :class="link.active ? 'btn-primary' : 'btn-ghost'"
        >
          <span v-html="link.label" />
        </Link>
        <span
          v-else
          class="btn btn-sm btn-disabled pointer-events-none min-h-9 min-w-9 px-2"
          v-html="link.label"
        />
      </template>
    </div>
  </div>
</template>
