import {
    CalendarDays,
    CheckSquare,
    ChevronDown,
    Circle,
    Clock,
    FileText,
    Grid3X3,
    List,
    Menu,
    MoreVertical,
    Star,
} from 'lucide-vue-next';
import type { QuestionType, QuestionTypeOption } from '@/types/quiz';

/** The title of a new question until the teacher writes one. */
export const UNTITLED_QUESTION = 'Pertanyaan tanpa judul';

/** Titles that only hold the place of a question's text (older forms still have the English ones). */
export const PLACEHOLDER_QUESTION_TITLES = [UNTITLED_QUESTION, 'Untitled Question', 'Short answer question', 'Paragraph question'];

/** The "Lainnya" option of a choice question (older forms have "Other"). */
export const OTHER_OPTIONS = ['Lainnya', 'Other'];

/** The names of the question types as teachers know them (the stored type stays in English). */
export const QUESTION_TYPE_LABELS: Record<string, string> = {
    'Multiple choice': 'Pilihan Ganda',
    Checkboxes: 'Kotak Centang',
    'Drop-down': 'Drop-down',
    'Short answer': 'Isian Singkat',
    Paragraph: 'Uraian',
    'File upload': 'Unggah Berkas',
    'Linear scale': 'Skala Linear',
    Rating: 'Rating',
    'Multiple-choice grid': 'Kisi Pilihan Ganda',
    'Tick box grid': 'Kisi Kotak Centang',
    Date: 'Tanggal',
    Time: 'Waktu',
    Section: 'Bagian',
    'Title and description': 'Judul dan deskripsi',
};

export const questionTypeLabel = (type: string): string => QUESTION_TYPE_LABELS[type] ?? type;

export const templatePresets: Record<string, { title: string; description: string; question: string; options: string[] }> = {
    blank: {
        title: 'Formulir tanpa judul',
        description: '',
        question: UNTITLED_QUESTION,
        options: ['Opsi 1'],
    },
    'pilihan-ganda': {
        title: 'Penilaian Tengah Semester (PTS)',
        description: 'Petunjuk: Pilihlah satu jawaban yang paling tepat pada soal pilihan ganda berikut.',
        question: 'Manakah di bawah ini yang merupakan fungsi utama Pancasila sebagai dasar negara?',
        options: ['Pedoman hidup dan sumber hukum tertinggi', 'Alat kekuasaan pemerintah', 'Hukum sementara', 'Peraturan daerah'],
    },
    'pai-arab': {
        title: 'Ujian PAI & Bahasa Arab (Madinah)',
        description: 'Ujian pemahaman ayat Al-Qur\'an dan hukum tajwid standar Mushaf Madinah.',
        question: 'بِسْمِ ٱللَّهِ ٱلرَّحْمَـٰنِ ٱلرَّحِيمِ ﴿١﴾ - Hukum tajwid pada lafaz \'ٱلرَّحْمَـٰنِ\' adalah?',
        options: ['Alif Lam Syamsiyah', 'Alif Lam Qamariyah', 'Idgham Bighunnah', 'Ikhfa Haqiqi'],
    },
    matematika: {
        title: 'Ujian Matematika & Eksakta',
        description: 'Petunjuk: Selesaikan soal matematika berikut dengan perhitungan yang teliti.',
        question: 'Jika diketahui persamaan kuadrat $f(x) = x^2 - 5x + 6 = 0$, maka akar-akar persamaan tersebut adalah?',
        options: ['$x_1 = 2$ dan $x_2 = 3$', '$x_1 = -2$ dan $x_2 = -3$', '$x_1 = 1$ dan $x_2 = 6$', '$x_1 = -1$ dan $x_2 = -6$'],
    },
    'esai-analisis': {
        title: 'Ujian Esai & Uraian Analisis',
        description: 'Jawablah pertanyaan analisis berikut dengan uraian yang jelas, runut, dan mendalam.',
        question: 'Jelaskan dampak perkembangan teknologi kecerdasan buatan terhadap masa depan dunia pendidikan!',
        options: ['Tuliskan uraian analisis Anda secara terstruktur.'],
    },
    'survei-belajar': {
        title: 'Refleksi & Evaluasi Pembelajaran Siswa',
        description: 'Kuesioner evaluasi proses belajar mengajar di kelas untuk peningkatan mutu pembelajaran.',
        question: 'Seberapa baik pemahaman Anda terhadap materi yang diajarkan pada bab ini?',
        options: ['Sangat memahami', 'Cukup memahami', 'Kurang memahami', 'Perlu bimbingan tambahan'],
    },
    'contact-information': {
        title: 'Contact Information',
        description: 'Kumpulkan informasi kontak responden.',
        question: 'Informasi apa yang ingin dikirim?',
        options: ['Nama lengkap', 'Email', 'Nomor WhatsApp'],
    },
    'party-invite': {
        title: 'Party Invite',
        description: 'Konfirmasi undangan acara.',
        question: 'Apakah kamu akan hadir?',
        options: ['Ya, hadir', 'Belum pasti', 'Tidak bisa hadir'],
    },
    'work-request': {
        title: 'Work Request',
        description: 'Detail permintaan pekerjaan untuk tim.',
        question: 'Jenis pekerjaan apa yang dibutuhkan?',
        options: ['Desain', 'Dokumen', 'Perbaikan teknis'],
    },
    rsvp: {
        title: 'RSVP',
        description: 'Konfirmasi kehadiran peserta.',
        question: 'Status kehadiran kamu?',
        options: ['Hadir', 'Tidak hadir', 'Mungkin hadir'],
    },
    't-shirt-sign-up': {
        title: 'T-Shirt Sign Up',
        description: 'Pemesanan kaos dan pilihan ukuran.',
        question: 'Ukuran kaos yang dipilih?',
        options: ['S', 'M', 'L', 'XL'],
    },
};

