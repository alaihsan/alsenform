<script setup lang="ts">
import {
    applyView,
    distinctValues,
    formatDateTime,
    formatNumber,
    isFilterActive,
    MARK_LABELS,
    markStats,
    normalize,
    numberStats,
    prepareRows,
    sanitizeView,
    toCsv,
    type ColumnFilter,
    type Filters,
    type GridColumn,
    type GridPayload,
    type GridRow,
    type Mark,
    type Sort,
} from '@/lib/smartFilter';
import axios from 'axios';
import { ArrowDown, ArrowUp, Columns3, Download, FileSpreadsheet, Filter, ListFilter, Loader2, RefreshCw, Search, X } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue';

/*
 * Filter Cerdas add-on: the results of an exam in a full-screen Excel mode. Find in every cell,
 * filter and sort any column, hide columns, download what is shown. Only the rows on screen are
 * drawn and the table itself is never made reactive, so hundreds of students stay smooth. The
 * find, filters, order and hidden columns are remembered on this device for each exam.
 */
const props = defineProps<{
    url: string;
    title: string;
    exportUrl?: string | null;
    /** Where this exam's view is remembered (localStorage). */
    storageKey: string;
}>();

const emit = defineEmits<{
    close: [];
}>();

const ROW_HEIGHT = 34;
const HEADER_HEIGHT = 52;
const OVERSCAN = 12;
const MAX_VALUES_SHOWN = 300;
/** Kept in view while the table scrolls sideways, like Excel's frozen panes. */
const FROZEN = ['no', 'name'];

const MARK_CLASSES: Record<Mark, string> = {
    correct: 'bg-emerald-50 text-emerald-900',
    wrong: 'bg-rose-50 text-rose-900',
    ungraded: 'bg-slate-50 text-slate-700',
    absent: 'bg-slate-100 italic text-slate-400',
};
const MARK_CHIPS: Record<Mark, string> = {
    correct: 'border-emerald-300 bg-emerald-50 text-emerald-800',
    wrong: 'border-rose-300 bg-rose-50 text-rose-800',
    ungraded: 'border-slate-300 bg-slate-50 text-slate-700',
    absent: 'border-slate-300 bg-slate-100 text-slate-500',
};
const MARKS = Object.keys(MARK_LABELS) as Mark[];

const columns = shallowRef<GridColumn[]>([]);
const rows = shallowRef<GridRow[]>([]);
const isLoading = ref(true);
const loadError = ref('');
let hasLoaded = false;

const findDraft = ref('');
const find = ref('');
const sort = ref<Sort>(null);
const filters = ref<Filters>({});
const hidden = ref<string[]>([]);
const selected = ref<{ id: number; key: string } | null>(null);

const isNarrow = ref(typeof window !== 'undefined' && window.innerWidth < 640);
const widthOf = (column: GridColumn): number =>
    (
        ({
            no: 52,
            name: isNarrow.value ? 140 : 210,
            nis: 120,
            kelas: 96,
            email: 200,
            grade: 96,
            score: 76,
            maxScore: 100,
            result: 120,
            status: 190,
            submittedAt: 140,
            attempt: 100,
        }) as Record<string, number>
    )[column.key] ?? (column.type === 'answer' ? 220 : 140);

type ShownColumn = GridColumn & { index: number; width: number; left: number | null };

/** The columns not hidden, with their width and, for the frozen ones at the start, their offset. */
const shownColumns = computed<ShownColumn[]>(() => {
    const hiddenKeys = new Set(hidden.value);
    let left = 0;
    let isFrozenRun = true;

    return columns.value.flatMap((column, index) => {
        if (hiddenKeys.has(column.key)) {
            return [];
        }
        const width = widthOf(column);
        const isFrozen = isFrozenRun && FROZEN.includes(column.key);
        isFrozenRun = isFrozen;
        const shown: ShownColumn = { ...column, index, width, left: isFrozen ? left : null };
        if (isFrozen) {
            left += width;
        }
        return [shown];
    });
});
const shownIndexes = computed(() => shownColumns.value.map((column) => column.index));
const tableWidth = computed(() => shownColumns.value.reduce((sum, column) => sum + column.width, 0));
const columnIndex = (key: string) => columns.value.findIndex((column) => column.key === key);

/** The rows passing the find and the filters, in the chosen order. */
const viewRows = computed(() =>
    applyView(rows.value, columns.value, { find: find.value, sort: sort.value, filters: filters.value }, shownIndexes.value),
);
const findKey = computed(() => normalize(find.value.trim()));

/* Only the rows in view (and a few around them) are drawn. */
const scroller = ref<HTMLElement | null>(null);
const scrollTop = ref(0);
const viewportHeight = ref(600);
let scrollFrame = 0;
const windowRange = computed(() => {
    const total = viewRows.value.length;
    const count = Math.ceil(viewportHeight.value / ROW_HEIGHT) + OVERSCAN * 2;
    const first = Math.max(0, Math.floor((scrollTop.value - HEADER_HEIGHT) / ROW_HEIGHT) - OVERSCAN);
    const start = Math.max(0, Math.min(first, total - count));
    return { start, end: Math.min(total, start + count) };
});
const windowRows = computed(() => viewRows.value.slice(windowRange.value.start, windowRange.value.end));
const topPadding = computed(() => windowRange.value.start * ROW_HEIGHT);
const bottomPadding = computed(() => (viewRows.value.length - windowRange.value.end) * ROW_HEIGHT);

