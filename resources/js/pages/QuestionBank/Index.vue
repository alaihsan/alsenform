<script setup lang="ts">
import RichContent from '@/components/RichContent.vue';
import AlsenformLayout from '@/layouts/AlsenformLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import axios from 'axios';
import {
    Archive,
    ArchiveRestore,
    ArrowLeft,
    ChevronDown,
    ExternalLink,
    FilePlus2,
    Image as ImageIcon,
    KeyRound,
    Library,
    Loader2,
    Search,
    Tag,
    X,
} from 'lucide-vue-next';
import { computed, onMounted, reactive, ref, watch } from 'vue';

type Labels = { subject: string | null; grade: string | null; topic: string | null; difficulty: string | null };

type BankItem = {
    id: number;
    type: string;
    title: string;
    options: string[];
    optionCount: number;
    points: number;
    hasAnswerKey: boolean;
    mediaCount: number;
    source: { title: string; trashed: boolean } | null;
    sourceUrl: string | null;
    updatedAt: string | null;
    labels: Labels;
    archived: boolean;
    question: Record<string, any> | null;
};

const TYPE_LABELS: Record<string, string> = {
    'Multiple choice': 'Pilihan Ganda',
    Checkboxes: 'Kotak Centang',
    'Drop-down': 'Drop-down',
    'Short answer': 'Isian Singkat',
    Paragraph: 'Uraian',
    'File upload': 'Unggah Berkas',
    'Linear scale': 'Skala Linear',
    Rating: 'Rating',
    'Multiple-choice grid': 'Kisi Pilihan Ganda',
    'Tick box grid': 'Kisi Kotak Centang',
    Date: 'Tanggal',
    Time: 'Waktu',
};
const DIFFICULTIES: Record<string, { label: string; class: string }> = {
    mudah: { label: 'Mudah', class: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' },
    sedang: { label: 'Sedang', class: 'bg-amber-50 text-amber-700 ring-amber-600/20' },
    sulit: { label: 'Sulit', class: 'bg-rose-50 text-rose-700 ring-rose-600/20' },
};
const typeLabel = (type: string) => TYPE_LABELS[type] ?? type;
const gradeText = (grade: string) => (/^kelas\b/i.test(grade) ? grade : `Kelas ${grade}`);

const filters = reactive({ search: '', type: '', form: '' as number | '', difficulty: '', subject: '', grade: '', topic: '', archived: false });
const items = ref<BankItem[]>([]);
const total = ref(0);
const currentPage = ref(0);
const hasMore = ref(false);
const types = ref<string[]>([]);
const forms = ref<{ id: number; title: string }[]>([]);
const suggestions = ref<{ subjects: string[]; grades: string[]; topics: string[] }>({ subjects: [], grades: [], topics: [] });
const isLoading = ref(false);
const loadError = ref('');
const notice = ref('');
const selected = ref<Map<number, BankItem>>(new Map());
const expanded = ref<Set<number>>(new Set());
let hasSynced = false;
let requestCounter = 0;

const load = async (page = 1) => {
    const request = ++requestCounter;
    isLoading.value = true;
    loadError.value = '';
    try {
        const response = await axios.get(route('question-bank.index'), {
            params: {
                search: filters.search.trim() || undefined,
                type: filters.type || undefined,
                form: filters.form || undefined,
                difficulty: filters.difficulty || undefined,
                subject: filters.subject || undefined,
                grade: filters.grade || undefined,
                topic: filters.topic || undefined,
                archived: filters.archived ? 1 : undefined,
                full: 1,
                page,
                sync: hasSynced ? undefined : 1,
            },
        });
        if (request !== requestCounter) {
            return;
        }
        hasSynced = true;
        items.value = page === 1 ? response.data.items : [...items.value, ...response.data.items];
        total.value = response.data.total;
        currentPage.value = response.data.currentPage;
        hasMore.value = response.data.hasMore;
        types.value = response.data.types ?? [];
        forms.value = response.data.forms ?? [];
        suggestions.value = response.data.suggestions ?? suggestions.value;
    } catch {
        if (request === requestCounter) {
            loadError.value = 'Bank soal tidak dapat dimuat. Periksa koneksi lalu coba lagi.';
        }
    } finally {
        if (request === requestCounter) {
            isLoading.value = false;
        }
    }
};

const reload = () => load(1);
const searchSoon = useDebounceFn(reload, 300);
watch(
    () => filters.search,
    () => searchSoon(),
);
watch(
    () => [filters.type, filters.form, filters.difficulty, filters.subject, filters.grade, filters.topic, filters.archived],
    () => {
        selected.value = new Map();
        reload();
    },
);

const hasFilters = computed(() =>
    Boolean(filters.search || filters.type || filters.form || filters.difficulty || filters.subject || filters.grade || filters.topic),
);
const resetFilters = () => Object.assign(filters, { search: '', type: '', form: '', difficulty: '', subject: '', grade: '', topic: '' });

const isSelected = (item: BankItem) => selected.value.has(item.id);
const toggle = (item: BankItem) => {
    const next = new Map(selected.value);
    if (next.has(item.id)) {
        next.delete(item.id);
    } else {
        next.set(item.id, item);
    }
    selected.value = next;
};
const allShownSelected = computed(() => items.value.length > 0 && items.value.every((item) => selected.value.has(item.id)));
const toggleAllShown = () => {
    const next = new Map(selected.value);
    items.value.forEach((item) => (allShownSelected.value ? next.delete(item.id) : next.set(item.id, item)));
    selected.value = next;
};
const toggleExpanded = (item: BankItem) => {
    const next = new Set(expanded.value);
    if (next.has(item.id)) {
        next.delete(item.id);
    } else {
        next.add(item.id);
    }
    expanded.value = next;
};

/* Labels: one question (all fields as shown) or many at once (only the fields filled in change). */
const labelDialog = reactive({
    open: false,
    ids: [] as number[],
    single: false,
    saving: false,
    error: '',
    subject: '',
    grade: '',
    topic: '',
    difficulty: '',
});

const openLabels = (targets: BankItem[]) => {
    const single = targets.length === 1;
    Object.assign(labelDialog, {
        open: true,
        ids: targets.map((item) => item.id),
        single,
        saving: false,
        error: '',
        subject: single ? (targets[0].labels.subject ?? '') : '',
        grade: single ? (targets[0].labels.grade ?? '') : '',
        topic: single ? (targets[0].labels.topic ?? '') : '',
        difficulty: single ? (targets[0].labels.difficulty ?? '') : 'keep',
    });
};

const saveLabels = async () => {
    const payload: Record<string, unknown> = { ids: labelDialog.ids };
    if (labelDialog.single) {
        Object.assign(payload, {
            subject: labelDialog.subject,
            grade: labelDialog.grade,
            topic: labelDialog.topic,
            difficulty: labelDialog.difficulty,
        });
    } else {
        (['subject', 'grade', 'topic'] as const).forEach((field) => {
            if (labelDialog[field].trim() !== '') {
                payload[field] = labelDialog[field];
            }
        });
        if (labelDialog.difficulty !== 'keep') {
            payload.difficulty = labelDialog.difficulty === 'clear' ? null : labelDialog.difficulty;
        }
    }

    labelDialog.saving = true;
    labelDialog.error = '';
    try {
        await axios.patch(route('question-bank.labels'), payload);
        labelDialog.open = false;
        notice.value = labelDialog.single ? 'Label soal disimpan.' : `Label ${labelDialog.ids.length} soal disimpan.`;
        await reload();
    } catch (error: any) {
        labelDialog.error = error.response?.data?.message || 'Label gagal disimpan. Coba lagi.';
    } finally {
        labelDialog.saving = false;
    }
};

const isArchiving = ref(false);
const setArchived = async (targets: BankItem[], archived: boolean) => {
    isArchiving.value = true;
    try {
        await axios.patch(route('question-bank.archive'), { ids: targets.map((item) => item.id), archived });
        notice.value = archived ? `${targets.length} soal dipindah ke arsip.` : `${targets.length} soal dikembalikan dari arsip.`;
        selected.value = new Map();
        await reload();
    } catch {
        notice.value = 'Perubahan gagal disimpan. Coba lagi.';
    } finally {
        isArchiving.value = false;
    }
};

const examDialog = reactive({ open: false, title: 'Ujian dari Bank Soal', saving: false });
const createExam = () => {
    examDialog.saving = true;
    router.post(
        route('question-bank.exam'),
        { ids: [...selected.value.keys()], title: examDialog.title },
        { onFinish: () => (examDialog.saving = false) },
    );
};

const selectedItems = computed(() => [...selected.value.values()]);

/* The answer key in words, for the preview. */
const isKeyOption = (item: BankItem, option: string, index: number): boolean => {
    const key = item.question?.answer;
    const matches = (value: unknown) =>
        typeof value === 'number' || /^\d+$/.test(String(value)) ? Number(value) === index : String(value) === option;
    return Array.isArray(key) ? key.some(matches) : key !== null && key !== undefined && key !== '' && matches(key);
};
const textKey = (item: BankItem): string | null => {
    const key = item.question?.answer;
    if (['Multiple choice', 'Drop-down', 'Checkboxes'].includes(item.type) || key === null || key === undefined || key === '') {
        return null;
    }
    return typeof key === 'object' ? JSON.stringify(key) : String(key);
};

const formatDate = (value: string | null) =>
    value ? new Date(value).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) : '';

