/*
 * Filter Cerdas add-on: find, filter and sort the results of an exam like in Excel. Pure functions
 * over the table the server sends (App\Services\QuizResultsReport::grid): the rows are prepared once
 * (each cell as shown and normalised for matching) and are never made reactive, so a class of
 * hundreds with dozens of questions stays light.
 */

export type ColumnType = 'number' | 'text' | 'datetime' | 'answer';
export type Mark = 'correct' | 'wrong' | 'ungraded' | 'absent';
export type CellValue = string | number | null;

export type GridColumn = { key: string; label: string; type: ColumnType; hint?: string };
export type GridPayload = { columns: GridColumn[]; rows: { id: number; cells: CellValue[]; marks: Mark[] }[]; kkm: number };

/** A row ready to filter: its cells, as shown, normalised for matching, and the mark of each answer. */
export type GridRow = { id: number; order: number; cells: CellValue[]; texts: string[]; keys: string[]; marks: (Mark | null)[] };

export type ColumnFilter = {
    /** The cell contains this text (case and accents ignored). */
    text?: string;
    /** Only these cells, as shown ('' is an empty cell). */
    values?: string[];
    min?: number | null;
    max?: number | null;
    /** Dates of a datetime column, YYYY-MM-DD, both included. */
    from?: string;
    to?: string;
    /** Answers that are right, wrong, not scored automatically, or of a question not drawn. */
    marks?: Mark[];
};
export type Filters = Record<string, ColumnFilter>;
export type Sort = { key: string; direction: 'asc' | 'desc' } | null;
export type ViewState = { find: string; sort: Sort; filters: Filters; hidden: string[] };

export const MARK_LABELS: Record<Mark, string> = {
    correct: 'Benar',
    wrong: 'Salah / kosong',
    ungraded: 'Tidak dinilai otomatis',
    absent: 'Soal tidak didapat',
};

const collator = new Intl.Collator('id', { sensitivity: 'base', numeric: true });

/** Lower case without accents: "Élsa" is found with "elsa". */
export const normalize = (text: string): string => text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

/** 78.5 as 78,5: the Indonesian decimal comma, at most two decimals. */
export const formatNumber = (value: number): string => String(Math.round(value * 100) / 100).replace('.', ',');

/** 2026-10-26 07:05 as 26/10/2026 07:05. */
export const formatDateTime = (value: string): string => {
    const match = /^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}:\d{2}))?/.exec(value);
    return match ? `${match[3]}/${match[2]}/${match[1]}${match[4] ? ` ${match[4]}` : ''}` : value;
};

export const cellText = (value: CellValue, type: ColumnType): string => {
    if (value === null || value === undefined) {
        return '';
    }
    if (typeof value === 'number') {
        return formatNumber(value);
    }
    return type === 'datetime' ? formatDateTime(value) : value;
};

/** The rows of the payload, prepared once for filtering. */
export function prepareRows(payload: GridPayload): GridRow[] {
    const answerColumns = payload.columns.map((column, index) => (column.type === 'answer' ? index : -1)).filter((index) => index >= 0);

    return payload.rows.map((row, order) => {
        const texts = payload.columns.map((column, index) => cellText(row.cells[index] ?? null, column.type));
        const marks: (Mark | null)[] = payload.columns.map(() => null);
        answerColumns.forEach((columnIndex, answerIndex) => {
            marks[columnIndex] = row.marks[answerIndex] ?? null;
        });

        return { id: row.id, order, cells: row.cells, texts, keys: texts.map(normalize), marks };
    });
}

export const isFilterActive = (filter: ColumnFilter | undefined): boolean =>
    Boolean(
        filter &&
            ((filter.text ?? '').trim() !== '' ||
                filter.values !== undefined ||
                (filter.min !== undefined && filter.min !== null) ||
                (filter.max !== undefined && filter.max !== null) ||
                filter.from ||
                filter.to ||
                (filter.marks !== undefined && filter.marks.length > 0)),
    );

type RowTest = (row: GridRow) => boolean;

