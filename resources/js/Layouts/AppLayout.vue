<script setup>
import { ref, computed, nextTick, watch, onMounted, onBeforeUnmount } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import {
    HomeIcon, CodeBracketIcon, ArrowDownCircleIcon, ArrowUpCircleIcon, ChartBarIcon,
    UsersIcon, Bars3Icon, XMarkIcon, ArrowRightOnRectangleIcon, BuildingOffice2Icon, BellAlertIcon,
    ShoppingCartIcon, ArchiveBoxIcon, UserCircleIcon, BanknotesIcon, CircleStackIcon, ChatBubbleLeftRightIcon, PaperAirplaneIcon,
    TrashIcon,
    BeakerIcon,
    WalletIcon,
    NewspaperIcon,
    PhotoIcon,
    GlobeAltIcon,
    ShareIcon,
    CalendarDaysIcon,
    ChevronDoubleLeftIcon,
    ChevronDoubleRightIcon,
    CheckIcon,
    ClipboardDocumentListIcon,
    ExclamationTriangleIcon,
    EyeSlashIcon,
    WrenchScrewdriverIcon,
} from '@heroicons/vue/24/outline';
import FlashMessage from '@/Components/FlashMessage.vue';

const SIDEBAR_COLLAPSE_STORAGE_KEY = 'ocn_sidebar_collapsed';
const page = usePage();
const auth = computed(() => page.props.auth);
const flash = computed(() => page.props.flash);
const inventoryAlerts = computed(() => page.props.inventoryAlerts ?? { lowStockCount: 0, lowStockItems: [] });
const localNotificationCenter = ref(page.props.notificationCenter ?? { total_count: 0, groups: [], items: [] });
const erpSetting = computed(() => page.props.erpSetting ?? {});
const uiPreferences = computed(() => page.props.uiPreferences ?? { module_menu_orders: {} });
const sidebarOpen = ref(false);
const readSidebarCollapsedPreference = () => {
    try {
        return localStorage.getItem(SIDEBAR_COLLAPSE_STORAGE_KEY) === 'true';
    } catch {
        return false;
    }
};
const desktopSidebarCollapsed = ref(readSidebarCollapsedPreference());
const hoveredSidebarItem = ref(null);
const hoveredSidebarPosition = ref({ x: 0, y: 0 });
const showAlertDropdown = ref(false);
const chatPanelOpen = ref(false);
const chatInput = ref('');
const chatLoading = ref(false);
const chatBodyRef = ref(null);
const chatInputRef = ref(null);
let notificationPollTimer = null;

const CHAT_STORAGE_KEY = 'erp_chat_history';
const WELCOME_MSG = {
    role: 'assistant',
    text: 'Halo! 👋 Saya asisten ERP Anda.\nSebagian jawaban sekarang mengambil **data live** dari database, terutama untuk stok, harga, penjualan, cashflow, invoice, dan project.\n\nKetik **bantuan** atau pilih contoh pertanyaan di bawah untuk mulai.',
    meta: {
        label: 'built-in',
        tone: 'neutral',
    },
    ts: Date.now(),
};

const chatbotIntentCatalog = [
    {
        key: 'stock_lookup',
        label: 'Cek stok',
        source: 'data',
        examples: ['stok lid cup', 'cek stok kabel lan'],
    },
    {
        key: 'product_price_lookup',
        label: 'Cek harga',
        source: 'data',
        examples: ['harga standing pouch', 'berapa harga kabel lan'],
    },
    {
        key: 'pos_sales_today',
        label: 'POS hari ini',
        source: 'data',
        examples: ['pos hari ini', 'penjualan bulan ini'],
    },
    {
        key: 'cashflow_today',
        label: 'Cashflow',
        source: 'data',
        examples: ['cashflow hari ini', 'kas bulan ini'],
    },
    {
        key: 'invoice_unpaid_list',
        label: 'Invoice unpaid',
        source: 'data',
        examples: ['invoice belum dibayar', 'invoice jatuh tempo'],
    },
    {
        key: 'project_active_list',
        label: 'Project aktif',
        source: 'data',
        examples: ['project aktif', 'daftar project'],
    },
    {
        key: 'send_invoice',
        label: 'Kirim invoice',
        source: 'action',
        examples: ['kirim invoice INV-PRJ-000123 ke client@mail.com'],
    },
    {
        key: 'help',
        label: 'Bantuan',
        source: 'built_in',
        examples: ['bantuan'],
    },
];

const quickReplyGroups = [
    {
        title: 'Produk',
        chips: ['stok lid cup', 'harga standing pouch', 'stok rendah'],
    },
    {
        title: 'Penjualan',
        chips: ['pos hari ini', 'produk terlaris', 'cashflow hari ini'],
    },
    {
        title: 'Invoice',
        chips: ['invoice belum dibayar', 'invoice jatuh tempo', 'project aktif'],
    },
];

