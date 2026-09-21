<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import {
    Users,
    UserPlus,
    UserMinus,
    Search,
    Filter,
    ArrowLeft,
    Check,
    X,
    Loader2,
    BookOpen,
    School,
    GraduationCap,
    Calendar,
    ChevronRight,
    ClipboardList,
    AlertCircle,
    Plus,
} from 'lucide-vue-next';
import { useToast } from '@/composables/useToast';

interface StudentMember {
    id: number;
    name: string;
    nis: string | null;
    kelas: string | null;
    email: string | null;
    pivot: {
        created_at: string;
    };
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedMembers {
    data: StudentMember[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: PaginationLink[];
}

interface Props {
    cohort: {
        id: number;
        name: string;
        code: string | null;
        description: string | null;
        users_count: number;
        quiz_forms_count: number;
        created_at: string;
        creator?: {
            id: number;
            name: string;
        } | null;
    };
    members: PaginatedMembers;
    filters: {
        search: string;
        kelas: string;
    };
    classes: string[];
    allAvailableClasses: string[];
}

const props = defineProps<Props>();
const page = usePage();
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
const selectedClass = ref(props.filters.kelas || '');
let searchTimer: ReturnType<typeof setTimeout> | null = null;

function applyFilters(): void {
    router.get(
        route('cohorts.show', props.cohort.id),
        {
            search: searchInput.value || undefined,
            kelas: selectedClass.value || undefined,
        },
        { preserveState: true, replace: true }
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

// --- MODAL: Add Members by Class ---
const isAddClassModalOpen = ref(false);
const addClassForm = useForm({
    class_name: '',
});

function openAddClassModal(): void {
    addClassForm.reset();
    isAddClassModalOpen.value = true;
}

function submitAddClass(): void {
    if (!addClassForm.class_name) return;
    addClassForm.post(route('cohorts.members.add', props.cohort.id), {
        onSuccess: () => {
            isAddClassModalOpen.value = false;
            showToast(`Murid dari Kelas ${addClassForm.class_name} berhasil ditambahkan!`);
        },
    });
}

// --- Remove Member ---
const isRemoveModalOpen = ref(false);
const memberToRemove = ref<StudentMember | null>(null);
const isRemoving = ref(false);

function openRemoveModal(member: StudentMember): void {
    memberToRemove.value = member;
    isRemoveModalOpen.value = true;
}

function confirmRemove(): void {
    if (!memberToRemove.value) return;
    isRemoving.value = true;
    router.delete(route('cohorts.members.remove', [props.cohort.id, memberToRemove.value.id]), {
        onFinish: () => {
            isRemoving.value = false;
            isRemoveModalOpen.value = false;
            memberToRemove.value = null;
            showToast('Murid berhasil dikeluarkan dari cohort.');
        },
    });
}
</script>

<template>
    <Head :title="`Cohort ${cohort.name} - AlsenForm`" />

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
                        :href="route('cohorts.index')"
                        class="flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900 shadow-sm"
                        title="Kembali ke Daftar Cohort"
                    >
                        <ArrowLeft class="h-5 w-5" />
                    </Link>
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="text-xl font-extrabold tracking-tight text-slate-900 sm:text-2xl truncate max-w-md">
                                {{ cohort.name }}
                            </h1>
                            <span v-if="cohort.code" class="rounded-lg bg-indigo-50 px-2 py-0.5 font-mono text-xs font-bold text-indigo-700 border border-indigo-200">
                                {{ cohort.code }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 hidden sm:block">
                            {{ cohort.description || 'Kelola daftar murid anggota cohort ini' }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        v-if="allAvailableClasses.length > 0"
                        type="button"
                        @click="openAddClassModal"
                        class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white shadow-sm shadow-indigo-600/30 hover:bg-indigo-700 transition"
                    >
                        <UserPlus class="h-4 w-4" />
                        <span>Tambah dari Kelas</span>
                    </button>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-4 pt-6 sm:px-6 lg:px-8">
            <!-- Details Overview Banner -->
            <div class="rounded-3xl border border-slate-200/80 bg-white p-5 shadow-xs mb-6">
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <div>
                        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Anggota</span>
                        <div class="mt-1 text-2xl font-black text-slate-900">{{ cohort.users_count }} Murid</div>
                    </div>
                    <div>
                        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Kuis Tertaut</span>
                        <div class="mt-1 text-2xl font-black text-indigo-600">{{ cohort.quiz_forms_count }} Kuis</div>
                    </div>
                    <div>
                        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Kelas Terwakili</span>
                        <div class="mt-1 text-2xl font-black text-blue-600">{{ classes.length }} Kelas</div>
                    </div>
                    <div>
                        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Dibuat Oleh</span>
                        <div class="mt-1 text-sm font-bold text-slate-800 truncate">{{ cohort.creator?.name || 'Admin' }}</div>
                    </div>
                </div>
            </div>

            <!-- Main Member Table Panel -->
            <div class="rounded-3xl border border-slate-200/80 bg-white shadow-sm overflow-hidden">
                <!-- Toolbar -->
                <div class="border-b border-slate-100 p-4 sm:p-5">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <h2 class="text-sm font-bold text-slate-900">
                            Daftar Anggota Murid ({{ members.total }})
                        </h2>

                        <div class="flex flex-wrap items-center gap-2">
                            <!-- Search -->
                            <div class="relative min-w-[200px] flex-1 sm:flex-initial">
                                <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                <input
                                    v-model="searchInput"
                                    type="text"
                                    @input="handleSearch"
                                    placeholder="Cari murid..."
                                    class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2 pl-9 pr-8 text-xs font-medium text-slate-800 transition focus:border-indigo-500 focus:bg-white focus:outline-none focus:ring-1 focus:ring-indigo-500"
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
                            <div v-if="classes.length > 0" class="relative">
                                <select
                                    v-model="selectedClass"
                                    @change="applyFilters"
                                    class="appearance-none rounded-xl border border-slate-200 bg-slate-50 py-2 pl-3 pr-8 text-xs font-medium text-slate-700 transition focus:border-indigo-500 focus:bg-white focus:outline-none focus:ring-1 focus:ring-indigo-500 cursor-pointer"
                                >
                                    <option value="">Semua Kelas</option>
                                    <option v-for="cls in classes" :key="cls" :value="cls">
                                        Kelas {{ cls }}
                                    </option>
                                </select>
                                <Filter class="pointer-events-none absolute right-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                            </div>

                            <button
                                v-if="searchInput || selectedClass"
                                type="button"
                                @click="clearFilters"
                                class="inline-flex items-center gap-1 rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 transition"
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
                                <th scope="col" class="px-5 py-3.5">Nama Murid</th>
                                <th scope="col" class="px-4 py-3.5">NIS</th>
                                <th scope="col" class="px-4 py-3.5">Kelas</th>
                                <th scope="col" class="px-4 py-3.5">Email</th>
                                <th scope="col" class="px-5 py-3.5 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="m in members.data"
                                :key="m.id"
                                class="transition hover:bg-slate-50/80"
                            >
                                <td class="px-5 py-3.5 font-bold text-slate-900">
                                    <div class="flex items-center gap-2.5">
                                        <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-indigo-100 text-indigo-700 font-bold uppercase text-xs">
                                            {{ m.name.charAt(0) }}
                                        </div>
                                        <span>{{ m.name }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3.5 font-mono font-semibold text-slate-800">
                                    {{ m.nis || '-' }}
                                </td>
                                <td class="px-4 py-3.5">
                                    <span v-if="m.kelas" class="rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-bold text-slate-700">
                                        {{ m.kelas }}
                                    </span>
                                    <span v-else class="text-slate-400">-</span>
                                </td>
                                <td class="px-4 py-3.5 text-slate-500">
                                    {{ m.email || '-' }}
                                </td>
                                <td class="px-5 py-3.5 text-right">
                                    <button
                                        type="button"
                                        @click="openRemoveModal(m)"
                                        class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2.5 py-1 text-[11px] font-semibold text-red-600 hover:bg-red-50 hover:border-red-200 transition"
                                        title="Keluarkan murid dari cohort ini"
                                    >
                                        <UserMinus class="h-3.5 w-3.5 text-red-500" />
                                        <span>Keluarkan</span>
                                    </button>
                                </td>
                            </tr>

                            <tr v-if="members.data.length === 0">
                                <td colspan="5" class="py-12 text-center text-slate-500">
                                    <Users class="mx-auto h-10 w-10 text-slate-300 mb-2" />
                                    <p class="font-bold text-slate-700">Belum ada anggota murid</p>
                                    <p class="text-xs text-slate-400 mt-1">
                                        Tambahkan murid ke cohort ini dengan tombol "Tambah dari Kelas" di atas.
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="members.total > members.per_page" class="border-t border-slate-100 px-5 py-3.5">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 text-xs text-slate-500">
                        <div>
                            Menampilkan <span class="font-bold text-slate-800">{{ members.from }}</span> -
                            <span class="font-bold text-slate-800">{{ members.to }}</span> dari
                            <span class="font-bold text-slate-800">{{ members.total }}</span> murid
                        </div>
                        <div class="flex items-center gap-1">
                            <Link
                                v-for="(link, i) in members.links"
                                :key="i"
                                :href="link.url || '#'"
                                :class="[
                                    'rounded-lg px-2.5 py-1.5 text-xs font-semibold transition',
                                    link.active
                                        ? 'bg-indigo-600 text-white'
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
        <!-- MODAL: Tambah Murid dari Kelas                 -->
        <!-- ============================================== -->
        <div
            v-if="isAddClassModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs"
        >
            <div class="relative w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl ring-1 ring-slate-900/10">
                <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Tambah Murid dari Kelas</h3>
                        <p class="text-xs text-slate-500">Masukkan semua murid dari satu kelas ke cohort ini</p>
                    </div>
                    <button
                        type="button"
                        @click="isAddClassModalOpen = false"
                        class="rounded-xl p-1.5 text-slate-400 hover:bg-slate-100 transition"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <form @submit.prevent="submitAddClass" class="mt-5 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Pilih Kelas</label>
                        <select
                            v-model="addClassForm.class_name"
                            required
                            class="w-full rounded-xl border border-slate-200 px-3.5 py-2.5 text-xs font-bold text-slate-800 transition focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                        >
                            <option value="" disabled>Pilih salah satu kelas</option>
                            <option v-for="cls in allAvailableClasses" :key="cls" :value="cls">
                                Kelas {{ cls }}
                            </option>
                        </select>
                    </div>

                    <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-4 mt-6">
                        <button
                            type="button"
                            @click="isAddClassModalOpen = false"
                            class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50 transition"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="addClassForm.processing || !addClassForm.class_name"
                            class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-5 py-2 text-xs font-bold text-white shadow-sm hover:bg-indigo-700 transition disabled:opacity-50"
                        >
                            <Loader2 v-if="addClassForm.processing" class="h-4 w-4 animate-spin" />
                            <span>Tambahkan ke Cohort</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ============================================== -->
        <!-- MODAL: Konfirmasi Keluarkan Murid              -->
        <!-- ============================================== -->
        <div
            v-if="isRemoveModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs"
        >
            <div class="relative w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl ring-1 ring-slate-900/10">
                <div class="flex items-center gap-3 text-red-600 mb-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-red-50 text-red-600">
                        <UserMinus class="h-5 w-5" />
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Keluarkan Murid</h3>
                        <p class="text-xs text-slate-500">Keluarkan dari kelompok ini</p>
                    </div>
                </div>

                <p class="text-xs text-slate-600 leading-relaxed">
                    Apakah Anda yakin ingin mengeluarkan murid <strong>{{ memberToRemove?.name }}</strong> dari Cohort <strong>{{ cohort.name }}</strong>? Akun murid tidak dihapus dan murid tetap terdaftar di sistem.
                </p>

                <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-4 mt-6">
                    <button
                        type="button"
                        @click="isRemoveModalOpen = false"
                        class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50 transition"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        @click="confirmRemove"
                        :disabled="isRemoving"
                        class="inline-flex items-center gap-2 rounded-xl bg-red-600 px-5 py-2 text-xs font-bold text-white shadow-sm hover:bg-red-700 transition disabled:opacity-50"
                    >
                        <Loader2 v-if="isRemoving" class="h-4 w-4 animate-spin" />
                        <span>Keluarkan</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
