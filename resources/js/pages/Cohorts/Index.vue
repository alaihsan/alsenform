<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import {
    Users,
    UserPlus,
    UploadCloud,
    Search,
    Filter,
    Pencil,
    Trash2,
    Check,
    ArrowLeft,
    Sparkles,
    Shield,
    ShieldCheck,
    RefreshCw,
    Loader2,
    FileText,
    BookOpen,
    School,
    GraduationCap,
    X,
    Plus,
    ChevronRight,
    ClipboardList,
} from 'lucide-vue-next';
import { useToast } from '@/composables/useToast';

interface CohortItem {
    id: number;
    name: string;
    code: string | null;
    description: string | null;
    users_count: number;
    quiz_forms_count: number;
    creator?: {
        id: number;
        name: string;
        email: string | null;
    } | null;
    created_at: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedCohorts {
    data: CohortItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: PaginationLink[];
}

interface Props {
    cohorts: PaginatedCohorts;
    filters: {
        search: string;
    };
    stats: {
        total_cohorts: number;
        total_members: number;
        total_classes: number;
    };
    availableClasses: string[];
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

// Search & Filter
const searchInput = ref(props.filters.search || '');
let searchTimer: ReturnType<typeof setTimeout> | null = null;

function applySearch(): void {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get(
            route('cohorts.index'),
            { search: searchInput.value || undefined },
            { preserveState: true, replace: true }
        );
    }, 350);
}

function clearSearch(): void {
    searchInput.value = '';
    router.get(route('cohorts.index'), {}, { preserveState: true, replace: true });
}

// --- MODAL: Create Cohort ---
const isCreateModalOpen = ref(false);
const createForm = useForm({
    name: '',
    code: '',
    description: '',
    source_class: '',
});

function openCreateModal(): void {
    createForm.reset();
    createForm.clearErrors();
    isCreateModalOpen.value = true;
}

function submitCreateCohort(): void {
    createForm.post(route('cohorts.store'), {
        onSuccess: () => {
            isCreateModalOpen.value = false;
            showToast(`Cohort '${createForm.name}' berhasil dibuat!`);
        },
    });
}

// --- MODAL: Edit Cohort ---
const isEditModalOpen = ref(false);
const editingCohortId = ref<number | null>(null);
const editForm = useForm({
    name: '',
    code: '',
    description: '',
});

function openEditModal(c: CohortItem): void {
    editingCohortId.value = c.id;
    editForm.name = c.name;
    editForm.code = c.code || '';
    editForm.description = c.description || '';
    editForm.clearErrors();
    isEditModalOpen.value = true;
}

function submitEditCohort(): void {
    if (!editingCohortId.value) return;
    editForm.put(route('cohorts.update', editingCohortId.value), {
        onSuccess: () => {
            isEditModalOpen.value = false;
            showToast('Data Cohort berhasil diperbarui!');
        },
    });
}

// --- MODAL: Delete Cohort ---
const isDeleteModalOpen = ref(false);
const cohortToDelete = ref<CohortItem | null>(null);
const isDeleting = ref(false);

function openDeleteModal(c: CohortItem): void {
    cohortToDelete.value = c;
    isDeleteModalOpen.value = true;
}

function confirmDelete(): void {
    if (!cohortToDelete.value) return;
    isDeleting.value = true;
    router.delete(route('cohorts.destroy', cohortToDelete.value.id), {
        onFinish: () => {
            isDeleting.value = false;
            isDeleteModalOpen.value = false;
            cohortToDelete.value = null;
            showToast('Cohort berhasil dihapus.');
        },
    });
}

// --- Sync from classes ---
const isSyncing = ref(false);
function syncFromClasses(): void {
    isSyncing.value = true;
    router.post(
        route('cohorts.sync-from-classes'),
        {},
        {
            onFinish: () => {
                isSyncing.value = false;
            },
        }
    );
}
</script>

