<script setup>
import { ref, watch } from 'vue';
import { CheckCircleIcon, ExclamationCircleIcon, XMarkIcon } from '@heroicons/vue/24/outline';

const props = defineProps({ flash: Object });
const visible = ref(false);

watch(() => props.flash, (val) => {
    if (val) {
        visible.value = true;
        setTimeout(() => { visible.value = false; }, 4000);
    }
}, { immediate: true });

const typeClass = {
    success: 'alert-success',
    error:   'alert-error',
    warning: 'alert-warning',
    info:    'alert-info',
};
</script>

<template>
    <Transition name="slide-down">
        <div v-if="visible && flash" class="fixed top-4 right-4 z-50 max-w-sm w-full">
            <div :class="['alert shadow-lg', typeClass[flash.type] ?? 'alert-info']">
                <CheckCircleIcon v-if="flash.type === 'success'" class="w-5 h-5 shrink-0" />
                <ExclamationCircleIcon v-else class="w-5 h-5 shrink-0" />
                <span>{{ flash.message }}</span>
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
