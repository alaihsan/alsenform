<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { AArrowDown, ClipboardList, FilePlus2, Folder, Grid3X3, List, Menu, MoreVertical, Search, Send, Sparkles, Users } from 'lucide-vue-next';
import { computed, ref } from 'vue';

type RecentForm = {
    id: number;
    title: string;
    description: string;
    editUrl: string;
    publicUrl: string;
    updatedLabel: string;
    tone: string;
    stripe: string;
    accent: string;
};

const props = defineProps<{
    recentForms: RecentForm[];
}>();

const searchQuery = ref('');
const normalizedSearchQuery = computed(() => searchQuery.value.trim().toLowerCase());
const filteredRecentForms = computed(() => {
    if (!normalizedSearchQuery.value) {
        return props.recentForms;
    }

    return props.recentForms.filter((form) =>
        [form.title, form.description, form.updatedLabel].some((value) => value.toLowerCase().includes(normalizedSearchQuery.value)),
    );
});

const templates = [
    { title: 'Blank form', slug: 'blank', theme: 'blank', color: 'bg-white' },
    { title: 'Contact Information', slug: 'contact-information', theme: 'contact', color: 'bg-emerald-50' },
    { title: 'Party Invite', slug: 'party-invite', theme: 'party', color: 'bg-fuchsia-50' },
    { title: 'Work Request', slug: 'work-request', theme: 'work', color: 'bg-cyan-50' },
    { title: 'RSVP', slug: 'rsvp', theme: 'rsvp', color: 'bg-orange-50' },
    { title: 'T-Shirt Sign Up', slug: 't-shirt-sign-up', theme: 'shirt', color: 'bg-violet-50' },
];
</script>

