<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AArrowDown,
    Clipboard,
    ClipboardList,
    ExternalLink,
    FilePenLine,
    Folder,
    FolderPlus,
    Grid3X3,
    LayoutDashboard,
    List,
    MoreVertical,
    RotateCcw,
    Search,
    Send,
    Sparkles,
    Trash2,
    Users,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

type RecentForm = {
    id: number;
    title: string;
    description: string;
    folderId: number | null;
    folder: string | null;
    editUrl: string;
    publicUrl: string;
    duplicateUrl: string;
    moveFolderUrl: string;
    deleteUrl: string;
    restoreUrl: string;
    forceDeleteUrl: string;
    updatedLabel: string;
    updatedAt: string;
    tone: string;
    stripe: string;
    accent: string;
    isPublished: boolean;
    isTrashed: boolean;
};

type QuizFolder = {
    id: number;
    name: string;
    formsCount: number;
    updateUrl: string;
    deleteUrl: string;
};

type FolderModalMode = 'create' | 'rename' | 'delete';
type FormDeleteMode = 'trash' | 'force';

const props = defineProps<{
    recentForms: RecentForm[];
    folders: QuizFolder[];
    createFolderUrl: string;
}>();

const searchQuery = ref('');
const viewMode = ref<'grid' | 'list'>('grid');
const statusFilter = ref<'all' | 'published' | 'draft' | 'trash'>('all');
const folderFilter = ref<number | ''>('');
const sortDirection = ref<'desc' | 'asc'>('desc');
const isAppsOpen = ref(false);
const isTemplateGalleryOpen = ref(true);
const activeMenuId = ref<number | null>(null);
const activeFolderMenuId = ref<number | null>(null);
const draggedFormId = ref<number | null>(null);
const activeDropFolderId = ref<number | null>(null);
const isHomeDropActive = ref(false);
const toastMessage = ref('');
const folderModalMode = ref<FolderModalMode | null>(null);
const selectedFolder = ref<QuizFolder | null>(null);
const folderNameInput = ref('');
const folderDeleteConfirmation = ref('');
const formDeleteMode = ref<FormDeleteMode | null>(null);
const selectedForm = ref<RecentForm | null>(null);
const formDeleteConfirmation = ref('');
const normalizedSearchQuery = computed(() => searchQuery.value.trim().toLowerCase());
const folders = computed(() => props.folders);
const filteredRecentForms = computed(() => {
    const filtered = props.recentForms.filter((form) => {
        const matchesSearch =
            !normalizedSearchQuery.value ||
            [form.title, form.description, form.updatedLabel, form.folder ?? ''].some((value) =>
                value.toLowerCase().includes(normalizedSearchQuery.value),
            );
        const matchesStatus =
            (statusFilter.value === 'all' && !form.isTrashed) ||
            (statusFilter.value === 'published' && form.isPublished && !form.isTrashed) ||
            (statusFilter.value === 'draft' && !form.isPublished && !form.isTrashed) ||
            (statusFilter.value === 'trash' && form.isTrashed);
        const matchesFolder = statusFilter.value === 'trash' || !folderFilter.value || (form.folderId === folderFilter.value && !form.isTrashed);

        return matchesSearch && matchesStatus && matchesFolder;
    });

    return [...filtered].sort((first, second) => {
        const firstTime = new Date(first.updatedAt).getTime();
        const secondTime = new Date(second.updatedAt).getTime();

        return sortDirection.value === 'desc' ? secondTime - firstTime : firstTime - secondTime;
    });
});

const templates = [
    { title: 'Blank form', slug: 'blank', theme: 'blank', color: 'bg-white' },
    { title: 'Contact Information', slug: 'contact-information', theme: 'contact', color: 'bg-emerald-50' },
    { title: 'Party Invite', slug: 'party-invite', theme: 'party', color: 'bg-fuchsia-50' },
    { title: 'Work Request', slug: 'work-request', theme: 'work', color: 'bg-cyan-50' },
    { title: 'RSVP', slug: 'rsvp', theme: 'rsvp', color: 'bg-orange-50' },
    { title: 'T-Shirt Sign Up', slug: 't-shirt-sign-up', theme: 'shirt', color: 'bg-violet-50' },
];

const visibleTemplates = computed(() => (isTemplateGalleryOpen.value ? templates : templates.slice(0, 3)));
const statusFilterLabel = computed(() => {
    if (statusFilter.value === 'published') {
        return 'Published';
    }

    if (statusFilter.value === 'draft') {
        return 'Draft';
    }

    if (statusFilter.value === 'trash') {
        return 'Trash';
    }

    return 'All forms';
});
const activeFolderFilter = computed(() => folders.value.find((folder) => folder.id === folderFilter.value) ?? null);

function showToast(message: string): void {
    toastMessage.value = message;
    setTimeout(() => {
        if (toastMessage.value === message) {
            toastMessage.value = '';
        }
    }, 2500);
}

