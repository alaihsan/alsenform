<script setup lang="ts">
import { asQuestionText, type Formula, type FormulaPalette, type FormulaTopic } from '@/lib/formulas';
import { renderLatex } from '@/utils/rich-content';
import { Loader2, PencilLine, Search, X } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

/*
 * The catalogue of a formula add-on (Rumus Matematika or Rumus IPA) under a question's toolbar:
 * search, a tab per topic, and per chapter every formula drawn by KaTeX with its name, what its
 * symbols mean and its LaTeX. A click puts the formula in the question; the pencil puts it in the
 * free LaTeX box first, to be changed before it goes in.
 */
const props = defineProps<{
    palette: FormulaPalette;
}>();

const emit = defineEmits<{
    (e: 'insert', snippet: string): void;
    (e: 'close'): void;
}>();

const topics = ref<FormulaTopic[] | null>(null);
const loadFailed = ref(false);
const activeTopicKey = ref('');
const query = ref('');
const customLatex = ref(props.palette.example);
const customInput = ref<HTMLInputElement | null>(null);
const searchInput = ref<HTMLInputElement | null>(null);

const load = async () => {
    loadFailed.value = false;
    try {
        topics.value = await props.palette.load();
        activeTopicKey.value ||= topics.value[0]?.key ?? '';
    } catch {
        loadFailed.value = true;
    }
};

/** KaTeX output by LaTeX: the catalogue is drawn once per page, not on every keystroke of the search. */
const rendered = new Map<string, string>();
const preview = (latex: string) => {
    if (!rendered.has(latex)) {
        rendered.set(latex, renderLatex(latex));
    }

    return rendered.get(latex) as string;
};

/** Lower case without accents, so "hooke" finds "Hooke" and "rontgen" finds "Röntgen". */
const normalize = (text: string) => text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

type ShownGroup = { key: string; name: string; formulas: Formula[] };

const searchWords = computed(() => normalize(query.value).split(/\s+/).filter(Boolean));

/** The chapters of the open topic or, while searching, every chapter with a formula that matches all the words. */
const shownGroups = computed<ShownGroup[]>(() => {
    if (!topics.value) {
        return [];
    }
    if (!searchWords.value.length) {
        const topic = topics.value.find((candidate) => candidate.key === activeTopicKey.value) ?? topics.value[0];

        return (topic?.groups ?? []).map((group) => ({ key: `${topic.key}-${group.name}`, name: group.name, formulas: group.formulas }));
    }

    return topics.value.flatMap((topic) =>
        topic.groups
            .map((group) => ({
                key: `${topic.key}-${group.name}`,
                name: `${topic.name} · ${group.name}`,
                formulas: group.formulas.filter((formula) => {
                    const haystack = normalize(`${topic.name} ${group.name} ${formula.label} ${formula.note ?? ''} ${formula.latex}`);

                    return searchWords.value.every((word) => haystack.includes(word));
                }),
            }))
            .filter((group) => group.formulas.length),
    );
});

const resultCount = computed(() => shownGroups.value.reduce((count, group) => count + group.formulas.length, 0));

const insert = (latex: string) => {
    if (!latex.trim()) {
        return;
    }
    emit('insert', asQuestionText(latex));
    emit('close');
};

/** Into the free LaTeX box, to change it before inserting. */
const editFirst = (latex: string) => {
    customLatex.value = latex;
    nextTick(() => {
        customInput.value?.focus();
        customInput.value?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    });
};

const onKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Escape') {
        event.stopPropagation();
        emit('close');
    }
};

onMounted(() => {
    load();
    document.addEventListener('keydown', onKeydown, true);
    nextTick(() => searchInput.value?.focus({ preventScroll: true }));
});
onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown, true));
</script>

