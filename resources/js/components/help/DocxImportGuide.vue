<script setup lang="ts">
import { Download, FileText } from 'lucide-vue-next';

/** One sample of the Word template (see DocxImportTemplate::guide()). */
export interface DocxGuideSample {
    title: string;
    summary: string;
    key: string;
    note: string | null;
    typeAliases: string[];
    lines: string[];
    table: string[][] | null;
    answer: string | null;
}

defineProps<{
    title: string;
    samples: DocxGuideSample[];
}>();

const rules = [
    {
        title: 'Nomor soal diketik manual',
        text: 'Tulis "1." atau "1)" di awal soal. Penomoran dan bullet otomatis Word tidak ikut terbaca.',
    },
    {
        title: 'Setiap soal diakhiri baris Jawaban',
        text: 'Baris "Jawaban: ..." (atau "Kunci: ...") menjadi pemisah antarsoal. Soal tanpa kunci, seperti survei atau esai, tetap ditulis "Jawaban: -".',
    },
    {
        title: 'Pilihan jawaban memakai huruf A sampai E',
        text: 'Tulis "A.", "B.", dan seterusnya, masing-masing di paragraf sendiri.',
    },
    {
        title: 'Satu baris, satu paragraf',
        text: 'Teks soal, setiap pilihan, baris Tipe/Poin/Wajib/Skala, dan baris Jawaban masing-masing diakhiri tombol Enter.',
    },
    {
        title: 'Tipe soal ditulis dengan baris "Tipe: ..."',
        text: 'Boleh tidak ditulis untuk Pilihan Ganda dan Isian Singkat: soal dengan pilihan A–E otomatis menjadi Pilihan Ganda, soal tanpa pilihan menjadi Isian Singkat.',
    },
    {
        title: 'Gambar ikut terimpor',
        text: 'Sisipkan gambar (Insert › Pictures) di bawah teks soal, sebelum baris Jawaban.',
    },
    {
        title: 'Rumus ditulis di antara tanda dolar',
        text: 'Contoh: $x^2 + 2x + 1$. Rumus dari Equation Editor Word tidak terbaca.',
    },
    {
        title: 'Kop dan petunjuk ujian',
        text: 'Teks pengantar boleh ditulis di atas baris "MULAI SOAL" agar tidak ikut terbaca. Baris yang diawali "//" juga diabaikan, cocok untuk catatan pribadi.',
    },
];

const optionalLines = [
    {
        line: 'Poin: 1',
        text: 'Bobot nilai soal. Bawaan 1; tulis angka lain (misalnya Poin: 2) untuk soal yang lebih berbobot. Nilai akhir murid tetap 0–100.',
    },
    { line: 'Wajib: Ya', text: 'Soal harus dijawab sebelum jawaban bisa dikirim. Bawaan: tidak wajib.' },
    { line: 'Skala: 1-10', text: 'Rentang Skala Linear atau jumlah bintang Rating. Bawaan 1-5, paling besar 0-10.' },
];

const warnings = [
    { title: 'Tipe tidak dikenal', text: 'Tipe ditentukan otomatis dari bentuk soal. Periksa ejaan baris "Tipe:".' },
    { title: 'Kunci tidak cocok', text: 'Kunci dikosongkan, misalnya "Jawaban: F" padahal pilihan hanya A–D. Isi kuncinya di editor.' },
    { title: 'Tidak ada pilihan A–E', text: 'Soal bertipe pilihan tanpa pilihan dijadikan Isian Singkat.' },
    { title: 'Tanggal atau waktu tidak terbaca', text: 'Gunakan format 17-08-1945 untuk tanggal dan 07:30 untuk waktu.' },
];

const isMetaLine = (line: string) => /^(Tipe|Poin|Wajib|Skala):/.test(line);
const isAnswerLine = (line: string) => /^(Jawaban|Kunci):/.test(line);
const sampleAnchor = (index: number) => `docx-type-${index + 1}`;
</script>

