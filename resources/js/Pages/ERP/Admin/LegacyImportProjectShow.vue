<script setup>
import AppLayout from "@/Layouts/AppLayout.vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import { computed, reactive, ref, watch } from "vue";
import { ArrowLeftIcon } from "@heroicons/vue/24/outline";
import { showGlobalAlert } from "@/utils/globalAlert";

const props = defineProps({
    project: { type: Object, required: true },
    generatedAt: { type: String, default: null },
    source: { type: Object, default: null },
    procurementVendors: { type: Array, default: () => [] },
    relatedProcurementStagings: { type: Array, default: () => [] },
});

const importForm = useForm({
    import_keys: [props.project.import_key],
    legacy_project_id: props.project.legacy_id,
});
const prepareProcurementForm = useForm({
    import_keys: [props.project.import_key],
    legacy_project_id: props.project.legacy_id,
});
const procurementStagingSaveForm = useForm({
    procurement_date: "",
    notes: "",
    lines: [],
    legacy_project_id: props.project.legacy_id,
});
const procurementStagingConvertForm = useForm({
    legacy_project_id: props.project.legacy_id,
});
const reconcileStagingForm = useForm({
    legacy_project_id: props.project.legacy_id,
    source_import_key: props.project.import_key,
});
const procurementStagingDrafts = reactive({});
const procurementStagingSavingId = ref(null);
const procurementStagingConvertingId = ref(null);
const activeTab = ref("overview");
const requiresProcurementFirst = computed(
    () => props.project.requires_procurement_first === true,
);
const procurementReadyForImport = computed(
    () => props.project.procurement_ready_for_import === true,
);
const canImportProject = computed(
    () =>
        props.project.is_importable &&
        (!requiresProcurementFirst.value || procurementReadyForImport.value),
);

function setActiveTab(tab) {
    activeTab.value = tab;
}

function openProjectMetaModal() {
    document.getElementById("legacy-project-meta-modal")?.showModal();
}

function closeProjectMetaModal() {
    document.getElementById("legacy-project-meta-modal")?.close();
}

function compareStatusClass(status) {
    if (
        status === "already_in_erp_project" ||
        status === "already_paid_in_erp" ||
        status === "matched_existing_distribution"
    )
        return "badge-info";
    if (status === "matched_master_product" || status === "matched_erp_user")
        return "badge-success";
    return "badge-warning";
}

function importStatusClass(importStatus) {
    return importStatus?.badge || "badge-ghost";
}

function processImport() {
    importForm.import_keys = [props.project.import_key];
    importForm.legacy_project_id = props.project.legacy_id;
    importForm.post(
        route("erp.admin.data-import.legacy-sales.import-selected"),
        {
            preserveScroll: true,
        },
    );
}

function prepareProcurementStaging() {
    prepareProcurementForm.import_keys = [props.project.import_key];
    prepareProcurementForm.legacy_project_id = props.project.legacy_id;
    prepareProcurementForm.post(
        route("erp.admin.data-import.legacy-sales.prepare-procurement"),
        {
            preserveScroll: true,
        },
    );
}

function reconcileProjectProcurementStaging() {
    reconcileStagingForm.post(
        route("erp.admin.data-import.procurement-stagings.reconcile"),
        {
            preserveScroll: true,
        },
    );
}

function buildProcurementStagingDraft(staging) {
    return {
        procurement_date: staging.procurement_date || "",
        notes: staging.notes || "",
        bulk_vendor_id: "",
        lines: (staging.lines || []).map((line) => ({
            id: line.id,
            vendor_id: line.vendor_id ? String(line.vendor_id) : "",
            qty: Number(line.qty || 0).toFixed(2),
            unit_cost: Number(line.unit_cost || 0).toFixed(2),
        })),
    };
}

function syncProcurementStagingDrafts(stagings) {
    const nextKeys = new Set(stagings.map((staging) => staging.id));

    for (const key of Object.keys(procurementStagingDrafts)) {
        if (!nextKeys.has(key)) {
            delete procurementStagingDrafts[key];
        }
    }

    for (const staging of stagings) {
        procurementStagingDrafts[staging.id] =
            buildProcurementStagingDraft(staging);
    }
}

function procurementStagingDraft(staging) {
    if (!procurementStagingDrafts[staging.id]) {
        procurementStagingDrafts[staging.id] =
            buildProcurementStagingDraft(staging);
    }

    return procurementStagingDrafts[staging.id];
}

function applyVendorToAllProcurementLines(staging) {
    const draft = procurementStagingDraft(staging);
    if (!draft.bulk_vendor_id) return;

    draft.lines = draft.lines.map((line) => ({
        ...line,
        vendor_id: String(draft.bulk_vendor_id),
    }));
}

function procurementLineTotal(line) {
    return Number(line.qty || 0) * Number(line.unit_cost || 0);
}

function procurementStagingReadyToConvert(staging) {
    if (staging.status === "converted") return false;

    const draft = procurementStagingDraft(staging);
    return (
        draft.lines.length > 0 &&
        draft.lines.every(
            (line) =>
                Number(line.qty || 0) > 0 &&
                !!String(line.vendor_id || "").trim(),
        )
    );
}

function saveProcurementStaging(staging) {
    const draft = procurementStagingDraft(staging);

    procurementStagingSaveForm.procurement_date = draft.procurement_date;
    procurementStagingSaveForm.notes = draft.notes;
    procurementStagingSaveForm.legacy_project_id = props.project.legacy_id;
    procurementStagingSaveForm.lines = draft.lines.map((line) => ({
        id: line.id,
        vendor_id: line.vendor_id ? Number(line.vendor_id) : null,
        qty: Number(line.qty || 0),
        unit_cost: Number(line.unit_cost || 0),
    }));

    procurementStagingSavingId.value = staging.id;
    procurementStagingSaveForm.post(
        route("erp.admin.data-import.procurement-stagings.update", staging.id),
        {
            preserveScroll: true,
            onFinish: () => {
                procurementStagingSavingId.value = null;
            },
        },
    );
}

function convertProcurementStaging(staging) {
    if (!procurementStagingReadyToConvert(staging)) {
        showGlobalAlert(
            "Pilih supplier untuk semua line dan pastikan qty > 0 sebelum convert.",
            "warning",
        );
        return;
    }

    procurementStagingConvertingId.value = staging.id;
    procurementStagingConvertForm.legacy_project_id = props.project.legacy_id;
    procurementStagingConvertForm.post(
        route("erp.admin.data-import.procurement-stagings.convert", staging.id),
        {
            preserveScroll: true,
            onFinish: () => {
                procurementStagingConvertingId.value = null;
            },
        },
    );
}

watch(
    () => props.relatedProcurementStagings,
    (stagings) => {
        syncProcurementStagingDrafts(stagings || []);
    },
    { deep: true, immediate: true },
);
</script>