/** One test per active filter, made once for every row to go through. */
function compileFilters(columns: GridColumn[], filters: Filters, skipKey?: string): RowTest[] {
    const tests: RowTest[] = [];

    columns.forEach((column, index) => {
        const filter = filters[column.key];
        if (column.key === skipKey || !isFilterActive(filter)) {
            return;
        }
        const text = normalize((filter.text ?? '').trim());
        if (text) {
            tests.push((row) => row.keys[index].includes(text));
        }
        if (filter.values !== undefined) {
            const allowed = new Set(filter.values);
            tests.push((row) => allowed.has(row.texts[index]));
        }
        if (typeof filter.min === 'number' || typeof filter.max === 'number') {
            const min = typeof filter.min === 'number' ? filter.min : -Infinity;
            const max = typeof filter.max === 'number' ? filter.max : Infinity;
            tests.push((row) => {
                const value = row.cells[index];
                return typeof value === 'number' && value >= min && value <= max;
            });
        }
        if (filter.from || filter.to) {
            const from = filter.from || '0000-00-00';
            const to = filter.to || '9999-99-99';
            tests.push((row) => {
                const value = row.cells[index];
                const day = typeof value === 'string' ? value.slice(0, 10) : '';
                return day !== '' && day >= from && day <= to;
            });
        }
        if (filter.marks && filter.marks.length) {
            const marks = new Set(filter.marks);
            tests.push((row) => row.marks[index] !== null && marks.has(row.marks[index] as Mark));
        }
    });

    return tests;
}

const isEmptyCell = (value: CellValue): boolean => value === null || value === '';

/** Two cells that are not empty, in the order of their column's type. */
function compareCells(a: GridRow, b: GridRow, index: number, type: ColumnType): number {
    const left = a.cells[index];
    const right = b.cells[index];
    if (type === 'number' && typeof left === 'number' && typeof right === 'number') {
        return left - right;
    }
    if (type === 'datetime') {
        return String(left) < String(right) ? -1 : String(left) > String(right) ? 1 : 0;
    }
    return collator.compare(a.texts[index], b.texts[index]);
}

/**
 * The rows to show, in their order: those passing every column filter and containing the text
 * searched in one of the shown columns, sorted on one column (ties keep the original order).
 */
export function applyView(
    rows: GridRow[],
    columns: GridColumn[],
    state: Pick<ViewState, 'find' | 'sort' | 'filters'>,
    shownIndexes: number[],
): GridRow[] {
    const tests = compileFilters(columns, state.filters);
    const find = normalize(state.find.trim());

    const result = rows.filter((row) => tests.every((test) => test(row)) && (!find || shownIndexes.some((index) => row.keys[index].includes(find))));

    const sortIndex = state.sort ? columns.findIndex((column) => column.key === state.sort?.key) : -1;
    if (state.sort && sortIndex >= 0) {
        const type = columns[sortIndex].type;
        const direction = state.sort.direction === 'asc' ? 1 : -1;
        result.sort((a, b) => {
            // Empty cells stay last in both directions, like Excel.
            const leftEmpty = isEmptyCell(a.cells[sortIndex]);
            const rightEmpty = isEmptyCell(b.cells[sortIndex]);
            if (leftEmpty || rightEmpty) {
                return leftEmpty === rightEmpty ? a.order - b.order : leftEmpty ? 1 : -1;
            }
            return direction * compareCells(a, b, sortIndex, type) || a.order - b.order;
        });
    }

    return result;
}

/** The values of a column among the rows the other filters keep, with how often each occurs. */
export function distinctValues(rows: GridRow[], columns: GridColumn[], filters: Filters, key: string): { value: string; count: number }[] {
    const index = columns.findIndex((column) => column.key === key);
    if (index < 0) {
        return [];
    }
    const tests = compileFilters(columns, filters, key);
    const counts = new Map<string, number>();
    for (const row of rows) {
        if (tests.every((test) => test(row))) {
            counts.set(row.texts[index], (counts.get(row.texts[index]) ?? 0) + 1);
        }
    }
    const isNumber = columns[index].type === 'number';

    return [...counts.entries()]
        .map(([value, count]) => ({ value, count }))
        .sort((a, b) => {
            if (a.value === '' || b.value === '') {
                return a.value === '' ? 1 : -1;
            }
            return isNumber ? Number(a.value.replace(',', '.')) - Number(b.value.replace(',', '.')) : collator.compare(a.value, b.value);
        });
}

