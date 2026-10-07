<script setup lang="ts">
import DocxImportGuide, { type DocxGuideSample } from '@/components/help/DocxImportGuide.vue';
import AlsenformLayout from '@/layouts/AlsenformLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    BookOpen,
    ClipboardList,
    Clock,
    FileSpreadsheet,
    FileText,
    KeyRound,
    Layers,
    LayoutGrid,
    Lock,
    Play,
    Search,
    Shield,
    Sparkles,
    UploadCloud,
    UserRound,
    Users,
    Wifi,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface HelpTopic {
    id: string;
    title: string;
    icon: any;
    badge: string;
    badgeColor: string;
    summary: string;
}

/** A guide written as a short list of steps or facts (used for the student guides). */
interface HelpArticle extends HelpTopic {
    intro: string;
    points: { title: string; text: string }[];
    tip?: string;
}

const page = usePage<any>();
const user = computed(() => page.props.auth?.user);
const isStudent = computed(() => {
    const u = user.value;
    if (!u) return false;
    return u.role === 'siswa' || u.role === 'murid' || (!u.is_admin && u.role !== 'guru' && !!u.nis);
});

const studentArticles: HelpArticle[] = [
    {
        id: 'student-login',
        title: '1. Masuk & Password Pertama',
        icon: KeyRound,
        badge: 'Akun',
        badgeColor: 'bg-indigo-50 text-indigo-700 border-indigo-200',
        summary: 'Masuk dengan NIS dan membuat password pribadi saat pertama kali login.',
        intro: 'Akun Anda dibuat oleh sekolah. Gunakan NIS/NISN sebagai nama pengguna.',
        points: [
            { title: 'Masuk', text: 'Ketik NIS/NISN dan password sementara yang diberikan sekolah, lalu tekan Masuk.' },
            {
                title: 'Buat password baru',
                text: 'Saat pertama kali masuk, Anda wajib mengganti password sementara dengan password pribadi (minimal 8 karakter). Setelah disimpan, Anda langsung masuk ke halaman Home.',
            },
            { title: 'Lupa password', text: 'Minta guru atau admin sekolah untuk mereset password. Setelah direset, Anda akan diminta membuat password baru lagi.' },
        ],
        tip: 'Jangan beri tahu password Anda kepada teman, dan selalu Logout setelah memakai komputer lab bersama.',
    },
    {
        id: 'student-start-exam',
        title: '2. Memulai Ujian',
        icon: Play,
        badge: 'Ujian',
        badgeColor: 'bg-emerald-50 text-emerald-700 border-emerald-200',
        summary: 'Membuka kuis dari halaman Home dan membaca aturan sebelum mulai.',
        intro: 'Semua kuis untuk kelas Anda tampil di halaman Home pada bagian Daftar Soal & Kuis.',
        points: [
            { title: 'Pilih kuis', text: 'Tekan tombol Kerjakan pada kartu kuis yang berstatus Belum Dikerjakan.' },
            {
                title: 'Baca konfirmasi ujian',
                text: 'Sebelum mulai, muncul jendela berisi nama ujian, jumlah soal, durasi, dan aturan mengerjakan. Baca dengan teliti.',
            },
            {
                title: 'Tekan Kerjakan Sekarang',
                text: 'Soal baru tampil dan waktu baru mulai berjalan setelah tombol ini ditekan. Tekan Kembali bila belum siap.',
            },
        ],
        tip: 'Jika halaman tidak sengaja tertutup, buka kuis yang sama lagi: ujian berlanjut dan jawaban Anda tetap ada.',
    },
    {
        id: 'student-answering',
        title: '3. Menjawab & Panel Nomor Soal',
        icon: LayoutGrid,
        badge: 'Ujian',
        badgeColor: 'bg-emerald-50 text-emerald-700 border-emerald-200',
        summary: 'Berpindah soal dengan panel nomor, arti warna, dan memperbesar gambar.',
        intro: 'Panel Nomor Soal selalu terlihat di samping soal saat Anda menggulir halaman.',
        points: [
            { title: 'Pindah soal', text: 'Klik nomor pada panel untuk langsung menuju soal tersebut.' },
            {
                title: 'Arti warna',
                text: 'Hijau berarti sudah dijawab, abu-abu belum dijawab, titik merah menandai soal wajib, dan bingkai biru menunjukkan soal yang sedang Anda baca.',
            },
            {
                title: 'Atur panel',
                text: 'Tombol panah kiri-kanan memindahkan panel ke sisi lain, dan tombol ciutkan menyembunyikannya. Di HP, panel dibuka lewat tombol Nomor Soal di pojok bawah.',
            },
            { title: 'Gambar soal', text: 'Klik gambar untuk melihatnya di layar penuh dan memperbesarnya.' },
        ],
    },
    {
        id: 'student-connection',
        title: '4. Simpan Otomatis & Koneksi Terputus',
        icon: Wifi,
        badge: 'Jaringan',
        badgeColor: 'bg-cyan-50 text-cyan-700 border-cyan-200',
        summary: 'Jawaban tersimpan otomatis walau Wi-Fi sekolah lemah atau terputus.',
        intro: 'Setiap jawaban otomatis disimpan di perangkat Anda dan di server ujian.',
        points: [
            {
                title: 'Wi-Fi terputus',
                text: 'Muncul pita kuning di atas halaman. Tetap lanjutkan mengerjakan: jawaban aman di perangkat dan dikirim otomatis begitu koneksi pulih.',
            },
            {
                title: 'Perangkat mati atau rusak',
                text: 'Masuk lagi dengan akun Anda di komputer lain dan buka kuis yang sama. Jawaban yang sudah tersimpan di server akan dipulihkan.',
            },
            { title: 'Jangan menutup paksa', text: 'Jangan menghapus data browser selama ujian berlangsung.' },
        ],
    },
    {
        id: 'student-submit',
        title: '5. Waktu & Mengirim Jawaban',
        icon: Clock,
        badge: 'Ujian',
        badgeColor: 'bg-emerald-50 text-emerald-700 border-emerald-200',
        summary: 'Sisa waktu, mengirim jawaban, dan apa yang terjadi saat waktu habis.',
        intro: 'Sisa waktu tampil di pojok kanan atas dan menghitung mundur sejak Anda menekan Kerjakan Sekarang.',
        points: [
            {
                title: 'Kirim jawaban',
                text: 'Tekan Kirim Jawaban di bawah soal terakhir, periksa ringkasan, lalu tekan Ya, Kirim Jawaban Sekarang.',
            },
            {
                title: 'Soal wajib belum dijawab',
                text: 'Jawaban belum bisa dikirim. Tekan Menuju Soal Belum Terjawab untuk langsung ke soal tersebut.',
            },
            { title: 'Waktu habis', text: 'Jawaban yang sudah diisi terkirim otomatis, Anda tidak perlu menekan apa pun.' },
        ],
        tip: 'Pada sebagian kuis jawaban hanya bisa dikirim satu kali, jadi periksa kembali sebelum mengirim.',
    },
    {
        id: 'student-locked',
        title: '6. Ujian Terkunci',
        icon: Lock,
        badge: 'Anti-Curang',
        badgeColor: 'bg-red-50 text-red-700 border-red-200',
        summary: 'Mengapa ujian bisa terkunci dan cara membukanya kembali.',
        intro: 'Pada ujian dengan pengaman anti-curang, berpindah tab, membuka aplikasi lain, atau meminimalkan browser membuat ujian terkunci.',
        points: [
            { title: 'Minta buka kunci', text: 'Tekan Minta Kode Buka Kunci. Setelah guru menyetujui, halaman terbuka sendiri.' },
            { title: 'Kode dari pengawas', text: 'Pengawas ruangan juga dapat memberikan kode 6 angka untuk Anda ketik pada kolom yang tersedia, lalu tekan Verifikasi.' },
            { title: 'Jawaban tetap aman', text: 'Selama terkunci, jawaban yang sudah diisi tidak hilang dan waktu ujian tetap berjalan.' },
        ],
    },
    {
        id: 'student-profile',
        title: '7. Profil & Ganti Password',
        icon: UserRound,
        badge: 'Akun',
        badgeColor: 'bg-indigo-50 text-indigo-700 border-indigo-200',
        summary: 'Mengganti foto profil dan password lewat menu akun.',
        intro: 'Buka menu akun (foto atau ikon profil di pojok kanan atas), lalu pilih Profile.',
        points: [
            { title: 'Foto profil', text: 'Tekan Pilih Foto Baru (JPG, PNG, atau WebP, maksimal 2 MB), lalu Simpan Profil.' },
            { title: 'Ganti password', text: 'Pilih menu Password pada halaman Pengaturan Akun.' },
            { title: 'Data sekolah', text: 'NIS dan kelas dikelola sekolah. Hubungi wali kelas atau admin bila ada data yang salah.' },
        ],
    },
];