<template>
    <Head :title="`Legacy Import - ${project.project_number}`" />
    <AppLayout>
        <div class="space-y-5">
            <div class="ocn-panel">
                <div class="ocn-panel__head">
                    <div
                        class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                    >
                        <div>
                            <p
                                class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70"
                            >
                                Legacy Import OCN
                            </p>
                            <h1 class="ocn-panel__title mt-1">
                                {{ project.project_number }} &middot;
                                {{ project.title }}
                            </h1>
                            <p class="ocn-panel__desc mt-1">
                                Detail compare dan kesiapan import untuk satu
                                project legacy.
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 shrink-0">
                            <Link
                                class="btn btn-ghost btn-sm gap-1.5"
                                :href="route('erp.admin.legacy-import')"
                            >
                                <ArrowLeftIcon class="h-4 w-4" />
                                Kembali ke daftar
                            </Link>
                            <button
                                type="button"
                                class="btn btn-primary btn-sm"
                                :disabled="
                                    !canImportProject || importForm.processing
                                "
                                @click="processImport"
                            >
                                {{
                                    importForm.processing
                                        ? "Memproses import..."
                                        : "Proses import project ini"
                                }}
                            </button>
                            <button
                                v-if="
                                    requiresProcurementFirst &&
                                    !relatedProcurementStagings.length
                                "
                                type="button"
                                class="btn btn-outline btn-sm"
                                :disabled="prepareProcurementForm.processing"
                                @click="prepareProcurementStaging"
                            >
                                {{
                                    prepareProcurementForm.processing
                                        ? "Menyiapkan staging..."
                                        : "Siapkan procurement staging dulu"
                                }}
                            </button>
                            <button
                                v-if="relatedProcurementStagings.length"
                                type="button"
                                class="btn btn-outline btn-sm"
                                :disabled="reconcileStagingForm.processing"
                                @click="reconcileProjectProcurementStaging"
                            >
                                {{
                                    reconcileStagingForm.processing
                                        ? "Mengecek staging..."
                                        : "Cek staging project ini"
                                }}
                            </button>
                            <button
                                v-if="relatedProcurementStagings.length"
                                type="button"
                                class="btn btn-outline btn-sm"
                                @click="setActiveTab('procurement')"
                            >
                                Proses procurement staging
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div
                class="grid gap-4 xl:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)]"
            >
                <div class="min-w-0 space-y-4">
                    <div
                        class="rounded-xl border border-base-200 bg-base-100 p-4"
                    >
                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                class="badge badge-sm"
                                :class="
                                    importStatusClass(project.import_status)
                                "
                            >
                                {{
                                    project.import_status?.label ||
                                    "Belum diimport"
                                }}
                            </span>
                            <span
                                class="badge badge-sm"
                                :class="
                                    project.readiness === 'ready'
                                        ? 'badge-success'
                                        : project.readiness === 'warning'
                                          ? 'badge-warning'
                                          : 'badge-error'
                                "
                            >
                                {{ project.readiness }}
                            </span>
                            <span class="badge badge-outline badge-sm"
                                >{{ project.payment_count }} payment</span
                            >
                        </div>
                        <div
                            class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4 text-sm"
                        >
                            <div
                                class="rounded-lg border border-base-200 bg-base-200/30 p-3"
                            >
                                <div
                                    class="text-xs uppercase tracking-[0.12em] text-base-content/60"
                                >
                                    Customer
                                </div>
                                <div class="mt-1 font-medium">
                                    {{ project.customer_name || "-" }}
                                </div>
                                <div
                                    v-if="project.crm_customer_match"
                                    class="mt-1 text-xs text-success"
                                >
                                    Match CRM:
                                    {{ project.crm_customer_match.name }}
                                </div>
                            </div>
                            <div
                                class="rounded-lg border border-base-200 bg-base-200/30 p-3"
                            >
                                <div
                                    class="text-xs uppercase tracking-[0.12em] text-base-content/60"
                                >
                                    Tanggal jual
                                </div>
                                <div class="mt-1 font-medium">
                                    {{ project.sale_date || "-" }}
                                </div>
                                <div class="mt-1 text-xs text-base-content/60">
                                    {{ project.sale_date_source || "-" }}
                                </div>
                            </div>
                            <div
                                class="rounded-lg border border-base-200 bg-base-200/30 p-3"
                            >
                                <div
                                    class="text-xs uppercase tracking-[0.12em] text-base-content/60"
                                >
                                    Nilai project
                                </div>
                                <div class="mt-1 font-medium">
                                    {{
                                        Number(
                                            project.expected_value || 0,
                                        ).toLocaleString("id-ID")
                                    }}
                                </div>
                                <div class="mt-1 text-xs text-base-content/60">
                                    sumber
                                    {{ project.expected_value_source || "-" }}
                                </div>
                            </div>
                            <div
                                class="rounded-lg border border-base-200 bg-base-200/30 p-3"
                            >
                                <div
                                    class="text-xs uppercase tracking-[0.12em] text-base-content/60"
                                >
                                    Total payment
                                </div>
                                <div class="mt-1 font-medium">
                                    {{
                                        Number(
                                            project.paid_total || 0,
                                        ).toLocaleString("id-ID")
                                    }}
                                </div>
                                <div class="mt-1 text-xs text-base-content/60">
                                    {{ project.last_payment_date || "-" }}
                                </div>
                            </div>
                        </div>
                        <div
                            class="mt-4 flex flex-wrap items-center gap-2 text-xs text-base-content/60"
                        >
                            <code class="rounded bg-base-200 px-1">{{
                                project.import_key
                            }}</code>
                            <button
                                type="button"
                                class="btn btn-ghost btn-xs"
                                @click="openProjectMetaModal"
                            >
                                Info source
                            </button>
                        </div>
                    </div>

                    <div
                        class="rounded-xl border border-base-200 bg-base-100 p-3"
                    >
                        <div class="flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="btn btn-sm"
                                :class="
                                    activeTab === 'overview'
                                        ? 'btn-primary'
                                        : 'btn-ghost'
                                "
                                @click="setActiveTab('overview')"
                            >
                                Overview
                            </button>
                            <button
                                type="button"
                                class="btn btn-sm"
                                :class="
                                    activeTab === 'items'
                                        ? 'btn-primary'
                                        : 'btn-ghost'
                                "
                                @click="setActiveTab('items')"
                            >
                                Item
                                <span class="badge badge-xs ml-1">{{
                                    project.compare_summary?.items_total ?? 0
                                }}</span>
                            </button>
                            <button
                                type="button"
                                class="btn btn-sm"
                                :class="
                                    activeTab === 'technicians'
                                        ? 'btn-primary'
                                        : 'btn-ghost'
                                "
                                @click="setActiveTab('technicians')"
                            >
                                Teknisi
                                <span class="badge badge-xs ml-1">{{
                                    project.compare_summary
                                        ?.technicians_total ?? 0
                                }}</span>
                            </button>
                            <button
                                type="button"
                                class="btn btn-sm"
                                :class="
                                    activeTab === 'payments'
                                        ? 'btn-primary'
                                        : 'btn-ghost'
                                "
                                @click="setActiveTab('payments')"
                            >
                                Payment
                                <span class="badge badge-xs ml-1">{{
                                    project.compare_summary
                                        ?.technician_payments_total ?? 0
                                }}</span>
                            </button>
                            <button
                                type="button"
                                class="btn btn-sm"
                                :class="
                                    activeTab === 'procurement'
                                        ? 'btn-primary'
                                        : 'btn-ghost'
                                "
                                @click="setActiveTab('procurement')"
                            >
                                Procurement
                                <span class="badge badge-xs ml-1">{{
                                    relatedProcurementStagings.length
                                }}</span>
                            </button>
                        </div>
                    </div>

                    <div
                        v-if="
                            activeTab === 'overview' && requiresProcurementFirst
                        "
                        class="rounded-xl border p-4"
                        :class="
                            procurementReadyForImport
                                ? 'border-success/40 bg-success/10'
                                : 'border-warning/40 bg-warning/10'
                        "
                    >
                        <div class="font-medium text-base-content">
                            Urutan import wajib
                        </div>
                        <div class="mt-2 text-sm text-base-content/85">
                            Procurement harus selesai lebih dulu untuk project
                            ini. Buat staging, pilih supplier, convert ke PO +
                            GR, lalu baru import project agar material project
                            tidak masuk dengan reservasi 0.
                        </div>
                        <div
                            v-if="project.procurement_gate_message"
                            class="mt-2 text-xs text-base-content/70"
                        >
                            Status: {{ project.procurement_gate_message }}
                        </div>
                        <div
                            v-if="procurementReadyForImport && canImportProject"
                            class="mt-3 flex flex-wrap items-center gap-2"
                        >
                            <span class="text-xs font-medium text-success"
                                >PO dan GR sudah selesai. Project siap
                                dilanjutkan ke import.</span
                            >
                            <button
                                type="button"
                                class="btn btn-success btn-sm"
                                :disabled="importForm.processing"
                                @click="processImport"
                            >
                                {{
                                    importForm.processing
                                        ? "Memproses import..."
                                        : "Proses import project ini"
                                }}
                            </button>
                        </div>
                    </div>

                    <div
                        v-if="
                            activeTab === 'overview' && project.issues?.length
                        "
                        class="rounded-xl border border-warning/40 bg-warning/10 p-4"
                    >
                        <div class="font-medium text-base-content">
                            Temuan QC
                        </div>
                        <div class="mt-3 space-y-2 text-sm">
                            <div
                                v-for="issue in project.issues"
                                :key="`${project.import_key}-${issue.code}`"
                                class="rounded-lg border border-base-200 bg-base-100/80 px-3 py-2"
                            >
                                <div class="flex flex-wrap items-center gap-2">
                                    <span
                                        class="badge badge-sm"
                                        :class="
                                            issue.severity === 'error'
                                                ? 'badge-error'
                                                : 'badge-warning'
                                        "
                                        >{{ issue.severity }}</span
                                    >
                                    <span class="font-medium">{{
                                        issue.label
                                    }}</span>
                                </div>
                                <div class="mt-1 text-base-content/80">
                                    {{ issue.message }}
                                </div>
                                <div class="mt-1 text-xs text-base-content/65">
                                    Perbaikan: {{ issue.fix_hint }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="activeTab === 'overview'"
                        class="grid gap-3 md:grid-cols-3 text-sm"
                    >
                        <div
                            class="rounded-xl border border-base-200 bg-base-100 p-4"
                        >
                            <div
                                class="text-xs uppercase tracking-[0.12em] text-base-content/60"
                            >
                                Item
                            </div>
                            <div class="mt-2 text-2xl font-semibold">
                                {{ project.compare_summary?.items_total ?? 0 }}
                            </div>
                            <div class="mt-1 text-xs text-base-content/60">
                                Unresolved:
                                {{
                                    project.compare_summary?.items_unresolved ??
                                    0
                                }}
                            </div>
                        </div>
                        <div
                            class="rounded-xl border border-base-200 bg-base-100 p-4"
                        >
                            <div
                                class="text-xs uppercase tracking-[0.12em] text-base-content/60"
                            >
                                Teknisi
                            </div>
                            <div class="mt-2 text-2xl font-semibold">
                                {{
                                    project.compare_summary
                                        ?.technicians_total ?? 0
                                }}
                            </div>
                            <div class="mt-1 text-xs text-base-content/60">
                                Unresolved:
                                {{
                                    project.compare_summary
                                        ?.technicians_unresolved ?? 0
                                }}
                            </div>
                        </div>
                        <div
                            class="rounded-xl border border-base-200 bg-base-100 p-4"
                        >
                            <div
                                class="text-xs uppercase tracking-[0.12em] text-base-content/60"
                            >
                                Payment teknisi
                            </div>
                            <div class="mt-2 text-2xl font-semibold">
                                {{
                                    project.compare_summary
                                        ?.technician_payments_total ?? 0
                                }}
                            </div>
                            <div class="mt-1 text-xs text-base-content/60">
                                Unresolved:
                                {{
                                    project.compare_summary
                                        ?.technician_payments_unresolved ?? 0
                                }}
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="
                            activeTab === 'items' &&
                            project.details?.items?.length
                        "
                        class="rounded-xl border border-base-200 bg-base-100 p-4"
                    >
                        <div class="font-medium text-base-content">
                            Item project
                        </div>
                        <div v-if="false" class="mt-3 overflow-x-auto">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Legacy item</th>
                                        <th>Qty</th>
                                        <th>Harga</th>
                                        <th>Cost</th>
                                        <th>Total</th>
                                        <th>Total cost</th>
                                        <th>Match ERP</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="item in project.details.items"
                                        :key="item.legacy_item_id"
                                    >
                                        <td>
                                            <div>{{ item.name }}</div>
                                            <div
                                                class="text-[11px] text-base-content/60"
                                            >
                                                {{ item.sku || "-" }} &middot;
                                                {{ item.unit || "-" }}
                                            </div>
                                        </td>
                                        <td>
                                            {{
                                                Number(
                                                    item.quantity || 0,
                                                ).toLocaleString("id-ID")
                                            }}
                                        </td>
                                        <td>
                                            {{
                                                Number(
                                                    item.price || 0,
                                                ).toLocaleString("id-ID")
                                            }}
                                        </td>
                                        <td>
                                            {{
                                                Number(
                                                    item.cost || 0,
                                                ).toLocaleString("id-ID")
                                            }}
                                        </td>
                                        <td>
                                            {{
                                                Number(
                                                    item.total_price || 0,
                                                ).toLocaleString("id-ID")
                                            }}
                                        </td>
                                        <td>
                                            {{
                                                Number(
                                                    item.total_cost || 0,
                                                ).toLocaleString("id-ID")
                                            }}
                                        </td>
                                        <td>
                                            <div v-if="item.matched_product">
                                                {{ item.matched_product.name }}
                                            </div>
                                            <div
                                                v-if="item.matched_product"
                                                class="text-[11px] text-base-content/60"
                                            >
                                                {{ item.matched_product.sku }}
                                            </div>
                                            <div
                                                v-if="item.existing_material"
                                                class="text-[11px] text-base-content/60"
                                            >
                                                ERP cost
                                                {{
                                                    Number(
                                                        item.existing_material
                                                            .unit_cost || 0,
                                                    ).toLocaleString("id-ID")
                                                }}
                                                &middot; ERP price
                                                {{
                                                    Number(
                                                        item.existing_material
                                                            .unit_price || 0,
                                                    ).toLocaleString("id-ID")
                                                }}
                                            </div>
                                            <div
                                                v-else
                                                class="text-base-content/50"
                                            >
                                                Belum ada
                                            </div>
                                        </td>
                                        <td>
                                            <span
                                                class="badge badge-xs"
                                                :class="
                                                    compareStatusClass(
                                                        item.status,
                                                    )
                                                "
                                                >{{ item.status }}</span
                                            >
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-3 space-y-2">
                            <div
                                v-for="item in project.details.items"
                                :key="`compact-${item.legacy_item_id}`"
                                class="rounded-lg border border-base-200 bg-base-200/20 px-3 py-2.5"
                            >
                                <div
                                    class="flex flex-col gap-1 md:flex-row md:items-start md:justify-between"
                                >
                                    <div class="min-w-0">
                                        <div
                                            class="truncate text-sm font-medium text-base-content"
                                        >
                                            {{ item.name }}
                                        </div>
                                        <div
                                            class="text-[11px] text-base-content/60"
                                        >
                                            {{ item.sku || "-" }} &middot;
                                            {{ item.unit || "-" }}
                                        </div>
                                    </div>
                                    <span
                                        class="badge badge-xs shrink-0"
                                        :class="compareStatusClass(item.status)"
                                        >{{ item.status }}</span
                                    >
                                </div>

                                <div class="mt-2 grid gap-2 md:grid-cols-5">
                                    <div
                                        class="rounded-md bg-base-100 px-2.5 py-2"
                                    >
                                        <div
                                            class="text-[10px] font-semibold uppercase tracking-[0.12em] text-base-content/50"
                                        >
                                            Qty
                                        </div>
                                        <div
                                            class="mt-1 text-sm font-semibold text-base-content"
                                        >
                                            {{
                                                Number(
                                                    item.quantity || 0,
                                                ).toLocaleString("id-ID")
                                            }}
                                        </div>
                                    </div>
                                    <div
                                        class="rounded-md bg-base-100 px-2.5 py-2"
                                    >
                                        <div
                                            class="text-[10px] font-semibold uppercase tracking-[0.12em] text-base-content/50"
                                        >
                                            Harga
                                        </div>
                                        <div
                                            class="mt-1 text-sm font-semibold text-base-content"
                                        >
                                            {{
                                                Number(
                                                    item.price || 0,
                                                ).toLocaleString("id-ID")
                                            }}
                                        </div>
                                    </div>
                                    <div
                                        class="rounded-md bg-base-100 px-2.5 py-2"
                                    >
                                        <div
                                            class="text-[10px] font-semibold uppercase tracking-[0.12em] text-base-content/50"
                                        >
                                            Cost
                                        </div>
                                        <div
                                            class="mt-1 text-sm font-semibold text-base-content"
                                        >
                                            {{
                                                Number(
                                                    item.cost || 0,
                                                ).toLocaleString("id-ID")
                                            }}
                                        </div>
                                    </div>
                                    <div
                                        class="rounded-md bg-base-100 px-2.5 py-2"
                                    >
                                        <div
                                            class="text-[10px] font-semibold uppercase tracking-[0.12em] text-base-content/50"
                                        >
                                            Total
                                        </div>
                                        <div
                                            class="mt-1 text-sm font-semibold text-base-content"
                                        >
                                            {{
                                                Number(
                                                    item.total_price || 0,
                                                ).toLocaleString("id-ID")
                                            }}
                                        </div>
                                    </div>
                                    <div
                                        class="rounded-md bg-base-100 px-2.5 py-2"
                                    >
                                        <div
                                            class="text-[10px] font-semibold uppercase tracking-[0.12em] text-base-content/50"
                                        >
                                            Total cost
                                        </div>
                                        <div
                                            class="mt-1 text-sm font-semibold text-base-content"
                                        >
                                            {{
                                                Number(
                                                    item.total_cost || 0,
                                                ).toLocaleString("id-ID")
                                            }}
                                        </div>
                                    </div>
                                </div>

                                <div
                                    class="mt-2 rounded-md bg-base-100 px-2.5 py-2"
                                >
                                    <div
                                        class="text-[10px] font-semibold uppercase tracking-[0.12em] text-base-content/50"
                                    >
                                        Match ERP
                                    </div>
                                    <div
                                        v-if="item.matched_product"
                                        class="mt-1 text-sm font-medium text-base-content"
                                    >
                                        {{ item.matched_product.name }}
                                    </div>
                                    <div
                                        v-if="item.matched_product"
                                        class="text-[11px] text-base-content/60"
                                    >
                                        {{ item.matched_product.sku }}
                                    </div>
                                    <div
                                        v-if="item.existing_material"
                                        class="mt-1 text-[11px] text-base-content/65"
                                    >
                                        ERP cost
                                        {{
                                            Number(
                                                item.existing_material
                                                    .unit_cost || 0,
                                            ).toLocaleString("id-ID")
                                        }}
                                        &middot; ERP price
                                        {{
                                            Number(
                                                item.existing_material
                                                    .unit_price || 0,
                                            ).toLocaleString("id-ID")
                                        }}
                                    </div>
                                    <div
                                        v-else
                                        class="mt-1 text-sm text-base-content/50"
                                    >
                                        Belum ada
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="
                            activeTab === 'technicians' &&
                            project.details?.technicians?.length
                        "
                        class="rounded-xl border border-base-200 bg-base-100 p-4"
                    >
                        <div class="font-medium text-base-content">
                            Teknisi project
                        </div>
                        <div class="mt-3 overflow-x-auto">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Legacy teknisi</th>
                                        <th>Fee</th>
                                        <th>Match ERP</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="technician in project.details
                                            .technicians"
                                        :key="technician.legacy_assignment_id"
                                    >
                                        <td>
                                            <div>
                                                {{ technician.technician_name }}
                                            </div>
                                            <div
                                                class="text-[11px] text-base-content/60"
                                            >
                                                {{
                                                    technician.legacy_user_email ||
                                                    technician.technician_phone ||
                                                    "-"
                                                }}
                                            </div>
                                        </td>
                                        <td>
                                            {{
                                                Number(
                                                    technician.fee || 0,
                                                ).toLocaleString("id-ID")
                                            }}
                                            <span
                                                class="text-[11px] text-base-content/60"
                                                >{{ technician.fee_type }}</span
                                            >
                                        </td>
                                        <td>
                                            <div v-if="technician.matched_user">
                                                {{
                                                    technician.matched_user.name
                                                }}
                                            </div>
                                            <div
                                                v-if="technician.matched_user"
                                                class="text-[11px] text-base-content/60"
                                            >
                                                {{
                                                    technician.matched_user
                                                        .email
                                                }}
                                            </div>
                                            <div
                                                v-else
                                                class="text-base-content/50"
                                            >
                                                Belum ada
                                            </div>
                                        </td>
                                        <td>
                                            <span
                                                class="badge badge-xs"
                                                :class="
                                                    compareStatusClass(
                                                        technician.status,
                                                    )
                                                "
                                                >{{ technician.status }}</span
                                            >
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div
                        v-if="
                            activeTab === 'payments' &&
                            project.details?.technician_payments?.length
                        "
                        class="rounded-xl border border-base-200 bg-base-100 p-4"
                    >
                        <div class="font-medium text-base-content">
                            Pembayaran teknisi
                        </div>
                        <div class="mt-3 overflow-x-auto">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Payment</th>
                                        <th>Teknisi</th>
                                        <th>Jumlah</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="payment in project.details
                                            .technician_payments"
                                        :key="payment.legacy_payment_id"
                                    >
                                        <td>
                                            <div>
                                                {{ payment.payment_number }}
                                            </div>
                                            <div
                                                class="text-[11px] text-base-content/60"
                                            >
                                                {{
                                                    payment.paid_date
                                                        ? payment.paid_date
                                                        : "Belum dibayar"
                                                }}<span v-if="payment.period">
                                                    &middot; Periode
                                                    {{ payment.period }}</span
                                                >
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                {{ payment.technician_name }}
                                            </div>
                                            <div
                                                v-if="payment.matched_user"
                                                class="text-[11px] text-base-content/60"
                                            >
                                                ERP:
                                                {{ payment.matched_user.name }}
                                            </div>
                                        </td>
                                        <td>
                                            {{
                                                Number(
                                                    payment.amount || 0,
                                                ).toLocaleString("id-ID")
                                            }}
                                        </td>
                                        <td>
                                            <span
                                                class="badge badge-xs"
                                                :class="
                                                    compareStatusClass(
                                                        payment.status,
                                                    )
                                                "
                                                >{{ payment.status }}</span
                                            >
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="min-w-0 space-y-4">
                    <div
                        class="rounded-xl border border-base-200 bg-base-100 p-4"
                    >
                        <div class="font-medium text-base-content">
                            Aksi terkait
                        </div>
                        <div class="mt-3 flex flex-col gap-2">
                            <button
                                type="button"
                                class="btn btn-primary btn-sm justify-start"
                                :disabled="
                                    !canImportProject || importForm.processing
                                "
                                @click="processImport"
                            >
                                {{
                                    importForm.processing
                                        ? "Memproses import..."
                                        : "Proses import project ini"
                                }}
                            </button>
                            <button
                                v-if="
                                    requiresProcurementFirst &&
                                    !relatedProcurementStagings.length
                                "
                                type="button"
                                class="btn btn-outline btn-sm justify-start"
                                :disabled="prepareProcurementForm.processing"
                                @click="prepareProcurementStaging"
                            >
                                {{
                                    prepareProcurementForm.processing
                                        ? "Menyiapkan staging..."
                                        : "Siapkan procurement staging dulu"
                                }}
                            </button>
                            <button
                                v-if="relatedProcurementStagings.length"
                                type="button"
                                class="btn btn-outline btn-sm justify-start"
                                @click="setActiveTab('procurement')"
                            >
                                Proses procurement staging
                            </button>
                            <button
                                v-if="relatedProcurementStagings.length"
                                type="button"
                                class="btn btn-outline btn-sm justify-start"
                                :disabled="reconcileStagingForm.processing"
                                @click="reconcileProjectProcurementStaging"
                            >
                                {{
                                    reconcileStagingForm.processing
                                        ? "Mengecek staging..."
                                        : "Cek staging project ini"
                                }}
                            </button>
                            <Link
                                class="btn btn-outline btn-sm justify-start"
                                :href="route('erp.admin.legacy-import')"
                            >
                                Kembali ke daftar legacy import
                            </Link>
                            <Link
                                v-if="project.existing_erp_project?.id"
                                class="btn btn-outline btn-sm justify-start"
                                :href="
                                    route(
                                        'projects.show',
                                        project.existing_erp_project.id,
                                    )
                                "
                            >
                                Buka project ERP
                            </Link>
                        </div>
                        <div class="mt-3 text-xs text-base-content/65">
                            <span v-if="canImportProject">
                                Project ini siap diimport dari halaman detail.
                            </span>
                            <span v-else-if="requiresProcurementFirst">
                                Import project dikunci sampai procurement
                                staging selesai dikonversi menjadi PO dan GR.
                            </span>
                            <span v-else>
                                Import dinonaktifkan karena project ini sudah
                                pernah diimport atau status QC-nya belum layak
                                diproses.
                            </span>
                        </div>
                    </div>

                    <div
                        v-if="false"
                        class="rounded-xl border border-base-200 bg-base-100 p-4"
                    >
                        <div
                            class="flex flex-wrap items-center justify-between gap-3"
                        >
                            <div>
                                <div class="font-medium text-base-content">
                                    Procurement terkait
                                </div>
                                <div class="mt-1 text-xs text-base-content/65">
                                    Jalankan cek staging setelah master product
                                    diubah menjadi <strong>service</strong> agar
                                    line jasa dibersihkan dari procurement
                                    project ini.
                                </div>
                            </div>
                            <button
                                v-if="relatedProcurementStagings.length"
                                type="button"
                                class="btn btn-outline btn-sm"
                                :disabled="reconcileStagingForm.processing"
                                @click="reconcileProjectProcurementStaging"
                            >
                                {{
                                    reconcileStagingForm.processing
                                        ? "Mengecek staging..."
                                        : "Cek staging project ini"
                                }}
                            </button>
                        </div>
                        <div
                            v-if="!relatedProcurementStagings.length"
                            class="mt-3 text-sm text-base-content/65"
                        >
                            Belum ada procurement staging untuk project ini.
                        </div>
                        <div
                            v-else
                            id="procurement-staging"
                            class="mt-3 space-y-4"
                        >
                            <details
                                v-for="staging in relatedProcurementStagings"
                                :key="staging.id"
                                class="rounded-xl border border-base-200 bg-base-200/30 p-4"
                                open
                            >
                                <summary class="cursor-pointer">
                                    <div
                                        class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between"
                                    >
                                        <div>
                                            <div class="font-medium">
                                                {{
                                                    staging.legacy_project_number
                                                }}
                                                &middot;
                                                {{
                                                    staging.legacy_project_name
                                                }}
                                            </div>
                                            <div
                                                class="text-xs text-base-content/60"
                                            >
                                                {{ staging.company_name }}
                                                &middot;
                                                {{ staging.warehouse_code }}
                                                &middot;
                                                {{
                                                    staging.procurement_date ||
                                                    "-"
                                                }}
                                            </div>
                                        </div>
                                        <div
                                            class="flex flex-wrap items-center gap-2 text-xs"
                                        >
                                            <span
                                                class="badge badge-outline badge-sm"
                                                >{{ staging.status }}</span
                                            >
                                            <span
                                                class="badge badge-outline badge-sm"
                                                >{{
                                                    staging.lines.length
                                                }}
                                                line</span
                                            >
                                            <span
                                                v-if="
                                                    staging.conversion_summary
                                                        ?.purchase_orders
                                                        ?.length
                                                "
                                                class="badge badge-outline badge-sm"
                                                >{{
                                                    staging.conversion_summary
                                                        .purchase_orders.length
                                                }}
                                                PO</span
                                            >
                                            <span
                                                v-if="
                                                    staging.conversion_summary
                                                        ?.goods_receipts?.length
                                                "
                                                class="badge badge-outline badge-sm"
                                                >{{
                                                    staging.conversion_summary
                                                        .goods_receipts.length
                                                }}
                                                GR</span
                                            >
                                        </div>
                                    </div>
                                </summary>

                                <div class="mt-4 space-y-4 text-sm">
                                    <div
                                        class="grid gap-3 lg:grid-cols-[1.1fr_1fr]"
                                    >
                                        <label class="form-control">
                                            <span
                                                class="label-text text-xs font-medium uppercase tracking-[0.12em] text-base-content/60"
                                                >Tanggal procurement</span
                                            >
                                            <input
                                                v-model="
                                                    procurementStagingDraft(
                                                        staging,
                                                    ).procurement_date
                                                "
                                                type="date"
                                                class="input input-sm input-bordered"
                                                :disabled="
                                                    staging.status ===
                                                    'converted'
                                                "
                                            />
                                        </label>
                                        <label class="form-control">
                                            <span
                                                class="label-text text-xs font-medium uppercase tracking-[0.12em] text-base-content/60"
                                                >Supplier semua line</span
                                            >
                                            <div class="flex gap-2">
                                                <select
                                                    v-model="
                                                        procurementStagingDraft(
                                                            staging,
                                                        ).bulk_vendor_id
                                                    "
                                                    class="select select-sm select-bordered flex-1"
                                                    :disabled="
                                                        staging.status ===
                                                        'converted'
                                                    "
                                                >
                                                    <option value="">
                                                        Pilih supplier
                                                    </option>
                                                    <option
                                                        v-for="vendor in procurementVendors"
                                                        :key="vendor.id"
                                                        :value="
                                                            String(vendor.id)
                                                        "
                                                    >
                                                        {{ vendor.code }}
                                                        &middot;
                                                        {{ vendor.name }}
                                                    </option>
                                                </select>
                                                <button
                                                    type="button"
                                                    class="btn btn-outline btn-sm"
                                                    :disabled="
                                                        staging.status ===
                                                            'converted' ||
                                                        !procurementStagingDraft(
                                                            staging,
                                                        ).bulk_vendor_id
                                                    "
                                                    @click="
                                                        applyVendorToAllProcurementLines(
                                                            staging,
                                                        )
                                                    "
                                                >
                                                    Terapkan
                                                </button>
                                            </div>
                                        </label>
                                    </div>

                                    <label class="form-control">
                                        <span
                                            class="label-text text-xs font-medium uppercase tracking-[0.12em] text-base-content/60"
                                            >Catatan procurement</span
                                        >
                                        <textarea
                                            v-model="
                                                procurementStagingDraft(staging)
                                                    .notes
                                            "
                                            class="textarea textarea-bordered textarea-sm min-h-24 w-full"
                                            :disabled="
                                                staging.status === 'converted'
                                            "
                                            placeholder="Supplier dipilih belakangan, catatan negosiasi, atau instruksi procurement lainnya"
                                        />
                                    </label>

                                    <div class="overflow-x-auto">
                                        <table class="table table-xs">
                                            <thead>
                                                <tr>
                                                    <th>Produk</th>
                                                    <th>Qty</th>
                                                    <th>Unit cost</th>
                                                    <th>Line total</th>
                                                    <th>Vendor</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr
                                                    v-for="(
                                                        line, lineIndex
                                                    ) in staging.lines"
                                                    :key="line.id"
                                                >
                                                    <td>
                                                        <div>
                                                            {{
                                                                line.product_name
                                                            }}
                                                        </div>
                                                        <div
                                                            class="text-[11px] text-base-content/60"
                                                        >
                                                            {{
                                                                line.sku || "-"
                                                            }}
                                                            &middot;
                                                            {{
                                                                line.unit || "-"
                                                            }}
                                                        </div>
                                                    </td>
                                                    <td class="min-w-24">
                                                        <input
                                                            v-model="
                                                                procurementStagingDraft(
                                                                    staging,
                                                                ).lines[
                                                                    lineIndex
                                                                ].qty
                                                            "
                                                            type="number"
                                                            min="0.01"
                                                            step="0.01"
                                                            class="input input-xs input-bordered w-24"
                                                            :disabled="
                                                                staging.status ===
                                                                'converted'
                                                            "
                                                        />
                                                    </td>
                                                    <td class="min-w-28">
                                                        <input
                                                            v-model="
                                                                procurementStagingDraft(
                                                                    staging,
                                                                ).lines[
                                                                    lineIndex
                                                                ].unit_cost
                                                            "
                                                            type="number"
                                                            min="0"
                                                            step="0.01"
                                                            class="input input-xs input-bordered w-28"
                                                            :disabled="
                                                                staging.status ===
                                                                'converted'
                                                            "
                                                        />
                                                    </td>
                                                    <td>
                                                        {{
                                                            procurementLineTotal(
                                                                procurementStagingDraft(
                                                                    staging,
                                                                ).lines[
                                                                    lineIndex
                                                                ],
                                                            ).toLocaleString(
                                                                "id-ID",
                                                            )
                                                        }}
                                                    </td>
                                                    <td class="min-w-56">
                                                        <select
                                                            v-model="
                                                                procurementStagingDraft(
                                                                    staging,
                                                                ).lines[
                                                                    lineIndex
                                                                ].vendor_id
                                                            "
                                                            class="select select-xs select-bordered w-full"
                                                            :disabled="
                                                                staging.status ===
                                                                'converted'
                                                            "
                                                        >
                                                            <option value="">
                                                                Belum dipilih
                                                            </option>
                                                            <option
                                                                v-for="vendor in procurementVendors"
                                                                :key="vendor.id"
                                                                :value="
                                                                    String(
                                                                        vendor.id,
                                                                    )
                                                                "
                                                            >
                                                                {{
                                                                    vendor.code
                                                                }}
                                                                &middot;
                                                                {{
                                                                    vendor.name
                                                                }}
                                                            </option>
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <span
                                                            class="badge badge-xs"
                                                            :class="
                                                                line.status ===
                                                                'converted'
                                                                    ? 'badge-success'
                                                                    : procurementStagingDraft(
                                                                            staging,
                                                                        ).lines[
                                                                            lineIndex
                                                                        ]
                                                                            .vendor_id
                                                                      ? 'badge-info'
                                                                      : 'badge-warning'
                                                            "
                                                        >
                                                            {{
                                                                line.status ===
                                                                "converted"
                                                                    ? "converted"
                                                                    : procurementStagingDraft(
                                                                            staging,
                                                                        ).lines[
                                                                            lineIndex
                                                                        ]
                                                                            .vendor_id
                                                                      ? "ready"
                                                                      : "draft"
                                                            }}
                                                        </span>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="flex flex-wrap gap-2">
                                        <button
                                            type="button"
                                            class="btn btn-outline btn-sm"
                                            :disabled="
                                                staging.status ===
                                                    'converted' ||
                                                procurementStagingSaveForm.processing
                                            "
                                            @click="
                                                saveProcurementStaging(staging)
                                            "
                                        >
                                            {{
                                                procurementStagingSavingId ===
                                                    staging.id &&
                                                procurementStagingSaveForm.processing
                                                    ? "Menyimpan..."
                                                    : "Simpan draft staging"
                                            }}
                                        </button>
                                        <button
                                            type="button"
                                            class="btn btn-primary btn-sm"
                                            :disabled="
                                                !procurementStagingReadyToConvert(
                                                    staging,
                                                ) ||
                                                procurementStagingConvertForm.processing ||
                                                staging.status === 'converted'
                                            "
                                            @click="
                                                convertProcurementStaging(
                                                    staging,
                                                )
                                            "
                                        >
                                            {{
                                                procurementStagingConvertingId ===
                                                    staging.id &&
                                                procurementStagingConvertForm.processing
                                                    ? "Mengonversi..."
                                                    : "Convert ke PO + GR"
                                            }}
                                        </button>
                                        <span
                                            v-if="
                                                staging.status !== 'converted'
                                            "
                                            class="text-xs text-base-content/60"
                                        >
                                            Convert akan membuat PO dan GR real
                                            lalu langsung posting ke stok gudang
                                            OCN dan hutang usaha.
                                        </span>
                                    </div>

                                    <div
                                        v-if="
                                            staging.conversion_summary
                                                ?.purchase_orders?.length ||
                                            staging.conversion_summary
                                                ?.goods_receipts?.length
                                        "
                                        class="rounded-lg border border-success/30 bg-success/10 p-3 text-sm"
                                    >
                                        <div
                                            class="font-medium text-base-content"
                                        >
                                            Dokumen hasil konversi
                                        </div>
                                        <div
                                            v-if="staging.converted_at"
                                            class="mt-1 text-xs text-base-content/65"
                                        >
                                            Dikonversi {{ staging.converted_at
                                            }}<span
                                                v-if="staging.converted_by_name"
                                            >
                                                oleh
                                                {{
                                                    staging.converted_by_name
                                                }}</span
                                            >
                                        </div>
                                        <div
                                            v-if="
                                                staging.conversion_summary
                                                    ?.purchase_orders?.length
                                            "
                                            class="mt-3"
                                        >
                                            <div
                                                class="text-xs uppercase tracking-[0.12em] text-base-content/60"
                                            >
                                                Purchase Order
                                            </div>
                                            <div
                                                class="mt-1 flex flex-wrap gap-2"
                                            >
                                                <Link
                                                    v-for="po in staging
                                                        .conversion_summary
                                                        .purchase_orders"
                                                    :key="po.number"
                                                    class="link link-primary text-sm"
                                                    :href="
                                                        route(
                                                            'erp.purchasing.purchase-orders.show',
                                                            po.number,
                                                        )
                                                    "
                                                >
                                                    {{ po.number }}
                                                </Link>
                                            </div>
                                        </div>
                                        <div
                                            v-if="
                                                staging.conversion_summary
                                                    ?.goods_receipts?.length
                                            "
                                            class="mt-3"
                                        >
                                            <div
                                                class="text-xs uppercase tracking-[0.12em] text-base-content/60"
                                            >
                                                Goods Receipt
                                            </div>
                                            <div
                                                class="mt-1 flex flex-wrap gap-2"
                                            >
                                                <Link
                                                    v-for="gr in staging
                                                        .conversion_summary
                                                        .goods_receipts"
                                                    :key="gr.number"
                                                    class="link link-primary text-sm"
                                                    :href="
                                                        route(
                                                            'erp.purchasing.goods-receipts.show',
                                                            gr.number,
                                                        )
                                                    "
                                                >
                                                    {{ gr.number }}
                                                </Link>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </details>
                        </div>
                    </div>
                </div>
            </div>

            <div
                v-if="activeTab === 'procurement'"
                class="rounded-xl border border-base-200 bg-base-100 p-4"
            >
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <div class="font-medium text-base-content">
                            Procurement terkait
                        </div>
                        <div class="mt-1 text-xs text-base-content/65">
                            Jalankan cek staging setelah master product diubah
                            menjadi <strong>service</strong> agar line jasa
                            dibersihkan dari procurement project ini.
                        </div>
                    </div>
                    <button
                        v-if="relatedProcurementStagings.length"
                        type="button"
                        class="btn btn-outline btn-sm"
                        :disabled="reconcileStagingForm.processing"
                        @click="reconcileProjectProcurementStaging"
                    >
                        {{
                            reconcileStagingForm.processing
                                ? "Mengecek staging..."
                                : "Cek staging project ini"
                        }}
                    </button>
                </div>
                <div
                    v-if="!relatedProcurementStagings.length"
                    class="mt-3 text-sm text-base-content/65"
                >
                    Belum ada procurement staging untuk project ini.
                </div>
                <div v-else class="mt-3 space-y-4">
                    <details
                        v-for="staging in relatedProcurementStagings"
                        :key="staging.id"
                        class="overflow-hidden rounded-xl border border-base-200 bg-base-200/20"
                        open
                    >
                        <summary
                            class="cursor-pointer list-none bg-base-100 px-4 py-3"
                        >
                            <div
                                class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
                            >
                                <div class="min-w-0">
                                    <div class="font-medium">
                                        {{ staging.legacy_project_number }}
                                        &middot;
                                        {{ staging.legacy_project_name }}
                                    </div>
                                    <div class="text-xs text-base-content/60">
                                        {{ staging.company_name }} &middot;
                                        {{ staging.warehouse_code }} &middot;
                                        {{ staging.procurement_date || "-" }}
                                    </div>
                                </div>
                                <div
                                    class="flex flex-wrap items-center gap-2 text-xs lg:justify-end"
                                >
                                    <span
                                        class="badge badge-outline badge-sm"
                                        >{{ staging.status }}</span
                                    >
                                    <span class="badge badge-outline badge-sm"
                                        >{{ staging.lines.length }} line</span
                                    >
                                    <span
                                        v-if="
                                            staging.conversion_summary
                                                ?.purchase_orders?.length
                                        "
                                        class="badge badge-outline badge-sm"
                                        >{{
                                            staging.conversion_summary
                                                .purchase_orders.length
                                        }}
                                        PO</span
                                    >
                                    <span
                                        v-if="
                                            staging.conversion_summary
                                                ?.goods_receipts?.length
                                        "
                                        class="badge badge-outline badge-sm"
                                        >{{
                                            staging.conversion_summary
                                                .goods_receipts.length
                                        }}
                                        GR</span
                                    >
                                </div>
                            </div>
                        </summary>

                        <div
                            class="space-y-4 border-t border-base-200 px-4 py-4 text-sm"
                        >
                            <div
                                class="grid gap-3 xl:grid-cols-[220px_minmax(0,1fr)]"
                            >
                                <div
                                    class="rounded-lg border border-base-200 bg-base-100 p-3"
                                >
                                    <label class="form-control">
                                        <span
                                            class="label-text text-[10px] font-semibold uppercase tracking-[0.14em] text-base-content/55"
                                            >Tanggal procurement</span
                                        >
                                        <input
                                            v-model="
                                                procurementStagingDraft(staging)
                                                    .procurement_date
                                            "
                                            type="date"
                                            class="input input-sm input-bordered mt-2 w-full"
                                            :disabled="
                                                staging.status === 'converted'
                                            "
                                        />
                                    </label>
                                </div>
                                <div
                                    class="rounded-lg border border-base-200 bg-base-100 p-3"
                                >
                                    <label class="form-control min-w-0">
                                        <span
                                            class="label-text text-[10px] font-semibold uppercase tracking-[0.14em] text-base-content/55"
                                            >Supplier semua line</span
                                        >
                                        <div
                                            class="mt-2 flex flex-col gap-2 lg:flex-row lg:items-end"
                                        >
                                            <select
                                                v-model="
                                                    procurementStagingDraft(
                                                        staging,
                                                    ).bulk_vendor_id
                                                "
                                                class="select select-sm select-bordered w-full min-w-0 flex-1"
                                                :disabled="
                                                    staging.status ===
                                                    'converted'
                                                "
                                            >
                                                <option value="">
                                                    Pilih supplier
                                                </option>
                                                <option
                                                    v-for="vendor in procurementVendors"
                                                    :key="vendor.id"
                                                    :value="String(vendor.id)"
                                                >
                                                    {{ vendor.code }} &middot;
                                                    {{ vendor.name }}
                                                </option>
                                            </select>
                                            <button
                                                type="button"
                                                class="btn btn-outline btn-sm lg:min-w-32"
                                                :disabled="
                                                    staging.status ===
                                                        'converted' ||
                                                    !procurementStagingDraft(
                                                        staging,
                                                    ).bulk_vendor_id
                                                "
                                                @click="
                                                    applyVendorToAllProcurementLines(
                                                        staging,
                                                    )
                                                "
                                            >
                                                Terapkan ke semua
                                            </button>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <div
                                class="rounded-lg border border-base-200 bg-base-100 p-3"
                            >
                                <label class="form-control">
                                    <span
                                        class="label-text text-[10px] font-semibold uppercase tracking-[0.14em] text-base-content/55"
                                        >Catatan procurement</span
                                    >
                                    <textarea
                                        v-model="
                                            procurementStagingDraft(staging)
                                                .notes
                                        "
                                        class="textarea textarea-bordered textarea-sm mt-2 min-h-20 w-full"
                                        :disabled="
                                            staging.status === 'converted'
                                        "
                                        placeholder="Supplier dipilih belakangan, catatan negosiasi, atau instruksi procurement lainnya"
                                    />
                                </label>
                            </div>

                            <div v-if="false" class="overflow-x-auto">
                                <table class="table table-xs">
                                    <thead>
                                        <tr>
                                            <th>Produk</th>
                                            <th>Qty</th>
                                            <th>Unit cost</th>
                                            <th>Line total</th>
                                            <th>Vendor</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="(
                                                line, lineIndex
                                            ) in staging.lines"
                                            :key="line.id"
                                        >
                                            <td>
                                                <div>
                                                    {{ line.product_name }}
                                                </div>
                                                <div
                                                    class="text-[11px] text-base-content/60"
                                                >
                                                    {{ line.sku || "-" }}
                                                    &middot;
                                                    {{ line.unit || "-" }}
                                                </div>
                                            </td>
                                            <td class="min-w-24">
                                                <input
                                                    v-model="
                                                        procurementStagingDraft(
                                                            staging,
                                                        ).lines[lineIndex].qty
                                                    "
                                                    type="number"
                                                    min="0.01"
                                                    step="0.01"
                                                    class="input input-xs input-bordered w-24"
                                                    :disabled="
                                                        staging.status ===
                                                        'converted'
                                                    "
                                                />
                                            </td>
                                            <td class="min-w-28">
                                                <input
                                                    v-model="
                                                        procurementStagingDraft(
                                                            staging,
                                                        ).lines[lineIndex]
                                                            .unit_cost
                                                    "
                                                    type="number"
                                                    min="0"
                                                    step="0.01"
                                                    class="input input-xs input-bordered w-28"
                                                    :disabled="
                                                        staging.status ===
                                                        'converted'
                                                    "
                                                />
                                            </td>
                                            <td>
                                                {{
                                                    procurementLineTotal(
                                                        procurementStagingDraft(
                                                            staging,
                                                        ).lines[lineIndex],
                                                    ).toLocaleString("id-ID")
                                                }}
                                            </td>
                                            <td class="min-w-56">
                                                <select
                                                    v-model="
                                                        procurementStagingDraft(
                                                            staging,
                                                        ).lines[lineIndex]
                                                            .vendor_id
                                                    "
                                                    class="select select-xs select-bordered w-full"
                                                    :disabled="
                                                        staging.status ===
                                                        'converted'
                                                    "
                                                >
                                                    <option value="">
                                                        Belum dipilih
                                                    </option>
                                                    <option
                                                        v-for="vendor in procurementVendors"
                                                        :key="vendor.id"
                                                        :value="
                                                            String(vendor.id)
                                                        "
                                                    >
                                                        {{ vendor.code }}
                                                        &middot;
                                                        {{ vendor.name }}
                                                    </option>
                                                </select>
                                            </td>
                                            <td>
                                                <span
                                                    class="badge badge-xs"
                                                    :class="
                                                        line.status ===
                                                        'converted'
                                                            ? 'badge-success'
                                                            : procurementStagingDraft(
                                                                    staging,
                                                                ).lines[
                                                                    lineIndex
                                                                ].vendor_id
                                                              ? 'badge-info'
                                                              : 'badge-warning'
                                                    "
                                                >
                                                    {{
                                                        line.status ===
                                                        "converted"
                                                            ? "converted"
                                                            : procurementStagingDraft(
                                                                    staging,
                                                                ).lines[
                                                                    lineIndex
                                                                ].vendor_id
                                                              ? "ready"
                                                              : "draft"
                                                    }}
                                                </span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="space-y-2">
                                <div
                                    v-for="(line, lineIndex) in staging.lines"
                                    :key="`compact-${line.id}`"
                                    class="rounded-lg border border-base-200 bg-base-100 px-3 py-3"
                                >
                                    <div
                                        class="flex flex-col gap-1.5 lg:flex-row lg:items-start lg:justify-between"
                                    >
                                        <div class="min-w-0">
                                            <div
                                                class="truncate text-sm font-medium text-base-content"
                                            >
                                                {{ line.product_name }}
                                            </div>
                                            <div
                                                class="text-[11px] text-base-content/60"
                                            >
                                                {{ line.sku || "-" }} &middot;
                                                {{ line.unit || "-" }}
                                            </div>
                                        </div>
                                        <span
                                            class="badge badge-xs shrink-0"
                                            :class="
                                                line.status === 'converted'
                                                    ? 'badge-success'
                                                    : procurementStagingDraft(
                                                            staging,
                                                        ).lines[lineIndex]
                                                            .vendor_id
                                                      ? 'badge-info'
                                                      : 'badge-warning'
                                            "
                                        >
                                            {{
                                                line.status === "converted"
                                                    ? "converted"
                                                    : procurementStagingDraft(
                                                            staging,
                                                        ).lines[lineIndex]
                                                            .vendor_id
                                                      ? "ready"
                                                      : "draft"
                                            }}
                                        </span>
                                    </div>

                                    <div
                                        class="mt-3 grid gap-2 xl:grid-cols-[88px_120px_minmax(0,1fr)_140px]"
                                    >
                                        <label class="form-control">
                                            <span
                                                class="label-text text-[10px] font-semibold uppercase tracking-[0.12em] text-base-content/50"
                                                >Qty</span
                                            >
                                            <input
                                                v-model="
                                                    procurementStagingDraft(
                                                        staging,
                                                    ).lines[lineIndex].qty
                                                "
                                                type="number"
                                                min="0.01"
                                                step="0.01"
                                                class="input input-xs input-bordered w-full"
                                                :disabled="
                                                    staging.status ===
                                                    'converted'
                                                "
                                            />
                                        </label>
                                        <label class="form-control">
                                            <span
                                                class="label-text text-[10px] font-semibold uppercase tracking-[0.12em] text-base-content/50"
                                                >Unit cost</span
                                            >
                                            <input
                                                v-model="
                                                    procurementStagingDraft(
                                                        staging,
                                                    ).lines[lineIndex].unit_cost
                                                "
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                class="input input-xs input-bordered w-full"
                                                :disabled="
                                                    staging.status ===
                                                    'converted'
                                                "
                                            />
                                        </label>
                                        <label class="form-control min-w-0">
                                            <span
                                                class="label-text text-[10px] font-semibold uppercase tracking-[0.12em] text-base-content/50"
                                                >Vendor</span
                                            >
                                            <select
                                                v-model="
                                                    procurementStagingDraft(
                                                        staging,
                                                    ).lines[lineIndex].vendor_id
                                                "
                                                class="select select-xs select-bordered w-full"
                                                :disabled="
                                                    staging.status ===
                                                    'converted'
                                                "
                                            >
                                                <option value="">
                                                    Belum dipilih
                                                </option>
                                                <option
                                                    v-for="vendor in procurementVendors"
                                                    :key="`compact-${line.id}-${vendor.id}`"
                                                    :value="String(vendor.id)"
                                                >
                                                    {{ vendor.code }} &middot;
                                                    {{ vendor.name }}
                                                </option>
                                            </select>
                                        </label>
                                        <div
                                            class="rounded-md border border-base-200 bg-base-200/40 px-2.5 py-2"
                                        >
                                            <div
                                                class="text-[10px] font-semibold uppercase tracking-[0.12em] text-base-content/50"
                                            >
                                                Line total
                                            </div>
                                            <div
                                                class="mt-1 text-sm font-semibold text-base-content"
                                            >
                                                {{
                                                    procurementLineTotal(
                                                        procurementStagingDraft(
                                                            staging,
                                                        ).lines[lineIndex],
                                                    ).toLocaleString("id-ID")
                                                }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="flex flex-col gap-3 rounded-lg border border-base-200 bg-base-100 p-3 lg:flex-row lg:items-center lg:justify-between"
                            >
                                <div class="text-xs text-base-content/60">
                                    <span v-if="staging.status !== 'converted'">
                                        Simpan draft bila supplier atau qty
                                        belum final. Convert akan langsung
                                        membentuk PO, GR, dan posting stok.
                                    </span>
                                    <span v-else>
                                        Staging ini sudah dikonversi. Data line
                                        tetap ditampilkan sebagai referensi
                                        procurement.
                                    </span>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        class="btn btn-outline btn-sm"
                                        :disabled="
                                            staging.status === 'converted' ||
                                            procurementStagingSaveForm.processing
                                        "
                                        @click="saveProcurementStaging(staging)"
                                    >
                                        {{
                                            procurementStagingSavingId ===
                                                staging.id &&
                                            procurementStagingSaveForm.processing
                                                ? "Menyimpan..."
                                                : "Simpan draft staging"
                                        }}
                                    </button>
                                    <button
                                        type="button"
                                        class="btn btn-primary btn-sm"
                                        :disabled="
                                            !procurementStagingReadyToConvert(
                                                staging,
                                            ) ||
                                            procurementStagingConvertForm.processing ||
                                            staging.status === 'converted'
                                        "
                                        @click="
                                            convertProcurementStaging(staging)
                                        "
                                    >
                                        {{
                                            procurementStagingConvertingId ===
                                                staging.id &&
                                            procurementStagingConvertForm.processing
                                                ? "Mengonversi..."
                                                : "Convert ke PO + GR"
                                        }}
                                    </button>
                                </div>
                            </div>

                            <div
                                v-if="
                                    staging.status === 'converted' &&
                                    canImportProject
                                "
                                class="rounded-lg border border-success/30 bg-success/10 p-3"
                            >
                                <div
                                    class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
                                >
                                    <div>
                                        <div
                                            class="text-sm font-medium text-base-content"
                                        >
                                            PO dan GR selesai diproses
                                        </div>
                                        <div
                                            class="mt-1 text-xs text-base-content/70"
                                        >
                                            Lanjutkan dengan import project agar
                                            material, invoice, dan distribusi
                                            tim terbentuk di ERP.
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        class="btn btn-success btn-sm"
                                        :disabled="importForm.processing"
                                        @click="processImport"
                                    >
                                        {{
                                            importForm.processing
                                                ? "Memproses import..."
                                                : "Proses import project ini"
                                        }}
                                    </button>
                                </div>
                            </div>

                            <div
                                v-if="
                                    staging.conversion_summary?.purchase_orders
                                        ?.length ||
                                    staging.conversion_summary?.goods_receipts
                                        ?.length
                                "
                                class="rounded-lg border border-success/30 bg-success/10 p-3 text-sm"
                            >
                                <div class="font-medium text-base-content">
                                    Dokumen hasil konversi
                                </div>
                                <div
                                    v-if="staging.converted_at"
                                    class="mt-1 text-xs text-base-content/65"
                                >
                                    Dikonversi {{ staging.converted_at
                                    }}<span v-if="staging.converted_by_name">
                                        oleh
                                        {{ staging.converted_by_name }}</span
                                    >
                                </div>
                                <div
                                    v-if="
                                        staging.conversion_summary
                                            ?.purchase_orders?.length
                                    "
                                    class="mt-3"
                                >
                                    <div
                                        class="text-xs uppercase tracking-[0.12em] text-base-content/60"
                                    >
                                        Purchase Order
                                    </div>
                                    <div class="mt-1 flex flex-wrap gap-2">
                                        <Link
                                            v-for="po in staging
                                                .conversion_summary
                                                .purchase_orders"
                                            :key="po.number"
                                            class="link link-primary text-sm"
                                            :href="
                                                route(
                                                    'erp.purchasing.purchase-orders.show',
                                                    po.number,
                                                )
                                            "
                                        >
                                            {{ po.number }}
                                        </Link>
                                    </div>
                                </div>
                                <div
                                    v-if="
                                        staging.conversion_summary
                                            ?.goods_receipts?.length
                                    "
                                    class="mt-3"
                                >
                                    <div
                                        class="text-xs uppercase tracking-[0.12em] text-base-content/60"
                                    >
                                        Goods Receipt
                                    </div>
                                    <div class="mt-1 flex flex-wrap gap-2">
                                        <Link
                                            v-for="gr in staging
                                                .conversion_summary
                                                .goods_receipts"
                                            :key="gr.number"
                                            class="link link-primary text-sm"
                                            :href="
                                                route(
                                                    'erp.purchasing.goods-receipts.show',
                                                    gr.number,
                                                )
                                            "
                                        >
                                            {{ gr.number }}
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </details>
                </div>
            </div>

            <dialog id="legacy-project-meta-modal" class="modal">
                <div class="modal-box max-w-lg">
                    <h3 class="text-lg font-semibold">Info source legacy</h3>
                    <div class="mt-4 space-y-3 text-sm">
                        <div
                            class="rounded-lg border border-base-200 bg-base-200/30 p-3"
                        >
                            <div
                                class="text-[10px] font-semibold uppercase tracking-[0.14em] text-base-content/55"
                            >
                                Import key
                            </div>
                            <code
                                class="mt-2 block break-all rounded bg-base-100 px-2 py-1 text-xs"
                                >{{ project.import_key }}</code
                            >
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div
                                class="rounded-lg border border-base-200 bg-base-200/30 p-3"
                            >
                                <div
                                    class="text-[10px] font-semibold uppercase tracking-[0.14em] text-base-content/55"
                                >
                                    Generated at
                                </div>
                                <div class="mt-2">{{ generatedAt || "-" }}</div>
                            </div>
                            <div
                                class="rounded-lg border border-base-200 bg-base-200/30 p-3"
                            >
                                <div
                                    class="text-[10px] font-semibold uppercase tracking-[0.14em] text-base-content/55"
                                >
                                    Database
                                </div>
                                <div class="mt-2">
                                    {{ source?.database || "-" }}
                                </div>
                            </div>
                            <div
                                class="rounded-lg border border-base-200 bg-base-200/30 p-3"
                            >
                                <div
                                    class="text-[10px] font-semibold uppercase tracking-[0.14em] text-base-content/55"
                                >
                                    Host
                                </div>
                                <div class="mt-2 break-all">
                                    {{ source?.host || "-" }}
                                </div>
                            </div>
                            <div
                                class="rounded-lg border border-base-200 bg-base-200/30 p-3"
                            >
                                <div
                                    class="text-[10px] font-semibold uppercase tracking-[0.14em] text-base-content/55"
                                >
                                    Schema
                                </div>
                                <div class="mt-2">
                                    {{ source?.schema || "-" }}
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-action">
                        <button
                            type="button"
                            class="btn btn-primary btn-sm"
                            @click="closeProjectMetaModal"
                        >
                            Tutup
                        </button>
                    </div>
                </div>
                <form method="dialog" class="modal-backdrop">
                    <button type="submit" @click="closeProjectMetaModal">
                        close
                    </button>
                </form>
            </dialog>
        </div>
    </AppLayout>
</template>
