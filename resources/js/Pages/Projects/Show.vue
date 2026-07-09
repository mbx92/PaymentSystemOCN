<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import CurrencyInput from '@/Components/CurrencyInput.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import { ArrowLeftIcon, TrashIcon } from '@heroicons/vue/24/outline';
import { computed, ref, watch } from 'vue';
import { useCurrency } from '@/composables/useCurrency';
import { useDateFormat } from '@/composables/useDateFormat';

const props = defineProps({
    project: Object,
    material_products: Array,
    warehouses: Array,
    warehouse_stocks: Object,
    team_members: Array,
    team_roles: Array,
    cash_accounts: Array,
    cash_category_options: Object,
});
const { format } = useCurrency();
const { formatDate } = useDateFormat();
const activeTab = ref('info');
const deletingMaterialId = ref(null);
const createFolderForm = useForm({});
const syncProjectStockForm = useForm({});
const sidebarCanvasMode = ref(null);
const openSidebarCanvas = (mode) => {
    sidebarCanvasMode.value = mode;
};
const closeSidebarCanvas = () => {
    sidebarCanvasMode.value = null;
};
const isProjectStockAuditCanvas = computed(() => sidebarCanvasMode.value === 'project-stock-audit');
const isTeamCalculatorCanvas = computed(() => sidebarCanvasMode.value === 'team-calculator');
const isReferralCalculatorCanvas = computed(() => sidebarCanvasMode.value === 'referral-calculator');

// Mark term paid
const payForm = useForm({ paid_at: new Date().toISOString().slice(0, 10), cash_account_id: '', note: '' });
const selectedTerm = ref(null);

const openPayModal = (term) => {
    selectedTerm.value = term;
    document.getElementById('modal-pay-term').showModal();
};
const submitPay = () => {
    payForm.patch(route('project-payments.mark-paid', selectedTerm.value.id), {
        onSuccess: () => document.getElementById('modal-pay-term').close(),
    });
};
const markUnpaid = (term) => {
    router.patch(route('project-payments.mark-unpaid', term.id));
};

const materialForm = useForm({
    master_product_id: '',
    warehouse_id: '',
    planned_qty: 1,
    unit_cost: 0,
    unit_price: 0,
    notes: '',
});

const materialWarehouseFilter = ref('');
const materialSearch = ref('');
const modalMaterialProducts = ref([]);
const warehouseStocksCache = ref(props.warehouse_stocks ?? {});
const materialProductsLoading = ref(false);
let materialSearchTimer;
let materialSearchRequestId = 0;

const defaultMaterialWarehouse = computed(() =>
    props.warehouses.find((warehouse) => warehouse.code === 'WH-OCN')
    ?? props.warehouses[0]
    ?? null,
);

const activeMaterialWarehouseLabel = computed(() => {
    const warehouse = props.warehouses.find((item) => String(item.id) === String(materialWarehouseFilter.value));
    return warehouse ? `${warehouse.code} - ${warehouse.name}` : '-';
});

const filteredMaterialProducts = computed(() => {
    if (!materialWarehouseFilter.value) return [];
    const whStocks = warehouseStocksCache.value?.[materialWarehouseFilter.value] ?? {};
    const keyword = materialSearch.value.trim().toLowerCase();
    return modalMaterialProducts.value
        .map((p) => {
            const stock = whStocks[p.id];
            return { ...p, available: p.available !== undefined ? p.available : (stock ? stock.available : 0) };
        })
        .filter((p) => {
            if (!keyword) return true;
            return [p.sku, p.name, p.category, p.uom]
                .filter(Boolean)
                .some((value) => String(value).toLowerCase().includes(keyword));
        });
});

const loadMaterialProducts = async () => {
    if (!materialWarehouseFilter.value) {
        modalMaterialProducts.value = [];
        return;
    }

    const requestId = ++materialSearchRequestId;
    const params = new URLSearchParams({ warehouse_id: materialWarehouseFilter.value });
    const keyword = materialSearch.value.trim();
    if (keyword) params.set('q', keyword);

    materialProductsLoading.value = true;
    try {
        const response = await fetch(`${route('projects.material-products.search', props.project.id)}?${params.toString()}`, {
            headers: { Accept: 'application/json' },
        });
        if (!response.ok) return;
        const data = await response.json();
        if (requestId === materialSearchRequestId) {
            modalMaterialProducts.value = data.products ?? [];
            if (materialWarehouseFilter.value) {
                const nextWarehouseStocks = { ...(warehouseStocksCache.value ?? {}) };
                const warehouseRows = { ...(nextWarehouseStocks[materialWarehouseFilter.value] ?? {}) };

                for (const product of modalMaterialProducts.value) {
                    if (product?.product_type === 'service') continue;
                    warehouseRows[product.id] = {
                        ...(warehouseRows[product.id] ?? {}),
                        available: Number(product.available ?? 0),
                    };
                }

                nextWarehouseStocks[materialWarehouseFilter.value] = warehouseRows;
                warehouseStocksCache.value = nextWarehouseStocks;
            }
        }
    } finally {
        if (requestId === materialSearchRequestId) {
            materialProductsLoading.value = false;
        }
    }
};

watch(materialWarehouseFilter, () => {
    materialForm.master_product_id = '';
    materialForm.warehouse_id = '';
    modalMaterialProducts.value = [];
    clearTimeout(materialSearchTimer);
    materialSearchTimer = setTimeout(loadMaterialProducts, 250);
});

watch(defaultMaterialWarehouse, (warehouse) => {
    const warehouseId = warehouse?.id ? String(warehouse.id) : '';
    if (warehouseId && String(materialWarehouseFilter.value) !== warehouseId) {
        materialWarehouseFilter.value = warehouseId;
        return;
    }

    if (!warehouseId) {
        materialWarehouseFilter.value = '';
        materialForm.warehouse_id = '';
        modalMaterialProducts.value = [];
        return;
    }

    materialForm.warehouse_id = warehouseId;
    clearTimeout(materialSearchTimer);
    materialSearchTimer = setTimeout(loadMaterialProducts, 0);
}, { immediate: true });

watch(materialSearch, () => {
    materialForm.master_product_id = '';
    clearTimeout(materialSearchTimer);
    materialSearchTimer = setTimeout(loadMaterialProducts, 250);
});

const selectMaterialProduct = (product) => {
    materialForm.master_product_id = product.id;
    materialForm.warehouse_id = materialWarehouseFilter.value;
    materialForm.planned_qty = 1;
    materialForm.unit_cost = 0;
    materialForm.unit_price = Number(product.selling_price) || 0;
};

const selectedMaterialProduct = computed(() =>
    modalMaterialProducts.value.find((p) => p.id === materialForm.master_product_id)
        ?? props.material_products.find((p) => p.id === materialForm.master_product_id),
);

const selectedProductAvailable = computed(() => {
    if (selectedMaterialProduct.value?.product_type === 'service') return null;
    if (!materialForm.master_product_id || !materialForm.warehouse_id) return 0;
    const selectedFromList = modalMaterialProducts.value.find((p) => p.id === materialForm.master_product_id);
    if (selectedFromList && selectedFromList.available !== undefined && selectedFromList.available !== null) {
        return Number(selectedFromList.available) || 0;
    }
    const whStocks = warehouseStocksCache.value?.[materialForm.warehouse_id] ?? {};
    const stock = whStocks[materialForm.master_product_id];
    return stock ? stock.available : 0;
});

const selectedProductIsService = computed(() => selectedMaterialProduct.value?.product_type === 'service');

const materialStatusLabel = (status) => ({
    planned: 'Planned',
    partial: 'Partial',
    ready: 'Ready',
    issued: 'Issued',
    reserved: 'Ready',
}[status] ?? status);

const materialStatusClass = (status) => ({
    planned: 'badge-warning',
    partial: 'badge-info',
    ready: 'badge-success',
    issued: 'badge-primary',
    reserved: 'badge-success',
}[status] ?? 'badge-ghost');

const submitMaterial = () => {
    materialForm.post(route('projects.materials.store', props.project.id), {
        preserveScroll: true,
        onSuccess: () => {
            materialForm.reset('master_product_id', 'warehouse_id', 'planned_qty', 'unit_cost', 'unit_price', 'notes');
            materialForm.planned_qty = 1;
            materialForm.unit_cost = 0;
            materialForm.unit_price = 0;
            materialWarehouseFilter.value = defaultMaterialWarehouse.value?.id ? String(defaultMaterialWarehouse.value.id) : '';
            materialSearch.value = '';
            document.getElementById('modal-add-material')?.close();
        },
    });
};

const confirmDeleteMaterial = (id) => {
    deletingMaterialId.value = id;
    document.getElementById('modal-delete-material')?.showModal();
};

const deleteMaterial = () => {
    if (!deletingMaterialId.value) return;
    router.delete(route('projects.materials.destroy', { project: props.project.id, material: deletingMaterialId.value }), {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => {
            activeTab.value = 'materials';
            deletingMaterialId.value = null;
        },
    });
};

const createLegalFolder = () => {
    createFolderForm.post(route('projects.legal-folder.create', props.project.id), {
        preserveScroll: true,
    });
};

const teamForm = useForm({
    user_id: '',
    team_role_id: '',
    percentage: 0,
    base_pay: 0,
    bonus: 0,
    total_pay: 0,
});

const submitTeamMember = () => {
    teamForm.post(route('projects.team-members.store', props.project.id), {
        preserveScroll: true,
        onSuccess: () => {
            teamForm.reset('user_id', 'team_role_id', 'percentage', 'base_pay', 'bonus', 'total_pay');
            teamForm.percentage = 0;
            teamForm.base_pay = 0;
            teamForm.bonus = 0;
            teamForm.total_pay = 0;
            document.getElementById('modal-assign-team')?.close();
        },
    });
};

const removeTeamMember = (id) => {
    router.delete(route('projects.team-members.destroy', { project: props.project.id, teamDistribution: id }), {
        preserveScroll: true,
    });
};

const openAssignTeamModal = () => {
    closeSidebarCanvas();
    if (!teamForm.team_role_id && props.team_roles?.length) {
        teamForm.team_role_id = props.team_roles[0].id;
    }
    teamForm.total_pay = (Number(teamForm.base_pay) || 0) + (Number(teamForm.bonus) || 0);
    document.getElementById('modal-assign-team')?.showModal();
};

const referralForm = useForm({
    project_id: props.project.id,
    referrer_name: '',
    commission_amount: 0,
    paid_at: '',
    note: '',
});

const submitReferral = () => {
    referralForm.post(route('referrals.store'), {
        preserveScroll: true,
        onSuccess: () => {
            referralForm.reset('referrer_name', 'commission_amount', 'paid_at', 'note');
            referralForm.project_id = props.project.id;
            document.getElementById('modal-add-referral')?.close();
        },
    });
};

const openAddReferralModal = () => {
    closeSidebarCanvas();
    document.getElementById('modal-add-referral')?.showModal();
};

const taskForm = useForm({
    title: '',
    description: '',
    status: 'todo',
    assigned_user_id: '',
    due_date: '',
});

const submitTask = () => {
    taskForm.post(route('projects.tasks.store', props.project.id), {
        preserveScroll: true,
        onSuccess: () => {
            taskForm.reset('title', 'description', 'assigned_user_id', 'due_date');
            taskForm.status = 'todo';
            document.getElementById('modal-add-task')?.close();
        },
    });
};

const openTaskModal = () => {
    document.getElementById('modal-add-task')?.showModal();
};

const updateTaskStatus = (task, status) => {
    router.patch(route('projects.tasks.update', { project: props.project.id, task: task.id }), { status }, { preserveScroll: true });
};

const deleteTask = (task) => {
    router.delete(route('projects.tasks.destroy', { project: props.project.id, task: task.id }), { preserveScroll: true });
};

const projectTypeLabel = () => props.project?.project_type_label ?? props.project?.project_type ?? '-';

