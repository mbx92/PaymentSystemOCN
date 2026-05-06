<script setup>
defineProps({ status: String });

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
    active: 'badge-success',
    admin:     'badge-error',
    manajer:   'badge-warning',
    anggota:   'badge-success',
};
</script>

<template>
    <span :class="['badge badge-sm font-medium capitalize', map[status] ?? 'badge-ghost']">
        {{ status }}
    </span>
</template>
