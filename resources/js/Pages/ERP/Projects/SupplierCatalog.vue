<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeftIcon, ArrowPathIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline';
import { computed, onMounted, ref, watch } from 'vue';
import { useCurrency } from '@/composables/useCurrency';

const props = defineProps({
    supplier_name: { type: String, default: '' },
    sheets: { type: Array, default: () => [] },
});

const { format } = useCurrency();

const activeSheet = ref(props.sheets[0]?.key ?? '');
const search = ref('');
const items = ref([]);
const loading = ref(false);
const error = ref('');

const activeSheetLabel = computed(() => props.sheets.find((s) => s.key === activeSheet.value)?.label ?? activeSheet.value);

async function fetchItems() {
    if (!activeSheet.value) return;
    loading.value = true;
    error.value = '';
    try {
        const params = search.value.trim() ? { q: search.value.trim() } : {};
        const { data } = await axios.get(`/api/supplier-catalog/${activeSheet.value}/items`, { params });
        items.value = data.items ?? [];
    } catch (err) {
        error.value = err?.response?.data?.message ?? 'Gagal memuat katalog. Pastikan Google Sheet dapat diakses (view publik).';
        items.value = [];
    } finally {
        loading.value = false;
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
                                Data live dari Google Sheets — {{ supplier_name }}. Tidak di-import; selalu mengikuti update sheet supplier.
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button class="btn btn-outline btn-sm" :disabled="loading" @click="fetchItems">
                                <ArrowPathIcon class="size-4" />
                                Refresh
                            </button>
                            <Link class="btn btn-ghost btn-sm gap-1.5" :href="route('erp.projects')">
                                <ArrowLeftIcon class="size-4" />
                                Back
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

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
                                <th class="text-right">Harga Supplier</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in items" :key="item.ref">
                                <td class="font-mono text-xs">{{ item.code }}</td>
                                <td>{{ item.name }}</td>
                                <td><span class="badge badge-ghost badge-sm">{{ item.category }}</span></td>
                                <td class="text-right font-medium tabular-nums">{{ format(item.supplier_price) }}</td>
                            </tr>
                            <tr v-if="!items.length">
                                <td colspan="4" class="text-center py-8 text-base-content/50">Tidak ada item yang cocok.</td>
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
                    <p>2. Pilih item dari <strong>Katalog Supplier</strong> — harga beli (HPP) terisi otomatis dari sheet.</p>
                    <p>3. Atur harga jual per item untuk margin, lalu simpan.</p>
                    <p>4. Saat customer setuju (<strong>Tandai Deal</strong>), item katalog otomatis dipromosikan ke Master Produk.</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