/** Where the table was scrolled when a menu opened: a real scroll away from it closes the menu. */
let menuScroll = { top: 0, left: 0 };
const onScroll = () => {
    // Not the scroll that brought the column into view, nor a swipe still gliding on a tablet.
    if (menu.value && scroller.value) {
        const moved = Math.max(Math.abs(scroller.value.scrollTop - menuScroll.top), Math.abs(scroller.value.scrollLeft - menuScroll.left));
        if (moved > 40) {
            closeMenu();
        }
    }
    if (scrollFrame) {
        return;
    }
    scrollFrame = requestAnimationFrame(() => {
        scrollFrame = 0;
        scrollTop.value = scroller.value?.scrollTop ?? 0;
    });
};
const toTop = () => {
    if (scroller.value) {
        scroller.value.scrollTop = 0;
    }
    scrollTop.value = 0;
};

/* Loading, and the view remembered for this exam. */
const storageName = `filter-cerdas:${props.storageKey}`;
const applyViewState = (raw: unknown) => {
    const view = sanitizeView(raw, columns.value);
    find.value = view.find;
    findDraft.value = view.find;
    sort.value = view.sort;
    filters.value = view.filters;
    hidden.value = view.hidden;
};
const load = async () => {
    isLoading.value = true;
    loadError.value = '';
    try {
        const { data } = await axios.get<GridPayload>(props.url, { headers: { Accept: 'application/json' } });
        columns.value = data.columns;
        rows.value = prepareRows(data);
        if (!hasLoaded) {
            let saved: unknown = null;
            try {
                saved = JSON.parse(localStorage.getItem(storageName) ?? 'null');
            } catch {
                saved = null;
            }
            applyViewState(saved);
            hasLoaded = true;
        } else {
            // Loaded again: questions may have changed, the view keeps what still exists.
            applyViewState({ find: find.value, sort: sort.value, filters: filters.value, hidden: hidden.value });
        }
    } catch (error) {
        const message = axios.isAxiosError(error) ? (error.response?.data as { message?: string } | undefined)?.message : undefined;
        loadError.value = message || 'Data belum dapat dimuat. Periksa koneksi, lalu coba lagi.';
    } finally {
        isLoading.value = false;
    }
};

let saveTimer: ReturnType<typeof setTimeout> | undefined;
const saveView = () => {
    saveTimer = undefined;
    try {
        // What is typed in the find box counts, also when the table closes before it was applied.
        localStorage.setItem(storageName, JSON.stringify({ find: findDraft.value, sort: sort.value, filters: filters.value, hidden: hidden.value }));
    } catch {
        // Storage refused (private mode): the view is simply not remembered.
    }
};
watch([find, sort, filters, hidden], () => {
    if (!hasLoaded) {
        return;
    }
    clearTimeout(saveTimer);
    saveTimer = setTimeout(saveView, 300);
});

let findTimer: ReturnType<typeof setTimeout> | undefined;
watch(findDraft, (value) => {
    clearTimeout(findTimer);
    findTimer = setTimeout(() => (find.value = value), 150);
});
watch([find, sort, filters], toTop);

/* Sorting and filtering. */
const toggleSort = (key: string) => {
    sort.value = sort.value?.key !== key ? { key, direction: 'asc' } : sort.value.direction === 'asc' ? { key, direction: 'desc' } : null;
};
const setSort = (key: string, direction: 'asc' | 'desc') => {
    sort.value = { key, direction };
};
const setFilter = (key: string, filter: ColumnFilter) => {
    const next = { ...filters.value };
    if (isFilterActive(filter)) {
        next[key] = filter;
    } else {
        delete next[key];
    }
    filters.value = next;
};
const clearAll = () => {
    filters.value = {};
    findDraft.value = '';
    find.value = '';
};
const hasFilters = computed(() => Object.keys(filters.value).length > 0 || find.value.trim() !== '');

