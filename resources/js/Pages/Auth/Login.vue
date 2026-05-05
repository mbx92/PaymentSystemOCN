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

const seedAndFillDevLogin = async () => {
    if (!props.isDevMode) return;

    devLoading.value = true;
    devMessage.value = '';
    devError.value = '';

    devSeedForm.post(route('dev.seed-login'), {
        preserveScroll: true,
        onSuccess: (page) => {
            const payload = page?.props?.devLoginSeed;

            if (!payload) {
                devError.value = 'Seeder selesai, tetapi respons login dev tidak ditemukan.';
                return;
            }

            form.email = payload.email ?? '';
            form.password = payload.password ?? '';
            devMessage.value = payload.message ?? 'Seeder berhasil dijalankan.';
        },
        onError: () => {
            devError.value = 'Gagal menjalankan seeder.';
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
                    <h2 class="text-4xl font-black tracking-tight leading-tight">Pembukuan tegas untuk project software.</h2>
                    <p class="mt-4 text-slate-400 leading-relaxed">
                        Pantau kas masuk, kas keluar, termin, profit, dan pembagian tim dalam satu dashboard yang rapi.
                    </p>
                </div>
                <div class="grid grid-cols-3 gap-3 text-center">
                    <div class="rounded-2xl bg-white/8 p-4 ring-1 ring-white/10">
                        <p class="text-xl font-black">3</p>
                        <p class="text-xs text-slate-400">Role</p>
                    </div>
                    <div class="rounded-2xl bg-white/8 p-4 ring-1 ring-white/10">
                        <p class="text-xl font-black">100%</p>
                        <p class="text-xs text-slate-400">Transparan</p>
                    </div>
                    <div class="rounded-2xl bg-white/8 p-4 ring-1 ring-white/10">
                        <p class="text-xl font-black">IDR</p>
                        <p class="text-xs text-slate-400">Rupiah</p>
                    </div>
                </div>
            </div>

            <div class="p-8 sm:p-10">
                <!-- Logo -->
                <div class="flex flex-col items-start gap-2 mb-8">
                    <div class="w-12 h-12 ocn-brand-mark rounded-xl flex items-center justify-center lg:hidden">
                        <span class="text-primary-content font-bold text-lg">OCN</span>
                    </div>
                    <h1 class="text-2xl font-black tracking-tight">Masuk ke Pembukuan OCN</h1>
                    <p class="text-sm text-base-content/60">Gunakan akun yang sudah terdaftar untuk melanjutkan.</p>
                </div>

                <div v-if="status" class="alert alert-success text-sm mb-3">{{ status }}</div>
                <div v-if="isDevMode && devMessage" class="alert alert-info text-sm mb-3">{{ devMessage }}</div>
                <div v-if="isDevMode && devError" class="alert alert-error text-sm mb-3">{{ devError }}</div>

                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label class="label"><span class="label-text font-medium">Email</span></label>
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
                        <label class="label"><span class="label-text font-medium">Password</span></label>
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
                            <span class="label-text">Ingat saya</span>
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
                        @click="seedAndFillDevLogin"
                    >
                        <span v-if="devLoading" class="loading loading-spinner loading-sm" />
                        Seed DB + Isi Login Dev
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>