const teacherTopics: HelpTopic[] = [
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
        title: "3. Bahasa Arab & Al-Qur'an (Mushaf Madinah)",
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
        summary: 'Panduan ekspor Blackboard 6.0-9.0 dari ExamView dan impor ke Alsenform lengkap dengan gambar soal.',
    },
    {
        id: 'docx-import',
        title: '6. Import Soal dari Word (.docx)',
        icon: FileText,
        badge: 'Import',
        badgeColor: 'bg-sky-50 text-sky-700 border-sky-200',
        summary:
            'Aturan penulisan soal di Word, baris Tipe/Poin/Wajib/Skala, dan contoh setiap tipe soal: pilihan ganda, kotak centang, drop-down, benar salah, isian, uraian, menjodohkan, kisi, skala, rating, tanggal, waktu.',
    },
    {
        id: 'proctoring-anti-cheat',
        title: '7. Pengawasan CBT & Fitur Anti-Curang',
        icon: Shield,
        badge: 'Keamanan',
        badgeColor: 'bg-red-50 text-red-700 border-red-200',
        summary: 'Deteksi Lock on Blur (pindah tab), toleransi pelanggaran, permintaan buka blokir, dan PIN Pengawas.',
    },
    {
        id: 'students-cohort',
        title: '8. Manajemen Siswa & Kelas (Cohort)',
        icon: Users,
        badge: 'Akademik',
        badgeColor: 'bg-amber-50 text-amber-700 border-amber-200',
        summary: 'Import data siswa dari Excel/CSV, manajemen NIS & kelas, proteksi akun siswa, dan grup kelas.',
    },
    {
        id: 'results-gradebook',
        title: '9. Rekap Nilai & Analisis Ujian',
        icon: FileSpreadsheet,
        badge: 'Laporan',
        badgeColor: 'bg-purple-50 text-purple-700 border-purple-200',
        summary: 'Koreksi otomatis, analisis KKM, statistik rata-rata, dan ekspor nilai ke berkas spreadsheet Excel/CSV.',
    },
    {
        id: 'profile-settings',
        title: '10. Pengaturan Profil & Keamanan Akun',
        icon: KeyRound,
        badge: 'Akun',
        badgeColor: 'bg-slate-100 text-slate-700 border-slate-200',
        summary: 'Upload foto profil avatar, manajemen sesi perangkat aktif, preferensi default kuis, dan backup arsip JSON.',
    },
];

