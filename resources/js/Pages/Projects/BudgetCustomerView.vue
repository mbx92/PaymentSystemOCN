<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeftIcon, PrinterIcon } from '@heroicons/vue/24/outline';
import { computed, onBeforeUnmount, onMounted } from 'vue';
import { useCurrency } from '@/composables/useCurrency';

const props = defineProps({
    budget: { type: Object, required: true },
    items: { type: Array, default: () => [] },
    markup_percent: { type: Number, default: 40 },
    total: { type: Number, default: 0 },
    brand: {
        type: Object,
        default: () => ({ name: '', tagline: '', logo_data_uri: null }),
    },
});

const { format } = useCurrency();

const generatedAt = computed(() =>
    new Date().toLocaleString('id-ID', { dateStyle: 'long', timeStyle: 'short' }),
);

function printView() {
    window.print();
}

onMounted(() => {
    document.documentElement.classList.add('overflow-hidden');
});

onBeforeUnmount(() => {
    document.documentElement.classList.remove('overflow-hidden');
});
</script>

<template>
    <Head :title="`Penawaran - ${budget.name}`" />

    <div class="customer-rab-shell fixed inset-0 z-[200] flex flex-col bg-base-100 print:static print:inset-auto print:z-auto print:h-auto">
        <header class="shrink-0 border-b border-base-200 bg-base-100 px-5 py-4 print:hidden">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <Link
                    class="btn btn-ghost btn-sm gap-1.5"
                    :href="route('erp.projects.budgets.show', budget.id)"
                    title="Kembali ke detail budget"
                >
                    <ArrowLeftIcon class="h-4 w-4" />
                    Kembali
                </Link>
                <div class="flex items-center gap-2">
                    <button type="button" class="btn btn-outline btn-sm gap-1.5" @click="printView">
                        <PrinterIcon class="h-4 w-4" />
                        Cetak
                    </button>
                </div>
            </div>
        </header>

        <main class="flex-1 overflow-auto px-5 py-6 print:overflow-visible print:px-0 print:py-0">
            <div class="mx-auto max-w-5xl space-y-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="flex items-start gap-4 min-w-0">
                        <img
                            v-if="brand.logo_data_uri"
                            :src="brand.logo_data_uri"
                            :alt="brand.name"
                            class="h-14 w-auto object-contain shrink-0"
                        />
                        <div class="min-w-0">
                            <h1 class="text-xl font-bold leading-tight">{{ brand.name }}</h1>
                            <p v-if="brand.tagline" class="text-sm text-base-content/60 mt-0.5">{{ brand.tagline }}</p>
                        </div>
                    </div>
                    <div class="text-sm sm:text-right shrink-0">
                        <p class="text-xs font-bold uppercase tracking-[0.14em] text-primary/70">Rencana Anggaran Biaya</p>
                        <p class="font-semibold mt-1">{{ budget.name }}</p>
                        <p class="text-base-content/60 mt-0.5">{{ generatedAt }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm rounded-xl border border-base-200 bg-base-200/30 p-4">
                    <div>
                        <span class="text-base-content/60">Customer</span>
                        <div class="font-medium">{{ budget.client_name }}</div>
                    </div>
                    <div>
                        <span class="text-base-content/60">Tipe Project</span>
                        <div>{{ budget.project_type_label }}</div>
                    </div>
                    <div v-if="budget.description" class="sm:col-span-2">
                        <span class="text-base-content/60">Catatan</span>
                        <div>{{ budget.description }}</div>
                    </div>
                </div>

                <div class="overflow-x-auto rounded-xl border border-base-200">
                    <table class="table table-sm">
                        <thead>
                            <tr class="bg-base-200 text-xs uppercase tracking-wide">
                                <th class="w-12 text-center">#</th>
                                <th>Item</th>
                                <th class="w-24 text-center">Qty</th>
                                <th class="w-24 text-center">Satuan</th>
                                <th class="w-36 text-right">Harga Satuan</th>
                                <th class="w-36 text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(item, index) in items" :key="`${item.name}-${index}`">
                                <td class="text-center text-base-content/50 font-mono text-xs">{{ index + 1 }}</td>
                                <td class="font-medium">{{ item.name }}</td>
                                <td class="text-center tabular-nums">{{ item.qty }}</td>
                                <td class="text-center text-base-content/70">{{ item.uom }}</td>
                                <td class="text-right tabular-nums">{{ format(item.unit_price) }}</td>
                                <td class="text-right tabular-nums font-medium">{{ format(item.subtotal) }}</td>
                            </tr>
                            <tr v-if="!items.length">
                                <td colspan="6" class="text-center py-10 text-base-content/50">
                                    Belum ada item pada RAB ini.
                                </td>
                            </tr>
                        </tbody>
                        <tfoot v-if="items.length">
                            <tr class="bg-base-200/80 font-semibold">
                                <td colspan="5" class="text-right text-sm uppercase tracking-wide">Total Penawaran</td>
                                <td class="text-right tabular-nums text-base">{{ format(total) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <p class="text-xs text-base-content/50 print:text-[10px]">
                    Penawaran ini disusun berdasarkan kebutuhan project. Harga belum termasuk revisi di luar scope tanpa persetujuan tertulis.
                </p>
            </div>
        </main>
    </div>
</template>

<style scoped>
@media print {
    .customer-rab-shell {
        background: white;
    }
}
</style>
