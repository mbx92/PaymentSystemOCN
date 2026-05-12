<script setup>
const props = defineProps({
    status: String,
    size: { type: String, default: 'badge-sm' },
});

const map = {
    negosiasi: 'badge-warning',
    berjalan:  'badge-info',
    selesai:   'badge-success',
    dibatalkan:'badge-error',
    draft: 'badge-ghost',
    submitted: 'badge-info',
    approved: 'badge-warning',
    posted: 'badge-success',
    void: 'badge-error',
    paid: 'badge-success',
    refunded: 'badge-error',
    reopened: 'badge-info',
    partial: 'badge-warning',
    unpaid: 'badge-ghost',
    active: 'badge-success',
    admin:     'badge-error',
    manajer:   'badge-warning',
    anggota:   'badge-success',
};
</script>

<template>
    <span :class="['badge font-medium capitalize', props.size, map[status] ?? 'badge-ghost']">
        {{ status }}
    </span>
</template>