const loadHistory = () => {
    try {
        const saved = localStorage.getItem(CHAT_STORAGE_KEY);
        if (saved) {
            const parsed = JSON.parse(saved);
            if (Array.isArray(parsed) && parsed.length > 0) return parsed;
        }
    } catch { /* ignore */ }
    return [{ ...WELCOME_MSG }];
};

const chatMessages = ref(loadHistory());

const saveHistory = () => {
    try {
        // keep max 60 messages
        const toSave = chatMessages.value.slice(-60);
        localStorage.setItem(CHAT_STORAGE_KEY, JSON.stringify(toSave));
    } catch { /* ignore */ }
};

const clearHistory = () => {
    chatMessages.value = [{ ...WELCOME_MSG, ts: Date.now() }];
    saveHistory();
};

const formatTime = (ts) => {
    if (!ts) return '';
    const d = new Date(ts);
    return d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
};

const renderMarkdown = (text) => {
    if (!text) return '';
    let safe = text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');

    safe = safe.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
    safe = safe.replace(/\*([^*]+?)\*/g, '<em>$1</em>');
    safe = safe.replace(/`([^`]+?)`/g, '<code class="rounded bg-base-300/60 px-1 py-0.5 text-xs font-mono">$1</code>');

    const lines = safe.split('\n');
    let inList = false;
    const out = [];
    for (const line of lines) {
        if (/^- /.test(line)) {
            if (!inList) { out.push('<ul class="list-disc ml-4 space-y-0.5 mt-1">'); inList = true; }
            out.push(`<li>${line.slice(2)}</li>`);
        } else {
            if (inList) { out.push('</ul>'); inList = false; }
            out.push(line === '' ? '<br>' : `<span class="block">${line}</span>`);
        }
    }
    if (inList) out.push('</ul>');
    return out.join('');
};

const chatbotIntentMeta = computed(() => {
    const map = new Map();
    chatbotIntentCatalog.forEach((item) => {
        map.set(item.key, item);
    });
    map.set('follow_up', { key: 'follow_up', label: 'Follow-up', source: 'data' });
    return map;
});

const sourceBadgeClass = (source) => {
    if (source === 'data') return 'badge-success';
    if (source === 'action') return 'badge-warning';
    return 'badge-ghost';
};

const sourceBadgeLabel = (source) => {
    if (source === 'data') return 'data live';
    if (source === 'action') return 'action';
    return 'built-in';
};

const assistantMetaFromIntent = (intent) => {
    const intentMeta = chatbotIntentMeta.value.get(intent);
    if (!intentMeta) {
        return { label: 'respons assistant', tone: 'neutral' };
    }

    return {
        label: intentMeta.label,
        source: intentMeta.source,
        tone: intentMeta.source === 'action' ? 'warning' : intentMeta.source === 'data' ? 'success' : 'neutral',
    };
};

const permissions = computed(() => page.props.auth?.permissions ?? []);
const usePermissionMenus = computed(() =>
    permissions.value.some((p) => typeof p === 'string' && p.startsWith('menu.')),
);

const canSeeNavItem = (item) => {
    if (item.permissionAny?.length) {
        if (!usePermissionMenus.value) {
            return true;
        }

        return item.permissionAny.some((p) => permissions.value.includes(p));
    }
    if (!item.permission) {
        return true;
    }
    if (!usePermissionMenus.value) {
        return true;
    }
    return permissions.value.includes(item.permission);
};

const sidebarModules = computed(() => {
    const role = auth.value?.user?.role;
    const legacyErp = role === 'admin' || role === 'manajer';
    const legacyCms = role === 'admin';
    const legacyPersonal = role === 'admin' || role === 'manajer';
    const legacyAdmin = role === 'admin';

    const showErp = usePermissionMenus.value
        ? permissions.value.some((p) => p.startsWith('menu.erp.')) || permissions.value.includes('manage-rnd')
        : legacyErp;
    const showCms = usePermissionMenus.value
        ? permissions.value.some((p) => p.startsWith('menu.cms.'))
        : legacyCms;
    const showPersonal = usePermissionMenus.value
        ? permissions.value.includes('menu.personal')
        : legacyPersonal;
    const showAdmin = legacyAdmin;

    const modules = [];

    const mainItems = [{ name: 'Dashboard', href: route('dashboard'), icon: HomeIcon, permission: 'menu.dashboard' }]
        .filter(canSeeNavItem);
    if (mainItems.length) {
        modules.push({ title: 'Main', items: mainItems });
    }

    if (showErp) {
        const erpItems = [
            { name: 'Accounting', href: route('erp.accounting'), icon: ArrowDownCircleIcon, permission: 'menu.erp.accounting' },
            { name: 'Sales', href: route('erp.sales'), icon: BanknotesIcon, permission: 'menu.erp.sales' },
            { name: 'Purchasing', href: route('erp.purchasing'), icon: ShoppingCartIcon, permission: 'menu.erp.purchasing' },
            { name: 'Inventory', href: route('erp.inventory'), icon: ArchiveBoxIcon, permission: 'menu.erp.inventory' },
            { name: 'Projects', href: route('erp.projects'), icon: CodeBracketIcon, permission: 'menu.erp.projects' },
            { name: 'R&D', href: route('rnd.dashboard'), icon: BeakerIcon, permissionAny: ['menu.erp.rnd', 'manage-rnd'] },
            { name: 'HR', href: route('erp.hr'), icon: UserCircleIcon, permission: 'menu.erp.hr' },
            { name: 'CRM', href: route('erp.crm'), icon: ShareIcon, permission: 'menu.erp.crm' },
            { name: 'Calendar', href: route('erp.calendar'), icon: CalendarDaysIcon, permission: 'menu.erp.calendar' },
            { name: 'Reporting', href: route('erp.reporting'), icon: ChartBarIcon, permission: 'menu.erp.reporting' },
        ].filter(canSeeNavItem);
        if (erpItems.length) {
            modules.push({ title: 'Modul ERP', items: erpItems });
        }
    }

    if (showCms) {
        const cmsItems = [
            { name: 'Dashboard CMS', href: route('erp.cms'), icon: NewspaperIcon, permission: 'menu.cms.dashboard' },
            { name: 'Landing sites', href: route('erp.cms.sites'), icon: GlobeAltIcon, permission: 'menu.cms.sites' },
            { name: 'Media library', href: route('erp.cms.media'), icon: PhotoIcon, permission: 'menu.cms.media' },
        ].filter(canSeeNavItem);
        if (cmsItems.length) {
            modules.push({ title: 'Website CMS', items: cmsItems });
        }
    }

    if (showPersonal) {
        const personalItems = [
            { name: 'Beranda', href: route('personal'), icon: WalletIcon, permission: 'menu.personal' },
        ].filter(canSeeNavItem);
        if (personalItems.length) {
            modules.push({ title: 'Personal', items: personalItems });
        }
    }

    if (showAdmin) {
        const adminItems = [
            {
                name: 'Kelola User',
                href: route('users.index'),
                icon: UsersIcon,
                permissionAny: ['menu.administration.users', 'menu.administration.roles'],
            },
            { name: 'Pengaturan ERP', href: route('erp.administration'), icon: BuildingOffice2Icon, permission: 'menu.administration.erp_settings' },
        ].filter(canSeeNavItem);
        if (adminItems.length) {
            modules.push({ title: 'Administration', items: adminItems });
        }
    }

    return modules;
});

const topbarContext = computed(() => {
    const pathname = page.url.split('?')[0];

    if (pathname.includes('/erp/sales/pos')) return { label: 'POS Workspace', subtitle: 'Mode kasir cepat untuk penjualan produk.' };
    if (pathname.includes('/laporan')) return { label: 'Reporting Workspace', subtitle: 'Analisis laporan keuangan dan operasional real-time.' };
    if (pathname.includes('/erp/accounting/mutasi-kas-bank')) return { label: 'Mutasi Kas/Bank', subtitle: 'Transfer dana antar akun kas/bank tanpa mempengaruhi laba rugi.' };
    if (pathname.includes('/erp/accounting/inventaris')) return { label: 'Inventaris', subtitle: 'Pencatatan pembelian inventaris ke akun aset (default Peralatan) dan kas/bank.' };
    if (pathname.includes('/erp/accounting/cashflow')) return { label: 'Cashflow Accounting', subtitle: 'Ringkasan arus kas dari invoice, POS, supplier, anggota, dan expenses.' };
    if (pathname.includes('/erp/accounting/payments/member')) return { label: 'Pembayaran Anggota', subtitle: 'Bayar distribusi tim dan lacak status di cashflow.' };
    if (pathname.includes('/erp/accounting/payments')) return { label: 'Pembayaran Workspace', subtitle: 'Pusat pembayaran project, tim, dan supplier.' };
    if (pathname.includes('/erp/accounting/reconciliation')) return { label: 'Rekonsiliasi Workspace', subtitle: 'Kontrol mutasi kas/bank harian dan mingguan.' };
    if (pathname.includes('/kas-keluar')) return { label: 'Expenses Workspace', subtitle: 'Input biaya tim, operasional, referral, dan pengeluaran project.' };
    if (pathname.includes('/kas-masuk')) return { label: 'Accounting Workspace', subtitle: 'Kas masuk legacy; gunakan invoice/termin untuk pemasukan project.' };
    if (pathname.includes('/projects')) return { label: 'Projects Workspace', subtitle: 'Pantau proyek, termin pembayaran, dan profitabilitas.' };
    if (pathname.includes('/erp/hr/legal')) return { label: 'Legal Workspace', subtitle: 'File manager dokumen legal di server.' };
    if (pathname.includes('/erp/calendar')) return { label: 'Calendar Workspace', subtitle: 'Jadwal event project, PO, pipeline, dan follow-up.' };
    if (pathname.startsWith('/rnd')) return { label: 'R&D Workspace', subtitle: 'Kelola riset, budget, pembelian, output produk, dan laporan proyek.' };
    if (pathname.includes('/erp/crm')) return { label: 'CRM Workspace', subtitle: 'Kelola prospek, customer, dan aktivitas follow-up.' };
    if (pathname.startsWith('/personal')) return { label: 'Personal Workspace', subtitle: 'Pencatatan keuangan pribadi dan keluarga.' };
    if (pathname.startsWith('/users/roles-permissions')) return { label: 'Roles & permission', subtitle: 'Atur hak akses menu per role.' };
    if (pathname.startsWith('/users/accounts')) return { label: 'User', subtitle: 'Daftar akun, role, dan tindakan pengguna.' };
    if (pathname === '/users' || pathname === '/users/') return { label: 'Kelola User', subtitle: 'Pilih submenu pengaturan akun dan hak akses.' };
    if (pathname.startsWith('/users')) return { label: 'Kelola User', subtitle: 'Pengaturan pengguna sistem.' };
    if (pathname.startsWith('/erp/cms')) return { label: 'Website CMS', subtitle: 'Konten landing publik, media, dan publikasi halaman.' };

    return { label: 'ERP Command Center', subtitle: 'Satu dashboard untuk finance, project, dan operasional.' };
});

const isPosFullscreen = computed(() => page.url.split('?')[0].includes('/erp/sales/pos'));
const notificationCenter = computed(() => localNotificationCenter.value ?? { total_count: 0, groups: [], items: [] });

const notificationCardClass = (item) => ([
    'block rounded-2xl border border-slate-200/80 bg-slate-200/35 p-2.5 backdrop-blur-sm transition duration-200 hover:-translate-y-0.5 hover:border-slate-300 hover:bg-slate-200/55 hover:shadow-sm',
    item.read ? 'opacity-75' : '',
]);

const notificationIcon = (notificationId) => {
    const prefix = String(notificationId || '').split('-').slice(0, 2).join('_');
    if (prefix === 'low_stock' || prefix === 'reserved_stock') return ArchiveBoxIcon;
    if (prefix === 'stock_mismatch') return ExclamationTriangleIcon;
    if (prefix === 'project_task') return ClipboardDocumentListIcon;
    if (prefix === 'payable') return WrenchScrewdriverIcon;
    if (prefix === 'purchase_order') return ShoppingCartIcon;
    return BellAlertIcon;
};

const markNotificationRead = (notificationId) => {
    router.patch(route('notifications.mark-read'), { notification_id: notificationId }, {
        preserveScroll: true,
        preserveState: true,
    });
};

const syncNotificationCenter = (payload) => {
    localNotificationCenter.value = payload ?? { total_count: 0, groups: [], items: [] };
};

watch(() => page.props.notificationCenter, (val) => {
    syncNotificationCenter(val);
}, { immediate: true });

const readSeenNotificationIds = () => {
    try {
        return JSON.parse(localStorage.getItem('ocn_seen_notification_ids') || '[]');
    } catch {
        return [];
    }
};

const writeSeenNotificationIds = (ids) => {
    try {
        localStorage.setItem('ocn_seen_notification_ids', JSON.stringify(ids.slice(0, 200)));
    } catch {
        // ignore
    }
};

const emitNewNotificationToasts = (payload) => {
    const items = (payload?.items ?? []).filter((item) => !item.read);
    const seen = new Set(readSeenNotificationIds());
    const fresh = items.filter((item) => !seen.has(item.notification_id)).slice(0, 3);

    fresh.forEach((item) => {
        window.dispatchEvent(new CustomEvent('ocn:alert', {
            detail: {
                type: item.severity === 'error' ? 'error' : item.severity === 'warning' ? 'warning' : 'info',
                message: item.meta ? `${item.title} — ${item.meta}` : item.title,
            },
        }));
        seen.add(item.notification_id);
    });

    items.forEach((item) => seen.add(item.notification_id));
    writeSeenNotificationIds(Array.from(seen));
};

const pollNotifications = async () => {
    if (!auth.value?.user) return;

    try {
        const response = await fetch(route('notifications.poll'), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (!response.ok) return;
        const data = await response.json();
        if (!data?.notificationCenter) return;
        syncNotificationCenter(data.notificationCenter);
        emitNewNotificationToasts(data.notificationCenter);
    } catch {
        // ignore
    }
};

onMounted(() => {
    emitNewNotificationToasts(localNotificationCenter.value);

    if (auth.value?.user) {
        notificationPollTimer = window.setInterval(() => {
            pollNotifications();
        }, 60000);
    }
});

onBeforeUnmount(() => {
    if (notificationPollTimer) {
        window.clearInterval(notificationPollTimer);
    }
});

const isActive = (href) => {
    if (!href) return false;
    const path = new URL(href).pathname;
    const currentPath = page.url.split('?')[0];
    if (path === '/') return currentPath === '/';
    if (path === '/personal') {
        return currentPath === '/personal' || currentPath.startsWith('/personal/');
    }
    const exactOnly = ['/erp/cms'];
    if (exactOnly.includes(path)) return currentPath === path;
    return currentPath === path || currentPath.startsWith(`${path}/`);
};

const toggleChatPanel = () => {
    chatPanelOpen.value = !chatPanelOpen.value;
    if (chatPanelOpen.value) {
        nextTick(() => {
            scrollChatToBottom();
            chatInputRef.value?.focus();
        });
    }
};

const scrollChatToBottom = () => {
    const container = chatBodyRef.value;
    if (!container) return;
    container.scrollTop = container.scrollHeight;
};

const getCookieValue = (name) => {
    const match = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`));
    return match ? decodeURIComponent(match[1]) : '';
};

