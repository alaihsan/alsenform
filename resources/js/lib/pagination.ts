/** Rows per page a table offers; 0 shows every row on one page. */
export const PAGE_SIZES = [10, 50, 100, 0] as const;

export type PageSize = (typeof PAGE_SIZES)[number];

export const isPageSize = (value: unknown): value is PageSize => PAGE_SIZES.includes(value as PageSize);

/** How many pages the rows take; one page for no rows or for every row at once. */
export const pageCount = (rows: number, size: PageSize): number => (size === 0 ? 1 : Math.max(1, Math.ceil(rows / size)));

/**
 * The page buttons of a pager, at most seven: the first and last page, the current one and its
 * neighbours, and a gap (null) where pages are left out. A gap never hides a single page.
 * 1 2 3 4 5 … 13 · 1 … 6 7 8 … 13 · 1 … 9 10 11 12 13
 */
export const pageNumbers = (current: number, last: number): (number | null)[] => {
    if (last <= 7) {
        return Array.from({ length: last }, (_, index) => index + 1);
    }

    let start = Math.max(2, Math.min(current - 1, last - 4));
    let end = Math.min(last - 1, Math.max(current + 1, 5));
    if (start === 3) {
        start = 2;
    }
    if (end === last - 2) {
        end = last - 1;
    }

    const pages: (number | null)[] = [1];
    if (start > 2) {
        pages.push(null);
    }
    for (let page = start; page <= end; page++) {
        pages.push(page);
    }
    if (end < last - 1) {
        pages.push(null);
    }
    pages.push(last);

    return pages;
};