/* The filter menu of a column. */
const menu = ref<{ key: string; left: number; top: number; width: number; maxHeight: number } | null>(null);
const valueSearch = ref('');
const closeMenu = () => {
    menu.value = null;
};
const openMenu = (key: string, event: MouseEvent) => {
    if (menu.value?.key === key) {
        closeMenu();
        return;
    }
    const rect = (event.currentTarget as HTMLElement).getBoundingClientRect();
    const width = Math.min(300, window.innerWidth - 16);
    const top = Math.min(rect.bottom + 4, window.innerHeight - 260);
    menu.value = {
        key,
        width,
        left: Math.max(8, Math.min(rect.right - width, window.innerWidth - width - 8)),
        top: Math.max(8, top),
        maxHeight: Math.max(240, window.innerHeight - Math.max(8, top) - 12),
    };
    menuScroll = { top: scroller.value?.scrollTop ?? 0, left: scroller.value?.scrollLeft ?? 0 };
    isColumnsOpen.value = false;
    valueSearch.value = '';
};
const menuColumn = computed(() => (menu.value ? (columns.value.find((column) => column.key === menu.value?.key) ?? null) : null));
const menuFilter = computed<ColumnFilter>(() => (menu.value ? (filters.value[menu.value.key] ?? {}) : {}));
const updateMenuFilter = (patch: Partial<ColumnFilter>) => {
    if (menu.value) {
        setFilter(menu.value.key, { ...menuFilter.value, ...patch });
    }
};
const menuValues = computed(() =>
    menu.value && menuColumn.value && menuColumn.value.type !== 'datetime'
        ? distinctValues(rows.value, columns.value, filters.value, menu.value.key)
        : [],
);
const shownMenuValues = computed(() => {
    const query = normalize(valueSearch.value.trim());
    const values = query ? menuValues.value.filter((entry) => normalize(entry.value).includes(query)) : menuValues.value;
    return values.slice(0, MAX_VALUES_SHOWN);
});
const isValueChecked = (value: string) => menuFilter.value.values === undefined || menuFilter.value.values.includes(value);
const toggleValue = (value: string) => {
    const all = menuValues.value.map((entry) => entry.value);
    const checked = new Set(menuFilter.value.values ?? all);
    if (checked.has(value)) {
        checked.delete(value);
    } else {
        checked.add(value);
    }
    const isEverything = checked.size === all.length && all.every((entry) => checked.has(entry));
    updateMenuFilter({ values: isEverything ? undefined : [...checked] });
};
const onlyValues = (values: string[]) => updateMenuFilter({ values });
const toggleMark = (mark: Mark) => {
    const marks = new Set(menuFilter.value.marks ?? []);
    if (marks.has(mark)) {
        marks.delete(mark);
    } else {
        marks.add(mark);
    }
    updateMenuFilter({ marks: [...marks] });
};
/** A day typed as YYYY-MM-DD (old browsers have no date picker and take any text). */
const dayInput = (value: string): string | undefined => (/^\d{4}-\d{2}-\d{2}$/.test(value.trim()) ? value.trim() : undefined);
const numberInput = (value: string): number | null => {
    const number = Number(value.replace(',', '.'));
    return value.trim() === '' || !Number.isFinite(number) ? null : number;
};

/* Hiding columns. */
const isColumnsOpen = ref(false);
const columnsMenu = ref<{ left: number; top: number; width: number; maxHeight: number } | null>(null);
const toggleColumnsMenu = (event: MouseEvent) => {
    if (isColumnsOpen.value) {
        isColumnsOpen.value = false;
        return;
    }
    closeMenu();
    const rect = (event.currentTarget as HTMLElement).getBoundingClientRect();
    const width = Math.min(320, window.innerWidth - 16);
    columnsMenu.value = {
        width,
        left: Math.max(8, Math.min(rect.left, window.innerWidth - width - 8)),
        top: rect.bottom + 4,
        maxHeight: Math.max(240, window.innerHeight - rect.bottom - 16),
    };
    isColumnsOpen.value = true;
};
const toggleColumn = (key: string) => {
    hidden.value = hidden.value.includes(key) ? hidden.value.filter((hiddenKey) => hiddenKey !== key) : [...hidden.value, key];
};
const answerKeys = computed(() => columns.value.filter((column) => column.type === 'answer').map((column) => column.key));
const showAllColumns = () => (hidden.value = []);
const hideAnswers = () => (hidden.value = [...new Set([...hidden.value, ...answerKeys.value])]);
const showAnswers = () => (hidden.value = hidden.value.filter((key) => !answerKeys.value.includes(key)));

/* What is applied, as chips. */
const shortLabel = (column: GridColumn) => (column.type === 'answer' ? `Soal ${column.label.split('.')[0]}` : column.label);
const describeFilter = (column: GridColumn, filter: ColumnFilter): string => {
    const parts: string[] = [];
    if ((filter.text ?? '').trim()) {
        parts.push(`berisi "${filter.text?.trim()}"`);
    }
    if (filter.values !== undefined) {
        parts.push(
            filter.values.length === 0
                ? 'tidak ada nilai'
                : filter.values.length <= 2
                  ? filter.values.map((value) => value || '(kosong)').join(', ')
                  : `${filter.values.length} nilai`,
        );
    }
    const hasMin = typeof filter.min === 'number';
    const hasMax = typeof filter.max === 'number';
    if (hasMin && hasMax) {
        parts.push(`${formatNumber(filter.min as number)}–${formatNumber(filter.max as number)}`);
    } else if (hasMin) {
        parts.push(`≥ ${formatNumber(filter.min as number)}`);
    } else if (hasMax) {
        parts.push(`≤ ${formatNumber(filter.max as number)}`);
    }
    if (filter.from || filter.to) {
        parts.push(`${filter.from ? formatDateTime(filter.from) : '…'} – ${filter.to ? formatDateTime(filter.to) : '…'}`);
    }
    if (filter.marks?.length) {
        parts.push(filter.marks.map((mark) => MARK_LABELS[mark]).join(', '));
    }
    return `${shortLabel(column)}: ${parts.join('; ')}`;
};
const chips = computed(() =>
    columns.value
        .filter((column) => isFilterActive(filters.value[column.key]))
        .map((column) => ({ key: column.key, label: describeFilter(column, filters.value[column.key]) })),
);

/* The cell bar (like Excel's formula bar) and the status bar. */
const rowsById = computed(() => new Map(rows.value.map((row) => [row.id, row])));
const selectedInfo = computed(() => {
    if (!selected.value) {
        return null;
    }
    const row = rowsById.value.get(selected.value.id);
    const index = columnIndex(selected.value.key);
    if (!row || index < 0) {
        return null;
    }
    const name = row.texts[columnIndex('name')] ?? '';
    return { where: `${name} · ${columns.value[index].label}`, text: row.texts[index], mark: row.marks[index], index };
});
const select = (row: GridRow, column: ShownColumn) => {
    selected.value = selected.value?.id === row.id && selected.value.key === column.key ? null : { id: row.id, key: column.key };
};
const isSelected = (row: GridRow, column: ShownColumn) => selected.value?.id === row.id && selected.value.key === column.key;

