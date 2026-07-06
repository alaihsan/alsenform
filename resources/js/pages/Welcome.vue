<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ClipboardList, LogIn, Sparkles, UserPlus } from 'lucide-vue-next';
</script>

<template>
    <Head title="Alsen Form" />

    <main class="min-h-screen bg-[#f6fbef] text-slate-950">
        <div class="mx-auto flex min-h-screen w-full max-w-6xl flex-col px-6 py-6">
            <header class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-500 text-white shadow-[0_6px_0_#159447]">
                        <ClipboardList class="h-7 w-7" />
                    </div>
                    <div>
                        <p class="text-xl font-black">Alsen Form</p>
                        <p class="text-sm font-bold text-emerald-700">Form belajar yang terasa ringan.</p>
                    </div>
                </div>
            </header>

            <section class="grid flex-1 items-center gap-10 py-12 lg:grid-cols-[minmax(0,0.95fr)_minmax(360px,0.75fr)]">
                <div class="max-w-3xl">
                    <div class="mb-5 inline-flex items-center gap-2 rounded-full bg-lime-100 px-4 py-2 text-sm font-black text-lime-700">
                        <Sparkles class="h-4 w-4" />
                        Nuansa Google Forms, lebih menyenangkan
                    </div>
                    <h1 class="text-5xl font-black leading-tight tracking-normal sm:text-6xl">
                        Masuk untuk membuat, mengelola, dan membagikan form.
                    </h1>
                    <p class="mt-5 max-w-2xl text-lg font-semibold leading-8 text-slate-600">
                        Halaman awal dibuat sederhana: pilih masuk atau daftar, lalu lanjut ke dashboard form seperti workspace Google Forms.
                    </p>
                </div>

                <div class="rounded-[2rem] border-2 border-emerald-200 bg-white p-6 shadow-[0_14px_0_#d9f99d]">
                    <div class="rounded-3xl bg-emerald-50 p-5">
                        <div class="mb-5 flex items-center gap-3">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-500 text-white">
                                <ClipboardList class="h-6 w-6" />
                            </div>
                            <div>
                                <h2 class="text-2xl font-black">Mulai dari akunmu</h2>
                                <p class="text-sm font-bold text-slate-500">Login atau register untuk lanjut.</p>
                            </div>
                        </div>

                        <div class="grid gap-3">
                            <Link
                                :href="route('login')"
                                class="flex h-14 items-center justify-center gap-2 rounded-2xl bg-emerald-500 px-5 text-base font-black text-white shadow-[0_6px_0_#159447] transition hover:-translate-y-0.5"
                            >
                                <LogIn class="h-5 w-5" />
                                Login
                            </Link>
                            <Link
                                :href="route('register')"
                                class="flex h-14 items-center justify-center gap-2 rounded-2xl border-2 border-emerald-200 bg-white px-5 text-base font-black text-emerald-700 shadow-[0_6px_0_#bbf7d0] transition hover:-translate-y-0.5"
                            >
                                <UserPlus class="h-5 w-5" />
                                Register
                            </Link>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </main>
</template>
