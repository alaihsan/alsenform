import { Atom, Sigma } from 'lucide-vue-next';
import type { Component } from 'vue';

/*
 * The formula palettes of the editor, each one an add-on: Rumus Matematika and Rumus IPA. A
 * palette is a catalogue of LaTeX formulas by topic and chapter; a click puts the formula in the
 * question between $...$, where KaTeX draws it (with mhchem for \ce{...} and \pu{...}). The
 * catalogues are loaded only when a palette is first opened.
 */

/** One formula: its name, its LaTeX, and what its symbols mean (with their units). */
export type Formula = {
    label: string;
    latex: string;
    note?: string;
};

/** A chapter of a topic, e.g. "Persamaan Kuadrat" of Aljabar or "Kinematika" of Fisika. */
export type FormulaGroup = {
    name: string;
    formulas: Formula[];
};

/** A tab of a palette, e.g. Trigonometri, or Fisika, Kimia and Biologi in Rumus IPA. */
export type FormulaTopic = {
    key: string;
    name: string;
    groups: FormulaGroup[];
};

/** What the question toolbar knows of a palette before its catalogue is loaded. */
export type FormulaPalette = {
    /** The key of the palette's add-on. */
    key: 'math-formulas' | 'science-formulas';
    title: string;
    /** The short name on the toolbar button. */
    buttonLabel: string;
    icon: Component;
    hint: string;
    /** What the free LaTeX box starts with. */
    example: string;
    searchPlaceholder: string;
    load: () => Promise<FormulaTopic[]>;
};

export const FORMULA_PALETTES: FormulaPalette[] = [
    {
        key: 'math-formulas',
        title: 'Katalog Rumus Matematika',
        buttonLabel: 'Rumus Matematika',
        icon: Sigma,
        hint: 'Bilangan, aljabar, fungsi, trigonometri, geometri, barisan, kalkulus, matriks, statistika, logika dan notasi',
        example: String.raw`x_{1,2} = \frac{-b \pm \sqrt{b^{2} - 4ac}}{2a}`,
        searchPlaceholder: 'Cari rumus: pecahan, diskriminan, integral, peluang...',
        load: () => import('./math').then((module) => module.MATH_TOPICS),
    },
    {
        key: 'science-formulas',
        title: 'Katalog Rumus IPA',
        buttonLabel: 'Rumus IPA',
        icon: Atom,
        hint: 'Fisika, Kimia dan Biologi dengan keterangan simbol dan satuan; reaksi kimia memakai \\ce{...}',
        example: String.raw`\ce{2H2(g) + O2(g) -> 2H2O(l)}`,
        searchPlaceholder: 'Cari rumus: gaya, Bernoulli, pH, Ksp, fotosintesis, Hardy-Weinberg...',
        load: () => import('./science').then((module) => module.SCIENCE_TOPICS),
    },
];

/** Raw LaTeX without doubled backslashes: tex`\frac{a}{b}`. */
export const tex = String.raw;

/** A formula of a catalogue. */
export const formula = (label: string, latex: string, note?: string): Formula => (note ? { label, latex, note } : { label, latex });

/** The formula as it is put in a question: inline math between dollar signs. */
export const asQuestionText = (latex: string): string => `$${latex.trim()}$`;
