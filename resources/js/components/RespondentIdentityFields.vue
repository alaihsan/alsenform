<script setup lang="ts">
/**
 * The identity a guest gives on a public quiz: the fields the teacher picked when publishing
 * (Nama Lengkap, Kelas, Email). Shown on the confirmation screen, or above a survey.
 */
const name = defineModel<string>('name', { default: '' });
const kelas = defineModel<string>('kelas', { default: '' });
const email = defineModel<string>('email', { default: '' });

defineProps<{
    fields: string[];
    classes: string[];
    errors: Record<string, string>;
    idPrefix: string;
}>();

const inputClass = (hasError: boolean): string[] => [
    'mt-1.5 w-full rounded-2xl border bg-white px-4 py-3 text-base text-slate-900 outline-none transition focus:ring-2 sm:text-sm',
    hasError ? 'border-red-400 focus:border-red-500 focus:ring-red-500/20' : 'border-slate-300 focus:border-indigo-500 focus:ring-indigo-500/20',
];
</script>

<template>
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <label v-if="fields.includes('name')" :for="`${idPrefix}-name`" class="block sm:col-span-2">
            <span class="text-sm font-bold text-slate-800">Nama Lengkap <span class="text-red-500">*</span></span>
            <input
                :id="`${idPrefix}-name`"
                v-model="name"
                type="text"
                autocomplete="name"
                maxlength="100"
                placeholder="Tulis nama lengkap Anda"
                :aria-invalid="Boolean(errors.name)"
                :class="inputClass(Boolean(errors.name))"
            />
            <span v-if="errors.name" class="mt-1 block text-xs font-semibold text-red-600">{{ errors.name }}</span>
        </label>

        <label v-if="fields.includes('class')" :for="`${idPrefix}-class`" class="block">
            <span class="text-sm font-bold text-slate-800">Kelas <span class="text-red-500">*</span></span>
            <select :id="`${idPrefix}-class`" v-model="kelas" :aria-invalid="Boolean(errors.kelas)" :class="inputClass(Boolean(errors.kelas))">
                <option value="" disabled>Pilih kelas</option>
                <option v-for="option in classes" :key="option" :value="option">{{ option }}</option>
            </select>
            <span v-if="errors.kelas" class="mt-1 block text-xs font-semibold text-red-600">{{ errors.kelas }}</span>
        </label>

        <label v-if="fields.includes('email')" :for="`${idPrefix}-email`" :class="['block', fields.includes('class') ? '' : 'sm:col-span-2']">
            <span class="text-sm font-bold text-slate-800">Email <span class="text-red-500">*</span></span>
            <input
                :id="`${idPrefix}-email`"
                v-model="email"
                type="email"
                autocomplete="email"
                inputmode="email"
                maxlength="255"
                placeholder="nama@contoh.com"
                :aria-invalid="Boolean(errors.email)"
                :class="inputClass(Boolean(errors.email))"
            />
            <span v-if="errors.email" class="mt-1 block text-xs font-semibold text-red-600">{{ errors.email }}</span>
        </label>
    </div>
</template>