const sendChatMessage = async (overrideText = null) => {
    const message = (overrideText ?? chatInput.value).trim();
    if (!message || chatLoading.value) return;

    chatMessages.value.push({ role: 'user', text: message, ts: Date.now() });
    chatInput.value = '';
    await nextTick();
    scrollChatToBottom();
    chatLoading.value = true;

    try {
        const response = await fetch(route('erp.chatbot.ask'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': getCookieValue('XSRF-TOKEN'),
            },
            body: JSON.stringify({
                message,
                history: chatMessages.value
                    .filter(m => m.role === 'user' || m.role === 'assistant')
                    .slice(-10)
                    .map(m => ({ role: m.role, text: m.text })),
            }),
        });

        if (!response.ok) {
            const errPayload = await response.json().catch(() => ({}));
            const errMsg = errPayload?.message || `Server error ${response.status}.`;
            chatMessages.value.push({ role: 'assistant', text: `⚠️ ${errMsg}`, ts: Date.now() });
        } else {
            const payload = await response.json();
            const answer = payload?.answer || 'Maaf, terjadi kendala saat memproses pertanyaan.';
            chatMessages.value.push({
                role: 'assistant',
                text: answer,
                intent: payload?.intent || null,
                meta: assistantMetaFromIntent(payload?.intent || null),
                ts: Date.now(),
            });
        }
    } catch {
        chatMessages.value.push({ role: 'assistant', text: '⚠️ Koneksi ke chatbot gagal. Coba lagi sebentar.', ts: Date.now() });
    } finally {
        chatLoading.value = false;
        saveHistory();
        await nextTick();
        scrollChatToBottom();
    }
};

