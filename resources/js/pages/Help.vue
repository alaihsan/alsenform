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
    Eye,
    FileText,
    Headphones,
    KeyRound,
    Layers,
    LayoutGrid,
    Mic,
    Lock,
    Play,
    Puzzle,
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
        summary: 'Masuk dengan NIS dan password default dari admin.',
        intro: 'Akun Anda dibuat oleh admin. Gunakan NIS/NISN sebagai nama pengguna.',
        points: [
            { title: 'Masuk', text: 'Ketik NIS/NISN dan password default yang diberikan admin, lalu tekan Masuk. Anda langsung masuk ke Beranda.' },
            {
                title: 'Ganti password (opsional)',
                text: 'Bila ingin, buat password pribadi (minimal 8 karakter) lewat menu Password pada halaman Pengaturan Akun.',
            },
            { title: 'Lupa password', text: 'Minta guru atau admin untuk mereset password ke password default.' },
        ],
        tip: 'Jangan beri tahu password Anda kepada teman, dan selalu Logout setelah memakai komputer lab bersama.',
    },
    {
        id: 'student-start-exam',
        title: '2. Memulai Ujian',
        icon: Play,
        badge: 'Ujian',
        badgeColor: 'bg-emerald-50 text-emerald-700 border-emerald-200',
        summary: 'Membuka ujian dari Beranda dan membaca aturan sebelum mulai.',
        intro: 'Semua ujian untuk kelas Anda tampil di Beranda pada bagian Daftar Ujian.',
        points: [
            { title: 'Pilih ujian', text: 'Tekan tombol Kerjakan pada kartu ujian yang berstatus Belum Dikerjakan.' },
            {
                title: 'Baca konfirmasi ujian',
                text: 'Sebelum mulai, muncul jendela berisi nama ujian, jumlah soal, durasi, dan aturan mengerjakan. Baca dengan teliti.',
            },
            {
                title: 'Tekan Kerjakan Sekarang',
                text: 'Soal baru tampil dan waktu baru mulai berjalan setelah tombol ini ditekan. Tekan Kembali bila belum siap.',
            },
            {
                title: 'Token Ujian',
                text: 'Bila pengawas memakai token, ketik token 6 huruf yang ditampilkan pengawas di layar konfirmasi, lalu tekan Kerjakan Sekarang. Token berganti setiap 15 menit, jadi minta token terbaru bila ditolak.',
            },
            {
                title: 'Ujian belum dibuka',
                text: 'Ujian yang dijadwalkan menampilkan hitung mundur sampai waktunya. Halaman terbuka sendiri saat ujian dimulai, tidak perlu dimuat ulang.',
            },
        ],
        tip: 'Jika halaman tidak sengaja tertutup, buka ujian yang sama lagi: ujian berlanjut dan jawaban Anda tetap ada.',
    },
    {
        id: 'student-answering',
        title: '3. Menjawab & Panel Nomor Soal',
        icon: LayoutGrid,
        badge: 'Ujian',
        badgeColor: 'bg-emerald-50 text-emerald-700 border-emerald-200',
        summary: 'Berpindah soal dengan panel nomor, arti warna, tanda Ragu-ragu, dan memperbesar gambar.',
        intro: 'Panel Nomor Soal selalu terlihat di samping soal saat Anda menggulir halaman.',
        points: [
            { title: 'Pindah soal', text: 'Klik nomor pada panel untuk langsung menuju soal tersebut.' },
            {
                title: 'Arti warna',
                text: 'Hijau berarti sudah dijawab, abu-abu belum dijawab, kuning ditandai Ragu-ragu, titik merah menandai soal wajib, dan bingkai biru menunjukkan soal yang sedang Anda baca.',
            },
            {
                title: 'Ragu-ragu',
                text: 'Belum yakin dengan jawaban? Tekan Ragu-ragu pada soal itu: nomornya menjadi kuning sehingga mudah diperiksa lagi sebelum mengirim. Tandai dengan jujur, karena guru memakainya untuk melihat materi yang belum dipahami.',
            },
            {
                title: 'Atur panel',
                text: 'Tombol panah kiri-kanan memindahkan panel ke sisi lain, dan tombol ciutkan menyembunyikannya. Di HP, panel dibuka lewat tombol Nomor Soal di pojok bawah.',
            },
            { title: 'Gambar soal', text: 'Klik gambar untuk melihatnya di layar penuh dan memperbesarnya.' },
        ],
    },
    {
        id: 'student-audio',
        title: '4. Soal Audio & Jawaban Suara',
        icon: Headphones,
        badge: 'Ujian',
        badgeColor: 'bg-emerald-50 text-emerald-700 border-emerald-200',
        summary: 'Memutar audio soal, batas putar, pilihan bersuara, dan merekam jawaban suara.',
        intro: 'Sebagian ujian memakai suara, seperti tes listening dan speaking TOEFL: soal yang didengarkan, pilihan jawaban bersuara, atau soal yang dijawab dengan merekam suara.',
        points: [
            {
                title: 'Memutar audio',
                text: 'Tekan tombol putar pada soal. Audio baru dimuat saat diputar. Tekan sekali lagi untuk jeda; melanjutkan audio yang dijeda tidak dihitung sebagai putar baru.',
            },
            {
                title: 'Batas putar',
                text: 'Sebagian audio hanya bisa diputar 1 sampai 3 kali. Sisa putar tampil di samping audio, dan audio tidak bisa digeser maju atau mundur. Setelah batasnya habis, tombol putar mati, juga bila halaman dimuat ulang.',
            },
            {
                title: 'Pilihan bersuara',
                text: 'Pilihan jawaban yang punya tombol putar kecil bisa didengarkan. Menekan tombol putar tidak memilih jawaban; pilih jawaban dengan menekan teks pilihannya.',
            },
            {
                title: 'Jawaban Suara',
                text: 'Tekan Rekam suara, izinkan mikrofon, bicara, lalu tekan Berhenti. Rekaman langsung terkirim dan bisa didengarkan lagi. Tekan Rekam ulang untuk menggantinya. Batas lama rekaman tertulis di samping tombol, dan rekaman berhenti sendiri saat batasnya tercapai.',
            },
            {
                title: 'Tidak bisa merekam di halaman',
                text: 'Di alamat sekolah yang diawali http, browser tidak mengizinkan mikrofon. Di HP, tekan Rekam dengan HP untuk membuka perekam suara HP; di laptop, kirim rekaman lewat Pilih file suara.',
            },
        ],
        tip: 'Pakai earphone atau headset agar suara soal terdengar jelas dan tidak mengganggu teman di sebelah Anda.',
    },
    {
        id: 'student-connection',
        title: '5. Simpan Otomatis & Koneksi Terputus',
        icon: Wifi,
        badge: 'Jaringan',
        badgeColor: 'bg-cyan-50 text-cyan-700 border-cyan-200',
        summary: 'Jawaban tersimpan otomatis walau Wi-Fi lemah atau terputus.',
        intro: 'Setiap jawaban otomatis disimpan di perangkat Anda dan di server ujian.',
        points: [
            {
                title: 'Wi-Fi terputus',
                text: 'Muncul pemberitahuan kecil "Koneksi terputus" di layar. Tetap lanjutkan mengerjakan: jawaban aman di perangkat dan dikirim otomatis begitu koneksi pulih.',
            },
            {
                title: 'Halaman lambat terbuka',
                text: 'Buka halaman Cek Koneksi (tautan di halaman login, atau alamat server ditambah /cek-koneksi) di perangkat yang lambat. Tes 10 detiknya menunjukkan apakah masalahnya di server, di Wi-Fi, atau di pengaturan perangkat, beserta sarannya.',
            },
            {
                title: 'Perangkat mati atau rusak',
                text: 'Masuk lagi dengan akun Anda di komputer lain dan buka ujian yang sama. Jawaban yang sudah tersimpan di server akan dipulihkan.',
            },
            { title: 'Jangan menutup paksa', text: 'Jangan menghapus data browser selama ujian berlangsung.' },
        ],
    },
    {
        id: 'student-submit',
        title: '6. Waktu & Mengirim Jawaban',
        icon: Clock,
        badge: 'Ujian',
        badgeColor: 'bg-emerald-50 text-emerald-700 border-emerald-200',
        summary: 'Sisa waktu, mengirim jawaban, bukti kirim, dan apa yang terjadi saat waktu habis.',
        intro: 'Sisa waktu tampil di pojok kanan atas (di HP, di bilah atas layar) dan menghitung mundur sejak Anda menekan Kerjakan Sekarang, misalnya 1 jam 5 menit, lalu 12 menit 30 detik, dan 45 detik di menit terakhir.',
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
            {
                title: 'Bukti kirim',
                text: 'Setelah jawaban terkirim, muncul kode Bukti kirim (misalnya 7F3A-9C21). Catat atau foto kode ini: guru dapat mencocokkannya dengan jawaban yang tersimpan.',
            },
            {
                title: 'Hasil & kesempatan lagi',
                text: 'Bila guru menampilkan hasil, tombol Lihat Hasil muncul. Bila guru memberi lebih dari satu kesempatan, tombol Kerjakan lagi menampilkan sisa kesempatan Anda.',
            },
        ],
        tip: 'Pada sebagian ujian jawaban hanya bisa dikirim satu kali, jadi periksa kembali sebelum mengirim.',
    },
    {
        id: 'student-locked',
        title: '7. Ujian Terkunci',
        icon: Lock,
        badge: 'Anti-Curang',
        badgeColor: 'bg-red-50 text-red-700 border-red-200',
        summary: 'Mengapa ujian bisa terkunci dan cara membukanya kembali.',
        intro: 'Pada ujian dengan pengaman anti-curang, berpindah tab, membuka aplikasi lain, atau meminimalkan browser langsung membuat ujian terkunci, juga saat Wi-Fi terputus.',
        points: [
            {
                title: 'Minta dibuka',
                text: 'Nama Anda langsung muncul di daftar siswa terkunci milik guru atau pengawas. Tekan Minta Guru Membuka Kunci agar mereka tahu Anda menunggu.',
            },
            { title: 'Terbuka sendiri', text: 'Setelah guru membuka kunci, halaman terbuka sendiri tanpa dimuat ulang dan Anda bisa melanjutkan.' },
            {
                title: 'Jawaban tetap aman',
                text: 'Jawaban yang sudah diisi tidak hilang, tetapi waktu ujian tetap berjalan. Jika waktu habis sebelum kunci dibuka, jawaban yang sudah diisi terkirim otomatis.',
            },
            { title: 'Memuat ulang halaman', text: 'Memuat ulang (refresh) halaman ujian tidak mengunci ujian, asalkan halaman kembali terbuka dalam beberapa detik.' },
        ],
    },
    {
        id: 'student-profile',
        title: '8. Profil & Ganti Password',
        icon: UserRound,
        badge: 'Akun',
        badgeColor: 'bg-indigo-50 text-indigo-700 border-indigo-200',
        summary: 'Mengganti foto profil dan password lewat menu akun.',
        intro: 'Buka menu akun (foto atau ikon profil di pojok kanan atas), lalu pilih Profile.',
        points: [
            { title: 'Foto profil', text: 'Tekan Pilih Foto Baru (JPG, PNG, atau WebP, maksimal 2 MB), lalu Simpan Profil.' },
            { title: 'Ganti password', text: 'Pilih menu Password pada halaman Pengaturan Akun.' },
            { title: 'Data dari admin', text: 'NIS dan kelas diatur oleh admin. Hubungi wali kelas atau admin bila ada data yang salah.' },
        ],
    },
];

