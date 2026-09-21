<script setup lang="ts">
import { ref } from 'vue';
import RichContent from '@/components/RichContent.vue';
import {
    AlignRight,
    X,
} from 'lucide-vue-next';

const props = defineProps<{
    previewText?: string;
}>();

const emit = defineEmits<{
    (e: 'insert', snippet: string): void;
    (e: 'toggle-rtl'): void;
}>();

const isMathOpen = ref(false);
const customLatex = ref('\\frac{-b \\pm \\sqrt{b^2 - 4ac}}{2a}');

const mathCategories = [
    {
        name: 'Pecahan & Akar',
        items: [
            { label: 'Pecahan', snippet: '\\frac{a}{b}', preview: '\\frac{a}{b}' },
            { label: 'Akar Kuadrat', snippet: '\\sqrt{x}', preview: '\\sqrt{x}' },
            { label: 'Akar Pangkat n', snippet: '\\sqrt[n]{x}', preview: '\\sqrt[n]{x}' },
            { label: 'Pecahan Campuran', snippet: 'c\\frac{a}{b}', preview: 'c\\frac{a}{b}' },
        ],
    },
    {
        name: 'Pangkat & Indeks',
        items: [
            { label: 'Pangkat (x²)', snippet: 'x^{2}', preview: 'x^{2}' },
            { label: 'Pangkat Variabel', snippet: 'x^{n}', preview: 'x^{n}' },
            { label: 'Indeks (x₁)', snippet: 'x_{1}', preview: 'x_{1}' },
            { label: 'Indeks & Pangkat', snippet: 'x_{1}^{2}', preview: 'x_{1}^{2}' },
        ],
    },
    {
        name: 'Simbol Matematika',
        items: [
            { label: 'Kali (×)', snippet: '\\times', preview: '\\times' },
            { label: 'Bagi (÷)', snippet: '\\div', preview: '\\div' },
            { label: 'Plus-Minus (±)', snippet: '\\pm', preview: '\\pm' },
            { label: 'Tidak Sama (≠)', snippet: '\\neq', preview: '\\neq' },
            { label: 'Kurang Dari Sama (≤)', snippet: '\\le', preview: '\\le' },
            { label: 'Lebih Dari Sama (≥)', snippet: '\\ge', preview: '\\ge' },
            { label: 'Mendekati (≈)', snippet: '\\approx', preview: '\\approx' },
            { label: 'Tak Hingga (∞)', snippet: '\\infty', preview: '\\infty' },
        ],
    },
    {
        name: 'Huruf Yunani & Konstanta',
        items: [
            { label: 'Pi (π)', snippet: '\\pi', preview: '\\pi' },
            { label: 'Alpha (α)', snippet: '\\alpha', preview: '\\alpha' },
            { label: 'Beta (β)', snippet: '\\beta', preview: '\\beta' },
            { label: 'Theta (θ)', snippet: '\\theta', preview: '\\theta' },
            { label: 'Delta (Δ)', snippet: '\\Delta', preview: '\\Delta' },
            { label: 'Lambda (λ)', snippet: '\\lambda', preview: '\\lambda' },
            { label: 'Sigma (Σ)', snippet: '\\Sigma', preview: '\\Sigma' },
            { label: 'Omega (Ω)', snippet: '\\Omega', preview: '\\Omega' },
        ],
    },
    {
        name: 'Kalkulus & Aljabar',
        items: [
            { label: 'Integral Tentu', snippet: '\\int_{a}^{b} f(x)\\,dx', preview: '\\int_{a}^{b} f(x)\\,dx' },
            { label: 'Integral Tak Tentu', snippet: '\\int f(x)\\,dx', preview: '\\int f(x)\\,dx' },
            { label: 'Notasi Sigma (Sum)', snippet: '\\sum_{i=1}^{n} i', preview: '\\sum_{i=1}^{n} i' },
            { label: 'Limit', snippet: '\\lim_{x \\to 0} f(x)', preview: '\\lim_{x \\to 0} f(x)' },
            { label: 'Logaritma', snippet: '\\log_{a}(b)', preview: '\\log_{a}(b)' },
            { label: 'Trigonometri', snippet: '\\sin(x) + \\cos(x)', preview: '\\sin(x) + \\cos(x)' },
        ],
    },
    {
        name: 'Matriks & Vektor',
        items: [
            {
                label: 'Matriks 2x2',
                snippet: '\\begin{pmatrix} a & b \\\\ c & d \\end{pmatrix}',
                preview: '\\begin{pmatrix} a & b \\\\ c & d \\end{pmatrix}',
            },
            {
                label: 'Determinan 2x2',
                snippet: '\\begin{vmatrix} a & b \\\\ c & d \\end{vmatrix}',
                preview: '\\begin{vmatrix} a & b \\\\ c & d \\end{vmatrix}',
            },
            { label: 'Vektor', snippet: '\\vec{v}', preview: '\\vec{v}' },
        ],
    },
];

function insertMath(snippet: string) {
    emit('insert', `$${snippet}$`);
}

function insertCustomMath() {
    if (!customLatex.value.trim()) return;
    emit('insert', `$${customLatex.value.trim()}$`);
    isMathOpen.value = false;
}
</script>