function copyPublicUrl(form: RecentForm): void {
    if (!navigator.clipboard) {
        showToast('Browser tidak mengizinkan copy otomatis');
        activeMenuId.value = null;

        return;
    }

    navigator.clipboard
        .writeText(form.publicUrl)
        .then(() => showToast('Link publik disalin'))
        .catch(() => showToast('Browser tidak mengizinkan copy otomatis'));
    activeMenuId.value = null;
}

function duplicateForm(form: RecentForm): void {
    activeMenuId.value = null;
    router.post(form.duplicateUrl);
}

function openCreateFolderModal(): void {
    selectedFolder.value = null;
    folderNameInput.value = '';
    folderDeleteConfirmation.value = '';
    folderModalMode.value = 'create';
}

function openRenameFolderModal(folder: QuizFolder): void {
    activeFolderMenuId.value = null;
    selectedFolder.value = folder;
    folderNameInput.value = folder.name;
    folderDeleteConfirmation.value = '';
    folderModalMode.value = 'rename';
}

function openDeleteFolderModal(folder: QuizFolder): void {
    activeFolderMenuId.value = null;
    selectedFolder.value = folder;
    folderNameInput.value = folder.name;
    folderDeleteConfirmation.value = '';
    folderModalMode.value = 'delete';
}

function closeFolderModal(): void {
    folderModalMode.value = null;
    selectedFolder.value = null;
    folderNameInput.value = '';
    folderDeleteConfirmation.value = '';
}

function submitFolderModal(): void {
    if (folderModalMode.value === 'create') {
        const name = folderNameInput.value.trim();

        if (!name) {
            return;
        }

        router.post(
            props.createFolderUrl,
            { name },
            {
                preserveScroll: true,
                onSuccess: () => {
                    closeFolderModal();
                    showToast('Folder dibuat');
                },
            },
        );

        return;
    }

    if (folderModalMode.value === 'rename' && selectedFolder.value) {
        const name = folderNameInput.value.trim();

        if (!name) {
            return;
        }

        router.patch(
            selectedFolder.value.updateUrl,
            { name },
            {
                preserveScroll: true,
                onSuccess: () => {
                    closeFolderModal();
                    showToast('Folder diganti nama');
                },
            },
        );

        return;
    }

    if (folderModalMode.value === 'delete' && selectedFolder.value && folderDeleteConfirmation.value === selectedFolder.value.name) {
        router.delete(selectedFolder.value.deleteUrl, {
            preserveScroll: true,
            onSuccess: () => {
                if (folderFilter.value === selectedFolder.value?.id) {
                    folderFilter.value = '';
                }

                closeFolderModal();
                showToast('Folder dihapus');
            },
        });
    }
}

function moveToFolder(form: RecentForm): void {
    activeMenuId.value = null;

    const folder = window.prompt('Nama folder', form.folder ?? '');

    if (folder === null) {
        return;
    }

    router.patch(
        form.moveFolderUrl,
        { folder },
        {
            preserveScroll: true,
            onSuccess: () => showToast(folder.trim() ? 'Form dipindahkan ke folder' : 'Form dikeluarkan dari folder'),
        },
    );
}

function removeFromFolder(form: RecentForm): void {
    activeMenuId.value = null;

    if (!form.folderId) {
        return;
    }

    router.patch(
        form.moveFolderUrl,
        { folder_id: null },
        {
            preserveScroll: true,
            onSuccess: () => showToast('Form dikeluarkan dari folder'),
        },
    );
}

function startDraggingForm(form: RecentForm, event: DragEvent): void {
    if (form.isTrashed) {
        return;
    }

    draggedFormId.value = form.id;
    event.dataTransfer?.setData('text/plain', String(form.id));
    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
    }
}

function dropFormIntoFolder(folder: QuizFolder): void {
    const formId = draggedFormId.value;
    activeDropFolderId.value = null;
    draggedFormId.value = null;

    if (!formId) {
        return;
    }

    const form = props.recentForms.find((candidate) => candidate.id === formId);

    if (!form || form.isTrashed || form.folderId === folder.id) {
        return;
    }

    router.patch(
        form.moveFolderUrl,
        { folder_id: folder.id },
        {
            preserveScroll: true,
            onSuccess: () => showToast(`Dipindahkan ke ${folder.name}`),
        },
    );
}

function dropFormOutOfFolder(): void {
    const formId = draggedFormId.value;
    isHomeDropActive.value = false;
    activeDropFolderId.value = null;
    draggedFormId.value = null;

    if (!formId) {
        return;
    }

    const form = props.recentForms.find((candidate) => candidate.id === formId);

    if (!form || form.isTrashed || !form.folderId) {
        return;
    }

    router.patch(
        form.moveFolderUrl,
        { folder_id: null },
        {
            preserveScroll: true,
            onSuccess: () => showToast('Form dikeluarkan dari folder'),
        },
    );
}

