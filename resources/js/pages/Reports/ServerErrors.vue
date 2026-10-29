<script setup lang="ts">
import AlsenformLayout from '@/layouts/AlsenformLayout.vue';
import { Head, Link, router, usePoll } from '@inertiajs/vue3';
import axios from 'axios';
import { ArrowLeft, CheckCircle2, ChevronDown, ChevronLeft, ChevronRight, RefreshCw, RotateCcw, Search, ServerCrash } from 'lucide-vue-next';
import { reactive, ref, watch } from 'vue';

interface ServerErrorRow {
    id: number;
    exception: string;
    exceptionClass: string;
    message: string;
    location: string | null;
    occurrences: number;
    firstSeen: string | null;
    lastSeen: string | null;
    lastSeenAgo: string | null;
    lastReference: string | null;
    lastRequest: string | null;
    lastUser: string | null;
    isResolved: boolean;
    resolvedAt: string | null;
    occurrencesUrl: string;
    updateUrl: string;
}

interface Occurrence {
    id: number;
    reference: string | null;
    date: string;
    time: string;
    timezone: string;
    person: { name: string; identifier: string | null; role: string; kelas: string | null };
    form: { title: string; url: string | null } | null;
    device: string | null;
    os: string | null;
    browser: string | null;
    ip: string | null;
    detail: string | null;
}

/*
 * Galat Server: every fault of the server once, newest first, with how often it happened and to
 * whom. The code a student reads from an error page (ERR-...) finds its fault.
 */
const props = defineProps<{
    filters: { status: 'open' | 'resolved' | 'all'; search: string };
    errors: { rows: ServerErrorRow[]; total: number; previousUrl: string | null; nextUrl: string | null };
    summary: { open: number; lastHour: number; lastDay: number };
}>();

const filterForm = reactive({ status: props.filters.status, search: props.filters.search });
const isLoading = ref(false);

const applyFilters = (): void => {
    router.get(
        route('server-errors.index'),
        { status: filterForm.status, ...(filterForm.search ? { search: filterForm.search } : {}) },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => (isLoading.value = true),
            onFinish: () => (isLoading.value = false),
        },
    );
};

watch(() => filterForm.status, applyFilters);

let searchTimer: ReturnType<typeof setTimeout> | undefined;
watch(
    () => filterForm.search,
    () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(applyFilters, 400);
    },
);

usePoll(30000, { only: ['errors', 'summary'] });

const refresh = (): void => {
    router.reload({ only: ['errors', 'summary'], onStart: () => (isLoading.value = true), onFinish: () => (isLoading.value = false) });
};

const statusTabs = [
    { value: 'open', label: 'Belum selesai' },
    { value: 'resolved', label: 'Sudah selesai' },
    { value: 'all', label: 'Semua' },
] as const;

/* The occurrences of the faults that are opened, fetched once each. */
const occurrences = reactive<Record<number, Occurrence[] | 'loading' | 'error'>>({});
const opened = ref<number[]>([]);

const toggleOccurrences = async (error: ServerErrorRow): Promise<void> => {
    if (opened.value.includes(error.id)) {
        opened.value = opened.value.filter((id) => id !== error.id);
        return;
    }
    opened.value = [...opened.value, error.id];
    occurrences[error.id] = 'loading';
    try {
        const response = await axios.get(error.occurrencesUrl, { timeout: 15000 });
        occurrences[error.id] = response.data.occurrences;
    } catch {
        occurrences[error.id] = 'error';
    }
};

const updatingIds = ref<number[]>([]);

const setResolved = (error: ServerErrorRow, resolved: boolean): void => {
    updatingIds.value = [...updatingIds.value, error.id];
    router.patch(
        error.updateUrl,
        { resolved },
        {
            preserveScroll: true,
            only: ['errors', 'summary'],
            onFinish: () => (updatingIds.value = updatingIds.value.filter((id) => id !== error.id)),
        },
    );
};

const deviceOf = (occurrence: Occurrence): string => [occurrence.device, occurrence.os, occurrence.browser].filter((part) => part).join(' · ');
</script>

