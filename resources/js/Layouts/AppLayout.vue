<script setup>
import { ref, computed, nextTick } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import {
    HomeIcon, CodeBracketIcon, ArrowDownCircleIcon, ArrowUpCircleIcon, ChartBarIcon,
    UsersIcon, Bars3Icon, XMarkIcon, ArrowRightOnRectangleIcon, BuildingOffice2Icon, BellAlertIcon,
    ShoppingCartIcon, ArchiveBoxIcon, UserCircleIcon, BanknotesIcon, CircleStackIcon, ChatBubbleLeftRightIcon, PaperAirplaneIcon,
    TrashIcon,
    WalletIcon,
    NewspaperIcon,
    PhotoIcon,
    GlobeAltIcon,
    ShareIcon,
    CalendarDaysIcon,
} from '@heroicons/vue/24/outline';
import FlashMessage from '@/Components/FlashMessage.vue';

const page = usePage();
const auth = computed(() => page.props.auth);
const flash = computed(() => page.props.flash);
const inventoryAlerts = computed(() => page.props.inventoryAlerts ?? { lowStockCount: 0, lowStockItems: [] });
const erpSetting = computed(() => page.props.erpSetting ?? {});
const sidebarOpen = ref(false);
const showAlertDropdown = ref(false);
const chatPanelOpen = ref(false);
const chatInput = ref('');
const chatLoading = ref(false);
const chatBodyRef = ref(null);
const chatInputRef = ref(null);

const CHAT_STORAGE_KEY = 'erp_chat_history';
const WELCOME_MSG = {
    role: 'assistant',
    text: 'Halo! 👋 Saya asisten ERP Anda.\nSaya bisa bantu cek **stok**, **harga**, **penjualan**, **cashflow**, **invoice**, dan lainnya.\n\nKetik **bantuan** atau klik chip di bawah untuk mulai.',
    ts: Date.now(),
};

