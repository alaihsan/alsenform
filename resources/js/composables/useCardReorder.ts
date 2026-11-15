import { nextTick, onBeforeUnmount, ref } from 'vue';

/** A place on screen, in pixels of the viewport. */
export type ReorderBox = { top: number; left: number; width: number };

/**
 * The place a card dragged from `from` takes when dropped at height `y`: in front of the first card
 * whose middle is below `y` (`middles` are those of all the cards in order, the dragged one's own
 * included), counted once it has left its old place. Null when it stays where it is.
 */
export function dropIndex(middles: number[], y: number, from: number): number | null {
    const before = middles.findIndex((middle) => y < middle);
    const insertAt = before === -1 ? middles.length : before;
    const to = insertAt > from ? insertAt - 1 : insertAt;

    return to === from ? null : to;
}

/** Holding the handle this long, or moving it this far, picks the card up. */
const HOLD_MS = 180;
const MOVE_TOLERANCE_PX = 6;
/** Near the top or bottom of the screen the page scrolls, faster closer to the edge. */
const EDGE_PX = 80;
const MAX_SCROLL_PX = 18;
/** Half the space between two cards: where the line is drawn. */
const LINE_GAP_PX = 8;
/** The pointer is this far below the top of the card that follows it. */
const GHOST_OFFSET_PX = 28;
const GHOST_HEIGHT_PX = 64;

/**
 * Moving cards of a list, like in Google Forms: hold the handle (or move it a little) and drag; a
 * line shows where the card lands, the page scrolls near the edges of the screen, and Escape
 * cancels. Pointer events work with a mouse, a pen and touch screens, which HTML drag and drop
 * does not (the handle needs touch-action: none). The arrow keys on a handle move its card one
 * place. The list is not changed while dragging: the card moves once, when it is dropped.
 */
