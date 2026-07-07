<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { formTemplates } from '@/constants/dashboard';
import { useToast } from '@/composables/useToast';
import type { QuizFolder, RecentForm } from '@/types/quiz';
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
import { computed, ref, onMounted } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { getInitials } from '@/composables/useInitials';
import { usePage, useForm } from '@inertiajs/vue3';
import { LogOut, User, Key, MessageSquare, Heart, Coins, Award, Shield, Wallet, History } from 'lucide-vue-next';
import axios from 'axios';

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
const { toastMessage, showToast } = useToast();
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

const visibleTemplates = computed(() => (isTemplateGalleryOpen.value ? formTemplates : formTemplates.slice(0, 3)));
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

// ==========================================
// DEVELOPER SUPPORT & PROFILE SETTINGS LOGIC
// ==========================================
const page = usePage<any>();
const user = computed(() => page.props.auth.user);

// Modals reactive states
const isProfileModalOpen = ref(false);
const profileModalTab = ref<'profile' | 'password'>('profile');

const isSuggestionModalOpen = ref(false);
const suggestionForm = useForm({
    subject: '',
    message: '',
});

const isDonationModalOpen = ref(false);
const donationModalTab = ref<'donate' | 'developer'>('donate');

// Donation flow states
const donorName = ref('');
const donationAmount = ref(10000);
const customAmount = ref('');
const donationMessage = ref('');
const activeDonation = ref<any>(null);
const donationSuccessDetails = ref<any>(null);
const isConfettiActive = ref(false);
const confettiCanvasRef = ref<HTMLCanvasElement | null>(null);

// Developer panel stats and logs
const devStats = ref({
    total_received: 0,
    total_withdrawn: 0,
    balance: 0,
    supporters: [] as any[],
    withdrawals: [] as any[],
    suggestions: [] as any[],
});

const isAdminModalOpen = ref(false);
const adminModalTab = ref<'finance' | 'suggestions'>('finance');

function openAdminModal(): void {
    adminModalTab.value = 'finance';
    isAdminModalOpen.value = true;
    fetchDevStats();
}

// Developer withdrawal form
const withdrawalForm = useForm({
    amount: '',
    bank_name: 'BCA',
    account_number: '',
    account_name: '',
});

// Profile & Password forms
const profileForm = useForm({
    name: '',
    email: '',
});

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

function openProfileModal(): void {
    profileForm.name = user.value?.name || '';
    profileForm.email = user.value?.email || '';
    profileForm.clearErrors();
    profileModalTab.value = 'profile';
    isProfileModalOpen.value = true;
}

function openPasswordModal(): void {
    passwordForm.reset();
    passwordForm.clearErrors();
    profileModalTab.value = 'password';
    isProfileModalOpen.value = true;
}

function openSuggestionModal(): void {
    suggestionForm.reset();
    suggestionForm.clearErrors();
    isSuggestionModalOpen.value = true;
}

function openDonationModal(): void {
    donorName.value = user.value?.name || '';
    donationAmount.value = 10000;
    customAmount.value = '';
    donationMessage.value = '';
    activeDonation.value = null;
    donationSuccessDetails.value = null;
    donationModalTab.value = 'donate';
    isDonationModalOpen.value = true;
    fetchDevStats();
}

function fetchDevStats(): void {
    axios.get(route('support.stats'))
        .then(response => {
            devStats.value = response.data;
        })
        .catch(err => {
            console.error('Gagal mengambil statistik developer:', err);
        });
}

function submitProfileForm(): void {
    profileForm.patch(route('profile.update'), {
        preserveScroll: true,
        onSuccess: () => {
            showToast('Profil berhasil diperbarui!');
            isProfileModalOpen.value = false;
        },
    });
}

function submitPasswordForm(): void {
    passwordForm.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => {
            showToast('Password berhasil diperbarui!');
            passwordForm.reset();
            isProfileModalOpen.value = false;
        },
        onError: () => {
            passwordForm.reset('password', 'password_confirmation');
        }
    });
}

function submitSuggestion(): void {
    suggestionForm.post(route('support.suggestion'), {
        preserveScroll: true,
        onSuccess: () => {
            showToast('Saran & masukan Anda berhasil terkirim!');
            suggestionForm.reset();
            isSuggestionModalOpen.value = false;
        },
    });
}

function submitDonation(): void {
    const finalAmount = donationAmount.value === 0 ? parseInt(customAmount.value) : donationAmount.value;
    if (!finalAmount || finalAmount < 1000) {
        showToast('Nominal donasi minimal Rp 1.000');
        return;
    }

    axios.post(route('support.donate'), {
        donor_name: donorName.value,
        amount: finalAmount,
        message: donationMessage.value,
    }).then(response => {
        if (response.data?.success) {
            activeDonation.value = response.data.donation;
        } else {
            showToast('Gagal memproses donasi. Silakan coba lagi.');
        }
    }).catch(err => {
        showToast('Terjadi kesalahan. Silakan coba lagi.');
    });
}

