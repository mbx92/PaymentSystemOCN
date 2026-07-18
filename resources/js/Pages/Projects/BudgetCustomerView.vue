<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeftIcon, PrinterIcon, ShareIcon } from '@heroicons/vue/24/outline';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useCurrency } from '@/composables/useCurrency';

const props = defineProps({
    budget: { type: Object, required: true },
    items: { type: Array, default: () => [] },
    markup_percent: { type: Number, default: 40 },
    total: { type: Number, default: 0 },
    share_url: { type: String, default: '' },
    pdf_url: { type: String, default: '' },
    is_public: { type: Boolean, default: false },
    brand: {
        type: Object,
        default: () => ({ name: '', tagline: '', logo_data_uri: null }),
    },
});

const { format } = useCurrency();
const shareLabel = ref('Share');

const generatedAt = computed(() =>
    new Date().toLocaleString('id-ID', { dateStyle: 'long', timeStyle: 'short' }),
);

function printView() {
    window.print();
}

async function shareView() {
    const url = props.share_url || window.location.href;

    try {
        if (navigator.share) {
            await navigator.share({
                title: `Penawaran - ${props.budget.name}`,
                text: `Penawaran untuk ${props.budget.client_name}`,
                url,
            });

            return;
        }

        if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(url);
            shareLabel.value = 'Link disalin';
            window.setTimeout(() => {
                shareLabel.value = 'Share';
            }, 2000);
            return;
        }

        window.prompt('Salin link penawaran ini:', url);
    } catch (error) {
        if (error?.name === 'AbortError') return;

        window.prompt('Salin link penawaran ini:', url);
    }
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
            <div class="flex flex-wrap items-center gap-3" :class="is_public ? 'justify-end' : 'justify-between'">
                <Link
                    v-if="!is_public"
                    class="btn btn-ghost btn-sm gap-1.5"
                    :href="route('erp.projects.budgets.show', budget.id)"
                    title="Kembali ke detail budget"
                >
                    <ArrowLeftIcon class="h-4 w-4" />
                    Kembali
                </Link>
                <div class="flex items-center gap-2">
                    <a
                        v-if="pdf_url"
                        :href="pdf_url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="btn btn-outline btn-sm gap-1.5"
                    >
                        Download PDF
                    </a>
                    <button type="button" class="btn btn-outline btn-sm gap-1.5" @click="shareView">
                        <ShareIcon class="h-4 w-4" />
                        {{ shareLabel }}
                    </button>
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

                <div class="space-y-3">
                    <div class="space-y-3 sm:hidden">
                        <article
                            v-for="(item, index) in items"
                            :key="`${item.name}-${index}`"
                            class="rounded-xl border border-base-200 bg-base-100 p-4 shadow-sm"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-base-content/45">Item {{ index + 1 }}</p>
                                    <h3 class="mt-1 font-semibold leading-snug">{{ item.name }}</h3>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs text-base-content/50">Subtotal</p>
                                    <p class="font-semibold tabular-nums">{{ format(item.subtotal) }}</p>
                                </div>
                            </div>
                            <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                                <div>
                                    <p class="text-base-content/50">Qty</p>
                                    <p class="font-medium tabular-nums">{{ item.qty }}</p>
                                </div>
                                <div>
                                    <p class="text-base-content/50">Satuan</p>
                                    <p class="font-medium">{{ item.uom }}</p>
                                </div>
                                <div class="col-span-2">
                                    <p class="text-base-content/50">Harga Satuan</p>
                                    <p class="font-medium tabular-nums">{{ format(item.unit_price) }}</p>
                                </div>
                            </div>
                        </article>
                        <div v-if="!items.length" class="rounded-xl border border-base-200 px-4 py-10 text-center text-base-content/50">
                            Belum ada item pada RAB ini.
                        </div>
                        <div v-if="items.length" class="rounded-xl border border-base-200 bg-base-200/40 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-base-content/55">Total Penawaran</p>
                            <p class="mt-2 text-lg font-bold tabular-nums">{{ format(total) }}</p>
                        </div>
                    </div>

                    <div class="hidden overflow-x-auto rounded-xl border border-base-200 sm:block">
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
                </div>

                <p class="text-xs text-base-content/50 print:text-[10px]">
                    Penawaran ini disusun berdasarkan kebutuhan project. Harga belum termasuk revisi di luar scope tanpa persetujuan tertulis.
                </p>
            </div>
        </main>
    </div>
</template>

<style scoped>
.customer-rab-shell,
.customer-rab-shell button,
.customer-rab-shell a,
.customer-rab-shell table {
    font-family: "DejaVu Sans", Arial, sans-serif;
}

@media print {
    .customer-rab-shell {
        background: white;
    }
}
</style>
