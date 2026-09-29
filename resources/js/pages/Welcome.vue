<script setup lang="ts">
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3';
import {
    ClipboardList,
    LogIn,
    KeyRound,
    Eye,
    EyeOff,
    Loader2,
} from 'lucide-vue-next';
import { computed, ref, onMounted } from 'vue';

const page = usePage();
const user = computed(() => (page.props.auth as any)?.user);

onMounted(() => {
    if (user.value) {
        router.visit(route('dashboard'));
    }
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const showPassword = ref(false);

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Alsen Form - Masuk" />

    <main class="min-h-screen bg-[#f6fbef] text-slate-950 flex flex-col justify-between selection:bg-emerald-500 selection:text-white">
        <!-- Header -->
        <header class="w-full border-b border-emerald-100/80 bg-white/75 backdrop-blur-md sticky top-0 z-20">
            <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-6">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-emerald-500 text-white shadow-[0_4px_0_#159447]">
                        <ClipboardList class="h-5 w-5" />
                    </div>
                    <div>
                        <p class="text-base font-black leading-tight">Alsen Form</p>
                        <p class="text-[11px] font-bold text-emerald-700">Platform Form & Ujian Sekolah</p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <Link
                        v-if="user"
                        :href="route('dashboard')"
                        class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-emerald-700"
                    >
                        Buka Dashboard
                    </Link>
                </div>
            </div>
        </header>

        <!-- Main Login Section (Centered) -->
        <div class="mx-auto flex w-full max-w-md flex-1 items-center justify-center px-4 py-8 sm:py-12">
            <div class="w-full">
                <div class="rounded-[2.2rem] border-2 border-emerald-200 bg-white p-6 sm:p-8 shadow-[0_12px_0_#d9f99d]">
                    <!-- Direct Login Form -->
                    <div class="space-y-5">
                            <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                                    <KeyRound class="h-5 w-5" />
                                </div>
                                <div>
                                    <h2 class="text-xl font-black text-slate-900">Masuk</h2>
                                    <p class="text-xs font-semibold text-slate-500">Gunakan Email (Guru) atau NIS (Murid)</p>
                                </div>
                            </div>

                            <form @submit.prevent="submit" class="space-y-4">
                                <!-- Email or NIS -->
                                <div>
                                    <label for="email" class="block text-xs font-bold text-slate-700 mb-1">
                                        Email atau NIS
                                    </label>
                                    <input
                                        id="email"
                                        v-model="form.email"
                                        type="text"
                                        required
                                        autofocus
                                        autocomplete="username"
                                        placeholder="nama@sekolah.com atau NIS murid"
                                        class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3.5 py-2.5 text-xs font-medium text-slate-900 placeholder:text-slate-400 focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                                    />
                                    <div v-if="form.errors.email" class="mt-1 text-[11px] font-semibold text-red-600">
                                        {{ form.errors.email }}
                                    </div>
                                </div>

                                <!-- Password -->
                                <div>
                                    <div class="flex items-center justify-between mb-1">
                                        <label for="password" class="block text-xs font-bold text-slate-700">
                                            Password
                                        </label>
                                        <Link
                                            :href="route('password.request')"
                                            class="text-[11px] font-bold text-emerald-700 hover:underline"
                                        >
                                            Lupa password?
                                        </Link>
                                    </div>
                                    <div class="relative">
                                        <input
                                            id="password"
                                            v-model="form.password"
                                            :type="showPassword ? 'text' : 'password'"
                                            required
                                            autocomplete="current-password"
                                            placeholder="Masukkan password..."
                                            class="w-full rounded-xl border border-slate-200 bg-slate-50/50 py-2.5 pl-3.5 pr-10 text-xs font-medium text-slate-900 placeholder:text-slate-400 focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                                        />
                                        <button
                                            type="button"
                                            @click="showPassword = !showPassword"
                                            class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                                            title="Lihat / Sembunyikan Password"
                                        >
                                            <EyeOff v-if="showPassword" class="h-4 w-4" />
                                            <Eye v-else class="h-4 w-4" />
                                        </button>
                                    </div>
                                    <div v-if="form.errors.password" class="mt-1 text-[11px] font-semibold text-red-600">
                                        {{ form.errors.password }}
                                    </div>
                                </div>

                                <!-- Remember Me -->
                                <div class="flex items-center gap-2 pt-0.5">
                                    <input
                                        id="remember"
                                        v-model="form.remember"
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                    />
                                    <label for="remember" class="text-xs font-semibold text-slate-600 cursor-pointer">
                                        Ingat saya di perangkat ini
                                    </label>
                                </div>

                                <!-- Submit Button -->
                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="flex h-12 w-full items-center justify-center gap-2 rounded-2xl bg-emerald-500 px-5 text-sm font-black text-white shadow-[0_5px_0_#159447] transition hover:-translate-y-0.5 active:translate-y-0 disabled:opacity-50"
                                >
                                    <Loader2 v-if="form.processing" class="h-4 w-4 animate-spin" />
                                    <LogIn v-else class="h-4 w-4" />
                                    <span>Masuk ke Alsen Form</span>
                                </button>

                                <!-- Link to Register -->
                                <div class="pt-2 text-center text-xs font-semibold text-slate-400">
                                    Akun murid dan guru dikelola oleh Administrator Sekolah.
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

        <!-- Footer -->
        <footer class="py-4 text-center text-xs font-semibold text-slate-400">
            &copy; {{ new Date().getFullYear() }} Alsen Form. All rights reserved.
        </footer>
    </main>
</template>
