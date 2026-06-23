<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowDownTrayIcon, ArrowLeftIcon, ArrowPathIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline';
import { computed, onMounted, ref, watch } from 'vue';
import { useCurrency } from '@/composables/useCurrency';

const props = defineProps({
    supplier_name: { type: String, default: '' },
    sheets: { type: Array, default: () => [] },
    last_synced_at: { type: String, default: null },
    sync_schedule: { type: String, default: '02:00' },
});

const { format } = useCurrency();

const activeSheet = ref(props.sheets[0]?.key ?? '');
const search = ref('');
const items = ref([]);
const loading = ref(false);
const syncing = ref(false);
const error = ref('');
const syncMessage = ref('');
const lastSyncedAt = ref(props.last_synced_at);

const activeSheetLabel = computed(() => props.sheets.find((s) => s.key === activeSheet.value)?.label ?? activeSheet.value);

function formatDateTime(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
}

async function fetchItems() {
    if (!activeSheet.value) return;
    loading.value = true;
    error.value = '';
    try {
        const params = search.value.trim() ? { q: search.value.trim() } : {};
        const { data } = await axios.get(`/api/supplier-catalog/${activeSheet.value}/items`, { params });
        items.value = data.items ?? [];
        if (data.last_synced_at) {
            lastSyncedAt.value = data.last_synced_at;
        }
    } catch (err) {
        error.value = err?.response?.data?.message ?? 'Gagal memuat katalog dari database.';
        items.value = [];
    } finally {
        loading.value = false;
    }
}

async function runSync() {
    if (syncing.value) return;
    syncing.value = true;
    error.value = '';
    syncMessage.value = '';
    try {
        const { data } = await axios.post('/api/supplier-catalog/sync', {}, { timeout: 300000 });
        if (data.last_synced_at) {
            lastSyncedAt.value = data.last_synced_at;
        }
        syncMessage.value = data.message ?? 'Sync selesai.';
        await fetchItems();
    } catch (err) {
        error.value = err?.response?.data?.message ?? 'Gagal sync katalog dari Google Sheets.';
    } finally {
        syncing.value = false;
    }
}

let timer;
watch([activeSheet, search], () => {
    clearTimeout(timer);
    timer = setTimeout(fetchItems, 250);
});

watch(activeSheet, () => {
    search.value = '';
});

onMounted(fetchItems);
</script>

<template>
    <Head title="Katalog Supplier" />
    <AppLayout>
        <div class="space-y-5">
            <div class="ocn-panel">
                <div class="ocn-panel__head">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Projects Workspace</p>
                            <h1 class="ocn-panel__title mt-1">Katalog Supplier</h1>
                            <p class="ocn-panel__desc mt-1">
                                {{ supplier_name }} — data disimpan lokal dan di-sync dari Google Sheets setiap hari
                                ({{ sync_schedule }} WIB). Terakhir sync: {{ formatDateTime(lastSyncedAt) }}.
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button class="btn btn-outline btn-sm" :disabled="loading || syncing" @click="fetchItems">
                                <ArrowPathIcon class="size-4" />
                                Refresh
                            </button>
                            <button
                                class="btn btn-primary btn-sm"
                                :disabled="loading || syncing"
                                title="Tarik semua brand dari Google Sheets"
                                @click="runSync"
                            >
                                <span v-if="syncing" class="loading loading-spinner loading-xs"></span>
                                <ArrowDownTrayIcon v-else class="size-4" />
                                Sync
                            </button>
                            <Link class="btn btn-ghost btn-sm gap-1.5" :href="route('erp.projects')">
                                <ArrowLeftIcon class="size-4" />
                                Back
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="syncMessage" class="alert alert-success alert-soft">{{ syncMessage }}</div>

            <div class="ocn-panel">
                <div class="ocn-panel__head">
                    <h2 class="ocn-panel__title">Daftar harga — {{ activeSheetLabel }}</h2>
                    <p class="ocn-panel__desc">{{ items.length }} item ditemukan</p>
                </div>
                <div class="card-body border-b border-base-200 space-y-3">
                    <div class="flex flex-wrap items-end gap-3">
                        <div class="form-control w-full max-w-xs">
                            <label class="label py-1"><span class="label-text text-xs font-medium">Brand</span></label>
                            <select v-model="activeSheet" class="select select-bordered select-sm w-full">
                                <option v-for="sheet in sheets" :key="sheet.key" :value="sheet.key">{{ sheet.label }}</option>
                            </select>
                        </div>
                        <label class="input input-bordered input-sm flex items-center gap-2 max-w-md flex-1 min-w-[200px]">
                            <MagnifyingGlassIcon class="size-4 opacity-50 shrink-0" />
                            <input v-model="search" type="text" placeholder="Cari kode, nama, jenis..." class="grow min-w-0" />
                        </label>
                    </div>
                </div>

                <div v-if="error" class="alert alert-error alert-soft m-4">{{ error }}</div>
                <div v-if="loading" class="flex justify-center py-16">
                    <span class="loading loading-spinner loading-lg text-primary"></span>
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="table table-sm table-zebra">
                        <thead>
                            <tr>
                                <th>Kode</th>
                                <th>Nama Item</th>
                                <th>Jenis</th>
                                <th class="text-right">Harga Sebelumnya</th>
                                <th class="text-right">Harga Supplier</th>
                                <th>Last Update</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in items" :key="item.ref">
                                <td class="font-mono text-xs">{{ item.code }}</td>
                                <td>{{ item.name }}</td>
                                <td><span class="badge badge-ghost badge-sm">{{ item.category }}</span></td>
                                <td class="text-right tabular-nums text-base-content/60">
                                    {{ item.last_price != null ? format(item.last_price) : '—' }}
                                </td>
                                <td class="text-right font-medium tabular-nums">{{ format(item.supplier_price) }}</td>
                                <td class="text-xs text-base-content/70 whitespace-nowrap">{{ formatDateTime(item.last_synced_at) }}</td>
                            </tr>
                            <tr v-if="!items.length">
                                <td colspan="6" class="text-center py-8 text-base-content/50">
                                    Tidak ada item. Jalankan <code class="text-xs">php artisan supplier-catalog:sync</code> untuk mengisi data.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="ocn-panel">
                <div class="ocn-panel__head">
                    <h2 class="ocn-panel__title">Cara pakai</h2>
                </div>
                <div class="card-body text-sm text-base-content/70 space-y-2">
                    <p>1. Buat budget project, lalu buka halaman detail budget.</p>
                    <p>2. Pilih item dari <strong>Katalog Supplier</strong> — harga beli (HPP) terisi otomatis dari katalog.</p>
                    <p>3. Atur harga jual per item untuk margin, lalu simpan.</p>
                    <p>4. Saat customer setuju (<strong>Tandai Deal</strong>), item katalog otomatis dipromosikan ke Master Produk.</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