<template>
    <section
        class="absolute left-0 top-full z-30 mt-2 flex w-full max-w-3xl flex-col rounded-2xl border border-slate-200 bg-white text-slate-700 shadow-xl duration-150 animate-in fade-in zoom-in-95"
        role="dialog"
        :aria-label="palette.title"
        :data-formula-palette="palette.key"
    >
        <header class="flex items-start justify-between gap-3 border-b border-slate-100 px-4 pb-2.5 pt-3">
            <div class="flex min-w-0 items-center gap-2">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-700">
                    <component :is="palette.icon" class="h-4 w-4" />
                </span>
                <div class="min-w-0">
                    <h4 class="text-sm font-bold text-slate-800">{{ palette.title }}</h4>
                    <p class="truncate text-[11px] text-slate-500" :title="palette.hint">{{ palette.hint }}</p>
                </div>
            </div>
            <button
                type="button"
                class="rounded-full p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                aria-label="Tutup katalog rumus"
                @click="emit('close')"
            >
                <X class="h-4 w-4" />
            </button>
        </header>

        <div class="px-4 pt-3">
            <label
                class="flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-1.5 focus-within:border-indigo-400 focus-within:bg-white"
            >
                <Search class="h-3.5 w-3.5 shrink-0 text-slate-400" />
                <input
                    ref="searchInput"
                    v-model="query"
                    type="search"
                    class="w-full border-0 bg-transparent p-0 text-xs outline-none focus:ring-0"
                    :placeholder="palette.searchPlaceholder"
                    aria-label="Cari rumus"
                />
                <span v-if="searchWords.length && topics" class="shrink-0 text-[10px] font-semibold text-slate-400">{{ resultCount }} rumus</span>
            </label>
        </div>

        <div v-if="topics && !searchWords.length" class="flex gap-1 overflow-x-auto px-4 pb-1 pt-2.5" role="tablist" aria-label="Topik rumus">
            <button
                v-for="topic in topics"
                :key="topic.key"
                type="button"
                role="tab"
                :aria-selected="topic.key === activeTopicKey"
                :class="[
                    'shrink-0 rounded-lg px-2.5 py-1 text-[11px] font-bold transition',
                    topic.key === activeTopicKey
                        ? 'bg-indigo-600 text-white shadow-sm'
                        : 'bg-slate-100 text-slate-600 hover:bg-indigo-50 hover:text-indigo-700',
                ]"
                @click="activeTopicKey = topic.key"
            >
                {{ topic.name }}
            </button>
        </div>

        <div class="max-h-80 space-y-4 overflow-y-auto px-4 py-3">
            <div v-if="loadFailed" class="rounded-xl bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700" role="alert">
                Katalog rumus belum dapat dimuat.
                <button type="button" class="ml-1 underline" @click="load">Coba lagi</button>
            </div>
            <div v-else-if="!topics" class="grid grid-cols-[repeat(auto-fill,minmax(11rem,1fr))] gap-1.5" aria-busy="true">
                <span class="col-span-full flex items-center gap-1.5 text-[11px] text-slate-400">
                    <Loader2 class="h-3.5 w-3.5 animate-spin" /> Memuat katalog rumus...
                </span>
                <div v-for="slot in 6" :key="slot" class="h-20 animate-pulse rounded-xl bg-slate-100"></div>
            </div>
            <p v-else-if="!shownGroups.length" class="py-6 text-center text-xs text-slate-500">
                Tidak ada rumus untuk "{{ query }}". Coba kata lain, atau tulis sendiri di kotak LaTeX di bawah.
            </p>

            <div v-for="group in shownGroups" :key="group.key">
                <h5 class="mb-1.5 text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ group.name }}</h5>
                <div class="grid grid-cols-[repeat(auto-fill,minmax(11rem,1fr))] gap-1.5">
                    <div
                        v-for="formula in group.formulas"
                        :key="formula.label"
                        class="group relative rounded-xl border border-slate-200 bg-slate-50/70 transition hover:border-indigo-400 hover:bg-indigo-50/50"
                    >
                        <button
                            type="button"
                            class="flex h-full w-full flex-col gap-1 p-2 text-left"
                            :title="`Sisipkan: $${formula.latex}$`"
                            @click="insert(formula.latex)"
                        >
                            <!-- Centred while it fits, scrolled from its start when it is wider than the card. -->
                            <span class="flex min-h-8 items-center overflow-x-auto py-1 text-sm text-slate-800">
                                <span class="mx-auto w-max" v-html="preview(formula.latex)"></span>
                            </span>
                            <span class="pr-5 text-[11px] font-semibold leading-snug text-slate-700">{{ formula.label }}</span>
                            <span v-if="formula.note" class="text-[10px] leading-snug text-slate-500">{{ formula.note }}</span>
                            <code
                                class="block truncate rounded bg-white/80 px-1 py-0.5 font-mono text-[10px] text-indigo-700"
                                :title="formula.latex"
                                >{{ formula.latex }}</code
                            >
                        </button>
                        <button
                            type="button"
                            class="absolute right-1 top-1 rounded-md p-1 text-slate-400 opacity-0 transition hover:bg-white hover:text-indigo-700 focus:opacity-100 group-hover:opacity-100"
                            :aria-label="`Ubah ${formula.label} sebelum disisipkan`"
                            title="Ubah dulu sebelum disisipkan"
                            @click="editFirst(formula.latex)"
                        >
                            <PencilLine class="h-3.5 w-3.5" />
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <footer class="rounded-b-2xl border-t border-slate-100 bg-slate-50 px-4 py-3">
            <div class="mb-1 flex flex-wrap items-center justify-between gap-1 text-[11px] font-bold text-slate-600">
                <span>Uji / ketik kode LaTeX sendiri</span>
                <span class="text-[10px] font-normal text-slate-400">Di soal, rumus ditulis di antara tanda $...$</span>
            </div>
            <form class="flex gap-1.5" @submit.prevent="insert(customLatex)">
                <input
                    ref="customInput"
                    v-model="customLatex"
                    type="text"
                    spellcheck="false"
                    class="min-w-0 flex-1 rounded-lg border border-slate-300 bg-white px-2.5 py-1 font-mono text-xs outline-none focus:border-indigo-500 focus:ring-0"
                    placeholder="Contoh: \frac{a}{b} atau \ce{H2SO4}"
                    aria-label="Kode LaTeX"
                />
                <button
                    type="submit"
                    class="shrink-0 rounded-lg bg-indigo-600 px-3 py-1 text-xs font-bold text-white hover:bg-indigo-700 disabled:opacity-50"
                    :disabled="!customLatex.trim()"
                >
                    Sisipkan
                </button>
            </form>
            <div
                v-if="customLatex.trim()"
                class="mt-2 flex min-h-10 items-center overflow-x-auto rounded-lg border border-indigo-100 bg-white px-2 py-2 text-slate-800"
            >
                <span class="mx-auto w-max" v-html="renderLatex(customLatex)"></span>
            </div>
        </footer>
    </section>
</template>
