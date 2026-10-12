<script setup lang="ts">
import { useDebounceFn } from '@vueuse/core';
import axios from 'axios';
import { Check, Image as ImageIcon, KeyRound, Library, Loader2, Search } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

type BankItem = {
    id: number;
    type: string;
    title: string;
    description: string;
    options: string[];
    optionCount: number;
    points: number;
    hasAnswerKey: boolean;
    mediaCount: number;
    source: { title: string; trashed: boolean } | null;
    updatedAt: string | null;
    inThisExam: boolean;
};

const props = defineProps<{
    /** The exam being edited, to point out the questions it already has. */
    examId?: number;
}>();

const emit = defineEmits<{
    add: [questions: Record<string, any>[]];
}>();

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
const typeLabel = (type: string) => TYPE_LABELS[type] ?? type;

const search = ref('');
const typeFilter = ref('');
const formFilter = ref<number | ''>('');
const items = ref<BankItem[]>([]);
const total = ref(0);
const currentPage = ref(0);
const hasMore = ref(false);
const types = ref<string[]>([]);
const forms = ref<{ id: number; title: string }[]>([]);
const isLoading = ref(false);
const isAdding = ref(false);
const loadError = ref('');
const addError = ref('');
// Kept across searches and pages, in the order the teacher ticked them.
const selected = ref<Map<number, BankItem>>(new Map());
let hasSynced = false;
let requestCounter = 0;

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
    if (allShownSelected.value) {
        items.value.forEach((item) => next.delete(item.id));
    } else {
        items.value.forEach((item) => next.set(item.id, item));
    }
    selected.value = next;
};

