<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    BookOpen,
    CheckCircle2,
    ClipboardList,
    FileSpreadsheet,
    GraduationCap,
    HelpCircle,
    KeyRound,
    Layers,
    Search,
    Shield,
    Sparkles,
    UploadCloud,
    Users,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Pusat Bantuan & Panduan',
        href: '/help',
    },
];

const searchQuery = ref('');
const activeSection = ref<string>('getting-started');

interface HelpTopic {
    id: string;
    title: string;
    icon: any;
    badge: string;
    badgeColor: string;
    summary: string;
}

const topics: HelpTopic[] = [
    {
        id: 'getting-started',
        title: '1. Memulai & Alur Kerja Kuis',
        icon: Sparkles,
        badge: 'Dasar',
        badgeColor: 'bg-emerald-50 text-emerald-700 border-emerald-200',
        summary: 'Panduan awal membuat kuis, memilih template ujian, mengorganisir folder, dan kolaborasi guru.',
    },
    {
        id: 'question-editor',
        title: '2. Editor Soal & Tipe Pertanyaan',
        icon: ClipboardList,
        badge: 'Editor',
        badgeColor: 'bg-indigo-50 text-indigo-700 border-indigo-200',
        summary: 'Pilihan ganda, esai, checkbox, grid, pengaturan kunci jawaban, dan pembobotan skor butir soal.',
    },
    {
        id: 'arabic-quran',
        title: '3. Bahasa Arab & Al-Qur\'an (Mushaf Madinah)',
        icon: BookOpen,
        badge: 'Fitur Utama',
        badgeColor: 'bg-emerald-50 text-emerald-700 border-emerald-200',
        summary: 'Font Amiri Quran Madinah, Scheherazade New, tabel shortcut keyboard Windows tanda harakat dan waqf.',
    },
    {
        id: 'math-katex',
        title: '4. Rumus Matematika & Sains (KaTeX)',
        icon: Layers,
        badge: 'Fitur Utama',
        badgeColor: 'bg-blue-50 text-blue-700 border-blue-200',
        summary: 'Penulisan rumus matematika otomatis, tombol f(x), sintaks pecahan, akar kuadrat, pangkat, dan simbol.',
    },
    {
        id: 'examview-import',
        title: '5. Import Soal ExamView / Blackboard (ZIP)',
        icon: UploadCloud,
        badge: 'Import',
        badgeColor: 'bg-cyan-50 text-cyan-700 border-cyan-200',
        summary: 'Panduan ekspor Blackboard 6.0-8.0 dari ExamView dan impor ke Alsenform lengkap dengan gambar soal.',
    },
    {
        id: 'proctoring-anti-cheat',
        title: '6. Pengawasan CBT & Fitur Anti-Curang',
        icon: Shield,
        badge: 'Keamanan',
        badgeColor: 'bg-red-50 text-red-700 border-red-200',
        summary: 'Deteksi Lock on Blur (pindah tab), toleransi pelanggaran, permintaan buka blokir, dan PIN Pengawas.',
    },
    {
        id: 'students-cohort',
        title: '7. Manajemen Siswa & Kelas (Cohort)',
        icon: Users,
        badge: 'Akademik',
        badgeColor: 'bg-amber-50 text-amber-700 border-amber-200',
        summary: 'Import data siswa dari Excel/CSV, manajemen NIS & kelas, proteksi akun siswa, dan grup kelas.',
    },
    {
        id: 'results-gradebook',
        title: '8. Rekap Nilai & Analisis Ujian',
        icon: FileSpreadsheet,
        badge: 'Laporan',
        badgeColor: 'bg-purple-50 text-purple-700 border-purple-200',
        summary: 'Koreksi otomatis, analisis KKM, statistik rata-rata, dan ekspor nilai ke berkas spreadsheet Excel/CSV.',
    },
    {
        id: 'profile-settings',
        title: '9. Pengaturan Profil & Keamanan Akun',
        icon: KeyRound,
        badge: 'Akun',
        badgeColor: 'bg-slate-100 text-slate-700 border-slate-200',
        summary: 'Upload foto profil avatar, manajemen sesi perangkat aktif, preferensi default kuis, dan backup arsip JSON.',
    },
];