const topics = computed<HelpTopic[]>(() => (isStudent.value ? studentArticles : teacherTopics));

const docxImportGuide = computed<DocxGuideSample[]>(() => page.props.docxImportGuide ?? []);

const searchQuery = ref('');
// "?topic=docx-import" opens that topic directly (used by the import dialog in the editor).
const requestedTopic = typeof window === 'undefined' ? null : new URLSearchParams(window.location.search).get('topic');
const activeSection = ref<string>(
    topics.value.some((topic) => topic.id === requestedTopic) ? (requestedTopic as string) : isStudent.value ? 'student-login' : 'getting-started',
);

const filteredTopics = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) {
        return topics.value;
    }
    return topics.value.filter((topic) => {
        const article = topic as Partial<HelpArticle>;
        const text = [topic.title, topic.summary, article.intro ?? '', ...(article.points ?? []).map((point) => `${point.title} ${point.text}`)];
        return text.join(' ').toLowerCase().includes(q);
    });
});

/** While searching, every matching topic is shown; otherwise only the selected one. */
const isTopicVisible = (id: string): boolean =>
    searchQuery.value.trim() ? filteredTopics.value.some((topic) => topic.id === id) : activeSection.value === id;

const selectTopic = (id: string) => {
    activeSection.value = id;
    searchQuery.value = '';
    if (window.innerWidth < 1024) {
        document.getElementById('help-content')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
};
</script>

<template>
    <Head title="Pusat Bantuan" />

    <AlsenformLayout>
        <main class="mx-auto max-w-6xl px-4 py-6 sm:px-6 sm:py-8">
            <Link
                :href="route('dashboard')"
                class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 transition hover:text-indigo-700"
            >
                <ArrowLeft class="h-4 w-4" />
                Kembali ke Beranda
            </Link>

            <div class="mt-3 flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                <div>
                    <h1 class="text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">Pusat Bantuan</h1>
                    <p class="mt-1 text-sm text-slate-500">
                        {{
                            isStudent
                                ? 'Panduan singkat masuk, mengerjakan ujian, dan mengirim jawaban di Alsenform.'
                                : 'Panduan membuat ujian, menulis soal Arab & rumus, pengawasan CBT, hingga rekap nilai siswa.'
                        }}
                    </p>
                </div>

                <label class="relative block w-full md:w-80">
                    <Search class="absolute left-4 top-3.5 h-4 w-4 text-slate-400" />
                    <input
                        v-model="searchQuery"
                        type="search"
                        placeholder="Cari panduan..."
                        class="w-full rounded-2xl border border-slate-300 bg-white py-3 pl-11 pr-4 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                    />
                </label>
            </div>

            <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-[300px_minmax(0,1fr)]">
                <aside class="min-w-0">
                    <!-- Phones: a compact topic picker instead of the long list -->
                    <label class="block lg:hidden">
                        <span class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-500">Topik panduan</span>
                        <select
                            :value="activeSection"
                            class="w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm font-bold text-slate-800 outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-100"
                            @change="selectTopic(($event.target as HTMLSelectElement).value)"
                        >
                            <option v-for="topic in topics" :key="topic.id" :value="topic.id">{{ topic.title }}</option>
                        </select>
                    </label>

                    <nav class="hidden space-y-1.5 lg:sticky lg:top-20 lg:block" aria-label="Daftar topik bantuan">
                        <button
                            v-for="topic in filteredTopics"
                            :key="topic.id"
                            type="button"
                            :class="[
                                'flex w-full items-center gap-3 rounded-2xl border px-3 py-2.5 text-left transition',
                                activeSection === topic.id && !searchQuery.trim()
                                    ? 'border-indigo-200 bg-white text-indigo-700 shadow-sm'
                                    : 'border-transparent text-slate-600 hover:bg-white/70 hover:text-slate-900',
                            ]"
                            :aria-current="activeSection === topic.id ? 'true' : undefined"
                            @click="selectTopic(topic.id)"
                        >
                            <span
                                :class="[
                                    'flex h-9 w-9 shrink-0 items-center justify-center rounded-xl',
                                    activeSection === topic.id && !searchQuery.trim() ? 'bg-indigo-600 text-white' : 'bg-white text-slate-500 shadow-sm',
                                ]"
                            >
                                <component :is="topic.icon" class="h-4 w-4" />
                            </span>
                            <span class="min-w-0 flex-1 text-sm font-bold leading-snug">{{ topic.title }}</span>
                        </button>
                        <p v-if="filteredTopics.length === 0" class="rounded-2xl border border-dashed border-slate-300 p-4 text-sm text-slate-500">
                            Tidak ada panduan yang cocok dengan "{{ searchQuery }}".
                        </p>
                    </nav>
                </aside>

                <section id="help-content" class="min-w-0 scroll-mt-20 space-y-6">
                    <p
                        v-if="filteredTopics.length === 0"
                        class="rounded-2xl border border-dashed border-slate-300 p-4 text-sm text-slate-500 lg:hidden"
                    >
                        Tidak ada panduan yang cocok dengan "{{ searchQuery }}".
                    </p>
                    <!-- Panduan siswa -->
                    <template v-if="isStudent">
                        <article
                            v-for="article in studentArticles"
                            v-show="isTopicVisible(article.id)"
                            :id="article.id"
                            :key="article.id"
                            class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8"
                        >
                            <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600">
                                    <component :is="article.icon" class="h-5 w-5" />
                                </div>
                                <div class="min-w-0">
                                    <h2 class="text-xl font-bold text-slate-900">{{ article.title }}</h2>
                                    <p class="text-sm text-slate-500">{{ article.summary }}</p>
                                </div>
                            </div>

                            <p class="mt-5 text-sm leading-relaxed text-slate-700">{{ article.intro }}</p>

                            <ol class="mt-4 space-y-3">
                                <li
                                    v-for="(point, index) in article.points"
                                    :key="point.title"
                                    class="flex items-start gap-3 rounded-2xl border border-slate-100 bg-slate-50 p-4"
                                >
                                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-black text-indigo-700">
                                        {{ index + 1 }}
                                    </span>
                                    <span>
                                        <span class="block text-sm font-bold text-slate-900">{{ point.title }}</span>
                                        <span class="mt-0.5 block text-sm leading-relaxed text-slate-600">{{ point.text }}</span>
                                    </span>
                                </li>
                            </ol>

                            <div v-if="article.tip" class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                                <strong>Tips:</strong> {{ article.tip }}
                            </div>
                        </article>
                    </template>

                    <!-- Panduan guru & admin -->
                    <template v-else>
                        <!-- 1. MEMULAI & ALUR KERJA KUIS -->
                        <section
                            v-show="isTopicVisible('getting-started')"
                            id="getting-started"
                            class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8"
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
                            v-show="isTopicVisible('question-editor')"
                            id="question-editor"
                            class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8"
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
                            v-show="isTopicVisible('arabic-quran')"
                            id="arabic-quran"
                            class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8"
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
                            v-show="isTopicVisible('math-katex')"
                            id="math-katex"
                            class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8"
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
                            v-show="isTopicVisible('examview-import')"
                            id="examview-import"
                            class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8"
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

                        <!-- 6. IMPORT SOAL DARI WORD (DOCX) -->
                        <section
                            v-show="isTopicVisible('docx-import')"
                            id="docx-import"
                            class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8"
                        >
                            <DocxImportGuide title="6. Import Soal dari Word (.docx)" :samples="docxImportGuide" />
                        </section>

                        <!-- 7. PENGAWASAN CBT & ANTI-CURANG -->
                        <section
                            v-show="isTopicVisible('proctoring-anti-cheat')"
                            id="proctoring-anti-cheat"
                            class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8"
                        >
                            <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                                <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-red-50 text-red-600">
                                    <Shield class="h-5 w-5" />
                                </div>
                                <div>
                                    <h2 class="text-xl font-bold text-slate-900">7. Pengawasan CBT & Fitur Anti-Curang</h2>
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

                        <!-- 8. MANAJEMEN SISWA & KELAS -->
                        <section
                            v-show="isTopicVisible('students-cohort')"
                            id="students-cohort"
                            class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8"
                        >
                            <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                                <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-amber-50 text-amber-600">
                                    <Users class="h-5 w-5" />
                                </div>
                                <div>
                                    <h2 class="text-xl font-bold text-slate-900">8. Manajemen Siswa & Kelas (Cohort)</h2>
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

                        <!-- 9. REKAP NILAI & ANALISIS -->
                        <section
                            v-show="isTopicVisible('results-gradebook')"
                            id="results-gradebook"
                            class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8"
                        >
                            <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                                <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-purple-50 text-purple-600">
                                    <FileSpreadsheet class="h-5 w-5" />
                                </div>
                                <div>
                                    <h2 class="text-xl font-bold text-slate-900">9. Rekap Nilai & Analisis Ujian</h2>
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

                        <!-- 10. PENGATURAN PROFIL & AKUN -->
                        <section
                            v-show="isTopicVisible('profile-settings')"
                            id="profile-settings"
                            class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8"
                        >
                            <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                                <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-slate-100 text-slate-700">
                                    <KeyRound class="h-5 w-5" />
                                </div>
                                <div>
                                    <h2 class="text-xl font-bold text-slate-900">10. Pengaturan Profil & Keamanan Akun</h2>
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
                    </template>
                </section>
            </div>
        </main>
    </AlsenformLayout>
</template>
