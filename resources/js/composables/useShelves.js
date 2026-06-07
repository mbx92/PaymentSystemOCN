import { ref } from 'vue';

export function useShelves() {
    const shelves = ref([]);
    const selectedShelf = ref(null);
    const shelfItems = ref(null);
    const loadingShelves = ref(false);
    const loadingItems = ref(false);
    const saving = ref(false);

    async function fetchShelves() {
        loadingShelves.value = true;
        try {
            const { data } = await axios.get('/api/shelves');
            shelves.value = data;
        } finally {
            loadingShelves.value = false;
        }
    }

    async function createShelf({ code, name, row, col }) {
        saving.value = true;
        try {
            const { data } = await axios.post('/api/shelves', {
                code,
                name,
                row_position: row,
                col_position: col,
            });
            shelves.value.push(data);
            return data;
        } finally {
            saving.value = false;
        }
    }

    async function updateShelf(shelfId, { code, name, row, col }) {
        saving.value = true;
        try {
            const { data } = await axios.patch(`/api/shelves/${shelfId}`, {
                code,
                name,
                row_position: row,
                col_position: col,
            });
            const idx = shelves.value.findIndex(s => s.id === shelfId);
            if (idx !== -1) {
                shelves.value[idx] = { ...shelves.value[idx], ...data };
            }
            return data;
        } finally {
            saving.value = false;
        }
    }

    async function deleteShelf(shelfId) {
        try {
            await axios.delete(`/api/shelves/${shelfId}`);
            shelves.value = shelves.value.filter(s => s.id !== shelfId);
            if (selectedShelf.value?.id === shelfId) {
                selectedShelf.value = null;
                shelfItems.value = null;
            }
        } catch (e) {
            console.error('Failed to delete shelf', e);
        }
    }

    async function fetchShelfItems(shelfId) {
        loadingItems.value = true;
        try {
            const { data } = await axios.get(`/api/shelves/${shelfId}/items`);
            shelfItems.value = data;
        } finally {
            loadingItems.value = false;
        }
    }

    async function selectShelf(shelf) {
        selectedShelf.value = shelf;
        shelfItems.value = null;
        await fetchShelfItems(shelf.id);
    }

    async function createSlot(shelfId, { tier, slotPosition, productId, qty, minQty }) {
        saving.value = true;
        try {
            await axios.post(`/api/shelves/${shelfId}/slots`, {
                tier,
                slot_position: slotPosition,
                product_id: productId || null,
                qty,
                min_qty: minQty,
            });
            await fetchShelfItems(shelfId);
        } finally {
            saving.value = false;
        }
    }

    async function moveSlot(shelfId, slotId, slotPosition) {
        try {
            await axios.patch(`/api/shelf-slots/${slotId}/position`, { slot_position: slotPosition });
            await fetchShelfItems(shelfId);
        } catch (e) {
            console.error('Failed to move slot', e);
        }
    }

    async function deleteSlot(slotId, shelfId) {
        try {
            await axios.delete(`/api/shelf-slots/${slotId}`);
            await fetchShelfItems(shelfId);
        } catch (e) {
            console.error('Failed to delete slot', e);
        }
    }

    function shelfColor(shelf) {
        if (!shelf._aggregated) return 'bg-gray-300';
        if (shelf._aggregated.hasEmpty) return 'bg-red-500';
        if (shelf._aggregated.hasLow) return 'bg-yellow-500';
        return 'bg-green-500';
    }

    function shelfStatusBadge(shelf) {
        if (!shelf._aggregated) return null;
        if (shelf._aggregated.hasEmpty) return { color: 'badge-error', label: 'Stok Habis' };
        if (shelf._aggregated.hasLow) return { color: 'badge-warning', label: 'Stok Menipis' };
        return { color: 'badge-success', label: 'Stok Aman' };
    }

    async function updatePosition(shelfId, row, col) {
        try {
            const { data } = await axios.patch(`/api/shelves/${shelfId}/position`, {
                row_position: row,
                col_position: col,
            });
            const idx = shelves.value.findIndex(s => s.id === shelfId);
            if (idx !== -1) {
                shelves.value[idx] = { ...shelves.value[idx], row: data.row, col: data.col };
            }
        } catch (e) {
            console.error('Failed to update shelf position', e);
        }
    }

    async function saveSlotQuantity(shelfId, slotId, qty) {
        try {
            await axios.patch(`/api/shelf-slots/${slotId}`, { qty });
            await fetchShelfItems(shelfId);
        } catch (e) {
            console.error('Failed to update slot quantity', e);
        }
    }

    return {
        shelves,
        selectedShelf,
        shelfItems,
        loadingShelves,
        loadingItems,
        saving,
        fetchShelves,
        createShelf,
        updateShelf,
        deleteShelf,
        fetchShelfItems,
        selectShelf,
        createSlot,
        deleteSlot,
        moveSlot,
        shelfColor,
        shelfStatusBadge,
        updatePosition,
        saveSlotQuantity,
    };
}
