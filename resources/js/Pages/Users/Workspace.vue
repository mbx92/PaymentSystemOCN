<script setup>
import WorkspaceMenuCollection from '@/Components/WorkspaceMenuCollection.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { ArrowLeftIcon, KeyIcon, UserIcon } from '@heroicons/vue/24/outline';

const page = usePage();
const permissions = computed(() => page.props.auth?.permissions ?? []);
const useMenuPerms = computed(() => permissions.value.some((p) => typeof p === 'string' && p.startsWith('menu.')));
const menuLayout = computed(() => page.props.erpSetting?.module_menu_layout ?? 'grid');

const showUserCard = computed(() => !useMenuPerms.value || permissions.value.includes('menu.administration.users'));
const showRolesCard = computed(() => !useMenuPerms.value || permissions.value.includes('menu.administration.roles'));
const workspaceMenus = computed(() => {
  const items = [];

  if (showUserCard.value) {
    items.push({
      title: 'User',
      description: 'Tambah, ubah, dan hapus akun beserta penugasan role.',
      href: route('users.accounts'),
      iconComponent: UserIcon,
    });
  }

  if (showRolesCard.value) {
    items.push({
      title: 'Roles & permission',
      description: 'Atur izin menu per role untuk membatasi akses modul di sidebar.',
      href: route('users.roles-permissions'),
      iconComponent: KeyIcon,
    });
  }

  return items;
});
</script>

<template>
  <Head title="Kelola User" />
  <AppLayout>
    <div class="space-y-6">
      <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">ERP Module</p>
        <div class="mt-2 flex items-center justify-between gap-3">
          <h1 class="text-3xl font-bold tracking-tight">Kelola User</h1>
          <Link class="btn btn-ghost btn-sm gap-1.5" :href="route('dashboard')">
            <ArrowLeftIcon class="h-4 w-4" />
            Back
          </Link>
        </div>
        <p class="mt-2 text-sm text-base-content/70">
          Pilih submenu untuk mengatur akun pengguna atau hak akses menu per role.
        </p>
      </div>

      <WorkspaceMenuCollection
        :menus="workspaceMenus"
        :layout="menuLayout"
        empty-message="Anda tidak memiliki izin untuk submenu di modul ini."
        action-label="Open menu"
        action-new-tab-label="Open menu"
      />
    </div>
  </AppLayout>
</template>
