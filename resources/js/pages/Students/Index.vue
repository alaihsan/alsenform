<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import {
    Users,
    UserPlus,
    UploadCloud,
    Download,
    Search,
    Filter,
    Key,
    Pencil,
    Trash2,
    CheckCircle2,
    AlertCircle,
    X,
    FileSpreadsheet,
    ClipboardPaste,
    Check,
    Copy,
    GraduationCap,
    School,
    ArrowLeft,
    Sparkles,
    ShieldAlert,
    RefreshCw,
    Loader2,
    FileText,
    Eye,
    EyeOff,
    Shuffle,
} from 'lucide-vue-next';
import { useToast } from '@/composables/useToast';
import axios from 'axios';

interface Student {
    id: number;
    nis: string;
    name: string;
    kelas: string | null;
    email: string | null;
    default_password: string;
    created_at: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedStudents {
    data: Student[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: PaginationLink[];
}

interface Props {
    students: PaginatedStudents;
    filters: {
        search: string;
        kelas: string;
    };
    classes: string[];
    stats: {
        total_students: number;
        total_classes: number;
    };
}

const props = defineProps<Props>();
const page = usePage();
const user = computed(() => (page.props.auth as any)?.user);

const { toastMessage, showToast } = useToast(3000);

// Flash message listener
watch(
    () => (page.props as any).flash?.success,
    (msg) => {
        if (msg) showToast(msg);
    },
    { immediate: true }
);

// Filters & Search
const searchInput = ref(props.filters.search || '');
const selectedClass = ref(props.filters.kelas || '');
let searchTimer: ReturnType<typeof setTimeout> | null = null;

function applyFilters(): void {
    router.get(
        route('students.index'),
        {
            search: searchInput.value || undefined,
            kelas: selectedClass.value || undefined,
        },
        {
            preserveState: true,
            replace: true,
        }
    );
}

function handleSearch(): void {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        applyFilters();
    }, 350);
}

function clearFilters(): void {
    searchInput.value = '';
    selectedClass.value = '';
    applyFilters();
}

// Copy to clipboard helper
const copiedNis = ref<string | null>(null);
function copyPassword(password: string, nis: string): void {
    navigator.clipboard.writeText(password);
    copiedNis.value = nis;
    showToast(`Password ${password} berhasil disalin!`);
    setTimeout(() => {
        if (copiedNis.value === nis) copiedNis.value = null;
    }, 2000);
}

// --- MODAL: Add / Edit Student ---
const isFormModalOpen = ref(false);
const isEditing = ref(false);
const editingStudentId = ref<number | null>(null);

const studentForm = useForm({
    nis: '',
    name: '',
    kelas: '',
    email: '',
});

const calculatedDefaultPassword = computed(() => {
    const raw = studentForm.nis.trim();
    const digits = raw.replace(/\D/g, '');
    if (digits.length >= 6) return digits.slice(-6);
    if (raw.length >= 6) return raw.slice(-6);
    return raw;
});

function openAddModal(): void {
    isEditing.value = false;
    editingStudentId.value = null;
    studentForm.reset();
    studentForm.clearErrors();
    isFormModalOpen.value = true;
}

function openEditModal(student: Student): void {
    isEditing.value = true;
    editingStudentId.value = student.id;
    studentForm.nis = student.nis || '';
    studentForm.name = student.name || '';
    studentForm.kelas = student.kelas || '';
    studentForm.email = student.email || '';
    studentForm.clearErrors();
    isFormModalOpen.value = true;
}

function submitStudent(): void {
    if (isEditing.value && editingStudentId.value) {
        studentForm.put(route('students.update', editingStudentId.value), {
            onSuccess: () => {
                isFormModalOpen.value = false;
                showToast('Data murid berhasil diperbarui!');
            },
        });
    } else {
        studentForm.post(route('students.store'), {
            onSuccess: () => {
                isFormModalOpen.value = false;
                showToast('Murid berhasil ditambahkan!');
            },
        });
    }
}

// --- MODAL: Delete Student ---
const isDeleteModalOpen = ref(false);
const studentToDelete = ref<Student | null>(null);
const isDeleting = ref(false);

function openDeleteModal(student: Student): void {
    studentToDelete.value = student;
    isDeleteModalOpen.value = true;
}

function confirmDelete(): void {
    if (!studentToDelete.value) return;
    isDeleting.value = true;
    router.delete(route('students.destroy', studentToDelete.value.id), {
        onFinish: () => {
            isDeleting.value = false;
            isDeleteModalOpen.value = false;
            studentToDelete.value = null;
            showToast('Murid berhasil dihapus.');
        },
    });
}

// --- MODAL: Ganti / Reset Password Murid ---
const isPasswordModalOpen = ref(false);
const studentToManagePassword = ref<Student | null>(null);
const newPasswordInput = ref('');
const showPasswordText = ref(false);
const isUpdatingPassword = ref(false);

function openPasswordModal(student: Student): void {
    studentToManagePassword.value = student;
    newPasswordInput.value = student.default_password || '';
    showPasswordText.value = true;
    isPasswordModalOpen.value = true;
}