const filteredTopics = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) {
        return topics;
    }
    return topics.filter(
        (t) =>
            t.title.toLowerCase().includes(q) ||
            t.summary.toLowerCase().includes(q)
    );
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Pusat Panduan & Bantuan Alsenform" />

        <div class="min-h-screen bg-slate-50/60 pb-16">
            <!-- HERO HEADER -->
            <div class="border-b border-slate-200/80 bg-white">
                <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
                    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <Link
                                    :href="route('dashboard')"
                                    class="inline-flex items-center gap-1 text-xs font-semibold text-slate-500 hover:text-slate-800"
                                >
                                    <ArrowLeft class="h-3.5 w-3.5" /> Kembali ke Dasbor
                                </Link>
                                <span class="text-slate-300">•</span>
                                <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-indigo-700">
                                    Dokumentasi Resmi
                                </span>
                            </div>
                            <h1 class="text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">
                                Pusat Panduan & Bantuan Alsenform
                            </h1>
                            <p class="text-sm text-slate-500">
                                Pelajari seluruh alur kerja pembuatan ujian, penulisan soal Arab & rumus matematika, pengawasan CBT, hingga rekap nilai siswa.
                            </p>
                        </div>

                        <!-- Live Search Input -->
                        <div class="w-full md:w-80">
                            <label class="relative block">
                                <Search class="absolute left-3.5 top-3 h-4 w-4 text-slate-400" />
                                <input
                                    v-model="searchQuery"
                                    type="search"
                                    placeholder="Cari topik panduan..."
                                    class="h-10 w-full rounded-2xl border border-slate-200 bg-slate-50/80 pl-10 pr-4 text-sm font-medium text-slate-800 shadow-xs outline-none transition focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-100"
                                />
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- MAIN CONTAINER -->
            <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
                <div class="grid grid-cols-1 gap-8 lg:grid-cols-12">
                    <!-- SIDEBAR NAVIGATION (4 Cols) -->
                    <aside class="space-y-2 lg:col-span-4">
                        <div class="sticky top-20 space-y-1 rounded-2xl border border-slate-200/90 bg-white p-2.5 shadow-sm">
                            <p class="px-3 py-2 text-[11px] font-bold uppercase tracking-wider text-slate-400">
                                Daftar Topik Panduan
                            </p>
                            <nav class="space-y-1">
                                <button
                                    v-for="topic in filteredTopics"
                                    :key="topic.id"
                                    type="button"
                                    @click="activeSection = topic.id"
                                    :class="[
                                        'flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-xs font-semibold transition',
                                        activeSection === topic.id
                                            ? 'bg-indigo-50 text-indigo-700 font-bold shadow-xs'
                                            : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900',
                                    ]"
                                >
                                    <component
                                        :is="topic.icon"
                                        :class="['h-4 w-4 shrink-0', activeSection === topic.id ? 'text-indigo-600' : 'text-slate-400']"
                                    />
                                    <span class="truncate flex-1">{{ topic.title }}</span>
                                    <span
                                        :class="[
                                            'rounded-full border px-1.5 py-0.5 text-[9px] font-bold shrink-0',
                                            topic.badgeColor,
                                        ]"
                                    >
                                        {{ topic.badge }}
                                    </span>
                                </button>
                            </nav>
                        </div>
                    </aside>

                    <!-- CONTENT PANELS (8 Cols) -->
                    <main class="space-y-8 lg:col-span-8">
                        <!-- 1. MEMULAI & ALUR KERJA KUIS -->
                        <section
                            v-show="activeSection === 'getting-started' || searchQuery.trim()"
                            id="getting-started"
                            class="rounded-3xl border border-slate-200/80 bg-white p-6 shadow-sm sm:p-8"
                        >
                            <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                                <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600">
                                    <Sparkles class="h-5 w-5" />
                                </div>
                                <div>
                                    <h2 class="text-xl font-bold text-slate-900">1. Memulai & Alur Kerja Kuis</h2>
                                    <p class="text-xs text-slate-500">Langkah mudah menyusun dan mendistribusikan kuis di Alsenform</p>
                                </div>
                            </div>

                            <div class="mt-6 space-y-5 text-sm text-slate-700 leading-relaxed">
                                <p>
                                    Alsenform dirancang dengan alur kerja modern berbasis formulir (seperti Google Forms) namun dioptimalkan penuh untuk standar ujian sekolah (CBT).
                                </p>

                                <h3 class="font-bold text-slate-900 text-base">Alur 4 Langkah Pelaksanaan Ujian:</h3>
                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    <div class="rounded-2xl border border-slate-100 bg-slate-50/70 p-4">
                                        <div class="flex items-center gap-2 font-bold text-indigo-700 text-xs uppercase tracking-wider mb-1">
                                            <span class="flex h-5 w-5 items-center justify-center rounded-full bg-indigo-100 text-indigo-800 text-[10px]">1</span>
                                            Buat / Pilih Template
                                        </div>
                                        <p class="text-xs text-slate-600">
                                            Mulai dari <em>Blank form</em> atau gunakan template siap pakai (PTS/PAS, PAI Arab, Matematika) dari baris template atas.
                                        </p>
                                    </div>
                                    <div class="rounded-2xl border border-slate-100 bg-slate-50/70 p-4">
                                        <div class="flex items-center gap-2 font-bold text-indigo-700 text-xs uppercase tracking-wider mb-1">
                                            <span class="flex h-5 w-5 items-center justify-center rounded-full bg-indigo-100 text-indigo-800 text-[10px]">2</span>
                                            Atur Soal & Kunci Jawaban
                                        </div>
                                        <p class="text-xs text-slate-600">
                                            Tulis pertanyaan, tentukan kunci jawaban yang benar, dan berikan bobot nilai (*poin*) untuk setiap butir soal.
                                        </p>
                                    </div>
                                    <div class="rounded-2xl border border-slate-100 bg-slate-50/70 p-4">
                                        <div class="flex items-center gap-2 font-bold text-indigo-700 text-xs uppercase tracking-wider mb-1">
                                            <span class="flex h-5 w-5 items-center justify-center rounded-full bg-indigo-100 text-indigo-800 text-[10px]">3</span>
                                            Konfigurasi Ujian (CBT)
                                        </div>
                                        <p class="text-xs text-slate-600">
                                            Tentukan durasi pengerjaan, KKM, acak urutan soal, dan aktifkan proteksi anti-curang (*Lock on Blur*).
                                        </p>
                                    </div>
                                    <div class="rounded-2xl border border-slate-100 bg-slate-50/70 p-4">
                                        <div class="flex items-center gap-2 font-bold text-indigo-700 text-xs uppercase tracking-wider mb-1">
                                            <span class="flex h-5 w-5 items-center justify-center rounded-full bg-indigo-100 text-indigo-800 text-[10px]">4</span>
                                            Publikasi & Bagikan Tautan
                                        </div>
                                        <p class="text-xs text-slate-600">
                                            Klik tombol <strong>Publikasikan</strong> lalu salin tautan publik kuis untuk dibagikan ke siswa atau kelas.
                                        </p>
                                    </div>
                                </div>

                                <div class="rounded-2xl border border-amber-200 bg-amber-50/80 p-4 text-xs text-amber-800">
                                    <strong>💡 Tips Kolaborasi Guru:</strong> Anda dapat mengundang rekan guru mata pelajaran serumpun untuk bersama-sama mengedit form soal melalui menu opsi titik tiga &gt; <em>Kolaborasi Form</em>.
                                </div>
                            </div>
                        </section>

                        <!-- 2. EDITOR SOAL & TIPE PERTANYAAN -->
                        <section
                            v-show="activeSection === 'question-editor' || searchQuery.trim()"
                            id="question-editor"
                            class="rounded-3xl border border-slate-200/80 bg-white p-6 shadow-sm sm:p-8"
                        >
                            <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                                <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600">
                                    <ClipboardList class="h-5 w-5" />
                                </div>
                                <div>
                                    <h2 class="text-xl font-bold text-slate-900">2. Editor Soal & Tipe Pertanyaan</h2>
                                    <p class="text-xs text-slate-500">Mengenal berbagai jenis butir soal yang didukung Alsenform</p>
                                </div>
                            </div>

                            <div class="mt-6 space-y-4 text-sm text-slate-700 leading-relaxed">
                                <p>Alsenform menyediakan fleksibilitas penuh untuk berbagai format penilaian ujian:</p>

                                <div class="space-y-3">
                                    <div class="rounded-xl border border-slate-100 p-3.5">
                                        <h4 class="font-bold text-slate-900">🔘 Pilihan Ganda (Multiple Choice)</h4>
                                        <p class="text-xs text-slate-600 mt-1">
                                            Siswa memilih satu jawaban paling benar dari beberapa opsi (A, B, C, D, E). Nilai dikoreksi otomatis sesuai kunci jawaban.
                                        </p>
                                    </div>
                                    <div class="rounded-xl border border-slate-100 p-3.5">
                                        <h4 class="font-bold text-slate-900">☑️ Kotak Centang (Checkboxes)</h4>
                                        <p class="text-xs text-slate-600 mt-1">
                                            Cocok untuk soal tipe multi-jawaban di mana siswa dapat memilih lebih dari satu pernyataan yang benar.
                                        </p>
                                    </div>
                                    <div class="rounded-xl border border-slate-100 p-3.5">
                                        <h4 class="font-bold text-slate-900">📝 Jawaban Singkat & Paragraf (Esai)</h4>
                                        <p class="text-xs text-slate-600 mt-1">
                                            Memberikan kolom input teks untuk siswa menuliskan jawaban ringkas atau uraian analitis yang lebih panjang.
                                        </p>
                                    </div>
                                    <div class="rounded-xl border border-slate-100 p-3.5">
                                        <h4 class="font-bold text-slate-900">▦ Kisi Pilihan Ganda & Kotak Centang (Grid)</h4>
                                        <p class="text-xs text-slate-600 mt-1">
                                            Format matriks baris dan kolom yang sangat berguna untuk soal mencocokkan pasangan kata atau survei skala penilaian.
                                        </p>
                                    </div>
                                </div>

                                <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                    <h4 class="font-bold text-slate-800 text-xs uppercase tracking-wider mb-2">Penetapan Skor & Poin</h4>
                                    <p class="text-xs text-slate-600 leading-relaxed">
                                        Setiap butir soal memiliki input bobot poin di pojok bawah kartu soal. Total akumulasi skor dari semua butir soal akan otomatis dikalkulasikan menjadi skala nilai 0-100 pada lembar hasil siswa.
                                    </p>
                                </div>
                            </div>
                        </section>

                        <!-- 3. BAHASA ARAB & MUSHAF MADINAH -->
                        <section
                            v-show="activeSection === 'arabic-quran' || searchQuery.trim()"
                            id="arabic-quran"
                            class="rounded-3xl border border-slate-200/80 bg-white p-6 shadow-sm sm:p-8"
                        >
                            <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                                <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600">
                                    <BookOpen class="h-5 w-5" />
                                </div>
                                <div>
                                    <h2 class="text-xl font-bold text-slate-900">3. Panduan Mengetik Bahasa Arab & Al-Qur'an</h2>
                                    <p class="text-xs text-slate-500">Mendukung standar font Mushaf Madinah dengan tanda harakat & waqf sempurna</p>
                                </div>
                            </div>

                            <div class="mt-6 space-y-5 text-sm text-slate-700 leading-relaxed">
                                <p>
                                    Alsenform memiliki integrasi font khusus untuk pembelajaran Pendidikan Agama Islam (PAI), Tahfidz, dan Bahasa Arab dengan font bawaan: <strong>Amiri Quran</strong> (khas Mushaf Standar Madinah) dan <strong>Scheherazade New</strong>.
                                </p>

                                <div class="rounded-2xl border border-emerald-100 bg-emerald-50/60 p-4">
                                    <h4 class="font-bold text-emerald-900 text-sm">Contoh Tampilan Ayat Al-Qur'an:</h4>
                                    <p class="mt-2 text-xl font-arabic text-right leading-loose text-emerald-950" dir="rtl">
                                        بِسْمِ ٱللَّهِ ٱلرَّحْمَـٰنِ ٱلرَّحِيمِ ﴿١﴾ ٱلْحَمْدُ لِلَّهِ رَبِّ ٱلْعَـٰلَمِينَ ﴿٢﴾
                                    </p>
                                </div>

                                <h3 class="font-bold text-slate-900 text-base">Tabel Shortcut Keyboard Windows untuk Harakat Arab:</h3>
                                <div class="overflow-x-auto rounded-2xl border border-slate-200">
                                    <table class="w-full text-left text-xs">
                                        <thead class="bg-slate-50 text-slate-700 font-bold border-b border-slate-200">
                                            <tr>
                                                <th class="p-3">Nama Tanda Baca</th>
                                                <th class="p-3 text-center">Bentuk</th>
                                                <th class="p-3">Kombinasi Tombol (Keyboard Arab)</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100">
                                            <tr>
                                                <td class="p-3 font-semibold">Fathah</td>
                                                <td class="p-3 text-center font-arabic text-base">ـَ</td>
                                                <td class="p-3 font-mono font-bold text-indigo-600">Shift + Q</td>
                                            </tr>
                                            <tr>
                                                <td class="p-3 font-semibold">Kasrah</td>
                                                <td class="p-3 text-center font-arabic text-base">ـِ</td>
                                                <td class="p-3 font-mono font-bold text-indigo-600">Shift + A</td>
                                            </tr>
                                            <tr>
                                                <td class="p-3 font-semibold">Dhommah</td>
                                                <td class="p-3 text-center font-arabic text-base">ـُ</td>
                                                <td class="p-3 font-mono font-bold text-indigo-600">Shift + E</td>
                                            </tr>
                                            <tr>
                                                <td class="p-3 font-semibold">Sukun</td>
                                                <td class="p-3 text-center font-arabic text-base">ـْ</td>
                                                <td class="p-3 font-mono font-bold text-indigo-600">Shift + X</td>
                                            </tr>
                                            <tr>
                                                <td class="p-3 font-semibold">Tasydid / Syaddah</td>
                                                <td class="p-3 text-center font-arabic text-base">ـّ</td>
                                                <td class="p-3 font-mono font-bold text-indigo-600">Shift + ~ (tombol di atas Tab)</td>
                                            </tr>
                                            <tr>
                                                <td class="p-3 font-semibold">Fathatain (Tanwin Fathah)</td>
                                                <td class="p-3 text-center font-arabic text-base">ـً</td>
                                                <td class="p-3 font-mono font-bold text-indigo-600">Shift + W</td>
                                            </tr>
                                            <tr>
                                                <td class="p-3 font-semibold">Kasratain (Tanwin Kasrah)</td>
                                                <td class="p-3 text-center font-arabic text-base">ـٍ</td>
                                                <td class="p-3 font-mono font-bold text-indigo-600">Shift + S</td>
                                            </tr>
                                            <tr>
                                                <td class="p-3 font-semibold">Dhommatain (Tanwin Dhommah)</td>
                                                <td class="p-3 text-center font-arabic text-base">ـٌ</td>
                                                <td class="p-3 font-mono font-bold text-indigo-600">Shift + R</td>
                                            </tr>
                                            <tr>
                                                <td class="p-3 font-semibold">Kurung Ayat Al-Qur'an</td>
                                                <td class="p-3 text-center font-arabic text-base">﴿ ﴾</td>
                                                <td class="p-3 font-mono text-slate-600">Bisa di-copy atau ketik tanda kurung biasa</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </section>

                        <!-- 4. RUMUS MATEMATIKA (KATEX) -->
                        <section
                            v-show="activeSection === 'math-katex' || searchQuery.trim()"
                            id="math-katex"
                            class="rounded-3xl border border-slate-200/80 bg-white p-6 shadow-sm sm:p-8"
                        >
                            <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                                <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-blue-50 text-blue-600">
                                    <Layers class="h-5 w-5" />
                                </div>
                                <div>
                                    <h2 class="text-xl font-bold text-slate-900">4. Panduan Menulis Rumus Matematika (KaTeX)</h2>
                                    <p class="text-xs text-slate-500">Tulis formula matematika, fisika, dan kimia dengan format LaTeX standar</p>
                                </div>
                            </div>

                            <div class="mt-6 space-y-5 text-sm text-slate-700 leading-relaxed">
                                <p>
                                    Cukup apit formula matematika menggunakan tanda dollar tunggal <code>$rumus$</code> untuk format sebaris (*inline*), atau klik tombol <strong>f(x) Rumus Matematika</strong> pada toolbar editor.
                                </p>

                                <div class="overflow-x-auto rounded-2xl border border-slate-200">
                                    <table class="w-full text-left text-xs">
                                        <thead class="bg-slate-50 text-slate-700 font-bold border-b border-slate-200">
                                            <tr>
                                                <th class="p-3">Kebutuhan Rumus</th>
                                                <th class="p-3">Sintaks yang Diketik</th>
                                                <th class="p-3">Hasil Tampilan</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100">
                                            <tr>
                                                <td class="p-3 font-semibold">Pecahan (*Fraction*)</td>
                                                <td class="p-3 font-mono text-indigo-600">$\frac{a}{b}$</td>
                                                <td class="p-3 font-serif">a / b bertingkat</td>
                                            </tr>
                                            <tr>
                                                <td class="p-3 font-semibold">Akar Kuadrat (*Square Root*)</td>
                                                <td class="p-3 font-mono text-indigo-600">$\sqrt{x^2 + y^2}$</td>
                                                <td class="p-3 font-serif">√(x² + y²)</td>
                                            </tr>
                                            <tr>
                                                <td class="p-3 font-semibold">Pangkat & Indeks Bawah</td>
                                                <td class="p-3 font-mono text-indigo-600">$x^2 + y_1$</td>
                                                <td class="p-3 font-serif">x² + y₁</td>
                                            </tr>
                                            <tr>
                                                <td class="p-3 font-semibold">Simbol Kali & Bagi</td>
                                                <td class="p-3 font-mono text-indigo-600">$5 \times 4 \div 2$</td>
                                                <td class="p-3 font-serif">5 × 4 ÷ 2</td>
                                            </tr>
                                            <tr>
                                                <td class="p-3 font-semibold">Integral & Batas</td>
                                                <td class="p-3 font-mono text-indigo-600">$\int_{0}^{\infty} f(x) dx$</td>
                                                <td class="p-3 font-serif">∫ f(x) dx</td>
                                            </tr>
                                            <tr>
                                                <td class="p-3 font-semibold">Persamaan Kimia / Reaksi</td>
                                                <td class="p-3 font-mono text-indigo-600">$2H_2 + O_2 \rightarrow 2H_2O$</td>
                                                <td class="p-3 font-serif">2H₂ + O₂ → 2H₂O</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </section>

                        <!-- 5. IMPORT SOAL EXAMVIEW (ZIP) -->
                        <section
                            v-show="activeSection === 'examview-import' || searchQuery.trim()"
                            id="examview-import"
                            class="rounded-3xl border border-slate-200/80 bg-white p-6 shadow-sm sm:p-8"
                        >
                            <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                                <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-cyan-50 text-cyan-600">
                                    <UploadCloud class="h-5 w-5" />
                                </div>
                                <div>
                                    <h2 class="text-xl font-bold text-slate-900">5. Import Bank Soal dari ExamView (ZIP)</h2>
                                    <p class="text-xs text-slate-500">Hemat waktu dengan mengimpor ratusan bank soal ExamView langsung ke Alsenform</p>
                                </div>
                            </div>

                            <div class="mt-6 space-y-4 text-sm text-slate-700 leading-relaxed">
                                <h3 class="font-bold text-slate-900">Cara Ekspor dari Aplikasi ExamView:</h3>
                                <ol class="list-decimal pl-5 space-y-2 text-xs text-slate-600">
                                    <li>Buka berkas bank soal Anda di program desktop <strong>ExamView Test Generator</strong>.</li>
                                    <li>Klik menu <strong>File &gt; Export &gt; Blackboard 6.0-7.0 (atau Blackboard 7.1-9.0)</strong>.</li>
                                    <li>Beri nama berkas dan simpan sebagai file arsip berformat <code>.zip</code>.</li>
                                    <li>Buka editor Alsenform, klik tombol <strong>Import Soal &gt; Upload ExamView ZIP</strong>.</li>
                                    <li>Alsenform akan mengekstrak butir soal, kunci jawaban, dan seluruh gambar diagram soal secara otomatis ke dalam kuis!</li>
                                </ol>
                            </div>
                        </section>

                        <!-- 6. PENGAWASAN CBT & ANTI-CURANG -->
                        <section
                            v-show="activeSection === 'proctoring-anti-cheat' || searchQuery.trim()"
                            id="proctoring-anti-cheat"
                            class="rounded-3xl border border-slate-200/80 bg-white p-6 shadow-sm sm:p-8"
                        >
                            <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                                <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-red-50 text-red-600">
                                    <Shield class="h-5 w-5" />
                                </div>
                                <div>
                                    <h2 class="text-xl font-bold text-slate-900">6. Pengawasan CBT & Fitur Anti-Curang</h2>
                                    <p class="text-xs text-slate-500">Mekanisme menjaga integritas dan kejujuran ujian online sekolah</p>
                                </div>
                            </div>

                            <div class="mt-6 space-y-4 text-sm text-slate-700 leading-relaxed">
                                <div class="space-y-3">
                                    <div class="rounded-xl border border-slate-100 p-3.5">
                                        <h4 class="font-bold text-slate-900">🔒 Deteksi Pindah Tab (Lock on Blur)</h4>
                                        <p class="text-xs text-slate-600 mt-1">
                                            Bila siswa berganti tab browser, membuka jendela lain, atau me-minimize layar, sistem akan mencatat log peringatan. Jika melebihi batas toleransi, lembar ujian siswa akan otomatis terkunci (*suspended*).
                                        </p>
                                    </div>
                                    <div class="rounded-xl border border-slate-100 p-3.5">
                                        <h4 class="font-bold text-slate-900">🔑 PIN Cepat Pengawas (Proctor PIN)</h4>
                                        <p class="text-xs text-slate-600 mt-1">
                                            Pengawas ruangan ujian dapat membuka kunci siswa langsung di tempat dengan memasukkan PIN 6 digit tanpa perlu mengetik kata sandi akun guru di hadapan siswa.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <!-- 7. MANAJEMEN SISWA & KELAS -->
                        <section
                            v-show="activeSection === 'students-cohort' || searchQuery.trim()"
                            id="students-cohort"
                            class="rounded-3xl border border-slate-200/80 bg-white p-6 shadow-sm sm:p-8"
                        >
                            <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                                <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-amber-50 text-amber-600">
                                    <Users class="h-5 w-5" />
                                </div>
                                <div>
                                    <h2 class="text-xl font-bold text-slate-900">7. Manajemen Siswa & Kelas (Cohort)</h2>
                                    <p class="text-xs text-slate-500">Pengelolaan data peserta ujian, kelas, dan kredensial akun siswa</p>
                                </div>
                            </div>

                            <div class="mt-6 space-y-4 text-sm text-slate-700 leading-relaxed">
                                <p>
                                    Admin dan guru dapat mengunggah daftar peserta ujian secara massal melalui berkas template Excel/CSV.
                                </p>
                                <ul class="list-disc pl-5 space-y-1.5 text-xs text-slate-600">
                                    <li><strong>Password Default:</strong> Password akun siswa secara otomatis disetel menggunakan 6 digit terakhir dari NIS/NISN siswa.</li>
                                    <li><strong>Proteksi Akun Siswa:</strong> Siswa tidak diizinkan menghapus akun secara mandiri untuk menjaga integritas data presensi dan riwayat nilai ujian.</li>
                                    <li><strong>Cohort:</strong> Pengelompokan siswa ke dalam rombel/angkatan kelas agar pembagian kuis dapat ditargetkan dengan presisi.</li>
                                </ul>
                            </div>
                        </section>

                        <!-- 8. REKAP NILAI & ANALISIS -->
                        <section
                            v-show="activeSection === 'results-gradebook' || searchQuery.trim()"
                            id="results-gradebook"
                            class="rounded-3xl border border-slate-200/80 bg-white p-6 shadow-sm sm:p-8"
                        >
                            <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                                <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-purple-50 text-purple-600">
                                    <FileSpreadsheet class="h-5 w-5" />
                                </div>
                                <div>
                                    <h2 class="text-xl font-bold text-slate-900">8. Rekap Nilai & Analisis Ujian</h2>
                                    <p class="text-xs text-slate-500">Melihat hasil pengerjaan siswa dan mengunduh laporan rekapitulasi</p>
                                </div>
                            </div>

                            <div class="mt-6 space-y-4 text-sm text-slate-700 leading-relaxed">
                                <p>
                                    Pada tab <strong>Respon</strong> di editor kuis, guru dapat memantau lembar jawaban yang masuk secara langsung (*real-time*):
                                </p>
                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 text-xs">
                                    <div class="rounded-xl border border-slate-100 bg-slate-50/50 p-3">
                                        <p class="font-bold text-slate-800">📊 Statistik Kelulusan KKM</p>
                                        <p class="text-slate-500 mt-0.5">Melihat persentase siswa tuntas vs belum tuntas berdasarkan standar KKM kuis.</p>
                                    </div>
                                    <div class="rounded-xl border border-slate-100 bg-slate-50/50 p-3">
                                        <p class="font-bold text-slate-800">📥 Download Rekap Excel / CSV</p>
                                        <p class="text-slate-500 mt-0.5">Ekspor satu klik untuk laporan buku nilai resmi guru dan wali kelas.</p>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <!-- 9. PENGATURAN PROFIL & AKUN -->
                        <section
                            v-show="activeSection === 'profile-settings' || searchQuery.trim()"
                            id="profile-settings"
                            class="rounded-3xl border border-slate-200/80 bg-white p-6 shadow-sm sm:p-8"
                        >
                            <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                                <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-slate-100 text-slate-700">
                                    <KeyRound class="h-5 w-5" />
                                </div>
                                <div>
                                    <h2 class="text-xl font-bold text-slate-900">9. Pengaturan Profil & Keamanan Akun</h2>
                                    <p class="text-xs text-slate-500">Konfigurasi preferensi pribadi, proteksi sesi, dan backup bank soal</p>
                                </div>
                            </div>

                            <div class="mt-6 space-y-4 text-sm text-slate-700 leading-relaxed">
                                <p>
                                    Melalui menu <Link :href="route('profile.edit')" class="font-bold text-indigo-600 underline">Pengaturan Profil</Link>, Anda dapat:
                                </p>
                                <ul class="list-disc pl-5 space-y-1.5 text-xs text-slate-600">
                                    <li><strong>Upload Foto Profil:</strong> Mengganti inisial dengan foto Anda (format JPG/PNG/WebP).</li>
                                    <li><strong>Data Akademik Guru:</strong> Melengkapi NIP, Mata Pelajaran, dan Asal Sekolah.</li>
                                    <li><strong>Preferensi Kuis Default:</strong> Mengatur KKM default, durasi default, dan font Arab pilihan agar otomatis terisi saat membuat form baru.</li>
                                    <li><strong>Manajemen Sesi Login:</strong> Memantau perangkat yang sedang login dan opsi keluar dari seluruh komputer lain.</li>
                                    <li><strong>Ekspor Bank Soal Mandiri:</strong> Mengunduh cadangan seluruh soal Anda dalam format berkas <code>.json</code>.</li>
                                </ul>
                            </div>
                        </section>
                    </main>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
