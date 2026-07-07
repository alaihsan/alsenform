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

export const templatePresets: Record<string, { title: string; description: string; question: string; options: string[] }> = {
    blank: {
        title: 'Untitled form',
        description: 'Form description',
        question: 'Untitled Question',
        options: ['Option 1'],
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
