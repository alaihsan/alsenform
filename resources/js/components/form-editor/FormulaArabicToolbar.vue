<script setup lang="ts">
import FormulaPalette from '@/components/form-editor/FormulaPalette.vue';
import type { FormulaPalette as Palette } from '@/lib/formulas';
import { AlignRight, Puzzle } from 'lucide-vue-next';
import { computed, ref } from 'vue';

/*
 * The toolbar above a question: a button per formula add-on the teacher has installed (Rumus
 * Matematika, Rumus IPA), each opening its catalogue, and the right-to-left switch for Arabic.
 */
const props = defineProps<{
    palettes: Palette[];
}>();

const emit = defineEmits<{
    (e: 'insert', snippet: string): void;
    (e: 'toggle-rtl'): void;
    (e: 'browse-addons'): void;
}>();

const openKey = ref<string | null>(null);
const openPalette = computed(() => props.palettes.find((palette) => palette.key === openKey.value) ?? null);

const toggle = (palette: Palette) => {
    openKey.value = openKey.value === palette.key ? null : palette.key;
};
</script>

<template>
    <div
        class="relative z-10 mb-2 flex flex-wrap items-center justify-between gap-1.5 rounded-lg border border-slate-200 bg-slate-50/80 px-2.5 py-1 text-xs text-slate-600"
    >
        <div class="flex flex-wrap items-center gap-1.5">
            <button
                v-for="palette in palettes"
                :key="palette.key"
                type="button"
                :class="[
                    'shadow-xs inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1 font-semibold transition',
                    openKey === palette.key
                        ? 'border-indigo-600 bg-indigo-600 text-white'
                        : 'border-indigo-200 bg-white text-indigo-700 hover:bg-indigo-50',
                ]"
                :title="`Buka ${palette.title.toLowerCase()} (KaTeX)`"
                :aria-expanded="openKey === palette.key"
                :data-formula-button="palette.key"
                @click="toggle(palette)"
            >
                <component :is="palette.icon" class="h-3.5 w-3.5" />
                <span>{{ palette.buttonLabel }}</span>
            </button>
            <button
                v-if="!palettes.length"
                type="button"
                class="inline-flex items-center gap-1 rounded-md px-1.5 py-1 text-[11px] font-semibold text-slate-500 transition hover:bg-white hover:text-indigo-700"
                title="Katalog rumus Matematika dan IPA dipasang lewat galeri add-on"
                data-formula-addons-hint
                @click="emit('browse-addons')"
            >
                <Puzzle class="h-3.5 w-3.5" />
                <span>Rumus Matematika &amp; IPA: pasang add-on</span>
            </button>
        </div>

        <div class="flex items-center gap-2">
            <span class="hidden text-[10px] text-slate-400 md:inline"> Didukung oleh KaTeX & Font Quran Madinah </span>
            <button
                type="button"
                class="inline-flex items-center gap-1 rounded border border-slate-200 bg-white px-2 py-0.5 text-[11px] text-slate-600 hover:bg-slate-100"
                title="Ubah perataan teks Kanan/Kiri (RTL)"
                @click="emit('toggle-rtl')"
            >
                <AlignRight class="h-3 w-3" />
                <span>RTL</span>
            </button>
        </div>

        <FormulaPalette
            v-if="openPalette"
            :key="openPalette.key"
            :palette="openPalette"
            @insert="(snippet) => emit('insert', snippet)"
            @close="openKey = null"
        />
    </div>
</template>
