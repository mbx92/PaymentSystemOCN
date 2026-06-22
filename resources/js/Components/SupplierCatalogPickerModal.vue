<script setup>
import Modal from '@/Components/Modal.vue';
import { computed, ref, watch } from 'vue';
import { useCurrency } from '@/composables/useCurrency';

const props = defineProps({
    show: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'confirm']);

const { format } = useCurrency();

const sheets = ref([]);
const supplierName = ref('');
const activeSheet = ref('');
const search = ref('');
const items = ref([]);
const loadingSheets = ref(false);
const loadingItems = ref(false);
const error = ref('');
const selectedRef = ref('');

const visibleItems = computed(() => items.value);

async function loadSheets() {
    loadingSheets.value = true;
    error.value = '';
    try {
        const { data } = await axios.get('/api/supplier-catalog/sheets');
        sheets.value = data.sheets ?? [];
        supplierName.value = data.supplier_name ?? '';
        if (!activeSheet.value && sheets.value.length) {
            activeSheet.value = sheets.value[0].key;
        } else if (sheets.value.length && !sheets.value.some((s) => s.key === activeSheet.value)) {
            activeSheet.value = sheets.value[0].key;
        }
    } catch (err) {
        error.value = err?.response?.data?.message ?? 'Gagal memuat daftar tab katalog.';
    } finally {
        loadingSheets.value = false;
    }
}

async function loadItems() {
    if (!activeSheet.value) return;
    loadingItems.value = true;
    error.value = '';
    try {
        const params = search.value.trim() ? { q: search.value.trim() } : {};
        const { data } = await axios.get(`/api/supplier-catalog/${activeSheet.value}/items`, { params });
        items.value = data.items ?? [];
    } catch (err) {
        error.value = err?.response?.data?.message ?? 'Gagal memuat item katalog.';
        items.value = [];
    } finally {
        loadingItems.value = false;
    }
}

let timer;
watch(search, () => {
    clearTimeout(timer);
    timer = setTimeout(loadItems, 250);
});

watch(activeSheet, () => {
    selectedRef.value = '';
    loadItems();
});

watch(
    () => props.show,
    (open) => {
        if (!open) return;
        search.value = '';
        selectedRef.value = '';
        loadSheets().then(loadItems);
    },
);

function confirmSelection() {
    const item = items.value.find((row) => row.ref === selectedRef.value);
    if (!item) return;
    emit('confirm', item);
}
</script>

<template>
    <Modal :show="show" max-width="4xl" @close="emit('close')">
        <div class="p-6 space-y-4">
            <div>
                <h3 class="text-lg font-bold">Pilih dari Katalog Supplier</h3>
                <p class="text-sm text-base-content/60 mt-1">
                    {{ supplierName || 'Supplier' }} — data live dari Google Sheets
                </p>
            </div>

            <div v-if="error" class="alert alert-error alert-soft py-2 text-sm">{{ error }}</div>

            <div class="flex flex-wrap items-end gap-3">
                <div class="form-control w-full max-w-[220px]">
                    <label class="label py-0"><span class="label-text text-xs font-medium">Brand</span></label>
                    <select v-model="activeSheet" class="select select-bordered select-sm w-full" :disabled="loadingSheets || !sheets.length">
                        <option v-for="sheet in sheets" :key="sheet.key" :value="sheet.key">{{ sheet.label }}</option>
                    </select>
                </div>
                <input
                    v-model="search"
                    type="text"
                    class="input input-bordered input-sm flex-1 min-w-[200px]"
                    placeholder="Cari kode / nama / jenis..."
                />
            </div>

            <div v-if="loadingSheets || loadingItems" class="flex justify-center py-10">
                <span class="loading loading-spinner loading-md text-primary"></span>
            </div>

            <div v-else class="overflow-x-auto max-h-80 border border-base-300 rounded-lg">
                <table class="table table-sm table-pin-rows">
                    <thead>
                        <tr>
                            <th class="w-10"></th>
                            <th>Kode</th>
                            <th>Nama</th>
                            <th>Jenis</th>
                            <th class="text-right">Harga Supplier</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="item in visibleItems"
                            :key="item.ref"
                            class="cursor-pointer hover"
                            @click="selectedRef = item.ref"
                        >
                            <td>
                                <input
                                    type="radio"
                                    name="catalog_pick"
                                    class="radio radio-sm radio-primary"
                                    :checked="selectedRef === item.ref"
                                    @change="selectedRef = item.ref"
                                />
                            </td>
                            <td class="font-mono text-xs">{{ item.code }}</td>
                            <td>{{ item.name }}</td>
                            <td><span class="badge badge-ghost badge-xs">{{ item.category }}</span></td>
                            <td class="text-right tabular-nums">{{ format(item.supplier_price) }}</td>
                        </tr>
                        <tr v-if="!visibleItems.length">
                            <td colspan="5" class="text-center py-6 text-base-content/50">Tidak ada item.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" class="btn btn-ghost btn-sm" @click="emit('close')">Batal</button>
                <button type="button" class="btn btn-primary btn-sm" :disabled="!selectedRef" @click="confirmSelection">
                    Tambah ke Budget
                </button>
            </div>
        </div>
    </Modal>
</template>
