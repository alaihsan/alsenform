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
    Shield,
    ShieldAlert,
    ShieldCheck,
    RefreshCw,
    Loader2,
    FileText,
    Eye,
    EyeOff,
    Shuffle,
    MoreVertical,
    UserCheck,
    BookOpen,
} from 'lucide-vue-next';
import { useToast } from '@/composables/useToast';
import axios from 'axios';

interface UserItem {
    id: number;
    name: string;
    email: string | null;
    nis: string | null;
    kelas: string | null;
    role: 'admin' | 'guru' | 'siswa';
    is_admin: boolean;
    default_password: string;
    created_at: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedUsers {
    data: UserItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: PaginationLink[];
}

interface Props {
    users: PaginatedUsers;
    filters: {
        search: string;
        role: string;
        kelas: string;
    };
    classes: string[];
    stats: {
        total_users: number;
        total_admins: number;
        total_teachers: number;
        total_students: number;
        total_classes: number;
    };
}

const props = defineProps<Props>();
const page = usePage();
const currentUser = computed(() => (page.props.auth as any)?.user);

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
const selectedRole = ref(props.filters.role || 'all');
const selectedClass = ref(props.filters.kelas || '');
let searchTimer: ReturnType<typeof setTimeout> | null = null;

function applyFilters(): void {
    router.get(
        route('users.index'),
        {
            search: searchInput.value || undefined,
            role: selectedRole.value !== 'all' ? selectedRole.value : undefined,
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

function filterByRole(role: string): void {
    selectedRole.value = role;
    applyFilters();
}

function clearFilters(): void {
    searchInput.value = '';
    selectedRole.value = 'all';
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

// --- MODAL: Add User ---
const isAddModalOpen = ref(false);
const addForm = useForm({
    role: 'guru' as 'admin' | 'guru' | 'siswa',
    name: '',
    email: '',
    password: '',
    nis: '',
    kelas: '',
});

function openAddModal(): void {
    addForm.reset();
    addForm.role = 'guru';
    addForm.clearErrors();
    generateAddPassword();
    isAddModalOpen.value = true;
}

function generateAddPassword(): void {
    const randomPass = Math.floor(100000 + Math.random() * 900000).toString();
    addForm.password = randomPass;
}

const calculatedStudentPassword = computed(() => {
    const raw = addForm.nis.trim();
    const digits = raw.replace(/\D/g, '');
    if (digits.length >= 6) return digits.slice(-6);
    if (raw.length >= 6) return raw.slice(-6);
    return raw || '-';
});

function submitAddUser(): void {
    addForm.post(route('users.store'), {
        onSuccess: () => {
            isAddModalOpen.value = false;
            showToast(`Akun ${addForm.name} berhasil ditambahkan!`);
        },
    });
}

// --- MODAL: Edit User ---
const isEditModalOpen = ref(false);
const editingUserId = ref<number | null>(null);
const editForm = useForm({
    role: 'guru' as 'admin' | 'guru' | 'siswa',
    name: '',
    email: '',
    nis: '',
    kelas: '',
});

function openEditModal(item: UserItem): void {
    editingUserId.value = item.id;
    editForm.role = item.role;
    editForm.name = item.name;
    editForm.email = item.email || '';
    editForm.nis = item.nis || '';
    editForm.kelas = item.kelas || '';
    editForm.clearErrors();
    isEditModalOpen.value = true;
}

function submitEditUser(): void {
    if (!editingUserId.value) return;
    editForm.put(route('users.update', editingUserId.value), {
        onSuccess: () => {
            isEditModalOpen.value = false;
            showToast('Data pengguna berhasil diperbarui!');
        },
    });
}

// --- MODAL: Change Role Directly ---
const isRoleModalOpen = ref(false);
const targetRoleUser = ref<UserItem | null>(null);
const selectedNewRole = ref<'admin' | 'guru' | 'siswa'>('guru');
const isChangingRole = ref(false);

function openRoleModal(item: UserItem): void {
    targetRoleUser.value = item;
    selectedNewRole.value = item.role;
    isRoleModalOpen.value = true;
}

function submitChangeRole(): void {
    if (!targetRoleUser.value) return;
    isChangingRole.value = true;
    router.patch(
        route('users.update-role', targetRoleUser.value.id),
        { role: selectedNewRole.value },
        {
            onFinish: () => {
                isChangingRole.value = false;
                isRoleModalOpen.value = false;
                showToast(`Peran ${targetRoleUser.value?.name} berhasil diubah ke ${selectedNewRole.value.toUpperCase()}!`);
                targetRoleUser.value = null;
            },
        }
    );
}

// --- MODAL: Delete User ---
const isDeleteModalOpen = ref(false);
const userToDelete = ref<UserItem | null>(null);
const isDeleting = ref(false);

function openDeleteModal(item: UserItem): void {
    userToDelete.value = item;
    isDeleteModalOpen.value = true;
}

function confirmDelete(): void {
    if (!userToDelete.value) return;
    isDeleting.value = true;
    router.delete(route('users.destroy', userToDelete.value.id), {
        onFinish: () => {
            isDeleting.value = false;
            isDeleteModalOpen.value = false;
            userToDelete.value = null;
            showToast('Pengguna berhasil dihapus.');
        },
    });
}

// --- MODAL: Change / Reset Password ---
const isPasswordModalOpen = ref(false);
const userToManagePassword = ref<UserItem | null>(null);
const newPasswordInput = ref('');
const showPasswordText = ref(true);
const isUpdatingPassword = ref(false);

function defaultPasswordForNis(nis?: string | null): string {
    if (!nis) return '';
    const clean = String(nis).trim();
    return clean.length >= 6 ? clean.slice(-6) : clean.padStart(6, '0');
}

function openPasswordModal(item: UserItem): void {
    userToManagePassword.value = item;
    newPasswordInput.value = '';
    showPasswordText.value = true;
    isPasswordModalOpen.value = true;
}

function generateRandomPassword(): void {
    const random6 = Math.floor(100000 + Math.random() * 900000).toString();
    newPasswordInput.value = random6;
}

function setPasswordToNisDefault(): void {
    if (!userToManagePassword.value) return;
    newPasswordInput.value = defaultPasswordForNis(userToManagePassword.value.nis);
}

function submitCustomPassword(): void {
    if (!userToManagePassword.value) return;
    if (newPasswordInput.value.length < 6) {
        showToast('Password minimal 6 karakter.');
        return;
    }

    isUpdatingPassword.value = true;
    router.post(
        route('users.change-password', userToManagePassword.value.id),
        { password: newPasswordInput.value },
        {
            onFinish: () => {
                isUpdatingPassword.value = false;
                isPasswordModalOpen.value = false;
                showToast(`Password pengguna '${userToManagePassword.value?.name}' berhasil diperbarui!`);
                userToManagePassword.value = null;
            },
        }
    );
}

function directResetToNisDefault(): void {
    if (!userToManagePassword.value) return;
    isUpdatingPassword.value = true;
    router.post(
        route('users.reset-password', userToManagePassword.value.id),
        {},
        {
            onFinish: () => {
                isUpdatingPassword.value = false;
                isPasswordModalOpen.value = false;
                showToast(`Password murid berhasil direset ke password default 6 digit NIS.`);
                userToManagePassword.value = null;
            },
        }
    );
}

// --- MODAL: Import Students ---
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
const parseStats = ref({
    totalRows: 0,
    validCount: 0,
    errorCount: 0,
});
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

function handleFileUpload(e: Event): void {
    const input = e.target as HTMLInputElement;
    if (input.files && input.files.length > 0) {
        importFile.value = input.files[0];
    }
}

async function runPreview(): Promise<void> {
    isParsing.value = true;
    parseErrors.value = [];
    previewRows.value = [];
    hasParsed.value = false;

    const formData = new FormData();
    formData.append('dry_run', '1');

    if (importTab.value === 'file' && importFile.value) {
        formData.append('file', importFile.value);
    } else if (importTab.value === 'paste' && importText.value.trim()) {
        formData.append('text', importText.value.trim());
    } else {
        isParsing.value = false;
        showToast('Silakan pilih file CSV atau tempel teks data murid.');
        return;
    }

    try {
        const response = await axios.post(route('users.import-students'), formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        if (response.data.success) {
            previewRows.value = response.data.preview || [];
            parseErrors.value = response.data.errors || [];
            parseStats.value = {
                totalRows: response.data.total_rows || 0,
                validCount: response.data.valid_count || 0,
                errorCount: response.data.error_count || 0,
            };
            hasParsed.value = true;
        }
    } catch (err: any) {
        showToast(err.response?.data?.message || 'Gagal memproses data murid.');
    } finally {
        isParsing.value = false;
    }
}

async function executeImport(): Promise<void> {
    if (previewRows.value.length === 0) return;
    isSubmittingImport.value = true;

    try {
        const response = await axios.post(route('users.import-students'), {
            students: previewRows.value,
        });

        if (response.data.success) {
            isImportModalOpen.value = false;
            showToast(response.data.message || 'Impor murid berhasil diselesaikan!');
            router.reload({ preserveScroll: true });
        }
    } catch (err: any) {
        showToast(err.response?.data?.message || 'Terjadi kesalahan saat mengimpor murid.');
    } finally {
        isSubmittingImport.value = false;
    }
}
</script>

<template>
    <Head title="Pengaturan User - AlsenForm" />

    <div class="min-h-screen bg-slate-50 text-slate-900 selection:bg-emerald-500 selection:text-white pb-16">
        <!-- Toast Notification -->
        <Transition
            enter-active-class="transform ease-out duration-300 transition"
            enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
            enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
            leave-active-class="transition ease-in duration-200"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="toastMessage"
                class="fixed bottom-5 right-5 z-50 flex items-center gap-3 rounded-2xl bg-slate-900 px-5 py-3.5 text-sm font-semibold text-white shadow-2xl ring-1 ring-white/10"
            >
                <div class="flex h-7 w-7 items-center justify-center rounded-full bg-emerald-500 text-white">
                    <Check class="h-4 w-4 stroke-[3]" />
                </div>
                <span>{{ toastMessage }}</span>
            </div>
        </Transition>

        <!-- Navbar Header -->
        <header class="sticky top-0 z-30 border-b border-slate-200/80 bg-white/95 backdrop-blur-md">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3 sm:px-6 lg:px-8">
                <div class="flex items-center gap-3">
                    <Link
                        :href="route('dashboard')"
                        class="flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900 shadow-sm"
                        title="Kembali ke Dashboard"
                    >
                        <ArrowLeft class="h-5 w-5" />
                    </Link>
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="text-xl font-extrabold tracking-tight text-slate-900 sm:text-2xl">
                                Pengaturan User
                            </h1>
                            <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-bold text-emerald-800">
                                Superadmin
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 hidden sm:block">
                            Kelola peran Admin, Guru, dan Murid, ubah password, dan impor data murid
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2 sm:gap-3">
                    <Link
                        :href="route('cohorts.index')"
                        class="inline-flex items-center gap-2 rounded-xl border border-indigo-600/30 bg-indigo-50 px-3.5 py-2 text-xs font-bold text-indigo-700 transition hover:bg-indigo-100 hover:border-indigo-600/50 shadow-sm"
                        title="Kelola Kelompok Belajar (Cohort)"
                    >
                        <BookOpen class="h-4 w-4 text-indigo-600" />
                        <span>Cohort</span>
                    </Link>
                    <button
                        type="button"
                        @click="openImportModal"
                        class="inline-flex items-center gap-2 rounded-xl border border-emerald-600/30 bg-emerald-50 px-3.5 py-2 text-xs font-bold text-emerald-700 transition hover:bg-emerald-100 hover:border-emerald-600/50 shadow-sm"
                    >
                        <UploadCloud class="h-4 w-4 text-emerald-600" />
                        <span class="hidden sm:inline">Impor Murid</span>
                        <span class="sm:hidden">Impor</span>
                    </button>
                    <button
                        type="button"
                        @click="openAddModal"
                        class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-xs font-bold text-white shadow-sm shadow-emerald-600/30 transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2"
                    >
                        <UserPlus class="h-4 w-4" />
                        <span>Tambah Pengguna</span>
                    </button>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-4 pt-6 sm:px-6 lg:px-8">
            <!-- Stat Cards -->
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 sm:gap-4 mb-6">
                <!-- Total Users -->
                <div
                    @click="filterByRole('all')"
                    class="cursor-pointer rounded-2xl border bg-white p-4 shadow-sm transition hover:shadow-md hover:border-slate-300"
                    :class="selectedRole === 'all' ? 'border-emerald-500 ring-2 ring-emerald-500/20' : 'border-slate-200/80'"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total User</span>
                        <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-slate-100 text-slate-600">
                            <Users class="h-4 w-4" />
                        </div>
                    </div>
                    <div class="mt-2 text-2xl font-black text-slate-900">{{ stats.total_users }}</div>
                    <p class="mt-0.5 text-xs text-slate-400">Seluruh akun terdaftar</p>
                </div>

                <!-- Admin -->
                <div
                    @click="filterByRole('admin')"
                    class="cursor-pointer rounded-2xl border bg-white p-4 shadow-sm transition hover:shadow-md hover:border-indigo-300"
                    :class="selectedRole === 'admin' ? 'border-indigo-500 ring-2 ring-indigo-500/20' : 'border-slate-200/80'"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-indigo-600 uppercase tracking-wider">Admin</span>
                        <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                            <ShieldCheck class="h-4 w-4" />
                        </div>
                    </div>
                    <div class="mt-2 text-2xl font-black text-indigo-950">{{ stats.total_admins }}</div>
                    <p class="mt-0.5 text-xs text-indigo-600/70">Akses penuh sistem</p>
                </div>

                <!-- Guru -->
                <div
                    @click="filterByRole('guru')"
                    class="cursor-pointer rounded-2xl border bg-white p-4 shadow-sm transition hover:shadow-md hover:border-blue-300"
                    :class="selectedRole === 'guru' ? 'border-blue-500 ring-2 ring-blue-500/20' : 'border-slate-200/80'"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-blue-600 uppercase tracking-wider">Guru</span>
                        <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                            <GraduationCap class="h-4 w-4" />
                        </div>
                    </div>
                    <div class="mt-2 text-2xl font-black text-blue-950">{{ stats.total_teachers }}</div>
                    <p class="mt-0.5 text-xs text-blue-600/70">Pembuat kuis / form</p>
                </div>

                <!-- Murid -->
                <div
                    @click="filterByRole('siswa')"
                    class="cursor-pointer rounded-2xl border bg-white p-4 shadow-sm transition hover:shadow-md hover:border-emerald-300"
                    :class="selectedRole === 'siswa' ? 'border-emerald-500 ring-2 ring-emerald-500/20' : 'border-slate-200/80'"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-emerald-600 uppercase tracking-wider">Murid</span>
                        <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                            <School class="h-4 w-4" />
                        </div>
                    </div>
                    <div class="mt-2 text-2xl font-black text-emerald-950">{{ stats.total_students }}</div>
                    <p class="mt-0.5 text-xs text-emerald-600/70">{{ stats.total_classes }} Kelas terdata</p>
                </div>
            </div>

            <!-- Main Panel Card -->
            <div class="rounded-3xl border border-slate-200/80 bg-white shadow-sm overflow-hidden">
                <!-- Toolbar: Role Tabs + Search + Filters -->
                <div class="border-b border-slate-100 p-4 sm:p-5">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <!-- Role Filter Tabs -->
                        <div class="flex flex-wrap items-center gap-1.5 rounded-2xl bg-slate-100 p-1">
                            <button
                                type="button"
                                @click="filterByRole('all')"
                                class="rounded-xl px-3 py-1.5 text-xs font-bold transition"
                                :class="selectedRole === 'all' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900'"
                            >
                                Semua User ({{ stats.total_users }})
                            </button>
                            <button
                                type="button"
                                @click="filterByRole('admin')"
                                class="flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-bold transition"
                                :class="selectedRole === 'admin' ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-600 hover:text-slate-900'"
                            >
                                <span class="h-2 w-2 rounded-full bg-indigo-500"></span>
                                Admin ({{ stats.total_admins }})
                            </button>
                            <button
                                type="button"
                                @click="filterByRole('guru')"
                                class="flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-bold transition"
                                :class="selectedRole === 'guru' ? 'bg-white text-blue-700 shadow-sm' : 'text-slate-600 hover:text-slate-900'"
                            >
                                <span class="h-2 w-2 rounded-full bg-blue-500"></span>
                                Guru ({{ stats.total_teachers }})
                            </button>
                            <button
                                type="button"
                                @click="filterByRole('siswa')"
                                class="flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-bold transition"
                                :class="selectedRole === 'siswa' ? 'bg-white text-emerald-700 shadow-sm' : 'text-slate-600 hover:text-slate-900'"
                            >
                                <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                                Murid ({{ stats.total_students }})
                            </button>
                        </div>

                        <!-- Search & Kelas Dropdown -->
                        <div class="flex flex-wrap items-center gap-2">
                            <!-- Search -->
                            <div class="relative min-w-[220px] flex-1 sm:flex-initial">
                                <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                <input
                                    v-model="searchInput"
                                    type="text"
                                    @input="handleSearch"
                                    placeholder="Cari nama, email, NIS..."
                                    class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2 pl-9 pr-8 text-xs font-medium text-slate-800 placeholder-slate-400 transition focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-1 focus:ring-emerald-500"
                                />
                                <button
                                    v-if="searchInput"
                                    type="button"
                                    @click="searchInput = ''; applyFilters()"
                                    class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                                >
                                    <X class="h-3.5 w-3.5" />
                                </button>
                            </div>

                            <!-- Filter Kelas -->
                            <div class="relative">
                                <select
                                    v-model="selectedClass"
                                    @change="applyFilters"
                                    class="appearance-none rounded-xl border border-slate-200 bg-slate-50 py-2 pl-3 pr-8 text-xs font-medium text-slate-700 transition focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-1 focus:ring-emerald-500 cursor-pointer"
                                >
                                    <option value="">Semua Kelas</option>
                                    <option v-for="cls in classes" :key="cls" :value="cls">
                                        Kelas {{ cls }}
                                    </option>
                                </select>
                                <Filter class="pointer-events-none absolute right-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                            </div>

                            <!-- Clear filter button -->
                            <button
                                v-if="searchInput || selectedRole !== 'all' || selectedClass"
                                type="button"
                                @click="clearFilters"
                                class="inline-flex items-center gap-1 rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 transition"
                                title="Reset filter"
                            >
                                <X class="h-3.5 w-3.5" />
                                <span>Reset</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-600">
                        <thead class="border-b border-slate-100 bg-slate-50/75 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                            <tr>
                                <th scope="col" class="px-5 py-3.5">Pengguna & Akun</th>
                                <th scope="col" class="px-4 py-3.5">Peran / Hak Akses</th>
                                <th scope="col" class="px-4 py-3.5">NIS & Kelas</th>
                                <th scope="col" class="px-4 py-3.5">Info Password</th>
                                <th scope="col" class="px-5 py-3.5 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="u in users.data"
                                :key="u.id"
                                class="transition hover:bg-slate-50/80"
                            >
                                <!-- Name & Email -->
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl font-black text-sm uppercase shadow-xs"
                                            :class="{
                                                'bg-indigo-100 text-indigo-700': u.role === 'admin',
                                                'bg-blue-100 text-blue-700': u.role === 'guru',
                                                'bg-emerald-100 text-emerald-700': u.role === 'siswa',
                                            }"
                                        >
                                            {{ u.name.charAt(0) }}
                                        </div>
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2">
                                                <span class="font-bold text-slate-900 truncate">{{ u.name }}</span>
                                                <span
                                                    v-if="u.id === currentUser?.id"
                                                    class="rounded-md bg-slate-200 px-1.5 py-0.5 text-[10px] font-bold text-slate-700"
                                                >
                                                    Anda
                                                </span>
                                            </div>
                                            <div class="text-[11px] text-slate-500 truncate">
                                                {{ u.email || 'Tanpa email (Login via NIS)' }}
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Role & Quick Switch Button -->
                                <td class="px-4 py-3.5">
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-bold shadow-xs"
                                            :class="{
                                                'bg-indigo-100 text-indigo-800 border border-indigo-200': u.role === 'admin',
                                                'bg-blue-100 text-blue-800 border border-blue-200': u.role === 'guru',
                                                'bg-emerald-100 text-emerald-800 border border-emerald-200': u.role === 'siswa',
                                            }"
                                        >
                                            <ShieldCheck v-if="u.role === 'admin'" class="h-3.5 w-3.5 text-indigo-600" />
                                            <GraduationCap v-else-if="u.role === 'guru'" class="h-3.5 w-3.5 text-blue-600" />
                                            <School v-else class="h-3.5 w-3.5 text-emerald-600" />
                                            <span>
                                                {{ u.role === 'admin' ? 'Admin' : (u.role === 'guru' ? 'Guru' : 'Murid') }}
                                            </span>
                                        </span>

                                        <!-- Quick Change Role Button -->
                                        <button
                                            type="button"
                                            @click="openRoleModal(u)"
                                            class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition"
                                            title="Ubah peran pengguna ini"
                                        >
                                            <Shuffle class="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                </td>

                                <!-- NIS & Kelas -->
                                <td class="px-4 py-3.5">
                                    <div v-if="u.nis || u.kelas">
                                        <div v-if="u.nis" class="font-mono font-semibold text-slate-800">
                                            NIS: {{ u.nis }}
                                        </div>
                                        <div v-if="u.kelas" class="inline-flex mt-0.5 items-center rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-bold text-slate-700">
                                            Kelas {{ u.kelas }}
                                        </div>
                                    </div>
                                    <span v-else class="text-slate-400 italic">
                                        -
                                    </span>
                                </td>

                                <!-- Password Info -->
                                <td class="px-4 py-3.5">
                                    <div class="flex items-center gap-1.5 text-slate-400 text-xs font-mono">
                                        <span>••••••</span>
                                    </div>
                                </td>

                                <!-- Actions -->
                                <td class="px-5 py-3.5 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <!-- Ubah Password -->
                                        <button
                                            type="button"
                                            @click="openPasswordModal(u)"
                                            class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2 py-1 text-[11px] font-semibold text-slate-700 hover:border-amber-400 hover:bg-amber-50 hover:text-amber-800 transition shadow-2xs"
                                            title="Ubah atau reset password"
                                        >
                                            <Key class="h-3 w-3 text-amber-500" />
                                            <span>Password</span>
                                        </button>

                                        <!-- Edit -->
                                        <button
                                            type="button"
                                            @click="openEditModal(u)"
                                            class="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 hover:text-slate-800 transition"
                                            title="Edit data pengguna"
                                        >
                                            <Pencil class="h-3.5 w-3.5" />
                                        </button>

                                        <!-- Delete -->
                                        <button
                                            type="button"
                                            @click="openDeleteModal(u)"
                                            :disabled="u.id === currentUser?.id"
                                            class="rounded-lg p-1.5 transition"
                                            :class="u.id === currentUser?.id ? 'text-slate-300 cursor-not-allowed' : 'text-slate-400 hover:bg-red-50 hover:text-red-600'"
                                            :title="u.id === currentUser?.id ? 'Tidak bisa menghapus akun sendiri' : 'Hapus pengguna'"
                                        >
                                            <Trash2 class="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <!-- Empty state -->
                            <tr v-if="users.data.length === 0">
                                <td colspan="5" class="py-12 text-center text-slate-500">
                                    <Users class="mx-auto h-10 w-10 text-slate-300 mb-2" />
                                    <p class="font-bold text-slate-700">Tidak ada pengguna ditemukan</p>
                                    <p class="text-xs text-slate-400 mt-1">Coba sesuaikan kata kunci pencarian atau filter peran.</p>
                                    <button
                                        type="button"
                                        @click="clearFilters"
                                        class="mt-3 inline-flex items-center gap-1.5 rounded-xl border border-slate-200 px-3.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition"
                                    >
                                        <RefreshCw class="h-3.5 w-3.5" />
                                        <span>Reset Semua Filter</span>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Footer -->
                <div v-if="users.total > users.per_page" class="border-t border-slate-100 px-5 py-3.5">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 text-xs text-slate-500">
                        <div>
                            Menampilkan <span class="font-bold text-slate-800">{{ users.from }}</span> -
                            <span class="font-bold text-slate-800">{{ users.to }}</span> dari
                            <span class="font-bold text-slate-800">{{ users.total }}</span> pengguna
                        </div>
                        <div class="flex items-center gap-1">
                            <Link
                                v-for="(link, i) in users.links"
                                :key="i"
                                :href="link.url || '#'"
                                :class="[
                                    'rounded-lg px-2.5 py-1.5 text-xs font-semibold transition',
                                    link.active
                                        ? 'bg-emerald-600 text-white'
                                        : link.url
                                        ? 'text-slate-600 hover:bg-slate-100'
                                        : 'text-slate-300 cursor-not-allowed',
                                ]"
                                v-html="link.label"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- ============================================== -->
        <!-- MODAL: Tambah Pengguna Baru (Admin/Guru/Murid) -->
        <!-- ============================================== -->
        <div
            v-if="isAddModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs overflow-y-auto"
        >
            <div class="relative w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl ring-1 ring-slate-900/10 my-8">
                <!-- Header -->
                <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900">Tambah Pengguna Baru</h3>
                        <p class="text-xs text-slate-500">Tentukan peran: Administrator, Guru, atau Murid</p>
                    </div>
                    <button
                        type="button"
                        @click="isAddModalOpen = false"
                        class="rounded-xl p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <form @submit.prevent="submitAddUser" class="mt-5 space-y-4">
                    <!-- Role Selection Radio Cards -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-2">Pilih Peran Akun</label>
                        <div class="grid grid-cols-3 gap-2">
                            <!-- Admin Option -->
                            <label
                                class="flex flex-col items-center justify-center p-3 rounded-2xl border cursor-pointer transition text-center"
                                :class="addForm.role === 'admin' ? 'border-indigo-600 bg-indigo-50/60 text-indigo-900 ring-2 ring-indigo-500/20' : 'border-slate-200 hover:bg-slate-50 text-slate-700'"
                            >
                                <input type="radio" v-model="addForm.role" value="admin" class="sr-only" />
                                <ShieldCheck class="h-5 w-5 mb-1" :class="addForm.role === 'admin' ? 'text-indigo-600' : 'text-slate-400'" />
                                <span class="text-xs font-bold">Admin</span>
                                <span class="text-[10px] text-slate-500 mt-0.5">Kelola Sistem</span>
                            </label>

                            <!-- Guru Option -->
                            <label
                                class="flex flex-col items-center justify-center p-3 rounded-2xl border cursor-pointer transition text-center"
                                :class="addForm.role === 'guru' ? 'border-blue-600 bg-blue-50/60 text-blue-900 ring-2 ring-blue-500/20' : 'border-slate-200 hover:bg-slate-50 text-slate-700'"
                            >
                                <input type="radio" v-model="addForm.role" value="guru" class="sr-only" />
                                <GraduationCap class="h-5 w-5 mb-1" :class="addForm.role === 'guru' ? 'text-blue-600' : 'text-slate-400'" />
                                <span class="text-xs font-bold">Guru</span>
                                <span class="text-[10px] text-slate-500 mt-0.5">Buat Form</span>
                            </label>

                            <!-- Murid Option -->
                            <label
                                class="flex flex-col items-center justify-center p-3 rounded-2xl border cursor-pointer transition text-center"
                                :class="addForm.role === 'siswa' ? 'border-emerald-600 bg-emerald-50/60 text-emerald-900 ring-2 ring-emerald-500/20' : 'border-slate-200 hover:bg-slate-50 text-slate-700'"
                            >
                                <input type="radio" v-model="addForm.role" value="siswa" class="sr-only" />
                                <School class="h-5 w-5 mb-1" :class="addForm.role === 'siswa' ? 'text-emerald-600' : 'text-slate-400'" />
                                <span class="text-xs font-bold">Murid</span>
                                <span class="text-[10px] text-slate-500 mt-0.5">Akses via NIS</span>
                            </label>
                        </div>
                    </div>

                    <!-- Nama Lengkap -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Nama Lengkap</label>
                        <input
                            v-model="addForm.name"
                            type="text"
                            required
                            placeholder="Contoh: Budi Santoso"
                            class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-xs text-slate-800 transition focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                        />
                        <p v-if="addForm.errors.name" class="mt-1 text-xs text-red-600">{{ addForm.errors.name }}</p>
                    </div>

                    <!-- Conditional Fields for Admin & Guru -->
                    <template v-if="addForm.role === 'admin' || addForm.role === 'guru'">
                        <!-- Email -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Alamat Email</label>
                            <input
                                v-model="addForm.email"
                                type="email"
                                required
                                placeholder="email@sekolah.sch.id"
                                class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-xs text-slate-800 transition focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                            />
                            <p v-if="addForm.errors.email" class="mt-1 text-xs text-red-600">{{ addForm.errors.email }}</p>
                        </div>

                        <!-- Password -->
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="text-xs font-bold text-slate-700">Password</label>
                                <button
                                    type="button"
                                    @click="generateAddPassword"
                                    class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-600 hover:text-emerald-700"
                                >
                                    <Shuffle class="h-3 w-3" />
                                    <span>Acak Password</span>
                                </button>
                            </div>
                            <input
                                v-model="addForm.password"
                                type="text"
                                required
                                minlength="6"
                                placeholder="Minimal 6 karakter"
                                class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 font-mono text-xs text-slate-800 transition focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                            />
                            <p v-if="addForm.errors.password" class="mt-1 text-xs text-red-600">{{ addForm.errors.password }}</p>
                        </div>
                    </template>

                    <!-- Conditional Fields for Murid -->
                    <template v-else>
                        <!-- NIS -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Nomor Induk Siswa (NIS)</label>
                            <input
                                v-model="addForm.nis"
                                type="text"
                                required
                                placeholder="Contoh: 202401001"
                                class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-xs font-mono text-slate-800 transition focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                            />
                            <p v-if="addForm.errors.nis" class="mt-1 text-xs text-red-600">{{ addForm.errors.nis }}</p>

                            <!-- Password Default Info Box -->
                            <div class="mt-2 flex items-center justify-between rounded-xl bg-emerald-50 p-2.5 text-xs text-emerald-900 border border-emerald-200/60">
                                <div>
                                    <span class="font-bold">Password Default Murid:</span>
                                    <p class="text-[11px] text-emerald-700">Otomatis 6 digit NIS terakhir</p>
                                </div>
                                <div class="font-mono text-sm font-black bg-white px-2.5 py-1 rounded-lg border border-emerald-300 text-emerald-800">
                                    {{ calculatedStudentPassword }}
                                </div>
                            </div>
                        </div>

                        <!-- Kelas -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Kelas (Opsional)</label>
                            <input
                                v-model="addForm.kelas"
                                type="text"
                                placeholder="Contoh: Kelas 7A, Kelas 8B, Kelas 1, dll."
                                class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-xs text-slate-800 transition focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                            />
                        </div>

                        <!-- Email (Opsional for Murid) -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Email Murid (Opsional)</label>
                            <input
                                v-model="addForm.email"
                                type="email"
                                placeholder="murid@sekolah.sch.id (bisa dikosongkan)"
                                class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-xs text-slate-800 transition focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                            />
                        </div>
                    </template>

                    <!-- Submit Buttons -->
                    <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-4 mt-6">
                        <button
                            type="button"
                            @click="isAddModalOpen = false"
                            class="rounded-xl border border-slate-200 px-4 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-50 transition"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="addForm.processing"
                            class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 text-xs font-bold text-white shadow-sm shadow-emerald-600/30 hover:bg-emerald-700 transition disabled:opacity-50"
                        >
                            <Loader2 v-if="addForm.processing" class="h-4 w-4 animate-spin" />
                            <UserPlus v-else class="h-4 w-4" />
                            <span>Simpan Akun</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ============================================== -->
        <!-- MODAL: Edit Data Pengguna                      -->
        <!-- ============================================== -->
        <div
            v-if="isEditModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs overflow-y-auto"
        >
            <div class="relative w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl ring-1 ring-slate-900/10 my-8">
                <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900">Ubah Data Pengguna</h3>
                        <p class="text-xs text-slate-500">Perbarui profil atau peran pengguna</p>
                    </div>
                    <button
                        type="button"
                        @click="isEditModalOpen = false"
                        class="rounded-xl p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <form @submit.prevent="submitEditUser" class="mt-5 space-y-4">
                    <!-- Peran -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Peran</label>
                        <select
                            v-model="editForm.role"
                            class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-xs font-bold text-slate-800 transition focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                        >
                            <option value="admin">Administrator (Akses Penuh)</option>
                            <option value="guru">Guru (Pembuat Kuis/Form)</option>
                            <option value="siswa">Murid (Login via NIS)</option>
                        </select>
                    </div>

                    <!-- Nama -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Nama Lengkap</label>
                        <input
                            v-model="editForm.name"
                            type="text"
                            required
                            class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-xs text-slate-800 transition focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                        />
                        <p v-if="editForm.errors.name" class="mt-1 text-xs text-red-600">{{ editForm.errors.name }}</p>
                    </div>

                    <!-- Email -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Email</label>
                        <input
                            v-model="editForm.email"
                            type="email"
                            :required="editForm.role !== 'siswa'"
                            class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-xs text-slate-800 transition focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                        />
                        <p v-if="editForm.errors.email" class="mt-1 text-xs text-red-600">{{ editForm.errors.email }}</p>
                    </div>

                    <!-- NIS & Kelas (if role is siswa) -->
                    <template v-if="editForm.role === 'siswa'">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Nomor Induk Siswa (NIS)</label>
                            <input
                                v-model="editForm.nis"
                                type="text"
                                class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 font-mono text-xs text-slate-800 transition focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                            />
                            <p v-if="editForm.errors.nis" class="mt-1 text-xs text-red-600">{{ editForm.errors.nis }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Kelas</label>
                            <input
                                v-model="editForm.kelas"
                                type="text"
                                class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-xs text-slate-800 transition focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                            />
                        </div>
                    </template>

                    <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-4 mt-6">
                        <button
                            type="button"
                            @click="isEditModalOpen = false"
                            class="rounded-xl border border-slate-200 px-4 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-50 transition"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="editForm.processing"
                            class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 text-xs font-bold text-white shadow-sm shadow-emerald-600/30 hover:bg-emerald-700 transition disabled:opacity-50"
                        >
                            <Loader2 v-if="editForm.processing" class="h-4 w-4 animate-spin" />
                            <span>Perbarui Data</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ============================================== -->
        <!-- MODAL: Quick Change Role                       -->
        <!-- ============================================== -->
        <div
            v-if="isRoleModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs"
        >
            <div class="relative w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl ring-1 ring-slate-900/10">
                <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                            <Shuffle class="h-5 w-5" />
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-slate-900">Ubah Peran Pengguna</h3>
                            <p class="text-xs text-slate-500 truncate max-w-[240px]">{{ targetRoleUser?.name }}</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        @click="isRoleModalOpen = false"
                        class="rounded-xl p-1.5 text-slate-400 hover:bg-slate-100 transition"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <div class="mt-5 space-y-3">
                    <p class="text-xs text-slate-600">
                        Pilih peran baru untuk akun <strong>{{ targetRoleUser?.name }}</strong>:
                    </p>

                    <!-- Admin Option -->
                    <label
                        class="flex items-start gap-3 p-3.5 rounded-2xl border cursor-pointer transition"
                        :class="selectedNewRole === 'admin' ? 'border-indigo-600 bg-indigo-50/60 ring-2 ring-indigo-500/20' : 'border-slate-200 hover:bg-slate-50'"
                    >
                        <input type="radio" v-model="selectedNewRole" value="admin" class="sr-only" />
                        <ShieldCheck class="h-5 w-5 shrink-0 text-indigo-600 mt-0.5" />
                        <div>
                            <span class="block text-xs font-bold text-slate-900">Administrator</span>
                            <span class="text-[11px] text-slate-500">Memiliki akses penuh mengelola user, form, donasi, dan pengaturan.</span>
                        </div>
                    </label>

                    <!-- Guru Option -->
                    <label
                        class="flex items-start gap-3 p-3.5 rounded-2xl border cursor-pointer transition"
                        :class="selectedNewRole === 'guru' ? 'border-blue-600 bg-blue-50/60 ring-2 ring-blue-500/20' : 'border-slate-200 hover:bg-slate-50'"
                    >
                        <input type="radio" v-model="selectedNewRole" value="guru" class="sr-only" />
                        <GraduationCap class="h-5 w-5 shrink-0 text-blue-600 mt-0.5" />
                        <div>
                            <span class="block text-xs font-bold text-slate-900">Guru</span>
                            <span class="text-[11px] text-slate-500">Dapat membuat formulir kuis, mengoreksi jawaban, dan mengekspor nilai.</span>
                        </div>
                    </label>

                    <!-- Murid Option -->
                    <label
                        class="flex items-start gap-3 p-3.5 rounded-2xl border cursor-pointer transition"
                        :class="selectedNewRole === 'siswa' ? 'border-emerald-600 bg-emerald-50/60 ring-2 ring-emerald-500/20' : 'border-slate-200 hover:bg-slate-50'"
                    >
                        <input type="radio" v-model="selectedNewRole" value="siswa" class="sr-only" />
                        <School class="h-5 w-5 shrink-0 text-emerald-600 mt-0.5" />
                        <div>
                            <span class="block text-xs font-bold text-slate-900">Murid</span>
                            <span class="text-[11px] text-slate-500">Mengerjakan kuis yang dibagikan dan login dengan NIS.</span>
                        </div>
                    </label>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-4 mt-6">
                    <button
                        type="button"
                        @click="isRoleModalOpen = false"
                        class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50 transition"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        @click="submitChangeRole"
                        :disabled="isChangingRole"
                        class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-5 py-2 text-xs font-bold text-white shadow-sm hover:bg-indigo-700 transition disabled:opacity-50"
                    >
                        <Loader2 v-if="isChangingRole" class="h-4 w-4 animate-spin" />
                        <Check v-else class="h-4 w-4" />
                        <span>Terapkan Peran</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ============================================== -->
        <!-- MODAL: Ganti / Reset Password                  -->
        <!-- ============================================== -->
        <div
            v-if="isPasswordModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs"
        >
            <div class="relative w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl ring-1 ring-slate-900/10">
                <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                            <Key class="h-5 w-5" />
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-slate-900">Kelola Password</h3>
                            <p class="text-xs text-slate-500 truncate max-w-[240px]">{{ userToManagePassword?.name }}</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        @click="isPasswordModalOpen = false"
                        class="rounded-xl p-1.5 text-slate-400 hover:bg-slate-100 transition"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <div class="mt-5 space-y-4">
                    <!-- Quick reset for student if NIS available -->
                    <div
                        v-if="userToManagePassword?.role === 'siswa' && userToManagePassword?.nis"
                        class="rounded-2xl border border-emerald-200 bg-emerald-50/70 p-3.5 text-xs text-emerald-900"
                    >
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="font-bold">Password Default 6 Digit NIS:</span>
                                <p class="text-[11px] text-emerald-700">NIS: {{ userToManagePassword.nis }}</p>
                            </div>
                            <span class="font-mono text-sm font-black bg-white px-2 py-1 rounded-lg border border-emerald-300 text-emerald-800">
                                {{ defaultPasswordForNis(userToManagePassword.nis) }}
                            </span>
                        </div>
                        <button
                            type="button"
                            @click="directResetToNisDefault"
                            :disabled="isUpdatingPassword"
                            class="mt-2.5 w-full inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-3 py-2 text-xs font-bold text-white shadow-xs hover:bg-emerald-700 transition"
                        >
                            <RefreshCw class="h-3.5 w-3.5" :class="{ 'animate-spin': isUpdatingPassword }" />
                            <span>Reset ke Password Default NIS Sekarang</span>
                        </button>
                    </div>

                    <!-- Custom Password Form -->
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="text-xs font-bold text-slate-700">Password Baru</label>
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    @click="generateRandomPassword"
                                    class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-600 hover:text-emerald-700"
                                >
                                    <Shuffle class="h-3 w-3" />
                                    <span>Acak (6 Angka)</span>
                                </button>
                                <button
                                    v-if="userToManagePassword?.nis"
                                    type="button"
                                    @click="setPasswordToNisDefault"
                                    class="text-[11px] font-semibold text-slate-500 hover:text-slate-800"
                                >
                                    Pakai NIS
                                </button>
                            </div>
                        </div>

                        <div class="relative">
                            <input
                                v-model="newPasswordInput"
                                :type="showPasswordText ? 'text' : 'password'"
                                placeholder="Masukkan password baru (min. 6 karakter)"
                                class="w-full rounded-xl border border-slate-200 py-2.5 pl-3.5 pr-10 font-mono text-xs text-slate-800 transition focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                            />
                            <button
                                type="button"
                                @click="showPasswordText = !showPasswordText"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                            >
                                <EyeOff v-if="showPasswordText" class="h-4 w-4" />
                                <Eye v-else class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-4 mt-6">
                    <button
                        type="button"
                        @click="isPasswordModalOpen = false"
                        class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50 transition"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        @click="submitCustomPassword"
                        :disabled="isUpdatingPassword || newPasswordInput.length < 6"
                        class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-5 py-2 text-xs font-bold text-white shadow-sm hover:bg-slate-800 transition disabled:opacity-50"
                    >
                        <Loader2 v-if="isUpdatingPassword" class="h-4 w-4 animate-spin" />
                        <Check v-else class="h-4 w-4" />
                        <span>Simpan Password</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ============================================== -->
        <!-- MODAL: Hapus Pengguna                          -->
        <!-- ============================================== -->
        <div
            v-if="isDeleteModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs"
        >
            <div class="relative w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl ring-1 ring-slate-900/10">
                <div class="flex items-center gap-3 text-red-600 mb-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-red-50 text-red-600">
                        <AlertCircle class="h-5 w-5" />
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Hapus Pengguna</h3>
                        <p class="text-xs text-slate-500">Tindakan ini tidak dapat dibatalkan</p>
                    </div>
                </div>

                <p class="text-xs text-slate-600 leading-relaxed">
                    Apakah Anda yakin ingin menghapus akun <strong>{{ userToDelete?.name }}</strong>
                    <span v-if="userToDelete?.nis"> (NIS: {{ userToDelete?.nis }})</span>? Semua data terkait pengguna ini akan dihapus dari sistem.
                </p>

                <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-4 mt-6">
                    <button
                        type="button"
                        @click="isDeleteModalOpen = false"
                        class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50 transition"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        @click="confirmDelete"
                        :disabled="isDeleting"
                        class="inline-flex items-center gap-2 rounded-xl bg-red-600 px-5 py-2 text-xs font-bold text-white shadow-sm hover:bg-red-700 transition disabled:opacity-50"
                    >
                        <Loader2 v-if="isDeleting" class="h-4 w-4 animate-spin" />
                        <Trash2 v-else class="h-4 w-4" />
                        <span>Ya, Hapus Akun</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ============================================== -->
        <!-- MODAL: Impor Murid (CSV / Paste)               -->
        <!-- ============================================== -->
        <div
            v-if="isImportModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs overflow-y-auto"
        >
            <div class="relative w-full max-w-2xl rounded-3xl bg-white p-6 shadow-2xl ring-1 ring-slate-900/10 my-8">
                <!-- Header -->
                <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600">
                            <UploadCloud class="h-5 w-5" />
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-slate-900">Impor Data Murid</h3>
                            <p class="text-xs text-slate-500">Unggah CSV atau tempel teks: NIS, NAMA, KELAS</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        @click="isImportModalOpen = false"
                        class="rounded-xl p-1.5 text-slate-400 hover:bg-slate-100 transition"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <!-- Guidance Info & Template Download -->
                <div class="mt-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 rounded-2xl bg-emerald-50/70 p-3.5 border border-emerald-100 text-xs text-emerald-900">
                    <div>
                        <div class="font-bold flex items-center gap-1.5">
                            <Sparkles class="h-3.5 w-3.5 text-emerald-600" />
                            <span>Password Default Otomatis: 6 Angka NIS Terakhir</span>
                        </div>
                        <p class="text-[11px] text-emerald-700 mt-0.5">
                            Kolom: <strong>NIS, NAMA, KELAS</strong> (Urutan kolom fleksibel).
                        </p>
                    </div>
                    <a
                        :href="route('users.student-template')"
                        download="template_impor_murid.csv"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-emerald-300 bg-white px-3 py-1.5 text-xs font-bold text-emerald-700 shadow-2xs hover:bg-emerald-50 transition shrink-0"
                    >
                        <Download class="h-3.5 w-3.5" />
                        <span>Unduh Template CSV</span>
                    </a>
                </div>

                <!-- Tabs: Paste Text vs Upload File -->
                <div class="mt-4 flex items-center gap-2 border-b border-slate-100 pb-2">
                    <button
                        type="button"
                        @click="importTab = 'paste'"
                        class="inline-flex items-center gap-2 rounded-xl px-3 py-1.5 text-xs font-bold transition"
                        :class="importTab === 'paste' ? 'bg-slate-900 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'"
                    >
                        <ClipboardPaste class="h-3.5 w-3.5" />
                        <span>Tempel / Ketik Teks</span>
                    </button>
                    <button
                        type="button"
                        @click="importTab = 'file'"
                        class="inline-flex items-center gap-2 rounded-xl px-3 py-1.5 text-xs font-bold transition"
                        :class="importTab === 'file' ? 'bg-slate-900 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'"
                    >
                        <FileSpreadsheet class="h-3.5 w-3.5" />
                        <span>Unggah File CSV</span>
                    </button>
                </div>

                <!-- Body Tab: Paste -->
                <div v-if="importTab === 'paste'" class="mt-4">
                    <div class="flex items-center justify-between mb-1.5 text-xs">
                        <span class="font-bold text-slate-700">Data Murid (Salin langsung dari Excel / Word / Catatan):</span>
                    </div>
                    <textarea
                        v-model="importText"
                        rows="6"
                        placeholder="Contoh:&#10;NIS,NAMA,KELAS&#10;202401001, Ahmad Fauzi, Kelas 7A&#10;202401002, Siti Nurhaliza, Kelas 7A&#10;202401003, Budi Santoso, Kelas 8B"
                        class="w-full rounded-2xl border border-slate-200 p-3 font-mono text-xs text-slate-800 transition focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                    ></textarea>
                </div>

                <!-- Body Tab: File -->
                <div v-else class="mt-4">
                    <label class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-300 p-6 text-center hover:border-emerald-500 hover:bg-emerald-50/30 transition cursor-pointer">
                        <UploadCloud class="h-10 w-10 text-slate-400 mb-2" />
                        <span class="text-xs font-bold text-slate-700">Klik untuk pilih file CSV</span>
                        <span class="text-[11px] text-slate-400 mt-0.5">Maksimal 5MB (.csv, .txt)</span>
                        <input
                            type="file"
                            accept=".csv,.txt"
                            @change="handleFileUpload"
                            class="sr-only"
                        />
                    </label>
                    <p v-if="importFile" class="mt-2 text-xs font-semibold text-emerald-700 flex items-center gap-1.5">
                        <CheckCircle2 class="h-4 w-4 text-emerald-600" />
                        <span>File terpilih: {{ importFile.name }} ({{ (importFile.size / 1024).toFixed(1) }} KB)</span>
                    </p>
                </div>

                <!-- Parsing / Verification Section -->
                <div class="mt-4">
                    <button
                        type="button"
                        @click="runPreview"
                        :disabled="isParsing || (importTab === 'paste' && !importText.trim()) || (importTab === 'file' && !importFile)"
                        class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-slate-800 transition disabled:opacity-50"
                    >
                        <Loader2 v-if="isParsing" class="h-3.5 w-3.5 animate-spin" />
                        <Eye v-else class="h-3.5 w-3.5" />
                        <span>Pratinjau & Verifikasi Data</span>
                    </button>
                </div>

                <!-- Preview Table & Results -->
                <div v-if="hasParsed" class="mt-4 border-t border-slate-100 pt-4">
                    <!-- Stats summary -->
                    <div class="flex items-center justify-between text-xs mb-3">
                        <div class="flex items-center gap-3">
                            <span class="font-bold text-slate-800">Hasil Verifikasi:</span>
                            <span class="rounded-md bg-emerald-100 px-2 py-0.5 text-[11px] font-bold text-emerald-800">
                                {{ parseStats.validCount }} Valid
                            </span>
                            <span v-if="parseStats.errorCount > 0" class="rounded-md bg-red-100 px-2 py-0.5 text-[11px] font-bold text-red-800">
                                {{ parseStats.errorCount }} Gagal
                            </span>
                        </div>
                    </div>

                    <!-- Error list -->
                    <div v-if="parseErrors.length > 0" class="mb-3 max-h-28 overflow-y-auto rounded-xl bg-red-50 p-2.5 text-xs text-red-700 border border-red-200">
                        <div class="font-bold mb-1 flex items-center gap-1">
                            <AlertCircle class="h-3.5 w-3.5" />
                            <span>Daftar Baris Tidak Valid:</span>
                        </div>
                        <ul class="space-y-1 text-[11px]">
                            <li v-for="(err, idx) in parseErrors" :key="idx">
                                Baris {{ err.row }}: {{ err.message }} ({{ err.line }})
                            </li>
                        </ul>
                    </div>

                    <!-- Preview Table -->
                    <div v-if="previewRows.length > 0" class="max-h-48 overflow-y-auto rounded-xl border border-slate-200">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-50 text-[11px] font-bold text-slate-500 uppercase sticky top-0">
                                <tr>
                                    <th class="px-3 py-2">NIS</th>
                                    <th class="px-3 py-2">Nama</th>
                                    <th class="px-3 py-2">Kelas</th>
                                    <th class="px-3 py-2">Default Password</th>
                                    <th class="px-3 py-2">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="(row, idx) in previewRows" :key="idx" class="hover:bg-slate-50">
                                    <td class="px-3 py-2 font-mono font-bold text-slate-800">{{ row.nis }}</td>
                                    <td class="px-3 py-2 font-semibold text-slate-800">{{ row.name }}</td>
                                    <td class="px-3 py-2">{{ row.kelas || '-' }}</td>
                                    <td class="px-3 py-2 font-mono font-bold text-emerald-700">{{ defaultPasswordForNis(row.nis) }}</td>
                                    <td class="px-3 py-2">
                                        <span
                                            class="rounded px-1.5 py-0.5 text-[10px] font-bold"
                                            :class="row.is_update ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'"
                                        >
                                            {{ row.is_update ? 'Update' : 'Baru' }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Footer Actions -->
                <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-4 mt-6">
                    <button
                        type="button"
                        @click="isImportModalOpen = false"
                        class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50 transition"
                    >
                        Tutup
                    </button>
                    <button
                        type="button"
                        @click="executeImport"
                        :disabled="isSubmittingImport || previewRows.length === 0"
                        class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2 text-xs font-bold text-white shadow-sm shadow-emerald-600/30 hover:bg-emerald-700 transition disabled:opacity-50"
                    >
                        <Loader2 v-if="isSubmittingImport" class="h-4 w-4 animate-spin" />
                        <Check v-else class="h-4 w-4" />
                        <span>Impor {{ previewRows.length }} Murid Sekarang</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
