<script setup lang="ts">
import { computed } from 'vue';
import type { RecentForm } from '@/types/quiz';
import RichContent from '@/components/RichContent.vue';

const props = withDefaults(
    defineProps<{
        form: RecentForm;
        viewMode?: 'grid' | 'list';
    }>(),
    {
        viewMode: 'grid',
    }
);

const firstQuestion = computed(() => props.form.firstQuestion || props.form.previewQuestions?.[0] || null);

const secondQuestion = computed(() => {
    if (props.form.previewQuestions && props.form.previewQuestions.length > 1) {
        return props.form.previewQuestions[1];
    }
    return null;
});

const displayOptions = computed(() => {
    if (!firstQuestion.value?.options) {
        return [];
    }
    return firstQuestion.value.options.slice(0, 3);
});

const isChoiceType = computed(() => {
    const type = firstQuestion.value?.type;
    return type === 'Multiple choice' || !type;
});

const isCheckboxType = computed(() => firstQuestion.value?.type === 'Checkboxes');
const isShortAnswer = computed(() => firstQuestion.value?.type === 'Short answer');
const isParagraph = computed(() => firstQuestion.value?.type === 'Paragraph');
const isDropdown = computed(() => firstQuestion.value?.type === 'Drop-down');
const isGridType = computed(() => firstQuestion.value?.type === 'Multiple-choice grid' || firstQuestion.value?.type === 'Tick box grid');
const isScaleOrRating = computed(() => firstQuestion.value?.type === 'Linear scale' || firstQuestion.value?.type === 'Rating');
</script>

