<script setup lang="ts">
import AlsenformLayout from '@/layouts/AlsenformLayout.vue';
import { Head } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Ban,
    CircleAlert,
    FileWarning,
    Hourglass,
    House,
    LogIn,
    RefreshCw,
    SearchX,
    ServerCrash,
    ShieldAlert,
    TimerReset,
    Wrench,
} from 'lucide-vue-next';
import type { Component } from 'vue';
import { computed } from 'vue';

/** A button of the error page; without an address it goes back in the browser history. */
interface ErrorAction {
    label: string;
    href: string | null;
    icon: string;
}

/**
 * Shown for errors during visits inside the app (App\Support\ErrorPage). Full page loads get the
 * matching Blade view in resources/views/errors, which looks the same.
 */
const props = defineProps<{
    status: number;
    title: string;
    message: string;
    detail: string | null;
    hint: string;
    icon: string;
    tone: 'indigo' | 'amber' | 'sky' | 'rose';
    primaryAction: ErrorAction;
    secondaryAction: ErrorAction;
    occurredAt: string | null;
}>();

const icons: Record<string, Component> = {
    'arrow-left': ArrowLeft,
    ban: Ban,
    'circle-alert': CircleAlert,
    'file-warning': FileWarning,
    hourglass: Hourglass,
    house: House,
    'log-in': LogIn,
    'refresh-cw': RefreshCw,
    'search-x': SearchX,
    'server-crash': ServerCrash,
    'shield-alert': ShieldAlert,
    'timer-reset': TimerReset,
    wrench: Wrench,
};

const tones = {
    indigo: { icon: 'bg-indigo-50 text-indigo-600 shadow-[0_5px_0_#c7d2fe]', code: 'text-indigo-500' },
    amber: { icon: 'bg-amber-50 text-amber-600 shadow-[0_5px_0_#fde68a]', code: 'text-amber-600' },
    sky: { icon: 'bg-sky-50 text-sky-600 shadow-[0_5px_0_#bae6fd]', code: 'text-sky-600' },
    rose: { icon: 'bg-rose-50 text-rose-600 shadow-[0_5px_0_#fecdd3]', code: 'text-rose-600' },
};

const tone = computed(() => tones[props.tone] ?? tones.indigo);
const iconFor = (name: string): Component => icons[name] ?? CircleAlert;

const goBack = (): void => {
    if (window.history.length > 1) {
        window.history.back();
        return;
    }
    window.location.assign(props.primaryAction.href ?? '/');
};
</script>

<template>
    <AlsenformLayout minimal>
        <Head :title="title" />

        <main class="flex justify-center px-4 py-10 sm:px-6 sm:py-16">
            <section class="w-full max-w-xl rounded-[2rem] border border-indigo-50 bg-white p-6 text-center shadow-[0_10px_0_#e0e7ff] sm:p-10">
                <div :class="['mx-auto flex h-16 w-16 items-center justify-center rounded-2xl', tone.icon]">
                    <component :is="iconFor(icon)" class="h-8 w-8" />
                </div>
                <p :class="['mt-6 text-xs font-black uppercase tracking-[0.2em]', tone.code]">Kode Error {{ status }}</p>
                <h1 class="mt-2 text-2xl font-black leading-tight text-slate-900 sm:text-3xl">{{ title }}</h1>
                <p class="mt-3 text-sm leading-relaxed text-slate-600 sm:text-[15px]">{{ message }}</p>

                <p
                    v-if="detail"
                    class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-left text-sm font-semibold text-amber-900"
                >
                    <strong class="mb-0.5 block text-[11px] font-extrabold uppercase tracking-wider text-amber-700">Keterangan</strong>
                    {{ detail }}
                </p>

                <p class="mt-4 text-[13px] font-semibold leading-normal text-slate-500">{{ hint }}</p>

                <div class="mt-7 flex flex-col-reverse gap-2.5 sm:flex-row sm:justify-center">
                    <a
                        v-if="secondaryAction.href"
                        :href="secondaryAction.href"
                        class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-bold text-slate-700 transition hover:bg-slate-50"
                    >
                        <component :is="iconFor(secondaryAction.icon)" class="h-4 w-4" />
                        {{ secondaryAction.label }}
                    </a>
                    <button
                        v-else
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-bold text-slate-700 transition hover:bg-slate-50"
                        @click="goBack"
                    >
                        <component :is="iconFor(secondaryAction.icon)" class="h-4 w-4" />
                        {{ secondaryAction.label }}
                    </button>
                    <!-- A full page load (not an Inertia visit) also renews the session token and assets. -->
                    <a
                        :href="primaryAction.href ?? '/'"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-5 py-3 text-sm font-bold text-white shadow-[0_4px_0_#3730a3] transition hover:-translate-y-px hover:bg-indigo-700"
                    >
                        <component :is="iconFor(primaryAction.icon)" class="h-4 w-4" />
                        {{ primaryAction.label }}
                    </a>
                </div>

                <p v-if="occurredAt" class="mt-6 text-[11px] text-slate-400">Waktu kejadian: {{ occurredAt }}</p>
            </section>
        </main>
    </AlsenformLayout>
</template>