const quickReplies = [
    'bantuan',
    'pos hari ini',
    'pos kemarin',
    'cashflow hari ini',
    'stok rendah',
    'produk terlaris',
    'invoice belum dibayar',
    'invoice jatuh tempo',
    'project aktif',
    'biaya operasional',
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

const sidebarModules = computed(() => {
    const role = auth.value?.user?.role;
    const modules = [{ title: 'Main', items: [{ name: 'Dashboard', href: route('dashboard'), icon: HomeIcon }] }];

    if (role === 'admin' || role === 'manajer') {
        modules.push({
            title: 'Modul ERP',
            items: [
                { name: 'Accounting', href: route('erp.accounting'), icon: ArrowDownCircleIcon },
                { name: 'Sales', href: route('erp.sales'), icon: BanknotesIcon },
                { name: 'Purchasing', href: route('erp.purchasing'), icon: ShoppingCartIcon },
                { name: 'Inventory', href: route('erp.inventory'), icon: ArchiveBoxIcon },
                { name: 'Projects', href: route('erp.projects'), icon: CodeBracketIcon },
                { name: 'HR', href: route('erp.hr'), icon: UserCircleIcon },
                { name: 'CRM', href: route('erp.crm'), icon: ShareIcon },
                { name: 'Calendar', href: route('erp.calendar'), icon: CalendarDaysIcon },
                { name: 'Reporting', href: route('erp.reporting'), icon: ChartBarIcon },
            ],
        });
    }

    if (role === 'admin') {
        modules.push({
            title: 'Website CMS',
            items: [
                { name: 'Dashboard CMS', href: route('erp.cms'), icon: NewspaperIcon },
                { name: 'Landing sites', href: route('erp.cms.sites'), icon: GlobeAltIcon },
                { name: 'Media library', href: route('erp.cms.media'), icon: PhotoIcon },
            ],
        });
    }

    if (role === 'admin' || role === 'manajer') {
        modules.push({
            title: 'Personal',
            items: [
                { name: 'Beranda', href: route('personal'), icon: WalletIcon },
            ],
        });
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
    if (pathname.includes('/erp/accounting/cashflow')) return { label: 'Cashflow Workspace', subtitle: 'Akses kas masuk dan kas keluar dalam satu submenu.' };
    if (pathname.includes('/erp/accounting/payments')) return { label: 'Pembayaran Workspace', subtitle: 'Pusat pembayaran project dan tim.' };
    if (pathname.includes('/erp/accounting/reconciliation')) return { label: 'Rekonsiliasi Workspace', subtitle: 'Kontrol mutasi kas/bank harian dan mingguan.' };
    if (pathname.includes('/kas-masuk') || pathname.includes('/kas-keluar')) return { label: 'Accounting Workspace', subtitle: 'Kelola transaksi kas dan posting jurnal terintegrasi.' };
    if (pathname.includes('/projects')) return { label: 'Projects Workspace', subtitle: 'Pantau proyek, termin pembayaran, dan profitabilitas.' };
    if (pathname.includes('/erp/hr/legal')) return { label: 'Legal Workspace', subtitle: 'File manager dokumen legal di server.' };
    if (pathname.includes('/erp/calendar')) return { label: 'Calendar Workspace', subtitle: 'Jadwal event project, PO, pipeline, dan follow-up.' };
    if (pathname.includes('/erp/crm')) return { label: 'CRM Workspace', subtitle: 'Kelola prospek, customer, dan aktivitas follow-up.' };
    if (pathname.startsWith('/personal')) return { label: 'Personal Workspace', subtitle: 'Pencatatan keuangan pribadi dan keluarga.' };
    if (pathname.startsWith('/erp/cms')) return { label: 'Website CMS', subtitle: 'Konten landing publik, media, dan publikasi halaman.' };

    return { label: 'ERP Command Center', subtitle: 'Satu dashboard untuk finance, project, dan operasional.' };
});

const isPosFullscreen = computed(() => page.url.split('?')[0].includes('/erp/sales/pos'));

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
            chatMessages.value.push({ role: 'assistant', text: answer, ts: Date.now() });
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
</script>

<template>
    <div class="min-h-screen ocn-shell">
        <div v-if="sidebarOpen" class="fixed inset-0 z-40 bg-black/50 lg:hidden" @click="sidebarOpen = false" />

        <aside
            :class="['fixed inset-y-0 left-0 z-50 w-72 ocn-sidebar flex flex-col transition-transform duration-300',
                sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0']"
        >
            <div class="flex items-center gap-3 px-6 py-5 border-b border-white/10">
                <div v-if="erpSetting?.app_logo_url" class="w-10 h-10 rounded-xl overflow-hidden bg-white/95 flex items-center justify-center p-1">
                    <img :src="erpSetting.app_logo_url" alt="Logo" class="w-full h-full object-contain">
                </div>
                <div v-else class="w-10 h-10 ocn-brand-mark text-white rounded-xl flex items-center justify-center">
                    <span class="font-bold text-sm">ERP</span>
                </div>
                <div>
                    <span class="block font-bold text-lg tracking-tight text-white leading-none">{{ erpSetting?.app_name || 'OCN ERP Suite' }}</span>
                    <span class="block text-xs text-slate-400 mt-1">{{ erpSetting?.app_tagline || 'Integrated Business Platform' }}</span>
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
                                <div class="flex items-center gap-1 rounded-xl bg-base-200 px-4 py-3">
                                    <span class="inline-block h-2 w-2 rounded-full bg-base-content/40 animate-bounce [animation-delay:0ms]" />
                                    <span class="inline-block h-2 w-2 rounded-full bg-base-content/40 animate-bounce [animation-delay:150ms]" />
                                    <span class="inline-block h-2 w-2 rounded-full bg-base-content/40 animate-bounce [animation-delay:300ms]" />
                                </div>
                            </div>
                        </div>

                        <!-- Quick reply chips -->
                        <div class="flex gap-1.5 overflow-x-auto border-t border-base-300 px-3 py-2 sm:flex-wrap">
                            <button
                                v-for="chip in quickReplies"
                                :key="chip"
                                class="badge badge-outline badge-sm shrink-0 cursor-pointer hover:badge-primary transition-colors"
                                :disabled="chatLoading"
                                @click="sendChatMessage(chip)"
                            >{{ chip }}</button>
                        </div>

                        <!-- Input bar -->
                        <div class="border-t border-base-300 p-2 sm:p-3">
                            <div class="flex items-center gap-2">
                                <input
                                    ref="chatInputRef"
                                    v-model="chatInput"
                                    type="text"
                                    class="input input-bordered input-sm w-full"
                                    placeholder="Tulis pertanyaan..."
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
