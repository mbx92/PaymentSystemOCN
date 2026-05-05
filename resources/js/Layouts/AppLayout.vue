<script setup>
import { ref, computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import {
    HomeIcon, FolderOpenIcon, ArrowDownCircleIcon, ArrowUpCircleIcon,
    UserGroupIcon, ChartBarIcon, UsersIcon, Bars3Icon, XMarkIcon,
    ChevronDownIcon, ArrowRightOnRectangleIcon
} from '@heroicons/vue/24/outline';
import FlashMessage from '@/Components/FlashMessage.vue';

const page = usePage();
const auth = computed(() => page.props.auth);
const flash = computed(() => page.props.flash);

const sidebarOpen = ref(false);

const navigation = computed(() => {
    const role = auth.value?.user?.role;
    const base = [
        { name: 'Dashboard', href: route('dashboard'), icon: HomeIcon },
    ];

    if (role === 'admin' || role === 'manajer') {
        base.push(
            { name: 'Projects', href: route('projects.index'), icon: FolderOpenIcon },
            { name: 'Kas Masuk', href: route('cash-in.index'), icon: ArrowDownCircleIcon },
            { name: 'Kas Keluar', href: route('cash-out.index'), icon: ArrowUpCircleIcon },
            { name: 'Pembagian Tim', href: route('team-distribution.calculator'), icon: UserGroupIcon },
            {
                name: 'Laporan', icon: ChartBarIcon, children: [
                    { name: 'Laba per Project', href: route('reports.project-profit') },
                    { name: 'Rekap Bulanan', href: route('reports.monthly') },
                    { name: 'Pembayaran Anggota', href: route('reports.member-payments') },
                ]
            },
        );
    }

    if (role === 'admin') {
        base.push({ name: 'Kelola User', href: route('users.index'), icon: UsersIcon });
    }

    return base;
});

const openMenu = ref(null);
const toggleMenu = (name) => {
    openMenu.value = openMenu.value === name ? null : name;
};

const isActive = (href) => {
    const path = new URL(href).pathname;
    const currentPath = page.url.split('?')[0];

    if (path === '/') {
        return currentPath === '/';
    }

    return currentPath === path || currentPath.startsWith(`${path}/`);
};
</script>

<template>
    <div class="min-h-screen ocn-shell">
        <!-- Mobile overlay -->
        <div v-if="sidebarOpen" class="fixed inset-0 z-40 bg-black/50 lg:hidden" @click="sidebarOpen = false" />

        <!-- Sidebar -->
        <aside
            :class="['fixed inset-y-0 left-0 z-50 w-64 ocn-sidebar flex flex-col transition-transform duration-300',
                sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0']"
        >
            <!-- Logo -->
            <div class="flex items-center gap-3 px-6 py-5 border-b border-white/10">
                <div class="w-9 h-9 ocn-brand-mark text-white rounded-xl flex items-center justify-center">
                    <span class="font-bold text-sm">OCN</span>
                </div>
                <div>
                    <span class="block font-bold text-lg tracking-tight text-white leading-none">Pembukuan</span>
                    <span class="block text-xs text-slate-400 mt-1">OCN Finance System</span>
                </div>
                <button class="ml-auto lg:hidden text-slate-300" @click="sidebarOpen = false">
                    <XMarkIcon class="w-5 h-5" />
                </button>
            </div>

            <!-- Nav -->
            <nav class="flex-1 overflow-y-auto px-4 py-6 space-y-1.5">
                <p class="px-3 mb-3 text-[11px] font-bold uppercase tracking-[0.18em] text-slate-500">Menu Utama</p>
                <template v-for="item in navigation" :key="item.name">
                    <!-- Item with children -->
                    <div v-if="item.children">
                        <button
                            class="ocn-nav-item w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-all"
                            @click="toggleMenu(item.name)"
                        >
                            <component :is="item.icon" class="w-5 h-5 shrink-0 stroke-2" />
                            <span class="flex-1 text-left">{{ item.name }}</span>
                            <ChevronDownIcon
                                :class="['w-4 h-4 transition-transform duration-200', openMenu === item.name ? 'rotate-180' : '']"
                            />
                        </button>
                        <div v-show="openMenu === item.name" class="mt-1 ml-4 pl-4 border-l border-white/10 space-y-1">
                            <Link
                                v-for="child in item.children" :key="child.name"
                                :href="child.href"
                                :class="['block px-3 py-2 rounded-lg text-sm transition-all',
                                    isActive(child.href)
                                        ? 'ocn-subnav-active'
                                        : 'text-slate-400 hover:bg-white/10 hover:text-white']"
                                @click="sidebarOpen = false"
                            >
                                {{ child.name }}
                            </Link>
                        </div>
                    </div>

                    <!-- Regular item -->
                    <Link
                        v-else
                        :href="item.href"
                        :class="['flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-all',
                            isActive(item.href)
                                ? 'ocn-nav-active'
                                : 'ocn-nav-item']"
                        @click="sidebarOpen = false"
                    >
                        <component :is="item.icon" class="w-5 h-5 shrink-0 stroke-2" />
                        {{ item.name }}
                    </Link>
                </template>
            </nav>

            <!-- User info -->
            <div class="border-t border-white/10 p-4">
                <div class="flex items-center gap-3 rounded-2xl bg-white/6 p-3 ring-1 ring-white/10">
                    <div class="avatar placeholder">
                        <div class="w-9 h-9 rounded-full bg-white/10 text-white ring-1 ring-white/20 flex items-center justify-center">
                            <span class="text-sm font-bold">{{ auth?.user?.name?.charAt(0) }}</span>
                        </div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-white truncate">{{ auth?.user?.name }}</p>
                        <span class="text-xs text-slate-400 capitalize">{{ auth?.user?.role }}</span>
                    </div>
                    <Link :href="route('logout')" method="post" as="button" class="btn btn-ghost btn-xs text-slate-300 hover:text-white hover:bg-white/10">
                        <ArrowRightOnRectangleIcon class="w-4 h-4" />
                    </Link>
                </div>
            </div>
        </aside>

        <!-- Main content -->
        <div class="lg:pl-64 flex flex-col min-h-screen">
            <!-- Topbar -->
            <header class="sticky top-0 z-30 ocn-topbar px-4 py-3 flex items-center gap-4">
                <button class="btn btn-ghost btn-sm lg:hidden" @click="sidebarOpen = true">
                    <Bars3Icon class="w-5 h-5" />
                </button>
                <div class="hidden md:block">
                    <p class="text-xs uppercase tracking-[0.16em] font-bold text-primary/70">Finance Dashboard</p>
                    <p class="text-sm text-base-content/60 mt-0.5">Kelola kas, project, dan pembagian tim dalam satu tempat.</p>
                </div>
                <div class="flex-1" />
                <div class="flex items-center gap-3">
                    <div class="text-right hidden sm:block">
                        <p class="text-sm font-semibold text-base-content leading-none">{{ auth?.user?.name }}</p>
                        <p class="text-xs text-base-content/60 capitalize mt-1">{{ auth?.user?.role }}</p>
                    </div>
                    <div class="avatar placeholder">
                        <div class="w-9 h-9 rounded-full bg-primary/10 text-primary flex items-center justify-center">
                            <span class="text-sm font-bold">{{ auth?.user?.name?.charAt(0) }}</span>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Flash message -->
            <FlashMessage :flash="flash" />

            <!-- Page content -->
            <main class="flex-1 p-4 md:p-8 max-w-7xl w-full mx-auto">
                <slot />
            </main>
        </div>
    </div>
</template>