const load = async (page = 1) => {
    const request = ++requestCounter;
    isLoading.value = true;
    loadError.value = '';
    try {
        const response = await axios.get(route('question-bank.index'), {
            params: {
                search: search.value.trim() || undefined,
                type: typeFilter.value || undefined,
                form: formFilter.value || undefined,
                exam: props.examId,
                page,
                sync: hasSynced ? undefined : 1,
            },
        });
        // A newer search was started meanwhile: its answer is the one shown.
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

const searchSoon = useDebounceFn(() => load(1), 300);
watch(search, () => searchSoon());
watch([typeFilter, formFilter], () => load(1));

const addSelected = async () => {
    if (selected.value.size === 0 || isAdding.value) {
        return;
    }
    isAdding.value = true;
    addError.value = '';
    try {
        const response = await axios.post(route('question-bank.questions'), { ids: [...selected.value.keys()] });
        const questions = Array.isArray(response.data.questions) ? response.data.questions : [];
        if (questions.length === 0) {
            addError.value = 'Soal yang dipilih tidak ditemukan lagi di bank soal.';
            return;
        }
        emit('add', questions);
        selected.value = new Map();
    } catch {
        addError.value = 'Soal gagal ditambahkan. Periksa koneksi lalu coba lagi.';
    } finally {
        isAdding.value = false;
    }
};

const formatDate = (value: string | null) =>
    value ? new Date(value).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) : '';

onMounted(() => load(1));
</script>

<template>
    <div class="space-y-4">
        <p class="text-sm leading-relaxed text-slate-500">
            Setiap soal yang Anda tulis di ujian mana pun tersimpan otomatis di sini sebagai cadangan, juga setelah soalnya atau ujiannya dihapus.
            Centang soal yang ingin dipakai lagi.
        </p>

        <div class="grid gap-2 sm:grid-cols-2">
            <label class="relative block sm:col-span-2">
                <span class="sr-only">Cari soal</span>
                <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input
                    v-model="search"
                    type="search"
                    placeholder="Cari teks soal atau pilihan jawaban..."
                    class="h-10 w-full rounded-xl border border-slate-200 bg-white pl-9 pr-3 text-sm text-slate-800 outline-none transition focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100"
                />
            </label>
            <select
                v-model="typeFilter"
                aria-label="Tipe soal"
                class="h-10 rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none focus:border-indigo-400"
            >
                <option value="">Semua tipe</option>
                <option v-for="type in types" :key="type" :value="type">{{ typeLabel(type) }}</option>
            </select>
            <select
                v-model="formFilter"
                aria-label="Asal ujian"
                class="h-10 min-w-0 rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none focus:border-indigo-400"
            >
                <option value="">Semua ujian</option>
                <option v-for="exam in forms" :key="exam.id" :value="exam.id">{{ exam.title }}</option>
            </select>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500">
            <label class="inline-flex cursor-pointer items-center gap-2 font-semibold text-slate-700" :class="{ 'opacity-50': items.length === 0 }">
                <input
                    type="checkbox"
                    class="h-4 w-4 rounded border-slate-300 text-indigo-600"
                    :checked="allShownSelected"
                    :disabled="items.length === 0"
                    @change="toggleAllShown"
                />
                Pilih semua yang tampil
            </label>
            <span
                >{{ total }} soal<template v-if="selected.size">
                    · <strong class="text-indigo-700">{{ selected.size }} dipilih</strong></template
                ></span
            >
        </div>

        <div class="max-h-[46vh] overflow-y-auto rounded-2xl border border-slate-200">
            <div v-if="loadError" class="p-6 text-center text-sm font-semibold text-rose-600">
                {{ loadError }}
                <button type="button" class="ml-1 underline" @click="load(1)">Coba lagi</button>
            </div>

            <div v-else-if="isLoading && items.length === 0" class="space-y-2 p-3" aria-label="Memuat bank soal">
                <div v-for="placeholder in 4" :key="placeholder" class="h-16 animate-pulse rounded-xl bg-slate-100"></div>
            </div>

            <div v-else-if="items.length === 0" class="px-6 py-10 text-center">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-indigo-50 text-indigo-500">
                    <Library class="h-6 w-6" />
                </div>
                <p class="mt-3 text-sm font-semibold text-slate-700">
                    {{ search || typeFilter || formFilter ? 'Tidak ada soal yang cocok.' : 'Bank soal masih kosong.' }}
                </p>
                <p v-if="!(search || typeFilter || formFilter)" class="mt-1 text-xs text-slate-500">
                    Soal yang Anda tulis di ujian akan muncul di sini.
                </p>
            </div>

            <ul v-else class="divide-y divide-slate-100">
                <li v-for="item in items" :key="item.id">
                    <label :class="['flex cursor-pointer gap-3 px-4 py-3 transition', isSelected(item) ? 'bg-indigo-50/70' : 'hover:bg-slate-50']">
                        <input
                            type="checkbox"
                            class="mt-1 h-4 w-4 shrink-0 rounded border-slate-300 text-indigo-600"
                            :checked="isSelected(item)"
                            @change="toggle(item)"
                        />
                        <span class="min-w-0 flex-1">
                            <span class="flex flex-wrap items-center gap-1.5">
                                <span class="rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-slate-600">{{
                                    typeLabel(item.type)
                                }}</span>
                                <span class="rounded-md bg-indigo-50 px-1.5 py-0.5 text-[10px] font-bold text-indigo-700"
                                    >{{ item.points }} poin</span
                                >
                                <span
                                    v-if="item.hasAnswerKey"
                                    class="inline-flex items-center gap-0.5 rounded-md bg-emerald-50 px-1.5 py-0.5 text-[10px] font-bold text-emerald-700"
                                >
                                    <KeyRound class="h-3 w-3" /> Kunci
                                </span>
                                <span
                                    v-if="item.mediaCount"
                                    class="inline-flex items-center gap-0.5 rounded-md bg-sky-50 px-1.5 py-0.5 text-[10px] font-bold text-sky-700"
                                >
                                    <ImageIcon class="h-3 w-3" /> {{ item.mediaCount }}
                                </span>
                                <span v-if="item.inThisExam" class="rounded-md bg-amber-50 px-1.5 py-0.5 text-[10px] font-bold text-amber-700"
                                    >Sudah ada di ujian ini</span
                                >
                            </span>
                            <span class="mt-1 line-clamp-2 block text-sm font-semibold text-slate-800">{{ item.title }}</span>
                            <span v-if="item.options.length" class="mt-0.5 line-clamp-1 block text-xs text-slate-500">
                                <template v-for="(option, index) in item.options" :key="index">
                                    <span v-if="index > 0"> · </span>{{ String.fromCharCode(65 + index) }}. {{ option }}
                                </template>
                                <span v-if="item.optionCount > item.options.length"> · …</span>
                            </span>
                            <span class="mt-1 block text-[11px] text-slate-400">
                                <template v-if="item.source"
                                    >Dari: {{ item.source.title }}<template v-if="item.source.trashed"> (di tempat sampah)</template> · </template
                                >{{ formatDate(item.updatedAt) }}
                            </span>
                        </span>
                        <Check v-if="isSelected(item)" class="mt-1 h-4 w-4 shrink-0 text-indigo-600" />
                    </label>
                </li>
            </ul>

            <div v-if="hasMore && !loadError" class="border-t border-slate-100 p-3 text-center">
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-xl bg-slate-100 px-4 py-2 text-xs font-bold text-slate-700 transition hover:bg-slate-200 disabled:opacity-60"
                    :disabled="isLoading"
                    @click="load(currentPage + 1)"
                >
                    <Loader2 v-if="isLoading" class="h-3.5 w-3.5 animate-spin" />
                    Tampilkan lebih banyak
                </button>
            </div>
        </div>

        <p v-if="addError" class="rounded-xl border border-red-100 bg-red-50/50 p-3 text-xs font-semibold text-red-600">{{ addError }}</p>

        <button
            type="button"
            class="flex w-full items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-3 text-sm font-bold text-white shadow transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:bg-slate-300"
            :disabled="selected.size === 0 || isAdding"
            @click="addSelected"
        >
            <Loader2 v-if="isAdding" class="h-4 w-4 animate-spin" />
            {{ selected.size ? `Tambahkan ${selected.size} soal ke ujian ini` : 'Pilih soal yang ingin ditambahkan' }}
        </button>
    </div>
</template>