function setPasswordToNisDefault(): void {
    if (!studentToManagePassword.value) return;
    newPasswordInput.value = studentToManagePassword.value.default_password || '';
}

function generateRandomPassword(): void {
    const random6 = Math.floor(100000 + Math.random() * 900000).toString();
    newPasswordInput.value = random6;
}

function submitCustomPassword(): void {
    if (!studentToManagePassword.value) return;
    if (newPasswordInput.value.length < 6) {
        showToast('Password minimal 6 karakter.');
        return;
    }

    isUpdatingPassword.value = true;
    router.post(
        route('students.change-password', studentToManagePassword.value.id),
        { password: newPasswordInput.value },
        {
            onFinish: () => {
                isUpdatingPassword.value = false;
                isPasswordModalOpen.value = false;
                showToast(`Password murid '${studentToManagePassword.value?.name}' berhasil diubah!`);
                studentToManagePassword.value = null;
            },
        }
    );
}

function directResetToNisDefault(): void {
    if (!studentToManagePassword.value) return;
    isUpdatingPassword.value = true;
    router.post(
        route('students.reset-password', studentToManagePassword.value.id),
        {},
        {
            onFinish: () => {
                isUpdatingPassword.value = false;
                isPasswordModalOpen.value = false;
                showToast(`Password murid berhasil direset ke: ${studentToManagePassword.value?.default_password}`);
                studentToManagePassword.value = null;
            },
        }
    );
}

// --- MODAL: Import Students (CSV / Paste) ---
const isImportModalOpen = ref(false);
const importTab = ref<'file' | 'paste'>('paste');
const importText = ref('');
const importFile = ref<File | null>(null);
const isParsing = ref(false);
const isSubmittingImport = ref(false);

interface ParsedStudentRow {
    nis: string;
    name: string;
    kelas: string;
    default_password: string;
    email: string;
    is_update: boolean;
}

interface ParseError {
    row: number;
    line: string;
    message: string;
}

const previewRows = ref<ParsedStudentRow[]>([]);
const parseErrors = ref<ParseError[]>([]);
const hasParsed = ref(false);

function openImportModal(): void {
    importTab.value = 'paste';
    importText.value = '';
    importFile.value = null;
    previewRows.value = [];
    parseErrors.value = [];
    hasParsed.value = false;
    isImportModalOpen.value = true;
}

function handleFileChange(event: Event): void {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        importFile.value = target.files[0];
        previewImport();
    }
}

