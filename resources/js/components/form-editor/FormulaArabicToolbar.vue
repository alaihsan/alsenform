<script setup lang="ts">
import FormulaPalette from '@/components/form-editor/FormulaPalette.vue';
import type { FormulaPalette as Palette } from '@/lib/formulas';
import { AlignRight } from 'lucide-vue-next';
import { computed, ref } from 'vue';

/*
 * The toolbar above a question, made of add-ons the teacher has installed: a button per formula
 * add-on (Rumus Matematika, Rumus IPA), each opening its catalogue, and the RTL button of Teks Arab
 * (RTL). The editor leaves the toolbar out when none of them is installed.
 */
const props = defineProps<{
    palettes: Palette[];
    /** The Teks Arab (RTL) add-on is installed. */
    showRtl: boolean;
    /** The question's field is right to left. */
    rtl: boolean;
}>();

const emit = defineEmits<{
    (e: 'insert', snippet: string): void;
    (e: 'toggle-rtl'): void;
}>();

const openKey = ref<string | null>(null);
const openPalette = computed(() => props.palettes.find((palette) => palette.key === openKey.value) ?? null);

const toggle = (palette: Palette) => {
    openKey.value = openKey.value === palette.key ? null : palette.key;
};

/** What draws what the buttons put in: KaTeX for formulas, the Mushaf Madinah font for Arabic. */
const poweredBy = computed(() => [props.palettes.length ? 'KaTeX' : '', props.showRtl ? 'Font Quran Madinah' : ''].filter(Boolean).join(' & '));
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
        </div>

        <div class="ml-auto flex items-center gap-2">
            <span v-if="poweredBy" class="hidden text-[10px] text-slate-400 md:inline">Didukung oleh {{ poweredBy }}</span>
            <button
                v-if="showRtl"
                type="button"
                :class="[
                    'inline-flex items-center gap-1 rounded border px-2 py-0.5 text-[11px] transition',
                    rtl ? 'border-emerald-600 bg-emerald-600 text-white' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-100',
                ]"
                :title="rtl ? 'Kembalikan arah tulisan ke kiri ke kanan' : 'Tulis dari kanan ke kiri (RTL) untuk teks Arab'"
                :aria-pressed="rtl"
                data-rtl-button
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
