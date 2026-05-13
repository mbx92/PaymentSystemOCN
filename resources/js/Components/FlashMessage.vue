<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { CheckCircleIcon, ExclamationCircleIcon, XMarkIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ flash: Object });
const page = usePage();
const visible = ref(false);
const localAlert = ref(null);
let hideTimer = null;
let lastErrorKey = '';

const currentAlert = computed(() => localAlert.value ?? props.flash);
const validationErrors = computed(() => page.props.errors ?? {});

const hideWithDelay = () => {
    clearTimeout(hideTimer);
    hideTimer = setTimeout(() => { visible.value = false; }, 4000);
};

const showAlert = (payload) => {
    localAlert.value = payload;
    visible.value = true;
    hideWithDelay();
};

watch(() => props.flash, (val) => {
    if (val) {
        localAlert.value = null;
        visible.value = true;
        hideWithDelay();
    }
}, { immediate: true });

watch(validationErrors, (errors) => {
    const entries = Object.entries(errors ?? {});
    if (!entries.length) {
        lastErrorKey = '';
        return;
    }

    const [field, value] = entries[0];
    const message = Array.isArray(value) ? value[0] : value;
    const errorKey = `${field}:${message}`;

    if (!message || errorKey === lastErrorKey) return;

    lastErrorKey = errorKey;
    showAlert({
        type: 'error',
        message,
    });
}, { deep: true });

const onGlobalAlert = (event) => {
    const detail = event?.detail ?? {};
    if (!detail?.message) return;
    showAlert({
        type: detail.type ?? 'info',
        message: detail.message,
    });
};

onMounted(() => {
    window.addEventListener('ocn:alert', onGlobalAlert);
});

onBeforeUnmount(() => {
    window.removeEventListener('ocn:alert', onGlobalAlert);
    clearTimeout(hideTimer);
});

const typeClass = {
    success: 'alert-success',
    error:   'alert-error',
    warning: 'alert-warning',
    info:    'alert-info',
};
</script>

<template>
    <Transition name="slide-down">
        <div v-if="visible && currentAlert" class="fixed top-4 right-4 z-50 max-w-sm w-full">
            <div :class="['alert shadow-lg', typeClass[currentAlert.type] ?? 'alert-info']">
                <CheckCircleIcon v-if="currentAlert.type === 'success'" class="w-5 h-5 shrink-0" />
                <ExclamationCircleIcon v-else class="w-5 h-5 shrink-0" />
                <span>{{ currentAlert.message }}</span>
                <button class="btn btn-ghost btn-xs ml-auto" @click="visible = false">
                    <XMarkIcon class="w-4 h-4" />
                </button>
            </div>
        </div>
    </Transition>
</template>

<style scoped>
.slide-down-enter-active, .slide-down-leave-active { transition: all 0.3s ease; }
.slide-down-enter-from { opacity: 0; transform: translateY(-1rem); }
.slide-down-leave-to   { opacity: 0; transform: translateY(-1rem); }
</style>