async function previewImport(): Promise<void> {
    isParsing.value = true;
    hasParsed.value = false;
    parseErrors.value = [];
    previewRows.value = [];

    try {
        const formData = new FormData();
        formData.append('dry_run', '1');

        if (importTab.value === 'file' && importFile.value) {
            formData.append('file', importFile.value);
        } else if (importTab.value === 'paste' && importText.value.trim()) {
            formData.append('text', importText.value);
        } else {
            showToast('Silakan pilih file atau tempel teks data murid terlebih dahulu.');
            isParsing.value = false;
            return;
        }

        const response = await axios.post(route('students.import'), formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        if (response.data.success) {
            previewRows.value = response.data.preview || [];
            parseErrors.value = response.data.errors || [];
            hasParsed.value = true;
        }
    } catch (err: any) {
        const message = err.response?.data?.message || 'Gagal memproses data impor.';
        showToast(message);
    } finally {
        isParsing.value = false;
    }
}

async function executeImport(): Promise<void> {
    if (previewRows.value.length === 0) {
        showToast('Tidak ada data murid valid untuk diimpor.');
        return;
    }

    isSubmittingImport.value = true;

    try {
        const response = await axios.post(route('students.import'), {
            students: previewRows.value,
        });

        if (response.data.success) {
            showToast(response.data.message);
            isImportModalOpen.value = false;
            router.reload({ preserveScroll: true });
        }
    } catch (err: any) {
        const message = err.response?.data?.message || 'Terjadi kesalahan saat menyimpan data.';
        showToast(message);
    } finally {
        isSubmittingImport.value = false;
    }
}
</script>

<template>
    <div class="min-h-screen bg-slate-50 font-sans text-slate-900 antialiased selection:bg-emerald-500 selection:text-white">
        <Head title="Manajemen & Impor Murid" />

        <!-- Toast Notification -->
        <Transition
            enter-active-class="transform ease-out duration-300 transition"
            enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
            enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
            leave-active-class="transition ease-in duration-100"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="toastMessage"
                class="fixed bottom-6 right-6 z-50 flex items-center gap-3 rounded-2xl bg-slate-900/95 px-4 py-3 text-sm font-medium text-white shadow-2xl backdrop-blur"
            >
                <div class="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-500/20 text-emerald-400">
                    <Check class="h-3.5 w-3.5" />
                </div>
                <span>{{ toastMessage }}</span>
            </div>
        </Transition>

        <!-- Navbar Header -->
        <header class="sticky top-0 z-30 border-b border-slate-200/80 bg-white/95 backdrop-blur">
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <div class="flex items-center gap-4">
                    <Link
                        :href="route('dashboard')"
                        class="group flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 text-slate-600 transition hover:border-slate-300 hover:bg-slate-100"
                        title="Kembali ke Dashboard Utama"
                    >
                        <ArrowLeft class="h-4 w-4 transition group-hover:-translate-x-0.5" />
                    </Link>

                    <div class="flex items-center gap-2.5">
                        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-tr from-emerald-600 to-teal-500 text-white shadow-sm">
                            <GraduationCap class="h-5 w-5" />
                        </div>
                        <div>
                            <h1 class="text-sm font-bold text-slate-900 sm:text-base">Manajemen Murid</h1>
                            <p class="text-[11px] font-medium text-slate-500">Database Siswa & Akun Alsenform</p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2 sm:gap-3">
                    <a
                        :href="route('students.template')"
                        download="template_impor_murid.csv"
                        class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
                        title="Unduh contoh template file CSV"
                    >
                        <Download class="h-3.5 w-3.5 text-slate-500" />
                        <span class="hidden sm:inline">Template CSV</span>
                    </a>

                    <button
                        type="button"
                        @click="openAddModal"
                        class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
                    >
                        <UserPlus class="h-3.5 w-3.5 text-indigo-600" />
                        <span>Tambah Murid</span>
                    </button>

                    <button
                        type="button"
                        @click="openImportModal"
                        class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-3.5 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-emerald-700 active:scale-[0.98]"
                    >
                        <UploadCloud class="h-3.5 w-3.5" />
                        <span>Impor Murid</span>
                    </button>
                </div>
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            <!-- Stat Cards Row -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <!-- Stat 1: Total Siswa -->
                <div class="relative overflow-hidden rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Total Murid Terdaftar</p>
                            <p class="mt-2 text-3xl font-extrabold text-slate-900">{{ stats.total_students }}</p>
                        </div>
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600">
                            <Users class="h-6 w-6" />
                        </div>
                    </div>
                    <div class="mt-4 flex items-center gap-2 text-xs font-medium text-slate-500">
                        <span class="inline-block h-2 w-2 rounded-full bg-emerald-500"></span>
                        Status akun aktif & siap mengerjakan form
                    </div>
                </div>

                <!-- Stat 2: Total Kelas -->
                <div class="relative overflow-hidden rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Total Kelas / Rombel</p>
                            <p class="mt-2 text-3xl font-extrabold text-slate-900">{{ stats.total_classes }}</p>
                        </div>
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600">
                            <School class="h-6 w-6" />
                        </div>
                    </div>
                    <div class="mt-4 flex items-center gap-2 text-xs font-medium text-slate-500">
                        <span class="inline-block h-2 w-2 rounded-full bg-indigo-500"></span>
                        Tersedia pengelompokan kelas otomatis
                    </div>
                </div>

                <!-- Stat 3: Format Password Default -->
                <div class="relative overflow-hidden rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Password Default</p>
                            <p class="mt-2 text-xl font-extrabold text-amber-600">6 Digit Akhir NIS</p>
                        </div>
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-50 text-amber-600">
                            <Key class="h-6 w-6" />
                        </div>
                    </div>
                    <div class="mt-4 flex items-center gap-2 text-xs font-medium text-slate-500">
                        <span class="inline-block h-2 w-2 rounded-full bg-amber-500"></span>
                        Murid dapat login langsung dengan NIS & password ini
                    </div>
                </div>
            </div>

            <!-- Toolbar: Search & Filter -->
            <div class="mt-6 flex flex-col gap-3 rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-1 flex-col gap-2 sm:flex-row sm:items-center">
                    <!-- Search Bar -->
                    <div class="relative flex-1 max-w-md">
                        <Search class="absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input
                            v-model="searchInput"
                            type="search"
                            @input="handleSearch"
                            placeholder="Cari berdasarkan Nama atau NIS..."
                            class="w-full rounded-xl border border-slate-200 bg-slate-50/50 py-2 pl-9 pr-4 text-xs font-medium text-slate-800 placeholder:text-slate-400 focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                        />
                    </div>

                    <!-- Filter Kelas -->
                    <div class="flex items-center gap-2">
                        <select
                            v-model="selectedClass"
                            @change="applyFilters"
                            class="rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2 text-xs font-semibold text-slate-700 focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                        >
                            <option value="">Semua Kelas</option>
                            <option v-for="cls in classes" :key="cls" :value="cls">
                                Kelas {{ cls }}
                            </option>
                        </select>

                        <button
                            v-if="searchInput || selectedClass"
                            type="button"
                            @click="clearFilters"
                            class="rounded-xl border border-slate-200 bg-slate-100 px-2.5 py-2 text-xs font-medium text-slate-600 transition hover:bg-slate-200"
                            title="Reset Filter"
                        >
                            Reset
                        </button>
                    </div>
                </div>

                <div class="text-xs font-medium text-slate-500">
                    Menampilkan <span class="font-bold text-slate-800">{{ students.from || 0 }} - {{ students.to || 0 }}</span> dari <span class="font-bold text-slate-800">{{ students.total }}</span> murid
                </div>
            </div>

            <!-- Student Data Table -->
            <div class="mt-4 overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="border-b border-slate-200 bg-slate-50/80 font-bold uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="py-3.5 pl-6 pr-3">NIS</th>
                                <th class="px-4 py-3.5">Nama Murid</th>
                                <th class="px-4 py-3.5">Kelas</th>
                                <th class="px-4 py-3.5">Password Default</th>
                                <th class="px-4 py-3.5">Terdaftar</th>
                                <th class="py-3.5 pl-4 pr-6 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700">
                            <tr
                                v-for="student in students.data"
                                :key="student.id"
                                class="transition hover:bg-slate-50/80"
                            >
                                <!-- NIS -->
                                <td class="py-3.5 pl-6 pr-3 font-mono font-bold text-slate-900">
                                    <span class="rounded-md bg-slate-100 px-2 py-1 text-[11px] font-semibold text-slate-800">
                                        {{ student.nis || '-' }}
                                    </span>
                                </td>

                                <!-- Nama -->
                                <td class="px-4 py-3.5">
                                    <div class="flex items-center gap-2.5">
                                        <div class="flex h-7 w-7 items-center justify-center rounded-full bg-emerald-100 text-[11px] font-bold text-emerald-800">
                                            {{ student.name.charAt(0).toUpperCase() }}
                                        </div>
                                        <div>
                                            <p class="font-semibold text-slate-900">{{ student.name }}</p>
                                            <p v-if="student.email" class="text-[10px] text-slate-400">{{ student.email }}</p>
                                        </div>
                                    </div>
                                </td>

                                <!-- Kelas -->
                                <td class="px-4 py-3.5">
                                    <span
                                        v-if="student.kelas"
                                        class="inline-flex items-center rounded-lg bg-indigo-50 px-2.5 py-1 text-[11px] font-bold text-indigo-700"
                                    >
                                        {{ student.kelas }}
                                    </span>
                                    <span v-else class="text-slate-400 italic text-[11px]">Belum diatur</span>
                                </td>

                                <!-- Password Default (6 digit) -->
                                <td class="px-4 py-3.5">
                                    <div class="flex items-center gap-2">
                                        <code class="rounded bg-amber-50 px-2 py-0.5 font-mono text-xs font-bold text-amber-800 border border-amber-200/60">
                                            {{ student.default_password || '******' }}
                                        </code>
                                        <button
                                            v-if="student.default_password"
                                            type="button"
                                            @click="copyPassword(student.default_password, student.nis)"
                                            class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition"
                                            title="Salin Password"
                                        >
                                            <Check v-if="copiedNis === student.nis" class="h-3.5 w-3.5 text-emerald-600" />
                                            <Copy v-else class="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                </td>

                                <!-- Terdaftar -->
                                <td class="px-4 py-3.5 text-slate-500 text-[11px]">
                                    {{ student.created_at }}
                                </td>

                                <!-- Aksi -->
                                <td class="py-3.5 pl-4 pr-6 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button
                                            type="button"
                                            @click="openPasswordModal(student)"
                                            class="rounded-lg p-1.5 text-slate-500 hover:bg-amber-50 hover:text-amber-700 transition"
                                            title="Ganti / Reset Password Murid"
                                        >
                                            <Key class="h-3.5 w-3.5" />
                                        </button>
                                        <button
                                            type="button"
                                            @click="openEditModal(student)"
                                            class="rounded-lg p-1.5 text-slate-500 hover:bg-indigo-50 hover:text-indigo-700 transition"
                                            title="Ubah Data Murid"
                                        >
                                            <Pencil class="h-3.5 w-3.5" />
                                        </button>
                                        <button
                                            type="button"
                                            @click="openDeleteModal(student)"
                                            class="rounded-lg p-1.5 text-slate-500 hover:bg-red-50 hover:text-red-700 transition"
                                            title="Hapus Murid"
                                        >
                                            <Trash2 class="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <!-- Empty State -->
                            <tr v-if="students.data.length === 0">
                                <td colspan="6" class="py-12 text-center">
                                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
                                        <Users class="h-7 w-7" />
                                    </div>
                                    <h3 class="mt-3 text-sm font-bold text-slate-800">Tidak ada data murid</h3>
                                    <p class="mt-1 text-xs text-slate-500 max-w-sm mx-auto">
                                        {{ searchInput || selectedClass ? 'Tidak ditemukan murid yang sesuai dengan filter pencarian.' : 'Belum ada data murid yang terdaftar. Mulai dengan mengimpor data atau menambah secara manual.' }}
                                    </p>
                                    <div class="mt-4 flex items-center justify-center gap-2">
                                        <button
                                            v-if="searchInput || selectedClass"
                                            type="button"
                                            @click="clearFilters"
                                            class="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                        >
                                            Reset Filter
                                        </button>
                                        <button
                                            v-else
                                            type="button"
                                            @click="openImportModal"
                                            class="rounded-xl bg-emerald-600 px-3.5 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700"
                                        >
                                            Impor Data Murid
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Footer -->
                <div
                    v-if="students.last_page > 1"
                    class="flex items-center justify-between border-t border-slate-200 bg-slate-50/60 px-6 py-3 text-xs"
                >
                    <div class="text-slate-500">
                        Halaman <span class="font-bold text-slate-800">{{ students.current_page }}</span> dari <span class="font-bold text-slate-800">{{ students.last_page }}</span>
                    </div>

                    <div class="flex items-center gap-1">
                        <template v-for="(link, idx) in students.links" :key="idx">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                :class="[
                                    'rounded-lg px-2.5 py-1 font-semibold transition',
                                    link.active
                                        ? 'bg-emerald-600 text-white'
                                        : 'text-slate-600 hover:bg-slate-200',
                                ]"
                                v-html="link.label"
                            />
                            <span
                                v-else
                                class="px-2.5 py-1 text-slate-400"
                                v-html="link.label"
                            />
                        </template>
                    </div>
                </div>
            </div>
        </main>

        <!-- ============================================== -->
        <!-- MODAL 1: IMPOR DATA MURID (CSV / PASTE)       -->
        <!-- ============================================== -->
        <div v-if="isImportModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
            <div class="w-full max-w-3xl rounded-3xl bg-white shadow-2xl overflow-hidden border border-slate-100 animate-in fade-in zoom-in-95 duration-200">
                <!-- Modal Header -->
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600">
                            <UploadCloud class="h-5 w-5" />
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-slate-900">Impor User Murid</h3>
                            <p class="text-xs text-slate-500">Upload CSV atau paste langsung dari Excel / Google Sheets</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        @click="isImportModalOpen = false"
                        class="rounded-full p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <div class="p-6 max-h-[80vh] overflow-y-auto space-y-5">
                    <!-- Format Explanation Banner -->
                    <div class="flex items-start gap-3 rounded-2xl bg-emerald-50/70 p-4 border border-emerald-100 text-xs text-emerald-950">
                        <Sparkles class="h-4 w-4 text-emerald-600 shrink-0 mt-0.5" />
                        <div class="space-y-1">
                            <p class="font-bold text-emerald-900">Ketentuan Format Kolom:</p>
                            <p class="text-emerald-800">
                                Gunakan kolom: <code class="font-mono font-bold bg-white/80 px-1 py-0.5 rounded border border-emerald-200">NIS</code>, <code class="font-mono font-bold bg-white/80 px-1 py-0.5 rounded border border-emerald-200">NAMA</code>, <code class="font-mono font-bold bg-white/80 px-1 py-0.5 rounded border border-emerald-200">KELAS</code>.
                            </p>
                            <p class="text-emerald-700">
                                💡 Password default akun murid otomatis disetel dari <strong>6 angka terakhir dari NIS</strong>. Murid dapat login langsung menggunakan NIS & password tersebut.
                            </p>
                        </div>
                    </div>

                    <!-- Input Method Tabs -->
                    <div class="flex rounded-xl bg-slate-100 p-1">
                        <button
                            type="button"
                            @click="importTab = 'paste'"
                            :class="[
                                'flex-1 rounded-lg py-2 text-xs font-bold transition flex items-center justify-center gap-2',
                                importTab === 'paste'
                                    ? 'bg-white text-slate-900 shadow-sm'
                                    : 'text-slate-600 hover:text-slate-900',
                            ]"
                        >
                            <ClipboardPaste class="h-3.5 w-3.5" />
                            <span>Copy-Paste dari Excel / Sheets</span>
                        </button>
                        <button
                            type="button"
                            @click="importTab = 'file'"
                            :class="[
                                'flex-1 rounded-lg py-2 text-xs font-bold transition flex items-center justify-center gap-2',
                                importTab === 'file'
                                    ? 'bg-white text-slate-900 shadow-sm'
                                    : 'text-slate-600 hover:text-slate-900',
                            ]"
                        >
                            <FileSpreadsheet class="h-3.5 w-3.5" />
                            <span>Upload File CSV</span>
                        </button>
                    </div>

                    <!-- Tab 1: Paste Text -->
                    <div v-if="importTab === 'paste'" class="space-y-2">
                        <label class="block text-xs font-bold text-slate-700">
                            Tempel Data Murid (baris baru per murid):
                        </label>
                        <textarea
                            v-model="importText"
                            rows="7"
                            placeholder="Contoh format (bisa langsung copy tabel Excel):
