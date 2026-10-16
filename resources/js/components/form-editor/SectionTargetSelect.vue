<script setup lang="ts">
import type { SectionTarget } from '@/types/quiz';
import { NEXT_SECTION, SUBMIT_FORM } from '@/utils/section-flow';

/*
 * Where a section, or an answer, sends the student: the next section, another section or the end
 * of the form. Like the "Setelah bagian" and "Buka bagian berdasarkan jawaban" menus of Google Forms.
 */
const props = defineProps<{
    modelValue?: SectionTarget | null;
    choices: { value: SectionTarget; label: string }[];
    label: string;
}>();

const emit = defineEmits<{ 'update:modelValue': [value: SectionTarget] }>();

const parse = (value: string): SectionTarget => (value === NEXT_SECTION || value === SUBMIT_FORM ? value : Number(value));

const current = () => {
    const value = String(props.modelValue ?? NEXT_SECTION);

    return props.choices.some((choice) => String(choice.value) === value) ? value : NEXT_SECTION;
};
</script>

<template>
    <select
        :value="current()"
        :aria-label="label"
        :title="label"
        class="min-w-0 max-w-full truncate rounded-lg border border-slate-300 bg-white py-1.5 pl-2.5 pr-8 text-sm text-slate-700 transition hover:border-indigo-300 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-100"
        @click.stop
        @change="emit('update:modelValue', parse(($event.target as HTMLSelectElement).value))"
    >
        <option v-for="choice in choices" :key="String(choice.value)" :value="String(choice.value)">{{ choice.label }}</option>
    </select>
</template>