const roleLabel = (role) => role;
const defaultTeamCalculatorRole = computed(() => props.team_roles?.[0]?.name ?? 'developer');
const teamCalculatorRate = ref(Number(props.project?.team_distribution_rate ?? 30));
const clampPercentage = (value) => Math.min(100, Math.max(0, Number(value) || 0));
const formatPercentageNumber = (value) => new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
}).format(Number(value) || 0);
const formatPercentage = (value) => `${formatPercentageNumber(value)}%`;
const parseLocalizedNumber = (value) => {
    const normalized = String(value ?? '')
        .replace(/[^\d,.-]/g, '')
        .replace(/\./g, '')
        .replace(',', '.');

    return Number(normalized) || 0;
};
let teamCalculatorRowCounter = 0;
const nextTeamCalculatorRowKey = () => `team-calculator-row-${teamCalculatorRowCounter++}`;
const findTeamMemberName = (userId) => props.team_members.find((member) => String(member.id) === String(userId))?.name ?? '';
const makeTeamCalculatorRow = (overrides = {}) => ({
    key: overrides.key ?? nextTeamCalculatorRowKey(),
    id: overrides.id ?? null,
    user_id: overrides.user_id ? Number(overrides.user_id) : '',
    user_name: overrides.user_name ?? findTeamMemberName(overrides.user_id),
    role_in_project: overrides.role_in_project || defaultTeamCalculatorRole.value,
    percentage: clampPercentage(overrides.percentage ?? 0),
    bonus: Math.max(0, Number(overrides.bonus) || 0),
    paid_at: overrides.paid_at ?? null,
    original_user_id: overrides.original_user_id ?? (overrides.user_id ? Number(overrides.user_id) : ''),
    original_role_in_project: overrides.original_role_in_project ?? (overrides.role_in_project || defaultTeamCalculatorRole.value),
    current_percentage: clampPercentage(overrides.current_percentage ?? overrides.percentage ?? 0),
    current_bonus: Math.max(0, Number(overrides.current_bonus ?? overrides.bonus) || 0),
    current_total_pay: Math.max(0, Number(overrides.current_total_pay ?? overrides.total_pay) || 0),
});
const buildTeamCalculatorRows = (rows = []) => (rows ?? []).map((row) => makeTeamCalculatorRow({
    id: row.id,
    user_id: row.user_id,
    user_name: row.user_name,
    role_in_project: row.role_in_project,
    percentage: row.percentage,
    bonus: row.bonus,
    total_pay: row.total_pay,
    paid_at: row.paid_at,
}));
const teamCalculatorRows = ref(buildTeamCalculatorRows(props.project?.team_distributions ?? []));
const teamCalculatorSaveForm = useForm({});
const referralCalculatorRate = ref(5);
const referralCalculatorBasis = ref('margin');
const referralBasisOptions = [
    { value: 'contract', label: 'Estimasi Revenue' },
    { value: 'paid', label: 'Cash In Masuk' },
    { value: 'margin', label: 'Margin Estimasi - Expense' },
    { value: 'team_pool', label: 'Pool Tim Tersedia' },
];
watch(() => props.project?.team_distribution_rate, (value) => {
    teamCalculatorRate.value = Number(value ?? 30);
});
watch(() => props.project?.team_distributions, (rows) => {
    teamCalculatorRows.value = buildTeamCalculatorRows(rows ?? []);
}, { deep: true, immediate: true });
watch(sidebarCanvasMode, (mode) => {
    if (mode === 'team-calculator') {
        resetTeamCalculatorShares();
    }
});
watch(() => props.project?.id, () => {
    referralCalculatorRate.value = 5;
    referralCalculatorBasis.value = 'margin';
});
const teamCalculatorContractValue = computed(() => {
    const directValue = Number(props.project?.total_value) || 0;
    if (directValue > 0) return directValue;
    return cctvProjectValue.value;
});
const teamCalculatorEstimatedCost = computed(() => {
    if ((Number(cctvEstimatedCost.value) || 0) > 0) {
        return Number(cctvEstimatedCost.value) || 0;
    }

    return (props.project?.materials ?? []).reduce((sum, item) => sum + (Number(item.subtotal_cost) || 0), 0);
});
const teamCalculatorExpense = computed(() => Number(props.project?.summary?.total_operational) || 0);
const teamCalculatorReferralTotal = computed(() => Number(props.project?.summary?.total_referral_commission) || 0);
const teamCalculatorEstimatedMargin = computed(() => teamCalculatorContractValue.value - teamCalculatorEstimatedCost.value);
const teamCalculatorNetEstimate = computed(() => teamCalculatorEstimatedMargin.value - teamCalculatorExpense.value);
const teamCalculatorReserveAmount = computed(() => Math.max(teamCalculatorNetEstimate.value * ((Number(teamCalculatorRate.value) || 0) / 100), 0));
const teamCalculatorPool = computed(() => Math.max(teamCalculatorNetEstimate.value - teamCalculatorReserveAmount.value, 0));
const teamCalculatorDistributed = computed(() => (props.project?.team_distributions ?? []).reduce((sum, item) => sum + (Number(item.total_pay) || 0), 0));
const teamCalculatorRemaining = computed(() => teamCalculatorPool.value - teamCalculatorDistributed.value);
const teamCalculatorBasePay = (row) => Math.round(teamCalculatorPool.value * (clampPercentage(row?.percentage ?? 0) / 100));
const teamCalculatorRowTotalPay = (row) => teamCalculatorBasePay(row) + (Number(row?.bonus) || 0);
const teamCalculatorAssignmentRows = computed(() => teamCalculatorRows.value.map((row) => {
    const userName = findTeamMemberName(row.user_id) || row.user_name || 'Pilih anggota';
    const simulatedBasePay = teamCalculatorBasePay(row);
    const simulatedTotalPay = simulatedBasePay + (Number(row.bonus) || 0);

    return {
        ...row,
        user_name: userName,
        bonus: Number(row.bonus) || 0,
        currentPercentage: clampPercentage(row.current_percentage ?? 0),
        currentTotalPay: Math.max(0, Number(row.current_total_pay) || 0),
        currentBonus: Math.max(0, Number(row.current_bonus) || 0),
        sliderPercentage: clampPercentage(row.percentage ?? 0),
        simulatedBasePay,
        simulatedTotalPay,
        simulatedDeltaPay: simulatedTotalPay - (Math.max(0, Number(row.current_total_pay) || 0)),
    };
}));
const teamCalculatorSimulatedPercentageTotal = computed(() => teamCalculatorAssignmentRows.value.reduce((sum, item) => sum + item.sliderPercentage, 0));
const teamCalculatorSimulatedDistributed = computed(() => teamCalculatorAssignmentRows.value.reduce((sum, item) => sum + item.simulatedTotalPay, 0));
const teamCalculatorSimulatedRemaining = computed(() => teamCalculatorPool.value - teamCalculatorSimulatedDistributed.value);
const teamCalculatorSimulatedPercentageRemaining = computed(() => 100 - teamCalculatorSimulatedPercentageTotal.value);
const teamCalculatorDuplicateMemberIds = computed(() => {
    const ids = teamCalculatorRows.value
        .map((row) => row.user_id)
        .filter((id) => id !== '' && id !== undefined && id !== null);

    return ids.filter((id, index) => ids.indexOf(id) !== index);
});
const teamCalculatorHasDuplicates = computed(() => teamCalculatorDuplicateMemberIds.value.length > 0);
const teamCalculatorRowsValid = computed(() => teamCalculatorRows.value.length > 0 && teamCalculatorRows.value.every((row) => row.user_id && row.role_in_project));
const teamCalculatorRateValid = computed(() => Number(teamCalculatorRate.value) >= 0 && Number(teamCalculatorRate.value) <= 100);
const teamCalculatorPercentageValid = computed(() => teamCalculatorSimulatedPercentageTotal.value <= 100.01);
const teamCalculatorBudgetValid = computed(() => teamCalculatorSimulatedDistributed.value <= teamCalculatorPool.value + 0.01);
const teamCalculatorMaxAllowedPercentage = (rowKey) => {
    const otherTotal = teamCalculatorRows.value.reduce((sum, row) => (
        row.key === rowKey ? sum : sum + clampPercentage(row.percentage)
    ), 0);

    return Math.max(0, 100 - otherTotal);
};
const teamCalculatorSimulationDirty = computed(() => {
    if (Math.abs((Number(teamCalculatorRate.value) || 0) - (Number(props.project?.team_distribution_rate) || 0)) > 0.01) {
        return true;
    }

    if (teamCalculatorRows.value.length !== (props.project?.team_distributions?.length ?? 0)) {
        return true;
    }

    return teamCalculatorAssignmentRows.value.some((row) => (
        !row.id
        || String(row.user_id) !== String(row.original_user_id)
        || row.role_in_project !== row.original_role_in_project
        || Math.abs(row.sliderPercentage - row.currentPercentage) > 0.01
        || Math.abs((Number(row.bonus) || 0) - (Number(row.currentBonus) || 0)) > 0.01
    ));
});
const canSaveTeamCalculator = computed(() => (
    teamCalculatorSimulationDirty.value
    && teamCalculatorRateValid.value
    && teamCalculatorRowsValid.value
    && teamCalculatorPercentageValid.value
    && teamCalculatorBudgetValid.value
    && !teamCalculatorHasDuplicates.value
    && !teamCalculatorSaveForm.processing
));
const addTeamCalculatorRow = () => {
    teamCalculatorRows.value.push(makeTeamCalculatorRow());
};
const removeTeamCalculatorRow = (key) => {
    teamCalculatorRows.value = teamCalculatorRows.value.filter((row) => row.key !== key);
};
const setTeamCalculatorShare = (rowKey, value) => {
    const maxAllowed = teamCalculatorMaxAllowedPercentage(rowKey);
    const nextPercentage = Math.min(clampPercentage(value), maxAllowed);

    teamCalculatorRows.value = teamCalculatorRows.value.map((row) => (
        row.key === rowKey
            ? { ...row, percentage: nextPercentage }
            : row
    ));
};
const setTeamCalculatorPercentageInput = (rowKey, value) => {
    setTeamCalculatorShare(rowKey, parseLocalizedNumber(value));
};
const setTeamCalculatorAmount = (rowKey, value) => {
    const amount = Math.max(0, parseLocalizedNumber(value));
    if (teamCalculatorPool.value <= 0) {
        setTeamCalculatorShare(rowKey, 0);
        return;
    }

    setTeamCalculatorShare(rowKey, (amount / teamCalculatorPool.value) * 100);
};
const setTeamCalculatorRole = (rowKey, value) => {
    teamCalculatorRows.value = teamCalculatorRows.value.map((row) => (
        row.key === rowKey
            ? { ...row, role_in_project: value || defaultTeamCalculatorRole.value }
            : row
    ));
};
const setTeamCalculatorUser = (rowKey, value) => {
    const nextUserId = value ? Number(value) : '';
    teamCalculatorRows.value = teamCalculatorRows.value.map((row) => (
        row.key === rowKey
            ? { ...row, user_id: nextUserId, user_name: findTeamMemberName(nextUserId) }
            : row
    ));
};
const setTeamCalculatorBonus = (rowKey, value) => {
    teamCalculatorRows.value = teamCalculatorRows.value.map((row) => (
        row.key === rowKey
            ? { ...row, bonus: Math.max(0, Number(value) || 0) }
            : row
    ));
};
const resetTeamCalculatorShares = () => {
    teamCalculatorRate.value = Number(props.project?.team_distribution_rate ?? 30);
    teamCalculatorRows.value = buildTeamCalculatorRows(props.project?.team_distributions ?? []);
};
const saveTeamCalculator = () => {
    activeTab.value = 'tim';

    teamCalculatorSaveForm
        .transform(() => ({
            project_id: props.project.id,
            distribution_rate: Number(teamCalculatorRate.value || 0),
            distributions: teamCalculatorRows.value.map((row) => ({
                user_id: row.user_id,
                role_in_project: row.role_in_project,
                percentage: Number(clampPercentage(row.percentage).toFixed(2)),
                base_pay: teamCalculatorBasePay(row),
                bonus: Number(row.bonus || 0),
            })),
        }))
        .post(route('team-distribution.save'), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                activeTab.value = 'tim';
                closeSidebarCanvas();
            },
        });
};
const referralCalculatorBaseAmount = computed(() => {
    switch (referralCalculatorBasis.value) {
    case 'paid':
        return Number(props.project?.summary?.total_cash_in) || 0;
    case 'margin':
        return Math.max(teamCalculatorNetEstimate.value, 0);
    case 'team_pool':
        return Math.max(teamCalculatorPool.value, 0);
    case 'contract':
    default:
        return teamCalculatorContractValue.value;
    }
});
const referralCalculatorAmount = computed(() => Math.round(referralCalculatorBaseAmount.value * ((Number(referralCalculatorRate.value) || 0) / 100)));
const applyReferralCalculatorAmount = () => {
    referralForm.commission_amount = referralCalculatorAmount.value;
    closeSidebarCanvas();
    openAddReferralModal();
};
const assignableTaskMembers = computed(() => {
    const rows = props.project?.team_distributions ?? [];
    const unique = new Map();

    for (const row of rows) {
        if (!row?.user_id || unique.has(row.user_id)) continue;
        unique.set(row.user_id, {
            id: row.user_id,
            name: row.user_name,
            role: row.role_in_project,
        });
    }

    return Array.from(unique.values()).sort((a, b) => String(a.name ?? '').localeCompare(String(b.name ?? '')));
});

const statusLabel = (status) => {
    if (status === 'todo') return 'To Do';
    if (status === 'in_progress') return 'In Progress';
    if (status === 'done') return 'Done';
    return status;
};

const taskCardClass = (status) => {
    if (status === 'todo') return 'border-slate-300 bg-slate-50';
    if (status === 'in_progress') return 'border-amber-300 bg-amber-50';
    if (status === 'done') return 'border-emerald-300 bg-emerald-50';
    return 'border-base-300';
};

const projectTasks = computed(() => {
    const tasks = [...(props.project?.tasks ?? [])];

    return tasks.sort((a, b) => {
        const statusOrder = { in_progress: 0, todo: 1, done: 2 };
        const statusGap = (statusOrder[a.status] ?? 99) - (statusOrder[b.status] ?? 99);
        if (statusGap !== 0) return statusGap;

        const dateA = a.due_date ?? '9999-12-31';
        const dateB = b.due_date ?? '9999-12-31';
        if (dateA !== dateB) return dateA.localeCompare(dateB);

        return String(a.title ?? '').localeCompare(String(b.title ?? ''));
    });
});

const taskChecklistSummary = computed(() => {
    const tasks = projectTasks.value;

    return {
        total: tasks.length,
        done: tasks.filter((task) => task.status === 'done').length,
        active: tasks.filter((task) => task.status === 'in_progress').length,
        pending: tasks.filter((task) => task.status === 'todo').length,
    };
});

const materialUsageChecklist = computed(() => {
    const materials = props.project?.materials ?? [];

    return materials
        .map((item) => {
            const plannedQty = Number(item.planned_qty) || 0;
            const issuedQty = Number(item.issued_qty) || 0;
            const reservedQty = Number(item.reserved_qty) || 0;
            const isFullyUsed = plannedQty > 0 ? issuedQty >= plannedQty : issuedQty > 0;
            const hasPartialUsage = issuedQty > 0 && !isFullyUsed;

            return {
                ...item,
                plannedQty,
                issuedQty,
                reservedQty,
                isFullyUsed,
                hasPartialUsage,
                canToggleUsage: item.status === 'ready' || item.status === 'issued',
                usagePercent: plannedQty > 0 ? Math.min((issuedQty / plannedQty) * 100, 100) : 0,
            };
        })
        .sort((a, b) => {
            if (a.isFullyUsed !== b.isFullyUsed) return a.isFullyUsed ? -1 : 1;
            if (a.hasPartialUsage !== b.hasPartialUsage) return a.hasPartialUsage ? -1 : 1;
            return String(a.product ?? '').localeCompare(String(b.product ?? ''));
        });
});

