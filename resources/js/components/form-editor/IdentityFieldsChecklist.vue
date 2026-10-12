<script setup lang="ts">
import { Check } from 'lucide-vue-next';

export interface IdentityChoice {
    name: boolean;
    class: boolean;
    email: boolean;
}

/**
 * What guests of a public quiz fill in on the confirmation screen before they start.
 */
const choice = defineModel<IdentityChoice>({ required: true });

const options: { key: keyof IdentityChoice; label: string; hint: string }[] = [
    { key: 'name', label: 'Nama Lengkap', hint: 'Diketik peserta. Tampil di rekap nilai, Excel, dan laporan.' },
    { key: 'class', label: 'Kelas', hint: 'Dipilih dari daftar 7A sampai 9D.' },
    { key: 'email', label: 'Email', hint: 'Alamat email peserta.' },
];

const toggle = (key: keyof IdentityChoice): void => {
    choice.value = { ...choice.value, [key]: !choice.value[key] };
};
</script>

<template>
    <div class="grid grid-cols-1 gap-2.5">
        <button
            v-for="option in options"
            :key="option.key"
            type="button"
            role="checkbox"
            :aria-checked="choice[option.key]"
            :class="[
                'flex items-start gap-3 rounded-2xl border p-3.5 text-left transition',
                choice[option.key] ? 'border-indigo-500 bg-indigo-50/60 ring-2 ring-indigo-500/15' : 'border-slate-200 bg-white hover:bg-slate-50',
            ]"
            @click="toggle(option.key)"
        >
            <span
                :class="[
                    'mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-md border',
                    choice[option.key] ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-slate-300 bg-white',
                ]"
            >
                <Check v-if="choice[option.key]" class="h-3.5 w-3.5 stroke-[3]" />
            </span>
            <span class="min-w-0">
                <span class="block text-sm font-bold text-slate-900">{{ option.label }}</span>
                <span class="block text-xs text-slate-500">{{ option.hint }}</span>
            </span>
        </button>
    </div>
</template>