function confirmDonationPayment(): void {
    if (!activeDonation.value) return;

    axios.post(route('support.confirm-donation', { donation: activeDonation.value.id }))
        .then(response => {
            if (response.data?.success) {
                donationSuccessDetails.value = response.data.donation;
                activeDonation.value = null;
                showToast('Pembayaran Donasi Berhasil! Terima kasih.');
                triggerConfetti();
                fetchDevStats();
            }
        })
        .catch(err => {
            showToast('Konfirmasi gagal. Silakan coba lagi.');
        });
}

function submitWithdrawal(): void {
    const amountVal = parseInt(withdrawalForm.amount);
    if (!amountVal || amountVal < 5000) {
        showToast('Nominal penarikan minimal Rp 5.000');
        return;
    }
    if (amountVal > devStats.value.balance) {
        showToast('Saldo Anda tidak mencukupi.');
        return;
    }

    withdrawalForm.post(route('support.withdraw'), {
        preserveScroll: true,
        onSuccess: () => {
            showToast('Penarikan saldo berhasil diproses!');
            withdrawalForm.reset('amount');
            fetchDevStats();
        },
        onError: (errs) => {
            if (errs.amount) {
                showToast(errs.amount);
            }
        }
    });
}

function numberFormat(value: number | string): string {
    if (!value) return '0';
    return Number(value).toLocaleString('id-ID');
}

// Confetti animation using Canvas
let confettiStopFn: (() => void) | null = null;
function triggerConfetti(): void {
    isConfettiActive.value = true;
    setTimeout(() => {
        if (confettiCanvasRef.value) {
            const canvas = confettiCanvasRef.value;
            const ctx = canvas.getContext('2d');
            if (!ctx) return;
            canvas.width = canvas.parentElement?.clientWidth || window.innerWidth;
            canvas.height = canvas.parentElement?.clientHeight || 600;
            
            let particles: any[] = [];
            const colors = ['#f43f5e', '#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899'];
            
            for (let i = 0; i < 120; i++) {
                particles.push({
                    x: Math.random() * canvas.width,
                    y: Math.random() * canvas.height - canvas.height,
                    r: Math.random() * 5 + 3,
                    d: Math.random() * canvas.height,
                    color: colors[Math.floor(Math.random() * colors.length)],
                    tilt: Math.random() * 10 - 5,
                    tiltAngleIncremental: Math.random() * 0.07 + 0.02,
                    tiltAngle: 0
                });
            }
            
            let animationFrameId: number;
            function draw() {
                if (!ctx) return;
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                let active = false;
                particles.forEach((p, idx) => {
                    p.tiltAngle += p.tiltAngleIncremental;
                    p.y += (Math.cos(p.d) + 3 + p.r / 2) / 2;
                    p.x += Math.sin(p.tiltAngle);
                    p.tilt = Math.sin(p.tiltAngle - idx / 3) * 15;
                    
                    if (p.y < canvas.height) {
                        active = true;
                    }
                    
                    ctx.beginPath();
                    ctx.lineWidth = p.r;
                    ctx.strokeStyle = p.color;
                    ctx.moveTo(p.x + p.tilt + p.r / 2, p.y);
                    ctx.lineTo(p.x + p.tilt, p.y + p.tilt + p.r / 2);
                    ctx.stroke();
                });
                
                if (active && isConfettiActive.value) {
                    animationFrameId = requestAnimationFrame(draw);
                } else {
                    isConfettiActive.value = false;
                }
            }
            
            draw();
            confettiStopFn = () => {
                cancelAnimationFrame(animationFrameId);
                isConfettiActive.value = false;
            };
        }
    }, 100);
}

