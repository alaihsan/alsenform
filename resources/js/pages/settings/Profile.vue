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
import InputError from '@/components/InputError.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
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
import {
    alsenCard,
    alsenCardDescription,
    alsenCardTitle,
    alsenCheckbox,
    alsenDangerButton,
    alsenHint,
    alsenInput,
    alsenLabel,
    alsenPrimaryButton,
    alsenSecondaryButton,
    alsenSuccessText,
} from '@/constants/alsenform-ui';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type SessionItem, type SharedData, type User } from '@/types';

interface Props {
    mustVerifyEmail: boolean;
    status?: string;
    canDeleteAccount?: boolean;
    sessions?: SessionItem[];
}

withDefaults(defineProps<Props>(), {
    canDeleteAccount: true,
    sessions: () => [],
});

const page = usePage<SharedData>();
const user = page.props.auth.user as User;

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
    <Head title="Profil" />

    <SettingsLayout>
        <!-- 1. Identitas & foto profil -->
        <div :class="alsenCard">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 :class="alsenCardTitle">Informasi Profil</h2>
                    <p :class="alsenCardDescription">Kelola foto profil dan data identitas akademik Anda.</p>
                </div>
                <span
                    v-if="user.role === 'guru'"
                    class="inline-flex shrink-0 items-center gap-1.5 self-start rounded-full bg-indigo-50 px-3 py-1 text-xs font-bold text-indigo-700"
                >
                    <GraduationCap class="h-3.5 w-3.5" /> Guru / Tenaga Pendidik
                </span>
                <span
                    v-else-if="isStudent"
                    class="inline-flex shrink-0 items-center gap-1.5 self-start rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700"
                >
                    <UserIcon class="h-3.5 w-3.5" /> Siswa Peserta Ujian
                </span>
                <span v-else class="inline-flex shrink-0 items-center gap-1.5 self-start rounded-full bg-purple-50 px-3 py-1 text-xs font-bold text-purple-700">
                    <Shield class="h-3.5 w-3.5" /> Administrator
                </span>
            </div>

            <form class="mt-6 space-y-6" @submit.prevent="submitProfile">
                <!-- Avatar -->
                <div class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:flex-row sm:items-center">
                    <div class="group relative h-24 w-24 shrink-0">
                        <Avatar class="h-24 w-24 overflow-hidden rounded-3xl border-4 border-white shadow-sm">
                            <AvatarImage v-if="currentAvatarDisplay" :src="currentAvatarDisplay" :alt="user.name" />
                            <AvatarFallback class="flex h-full w-full items-center justify-center rounded-3xl bg-slate-200 text-slate-400">
                                <UserIcon class="h-12 w-12" />
                            </AvatarFallback>
                        </Avatar>
                        <button
                            type="button"
                            class="absolute inset-0 flex flex-col items-center justify-center rounded-3xl bg-slate-900/50 text-white opacity-0 transition-opacity group-hover:opacity-100"
                            title="Ubah foto profil"
                            @click="triggerAvatarUpload"
                        >
                            <Camera class="mb-1 h-5 w-5" />
                            <span class="text-[10px] font-bold">Ubah</span>
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
                            <button type="button" :class="[alsenSecondaryButton, 'py-2.5']" @click="triggerAvatarUpload">
                                <Camera class="h-4 w-4" />
                                Pilih Foto Baru
                            </button>
                            <button
                                v-if="currentAvatarDisplay"
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-2xl px-4 py-2.5 text-sm font-bold text-rose-600 transition hover:bg-rose-50"
                                @click="handleRemoveAvatar"
                            >
                                <Trash2 class="h-4 w-4" />
                                Hapus Foto
                            </button>
                        </div>
                        <p :class="alsenHint">JPG, PNG, atau WebP, maksimal 2 MB. Foto tampil di menu akun dan kartu ujian.</p>
                        <InputError :message="form.errors.avatar" />
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div class="space-y-2">
                        <label for="name" :class="alsenLabel">Nama lengkap</label>
                        <input id="name" v-model="form.name" :class="alsenInput" required autocomplete="name" placeholder="Nama lengkap beserta gelar" />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="space-y-2">
                        <label for="email" :class="alsenLabel">Alamat email</label>
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            :class="alsenInput"
                            required
                            autocomplete="username"
                            placeholder="email@sekolah.sch.id"
                        />
                        <InputError :message="form.errors.email" />
                    </div>

                    <!-- Siswa: NIS & kelas dikelola sekolah -->
                    <template v-if="isStudent">
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <label for="nis" :class="alsenLabel">NIS / NISN</label>
                                <span class="flex items-center gap-1 text-[11px] font-semibold text-slate-400"><Lock class="h-3 w-3" /> Data sekolah</span>
                            </div>
                            <input id="nis" :value="user.nis || '-'" disabled :class="[alsenInput, 'font-mono']" />
                        </div>

                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <label for="kelas" :class="alsenLabel">Kelas / Rombel</label>
                                <span class="flex items-center gap-1 text-[11px] font-semibold text-slate-400"><Lock class="h-3 w-3" /> Data sekolah</span>
                            </div>
                            <input id="kelas" :value="user.kelas || '-'" disabled :class="alsenInput" />
                        </div>
                    </template>

                    <!-- Guru / admin -->
                    <template v-if="isTeacherOrAdmin">
                        <div class="space-y-2">
                            <label for="nip" :class="alsenLabel">NIP / NUPTK</label>
                            <input
                                id="nip"
                                v-model="form.nip"
                                :class="alsenInput"
                                :required="user.role === 'guru' && !user.is_admin"
                                placeholder="Contoh: 198501012010011001"
                            />
                            <InputError :message="form.errors.nip" />
                        </div>

                        <div class="space-y-2">
                            <label for="subject" :class="alsenLabel">Mata pelajaran yang diampu</label>
                            <div class="relative">
                                <BookOpen class="absolute left-4 top-3.5 h-4 w-4 text-slate-400" />
                                <input id="subject" v-model="form.subject" :class="[alsenInput, 'pl-11']" placeholder="Contoh: Matematika, PAI, Fisika" />
                            </div>
                            <InputError :message="form.errors.subject" />
                        </div>

                        <div class="space-y-2">
                            <label for="school_origin" :class="alsenLabel">Asal sekolah / lembaga</label>
                            <div class="relative">
                                <Building2 class="absolute left-4 top-3.5 h-4 w-4 text-slate-400" />
                                <input id="school_origin" v-model="form.school_origin" :class="[alsenInput, 'pl-11']" placeholder="Contoh: SMA Al-Ihsan" />
                            </div>
                            <InputError :message="form.errors.school_origin" />
                        </div>

                        <div class="space-y-2">
                            <label for="phone" :class="alsenLabel">Nomor kontak / WhatsApp</label>
                            <div class="relative">
                                <Phone class="absolute left-4 top-3.5 h-4 w-4 text-slate-400" />
                                <input id="phone" v-model="form.phone" :class="[alsenInput, 'pl-11']" placeholder="081234567890" />
                            </div>
                            <InputError :message="form.errors.phone" />
                        </div>
                    </template>
                </div>

                <p v-if="mustVerifyEmail && !user.email_verified_at" class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                    Alamat email Anda belum diverifikasi.
                    <Link :href="route('verification.send')" method="post" as="button" class="font-bold underline hover:text-amber-900">
                        Kirim ulang link verifikasi email.
                    </Link>
                </p>

                <div class="flex flex-wrap items-center gap-4 border-t border-slate-100 pt-5">
                    <button type="submit" :disabled="form.processing" :class="alsenPrimaryButton">
                        {{ form.processing ? 'Menyimpan...' : 'Simpan Profil' }}
                    </button>
                    <TransitionRoot
                        :show="form.recentlySuccessful"
                        enter="transition ease-in-out duration-300"
                        enter-from="opacity-0 translate-y-1"
                        leave="transition ease-in-out duration-300"
                        leave-to="opacity-0"
                    >
                        <span :class="alsenSuccessText"><CheckCircle2 class="h-4 w-4" /> Profil berhasil disimpan.</span>
                    </TransitionRoot>
                </div>
            </form>
        </div>

        <!-- 2. Preferensi default kuis (guru & admin) -->
        <div v-if="isTeacherOrAdmin" :class="alsenCard">
            <h2 :class="alsenCardTitle">Preferensi Pembuatan Kuis</h2>
            <p :class="alsenCardDescription">Pengaturan standar yang otomatis dipakai saat Anda membuat kuis baru.</p>

            <form class="mt-6 space-y-6" @submit.prevent="submitPreferences">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div class="space-y-2">
                        <label for="default_kkm" :class="alsenLabel">Standar KKM default</label>
                        <input id="default_kkm" v-model="prefForm.default_kkm" type="number" min="0" max="100" :class="alsenInput" placeholder="75" />
                        <p :class="alsenHint">Kriteria Ketuntasan Minimal untuk indikator kelulusan.</p>
                    </div>

                    <div class="space-y-2">
                        <label for="default_duration" :class="alsenLabel">Durasi ujian default (menit)</label>
                        <input
                            id="default_duration"
                            v-model="prefForm.default_duration"
                            type="number"
                            min="1"
                            max="600"
                            :class="alsenInput"
                            placeholder="60"
                        />
                        <p :class="alsenHint">Batas waktu pengerjaan saat siswa memulai ujian.</p>
                    </div>

                    <div class="space-y-2">
                        <label for="default_arabic_font" :class="alsenLabel">Font Arab default</label>
                        <select id="default_arabic_font" v-model="prefForm.default_arabic_font" :class="alsenInput">
                            <option value="Amiri Quran">Amiri Quran (Standar Mushaf Madinah)</option>
                            <option value="Scheherazade New">Scheherazade New (Khas Naskh Klasik)</option>
                            <option value="Noto Naskh Arabic">Noto Naskh Arabic (Modern & Bersih)</option>
                            <option value="Amiri">Amiri (Standar Teks Arab)</option>
                        </select>
                        <p :class="alsenHint">Dipakai saat editor mendeteksi teks bahasa Arab / ayat Al-Qur'an.</p>
                    </div>

                    <div class="space-y-2">
                        <label for="default_school_name" :class="alsenLabel">Nama sekolah untuk kop kuis</label>
                        <input
                            id="default_school_name"
                            v-model="prefForm.default_school_name"
                            :class="alsenInput"
                            placeholder="Contoh: SMA Al-Ihsan Boarding School"
                        />
                        <p :class="alsenHint">Otomatis tampil di header kuis dan laporan rekap nilai.</p>
                    </div>
                </div>

                <div class="space-y-3 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <label class="flex cursor-pointer items-start gap-3">
                        <input v-model="prefForm.default_shuffle_questions" type="checkbox" :class="alsenCheckbox" />
                        <span>
                            <span class="block text-sm font-bold text-slate-800">Acak urutan soal</span>
                            <span :class="alsenHint">Setiap peserta mendapat urutan nomor soal yang berbeda.</span>
                        </span>
                    </label>
                    <label class="flex cursor-pointer items-start gap-3">
                        <input v-model="prefForm.default_shuffle_options" type="checkbox" :class="alsenCheckbox" />
                        <span>
                            <span class="block text-sm font-bold text-slate-800">Acak pilihan jawaban</span>
                            <span :class="alsenHint">Opsi pilihan ganda diacak untuk mencegah mencontek.</span>
                        </span>
                    </label>
                    <label class="flex cursor-pointer items-start gap-3">
                        <input v-model="prefForm.default_anti_cheat_blur" type="checkbox" :class="alsenCheckbox" />
                        <span>
                            <span class="block text-sm font-bold text-slate-800">Aktifkan anti-curang (kunci saat pindah tab)</span>
                            <span :class="alsenHint">Ujian terkunci jika siswa berganti tab, membuka aplikasi lain, atau meminimalkan layar.</span>
                        </span>
                    </label>
                </div>

                <div class="flex flex-wrap items-center gap-4 border-t border-slate-100 pt-5">
                    <button type="submit" :disabled="prefForm.processing" :class="alsenPrimaryButton">Simpan Preferensi</button>
                    <TransitionRoot
                        :show="prefForm.recentlySuccessful"
                        enter="transition ease-in-out duration-300"
                        enter-from="opacity-0 translate-y-1"
                        leave="transition ease-in-out duration-300"
                        leave-to="opacity-0"
                    >
                        <span :class="alsenSuccessText"><CheckCircle2 class="h-4 w-4" /> Preferensi kuis disimpan.</span>
                    </TransitionRoot>
                </div>
            </form>
        </div>

        <!-- 3. PIN pengawas (guru & admin) -->
        <div v-if="isTeacherOrAdmin" :class="alsenCard">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 :class="alsenCardTitle">PIN Pengawas (Proctor PIN)</h2>
                    <p :class="alsenCardDescription">PIN 6 angka untuk membuka kunci ujian siswa di lab tanpa mengetik password utama.</p>
                </div>
                <span
                    v-if="user.has_proctor_pin"
                    class="inline-flex shrink-0 items-center gap-1 self-start rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700"
                >
                    <CheckCircle2 class="h-3.5 w-3.5" /> PIN aktif
                </span>
                <span v-else class="inline-flex shrink-0 items-center gap-1 self-start rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">
                    <AlertCircle class="h-3.5 w-3.5" /> Belum diatur
                </span>
            </div>

            <form class="mt-6 space-y-5" @submit.prevent="submitProctorPin">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div class="space-y-2">
                        <label for="proctor_pin" :class="alsenLabel">PIN pengawas (6 angka)</label>
                        <input
                            id="proctor_pin"
                            v-model="pinForm.pin"
                            type="password"
                            inputmode="numeric"
                            maxlength="6"
                            :class="[alsenInput, 'text-center font-mono text-base tracking-widest']"
                            placeholder="Contoh: 123456"
                            required
                        />
                        <InputError :message="pinForm.errors.pin" />
                    </div>
                    <div class="space-y-2">
                        <label for="pin_current_password" :class="alsenLabel">Password akun (verifikasi)</label>
                        <input
                            id="pin_current_password"
                            v-model="pinForm.current_password"
                            type="password"
                            :class="alsenInput"
                            placeholder="Masukkan password login"
                            required
                        />
                        <InputError :message="pinForm.errors.current_password" />
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-4 border-t border-slate-100 pt-5">
                    <button type="submit" :disabled="pinForm.processing" :class="alsenPrimaryButton"><KeyRound class="h-4 w-4" /> Simpan PIN</button>
                    <TransitionRoot
                        :show="pinForm.recentlySuccessful"
                        enter="transition ease-in-out duration-300"
                        enter-from="opacity-0 translate-y-1"
                        leave="transition ease-in-out duration-300"
                        leave-to="opacity-0"
                    >
                        <span :class="alsenSuccessText"><CheckCircle2 class="h-4 w-4" /> PIN pengawas diperbarui.</span>
                    </TransitionRoot>
                </div>
            </form>
        </div>

        <!-- 4. Sesi login aktif -->
        <div :class="alsenCard">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 :class="alsenCardTitle">Perangkat yang Sedang Login</h2>
                    <p :class="alsenCardDescription">Daftar perangkat dan browser yang saat ini masuk ke akun Anda.</p>
                </div>
                <Dialog v-model:open="logoutOtherOpen">
                    <DialogTrigger as-child>
                        <button type="button" :class="[alsenSecondaryButton, 'shrink-0 self-start py-2.5']">
                            <Shield class="h-4 w-4" /> Keluar dari Perangkat Lain
                        </button>
                    </DialogTrigger>
                    <DialogContent class="rounded-3xl border-slate-200 bg-white p-6 sm:p-8">
                        <form class="space-y-5" @submit="submitLogoutOtherSessions">
                            <DialogHeader>
                                <DialogTitle class="text-lg font-bold text-slate-900">Keluar dari semua perangkat lain?</DialogTitle>
                                <DialogDescription class="text-sm text-slate-500">
                                    Sesi login di komputer atau ponsel lain akan diakhiri. Masukkan password akun Anda untuk konfirmasi.
                                </DialogDescription>
                            </DialogHeader>

                            <div class="space-y-2">
                                <label for="logout_password" :class="alsenLabel">Password akun</label>
                                <input
                                    id="logout_password"
                                    v-model="logoutOtherForm.password"
                                    type="password"
                                    :class="alsenInput"
                                    placeholder="Password akun Anda"
                                    required
                                />
                                <InputError :message="logoutOtherForm.errors.password" />
                            </div>

                            <DialogFooter class="gap-2">
                                <DialogClose as-child>
                                    <button type="button" :class="alsenSecondaryButton">Batal</button>
                                </DialogClose>
                                <button type="submit" :disabled="logoutOtherForm.processing" :class="alsenDangerButton">Keluar dari Perangkat Lain</button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>

            <div v-if="sessions && sessions.length > 0" class="mt-5 divide-y divide-slate-100">
                <div v-for="session in sessions" :key="session.id" class="flex items-center gap-3.5 py-3.5 first:pt-0 last:pb-0">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-slate-100 text-slate-500">
                        <Laptop v-if="session.agent.is_desktop" class="h-5 w-5" />
                        <Smartphone v-else class="h-5 w-5" />
                    </div>
                    <div class="min-w-0 space-y-0.5">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-sm font-bold text-slate-800">{{ session.agent.platform }} — {{ session.agent.browser }}</p>
                            <span v-if="session.is_current_device" class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-800">
                                Perangkat ini
                            </span>
                        </div>
                        <p :class="alsenHint">IP {{ session.ip_address || '127.0.0.1' }} · aktif {{ session.last_active }}</p>
                    </div>
                </div>
            </div>
            <div v-else class="mt-5 rounded-2xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500">
                Informasi perangkat yang sedang login belum tersedia.
            </div>
        </div>

        <!-- 5. Cadangan bank soal (guru & admin) -->
        <div v-if="isTeacherOrAdmin" :class="alsenCard">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 :class="alsenCardTitle">Cadangan Bank Soal</h2>
                    <p :class="alsenCardDescription">Unduh salinan seluruh kuis, soal, kunci jawaban, dan pengaturannya dalam format JSON.</p>
                </div>
                <a :href="route('profile.quizzes.export')" download :class="[alsenSecondaryButton, 'shrink-0']">
                    <Download class="h-4 w-4" /> Unduh Cadangan (.json)
                </a>
            </div>
        </div>

        <!-- 6. Hapus akun -->
        <DeleteUser :can-delete="canDeleteAccount" />
    </SettingsLayout>
</template>