const gradeStats = computed(() => {
    const index = columnIndex('grade');
    return index >= 0 ? numberStats(viewRows.value, index) : null;
});
const passedCount = computed(() => {
    const index = columnIndex('result');
    return index >= 0 ? viewRows.value.filter((row) => row.texts[index] === 'Tuntas').length : null;
});
const selectedColumnStats = computed(() => {
    const info = selectedInfo.value;
    if (!info) {
        return null;
    }
    const column = columns.value[info.index];
    if (column.type === 'answer') {
        const stats = markStats(viewRows.value, info.index);
        const scored = stats.correct + stats.wrong;
        return `${shortLabel(column)}: benar ${stats.correct} dari ${scored}${scored ? ` (${formatNumber((stats.correct / scored) * 100)}%)` : ''}`;
    }
    if (column.type === 'number' && column.key !== 'grade' && column.key !== 'no') {
        const stats = numberStats(viewRows.value, info.index);
        return stats
            ? `${column.label}: rata-rata ${formatNumber(stats.average)} · terendah ${formatNumber(stats.min)} · tertinggi ${formatNumber(stats.max)}`
            : null;
    }
    return null;
});

const cellClass = (row: GridRow, column: ShownColumn) => {
    const mark = row.marks[column.index];
    return [
        'h-[34px] cursor-default overflow-hidden text-ellipsis whitespace-nowrap border-b border-r border-slate-200 px-2',
        column.type === 'number' ? 'text-right tabular-nums' : '',
        column.key === 'name' ? 'font-semibold text-slate-900' : '',
        mark ? MARK_CLASSES[mark] : column.left !== null ? 'bg-white' : '',
        column.left !== null ? 'sticky z-[1]' : '',
        isSelected(row, column)
            ? 'shadow-[inset_0_0_0_2px_#4f46e5]'
            : findKey.value && row.keys[column.index].includes(findKey.value)
              ? 'shadow-[inset_0_0_0_2px_#f59e0b]'
              : '',
    ];
};

/* Download what is shown. */
const downloadCsv = () => {
    const blob = new Blob([toCsv(viewRows.value, columns.value, shownIndexes.value)], { type: 'text/csv;charset=utf-8' });
    const link = document.createElement('a');
    const slug =
        normalize(props.title)
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '')
            .slice(0, 60) || 'hasil';
    link.href = URL.createObjectURL(blob);
    link.download = `filter-cerdas-${slug}.csv`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(link.href), 1000);
};

/* Keyboard: Esc closes a menu, then the table; Ctrl/⌘+F goes to the find box. */
const findInput = ref<HTMLInputElement | null>(null);
const onKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Escape') {
        event.stopPropagation();
        if (menu.value) {
            closeMenu();
        } else if (isColumnsOpen.value) {
            isColumnsOpen.value = false;
        } else if (selected.value) {
            selected.value = null;
        } else {
            emit('close');
        }
        return;
    }
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'f') {
        event.preventDefault();
        findInput.value?.focus();
        findInput.value?.select();
    }
};

let resizeObserver: ResizeObserver | null = null;
const onWindowResize = () => {
    isNarrow.value = window.innerWidth < 640;
    closeMenu();
    isColumnsOpen.value = false;
};
let previousOverflow = '';
onMounted(() => {
    previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    document.addEventListener('keydown', onKeydown, true);
    window.addEventListener('resize', onWindowResize);
    if (scroller.value) {
        viewportHeight.value = scroller.value.clientHeight || 600;
        resizeObserver = new ResizeObserver(() => {
            viewportHeight.value = scroller.value?.clientHeight || viewportHeight.value;
        });
        resizeObserver.observe(scroller.value);
    }
    load();
    nextTick(() => findInput.value?.focus({ preventScroll: true }));
});
onBeforeUnmount(() => {
    document.body.style.overflow = previousOverflow;
    document.removeEventListener('keydown', onKeydown, true);
    window.removeEventListener('resize', onWindowResize);
    resizeObserver?.disconnect();
    cancelAnimationFrame(scrollFrame);
    clearTimeout(findTimer);
    // Closed right after a change: it is still remembered.
    if (saveTimer !== undefined || (hasLoaded && findDraft.value !== find.value)) {
        clearTimeout(saveTimer);
        saveView();
    }
});
</script>