function closeDonationModal(): void {
    if (confettiStopFn) {
        confettiStopFn();
        confettiStopFn = null;
    }
    isConfettiActive.value = false;
    isDonationModalOpen.value = false;
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
                    <DropdownMenu>
                        <DropdownMenuTrigger :as-child="true">
                            <button
                                type="button"
                                class="relative flex h-9 items-center gap-2 rounded-full border border-slate-200 bg-white px-2.5 py-1 text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2"
                                aria-label="Profile menu"
                            >
                                <Avatar class="h-6 w-6 overflow-hidden rounded-full border border-slate-100">
                                    <AvatarImage :src="user?.avatar" :alt="user?.name" />
                                    <AvatarFallback class="bg-indigo-50 font-black text-indigo-700 flex items-center justify-center text-[10px] w-full h-full">
                                        {{ getInitials(user?.name) }}
                                    </AvatarFallback>
                                </Avatar>
                                <span class="hidden max-w-[80px] truncate text-xs font-semibold sm:inline">{{ user?.name }}</span>
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-56 mt-1 rounded-2xl shadow-xl border border-slate-200 bg-white p-2 z-50">
                            <DropdownMenuLabel class="px-2.5 py-2 font-normal">
                                <div class="flex flex-col space-y-1">
                                    <p class="text-xs font-bold text-slate-900 leading-none">{{ user?.name }}</p>
                                    <p class="text-[10px] text-slate-500 leading-none truncate mt-1">{{ user?.email }}</p>
                                </div>
                            </DropdownMenuLabel>
                            <DropdownMenuSeparator class="my-1.5 border-t border-slate-100" />
                            <DropdownMenuGroup>
                                <DropdownMenuItem @select="openProfileModal" class="flex items-center gap-2 rounded-xl px-2.5 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 cursor-pointer">
                                    <User class="h-3.5 w-3.5 text-indigo-500" />
                                    <span>Ubah Profil</span>
                                </DropdownMenuItem>
                                <DropdownMenuItem @select="openPasswordModal" class="flex items-center gap-2 rounded-xl px-2.5 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 cursor-pointer">
                                    <Key class="h-3.5 w-3.5 text-amber-500" />
                                    <span>Ubah Password</span>
                                </DropdownMenuItem>
                            </DropdownMenuGroup>
                            <DropdownMenuSeparator class="my-1.5 border-t border-slate-100" />
                            <DropdownMenuGroup v-if="user?.is_admin">
                                <DropdownMenuItem @select="openAdminModal" class="flex items-center gap-2 rounded-xl px-2.5 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 cursor-pointer">
                                    <Shield class="h-3.5 w-3.5 text-pink-500" />
                                    <span>Panel Admin (Donasi & Saran)</span>
                                </DropdownMenuItem>
                            </DropdownMenuGroup>
                            <DropdownMenuGroup v-else>
                                <DropdownMenuItem @select="openSuggestionModal" class="flex items-center gap-2 rounded-xl px-2.5 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 cursor-pointer">
                                    <MessageSquare class="h-3.5 w-3.5 text-emerald-500" />
                                    <span>Kirim Saran & Masukan</span>
                                </DropdownMenuItem>
                                <DropdownMenuItem @select="openDonationModal" class="flex items-center gap-2 rounded-xl px-2.5 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 cursor-pointer">
                                    <Heart class="h-3.5 w-3.5 text-pink-500 fill-pink-500" />
                                    <span>Dukung Developer</span>
                                </DropdownMenuItem>
                            </DropdownMenuGroup>
                            <DropdownMenuSeparator class="my-1.5 border-t border-slate-100" />
                            <DropdownMenuItem :as-child="true">
                                <Link
                                    method="post"
                                    :href="route('logout')"
                                    as="button"
                                    class="flex w-full items-center gap-2 rounded-xl px-2.5 py-2 text-xs font-bold text-red-600 hover:bg-red-50 hover:text-red-700 cursor-pointer"
                                >
                                    <LogOut class="h-3.5 w-3.5 text-red-500" />
                                    <span>Keluar</span>
                                </Link>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
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

        <!-- MODAL UBAH PROFIL & PASSWORD -->
        <div v-if="isProfileModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 backdrop-blur-xs px-4">
            <section class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl animate-in fade-in zoom-in-95 duration-150">
                <div class="mb-4 flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900">Pengaturan Akun</h2>
                        <p class="mt-1 text-xs text-slate-500">Perbarui informasi profil atau ganti password akun Anda.</p>
                    </div>
                    <button type="button" class="rounded-full p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700" @click="isProfileModalOpen = false">
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <!-- Tab Selector -->
                <div class="mb-5 flex border-b border-slate-100">
                    <button
                        type="button"
                        @click="profileModalTab = 'profile'"
                        :class="[
                            'flex-1 pb-3 text-sm font-bold border-b-2 transition-all',
                            profileModalTab === 'profile' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-700'
                        ]"
                    >
                        Profil
                    </button>
                    <button
                        type="button"
                        @click="profileModalTab = 'password'"
                        :class="[
                            'flex-1 pb-3 text-sm font-bold border-b-2 transition-all',
                            profileModalTab === 'password' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-700'
                        ]"
                    >
                        Password
                    </button>
                </div>

                <!-- Tab 1: Profile Form -->
                <form v-if="profileModalTab === 'profile'" class="space-y-4" @submit.prevent="submitProfileForm">
                    <div class="space-y-1.5 text-left">
                        <label class="text-xs font-bold text-slate-700">Nama Lengkap</label>
                        <input
                            v-model="profileForm.name"
                            type="text"
                            class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
                            required
                        />
                        <span v-if="profileForm.errors.name" class="text-xs text-red-500 font-semibold">{{ profileForm.errors.name }}</span>
                    </div>

                    <div class="space-y-1.5 text-left">
                        <label class="text-xs font-bold text-slate-700">Alamat Email</label>
                        <input
                            v-model="profileForm.email"
                            type="email"
                            class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
                            required
                        />
                        <span v-if="profileForm.errors.email" class="text-xs text-red-500 font-semibold">{{ profileForm.errors.email }}</span>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button
                            type="button"
                            class="rounded-xl px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100"
                            @click="isProfileModalOpen = false"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="profileForm.processing"
                            class="rounded-xl bg-indigo-600 px-5 py-2 text-xs font-bold text-white hover:bg-indigo-700 disabled:opacity-50"
                        >
                            Simpan Perubahan
                        </button>
                    </div>
                </form>

                <!-- Tab 2: Password Form -->
                <form v-if="profileModalTab === 'password'" class="space-y-4" @submit.prevent="submitPasswordForm">
                    <div class="space-y-1.5 text-left">
                        <label class="text-xs font-bold text-slate-700">Password Saat Ini</label>
                        <input
                            v-model="passwordForm.current_password"
                            type="password"
                            class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
                            required
                        />
                        <span v-if="passwordForm.errors.current_password" class="text-xs text-red-500 font-semibold">{{ passwordForm.errors.current_password }}</span>
                    </div>

                    <div class="space-y-1.5 text-left">
                        <label class="text-xs font-bold text-slate-700">Password Baru</label>
                        <input
                            v-model="passwordForm.password"
                            type="password"
                            class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
                            required
                        />
                        <span v-if="passwordForm.errors.password" class="text-xs text-red-500 font-semibold">{{ passwordForm.errors.password }}</span>
                    </div>

                    <div class="space-y-1.5 text-left">
                        <label class="text-xs font-bold text-slate-700">Konfirmasi Password Baru</label>
                        <input
                            v-model="passwordForm.password_confirmation"
                            type="password"
                            class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100"
                            required
                        />
                        <span v-if="passwordForm.errors.password_confirmation" class="text-xs text-red-500 font-semibold">{{ passwordForm.errors.password_confirmation }}</span>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button
                            type="button"
                            class="rounded-xl px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100"
                            @click="isProfileModalOpen = false"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="passwordForm.processing"
                            class="rounded-xl bg-indigo-600 px-5 py-2 text-xs font-bold text-white hover:bg-indigo-700 disabled:opacity-50"
                        >
                            Ganti Password
                        </button>
                    </div>
                </form>
            </section>
        </div>

        <!-- MODAL SARAN & MASUKAN -->
        <div v-if="isSuggestionModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 backdrop-blur-xs px-4">
            <section class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl animate-in fade-in zoom-in-95 duration-150">
                <div class="mb-4 flex items-start justify-between gap-4">
                    <div class="flex items-center gap-2.5">
                        <div class="rounded-xl bg-emerald-50 p-2 text-emerald-600">
                            <MessageSquare class="h-5 w-5" />
                        </div>
                        <div>
                            <h2 class="text-lg font-bold text-slate-900">Saran & Masukan</h2>
                            <p class="mt-0.5 text-xs text-slate-500">Kirim feedback Anda untuk membantu kami menjadi lebih baik.</p>
                        </div>
                    </div>
                    <button type="button" class="rounded-full p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700" @click="isSuggestionModalOpen = false">
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <form class="space-y-4 text-left" @submit.prevent="submitSuggestion">
                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-slate-700">Subjek / Topik</label>
                        <input
                            v-model="suggestionForm.subject"
                            type="text"
                            class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                            placeholder="Mis. Masukan Fitur Baru"
                            required
                        />
                        <span v-if="suggestionForm.errors.subject" class="text-xs text-red-500 font-semibold">{{ suggestionForm.errors.subject }}</span>
                    </div>

                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-slate-700">Detail Saran & Feedback</label>
                        <textarea
                            v-model="suggestionForm.message"
                            rows="4"
                            class="w-full rounded-xl border border-slate-200 p-3 text-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 resize-none animate-none"
                            placeholder="Tuliskan ide, masukan, atau kritik membangun Anda..."
                            required
                        ></textarea>
                        <span v-if="suggestionForm.errors.message" class="text-xs text-red-500 font-semibold">{{ suggestionForm.errors.message }}</span>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button
                            type="button"
                            class="rounded-xl px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100"
                            @click="isSuggestionModalOpen = false"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="suggestionForm.processing"
                            class="rounded-xl bg-emerald-600 px-5 py-2 text-xs font-bold text-white hover:bg-emerald-700 disabled:opacity-50"
                        >
                            Kirim Masukan
                        </button>
                    </div>
                </form>
            </section>
        </div>

        <!-- MODAL DUKUNGAN DEVELOPER (DONASI) -->
        <div v-if="isDonationModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 backdrop-blur-xs px-4">
            <section class="relative w-full max-w-lg rounded-3xl border border-slate-200 bg-white shadow-2xl animate-in fade-in zoom-in-95 duration-150 overflow-hidden">
                <!-- Canvas for confetti -->
                <canvas v-if="isConfettiActive" ref="confettiCanvasRef" class="absolute inset-0 pointer-events-none z-50 w-full h-full"></canvas>

                <!-- Modal Header -->
                <div class="border-b border-slate-100 bg-slate-50/50 p-5 flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <div class="rounded-xl bg-pink-50 p-2 text-pink-600">
                            <Heart class="h-5 w-5 fill-pink-500" />
                        </div>
                        <div>
                            <h2 class="text-lg font-extrabold text-slate-900">Dukung Developer</h2>
                            <p class="text-xs text-slate-500">Dukung keberlanjutan dan pengembangan Alsenform.</p>
                        </div>
                    </div>
                    <button type="button" class="rounded-full p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700 z-10" @click="closeDonationModal">
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <!-- Modal Body Scrollable -->
                <div class="p-6 max-h-[65vh] overflow-y-auto bg-white text-left">
                    <!-- VIEW A: PAYMENT SUCCESS CARD -->
                    <div v-if="donationSuccessDetails" class="text-center py-6 space-y-4 animate-in fade-in slide-in-from-bottom-4 duration-300">
                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 shadow-inner">
                            <Award class="h-8 w-8" />
                        </div>
                        <div class="space-y-1">
                            <h3 class="text-xl font-black text-slate-900">Terima Kasih, {{ donationSuccessDetails.donor_name }}!</h3>
                            <p class="text-sm text-slate-500">Donasi Anda sebesar <span class="font-extrabold text-emerald-600">Rp {{ numberFormat(donationSuccessDetails.amount) }}</span> telah sukses kami terima.</p>
                        </div>
                        <div class="rounded-2xl bg-indigo-50/50 p-4 border border-indigo-100 max-w-sm mx-auto text-left space-y-2">
                            <div class="text-[10px] uppercase tracking-wider font-extrabold text-indigo-500">Pesan Dukungan Anda:</div>
                            <p class="text-xs font-medium text-indigo-950 italic">"{{ donationSuccessDetails.message || 'Dukungan tanpa pesan.' }}"</p>
                        </div>
                        <p class="text-xs text-slate-400 font-semibold text-center">Dukungan Anda sangat berarti untuk memelihara server dan terus merilis fitur-fitur baru.</p>
                        <div class="pt-4 flex justify-center">
                            <button
                                type="button"
                                class="rounded-full bg-slate-900 px-6 py-2.5 text-xs font-bold text-white hover:bg-slate-800 transition-colors"
                                @click="donationSuccessDetails = null"
                            >
                                Donasi Lagi
                            </button>
                        </div>
                    </div>

                    <!-- VIEW B: QRIS SCREEN -->
                    <div v-else-if="activeDonation" class="space-y-5 animate-in fade-in zoom-in-95 duration-200">
                        <div class="rounded-2xl bg-pink-50/50 border border-pink-100 p-4 text-center">
                            <div class="text-xs font-bold text-pink-700">QRIS Pembayaran</div>
                            <div class="text-2xl font-black text-slate-900 mt-1">Rp {{ numberFormat(activeDonation.amount) }}</div>
                            <div class="text-[10px] font-semibold text-slate-500 mt-0.5">Ref: {{ activeDonation.payment_reference }}</div>
                        </div>

                        <!-- Mock QRIS QR Code -->
                        <div class="mx-auto max-w-[200px] border border-slate-200 rounded-2xl p-4 bg-white shadow-sm flex flex-col items-center justify-center space-y-2">
                            <!-- QRIS Logo mock -->
                            <div class="flex items-center gap-1 font-black text-slate-800 text-[10px] pb-1 border-b border-slate-100 w-full justify-center">
                                <span class="text-red-500 font-black">Q</span>
                                <span class="text-indigo-500 font-black">R</span>
                                <span class="text-amber-500 font-black">I</span>
                                <span class="text-teal-500 font-black">S</span>
                                <span class="text-[8px] text-slate-400 font-bold ml-1">Merchant</span>
                            </div>
                            <!-- Mock dynamic QR representation -->
                            <div class="relative w-36 h-36 border border-slate-100 p-1 rounded-lg bg-slate-50 flex items-center justify-center">
                                <svg viewBox="0 0 100 100" class="w-full h-full text-slate-800 fill-current opacity-90">
                                    <rect x="0" y="0" width="25" height="25" />
                                    <rect x="3" y="3" width="19" height="19" fill="white" />
                                    <rect x="7" y="7" width="11" height="11" />
                                    <rect x="75" y="0" width="25" height="25" />
                                    <rect x="78" y="3" width="19" height="19" fill="white" />
                                    <rect x="82" y="7" width="11" height="11" />
                                    <rect x="0" y="75" width="25" height="25" />
                                    <rect x="3" y="78" width="19" height="19" fill="white" />
                                    <rect x="7" y="82" width="11" height="11" />
                                    <rect x="35" y="5" width="8" height="8" />
                                    <rect x="50" y="0" width="12" height="6" />
                                    <rect x="40" y="20" width="6" height="14" />
                                    <rect x="5" y="35" width="12" height="12" />
                                    <rect x="25" y="45" width="20" height="8" />
                                    <rect x="60" y="30" width="25" height="12" />
                                    <rect x="85" y="45" width="10" height="18" />
                                    <rect x="35" y="65" width="15" height="15" />
                                    <rect x="55" y="75" width="12" height="18" />
                                    <rect x="70" y="65" width="8" height="8" />
                                    <rect x="30" y="85" width="25" height="10" />
                                    <rect x="80" y="85" width="15" height="12" />
                                </svg>
                                <div class="absolute inset-0 bg-gradient-to-tr from-transparent via-white/5 to-transparent flex items-center justify-center">
                                    <div class="h-6 w-6 rounded-md bg-white border border-slate-100 flex items-center justify-center text-[8px] font-black text-pink-600 shadow-xs">AL</div>
                                </div>
                            </div>
                            <div class="text-[8px] font-extrabold text-slate-400">ALSENFORM DEV SUPPORT</div>
                        </div>

                        <div class="text-xs text-slate-500 leading-relaxed text-center max-w-sm mx-auto">
                            Scan QRIS di atas dengan aplikasi pembayaran Anda. Setelah melakukan pembayaran, klik tombol simulasi di bawah untuk verifikasi pembayaran secara instan.
                        </div>

                        <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
                            <button
                                type="button"
                                class="rounded-xl px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100"
                                @click="activeDonation = null"
                            >
                                Kembali
                            </button>
                            <button
                                type="button"
                                class="rounded-xl bg-emerald-600 px-5 py-2.5 text-xs font-extrabold text-white hover:bg-emerald-700 shadow-md flex items-center gap-1.5 transition-colors"
                                @click="confirmDonationPayment"
                            >
                                <Coins class="h-4 w-4" />
                                Simulasikan Bayar Sukses
                            </button>
                        </div>
                    </div>

                    <!-- VIEW C: INPUT DONATION FORM -->
                    <div v-else class="space-y-5 animate-in fade-in duration-200">
                        <div class="rounded-2xl bg-gradient-to-br from-pink-500 via-purple-500 to-indigo-600 p-5 text-white shadow-md">
                            <div class="text-xs font-bold opacity-80 uppercase tracking-widest">Dukung Developer</div>
                            <p class="text-[10px] opacity-90 mt-2 leading-relaxed font-medium">Bantuan donasi Anda digunakan untuk biaya operasional hosting, domain kustom, serta kelancaran pemeliharaan aplikasi Alsenform.</p>
                        </div>

                        <form class="space-y-4" @submit.prevent="submitDonation">
                            <div class="space-y-1.5">
                                <label class="text-xs font-bold text-slate-700">Nama Donatur</label>
                                <input
                                    v-model="donorName"
                                    type="text"
                                    class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-sm outline-none focus:border-pink-500 focus:ring-2 focus:ring-pink-100"
                                    placeholder="Tuliskan nama Anda"
                                    required
                                />
                            </div>

                            <div class="space-y-1.5">
                                <label class="text-xs font-bold text-slate-700">Nominal Donasi</label>
                                <!-- Preset Grid -->
                                <div class="grid grid-cols-4 gap-2 mb-2">
                                    <button
                                        type="button"
                                        v-for="amt in [10000, 25000, 50000, 100000]"
                                        :key="amt"
                                        @click="
                                            donationAmount = amt;
                                            customAmount = '';
                                        "
                                        :class="[
                                            'h-9 rounded-xl text-xs font-bold border transition-all',
                                            donationAmount === amt ? 'bg-pink-50 border-pink-500 text-pink-600 ring-2 ring-pink-100' : 'border-slate-200 bg-white hover:bg-slate-50 text-slate-700'
                                        ]"
                                    >
                                        Rp {{ numberFormat(amt) }}
                                    </button>
                                    <button
                                        type="button"
                                        @click="donationAmount = 0"
                                        :class="[
                                            'h-9 rounded-xl text-xs font-bold border col-span-4 transition-all',
                                            donationAmount === 0 ? 'bg-pink-50 border-pink-500 text-pink-600 ring-2 ring-pink-100' : 'border-slate-200 bg-white hover:bg-slate-50 text-slate-700'
                                        ]"
                                    >
                                        Masukkan Nominal Lain
                                    </button>
                                </div>
                                <div v-if="donationAmount === 0" class="relative">
                                    <span class="absolute left-3.5 top-2 text-sm font-bold text-slate-400">Rp</span>
                                    <input
                                        v-model="customAmount"
                                        type="number"
                                        min="1000"
                                        class="h-10 w-full rounded-xl border border-slate-200 pl-10 pr-3.5 text-sm outline-none focus:border-pink-500 focus:ring-2 focus:ring-pink-100"
                                        placeholder="Min. Rp 1.000"
                                        required
                                    />
                                </div>
                            </div>

                            <div class="space-y-1.5">
                                <label class="text-xs font-bold text-slate-700">Pesan Dukungan (Opsional)</label>
                                <input
                                    v-model="donationMessage"
                                    type="text"
                                    class="h-10 w-full rounded-xl border border-slate-200 px-3.5 text-sm outline-none focus:border-pink-500 focus:ring-2 focus:ring-pink-100"
                                    placeholder="Pesan untuk Developer (Mis. Semangat bang!)"
                                />
                            </div>

                            <div class="flex justify-end gap-2 pt-2">
                                <button
                                    type="button"
                                    class="rounded-xl px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100"
                                    @click="isDonationModalOpen = false"
                                >
                                    Batal
                                </button>
                                <button
                                    type="submit"
                                    class="rounded-xl bg-pink-600 px-5 py-2 text-xs font-extrabold text-white hover:bg-pink-700 shadow-md transition-colors"
                                >
                                    Lanjut ke QRIS
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </section>
        </div>

        <!-- MODAL PANEL ADMIN (DONASI & SARAN) - KHUSUS SUPERADMIN -->
        <div v-if="isAdminModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 backdrop-blur-xs px-4">
            <section class="w-full max-w-3xl rounded-3xl border border-slate-200 bg-white shadow-2xl animate-in fade-in zoom-in-95 duration-150 overflow-hidden">
                <!-- Modal Header -->
                <div class="border-b border-slate-100 bg-slate-50/50 p-5 flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <div class="rounded-xl bg-indigo-50 p-2 text-indigo-600">
                            <Shield class="h-5 w-5" />
                        </div>
                        <div>
                            <h2 class="text-lg font-extrabold text-slate-900">Panel Administrator</h2>
                            <p class="text-xs text-slate-500">Kelola keuangan donasi dan tinjau saran masukan dari pengguna.</p>
                        </div>
                    </div>
                    <button type="button" class="rounded-full p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700" @click="isAdminModalOpen = false">
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <!-- Tabs -->
                <div class="flex border-b border-slate-100 px-5 bg-white">
                    <button
                        type="button"
                        @click="adminModalTab = 'finance'"
                        :class="[
                            'py-3.5 text-xs font-extrabold border-b-2 mr-6 transition-all flex items-center gap-1.5',
                            adminModalTab === 'finance' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-700'
                        ]"
                    >
                        <Wallet class="h-3.5 w-3.5" />
                        Keuangan & Penarikan
                    </button>
                    <button
                        type="button"
                        @click="adminModalTab = 'suggestions'"
                        :class="[
                            'py-3.5 text-xs font-extrabold border-b-2 transition-all flex items-center gap-1.5',
                            adminModalTab === 'suggestions' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-700'
                        ]"
                    >
                        <MessageSquare class="h-3.5 w-3.5" />
                        Saran & Masukan ({{ devStats.suggestions?.length || 0 }})
                    </button>
                </div>

                <!-- Scrollable Body -->
                <div class="p-6 max-h-[60vh] overflow-y-auto bg-white text-left">
                    
                    <!-- TAB 1: FINANCE -->
                    <div v-if="adminModalTab === 'finance'" class="space-y-6">
                        <!-- Stats Cards -->
                        <div class="grid grid-cols-3 gap-4 text-center">
                            <div class="rounded-2xl border border-slate-100 bg-slate-50/50 p-4">
                                <div class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Total Masuk</div>
                                <div class="text-base font-extrabold text-slate-800 mt-1">Rp {{ numberFormat(devStats.total_received) }}</div>
                            </div>
                            <div class="rounded-2xl border border-slate-100 bg-slate-50/50 p-4">
                                <div class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Telah Ditarik</div>
                                <div class="text-base font-extrabold text-slate-800 mt-1">Rp {{ numberFormat(devStats.total_withdrawn) }}</div>
                            </div>
                            <div class="rounded-2xl border border-indigo-100 bg-indigo-50/30 p-4">
                                <div class="text-[10px] font-bold text-indigo-700 uppercase tracking-wider">Saldo Tersedia</div>
                                <div class="text-base font-black text-indigo-700 mt-1">Rp {{ numberFormat(devStats.balance) }}</div>
                            </div>
                        </div>

                        <!-- Tarik Saldo Form -->
                        <section class="rounded-2xl border border-slate-100 p-4 space-y-3 bg-slate-50/30">
                            <h3 class="text-xs font-extrabold text-slate-800 flex items-center gap-1.5">
                                <Coins class="h-4 w-4 text-indigo-500" />
                                Form Pencairan Saldo
                            </h3>
                            
                            <form class="space-y-3" @submit.prevent="submitWithdrawal">
                                <div class="grid grid-cols-2 gap-3">
                                    <div class="space-y-1">
                                        <label class="text-[10px] font-bold text-slate-700 text-left block">Bank / E-Wallet</label>
                                        <select
                                            v-model="withdrawalForm.bank_name"
                                            class="h-9 w-full rounded-xl border border-slate-200 px-2 text-xs outline-none bg-white focus:border-indigo-500"
                                            required
                                        >
                                            <option value="BCA">BCA</option>
                                            <option value="Mandiri">Mandiri</option>
                                            <option value="BRI">BRI</option>
                                            <option value="BNI">BNI</option>
                                            <option value="OVO">OVO</option>
                                            <option value="GoPay">GoPay</option>
                                            <option value="Dana">Dana</option>
                                            <option value="LinkAja">LinkAja</option>
                                        </select>
                                    </div>
                                    <div class="space-y-1">
                                        <label class="text-[10px] font-bold text-slate-700 text-left block">Nominal Penarikan (Rp)</label>
                                        <input
                                            v-model="withdrawalForm.amount"
                                            type="number"
                                            min="5000"
                                            :max="devStats.balance"
                                            class="h-9 w-full rounded-xl border border-slate-200 px-3 text-xs outline-none focus:border-indigo-500"
                                            placeholder="Min. Rp 5.000"
                                            required
                                        />
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div class="space-y-1">
                                        <label class="text-[10px] font-bold text-slate-700 text-left block">Nomor Rekening / No. HP</label>
                                        <input
                                            v-model="withdrawalForm.account_number"
                                            type="text"
                                            class="h-9 w-full rounded-xl border border-slate-200 px-3 text-xs outline-none focus:border-indigo-500"
                                            placeholder="Mis. 8010293021"
                                            required
                                        />
                                    </div>
                                    <div class="space-y-1">
                                        <label class="text-[10px] font-bold text-slate-700 text-left block">Nama Pemilik Rekening</label>
                                        <input
                                            v-model="withdrawalForm.account_name"
                                            type="text"
                                            class="h-9 w-full rounded-xl border border-slate-200 px-3 text-xs outline-none focus:border-indigo-500"
                                            placeholder="Mis. John Doe"
                                            required
                                        />
                                    </div>
                                </div>

                                <div class="flex justify-end pt-1">
                                    <button
                                        type="submit"
                                        :disabled="withdrawalForm.processing || devStats.balance < 5000"
                                        class="rounded-xl bg-slate-900 px-4 py-2 text-xs font-bold text-white hover:bg-slate-800 disabled:opacity-40 transition-colors"
                                    >
                                        Tarik Saldo Sekarang
                                    </button>
                                </div>
                            </form>
                        </section>

                        <!-- Supporters & Withdrawal Logs -->
                        <div class="grid grid-cols-2 gap-4 border-t border-slate-100 pt-4">
                            <!-- Supporters log -->
                            <div class="space-y-2">
                                <h4 class="text-xs font-extrabold text-slate-800 flex items-center gap-1">
                                    <Heart class="h-3.5 w-3.5 text-pink-500" />
                                    Donatur Terbaru
                                </h4>
                                <div class="space-y-2 max-h-48 overflow-y-auto pr-1">
                                    <div v-if="devStats.supporters?.length === 0" class="text-[10px] text-slate-400 font-semibold py-3 text-center">Belum ada donatur.</div>
                                    <div
                                        v-for="sup in devStats.supporters"
                                        :key="sup.id"
                                        class="rounded-xl bg-slate-50 p-2.5 text-[10px] border border-slate-100"
                                    >
                                        <div class="flex justify-between font-bold text-slate-800">
                                            <span>{{ sup.donor_name }}</span>
                                            <span class="text-pink-600 flex items-center">Rp {{ numberFormat(sup.amount) }}</span>
                                        </div>
                                        <p v-if="sup.message" class="text-slate-500 mt-1 italic leading-normal">"{{ sup.message }}"</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Withdrawal logs -->
                            <div class="space-y-2">
                                <h4 class="text-xs font-extrabold text-slate-800 flex items-center gap-1">
                                    <History class="h-3.5 w-3.5 text-indigo-500" />
                                    Riwayat Penarikan
                                </h4>
                                <div class="space-y-2 max-h-48 overflow-y-auto pr-1">
                                    <div v-if="devStats.withdrawals?.length === 0" class="text-[10px] text-slate-400 font-semibold py-3 text-center">Belum ada riwayat penarikan.</div>
                                    <div
                                        v-for="wd in devStats.withdrawals"
                                        :key="wd.id"
                                        class="rounded-xl bg-slate-50 p-2.5 text-[10px] border border-slate-100 flex justify-between items-center"
                                    >
                                        <div class="text-left">
                                            <span class="font-bold text-slate-800 block">{{ wd.bank_name }} - {{ wd.account_number }}</span>
                                            <span class="text-[8px] text-slate-400 block mt-0.5">{{ wd.account_name }}</span>
                                        </div>
                                        <span class="font-extrabold text-slate-700">-Rp {{ numberFormat(wd.amount) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 2: SUGGESTIONS -->
                    <div v-if="adminModalTab === 'suggestions'" class="space-y-4">
                        <div v-if="devStats.suggestions?.length === 0" class="text-xs text-slate-400 font-semibold py-8 text-center bg-slate-50/50 rounded-2xl border border-dashed border-slate-200">
                            Belum ada saran & masukan dari user.
                        </div>
                        <div v-else class="space-y-3">
                            <div
                                v-for="sug in devStats.suggestions"
                                :key="sug.id"
                                class="rounded-2xl border border-slate-100 bg-slate-50/60 p-4 transition hover:bg-slate-50"
                            >
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <h4 class="text-sm font-bold text-slate-900">{{ sug.subject }}</h4>
                                        <div class="flex items-center gap-2 mt-1 text-[10px] text-slate-500 font-semibold">
                                            <span class="text-indigo-600 font-bold">{{ sug.user?.name }}</span>
                                            <span>({{ sug.user?.email }})</span>
                                            <span class="text-slate-300">•</span>
                                            <span>{{ new Date(sug.created_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) }}</span>
                                        </div>
                                    </div>
                                </div>
                                <p class="text-xs text-slate-600 mt-3 bg-white border border-slate-100 rounded-xl p-3 leading-relaxed whitespace-pre-wrap">
                                    {{ sug.message }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
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
