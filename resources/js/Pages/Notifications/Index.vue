<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
  ArrowLeftIcon,
  CheckIcon,
  EyeSlashIcon,
  ArchiveBoxIcon,
  ExclamationTriangleIcon,
  ClipboardDocumentListIcon,
  ShoppingCartIcon,
  WrenchScrewdriverIcon,
} from '@heroicons/vue/24/outline';
import { computed, ref } from 'vue';

const props = defineProps({
  notificationCenter: {
    type: Object,
    default: () => ({ total_count: 0, groups: [], items: [] }),
  },
});

const filter = ref('unread');

const items = computed(() => props.notificationCenter?.items ?? []);
const unreadCount = computed(() => items.value.filter((item) => !item.read).length);

const filteredItems = computed(() => {
  if (filter.value === 'all') return items.value;
  if (filter.value === 'read') return items.value.filter((item) => item.read);
  return items.value.filter((item) => !item.read);
});

const notificationCardClass = (item) => ([
  'group relative block rounded-[28px] border border-slate-200/80 bg-slate-200/35 p-4 shadow-sm backdrop-blur-sm transition duration-200 hover:-translate-y-0.5 hover:border-slate-300 hover:bg-slate-200/55 hover:shadow-md',
  item.read ? 'opacity-75' : '',
]);

const iconMap = {
  low_stock: ArchiveBoxIcon,
  reserved_stock: ArchiveBoxIcon,
  stock_mismatch: ExclamationTriangleIcon,
  project_tasks: ClipboardDocumentListIcon,
  supplier_bills: WrenchScrewdriverIcon,
  purchase_orders: ShoppingCartIcon,
};

const iconForItem = (item) => {
  const prefix = String(item.notification_id || '').split('-').slice(0, 2).join('_');
  if (prefix === 'low_stock') return iconMap.low_stock;
  if (prefix === 'reserved_stock') return iconMap.reserved_stock;
  if (prefix === 'stock_mismatch') return iconMap.stock_mismatch;
  if (prefix === 'project_task') return iconMap.project_tasks;
  if (prefix === 'payable') return iconMap.supplier_bills;
  if (prefix === 'purchase_order') return iconMap.purchase_orders;

  return ArchiveBoxIcon;
};

const markRead = (notificationId) => {
  router.patch(route('notifications.mark-read'), { notification_id: notificationId }, {
    preserveScroll: true,
  });
};

const markUnread = (notificationId) => {
  router.delete(route('notifications.mark-unread'), {
    data: { notification_id: notificationId },
    preserveScroll: true,
  });
};

const markAllRead = () => {
  router.post(route('notifications.mark-all-read'), {}, {
    preserveScroll: true,
  });
};
</script>

<template>
  <Head title="Notifications" />
  <AppLayout>
    <div class="space-y-6">
      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Notification Center</p>
              <h1 class="ocn-panel__title mt-1">Semua notifikasi</h1>
              <p class="ocn-panel__desc mt-1">Pantau alert inventory, purchasing, dan task project dari satu tempat.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
              <button class="btn btn-primary btn-sm" :disabled="unreadCount === 0" @click="markAllRead">
                Tandai semua dibaca
              </button>
              <Link class="btn btn-ghost btn-sm shrink-0 gap-1.5" :href="route('dashboard')">
                <ArrowLeftIcon class="h-4 w-4" />
                Back
              </Link>
            </div>
          </div>
        </div>
      </div>

      <div class="grid gap-4 sm:grid-cols-3">
        <button class="rounded-2xl border p-5 text-left shadow-sm" :class="filter === 'unread' ? 'border-primary bg-primary/5' : 'border-slate-200 bg-white'" @click="filter = 'unread'">
          <p class="text-xs font-semibold uppercase text-base-content/50">Belum dibaca</p>
          <p class="mt-2 text-3xl font-bold text-primary">{{ unreadCount }}</p>
        </button>
        <button class="rounded-2xl border p-5 text-left shadow-sm" :class="filter === 'all' ? 'border-primary bg-primary/5' : 'border-slate-200 bg-white'" @click="filter = 'all'">
          <p class="text-xs font-semibold uppercase text-base-content/50">Semua</p>
          <p class="mt-2 text-3xl font-bold">{{ items.length }}</p>
        </button>
        <button class="rounded-2xl border p-5 text-left shadow-sm" :class="filter === 'read' ? 'border-primary bg-primary/5' : 'border-slate-200 bg-white'" @click="filter = 'read'">
          <p class="text-xs font-semibold uppercase text-base-content/50">Sudah dibaca</p>
          <p class="mt-2 text-3xl font-bold text-success">{{ items.length - unreadCount }}</p>
        </button>
      </div>

      <div class="ocn-panel">
        <div class="ocn-panel__head">
          <h2 class="ocn-panel__title">Daftar notifikasi</h2>
          <p class="ocn-panel__desc">{{ filteredItems.length }} item tampil.</p>
        </div>
        <div class="card-body space-y-3">
          <Link
            v-for="item in filteredItems"
            :key="item.notification_id"
            :href="item.href"
            :class="notificationCardClass(item)"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="flex min-w-0 items-start gap-3">
                <div class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl border border-white/70 bg-white/70 text-slate-600">
                  <component :is="iconForItem(item)" class="h-5 w-5" />
                </div>
                <div class="min-w-0">
                  <div class="flex flex-wrap items-center gap-2">
                    <span class="badge badge-sm border-0 bg-white/80 text-slate-700">
                      {{ item.read ? 'Sudah dibaca' : 'Baru' }}
                    </span>
                  </div>
                  <p class="mt-3 text-base font-semibold text-slate-900 group-hover:text-slate-950">{{ item.title }}</p>
                  <p v-if="item.meta" class="mt-1 text-sm leading-6 text-slate-600">{{ item.meta }}</p>
                </div>
              </div>
              <button
                v-if="!item.read"
                type="button"
                class="btn btn-ghost btn-xs rounded-full border border-slate-300/80 bg-white/65 text-slate-600 hover:border-slate-400 hover:bg-white"
                :title="`Tandai dibaca · ${item.notification_id}`"
                @click.prevent.stop="markRead(item.notification_id)"
              >
                <CheckIcon class="h-4 w-4" />
              </button>
              <button
                v-else
                type="button"
                class="btn btn-ghost btn-xs rounded-full border border-slate-300/80 bg-white/65 text-slate-500 hover:border-slate-400 hover:bg-white"
                :title="`Tandai belum dibaca · ${item.notification_id}`"
                @click.prevent.stop="markUnread(item.notification_id)"
              >
                <EyeSlashIcon class="h-4 w-4" />
              </button>
            </div>
            <span class="sr-only">{{ item.notification_id }}</span>
          </Link>

          <div v-if="filteredItems.length === 0" class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-base-content/60">
            Tidak ada notifikasi pada filter ini.
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
