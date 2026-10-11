<script setup lang="ts">
import AlsenformLayout from '@/layouts/AlsenformLayout.vue';
import { Head, Link, router, usePoll } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ChartColumn,
    ChevronLeft,
    ChevronRight,
    CircleHelp,
    CirclePlay,
    Download,
    LogIn,
    LogOut,
    Monitor,
    RefreshCw,
    Search,
    SendHorizontal,
    Smartphone,
    Tablet,
} from 'lucide-vue-next';
import type { Component } from 'vue';
import { computed, onUnmounted, reactive, ref, watch } from 'vue';

interface Activity {
    id: number;
    event: 'login' | 'logout' | 'exam_started' | 'exam_submitted';
    eventLabel: string;
    date: string;
    time: string;
    timezone: string;
    person: { name: string; identifier: string | null; role: string; kelas: string | null };
    form: { title: string; url: string | null } | null;
    deviceType: 'mobile' | 'tablet' | 'desktop' | 'unknown';
    deviceTypeLabel: string;
    device: string | null;
    os: string | null;
    browser: string | null;
    ip: string | null;
    userAgent: string | null;
    note: string | null;
}

interface Option {
    value: string;
    label: string;
}

const props = defineProps<{
    filters: { from: string; to: string; event: string | null; form: number | null; role: string | null; search: string };
    activities: {
        rows: Activity[];
        currentPage: number;
        lastPage: number;
        total: number;
        from: number | null;
        to: number | null;
        previousUrl: string | null;
        nextUrl: string | null;
    };
    summary: {
        logins: number;
        loginUsers: number;
        examStarted: number;
        examSubmitted: number;
        people: Record<'mobile' | 'tablet' | 'desktop' | 'unknown', number>;
    };
    forms: { id: number; title: string }[];
    eventOptions: Option[];
    roleOptions: Option[] | null;
    today: string;
    exportUrl: string;
}>();

const filterForm = reactive({
    from: props.filters.from,
    to: props.filters.to,
    event: props.filters.event ?? '',
    form: props.filters.form ? String(props.filters.form) : '',
    role: props.filters.role ?? '',
    search: props.filters.search,
});

const isLoading = ref(false);

const applyFilters = (): void => {
    const query = Object.fromEntries(Object.entries(filterForm).filter(([, value]) => value !== '' && value !== null));
    router.get(route('reports.index'), query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onStart: () => (isLoading.value = true),
        onFinish: () => (isLoading.value = false),
    });
};

watch(() => [filterForm.from, filterForm.to, filterForm.event, filterForm.form, filterForm.role], applyFilters);

// The server corrects a period whose end comes before its start.
watch(
    () => props.filters,
    (filters) => {
        if (filters.from !== filterForm.from || filters.to !== filterForm.to) {
            filterForm.from = filters.from;
            filterForm.to = filters.to;
        }
    },
);

let searchTimer: ReturnType<typeof setTimeout> | undefined;
watch(
    () => filterForm.search,
    () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(applyFilters, 400);
    },
);

/** A date `days` before today, as YYYY-MM-DD in the school's calendar. */
const daysBeforeToday = (days: number): string => {
    const [year, month, day] = props.today.split('-').map(Number);
    const date = new Date(Date.UTC(year, month - 1, day - days));
    return date.toISOString().slice(0, 10);
};

const quickRanges = [
    { label: 'Hari ini', days: 0 },
    { label: '7 hari', days: 6 },
    { label: '30 hari', days: 29 },
];

const isQuickRange = (days: number): boolean => filterForm.to === props.today && filterForm.from === daysBeforeToday(days);

const selectQuickRange = (days: number): void => {
    filterForm.from = daysBeforeToday(days);
    filterForm.to = props.today;
};