/** Count, average, lowest and highest of a number column over the shown rows. */
export function numberStats(rows: GridRow[], index: number): { count: number; average: number; min: number; max: number } | null {
    let count = 0;
    let sum = 0;
    let min = Infinity;
    let max = -Infinity;
    for (const row of rows) {
        const value = row.cells[index];
        if (typeof value === 'number') {
            count++;
            sum += value;
            min = Math.min(min, value);
            max = Math.max(max, value);
        }
    }
    return count ? { count, average: sum / count, min, max } : null;
}

/** Right, wrong and other answers of a question over the shown rows. */
export function markStats(rows: GridRow[], index: number): Record<Mark, number> {
    const stats: Record<Mark, number> = { correct: 0, wrong: 0, ungraded: 0, absent: 0 };
    for (const row of rows) {
        const mark = row.marks[index];
        if (mark) {
            stats[mark]++;
        }
    }
    return stats;
}

/**
 * The shown rows and columns as CSV for Excel in Indonesian: UTF-8 with BOM, semicolons, decimal
 * commas. A cell starting with = + - or @ is prefixed with ' so Excel never runs it as a formula.
 */
export function toCsv(rows: GridRow[], columns: GridColumn[], shownIndexes: number[]): string {
    const field = (text: string): string => {
        const safe = /^[=+\-@\t\r]/.test(text) ? `'${text}` : text;
        return /[";\n\r]/.test(safe) || safe !== safe.trim() ? `"${safe.replace(/"/g, '""')}"` : safe;
    };
    const lines = [shownIndexes.map((index) => field(columns[index].label)).join(';')];
    for (const row of rows) {
        lines.push(shownIndexes.map((index) => field(row.texts[index])).join(';'));
    }

    return `﻿${lines.join('\r\n')}\r\n`;
}

/** A saved view made safe: only known columns and well-formed filters survive. */
export function sanitizeView(raw: unknown, columns: GridColumn[]): ViewState {
    const view: ViewState = { find: '', sort: null, filters: {}, hidden: [] };
    if (!raw || typeof raw !== 'object') {
        return view;
    }
    const data = raw as Record<string, unknown>;
    const keys = new Set(columns.map((column) => column.key));
    const isNumber = (value: unknown): value is number => typeof value === 'number' && Number.isFinite(value);
    const isDay = (value: unknown): value is string => typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value);
    const marks = new Set(Object.keys(MARK_LABELS));

    if (typeof data.find === 'string') {
        view.find = data.find.slice(0, 200);
    }
    const sort = data.sort as Record<string, unknown> | null;
    if (sort && typeof sort.key === 'string' && keys.has(sort.key) && (sort.direction === 'asc' || sort.direction === 'desc')) {
        view.sort = { key: sort.key, direction: sort.direction };
    }
    if (Array.isArray(data.hidden)) {
        view.hidden = data.hidden.filter((key): key is string => typeof key === 'string' && keys.has(key) && key !== 'name');
    }
    if (data.filters && typeof data.filters === 'object') {
        for (const [key, value] of Object.entries(data.filters as Record<string, unknown>)) {
            if (!keys.has(key) || !value || typeof value !== 'object') {
                continue;
            }
            const source = value as Record<string, unknown>;
            const filter: ColumnFilter = {};
            if (typeof source.text === 'string' && source.text.trim()) {
                filter.text = source.text.slice(0, 200);
            }
            if (Array.isArray(source.values)) {
                filter.values = source.values.filter((item): item is string => typeof item === 'string');
            }
            if (isNumber(source.min)) {
                filter.min = source.min;
            }
            if (isNumber(source.max)) {
                filter.max = source.max;
            }
            if (isDay(source.from)) {
                filter.from = source.from;
            }
            if (isDay(source.to)) {
                filter.to = source.to;
            }
            if (Array.isArray(source.marks)) {
                filter.marks = source.marks.filter((mark): mark is Mark => typeof mark === 'string' && marks.has(mark));
            }
            if (isFilterActive(filter)) {
                view.filters[key] = filter;
            }
        }
    }

    return view;
}
