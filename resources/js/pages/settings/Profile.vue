<script setup lang="ts">
import { TransitionRoot } from '@headlessui/vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertCircle,
    BookOpen,
    Building2,
    Camera,
    CheckCircle2,
    Download,
    GraduationCap,
    KeyRound,
    Laptop,
    Lock,
    Phone,
    Shield,
    Smartphone,
    Trash2,
    User as UserIcon,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

import DeleteUser from '@/components/DeleteUser.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useInitials } from '@/composables/useInitials';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem, type SessionItem, type SharedData, type User } from '@/types';

interface Props {
    mustVerifyEmail: boolean;
    status?: string;
    canDeleteAccount?: boolean;
    sessions?: SessionItem[];
}

const props = withDefaults(defineProps<Props>(), {
    canDeleteAccount: true,
    sessions: () => [],
});

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Pengaturan Profil',
        href: '/settings/profile',
    },
];

const page = usePage<SharedData>();
const user = page.props.auth.user as User;
const { getInitials } = useInitials();

// Avatar management
const avatarInput = ref<HTMLInputElement | null>(null);
const avatarPreview = ref<string | null>(null);

const triggerAvatarUpload = () => {
    avatarInput.value?.click();
};

const handleAvatarSelected = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        const file = target.files[0];
        form.avatar = file;
        form.remove_avatar = false;

        const reader = new FileReader();
        reader.onload = (e) => {
            avatarPreview.value = e.target?.result as string;
        };
        reader.readAsDataURL(file);
    }
};

const handleRemoveAvatar = () => {
    avatarPreview.value = null;
    form.avatar = null;
    form.remove_avatar = true;
    if (avatarInput.value) {
        avatarInput.value.value = '';
    }
};

const currentAvatarDisplay = computed(() => {
    if (avatarPreview.value) {
        return avatarPreview.value;
    }
    if (form.remove_avatar) {
        return null;
    }
    return user.avatar_url || user.avatar || null;
});

// Main profile form
const form = useForm({
    name: user.name || '',
    email: user.email || '',
    nip: user.nip || '',
    phone: user.phone || '',
    subject: user.subject || '',
    school_origin: user.school_origin || '',
    avatar: null as File | null,
    remove_avatar: false,
});

const submitProfile = () => {
    form.post(route('profile.update.post'), {
        preserveScroll: true,
        onSuccess: () => {
            avatarPreview.value = null;
        },
    });
};

// Preferences form
const prefs = user.quiz_preferences || {};
const prefForm = useForm({
    default_kkm: prefs.default_kkm ?? 75,
    default_duration: prefs.default_duration ?? 60,
    default_shuffle_questions: prefs.default_shuffle_questions ?? true,
    default_shuffle_options: prefs.default_shuffle_options ?? true,
    default_anti_cheat_blur: prefs.default_anti_cheat_blur ?? true,
    default_school_name: prefs.default_school_name ?? '',
    default_arabic_font: prefs.default_arabic_font ?? 'Amiri Quran',
});

const submitPreferences = () => {
    prefForm.patch(route('profile.preferences.update'), {
        preserveScroll: true,
    });
};

// Proctor PIN form
const pinForm = useForm({
    pin: '',
    current_password: '',
});

const submitProctorPin = () => {
    pinForm.post(route('profile.proctor-pin.update'), {
        preserveScroll: true,
        onSuccess: () => {
            pinForm.reset();
        },
    });
};

// Other sessions logout form
const logoutOtherOpen = ref(false);
const logoutOtherForm = useForm({
    password: '',
});

const submitLogoutOtherSessions = (e: Event) => {
    e.preventDefault();
    logoutOtherForm.post(route('profile.sessions.logout-other'), {
        preserveScroll: true,
        onSuccess: () => {
            logoutOtherOpen.value = false;
            logoutOtherForm.reset();
        },
    });
};

const isTeacherOrAdmin = computed(() => {
    return user.role === 'guru' || user.role === 'admin' || user.is_admin || !user.role;
});