<template>
    <div class="relative z-10 mb-2 flex flex-wrap items-center justify-between gap-1.5 rounded-lg border border-slate-200 bg-slate-50/80 px-2.5 py-1 text-xs text-slate-600">
        <div class="flex items-center gap-1.5">
            <!-- Math Formula Button -->
            <!-- Math Formula Button (Merged with $rumus$) -->
            <button
                type="button"
                :class="[
                    'inline-flex items-center gap-1.5 rounded-md px-2.5 py-1 font-semibold transition shadow-xs border',
                    isMathOpen
                        ? 'bg-indigo-600 text-white border-indigo-600'
                        : 'bg-white text-indigo-700 hover:bg-indigo-50 border-indigo-200',
                ]"
                title="Buka panel rumus matematika KaTeX ($rumus$)"
                @click="isMathOpen = !isMathOpen"
            >
                <span class="font-serif italic font-bold">f(x)</span>
                <span>Rumus Matematika</span>
                <code
                    :class="[
                        'rounded px-1.5 py-0.5 text-[10px] font-mono transition',
                        isMathOpen
                            ? 'bg-indigo-700 text-indigo-100'
                            : 'bg-indigo-50 text-indigo-600 border border-indigo-100',
                    ]"
                >
                    $rumus$
                </code>
            </button>
        </div>

        <div class="flex items-center gap-2">
            <span class="hidden text-[10px] text-slate-400 md:inline">
                Didukung oleh KaTeX & Font Quran Madinah
            </span>
            <!-- RTL Toggle -->
            <button
                type="button"
                class="inline-flex items-center gap-1 rounded border border-slate-200 bg-white px-2 py-0.5 text-[11px] text-slate-600 hover:bg-slate-100"
                title="Ubah perataan teks Kanan/Kiri (RTL)"
                @click="emit('toggle-rtl')"
            >
                <AlignRight class="h-3 w-3" />
                <span>RTL</span>
            </button>
        </div>

        <!-- Math Helper Palette Modal/Popover -->
        <div
            v-if="isMathOpen"
            class="absolute left-0 top-full mt-2 w-full max-w-xl rounded-2xl border border-slate-200 bg-white p-4 shadow-xl z-30 animate-in fade-in zoom-in-95 duration-150"
        >
            <div class="mb-3 flex items-center justify-between border-b border-slate-100 pb-2">
                <div class="flex items-center gap-1.5">
                    <span class="flex h-6 w-6 items-center justify-center rounded-lg bg-indigo-50 font-serif font-bold text-indigo-700">∑</span>
                    <h4 class="font-bold text-slate-800">Katalog Rumus Matematika (KaTeX)</h4>
                </div>
                <button
                    type="button"
                    class="rounded-full p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                    @click="isMathOpen = false"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>

            <!-- Custom LaTeX Input with Live Preview -->
            <div class="mb-3 rounded-xl border border-slate-200 bg-slate-50 p-2.5">
                <div class="mb-1 flex items-center justify-between text-[11px] font-bold text-slate-600">
                    <span>Uji / Ketik Kode LaTeX Bebas:</span>
                    <span class="text-[10px] font-normal text-slate-400">Gunakan tag $...$ di teks soal</span>
                </div>
                <div class="flex gap-1.5">
                    <input
                        v-model="customLatex"
                        type="text"
                        placeholder="Contoh: \frac{a}{b} atau x^2 + y^2 = r^2"
                        class="flex-1 rounded-lg border border-slate-300 bg-white px-2.5 py-1 text-xs font-mono outline-none focus:border-indigo-500"
                    />
                    <button
                        type="button"
                        class="rounded-lg bg-indigo-600 px-3 py-1 text-xs font-bold text-white hover:bg-indigo-700 shrink-0"
                        @click="insertCustomMath"
                    >
                        Sisipkan
                    </button>
                </div>
                <!-- Live LaTeX Preview -->
                <div class="mt-2 flex items-center justify-center rounded-lg border border-indigo-100 bg-white py-2 min-h-10 text-slate-800">
                    <RichContent :content="`$${customLatex}$`" />
                </div>
            </div>

            <!-- Category Grid -->
            <div class="max-h-72 overflow-y-auto space-y-3 pr-1 text-slate-700">
                <div v-for="cat in mathCategories" :key="cat.name">
                    <h5 class="mb-1.5 text-[11px] font-bold uppercase tracking-wider text-slate-400">{{ cat.name }}</h5>
                    <div class="grid grid-cols-2 gap-1.5 sm:grid-cols-4">
                        <button
                            v-for="item in cat.items"
                            :key="item.label"
                            type="button"
                            class="flex flex-col items-center justify-center rounded-xl border border-slate-200 bg-slate-50/70 p-2 text-center transition hover:border-indigo-400 hover:bg-indigo-50/50"
                            :title="item.snippet"
                            @click="insertMath(item.snippet); isMathOpen = false"
                        >
                            <div class="mb-1 min-h-6 flex items-center justify-center text-sm">
                                <RichContent :content="`$${item.preview}$`" />
                            </div>
                            <span class="text-[10px] text-slate-500">{{ item.label }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
