<script setup>
import { ref } from 'vue';

defineProps({
    id: { type: String, required: true },
    title: { type: String, default: 'Konfirmasi' },
    message: { type: String, default: 'Apakah Anda yakin?' },
    confirmText: { type: String, default: 'Hapus' },
    confirmClass: { type: String, default: 'btn-error' },
});

const emit = defineEmits(['confirm']);
const dialogRef = ref(null);

const closeModal = () => {
    dialogRef.value?.close();
};

const confirmAction = () => {
    closeModal();
    emit('confirm');
};
</script>

<template>
    <dialog :id="id" ref="dialogRef" class="modal">
        <div class="modal-box">
            <h3 class="font-bold text-lg">{{ title }}</h3>
            <p class="py-4 text-base-content/70">{{ message }}</p>
            <div class="modal-action">
                <form method="dialog">
                    <button class="btn btn-ghost">Batal</button>
                </form>
                <button type="button" :class="['btn', confirmClass]" @click="confirmAction">
                    {{ confirmText }}
                </button>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop"><button>close</button></form>
    </dialog>
</template>
