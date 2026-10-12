<script setup lang="ts">
import { Key, LockOpen, RefreshCw } from 'lucide-vue-next';
import { computed } from 'vue';

export type UnlockPanelEntry = {
    id: string;
    session_id: number | null;
    request_id: number | null;
    respondent_identifier: string;
    email: string | null;
    name?: string | null;
    kelas?: string | null;
    state: 'locked' | 'unlocked' | 'submitted';
    status: string;
    unlock_requested: boolean;
    locked_at?: string | null;
    unlocked_at?: string | null;
    submitted_at?: string | null;
    created_at?: string | null;
    blur_count?: number;
    last_blur_at?: string | null;
};

const props = defineProps<{
    unlockRequests: UnlockPanelEntry[];
    lockOnBlur: boolean;
    busyIds: string[];
    isUnlockingAll: boolean;
}>();

defineEmits<{
    refresh: [];
    unlock: [entry: UnlockPanelEntry];
    unlockAll: [];
    toggleLock: [];
}>();

const lockedEntries = computed(() => props.unlockRequests.filter((entry) => entry.state === 'locked'));
const otherEntries = computed(() => props.unlockRequests.filter((entry) => entry.state !== 'locked'));

const formatTime = (value?: string | null) =>
    value ? new Date(value).toLocaleString('id-ID', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit', second: '2-digit' }) : null;
</script>

<template>
    <section class="space-y-5">
        <div class="rounded-xl border border-slate-300 bg-white p-5 shadow-sm sm:p-7">
            <div class="border-b border-slate-100 pb-5">
                <h2 class="text-xl font-normal text-slate-950 sm:text-2xl">Keamanan & Buka Kunci</h2>
                <p class="mt-2 text-sm text-slate-500 sm:text-base">
                    Siswa yang pindah tab atau aplikasi saat ujian terkunci dan muncul di sini, walau belum meminta dibuka.
                </p>
            </div>

            <div class="mt-5 flex items-start justify-between gap-4 rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:p-5">
                <div>
                    <h3 class="text-base font-semibold text-slate-900">Kunci saat siswa pindah tab</h3>
                    <p class="mt-1 text-sm text-slate-600">
                        <template v-if="lockOnBlur">Aktif: siswa yang meninggalkan halaman ujian langsung terkunci.</template>
                        <template v-else>Nonaktif: siswa tidak dikunci. Mematikan fitur ini membuka semua siswa yang sedang terkunci.</template>
                    </p>
                    <p class="mt-1 text-xs text-slate-500">
                        Bisa diubah sebelum atau selama ujian dan langsung berlaku untuk siswa yang sedang mengerjakan, tanpa pemberitahuan ke siswa.
                    </p>
                </div>
                <button
                    type="button"
                    role="switch"
                    :aria-checked="lockOnBlur"
                    aria-label="Kunci saat siswa pindah tab"
                    :class="['relative mt-1 h-5 w-11 shrink-0 rounded-full transition', lockOnBlur ? 'bg-indigo-500' : 'bg-slate-300']"
                    @click="$emit('toggleLock')"
                >
                    <span
                        :class="[
                            'absolute top-1/2 h-8 w-8 -translate-y-1/2 rounded-full bg-white shadow-md transition',
                            lockOnBlur ? 'left-5' : '-left-1',
                        ]"
                    ></span>
                </button>
            </div>

            <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
                <h3 class="text-base font-semibold text-slate-900">
                    Siswa terkunci
                    <span
                        :class="[
                            'ml-1 rounded-full px-2 py-0.5 text-xs font-bold',
                            lockedEntries.length ? 'bg-rose-100 text-rose-700' : 'bg-slate-100 text-slate-500',
                        ]"
                        >{{ lockedEntries.length }}</span
                    >
                </h3>
                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-50 px-4 py-2 text-xs font-bold text-indigo-700 transition hover:bg-indigo-100"
                        @click="$emit('refresh')"
                    >
                        <RefreshCw class="h-3.5 w-3.5" />
                        Segarkan
                    </button>
                    <button
                        v-if="lockedEntries.length > 1"
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-4 py-2 text-xs font-bold text-white shadow transition hover:bg-emerald-700 disabled:opacity-60"
                        :disabled="isUnlockingAll"
                        @click="$emit('unlockAll')"
                    >
                        <LockOpen class="h-3.5 w-3.5" />
                        Buka Semua ({{ lockedEntries.length }})
                    </button>
                </div>
            </div>

            <div v-if="lockedEntries.length" class="mt-4 divide-y divide-slate-100 overflow-hidden rounded-2xl border border-rose-200">
                <div
                    v-for="entry in lockedEntries"
                    :key="entry.id"
                    class="flex flex-wrap items-center justify-between gap-4 bg-rose-50/40 p-4 sm:p-5"
                >
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-base font-bold text-slate-800">{{ entry.name ?? entry.email ?? 'Responden Anonim' }}</span>
                            <span v-if="entry.kelas" class="rounded-lg bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">{{
                                entry.kelas
                            }}</span>
                            <span class="rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-extrabold uppercase tracking-wider text-rose-700"
                                >Terkunci</span
                            >
                            <span
                                v-if="entry.unlock_requested"
                                class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-extrabold uppercase tracking-wider text-amber-800"
                                >Minta dibuka</span
                            >
                            <span
                                v-if="(entry.blur_count ?? 0) > 0"
                                class="rounded-full bg-white px-2 py-0.5 text-[11px] font-semibold text-rose-700 ring-1 ring-inset ring-rose-600/20"
                            >
                                {{ entry.blur_count }}x pindah tab
                            </span>
                        </div>
                        <div class="mt-1 flex flex-wrap items-center gap-x-2 text-xs text-slate-500">
                            <span v-if="entry.locked_at ?? entry.created_at">Terkunci: {{ formatTime(entry.locked_at ?? entry.created_at) }}</span>
                            <span class="font-mono text-slate-400">ID: {{ entry.respondent_identifier }}</span>
                        </div>
                    </div>

                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-bold text-white shadow transition-all hover:bg-emerald-700 disabled:opacity-60"
                        :disabled="busyIds.includes(entry.id)"
                        @click="$emit('unlock', entry)"
                    >
                        <LockOpen class="h-3.5 w-3.5" />
                        {{ busyIds.includes(entry.id) ? 'Membuka...' : 'Buka Kunci' }}
                    </button>
                </div>
            </div>

            <div v-else class="mt-4 rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-10 text-center">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-indigo-50">
                    <Key class="h-6 w-6 text-indigo-500" />
                </div>
                <h3 class="mt-4 text-lg font-semibold text-slate-700">Tidak ada siswa yang terkunci</h3>
                <p class="mt-2 text-sm text-slate-500">Daftar ini diperbarui otomatis setiap beberapa detik.</p>
            </div>

            <template v-if="otherEntries.length">
                <h3 class="mt-8 text-base font-semibold text-slate-900">Riwayat pindah tab</h3>
                <div class="mt-4 divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200">
                    <div v-for="entry in otherEntries" :key="entry.id" class="flex flex-wrap items-center justify-between gap-3 p-4 sm:px-5">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-sm font-bold text-slate-800">{{ entry.name ?? entry.email ?? 'Responden Anonim' }}</span>
                                <span v-if="entry.kelas" class="rounded-lg bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">{{
                                    entry.kelas
                                }}</span>
                                <span
                                    :class="[
                                        'rounded-full px-2 py-0.5 text-[10px] font-extrabold uppercase tracking-wider',
                                        entry.state === 'submitted' ? 'bg-slate-100 text-slate-600' : 'bg-emerald-100 text-emerald-800',
                                    ]"
                                    >{{ entry.state === 'submitted' ? 'Sudah mengirim' : 'Sudah dibuka' }}</span
                                >
                                <span v-if="(entry.blur_count ?? 0) > 0" class="text-[11px] font-semibold text-rose-700"
                                    >{{ entry.blur_count }}x pindah tab</span
                                >
                            </div>
                            <div class="mt-1 flex flex-wrap gap-x-2 text-xs text-slate-500">
                                <span v-if="entry.last_blur_at">Pindah tab terakhir: {{ formatTime(entry.last_blur_at) }}</span>
                                <span v-if="entry.state === 'unlocked' && entry.unlocked_at">• Dibuka: {{ formatTime(entry.unlocked_at) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </section>
</template>
