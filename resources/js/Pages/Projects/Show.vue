<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import CurrencyInput from '@/Components/CurrencyInput.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useCurrency } from '@/composables/useCurrency';

const props = defineProps({ project: Object });
const { format } = useCurrency();

const activeTab = ref('info');

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

// Cash forms
const cashInForm = useForm({ project_id: props.project.id, category: 'pendapatan_jasa', amount: 0, date: new Date().toISOString().slice(0, 10), note: '' });
const cashOutForm = useForm({ project_id: props.project.id, category: 'biaya_tim', amount: 0, date: new Date().toISOString().slice(0, 10), note: '', recipient_name: '' });

const submitCashIn = () => cashInForm.post(route('cash-in.store'), { onSuccess: () => { cashInForm.reset('amount', 'note'); document.getElementById('modal-cash-in').close(); } });
const submitCashOut = () => cashOutForm.post(route('cash-out.store'), { onSuccess: () => { cashOutForm.reset('amount', 'note', 'recipient_name'); document.getElementById('modal-cash-out').close(); } });

// Delete project
const deleteProject = () => {
    router.delete(route('projects.destroy', props.project.id));
};
</script>

<template>
    <AppLayout>
        <div class="space-y-5">
            <!-- Header -->
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <Link :href="route('projects.index')" class="btn btn-ghost btn-xs">← Projects</Link>
                    </div>
                    <h1 class="text-2xl font-bold">{{ project.name }}</h1>
                    <p class="text-base-content/60">{{ project.client_name }}</p>
                </div>
                <div class="flex gap-2">
                    <StatusBadge :status="project.status" />
                    <Link :href="route('projects.edit', project.id)" class="btn btn-outline btn-sm">Edit</Link>
                    <button class="btn btn-error btn-outline btn-sm" onclick="document.getElementById('modal-delete-project').showModal()">Hapus</button>
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
                <button :class="['tab', activeTab === 'kas' ? 'tab-active' : '']" @click="activeTab = 'kas'">Kas Masuk / Keluar</button>
                <button :class="['tab', activeTab === 'tim' ? 'tab-active' : '']" @click="activeTab = 'tim'">Tim & Referral</button>
            </div>

            <!-- Tab: Info -->
            <div v-if="activeTab === 'info'" class="space-y-4">
                <div class="card bg-base-100 shadow">
                    <div class="card-body">
                        <h2 class="card-title text-base">Detail Project</h2>
                        <div class="grid grid-cols-2 gap-2 text-sm">
                            <div class="text-base-content/60">Kontak Klien</div><div>{{ project.client_contact ?? '-' }}</div>
                            <div class="text-base-content/60">Tanggal Mulai</div><div>{{ project.started_at ?? '-' }}</div>
                            <div class="text-base-content/60">Tanggal Selesai</div><div>{{ project.finished_at ?? '-' }}</div>
                            <div class="text-base-content/60">Deskripsi</div><div>{{ project.description ?? '-' }}</div>
                        </div>
                    </div>
                </div>

                <div class="card bg-base-100 shadow">
                    <div class="card-body">
                        <h2 class="card-title text-base">Termin Pembayaran</h2>
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
            </div>

            <!-- Tab: Kas -->
            <div v-if="activeTab === 'kas'" class="space-y-4">
                <div class="flex gap-2">
                    <button class="btn btn-success btn-sm" onclick="document.getElementById('modal-cash-in').showModal()">+ Kas Masuk</button>
                    <button class="btn btn-error btn-sm" onclick="document.getElementById('modal-cash-out').showModal()">+ Kas Keluar</button>
                </div>

                <div class="card bg-base-100 shadow">
                    <div class="card-body p-0">
                        <div class="p-4 border-b border-base-300"><h2 class="font-semibold text-success">Kas Masuk</h2></div>
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

                <div class="card bg-base-100 shadow">
                    <div class="card-body p-0">
                        <div class="p-4 border-b border-base-300"><h2 class="font-semibold text-error">Kas Keluar</h2></div>
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
                <div class="card bg-base-100 shadow">
                    <div class="card-body">
                        <div class="flex items-center justify-between mb-3">
                            <h2 class="card-title text-base">Pembagian Tim</h2>
                            <Link :href="route('team-distribution.calculator') + '?project_id=' + project.id" class="btn btn-primary btn-sm">
                                Kalkulator
                            </Link>
                        </div>
                        <table class="table table-sm">
                            <thead><tr><th>Nama</th><th>Peran</th><th>%</th><th>Base Pay</th><th>Bonus</th><th>Total</th></tr></thead>
                            <tbody>
                                <tr v-for="d in project.team_distributions" :key="d.id">
                                    <td class="font-medium">{{ d.user_name }}</td>
                                    <td class="capitalize">{{ d.role_in_project }}</td>
                                    <td>{{ d.percentage }}%</td>
                                    <td>{{ format(d.base_pay) }}</td>
                                    <td>{{ format(d.bonus) }}</td>
                                    <td class="font-semibold text-primary">{{ format(d.total_pay) }}</td>
                                </tr>
                                <tr v-if="!project.team_distributions.length"><td colspan="6" class="text-center py-6 text-base-content/50">Belum ada pembagian tim</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card bg-base-100 shadow">
                    <div class="card-body">
                        <h2 class="card-title text-base">Komisi Referral</h2>
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

        <!-- Modal: Delete Project -->
        <ConfirmModal
            id="modal-delete-project"
            title="Hapus Project"
            :message="`Apakah Anda yakin ingin menghapus project '${project.name}'? Data akan dihapus sementara (soft delete).`"
            @confirm="deleteProject"
        />
    </AppLayout>
</template>
