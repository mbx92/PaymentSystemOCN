<script setup>
import { ref, watch } from 'vue';
import { useCurrency } from '@/composables/useCurrency';

const props = defineProps({
    modelValue: { type: [Number, String], default: 0 },
    label: String,
    error: String,
    required: Boolean,
    placeholder: { type: String, default: '0' },
});

const emit = defineEmits(['update:modelValue']);
const { parse, formatInput } = useCurrency();

const display = ref(props.modelValue ? formatInput(props.modelValue) : '');

watch(() => props.modelValue, (val) => {
    const current = parse(display.value);
    if (current !== Number(val)) {
        display.value = val ? formatInput(val) : '';
    }
});

const onInput = (e) => {
    const raw = e.target.value.replace(/[^\d]/g, '');
    const num = parseInt(raw, 10) || 0;
    display.value = num.toLocaleString('id-ID');
    e.target.value = display.value;
    emit('update:modelValue', num);
};
</script>

<template>
    <div>
        <label v-if="label" class="label">
            <span class="label-text font-medium">{{ label }}<span v-if="required" class="text-error ml-1">*</span></span>
        </label>
        <label class="input validator w-full" :class="error ? 'input-error' : ''">
            <span class="text-base-content/50 text-sm">Rp</span>
            <input
                type="text"
                inputmode="numeric"
                :value="display"
                :placeholder="placeholder"
                class="grow text-right"
                @input="onInput"
            />
        </label>
        <p v-if="error" class="text-error text-xs mt-1">{{ error }}</p>
    </div>
</template>