202401001	Ahmad Fauzi	X IPA 1
202401002	Siti Nurhaliza	X IPA 1
202401003	Budi Santoso	X IPS 2"
                            class="w-full rounded-2xl border border-slate-200 bg-slate-50/50 p-3 font-mono text-xs text-slate-800 placeholder:text-slate-400 focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                        ></textarea>
                        <div class="flex justify-between items-center text-[11px] text-slate-500">
                            <span>Mendukung pemisah TAB (Excel), koma (,), atau titik koma (;).</span>
                            <button
                                type="button"
                                @click="previewImport"
                                :disabled="isParsing || !importText.trim()"
                                class="inline-flex items-center gap-1.5 rounded-xl bg-slate-900 px-3 py-1.5 font-bold text-white transition hover:bg-slate-800 disabled:opacity-50"
                            >
                                <Loader2 v-if="isParsing" class="h-3 w-3 animate-spin" />
                                <RefreshCw v-else class="h-3 w-3" />
                                <span>Pratinjau Data</span>
                            </button>
                        </div>
                    </div>

                    <!-- Tab 2: Upload File -->
                    <div v-if="importTab === 'file'" class="space-y-3">
                        <label
                            class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50/50 p-8 text-center cursor-pointer hover:border-emerald-500 hover:bg-emerald-50/20 transition"
                        >
                            <FileSpreadsheet class="h-10 w-10 text-slate-400" />
                            <p class="mt-3 text-xs font-bold text-slate-800">
                                {{ importFile ? importFile.name : 'Pilih atau seret file CSV ke sini' }}
                            </p>
                            <p class="mt-1 text-[11px] text-slate-500">Mendukung format .csv atau .txt (Maks 5MB)</p>
                            <input
                                type="file"
                                accept=".csv,.txt"
                                @change="handleFileChange"
                                class="hidden"
                            />
                        </label>

                        <div class="flex justify-between items-center text-xs">
                            <a
                                :href="route('students.template')"
                                download="template_impor_murid.csv"
                                class="text-emerald-600 hover:underline inline-flex items-center gap-1 font-semibold"
                            >
                                <Download class="h-3 w-3" />
                                Download Contoh Format CSV
                            </a>
                            <button
                                v-if="importFile"
                                type="button"
                                @click="previewImport"
                                :disabled="isParsing"
                                class="inline-flex items-center gap-1.5 rounded-xl bg-slate-900 px-3 py-1.5 font-bold text-white transition hover:bg-slate-800 disabled:opacity-50"
                            >
                                <Loader2 v-if="isParsing" class="h-3 w-3 animate-spin" />
                                <span>Pratinjau File</span>
                            </button>
                        </div>
                    </div>

                    <!-- Error Messages during Parsing -->
                    <div v-if="parseErrors.length > 0" class="rounded-2xl bg-amber-50 p-4 border border-amber-200 text-xs">
                        <div class="flex items-center gap-2 font-bold text-amber-900 mb-2">
                            <AlertCircle class="h-4 w-4 text-amber-600" />
                            <span>Terdapat {{ parseErrors.length }} baris yang perlu diperhatikan:</span>
                        </div>
                        <ul class="list-disc pl-5 space-y-1 text-amber-800">
                            <li v-for="(err, idx) in parseErrors.slice(0, 5)" :key="idx">
                                Baris {{ err.row }}: {{ err.message }}
                            </li>
                            <li v-if="parseErrors.length > 5" class="text-slate-500 font-semibold">
                                ...dan {{ parseErrors.length - 5 }} baris bermasalah lainnya.
                            </li>
                        </ul>
                    </div>

                    <!-- Preview Section -->
                    <div v-if="hasParsed" class="space-y-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="flex h-5 w-5 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 text-xs font-bold">
                                    ✓
                                </span>
                                <span class="text-xs font-bold text-slate-800">
                                    Hasil Pratinjau: {{ previewRows.length }} murid siap diimpor
                                </span>
                            </div>
                            <span class="text-[11px] text-slate-500">
                                Password otomatis terhitung 6 angka terakhir NIS
                            </span>
                        </div>

                        <!-- Preview Table -->
                        <div class="max-h-56 overflow-y-auto rounded-xl border border-slate-200 bg-white">
                            <table class="w-full text-left text-[11px]">
                                <thead class="sticky top-0 bg-slate-50 font-bold uppercase text-slate-500 border-b border-slate-200">
                                    <tr>
                                        <th class="py-2 pl-3 pr-2">NIS</th>
                                        <th class="px-2 py-2">Nama</th>
                                        <th class="px-2 py-2">Kelas</th>
                                        <th class="px-2 py-2">Password Default</th>
                                        <th class="py-2 pl-2 pr-3 text-right">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-slate-700 font-medium">
                                    <tr v-for="(row, idx) in previewRows" :key="idx" class="hover:bg-slate-50">
                                        <td class="py-2 pl-3 pr-2 font-mono font-bold text-slate-900">{{ row.nis }}</td>
                                        <td class="px-2 py-2">{{ row.name }}</td>
                                        <td class="px-2 py-2">
                                            <span class="rounded bg-indigo-50 px-1.5 py-0.5 text-[10px] font-bold text-indigo-700">
                                                {{ row.kelas || '-' }}
                                            </span>
                                        </td>
                                        <td class="px-2 py-2">
                                            <code class="rounded bg-amber-50 px-1.5 py-0.5 font-mono text-amber-800 font-bold border border-amber-200/60">
                                                {{ row.default_password }}
                                            </code>
                                        </td>
                                        <td class="py-2 pl-2 pr-3 text-right">
                                            <span
                                                v-if="row.is_update"
                                                class="rounded bg-blue-50 px-1.5 py-0.5 text-[10px] font-bold text-blue-700"
                                            >
                                                Update Data
                                            </span>
                                            <span
                                                v-else
                                                class="rounded bg-emerald-50 px-1.5 py-0.5 text-[10px] font-bold text-emerald-700"
                                            >
                                                Murid Baru
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="flex items-center justify-end gap-3 border-t border-slate-100 bg-slate-50 px-6 py-4">
                    <button
                        type="button"
                        @click="isImportModalOpen = false"
                        class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        @click="executeImport"
                        :disabled="isSubmittingImport || previewRows.length === 0"
                        class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-emerald-700 disabled:opacity-50"
                    >
                        <Loader2 v-if="isSubmittingImport" class="h-3.5 w-3.5 animate-spin" />
                        <Check v-else class="h-3.5 w-3.5" />
                        <span>Simpan {{ previewRows.length }} Murid ke Database</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ============================================== -->
        <!-- MODAL 2: TAMBAH / EDIT MURID SATUAN           -->
        <!-- ============================================== -->
        <div v-if="isFormModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
            <div class="w-full max-w-md rounded-3xl bg-white shadow-2xl overflow-hidden border border-slate-100 animate-in fade-in zoom-in-95 duration-200">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <h3 class="text-base font-bold text-slate-900">
                        {{ isEditing ? 'Ubah Data Murid' : 'Tambah Murid Baru' }}
                    </h3>
                    <button
                        type="button"
                        @click="isFormModalOpen = false"
                        class="rounded-full p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <form @submit.prevent="submitStudent" class="p-6 space-y-4">
                    <!-- NIS -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">
                            NIS (Nomor Induk Siswa) <span class="text-red-500">*</span>
                        </label>
                        <input
                            v-model="studentForm.nis"
                            type="text"
                            required
                            placeholder="Contoh: 2024010123"
                            class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2 text-xs font-medium text-slate-800 placeholder:text-slate-400 focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 font-mono"
                        />
                        <div v-if="studentForm.errors.nis" class="mt-1 text-[11px] font-medium text-red-600">
                            {{ studentForm.errors.nis }}
                        </div>
                    </div>

                    <!-- Password default preview -->
                    <div v-if="studentForm.nis.trim()" class="rounded-xl bg-amber-50/80 p-3 border border-amber-200/70 text-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-amber-900 font-semibold">Password default:</span>
                            <code class="rounded bg-white px-2 py-0.5 font-mono font-bold text-amber-800 border border-amber-200">
                                {{ calculatedDefaultPassword }}
                            </code>
                        </div>
                        <p class="mt-1 text-[10px] text-amber-700">
                            (Otomatis 6 digit angka terakhir dari NIS)
                        </p>
                    </div>

                    <!-- Nama Lengkap -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">
                            Nama Lengkap Murid <span class="text-red-500">*</span>
                        </label>
                        <input
                            v-model="studentForm.name"
                            type="text"
                            required
                            placeholder="Contoh: Ahmad Fauzi Pratama"
                            class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2 text-xs font-medium text-slate-800 placeholder:text-slate-400 focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                        />
                        <div v-if="studentForm.errors.name" class="mt-1 text-[11px] font-medium text-red-600">
                            {{ studentForm.errors.name }}
                        </div>
                    </div>

                    <!-- Kelas -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">
                            Kelas / Rombel
                        </label>
                        <input
                            v-model="studentForm.kelas"
                            type="text"
                            placeholder="Contoh: X IPA 1, VII-B, XII RPL 2"
                            class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2 text-xs font-medium text-slate-800 placeholder:text-slate-400 focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                        />
                        <div v-if="studentForm.errors.kelas" class="mt-1 text-[11px] font-medium text-red-600">
                            {{ studentForm.errors.kelas }}
                        </div>
                    </div>

                    <!-- Email (Opsional) -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">
                            Email Murid (Opsional)
                        </label>
                        <input
                            v-model="studentForm.email"
                            type="email"
                            placeholder="Biarkan kosong jika murid tidak memiliki email"
                            class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2 text-xs font-medium text-slate-800 placeholder:text-slate-400 focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                        />
                        <p class="mt-1 text-[10px] text-slate-400">
                            Murid cukup menggunakan NIS dan password 6 angka NIS untuk masuk.
                        </p>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                        <button
                            type="button"
                            @click="isFormModalOpen = false"
                            class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="studentForm.processing"
                            class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-emerald-700 disabled:opacity-50"
                        >
                            <Loader2 v-if="studentForm.processing" class="h-3.5 w-3.5 animate-spin" />
                            <span>{{ isEditing ? 'Simpan Perubahan' : 'Tambah Murid' }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ============================================== -->
        <!-- MODAL 3: KELOLA / GANTI PASSWORD MURID         -->
        <!-- ============================================== -->
        <div v-if="isPasswordModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
            <div class="w-full max-w-md rounded-3xl bg-white shadow-2xl overflow-hidden border border-slate-100 animate-in fade-in zoom-in-95 duration-200">
                <!-- Modal Header -->
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-amber-50 text-amber-600">
                            <Key class="h-5 w-5" />
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-slate-900">Ganti Password Murid</h3>
                            <p class="text-xs text-slate-500 truncate max-w-[240px]">
                                {{ studentToManagePassword?.name }} • NIS: {{ studentToManagePassword?.nis }}
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        @click="isPasswordModalOpen = false"
                        class="rounded-full p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <div class="p-6 space-y-4">
                    <!-- Password Input Form -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">
                            Password Baru Murid
                        </label>
                        <div class="relative">
                            <input
                                v-model="newPasswordInput"
                                :type="showPasswordText ? 'text' : 'password'"
                                placeholder="Masukkan password baru..."
                                class="w-full rounded-xl border border-slate-200 bg-slate-50/50 py-2.5 pl-3 pr-10 text-xs font-mono font-medium text-slate-800 placeholder:text-slate-400 focus:border-amber-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-amber-500/20"
                            />
                            <button
                                type="button"
                                @click="showPasswordText = !showPasswordText"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                                title="Lihat/Sembunyikan Password"
                            >
                                <EyeOff v-if="showPasswordText" class="h-4 w-4" />
                                <Eye v-else class="h-4 w-4" />
                            </button>
                        </div>
                        <p class="mt-1 text-[10px] text-slate-400">
                            Minimal 6 karakter. Murid langsung dapat login menggunakan password ini.
                        </p>
                    </div>

                    <!-- Quick Fill Buttons -->
                    <div class="rounded-2xl bg-slate-50 p-3.5 border border-slate-200/70 space-y-2">
                        <p class="text-[11px] font-bold text-slate-700">Pilihan Cepat:</p>
                        <div class="flex flex-wrap gap-2">
                            <button
                                type="button"
                                @click="setPasswordToNisDefault"
                                class="inline-flex items-center gap-1.5 rounded-xl border border-amber-200 bg-amber-50/80 px-2.5 py-1.5 text-xs font-semibold text-amber-800 hover:bg-amber-100 transition"
                            >
                                <Key class="h-3 w-3 text-amber-600" />
                                <span>Pakai 6 Digit NIS ({{ studentToManagePassword?.default_password }})</span>
                            </button>
                            <button
                                type="button"
                                @click="generateRandomPassword"
                                class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition"
                            >
                                <Shuffle class="h-3 w-3 text-indigo-600" />
                                <span>Acak 6 Angka</span>
                            </button>
                        </div>
                    </div>

                    <!-- Direct Reset Option Banner -->
                    <div class="flex items-center justify-between rounded-xl bg-amber-50/50 p-3 border border-amber-200/50 text-xs">
                        <span class="text-amber-900 font-medium">Atau reset langsung:</span>
                        <button
                            type="button"
                            @click="directResetToNisDefault"
                            :disabled="isUpdatingPassword"
                            class="inline-flex items-center gap-1 rounded-lg bg-amber-600 px-2.5 py-1 text-[11px] font-bold text-white hover:bg-amber-700 transition disabled:opacity-50"
                        >
                            <span>Kembalikan ke 6 Digit NIS</span>
                        </button>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="flex items-center justify-end gap-3 border-t border-slate-100 bg-slate-50 px-6 py-4">
                    <button
                        type="button"
                        @click="isPasswordModalOpen = false"
                        class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        @click="submitCustomPassword"
                        :disabled="isUpdatingPassword || newPasswordInput.length < 6"
                        class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-5 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-slate-800 disabled:opacity-50"
                    >
                        <Loader2 v-if="isUpdatingPassword" class="h-3.5 w-3.5 animate-spin" />
                        <Check v-else class="h-3.5 w-3.5" />
                        <span>Simpan Password Baru</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ============================================== -->
        <!-- MODAL 4: HAPUS MURID CONFIRMATION             -->
        <!-- ============================================== -->
        <div v-if="isDeleteModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
            <div class="w-full max-w-sm rounded-3xl bg-white p-6 shadow-2xl border border-slate-100 animate-in fade-in zoom-in-95 duration-200">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-red-50 text-red-600 mx-auto">
                    <Trash2 class="h-6 w-6" />
                </div>
                <h3 class="mt-4 text-center text-base font-bold text-slate-900">Hapus Data Murid?</h3>
                <p class="mt-2 text-center text-xs text-slate-500">
                    Apakah Anda yakin ingin menghapus murid <strong>{{ studentToDelete?.name }}</strong> (NIS: {{ studentToDelete?.nis }})? Tindakan ini tidak dapat dibatalkan.
                </p>
                <div class="mt-5 flex items-center gap-2">
                    <button
                        type="button"
                        @click="isDeleteModalOpen = false"
                        class="flex-1 rounded-xl border border-slate-200 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        @click="confirmDelete"
                        :disabled="isDeleting"
                        class="flex-1 rounded-xl bg-red-600 py-2 text-xs font-bold text-white hover:bg-red-700 disabled:opacity-50"
                    >
                        {{ isDeleting ? 'Menghapus...' : 'Ya, Hapus' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
