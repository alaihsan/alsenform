<script setup lang="ts">
import axios from 'axios';
import { Brain, Building2, Check, ChevronDown, Eye, FlaskConical, GraduationCap, KeyRound, Plus, Puzzle, Sparkles, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';

export type AddonEntry = {
    key: string;
    name: string;
    tagline: string;
    description: string;
    icon: string;
    adds: string[];
    reads: string[];
    onByDefault: boolean;
    installed: boolean;
    installedForEveryone: boolean;
    canUninstall: boolean;
    /** Only in the editor: whether it is on for the exam, and installed for the exam's owner. */
    activeOnExam?: boolean;
    installedForOwner?: boolean;
};

const props = defineProps<{
    addons: AddonEntry[];
    canInstallForEveryone: boolean;
    /** With "__addon__" in place of the add-on key. */
    installUrl: string;
    uninstallUrl: string;
}>();

const emit = defineEmits<{
    'update:addons': [addons: AddonEntry[]];
}>();

const ICONS: Record<string, unknown> = { 'flask-conical': FlaskConical, 'key-round': KeyRound, 'graduation-cap': GraduationCap, brain: Brain };

const openKey = ref<string | null>(props.addons.length === 1 ? props.addons[0].key : null);
const busyKey = ref<string | null>(null);
const message = ref('');
const error = ref('');

const urlOf = (template: string, key: string) => template.replace('__addon__', encodeURIComponent(key));

/** Install or remove an add-on, for this teacher or (admins) for every teacher. */
const change = async (addon: AddonEntry, install: boolean, everyone = false) => {
    if (busyKey.value) {
        return;
    }
    busyKey.value = addon.key;
    message.value = '';
    error.value = '';
    try {
        const response = install
            ? await axios.post(urlOf(props.installUrl, addon.key), { everyone })
            : await axios.delete(urlOf(props.uninstallUrl, addon.key), { params: { everyone: everyone ? 1 : 0 } });
        message.value = response.data.message;
        emit('update:addons', response.data.addons);
    } catch (exception: unknown) {
        const response = (exception as { response?: { data?: { message?: string } } }).response;
        error.value = response?.data?.message ?? 'Add-on belum dapat diubah. Coba lagi.';
    } finally {
        busyKey.value = null;
    }
};
</script>

<template>
    <div class="space-y-4">
        <p v-if="message" class="rounded-xl bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-800" role="status">{{ message }}</p>
        <p v-if="error" class="rounded-xl bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-800" role="alert">{{ error }}</p>

        <article v-for="addon in addons" :key="addon.key" class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
            <div class="flex items-start gap-3.5 p-4 sm:p-5">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600">
                    <component :is="ICONS[addon.icon] ?? Puzzle" class="h-6 w-6" />
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="text-base font-extrabold text-slate-900">{{ addon.name }}</h3>
                        <span
                            v-if="addon.installedForEveryone"
                            class="inline-flex items-center gap-1 rounded-full bg-violet-50 px-2 py-0.5 text-[10px] font-extrabold uppercase text-violet-700"
                            ><Building2 class="h-3 w-3" /> Dipasang admin untuk semua guru</span
                        >
                        <span
                            v-else-if="addon.installed"
                            class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-extrabold uppercase text-emerald-700"
                            ><Check class="h-3 w-3" /> Terpasang</span
                        >
                    </div>
                    <p class="mt-1 text-sm leading-relaxed text-slate-600">{{ addon.tagline }}</p>

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <button
                            v-if="!addon.installed"
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-3.5 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-indigo-700 disabled:opacity-60"
                            :disabled="busyKey !== null"
                            @click="change(addon, true)"
                        >
                            <Plus class="h-3.5 w-3.5" /> {{ busyKey === addon.key ? 'Memasang...' : 'Pasang' }}
                        </button>
                        <button
                            v-else-if="addon.canUninstall && !addon.installedForEveryone"
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-bold text-slate-600 transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-700 disabled:opacity-60"
                            :disabled="busyKey !== null"
                            @click="change(addon, false)"
                        >
                            <Trash2 class="h-3.5 w-3.5" /> {{ busyKey === addon.key ? 'Melepas...' : 'Lepas' }}
                        </button>

                        <template v-if="canInstallForEveryone">
                            <button
                                v-if="!addon.installedForEveryone"
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-xl border border-violet-200 bg-violet-50 px-3.5 py-2 text-xs font-bold text-violet-700 transition hover:bg-violet-100 disabled:opacity-60"
                                :disabled="busyKey !== null"
                                @click="change(addon, true, true)"
                            >
                                <Building2 class="h-3.5 w-3.5" /> Pasang untuk semua guru
                            </button>
                            <button
                                v-else
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-bold text-slate-600 transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-700 disabled:opacity-60"
                                :disabled="busyKey !== null"
                                @click="change(addon, false, true)"
                            >
                                <Trash2 class="h-3.5 w-3.5" /> Lepas dari semua guru
                            </button>
                        </template>

                        <button
                            type="button"
                            class="ml-auto inline-flex items-center gap-1 text-xs font-bold text-slate-500 hover:text-indigo-700"
                            :aria-expanded="openKey === addon.key"
                            @click="openKey = openKey === addon.key ? null : addon.key"
                        >
                            Detail <ChevronDown :class="['h-3.5 w-3.5 transition', openKey === addon.key ? 'rotate-180' : '']" />
                        </button>
                    </div>
                </div>
            </div>

            <div v-if="openKey === addon.key" class="space-y-3 border-t border-slate-100 bg-slate-50/60 px-4 py-4 text-sm sm:px-5">
                <p class="leading-relaxed text-slate-600">{{ addon.description }}</p>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <p class="flex items-center gap-1.5 text-[11px] font-extrabold uppercase tracking-wider text-slate-500">
                            <Sparkles class="h-3.5 w-3.5" /> Yang ditambahkan
                        </p>
                        <ul class="mt-1.5 list-disc space-y-1 pl-4 text-xs leading-relaxed text-slate-600">
                            <li v-for="item in addon.adds" :key="item">{{ item }}</li>
                        </ul>
                    </div>
                    <div>
                        <p class="flex items-center gap-1.5 text-[11px] font-extrabold uppercase tracking-wider text-slate-500">
                            <Eye class="h-3.5 w-3.5" /> Yang dibaca
                        </p>
                        <ul class="mt-1.5 list-disc space-y-1 pl-4 text-xs leading-relaxed text-slate-600">
                            <li v-for="item in addon.reads" :key="item">{{ item }}</li>
                        </ul>
                    </div>
                </div>
                <p class="text-xs text-slate-500">
                    Setelah dipasang, add-on {{ addon.onByDefault ? 'langsung aktif di semua ujian Anda' : 'perlu diaktifkan per ujian' }}. Matikan
                    untuk ujian tertentu lewat tombol Add-ons di editor. Melepas add-on mematikannya di semua ujian Anda tanpa menghapus data.
                </p>
            </div>
        </article>

        <p class="rounded-2xl border border-dashed border-slate-200 px-4 py-3 text-center text-xs text-slate-500">Add-on lain sedang disiapkan.</p>
    </div>
</template>