<template>
    <div>
        <div class="flex items-start gap-3 border-b border-slate-100 pb-4">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-sky-50 text-sky-600">
                <FileText class="h-5 w-5" />
            </div>
            <div class="min-w-0">
                <h2 class="text-lg font-bold text-slate-900 sm:text-xl">{{ title }}</h2>
                <p class="text-xs text-slate-500">Aturan penulisan dan contoh setiap tipe soal untuk impor dari Microsoft Word</p>
            </div>
        </div>

        <div class="mt-6 space-y-8 text-sm leading-relaxed text-slate-700">
            <div class="flex flex-col gap-3 rounded-2xl border border-indigo-100 bg-indigo-50/60 p-4 sm:flex-row sm:items-center">
                <p class="flex-1 text-sm text-indigo-950">
                    Templat berisi 15 contoh soal, satu untuk setiap tipe. Unduh, ganti contohnya dengan soal Anda, lalu impor di editor:
                    <strong>ikon Import Soal › Word (.docx)</strong>.
                </p>
                <a
                    :href="route('questions.import.template')"
                    class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-xs font-bold text-white shadow-sm transition hover:bg-indigo-700"
                >
                    <Download class="h-4 w-4" />
                    Unduh Templat (.docx)
                </a>
            </div>

            <section class="space-y-3">
                <h3 class="text-base font-bold text-slate-900">Aturan Penulisan</h3>
                <ol class="space-y-2">
                    <li
                        v-for="(rule, index) in rules"
                        :key="rule.title"
                        class="flex items-start gap-3 rounded-2xl border border-slate-100 bg-slate-50 p-3.5"
                    >
                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-black text-indigo-700">
                            {{ index + 1 }}
                        </span>
                        <span class="min-w-0">
                            <span class="block font-bold text-slate-900">{{ rule.title }}</span>
                            <span class="block text-xs text-slate-600 sm:text-sm">{{ rule.text }}</span>
                        </span>
                    </li>
                </ol>
            </section>

            <section class="space-y-3">
                <h3 class="text-base font-bold text-slate-900">Baris Tambahan (Opsional)</h3>
                <p class="text-xs text-slate-500 sm:text-sm">Ditulis di dalam soal sebelum baris Jawaban, sebaiknya tepat di bawah teks soal.</p>
                <dl class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                    <div v-for="item in optionalLines" :key="item.line" class="rounded-2xl border border-slate-200 p-3.5">
                        <dt>
                            <code class="rounded-lg bg-indigo-50 px-2 py-1 font-mono text-xs font-bold text-indigo-700">{{ item.line }}</code>
                        </dt>
                        <dd class="mt-2 text-xs text-slate-600">{{ item.text }}</dd>
                    </div>
                </dl>
            </section>

            <section class="space-y-4">
                <div>
                    <h3 class="text-base font-bold text-slate-900">Tipe Soal & Contoh</h3>
                    <p class="text-xs text-slate-500 sm:text-sm">Contoh di bawah sama persis dengan isi templat.</p>
                </div>

                <nav class="-mx-1 flex flex-wrap gap-1.5" aria-label="Daftar tipe soal">
                    <a
                        v-for="(sample, index) in samples"
                        :key="sample.title"
                        :href="`#${sampleAnchor(index)}`"
                        class="mx-0.5 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-[11px] font-semibold text-slate-600 transition hover:border-indigo-200 hover:text-indigo-700"
                    >
                        {{ sample.title }}
                    </a>
                </nav>

                <article
                    v-for="(sample, index) in samples"
                    :id="sampleAnchor(index)"
                    :key="sample.title"
                    class="scroll-mt-24 overflow-hidden rounded-2xl border border-slate-200"
                >
                    <header class="flex items-center gap-3 border-b border-slate-100 bg-slate-50/80 px-4 py-3">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-xs font-black text-white">
                            {{ index + 1 }}
                        </span>
                        <h4 class="min-w-0 font-bold text-slate-900">{{ sample.title }}</h4>
                    </header>

                    <div class="grid grid-cols-1 gap-4 p-4 lg:grid-cols-2">
                        <div class="min-w-0 space-y-3 text-xs sm:text-sm">
                            <p>{{ sample.summary }}</p>
                            <p><span class="font-bold text-slate-900">Kunci jawaban:</span> {{ sample.key }}</p>
                            <p v-if="sample.note" class="rounded-xl border border-amber-200 bg-amber-50 p-2.5 text-xs text-amber-900">
                                {{ sample.note }}
                            </p>
                            <div v-if="sample.typeAliases.length">
                                <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-400"
                                    >Penulisan "Tipe:" yang diterima</span
                                >
                                <div class="mt-1.5 flex flex-wrap gap-1.5">
                                    <code
                                        v-for="alias in sample.typeAliases"
                                        :key="alias"
                                        class="rounded-md bg-slate-100 px-1.5 py-0.5 font-mono text-[11px] text-slate-700"
                                        >{{ alias }}</code
                                    >
                                </div>
                            </div>
                        </div>

                        <div class="min-w-0 rounded-xl border border-slate-200 bg-white p-3 font-mono text-xs leading-relaxed text-slate-800">
                            <p class="mb-2 font-sans text-[11px] font-bold uppercase tracking-wider text-slate-400">Contoh di Word</p>
                            <p
                                v-for="(line, lineIndex) in sample.lines"
                                :key="lineIndex"
                                :class="[
                                    'whitespace-pre-wrap break-words',
                                    lineIndex === 0 || isAnswerLine(line) ? 'font-bold' : '',
                                    isMetaLine(line) ? 'text-indigo-700' : '',
                                ]"
                            >
                                {{ line }}
                            </p>
                            <div v-if="sample.table" class="my-2 overflow-x-auto">
                                <table class="w-full min-w-[260px] border-collapse font-sans text-[11px]">
                                    <tbody>
                                        <tr v-for="(row, rowIndex) in sample.table" :key="rowIndex">
                                            <td
                                                v-for="(cell, cellIndex) in row"
                                                :key="cellIndex"
                                                :class="[
                                                    'border border-slate-300 px-2 py-1',
                                                    rowIndex === 0 ? 'bg-indigo-50 font-bold text-indigo-900' : '',
                                                    cell === 'X' ? 'text-center font-bold' : '',
                                                ]"
                                            >
                                                {{ cell }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <p v-if="sample.answer" class="font-bold">{{ sample.answer }}</p>
                        </div>
                    </div>
                </article>
            </section>

            <section class="space-y-3">
                <h3 class="text-base font-bold text-slate-900">Jika Muncul Catatan Setelah Impor</h3>
                <p class="text-xs text-slate-500 sm:text-sm">
                    Soal tetap diimpor. Kotak kuning di jendela impor menyebutkan nomor soal yang perlu diperiksa di editor:
                </p>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <div v-for="warning in warnings" :key="warning.title" class="rounded-2xl border border-amber-100 bg-amber-50/60 p-3.5">
                        <p class="font-bold text-amber-900">{{ warning.title }}</p>
                        <p class="mt-0.5 text-xs text-amber-900/80">{{ warning.text }}</p>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>
