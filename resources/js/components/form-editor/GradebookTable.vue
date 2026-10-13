<script setup lang="ts">
import type { QuizFormPayload } from '@/types/quiz';
import { ArrowDown, ArrowUp, ChevronsUpDown, Download, ListFilter, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

type GradebookRow = NonNullable<NonNullable<QuizFormPayload['responses']>['gradebook']>[number];
type SortKey = 'nis' | 'firstName' | 'lastName' | 'kelas' | 'score' | 'percentage' | 'status' | 'submittedAt';
type SortDirection = 'asc' | 'desc';

const props = defineProps<{
    rows: GradebookRow[];
    maxScore: number;
    exportUrl: string;
}>();

const LETTERS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('');
const collator = new Intl.Collator('id', { sensitivity: 'base', numeric: true });

const search = ref('');
const sortKey = ref<SortKey>('submittedAt');
const sortDirection = ref<SortDirection>('desc');
// Like Moodle: the students whose first or last name starts with a letter.
const firstInitial = ref<string | null>(null);
const lastInitial = ref<string | null>(null);
const isInitialsPanelOpen = ref(false);

const autoSubmittedHint = 'Waktu habis saat halaman ujian tertutup atau offline. Jawaban yang sudah tersimpan dikirim otomatis oleh sistem.';
const statusLabel = (row: GradebookRow) =>
    row.is_auto_submitted ? 'Otomatis' : row.lateMinutes ? `Terlambat ${row.lateMinutes} mnt` : row.is_timeout ? 'Timeout' : 'Selesai';

/** First and last word of the name ("Siti Nur Aisyah": Siti, Aisyah); one word is both. */
const nameParts = (name: string) => {
    const words = name.trim().split(/\s+/).filter(Boolean);
    return { first: words[0] ?? '', last: words[words.length - 1] ?? '' };
};

/** The letter a name is filed under: "Élsa" under E; a name starting with a digit under none. */
const initialOf = (word: string): string | null => {
    const letter = word.normalize('NFD').replace(/[̀-ͯ]/g, '').charAt(0).toUpperCase();
    return /^[A-Z]$/.test(letter) ? letter : null;
};

const searched = computed(() => {
    const query = search.value.toLowerCase().trim();
    if (!query) {
        return props.rows;
    }
    return props.rows.filter((row) => [row.name, row.nis, row.kelas, row.email].some((value) => (value ?? '').toLowerCase().includes(query)));
});

const matchesInitials = (row: GradebookRow, first: string | null, last: string | null) => {
    const parts = nameParts(row.name);
    return (first === null || initialOf(parts.first) === first) && (last === null || initialOf(parts.last) === last);
};

/** Letters with at least one student, given the other letter already chosen (the rest are greyed out). */
const availableFirstInitials = computed(
    () => new Set(searched.value.filter((row) => matchesInitials(row, null, lastInitial.value)).map((row) => initialOf(nameParts(row.name).first))),
);
const availableLastInitials = computed(
    () => new Set(searched.value.filter((row) => matchesInitials(row, firstInitial.value, null)).map((row) => initialOf(nameParts(row.name).last))),
);

const initialBars = computed(() => [
    { key: 'first' as const, label: 'Nama depan', selected: firstInitial.value, available: availableFirstInitials.value },
    { key: 'last' as const, label: 'Nama belakang', selected: lastInitial.value, available: availableLastInitials.value },
]);

/** Pick a letter (or all, with null); the chosen letter again takes the filter off. */
const chooseInitial = (bar: 'first' | 'last', letter: string | null) => {
    const current = bar === 'first' ? firstInitial : lastInitial;
    current.value = letter !== null && current.value === letter ? null : letter;
};

const compareRows = (a: GradebookRow, b: GradebookRow): number => {
    switch (sortKey.value) {
        case 'nis':
            return collator.compare(a.nis, b.nis);
        case 'firstName':
            return collator.compare(a.name, b.name);
        case 'lastName':
            return collator.compare(nameParts(a.name).last, nameParts(b.name).last) || collator.compare(a.name, b.name);
        case 'kelas':
            return collator.compare(a.kelas, b.kelas);
        case 'score':
            return a.score - b.score || (a.maxScore ?? props.maxScore) - (b.maxScore ?? props.maxScore);
        case 'percentage':
            return a.percentage - b.percentage;
        case 'status':
            return collator.compare(statusLabel(a), statusLabel(b));
        case 'submittedAt':
            return (a.submittedAtTimestamp ?? a.id) - (b.submittedAtTimestamp ?? b.id);
    }
};

const visibleRows = computed(() => {
    const direction = sortDirection.value === 'asc' ? 1 : -1;
    return searched.value
        .filter((row) => matchesInitials(row, firstInitial.value, lastInitial.value))
        .slice()
        .sort((a, b) => direction * compareRows(a, b) || collator.compare(a.name, b.name) || a.id - b.id);
});

/** First click sorts ascending (A–Z, lowest first), the next one descending. */
const sortBy = (key: SortKey) => {
    if (sortKey.value === key) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
        return;
    }
    sortKey.value = key;
    sortDirection.value = 'asc';
};

