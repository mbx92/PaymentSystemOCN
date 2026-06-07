<script setup>
import { onMounted, ref } from 'vue';
import { PlusIcon, ArrowLeftIcon, TrashIcon, PencilSquareIcon } from '@heroicons/vue/24/outline';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SVGMap from '@/Components/SVGMap.vue';
import ShelfDetail from '@/Components/ShelfDetail.vue';
import { useShelves } from '@/composables/useShelves.js';

const {
    shelves,
    selectedShelf,
    shelfItems,
    loadingShelves,
    loadingItems,
    saving,
    fetchShelves,
    createShelf,
    deleteShelf,
    selectShelf,
    createSlot,
    deleteSlot,
    updatePosition,
    moveSlot,
    updateShelf,
} = useShelves();

const selectedShelfId = ref(null);

// Create Shelf modal
const showCreateModal = ref(false);
const createForm = ref({ code: '', name: '', row: 0, col: 0 });
const createError = ref('');

// Master products lookup
const products = ref([]);
const loadingProducts = ref(false);

// Edit Shelf modal
const showEditModal = ref(false);
const editingShelf = ref(null);
const editForm = ref({ code: '', name: '', row: 0, col: 0 });
const editError = ref('');

onMounted(async () => {
    await fetchShelves();
});

async function handleSelect(shelf) {
    selectedShelfId.value = shelf.id;
    await selectShelf(shelf);
}

async function handleUpdatePosition({ id, row, col }) {
    await updatePosition(id, row, col);
    await fetchShelves();
}

function handleCloseDetail() {
    selectedShelfId.value = null;
}

function openCreateModal() {
    createForm.value = { code: '', name: '', row: 0, col: 0 };
    createError.value = '';
    showCreateModal.value = true;
}

async function handleCreateShelf() {
    createError.value = '';
    if (!createForm.value.code.trim() || !createForm.value.name.trim()) {
        createError.value = 'Kode dan nama rak wajib diisi.';
        return;
    }

    try {
        await createShelf({
            code: createForm.value.code.trim(),
            name: createForm.value.name.trim(),
            row: createForm.value.row,
            col: createForm.value.col,
        });
        showCreateModal.value = false;
    } catch (e) {
        if (e.response?.status === 422) {
            createError.value = Object.values(e.response.data.errors).flat().join(', ');
        } else {
            createError.value = 'Gagal membuat rak. Silakan coba lagi.';
        }
    }
}

async function handleDeleteShelf(shelfId) {
    if (confirm('Hapus rak ini beserta semua slot isinya?')) {
        await deleteShelf(shelfId);
    }
}

async function fetchProducts() {
    if (products.value.length > 0) return;
    loadingProducts.value = true;
    try {
        const { data } = await axios.get('/api/products/search');
        products.value = data;
    } catch {
        products.value = [];
    } finally {
        loadingProducts.value = false;
    }
}

async function handleCreateSlot({ tier, slotPosition, productId, qty, minQty }) {
    if (!selectedShelf.value) return;
    await createSlot(selectedShelf.value.id, { tier, slotPosition, productId, qty, minQty });
}

async function handleDeleteSlot(slotId) {
    if (!selectedShelf.value) return;
    if (confirm('Hapus slot ini?')) {
        await deleteSlot(slotId, selectedShelf.value.id);
    }
}

async function handleMoveSlot({ slotId, tier, fromPosition, toPosition }) {
    if (!selectedShelf.value) return;
    await moveSlot(selectedShelf.value.id, slotId, toPosition);
}

function openEditModal(shelf) {
    editingShelf.value = shelf;
    editForm.value = {
        code: shelf.code,
        name: shelf.name,
        row: shelf.row,
        col: shelf.col,
    };
    editError.value = '';
    showEditModal.value = true;
}

async function handleUpdateShelf() {
    editError.value = '';
    if (!editForm.value.code.trim() || !editForm.value.name.trim()) {
        editError.value = 'Kode dan nama rak wajib diisi.';
        return;
    }

    try {
        await updateShelf(editingShelf.value.id, {
            code: editForm.value.code.trim(),
            name: editForm.value.name.trim(),
            row: editForm.value.row,
            col: editForm.value.col,
        });
        showEditModal.value = false;
    } catch (e) {
        if (e.response?.status === 422) {
            editError.value = Object.values(e.response.data.errors).flat().join(', ');
        } else {
            editError.value = 'Gagal mengupdate rak.';
        }
    }
}
</script>

