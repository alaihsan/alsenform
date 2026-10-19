<script setup lang="ts">
import BrandMark from '@/components/BrandMark.vue';
import { BRAND_NAME } from '@/lib/brand';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Eye, EyeOff, KeyRound, Loader2, LogIn } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

const page = usePage();
const user = computed(() => (page.props.auth as any)?.user);

onMounted(() => {
    if (user.value) {
        router.visit(route('dashboard'));
    }
});

const form = useForm({
    identifier: '',
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
    <Head title="Masuk" />

    <main class="flex min-h-screen flex-col justify-between bg-[#f5f4ff] text-slate-950 selection:bg-indigo-500 selection:text-white">
        <!-- Header -->
        <header class="sticky top-0 z-20 w-full border-b border-indigo-100/80 bg-white/75 backdrop-blur-md">
            <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-6">
                <BrandMark size="lg" tagline="always" />

                <div class="flex items-center gap-2">
                    <Link
                        v-if="user"
                        :href="route('dashboard')"
                        class="inline-flex shrink-0 items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-indigo-700"
                    >
                        Buka Beranda
                    </Link>
                </div>
            </div>
        </header>

        <!-- Main Login Section (Centered) -->
        <div class="mx-auto flex w-full max-w-md flex-1 items-center justify-center px-4 py-8 sm:py-12">
            <div class="w-full">
                <div class="rounded-[2.2rem] border-2 border-indigo-100 bg-white p-6 shadow-[0_12px_0_#e0e7ff] sm:p-8">
                    <!-- Direct Login Form -->
                    <div class="space-y-5">
                        <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                                <KeyRound class="h-5 w-5" />
                            </div>
                            <div>
                                <h2 class="text-xl font-black text-slate-900">Masuk</h2>
                                <p class="text-xs font-semibold text-slate-500">Masuk dengan email atau nomor induk dari lembaga Anda.</p>
                            </div>
                        </div>

                        <form @submit.prevent="submit" class="space-y-4">
                            <div>
                                <label for="identifier" class="mb-1 block text-xs font-bold text-slate-700">Email atau nomor induk</label>
                                <input
                                    id="identifier"
                                    v-model="form.identifier"
                                    type="text"
                                    required
                                    autofocus
                                    autocomplete="username"
                                    placeholder="Contoh: NIS, NIP, atau email"
                                    class="w-full rounded-xl border border-slate-200 bg-slate-50/50 px-3.5 py-2.5 text-xs font-medium text-slate-900 placeholder:text-slate-400 focus:border-indigo-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                                />
                                <div v-if="form.errors.identifier" class="mt-1 text-[11px] font-semibold text-red-600">
                                    {{ form.errors.identifier }}
                                </div>
                            </div>

                            <!-- Password -->
                            <div>
                                <div class="mb-1 flex items-center justify-between">
                                    <label for="password" class="block text-xs font-bold text-slate-700"> Password </label>
                                    <Link :href="route('password.request')" class="text-[11px] font-bold text-indigo-700 hover:underline">
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
                                        class="w-full rounded-xl border border-slate-200 bg-slate-50/50 py-2.5 pl-3.5 pr-10 text-xs font-medium text-slate-900 placeholder:text-slate-400 focus:border-indigo-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
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
                                    class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                <label for="remember" class="cursor-pointer text-xs font-semibold text-slate-600">
                                    Ingat saya di perangkat ini
                                </label>
                            </div>

                            <!-- Submit Button -->
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="flex h-12 w-full items-center justify-center gap-2 rounded-2xl bg-indigo-600 px-5 text-sm font-black text-white shadow-[0_5px_0_#3730a3] transition hover:-translate-y-0.5 active:translate-y-0 disabled:opacity-50"
                            >
                                <Loader2 v-if="form.processing" class="h-4 w-4 animate-spin" />
                                <LogIn v-else class="h-4 w-4" />
                                <span>Masuk ke {{ BRAND_NAME }}</span>
                            </button>

                            <!-- Link to Register -->
                            <div class="pt-2 text-center text-xs font-semibold text-slate-400">
                                Akun dibuat oleh admin lembaga Anda. Belum punya akun? Hubungi admin.
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
    </main>
</template>