function endDraggingForm(): void {
    draggedFormId.value = null;
    activeDropFolderId.value = null;
    isHomeDropActive.value = false;
}

function deleteForm(form: RecentForm): void {
    activeMenuId.value = null;
    selectedForm.value = form;
    formDeleteMode.value = 'trash';
    formDeleteConfirmation.value = '';
}

function restoreForm(form: RecentForm): void {
    activeMenuId.value = null;

    router.patch(
        form.restoreUrl,
        {},
        {
            preserveScroll: true,
            onSuccess: () => showToast('Form dipulihkan'),
        },
    );
}

function forceDeleteForm(form: RecentForm): void {
    activeMenuId.value = null;
    selectedForm.value = form;
    formDeleteMode.value = 'force';
    formDeleteConfirmation.value = '';
}

function closeFormDeleteModal(): void {
    selectedForm.value = null;
    formDeleteMode.value = null;
    formDeleteConfirmation.value = '';
}

function submitFormDeleteModal(): void {
    if (!selectedForm.value || formDeleteConfirmation.value !== selectedForm.value.title) {
        return;
    }

    const form = selectedForm.value;
    const isForceDelete = formDeleteMode.value === 'force';

    router.delete(isForceDelete ? form.forceDeleteUrl : form.deleteUrl, {
        preserveScroll: true,
        onSuccess: () => {
            closeFormDeleteModal();
            showToast(isForceDelete ? 'Form dihapus permanen' : 'Form dipindahkan ke Trash');
        },
    });
}

function toggleSortDirection(): void {
    sortDirection.value = sortDirection.value === 'desc' ? 'asc' : 'desc';
}

function goHome(): void {
    folderFilter.value = '';
    statusFilter.value = 'all';
    searchQuery.value = '';
}
</script>