<template>
    <Head title="Inventory - Peta Rak" />
    <AppLayout>
        <div class="p-4 sm:p-6 space-y-5">

            <!-- Card 1: Page Hero -->
            <div class="ocn-panel">
                <div class="ocn-panel__head">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Inventory Workspace</p>
                            <h1 class="ocn-panel__title mt-1">Store Shelf Mapping</h1>
                            <p class="ocn-panel__desc mt-1">Visualisasi peta rak toko, status stok barang, dan manajemen slot per tingkat.</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button class="btn btn-sm btn-primary" @click="openCreateModal">
                                <PlusIcon class="size-4" />
                                Tambah Rak
                            </button>
                            <Link class="btn btn-ghost btn-sm gap-1.5" :href="route('erp.inventory')">
                                <ArrowLeftIcon class="size-4" />
                                Back
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card 2: Summary Stats -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="ocn-panel">
                    <div class="ocn-panel__head py-3"><h2 class="ocn-panel__title text-sm font-medium">Total Rak</h2></div>
                    <div class="card-body p-5 pt-2"><p class="text-2xl font-bold text-primary">{{ shelves.length }}</p></div>
                </div>
                <div class="ocn-panel">
                    <div class="ocn-panel__head py-3"><h2 class="ocn-panel__title text-sm font-medium">Rak Aman</h2></div>
                    <div class="card-body p-5 pt-2"><p class="text-2xl font-bold text-green-500">{{ shelves.filter(s => s._aggregated && !s._aggregated.hasEmpty && !s._aggregated.hasLow).length }}</p></div>
                </div>
                <div class="ocn-panel">
                    <div class="ocn-panel__head py-3"><h2 class="ocn-panel__title text-sm font-medium">Rak Menipis</h2></div>
                    <div class="card-body p-5 pt-2"><p class="text-2xl font-bold text-yellow-500">{{ shelves.filter(s => s._aggregated && s._aggregated.hasLow && !s._aggregated.hasEmpty).length }}</p></div>
                </div>
                <div class="ocn-panel">
                    <div class="ocn-panel__head py-3"><h2 class="ocn-panel__title text-sm font-medium">Rak Habis</h2></div>
                    <div class="card-body p-5 pt-2"><p class="text-2xl font-bold text-red-500">{{ shelves.filter(s => s._aggregated && s._aggregated.hasEmpty).length }}</p></div>
                </div>
            </div>

            <!-- Card 3: Map + Daftar Rak -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                <!-- Left: Store Map -->
                <div class="lg:col-span-2">
                    <SVGMap
                        :shelves="shelves"
                        :loading="loadingShelves"
                        @select="handleSelect"
                        @update-position="handleUpdatePosition"
                    />
                </div>

                <!-- Right: Shelf list table -->
                <div class="lg:col-span-1">
                    <div class="ocn-panel">
                        <div class="ocn-panel__head">
                            <h2 class="ocn-panel__title">Daftar Rak</h2>
                        </div>
                        <div v-if="shelves.length === 0" class="p-8 text-center text-base-content/40 text-sm">
                            Belum ada rak. Klik "Tambah Rak" untuk membuat.
                        </div>
                        <div v-else class="p-3 space-y-2">
                            <div
                                v-for="shelf in shelves"
                                :key="shelf.id"
                                :class="[
                                    'flex items-center gap-3 p-3 rounded-lg border transition-colors cursor-pointer',
                                    selectedShelfId === shelf.id
                                        ? 'border-primary bg-primary/5'
                                        : 'border-base-300 bg-base-100 hover:border-base-content/20',
                                ]"
                                @click="handleSelect(shelf)"
                            >
                                <!-- Status indicator dot -->
                                <div class="flex-shrink-0">
                                    <span
                                        v-if="shelf._aggregated?.hasEmpty"
                                        class="inline-block w-3 h-3 rounded-full bg-red-500"
                                    ></span>
                                    <span
                                        v-else-if="shelf._aggregated?.hasLow"
                                        class="inline-block w-3 h-3 rounded-full bg-yellow-500"
                                    ></span>
                                    <span
                                        v-else
                                        class="inline-block w-3 h-3 rounded-full bg-green-500"
                                    ></span>
                                </div>

                                <!-- Info -->
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-baseline gap-2">
                                        <span class="font-mono font-bold text-sm">{{ shelf.code }}</span>
                                        <span class="text-xs text-base-content/60 truncate">{{ shelf.name }}</span>
                                    </div>
                                </div>

                                <!-- Meta -->
                                <div class="flex-shrink-0 flex items-center gap-2">
                                    <span
                                        v-if="shelf._aggregated?.hasEmpty"
                                        class="badge badge-error badge-xs"
                                    >Habis</span>
                                    <span
                                        v-else-if="shelf._aggregated?.hasLow"
                                        class="badge badge-warning badge-xs"
                                    >Menipis</span>
                                    <span
                                        v-else
                                        class="badge badge-success badge-xs"
                                    >Aman</span>

                                    <button
                                        class="btn btn-ghost btn-xs btn-square"
                                        title="Edit rak"
                                        @click.stop="openEditModal(shelf)"
                                    >
                                        <PencilSquareIcon class="size-3.5" />
                                    </button>
                                    <button
                                        class="btn btn-ghost btn-xs btn-square text-error"
                                        title="Hapus rak"
                                        @click.stop="handleDeleteShelf(shelf.id)"
                                    >
                                        <TrashIcon class="size-3.5" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card 4: Shelf Detail (full width) -->
            <div v-if="selectedShelfId">
                <ShelfDetail
                    :data="shelfItems"
                    :loading="loadingItems"
                    :saving="saving"
                    :products="products"
                    :products-loading="loadingProducts"
                    :on-create-slot="handleCreateSlot"
                    :on-delete-slot="handleDeleteSlot"
                    :on-move-slot="handleMoveSlot"
                    :on-focus-products="fetchProducts"
                    :on-close="handleCloseDetail"
                />
            </div>
        </div>

        <!-- Create Shelf Modal -->
        <dialog :open="showCreateModal" class="modal" @click.self="showCreateModal = false">
            <div class="modal-box max-w-sm">
                <h3 class="text-lg font-bold mb-4">Tambah Rak Baru</h3>

                <div v-if="createError" class="alert alert-error alert-soft mb-3 py-2 text-sm">
                    {{ createError }}
                </div>

                <div class="space-y-3">
                    <div>
                        <label class="label py-1"><span class="label-text text-xs font-medium">Kode Rak</span></label>
                        <input
                            v-model="createForm.code"
                            type="text"
                            class="input input-bordered input-sm w-full"
                            placeholder="Contoh: D4"
                            maxlength="20"
                        />
                    </div>
                    <div>
                        <label class="label py-1"><span class="label-text text-xs font-medium">Nama Rak</span></label>
                        <input
                            v-model="createForm.name"
                            type="text"
                            class="input input-bordered input-sm w-full"
                            placeholder="Contoh: Minuman Kaleng"
                        />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="label py-1"><span class="label-text text-xs font-medium">Baris (Row)</span></label>
                            <input v-model.number="createForm.row" type="number" min="0" max="10" class="input input-bordered input-sm w-full" />
                        </div>
                        <div>
                            <label class="label py-1"><span class="label-text text-xs font-medium">Kolom (Col)</span></label>
                            <input v-model.number="createForm.col" type="number" min="0" max="10" class="input input-bordered input-sm w-full" />
                        </div>
                    </div>
                </div>

                <div class="modal-action mt-4">
                    <button class="btn btn-sm btn-ghost" @click="showCreateModal = false">Batal</button>
                    <button class="btn btn-sm btn-primary" :disabled="saving" @click="handleCreateShelf">
                        <span v-if="saving" class="loading loading-spinner loading-xs"></span>
                        Simpan
                    </button>
                </div>
            </div>
        </dialog>

        <!-- Edit Shelf Modal -->
        <dialog :open="showEditModal" class="modal" @click.self="showEditModal = false">
            <div class="modal-box max-w-sm">
                <h3 class="text-lg font-bold mb-4">Edit Rak</h3>

                <div v-if="editError" class="alert alert-error alert-soft mb-3 py-2 text-sm">
                    {{ editError }}
                </div>

                <div class="space-y-3">
                    <div>
                        <label class="label py-1"><span class="label-text text-xs font-medium">Kode Rak</span></label>
                        <input
                            v-model="editForm.code"
                            type="text"
                            class="input input-bordered input-sm w-full"
                            maxlength="20"
                        />
                    </div>
                    <div>
                        <label class="label py-1"><span class="label-text text-xs font-medium">Nama Rak</span></label>
                        <input
                            v-model="editForm.name"
                            type="text"
                            class="input input-bordered input-sm w-full"
                        />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="label py-1"><span class="label-text text-xs font-medium">Baris (Row)</span></label>
                            <input v-model.number="editForm.row" type="number" min="0" max="10" class="input input-bordered input-sm w-full" />
                        </div>
                        <div>
                            <label class="label py-1"><span class="label-text text-xs font-medium">Kolom (Col)</span></label>
                            <input v-model.number="editForm.col" type="number" min="0" max="10" class="input input-bordered input-sm w-full" />
                        </div>
                    </div>
                </div>

                <div class="modal-action mt-4">
                    <button class="btn btn-sm btn-ghost" @click="showEditModal = false">Batal</button>
                    <button class="btn btn-sm btn-primary" :disabled="saving" @click="handleUpdateShelf">
                        <span v-if="saving" class="loading loading-spinner loading-xs"></span>
                        Simpan
                    </button>
                </div>
            </div>
        </dialog>
    </AppLayout>
</template>
