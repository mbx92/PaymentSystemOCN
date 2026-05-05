<script setup>
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import CurrencyInput from '@/Components/CurrencyInput.vue';
import { useForm, Link } from '@inertiajs/vue3';
import { PlusIcon, TrashIcon } from '@heroicons/vue/24/outline';
import { useCurrency } from '@/composables/useCurrency';

const { format } = useCurrency();

const form = useForm({
    name: '',
    client_name: '',
    client_contact: '',
    total_value: 0,
    status: 'negosiasi',
    started_at: '',
    finished_at: '',
    description: '',
    payments: [
        { percentage: 30, note: '' },
        { percentage: 40, note: '' },
        { percentage: 30, note: '' },
    ],
});

/** Preview jumlah per termin (sama logika dengan server: pembulatan per baris, sisa ke termin terakhir) */
const previewAmounts = computed(() => {
    const tv = Number(form.total_value) || 0;
    const rows = form.payments;
    const n = rows.length;
    if (!n || !tv) return rows.map(() => 0);

    let assigned = 0;
    return rows.map((row, i) => {
        const pct = Number(row.percentage) || 0;
        if (i === n - 1) {
            return Math.round((tv - assigned) * 100) / 100;
        }
        const amt = Math.round(tv * (pct / 100) * 100) / 100;
        assigned += amt;
        return amt;
    });
});

const totalPercent = computed(() =>
    form.payments.reduce((s, row) => s + (Number(row.percentage) || 0), 0),
);

const percentOk = computed(() => Math.abs(totalPercent.value - 100) < 0.02);

const addTerm = () => {
    form.payments.push({ percentage: 0, note: '' });
};

const removeTerm = (index) => {
    if (form.payments.length <= 1) return;
    form.payments.splice(index, 1);
};

const submit = () => form.post(route('projects.store'));
</script>

<template>
    <AppLayout>
        <div class="max-w-3xl">
            <div class="flex items-center gap-3 mb-6">
                <Link :href="route('projects.index')" class="btn btn-ghost btn-sm">← Kembali</Link>
                <h1 class="text-2xl font-bold">Tambah Project Baru</h1>
            </div>

            <div class="card bg-base-100 shadow">
                <div class="card-body space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2">
                            <label class="label"><span class="label-text font-medium">Nama Project <span class="text-error">*</span></span></label>
                            <input v-model="form.name" type="text" class="input input-bordered w-full" :class="form.errors.name ? 'input-error' : ''" placeholder="Sistem Pembukuan XYZ" />
                            <p v-if="form.errors.name" class="text-error text-xs mt-1">{{ form.errors.name }}</p>
                        </div>

                        <div>
                            <label class="label"><span class="label-text font-medium">Nama Klien <span class="text-error">*</span></span></label>
                            <input v-model="form.client_name" type="text" class="input input-bordered w-full" :class="form.errors.client_name ? 'input-error' : ''" />
                            <p v-if="form.errors.client_name" class="text-error text-xs mt-1">{{ form.errors.client_name }}</p>
                        </div>

                        <div>
                            <label class="label"><span class="label-text font-medium">Kontak Klien</span></label>
                            <input v-model="form.client_contact" type="text" class="input input-bordered w-full" placeholder="08xx / email" />
                        </div>

                        <div>
                            <CurrencyInput v-model="form.total_value" label="Nilai Kontrak" :required="true" :error="form.errors.total_value" />
                        </div>

                        <div>
                            <label class="label"><span class="label-text font-medium">Status <span class="text-error">*</span></span></label>
                            <select v-model="form.status" class="select select-bordered w-full">
                                <option value="negosiasi">Negosiasi</option>
                                <option value="berjalan">Berjalan</option>
                                <option value="selesai">Selesai</option>
                                <option value="dibatalkan">Dibatalkan</option>
                            </select>
                        </div>

                        <div>
                            <label class="label"><span class="label-text font-medium">Tanggal Mulai</span></label>
                            <input v-model="form.started_at" type="date" class="input input-bordered w-full" />
                        </div>
                        <div>
                            <label class="label"><span class="label-text font-medium">Tanggal Selesai</span></label>
                            <input v-model="form.finished_at" type="date" class="input input-bordered w-full" />
                            <p v-if="form.errors.finished_at" class="text-error text-xs mt-1">{{ form.errors.finished_at }}</p>
                        </div>

                        <div class="sm:col-span-2">
                            <label class="label"><span class="label-text font-medium">Deskripsi</span></label>
                            <textarea v-model="form.description" class="textarea textarea-bordered w-full" rows="3" />
                        </div>
                    </div>

                    <!-- Termin pembayaran manual -->
                    <div class="divider">Termin pembayaran</div>

                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <p class="text-sm font-semibold">Atur termin secara manual</p>
                            <p class="text-xs text-base-content/60">Total persentase harus 100%. Jumlah per termin dihitung otomatis dari nilai kontrak.</p>
                        </div>
                        <button type="button" class="btn btn-outline btn-sm gap-1" @click="addTerm">
                            <PlusIcon class="w-4 h-4" /> Tambah termin
                        </button>
                    </div>

                    <div v-if="form.errors.payments" class="alert alert-error text-sm">
                        {{ typeof form.errors.payments === 'string' ? form.errors.payments : form.errors.payments[0] }}
                    </div>

                    <div class="overflow-x-auto rounded-xl border border-base-300">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th class="w-12">#</th>
                                    <th>Persentase (%)</th>
                                    <th>Jumlah (preview)</th>
                                    <th>Catatan</th>
                                    <th class="w-12" />
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(row, index) in form.payments" :key="index">
                                    <td class="font-mono text-base-content/70">{{ index + 1 }}</td>
                                    <td>
                                        <input
                                            v-model.number="row.percentage"
                                            type="number"
                                            min="0"
                                            max="100"
                                            step="0.5"
                                            class="input input-bordered input-sm w-full max-w-[8rem]"
                                            :class="form.errors[`payments.${index}.percentage`] ? 'input-error' : ''"
                                        />
                                        <p v-if="form.errors[`payments.${index}.percentage`]" class="text-error text-xs mt-1">
                                            {{ form.errors[`payments.${index}.percentage`] }}
                                        </p>
                                    </td>
                                    <td class="font-semibold whitespace-nowrap">{{ format(previewAmounts[index]) }}</td>
                                    <td>
                                        <input v-model="row.note" type="text" class="input input-bordered input-sm w-full" placeholder="Opsional" />
                                    </td>
                                    <td>
                                        <button
                                            type="button"
                                            class="btn btn-ghost btn-xs text-error"
                                            :disabled="form.payments.length <= 1"
                                            @click="removeTerm(index)"
                                        >
                                            <TrashIcon class="w-4 h-4" />
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                        <span>
                            Total persentase:
                            <strong :class="percentOk ? 'text-success' : 'text-error'">{{ totalPercent.toFixed(2) }}%</strong>
                            <span v-if="!percentOk" class="text-error"> — harus 100%</span>
                        </span>
                        <span class="text-base-content/60">
                            Total preview: <strong>{{ format(previewAmounts.reduce((a, b) => a + b, 0)) }}</strong>
                        </span>
                    </div>

                    <div class="flex justify-end gap-3">
                        <Link :href="route('projects.index')" class="btn btn-ghost">Batal</Link>
                        <button class="btn btn-primary" :disabled="form.processing || !percentOk" @click="submit">
                            <span v-if="form.processing" class="loading loading-spinner loading-sm" />
                            Simpan Project
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
