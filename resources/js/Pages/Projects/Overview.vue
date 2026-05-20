<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import ProjectRevenueLineChart from '@/Components/Charts/ProjectRevenueLineChart.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { useCurrency } from '@/composables/useCurrency';

const props = defineProps({
    selected_year: Number,
    stats: Object,
    status_summary: Object,
    task_summary: Object,
    material_summary: Object,
    type_summary: Array,
    recent_projects: Array,
    monthly_data: Array,
});

const { format } = useCurrency();

const percentLabel = (value) => `${Number(value ?? 0).toLocaleString('id-ID', { maximumFractionDigits: 1 })}%`;
</script>

<template>
    <Head title="Projects Overview" />

    <AppLayout>
        <div class="space-y-5">
            <div class="ocn-panel">
                <div class="ocn-panel__head">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Projects Workspace</p>
                            <h1 class="ocn-panel__title mt-1">Overview</h1>
                            <p class="ocn-panel__desc mt-1">Dashboard statistik seluruh project untuk memantau pipeline, penagihan, eksekusi task, dan kesiapan material.</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 shrink-0">
                            <Link :href="route('projects.index')" class="btn btn-outline btn-sm shrink-0">Daftar Project</Link>
                            <Link :href="route('erp.projects')" class="btn btn-ghost btn-sm shrink-0 gap-1.5">
                                <ArrowLeftIcon class="h-4 w-4" />
                                Back
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">Total project</p>
                    <p class="mt-2 text-3xl font-bold text-base-content">{{ stats?.project_count ?? 0 }}</p>
                    <p class="mt-2 text-xs text-base-content/55">{{ status_summary?.berjalan ?? 0 }} berjalan, {{ status_summary?.selesai ?? 0 }} selesai</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">Nilai kontrak</p>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ format(stats?.total_contract_value) }}</p>
                    <p class="mt-2 text-xs text-base-content/55">Rata-rata {{ format(stats?.average_contract_value) }} per project</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">Pembayaran terkumpul</p>
                    <p class="mt-2 text-2xl font-bold text-success">{{ format(stats?.total_collected) }}</p>
                    <p class="mt-2 text-xs text-base-content/55">Collection rate {{ percentLabel(stats?.collection_rate) }}</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">Sisa tagihan</p>
                    <p class="mt-2 text-2xl font-bold text-warning">{{ format(stats?.outstanding_amount) }}</p>
                    <p class="mt-2 text-xs text-base-content/55">Margin kas {{ format(stats?.gross_margin) }}</p>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-3">
                <div class="ocn-panel lg:col-span-2">
                    <div class="ocn-panel__head">
                        <h2 class="ocn-panel__title">Arus kas project {{ selected_year }}</h2>
                        <p class="ocn-panel__desc">Perbandingan pembayaran masuk dan biaya keluar dari seluruh project aktif dan historis.</p>
                    </div>
                    <div class="card-body">
                        <ProjectRevenueLineChart :monthly-data="monthly_data ?? []" />
                    </div>
                </div>

                <div class="ocn-panel">
                    <div class="ocn-panel__head">
                        <h2 class="ocn-panel__title">Komposisi status</h2>
                        <p class="ocn-panel__desc">Sebaran tahap kerja seluruh project di workspace.</p>
                    </div>
                    <div class="card-body space-y-2 text-sm">
                        <div class="flex items-center justify-between rounded-lg bg-base-200 px-3 py-2">
                            <span>Negosiasi</span>
                            <strong>{{ status_summary?.negosiasi ?? 0 }}</strong>
                        </div>
                        <div class="flex items-center justify-between rounded-lg bg-info/10 px-3 py-2">
                            <span>Berjalan</span>
                            <strong>{{ status_summary?.berjalan ?? 0 }}</strong>
                        </div>
                        <div class="flex items-center justify-between rounded-lg bg-success/10 px-3 py-2">
                            <span>Selesai</span>
                            <strong>{{ status_summary?.selesai ?? 0 }}</strong>
                        </div>
                        <div class="flex items-center justify-between rounded-lg bg-error/10 px-3 py-2">
                            <span>Dibatalkan</span>
                            <strong>{{ status_summary?.dibatalkan ?? 0 }}</strong>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid gap-4 xl:grid-cols-3">
                <div class="ocn-panel">
                    <div class="ocn-panel__head">
                        <h2 class="ocn-panel__title">Eksekusi task</h2>
                        <p class="ocn-panel__desc">Progress board task gabungan dari semua project.</p>
                    </div>
                    <div class="card-body space-y-4">
                        <div class="rounded-2xl bg-base-200/70 p-4">
                            <div class="flex items-end justify-between gap-3">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">Completion rate</p>
                                    <p class="mt-1 text-3xl font-bold text-primary">{{ percentLabel(stats?.task_completion_rate) }}</p>
                                </div>
                                <p class="text-sm text-base-content/60">{{ task_summary?.done ?? 0 }} dari {{ task_summary?.total ?? 0 }} task selesai</p>
                            </div>
                            <progress class="progress progress-primary mt-3 h-3 w-full" :value="stats?.task_completion_rate ?? 0" max="100" />
                        </div>

                        <div class="grid gap-3 sm:grid-cols-3">
                            <div class="rounded-xl border border-slate-200 p-3">
                                <p class="text-xs uppercase tracking-wide text-base-content/50">Todo</p>
                                <p class="mt-1 text-xl font-bold">{{ task_summary?.todo ?? 0 }}</p>
                            </div>
                            <div class="rounded-xl border border-slate-200 p-3">
                                <p class="text-xs uppercase tracking-wide text-base-content/50">In progress</p>
                                <p class="mt-1 text-xl font-bold text-info">{{ task_summary?.in_progress ?? 0 }}</p>
                            </div>
                            <div class="rounded-xl border border-slate-200 p-3">
                                <p class="text-xs uppercase tracking-wide text-base-content/50">Done</p>
                                <p class="mt-1 text-xl font-bold text-success">{{ task_summary?.done ?? 0 }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="ocn-panel">
                    <div class="ocn-panel__head">
                        <h2 class="ocn-panel__title">Kesiapan material</h2>
                        <p class="ocn-panel__desc">Pantau reserve dan issue material project lintas gudang.</p>
                    </div>
                    <div class="card-body space-y-4">
                        <div class="rounded-2xl bg-base-200/70 p-4">
                            <div class="flex items-end justify-between gap-3">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">Readiness rate</p>
                                    <p class="mt-1 text-3xl font-bold text-secondary">{{ percentLabel(stats?.material_readiness_rate) }}</p>
                                </div>
                                <p class="text-sm text-base-content/60">{{ material_summary?.lines ?? 0 }} line material</p>
                            </div>
                            <progress class="progress progress-secondary mt-3 h-3 w-full" :value="stats?.material_readiness_rate ?? 0" max="100" />
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="rounded-xl border border-slate-200 p-3">
                                <p class="text-xs uppercase tracking-wide text-base-content/50">Qty planned</p>
                                <p class="mt-1 text-xl font-bold">{{ Number(material_summary?.planned_qty ?? 0).toLocaleString('id-ID') }}</p>
                            </div>
                            <div class="rounded-xl border border-slate-200 p-3">
                                <p class="text-xs uppercase tracking-wide text-base-content/50">Qty reserved</p>
                                <p class="mt-1 text-xl font-bold text-info">{{ Number(material_summary?.reserved_qty ?? 0).toLocaleString('id-ID') }}</p>
                            </div>
                            <div class="rounded-xl border border-slate-200 p-3">
                                <p class="text-xs uppercase tracking-wide text-base-content/50">Qty issued</p>
                                <p class="mt-1 text-xl font-bold text-success">{{ Number(material_summary?.issued_qty ?? 0).toLocaleString('id-ID') }}</p>
                            </div>
                            <div class="rounded-xl border border-slate-200 p-3">
                                <p class="text-xs uppercase tracking-wide text-base-content/50">Nilai biaya</p>
                                <p class="mt-1 text-xl font-bold">{{ format(material_summary?.cost_value) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="ocn-panel">
                    <div class="ocn-panel__head">
                        <h2 class="ocn-panel__title">Tipe project</h2>
                        <p class="ocn-panel__desc">Kontribusi nilai kontrak per tipe project.</p>
                    </div>
                    <div class="card-body">
                        <div class="space-y-3">
                            <div
                                v-for="row in type_summary ?? []"
                                :key="row.key"
                                class="rounded-xl border border-slate-200 p-3"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="font-semibold leading-tight">{{ row.label }}</p>
                                        <p class="mt-1 text-xs text-base-content/55">{{ row.count }} project</p>
                                    </div>
                                    <p class="text-right text-sm font-bold text-primary">{{ format(row.value) }}</p>
                                </div>
                            </div>
                            <div v-if="!(type_summary && type_summary.length)" class="rounded-xl border border-dashed border-slate-200 p-6 text-center text-sm text-base-content/50">
                                Belum ada tipe project yang bisa diringkas.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="ocn-panel">
                <div class="ocn-panel__head">
                    <div>
                        <h2 class="ocn-panel__title">Project terbaru</h2>
                        <p class="ocn-panel__desc">Snapshot cepat nilai, progress penagihan, dan progress task project terakhir.</p>
                    </div>
                </div>
                <div class="p-0">
                    <div class="overflow-x-auto">
                        <table class="table table-zebra table-sm text-xs">
                            <thead>
                                <tr>
                                    <th>Project</th>
                                    <th>Klien</th>
                                    <th>Status</th>
                                    <th>Nilai</th>
                                    <th>Pembayaran</th>
                                    <th>Task</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="project in recent_projects ?? []" :key="project.id">
                                    <td class="py-2">
                                        <Link :href="route('projects.show', project.id)" class="link link-hover font-medium">
                                            {{ project.name }}
                                        </Link>
                                        <p class="mt-1 text-[11px] text-base-content/55">{{ project.project_type_label }}</p>
                                    </td>
                                    <td class="py-2 text-base-content/75">{{ project.client_name }}</td>
                                    <td class="py-2"><StatusBadge :status="project.status" /></td>
                                    <td class="py-2 font-medium whitespace-nowrap">{{ format(project.total_value) }}</td>
                                    <td class="py-2">
                                        <div class="flex min-w-[180px] items-center gap-2">
                                            <progress class="progress progress-success h-2 w-16" :value="project.paid_amount" :max="project.total_value || 1" />
                                            <span class="text-[11px] text-base-content/60">{{ format(project.paid_amount) }} / {{ format(project.total_value) }}</span>
                                        </div>
                                    </td>
                                    <td class="py-2">
                                        <div class="flex min-w-[120px] items-center gap-2">
                                            <progress class="progress progress-primary h-2 w-16" :value="project.task_progress" max="100" />
                                            <span class="text-[11px] text-base-content/60">{{ percentLabel(project.task_progress) }}</span>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!((recent_projects ?? []).length)">
                                    <td colspan="6" class="py-10 text-center text-base-content/50">Belum ada project untuk ditampilkan.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
