<script setup lang="ts">
import AlsenformLayout from '@/layouts/AlsenformLayout.vue';
import { Head, Link, usePoll } from '@inertiajs/vue3';
import {
    Activity,
    ArrowLeft,
    ChartColumn,
    CheckCircle2,
    Database,
    FileText,
    GraduationCap,
    HardDrive,
    Layers,
    MonitorCog,
    ServerCrash,
    ShieldCheck,
    TriangleAlert,
    XCircle,
} from 'lucide-vue-next';
import type { Component } from 'vue';
import { computed } from 'vue';

type Tone = 'good' | 'warn' | 'bad';
interface HealthCheck {
    key: string;
    label: string;
    value: string;
    detail: string | null;
    tone: Tone;
    hint: string | null;
}

/*
 * Kesehatan Server: what to look at on the server before and during an exam, each with what to do
 * when it is not fine. Refreshed every 10 seconds.
 */
const props = defineProps<{
    health: { overall: Tone; checks: HealthCheck[]; checkedAt: string | null };
}>();

usePoll(10000, { only: ['health'] });

const icons: Record<string, Component> = {
    database: Database,
    exams: GraduationCap,
    server_errors: ServerCrash,
    backup: ShieldCheck,
    disk: HardDrive,
    migrations: Layers,
    build: MonitorCog,
    environment: Activity,
    logs: FileText,
};

const TONES: Record<Tone, { card: string; icon: string; badge: string; label: string }> = {
    good: { card: 'border-slate-200/80', icon: 'bg-emerald-50 text-emerald-600', badge: 'bg-emerald-50 text-emerald-700', label: 'Baik' },
    warn: { card: 'border-amber-200', icon: 'bg-amber-50 text-amber-600', badge: 'bg-amber-100 text-amber-800', label: 'Perlu dilihat' },
    bad: { card: 'border-rose-300', icon: 'bg-rose-50 text-rose-600', badge: 'bg-rose-100 text-rose-800', label: 'Perlu diperbaiki' },
};

const summary = computed(
    () =>
        ({
            good: { icon: CheckCircle2, box: 'border-emerald-200 bg-emerald-50 text-emerald-900', text: 'Server siap dipakai ujian.' },
            warn: {
                icon: TriangleAlert,
                box: 'border-amber-200 bg-amber-50 text-amber-900',
                text: 'Server berjalan, tetapi ada yang perlu dilihat.',
            },
            bad: { icon: XCircle, box: 'border-rose-200 bg-rose-50 text-rose-900', text: 'Ada yang perlu diperbaiki sebelum ujian.' },
        })[props.health.overall],
);
</script>

<template>
    <AlsenformLayout>
        <Head title="Kesehatan Server" />

        <main class="mx-auto max-w-6xl px-4 py-6 sm:px-6 sm:py-8">
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
                        <Activity class="h-3.5 w-3.5" />
                        Admin
                    </p>
                    <h1 class="mt-1 text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">Kesehatan Server</h1>
                    <p class="mt-1 text-sm text-slate-500">Periksa sebelum ujian dimulai. Diperbarui otomatis tiap 10 detik.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link
                        :href="route('server-errors.index')"
                        class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
                    >
                        <ServerCrash class="h-3.5 w-3.5 text-rose-500" />
                        Galat Server
                    </Link>
                    <Link
                        :href="route('reports.index')"
                        class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
                    >
                        <ChartColumn class="h-3.5 w-3.5 text-slate-500" />
                        Laporan Aktivitas
                    </Link>
                </div>
            </div>

            <div :class="['mt-6 flex items-center gap-3 rounded-2xl border px-4 py-3 text-sm font-semibold', summary.box]" role="status">
                <component :is="summary.icon" class="h-5 w-5 shrink-0" />
                <span>{{ summary.text }}</span>
                <span v-if="health.checkedAt" class="ml-auto text-xs font-medium opacity-70">Diperiksa {{ health.checkedAt }}</span>
            </div>

            <ul class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <li v-for="check in health.checks" :key="check.key" :class="['rounded-2xl border bg-white p-4 shadow-sm', TONES[check.tone].card]">
                    <div class="flex items-start justify-between gap-3">
                        <div :class="['flex h-9 w-9 shrink-0 items-center justify-center rounded-xl', TONES[check.tone].icon]">
                            <component :is="icons[check.key] ?? Activity" class="h-4 w-4" />
                        </div>
                        <span :class="['rounded-full px-2 py-0.5 text-[10px] font-extrabold uppercase', TONES[check.tone].badge]">{{
                            TONES[check.tone].label
                        }}</span>
                    </div>
                    <p class="mt-3 text-[11px] font-bold uppercase tracking-wider text-slate-500">{{ check.label }}</p>
                    <p class="mt-0.5 text-lg font-black text-slate-900">{{ check.value }}</p>
                    <p v-if="check.detail" class="mt-0.5 break-words text-xs text-slate-500">{{ check.detail }}</p>
                    <p v-if="check.hint" class="mt-2 rounded-xl bg-slate-50 px-3 py-2 text-xs font-medium text-slate-700">{{ check.hint }}</p>
                </li>
            </ul>
        </main>
    </AlsenformLayout>
</template>