const ariaSort = (key: SortKey) => (sortKey.value !== key ? 'none' : sortDirection.value === 'asc' ? 'ascending' : 'descending');

const SORT_OPTIONS: { value: string; label: string }[] = [
    { value: 'submittedAt:desc', label: 'Terbaru dikirim' },
    { value: 'submittedAt:asc', label: 'Terlama dikirim' },
    { value: 'firstName:asc', label: 'Nama depan A–Z' },
    { value: 'firstName:desc', label: 'Nama depan Z–A' },
    { value: 'lastName:asc', label: 'Nama belakang A–Z' },
    { value: 'lastName:desc', label: 'Nama belakang Z–A' },
    { value: 'percentage:desc', label: 'Nilai tertinggi' },
    { value: 'percentage:asc', label: 'Nilai terendah' },
    { value: 'score:desc', label: 'Poin terbanyak' },
    { value: 'score:asc', label: 'Poin tersedikit' },
    { value: 'kelas:asc', label: 'Kelas A–Z' },
    { value: 'kelas:desc', label: 'Kelas Z–A' },
    { value: 'nis:asc', label: 'NIS terkecil' },
    { value: 'nis:desc', label: 'NIS terbesar' },
    { value: 'status:asc', label: 'Status A–Z' },
    { value: 'status:desc', label: 'Status Z–A' },
];
const sortChoice = computed({
    get: () => `${sortKey.value}:${sortDirection.value}`,
    set: (value: string) => {
        const [key, direction] = value.split(':') as [SortKey, SortDirection];
        sortKey.value = key;
        sortDirection.value = direction;
    },
});

const activeInitialsCount = computed(() => (firstInitial.value ? 1 : 0) + (lastInitial.value ? 1 : 0));
const isFiltered = computed(() => activeInitialsCount.value > 0 || search.value.trim() !== '');
const resetFilters = () => {
    firstInitial.value = null;
    lastInitial.value = null;
    search.value = '';
};

const gradeTone = (percentage: number) =>
    percentage >= 75 ? 'bg-emerald-100 text-emerald-800' : percentage >= 60 ? 'bg-amber-100 text-amber-800' : 'bg-rose-100 text-rose-800';

const COLUMNS: { key: SortKey; label: string; align?: 'center'; title?: string }[] = [
    { key: 'nis', label: 'NIS' },
    { key: 'kelas', label: 'Kelas / Cohort' },
    { key: 'score', label: 'Poin Benar', align: 'center' },
    { key: 'percentage', label: 'Nilai (0-100)', align: 'center', title: 'Poin benar ÷ total poin soal berkunci × 100' },
    { key: 'status', label: 'Status' },
    { key: 'submittedAt', label: 'Waktu Selesai' },
];
const column = (key: SortKey) => COLUMNS.find((entry) => entry.key === key)!;
// Sortable columns after the name column.
const COLUMNS_AFTER_NAME: SortKey[] = ['kelas', 'score', 'percentage', 'status', 'submittedAt'];
</script>