// While the period includes today, new logins and exam activity appear by themselves.
const isLive = computed(() => props.filters.to >= props.today);
const { start: startPolling, stop: stopPolling } = usePoll(30000, { only: ['activities', 'summary'] }, { autoStart: false });
watch(isLive, (live) => (live ? startPolling() : stopPolling()), { immediate: true });
onUnmounted(() => {
    stopPolling();
    clearTimeout(searchTimer);
});

const refresh = (): void => {
    router.reload({ only: ['activities', 'summary', 'forms'], onStart: () => (isLoading.value = true), onFinish: () => (isLoading.value = false) });
};

const eventStyles: Record<Activity['event'], { icon: Component; badge: string }> = {
    login: { icon: LogIn, badge: 'bg-emerald-50 text-emerald-700 ring-emerald-200' },
    logout: { icon: LogOut, badge: 'bg-slate-100 text-slate-600 ring-slate-200' },
    exam_started: { icon: CirclePlay, badge: 'bg-indigo-50 text-indigo-700 ring-indigo-200' },
    exam_submitted: { icon: SendHorizontal, badge: 'bg-amber-50 text-amber-700 ring-amber-200' },
};

const deviceIcons: Record<Activity['deviceType'], Component> = {
    mobile: Smartphone,
    tablet: Tablet,
    desktop: Monitor,
    unknown: CircleHelp,
};

const deviceSummary = computed(() =>
    [
        { label: 'HP', icon: Smartphone, total: props.summary.people.mobile },
        { label: 'Komputer', icon: Monitor, total: props.summary.people.desktop },
        { label: 'Tablet', icon: Tablet, total: props.summary.people.tablet },
    ].concat(props.summary.people.unknown > 0 ? [{ label: 'Lainnya', icon: CircleHelp, total: props.summary.people.unknown }] : []),
);

const personDetail = (activity: Activity): string =>
    [activity.person.identifier, activity.person.role, activity.person.kelas].filter((part) => part).join(' · ');

const deviceDetail = (activity: Activity): string => [activity.os, activity.deviceTypeLabel].filter((part) => part).join(' · ');

const inputClass =
    'w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/20';
const labelClass = 'mb-1 block text-[11px] font-bold uppercase tracking-wider text-slate-500';
</script>