export const fonts = [
    { name: 'Inter (Default)', value: "'Inter', sans-serif" },
    { name: 'Amiri Quran (Mushaf Madinah)', value: "'Amiri Quran', 'Scheherazade New', serif" },
    { name: 'Scheherazade New (Arab Tradisional)', value: "'Scheherazade New', serif" },
    { name: 'Noto Naskh Arabic (Arab Standar)', value: "'Noto Naskh Arabic', serif" },
    { name: 'Amiri (Arab Klasik)', value: "'Amiri', serif" },
    { name: 'Outfit (Modern)', value: "'Outfit', sans-serif" },
    { name: 'Plus Jakarta Sans', value: "'Plus Jakarta Sans', sans-serif" },
    { name: 'Montserrat', value: "'Montserrat', sans-serif" },
    { name: 'Roboto', value: "'Roboto', sans-serif" },
    { name: 'Playfair Display', value: "'Playfair Display', serif" },
    { name: 'Lora', value: "'Lora', serif" },
    { name: 'Merriweather', value: "'Merriweather', serif" },
    { name: 'Georgia', value: "'Georgia', serif" },
    { name: 'Fira Code (Monospace)', value: "'Fira Code', monospace" },
];

export const colorThemes = [
    { name: 'Classic Indigo', theme: 'bg-indigo-600', bg: 'bg-[#f0efff]' },
    { name: 'Emerald Garden', theme: 'bg-emerald-600', bg: 'bg-[#eefdf5]' },
    { name: 'Rose Blush', theme: 'bg-rose-600', bg: 'bg-[#fff1f2]' },
    { name: 'Amber Warmth', theme: 'bg-amber-600', bg: 'bg-[#fefaf0]' },
    { name: 'Sky Breeze', theme: 'bg-sky-600', bg: 'bg-[#f0f9ff]' },
    { name: 'Fuchsia Fantasy', theme: 'bg-fuchsia-600', bg: 'bg-[#fdf4ff]' },
    { name: 'Violet Mystery', theme: 'bg-violet-600', bg: 'bg-[#f5f3ff]' },
    { name: 'Teal Dream', theme: 'bg-teal-600', bg: 'bg-[#f0fdfa]' },
    { name: 'Orange Warmth', theme: 'bg-orange-600', bg: 'bg-[#fff7ed]' },
    { name: 'Slate Gray', theme: 'bg-slate-700', bg: 'bg-[#f8fafc]' },
];

export const backgroundPatterns = [
    { name: 'Solid Background', value: 'pattern-none' },
    { name: 'Polka Dots', value: 'pattern-dots' },
    { name: 'Grid Graph', value: 'pattern-grid' },
    { name: 'Stripes', value: 'pattern-diagonal' },
    { name: 'Waves Ripple', value: 'pattern-waves' },
    { name: 'Zigzag Chevron', value: 'pattern-zigzag' },
    { name: 'Hexagon Cell', value: 'pattern-hexagons' },
    { name: 'Blueprint Grid', value: 'pattern-blueprint' },
    { name: 'Bubbles Circle', value: 'pattern-bubbles' },
    { name: 'Triangle Mesh', value: 'pattern-triangles' },
];

export const questionTypes = [
    { value: 'Short answer', group: 'text', icon: Menu },
    { value: 'Paragraph', group: 'text', icon: List },
    { value: 'Multiple choice', group: 'choice', icon: Circle },
    { value: 'Checkboxes', group: 'choice', icon: CheckSquare },
    { value: 'Drop-down', group: 'choice', icon: ChevronDown },
    { value: 'File upload', group: 'file', icon: FileText },
    { value: 'Linear scale', group: 'scale', icon: MoreVertical },
    { value: 'Rating', group: 'scale', icon: Star },
    { value: 'Multiple-choice grid', group: 'grid', icon: Grid3X3 },
    { value: 'Tick box grid', group: 'grid', icon: Grid3X3 },
    { value: 'Date', group: 'date', icon: CalendarDays },
    { value: 'Time', group: 'date', icon: Clock },
] as const satisfies readonly QuestionTypeOption[];

export const optionQuestionTypes: QuestionType[] = ['Multiple choice', 'Checkboxes', 'Drop-down'];
export const noOptionQuestionTypes: QuestionType[] = ['Short answer', 'Paragraph', 'File upload', 'Date', 'Time'];
export const scaleQuestionTypes: QuestionType[] = ['Linear scale', 'Rating'];
export const gridQuestionTypes: QuestionType[] = ['Multiple-choice grid', 'Tick box grid'];
export const pieColors = ['#4f46e5', '#10b981', '#f59e0b', '#ef4444', '#0ea5e9', '#a855f7', '#14b8a6', '#f97316'];