<template>
    <Head title="Kelompok Belajar (Cohort) - AlsenForm" />

    <div class="min-h-screen bg-slate-50 text-slate-900 selection:bg-indigo-500 selection:text-white pb-16">
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
                                Kelompok Belajar (Cohort)
                            </h1>
                            <span class="rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-bold text-indigo-800">
                                Moodle Style
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 hidden sm:block">
                            Kelola kelompok siswa/rombel untuk memudahkan penugasan kuis secara massal
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2 sm:gap-3">
                    <Link
                        v-if="currentUser?.is_admin"
                        :href="route('users.index')"
                        class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-bold text-slate-700 transition hover:bg-slate-50 shadow-sm"
                    >
                        <Users class="h-4 w-4 text-emerald-600" />
                        <span class="hidden sm:inline">Pengaturan User</span>
                        <span class="sm:hidden">User</span>
                    </Link>
                    <button
                        type="button"
                        @click="syncFromClasses"
                        :disabled="isSyncing || availableClasses.length === 0"
                        class="inline-flex items-center gap-2 rounded-xl border border-indigo-600/30 bg-indigo-50 px-3.5 py-2 text-xs font-bold text-indigo-700 transition hover:bg-indigo-100 hover:border-indigo-600/50 shadow-sm disabled:opacity-50"
                        title="Buat cohort otomatis dari data kelas siswa di database"
                    >
                        <RefreshCw class="h-4 w-4 text-indigo-600" :class="{ 'animate-spin': isSyncing }" />
                        <span class="hidden sm:inline">Sinkronkan dari Kelas</span>
                        <span class="sm:hidden">Sinkron</span>
                    </button>
                    <button
                        type="button"
                        @click="openCreateModal"
                        class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white shadow-sm shadow-indigo-600/30 transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    >
                        <Plus class="h-4 w-4" />
                        <span>Buat Cohort</span>
                    </button>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-4 pt-6 sm:px-6 lg:px-8">
            <!-- Stat Cards -->
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3 sm:gap-4 mb-6">
                <!-- Total Cohort -->
                <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Cohort</span>
                        <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                            <BookOpen class="h-4 w-4" />
                        </div>
                    </div>
                    <div class="mt-2 text-2xl font-black text-slate-900">{{ stats.total_cohorts }}</div>
                    <p class="mt-0.5 text-xs text-slate-400">Kelompok belajar terdaftar</p>
                </div>

                <!-- Total Siswa Terkelompok -->
                <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-emerald-600 uppercase tracking-wider">Anggota Siswa</span>
                        <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                            <Users class="h-4 w-4" />
                        </div>
                    </div>
                    <div class="mt-2 text-2xl font-black text-emerald-950">{{ stats.total_members }}</div>
                    <p class="mt-0.5 text-xs text-emerald-600/70">Siswa tergabung dalam cohort</p>
                </div>

                <!-- Total Kelas -->
                <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-blue-600 uppercase tracking-wider">Kelas Terdata</span>
                        <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                            <School class="h-4 w-4" />
                        </div>
                    </div>
                    <div class="mt-2 text-2xl font-black text-blue-950">{{ stats.total_classes }}</div>
                    <p class="mt-0.5 text-xs text-blue-600/70">Kelas siap dijadikan cohort</p>
                </div>
            </div>

            <!-- Toolbar & Search -->
            <div class="mb-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="relative min-w-[280px] flex-1 sm:max-w-md">
                    <Search class="absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input
                        v-model="searchInput"
                        type="text"
                        @input="applySearch"
                        placeholder="Cari nama atau kode cohort..."
                        class="w-full rounded-2xl border border-slate-200 bg-white py-2.5 pl-10 pr-9 text-xs font-medium text-slate-800 placeholder-slate-400 transition focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 shadow-xs"
                    />
                    <button
                        v-if="searchInput"
                        type="button"
                        @click="clearSearch"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>
            </div>

            <!-- Cohort Grid Cards -->
            <div v-if="cohorts.data.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div
                    v-for="c in cohorts.data"
                    :key="c.id"
                    class="group relative flex flex-col justify-between rounded-3xl border border-slate-200/80 bg-white p-5 shadow-xs transition hover:shadow-md hover:border-indigo-200"
                >
                    <!-- Top row: Code & Actions -->
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <span class="rounded-lg bg-indigo-50 px-2.5 py-1 font-mono text-[11px] font-bold text-indigo-700 border border-indigo-200/60">
                                {{ c.code || 'COHORT' }}
                            </span>
                            <div class="flex items-center gap-1">
                                <button
                                    type="button"
                                    @click="openEditModal(c)"
                                    class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition"
                                    title="Edit Cohort"
                                >
                                    <Pencil class="h-3.5 w-3.5" />
                                </button>
                                <button
                                    type="button"
                                    @click="openDeleteModal(c)"
                                    class="rounded-lg p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600 transition"
                                    title="Hapus Cohort"
                                >
                                    <Trash2 class="h-3.5 w-3.5" />
                                </button>
                            </div>
                        </div>

                        <!-- Title & Description -->
                        <h3 class="text-base font-bold text-slate-900 group-hover:text-indigo-600 transition truncate">
                            {{ c.name }}
                        </h3>
                        <p class="mt-1 text-xs text-slate-500 line-clamp-2 min-h-[32px]">
                            {{ c.description || 'Tidak ada keterangan tambahan.' }}
                        </p>
                    </div>

                    <!-- Bottom row: Stats & Link -->
                    <div class="mt-5 border-t border-slate-100 pt-4">
                        <div class="flex items-center justify-between text-xs text-slate-600 mb-3">
                            <div class="flex items-center gap-1.5">
                                <Users class="h-4 w-4 text-emerald-600" />
                                <span class="font-bold text-slate-900">{{ c.users_count }}</span> Murid
                            </div>
                            <div class="flex items-center gap-1.5">
                                <ClipboardList class="h-4 w-4 text-indigo-600" />
                                <span class="font-bold text-slate-900">{{ c.quiz_forms_count }}</span> Kuis Tertaut
                            </div>
                        </div>

                        <Link
                            :href="route('cohorts.show', c.id)"
                            class="flex w-full items-center justify-center gap-1.5 rounded-xl bg-slate-50 px-4 py-2 text-xs font-bold text-slate-700 transition group-hover:bg-indigo-600 group-hover:text-white"
                        >
                            <span>Kelola Anggota & Detail</span>
                            <ChevronRight class="h-4 w-4" />
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else class="rounded-3xl border border-dashed border-slate-300 bg-white p-12 text-center my-6">
                <BookOpen class="mx-auto h-12 w-12 text-slate-300 mb-3" />
                <h3 class="text-base font-bold text-slate-800">Belum ada Cohort</h3>
                <p class="text-xs text-slate-500 mt-1 max-w-md mx-auto">
                    Buat kelompok belajar secara manual atau klik tombol di bawah untuk otomatis mengelompokkan siswa berdasarkan kelas yang sudah ada.
                </p>
                <div class="mt-5 flex items-center justify-center gap-3">
                    <button
                        v-if="availableClasses.length > 0"
                        type="button"
                        @click="syncFromClasses"
                        class="inline-flex items-center gap-2 rounded-xl border border-indigo-300 bg-indigo-50 px-4 py-2 text-xs font-bold text-indigo-700 hover:bg-indigo-100 transition"
                    >
                        <RefreshCw class="h-4 w-4 text-indigo-600" />
                        <span>Sinkronkan Otomatis dari Kelas ({{ availableClasses.length }})</span>
                    </button>
                    <button
                        type="button"
                        @click="openCreateModal"
                        class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white hover:bg-indigo-700 transition shadow-sm"
                    >
                        <Plus class="h-4 w-4" />
                        <span>Buat Cohort Baru</span>
                    </button>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="cohorts.total > cohorts.per_page" class="mt-6 flex justify-between items-center text-xs text-slate-500">
                <div>
                    Menampilkan <span class="font-bold text-slate-800">{{ cohorts.from }}</span> -
                    <span class="font-bold text-slate-800">{{ cohorts.to }}</span> dari
                    <span class="font-bold text-slate-800">{{ cohorts.total }}</span> Cohort
                </div>
                <div class="flex items-center gap-1">
                    <Link
                        v-for="(link, i) in cohorts.links"
                        :key="i"
                        :href="link.url || '#'"
                        :class="[
                            'rounded-lg px-2.5 py-1.5 text-xs font-semibold transition',
                            link.active
                                ? 'bg-indigo-600 text-white'
                                : link.url
                                ? 'text-slate-600 hover:bg-slate-200'
                                : 'text-slate-300 cursor-not-allowed',
                        ]"
                        v-html="link.label"
                    />
                </div>
            </div>
        </main>

        <!-- ============================================== -->
        <!-- MODAL: Buat Cohort Baru                        -->
        <!-- ============================================== -->
        <div
            v-if="isCreateModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs"
        >
            <div class="relative w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl ring-1 ring-slate-900/10">
                <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Buat Cohort Baru</h3>
                        <p class="text-xs text-slate-500">Kelompok siswa / rombel untuk kuis</p>
                    </div>
                    <button
                        type="button"
                        @click="isCreateModalOpen = false"
                        class="rounded-xl p-1.5 text-slate-400 hover:bg-slate-100 transition"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <form @submit.prevent="submitCreateCohort" class="mt-5 space-y-4">
                    <!-- Nama Cohort -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Nama Cohort</label>
                        <input
                            v-model="createForm.name"
                            type="text"
                            required
                            placeholder="Contoh: Kelas 7A, Angkatan 2024, dll."
                            class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-xs text-slate-800 transition focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        />
                        <p v-if="createForm.errors.name" class="mt-1 text-xs text-red-600">{{ createForm.errors.name }}</p>
                    </div>

                    <!-- Kode Cohort -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Kode Cohort (Opsional / Otomatis)</label>
                        <input
                            v-model="createForm.code"
                            type="text"
                            placeholder="Contoh: KLS-7A, ANGK-2024"
                            class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 font-mono text-xs text-slate-800 uppercase transition focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        />
                        <p v-if="createForm.errors.code" class="mt-1 text-xs text-red-600">{{ createForm.errors.code }}</p>
                    </div>

                    <!-- Isi Otomatis dari Kelas -->
                    <div v-if="availableClasses.length > 0">
                        <label class="block text-xs font-bold text-slate-700 mb-1">
                            Masukkan Murid dari Kelas (Opsional)
                        </label>
                        <select
                            v-model="createForm.source_class"
                            class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-xs text-slate-800 transition focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        >
                            <option value="">Pilih kelas (kosongkan jika buat kelompok manual)</option>
                            <option v-for="cls in availableClasses" :key="cls" :value="cls">
                                Masukkan semua murid Kelas {{ cls }}
                            </option>
                        </select>
                        <p class="text-[11px] text-slate-400 mt-1">
                            Jika dipilih, semua murid yang memiliki kelas ini otomatis dimasukkan ke dalam cohort.
                        </p>
                    </div>

                    <!-- Deskripsi -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Keterangan (Opsional)</label>
                        <textarea
                            v-model="createForm.description"
                            rows="2"
                            placeholder="Catatan mengenai kelompok ini..."
                            class="w-full rounded-xl border border-slate-200 p-3 text-xs text-slate-800 transition focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        ></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-4 mt-6">
                        <button
                            type="button"
                            @click="isCreateModalOpen = false"
                            class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50 transition"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="createForm.processing"
                            class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-5 py-2 text-xs font-bold text-white shadow-sm hover:bg-indigo-700 transition disabled:opacity-50"
                        >
                            <Loader2 v-if="createForm.processing" class="h-4 w-4 animate-spin" />
                            <Plus v-else class="h-4 w-4" />
                            <span>Simpan Cohort</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ============================================== -->
        <!-- MODAL: Edit Cohort                             -->
        <!-- ============================================== -->
        <div
            v-if="isEditModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs"
        >
            <div class="relative w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl ring-1 ring-slate-900/10">
                <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Ubah Data Cohort</h3>
                        <p class="text-xs text-slate-500">Perbarui nama atau kode kelompok</p>
                    </div>
                    <button
                        type="button"
                        @click="isEditModalOpen = false"
                        class="rounded-xl p-1.5 text-slate-400 hover:bg-slate-100 transition"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <form @submit.prevent="submitEditCohort" class="mt-5 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Nama Cohort</label>
                        <input
                            v-model="editForm.name"
                            type="text"
                            required
                            class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-xs text-slate-800 transition focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        />
                        <p v-if="editForm.errors.name" class="mt-1 text-xs text-red-600">{{ editForm.errors.name }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Kode Cohort</label>
                        <input
                            v-model="editForm.code"
                            type="text"
                            class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 font-mono text-xs text-slate-800 uppercase transition focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        />
                        <p v-if="editForm.errors.code" class="mt-1 text-xs text-red-600">{{ editForm.errors.code }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Keterangan</label>
                        <textarea
                            v-model="editForm.description"
                            rows="2"
                            class="w-full rounded-xl border border-slate-200 p-3 text-xs text-slate-800 transition focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        ></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-4 mt-6">
                        <button
                            type="button"
                            @click="isEditModalOpen = false"
                            class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50 transition"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="editForm.processing"
                            class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-5 py-2 text-xs font-bold text-white shadow-sm hover:bg-indigo-700 transition disabled:opacity-50"
                        >
                            <Loader2 v-if="editForm.processing" class="h-4 w-4 animate-spin" />
                            <span>Perbarui Data</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ============================================== -->
        <!-- MODAL: Hapus Cohort                            -->
        <!-- ============================================== -->
        <div
            v-if="isDeleteModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs"
        >
            <div class="relative w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl ring-1 ring-slate-900/10">
                <div class="flex items-center gap-3 text-red-600 mb-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-red-50 text-red-600">
                        <Trash2 class="h-5 w-5" />
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Hapus Cohort</h3>
                        <p class="text-xs text-slate-500">Tindakan ini tidak menghapus akun murid</p>
                    </div>
                </div>

                <p class="text-xs text-slate-600 leading-relaxed">
                    Apakah Anda yakin ingin menghapus cohort <strong>{{ cohortToDelete?.name }}</strong>? Anggota murid di dalamnya akan dilepas dari kelompok ini, namun akun murid tetap ada.
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
                        <span>Ya, Hapus Cohort</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