const teacherTopics: HelpTopic[] = [
    {
        id: 'getting-started',
        title: '1. Memulai & Alur Kerja Formulir',
        icon: Sparkles,
        badge: 'Dasar',
        badgeColor: 'bg-emerald-50 text-emerald-700 border-emerald-200',
        summary: 'Panduan awal membuat formulir, memilih templat ujian, mengatur ujian, mencoba lewat pratinjau, dan membagikannya.',
    },
    {
        id: 'question-editor',
        title: '2. Editor Soal & Tipe Pertanyaan',
        icon: ClipboardList,
        badge: 'Editor',
        badgeColor: 'bg-indigo-50 text-indigo-700 border-indigo-200',
        summary: 'Semua tipe soal, bagian dan lompat bagian, memindah soal, format teks, kunci jawaban, dan poin.',
    },
    {
        id: 'audio-listening',
        title: '3. Soal Audio & Jawaban Suara',
        icon: Mic,
        badge: 'Fitur Baru',
        badgeColor: 'bg-rose-50 text-rose-700 border-rose-200',
        summary: 'Rekam atau unggah audio soal dan pilihan jawaban, batas putar ala TOEFL, tipe soal Jawaban Suara, dan kompresi otomatis.',
    },
    {
        id: 'preview-exam',
        title: '4. Pratinjau Ujian',
        icon: Eye,
        badge: 'Fitur Baru',
        badgeColor: 'bg-rose-50 text-rose-700 border-rose-200',
        summary: 'Mencoba ujian seperti siswa, Tutup pratinjau, serta waktu dan durasi pratinjau yang tercatat.',
    },
    {
        id: 'arabic-quran',
        title: "5. Bahasa Arab & Al-Qur'an (Mushaf Madinah)",
        icon: BookOpen,
        badge: 'Fitur Utama',
        badgeColor: 'bg-emerald-50 text-emerald-700 border-emerald-200',
        summary: 'Font Amiri Quran Madinah, Scheherazade New, add-on Teks Arab (RTL), tabel shortcut keyboard Windows tanda harakat dan waqf.',
    },
    {
        id: 'math-katex',
        title: '6. Rumus Matematika & IPA (KaTeX)',
        icon: Layers,
        badge: 'Fitur Utama',
        badgeColor: 'bg-blue-50 text-blue-700 border-blue-200',
        summary: 'Rumus di antara tanda $...$, rumus kimia dengan \\ce{...}, serta add-on katalog Rumus Matematika dan Rumus IPA.',
    },
    {
        id: 'examview-import',
        title: '7. Import Soal ExamView / Blackboard (ZIP)',
        icon: UploadCloud,
        badge: 'Import',
        badgeColor: 'bg-cyan-50 text-cyan-700 border-cyan-200',
        summary: 'Panduan ekspor Blackboard 6.0-9.0 dari ExamView dan impor ke Falcon Forms lengkap dengan gambar soal.',
    },
    {
        id: 'docx-import',
        title: '8. Import Soal dari Word (.docx)',
        icon: FileText,
        badge: 'Import',
        badgeColor: 'bg-sky-50 text-sky-700 border-sky-200',
        summary:
            'Aturan penulisan soal di Word, baris Tipe/Poin/Wajib/Skala, dan contoh setiap tipe soal: pilihan ganda, kotak centang, drop-down, benar salah, isian, uraian, menjodohkan, kisi, skala, rating, tanggal, waktu.',
    },
    {
        id: 'proctoring-anti-cheat',
        title: '9. Pengawasan Ujian & Fitur Anti-Curang',
        icon: Shield,
        badge: 'Keamanan',
        badgeColor: 'bg-red-50 text-red-700 border-red-200',
        summary: 'Tab Keamanan: kunci saat pindah tab, Peta Kelas, Tambah Waktu, Token Ujian, buka kunci, jadwal ujian, dan Laporan Aktivitas.',
    },
    {
        id: 'students-cohort',
        title: '10. Manajemen Siswa, Kelas & Kelompok',
        icon: Users,
        badge: 'Akademik',
        badgeColor: 'bg-amber-50 text-amber-700 border-amber-200',
        summary: 'Import data siswa dari Excel/CSV, manajemen NIS & kelas, proteksi akun siswa, dan grup kelas.',
    },
    {
        id: 'results-gradebook',
        title: '11. Rekap Nilai & Analisis Ujian',
        icon: FileSpreadsheet,
        badge: 'Laporan',
        badgeColor: 'bg-purple-50 text-purple-700 border-purple-200',
        summary: 'Tabel rekap dengan cari, urut, A–Z dan halaman; penilaian esai dan jawaban suara; bukti kirim; unduh laporan Excel.',
    },
    {
        id: 'addons',
        title: '12. Add-on',
        icon: Puzzle,
        badge: 'Add-on',
        badgeColor: 'bg-violet-50 text-violet-700 border-violet-200',
        summary: 'Fitur tambahan yang dipasang dari halaman Add-on: Analisis Butir Soal, Token Ujian, Remedial & Pengayaan, Filter Cerdas, dan lainnya.',
    },
    {
        id: 'profile-settings',
        title: '13. Pengaturan Profil & Keamanan Akun',
        icon: KeyRound,
        badge: 'Akun',
        badgeColor: 'bg-slate-100 text-slate-700 border-slate-200',
        summary: 'Upload foto profil avatar, manajemen sesi perangkat aktif, preferensi bawaan ujian, dan backup arsip JSON.',
    },
];