<template>
    <div class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-slate-50/70 px-5 py-3.5">
            <div>
                <h3 class="text-base font-bold text-slate-900">Rekapitulasi Nilai Siswa</h3>
                <p class="text-xs text-slate-500">Daftar skor dan status pengerjaan seluruh responden.</p>
            </div>
            <div class="flex w-full items-center gap-2 sm:w-auto">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Cari siswa / NIS / kelas..."
                    aria-label="Cari siswa"
                    class="w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-800 placeholder-slate-400 focus:border-indigo-500 focus:outline-none sm:w-auto sm:py-1.5"
                />
                <button
                    type="button"
                    :class="[
                        'inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-xl border px-3 py-2 text-xs font-bold transition sm:py-1.5',
                        isInitialsPanelOpen || activeInitialsCount
                            ? 'border-indigo-200 bg-indigo-50 text-indigo-700'
                            : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50',
                    ]"
                    :aria-expanded="isInitialsPanelOpen"
                    aria-controls="gradebook-initials"
                    @click="isInitialsPanelOpen = !isInitialsPanelOpen"
                >
                    <ListFilter class="h-3.5 w-3.5" />
                    A–Z
                    <span
                        v-if="activeInitialsCount"
                        class="inline-flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-indigo-600 px-1 text-[10px] text-white"
                        >{{ activeInitialsCount }}</span
                    >
                </button>
                <a
                    v-if="rows.length"
                    :href="exportUrl"
                    class="hidden shrink-0 items-center gap-1.5 whitespace-nowrap rounded-xl bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white shadow-sm transition hover:bg-emerald-700 sm:inline-flex"
                >
                    <Download class="h-3.5 w-3.5" />
                    Unduh Excel
                </a>
            </div>
        </div>

        <!-- Initials (Moodle style): hidden behind the A–Z button so the table stays tidy -->
        <div v-if="isInitialsPanelOpen" id="gradebook-initials" class="space-y-2.5 border-b border-slate-200 bg-white px-5 py-3.5">
            <div v-for="bar in initialBars" :key="bar.key" class="flex flex-col gap-1.5 lg:flex-row lg:items-center lg:gap-3">
                <span class="w-28 shrink-0 text-[11px] font-extrabold uppercase tracking-wider text-slate-500">{{ bar.label }}</span>
                <div class="flex flex-wrap gap-1" role="group" :aria-label="`Saring ${bar.label.toLowerCase()} berdasarkan huruf awal`">
                    <button
                        type="button"
                        :class="[
                            'h-7 rounded-lg px-2 text-[11px] font-bold transition',
                            bar.selected === null ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200',
                        ]"
                        :aria-pressed="bar.selected === null"
                        @click="chooseInitial(bar.key, null)"
                    >
                        Semua
                    </button>
                    <button
                        v-for="letter in LETTERS"
                        :key="letter"
                        type="button"
                        :disabled="!bar.available.has(letter) && bar.selected !== letter"
                        :class="[
                            'h-7 w-7 rounded-lg text-[11px] font-bold transition',
                            bar.selected === letter
                                ? 'bg-indigo-600 text-white'
                                : bar.available.has(letter)
                                  ? 'bg-slate-100 text-slate-700 hover:bg-indigo-100 hover:text-indigo-700'
                                  : 'cursor-default text-slate-300',
                        ]"
                        :aria-pressed="bar.selected === letter"
                        :aria-label="`${bar.label} berawalan ${letter}`"
                        @click="chooseInitial(bar.key, letter)"
                    >
                        {{ letter }}
                    </button>
                </div>
            </div>
        </div>

        <!-- What is applied, and how many students are shown -->
        <div
            v-if="isFiltered || rows.length"
            class="flex flex-wrap items-center gap-2 border-b border-slate-100 px-5 py-2 text-[11px] font-semibold text-slate-500"
        >
            <span>Menampilkan {{ visibleRows.length }} dari {{ rows.length }} jawaban</span>
            <button
                v-if="firstInitial"
                type="button"
                class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2 py-0.5 font-bold text-indigo-700"
                :aria-label="`Hapus saringan nama depan ${firstInitial}`"
                @click="firstInitial = null"
            >
                Nama depan: {{ firstInitial }} <X class="h-3 w-3" />
            </button>
            <button
                v-if="lastInitial"
                type="button"
                class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2 py-0.5 font-bold text-indigo-700"
                :aria-label="`Hapus saringan nama belakang ${lastInitial}`"
                @click="lastInitial = null"
            >
                Nama belakang: {{ lastInitial }} <X class="h-3 w-3" />
            </button>
            <button v-if="isFiltered" type="button" class="font-bold text-indigo-600 hover:underline" @click="resetFilters">Reset</button>

            <!-- Phones have no column headers: the order is picked here -->
            <label class="ml-auto flex items-center gap-1.5 sm:hidden">
                Urutkan
                <select v-model="sortChoice" class="rounded-lg border border-slate-200 bg-white py-1 pl-2 pr-7 text-[11px] font-bold text-slate-700">
                    <option v-for="option in SORT_OPTIONS" :key="option.value" :value="option.value">{{ option.label }}</option>
                </select>
            </label>
        </div>

        <!-- Phones: one card per student instead of a wide table -->
        <ul class="divide-y divide-slate-100 sm:hidden">
            <li v-for="(row, index) in visibleRows" :key="`grade-card-${row.id}`" class="flex items-center gap-3 px-4 py-3">
                <span class="w-5 shrink-0 text-xs font-semibold text-slate-400">{{ index + 1 }}</span>
                <div class="min-w-0 flex-1">
                    <p class="flex min-w-0 items-center gap-1.5">
                        <span class="truncate text-sm font-bold text-slate-900">{{ row.name }}</span>
                        <span v-if="row.isGuest" class="shrink-0 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-500">Tamu</span>
                        <span
                            v-if="row.duplicateName"
                            class="shrink-0 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold text-amber-800"
                            title="Nama dan kelas yang sama mengirim lebih dari satu jawaban"
                            >Nama ganda</span
                        >
                        <span
                            v-if="(row.attempts ?? 1) > 1"
                            class="shrink-0 rounded bg-indigo-50 px-1.5 py-0.5 text-[10px] font-bold text-indigo-700"
                            :title="`Percobaan ke-${row.attempt} dari ${row.attempts} percobaan siswa ini`"
                            >Percobaan {{ row.attempt }}/{{ row.attempts }}</span
                        >
                    </p>
                    <p class="truncate text-[11px] text-slate-500">
                        <span class="font-mono">{{ row.nis }}</span> · {{ row.kelas }} · {{ row.formattedDate }}
                    </p>
                </div>
                <div class="shrink-0 text-right">
                    <span :class="['inline-block rounded-full px-2.5 py-0.5 text-xs font-bold', gradeTone(row.percentage)]">{{
                        row.percentage
                    }}</span>
                    <p class="mt-0.5 text-[11px] font-semibold text-indigo-600">{{ row.score }} / {{ row.maxScore ?? maxScore }} poin</p>
                    <p
                        :class="['text-[10px] font-extrabold uppercase', row.is_timeout ? 'text-rose-600' : 'text-emerald-600']"
                        :title="row.is_auto_submitted ? autoSubmittedHint : undefined"
                    >
                        {{ statusLabel(row) }}
                    </p>
                </div>
            </li>
            <li v-if="visibleRows.length === 0" class="px-4 py-8 text-center text-xs text-slate-400">
                {{
                    rows.length === 0
                        ? 'Belum ada tanggapan masuk untuk kuis ini.'
                        : 'Tidak ada siswa yang cocok dengan pencarian atau huruf yang dipilih.'
                }}
            </li>
        </ul>

        <div class="hidden overflow-x-auto sm:block">
            <table class="w-full text-left text-xs text-slate-600">
                <thead class="border-b border-slate-200 bg-slate-50 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-4 py-3">No</th>
                        <th class="px-4 py-3" :aria-sort="ariaSort('nis')">
                            <button type="button" class="inline-flex items-center gap-1 uppercase hover:text-indigo-700" @click="sortBy('nis')">
                                NIS
                                <ArrowUp v-if="sortKey === 'nis' && sortDirection === 'asc'" class="h-3 w-3 text-indigo-600" />
                                <ArrowDown v-else-if="sortKey === 'nis'" class="h-3 w-3 text-indigo-600" />
                                <ChevronsUpDown v-else class="h-3 w-3 opacity-40" />
                            </button>
                        </th>
                        <!-- Like Moodle: one column, sortable by first or by last name -->
                        <th class="px-4 py-3" :aria-sort="sortKey === 'firstName' || sortKey === 'lastName' ? ariaSort(sortKey) : 'none'">
                            <span class="inline-flex flex-wrap items-center gap-x-1.5">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 whitespace-nowrap uppercase hover:text-indigo-700"
                                    :class="{ 'text-indigo-700': sortKey === 'firstName' }"
                                    aria-label="Urutkan menurut nama depan"
                                    @click="sortBy('firstName')"
                                >
                                    Nama depan
                                    <ArrowUp v-if="sortKey === 'firstName' && sortDirection === 'asc'" class="h-3 w-3 text-indigo-600" />
                                    <ArrowDown v-else-if="sortKey === 'firstName'" class="h-3 w-3 text-indigo-600" />
                                    <ChevronsUpDown v-else class="h-3 w-3 opacity-40" />
                                </button>
                                <span class="text-slate-300">/</span>
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 whitespace-nowrap uppercase hover:text-indigo-700"
                                    :class="{ 'text-indigo-700': sortKey === 'lastName' }"
                                    aria-label="Urutkan menurut nama belakang"
                                    @click="sortBy('lastName')"
                                >
                                    Belakang
                                    <ArrowUp v-if="sortKey === 'lastName' && sortDirection === 'asc'" class="h-3 w-3 text-indigo-600" />
                                    <ArrowDown v-else-if="sortKey === 'lastName'" class="h-3 w-3 text-indigo-600" />
                                    <ChevronsUpDown v-else class="h-3 w-3 opacity-40" />
                                </button>
                            </span>
                        </th>
                        <th
                            v-for="key in COLUMNS_AFTER_NAME"
                            :key="key"
                            :class="['px-4 py-3', column(key).align === 'center' ? 'text-center' : '']"
                            :aria-sort="ariaSort(key)"
                            :title="column(key).title"
                        >
                            <button type="button" class="inline-flex items-center gap-1 uppercase hover:text-indigo-700" @click="sortBy(key)">
                                {{ column(key).label }}
                                <ArrowUp v-if="sortKey === key && sortDirection === 'asc'" class="h-3 w-3 text-indigo-600" />
                                <ArrowDown v-else-if="sortKey === key" class="h-3 w-3 text-indigo-600" />
                                <ChevronsUpDown v-else class="h-3 w-3 opacity-40" />
                            </button>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="(row, index) in visibleRows" :key="row.id" class="transition-colors hover:bg-slate-50/80">
                        <td class="px-4 py-3 font-semibold text-slate-400">{{ index + 1 }}</td>
                        <td class="px-4 py-3 font-mono font-medium text-slate-700">{{ row.nis }}</td>
                        <td class="px-4 py-3 font-bold text-slate-900">
                            {{ row.name }}
                            <span v-if="row.isGuest" class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-500">Tamu</span>
                            <span
                                v-if="row.duplicateName"
                                class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold text-amber-800"
                                title="Nama dan kelas yang sama mengirim lebih dari satu jawaban"
                                >Nama ganda</span
                            >
                            <span
                                v-if="(row.attempts ?? 1) > 1"
                                class="ml-1 whitespace-nowrap rounded bg-indigo-50 px-1.5 py-0.5 text-[10px] font-bold text-indigo-700"
                                :title="`Percobaan ke-${row.attempt} dari ${row.attempts} percobaan siswa ini`"
                                >Percobaan {{ row.attempt }}/{{ row.attempts }}</span
                            >
                        </td>
                        <td class="px-4 py-3">
                            <span class="rounded-lg bg-slate-100 px-2 py-0.5 font-medium text-slate-700">{{ row.kelas }}</span>
                        </td>
                        <td class="px-4 py-3 text-center font-bold text-indigo-600">{{ row.score }} / {{ row.maxScore ?? maxScore }}</td>
                        <td class="px-4 py-3 text-center">
                            <span :class="['inline-block rounded-full px-2.5 py-0.5 font-bold', gradeTone(row.percentage)]">{{
                                row.percentage
                            }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <span
                                :class="[
                                    'whitespace-nowrap rounded-full px-2 py-0.5 text-[10px] font-extrabold uppercase',
                                    row.is_timeout
                                        ? 'border border-rose-200 bg-rose-50 text-rose-700'
                                        : 'border border-emerald-200 bg-emerald-50 text-emerald-700',
                                ]"
                                :title="row.is_auto_submitted ? autoSubmittedHint : undefined"
                            >
                                {{ statusLabel(row) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-slate-400">{{ row.formattedDate }}</td>
                    </tr>
                    <tr v-if="visibleRows.length === 0">
                        <td colspan="8" class="py-8 text-center text-slate-400">
                            {{
                                rows.length === 0
                                    ? 'Belum ada tanggapan masuk untuk kuis ini.'
                                    : 'Tidak ada siswa yang cocok dengan pencarian atau huruf yang dipilih.'
                            }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