<template>
    <Teleport to="body">
        <div
            class="fixed inset-0 z-[60] flex flex-col bg-white text-slate-700"
            role="dialog"
            aria-modal="true"
            aria-label="Filter Cerdas"
            data-smart-filter
        >
            <!-- Toolbar -->
            <header class="flex flex-wrap items-center gap-2 border-b border-slate-200 px-3 py-2">
                <div class="flex min-w-0 items-center gap-2">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-cyan-50 text-cyan-700">
                        <ListFilter class="h-4 w-4" />
                    </span>
                    <div class="min-w-0">
                        <h2 class="text-sm font-extrabold text-slate-900">Filter Cerdas</h2>
                        <p class="max-w-[16rem] truncate text-[11px] text-slate-500" :title="title">{{ title }}</p>
                    </div>
                </div>
                <label
                    class="order-last flex w-full items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-1.5 focus-within:border-cyan-500 focus-within:bg-white sm:order-none sm:w-72"
                >
                    <Search class="h-3.5 w-3.5 shrink-0 text-slate-400" />
                    <input
                        ref="findInput"
                        v-model="findDraft"
                        type="search"
                        class="w-full border-0 bg-transparent p-0 text-xs outline-none focus:ring-0"
                        placeholder="Cari di semua sel… (Ctrl+F)"
                        aria-label="Cari di semua sel"
                    />
                </label>
                <span class="text-[11px] font-semibold text-slate-500" aria-live="polite" data-smart-filter-count>
                    {{ viewRows.length }} dari {{ rows.length }} baris · {{ shownColumns.length }} dari {{ columns.length }} kolom
                </span>
                <div class="ml-auto flex items-center gap-1.5">
                    <button
                        type="button"
                        :class="[
                            'inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-xs font-bold transition',
                            isColumnsOpen || hidden.length
                                ? 'border-cyan-300 bg-cyan-50 text-cyan-800'
                                : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50',
                        ]"
                        :aria-expanded="isColumnsOpen"
                        @click="toggleColumnsMenu"
                    >
                        <Columns3 class="h-3.5 w-3.5" /> Kolom<span v-if="hidden.length" class="text-[10px]"
                            >({{ hidden.length }} disembunyikan)</span
                        >
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-bold text-slate-600 transition hover:bg-slate-50 disabled:opacity-50"
                        :disabled="isLoading"
                        title="Ambil jawaban terbaru dari server"
                        @click="load"
                    >
                        <RefreshCw :class="['h-3.5 w-3.5', isLoading ? 'animate-spin' : '']" /> <span class="hidden sm:inline">Muat ulang</span>
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-bold text-slate-600 transition hover:bg-slate-50 disabled:opacity-50"
                        :disabled="!viewRows.length"
                        title="Unduh baris dan kolom yang tampil sebagai CSV (dibuka di Excel)"
                        @click="downloadCsv"
                    >
                        <Download class="h-3.5 w-3.5" /> <span class="hidden sm:inline">CSV</span>
                    </button>
                    <a
                        v-if="exportUrl"
                        :href="exportUrl"
                        class="hidden items-center gap-1.5 rounded-lg bg-emerald-600 px-2.5 py-1.5 text-xs font-bold text-white transition hover:bg-emerald-700 sm:inline-flex"
                        title="Unduh berkas Excel lengkap (semua lembar)"
                    >
                        <FileSpreadsheet class="h-3.5 w-3.5" /> Excel
                    </a>
                    <button
                        type="button"
                        class="rounded-lg p-1.5 text-slate-500 transition hover:bg-slate-100 hover:text-slate-800"
                        aria-label="Tutup Filter Cerdas"
                        @click="emit('close')"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>
            </header>

            <!-- What is applied -->
            <div
                v-if="chips.length || find.trim()"
                class="flex flex-wrap items-center gap-1.5 border-b border-slate-200 bg-cyan-50/40 px-3 py-1.5"
                data-smart-filter-chips
            >
                <span
                    v-if="find.trim()"
                    class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-bold text-amber-900"
                >
                    Cari: "{{ find.trim() }}"
                    <button
                        type="button"
                        class="rounded-full hover:bg-amber-200"
                        aria-label="Hapus pencarian"
                        @click="((findDraft = ''), (find = ''))"
                    >
                        <X class="h-3 w-3" />
                    </button>
                </span>
                <span
                    v-for="chip in chips"
                    :key="chip.key"
                    class="inline-flex max-w-full items-center gap-1 rounded-full bg-cyan-100 px-2 py-0.5 text-[11px] font-bold text-cyan-900"
                >
                    <span class="truncate">{{ chip.label }}</span>
                    <button
                        type="button"
                        class="shrink-0 rounded-full hover:bg-cyan-200"
                        :aria-label="`Hapus saringan ${chip.label}`"
                        @click="setFilter(chip.key, {})"
                    >
                        <X class="h-3 w-3" />
                    </button>
                </span>
                <button v-if="hasFilters" type="button" class="text-[11px] font-bold text-cyan-800 hover:underline" @click="clearAll">
                    Hapus semua saringan
                </button>
            </div>

            <!-- Cell bar: the whole content of the selected cell -->
            <div class="flex min-h-[32px] items-start gap-2 border-b border-slate-200 bg-slate-50 px-3 py-1.5 text-xs" data-smart-filter-cell>
                <template v-if="selectedInfo">
                    <span class="max-w-[45%] shrink-0 truncate font-bold text-slate-500" :title="selectedInfo.where">{{ selectedInfo.where }}</span>
                    <span v-if="selectedInfo.mark" :class="['shrink-0 rounded border px-1.5 text-[10px] font-bold', MARK_CHIPS[selectedInfo.mark]]">{{
                        MARK_LABELS[selectedInfo.mark]
                    }}</span>
                    <span class="max-h-24 min-w-0 flex-1 overflow-y-auto whitespace-pre-wrap break-words text-slate-800">{{
                        selectedInfo.text || '(kosong)'
                    }}</span>
                </template>
                <span v-else class="text-slate-400"
                    >Klik sel untuk melihat isinya utuh. Klik judul kolom untuk mengurutkan, ikon corong untuk menyaring.</span
                >
            </div>

            <!-- The table -->
            <div ref="scroller" class="relative min-h-0 flex-1 overflow-auto overscroll-contain" @scroll.passive="onScroll">
                <table
                    v-if="columns.length"
                    class="border-separate border-spacing-0 text-xs"
                    :style="{ width: `${tableWidth}px`, tableLayout: 'fixed' }"
                    data-smart-filter-table
                >
                    <colgroup>
                        <col v-for="column in shownColumns" :key="column.key" :style="{ width: `${column.width}px` }" />
                    </colgroup>
                    <thead>
                        <tr>
                            <th
                                v-for="column in shownColumns"
                                :key="column.key"
                                :class="[
                                    'sticky top-0 h-[52px] border-b border-r border-slate-300 bg-slate-100 p-0 align-top',
                                    column.left !== null ? 'z-30' : 'z-20',
                                ]"
                                :style="column.left !== null ? { left: `${column.left}px` } : undefined"
                                :aria-sort="sort?.key === column.key ? (sort.direction === 'asc' ? 'ascending' : 'descending') : 'none'"
                                :data-column="column.key"
                            >
                                <div class="flex h-full items-start gap-0.5 px-1.5 py-1">
                                    <button
                                        type="button"
                                        class="min-w-0 flex-1 text-left hover:text-cyan-800"
                                        :title="column.hint ? `${column.label}\n${column.hint}` : column.label"
                                        @click="toggleSort(column.key)"
                                    >
                                        <span class="line-clamp-2 text-[11px] font-bold leading-tight text-slate-700">{{ column.label }}</span>
                                        <span v-if="column.hint" class="block truncate text-[10px] font-normal text-slate-500">{{
                                            column.hint
                                        }}</span>
                                    </button>
                                    <ArrowUp
                                        v-if="sort?.key === column.key && sort.direction === 'asc'"
                                        class="mt-0.5 h-3 w-3 shrink-0 text-cyan-700"
                                    />
                                    <ArrowDown v-else-if="sort?.key === column.key" class="mt-0.5 h-3 w-3 shrink-0 text-cyan-700" />
                                    <button
                                        type="button"
                                        :class="[
                                            'shrink-0 rounded p-1 transition',
                                            isFilterActive(filters[column.key])
                                                ? 'bg-cyan-600 text-white'
                                                : menu?.key === column.key
                                                  ? 'bg-slate-300 text-slate-700'
                                                  : 'text-slate-400 hover:bg-slate-200 hover:text-slate-700',
                                        ]"
                                        :aria-label="`Saring kolom ${column.label}`"
                                        :aria-expanded="menu?.key === column.key"
                                        data-filter-button
                                        @click.stop="openMenu(column.key, $event)"
                                    >
                                        <Filter class="h-3 w-3" />
                                    </button>
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="topPadding" :style="{ height: `${topPadding}px` }" aria-hidden="true">
                            <td :colspan="shownColumns.length" class="p-0"></td>
                        </tr>
                        <tr v-for="row in windowRows" :key="row.id" :style="{ height: `${ROW_HEIGHT}px` }" :data-row="row.id">
                            <td
                                v-for="column in shownColumns"
                                :key="column.key"
                                :class="cellClass(row, column)"
                                :style="column.left !== null ? { left: `${column.left}px` } : undefined"
                                @click="select(row, column)"
                            >
                                {{ row.texts[column.index] }}
                            </td>
                        </tr>
                        <tr v-if="bottomPadding" :style="{ height: `${bottomPadding}px` }" aria-hidden="true">
                            <td :colspan="shownColumns.length" class="p-0"></td>
                        </tr>
                    </tbody>
                </table>

                <div v-if="isLoading && !rows.length" class="space-y-2 p-4" aria-busy="true">
                    <p class="flex items-center gap-2 text-xs font-semibold text-slate-500">
                        <Loader2 class="h-4 w-4 animate-spin" /> Memuat hasil ujian…
                    </p>
                    <div v-for="line in 8" :key="line" class="h-7 animate-pulse rounded bg-slate-100"></div>
                </div>
                <div v-else-if="loadError" class="m-4 rounded-xl bg-rose-50 p-4 text-sm text-rose-800" role="alert">
                    {{ loadError }}
                    <button type="button" class="ml-2 font-bold underline" @click="load">Coba lagi</button>
                </div>
                <div v-else-if="!rows.length" class="p-8 text-center text-sm text-slate-500">Belum ada jawaban yang masuk untuk ujian ini.</div>
                <div v-else-if="!viewRows.length" class="sticky left-0 p-8 text-center text-sm text-slate-500">
                    Tidak ada baris yang cocok.
                    <button type="button" class="ml-1 font-bold text-cyan-700 hover:underline" @click="clearAll">Hapus semua saringan</button>
                </div>
            </div>

            <!-- Status bar -->
            <footer
                class="flex flex-wrap items-center gap-x-4 gap-y-0.5 border-t border-slate-200 bg-slate-50 px-3 py-1.5 text-[11px] text-slate-600"
                data-smart-filter-status
            >
                <span
                    ><b>{{ viewRows.length }}</b> baris tampil</span
                >
                <template v-if="gradeStats">
                    <span
                        >Rata-rata nilai <b>{{ formatNumber(gradeStats.average) }}</b></span
                    >
                    <span
                        >Tertinggi <b>{{ formatNumber(gradeStats.max) }}</b></span
                    >
                    <span
                        >Terendah <b>{{ formatNumber(gradeStats.min) }}</b></span
                    >
                </template>
                <span v-if="passedCount !== null && viewRows.length"
                    >Tuntas <b>{{ passedCount }}</b> dari {{ viewRows.length }}</span
                >
                <span v-if="selectedColumnStats" class="font-semibold text-cyan-800">{{ selectedColumnStats }}</span>
            </footer>

            <!-- Menus: a click outside closes them -->
            <div v-if="menu || isColumnsOpen" class="fixed inset-0 z-[65]" aria-hidden="true" @click="((menu = null), (isColumnsOpen = false))"></div>

            <section
                v-if="menu && menuColumn"
                class="fixed z-[70] flex flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl"
                :style="{ left: `${menu.left}px`, top: `${menu.top}px`, width: `${menu.width}px`, maxHeight: `${menu.maxHeight}px` }"
                role="dialog"
                :aria-label="`Saring ${menuColumn.label}`"
                data-filter-menu
            >
                <div class="border-b border-slate-100 px-3 py-2">
                    <p class="truncate text-xs font-extrabold text-slate-900" :title="menuColumn.label">{{ menuColumn.label }}</p>
                    <div class="mt-1.5 flex gap-1">
                        <button
                            type="button"
                            :class="[
                                'flex-1 rounded-lg border px-2 py-1 text-[11px] font-bold',
                                sort?.key === menuColumn.key && sort.direction === 'asc'
                                    ? 'border-cyan-500 bg-cyan-50 text-cyan-800'
                                    : 'border-slate-200 hover:bg-slate-50',
                            ]"
                            @click="setSort(menuColumn.key, 'asc')"
                        >
                            {{
                                menuColumn.type === 'number' ? 'Terkecil → terbesar' : menuColumn.type === 'datetime' ? 'Terlama → terbaru' : 'A → Z'
                            }}
                        </button>
                        <button
                            type="button"
                            :class="[
                                'flex-1 rounded-lg border px-2 py-1 text-[11px] font-bold',
                                sort?.key === menuColumn.key && sort.direction === 'desc'
                                    ? 'border-cyan-500 bg-cyan-50 text-cyan-800'
                                    : 'border-slate-200 hover:bg-slate-50',
                            ]"
                            @click="setSort(menuColumn.key, 'desc')"
                        >
                            {{
                                menuColumn.type === 'number' ? 'Terbesar → terkecil' : menuColumn.type === 'datetime' ? 'Terbaru → terlama' : 'Z → A'
                            }}
                        </button>
                    </div>
                </div>

                <div class="min-h-0 flex-1 space-y-3 overflow-y-auto px-3 py-2.5">
                    <!-- Answers: right, wrong, … -->
                    <div v-if="menuColumn.type === 'answer'">
                        <p class="mb-1 text-[10px] font-extrabold uppercase tracking-wider text-slate-500">Jawaban</p>
                        <div class="flex flex-wrap gap-1">
                            <button
                                v-for="mark in MARKS"
                                :key="mark"
                                type="button"
                                :class="[
                                    'rounded-full border px-2 py-0.5 text-[11px] font-bold transition',
                                    menuFilter.marks?.includes(mark) ? MARK_CHIPS[mark] : 'border-slate-200 text-slate-500 hover:bg-slate-50',
                                ]"
                                :aria-pressed="Boolean(menuFilter.marks?.includes(mark))"
                                @click="toggleMark(mark)"
                            >
                                {{ MARK_LABELS[mark] }}
                            </button>
                        </div>
                    </div>

                    <!-- Numbers: a range -->
                    <div v-if="menuColumn.type === 'number'" class="grid grid-cols-2 gap-2">
                        <label class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500">
                            Dari
                            <input
                                type="number"
                                step="any"
                                class="mt-0.5 w-full rounded-lg border border-slate-200 px-2 py-1 text-xs font-normal normal-case text-slate-800"
                                :value="menuFilter.min ?? ''"
                                @input="updateMenuFilter({ min: numberInput(($event.target as HTMLInputElement).value) })"
                            />
                        </label>
                        <label class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500">
                            Sampai
                            <input
                                type="number"
                                step="any"
                                class="mt-0.5 w-full rounded-lg border border-slate-200 px-2 py-1 text-xs font-normal normal-case text-slate-800"
                                :value="menuFilter.max ?? ''"
                                @input="updateMenuFilter({ max: numberInput(($event.target as HTMLInputElement).value) })"
                            />
                        </label>
                    </div>

                    <!-- Dates: a period -->
                    <div v-if="menuColumn.type === 'datetime'" class="grid grid-cols-2 gap-2">
                        <label class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500">
                            Dari tanggal
                            <input
                                type="date"
                                class="mt-0.5 w-full rounded-lg border border-slate-200 px-2 py-1 text-xs font-normal normal-case text-slate-800"
                                :value="menuFilter.from ?? ''"
                                @input="updateMenuFilter({ from: dayInput(($event.target as HTMLInputElement).value) })"
                            />
                        </label>
                        <label class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500">
                            Sampai tanggal
                            <input
                                type="date"
                                class="mt-0.5 w-full rounded-lg border border-slate-200 px-2 py-1 text-xs font-normal normal-case text-slate-800"
                                :value="menuFilter.to ?? ''"
                                @input="updateMenuFilter({ to: dayInput(($event.target as HTMLInputElement).value) })"
                            />
                        </label>
                    </div>

                    <!-- Text: contains -->
                    <label
                        v-if="menuColumn.type === 'text' || menuColumn.type === 'answer'"
                        class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-500"
                    >
                        Berisi teks
                        <input
                            type="text"
                            class="mt-0.5 w-full rounded-lg border border-slate-200 px-2 py-1 text-xs font-normal normal-case text-slate-800"
                            :value="menuFilter.text ?? ''"
                            placeholder="Ketik sebagian isi sel…"
                            @input="updateMenuFilter({ text: ($event.target as HTMLInputElement).value })"
                        />
                    </label>

                    <!-- Values, like Excel's checklist -->
                    <div v-if="menuColumn.type !== 'datetime'">
                        <div class="mb-1 flex items-center justify-between gap-2">
                            <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500">Nilai ({{ menuValues.length }})</p>
                            <div class="flex gap-2 text-[11px] font-bold text-cyan-700">
                                <button type="button" class="hover:underline" @click="updateMenuFilter({ values: undefined })">Pilih semua</button>
                                <button type="button" class="hover:underline" @click="onlyValues([])">Kosongkan</button>
                            </div>
                        </div>
                        <input
                            v-if="menuValues.length > 8"
                            v-model="valueSearch"
                            type="search"
                            class="mb-1 w-full rounded-lg border border-slate-200 px-2 py-1 text-xs"
                            placeholder="Cari nilai…"
                            aria-label="Cari nilai di daftar"
                        />
                        <button
                            v-if="valueSearch.trim() && shownMenuValues.length"
                            type="button"
                            class="mb-1 text-[11px] font-bold text-cyan-700 hover:underline"
                            @click="onlyValues(shownMenuValues.map((entry) => entry.value))"
                        >
                            Pilih hasil cari saja
                        </button>
                        <ul class="max-h-56 overflow-y-auto rounded-lg border border-slate-100">
                            <li v-for="entry in shownMenuValues" :key="entry.value" class="group flex items-center gap-2 px-2 py-1 hover:bg-slate-50">
                                <label class="flex min-w-0 flex-1 cursor-pointer items-center gap-2 text-xs">
                                    <input
                                        type="checkbox"
                                        class="rounded border-slate-300 text-cyan-600"
                                        :checked="isValueChecked(entry.value)"
                                        @change="toggleValue(entry.value)"
                                    />
                                    <span
                                        :class="['min-w-0 flex-1 truncate', entry.value ? 'text-slate-800' : 'italic text-slate-400']"
                                        :title="entry.value"
                                        >{{ entry.value || '(kosong)' }}</span
                                    >
                                    <span class="shrink-0 text-[10px] tabular-nums text-slate-400">{{ entry.count }}</span>
                                </label>
                                <button
                                    type="button"
                                    class="shrink-0 text-[10px] font-bold text-cyan-700 opacity-0 hover:underline focus:opacity-100 group-hover:opacity-100"
                                    @click="onlyValues([entry.value])"
                                >
                                    hanya ini
                                </button>
                            </li>
                            <li v-if="!shownMenuValues.length" class="px-2 py-2 text-xs text-slate-400">Tidak ada nilai.</li>
                        </ul>
                        <p v-if="menuValues.length > MAX_VALUES_SHOWN && !valueSearch.trim()" class="mt-1 text-[10px] text-slate-400">
                            {{ MAX_VALUES_SHOWN }} nilai pertama ditampilkan; ketik di Cari nilai untuk yang lain.
                        </p>
                    </div>
                </div>

                <div class="flex justify-between gap-2 border-t border-slate-100 px-3 py-2">
                    <button
                        type="button"
                        class="text-[11px] font-bold text-rose-600 hover:underline disabled:opacity-40"
                        :disabled="!isFilterActive(filters[menuColumn.key])"
                        @click="setFilter(menuColumn.key, {})"
                    >
                        Hapus saringan kolom
                    </button>
                    <button
                        type="button"
                        class="rounded-lg bg-cyan-600 px-3 py-1 text-[11px] font-bold text-white hover:bg-cyan-700"
                        @click="closeMenu"
                    >
                        Selesai
                    </button>
                </div>
            </section>

            <section
                v-if="isColumnsOpen && columnsMenu"
                class="fixed z-[70] flex flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl"
                :style="{
                    left: `${columnsMenu.left}px`,
                    top: `${columnsMenu.top}px`,
                    width: `${columnsMenu.width}px`,
                    maxHeight: `${columnsMenu.maxHeight}px`,
                }"
                role="dialog"
                aria-label="Kolom yang ditampilkan"
                data-columns-menu
            >
                <div class="flex flex-wrap gap-x-3 gap-y-1 border-b border-slate-100 px-3 py-2 text-[11px] font-bold text-cyan-700">
                    <button type="button" class="hover:underline" @click="showAllColumns">Tampilkan semua</button>
                    <button v-if="answerKeys.length" type="button" class="hover:underline" @click="hideAnswers">Sembunyikan semua soal</button>
                    <button v-if="answerKeys.length" type="button" class="hover:underline" @click="showAnswers">Tampilkan semua soal</button>
                </div>
                <ul class="min-h-0 flex-1 overflow-y-auto py-1">
                    <li v-for="column in columns" :key="column.key">
                        <label class="flex cursor-pointer items-center gap-2 px-3 py-1 text-xs hover:bg-slate-50">
                            <input
                                type="checkbox"
                                class="rounded border-slate-300 text-cyan-600"
                                :checked="!hidden.includes(column.key)"
                                :disabled="column.key === 'name'"
                                @change="toggleColumn(column.key)"
                            />
                            <span class="min-w-0 flex-1 truncate" :title="column.label">{{ column.label }}</span>
                        </label>
                    </li>
                </ul>
            </section>
        </div>
    </Teleport>
</template>
