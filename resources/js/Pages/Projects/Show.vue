<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import CurrencyInput from '@/Components/CurrencyInput.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useCurrency } from '@/composables/useCurrency';

const props = defineProps({
    project: Object,
    material_products: Array,
    warehouses: Array,
    team_members: Array,
    team_roles: Array,
});
const { format } = useCurrency();
const activeTab = ref('info');
const deletingMaterialId = ref(null);

// Mark term paid
const payForm = useForm({ paid_at: new Date().toISOString().slice(0, 10), note: '' });
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
    notes: '',
});

const submitMaterial = () => {
    materialForm.post(route('projects.materials.store', props.project.id), {
        preserveScroll: true,
        onSuccess: () => materialForm.reset('master_product_id', 'warehouse_id', 'planned_qty', 'notes'),
    });
};

const confirmDeleteMaterial = (id) => {
    deletingMaterialId.value = id;
    document.getElementById('modal-delete-material')?.showModal();
};

const deleteMaterial = () => {
    if (!deletingMaterialId.value) return;
    router.delete(route('projects.materials.destroy', { project: props.project.id, material: deletingMaterialId.value }));
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

const projectTypeLabel = (value) => {
    if (value === 'cctv_installation') return 'CCTV Installation';
    if (value === 'system_website_development') return 'System/Website Development';
    return value;
};

const roleLabel = (role) => role;

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

const canShowKanban = computed(() => props.project?.project_type === 'system_website_development');
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
    const templates = props.project.project_type === 'cctv_installation'
        ? [
            { name: 'Survey & Design', from: 0, to: 20 },
            { name: 'Procurement', from: 20, to: 45 },
            { name: 'Installation', from: 45, to: 85 },
            { name: 'Testing & Handover', from: 85, to: 100 },
        ]
        : [
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

// Cash forms
const cashInForm = useForm({ project_id: props.project.id, category: 'pendapatan_jasa', amount: 0, date: new Date().toISOString().slice(0, 10), note: '' });
const cashOutForm = useForm({ project_id: props.project.id, category: 'biaya_tim', amount: 0, date: new Date().toISOString().slice(0, 10), note: '', recipient_name: '' });

const submitCashIn = () => cashInForm.post(route('cash-in.store'), { onSuccess: () => { cashInForm.reset('amount', 'note'); document.getElementById('modal-cash-in').close(); } });
const submitCashOut = () => cashOutForm.post(route('cash-out.store'), { onSuccess: () => { cashOutForm.reset('amount', 'note', 'recipient_name'); document.getElementById('modal-cash-out').close(); } });

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
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <Link :href="route('projects.index')" class="btn btn-ghost btn-xs">← Projects</Link>
                        </div>
                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Projects Workspace</p>
                        <h1 class="mt-2 text-3xl font-bold tracking-tight">{{ project.name }}</h1>
                        <p class="text-base-content/60">{{ project.client_name }}</p>
                        <p class="mt-1 text-sm text-base-content/70">Pantau progres project, keuangan, material, tim, dan task dalam satu halaman.</p>
                        <span class="badge badge-ghost badge-sm mt-1">{{ projectTypeLabel(project.project_type) }}</span>
                    </div>
                    <div class="flex gap-2">
                        <StatusBadge :status="project.status" />
                        <button v-if="canMoveToBerjalan" class="btn btn-success btn-sm" @click="openStartStatusModal">Mulai Project</button>
                        <button v-if="canMoveToSelesai" class="btn btn-primary btn-sm" @click="openFinishStatusModal">Selesaikan Project</button>
                        <Link :href="route('projects.edit', project.id)" class="btn btn-outline btn-sm">Edit</Link>
                        <button class="btn btn-error btn-outline btn-sm" onclick="document.getElementById('modal-delete-project').showModal()">Hapus</button>
                    </div>
                </div>
            </div>

            <!-- Ringkasan keuangan (panel kontras, bukan stats/base-100 halaman) -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article
                    class="rounded-2xl border border-slate-700/40 bg-gradient-to-br from-slate-800 to-slate-950 p-5 text-white shadow-xl ring-1 ring-black/20"
                >
                    <p class="text-xs font-semibold uppercase tracking-wide text-white/55">Nilai kontrak</p>
                    <p class="mt-3 text-xl font-bold tabular-nums tracking-tight sm:text-2xl">{{ format(project.total_value) }}</p>
                </article>
                <article
                    class="rounded-2xl border border-emerald-900/50 bg-gradient-to-br from-emerald-900 to-emerald-950 p-5 text-white shadow-xl ring-1 ring-emerald-950/60"
                >
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-100/70">Kas masuk</p>
                    <p class="mt-3 text-xl font-bold tabular-nums tracking-tight text-emerald-50 sm:text-2xl">
                        {{ format(project.summary.total_cash_in) }}
                    </p>
                </article>
                <article
                    class="rounded-2xl border border-rose-900/50 bg-gradient-to-br from-rose-900 to-rose-950 p-5 text-white shadow-xl ring-1 ring-rose-950/60"
                >
                    <p class="text-xs font-semibold uppercase tracking-wide text-rose-100/70">Kas keluar</p>
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
                <button :class="['tab', activeTab === 'materials' ? 'tab-active' : '']" @click="activeTab = 'materials'">Material / BOM</button>
                <button :class="['tab', activeTab === 'kas' ? 'tab-active' : '']" @click="activeTab = 'kas'">Kas Masuk / Keluar</button>
                <button :class="['tab', activeTab === 'tim' ? 'tab-active' : '']" @click="activeTab = 'tim'">Tim & Referral</button>
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
                            <div class="text-base-content/60">Tipe Project</div><div>{{ projectTypeLabel(project.project_type) }}</div>
                            <div class="text-base-content/60">Tanggal Mulai</div><div>{{ project.started_at ?? '-' }}</div>
                            <div class="text-base-content/60">Tanggal Selesai</div><div>{{ project.finished_at ?? '-' }}</div>
                            <div class="text-base-content/60">Deskripsi</div><div>{{ project.description ?? '-' }}</div>
                        </div>
                    </div>
                </div>

                <div class="ocn-panel">
                    <div class="ocn-panel__head">
                        <h2 class="ocn-panel__title">Termin pembayaran</h2>
                    </div>
                    <div class="card-body">
                        <div class="space-y-3">
                            <div v-for="term in project.payments" :key="term.id"
                                :class="['flex items-center justify-between p-3 rounded-lg border', term.paid_at ? 'border-success/30 bg-success/5' : 'border-base-300']"
                            >
                                <div>
                                    <p class="font-medium">Termin {{ term.term_number }} — {{ term.percentage }}%</p>
                                    <p class="text-sm text-base-content/60">{{ format(term.amount) }}</p>
                                    <p v-if="term.paid_at" class="text-xs text-success">Lunas: {{ term.paid_at }}</p>
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

                <div class="ocn-panel">
                    <div class="ocn-panel__head">
                        <h2 class="ocn-panel__title">Gantt timeline</h2>
                        <p class="ocn-panel__desc">Otomatis dari tanggal mulai–selesai dan tipe project.</p>
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

            <div v-if="activeTab === 'materials'" class="space-y-4">
                <div class="ocn-panel">
                    <div class="ocn-panel__head">
                        <h2 class="ocn-panel__title">Tambah material project</h2>
                    </div>
                    <div class="card-body">
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-5">
                            <div class="md:col-span-2">
                                <label class="label"><span class="label-text">Produk</span></label>
                                <select v-model="materialForm.master_product_id" class="select select-bordered w-full">
                                    <option value="">Pilih produk</option>
                                    <option v-for="p in material_products" :key="p.id" :value="p.id">{{ p.sku }} - {{ p.name }} ({{ p.uom }})</option>
                                </select>
                            </div>
                            <div>
                                <label class="label"><span class="label-text">Warehouse</span></label>
                                <select v-model="materialForm.warehouse_id" class="select select-bordered w-full">
                                    <option value="">Pilih warehouse</option>
                                    <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.code }} - {{ w.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="label"><span class="label-text">Qty Reserve</span></label>
                                <input v-model.number="materialForm.planned_qty" type="number" min="1" step="1" class="input input-bordered w-full" />
                            </div>
                            <div>
                                <label class="label"><span class="label-text">Catatan</span></label>
                                <input v-model="materialForm.notes" type="text" class="input input-bordered w-full" />
                            </div>
                        </div>
                        <div class="mt-3">
                            <button class="btn btn-primary btn-sm" :disabled="materialForm.processing" @click="submitMaterial">Tambah & Reserve</button>
                        </div>
                    </div>
                </div>

                <div class="ocn-panel">
                    <div class="ocn-panel__head">
                        <h2 class="ocn-panel__title">Daftar material (BOM reserved)</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead><tr><th>SKU</th><th>Produk</th><th>Warehouse</th><th>Planned</th><th>Reserved</th><th>Issued</th><th>Status</th><th></th></tr></thead>
                            <tbody>
                                <tr v-for="m in project.materials" :key="m.id">
                                    <td class="font-mono text-xs">{{ m.sku }}</td>
                                    <td>{{ m.product }}</td>
                                    <td>{{ m.warehouse }}</td>
                                    <td>{{ m.planned_qty }} {{ m.uom }}</td>
                                    <td>{{ m.reserved_qty }} {{ m.uom }}</td>
                                    <td>{{ m.issued_qty }} {{ m.uom }}</td>
                                    <td><span class="badge badge-ghost badge-sm">{{ m.status }}</span></td>
                                    <td class="text-right"><button class="btn btn-ghost btn-xs text-error" @click="confirmDeleteMaterial(m.id)">Hapus</button></td>
                                </tr>
                                <tr v-if="!project.materials.length"><td colspan="8" class="text-center py-6 text-base-content/50">Belum ada material project.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Tab: Kas -->
            <div v-if="activeTab === 'kas'" class="space-y-4">
                <div class="flex gap-2">
                    <button class="btn btn-success btn-sm" onclick="document.getElementById('modal-cash-in').showModal()">+ Kas Masuk</button>
                    <button class="btn btn-error btn-sm" onclick="document.getElementById('modal-cash-out').showModal()">+ Kas Keluar</button>
                </div>

                <div class="ocn-panel">
                    <div class="ocn-panel__head">
                        <h2 class="ocn-panel__title text-success">Kas masuk</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead><tr><th>Tanggal</th><th>Kategori</th><th>Jumlah</th><th>Keterangan</th><th>Oleh</th></tr></thead>
                            <tbody>
                                <tr v-for="c in project.cash_ins" :key="c.id">
                                    <td>{{ c.date }}</td>
                                    <td><span class="badge badge-sm badge-ghost">{{ c.category }}</span></td>
                                    <td class="font-medium text-success">{{ format(c.amount) }}</td>
                                    <td class="text-sm text-base-content/70">{{ c.note ?? '-' }}</td>
                                    <td class="text-sm text-base-content/60">{{ c.creator_name }}</td>
                                </tr>
                                <tr v-if="!project.cash_ins.length"><td colspan="5" class="text-center py-6 text-base-content/50">Belum ada kas masuk</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="ocn-panel">
                    <div class="ocn-panel__head">
                        <h2 class="ocn-panel__title text-error">Kas keluar</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead><tr><th>Tanggal</th><th>Kategori</th><th>Jumlah</th><th>Penerima</th><th>Keterangan</th></tr></thead>
                            <tbody>
                                <tr v-for="c in project.cash_outs" :key="c.id">
                                    <td>{{ c.date }}</td>
                                    <td><span class="badge badge-sm badge-ghost">{{ c.category }}</span></td>
                                    <td class="font-medium text-error">{{ format(c.amount) }}</td>
                                    <td>{{ c.recipient_name ?? '-' }}</td>
                                    <td class="text-sm text-base-content/70">{{ c.note ?? '-' }}</td>
                                </tr>
                                <tr v-if="!project.cash_outs.length"><td colspan="5" class="text-center py-6 text-base-content/50">Belum ada kas keluar</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Tab: Tim -->
            <div v-if="activeTab === 'tim'" class="space-y-4">
                <div class="ocn-panel">
                    <div class="ocn-panel__head flex flex-wrap items-center justify-between gap-2">
                        <h2 class="ocn-panel__title">Pembagian tim</h2>
                        <div class="flex gap-2 shrink-0">
                                <button class="btn btn-outline btn-sm" @click="openAssignTeamModal">Assign Tim</button>
                                <Link :href="route('team-distribution.calculator') + '?project_id=' + project.id" class="btn btn-primary btn-sm">
                                    Kalkulator
                                </Link>
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
                        <button class="btn btn-outline btn-sm shrink-0" @click="openAddReferralModal">Add referral</button>
                    </div>
                    <div class="card-body">
                        <div class="overflow-x-auto">
                        <table class="table table-sm">
                            <thead><tr><th>Nama Referrer</th><th>Komisi</th><th>Tgl Bayar</th><th>Catatan</th></tr></thead>
                            <tbody>
                                <tr v-for="r in project.referrals" :key="r.id">
                                    <td class="font-medium">{{ r.referrer_name }}</td>
                                    <td>{{ format(r.commission_amount) }}</td>
                                    <td>{{ r.paid_at ?? '-' }}</td>
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
                                    <div class="text-xs text-base-content/60">Due: {{ task.due_date ?? '-' }}</div>
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
                            <option v-for="user in team_members" :key="user.id" :value="user.id">{{ user.name }}</option>
                        </select>
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

        <!-- Modal: Cash In -->
        <dialog id="modal-cash-in" class="modal">
            <div class="modal-box">
                <h3 class="font-bold text-lg">Tambah Kas Masuk</h3>
                <div class="space-y-3 mt-4">
                    <div>
                        <label class="label"><span class="label-text">Kategori</span></label>
                        <select v-model="cashInForm.category" class="select select-bordered w-full">
                            <option value="pendapatan_jasa">Pendapatan Jasa</option>
                            <option value="lainnya">Lainnya</option>
                        </select>
                    </div>
                    <CurrencyInput v-model="cashInForm.amount" label="Jumlah" :required="true" :error="cashInForm.errors.amount" />
                    <div>
                        <label class="label"><span class="label-text">Tanggal</span></label>
                        <input v-model="cashInForm.date" type="date" class="input input-bordered w-full" />
                        <p v-if="cashInForm.errors.date" class="text-error text-xs mt-1">{{ cashInForm.errors.date }}</p>
                    </div>
                    <div>
                        <label class="label"><span class="label-text">Keterangan</span></label>
                        <input v-model="cashInForm.note" type="text" class="input input-bordered w-full" />
                    </div>
                </div>
                <div class="modal-action">
                    <form method="dialog"><button class="btn btn-ghost">Batal</button></form>
                    <button class="btn btn-success" :disabled="cashInForm.processing" @click="submitCashIn">Simpan</button>
                </div>
            </div>
        </dialog>

        <!-- Modal: Cash Out -->
        <dialog id="modal-cash-out" class="modal">
            <div class="modal-box">
                <h3 class="font-bold text-lg">Tambah Kas Keluar</h3>
                <div class="space-y-3 mt-4">
                    <div>
                        <label class="label"><span class="label-text">Kategori</span></label>
                        <select v-model="cashOutForm.category" class="select select-bordered w-full">
                            <option value="biaya_tim">Biaya Tim</option>
                            <option value="komisi_referral">Komisi Referral</option>
                            <option value="operasional">Operasional</option>
                            <option value="lainnya">Lainnya</option>
                        </select>
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
                    <button class="btn btn-error" :disabled="cashOutForm.processing" @click="submitCashOut">Simpan</button>
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
        <ConfirmModal
            id="modal-delete-material"
            title="Hapus Material Project"
            message="Hapus material ini dan kembalikan reserve stok ke warehouse?"
            @confirm="deleteMaterial"
        />
    </AppLayout>
</template>