const isStudent = computed(() => {
    return user.role === 'siswa' || (user.nis && user.role !== 'guru' && user.role !== 'admin');
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Pengaturan Profil" />

        <SettingsLayout>
            <div class="space-y-10">
                <!-- 1. IDENTITAS & FOTO PROFIL -->
                <Card class="border-border/60 shadow-sm">
                    <CardHeader class="pb-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <CardTitle class="text-lg font-semibold tracking-tight">Informasi Profil</CardTitle>
                                <CardDescription>Kelola foto profil dan data identitas akademik Anda</CardDescription>
                            </div>
                            <div>
                                <span
                                    v-if="user.role === 'guru'"
                                    class="inline-flex items-center gap-1.5 rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700 ring-1 ring-inset ring-indigo-700/20 dark:bg-indigo-950/40 dark:text-indigo-300"
                                >
                                    <GraduationCap class="h-3.5 w-3.5" /> Guru / Tenaga Pendidik
                                </span>
                                <span
                                    v-else-if="isStudent"
                                    class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-700/20 dark:bg-emerald-950/40 dark:text-emerald-300"
                                >
                                    <UserIcon class="h-3.5 w-3.5" /> Siswa Peserta Ujian
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center gap-1.5 rounded-full bg-purple-50 px-3 py-1 text-xs font-semibold text-purple-700 ring-1 ring-inset ring-purple-700/20 dark:bg-purple-950/40 dark:text-purple-300"
                                >
                                    <Shield class="h-3.5 w-3.5" /> Administrator
                                </span>
                            </div>
                        </div>
                    </CardHeader>

                    <CardContent>
                        <form @submit.prevent="submitProfile" class="space-y-6">
                            <!-- Avatar Upload Section -->
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                                <div class="relative group">
                                    <Avatar class="h-24 w-24 rounded-2xl border-2 border-border shadow-inner">
                                        <AvatarImage
                                            v-if="currentAvatarDisplay"
                                            :src="currentAvatarDisplay"
                                            :alt="user.name"
                                            class="object-cover"
                                        />
                                        <AvatarFallback class="rounded-2xl bg-muted text-xl font-bold">
                                            {{ getInitials(user.name) }}
                                        </AvatarFallback>
                                    </Avatar>
                                    <button
                                        type="button"
                                        @click="triggerAvatarUpload"
                                        class="absolute inset-0 flex flex-col items-center justify-center rounded-2xl bg-black/50 text-white opacity-0 transition-opacity hover:opacity-100 group-hover:opacity-100"
                                        title="Ubah Foto Profil"
                                    >
                                        <Camera class="h-5 w-5 mb-1" />
                                        <span class="text-[10px] font-medium">Ubah</span>
                                    </button>
                                </div>

                                <div class="space-y-2">
                                    <input
                                        ref="avatarInput"
                                        type="file"
                                        accept="image/png,image/jpeg,image/jpg,image/webp"
                                        class="hidden"
                                        @change="handleAvatarSelected"
                                    />
                                    <div class="flex flex-wrap items-center gap-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            @click="triggerAvatarUpload"
                                            class="h-9 gap-1.5"
                                        >
                                            <Camera class="h-4 w-4" />
                                            Pilih Foto Baru
                                        </Button>

                                        <Button
                                            v-if="currentAvatarDisplay"
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            @click="handleRemoveAvatar"
                                            class="h-9 gap-1.5 text-red-600 hover:bg-red-50 hover:text-red-700 dark:hover:bg-red-950/30"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                            Hapus Foto
                                        </Button>
                                    </div>
                                    <p class="text-xs text-muted-foreground">
                                        Format didukung: JPG, PNG, atau WebP. Maksimal 2MB. Foto tampil di kartu ujian dan header kuis.
                                    </p>
                                    <InputError :message="form.errors.avatar" />
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <!-- Nama Lengkap -->
                                <div class="space-y-2">
                                    <Label for="name">Nama Lengkap</Label>
                                    <Input
                                        id="name"
                                        v-model="form.name"
                                        required
                                        autocomplete="name"
                                        placeholder="Nama Lengkap beserta gelar"
                                    />
                                    <InputError :message="form.errors.name" />
                                </div>

                                <!-- Email -->
                                <div class="space-y-2">
                                    <Label for="email">Alamat Email</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        v-model="form.email"
                                        required
                                        autocomplete="username"
                                        placeholder="email@sekolah.sch.id"
                                    />
                                    <InputError :message="form.errors.email" />
                                </div>

                                <!-- Jika Siswa: NIS & Kelas Readonly -->
                                <template v-if="isStudent">
                                    <div class="space-y-2">
                                        <div class="flex items-center justify-between">
                                            <Label for="nis">Nomor Induk Siswa (NIS/NISN)</Label>
                                            <span class="text-[11px] text-muted-foreground flex items-center gap-1">
                                                <Lock class="h-3 w-3" /> Data Sekolah
                                            </span>
                                        </div>
                                        <Input
                                            id="nis"
                                            :value="user.nis || '-'"
                                            disabled
                                            class="bg-muted/50 cursor-not-allowed font-mono text-sm"
                                        />
                                    </div>

                                    <div class="space-y-2">
                                        <div class="flex items-center justify-between">
                                            <Label for="kelas">Kelas / Rombel</Label>
                                            <span class="text-[11px] text-muted-foreground flex items-center gap-1">
                                                <Lock class="h-3 w-3" /> Data Sekolah
                                            </span>
                                        </div>
                                        <Input
                                            id="kelas"
                                            :value="user.kelas || '-'"
                                            disabled
                                            class="bg-muted/50 cursor-not-allowed font-medium text-sm"
                                        />
                                    </div>
                                </template>

                                <!-- Jika Guru/Admin: NIP, Mapel, Sekolah, No Telepon -->
                                <template v-if="isTeacherOrAdmin">
                                    <div class="space-y-2">
                                        <Label for="nip">NIP / NUPTK</Label>
                                        <Input
                                            id="nip"
                                            v-model="form.nip"
                                            placeholder="Contoh: 198501012010011001"
                                        />
                                        <InputError :message="form.errors.nip" />
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="subject">Mata Pelajaran yang Diampu</Label>
                                        <div class="relative">
                                            <BookOpen class="absolute left-3 top-2.5 h-4 w-4 text-muted-foreground" />
                                            <Input
                                                id="subject"
                                                v-model="form.subject"
                                                class="pl-9"
                                                placeholder="Contoh: Matematika, PAI, Fisika"
                                            />
                                        </div>
                                        <InputError :message="form.errors.subject" />
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="school_origin">Asal Sekolah / Lembaga</Label>
                                        <div class="relative">
                                            <Building2 class="absolute left-3 top-2.5 h-4 w-4 text-muted-foreground" />
                                            <Input
                                                id="school_origin"
                                                v-model="form.school_origin"
                                                class="pl-9"
                                                placeholder="Contoh: SMA Al-Ihsan"
                                            />
                                        </div>
                                        <InputError :message="form.errors.school_origin" />
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="phone">Nomor Kontak / WhatsApp</Label>
                                        <div class="relative">
                                            <Phone class="absolute left-3 top-2.5 h-4 w-4 text-muted-foreground" />
                                            <Input
                                                id="phone"
                                                v-model="form.phone"
                                                class="pl-9"
                                                placeholder="081234567890"
                                            />
                                        </div>
                                        <InputError :message="form.errors.phone" />
                                    </div>
                                </template>
                            </div>

                            <div v-if="mustVerifyEmail && !user.email_verified_at">
                                <p class="text-sm text-amber-600 dark:text-amber-400">
                                    Alamat email Anda belum diverifikasi.
                                    <Link
                                        :href="route('verification.send')"
                                        method="post"
                                        as="button"
                                        class="underline hover:text-amber-800 font-medium"
                                    >
                                        Kirim ulang link verifikasi email.
                                    </Link>
                                </p>
                            </div>

                            <div class="flex items-center gap-4 pt-2">
                                <Button :disabled="form.processing">Simpan Profil</Button>

                                <TransitionRoot
                                    :show="form.recentlySuccessful"
                                    enter="transition ease-in-out duration-300"
                                    enter-from="opacity-0 translate-y-1"
                                    leave="transition ease-in-out duration-300"
                                    leave-to="opacity-0"
                                >
                                    <span class="inline-flex items-center gap-1.5 text-sm font-medium text-emerald-600 dark:text-emerald-400">
                                        <CheckCircle2 class="h-4 w-4" /> Perubahan profil berhasil disimpan.
                                    </span>
                                </TransitionRoot>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <!-- 2. PREFERENSI DEFAULT KUIS (KHUSUS GURU & ADMIN) -->
                <Card v-if="isTeacherOrAdmin" class="border-border/60 shadow-sm">
                    <CardHeader>
                        <CardTitle class="text-lg font-semibold tracking-tight">Preferensi Pembuatan Kuis</CardTitle>
                        <CardDescription>
                            Tentukan pengaturan standar yang akan diterapkan secara otomatis saat Anda membuat formulir kuis baru
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="submitPreferences" class="space-y-6">
                            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                                <div class="space-y-2">
                                    <Label for="default_kkm">Standar KKM Default</Label>
                                    <Input
                                        id="default_kkm"
                                        type="number"
                                        min="0"
                                        max="100"
                                        v-model="prefForm.default_kkm"
                                        placeholder="75"
                                    />
                                    <p class="text-xs text-muted-foreground">Kriteria Ketuntasan Minimal untuk indikator kelulusan</p>
                                </div>

                                <div class="space-y-2">
                                    <Label for="default_duration">Durasi Ujian Default (Menit)</Label>
                                    <Input
                                        id="default_duration"
                                        type="number"
                                        min="1"
                                        max="600"
                                        v-model="prefForm.default_duration"
                                        placeholder="60"
                                    />
                                    <p class="text-xs text-muted-foreground">Batas waktu pengerjaan otomatis saat siswa memulai ujian</p>
                                </div>

                                <div class="space-y-2">
                                    <Label for="default_arabic_font">Font Arab Default untuk Soal Keagamaan</Label>
                                    <select
                                        id="default_arabic_font"
                                        v-model="prefForm.default_arabic_font"
                                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs focus:outline-hidden focus:ring-2 focus:ring-ring"
                                    >
                                        <option value="Amiri Quran">Amiri Quran (Standar Mushaf Madinah)</option>
                                        <option value="Scheherazade New">Scheherazade New (Khas Naskh Klasik)</option>
                                        <option value="Noto Naskh Arabic">Noto Naskh Arabic (Modern & Bersih)</option>
                                        <option value="Amiri">Amiri (Standar Teks Arab)</option>
                                    </select>
                                    <p class="text-xs text-muted-foreground">Font yang diterapkan saat editor mendeteksi ketikan bahasa Arab / ayat Al-Qur'an</p>
                                </div>

                                <div class="space-y-2">
                                    <Label for="default_school_name">Nama Sekolah / Lembaga untuk Kop Kuis</Label>
                                    <Input
                                        id="default_school_name"
                                        v-model="prefForm.default_school_name"
                                        placeholder="Contoh: SMA Al-Ihsan Boarding School"
                                    />
                                    <p class="text-xs text-muted-foreground">Otomatis terisi pada header kuis dan laporan rekap nilai</p>
                                </div>
                            </div>

                            <!-- Toggle Flags -->
                            <div class="space-y-3 pt-2">
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        v-model="prefForm.default_shuffle_questions"
                                        class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                                    />
                                    <div>
                                        <p class="text-sm font-medium">Acak Urutan Soal Secara Default</p>
                                        <p class="text-xs text-muted-foreground">Tiap peserta ujian akan mendapatkan urutan nomor soal yang berlainan</p>
                                    </div>
                                </label>

                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        v-model="prefForm.default_shuffle_options"
                                        class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                                    />
                                    <div>
                                        <p class="text-sm font-medium">Acak Pilihan Jawaban Secara Default</p>
                                        <p class="text-xs text-muted-foreground">Opsi pilihan ganda A, B, C, D diacak secara otomatis untuk mencegah contek</p>
                                    </div>
                                </label>

                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        v-model="prefForm.default_anti_cheat_blur"
                                        class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                                    />
                                    <div>
                                        <p class="text-sm font-medium">Aktifkan Anti-Curang (Lock on Blur) Default</p>
                                        <p class="text-xs text-muted-foreground">Kunci ujian otomatis jika siswa berganti tab, membuka aplikasi lain, atau minimize layar</p>
                                    </div>
                                </label>
                            </div>

                            <div class="flex items-center gap-4 pt-2">
                                <Button :disabled="prefForm.processing">Simpan Preferensi Kuis</Button>

                                <TransitionRoot
                                    :show="prefForm.recentlySuccessful"
                                    enter="transition ease-in-out duration-300"
                                    enter-from="opacity-0 translate-y-1"
                                    leave="transition ease-in-out duration-300"
                                    leave-to="opacity-0"
                                >
                                    <span class="inline-flex items-center gap-1.5 text-sm font-medium text-emerald-600 dark:text-emerald-400">
                                        <CheckCircle2 class="h-4 w-4" /> Preferensi kuis berhasil disimpan.
                                    </span>
                                </TransitionRoot>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <!-- 3. PIN PENGAWAS UJIAN (KHUSUS GURU & ADMIN) -->
                <Card v-if="isTeacherOrAdmin" class="border-border/60 shadow-sm">
                    <CardHeader class="pb-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <CardTitle class="text-lg font-semibold tracking-tight">PIN Otorisasi Pengawas (Proctor PIN)</CardTitle>
                                <CardDescription>
                                    PIN 6 angka cepat untuk membuka kunci siswa yang ter-suspend di lab tanpa harus mengetik password utama Anda
                                </CardDescription>
                            </div>
                            <span
                                v-if="user.has_proctor_pin"
                                class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300"
                            >
                                <CheckCircle2 class="h-3.5 w-3.5" /> PIN Aktif
                            </span>
                            <span
                                v-else
                                class="inline-flex items-center gap-1 rounded-full bg-neutral-100 px-2.5 py-0.5 text-xs font-medium text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400"
                            >
                                <AlertCircle class="h-3.5 w-3.5" /> Belum Diatur
                            </span>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="submitProctorPin" class="space-y-4">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div class="space-y-2">
                                    <Label for="proctor_pin">PIN Pengawas (6 Angka)</Label>
                                    <Input
                                        id="proctor_pin"
                                        type="password"
                                        inputmode="numeric"
                                        maxlength="6"
                                        v-model="pinForm.pin"
                                        placeholder="Contoh: 123456"
                                        required
                                        class="font-mono tracking-widest text-center text-base"
                                    />
                                    <InputError :message="pinForm.errors.pin" />
                                </div>

                                <div class="space-y-2">
                                    <Label for="current_password">Kata Sandi Akun Anda (Verifikasi)</Label>
                                    <Input
                                        id="current_password"
                                        type="password"
                                        v-model="pinForm.current_password"
                                        placeholder="Masukkan kata sandi login"
                                        required
                                    />
                                    <InputError :message="pinForm.errors.current_password" />
                                </div>
                            </div>

                            <div class="flex items-center gap-4 pt-1">
                                <Button :disabled="pinForm.processing" class="gap-1.5">
                                    <KeyRound class="h-4 w-4" /> Simpan PIN Pengawas
                                </Button>

                                <TransitionRoot
                                    :show="pinForm.recentlySuccessful"
                                    enter="transition ease-in-out duration-300"
                                    enter-from="opacity-0 translate-y-1"
                                    leave="transition ease-in-out duration-300"
                                    leave-to="opacity-0"
                                >
                                    <span class="inline-flex items-center gap-1.5 text-sm font-medium text-emerald-600 dark:text-emerald-400">
                                        <CheckCircle2 class="h-4 w-4" /> PIN Pengawas berhasil diperbarui.
                                    </span>
                                </TransitionRoot>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <!-- 4. SESI LOGIN AKTIF (ACTIVE DEVICES) -->
                <Card class="border-border/60 shadow-sm">
                    <CardHeader class="pb-3">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <CardTitle class="text-lg font-semibold tracking-tight">Manajemen Sesi Perangkat</CardTitle>
                                <CardDescription>Pantau daftar perangkat dan browser yang saat ini sedang login ke akun Anda</CardDescription>
                            </div>
                            <Dialog v-model:open="logoutOtherOpen">
                                <DialogTrigger as-child>
                                    <Button variant="outline" size="sm" class="gap-1.5 shrink-0">
                                        <Shield class="h-3.5 w-3.5" /> Keluar Sesi Lain
                                    </Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <form @submit="submitLogoutOtherSessions" class="space-y-4">
                                        <DialogHeader>
                                            <DialogTitle>Keluar dari Semua Sesi Lain?</DialogTitle>
                                            <DialogDescription>
                                                Tindakan ini akan mengakhiri sesi login di semua komputer atau ponsel lain. Masukkan kata sandi akun Anda untuk konfirmasi.
                                            </DialogDescription>
                                        </DialogHeader>

                                        <div class="space-y-2">
                                            <Label for="logout_password">Kata Sandi Akun</Label>
                                            <Input
                                                id="logout_password"
                                                type="password"
                                                v-model="logoutOtherForm.password"
                                                placeholder="Kata sandi akun Anda"
                                                required
                                            />
                                            <InputError :message="logoutOtherForm.errors.password" />
                                        </div>

                                        <DialogFooter>
                                            <DialogClose as-child>
                                                <Button variant="secondary" type="button">Batal</Button>
                                            </DialogClose>
                                            <Button variant="destructive" :disabled="logoutOtherForm.processing">
                                                Konfirmasi Keluar Sesi Lain
                                            </Button>
                                        </DialogFooter>
                                    </form>
                                </DialogContent>
                            </Dialog>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div v-if="sessions && sessions.length > 0" class="divide-y divide-border/60">
                            <div
                                v-for="session in sessions"
                                :key="session.id"
                                class="flex items-center justify-between py-3.5 first:pt-0 last:pb-0"
                            >
                                <div class="flex items-center gap-3.5">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-muted text-muted-foreground">
                                        <Laptop v-if="session.agent.is_desktop" class="h-5 w-5" />
                                        <Smartphone v-else class="h-5 w-5" />
                                    </div>
                                    <div class="space-y-0.5">
                                        <div class="flex items-center gap-2">
                                            <p class="text-sm font-medium leading-none">
                                                {{ session.agent.platform }} — {{ session.agent.browser }}
                                            </p>
                                            <span
                                                v-if="session.is_current_device"
                                                class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300"
                                            >
                                                Perangkat Ini
                                            </span>
                                        </div>
                                        <p class="text-xs text-muted-foreground">
                                            IP: {{ session.ip_address || '127.0.0.1' }} • Aktif {{ session.last_active }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="rounded-lg border border-dashed border-border p-6 text-center text-sm text-muted-foreground">
                            Informasi sesi perangkat aktif tidak tersedia pada konfigurasi sesi saat ini.
                        </div>
                    </CardContent>
                </Card>

                <!-- 5. EKSPOR BANK SOAL MANDIRI (KHUSUS GURU & ADMIN) -->
                <Card v-if="isTeacherOrAdmin" class="border-border/60 shadow-sm">
                    <CardHeader class="pb-3">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <CardTitle class="text-lg font-semibold tracking-tight">Cadangan Bank Soal Mandiri</CardTitle>
                                <CardDescription>
                                    Unduh salinan seluruh kuis, butir soal, kunci jawaban, dan konfigurasi yang pernah Anda buat
                                </CardDescription>
                            </div>
                            <Button as-child variant="outline" class="gap-1.5 shrink-0">
                                <a :href="route('profile.quizzes.export')" download>
                                    <Download class="h-4 w-4" /> Download Backup (.json)
                                </a>
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <p class="text-xs text-muted-foreground">
                            Format berkas JSON terstruktur dapat digunakan sebagai arsip portofolio soal guru atau diimpor kembali sewaktu-waktu.
                        </p>
                    </CardContent>
                </Card>

                <!-- 6. HAPUS AKUN -->
                <DeleteUser :can-delete="canDeleteAccount" />
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