<template>
    <Head title="Forms" />

    <main class="min-h-screen bg-white text-slate-900">
        <header class="sticky top-0 z-30 border-b border-slate-100 bg-white">
            <div class="flex h-14 items-center gap-3 px-4 sm:px-6">
                <div class="flex items-center gap-2.5">
                    <div class="grid h-8 w-8 grid-cols-2 gap-1 rounded-xl bg-indigo-500 p-1.5 text-white shadow-[0_2px_0_#4338ca]">
                        <span class="rounded-lg bg-white/95"></span>
                        <span class="rounded-lg bg-white/70"></span>
                        <span class="rounded-lg bg-white/70"></span>
                        <span class="rounded-lg bg-white/95"></span>
                    </div>
                    <h1 class="text-xl font-semibold tracking-normal">Forms</h1>
                </div>

                <label class="mx-auto hidden h-10 w-full max-w-2xl items-center gap-3 rounded-full bg-slate-100 px-4 text-slate-500 md:flex">
                    <Search class="h-4 w-4" />
                    <input
                        v-model="searchQuery"
                        type="search"
                        class="w-full border-0 bg-transparent text-sm font-medium text-slate-700 outline-none placeholder:text-slate-500"
                        placeholder="Search quiz"
                    />
                </label>

                <div class="ml-auto flex items-center gap-2">
                    <div class="relative hidden sm:block">
                        <button
                            type="button"
                            aria-label="Apps"
                            @click="isAppsOpen = !isAppsOpen"
                            class="flex h-9 w-9 items-center justify-center rounded-full text-slate-600 hover:bg-slate-100"
                        >
                            <Grid3X3 class="h-5 w-5" />
                        </button>
                        <div v-if="isAppsOpen" class="absolute right-0 top-11 z-40 w-56 rounded-2xl border border-slate-200 bg-white p-2 shadow-xl">
                            <Link
                                :href="route('dashboard')"
                                class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100"
                            >
                                <LayoutDashboard class="h-4 w-4 text-emerald-600" />
                                Dashboard
                            </Link>
                            <button
                                type="button"
                                class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-left text-sm font-semibold text-slate-700 hover:bg-slate-100"
                                @click="
                                    isTemplateGalleryOpen = true;
                                    isAppsOpen = false;
                                "
                            >
                                <ClipboardList class="h-4 w-4 text-indigo-600" />
                                Template gallery
                            </button>
                        </div>
                    </div>
                    <Link
                        :href="route('logout')"
                        method="post"
                        as="button"
                        class="h-9 rounded-full border-2 border-emerald-400 bg-gradient-to-br from-lime-200 via-emerald-300 to-sky-300 px-3 text-xs font-black text-slate-800"
                    >
                        Keluar
                    </Link>
                </div>
            </div>
        </header>

        <section class="bg-slate-100/80">
            <div class="mx-auto max-w-[1180px] px-4 py-4 sm:px-6">
                <label class="mb-4 flex h-10 items-center gap-3 rounded-full bg-white px-4 text-slate-500 shadow-sm md:hidden">
                    <Search class="h-5 w-5" />
                    <input
                        v-model="searchQuery"
                        type="search"
                        class="w-full border-0 bg-transparent text-base font-medium text-slate-700 outline-none placeholder:text-slate-500"
                        placeholder="Search quiz"
                    />
                </label>

                <div class="mb-4 flex items-center justify-between gap-4">
                    <h2 class="text-base font-semibold">Start a new form</h2>
                    <div class="hidden items-center gap-4 text-sm font-semibold text-slate-700 md:flex">
                        <button
                            type="button"
                            aria-label="Toggle template gallery"
                            class="flex items-center gap-2 hover:text-emerald-700"
                            @click="isTemplateGalleryOpen = !isTemplateGalleryOpen"
                        >
                            Template gallery
                            <AArrowDown :class="['h-5 w-5 transition-transform', isTemplateGalleryOpen ? 'rotate-180' : '']" />
                        </button>
                        <span class="h-9 border-l border-slate-300"></span>
                        <button
                            type="button"
                            aria-label="Template options"
                            class="rounded-full p-1 hover:bg-white"
                            @click="showToast('Pilih template untuk membuat form baru')"
                        >
                            <MoreVertical class="h-6 w-6" />
                        </button>
                    </div>
                </div>

                <div class="flex gap-4 overflow-x-auto pb-1">
                    <a
                        v-for="template in visibleTemplates"
                        :key="template.title"
                        :href="route('forms.create', { template: template.slug })"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="group w-32 shrink-0 text-left sm:w-36"
                    >
                        <div
                            :class="[
                                'flex aspect-[4/3] items-center justify-center overflow-hidden rounded-xl border border-slate-300 transition group-hover:border-emerald-500',
                                template.color,
                            ]"
                        >
                            <div v-if="template.theme === 'blank'" class="relative h-12 w-12">
                                <span class="absolute left-1/2 top-0 h-full w-3 -translate-x-1/2 bg-emerald-500"></span>
                                <span class="absolute left-0 top-1/2 h-3 w-full -translate-y-1/2 bg-amber-400"></span>
                                <span class="absolute right-0 top-1/2 h-3 w-1/2 -translate-y-1/2 bg-blue-500"></span>
                                <span class="absolute left-1/2 top-0 h-1/2 w-3 -translate-x-1/2 bg-red-500"></span>
                            </div>
                            <div v-else class="w-3/5 overflow-hidden rounded-xl bg-white shadow-sm">
                                <div
                                    :class="[
                                        'h-6',
                                        template.theme === 'party'
                                            ? 'bg-fuchsia-300'
                                            : template.theme === 'work'
                                              ? 'bg-emerald-300'
                                              : 'bg-emerald-500',
                                    ]"
                                ></div>
                                <div class="space-y-1.5 p-2.5">
                                    <div class="h-2 w-2/3 rounded-full bg-slate-300"></div>
                                    <div class="h-4 rounded-lg bg-slate-100"></div>
                                    <div class="h-4 rounded-lg bg-slate-100"></div>
                                    <div class="h-4 rounded-lg bg-slate-100"></div>
                                </div>
                            </div>
                        </div>
                        <p class="mt-1.5 truncate text-sm font-semibold text-slate-900">{{ template.title }}</p>
                    </a>
                </div>
            </div>
        </section>

        <section class="mx-auto max-w-[1180px] px-4 py-6 sm:px-6">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-base font-semibold">Recent forms</h2>
                    <p v-if="searchQuery.trim()" class="mt-1 text-sm font-medium text-slate-500">
                        Menampilkan hasil pencarian: <span class="text-slate-800">"{{ searchQuery.trim() }}"</span>
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-3 text-slate-600">
                    <button
                        type="button"
                        aria-label="Create folder"
                        class="flex h-9 items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 text-xs font-bold text-emerald-700 hover:bg-emerald-100"
                        @click="openCreateFolderModal"
                    >
                        <FolderPlus class="h-4 w-4" />
                        Folder
                    </button>
                    <div class="relative">
                        <select
                            v-model="statusFilter"
                            class="h-9 rounded-full border border-slate-200 bg-white py-1.5 pl-3 pr-8 text-xs font-semibold text-slate-700 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                            aria-label="Filter forms"
                        >
                            <option value="all">All forms</option>
                            <option value="published">Published</option>
                            <option value="draft">Draft</option>
                            <option value="trash">Trash</option>
                        </select>
                    </div>
                    <select
                        v-model="folderFilter"
                        class="h-9 max-w-40 rounded-full border border-slate-200 bg-white py-1.5 pl-3 pr-8 text-xs font-semibold text-slate-700 shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
                        aria-label="Filter folders"
                        :disabled="!folders.length || statusFilter === 'trash'"
                    >
                        <option value="">All folders</option>
                        <option v-for="folder in folders" :key="folder.id" :value="folder.id">{{ folder.name }}</option>
                    </select>
                    <button
                        type="button"
                        :aria-label="viewMode === 'grid' ? 'Switch to list view' : 'Switch to grid view'"
                        class="rounded-full p-1.5 hover:bg-slate-100"
                        @click="viewMode = viewMode === 'grid' ? 'list' : 'grid'"
                    >
                        <List v-if="viewMode === 'grid'" class="h-6 w-6" />
                        <Grid3X3 v-else class="h-6 w-6" />
                    </button>
                    <button
                        type="button"
                        :aria-label="sortDirection === 'desc' ? 'Sort oldest first' : 'Sort newest first'"
                        class="rounded-full p-1.5 hover:bg-slate-100"
                        @click="toggleSortDirection"
                    >
                        <AArrowDown :class="['h-6 w-6 transition-transform', sortDirection === 'asc' ? 'rotate-180' : '']" />
                    </button>
                    <button type="button" aria-label="Back to all folders" class="rounded-full p-1.5 hover:bg-slate-100" @click="folderFilter = ''">
                        <Folder class="h-7 w-7" />
                    </button>
                </div>
            </div>

            <div
                v-if="activeFolderFilter || statusFilter !== 'all' || searchQuery.trim()"
                class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3"
            >
                <div class="flex min-w-0 items-center gap-2 text-sm font-semibold text-slate-600">
                    <button
                        type="button"
                        class="with-tooltip text-emerald-700 hover:text-emerald-800"
                        aria-label="Back to dashboard home"
                        @click="goHome"
                    >
                        Home
                    </button>
                    <span>/</span>
                    <span class="min-w-0 truncate text-slate-900">
                        {{ activeFolderFilter?.name ?? statusFilterLabel }}
                    </span>
                </div>
                <button
                    type="button"
                    class="with-tooltip rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-100"
                    aria-label="Clear filters and return home"
                    @click="goHome"
                >
                    Back to Home
                </button>
            </div>

            <div
                v-if="draggedFormId || activeFolderFilter"
                :class="[
                    'mb-4 flex items-center justify-between gap-3 rounded-2xl border border-dashed px-4 py-3 transition',
                    isHomeDropActive ? 'border-emerald-400 bg-emerald-50 text-emerald-800' : 'border-slate-300 bg-white text-slate-600',
                ]"
                @dragover.prevent="isHomeDropActive = true"
                @dragleave="isHomeDropActive = false"
                @drop.prevent="dropFormOutOfFolder"
            >
                <div class="min-w-0">
                    <p class="text-sm font-bold">All forms / Home</p>
                    <p class="mt-1 text-xs font-semibold text-slate-500">Drop form di sini untuk mengeluarkannya dari folder.</p>
                </div>
                <button
                    type="button"
                    class="with-tooltip rounded-full bg-slate-900 px-3 py-1.5 text-xs font-bold text-white hover:bg-slate-700"
                    aria-label="Show all forms"
                    @click="folderFilter = ''"
                >
                    View Home
                </button>
            </div>

            <div v-if="folders.length" class="mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5">
                <article
                    v-for="folder in folders"
                    :key="folder.id"
                    role="button"
                    tabindex="0"
                    :class="[
                        'group relative flex aspect-[5/3] min-w-0 flex-col justify-between rounded-xl border p-3 text-left transition',
                        activeDropFolderId === folder.id
                            ? 'border-emerald-400 bg-emerald-50 shadow-sm'
                            : folderFilter === folder.id
                              ? 'border-indigo-300 bg-indigo-50'
                              : 'border-slate-200 bg-white hover:bg-slate-50',
                    ]"
                    @click="folderFilter = folder.id"
                    @dragover.prevent="activeDropFolderId = folder.id"
                    @dragleave="activeDropFolderId = null"
                    @drop.prevent="dropFormIntoFolder(folder)"
                >
                    <span class="flex items-start justify-between gap-3">
                        <Folder class="h-16 w-16 shrink-0 text-emerald-500" />
                        <span class="relative">
                            <button
                                type="button"
                                aria-label="Folder options"
                                class="rounded-full p-1 text-slate-500 hover:bg-white hover:text-slate-800"
                                @click.stop="activeFolderMenuId = activeFolderMenuId === folder.id ? null : folder.id"
                            >
                                <MoreVertical class="h-5 w-5" />
                            </button>
                            <span
                                v-if="activeFolderMenuId === folder.id"
                                class="absolute right-0 top-8 z-20 w-40 rounded-2xl border border-slate-200 bg-white p-2 shadow-xl"
                            >
                                <button
                                    type="button"
                                    class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-left text-sm font-semibold text-slate-700 hover:bg-slate-100"
                                    @click.stop="openRenameFolderModal(folder)"
                                >
                                    <FilePenLine class="h-4 w-4" />
                                    Rename
                                </button>
                                <button
                                    type="button"
                                    class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-left text-sm font-semibold text-red-600 hover:bg-red-50"
                                    @click.stop="openDeleteFolderModal(folder)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                    Delete
                                </button>
                            </span>
                        </span>
                    </span>
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-semibold text-slate-800">{{ folder.name }}</span>
                        <span class="mt-1 inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs font-bold text-slate-500">
                            {{ folder.formsCount }} form
                        </span>
                    </span>
                </article>
            </div>

            <div
                v-if="filteredRecentForms.length"
                :class="[viewMode === 'grid' ? 'grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5' : 'flex flex-col gap-2']"
            >
                <article
                    v-for="form in filteredRecentForms"
                    :key="form.id"
                    :draggable="!form.isTrashed"
                    :class="[
                        'relative overflow-visible rounded-xl border border-slate-300 bg-white transition hover:-translate-y-0.5 hover:shadow-lg',
                        viewMode === 'list' ? 'flex items-stretch' : '',
                        !form.isTrashed ? 'cursor-grab active:cursor-grabbing' : '',
                    ]"
                    @dragstart="startDraggingForm(form, $event)"
                    @dragend="endDraggingForm"
                >
                    <Link v-if="!form.isTrashed" :href="form.editUrl" :class="[viewMode === 'list' ? 'flex w-36 shrink-0' : 'block']">
                        <div :class="['flex aspect-[5/3] items-start justify-center p-2', form.tone, viewMode === 'list' ? 'h-full w-full' : '']">
                            <div class="w-3/5 overflow-hidden rounded-lg bg-white shadow">
                                <div :class="['h-5', form.stripe]"></div>
                                <div class="space-y-1 p-2">
                                    <div class="h-2 w-4/5 rounded-full bg-slate-300"></div>
                                    <div class="h-4 rounded-md bg-slate-100"></div>
                                    <div class="h-4 rounded-md bg-slate-100"></div>
                                    <div class="h-4 rounded-md bg-slate-100"></div>
                                </div>
                            </div>
                        </div>
                    </Link>
                    <div v-else :class="[viewMode === 'list' ? 'flex w-36 shrink-0' : 'block']">
                        <div
                            :class="[
                                'flex aspect-[5/3] items-start justify-center p-2 opacity-60',
                                form.tone,
                                viewMode === 'list' ? 'h-full w-full' : '',
                            ]"
                        >
                            <div class="w-3/5 overflow-hidden rounded-lg bg-white shadow">
                                <div class="h-5 bg-slate-400"></div>
                                <div class="space-y-1 p-2">
                                    <div class="h-2 w-4/5 rounded-full bg-slate-300"></div>
                                    <div class="h-4 rounded-md bg-slate-100"></div>
                                    <div class="h-4 rounded-md bg-slate-100"></div>
                                    <div class="h-4 rounded-md bg-slate-100"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        :class="['border-t border-slate-200 p-3', viewMode === 'list' ? 'flex min-w-0 flex-1 items-center border-l border-t-0' : '']"
                    >
                        <div class="min-w-0 flex-1">
                            <div class="mb-2 flex items-center justify-between gap-2">
                                <Link v-if="!form.isTrashed" :href="form.editUrl" class="min-w-0">
                                    <h3 class="truncate text-sm font-semibold text-slate-800 hover:text-emerald-700" :title="form.title">
                                        {{ form.title }}
                                    </h3>
                                </Link>
                                <h3 v-else class="min-w-0 truncate text-sm font-semibold text-slate-500" :title="form.title">{{ form.title }}</h3>
                                <span
                                    :class="[
                                        'shrink-0 rounded-full px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider',
                                        form.isTrashed
                                            ? 'border border-slate-200 bg-slate-100 text-slate-600'
                                            : form.isPublished
                                              ? 'border border-emerald-200 bg-emerald-50 text-emerald-700'
                                              : 'border border-amber-200 bg-amber-50 text-amber-700',
                                    ]"
                                >
                                    {{ form.isTrashed ? 'Trash' : form.isPublished ? 'Published' : 'Draft' }}
                                </span>
                            </div>
                            <div class="flex min-w-0 items-center gap-1.5 text-xs font-medium text-slate-500">
                                <span :class="['flex h-5 w-5 shrink-0 items-center justify-center rounded-md text-white', form.accent]">
                                    <ClipboardList class="h-3.5 w-3.5" />
                                </span>
                                <Users class="h-4 w-4 shrink-0" />
                                <span class="min-w-0 flex-1 truncate">{{ form.updatedLabel }}</span>
                                <span
                                    v-if="form.folder && !form.isTrashed"
                                    class="max-w-20 shrink truncate rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] text-slate-600"
                                >
                                    {{ form.folder }}
                                </span>
                                <div class="relative shrink-0">
                                    <button
                                        type="button"
                                        aria-label="More options"
                                        class="rounded-full p-0.5 hover:bg-slate-100"
                                        @click="activeMenuId = activeMenuId === form.id ? null : form.id"
                                    >
                                        <MoreVertical class="h-5 w-5" />
                                    </button>
                                    <div
                                        v-if="activeMenuId === form.id"
                                        class="absolute right-0 top-7 z-20 w-48 rounded-2xl border border-slate-200 bg-white p-2 shadow-xl"
                                    >
                                        <template v-if="!form.isTrashed">
                                            <Link
                                                :href="form.editUrl"
                                                class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100"
                                            >
                                                <FilePenLine class="h-4 w-4" />
                                                Edit
                                            </Link>
                                            <a
                                                :href="form.publicUrl"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100"
                                            >
                                                <ExternalLink class="h-4 w-4" />
                                                Open public
                                            </a>
                                            <button
                                                type="button"
                                                class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-left text-sm font-semibold text-slate-700 hover:bg-slate-100"
                                                @click="copyPublicUrl(form)"
                                            >
                                                <Clipboard class="h-4 w-4" />
                                                Copy link
                                            </button>
                                            <button
                                                type="button"
                                                class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-left text-sm font-semibold text-slate-700 hover:bg-slate-100"
                                                @click="duplicateForm(form)"
                                            >
                                                <ClipboardList class="h-4 w-4" />
                                                Duplicate
                                            </button>
                                            <button
                                                type="button"
                                                class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-left text-sm font-semibold text-slate-700 hover:bg-slate-100"
                                                @click="moveToFolder(form)"
                                            >
                                                <Folder class="h-4 w-4" />
                                                Move to folder
                                            </button>
                                            <button
                                                v-if="form.folderId"
                                                type="button"
                                                class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-left text-sm font-semibold text-slate-700 hover:bg-slate-100"
                                                @click="removeFromFolder(form)"
                                            >
                                                <X class="h-4 w-4" />
                                                Remove from folder
                                            </button>
                                            <button
                                                type="button"
                                                class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-left text-sm font-semibold text-red-600 hover:bg-red-50"
                                                @click="deleteForm(form)"
                                            >
                                                <Trash2 class="h-4 w-4" />
                                                Delete
                                            </button>
                                        </template>
                                        <template v-else>
                                            <button
                                                type="button"
                                                class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-left text-sm font-semibold text-slate-700 hover:bg-slate-100"
                                                @click="restoreForm(form)"
                                            >
                                                <RotateCcw class="h-4 w-4" />
                                                Restore
                                            </button>
                                            <button
                                                type="button"
                                                class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-left text-sm font-semibold text-red-600 hover:bg-red-50"
                                                @click="forceDeleteForm(form)"
                                            >
                                                <Trash2 class="h-4 w-4" />
                                                Delete forever
                                            </button>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </article>
            </div>

            <div v-else class="rounded-3xl border border-dashed border-slate-300 bg-slate-50 px-6 py-12 text-center">
                <ClipboardList class="mx-auto h-10 w-10 text-slate-400" />
                <h3 class="mt-4 text-lg font-semibold text-slate-700">
                    {{ searchQuery.trim() ? `Quiz "${searchQuery.trim()}" tidak tersedia` : 'Belum ada recent form' }}
                </h3>
                <p class="mt-2 text-sm text-slate-500">
                    {{
                        searchQuery.trim()
                            ? 'Coba kata kunci lain atau buat quiz baru dari template.'
                            : statusFilter === 'all'
                              ? 'Buat form dari blank form atau template, lalu form akan muncul di sini.'
                              : `Tidak ada form dengan status ${statusFilterLabel}.`
                    }}
                </p>
            </div>
        </section>

        <div v-if="toastMessage" class="fixed bottom-7 right-7 z-50 rounded-full bg-slate-900 px-4 py-3 text-sm font-bold text-white shadow-xl">
            {{ toastMessage }}
        </div>

        <div v-if="folderModalMode" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/35 px-4">
            <section class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl">
                <div class="mb-4 flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">
                            {{ folderModalMode === 'create' ? 'Buat folder' : folderModalMode === 'rename' ? 'Rename folder' : 'Hapus folder' }}
                        </h2>
                        <p class="mt-1 text-sm text-slate-500">
                            {{
                                folderModalMode === 'delete'
                                    ? 'Form di dalam folder tidak dihapus, hanya dikeluarkan dari folder.'
                                    : 'Folder membantu mengelompokkan form di dashboard.'
                            }}
                        </p>
                    </div>
                    <button type="button" class="rounded-full p-1 text-slate-500 hover:bg-slate-100" @click="closeFolderModal">
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <form class="space-y-4" @submit.prevent="submitFolderModal">
                    <label v-if="folderModalMode !== 'delete'" class="block">
                        <span class="text-sm font-semibold text-slate-700">Nama folder</span>
                        <input
                            v-model="folderNameInput"
                            type="text"
                            class="mt-2 h-11 w-full rounded-xl border border-slate-300 px-3 text-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                            placeholder="Mis. Client Work"
                            autofocus
                        />
                    </label>

                    <label v-else class="block">
                        <span class="text-sm font-semibold text-slate-700">Ketik nama folder untuk konfirmasi</span>
                        <span class="mt-1 block rounded-lg bg-red-50 px-3 py-2 text-sm font-bold text-red-700">{{ selectedFolder?.name }}</span>
                        <input
                            v-model="folderDeleteConfirmation"
                            type="text"
                            class="mt-2 h-11 w-full rounded-xl border border-red-200 px-3 text-sm outline-none focus:border-red-500 focus:ring-2 focus:ring-red-100"
                            placeholder="Ketik persis nama folder"
                        />
                    </label>

                    <div class="flex justify-end gap-2">
                        <button
                            type="button"
                            class="rounded-full px-4 py-2 text-sm font-bold text-slate-600 hover:bg-slate-100"
                            @click="closeFolderModal"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="folderModalMode === 'delete' ? folderDeleteConfirmation !== selectedFolder?.name : !folderNameInput.trim()"
                            :class="[
                                'rounded-full px-4 py-2 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-40',
                                folderModalMode === 'delete' ? 'bg-red-600 hover:bg-red-700' : 'bg-emerald-600 hover:bg-emerald-700',
                            ]"
                        >
                            {{ folderModalMode === 'delete' ? 'Hapus folder' : folderModalMode === 'rename' ? 'Simpan' : 'Buat' }}
                        </button>
                    </div>
                </form>
            </section>
        </div>

        <div v-if="formDeleteMode && selectedForm" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/35 px-4">
            <section class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl">
                <div class="mb-4 flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">
                            {{ formDeleteMode === 'force' ? 'Hapus permanen form' : 'Pindahkan form ke Trash' }}
                        </h2>
                        <p class="mt-1 text-sm text-slate-500">
                            {{
                                formDeleteMode === 'force'
                                    ? 'Aksi ini menghapus form dari database dan tidak bisa dipulihkan.'
                                    : 'Form masih bisa dipulihkan dari filter Trash.'
                            }}
                        </p>
                    </div>
                    <button type="button" class="rounded-full p-1 text-slate-500 hover:bg-slate-100" @click="closeFormDeleteModal">
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <form class="space-y-4" @submit.prevent="submitFormDeleteModal">
                    <label class="block">
                        <span class="text-sm font-semibold text-slate-700">Ketik nama form untuk konfirmasi</span>
                        <span class="mt-1 block rounded-lg bg-red-50 px-3 py-2 text-sm font-bold text-red-700">{{ selectedForm.title }}</span>
                        <input
                            v-model="formDeleteConfirmation"
                            type="text"
                            class="mt-2 h-11 w-full rounded-xl border border-red-200 px-3 text-sm outline-none focus:border-red-500 focus:ring-2 focus:ring-red-100"
                            placeholder="Ketik persis nama form"
                        />
                    </label>

                    <div class="flex justify-end gap-2">
                        <button
                            type="button"
                            class="rounded-full px-4 py-2 text-sm font-bold text-slate-600 hover:bg-slate-100"
                            @click="closeFormDeleteModal"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="formDeleteConfirmation !== selectedForm.title"
                            class="rounded-full bg-red-600 px-4 py-2 text-sm font-bold text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            {{ formDeleteMode === 'force' ? 'Hapus permanen' : 'Pindahkan ke Trash' }}
                        </button>
                    </div>
                </form>
            </section>
        </div>

        <div
            class="fixed bottom-8 left-8 hidden rounded-full bg-lime-100 px-4 py-3 text-sm font-black text-lime-700 shadow-sm lg:flex lg:items-center lg:gap-2"
        >
            <Sparkles class="h-4 w-4" />
            Template terbuka di tab baru
            <Send class="h-4 w-4" />
        </div>
    </main>
</template>

<style scoped>
button[aria-label],
.with-tooltip {
    position: relative;
}

button[aria-label]::after,
.with-tooltip::after {
    content: attr(aria-label);
    pointer-events: none;
    position: absolute;
    left: 50%;
    top: calc(100% + 8px);
    z-index: 60;
    max-width: 180px;
    transform: translate(-50%, -4px);
    white-space: nowrap;
    border-radius: 8px;
    background: #0f172a;
    padding: 6px 8px;
    font-size: 11px;
    font-weight: 700;
    line-height: 1;
    color: white;
    opacity: 0;
    box-shadow: 0 10px 25px rgb(15 23 42 / 18%);
    transition:
        opacity 120ms ease,
        transform 120ms ease;
}

button[aria-label]:hover::after,
button[aria-label]:focus-visible::after,
.with-tooltip:hover::after,
.with-tooltip:focus-visible::after {
    transform: translate(-50%, 0);
    opacity: 1;
}
</style>