<template>
    <AlsenformLayout>
        <Head title="Galat Server" />

        <main class="mx-auto max-w-6xl px-4 py-6 sm:px-6 sm:py-8">
            <Link
                :href="route('server-health.index')"
                class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 transition hover:text-indigo-700"
            >
                <ArrowLeft class="h-4 w-4" />
                Kesehatan Server
            </Link>

            <div class="mt-3 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div class="min-w-0">
                    <p class="flex items-center gap-1.5 text-xs font-black uppercase tracking-[0.18em] text-rose-500">
                        <ServerCrash class="h-3.5 w-3.5" />
                        Laporan
                    </p>
                    <h1 class="mt-1 text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">Galat Server</h1>
                    <p class="mt-1 text-sm text-slate-500">
                        Setiap kesalahan server dicatat sekali beserta jumlah kejadiannya. Ketik kode dari layar siswa (ERR-...) untuk menemukannya.
                    </p>
                </div>
                <button
                    type="button"
                    class="inline-flex items-center gap-2 self-start rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 sm:self-auto"
                    @click="refresh"
                >
                    <RefreshCw :class="['h-3.5 w-3.5 text-slate-500', isLoading ? 'animate-spin' : '']" />
                    Perbarui
                </button>
            </div>

            <section class="mt-6 grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Belum selesai</p>
                    <p :class="['mt-1 text-3xl font-black tabular-nums', summary.open ? 'text-rose-600' : 'text-emerald-600']">{{ summary.open }}</p>
                    <p class="text-xs text-slate-500">jenis kesalahan</p>
                </div>
                <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">1 jam terakhir</p>
                    <p :class="['mt-1 text-3xl font-black tabular-nums', summary.lastHour ? 'text-amber-600' : 'text-slate-900']">
                        {{ summary.lastHour }}
                    </p>
                    <p class="text-xs text-slate-500">kejadian di halaman</p>
                </div>
                <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">24 jam terakhir</p>
                    <p class="mt-1 text-3xl font-black tabular-nums text-slate-900">{{ summary.lastDay }}</p>
                    <p class="text-xs text-slate-500">kejadian di halaman</p>
                </div>
            </section>

            <section class="mt-4 flex flex-col gap-3 rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm sm:flex-row sm:items-center">
                <div class="flex flex-wrap gap-1.5" role="group" aria-label="Saring menurut status">
                    <button
                        v-for="tab in statusTabs"
                        :key="tab.value"
                        type="button"
                        :aria-pressed="filterForm.status === tab.value"
                        :class="[
                            'rounded-full border px-3 py-1 text-xs font-bold transition',
                            filterForm.status === tab.value
                                ? 'border-indigo-200 bg-indigo-50 text-indigo-700'
                                : 'border-slate-200 bg-white text-slate-600 hover:border-indigo-200 hover:text-indigo-700',
                        ]"
                        @click="filterForm.status = tab.value"
                    >
                        {{ tab.label }}
                    </button>
                </div>
                <label class="relative min-w-0 flex-1 sm:ml-auto sm:max-w-sm">
                    <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input
                        v-model="filterForm.search"
                        type="search"
                        placeholder="Kode ERR-..., pesan, atau berkas"
                        aria-label="Cari galat"
                        class="w-full rounded-xl border border-slate-200 bg-white py-2 pl-9 pr-3 text-sm text-slate-800 focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                    />
                </label>
            </section>

            <p
                v-if="!errors.rows.length"
                class="mt-4 rounded-2xl border border-dashed border-slate-200 bg-white px-4 py-10 text-center text-sm text-slate-500"
            >
                <CheckCircle2 class="mx-auto mb-2 h-8 w-8 text-emerald-500" />
                {{ filters.search ? 'Tidak ada galat yang cocok dengan pencarian ini.' : 'Tidak ada galat server di sini.' }}
            </p>

            <ul v-else class="mt-4 space-y-3">
                <li
                    v-for="error in errors.rows"
                    :key="error.id"
                    :class="[
                        'rounded-2xl border border-l-4 bg-white p-4 shadow-sm',
                        error.isResolved ? 'border-slate-200 border-l-emerald-400' : 'border-slate-200 border-l-rose-500',
                    ]"
                >
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <p class="flex flex-wrap items-center gap-2 text-sm font-extrabold text-slate-900">
                                <span :title="error.exceptionClass">{{ error.exception }}</span>
                                <span class="rounded-full bg-rose-50 px-2 py-0.5 text-[11px] font-bold tabular-nums text-rose-700"
                                    >{{ error.occurrences }}x</span
                                >
                                <span v-if="error.isResolved" class="rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-bold text-emerald-700"
                                    >Selesai {{ error.resolvedAt }}</span
                                >
                            </p>
                            <p v-if="error.location" class="mt-0.5 break-all font-mono text-[11px] text-slate-500">{{ error.location }}</p>
                            <p class="mt-2 line-clamp-3 whitespace-pre-wrap break-words font-mono text-xs text-slate-700">{{ error.message }}</p>
                            <p class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-[11px] text-slate-500">
                                <span>Terakhir {{ error.lastSeenAgo }} ({{ error.lastSeen }})</span>
                                <span>Pertama {{ error.firstSeen }}</span>
                                <span v-if="error.lastReference" class="select-all font-mono font-bold text-slate-700">{{
                                    error.lastReference
                                }}</span>
                                <span v-if="error.lastRequest" class="break-all font-mono">{{ error.lastRequest }}</span>
                                <span v-if="error.lastUser">oleh {{ error.lastUser }}</span>
                            </p>
                        </div>
                        <div class="flex shrink-0 flex-wrap gap-2">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-bold text-slate-700 transition hover:bg-slate-50"
                                :aria-expanded="opened.includes(error.id)"
                                @click="toggleOccurrences(error)"
                            >
                                <ChevronDown :class="['h-3.5 w-3.5 transition', opened.includes(error.id) ? 'rotate-180' : '']" />
                                Kejadian
                            </button>
                            <button
                                v-if="!error.isResolved"
                                type="button"
                                class="inline-flex items-center gap-1 rounded-xl bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white transition hover:bg-emerald-700 disabled:opacity-60"
                                :disabled="updatingIds.includes(error.id)"
                                @click="setResolved(error, true)"
                            >
                                <CheckCircle2 class="h-3.5 w-3.5" /> Tandai selesai
                            </button>
                            <button
                                v-else
                                type="button"
                                class="inline-flex items-center gap-1 rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-bold text-slate-700 transition hover:bg-slate-50 disabled:opacity-60"
                                :disabled="updatingIds.includes(error.id)"
                                @click="setResolved(error, false)"
                            >
                                <RotateCcw class="h-3.5 w-3.5" /> Buka lagi
                            </button>
                        </div>
                    </div>

                    <div v-if="opened.includes(error.id)" class="mt-3 border-t border-slate-100 pt-3">
                        <p v-if="occurrences[error.id] === 'loading'" class="text-xs text-slate-500">Memuat kejadian...</p>
                        <p v-else-if="occurrences[error.id] === 'error'" class="text-xs font-semibold text-rose-700">
                            Kejadian belum dapat dimuat. Coba lagi.
                        </p>
                        <p v-else-if="!(occurrences[error.id] as Occurrence[])?.length" class="text-xs text-slate-500">
                            Belum ada kejadian di halaman (misalnya galat dari perintah php artisan).
                        </p>
                        <div v-else class="overflow-x-auto">
                            <table class="w-full min-w-[640px] text-left text-xs">
                                <thead class="text-[10px] uppercase tracking-wider text-slate-500">
                                    <tr>
                                        <th class="py-1 pr-3 font-bold">Waktu</th>
                                        <th class="py-1 pr-3 font-bold">Kode</th>
                                        <th class="py-1 pr-3 font-bold">Orang</th>
                                        <th class="py-1 pr-3 font-bold">Perangkat</th>
                                        <th class="py-1 font-bold">Halaman</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr
                                        v-for="occurrence in occurrences[error.id] as Occurrence[]"
                                        :key="occurrence.id"
                                        class="align-top text-slate-700"
                                    >
                                        <td class="whitespace-nowrap py-1.5 pr-3 tabular-nums">{{ occurrence.date }} {{ occurrence.time }}</td>
                                        <td class="select-all py-1.5 pr-3 font-mono font-bold">{{ occurrence.reference }}</td>
                                        <td class="py-1.5 pr-3">
                                            {{ occurrence.person.name }}
                                            <span class="block text-[10px] text-slate-500">{{
                                                [occurrence.person.role, occurrence.person.kelas].filter(Boolean).join(' · ')
                                            }}</span>
                                        </td>
                                        <td class="py-1.5 pr-3">
                                            {{ deviceOf(occurrence) || '-' }}
                                            <span v-if="occurrence.ip" class="block font-mono text-[10px] text-slate-500">{{ occurrence.ip }}</span>
                                        </td>
                                        <td class="break-all py-1.5 font-mono text-[11px]">
                                            {{ occurrence.detail }}
                                            <span v-if="occurrence.form" class="block font-sans text-[10px] text-slate-500">{{
                                                occurrence.form.title
                                            }}</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </li>
            </ul>

            <nav v-if="errors.previousUrl || errors.nextUrl" class="mt-4 flex items-center justify-between text-sm" aria-label="Halaman">
                <Link
                    v-if="errors.previousUrl"
                    :href="errors.previousUrl"
                    preserve-scroll
                    class="inline-flex items-center gap-1 rounded-xl border border-slate-200 bg-white px-3 py-1.5 font-semibold text-slate-700 hover:bg-slate-50"
                >
                    <ChevronLeft class="h-4 w-4" /> Sebelumnya
                </Link>
                <span v-else></span>
                <Link
                    v-if="errors.nextUrl"
                    :href="errors.nextUrl"
                    preserve-scroll
                    class="inline-flex items-center gap-1 rounded-xl border border-slate-200 bg-white px-3 py-1.5 font-semibold text-slate-700 hover:bg-slate-50"
                >
                    Berikutnya <ChevronRight class="h-4 w-4" />
                </Link>
            </nav>
        </main>
    </AlsenformLayout>
</template>
