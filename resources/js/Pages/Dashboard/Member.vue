<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { useCurrency } from '@/composables/useCurrency';

defineProps({ totalEarned: Number, projectList: Array });
const { format } = useCurrency();
</script>

<template>
    <AppLayout>
        <div class="space-y-6">
            <h1 class="text-2xl font-bold">Dashboard Saya</h1>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 max-w-2xl">
                <div class="ocn-panel">
                    <div class="ocn-panel__head">
                        <h2 class="ocn-panel__title">Ringkasan pendapatan</h2>
                    </div>
                    <div class="card-body p-5">
                        <h3 class="text-sm font-medium text-base-content/70">Total pendapatan saya</h3>
                        <p class="text-3xl font-bold text-primary mt-1">{{ format(totalEarned) }}</p>
                        <p class="text-sm text-base-content/50 mt-1">dari {{ projectList.length }} project</p>
                    </div>
                </div>
            </div>

            <div class="ocn-panel">
                <div class="ocn-panel__head">
                    <h2 class="ocn-panel__title">Riwayat project & pembayaran</h2>
                </div>
                <div class="card-body">
                    <div class="overflow-x-auto">
                        <table class="table table-zebra table-sm">
                            <thead>
                                <tr>
                                    <th>Project</th>
                                    <th>Status</th>
                                    <th>Peran</th>
                                    <th>%</th>
                                    <th>Base Pay</th>
                                    <th>Bonus</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="d in projectList" :key="d.project_id">
                                    <td class="font-medium">{{ d.project_name }}</td>
                                    <td><StatusBadge :status="d.project_status" /></td>
                                    <td class="capitalize">{{ d.role_in_project }}</td>
                                    <td>{{ d.percentage }}%</td>
                                    <td>{{ format(d.base_pay) }}</td>
                                    <td>{{ format(d.bonus) }}</td>
                                    <td class="font-semibold text-primary">{{ format(d.total_pay) }}</td>
                                </tr>
                                <tr v-if="!projectList.length">
                                    <td colspan="7" class="text-center text-base-content/50 py-8">Belum ada data pembagian</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