const toggleDesktopSidebar = () => {
    desktopSidebarCollapsed.value = !desktopSidebarCollapsed.value;
    hoveredSidebarItem.value = null;
    try {
        localStorage.setItem(SIDEBAR_COLLAPSE_STORAGE_KEY, desktopSidebarCollapsed.value ? 'true' : 'false');
    } catch {
        // ignore storage access issues
    }
};

const handleSidebarItemMouseEnter = (itemName, event) => {
    if (!desktopSidebarCollapsed.value) return;
    hoveredSidebarItem.value = itemName;
    hoveredSidebarPosition.value = {
        x: event.clientX + 14,
        y: event.clientY,
    };
};

const handleSidebarItemMouseMove = (event) => {
    if (!desktopSidebarCollapsed.value || !hoveredSidebarItem.value) return;
    hoveredSidebarPosition.value = {
        x: event.clientX + 14,
        y: event.clientY,
    };
};

const handleSidebarItemMouseLeave = () => {
    hoveredSidebarItem.value = null;
};
</script>

<template>
    <div class="min-h-screen ocn-shell">
        <div v-if="sidebarOpen" class="fixed inset-0 z-40 bg-black/50 md:hidden" @click="sidebarOpen = false" />

        <aside
            :class="['fixed inset-y-0 left-0 z-50 ocn-sidebar flex flex-col transition-all duration-300',
                desktopSidebarCollapsed ? 'w-20' : 'w-72',
                sidebarOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0']"
        >
            <div :class="['flex items-center border-b border-white/10 py-5', desktopSidebarCollapsed ? 'justify-center px-3' : 'gap-3 px-6']">
                <div v-if="erpSetting?.app_logo_url" class="h-10 w-10 rounded-xl overflow-hidden bg-white/95 flex items-center justify-center p-1">
                    <img :src="erpSetting.app_logo_url" alt="Logo" class="w-full h-full object-contain">
                </div>
                <div v-else class="h-10 w-10 ocn-brand-mark text-white rounded-xl flex items-center justify-center">
                    <span class="font-bold text-sm">ERP</span>
                </div>
                <div v-if="!desktopSidebarCollapsed">
                    <span class="block font-bold text-lg tracking-tight text-white leading-none">{{ erpSetting?.app_name || 'OCN ERP Suite' }}</span>
                    <span class="block text-xs text-slate-400 mt-1">{{ erpSetting?.app_tagline || 'Integrated Business Platform' }}</span>
                </div>
                <button class="ml-auto md:hidden text-slate-300" @click="sidebarOpen = false">
                    <XMarkIcon class="w-5 h-5" />
                </button>
            </div>

            <nav :class="['flex-1 overflow-y-auto py-6 space-y-4', desktopSidebarCollapsed ? 'px-2' : 'px-4']">
                <div v-for="module in sidebarModules" :key="module.title" class="space-y-1.5">
                    <p v-if="!desktopSidebarCollapsed" class="px-3 mb-2 text-[11px] font-bold uppercase tracking-[0.18em] text-slate-500">{{ module.title }}</p>
                    <template v-for="item in module.items" :key="item.name">
                        <Link
                            :href="item.href"
                            :title="desktopSidebarCollapsed ? item.name : null"
                            :class="['flex items-center rounded-xl text-sm font-semibold transition-all',
                                desktopSidebarCollapsed ? 'justify-center px-2 py-3' : 'gap-3 px-3 py-2.5',
                                isActive(item.href) ? 'ocn-nav-active' : 'ocn-nav-item']"
                            @mouseenter="handleSidebarItemMouseEnter(item.name, $event)"
                            @mousemove="handleSidebarItemMouseMove($event)"
                            @mouseleave="handleSidebarItemMouseLeave"
                            @click="sidebarOpen = false"
                        >
                            <component :is="item.icon" class="w-5 h-5 shrink-0 stroke-2" />
                            <span v-if="!desktopSidebarCollapsed">{{ item.name }}</span>
                        </Link>
                    </template>
                </div>
            </nav>

            <div :class="['border-t border-white/10', desktopSidebarCollapsed ? 'p-2' : 'p-4']">
                <div :class="['rounded-2xl bg-white/6 ring-1 ring-white/10', desktopSidebarCollapsed ? 'p-2' : 'flex items-center gap-3 p-3']">
                    <div class="avatar placeholder">
                        <div class="w-9 h-9 rounded-full bg-white/10 text-white ring-1 ring-white/20 flex items-center justify-center">
                            <span class="text-sm font-bold">{{ auth?.user?.name?.charAt(0) }}</span>
                        </div>
                    </div>
                    <div v-if="!desktopSidebarCollapsed" class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-white truncate">{{ auth?.user?.name }}</p>
                        <span class="text-xs text-slate-400 capitalize">{{ auth?.user?.role }}</span>
                    </div>
                    <Link :href="route('logout')" method="post" as="button" :class="['btn btn-ghost btn-xs text-slate-300 hover:text-white hover:bg-white/10', desktopSidebarCollapsed ? 'mt-2 flex w-full justify-center' : '']">
                        <ArrowRightOnRectangleIcon class="w-4 h-4" />
                    </Link>
                </div>
            </div>
        </aside>

        <div
            v-if="desktopSidebarCollapsed && hoveredSidebarItem"
            class="pointer-events-none fixed z-[70] -translate-y-1/2 whitespace-nowrap rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-xl"
            :style="{ left: `${hoveredSidebarPosition.x}px`, top: `${hoveredSidebarPosition.y}px` }"
        >
            {{ hoveredSidebarItem }}
        </div>

        <div :class="[desktopSidebarCollapsed ? 'md:pl-20' : 'md:pl-72', 'flex flex-col min-h-screen transition-all duration-300']">
            <header class="sticky top-0 z-30 ocn-topbar px-4 py-3 flex items-center gap-4">
                <button class="btn btn-ghost btn-sm md:hidden" @click="sidebarOpen = true">
                    <Bars3Icon class="w-5 h-5" />
                </button>
                <button class="btn btn-ghost btn-sm hidden md:inline-flex" @click="toggleDesktopSidebar">
                    <ChevronDoubleRightIcon v-if="desktopSidebarCollapsed" class="w-5 h-5" />
                    <ChevronDoubleLeftIcon v-else class="w-5 h-5" />
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
                            <span v-if="notificationCenter.total_count > 0" class="absolute -top-1 -right-1 badge badge-error badge-xs">
                                {{ notificationCenter.total_count }}
                            </span>
                        </button>
                        <div v-if="showAlertDropdown" class="absolute right-0 mt-2 w-[22rem] rounded-xl border bg-white p-3 shadow-xl z-50">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-sm font-semibold">Notification Center</p>
                                <div class="flex items-center gap-2">
                                    <Link :href="route('notifications.index')" class="text-[11px] font-medium text-primary hover:underline" @click="showAlertDropdown = false">
                                        Buka semua
                                    </Link>
                                    <span class="badge badge-warning badge-sm">{{ notificationCenter.total_count }}</span>
                                </div>
                            </div>
                            <div class="space-y-3 max-h-80 overflow-auto">
                                <div v-for="group in notificationCenter.groups" :key="group.key" class="space-y-2">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <p class="text-xs font-semibold uppercase tracking-wide text-base-content/60">{{ group.label }}</p>
                                            <span v-if="group.unread_count > 0" class="badge badge-error badge-xs">{{ group.unread_count }}</span>
                                        </div>
                                        <Link
                                            v-if="group.href"
                                            :href="group.href"
                                            class="text-[11px] font-medium text-primary hover:underline"
                                            @click="showAlertDropdown = false"
                                        >
                                            Lihat
                                        </Link>
                                    </div>
                                    <div class="space-y-2">
                                        <Link
                                            v-for="item in group.items"
                                            :key="item.notification_id"
                                            :href="item.href"
                                            :class="notificationCardClass(item)"
                                            @click="showAlertDropdown = false"
                                        >
                                            <div class="flex items-start justify-between gap-3">
                                                <div class="flex min-w-0 items-start gap-2.5">
                                                    <div class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-xl border border-white/70 bg-white/70 text-slate-600">
                                                        <component :is="notificationIcon(item.notification_id)" class="h-4 w-4" />
                                                    </div>
                                                    <div class="min-w-0">
                                                        <div class="flex items-center gap-2">
                                                            <p class="truncate text-sm font-medium text-base-content">{{ item.title }}</p>
                                                            <span v-if="!item.read" class="badge badge-primary badge-xs">Baru</span>
                                                        </div>
                                                        <p v-if="item.meta" class="mt-1 text-xs text-base-content/70">{{ item.meta }}</p>
                                                    </div>
                                                </div>
                                                <button
                                                    v-if="!item.read"
                                                    type="button"
                                                    class="btn btn-ghost btn-xs rounded-full border border-slate-300/80 bg-white/65 text-slate-600 hover:border-slate-400 hover:bg-white"
                                                    :title="`Tandai dibaca · ${item.notification_id}`"
                                                    @click.prevent.stop="markNotificationRead(item.notification_id)"
                                                >
                                                    <CheckIcon class="h-3.5 w-3.5" />
                                                </button>
                                                <button
                                                    v-else
                                                    type="button"
                                                    class="btn btn-ghost btn-xs rounded-full border border-slate-300/80 bg-white/65 text-slate-500"
                                                    :title="`Sudah dibaca · ${item.notification_id}`"
                                                    @click.prevent.stop
                                                >
                                                    <EyeSlashIcon class="h-3.5 w-3.5" />
                                                </button>
                                            </div>
                                        </Link>
                                    </div>
                                </div>
                                <p v-if="notificationCenter.groups.length === 0" class="text-sm text-base-content/60">Belum ada notifikasi penting saat ini.</p>
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

            <div class="fixed bottom-3 right-3 z-[9999] flex flex-col items-end gap-2 sm:bottom-6 sm:right-6 sm:gap-3">
                <div
                    v-if="chatPanelOpen"
                    class="w-[calc(100vw-1.5rem)] overflow-hidden rounded-2xl border border-base-300 bg-base-100 shadow-2xl sm:w-[42rem] sm:max-w-[calc(100vw-3rem)]"
                >
                    <!-- Chat header -->
                    <div class="flex items-center justify-between border-b border-base-300 px-3 py-2.5 sm:px-4 sm:py-3">
                        <div>
                            <p class="hidden text-xs font-bold uppercase tracking-[0.14em] text-primary/70 sm:block">Assistant</p>
                            <p class="text-sm font-semibold">Assistant</p>
                        </div>
                        <div class="flex items-center gap-1">
                            <button class="btn btn-ghost btn-xs text-base-content/50 hover:text-error" title="Hapus riwayat chat" @click="clearHistory">
                                <TrashIcon class="h-4 w-4" />
                            </button>
                            <button class="btn btn-ghost btn-xs" @click="chatPanelOpen = false">
                                <XMarkIcon class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <div class="flex h-[calc(100dvh-7rem)] flex-col sm:h-[34rem]">
                        <div class="border-b border-base-300 bg-base-200/40 px-3 py-2 sm:px-4">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="badge badge-success badge-sm">data live siap</span>
                                <span class="badge badge-warning badge-sm">action perlu konfirmasi</span>
                                <span class="text-xs text-base-content/60">Selaras dengan halaman setting chatbot.</span>
                            </div>
                        </div>

                        <!-- Message list -->
                        <div ref="chatBodyRef" class="flex-1 space-y-3 overflow-y-auto p-3 sm:p-4">
                            <div
                                v-for="(msg, idx) in chatMessages"
                                :key="idx"
                                :class="msg.role === 'user' ? 'flex justify-end' : 'flex justify-start'"
                            >
                                <div
                                    class="max-w-[90%] rounded-xl px-3 py-2.5 text-sm sm:max-w-[85%]"
                                    :class="msg.role === 'user'
                                        ? 'bg-primary text-primary-content'
                                        : 'bg-base-200 text-base-content/90'"
                                >
                                    <div v-if="msg.role === 'assistant' && msg.meta" class="mb-2 flex flex-wrap items-center gap-1.5">
                                        <span class="badge badge-xs" :class="sourceBadgeClass(msg.meta.source)">{{ sourceBadgeLabel(msg.meta.source) }}</span>
                                        <span class="text-[10px] font-semibold uppercase tracking-[0.12em] text-base-content/45">{{ msg.meta.label }}</span>
                                    </div>
                                    <!-- eslint-disable-next-line vue/no-v-html -->
                                    <div v-if="msg.role === 'assistant'" class="leading-relaxed" v-html="renderMarkdown(msg.text)" />
                                    <p v-else class="leading-relaxed">{{ msg.text }}</p>
                                    <p
                                        class="mt-1 text-[10px]"
                                        :class="msg.role === 'user' ? 'text-primary-content/60 text-right' : 'text-base-content/40'"
                                    >{{ formatTime(msg.ts) }}</p>
                                </div>
                            </div>

                            <!-- Typing indicator -->
                            <div v-if="chatLoading" class="flex justify-start">
                                <div class="rounded-xl bg-base-200 px-4 py-3">
                                    <div class="flex items-center gap-1">
                                        <span class="inline-block h-2 w-2 rounded-full bg-base-content/40 animate-bounce [animation-delay:0ms]" />
                                        <span class="inline-block h-2 w-2 rounded-full bg-base-content/40 animate-bounce [animation-delay:150ms]" />
                                        <span class="inline-block h-2 w-2 rounded-full bg-base-content/40 animate-bounce [animation-delay:300ms]" />
                                    </div>
                                    <p class="mt-2 text-[11px] text-base-content/55">Assistant sedang memproses intent dan membaca data yang diperlukan.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Quick reply chips -->
                        <div class="border-t border-base-300 px-3 py-2 sm:px-4">
                            <div class="space-y-2">
                                <div v-for="group in quickReplyGroups" :key="group.title" class="space-y-1">
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-base-content/45">{{ group.title }}</p>
                                    <div class="flex gap-1.5 overflow-x-auto sm:flex-wrap">
                                        <button
                                            v-for="chip in group.chips"
                                            :key="chip"
                                            class="badge badge-outline badge-sm shrink-0 cursor-pointer transition-colors hover:border-primary hover:text-primary"
                                            :disabled="chatLoading"
                                            @click="sendChatMessage(chip)"
                                        >{{ chip }}</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Input bar -->
                        <div class="border-t border-base-300 p-2 sm:p-3">
                            <div class="flex items-center gap-2">
                                <input
                                    ref="chatInputRef"
                                    v-model="chatInput"
                                    type="text"
                                    class="input input-bordered input-sm w-full"
                                    placeholder="Tulis pertanyaan, mis. stok kabel lan atau invoice belum dibayar"
                                    @keyup.enter="sendChatMessage()"
                                />
                                <button class="btn btn-primary btn-sm" :disabled="chatLoading || !chatInput.trim()" @click="sendChatMessage()">
                                    <PaperAirplaneIcon class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <button class="btn btn-primary btn-circle shadow-xl sm:btn-wide sm:rounded-full sm:px-4" @click="toggleChatPanel">
                    <ChatBubbleLeftRightIcon class="h-5 w-5" />
                    <span class="hidden sm:inline">{{ chatPanelOpen ? 'Tutup Chat' : 'Assistant' }}</span>
                </button>
            </div>
        </div>
    </div>
</template>