export function useCardReorder(options: {
    /** The element holding the cards, each marked `data-reorder-card`, in the order of the items. */
    list: () => HTMLElement | null;
    /** How many items there are; a card still leaving the list (being deleted) stops a drag. */
    count: () => number;
    move: (from: number, to: number) => void;
}) {
    const draggingIndex = ref<number | null>(null);
    const ghost = ref<ReorderBox | null>(null);
    const line = ref<ReorderBox | null>(null);

    let press: { index: number; pointerId: number; pointerType: string; x: number; y: number; handle: HTMLElement; timer: number } | null = null;
    let pointerY = 0;
    let target: number | null = null;
    let frame = 0;
    let bodyStyle: { userSelect: string; cursor: string } | null = null;

    const cards = (): HTMLElement[] => Array.from(options.list()?.querySelectorAll<HTMLElement>('[data-reorder-card]') ?? []);

    /** Where the card would land, with its ghost under the pointer and the line in front of it. */
    const update = (): void => {
        const from = draggingIndex.value;
        const list = options.list();
        if (from === null || !list) {
            return;
        }

        const listBox = list.getBoundingClientRect();
        const rects = cards().map((card) => card.getBoundingClientRect());
        ghost.value = {
            top: Math.min(Math.max(pointerY - GHOST_OFFSET_PX, 8), window.innerHeight - GHOST_HEIGHT_PX - 8),
            left: listBox.left,
            width: listBox.width,
        };

        target =
            rects.length === options.count()
                ? dropIndex(
                      rects.map((rect) => (rect.top + rect.bottom) / 2),
                      pointerY,
                      from,
                  )
                : null;
        if (target === null) {
            line.value = null;

            return;
        }

        const insertAt = target > from ? target + 1 : target;
        const y = insertAt === 0 ? rects[0].top - LINE_GAP_PX : rects[insertAt - 1].bottom + LINE_GAP_PX;
        line.value = { top: y, left: listBox.left, width: listBox.width };
    };

    /** Every frame of a drag: scroll near the edges of the screen, and follow what moved under the pointer. */
    const tick = (): void => {
        if (draggingIndex.value === null) {
            return;
        }

        const bottomEdge = window.innerHeight - EDGE_PX;
        const pull = pointerY < EDGE_PX ? -(EDGE_PX - pointerY) / EDGE_PX : pointerY > bottomEdge ? (pointerY - bottomEdge) / EDGE_PX : 0;
        if (pull !== 0) {
            window.scrollBy(0, Math.sign(pull) * Math.ceil(Math.min(Math.abs(pull), 1) * MAX_SCROLL_PX));
        }
        update();

        frame = window.requestAnimationFrame(tick);
    };

    const finish = (): void => {
        if (press) {
            window.clearTimeout(press.timer);
            try {
                press.handle.releasePointerCapture(press.pointerId);
            } catch {
                // The pointer is already gone.
            }
        }
        window.cancelAnimationFrame(frame);
        window.removeEventListener('pointermove', onMove);
        window.removeEventListener('pointerup', onUp);
        window.removeEventListener('pointercancel', finish);
        window.removeEventListener('keydown', onKey);
        window.removeEventListener('blur', finish);
        if (bodyStyle) {
            document.body.style.userSelect = bodyStyle.userSelect;
            document.body.style.cursor = bodyStyle.cursor;
            bodyStyle = null;
        }

        press = null;
        target = null;
        draggingIndex.value = null;
        ghost.value = null;
        line.value = null;
    };

    const begin = (): void => {
        if (!press || draggingIndex.value !== null) {
            return;
        }
        window.clearTimeout(press.timer);
        if (cards().length !== options.count()) {
            finish();

            return;
        }

        draggingIndex.value = press.index;
        bodyStyle = { userSelect: document.body.style.userSelect, cursor: document.body.style.cursor };
        document.body.style.userSelect = 'none';
        document.body.style.cursor = 'grabbing';
        if (press.pointerType === 'touch') {
            navigator.vibrate?.(8);
        }

        update();
        frame = window.requestAnimationFrame(tick);
    };

    function onMove(event: PointerEvent): void {
        if (!press || event.pointerId !== press.pointerId) {
            return;
        }

        pointerY = event.clientY;
        if (draggingIndex.value === null) {
            if (Math.hypot(event.clientX - press.x, event.clientY - press.y) > MOVE_TOLERANCE_PX) {
                begin();
            }

            return;
        }

        event.preventDefault();
        update();
    }

    function onUp(event: PointerEvent): void {
        if (!press || event.pointerId !== press.pointerId) {
            return;
        }

        pointerY = event.clientY;
        const from = draggingIndex.value;
        if (from !== null) {
            update();
        }
        const to = target;
        finish();

        if (from !== null && to !== null) {
            options.move(from, to);
        }
    }

    function onKey(event: KeyboardEvent): void {
        if (event.key === 'Escape') {
            event.preventDefault();
            finish();
        }
    }

    /** On pointerdown of a handle: the card is picked up once held, or moved a little. */
    const start = (index: number, event: PointerEvent): void => {
        if (press || (event.pointerType === 'mouse' && event.button !== 0)) {
            return;
        }

        const handle = event.currentTarget as HTMLElement;
        // No text selection and no focus change; the handle's touch-action keeps the page still.
        event.preventDefault();
        try {
            handle.setPointerCapture(event.pointerId);
        } catch {
            // Window listeners follow the pointer anyway.
        }

        pointerY = event.clientY;
        press = {
            index,
            pointerId: event.pointerId,
            pointerType: event.pointerType,
            x: event.clientX,
            y: event.clientY,
            handle,
            timer: window.setTimeout(begin, HOLD_MS),
        };
        window.addEventListener('pointermove', onMove, { passive: false });
        window.addEventListener('pointerup', onUp);
        window.addEventListener('pointercancel', finish);
        window.addEventListener('keydown', onKey);
        window.addEventListener('blur', finish);
    };

    /** Arrow up or down on a handle moves its card one place, and keeps the handle in focus. */
    const moveByKey = (index: number, event: KeyboardEvent): void => {
        const step = event.key === 'ArrowUp' ? -1 : event.key === 'ArrowDown' ? 1 : 0;
        if (step === 0 || press) {
            return;
        }

        event.preventDefault();
        const to = index + step;
        if (to < 0 || to >= options.count()) {
            return;
        }

        const handle = event.currentTarget as HTMLElement;
        options.move(index, to);
        nextTick(() => {
            handle.focus({ preventScroll: true });
            handle.scrollIntoView({ block: 'nearest' });
        });
    };

    onBeforeUnmount(finish);

    return { draggingIndex, ghost, line, start, moveByKey };
}