/** The add-ons, as the Add-on page names them (App\Support\Addon). */
const ADDON_GUIDE: { name: string; text: string }[] = [
    { name: 'Analisis Butir Soal', text: 'Tingkat kesukaran, daya pembeda dan pengecoh tiap soal, dengan saran Dipakai, Revisi atau Ganti.' },
    { name: 'Token Ujian', text: 'Siswa baru bisa mulai setelah mengetik token 6 huruf dari pengawas; token berganti setiap 15 menit.' },
    { name: 'Remedial & Pengayaan', text: 'Daftar siswa di bawah KKM/KKTP tersusun otomatis; ujian remedial dan pengayaan dibuat dalam beberapa klik.' },
    { name: 'Peta Miskonsepsi', text: 'Membedakan siswa yang menebak, yang belum paham, dan yang yakin pada konsep keliru, dari tombol Ragu-ragu.' },
    { name: 'Desainer Formulir', text: 'Tema siap pakai, warna, huruf, gambar header dan logo sekolah untuk halaman ujian.' },
    { name: 'Rekap Kuis', text: 'Nilai semua kuis Anda dalam satu tabel per kelas, siap diunduh sebagai Excel.' },
    { name: 'Filter Cerdas', text: 'Hasil ujian dalam mode seperti Excel: cari, saring dan urutkan kolom apa saja, termasuk jawaban setiap soal.' },
    { name: 'Rumus Matematika & Rumus IPA', text: 'Katalog rumus LaTeX untuk Matematika, Fisika, Kimia dan Biologi; klik untuk menyisipkan ke soal.' },
    { name: 'Teks Arab (RTL)', text: 'Tombol RTL di bilah alat soal untuk menulis soal berbahasa Arab dan ayat Al-Qur\'an dari kanan ke kiri.' },
    { name: 'Ekspor Soal ke Word', text: 'Unduh soal ujian sebagai berkas Word (.docx) berformat templat Import Soal, lengkap dengan kunci dan poin.' },
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
                                ? 'Panduan singkat masuk, mengerjakan ujian, dan mengirim jawaban di Falcon Forms.'
                                : 'Panduan membuat ujian, menulis soal Arab & rumus, pengawasan ujian, hingga rekap nilai siswa.'
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
                                    <h2 class="text-xl font-bold text-slate-900">1. Memulai & Alur Kerja Formulir</h2>
                                    <p class="text-xs text-slate-500">Langkah mudah menyusun dan membagikan formulir di Falcon Forms</p>
                                </div>
                            </div>

                            <div class="mt-6 space-y-5 text-sm text-slate-700 leading-relaxed">
                                <p>
                                    Falcon Forms membantu Anda menyusun formulir dan ujian, membagikannya ke peserta, dan melihat hasilnya langsung, di kelas, di lab komputer, atau di ruang pelatihan.
                                </p>

                                <h3 class="font-bold text-slate-900 text-base">Alur 4 Langkah Pelaksanaan Ujian:</h3>
                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    <div class="rounded-2xl border border-slate-100 bg-slate-50/70 p-4">
                                        <div class="flex items-center gap-2 font-bold text-indigo-700 text-xs uppercase tracking-wider mb-1">
                                            <span class="flex h-5 w-5 items-center justify-center rounded-full bg-indigo-100 text-indigo-800 text-[10px]">1</span>
                                            Buat / Pilih Templat
                                        </div>
                                        <p class="text-xs text-slate-600">
                                            Mulai dari <em>Formulir kosong</em> atau gunakan templat siap pakai (PTS/PAS, PAI Arab, Matematika) dari baris templat atas.
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
                                            Pengaturan Ujian
                                        </div>
                                        <p class="text-xs text-slate-600">
                                            Di tab <strong>Pengaturan</strong>: durasi, KKM, jadwal ujian, kesempatan mengerjakan, acak urutan soal dan pilihan, tampilkan hasil ke siswa, serta proteksi anti-curang di tab <strong>Keamanan</strong>.
                                        </p>
                                    </div>
                                    <div class="rounded-2xl border border-slate-100 bg-slate-50/70 p-4">
                                        <div class="flex items-center gap-2 font-bold text-indigo-700 text-xs uppercase tracking-wider mb-1">
                                            <span class="flex h-5 w-5 items-center justify-center rounded-full bg-indigo-100 text-indigo-800 text-[10px]">4</span>
                                            Publikasi & Bagikan Tautan
                                        </div>
                                        <p class="text-xs text-slate-600">
                                            Coba dulu lewat <strong>Pratinjau</strong> (ikon mata), lalu klik tombol <strong>Terbit</strong> dan salin tautan publik formulir untuk dibagikan ke siswa atau kelas.
                                        </p>
                                    </div>
                                </div>

                                <div class="rounded-2xl border border-amber-200 bg-amber-50/80 p-4 text-xs text-amber-800">
                                    <strong>💡 Tips Kolaborasi Guru:</strong> Anda dapat mengundang rekan guru mata pelajaran serumpun untuk bersama-sama mengedit formulir soal melalui menu opsi titik tiga &gt; <em>Kolaborasi Formulir</em>.
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
                                    <p class="text-xs text-slate-500">Mengenal berbagai jenis butir soal yang didukung Falcon Forms</p>
                                </div>
                            </div>

                            <div class="mt-6 space-y-4 text-sm text-slate-700 leading-relaxed">
                                <p>Falcon Forms menyediakan fleksibilitas penuh untuk berbagai format penilaian ujian:</p>

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
                                    <div class="rounded-xl border border-slate-100 p-3.5">
                                        <h4 class="font-bold text-slate-900">🔽 Drop-down, Skala Linear, Rating, Tanggal & Waktu</h4>
                                        <p class="text-xs text-slate-600 mt-1">
                                            Drop-down menampilkan pilihan dalam daftar ringkas dan ikut dikoreksi otomatis. Skala, Rating, Tanggal dan Waktu cocok untuk angket.
                                        </p>
                                    </div>
                                    <div class="rounded-xl border border-slate-100 p-3.5">
                                        <h4 class="font-bold text-slate-900">📎 Unggah Berkas & 🎙️ Jawaban Suara</h4>
                                        <p class="text-xs text-slate-600 mt-1">
                                            Siswa mengirim foto, PDF, Word, Excel atau PowerPoint, atau merekam jawabannya dengan suara. Keduanya dinilai guru di tab
                                            Jawaban. Lihat topik <em>Soal Audio & Jawaban Suara</em>.
                                        </p>
                                    </div>
                                    <div class="rounded-xl border border-slate-100 p-3.5">
                                        <h4 class="font-bold text-slate-900">📑 Bagian, Judul dan deskripsi</h4>
                                        <p class="text-xs text-slate-600 mt-1">
                                            <strong>Bagian</strong> membagi ujian menjadi beberapa halaman dengan tombol Berikutnya dan Kembali, seperti Google Forms.
                                            Setelah sebuah bagian, atau berdasarkan jawaban soal Pilihan Ganda dan Drop-down, siswa dapat diarahkan ke bagian lain.
                                            <strong>Judul dan deskripsi</strong> menyisipkan teks petunjuk di antara soal tanpa perlu dijawab.
                                        </p>
                                    </div>
                                </div>

                                <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                    <h4 class="mb-2 text-xs font-bold uppercase tracking-wider text-slate-800">Menyusun soal</h4>
                                    <ul class="list-disc space-y-1.5 pl-5 text-xs leading-relaxed text-slate-600">
                                        <li><strong>Memindah soal:</strong> tahan ikon ⋮ pada soal, lalu seret ke tempat baru. Berlaku juga di layar sentuh.</li>
                                        <li><strong>Format teks:</strong> pilih teks pada soal untuk menebalkan, memiringkan, menggarisbawahi, atau memberi warna spidol.</li>
                                        <li><strong>Gambar, video & audio:</strong> tombol di bilah alat samping menambahkan media ke soal yang sedang aktif.</li>
                                        <li>
                                            <strong>Acak:</strong> di tab Pengaturan, urutan soal dan pilihan dapat diacak, dan dengan <em>Soal acak per siswa</em>
                                            setiap siswa mendapat sebagian soal yang diambil acak.
                                        </li>
                                    </ul>
                                </div>

                                <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                    <h4 class="mb-2 text-xs font-bold uppercase tracking-wider text-slate-800">Penetapan Skor & Poin</h4>
                                    <p class="text-xs leading-relaxed text-slate-600">
                                        Setiap soal bernilai <strong>1 poin</strong> secara bawaan dan dapat diganti angka lain per soal (atau lewat
                                        Pengaturan › Bawaan › Poin bawaan soal). Nilai akhir siswa selalu berada pada rentang <strong>0–100</strong>:
                                    </p>
                                    <p class="mt-2 rounded-xl bg-white px-3 py-2 text-center font-mono text-xs font-bold text-indigo-700">
                                        Nilai = poin yang diperoleh ÷ total poin soal yang dinilai × 100
                                    </p>
                                    <p class="mt-2 text-xs leading-relaxed text-slate-600">
                                        Contoh: 20 soal masing-masing 1 poin, 15 benar → nilai 75. Soal esai dan unggah berkas dinilai guru lewat tab
                                        Jawaban › "Nilai esai & isian"; sebelum dinilai, soal itu belum dihitung dan nilai siswa ditandai "sementara".
                                        Soal tanpa kunci jawaban (misalnya survei) tidak ikut dihitung. Nilai dihitung ulang dari kunci terbaru, jadi
                                        kunci yang diperbaiki langsung memperbarui nilai siswa.
                                    </p>
                                </div>
                            </div>
                        </section>

                        <!-- 3. SOAL AUDIO & JAWABAN SUARA -->
                        <section
                            v-show="isTopicVisible('audio-listening')"
                            id="audio-listening"
                            class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8"
                        >
                            <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                                <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-rose-50 text-rose-600">
                                    <Mic class="h-5 w-5" />
                                </div>
                                <div>
                                    <h2 class="text-xl font-bold text-slate-900">3. Soal Audio & Jawaban Suara</h2>
                                    <p class="text-xs text-slate-500">Soal listening dan speaking seperti tes TOEFL</p>
                                </div>
                            </div>

                            <div class="mt-6 space-y-4 text-sm leading-relaxed text-slate-700">
                                <div class="space-y-3">
                                    <div class="rounded-xl border border-slate-100 p-3.5">
                                        <h4 class="font-bold text-slate-900">🎵 Audio pada soal</h4>
                                        <p class="mt-1 text-xs text-slate-600">
                                            Pilih soal, lalu tekan <strong>Tambah audio</strong> (ikon not balok) di bilah alat samping. Rekam langsung dengan
                                            <strong>Rekam suara</strong>, atau kirim berkas lewat <strong>Pilih file suara</strong> (MP3, M4A, WAV, OGG, WebM atau
                                            rekaman HP, paling besar 40 MB). Tambahkan keterangan bila perlu, atau tekan <strong>Ganti suara</strong> untuk
                                            menggantinya.
                                        </p>
                                    </div>
                                    <div class="rounded-xl border border-slate-100 p-3.5">
                                        <h4 class="font-bold text-slate-900">🔁 Batas putar siswa</h4>
                                        <p class="mt-1 text-xs text-slate-600">
                                            Pilih <strong>Bebas</strong>, <strong>1×</strong>, <strong>2×</strong> atau <strong>3×</strong>. Seperti tes
                                            listening TOEFL, siswa hanya dapat memulai audio sebanyak itu; menjeda lalu melanjutkan tidak dihitung, dan audio
                                            yang dibatasi tidak dapat digeser. Hitungan putar dicatat di perangkat siswa untuk setiap percobaan.
                                        </p>
                                    </div>
                                    <div class="rounded-xl border border-slate-100 p-3.5">
                                        <h4 class="font-bold text-slate-900">🔘 Suara pada pilihan jawaban</h4>
                                        <p class="mt-1 text-xs text-slate-600">
                                            Pada soal Pilihan Ganda dan Kotak Centang, tekan ikon mikrofon di samping pilihan untuk merekam atau mengunggah
                                            suaranya. Saat urutan pilihan diacak, suara ikut pindah bersama pilihannya. Menekan tombol putar pada pilihan tidak
                                            memilih jawaban siswa.
                                        </p>
                                    </div>
                                    <div class="rounded-xl border border-slate-100 p-3.5">
                                        <h4 class="font-bold text-slate-900">🎙️ Tipe soal Jawaban Suara</h4>
                                        <p class="mt-1 text-xs text-slate-600">
                                            Siswa merekam jawabannya, seperti bagian speaking TOEFL. Atur lama rekaman paling lama, dari 30 detik sampai 5 menit
                                            atau tanpa batas. Rekaman yang terlalu panjang ditolak. Nilai jawabannya di tab <strong>Jawaban</strong> ›
                                            <strong>Nilai esai & isian</strong>: rekaman diputar langsung di sana dan dapat diunduh.
                                        </p>
                                    </div>
                                </div>

                                <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4 text-xs leading-relaxed text-slate-600">
                                    <h4 class="mb-2 font-bold uppercase tracking-wider text-slate-800">Ringan & stabil</h4>
                                    Semua suara dikompres otomatis di server menjadi MP3 mono dengan volume yang diratakan; rekaman WAV 1,4 MB misalnya menjadi
                                    sekitar 66 KB. Audio baru dimuat saat tombol putar ditekan, dan hanya satu suara yang berbunyi dalam satu waktu.
                                </div>

                                <div class="rounded-2xl border border-amber-200 bg-amber-50/80 p-4 text-xs text-amber-800">
                                    <strong>💡 Merekam butuh https:</strong> browser hanya mengizinkan mikrofon di alamat https atau di komputer server
                                    sendiri. Di alamat intranet yang diawali http, tombol rekam berganti menjadi <em>Rekam dengan HP</em> (membuka perekam
                                    suara HP) dan <em>Pilih file suara</em>.
                                </div>
                            </div>
                        </section>

                        <!-- 4. PRATINJAU UJIAN -->
                        <section
                            v-show="isTopicVisible('preview-exam')"
                            id="preview-exam"
                            class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8"
                        >
                            <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                                <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-rose-50 text-rose-600">
                                    <Eye class="h-5 w-5" />
                                </div>
                                <div>
                                    <h2 class="text-xl font-bold text-slate-900">4. Pratinjau Ujian</h2>
                                    <p class="text-xs text-slate-500">Mencoba ujian persis seperti yang dilihat siswa</p>
                                </div>
                            </div>

                            <div class="mt-6 space-y-4 text-sm leading-relaxed text-slate-700">
                                <ol class="space-y-3">
                                    <li class="rounded-xl border border-slate-100 p-3.5">
                                        <h4 class="font-bold text-slate-900">1. Buka pratinjau</h4>
                                        <p class="mt-1 text-xs text-slate-600">
                                            Tekan ikon mata (<strong>Pratinjau</strong>) di kanan atas editor, atau menu ⋮ › <strong>Buka pratinjau</strong>.
                                            Ujian terbuka di tab baru, lengkap dengan aturan, waktu, kunci pindah tab dan audio seperti di layar siswa.
                                        </p>
                                    </li>
                                    <li class="rounded-xl border border-slate-100 p-3.5">
                                        <h4 class="font-bold text-slate-900">2. Bar pratinjau</h4>
                                        <p class="mt-1 text-xs text-slate-600">
                                            Di pojok bawah tampil bar <strong>Pratinjau · Dibuka 08:15 · 4:05</strong>: jam pratinjau dibuka dan lama berjalan.
                                            Bar ini tetap bisa ditekan di atas layar aturan dan layar terkunci.
                                        </p>
                                    </li>
                                    <li class="rounded-xl border border-slate-100 p-3.5">
                                        <h4 class="font-bold text-slate-900">3. Tutup pratinjau</h4>
                                        <p class="mt-1 text-xs text-slate-600">
                                            Tekan <strong>Tutup pratinjau</strong>. Sesi pratinjau selesai dan halaman <strong>Pratinjau ditutup</strong>
                                            menampilkan jam dibuka, jam ditutup, dan durasinya. Jawaban pratinjau tidak dikirim, dan pratinjau berikutnya mulai
                                            dari kosong. Dari sana Anda dapat kembali ke editor atau membuka pratinjau lagi.
                                        </p>
                                    </li>
                                </ol>

                                <ul class="list-disc space-y-1.5 pl-5 text-xs text-slate-600">
                                    <li>
                                        Setiap pratinjau yang ditutup tercatat di
                                        <Link :href="route('reports.index')" class="font-bold text-indigo-600 underline">Laporan Aktivitas</Link> sebagai
                                        <em>Tutup pratinjau</em>, dengan jam dan durasinya.
                                    </li>
                                    <li>
                                        Pemilik ujian, kolaborator dan admin dapat membuka pratinjau kapan saja: tidak terikat jadwal ujian, batas kesempatan
                                        mengerjakan, atau token.
                                    </li>
                                    <li>Pratinjau yang ditinggal tanpa ditutup (tab ditutup) selesai sendiri setelah 6 jam tidak dibuka.</li>
                                    <li>Bila Anda menekan Kirim Jawaban saat pratinjau, jawaban itu tercatat di tab Jawaban seperti jawaban siswa.</li>
                                    <li>Pada ujian Mode pewawancara, halaman ujian dipakai untuk calon sungguhan, jadi tidak dianggap pratinjau.</li>
                                </ul>
                            </div>
                        </section>

                        <!-- 5. BAHASA ARAB & MUSHAF MADINAH -->
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
                                    <h2 class="text-xl font-bold text-slate-900">5. Panduan Mengetik Bahasa Arab & Al-Qur'an</h2>
                                    <p class="text-xs text-slate-500">Mendukung standar font Mushaf Madinah dengan tanda harakat & waqf sempurna</p>
                                </div>
                            </div>

                            <div class="mt-6 space-y-5 text-sm text-slate-700 leading-relaxed">
                                <p>
                                    Falcon Forms memiliki integrasi font khusus untuk pembelajaran Pendidikan Agama Islam (PAI), Tahfidz, dan Bahasa Arab dengan font bawaan: <strong>Amiri Quran</strong> (khas Mushaf Standar Madinah) dan <strong>Scheherazade New</strong>.
                                </p>
                                <p>
                                    Kolom soal yang isinya sebagian besar huruf Arab otomatis ditulis dari kanan ke kiri. Untuk mengatur arahnya sendiri, pasang
                                    add-on <strong>Teks Arab (RTL)</strong> di galeri add-on: tombol <strong>RTL</strong> lalu muncul di bilah alat setiap soal
                                    dan berwarna hijau saat aktif. Teks Arab tetap tampil dengan font Mushaf Madinah untuk siswa walaupun add-on dilepas.
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

                        <!-- 6. RUMUS MATEMATIKA (KATEX) -->
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
                                    <h2 class="text-xl font-bold text-slate-900">6. Panduan Menulis Rumus Matematika & IPA (KaTeX)</h2>
                                    <p class="text-xs text-slate-500">Tulis formula matematika, fisika, dan kimia dengan format LaTeX standar</p>
                                </div>
                            </div>

                            <div class="mt-6 space-y-5 text-sm text-slate-700 leading-relaxed">
                                <p>
                                    Cukup apit formula matematika menggunakan tanda dollar tunggal <code>$rumus$</code> untuk format sebaris (*inline*). Rumus
                                    kimia dan persamaan reaksi ditulis dengan <code>\ce{...}</code> di dalam tanda dolar, misalnya <code>$\ce{H2SO4}$</code>.
                                </p>
                                <p>
                                    Katalog rumus siap klik tersedia sebagai dua add-on yang dapat dipasang dan dilepas di galeri add-on:
                                    <strong>Rumus Matematika</strong> (bilangan, aljabar, fungsi, trigonometri, geometri, barisan, kalkulus, matriks, statistika,
                                    logika, notasi) dan <strong>Rumus IPA</strong> (Fisika, Kimia, Biologi, lengkap dengan keterangan simbol dan satuan). Setelah
                                    dipasang, tombolnya muncul di bilah alat setiap soal. Rumus yang sudah ada di soal tetap tampil walaupun add-on dilepas.
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
                                                <td class="p-3 font-mono text-indigo-600">$\ce{2H2 + O2 -&gt; 2H2O}$</td>
                                                <td class="p-3 font-serif">2H₂ + O₂ → 2H₂O</td>
                                            </tr>
                                            <tr>
                                                <td class="p-3 font-semibold">Kesetimbangan & Ion</td>
                                                <td class="p-3 font-mono text-indigo-600">$\ce{NH3 + H2O &lt;=&gt; NH4+ + OH-}$</td>
                                                <td class="p-3 font-serif">NH₃ + H₂O ⇌ NH₄⁺ + OH⁻</td>
                                            </tr>
                                            <tr>
                                                <td class="p-3 font-semibold">Besaran dengan Satuan</td>
                                                <td class="p-3 font-mono text-indigo-600">$g = 9{,}8\ \mathrm{m/s^{2}}$</td>
                                                <td class="p-3 font-serif">g = 9,8 m/s²</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </section>

                        <!-- 7. IMPORT SOAL EXAMVIEW (ZIP) -->
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
                                    <h2 class="text-xl font-bold text-slate-900">7. Import Bank Soal dari ExamView (ZIP)</h2>
                                    <p class="text-xs text-slate-500">Hemat waktu dengan mengimpor ratusan bank soal ExamView langsung ke Falcon Forms</p>
                                </div>
                            </div>

                            <div class="mt-6 space-y-4 text-sm text-slate-700 leading-relaxed">
                                <h3 class="font-bold text-slate-900">Cara Ekspor dari Aplikasi ExamView:</h3>
                                <ol class="list-decimal pl-5 space-y-2 text-xs text-slate-600">
                                    <li>Buka berkas bank soal Anda di program desktop <strong>ExamView Test Generator</strong>.</li>
                                    <li>Klik menu <strong>File &gt; Export &gt; Blackboard 6.0-7.0 (atau Blackboard 7.1-9.0)</strong>.</li>
                                    <li>Beri nama berkas dan simpan sebagai file arsip berformat <code>.zip</code>.</li>
                                    <li>Buka editor Falcon Forms, klik tombol <strong>Import Soal &gt; Upload ExamView ZIP</strong>.</li>
                                    <li>Falcon Forms akan mengekstrak butir soal, kunci jawaban, dan seluruh gambar diagram soal secara otomatis ke dalam formulir!</li>
                                </ol>
                            </div>
                        </section>

                        <!-- 8. IMPORT SOAL DARI WORD (DOCX) -->
                        <section
                            v-show="isTopicVisible('docx-import')"
                            id="docx-import"
                            class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8"
                        >
                            <DocxImportGuide title="8. Import Soal dari Word (.docx)" :samples="docxImportGuide" />
                        </section>

                        <!-- 9. PENGAWASAN CBT & ANTI-CURANG -->
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
                                    <h2 class="text-xl font-bold text-slate-900">9. Pengawasan Ujian & Fitur Anti-Curang</h2>
                                    <p class="text-xs text-slate-500">Mekanisme menjaga integritas dan kejujuran ujian online</p>
                                </div>
                            </div>

                            <div class="mt-6 space-y-4 text-sm text-slate-700 leading-relaxed">
                                <p>Semua alat pengawasan ada di tab <strong>Keamanan</strong> pada editor ujian:</p>
                                <div class="space-y-3">
                                    <div class="rounded-xl border border-slate-100 p-3.5">
                                        <h4 class="font-bold text-slate-900">🔒 Kunci saat siswa pindah tab</h4>
                                        <p class="text-xs text-slate-600 mt-1">
                                            Bila aktif, siswa yang berpindah tab, membuka aplikasi lain, atau me-minimize browser langsung terkunci, juga saat
                                            offline. Memuat ulang halaman yang sama dalam beberapa detik tidak mengunci. Selama ujian berjalan, soal tidak bisa
                                            disalin atau dicetak. Mematikan pengaman ini membuka semua siswa yang sedang terkunci.
                                        </p>
                                    </div>
                                    <div class="rounded-xl border border-slate-100 p-3.5">
                                        <h4 class="font-bold text-slate-900">🗺️ Peta Kelas</h4>
                                        <p class="text-xs text-slate-600 mt-1">
                                            Semua siswa sekilas, diperbarui tiap 5 detik: Terkunci, Keluar halaman, Terputus, Belum tersimpan, Diam, Mengerjakan,
                                            Belum mulai dan Selesai, lengkap dengan jumlah soal terjawab, tanda ragu, sisa waktu, dan kapan jawabannya terakhir
                                            tersimpan di server. Siswa yang perlu dilihat pengawas tampil paling depan.
                                        </p>
                                    </div>
                                    <div class="rounded-xl border border-slate-100 p-3.5">
                                        <h4 class="font-bold text-slate-900">🔓 Membuka kunci</h4>
                                        <p class="text-xs text-slate-600 mt-1">
                                            Siswa yang terkunci muncul di daftar <strong>Keamanan & Buka Kunci</strong> walau belum meminta. Tekan
                                            <strong>Buka Kunci</strong> per siswa, atau <strong>Buka Semua</strong>. Halaman siswa terbuka sendiri. Riwayat pindah
                                            tab setiap siswa tetap tercatat.
                                        </p>
                                    </div>
                                    <div class="rounded-xl border border-slate-100 p-3.5">
                                        <h4 class="font-bold text-slate-900">⏱️ Tambah Waktu</h4>
                                        <p class="text-xs text-slate-600 mt-1">
                                            Untuk ujian berbatas waktu atau berjadwal tutup: beri tambahan menit kepada semua siswa yang sedang mengerjakan,
                                            misalnya setelah listrik atau jaringan padam. Halaman siswa segera menampilkan sisa waktu yang baru.
                                        </p>
                                    </div>
                                    <div class="rounded-xl border border-slate-100 p-3.5">
                                        <h4 class="font-bold text-slate-900">🔑 Token Ujian (add-on)</h4>
                                        <p class="text-xs text-slate-600 mt-1">
                                            Siswa baru bisa mulai setelah mengetik token 6 huruf yang ditampilkan pengawas, bisa layar penuh untuk proyektor.
                                            Token berganti setiap 15 menit, jadi ujian tidak dapat dikerjakan dari luar ruangan.
                                        </p>
                                    </div>
                                    <div class="rounded-xl border border-slate-100 p-3.5">
                                        <h4 class="font-bold text-slate-900">📅 Jadwal ujian</h4>
                                        <p class="text-xs text-slate-600 mt-1">
                                            Di tab Pengaturan, tentukan waktu buka dan tutup. Sebelum dibuka siswa melihat hitung mundur; setelah ditutup tidak ada
                                            percobaan baru, sedangkan siswa yang sedang mengerjakan bekerja sampai waktu tutup.
                                        </p>
                                    </div>
                                </div>
                                <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4 text-xs leading-relaxed text-slate-600">
                                    <strong class="text-slate-800">Laporan Aktivitas:</strong>
                                    <Link :href="route('reports.index')" class="font-bold text-indigo-600 underline">Laporan</Link>
                                    mencatat siapa yang masuk, mulai ujian, mengirim jawaban, diberi tambah waktu, dan menutup pratinjau, lengkap dengan jam,
                                    perangkat, browser dan alamat IP.
                                </div>
                            </div>
                        </section>

                        <!-- 10. MANAJEMEN SISWA & KELAS -->
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
                                    <h2 class="text-xl font-bold text-slate-900">10. Manajemen Siswa, Kelas & Kelompok</h2>
                                    <p class="text-xs text-slate-500">Pengelolaan data peserta ujian, kelas, dan kredensial akun siswa</p>
                                </div>
                            </div>

                            <div class="mt-6 space-y-4 text-sm text-slate-700 leading-relaxed">
                                <p>
                                    Admin dan guru dapat mengunggah daftar peserta ujian secara massal melalui berkas templat Excel/CSV.
                                </p>
                                <ul class="list-disc pl-5 space-y-1.5 text-xs text-slate-600">
                                    <li><strong>Password Default:</strong> Password akun siswa secara otomatis disetel menggunakan 6 digit terakhir dari NIS/NISN siswa.</li>
                                    <li><strong>Proteksi Akun Siswa:</strong> Siswa tidak diizinkan menghapus akun secara mandiri untuk menjaga integritas data presensi dan riwayat nilai ujian.</li>
                                    <li><strong>Kelompok:</strong> Pengelompokan siswa ke dalam rombel/angkatan kelas agar pembagian formulir dapat ditargetkan dengan presisi.</li>
                                </ul>
                            </div>
                        </section>

                        <!-- 11. REKAP NILAI & ANALISIS -->
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
                                    <h2 class="text-xl font-bold text-slate-900">11. Rekap Nilai & Analisis Ujian</h2>
                                    <p class="text-xs text-slate-500">Melihat hasil pengerjaan siswa dan mengunduh laporan rekapitulasi</p>
                                </div>
                            </div>

                            <div class="mt-6 space-y-4 text-sm text-slate-700 leading-relaxed">
                                <p>
                                    Pada tab <strong>Jawaban</strong> di editor formulir, guru dapat memantau lembar jawaban yang masuk secara langsung (*real-time*):
                                </p>
                                <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                                    <h4 class="mb-2 text-xs font-bold uppercase tracking-wider text-slate-800">Rekapitulasi Nilai Siswa</h4>
                                    <ul class="list-disc space-y-1.5 pl-5 text-xs leading-relaxed text-slate-600">
                                        <li><strong>Cari</strong> nama, NIS, kelas, atau kode bukti kirim siswa.</li>
                                        <li><strong>A–Z:</strong> saring siswa menurut huruf awal nama depan atau nama belakang.</li>
                                        <li><strong>Urutkan</strong> dengan menekan judul kolom; tekan lagi untuk membalik urutan. Di HP, pilih urutan di menu Urutkan.</li>
                                        <li>
                                            <strong>Halaman:</strong> tabel menampilkan 10 baris per halaman. Ganti lewat <em>Baris per halaman</em> menjadi 50, 100,
                                            atau Semua, lalu pindah halaman dengan Sebelumnya, nomor halaman, dan Berikutnya. Pilihan ini diingat di perangkat Anda.
                                        </li>
                                        <li>
                                            <strong>Status:</strong> Selesai, Terlambat (menit), Otomatis (waktu habis saat halaman siswa tertutup atau offline, jawaban
                                            tersimpan dikirim sistem), atau Timeout.
                                        </li>
                                        <li>
                                            <strong>Bukti kirim & riwayat:</strong> kode di kolom Waktu Selesai membuka riwayat jawaban siswa selama ujian. Ikon perisai
                                            merah berarti jawaban tersimpan sudah berubah sejak dikirim.
                                        </li>
                                    </ul>
                                </div>
                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 text-xs">
                                    <div class="rounded-xl border border-slate-100 bg-slate-50/50 p-3">
                                        <p class="font-bold text-slate-800">✍️ Nilai esai & isian</p>
                                        <p class="text-slate-500 mt-0.5">
                                            Nilai uraian, unggah berkas, dan jawaban suara soal demi soal. Sebelum dinilai, nilai siswa ditandai "sementara".
                                        </p>
                                    </div>
                                    <div class="rounded-xl border border-slate-100 bg-slate-50/50 p-3">
                                        <p class="font-bold text-slate-800">📊 Statistik Kelulusan KKM</p>
                                        <p class="text-slate-500 mt-0.5">Melihat persentase siswa tuntas vs belum tuntas berdasarkan standar KKM ujian.</p>
                                    </div>
                                    <div class="rounded-xl border border-slate-100 bg-slate-50/50 p-3">
                                        <p class="font-bold text-slate-800">📥 Unduh Laporan (Excel)</p>
                                        <p class="text-slate-500 mt-0.5">Ekspor satu klik untuk laporan buku nilai resmi guru dan wali kelas.</p>
                                    </div>
                                    <div class="rounded-xl border border-slate-100 bg-slate-50/50 p-3">
                                        <p class="font-bold text-slate-800">📶 Pengiriman Jawaban</p>
                                        <p class="text-slate-500 mt-0.5">Seberapa cepat jawaban siswa sampai ke server, dan berapa yang terkirim pada percobaan pertama.</p>
                                    </div>
                                </div>
                                <p class="text-xs text-slate-500">
                                    Analisis Butir Soal, Filter Cerdas, Remedial & Pengayaan, Peta Miskonsepsi dan Rekap Kuis tersedia sebagai add-on. Lihat topik
                                    <em>Add-on</em>.
                                </p>
                            </div>
                        </section>

                        <!-- 12. ADD-ON -->
                        <section
                            v-show="isTopicVisible('addons')"
                            id="addons"
                            class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8"
                        >
                            <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                                <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-violet-50 text-violet-600">
                                    <Puzzle class="h-5 w-5" />
                                </div>
                                <div>
                                    <h2 class="text-xl font-bold text-slate-900">12. Add-on</h2>
                                    <p class="text-xs text-slate-500">Fitur tambahan yang bisa dipasang sesuai kebutuhan</p>
                                </div>
                            </div>

                            <div class="mt-6 space-y-4 text-sm leading-relaxed text-slate-700">
                                <p>
                                    Pasang add-on dari halaman
                                    <Link :href="route('addons.index')" class="font-bold text-indigo-600 underline">Add-on</Link>. Add-on yang terpasang dapat
                                    dimatikan untuk ujian tertentu dari editor ujian tanpa dilepas.
                                </p>
                                <div class="grid grid-cols-1 gap-3 text-xs sm:grid-cols-2">
                                    <div v-for="addon in ADDON_GUIDE" :key="addon.name" class="rounded-xl border border-slate-100 bg-slate-50/50 p-3">
                                        <p class="font-bold text-slate-800">{{ addon.name }}</p>
                                        <p class="mt-0.5 text-slate-500">{{ addon.text }}</p>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <!-- 13. PENGATURAN PROFIL & AKUN -->
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
                                    <h2 class="text-xl font-bold text-slate-900">13. Pengaturan Profil & Keamanan Akun</h2>
                                    <p class="text-xs text-slate-500">Konfigurasi preferensi pribadi, proteksi sesi, dan backup bank soal</p>
                                </div>
                            </div>

                            <div class="mt-6 space-y-4 text-sm text-slate-700 leading-relaxed">
                                <p>
                                    Melalui menu <Link :href="route('profile.edit')" class="font-bold text-indigo-600 underline">Pengaturan Profil</Link>, Anda dapat:
                                </p>
                                <ul class="list-disc pl-5 space-y-1.5 text-xs text-slate-600">
                                    <li><strong>Upload Foto Profil:</strong> Mengganti inisial dengan foto Anda (format JPG/PNG/WebP).</li>
                                    <li><strong>Data Akademik Guru:</strong> Melengkapi NIP, Mata Pelajaran, dan Asal Sekolah/Lembaga.</li>
                                    <li><strong>Preferensi Ujian Bawaan:</strong> Mengatur KKM default, durasi default, dan font Arab pilihan agar otomatis terisi saat membuat formulir baru.</li>
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