const materialUsageSummary = computed(() => {
    const items = materialUsageChecklist.value;

    return {
        total: items.length,
        used: items.filter((item) => item.isFullyUsed).length,
        issuedQty: items.reduce((sum, item) => sum + item.issuedQty, 0),
        reservedQty: items.reduce((sum, item) => sum + item.reservedQty, 0),
    };
});

const toggleTaskChecklist = (task, checked) => {
    updateTaskStatus(task, checked ? 'done' : 'todo');
};

const toggleMaterialUsage = (item, checked) => {
    router.patch(
        route('projects.materials.usage', { project: props.project.id, material: item.id }),
        { used: checked },
        { preserveScroll: true, preserveState: true },
    );
};

const canShowKanban = computed(() => !!props.project?.supports_project_board);
const isCctvProject = computed(() => !!props.project?.supports_budget_items);
const materialSummary = computed(() => {
    const materials = props.project?.materials ?? [];

    return {
        itemCount: materials.length,
        plannedQty: materials.reduce((sum, item) => sum + (Number(item.planned_qty) || 0), 0),
        readyQty: materials.reduce((sum, item) => sum + (Number(item.reserved_qty) || 0), 0),
    };
});
const projectStockCheck = computed(() => props.project?.stock_check ?? {
    summary: { line_count: 0, warning_count: 0, total_issued_qty: 0, total_movement_net: 0, total_warehouse_qty: 0, mismatch_count: 0 },
    lines: [],
    warnings: [],
});
const projectStockCheckSummary = computed(() => projectStockCheck.value.summary ?? {
    line_count: 0,
    warning_count: 0,
    total_issued_qty: 0,
    total_movement_net: 0,
    total_warehouse_qty: 0,
    mismatch_count: 0,
});
const projectStockCheckLines = computed(() => projectStockCheck.value.lines ?? []);
const projectStockCheckWarnings = computed(() => projectStockCheck.value.warnings ?? []);
const stockCheckBadgeClass = (isSynced) => (isSynced ? 'badge-success' : 'badge-error');
const syncProjectStock = () => {
    syncProjectStockForm.patch(route('projects.stock.sync', props.project.id), {
        preserveScroll: true,
        preserveState: true,
    });
};
const cctvBudgetSummary = computed(() => props.project?.budget_summary ?? {});
const cctvProjectValue = computed(() => {
    const summaryPrice = Number(cctvBudgetSummary.value.total_price) || 0;
    if (summaryPrice > 0) return summaryPrice;

    return Number(props.project?.total_value) || 0;
});
const cctvEstimatedCost = computed(() => Number(cctvBudgetSummary.value.total_cost) || 0);
const cctvEstimatedMargin = computed(() => {
    const budgetMargin = Number(cctvBudgetSummary.value.total_margin);
    if (budgetMargin || cctvProjectValue.value > 0 || cctvEstimatedCost.value > 0) return budgetMargin;

    return Number(props.project?.summary?.profit) || 0;
});
const draggingTaskId = ref(null);
const dropColumnKey = ref(null);

const kanbanColumns = computed(() => {
    const tasks = props.project?.tasks ?? [];
    return [
        { key: 'todo', label: 'To Do', tasks: tasks.filter((task) => task.status === 'todo') },
        { key: 'in_progress', label: 'In Progress', tasks: tasks.filter((task) => task.status === 'in_progress') },
        { key: 'done', label: 'Done', tasks: tasks.filter((task) => task.status === 'done') },
    ];
});

const onTaskDragStart = (task) => {
    draggingTaskId.value = task.id;
};

const onTaskDragEnd = () => {
    draggingTaskId.value = null;
    dropColumnKey.value = null;
};

const onColumnDragOver = (columnKey, event) => {
    event.preventDefault();
    dropColumnKey.value = columnKey;
};

const onColumnDrop = (columnKey, event) => {
    event.preventDefault();
    dropColumnKey.value = null;
    if (!draggingTaskId.value) return;
    const task = (props.project?.tasks ?? []).find((item) => item.id === draggingTaskId.value);
    if (!task || task.status === columnKey) {
        draggingTaskId.value = null;
        return;
    }
    updateTaskStatus(task, columnKey);
    draggingTaskId.value = null;
};

const ganttPhases = computed(() => {
    if (!props.project?.started_at || !props.project?.finished_at) return [];
    const start = new Date(props.project.started_at);
    const end = new Date(props.project.finished_at);
    const templates = [
        { name: 'Discovery', from: 0, to: 20 },
        { name: 'Development', from: 20, to: 75 },
        { name: 'UAT', from: 75, to: 90 },
        { name: 'Go-Live', from: 90, to: 100 },
    ];

    const totalMs = Math.max(end.getTime() - start.getTime(), 1);
    return templates.map((phase) => {
        const phaseStart = new Date(start.getTime() + (totalMs * phase.from) / 100);
        const phaseEnd = new Date(start.getTime() + (totalMs * phase.to) / 100);
        return {
            ...phase,
            start: phaseStart.toISOString().slice(0, 10),
            end: phaseEnd.toISOString().slice(0, 10),
            left: `${phase.from}%`,
            width: `${phase.to - phase.from}%`,
        };
    });
});

const cctvLifecycleTimeline = computed(() => {
    if (!isCctvProject.value) return [];

    const p = props.project;
    const steps = [];

    const addStep = (key, label, date, detail, done) => {
        steps.push({
            key,
            label,
            date: date || null,
            detail: detail || null,
            done: !!done,
        });
    };

    addStep('created', 'Project dibuat & data diinput', p.created_at, p.client_name ? `Klien: ${p.client_name}` : null, !!p.created_at);
    addStep('started', 'Project mulai berjalan', p.started_at, null, !!p.started_at);
    addStep('finished', 'Project selesai', p.finished_at, null, !!p.finished_at);

    const invoiceReady = !!(p.invoiced_at || p.invoice?.number);
    addStep(
        'invoice',
        'Invoice project diterbitkan',
        p.invoiced_at,
        p.invoice?.number ? `No. ${p.invoice.number}` : null,
        invoiceReady,
    );

    for (const term of p.payments ?? []) {
        addStep(
            `term-${term.id}`,
            `Pembayaran invoice — termin ${term.term_number}`,
            term.timeline_paid_at || term.paid_at,
            `${term.percentage}% · ${format(term.amount)}`,
            !!term.is_paid,
        );
    }

    for (const dist of p.team_distributions ?? []) {
        addStep(
            `staff-${dist.id}`,
            `Pembayaran tim — ${dist.user_name}`,
            dist.paid_at,
            `${dist.role_in_project || 'Anggota'} · ${format(dist.total_pay)}`,
            !!dist.paid_at,
        );
    }

    return steps;
});

// Expense forms
const defaultCashAccountId = computed(() => props.cash_accounts?.[0]?.id ?? '');
const cashOutCategories = computed(() => props.cash_category_options?.out ?? []);
const cashCategoryLabels = computed(() => props.cash_category_options?.labels ?? {});
const categoryLabel = (value) => cashCategoryLabels.value?.[value] ?? value;

const cashOutForm = useForm({
    project_id: props.project.id,
    cash_account_id: defaultCashAccountId.value,
    category: 'biaya_tim',
    amount: 0,
    date: new Date().toISOString().slice(0, 10),
    note: '',
    recipient_name: '',
});

const submitCashOut = () => cashOutForm.post(route('cash-out.store'), { onSuccess: () => { cashOutForm.reset('amount', 'note', 'recipient_name'); document.getElementById('modal-cash-out').close(); } });

const expenseEntries = computed(() => (props.project?.cash_outs ?? [])
    .map((c) => ({ ...c, type: 'out' }))
    .sort((a, b) => (a.date ?? '').localeCompare(b.date ?? '')));

const canMoveToBerjalan = computed(() => props.project?.status === 'negosiasi');
const canMoveToSelesai = computed(() => props.project?.status === 'berjalan');

const statusStartForm = useForm({
    target_status: 'berjalan',
    started_at: props.project.started_at || new Date().toISOString().slice(0, 10),
});

const statusFinishForm = useForm({
    target_status: 'selesai',
    finished_at: props.project.finished_at || new Date().toISOString().slice(0, 10),
});

const openStartStatusModal = () => {
    statusStartForm.target_status = 'berjalan';
    statusStartForm.started_at = props.project.started_at || new Date().toISOString().slice(0, 10);
    document.getElementById('modal-project-start')?.showModal();
};

const openFinishStatusModal = () => {
    statusFinishForm.target_status = 'selesai';
    statusFinishForm.finished_at = props.project.finished_at || new Date().toISOString().slice(0, 10);
    document.getElementById('modal-project-finish')?.showModal();
};

const submitMoveToBerjalan = () => {
    statusStartForm.patch(route('projects.status.update', props.project.id), {
        preserveScroll: true,
        onSuccess: () => document.getElementById('modal-project-start')?.close(),
    });
};

const submitMoveToSelesai = () => {
    statusFinishForm.patch(route('projects.status.update', props.project.id), {
        preserveScroll: true,
        onSuccess: () => document.getElementById('modal-project-finish')?.close(),
    });
};

// Delete project
const deleteProject = () => {
    router.delete(route('projects.destroy', props.project.id));
};
</script>

