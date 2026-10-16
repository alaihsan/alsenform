import type { SectionTarget } from '@/types/quiz';

/*
 * Going from section to section, like in Google Forms (the same rules as App\Support\SectionFlow
 * on the server). After a section the student goes on to the next section, to another section, or
 * straight to the end, as the teacher set for that section. A multiple-choice or drop-down question
 * set to "Buka bagian berdasarkan jawaban" decides by its answer instead (the last such question
 * answered in the section). Only the sections on the student's way count.
 *
 * A section is known by its key: the id of its section item, or 0 for the first section of a form
 * that does not start with one (the form's title is its header).
 */

export const NEXT_SECTION = 'next';
export const SUBMIT_FORM = 'submit';
export const FIRST_SECTION = 0;

/** Question types that can send the student to a section by their answer. */
export const BRANCHING_TYPES: string[] = ['Multiple choice', 'Drop-down'];

/** What the flow needs of an item of the form (the editor's and the exam page's questions). */
export type FlowItem = { id: number; type: string; options: string[]; goTo?: SectionTarget; branching?: boolean; optionGoTo?: SectionTarget[] };

export type FlowSection<T extends FlowItem = FlowItem> = { key: number; header: T | null; items: T[] };

/** The sections in their order, each with its key, its section item and the items in it. */
export function flowSections<T extends FlowItem>(items: T[]): FlowSection<T>[] {
    const sections: FlowSection<T>[] = [];
    items.forEach((item, index) => {
        if (item.type === 'Section') {
            sections.push({ key: Number(item.id), header: item, items: [] });
            return;
        }
        if (index === 0) {
            sections.push({ key: FIRST_SECTION, header: null, items: [] });
        }
        sections[sections.length - 1].items.push(item);
    });

    return sections;
}

const asTarget = (target: unknown): SectionTarget =>
    target === SUBMIT_FORM
        ? SUBMIT_FORM
        : typeof target === 'number' || (typeof target === 'string' && /^\d+$/.test(target))
          ? Number(target)
          : NEXT_SECTION;

/** Whether the question sends the student to a section by their answer. */
export const sendsByAnswer = (question: FlowItem): boolean => question.branching === true && BRANCHING_TYPES.includes(question.type);

/** Where a section leads by its own setting. */
export const sectionGoTo = (section: FlowSection, firstSectionGoTo?: SectionTarget | null): SectionTarget =>
    asTarget(section.header ? section.header.goTo : firstSectionGoTo);

/** Where the student goes after a section, given their answers. */
export function targetAfter(
    section: FlowSection,
    firstSectionGoTo: SectionTarget | null | undefined,
    answers: Record<number, unknown>,
): SectionTarget {
    let target = sectionGoTo(section, firstSectionGoTo);
    for (const item of section.items) {
        const answer = answers[item.id];
        if (!sendsByAnswer(item) || typeof answer !== 'string') {
            continue;
        }
        const at = item.options.indexOf(answer);
        if (at >= 0) {
            target = asTarget(item.optionGoTo?.[at]);
        }
    }

    return target;
}

/**
 * The keys of the sections on the student's way, in order. The way ends at "submit", after the
 * last section, or where it would come back to a section already on it.
 */
export function sectionPath(items: FlowItem[], firstSectionGoTo: SectionTarget | null | undefined, answers: Record<number, unknown>): number[] {
    const sections = flowSections(items);
    const path: number[] = [];
    let at: number | null = sections.length ? 0 : null;

    while (at !== null && !path.includes(sections[at].key)) {
        path.push(sections[at].key);
        const target = targetAfter(sections[at], firstSectionGoTo, answers);
        const next: number | null = at + 1 < sections.length ? at + 1 : null;
        if (target === SUBMIT_FORM) {
            at = null;
        } else if (target === NEXT_SECTION) {
            at = next;
        } else {
            const position = sections.findIndex((section) => section.key === target);
            at = position >= 0 ? position : next;
        }
    }

    return path;
}

/** Whether anyone can be sent somewhere other than the next section, and so skip questions. */
export function formBranches(items: FlowItem[], firstSectionGoTo?: SectionTarget | null): boolean {
    const sections = flowSections(items);

    return (
        sections.length > 1 &&
        sections.some(
            (section) =>
                sectionGoTo(section, firstSectionGoTo) !== NEXT_SECTION ||
                section.items.some((item) => sendsByAnswer(item) && (item.optionGoTo ?? []).some((target) => asTarget(target) !== NEXT_SECTION)),
        )
    );
}
