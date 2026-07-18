<script setup>
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ canResetPassword: Boolean, status: String, isDevMode: Boolean });

const form = useForm({ email: '', password: '', remember: false });
const devSeedForm = useForm({});
const submit = () => form.post(route('login'));
const devLoading = ref(false);
const devMessage = ref('');
const devError = ref('');

const fillDevLogin = async () => {
    if (!props.isDevMode) return;

    devLoading.value = true;
    devMessage.value = '';
    devError.value = '';

    devSeedForm.post(route('dev.seed-login'), {
        preserveScroll: true,
        onSuccess: (page) => {
            const payload = page?.props?.devLoginSeed;

            if (!payload) {
                devError.value = 'Gagal mengisi kredensial dev.';
                return;
            }

            form.email = 'mbx@ocnetworks.web.id' ?? '';
            form.password = 'Nopassword123!' ?? '';
            devMessage.value = payload.message ?? 'Kredensial dev diisi.';
        },
        onError: () => {
            devError.value = 'Gagal mengisi kredensial dev.';
        },
        onFinish: () => {
            devLoading.value = false;
        },
    });
};
</script>

<template>
    <div class="min-h-screen ocn-shell flex items-center justify-center p-4">
        <div class="grid w-full max-w-5xl overflow-hidden rounded-3xl bg-base-100 shadow-2xl ring-1 ring-slate-200 lg:grid-cols-2">
            <div class="hidden lg:flex flex-col justify-between bg-[#08111f] p-10 text-white">
                <div>
                    <div class="w-12 h-12 ocn-brand-mark rounded-2xl flex items-center justify-center mb-8">
                        <span class="font-black">OCN</span>
                    </div>
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-cyan-200/80">ERP OCN System</p>
                    <h2 class="mt-3 text-4xl font-bold tracking-tight leading-tight">Satu sistem kerja untuk operasional harian.</h2>
                    <p class="mt-4 text-sm text-slate-300/85 leading-relaxed">
                        Kelola sales, project, inventory, dan administrasi dari satu dashboard dengan data yang sinkron.
                    </p>
                </div>
                <div class="grid grid-cols-3 gap-3 text-center">
                    <div class="rounded-2xl bg-white/8 p-4 ring-1 ring-white/10">
                        <p class="text-xl font-bold tracking-tight">ERP</p>
                        <p class="text-xs text-slate-300/80">Terintegrasi</p>
                    </div>
                    <div class="rounded-2xl bg-white/8 p-4 ring-1 ring-white/10">
                        <p class="text-xl font-bold tracking-tight">Real-time</p>
                        <p class="text-xs text-slate-300/80">Monitoring</p>
                    </div>
                    <div class="rounded-2xl bg-white/8 p-4 ring-1 ring-white/10">
                        <p class="text-xl font-bold tracking-tight">Multi Modul</p>
                        <p class="text-xs text-slate-300/80">Satu Akses</p>
                    </div>
                </div>
            </div>

            <div class="p-8 sm:p-10">
                <!-- Logo -->
                <div class="flex flex-col items-start gap-2 mb-8">
                    <div class="w-12 h-12 ocn-brand-mark rounded-xl flex items-center justify-center lg:hidden">
                        <span class="text-primary-content font-bold text-lg">OCN</span>
                    </div>
                    <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary/70">Sign In</p>
                    <h1 class="text-3xl font-bold tracking-tight leading-tight">Masuk ke ERP OCN System</h1>
                    <p class="text-sm leading-relaxed text-base-content/70">Gunakan akun yang sudah terdaftar untuk melanjutkan pekerjaan Anda.</p>
                </div>

                <div v-if="status" class="alert alert-success text-sm mb-3">{{ status }}</div>
                <div v-if="isDevMode && devMessage" class="alert alert-info text-sm mb-3">{{ devMessage }}</div>
                <div v-if="isDevMode && devError" class="alert alert-error text-sm mb-3">{{ devError }}</div>

                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label class="label"><span class="label-text text-sm font-semibold">Email</span></label>
                        <input
                            v-model="form.email"
                            type="email"
                            autocomplete="username"
                            class="input input-bordered w-full"
                            :class="form.errors.email ? 'input-error' : ''"
                            placeholder="admin@ocn.test"
                        />
                        <p v-if="form.errors.email" class="text-error text-xs mt-1">{{ form.errors.email }}</p>
                    </div>

                    <div>
                        <label class="label"><span class="label-text text-sm font-semibold">Password</span></label>
                        <input
                            v-model="form.password"
                            type="password"
                            autocomplete="current-password"
                            class="input input-bordered w-full"
                            :class="form.errors.password ? 'input-error' : ''"
                        />
                        <p v-if="form.errors.password" class="text-error text-xs mt-1">{{ form.errors.password }}</p>
                    </div>

                    <div class="flex items-center justify-between">
                        <label class="label cursor-pointer gap-2">
                            <input v-model="form.remember" type="checkbox" class="checkbox checkbox-sm" />
                            <span class="label-text text-sm text-base-content/80">Ingat saya</span>
                        </label>
                    </div>

                    <button type="submit" class="btn btn-primary w-full" :disabled="form.processing">
                        <span v-if="form.processing" class="loading loading-spinner loading-sm" />
                        Masuk
                    </button>

                    <button
                        v-if="isDevMode"
                        type="button"
                        class="btn btn-outline w-full"
                        :disabled="devLoading"
                        @click="fillDevLogin"
                    >
                        <span v-if="devLoading" class="loading loading-spinner loading-sm" />
                        Fill Credential Dev
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>