<template>
    <Head title="Forms" />

    <main class="min-h-screen bg-white text-slate-900">
        <header class="sticky top-0 z-30 border-b border-slate-100 bg-white">
            <div class="flex h-16 items-center gap-3 px-4 sm:px-6">
                <button
                    type="button"
                    aria-label="Open menu"
                    class="flex h-9 w-9 items-center justify-center rounded-full text-slate-600 hover:bg-slate-100"
                >
                    <Menu class="h-6 w-6" />
                </button>

                <div class="flex items-center gap-2.5">
                    <div class="grid h-9 w-9 grid-cols-2 gap-1 rounded-2xl bg-indigo-500 p-1.5 text-white shadow-[0_3px_0_#4338ca]">
                        <span class="rounded-lg bg-white/95"></span>
                        <span class="rounded-lg bg-white/70"></span>
                        <span class="rounded-lg bg-white/70"></span>
                        <span class="rounded-lg bg-white/95"></span>
                    </div>
                    <h1 class="text-2xl font-semibold tracking-normal">Forms</h1>
                </div>

                <label class="mx-auto hidden h-12 w-full max-w-3xl items-center gap-3 rounded-full bg-slate-100 px-5 text-slate-500 md:flex">
                    <Search class="h-5 w-5" />
                    <input
                        v-model="searchQuery"
                        type="search"
                        class="w-full border-0 bg-transparent text-base font-medium text-slate-700 outline-none placeholder:text-slate-500"
                        placeholder="Search quiz"
                    />
                </label>

                <div class="ml-auto flex items-center gap-2">
                    <button
                        type="button"
                        aria-label="Apps"
                        class="hidden h-9 w-9 items-center justify-center rounded-full text-slate-600 hover:bg-slate-100 sm:flex"
                    >
                        <Grid3X3 class="h-5 w-5" />
                    </button>
                    <Link
                        :href="route('logout')"
                        method="post"
                        as="button"
                        class="h-10 rounded-full border-2 border-emerald-400 bg-gradient-to-br from-lime-200 via-emerald-300 to-sky-300 px-4 text-sm font-black text-slate-800"
                    >
                        Keluar
                    </Link>
                </div>
            </div>
        </header>

        <section class="bg-slate-100/80">
            <div class="mx-auto max-w-[1360px] px-5 py-6 sm:px-8">
                <label class="mb-5 flex h-12 items-center gap-3 rounded-full bg-white px-5 text-slate-500 shadow-sm md:hidden">
                    <Search class="h-5 w-5" />
                    <input
                        v-model="searchQuery"
                        type="search"
                        class="w-full border-0 bg-transparent text-base font-medium text-slate-700 outline-none placeholder:text-slate-500"
                        placeholder="Search quiz"
                    />
                </label>

                <div class="mb-5 flex items-center justify-between gap-4">
                    <h2 class="text-xl font-semibold">Start a new form</h2>
                    <div class="hidden items-center gap-4 text-base font-semibold text-slate-700 md:flex">
                        <span>Template gallery</span>
                        <AArrowDown class="h-5 w-5" />
                        <span class="h-9 border-l border-slate-300"></span>
                        <MoreVertical class="h-6 w-6" />
                    </div>
                </div>

                <div class="flex gap-6 overflow-x-auto pb-2">
                    <a
                        v-for="template in templates"
                        :key="template.title"
                        :href="route('forms.create', { template: template.slug })"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="group w-44 shrink-0 text-left"
                    >
                        <div
                            :class="[
                                'flex aspect-[4/3] items-center justify-center overflow-hidden rounded-2xl border border-slate-300 transition group-hover:border-emerald-500',
                                template.color,
                            ]"
                        >
                            <div v-if="template.theme === 'blank'" class="relative h-16 w-16">
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
                        <p class="mt-2 truncate text-base font-semibold text-slate-900">{{ template.title }}</p>
                    </a>
                </div>
            </div>
        </section>

        <section class="mx-auto max-w-[1360px] px-5 py-8 sm:px-8">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-5">
                <div>
                    <h2 class="text-xl font-semibold">Recent forms</h2>
                    <p v-if="searchQuery.trim()" class="mt-1 text-sm font-medium text-slate-500">
                        Menampilkan hasil pencarian: <span class="text-slate-800">"{{ searchQuery.trim() }}"</span>
                    </p>
                </div>
                <div class="flex items-center gap-5 text-slate-600">
                    <button type="button" class="flex items-center gap-2 text-base font-medium">
                        Owned by anyone
                        <span class="h-0 w-0 border-x-4 border-t-4 border-x-transparent border-t-slate-500"></span>
                    </button>
                    <List class="h-6 w-6" />
                    <AArrowDown class="h-6 w-6" />
                    <Folder class="h-7 w-7" />
                </div>
            </div>

            <div v-if="filteredRecentForms.length" class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5">
                <a
                    v-for="form in filteredRecentForms"
                    :key="form.id"
                    :href="form.editUrl"
                    class="overflow-hidden rounded-2xl border border-slate-300 bg-white transition hover:-translate-y-0.5 hover:shadow-lg"
                >
                    <div :class="['flex aspect-[4/3] items-start justify-center p-3', form.tone]">
                        <div class="w-2/3 overflow-hidden rounded-xl bg-white shadow">
                            <div :class="['h-7', form.stripe]"></div>
                            <div class="space-y-1.5 p-2.5">
                                <div class="h-2.5 w-4/5 rounded-full bg-slate-300"></div>
                                <div class="h-6 rounded-lg bg-slate-100"></div>
                                <div class="h-6 rounded-lg bg-slate-100"></div>
                                <div class="h-6 rounded-lg bg-slate-100"></div>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-slate-200 p-4">
                        <h3 class="truncate text-base font-semibold text-slate-800">{{ form.title }}</h3>
                        <div class="mt-3 flex items-center gap-2 text-sm font-medium text-slate-500">
                            <span :class="['flex h-6 w-6 items-center justify-center rounded-lg text-white', form.accent]">
                                <ClipboardList class="h-4 w-4" />
                            </span>
                            <Users class="h-5 w-5" />
                            <span class="truncate">{{ form.updatedLabel }}</span>
                            <button type="button" aria-label="More options" class="ml-auto" @click.prevent>
                                <MoreVertical class="h-6 w-6" />
                            </button>
                        </div>
                    </div>
                </a>
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
                            : 'Buat form dari blank form atau template, lalu form akan muncul di sini.'
                    }}
                </p>
            </div>
        </section>

        <a
            :href="route('forms.create', { template: 'blank' })"
            target="_blank"
            rel="noopener noreferrer"
            aria-label="Create new form"
            class="fixed bottom-7 right-7 flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-500 text-white shadow-[0_6px_0_#159447] transition hover:-translate-y-0.5"
        >
            <FilePlus2 class="h-7 w-7" />
        </a>

        <div
            class="fixed bottom-8 left-8 hidden rounded-full bg-lime-100 px-4 py-3 text-sm font-black text-lime-700 shadow-sm lg:flex lg:items-center lg:gap-2"
        >
            <Sparkles class="h-4 w-4" />
            Template terbuka di tab baru
            <Send class="h-4 w-4" />
        </div>
    </main>
</template>
