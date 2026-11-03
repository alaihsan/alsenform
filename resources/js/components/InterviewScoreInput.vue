<script setup lang="ts">
import { computed } from 'vue';

/*
 * Mode pewawancara, "Skor diisi pewawancara": the score the candidate earns for a choice made, from
 * 0 up to the maximum of that choice, with a decimal comma or point (4,5 or 4.5).
 */
const props = defineProps<{
    modelValue: string;
    maximum: number;
    /** What the score is for, read by screen readers: the question and the choice. */
    label: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

/** A typed score as a number (decimal comma or point), or null when it is not one. */
const parseScore = (value: string): number | null => {
    const text = value.trim().replace(',', '.');

    return /^\d+(\.\d+)?$/.test(text) ? Number(text) : null;
};

const problem = computed<string | null>(() => {
    if (props.modelValue.trim() === '') {
        return 'Isi skornya.';
    }
    const score = parseScore(props.modelValue);

    return score === null || score > props.maximum ? `Skor harus angka 0 sampai ${props.maximum.toLocaleString('id-ID')}.` : null;
});
</script>

<template>
    <div
        class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 rounded-2xl border border-indigo-100 bg-white px-3.5 py-2.5"
        data-testid="interview-score"
    >
        <label class="flex items-center gap-2 text-sm font-bold text-slate-700">
            Skor
            <input
                type="text"
                inputmode="decimal"
                autocomplete="off"
                :value="modelValue"
                :aria-label="label"
                :aria-invalid="problem !== null"
                :placeholder="`0–${maximum.toLocaleString('id-ID')}`"
                :class="[
                    'h-10 w-24 rounded-xl border px-3 text-center text-base font-black text-slate-900 outline-none focus:ring-2',
                    problem && modelValue.trim() !== ''
                        ? 'border-rose-400 focus:ring-rose-200'
                        : 'border-slate-300 focus:border-indigo-500 focus:ring-indigo-100',
                ]"
                @click.stop
                @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)"
            />
            <span class="font-semibold text-slate-500">/ {{ maximum.toLocaleString('id-ID') }}</span>
        </label>
        <p v-if="problem" :class="['text-xs font-semibold', modelValue.trim() === '' ? 'text-amber-700' : 'text-rose-600']">{{ problem }}</p>
    </div>
</template>
