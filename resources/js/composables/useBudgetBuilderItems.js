export const GRID_LAYOUT = {
    cols: 1,
    minRows: 12,
    extraEmptyRows: 2,
};

export function normalizeProductQty(value) {
    return Math.max(1, Math.round(Number(value) || 1));
}

export function normalizeProductAmount(value) {
    return Math.max(0, Math.round(Number(value) || 0));
}

export function emptyBudgetItem() {
    return {
        master_product_id: null,
        catalog_sheet: null,
        catalog_ref: null,
        catalog_category: null,
        item_type: 'material',
        name: '',
        uom: 'unit',
        qty: 1,
        unit_cost: 0,
        unit_price: 0,
        notes: '',
    };
}

export function applyMasterProductToItem(item, product) {
    if (!item || !product) return;
    item.master_product_id = product.id ?? null;
    item.catalog_sheet = null;
    item.catalog_ref = null;
    item.catalog_category = null;
    item.item_type = product.product_type === 'service' ? 'service' : 'material';
    item.name = product.name ?? '';
    item.uom = product.uom ?? 'unit';
    item.unit_cost = normalizeProductAmount(product.unit_cost ?? 0);
    item.unit_price = normalizeProductAmount(product.selling_price ?? product.price ?? 0);
    item.notes = product.sku ? `SKU: ${product.sku}` : '';
}

export function applyCatalogToItem(item, catalogItem) {
    if (!item || !catalogItem) return;
    item.master_product_id = null;
    item.catalog_sheet = catalogItem.sheet_key;
    item.catalog_ref = catalogItem.code;
    item.catalog_category = catalogItem.category;
    item.name = catalogItem.name ?? '';
    item.uom = 'unit';
    item.unit_cost = normalizeProductAmount(catalogItem.supplier_price);
    item.unit_price = normalizeProductAmount(catalogItem.supplier_price);
    item.item_type = /jasa|service/i.test(String(catalogItem.category)) ? 'service' : 'material';
    item.notes = `Katalog: ${catalogItem.supplier_name} / ${catalogItem.sheet_label}`;
}

export function itemMatchKey(item) {
    if (item.catalog_ref) return `catalog:${item.catalog_sheet}:${item.catalog_ref}`;
    if (item.master_product_id) return `master:${item.master_product_id}`;
    return `name:${String(item.name ?? '').trim().toLowerCase()}`;
}

export function normalizeBudgetItem(raw) {
    const item = { ...emptyBudgetItem(), ...raw };
    item.qty = normalizeProductQty(item.qty);
    item.unit_cost = normalizeProductAmount(item.unit_cost);
    item.unit_price = normalizeProductAmount(item.unit_price);
    return item;
}

export function itemsToSlotMap(items) {
    /** @type {Record<number, object>} */
    const map = {};

    (items ?? []).forEach((raw, index) => {
        const item = normalizeBudgetItem(raw);
        if (!String(item.name ?? '').trim()) return;
        map[index] = { id: `slot-item-${index}-${itemMatchKey(item)}`, ...item };
    });

    return map;
}

export function slotMapToItems(slotMap) {
    return Object.entries(slotMap ?? {})
        .map(([index, cell]) => ({ index: Number(index), cell }))
        .filter(({ cell }) => cell && String(cell.name ?? '').trim())
        .sort((a, b) => a.index - b.index)
        .map(({ cell }) => {
            const { id, slotIndex, zoneId, ...item } = cell;
            return normalizeBudgetItem(item);
        });
}

export function occupiedSlotIndexes(slotMap) {
    return Object.keys(slotMap ?? {})
        .map(Number)
        .filter((index) => slotMap[index] && String(slotMap[index].name ?? '').trim())
        .sort((a, b) => a - b);
}

export function nextEmptySlotIndex(slotMap) {
    const used = new Set(occupiedSlotIndexes(slotMap));
    let index = 0;
    while (used.has(index)) index += 1;
    return index;
}

export function gridRowCount(slotMap) {
    const occupied = occupiedSlotIndexes(slotMap);
    const highest = occupied.length ? Math.max(...occupied) : -1;
    const needed = Math.max(
        GRID_LAYOUT.minRows,
        highest + 1 + GRID_LAYOUT.extraEmptyRows,
        occupied.length + GRID_LAYOUT.extraEmptyRows,
    );

    return needed;
}

export function findSlotByItemKey(slotMap, key) {
    const index = occupiedSlotIndexes(slotMap).find(
        (slotIndex) => itemMatchKey(slotMap[slotIndex]) === key,
    );
    return index ?? null;
}

export function createSlotCell(item) {
    return {
        id: `slot-item-${Date.now()}-${Math.random().toString(36).slice(2, 7)}`,
        ...normalizeBudgetItem(item),
    };
}