<template>
    <div
        :class="[
            'relative flex aspect-[5/3] select-none items-start justify-center overflow-hidden transition-colors',
            viewMode === 'list' ? 'h-full w-full p-1.5' : 'w-full p-2 sm:p-2.5',
            form.backgroundColorClass || form.tone || 'bg-slate-100',
            form.isTrashed ? 'opacity-65 grayscale' : '',
        ]"
    >
        <!-- Miniature Google Forms Paper -->
        <div
            class="flex h-full w-[88%] sm:w-[90%] flex-col overflow-hidden rounded-t-md border border-slate-200/90 bg-white shadow-sm transition-shadow group-hover:shadow-md"
        >
            <!-- Top Theme Accent Stripe -->
            <div
                :class="[
                    'h-2.5 w-full shrink-0 transition-colors sm:h-3',
                    form.isTrashed ? 'bg-slate-400' : form.themeColorClass || form.stripe || 'bg-indigo-600',
                ]"
            ></div>

            <!-- Form Document Sheet -->
            <div class="flex flex-1 flex-col justify-start gap-1 overflow-hidden bg-white p-1.5 sm:p-2">
                <!-- Form Title Header -->
                <div class="shrink-0 border-b border-slate-100 pb-1">
                    <RichContent
                        :content="form.title || 'Untitled form'"
                        as="h4"
                        class="line-clamp-1 text-[8.5px] font-bold leading-tight tracking-tight text-slate-800 sm:text-[9.5px]"
                    />
                </div>

                <!-- First Question Preview -->
                <div v-if="firstQuestion" class="flex flex-1 flex-col justify-start gap-0.5 overflow-hidden">
                    <!-- Question Title -->
                    <RichContent
                        :content="firstQuestion.title || 'Pertanyaan tanpa judul'"
                        as="p"
                        class="line-clamp-2 text-[7.5px] font-semibold leading-snug text-slate-700 sm:text-[8px]"
                    />

                    <!-- Multiple Choice Preview -->
                    <div v-if="isChoiceType" class="mt-0.5 space-y-0.5">
                        <template v-if="displayOptions.length > 0">
                            <div v-for="(opt, idx) in displayOptions" :key="idx" class="flex min-w-0 items-center gap-1">
                                <span class="inline-block h-1.5 w-1.5 shrink-0 rounded-full border border-slate-400 bg-white"></span>
                                <RichContent :content="opt" class="truncate text-[6.5px] leading-tight text-slate-600 sm:text-[7px]" />
                            </div>
                        </template>
                        <template v-else>
                            <div class="flex items-center gap-1">
                                <span class="inline-block h-1.5 w-1.5 shrink-0 rounded-full border border-slate-300"></span>
                                <span class="h-1.5 w-12 rounded-full bg-slate-200"></span>
                            </div>
                            <div class="flex items-center gap-1">
                                <span class="inline-block h-1.5 w-1.5 shrink-0 rounded-full border border-slate-300"></span>
                                <span class="h-1.5 w-16 rounded-full bg-slate-200"></span>
                            </div>
                        </template>
                    </div>

                    <!-- Checkboxes Preview -->
                    <div v-else-if="isCheckboxType" class="mt-0.5 space-y-0.5">
                        <template v-if="displayOptions.length > 0">
                            <div v-for="(opt, idx) in displayOptions" :key="idx" class="flex min-w-0 items-center gap-1">
                                <span class="inline-block h-1.5 w-1.5 shrink-0 rounded-[2px] border border-slate-400 bg-white"></span>
                                <span class="truncate text-[6.5px] leading-tight text-slate-600 sm:text-[7px]">{{ opt }}</span>
                            </div>
                        </template>
                        <template v-else>
                            <div class="flex items-center gap-1">
                                <span class="inline-block h-1.5 w-1.5 shrink-0 rounded-[2px] border border-slate-300"></span>
                                <span class="h-1.5 w-12 rounded-full bg-slate-200"></span>
                            </div>
                        </template>
                    </div>

                    <!-- Short Answer Preview -->
                    <div v-else-if="isShortAnswer" class="mt-0.5 flex flex-col gap-0.5">
                        <div class="h-1 w-3/4 border-b border-dashed border-slate-300"></div>
                        <span class="text-[6px] italic text-slate-400 sm:text-[6.5px]">Teks jawaban singkat</span>
                    </div>

                    <!-- Paragraph Preview -->
                    <div v-else-if="isParagraph" class="mt-0.5 space-y-0.5">
                        <div class="h-1 w-full border-b border-dashed border-slate-300"></div>
                        <div class="h-1 w-4/5 border-b border-dashed border-slate-200"></div>
                        <span class="text-[6px] italic text-slate-400 sm:text-[6.5px]">Teks jawaban panjang</span>
                    </div>

                    <!-- Dropdown Preview -->
                    <div v-else-if="isDropdown" class="mt-0.5 flex h-3 w-3/4 items-center justify-between rounded border border-slate-200 bg-slate-50 px-1 text-[6px] text-slate-500 sm:text-[6.5px]">
                        <span class="truncate">{{ displayOptions[0] || 'Pilih opsi...' }}</span>
                        <span class="text-[5px]">▼</span>
                    </div>

                    <!-- Grid Preview -->
                    <div v-else-if="isGridType" class="mt-0.5 grid w-20 grid-cols-4 gap-1 py-0.5">
                        <span v-for="dot in 8" :key="dot" class="mx-auto h-1 w-1 rounded-full bg-slate-300"></span>
                    </div>

                    <!-- Linear scale / Rating Preview -->
                    <div v-else-if="isScaleOrRating" class="mt-0.5 flex items-center gap-0.5">
                        <span
                            v-for="n in 5"
                            :key="n"
                            class="flex h-2.5 w-2.5 items-center justify-center rounded-full border border-slate-300 text-[5.5px] text-slate-500"
                        >
                            {{ n }}
                        </span>
                    </div>

                    <!-- Fallback / Generic Preview -->
                    <div v-else class="mt-0.5 flex items-center gap-1">
                        <span class="inline-block h-1.5 w-1.5 shrink-0 rounded-full border border-slate-300"></span>
                        <span class="h-1.5 w-14 rounded-full bg-slate-200"></span>
                    </div>

                    <!-- Second Question Peek -->
                    <div v-if="secondQuestion" class="mt-auto border-t border-slate-100 pt-0.5">
                        <RichContent
                            :content="`2. ${secondQuestion.title || 'Pertanyaan selanjutnya'}`"
                            as="p"
                            class="truncate text-[6.5px] font-medium text-slate-400 sm:text-[7px]"
                        />
                    </div>
                </div>

                <!-- Empty State (No questions yet) -->
                <div v-else class="flex flex-1 flex-col justify-center space-y-1 py-0.5">
                    <div class="h-1.5 w-3/4 rounded bg-slate-200/80"></div>
                    <div class="flex items-center gap-1">
                        <span class="h-1.5 w-1.5 rounded-full border border-slate-300"></span>
                        <span class="h-1.5 w-14 rounded bg-slate-100"></span>
                    </div>
                    <div class="flex items-center gap-1">
                        <span class="h-1.5 w-1.5 rounded-full border border-slate-300"></span>
                        <span class="h-1.5 w-10 rounded bg-slate-100"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
