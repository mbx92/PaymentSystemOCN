<script setup>
import { ref, computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import {
    HomeIcon, CodeBracketIcon, ArrowDownCircleIcon, ArrowUpCircleIcon, ChartBarIcon,
    UsersIcon, Bars3Icon, XMarkIcon, ArrowRightOnRectangleIcon, BuildingOffice2Icon, BellAlertIcon,
    ShoppingCartIcon, ArchiveBoxIcon, UserCircleIcon, BanknotesIcon, CircleStackIcon,
} from '@heroicons/vue/24/outline';
import FlashMessage from '@/Components/FlashMessage.vue';

const page = usePage();
const auth = computed(() => page.props.auth);
const flash = computed(() => page.props.flash);
const inventoryAlerts = computed(() => page.props.inventoryAlerts ?? { lowStockCount: 0, lowStockItems: [] });
const sidebarOpen = ref(false);
const showAlertDropdown = ref(false);

const sidebarModules = computed(() => {
    const role = auth.value?.user?.role;
    const modules = [{ title: 'Main', items: [{ name: 'Dashboard', href: route('dashboard'), icon: HomeIcon }] }];

    if (role === 'admin' || role === 'manajer') {
        modules.push(
            {
                title: 'Modul ERP',
                items: [
                    { name: 'Accounting', href: route('erp.accounting'), icon: ArrowDownCircleIcon },
                    { name: 'Sales', href: route('erp.sales'), icon: BanknotesIcon },
                    { name: 'Purchasing', href: route('erp.purchasing'), icon: ShoppingCartIcon },
                    { name: 'Inventory', href: route('erp.inventory'), icon: ArchiveBoxIcon },
                    { name: 'Projects', href: route('erp.projects'), icon: CodeBracketIcon },
                    { name: 'HR', href: route('erp.hr'), icon: UserCircleIcon },
                    { name: 'Reporting', href: route('erp.reporting'), icon: ChartBarIcon },
                ],
            },
        );
    }

    if (role === 'admin') {
        modules.push({
            title: 'Administration',
            items: [
                { name: 'Kelola User', href: route('users.index'), icon: UsersIcon },
                { name: 'Pengaturan ERP', href: route('erp.administration'), icon: BuildingOffice2Icon },
            ],
        });
    }

    return modules;
});

const topbarContext = computed(() => {
    const pathname = page.url.split('?')[0];

    if (pathname.includes('/erp/sales/pos')) return { label: 'POS Workspace', subtitle: 'Mode kasir cepat untuk penjualan produk.' };
    if (pathname.includes('/laporan')) return { label: 'Reporting Workspace', subtitle: 'Analisis laporan keuangan dan operasional real-time.' };
    if (pathname.includes('/kas-masuk') || pathname.includes('/kas-keluar')) return { label: 'Accounting Workspace', subtitle: 'Kelola transaksi kas dan posting jurnal terintegrasi.' };
    if (pathname.includes('/projects')) return { label: 'Projects Workspace', subtitle: 'Pantau proyek, termin pembayaran, dan profitabilitas.' };

    return { label: 'ERP Command Center', subtitle: 'Satu dashboard untuk finance, project, dan operasional.' };
});

const isPosFullscreen = computed(() => page.url.split('?')[0].includes('/erp/sales/pos'));

const isActive = (href) => {
    if (!href) return false;
    const path = new URL(href).pathname;
    const currentPath = page.url.split('?')[0];
    return path === '/' ? currentPath === '/' : currentPath === path || currentPath.startsWith(`${path}/`);
};
</script>

<template>
    <div class="min-h-screen ocn-shell">
        <div v-if="sidebarOpen" class="fixed inset-0 z-40 bg-black/50 lg:hidden" @click="sidebarOpen = false" />

        <aside
            :class="['fixed inset-y-0 left-0 z-50 w-72 ocn-sidebar flex flex-col transition-transform duration-300',
                sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0']"
        >
            <div class="flex items-center gap-3 px-6 py-5 border-b border-white/10">
                <div class="w-10 h-10 ocn-brand-mark text-white rounded-xl flex items-center justify-center">
                    <span class="font-bold text-sm">ERP</span>
                </div>
                <div>
                    <span class="block font-bold text-lg tracking-tight text-white leading-none">OCN ERP Suite</span>
                    <span class="block text-xs text-slate-400 mt-1">Integrated Business Platform</span>
                </div>
                <button class="ml-auto lg:hidden text-slate-300" @click="sidebarOpen = false">
                    <XMarkIcon class="w-5 h-5" />
                </button>
            </div>

            <nav class="flex-1 overflow-y-auto px-4 py-6 space-y-4">
                <div v-for="module in sidebarModules" :key="module.title" class="space-y-1.5">
                    <p class="px-3 mb-2 text-[11px] font-bold uppercase tracking-[0.18em] text-slate-500">{{ module.title }}</p>
                    <template v-for="item in module.items" :key="item.name">
                        <Link
                            :href="item.href"
                            :class="['flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-all',
                                isActive(item.href) ? 'ocn-nav-active' : 'ocn-nav-item']"
                            @click="sidebarOpen = false"
                        >
                            <component :is="item.icon" class="w-5 h-5 shrink-0 stroke-2" />
                            {{ item.name }}
                        </Link>
                    </template>
                </div>
            </nav>

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

        <div class="lg:pl-72 flex flex-col min-h-screen">
            <header class="sticky top-0 z-30 ocn-topbar px-4 py-3 flex items-center gap-4">
                <button class="btn btn-ghost btn-sm lg:hidden" @click="sidebarOpen = true">
                    <Bars3Icon class="w-5 h-5" />
                </button>
                <div class="hidden md:block">
                    <p class="text-xs uppercase tracking-[0.16em] font-bold text-primary/70">{{ topbarContext.label }}</p>
                    <p class="text-sm text-base-content/60 mt-0.5">{{ topbarContext.subtitle }}</p>
                </div>
                <div class="flex-1" />
                <div class="flex items-center gap-3">
                    <div class="relative">
                        <button class="btn btn-ghost btn-sm relative" @click="showAlertDropdown = !showAlertDropdown">
                            <BellAlertIcon class="w-5 h-5" />
                            <span v-if="inventoryAlerts.lowStockCount > 0" class="absolute -top-1 -right-1 badge badge-error badge-xs">
                                {{ inventoryAlerts.lowStockCount }}
                            </span>
                        </button>
                        <div v-if="showAlertDropdown" class="absolute right-0 mt-2 w-80 rounded-xl border bg-white p-3 shadow-xl z-50">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-sm font-semibold">Notifikasi Stok Rendah</p>
                                <span class="badge badge-warning badge-sm">{{ inventoryAlerts.lowStockCount }}</span>
                            </div>
                            <div class="space-y-2 max-h-64 overflow-auto">
                                <div v-for="item in inventoryAlerts.lowStockItems" :key="item.id" class="rounded-lg border p-2">
                                    <p class="font-mono text-xs text-base-content/60">{{ item.sku }}</p>
                                    <p class="text-sm font-medium">{{ item.name }}</p>
                                    <p class="text-xs text-error">Stok {{ item.stock }} / Min {{ item.min_stock }}</p>
                                </div>
                                <p v-if="inventoryAlerts.lowStockItems.length === 0" class="text-sm text-base-content/60">Tidak ada alert stok rendah.</p>
                            </div>
                        </div>
                    </div>
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

            <FlashMessage :flash="flash" />

            <main :class="[
                'flex-1 w-full',
                isPosFullscreen ? 'px-3 py-4 md:px-4 md:py-5 max-w-none' : 'p-4 md:p-8 max-w-7xl mx-auto',
            ]">
                <slot />
            </main>
        </div>
    </div>
</template>