<template>
    <AppLayout>
        <div class="space-y-5">
            <!-- Header -->
            <div class="ocn-panel">
        <div class="ocn-panel__head">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Projects Workspace</p>
              <h1 class="ocn-panel__title mt-1">{{ project.name }}</h1>
              <p class="text-sm text-base-content/60 mt-1">{{ project.client_name }}</p>
                        <p class="ocn-panel__desc mt-1">Pantau progres project, keuangan, material, tim, dan task dalam satu halaman.</p>
                        <span class="badge badge-ghost badge-sm mt-1">{{ projectTypeLabel() }}</span>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
              <div class="flex flex-wrap justify-end gap-2">
                        <StatusBadge :status="project.status" />
                        <button v-if="canMoveToBerjalan" class="btn btn-success btn-sm" @click="openStartStatusModal">Mulai Project</button>
                        <button v-if="canMoveToSelesai" class="btn btn-primary btn-sm" @click="openFinishStatusModal">Selesaikan Project</button>
                        <Link :href="route('projects.edit', project.id)" class="btn btn-outline btn-sm">Edit</Link>
                        <button class="btn btn-error btn-outline btn-sm" onclick="document.getElementById('modal-delete-project').showModal()">Hapus</button>
                        <Link class="btn btn-ghost btn-sm shrink-0 gap-1.5" :href="route('projects.index')">
                      <ArrowLeftIcon class="h-4 w-4" />
                      Back
                    </Link>
                    </div>
            </div>
          </div>
        </div>
      </div>

            <!-- Ringkasan keuangan (panel kontras, bukan stats/base-100 halaman) -->
            <div v-if="isCctvProject" class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <article
                    class="rounded-2xl border border-slate-700/40 bg-gradient-to-br from-slate-800 to-slate-950 p-5 text-white shadow-xl ring-1 ring-black/20"
                >
                    <p class="text-xs font-semibold uppercase tracking-wide text-white/55">Nilai Project CCTV</p>
                    <p class="mt-3 text-xl font-bold tabular-nums tracking-tight sm:text-2xl">{{ format(cctvProjectValue) }}</p>
                </article>
                <article
                    class="rounded-2xl border border-cyan-900/50 bg-gradient-to-br from-cyan-900 to-slate-950 p-5 text-white shadow-xl ring-1 ring-cyan-950/60"
                >
                    <p class="text-xs font-semibold uppercase tracking-wide text-cyan-100/70">Material / BOM</p>
                    <p class="mt-3 text-2xl font-bold tabular-nums tracking-tight">{{ materialSummary.itemCount }}</p>
                    <p class="mt-1 text-xs text-cyan-100/70">{{ materialSummary.readyQty }} / {{ materialSummary.plannedQty }} qty ready</p>
                </article>
                <article
                    class="rounded-2xl border border-rose-900/50 bg-gradient-to-br from-rose-900 to-slate-950 p-5 text-white shadow-xl ring-1 ring-rose-950/60"
                >
                    <p class="text-xs font-semibold uppercase tracking-wide text-rose-100/70">Estimasi HPP</p>
                    <p class="mt-3 text-xl font-bold tabular-nums tracking-tight sm:text-2xl">{{ format(cctvEstimatedCost) }}</p>
                </article>
                <article
                    class="rounded-2xl border border-emerald-900/50 bg-gradient-to-br from-emerald-900 to-slate-950 p-5 text-white shadow-xl ring-1 ring-emerald-950/60"
                >
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-100/70">Estimasi Margin</p>
                    <p class="mt-3 text-xl font-bold tabular-nums tracking-tight sm:text-2xl">{{ format(cctvEstimatedMargin) }}</p>
                </article>
                <article
                    class="rounded-2xl border border-indigo-800/50 bg-gradient-to-br from-indigo-800 to-violet-950 p-5 text-white shadow-xl ring-1 ring-indigo-950/50"
                >
                    <p class="text-xs font-semibold uppercase tracking-wide text-indigo-100/70">Expenses Project</p>
                    <p class="mt-3 text-xl font-bold tabular-nums tracking-tight sm:text-2xl">{{ format(project.summary.total_cash_out) }}</p>
                </article>
            </div>

            <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article
                    class="rounded-2xl border border-slate-700/40 bg-gradient-to-br from-slate-800 to-slate-950 p-5 text-white shadow-xl ring-1 ring-black/20"
                >
                    <p class="text-xs font-semibold uppercase tracking-wide text-white/55">Nilai kontrak</p>
                    <p class="mt-3 text-xl font-bold tabular-nums tracking-tight sm:text-2xl">{{ format(project.total_value) }}</p>
                </article>
                <article
                    class="rounded-2xl border border-emerald-900/50 bg-gradient-to-br from-emerald-900 to-emerald-950 p-5 text-white shadow-xl ring-1 ring-emerald-950/60"
                >
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-100/70">Pembayaran diterima</p>
                    <p class="mt-3 text-xl font-bold tabular-nums tracking-tight text-emerald-50 sm:text-2xl">
                        {{ format(project.summary.total_cash_in) }}
                    </p>
                </article>
                <article
                    class="rounded-2xl border border-rose-900/50 bg-gradient-to-br from-rose-900 to-rose-950 p-5 text-white shadow-xl ring-1 ring-rose-950/60"
                >
                    <p class="text-xs font-semibold uppercase tracking-wide text-rose-100/70">Expenses project</p>
                    <p class="mt-3 text-xl font-bold tabular-nums tracking-tight text-rose-50 sm:text-2xl">
                        {{ format(project.summary.total_cash_out) }}
                    </p>
                </article>
                <article
                    :class="[
                        'rounded-2xl border p-5 text-white shadow-xl ring-1',
                        project.summary.profit >= 0
                            ? 'border-indigo-800/50 bg-gradient-to-br from-indigo-800 to-violet-950 ring-indigo-950/50'
                            : 'border-red-900/50 bg-gradient-to-br from-red-900 to-red-950 ring-red-950/60',
                    ]"
                >
                    <p
                        :class="[
                            'text-xs font-semibold uppercase tracking-wide',
                            project.summary.profit >= 0 ? 'text-indigo-100/70' : 'text-red-100/70',
                        ]"
                    >
                        Laba
                    </p>
                    <p class="mt-3 text-xl font-bold tabular-nums tracking-tight sm:text-2xl">
                        {{ format(project.summary.profit) }}
                    </p>
                </article>
            </div>

            <!-- Tabs -->
            <div class="tabs tabs-boxed bg-base-100">
                <button :class="['tab', activeTab === 'info' ? 'tab-active' : '']" @click="activeTab = 'info'">Info & Termin</button>
                <button :class="['tab', activeTab === 'work' ? 'tab-active' : '']" @click="activeTab = 'work'">Pekerjaan & Checklist</button>
                <button :class="['tab', activeTab === 'materials' ? 'tab-active' : '']" @click="activeTab = 'materials'">Material / BOM</button>
                <button :class="['tab', activeTab === 'kas' ? 'tab-active' : '']" @click="activeTab = 'kas'">Expenses</button>
                <button :class="['tab', activeTab === 'tim' ? 'tab-active' : '']" @click="activeTab = 'tim'">Tim & Referral</button>
                <button :class="['tab', activeTab === 'docs' ? 'tab-active' : '']" @click="activeTab = 'docs'">Dokumen & Invoice</button>
                <button v-if="canShowKanban" :class="['tab', activeTab === 'kanban' ? 'tab-active' : '']" @click="activeTab = 'kanban'">Kanban Task</button>
            </div>

            <!-- Tab: Info -->
            <div v-if="activeTab === 'info'" class="space-y-4">
                <div class="ocn-panel">
                    <div class="ocn-panel__head">
                        <h2 class="ocn-panel__title">Detail project</h2>
                    </div>
                    <div class="card-body">
                        <div class="grid grid-cols-2 gap-2 text-sm">
                            <div class="text-base-content/60">Kontak Klien</div><div>{{ project.client_contact ?? '-' }}</div>
                            <div class="text-base-content/60">Tipe Project</div><div>{{ projectTypeLabel() }}</div>
                            <div class="text-base-content/60">Tanggal Mulai</div><div>{{ formatDate(project.started_at) }}</div>
                            <div class="text-base-content/60">Tanggal Selesai</div><div>{{ formatDate(project.finished_at) }}</div>
                            <div class="text-base-content/60">Deskripsi</div><div>{{ project.description ?? '-' }}</div>
                        </div>
                    </div>
                </div>

                <div class="ocn-panel">
                    <div class="ocn-panel__head flex flex-wrap items-center justify-between gap-2">
                        <h2 class="ocn-panel__title">Termin pembayaran</h2>
                        <a
                            :href="route('erp.sales.project-invoices.download', project.id)"
                            target="_blank"
                            class="btn btn-outline btn-sm gap-1.5"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            Download Invoice PDF
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="space-y-3">
                            <div v-for="term in project.payments" :key="term.id"
                                :class="['flex items-center justify-between p-3 rounded-lg border', term.paid_at ? 'border-success/30 bg-success/5' : 'border-base-300']"
                            >
                                <div>
                                    <p class="font-medium">Termin {{ term.term_number }} — {{ term.percentage }}%</p>
                                    <p class="text-sm text-base-content/60">{{ format(term.amount) }}</p>
                                    <p v-if="term.paid_at" class="text-xs text-success">Lunas: {{ formatDate(term.paid_at) }}</p>
                                </div>
                                <div>
                                    <button v-if="!term.paid_at" class="btn btn-success btn-sm" @click="openPayModal(term)">
                                        Tandai Lunas
                                    </button>
                                    <button v-else class="btn btn-ghost btn-sm" @click="markUnpaid(term)">
                                        Batalkan
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="isCctvProject" class="ocn-panel">
                    <div class="ocn-panel__head">
                        <h2 class="ocn-panel__title">Timeline project</h2>
                        <p class="ocn-panel__desc">Dari pembuatan project hingga pembayaran invoice dan tim.</p>
                    </div>
                    <div class="card-body">
                        <ul class="timeline timeline-vertical timeline-snap-icon">
                            <li v-for="(step, index) in cctvLifecycleTimeline" :key="step.key">
                                <div v-if="index > 0" class="timeline-middle">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5 text-base-content/30">
                                        <path fill-rule="evenodd" d="M10 3a.75.75 0 01.75.75v13.5a.75.75 0 01-1.5 0V3.75A.75.75 0 0110 3z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="timeline-start mb-8 whitespace-nowrap text-xs font-medium text-base-content/60 md:text-end">
                                    {{ step.date ? formatDate(step.date) : '—' }}
                                </div>
                                <div class="timeline-middle">
                                    <span
                                        class="flex h-3.5 w-3.5 items-center justify-center rounded-full ring-4 ring-base-100"
                                        :class="step.done ? 'bg-success' : 'bg-base-300'"
                                    />
                                </div>
                                <div class="timeline-end mb-8 max-w-md md:mb-10">
                                    <div class="rounded-lg border border-base-200 bg-base-100 px-3 py-2 shadow-sm">
                                        <p class="font-semibold">{{ step.label }}</p>
                                        <p v-if="step.detail" class="mt-0.5 text-sm text-base-content/60">{{ step.detail }}</p>
                                        <span v-if="!step.done" class="badge badge-ghost badge-sm mt-2">Menunggu</span>
                                    </div>
                                </div>
                                <hr v-if="index < cctvLifecycleTimeline.length - 1" />
                            </li>
                        </ul>
                    </div>
                </div>

                <div v-else class="ocn-panel">
                    <div class="ocn-panel__head">
                        <h2 class="ocn-panel__title">Gantt timeline</h2>
                        <p class="ocn-panel__desc">Otomatis dari tanggal mulai–selesai project.</p>
                    </div>
                    <div class="card-body">
                        <div v-if="project.started_at && project.finished_at" class="mt-1 space-y-3">
                            <div v-for="(phase, idx) in ganttPhases" :key="idx" class="space-y-1">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="font-medium">{{ phase.name }}</span>
                                    <span class="text-base-content/60">{{ phase.start }} → {{ phase.end }}</span>
                                </div>
                                <div class="relative h-6 rounded bg-base-200">
                                    <div class="absolute top-0 h-6 rounded bg-primary/80" :style="{ left: phase.left, width: phase.width }" />
                                </div>
                            </div>
                        </div>
                        <p v-else class="text-sm text-base-content/60">Isi tanggal mulai dan selesai untuk menampilkan Gantt.</p>
                    </div>
                </div>
            </div>

            <div v-if="activeTab === 'work'" class="space-y-4">
                <div class="grid grid-cols-1 gap-4 xl:grid-cols-[1.2fr_0.8fr]">
                    <div class="ocn-panel">
                        <div class="ocn-panel__head flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <h2 class="ocn-panel__title">Task pekerjaan project</h2>
                                <p class="ocn-panel__desc">Pantau pekerjaan aktif, PIC, due date, dan progres penyelesaian.</p>
                            </div>
                            <button class="btn btn-primary btn-sm shrink-0" @click="openTaskModal">+ Tambah task</button>
                        </div>
                        <div class="card-body space-y-4">
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                <div class="rounded-xl border border-base-200 bg-base-200/30 p-4">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">Total Task</p>
                                    <p class="mt-2 text-2xl font-bold tabular-nums">{{ taskChecklistSummary.total }}</p>
                                </div>
                                <div class="rounded-xl border border-warning/30 bg-warning/10 p-4">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-warning/80">Sedang Jalan</p>
                                    <p class="mt-2 text-2xl font-bold tabular-nums text-warning">{{ taskChecklistSummary.active }}</p>
                                </div>
                                <div class="rounded-xl border border-success/30 bg-success/10 p-4">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-success/80">Selesai</p>
                                    <p class="mt-2 text-2xl font-bold tabular-nums text-success">{{ taskChecklistSummary.done }}</p>
                                </div>
                            </div>

                            <div class="space-y-3">
                                <div
                                    v-for="task in projectTasks"
                                    :key="task.id"
                                    :class="[
                                        'rounded-xl border p-4 transition-colors',
                                        taskCardClass(task.status),
                                    ]"
                                >
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                        <label class="flex min-w-0 items-start gap-3">
                                            <input
                                                type="checkbox"
                                                class="checkbox checkbox-sm mt-0.5"
                                                :checked="task.status === 'done'"
                                                @change="toggleTaskChecklist(task, $event.target.checked)"
                                            >
                                            <div class="min-w-0">
                                                <p class="font-semibold" :class="task.status === 'done' ? 'line-through text-base-content/60' : ''">{{ task.title }}</p>
                                                <p v-if="task.description" class="mt-1 text-sm text-base-content/70">{{ task.description }}</p>
                                                <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-base-content/60">
                                                    <span class="badge badge-ghost badge-sm">{{ statusLabel(task.status) }}</span>
                                                    <span>PIC: {{ task.assigned_user_name ?? '-' }}</span>
                                                    <span>Due: {{ formatDate(task.due_date) }}</span>
                                                </div>
                                            </div>
                                        </label>
                                        <div class="flex flex-wrap items-center gap-2 shrink-0">
                                            <select class="select select-bordered select-xs" :value="task.status" @change="updateTaskStatus(task, $event.target.value)">
                                                <option value="todo">To Do</option>
                                                <option value="in_progress">In Progress</option>
                                                <option value="done">Done</option>
                                            </select>
                                            <button class="btn btn-ghost btn-xs text-error" @click="deleteTask(task)">Hapus</button>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="!projectTasks.length" class="rounded-xl border border-dashed border-base-300 px-4 py-10 text-center text-sm text-base-content/60">
                                    Belum ada task pekerjaan untuk project ini.
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="ocn-panel">
                        <div class="ocn-panel__head">
                            <h2 class="ocn-panel__title">Checklist item digunakan</h2>
                            <p class="ocn-panel__desc">Material yang sudah ready bisa dicentang untuk menandai qty planned sebagai sudah digunakan.</p>
                        </div>
                        <div class="card-body space-y-4">
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3 xl:grid-cols-1">
                                <div class="rounded-xl border border-base-200 bg-base-200/30 p-4">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">Item Dipakai</p>
                                    <p class="mt-2 text-2xl font-bold tabular-nums">{{ materialUsageSummary.used }} / {{ materialUsageSummary.total }}</p>
                                </div>
                                <div class="rounded-xl border border-info/30 bg-info/10 p-4">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-info/80">Qty Reserved</p>
                                    <p class="mt-2 text-2xl font-bold tabular-nums text-info">{{ materialUsageSummary.reservedQty }}</p>
                                </div>
                                <div class="rounded-xl border border-primary/30 bg-primary/10 p-4">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-primary/80">Qty Used</p>
                                    <p class="mt-2 text-2xl font-bold tabular-nums text-primary">{{ materialUsageSummary.issuedQty }}</p>
                                </div>
                            </div>

                            <div class="space-y-3">
                                <label
                                    v-for="item in materialUsageChecklist"
                                    :key="item.id"
                                    class="flex items-start gap-3 rounded-xl border border-base-200 bg-base-100 px-4 py-3"
                                >
                                    <input
                                        type="checkbox"
                                        class="checkbox checkbox-sm mt-0.5"
                                        :checked="item.isFullyUsed"
                                        :disabled="!item.canToggleUsage"
                                        @change="toggleMaterialUsage(item, $event.target.checked)"
                                    >
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                            <div class="min-w-0">
                                                <p class="font-semibold">{{ item.product }}</p>
                                                <p class="text-xs text-base-content/60">{{ item.sku || '-' }} · {{ item.warehouse || '-' }}</p>
                                            </div>
                                            <span
                                                class="badge badge-sm"
                                                :class="item.isFullyUsed ? 'badge-success' : item.hasPartialUsage ? 'badge-info' : 'badge-ghost'"
                                            >
                                                {{ item.isFullyUsed ? 'Sudah dipakai' : item.hasPartialUsage ? 'Dipakai sebagian' : 'Belum dipakai' }}
                                            </span>
                                        </div>
                                        <div class="mt-3 grid grid-cols-2 gap-3 text-xs text-base-content/70">
                                            <div>
                                                <p class="text-base-content/50">Qty digunakan</p>
                                                <p class="font-semibold text-base-content">{{ item.issuedQty }} {{ item.uom }}</p>
                                            </div>
                                            <div>
                                                <p class="text-base-content/50">Target / planned</p>
                                                <p class="font-semibold text-base-content">{{ item.plannedQty }} {{ item.uom }}</p>
                                            </div>
                                        </div>
                                        <p v-if="!item.canToggleUsage" class="mt-3 text-xs text-base-content/55">
                                            Checklist aktif setelah material berstatus ready.
                                        </p>
                                        <progress class="progress progress-primary mt-3 h-2 w-full" :value="item.issuedQty" :max="item.plannedQty || 1" />
                                    </div>
                                </label>

                                <div v-if="!materialUsageChecklist.length" class="rounded-xl border border-dashed border-base-300 px-4 py-10 text-center text-sm text-base-content/60">
                                    Belum ada item material untuk project ini.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="activeTab === 'materials'" class="space-y-4">
                <div class="flex flex-wrap gap-2">
                    <button class="btn btn-primary btn-sm" onclick="document.getElementById('modal-add-material').showModal()">+ Tambah Material</button>
                    <button class="btn btn-outline btn-sm" @click="openSidebarCanvas('project-stock-audit')">
                        Audit stok project
                    </button>
                </div>

                <div class="ocn-panel">
                    <div class="ocn-panel__head">
                        <h2 class="ocn-panel__title">Daftar material project</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead><tr><th>SKU</th><th>Produk</th><th>Warehouse</th><th>Planned</th><th class="text-right">HPP</th><th class="text-right">Harga Jual</th><th>Reserved</th><th>Issued</th><th>Status</th><th></th></tr></thead>
                            <tbody>
                                <tr v-for="m in project.materials" :key="m.id">
                                    <td class="font-mono text-xs">{{ m.sku }}</td>
                                    <td>{{ m.product }}</td>
                                    <td>{{ m.warehouse }}</td>
                                    <td>{{ m.planned_qty }} {{ m.uom }}</td>
                                    <td class="text-right tabular-nums">{{ format(m.subtotal_cost) }}</td>
                                    <td class="text-right tabular-nums font-semibold">{{ format(m.subtotal_price) }}</td>
                                    <td>{{ m.reserved_qty }} {{ m.uom }}</td>
                                    <td>{{ m.issued_qty }} {{ m.uom }}</td>
                                    <td><span class="badge badge-sm" :class="materialStatusClass(m.status)">{{ materialStatusLabel(m.status) }}</span></td>
                                    <td class="text-right"><button class="btn btn-ghost btn-xs text-error" @click="confirmDeleteMaterial(m.id)">Hapus</button></td>
                                </tr>
                                <tr v-if="!project.materials.length"><td colspan="10" class="text-center py-6 text-base-content/50">Belum ada material project.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <teleport to="body">
                <div
                    v-if="sidebarCanvasMode"
                    class="fixed inset-0 z-40 bg-slate-950/35 backdrop-blur-[1px]"
                    @click="closeSidebarCanvas"
                />
                <aside
                    class="fixed inset-y-0 right-0 z-50 flex w-full max-w-2xl flex-col border-l border-base-300 bg-base-100 shadow-2xl transition-transform duration-300 ease-out"
                    :class="sidebarCanvasMode ? 'translate-x-0' : 'translate-x-full'"
                >
                    <div class="flex items-start justify-between gap-3 border-b border-base-200 px-5 py-4">
                        <div>
                            <h2 class="text-lg font-bold">
                                {{ isProjectStockAuditCanvas ? 'Audit stok project' : isTeamCalculatorCanvas ? 'Kalkulator Tim' : 'Kalkulator Referral' }}
                            </h2>
                            <p class="mt-1 text-sm text-base-content/60">
                                {{ isProjectStockAuditCanvas
                                    ? 'Bandingkan issued material project dengan movement stok aktual di gudang.'
                                    : isTeamCalculatorCanvas
                                        ? 'Simulasi pembagian tim dari estimasi project setelah dikurangi HPP dan expense.'
                                        : 'Hitung nominal komisi referral tanpa meninggalkan halaman project.' }}
                            </p>
                        </div>
                        <button class="btn btn-ghost btn-sm" @click="closeSidebarCanvas">Tutup</button>
                    </div>

                    <div class="flex-1 overflow-y-auto px-5 py-4">
                        <div v-if="isProjectStockAuditCanvas" class="space-y-4">
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div class="rounded-xl border border-base-200 bg-base-200/30 p-4">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">Item Dicek</p>
                                    <p class="mt-2 text-2xl font-bold tabular-nums">{{ projectStockCheckSummary.line_count }}</p>
                                </div>
                                <div
                                    class="rounded-xl p-4"
                                    :class="projectStockCheckSummary.mismatch_count > 0 ? 'border border-error/30 bg-error/10' : 'border border-success/30 bg-success/10'"
                                >
                                    <p
                                        class="text-xs font-semibold uppercase tracking-wide"
                                        :class="projectStockCheckSummary.mismatch_count > 0 ? 'text-error/80' : 'text-success/80'"
                                    >
                                        Mismatch
                                    </p>
                                    <p
                                        class="mt-2 text-2xl font-bold tabular-nums"
                                        :class="projectStockCheckSummary.mismatch_count > 0 ? 'text-error' : 'text-success'"
                                    >
                                        {{ projectStockCheckSummary.mismatch_count }}
                                    </p>
                                </div>
                                <div class="rounded-xl border border-primary/30 bg-primary/10 p-4">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-primary/80">Issued Qty</p>
                                    <p class="mt-2 text-2xl font-bold tabular-nums text-primary">{{ projectStockCheckSummary.total_issued_qty }}</p>
                                </div>
                                <div class="rounded-xl border border-info/30 bg-info/10 p-4">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-info/80">Movement Net</p>
                                    <p class="mt-2 text-2xl font-bold tabular-nums text-info">{{ projectStockCheckSummary.total_movement_net }}</p>
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <button
                                    class="btn btn-outline btn-sm"
                                    :disabled="syncProjectStockForm.processing || projectStockCheckLines.length === 0"
                                    @click="syncProjectStock"
                                >
                                    {{ syncProjectStockForm.processing ? 'Menyinkronkan...' : 'Sinkronkan stok project' }}
                                </button>
                            </div>

                            <div
                                v-if="projectStockCheckWarnings.length"
                                class="rounded-xl border border-error/30 bg-error/10 px-4 py-3 text-sm text-error"
                            >
                                <p class="font-semibold">Ditemukan selisih stok project.</p>
                                <ul class="mt-2 list-disc pl-5">
                                    <li v-for="warning in projectStockCheckWarnings" :key="warning">{{ warning }}</li>
                                </ul>
                            </div>

                            <div v-if="projectStockCheckLines.length" class="space-y-3">
                                <article
                                    v-for="row in projectStockCheckLines"
                                    :key="row.material_id"
                                    class="rounded-2xl border border-base-200 bg-base-100 p-4"
                                >
                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                        <div class="min-w-0">
                                            <p class="font-semibold">{{ row.name }}</p>
                                            <p class="text-xs text-base-content/60">{{ row.sku || '-' }} · {{ row.warehouse || '-' }}</p>
                                        </div>
                                        <span class="badge badge-sm" :class="stockCheckBadgeClass(row.is_synced)">
                                            {{ row.is_synced ? 'Sinkron' : 'Mismatch' }}
                                        </span>
                                    </div>

                                    <div class="mt-4 grid grid-cols-2 gap-3 text-sm md:grid-cols-3">
                                        <div>
                                            <p class="text-xs uppercase tracking-wide text-base-content/50">Issued</p>
                                            <p class="mt-1 font-semibold">{{ row.issued_qty }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs uppercase tracking-wide text-base-content/50">Movement Net</p>
                                            <p class="mt-1 font-semibold">{{ row.movement_net }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs uppercase tracking-wide text-base-content/50">Delta</p>
                                            <p class="mt-1 font-semibold" :class="Math.abs(Number(row.delta_qty) || 0) > 0.00001 ? 'text-error' : 'text-success'">
                                                {{ row.delta_qty }}
                                            </p>
                                        </div>
                                        <div>
                                            <p class="text-xs uppercase tracking-wide text-base-content/50">WH Qty</p>
                                            <p class="mt-1 font-semibold">{{ row.warehouse_qty }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs uppercase tracking-wide text-base-content/50">WH Reserved</p>
                                            <p class="mt-1 font-semibold">{{ row.warehouse_reserved }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs uppercase tracking-wide text-base-content/50">Status Material</p>
                                            <p class="mt-1 font-semibold">{{ materialStatusLabel(row.status) }}</p>
                                        </div>
                                    </div>
                                </article>
                            </div>

                            <div v-else class="rounded-xl border border-dashed border-base-300 px-4 py-8 text-center text-sm text-base-content/60">
                                Tidak ada material stock-tracked yang perlu diaudit pada project ini.
                            </div>
                        </div>

                        <div v-else-if="isTeamCalculatorCanvas" class="space-y-4">
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div class="rounded-xl border border-base-200 bg-base-200/30 p-4">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">Estimasi Revenue</p>
                                    <p class="mt-2 text-xl font-bold">{{ format(teamCalculatorContractValue) }}</p>
                                </div>
                                <div class="rounded-xl border border-error/30 bg-error/10 p-4">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-error/80">HPP</p>
                                    <p class="mt-2 text-xl font-bold text-error">{{ format(teamCalculatorEstimatedCost) }}</p>
                                </div>
                                <div class="rounded-xl border border-warning/30 bg-warning/10 p-4">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-warning/80">Expense Project</p>
                                    <p class="mt-2 text-xl font-bold text-warning">{{ format(teamCalculatorExpense) }}</p>
                                </div>
                                <div class="rounded-xl border border-secondary/30 bg-secondary/10 p-4">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-secondary/80">Referral Tersimpan</p>
                                    <p class="mt-2 text-xl font-bold text-secondary">{{ format(teamCalculatorReferralTotal) }}</p>
                                </div>
                            </div>

                            <div class="rounded-2xl border border-base-200 bg-base-100 p-4">
                                <label class="label px-0 pt-0"><span class="label-text font-semibold">Rate cadangan perusahaan (%)</span></label>
                                <input
                                    :value="formatPercentageNumber(teamCalculatorRate)"
                                    type="text"
                                    inputmode="decimal"
                                    class="input input-bordered w-full"
                                    @input="teamCalculatorRate = clampPercentage(parseLocalizedNumber($event.target.value))"
                                >
                                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                                    <div>
                                        <p class="text-xs uppercase tracking-wide text-base-content/50">Margin Estimasi</p>
                                        <p class="mt-1 font-semibold" :class="teamCalculatorEstimatedMargin >= 0 ? 'text-success' : 'text-error'">{{ format(teamCalculatorEstimatedMargin) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs uppercase tracking-wide text-base-content/50">Nilai Dasar Kalkulator</p>
                                        <p class="mt-1 font-semibold" :class="teamCalculatorNetEstimate >= 0 ? 'text-success' : 'text-error'">{{ format(teamCalculatorNetEstimate) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs uppercase tracking-wide text-base-content/50">Cadangan</p>
                                        <p class="mt-1 font-semibold">{{ format(teamCalculatorReserveAmount) }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-2xl border border-base-200 bg-base-100 p-4">
                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                    <div>
                                        <p class="text-xs uppercase tracking-wide text-base-content/50">Pool Tim</p>
                                        <p class="mt-1 font-semibold text-primary">{{ format(teamCalculatorPool) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs uppercase tracking-wide text-base-content/50">Sudah Dialokasikan</p>
                                        <p class="mt-1 font-semibold">{{ format(teamCalculatorDistributed) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs uppercase tracking-wide text-base-content/50">Sisa Pool</p>
                                        <p class="mt-1 font-semibold" :class="teamCalculatorRemaining >= 0 ? 'text-success' : 'text-error'">{{ format(teamCalculatorRemaining) }}</p>
                                    </div>
                                </div>
                                <p class="mt-4 text-xs text-base-content/60">
                                    Kalkulator ini memakai rumus estimasi revenue dikurangi HPP dan expense, jadi tetap bisa dipakai walaupun invoice atau cash in belum masuk.
                                </p>
                                <div class="mt-4 flex flex-wrap gap-2">
                                    <button class="btn btn-primary btn-sm" @click="addTeamCalculatorRow">Tambah Tim</button>
                                    <button class="btn btn-outline btn-sm" @click="openAssignTeamModal">Assign via modal</button>
                                </div>
                            </div>

                            <div class="rounded-2xl border border-base-200 bg-base-100 p-4">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <h3 class="font-semibold">Pembagian tim di canvas</h3>
                                        <p class="mt-1 text-sm text-base-content/60">
                                            Tambah anggota, atur persen atau nominal rupiah, lalu simpan dari canvas ini. Hasil simpan akan masuk ke tabel Tim project.
                                        </p>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <button class="btn btn-outline btn-sm" @click="addTeamCalculatorRow">Tambah Baris</button>
                                        <button
                                            class="btn btn-ghost btn-sm"
                                            :disabled="!teamCalculatorSimulationDirty"
                                            @click="resetTeamCalculatorShares"
                                        >
                                            Reset
                                        </button>
                                    </div>
                                </div>

                                <div v-if="!teamCalculatorRateValid || !teamCalculatorPercentageValid || !teamCalculatorBudgetValid || !teamCalculatorRowsValid || teamCalculatorHasDuplicates || teamCalculatorSaveForm.errors.distributions" class="mt-4 space-y-2">
                                    <div v-if="!teamCalculatorRateValid" class="rounded-xl border border-warning/30 bg-warning/10 px-4 py-3 text-sm text-warning">
                                        Rate cadangan perusahaan harus antara 0,00% sampai 100,00%.
                                    </div>
                                    <div v-if="!teamCalculatorPercentageValid" class="rounded-xl border border-warning/30 bg-warning/10 px-4 py-3 text-sm text-warning">
                                        Total pembagian saat ini {{ formatPercentage(teamCalculatorSimulatedPercentageTotal) }} dan tidak boleh melebihi 100,00%.
                                    </div>
                                    <div v-if="!teamCalculatorBudgetValid" class="rounded-xl border border-error/30 bg-error/10 px-4 py-3 text-sm text-error">
                                        Total simulasi {{ format(teamCalculatorSimulatedDistributed) }} melebihi pool tim {{ format(teamCalculatorPool) }}.
                                    </div>
                                    <div v-if="!teamCalculatorRowsValid" class="rounded-xl border border-info/30 bg-info/10 px-4 py-3 text-sm text-info">
                                        Setiap baris wajib punya anggota dan peran sebelum disimpan.
                                    </div>
                                    <div v-if="teamCalculatorHasDuplicates" class="rounded-xl border border-warning/30 bg-warning/10 px-4 py-3 text-sm text-warning">
                                        Anggota terpilih ganda:
                                        {{ teamCalculatorDuplicateMemberIds.map(id => team_members.find(member => String(member.id) === String(id))?.name).filter(Boolean).join(', ') }}.
                                    </div>
                                    <div v-if="teamCalculatorSaveForm.errors.distributions" class="rounded-xl border border-error/30 bg-error/10 px-4 py-3 text-sm text-error">
                                        {{ teamCalculatorSaveForm.errors.distributions }}
                                    </div>
                                </div>

                                <div v-if="teamCalculatorAssignmentRows.length" class="mt-4 space-y-4">
                                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                        <div class="rounded-xl border border-base-200 bg-base-200/30 p-4">
                                            <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">Total Persentase</p>
                                            <p
                                                class="mt-2 text-xl font-bold tabular-nums"
                                                :class="Math.abs(teamCalculatorSimulatedPercentageRemaining) <= 0.01 ? 'text-success' : 'text-warning'"
                                            >
                                                {{ formatPercentage(teamCalculatorSimulatedPercentageTotal) }}
                                            </p>
                                        </div>
                                        <div class="rounded-xl border border-primary/30 bg-primary/10 p-4">
                                            <p class="text-xs font-semibold uppercase tracking-wide text-primary/80">Total Simulasi</p>
                                            <p class="mt-2 text-xl font-bold tabular-nums text-primary">{{ format(teamCalculatorSimulatedDistributed) }}</p>
                                        </div>
                                        <div
                                            class="rounded-xl p-4"
                                            :class="teamCalculatorSimulatedRemaining >= 0 ? 'border border-success/30 bg-success/10' : 'border border-error/30 bg-error/10'"
                                        >
                                            <p
                                                class="text-xs font-semibold uppercase tracking-wide"
                                                :class="teamCalculatorSimulatedRemaining >= 0 ? 'text-success/80' : 'text-error/80'"
                                            >
                                                Sisa Pool Simulasi
                                            </p>
                                            <p
                                                class="mt-2 text-xl font-bold tabular-nums"
                                                :class="teamCalculatorSimulatedRemaining >= 0 ? 'text-success' : 'text-error'"
                                            >
                                                {{ format(teamCalculatorSimulatedRemaining) }}
                                            </p>
                                        </div>
                                    </div>

                                    <div
                                        class="rounded-xl border px-4 py-3 text-sm"
                                        :class="Math.abs(teamCalculatorSimulatedPercentageRemaining) <= 0.01 ? 'border-success/30 bg-success/10 text-success' : 'border-warning/30 bg-warning/10 text-warning'"
                                    >
                                        <span v-if="Math.abs(teamCalculatorSimulatedPercentageRemaining) <= 0.01">
                                            Total pembagian sudah pas 100,00%.
                                        </span>
                                        <span v-else-if="teamCalculatorSimulatedPercentageRemaining > 0">
                                            Masih ada sisa {{ formatPercentage(teamCalculatorSimulatedPercentageRemaining) }} yang belum dialokasikan.
                                        </span>
                                        <span v-else>
                                            Alokasi melebihi 100% sebesar {{ formatPercentage(Math.abs(teamCalculatorSimulatedPercentageRemaining)) }}.
                                        </span>
                                    </div>

                                    <article
                                        v-for="distribution in teamCalculatorAssignmentRows"
                                        :key="distribution.key"
                                        class="rounded-2xl border border-base-200 bg-base-100 p-4"
                                    >
                                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                            <div>
                                                <p class="font-semibold">{{ distribution.user_name }}</p>
                                                <p class="text-sm text-base-content/60">{{ roleLabel(distribution.role_in_project) }}</p>
                                            </div>
                                            <div class="flex flex-wrap items-center gap-2 text-xs">
                                                <span class="badge badge-ghost badge-sm">Tersimpan {{ formatPercentage(distribution.currentPercentage) }}</span>
                                                <span class="badge badge-primary badge-sm">Aktif {{ formatPercentage(distribution.sliderPercentage) }}</span>
                                                <span v-if="distribution.paid_at" class="badge badge-success badge-sm">Sudah dibayar</span>
                                            </div>
                                        </div>

                                        <div class="mt-4 grid grid-cols-1 gap-3 xl:grid-cols-2">
                                            <div>
                                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-base-content/50">Anggota Tim</label>
                                                <select
                                                    :value="distribution.user_id"
                                                    class="select select-bordered select-sm w-full"
                                                    @change="setTeamCalculatorUser(distribution.key, $event.target.value)"
                                                >
                                                    <option value="">Pilih anggota</option>
                                                    <option v-for="member in team_members" :key="member.id" :value="member.id">{{ member.name }}</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-base-content/50">Peran</label>
                                                <select
                                                    :value="distribution.role_in_project"
                                                    class="select select-bordered select-sm w-full"
                                                    @change="setTeamCalculatorRole(distribution.key, $event.target.value)"
                                                >
                                                    <option v-for="role in team_roles" :key="role.id" :value="role.name">{{ role.name }}</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="mt-4 rounded-xl border border-base-200 bg-base-200/20 p-3">
                                            <div class="mb-2 flex items-center justify-between gap-3 text-xs font-semibold uppercase tracking-wide text-base-content/50">
                                                <span>Slider Persentase</span>
                                                <span>{{ formatPercentage(distribution.sliderPercentage) }}</span>
                                            </div>
                                            <input
                                                :value="distribution.sliderPercentage"
                                                type="range"
                                                min="0"
                                                :max="teamCalculatorMaxAllowedPercentage(distribution.key)"
                                                step="0.01"
                                                class="range range-primary"
                                                @input="setTeamCalculatorShare(distribution.key, $event.target.value)"
                                            >
                                        </div>

                                        <div class="mt-4 grid grid-cols-1 gap-3 xl:grid-cols-[120px_minmax(0,1fr)_minmax(0,1fr)_44px]">
                                            <div>
                                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-base-content/50">Persen</label>
                                                <input
                                                    :value="formatPercentageNumber(distribution.sliderPercentage)"
                                                    type="text"
                                                    inputmode="decimal"
                                                    class="input input-bordered input-sm w-full text-right"
                                                    @input="setTeamCalculatorPercentageInput(distribution.key, $event.target.value)"
                                                >
                                                <p class="mt-1 text-[11px] text-base-content/50">
                                                    Maks {{ formatPercentage(teamCalculatorMaxAllowedPercentage(distribution.key)) }}
                                                </p>
                                            </div>
                                            <div>
                                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-base-content/50">Nominal Rupiah</label>
                                                <CurrencyInput
                                                    :model-value="distribution.simulatedBasePay"
                                                    @update:modelValue="setTeamCalculatorAmount(distribution.key, $event)"
                                                />
                                                <p class="mt-1 text-[11px] text-base-content/50">
                                                    Maks {{ format(Math.round(teamCalculatorPool * (teamCalculatorMaxAllowedPercentage(distribution.key) / 100))) }}
                                                </p>
                                            </div>
                                            <div>
                                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-base-content/50">Bonus</label>
                                                <CurrencyInput
                                                    :model-value="distribution.bonus"
                                                    @update:modelValue="setTeamCalculatorBonus(distribution.key, $event)"
                                                />
                                            </div>
                                            <div class="flex items-start justify-end xl:pt-6">
                                                <button
                                                    class="btn btn-ghost btn-square btn-sm text-error"
                                                    :disabled="!!distribution.paid_at"
                                                    @click="removeTeamCalculatorRow(distribution.key)"
                                                    :title="distribution.paid_at ? 'Baris yang sudah dibayar tidak bisa dihapus' : 'Hapus baris tim'"
                                                    aria-label="Hapus baris tim"
                                                >
                                                    <TrashIcon class="h-4 w-4" />
                                                </button>
                                            </div>
                                        </div>

                                        <div class="mt-4 grid grid-cols-2 gap-3 text-sm md:grid-cols-4">
                                            <div>
                                                <p class="text-xs uppercase tracking-wide text-base-content/50">Base Pay Simulasi</p>
                                                <p class="mt-1 font-semibold">{{ format(distribution.simulatedBasePay) }}</p>
                                            </div>
                                            <div>
                                                <p class="text-xs uppercase tracking-wide text-base-content/50">Bonus</p>
                                                <p class="mt-1 font-semibold">{{ format(distribution.bonus) }}</p>
                                            </div>
                                            <div>
                                                <p class="text-xs uppercase tracking-wide text-base-content/50">Total Simulasi</p>
                                                <p class="mt-1 font-semibold text-primary">{{ format(teamCalculatorRowTotalPay(distribution)) }}</p>
                                            </div>
                                            <div>
                                                <p class="text-xs uppercase tracking-wide text-base-content/50">Delta vs Tersimpan</p>
                                                <p
                                                    class="mt-1 font-semibold"
                                                    :class="distribution.simulatedDeltaPay >= 0 ? 'text-success' : 'text-error'"
                                                >
                                                    {{ distribution.simulatedDeltaPay >= 0 ? '+' : '-' }}{{ format(Math.abs(distribution.simulatedDeltaPay)) }}
                                                </p>
                                            </div>
                                        </div>
                                    </article>

                                    <div class="flex flex-col gap-3 border-t border-base-300 pt-4 sm:flex-row sm:items-center sm:justify-between">
                                        <div>
                                            <p class="text-sm font-semibold">Total simulasi: {{ format(teamCalculatorSimulatedDistributed) }}</p>
                                            <p class="text-xs text-base-content/60">Klik simpan untuk menulis pembagian tim ke project dan memperbarui tabel Tim.</p>
                                        </div>
                                        <div class="flex flex-wrap gap-2">
                                            <Link
                                                class="btn btn-outline btn-sm"
                                                :href="route('team-distribution.calculator', { project_id: project.id })"
                                            >
                                                Buka kalkulator penuh
                                            </Link>
                                            <button class="btn btn-primary btn-sm" :disabled="!canSaveTeamCalculator" @click="saveTeamCalculator">
                                                {{ teamCalculatorSaveForm.processing ? 'Menyimpan...' : 'Simpan ke Tim' }}
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div v-else class="mt-4 rounded-xl border border-dashed border-base-300 px-4 py-8 text-center text-sm text-base-content/60">
                                    <p>Belum ada anggota tim di canvas ini.</p>
                                    <div class="mt-4 flex flex-wrap justify-center gap-2">
                                        <button class="btn btn-primary btn-sm" @click="addTeamCalculatorRow">Tambah Tim</button>
                                        <button class="btn btn-outline btn-sm" @click="openAssignTeamModal">Assign via modal</button>
                                        <Link
                                            class="btn btn-primary btn-sm"
                                            :href="route('team-distribution.calculator', { project_id: project.id })"
                                        >
                                            Buka kalkulator penuh
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-else-if="isReferralCalculatorCanvas" class="space-y-4">
                            <div class="rounded-2xl border border-base-200 bg-base-100 p-4">
                                <label class="label px-0 pt-0"><span class="label-text font-semibold">Basis Perhitungan</span></label>
                                <select v-model="referralCalculatorBasis" class="select select-bordered w-full">
                                    <option v-for="option in referralBasisOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                </select>

                                <label class="label mt-3 px-0"><span class="label-text font-semibold">Persentase Komisi (%)</span></label>
                                <input v-model.number="referralCalculatorRate" type="number" min="0" max="100" step="0.01" class="input input-bordered w-full" />
                            </div>

                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div class="rounded-xl border border-base-200 bg-base-200/30 p-4">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">Nilai Basis</p>
                                    <p class="mt-2 text-xl font-bold">{{ format(referralCalculatorBaseAmount) }}</p>
                                </div>
                                <div class="rounded-xl border border-secondary/30 bg-secondary/10 p-4">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-secondary/80">Nominal Komisi</p>
                                    <p class="mt-2 text-xl font-bold text-secondary">{{ format(referralCalculatorAmount) }}</p>
                                </div>
                            </div>

                            <div class="rounded-2xl border border-base-200 bg-base-100 p-4">
                                <p class="text-sm text-base-content/70">
                                    Gunakan kalkulator ini untuk menentukan nominal komisi referral dengan basis estimasi project atau nilai real yang sudah masuk.
                                </p>
                                <div class="mt-4 flex flex-wrap gap-2">
                                    <button class="btn btn-primary btn-sm" @click="applyReferralCalculatorAmount">Gunakan nominal ini</button>
                                    <button class="btn btn-outline btn-sm" @click="openAddReferralModal">Input referral manual</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </aside>
            </teleport>

            <!-- Tab: Expenses -->
            <div v-if="activeTab === 'kas'" class="space-y-4">
                <div class="flex gap-2">
                    <button class="btn btn-error btn-sm" onclick="document.getElementById('modal-cash-out').showModal()">+ Input Expense</button>
                    <Link v-if="project.invoice?.show_url" class="btn btn-outline btn-sm" :href="project.invoice.show_url">
                        Lihat invoice & pembayaran
                    </Link>
                </div>
                <div class="ocn-panel">
                    <div class="ocn-panel__head">
                        <h2 class="ocn-panel__title">Riwayat expenses project</h2>
                        <p class="ocn-panel__desc">Pemasukan project dicatat dari termin/invoice. Halaman ini hanya untuk biaya tim dan pengeluaran project.</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Kategori</th>
                                    <th class="text-right">Jumlah</th>
                                    <th>Penerima / Oleh</th>
                                    <th>Keterangan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="c in expenseEntries"
                                    :key="c.id"
                                    class="border-l-4 border-l-error"
                                >
                                    <td class="whitespace-nowrap">{{ formatDate(c.date) }}</td>
                                    <td><span class="badge badge-sm badge-ghost">{{ categoryLabel(c.category) }}</span></td>
                                    <td class="text-right font-semibold tabular-nums text-error">
                                        -{{ format(c.amount) }}
                                    </td>
                                    <td class="text-sm text-base-content/70">{{ c.recipient_name ?? c.creator_name ?? '-' }}</td>
                                    <td class="text-sm text-base-content/70">{{ c.note ?? '-' }}</td>
                                </tr>
                                <tr v-if="expenseEntries.length === 0">
                                    <td colspan="5" class="text-center py-6 text-base-content/50">Belum ada expense project</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Tab: Tim -->
            <div v-if="activeTab === 'docs'" class="space-y-4">
                <div class="ocn-panel">
                    <div class="ocn-panel__head">
                        <h2 class="ocn-panel__title">Dokumen legal (kontrak)</h2>
                        <p class="ocn-panel__desc">
                            Folder khusus project di Legal Workspace. Unggah kontrak kerja, NDA, atau dokumen lain ke folder ini.
                        </p>
                    </div>
                    <div class="card-body space-y-3">
                        <p class="text-sm text-base-content/70 flex flex-wrap items-center gap-2">
                            Path vault:
                            <span class="font-mono text-xs bg-base-200 px-2 py-0.5 rounded">
                                {{ project.legal_documents?.vault_path || project.legal_documents?.default_path_hint || '-' }}
                            </span>
                            <span v-if="project.legal_documents?.folder_exists" class="badge badge-success badge-sm">Folder tersedia</span>
                            <span v-else class="badge badge-warning badge-sm">Belum dibuat</span>
                            <span v-if="project.legal_documents?.has_saved_mapping" class="badge badge-info badge-sm">Path tersimpan</span>
                            <span v-else class="badge badge-ghost badge-sm">Default disarankan</span>
                        </p>
                        <p class="text-xs text-base-content/55">
                            Folder dokumen dibuat hanya saat tombol dibuat ditekan. Setelah dibuat, path disimpan ke project agar tidak berubah saat nama project diedit.
                            — ubah di <Link :href="route('projects.edit', project.id)" class="link link-primary">Edit project</Link>.
                        </p>
                        <p v-if="createFolderForm.errors.legal_vault_path" class="text-xs text-error">{{ createFolderForm.errors.legal_vault_path }}</p>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-if="!project.legal_documents?.folder_exists"
                                type="button"
                                class="btn btn-primary btn-sm"
                                :disabled="createFolderForm.processing"
                                @click="createLegalFolder"
                            >Buat folder dokumen</button>
                            <Link
                                v-if="project.legal_documents?.folder_exists && project.legal_documents?.vault_path"
                                :href="route('erp.hr.legal', { path: project.legal_documents.vault_path })"
                                class="btn btn-primary btn-sm"
                            >Buka folder di Legal</Link>
                            <Link :href="route('projects.edit', project.id)" class="btn btn-outline btn-sm">Atur path</Link>
                            <Link :href="route('erp.hr')" class="btn btn-outline btn-sm">Ke modul HR</Link>
                        </div>
                    </div>
                </div>

                <div class="ocn-panel">
                    <div class="ocn-panel__head">
                        <h2 class="ocn-panel__title">Invoice project</h2>
                        <p class="ocn-panel__desc">Halaman invoice penagihan (setelah project selesai).</p>
                    </div>
                    <div class="card-body space-y-3">
                        <template v-if="project.invoice?.available && project.invoice?.show_url">
                            <p v-if="project.invoice.number" class="text-sm">
                                Nomor invoice: <span class="font-semibold font-mono">{{ project.invoice.number }}</span>
                            </p>
                            <p v-else class="text-sm text-base-content/70">
                                Nomor invoice akan muncul setelah invoice pertama kali di-generate / dibuka dari Sales.
                            </p>
                            <Link :href="project.invoice.show_url" class="btn btn-primary btn-sm">Buka halaman invoice</Link>
                        </template>
                        <p v-else class="text-sm text-base-content/70">
                            Invoice belum tersedia. Ubah status project ke <strong>Selesai</strong> untuk mengaktifkan invoice project di menu Sales.
                        </p>
                    </div>
                </div>
            </div>

            <div v-if="activeTab === 'tim'" class="space-y-4">
                <div class="ocn-panel">
                    <div class="ocn-panel__head flex flex-wrap items-center justify-between gap-2">
                        <h2 class="ocn-panel__title">Pembagian tim</h2>
                        <div class="flex gap-2 shrink-0">
                                <button class="btn btn-outline btn-sm" @click="openAssignTeamModal">Assign Tim</button>
                                <button class="btn btn-primary btn-sm" @click="openSidebarCanvas('team-calculator')">
                                    Kalkulator
                                </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead><tr><th>Nama</th><th>Peran</th><th>%</th><th>Base Pay</th><th>Bonus</th><th>Total</th><th></th></tr></thead>
                            <tbody>
                                <tr v-for="d in project.team_distributions" :key="d.id">
                                    <td class="font-medium">{{ d.user_name }}</td>
                                    <td>{{ roleLabel(d.role_in_project) }}</td>
                                    <td>{{ d.percentage }}%</td>
                                    <td>{{ format(d.base_pay) }}</td>
                                    <td>{{ format(d.bonus) }}</td>
                                    <td class="font-semibold text-primary">{{ format(d.total_pay) }}</td>
                                    <td class="text-right"><button class="btn btn-ghost btn-xs text-error" @click="removeTeamMember(d.id)">Lepas</button></td>
                                </tr>
                                <tr v-if="!project.team_distributions.length"><td colspan="7" class="text-center py-6 text-base-content/50">Belum ada pembagian tim</td></tr>
                            </tbody>
                        </table>
                        </div>
                    </div>
                </div>

                <div class="ocn-panel">
                    <div class="ocn-panel__head flex flex-wrap items-center justify-between gap-2">
                        <h2 class="ocn-panel__title">Komisi referral</h2>
                        <div class="flex gap-2 shrink-0">
                            <button class="btn btn-outline btn-sm" @click="openSidebarCanvas('referral-calculator')">Kalkulator</button>
                            <button class="btn btn-outline btn-sm" @click="openAddReferralModal">Add referral</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead><tr><th>Nama Referrer</th><th>Komisi</th><th>Tgl Bayar</th><th>Catatan</th></tr></thead>
                            <tbody>
                                <tr v-for="r in project.referrals" :key="r.id">
                                    <td class="font-medium">{{ r.referrer_name }}</td>
                                    <td>{{ format(r.commission_amount) }}</td>
                                    <td class="whitespace-nowrap">{{ formatDate(r.paid_at) }}</td>
                                    <td class="text-sm text-base-content/70">{{ r.note ?? '-' }}</td>
                                </tr>
                                <tr v-if="!project.referrals.length"><td colspan="4" class="text-center py-6 text-base-content/50">Belum ada referral</td></tr>
                            </tbody>
                        </table>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="activeTab === 'kanban' && canShowKanban" class="space-y-4">
                <div class="ocn-panel">
                    <div class="ocn-panel__head flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h2 class="ocn-panel__title">Kanban task</h2>
                            <p class="ocn-panel__desc">Drag & drop antar kolom.</p>
                        </div>
                        <button class="btn btn-primary btn-sm shrink-0" @click="openTaskModal">+ Tambah task</button>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <div
                        v-for="column in kanbanColumns"
                        :key="column.key"
                        :class="[
                            'card shadow transition-colors',
                            dropColumnKey === column.key ? 'bg-primary/10 ring-2 ring-primary/30' : 'bg-base-100',
                        ]"
                        @dragover="onColumnDragOver(column.key, $event)"
                        @drop="onColumnDrop(column.key, $event)"
                        @dragleave="dropColumnKey = null"
                    >
                        <div class="card-body p-4">
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="font-semibold">{{ column.label }}</h3>
                                <span class="badge badge-ghost badge-sm">{{ column.tasks.length }}</span>
                            </div>
                            <div class="space-y-3">
                                <div
                                    v-for="task in column.tasks"
                                    :key="task.id"
                                    :class="[
                                        'rounded-lg border p-3 space-y-2 cursor-grab active:cursor-grabbing transition-colors',
                                        taskCardClass(task.status),
                                    ]"
                                    draggable="true"
                                    @dragstart="onTaskDragStart(task)"
                                    @dragend="onTaskDragEnd"
                                >
                                    <div class="font-medium">{{ task.title }}</div>
                                    <p v-if="task.description" class="text-sm text-base-content/70">{{ task.description }}</p>
                                    <div class="text-xs text-base-content/60">PIC: {{ task.assigned_user_name ?? '-' }}</div>
                                    <div class="text-xs text-base-content/60">Due: {{ formatDate(task.due_date) }}</div>
                                    <div class="flex gap-2">
                                        <select class="select select-bordered select-xs" :value="task.status" @change="updateTaskStatus(task, $event.target.value)">
                                            <option value="todo">To Do</option>
                                            <option value="in_progress">In Progress</option>
                                            <option value="done">Done</option>
                                        </select>
                                        <button class="btn btn-ghost btn-xs text-error" @click="deleteTask(task)">Hapus</button>
                                    </div>
                                </div>
                                <div v-if="!column.tasks.length" class="text-xs text-base-content/50 py-3">Belum ada task {{ statusLabel(column.key) }}.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <dialog id="modal-add-task" class="modal">
            <div class="modal-box max-w-2xl">
                <h3 class="font-bold text-lg">Tambah Task</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-3">
                    <div class="md:col-span-2">
                        <label class="label"><span class="label-text">Judul Task</span></label>
                        <input v-model="taskForm.title" type="text" class="input input-bordered w-full" />
                        <p v-if="taskForm.errors.title" class="text-error text-xs mt-1">{{ taskForm.errors.title }}</p>
                    </div>
                    <div>
                        <label class="label"><span class="label-text">Assignee</span></label>
                        <select v-model="taskForm.assigned_user_id" class="select select-bordered w-full">
                            <option value="">Unassigned</option>
                            <option v-for="user in assignableTaskMembers" :key="user.id" :value="user.id">
                                {{ user.name }}<template v-if="user.role"> - {{ user.role }}</template>
                            </option>
                        </select>
                        <p v-if="taskForm.errors.assigned_user_id" class="text-error text-xs mt-1">{{ taskForm.errors.assigned_user_id }}</p>
                        <p v-else-if="!assignableTaskMembers.length" class="text-xs text-base-content/60 mt-1">
                            Assign tim project dulu agar task bisa punya assignee.
                        </p>
                    </div>
                    <div>
                        <label class="label"><span class="label-text">Status</span></label>
                        <select v-model="taskForm.status" class="select select-bordered w-full">
                            <option value="todo">To Do</option>
                            <option value="in_progress">In Progress</option>
                            <option value="done">Done</option>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="label"><span class="label-text">Due Date</span></label>
                        <input v-model="taskForm.due_date" type="date" class="input input-bordered w-full" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="label"><span class="label-text">Deskripsi</span></label>
                        <textarea v-model="taskForm.description" class="textarea textarea-bordered w-full" rows="3" />
                    </div>
                </div>
                <div class="modal-action">
                    <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
                    <button class="btn btn-primary" :disabled="taskForm.processing" @click="submitTask">Tambah Task</button>
                </div>
            </div>
        </dialog>

        <dialog id="modal-assign-team" class="modal">
            <div class="modal-box max-w-xl">
                <h3 class="font-bold text-lg">Assign Tim Project</h3>
                <div class="space-y-3 mt-3">
                    <div>
                        <label class="label"><span class="label-text">Pilih Anggota</span></label>
                        <select v-model="teamForm.user_id" class="select select-bordered w-full">
                            <option value="">Pilih user</option>
                            <option v-for="user in team_members" :key="user.id" :value="user.id">{{ user.name }} ({{ user.email }})</option>
                        </select>
                        <p v-if="teamForm.errors.user_id" class="text-error text-xs mt-1">{{ teamForm.errors.user_id }}</p>
                    </div>
                    <div>
                        <label class="label"><span class="label-text">Role</span></label>
                        <select v-model="teamForm.team_role_id" class="select select-bordered w-full">
                            <option value="">Pilih role</option>
                            <option v-for="role in team_roles" :key="role.id" :value="role.id">{{ role.name }}</option>
                        </select>
                        <p v-if="teamForm.errors.team_role_id" class="text-error text-xs mt-1">{{ teamForm.errors.team_role_id }}</p>
                    </div>
                    <div>
                        <label class="label"><span class="label-text">Persentase (%)</span></label>
                        <input v-model.number="teamForm.percentage" type="number" min="0" max="100" step="0.01" class="input input-bordered w-full" />
                        <p v-if="teamForm.errors.percentage" class="text-error text-xs mt-1">{{ teamForm.errors.percentage }}</p>
                    </div>
                    <div>
                        <label class="label"><span class="label-text">Base Pay</span></label>
                        <input v-model.number="teamForm.base_pay" type="number" min="0" step="1000" class="input input-bordered w-full" @input="teamForm.total_pay = (Number(teamForm.base_pay) || 0) + (Number(teamForm.bonus) || 0)" />
                        <p v-if="teamForm.errors.base_pay" class="text-error text-xs mt-1">{{ teamForm.errors.base_pay }}</p>
                    </div>
                    <div>
                        <label class="label"><span class="label-text">Bonus</span></label>
                        <input v-model.number="teamForm.bonus" type="number" min="0" step="1000" class="input input-bordered w-full" @input="teamForm.total_pay = (Number(teamForm.base_pay) || 0) + (Number(teamForm.bonus) || 0)" />
                        <p v-if="teamForm.errors.bonus" class="text-error text-xs mt-1">{{ teamForm.errors.bonus }}</p>
                    </div>
                    <div>
                        <label class="label"><span class="label-text">Total Pay</span></label>
                        <input v-model.number="teamForm.total_pay" type="number" min="0" step="1000" class="input input-bordered w-full" />
                        <p class="text-xs text-base-content/60 mt-1">Default dihitung dari Base Pay + Bonus.</p>
                        <p v-if="teamForm.errors.total_pay" class="text-error text-xs mt-1">{{ teamForm.errors.total_pay }}</p>
                    </div>
                </div>
                <div class="modal-action">
                    <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
                    <button class="btn btn-primary" :disabled="teamForm.processing" @click="submitTeamMember">Assign Tim</button>
                </div>
            </div>
        </dialog>

        <dialog id="modal-add-referral" class="modal">
            <div class="modal-box max-w-2xl">
                <h3 class="font-bold text-lg">Tambah Referral</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-3">
                    <div class="md:col-span-2">
                        <label class="label"><span class="label-text">Nama Referrer</span></label>
                        <input v-model="referralForm.referrer_name" type="text" class="input input-bordered w-full" />
                        <p v-if="referralForm.errors.referrer_name" class="text-error text-xs mt-1">{{ referralForm.errors.referrer_name }}</p>
                    </div>
                    <div>
                        <CurrencyInput v-model="referralForm.commission_amount" label="Komisi" :required="true" :error="referralForm.errors.commission_amount" />
                    </div>
                    <div>
                        <label class="label"><span class="label-text">Tanggal Bayar</span></label>
                        <input v-model="referralForm.paid_at" type="date" class="input input-bordered w-full" />
                        <p v-if="referralForm.errors.paid_at" class="text-error text-xs mt-1">{{ referralForm.errors.paid_at }}</p>
                    </div>
                    <div class="md:col-span-2">
                        <label class="label"><span class="label-text">Catatan</span></label>
                        <textarea v-model="referralForm.note" class="textarea textarea-bordered w-full" rows="3" />
                        <p v-if="referralForm.errors.note" class="text-error text-xs mt-1">{{ referralForm.errors.note }}</p>
                    </div>
                </div>
                <div class="modal-action">
                    <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
                    <button class="btn btn-primary" :disabled="referralForm.processing" @click="submitReferral">Simpan Referral</button>
                </div>
            </div>
        </dialog>

        <!-- Modal: Mark Paid -->
        <dialog id="modal-pay-term" class="modal">
            <div class="modal-box">
                <h3 class="font-bold text-lg">Tandai Termin Lunas</h3>
                <div class="space-y-3 mt-4">
                    <div>
                        <label class="label"><span class="label-text">Tanggal Bayar</span></label>
                        <input v-model="payForm.paid_at" type="date" class="input input-bordered w-full" />
                        <p v-if="payForm.errors.paid_at" class="text-error text-xs mt-1">{{ payForm.errors.paid_at }}</p>
                    </div>
                    <div>
                        <label class="label"><span class="label-text">Sumber Dana Kas / Bank</span></label>
                        <select v-model="payForm.cash_account_id" class="select select-bordered w-full">
                            <option value="">Pilih akun kas/bank</option>
                            <option v-for="account in cash_accounts" :key="account.id" :value="account.id">{{ account.code }} - {{ account.name }}</option>
                        </select>
                        <p v-if="payForm.errors.cash_account_id" class="text-error text-xs mt-1">{{ payForm.errors.cash_account_id }}</p>
                    </div>
                    <div>
                        <label class="label"><span class="label-text">Catatan</span></label>
                        <input v-model="payForm.note" type="text" class="input input-bordered w-full" />
                    </div>
                </div>
                <div class="modal-action">
                    <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
                    <button class="btn btn-success" :disabled="payForm.processing" @click="submitPay">Simpan</button>
                </div>
            </div>
        </dialog>

        <!-- Modal: Expense -->
        <dialog id="modal-cash-out" class="modal">
            <div class="modal-box">
                <h3 class="font-bold text-lg">Input Expense Project</h3>
                <div class="space-y-3 mt-4">
                    <div>
                        <label class="label"><span class="label-text">Kategori</span></label>
                        <select v-model="cashOutForm.category" class="select select-bordered w-full">
                            <option v-for="category in cashOutCategories" :key="category.value" :value="category.value">{{ category.label }}</option>
                        </select>
                        <p v-if="cashOutForm.errors.category" class="text-error text-xs mt-1">{{ cashOutForm.errors.category }}</p>
                    </div>
                    <div>
                        <label class="label"><span class="label-text">Sumber Dana Kas / Bank</span></label>
                        <select v-model="cashOutForm.cash_account_id" class="select select-bordered w-full">
                            <option value="">Pilih akun kas/bank</option>
                            <option v-for="account in cash_accounts" :key="account.id" :value="account.id">{{ account.code }} - {{ account.name }}</option>
                        </select>
                        <p v-if="cashOutForm.errors.cash_account_id" class="text-error text-xs mt-1">{{ cashOutForm.errors.cash_account_id }}</p>
                    </div>
                    <CurrencyInput v-model="cashOutForm.amount" label="Jumlah" :required="true" :error="cashOutForm.errors.amount" />
                    <div>
                        <label class="label"><span class="label-text">Tanggal</span></label>
                        <input v-model="cashOutForm.date" type="date" class="input input-bordered w-full" />
                        <p v-if="cashOutForm.errors.date" class="text-error text-xs mt-1">{{ cashOutForm.errors.date }}</p>
                    </div>
                    <div>
                        <label class="label"><span class="label-text">Penerima</span></label>
                        <input v-model="cashOutForm.recipient_name" type="text" class="input input-bordered w-full" />
                    </div>
                    <div>
                        <label class="label"><span class="label-text">Keterangan</span></label>
                        <input v-model="cashOutForm.note" type="text" class="input input-bordered w-full" />
                    </div>
                </div>
                <div class="modal-action">
                    <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
                    <button class="btn btn-error" :disabled="cashOutForm.processing" @click="submitCashOut">Simpan Expense</button>
                </div>
            </div>
        </dialog>

        <dialog id="modal-project-start" class="modal">
            <div class="modal-box max-w-lg">
                <h3 class="font-bold text-lg">Ubah Status ke Berjalan</h3>
                <div class="mt-4">
                    <label class="label"><span class="label-text">Tanggal Mulai</span></label>
                    <input v-model="statusStartForm.started_at" type="date" class="input input-bordered w-full" />
                    <p v-if="statusStartForm.errors.started_at" class="text-error text-xs mt-1">{{ statusStartForm.errors.started_at }}</p>
                    <p v-if="statusStartForm.errors.target_status" class="text-error text-xs mt-1">{{ statusStartForm.errors.target_status }}</p>
                </div>
                <div class="modal-action">
                    <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
                    <button class="btn btn-success" :disabled="statusStartForm.processing" @click="submitMoveToBerjalan">Simpan</button>
                </div>
            </div>
        </dialog>

        <dialog id="modal-project-finish" class="modal">
            <div class="modal-box max-w-lg">
                <h3 class="font-bold text-lg">Ubah Status ke Selesai</h3>
                <div class="mt-4">
                    <label class="label"><span class="label-text">Tanggal Selesai</span></label>
                    <input v-model="statusFinishForm.finished_at" type="date" class="input input-bordered w-full" />
                    <p v-if="statusFinishForm.errors.finished_at" class="text-error text-xs mt-1">{{ statusFinishForm.errors.finished_at }}</p>
                    <p v-if="statusFinishForm.errors.target_status" class="text-error text-xs mt-1">{{ statusFinishForm.errors.target_status }}</p>
                </div>
                <div class="modal-action">
                    <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
                    <button class="btn btn-primary" :disabled="statusFinishForm.processing" @click="submitMoveToSelesai">Simpan</button>
                </div>
            </div>
        </dialog>

        <!-- Modal: Delete Project -->
        <ConfirmModal
            id="modal-delete-project"
            title="Hapus Project"
            :message="`Apakah Anda yakin ingin menghapus project '${project.name}'? Data akan dihapus sementara (soft delete).`"
            @confirm="deleteProject"
        />
        <!-- Modal: Add Material -->
        <dialog id="modal-add-material" class="modal">
            <div class="modal-box max-w-4xl">
                <h3 class="font-bold text-lg">Tambah Material Project</h3>

                <div class="mt-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-base-content/60">Gudang Material</p>
                    <div class="mt-2 rounded-lg border border-base-300 bg-base-200/70 px-4 py-3 text-sm font-medium">
                        {{ activeMaterialWarehouseLabel }}
                    </div>
                </div>

                <div class="mt-4">
                    <div class="mb-3">
                        <label class="label"><span class="label-text font-semibold">Cari Material</span></label>
                        <input
                            v-model="materialSearch"
                            type="search"
                            class="input input-bordered w-full"
                            placeholder="Cari SKU, nama material, kategori, atau UoM..."
                            @input="materialForm.master_product_id = ''"
                        />
                    </div>
                    <div class="mb-2 flex items-center justify-between">
                        <p class="text-sm text-base-content/60">Klik produk untuk memilih:</p>
                        <span class="text-xs text-base-content/50">
                            {{ materialProductsLoading ? 'Memuat...' : `${filteredMaterialProducts.length} item` }}
                        </span>
                    </div>
                    <div class="overflow-x-auto max-h-[40vh] overflow-y-auto border rounded-lg">
                        <table class="table table-sm table-zebra">
                            <thead class="sticky top-0 bg-base-200 z-10">
                                <tr><th>SKU</th><th>Produk</th><th>UoM</th><th class="text-right">Harga Jual</th><th class="text-right">Tersedia</th></tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="p in filteredMaterialProducts"
                                    :key="p.id"
                                    class="cursor-pointer hover"
                                    :class="materialForm.master_product_id === p.id ? 'bg-primary/10 border-l-4 border-l-primary' : ''"
                                    @click="selectMaterialProduct(p)"
                                >
                                    <td class="font-mono text-xs">{{ p.sku }}</td>
                                    <td class="font-semibold">{{ p.name }}</td>
                                    <td class="uppercase">{{ p.uom }}</td>
                                    <td class="text-right tabular-nums">{{ format(p.selling_price) }}</td>
                                    <td class="text-right tabular-nums font-medium text-success">{{ p.product_type === 'service' ? '-' : p.available }}</td>
                                </tr>
                                <tr v-if="filteredMaterialProducts.length === 0">
                                    <td colspan="5" class="text-center py-6 text-base-content/50">
                                        Tidak ada material project aktif yang cocok dengan pencarian.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-if="materialForm.master_product_id" class="mt-4 rounded-lg border border-primary/20 bg-primary/5 p-4">
                    <p class="text-sm font-semibold mb-3">Produk dipilih: <span class="text-primary">{{ selectedMaterialProduct?.sku }} — {{ selectedMaterialProduct?.name }}</span></p>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label class="label"><span class="label-text">Qty Kebutuhan <span class="text-error">*</span></span></label>
                            <input v-model.number="materialForm.planned_qty" type="number" min="1" step="1" class="input input-bordered w-full" />
                            <p class="mt-1 text-xs text-base-content/60">
                                {{ selectedProductIsService ? 'Jasa / non-stok tidak masuk perencanaan PO.' : `Tersedia: ${selectedProductAvailable}. Kekurangan otomatis masuk perencanaan PO.` }}
                            </p>
                        </div>
                        <div>
                            <label class="label"><span class="label-text">HPP / Unit</span></label>
                            <input v-model.number="materialForm.unit_cost" type="number" min="0" step="1000" class="input input-bordered w-full" />
                            <p v-if="materialForm.errors.unit_cost" class="text-error text-xs mt-1">{{ materialForm.errors.unit_cost }}</p>
                        </div>
                        <div>
                            <label class="label"><span class="label-text">Harga Jual / Unit</span></label>
                            <input v-model.number="materialForm.unit_price" type="number" min="0" step="1000" class="input input-bordered w-full" />
                            <p class="mt-1 text-xs text-base-content/60">Default mengikuti harga jual master product.</p>
                            <p v-if="materialForm.errors.unit_price" class="text-error text-xs mt-1">{{ materialForm.errors.unit_price }}</p>
                        </div>
                        <div>
                            <label class="label"><span class="label-text">Catatan</span></label>
                            <input v-model="materialForm.notes" type="text" class="input input-bordered w-full" placeholder="Opsional" />
                        </div>
                        <div class="sm:col-span-2 rounded-lg bg-base-100 p-3 text-sm">
                            <div class="flex flex-wrap justify-between gap-2">
                                <span>Total HPP: <strong>{{ format((Number(materialForm.planned_qty) || 0) * (Number(materialForm.unit_cost) || 0)) }}</strong></span>
                                <span>Total jual: <strong>{{ format((Number(materialForm.planned_qty) || 0) * (Number(materialForm.unit_price) || 0)) }}</strong></span>
                                <span>Margin: <strong>{{ format((Number(materialForm.planned_qty) || 0) * ((Number(materialForm.unit_price) || 0) - (Number(materialForm.unit_cost) || 0))) }}</strong></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-action">
                    <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
                    <button
                        class="btn"
                        :class="materialForm.master_product_id && materialForm.planned_qty > 0 ? 'btn-primary' : 'btn-secondary btn-disabled'"
                        :disabled="!materialForm.master_product_id || materialForm.planned_qty <= 0 || materialForm.processing"
                        @click="submitMaterial"
                    >
                        Tambah Kebutuhan
                    </button>
                </div>
            </div>
        </dialog>

        <ConfirmModal
            id="modal-delete-material"
            title="Hapus Material Project"
            message="Hapus material ini dan kembalikan reserve stok ke warehouse?"
            @confirm="deleteMaterial"
        />
    </AppLayout>
</template>