<template>
    <AlsenformLayout>
        <Head title="Laporan Aktivitas" />

        <main class="mx-auto max-w-7xl px-4 py-6 sm:px-6 sm:py-8">
            <Link
                :href="route('dashboard')"
                class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 transition hover:text-indigo-700"
            >
                <ArrowLeft class="h-4 w-4" />
                Kembali ke Beranda
            </Link>

            <div class="mt-3 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div class="min-w-0">
                    <p class="flex items-center gap-1.5 text-xs font-black uppercase tracking-[0.18em] text-indigo-500">
                        <ChartColumn class="h-3.5 w-3.5" />
                        Laporan
                    </p>
                    <h1 class="mt-1 text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">Aktivitas Login & Ujian</h1>
                    <p class="mt-1 text-sm text-slate-500">Siapa yang masuk dan mengerjakan ujian, dari perangkat dan browser apa, beserta jamnya.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
                        @click="refresh"
                    >
                        <RefreshCw :class="['h-3.5 w-3.5 text-slate-500', isLoading ? 'animate-spin' : '']" />
                        Perbarui
                    </button>
                    <a
                        :href="exportUrl"
                        class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-3.5 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-emerald-700"
                    >
                        <Download class="h-3.5 w-3.5" />
                        Unduh Excel
                    </a>
                </div>
            </div>

            <!-- Filters -->
            <section class="mt-6 rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm">
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        v-for="range in quickRanges"
                        :key="range.days"
                        type="button"
                        :class="[
                            'rounded-full border px-3 py-1 text-xs font-bold transition',
                            isQuickRange(range.days)
                                ? 'border-indigo-200 bg-indigo-50 text-indigo-700'
                                : 'border-slate-200 bg-white text-slate-600 hover:border-indigo-200 hover:text-indigo-700',
                        ]"
                        @click="selectQuickRange(range.days)"
                    >
                        {{ range.label }}
                    </button>
                    <span v-if="isLive" class="ml-auto inline-flex items-center gap-1.5 text-[11px] font-semibold text-emerald-700">
                        <span class="relative flex h-2 w-2">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
                        </span>
                        Diperbarui otomatis tiap 30 detik
                    </span>
                </div>

                <div :class="['mt-4 grid grid-cols-2 gap-3', roleOptions ? 'lg:grid-cols-7' : 'lg:grid-cols-6']">
                    <label class="min-w-0">
                        <span :class="labelClass">Dari</span>
                        <input v-model="filterForm.from" type="date" :max="today" :class="inputClass" />
                    </label>
                    <label class="min-w-0">
                        <span :class="labelClass">Sampai</span>
                        <input v-model="filterForm.to" type="date" :max="today" :class="inputClass" />
                    </label>
                    <label class="min-w-0">
                        <span :class="labelClass">Aktivitas</span>
                        <select v-model="filterForm.event" :class="inputClass">
                            <option value="">Semua aktivitas</option>
                            <option v-for="option in eventOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                    </label>
                    <label class="min-w-0">
                        <span :class="labelClass">Ujian</span>
                        <select v-model="filterForm.form" :class="inputClass">
                            <option value="">Semua ujian</option>
                            <option v-for="examForm in forms" :key="examForm.id" :value="String(examForm.id)">{{ examForm.title }}</option>
                        </select>
                    </label>
                    <label v-if="roleOptions" class="min-w-0">
                        <span :class="labelClass">Peran</span>
                        <select v-model="filterForm.role" :class="inputClass">
                            <option value="">Semua peran</option>
                            <option v-for="option in roleOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                    </label>
                    <label class="col-span-2 min-w-0">
                        <span :class="labelClass">Cari</span>
                        <span class="relative block">
                            <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input
                                v-model="filterForm.search"
                                type="search"
                                placeholder="Nama, NIS, NIP, email, atau IP"
                                :class="[inputClass, 'pl-9']"
                            />
                        </span>
                    </label>
                </div>
            </section>

            <!-- Totals of the period -->
            <section class="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm">
                    <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-slate-500">
                        <LogIn class="h-4 w-4 text-emerald-600" />
                        Login
                    </div>
                    <p class="mt-2 text-2xl font-black text-slate-900">{{ summary.logins }}</p>
                    <p class="text-xs font-medium text-slate-500">oleh {{ summary.loginUsers }} pengguna</p>
                </div>
                <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm">
                    <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-slate-500">
                        <CirclePlay class="h-4 w-4 text-indigo-600" />
                        Mulai Ujian
                    </div>
                    <p class="mt-2 text-2xl font-black text-slate-900">{{ summary.examStarted }}</p>
                    <p class="text-xs font-medium text-slate-500">ujian dimulai</p>
                </div>
                <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm">
                    <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-slate-500">
                        <SendHorizontal class="h-4 w-4 text-amber-600" />
                        Jawaban Dikirim
                    </div>
                    <p class="mt-2 text-2xl font-black text-slate-900">{{ summary.examSubmitted }}</p>
                    <p class="text-xs font-medium text-slate-500">ujian selesai</p>
                </div>
                <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm">
                    <div class="text-xs font-bold uppercase tracking-wider text-slate-500">Pengguna per Perangkat</div>
                    <ul class="mt-2 space-y-1">
                        <li v-for="item in deviceSummary" :key="item.label" class="flex items-center gap-2 text-sm text-slate-600">
                            <component :is="item.icon" class="h-4 w-4 text-slate-400" />
                            <span class="flex-1">{{ item.label }}</span>
                            <span class="font-black text-slate-900">{{ item.total }}</span>
                        </li>
                    </ul>
                </div>
            </section>

            <!-- Activities -->
            <section
                :class="['mt-4 overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm transition', isLoading ? 'opacity-60' : '']"
            >
                <div v-if="activities.rows.length === 0" class="flex flex-col items-center px-6 py-14 text-center">
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-500">
                        <ChartColumn class="h-7 w-7" />
                    </div>
                    <p class="mt-4 text-base font-black text-slate-900">Belum ada aktivitas</p>
                    <p class="mt-1 max-w-md text-sm text-slate-500">
                        Tidak ada login atau ujian yang cocok dengan filter ini. Aktivitas tercatat mulai sejak fitur laporan ini dipasang.
                    </p>
                </div>

                <template v-else>
                    <!-- Phones and tablets: one card per activity -->
                    <ul class="divide-y divide-slate-100 lg:hidden">
                        <li v-for="activity in activities.rows" :key="activity.id" class="space-y-2 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <span
                                    :class="[
                                        'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-bold ring-1 ring-inset',
                                        eventStyles[activity.event].badge,
                                    ]"
                                >
                                    <component :is="eventStyles[activity.event].icon" class="h-3.5 w-3.5" />
                                    {{ activity.eventLabel }}
                                </span>
                                <span class="text-right">
                                    <span class="block text-sm font-black tabular-nums text-slate-900"
                                        >{{ activity.time }} {{ activity.timezone }}</span
                                    >
                                    <span class="block text-[11px] text-slate-500">{{ activity.date }}</span>
                                </span>
                            </div>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-bold text-slate-900">{{ activity.person.name }}</p>
                                <p class="truncate text-xs text-slate-500">{{ personDetail(activity) }}</p>
                            </div>
                            <p v-if="activity.form" class="text-xs text-slate-600">
                                Ujian:
                                <Link v-if="activity.form.url" :href="activity.form.url" class="font-semibold text-indigo-700 hover:underline">{{
                                    activity.form.title
                                }}</Link>
                                <span v-else class="font-semibold">{{ activity.form.title }}</span>
                                <span v-if="activity.note" class="ml-1 rounded bg-rose-50 px-1.5 py-0.5 text-[10px] font-bold text-rose-600">{{
                                    activity.note
                                }}</span>
                            </p>
                            <div class="flex items-start gap-2 rounded-xl bg-slate-50 p-2.5 text-xs" :title="activity.userAgent ?? undefined">
                                <component :is="deviceIcons[activity.deviceType]" class="mt-0.5 h-4 w-4 shrink-0 text-slate-500" />
                                <div class="min-w-0 flex-1">
                                    <p class="font-semibold text-slate-800">{{ activity.device ?? 'Perangkat tidak dikenal' }}</p>
                                    <p class="text-slate-500">{{ deviceDetail(activity) }}</p>
                                    <p class="mt-1 text-slate-700">{{ activity.browser ?? 'Browser tidak dikenal' }}</p>
                                    <p v-if="activity.ip" class="mt-0.5 font-mono text-[11px] text-slate-400">IP {{ activity.ip }}</p>
                                </div>
                            </div>
                        </li>
                    </ul>

                    <!-- Computers: a table -->
                    <div class="hidden overflow-x-auto lg:block">
                        <table class="w-full text-left text-sm">
                            <thead class="border-b border-slate-100 bg-slate-50/80 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                                <tr>
                                    <th class="px-4 py-3">Waktu</th>
                                    <th class="px-4 py-3">Pengguna</th>
                                    <th class="px-4 py-3">Aktivitas</th>
                                    <th class="px-4 py-3">Perangkat</th>
                                    <th class="px-4 py-3">Browser</th>
                                    <th class="px-4 py-3">Alamat IP</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="activity in activities.rows" :key="activity.id" class="align-top transition hover:bg-slate-50/60">
                                    <td class="whitespace-nowrap px-4 py-3">
                                        <span class="block font-black tabular-nums text-slate-900">{{ activity.time }} {{ activity.timezone }}</span>
                                        <span class="block text-xs text-slate-500">{{ activity.date }}</span>
                                    </td>
                                    <td class="max-w-[220px] px-4 py-3">
                                        <span class="block truncate font-bold text-slate-900">{{ activity.person.name }}</span>
                                        <span class="block truncate text-xs text-slate-500">{{ personDetail(activity) }}</span>
                                    </td>
                                    <td class="max-w-[260px] px-4 py-3">
                                        <span
                                            :class="[
                                                'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-bold ring-1 ring-inset',
                                                eventStyles[activity.event].badge,
                                            ]"
                                        >
                                            <component :is="eventStyles[activity.event].icon" class="h-3.5 w-3.5" />
                                            {{ activity.eventLabel }}
                                        </span>
                                        <span v-if="activity.form" class="mt-1 block truncate text-xs text-slate-600">
                                            <Link
                                                v-if="activity.form.url"
                                                :href="activity.form.url"
                                                class="font-semibold text-indigo-700 hover:underline"
                                                >{{ activity.form.title }}</Link
                                            >
                                            <span v-else class="font-semibold">{{ activity.form.title }}</span>
                                        </span>
                                        <span
                                            v-if="activity.note"
                                            class="mt-1 inline-block rounded bg-rose-50 px-1.5 py-0.5 text-[10px] font-bold text-rose-600"
                                            >{{ activity.note }}</span
                                        >
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="flex items-start gap-2">
                                            <component :is="deviceIcons[activity.deviceType]" class="mt-0.5 h-4 w-4 shrink-0 text-slate-400" />
                                            <span class="min-w-0">
                                                <span class="block font-semibold text-slate-800">{{ activity.device ?? 'Tidak dikenal' }}</span>
                                                <span class="block text-xs text-slate-500">{{ deviceDetail(activity) }}</span>
                                            </span>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-slate-700" :title="activity.userAgent ?? undefined">
                                        {{ activity.browser ?? 'Tidak dikenal' }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-slate-500">{{ activity.ip ?? '-' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div
                        class="flex flex-col gap-3 border-t border-slate-100 px-4 py-3 text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <span>
                            Menampilkan <strong class="text-slate-800">{{ activities.from }}–{{ activities.to }}</strong> dari
                            <strong class="text-slate-800">{{ activities.total }}</strong> aktivitas
                        </span>
                        <div class="flex items-center gap-2">
                            <component
                                :is="activities.previousUrl ? Link : 'span'"
                                :href="activities.previousUrl ?? undefined"
                                preserve-state
                                :class="[
                                    'inline-flex items-center gap-1 rounded-lg border px-2.5 py-1.5 font-semibold',
                                    activities.previousUrl ? 'border-slate-200 text-slate-700 hover:bg-slate-50' : 'border-slate-100 text-slate-300',
                                ]"
                            >
                                <ChevronLeft class="h-3.5 w-3.5" />
                                Sebelumnya
                            </component>
                            <span class="font-semibold text-slate-600">{{ activities.currentPage }} / {{ activities.lastPage }}</span>
                            <component
                                :is="activities.nextUrl ? Link : 'span'"
                                :href="activities.nextUrl ?? undefined"
                                preserve-state
                                :class="[
                                    'inline-flex items-center gap-1 rounded-lg border px-2.5 py-1.5 font-semibold',
                                    activities.nextUrl ? 'border-slate-200 text-slate-700 hover:bg-slate-50' : 'border-slate-100 text-slate-300',
                                ]"
                            >
                                Berikutnya
                                <ChevronRight class="h-3.5 w-3.5" />
                            </component>
                        </div>
                    </div>
                </template>
            </section>

            <p class="mt-3 text-[11px] leading-relaxed text-slate-400">
                Jam ditampilkan dalam zona waktu sekolah. Arahkan kursor ke nama browser untuk melihat data lengkap dari browser (User-Agent). Chrome
                di HP Android tidak menyebutkan merek dan tipe HP, sehingga tercatat sebagai "HP Android".
            </p>
        </main>
    </AlsenformLayout>
</template>
