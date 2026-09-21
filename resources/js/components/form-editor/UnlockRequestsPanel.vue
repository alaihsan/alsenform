<script setup lang="ts">
import { Key } from 'lucide-vue-next';

defineProps<{
    unlockRequests: {
        id: number;
        respondent_identifier: string;
        email: string | null;
        status: 'pending' | 'approved' | string;
        created_at: string;
        blur_count?: number;
        last_blur_at?: string | null;
    }[];
}>();

defineEmits<{
    refresh: [];
    approve: [requestId: number];
}>();
</script>

<template>
    <section class="space-y-5">
        <div class="rounded-xl border border-slate-300 bg-white p-7 shadow-sm">
            <div class="flex items-center justify-between gap-4 border-b border-slate-100 pb-5">
                <div>
                    <h2 class="text-2xl font-normal text-slate-950">Keamanan & Buka Kunci Kuis</h2>
                    <p class="mt-2 text-base text-slate-500">Lihat dan setujui permintaan pengerjaan ulang / buka kunci kuis akibat pemindahan tab oleh responden.</p>
                </div>
                <button
                    type="button"
                    class="rounded-xl bg-indigo-50 px-4 py-2 text-xs font-bold text-indigo-700 transition hover:bg-indigo-100"
                    @click="$emit('refresh')"
                >
                    Segarkan List
                </button>
            </div>

            <div v-if="unlockRequests.length" class="mt-6 divide-y divide-slate-150 overflow-hidden rounded-2xl border border-slate-200">
                <div v-for="req in unlockRequests" :key="req.id" class="flex items-center justify-between gap-4 p-5 transition-colors hover:bg-slate-50">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-base font-bold text-slate-800">{{ req.email ?? 'Responden Anonim' }}</span>
                            <span
                                :class="[
                                    'rounded-full px-2 py-0.5 text-[10px] font-extrabold uppercase tracking-wider',
                                    req.status === 'approved' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800',
                                ]"
                            >
                                {{ req.status === 'approved' ? 'Terbuka' : 'Terkunci' }}
                            </span>
                            <span
                                v-if="(req.blur_count ?? 0) > 0"
                                class="inline-flex items-center gap-1 rounded-full bg-rose-50 px-2 py-0.5 text-[11px] font-semibold text-rose-700 ring-1 ring-inset ring-rose-600/20"
                            >
                                ⚠️ {{ req.blur_count }}x Pindah Tab
                            </span>
                        </div>
                        <span class="mt-1 block font-mono text-xs text-slate-500">ID: {{ req.respondent_identifier }}</span>
                        <div class="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-slate-400">
                            <span>Meminta pada: {{ new Date(req.created_at).toLocaleString('id-ID') }}</span>
                            <span v-if="req.last_blur_at" class="text-rose-600 font-medium">
                                • Pindah tab terakhir: {{ new Date(req.last_blur_at).toLocaleTimeString('id-ID') }}
                            </span>
                        </div>
                    </div>

                    <button
                        v-if="req.status === 'pending'"
                        type="button"
                        class="rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-bold text-white shadow transition-all hover:bg-emerald-700"
                        @click="$emit('approve', req.id)"
                    >
                        Setujui
                    </button>
                </div>
            </div>

            <div v-else class="mt-6 rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-indigo-50">
                    <Key class="h-6 w-6 text-indigo-500" />
                </div>
                <h3 class="mt-4 text-lg font-semibold text-slate-700">Belum ada request masuk</h3>
                <p class="mt-2 text-sm text-slate-500">Jika ada responden yang pindah tab saat mengerjakan kuis, permintaan persetujuan akan muncul di sini.</p>
            </div>
        </div>
    </section>
</template>