onMounted(reload);
</script>

<template>
    <AlsenformLayout>
        <Head title="Bank Soal" />

        <main class="mx-auto max-w-6xl px-4 py-6 pb-28 sm:px-6 sm:py-8">
            <Link
                :href="route('dashboard')"
                class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 transition hover:text-indigo-700"
            >
                <ArrowLeft class="h-4 w-4" />
                Kembali ke Beranda
            </Link>

            <div class="mt-3">
                <p class="flex items-center gap-1.5 text-xs font-black uppercase tracking-[0.18em] text-indigo-500">
                    <Library class="h-3.5 w-3.5" />
                    Bank Soal
                </p>
                <h1 class="mt-1 text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">Bank Soal Saya</h1>
                <p class="mt-1 max-w-3xl text-sm text-slate-500">
                    Setiap soal yang Anda tulis tersimpan di sini. Beri label mapel, kelas, bab, dan tingkat kesulitan, lalu pakai lagi lewat
                    <strong>Import Soal → Bank Soal</strong> atau buat ujian baru langsung dari soal yang dicentang.
                </p>
            </div>

            <section class="mt-5 space-y-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <label class="relative block">
                    <span class="sr-only">Cari soal</span>
                    <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input
                        v-model="filters.search"
                        type="search"
                        placeholder="Cari teks soal atau pilihan jawaban..."
                        class="h-10 w-full rounded-xl border border-slate-200 bg-white pl-9 pr-3 text-sm outline-none focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100"
                    />
                </label>
                <div class="grid gap-2 sm:grid-cols-3 lg:grid-cols-6">
                    <select
                        v-model="filters.difficulty"
                        aria-label="Tingkat kesulitan"
                        class="h-10 min-w-0 rounded-xl border border-slate-200 bg-white px-3 text-sm"
                    >
                        <option value="">Semua kesulitan</option>
                        <option value="mudah">Mudah</option>
                        <option value="sedang">Sedang</option>
                        <option value="sulit">Sulit</option>
                        <option value="none">Belum dilabeli</option>
                    </select>
                    <select
                        v-model="filters.subject"
                        aria-label="Mapel"
                        class="h-10 min-w-0 rounded-xl border border-slate-200 bg-white px-3 text-sm"
                    >
                        <option value="">Semua mapel</option>
                        <option v-for="value in suggestions.subjects" :key="value" :value="value">{{ value }}</option>
                        <option value="none">Tanpa mapel</option>
                    </select>
                    <select v-model="filters.grade" aria-label="Kelas" class="h-10 min-w-0 rounded-xl border border-slate-200 bg-white px-3 text-sm">
                        <option value="">Semua kelas</option>
                        <option v-for="value in suggestions.grades" :key="value" :value="value">{{ gradeText(value) }}</option>
                        <option value="none">Tanpa kelas</option>
                    </select>
                    <select v-model="filters.topic" aria-label="Bab" class="h-10 min-w-0 rounded-xl border border-slate-200 bg-white px-3 text-sm">
                        <option value="">Semua bab / TP</option>
                        <option v-for="value in suggestions.topics" :key="value" :value="value">{{ value }}</option>
                        <option value="none">Tanpa bab</option>
                    </select>
                    <select
                        v-model="filters.type"
                        aria-label="Tipe soal"
                        class="h-10 min-w-0 rounded-xl border border-slate-200 bg-white px-3 text-sm"
                    >
                        <option value="">Semua tipe</option>
                        <option v-for="type in types" :key="type" :value="type">{{ typeLabel(type) }}</option>
                    </select>
                    <select
                        v-model="filters.form"
                        aria-label="Asal ujian"
                        class="h-10 min-w-0 rounded-xl border border-slate-200 bg-white px-3 text-sm"
                    >
                        <option value="">Semua ujian</option>
                        <option v-for="exam in forms" :key="exam.id" :value="exam.id">{{ exam.title }}</option>
                    </select>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-2 text-xs">
                    <div class="inline-flex rounded-xl bg-slate-100 p-1 font-bold">
                        <button
                            type="button"
                            :class="['rounded-lg px-3 py-1.5', !filters.archived ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-600']"
                            @click="filters.archived = false"
                        >
                            Soal aktif
                        </button>
                        <button
                            type="button"
                            :class="['rounded-lg px-3 py-1.5', filters.archived ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-600']"
                            @click="filters.archived = true"
                        >
                            Arsip
                        </button>
                    </div>
                    <button
                        v-if="hasFilters"
                        type="button"
                        class="inline-flex items-center gap-1 font-semibold text-slate-500 hover:text-slate-800"
                        @click="resetFilters"
                    >
                        <X class="h-3.5 w-3.5" /> Hapus filter
                    </button>
                </div>
            </section>

            <p v-if="notice" class="mt-4 rounded-xl bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-800" role="status">{{ notice }}</p>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-2 text-sm text-slate-500">
                <label
                    class="inline-flex cursor-pointer items-center gap-2 font-semibold text-slate-700"
                    :class="{ 'opacity-50': items.length === 0 }"
                >
                    <input
                        type="checkbox"
                        class="h-4 w-4 rounded border-slate-300 text-indigo-600"
                        :checked="allShownSelected"
                        :disabled="items.length === 0"
                        @change="toggleAllShown"
                    />
                    Pilih semua yang tampil
                </label>
                <span>{{ total }} soal</span>
            </div>

            <div v-if="loadError" class="mt-3 rounded-2xl border border-rose-200 bg-rose-50 p-6 text-center text-sm font-semibold text-rose-700">
                {{ loadError }} <button type="button" class="underline" @click="reload">Coba lagi</button>
            </div>

            <div v-else-if="isLoading && items.length === 0" class="mt-3 space-y-2">
                <div v-for="placeholder in 5" :key="placeholder" class="h-24 animate-pulse rounded-2xl bg-slate-100"></div>
            </div>

            <div v-else-if="items.length === 0" class="mt-3 rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-indigo-50 text-indigo-500">
                    <Library class="h-6 w-6" />
                </div>
                <p class="mt-3 font-semibold text-slate-700">
                    {{ filters.archived ? 'Arsip kosong.' : hasFilters ? 'Tidak ada soal yang cocok.' : 'Bank soal masih kosong.' }}
                </p>
                <p v-if="!filters.archived && !hasFilters" class="mt-1 text-sm text-slate-500">Soal yang Anda tulis di ujian akan muncul di sini.</p>
            </div>

            <ul v-else class="mt-3 space-y-2">
                <li
                    v-for="item in items"
                    :key="item.id"
                    :class="[
                        'rounded-2xl border bg-white shadow-sm transition',
                        isSelected(item) ? 'border-indigo-300 ring-2 ring-indigo-100' : 'border-slate-200',
                    ]"
                >
                    <div class="flex gap-3 p-4">
                        <input
                            type="checkbox"
                            class="mt-1 h-4 w-4 shrink-0 rounded border-slate-300 text-indigo-600"
                            :checked="isSelected(item)"
                            :aria-label="`Pilih soal: ${item.title}`"
                            @change="toggle(item)"
                        />
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-1.5 text-[11px] font-bold">
                                <span class="rounded-md bg-slate-100 px-1.5 py-0.5 uppercase tracking-wide text-slate-600">{{
                                    typeLabel(item.type)
                                }}</span>
                                <span class="rounded-md bg-indigo-50 px-1.5 py-0.5 text-indigo-700">{{ item.points }} poin</span>
                                <span
                                    v-if="item.labels.difficulty"
                                    :class="['rounded-md px-1.5 py-0.5 ring-1 ring-inset', DIFFICULTIES[item.labels.difficulty]?.class]"
                                >
                                    {{ DIFFICULTIES[item.labels.difficulty]?.label }}
                                </span>
                                <span v-if="item.labels.subject" class="rounded-md bg-sky-50 px-1.5 py-0.5 text-sky-700">{{
                                    item.labels.subject
                                }}</span>
                                <span v-if="item.labels.grade" class="rounded-md bg-violet-50 px-1.5 py-0.5 text-violet-700">{{
                                    gradeText(item.labels.grade)
                                }}</span>
                                <span v-if="item.labels.topic" class="rounded-md bg-teal-50 px-1.5 py-0.5 text-teal-700">{{
                                    item.labels.topic
                                }}</span>
                                <span v-if="item.hasAnswerKey" class="inline-flex items-center gap-0.5 text-emerald-700"
                                    ><KeyRound class="h-3 w-3" /> Kunci</span
                                >
                                <span v-if="item.mediaCount" class="inline-flex items-center gap-0.5 text-sky-700"
                                    ><ImageIcon class="h-3 w-3" /> {{ item.mediaCount }}</span
                                >
                            </div>
                            <p class="mt-1.5 text-sm font-semibold text-slate-800">{{ item.title }}</p>
                            <p v-if="item.options.length && !expanded.has(item.id)" class="mt-0.5 line-clamp-1 text-xs text-slate-500">
                                <template v-for="(option, index) in item.options" :key="index"
                                    ><span v-if="index > 0"> · </span>{{ String.fromCharCode(65 + index) }}. {{ option }}</template
                                >
                            </p>

                            <div v-if="expanded.has(item.id) && item.question" class="mt-3 space-y-2 rounded-xl bg-slate-50 p-3 text-sm">
                                <RichContent :content="item.question.title" class="block font-semibold text-slate-900" />
                                <RichContent v-if="item.question.description" :content="item.question.description" as="p" class="text-slate-600" />
                                <ol v-if="Array.isArray(item.question.options) && item.question.options.length" class="space-y-1">
                                    <li
                                        v-for="(option, index) in item.question.options"
                                        :key="index"
                                        :class="[
                                            'flex gap-2 rounded-lg px-2 py-1',
                                            isKeyOption(item, option, index) ? 'bg-emerald-100 font-semibold text-emerald-900' : 'text-slate-700',
                                        ]"
                                    >
                                        <span class="w-5 shrink-0 font-bold">{{ String.fromCharCode(65 + index) }}.</span>
                                        <RichContent :content="option" class="flex-1" />
                                        <span v-if="isKeyOption(item, option, index)" class="text-xs font-bold">Kunci</span>
                                    </li>
                                </ol>
                                <p v-if="Array.isArray(item.question.rows) && item.question.rows.length" class="text-xs text-slate-500">
                                    Baris: {{ item.question.rows.join(', ') }} · Kolom: {{ (item.question.columns ?? []).join(', ') }}
                                </p>
                                <p v-if="textKey(item)" class="rounded-lg bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-900">
                                    Kunci: {{ textKey(item) }}
                                </p>
                            </div>

                            <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-slate-400">
                                <span v-if="item.source">
                                    Dari:
                                    <a
                                        v-if="item.sourceUrl"
                                        :href="item.sourceUrl"
                                        class="inline-flex items-center gap-0.5 font-semibold text-indigo-600 hover:underline"
                                    >
                                        {{ item.source.title }} <ExternalLink class="h-3 w-3" />
                                    </a>
                                    <template v-else
                                        >{{ item.source.title }}<template v-if="item.source.trashed"> (di tempat sampah)</template></template
                                    >
                                </span>
                                <span>{{ formatDate(item.updatedAt) }}</span>
                            </div>
                        </div>
                        <div class="flex shrink-0 flex-col items-end gap-1.5">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-bold text-slate-600 hover:bg-slate-100"
                                @click="toggleExpanded(item)"
                            >
                                <ChevronDown :class="['h-3.5 w-3.5 transition', expanded.has(item.id) ? 'rotate-180' : '']" />
                                {{ expanded.has(item.id) ? 'Tutup' : 'Lihat' }}
                            </button>
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-bold text-slate-600 hover:bg-slate-100"
                                @click="openLabels([item])"
                            >
                                <Tag class="h-3.5 w-3.5" /> Label
                            </button>
                        </div>
                    </div>
                </li>
            </ul>

            <div v-if="hasMore && !loadError" class="mt-4 text-center">
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-xl bg-white px-4 py-2 text-sm font-bold text-slate-700 shadow-sm ring-1 ring-slate-200 hover:bg-slate-50 disabled:opacity-60"
                    :disabled="isLoading"
                    @click="load(currentPage + 1)"
                >
                    <Loader2 v-if="isLoading" class="h-4 w-4 animate-spin" />
                    Tampilkan lebih banyak
                </button>
            </div>
        </main>

        <!-- Actions on the ticked questions -->
        <div
            v-if="selected.size"
            class="fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white/95 px-4 py-3 shadow-[0_-8px_24px_rgba(15,23,42,0.08)] backdrop-blur"
        >
            <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-2">
                <span class="mr-auto text-sm font-bold text-slate-800">{{ selected.size }} soal dipilih</span>
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50"
                    @click="openLabels(selectedItems)"
                >
                    <Tag class="h-3.5 w-3.5" /> Beri label
                </button>
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 disabled:opacity-60"
                    :disabled="isArchiving"
                    @click="setArchived(selectedItems, !filters.archived)"
                >
                    <component :is="filters.archived ? ArchiveRestore : Archive" class="h-3.5 w-3.5" />
                    {{ filters.archived ? 'Kembalikan dari arsip' : 'Arsipkan' }}
                </button>
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-3 py-2 text-xs font-bold text-white hover:bg-indigo-700"
                    @click="examDialog.open = true"
                >
                    <FilePlus2 class="h-3.5 w-3.5" /> Buat ujian baru
                </button>
                <button
                    type="button"
                    class="rounded-xl px-2 py-2 text-xs font-bold text-slate-500 hover:text-slate-800"
                    @click="selected = new Map()"
                >
                    Batal
                </button>
            </div>
        </div>

        <!-- Label dialog -->
        <div
            v-if="labelDialog.open"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 p-4"
            @click.self="labelDialog.open = false"
        >
            <section
                class="w-full max-w-md space-y-4 rounded-3xl bg-white p-6 shadow-2xl"
                role="dialog"
                aria-modal="true"
                aria-labelledby="label-dialog-title"
            >
                <div>
                    <h2 id="label-dialog-title" class="text-lg font-extrabold text-slate-900">
                        {{ labelDialog.single ? 'Label soal' : `Label ${labelDialog.ids.length} soal` }}
                    </h2>
                    <p class="mt-1 text-xs text-slate-500">
                        {{
                            labelDialog.single
                                ? 'Kosongkan isian untuk menghapus label itu.'
                                : 'Hanya isian yang diisi yang diubah; yang kosong dibiarkan.'
                        }}
                        Label berlaku untuk soal yang sama di semua ujian.
                    </p>
                </div>
                <label class="block text-xs font-bold text-slate-700">
                    Tingkat kesulitan
                    <select
                        v-model="labelDialog.difficulty"
                        class="mt-1 h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-normal"
                    >
                        <option v-if="!labelDialog.single" value="keep">Tidak diubah</option>
                        <option v-if="!labelDialog.single" value="clear">Kosongkan</option>
                        <option v-if="labelDialog.single" value="">Belum ditentukan</option>
                        <option value="mudah">Mudah</option>
                        <option value="sedang">Sedang</option>
                        <option value="sulit">Sulit</option>
                    </select>
                </label>
                <label class="block text-xs font-bold text-slate-700">
                    Mapel
                    <input
                        v-model="labelDialog.subject"
                        list="bank-subjects"
                        maxlength="100"
                        placeholder="Contoh: IPA"
                        class="mt-1 h-10 w-full rounded-xl border border-slate-200 px-3 text-sm font-normal"
                    />
                </label>
                <label class="block text-xs font-bold text-slate-700">
                    Kelas
                    <input
                        v-model="labelDialog.grade"
                        list="bank-grades"
                        maxlength="50"
                        placeholder="Contoh: 8"
                        class="mt-1 h-10 w-full rounded-xl border border-slate-200 px-3 text-sm font-normal"
                    />
                </label>
                <label class="block text-xs font-bold text-slate-700">
                    Bab / Tujuan Pembelajaran
                    <input
                        v-model="labelDialog.topic"
                        list="bank-topics"
                        maxlength="150"
                        placeholder="Contoh: Fotosintesis"
                        class="mt-1 h-10 w-full rounded-xl border border-slate-200 px-3 text-sm font-normal"
                    />
                </label>
                <datalist id="bank-subjects"><option v-for="value in suggestions.subjects" :key="value" :value="value" /></datalist>
                <datalist id="bank-grades"><option v-for="value in suggestions.grades" :key="value" :value="value" /></datalist>
                <datalist id="bank-topics"><option v-for="value in suggestions.topics" :key="value" :value="value" /></datalist>
                <p v-if="labelDialog.error" class="rounded-xl bg-rose-50 p-3 text-xs font-semibold text-rose-700">{{ labelDialog.error }}</p>
                <div class="flex justify-end gap-2">
                    <button
                        type="button"
                        class="rounded-xl px-4 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-100"
                        @click="labelDialog.open = false"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        class="rounded-xl bg-indigo-600 px-4 py-2.5 text-xs font-bold text-white hover:bg-indigo-700 disabled:opacity-60"
                        :disabled="labelDialog.saving"
                        @click="saveLabels"
                    >
                        {{ labelDialog.saving ? 'Menyimpan...' : 'Simpan label' }}
                    </button>
                </div>
            </section>
        </div>

        <!-- New exam dialog -->
        <div
            v-if="examDialog.open"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 p-4"
            @click.self="examDialog.open = false"
        >
            <section
                class="w-full max-w-md space-y-4 rounded-3xl bg-white p-6 shadow-2xl"
                role="dialog"
                aria-modal="true"
                aria-labelledby="exam-dialog-title"
            >
                <div>
                    <h2 id="exam-dialog-title" class="text-lg font-extrabold text-slate-900">Buat ujian dari {{ selected.size }} soal</h2>
                    <p class="mt-1 text-xs text-slate-500">Soal disalin ke ujian baru (belum dipublikasikan) lalu dibuka di editor.</p>
                </div>
                <label class="block text-xs font-bold text-slate-700">
                    Judul ujian
                    <input
                        v-model="examDialog.title"
                        maxlength="255"
                        class="mt-1 h-10 w-full rounded-xl border border-slate-200 px-3 text-sm font-normal"
                    />
                </label>
                <div class="flex justify-end gap-2">
                    <button
                        type="button"
                        class="rounded-xl px-4 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-100"
                        @click="examDialog.open = false"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        class="rounded-xl bg-indigo-600 px-4 py-2.5 text-xs font-bold text-white hover:bg-indigo-700 disabled:opacity-60"
                        :disabled="examDialog.saving"
                        @click="createExam"
                    >
                        {{ examDialog.saving ? 'Membuat...' : 'Buat ujian' }}
                    </button>
                </div>
            </section>
        </div>
    </AlsenformLayout>
</template>
