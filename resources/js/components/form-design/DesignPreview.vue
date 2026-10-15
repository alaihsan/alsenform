<script setup lang="ts">
import { designVariables, logoJustify, pageBackgroundStyle, type FormDesign } from '@/lib/formDesign';
import { computed } from 'vue';

const props = defineProps<{
    design: FormDesign;
    title: string;
    description?: string;
    questionFont?: string;
    answerFont?: string;
}>();

const variables = computed(() => designVariables(props.design));
const options = ['Fotosintesis', 'Respirasi', 'Transpirasi'];
</script>

<template>
    <div class="af-designed h-full overflow-y-auto" :style="variables" data-testid="design-preview">
        <div class="af-page min-h-full px-4 py-6 sm:px-6" :style="pageBackgroundStyle(design)">
            <div class="mx-auto max-w-3xl space-y-3">
                <img v-if="design.headerImage" :src="design.headerImage" alt="" class="af-header-image" />
                <section class="af-card af-header overflow-hidden border bg-white">
                    <div class="af-header-bar af-accent-bg h-2.5"></div>
                    <div class="p-5">
                        <div v-if="design.logo" class="mb-3 flex" :style="{ justifyContent: logoJustify(design) }">
                            <img :src="design.logo" alt="Logo" class="af-logo" />
                        </div>
                        <h1 class="af-title font-semibold leading-tight text-slate-900">{{ title || 'Judul ujian' }}</h1>
                        <p class="af-description mt-2 text-sm text-slate-500" :style="{ fontFamily: answerFont }">
                            {{ description || 'Petunjuk: pilihlah satu jawaban yang paling tepat.' }}
                        </p>
                    </div>
                </section>

                <section class="af-card af-section overflow-hidden border bg-white">
                    <div class="af-accent-bg h-1.5"></div>
                    <div class="p-5">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-indigo-700">Bagian 1 dari 2</p>
                        <h2 class="mt-1 text-lg font-semibold text-slate-900">Biologi Dasar</h2>
                        <p class="mt-1 text-sm text-slate-600">Bacalah soal dengan teliti.</p>
                    </div>
                </section>

                <article class="af-card af-question border bg-white p-5">
                    <div class="flex items-center gap-2">
                        <span class="rounded-xl bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-700">Soal 1</span>
                        <span class="rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-bold text-indigo-700">1 Poin</span>
                    </div>
                    <h2 class="af-question-title mt-3 font-medium leading-relaxed text-slate-900" :style="{ fontFamily: questionFont }">
                        Proses tumbuhan membuat makanan dengan bantuan cahaya matahari disebut ...
                    </h2>
                    <div class="mt-4 space-y-2.5">
                        <div
                            v-for="(option, index) in options"
                            :key="option"
                            :class="[
                                'af-option flex items-center gap-3 rounded-2xl border px-4 py-3',
                                index === 0 ? 'border-indigo-500 bg-indigo-50/70' : 'border-slate-200 bg-white',
                            ]"
                            :style="{ fontFamily: answerFont }"
                        >
                            <span
                                :class="[
                                    'flex h-6 w-6 shrink-0 items-center justify-center rounded-full border text-xs font-bold',
                                    index === 0 ? 'af-accent-bg border-transparent text-white' : 'border-slate-300 text-slate-500',
                                ]"
                            >
                                {{ String.fromCharCode(65 + index) }}
                            </span>
                            <span class="text-slate-800">{{ option }}</span>
                        </div>
                    </div>
                </article>

                <div class="flex items-center justify-between gap-3 pt-1">
                    <span class="af-on-page text-xs font-bold uppercase tracking-wider">Halaman 1 dari 2</span>
                    <span class="af-button af-accent-bg inline-flex items-center rounded-2xl px-5 py-2.5 text-sm font-bold text-white shadow-md"
                        >Selanjutnya</span
                    >
                </div>
            </div>
        </div>
    </div>
</template>
