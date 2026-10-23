<script setup lang="ts">
import axios from 'axios';
import {
    AlertTriangle,
    Atom,
    Brain,
    Building2,
    Check,
    Eye,
    FlaskConical,
    GraduationCap,
    Info,
    KeyRound,
    Languages,
    Paintbrush,
    Plus,
    Puzzle,
    RefreshCw,
    Sheet,
    Sigma,
    Sparkles,
    Trash2,
    X,
} from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';

export type AddonEntry = {
    key: string;
    name: string;
    tagline: string;
    description: string;
    icon: string;
    adds: string[];
    reads: string[];
    onByDefault: boolean;
    /** Switched on or off per exam; one that is not (Rekap Kuis, the formula palettes, the RTL button) works while installed. */
    perExam: boolean;
    /** Where an add-on that is not per exam shows once installed, e.g. "menu Aplikasi di Beranda". */
    shownIn: string | null;
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

const ICONS: Record<string, unknown> = {
    'flask-conical': FlaskConical,
    'key-round': KeyRound,
    'graduation-cap': GraduationCap,
    brain: Brain,
    paintbrush: Paintbrush,
    sheet: Sheet,
    sigma: Sigma,
    atom: Atom,
    languages: Languages,
};

/** A colour per add-on, so the cards are told apart at a glance. */
const TONES: Record<string, string> = {
    'item-analysis': 'bg-indigo-50 text-indigo-600 ring-indigo-100',
    'exam-token': 'bg-amber-50 text-amber-600 ring-amber-100',
    remedial: 'bg-emerald-50 text-emerald-600 ring-emerald-100',
    misconception: 'bg-rose-50 text-rose-600 ring-rose-100',
    'form-designer': 'bg-fuchsia-50 text-fuchsia-600 ring-fuchsia-100',
    'quiz-recap': 'bg-violet-50 text-violet-600 ring-violet-100',
    'math-formulas': 'bg-sky-50 text-sky-600 ring-sky-100',
    'science-formulas': 'bg-teal-50 text-teal-600 ring-teal-100',
    'arabic-rtl': 'bg-emerald-50 text-emerald-600 ring-emerald-100',
};
const toneOf = (addon: AddonEntry) => TONES[addon.key] ?? 'bg-slate-100 text-slate-600 ring-slate-200';

type Filter = 'all' | 'installed' | 'available';
const filter = ref<Filter>('all');
const installedCount = computed(() => props.addons.filter((addon) => addon.installed).length);
const filters = computed<{ value: Filter; label: string; count: number }[]>(() => [
    { value: 'all', label: 'Semua', count: props.addons.length },
    { value: 'installed', label: 'Terpasang', count: installedCount.value },
    { value: 'available', label: 'Belum dipasang', count: props.addons.length - installedCount.value },
]);
const shown = computed(() =>
    props.addons.filter((addon) => (filter.value === 'installed' ? addon.installed : filter.value === 'available' ? !addon.installed : true)),
);

const message = ref('');
const error = ref('');

/** What the teacher may do with an add-on: on its card and in its details. */
type Action = { install: boolean; everyone: boolean };
const actionsOf = (addon: AddonEntry): Action[] => {
    const actions: Action[] = [];
    if (!addon.installed) {
        actions.push({ install: true, everyone: false });
    } else if (addon.canUninstall && !addon.installedForEveryone) {
        actions.push({ install: false, everyone: false });
    }
    if (props.canInstallForEveryone) {
        actions.push({ install: !addon.installedForEveryone, everyone: true });
    }

    return actions;
};
const actionLabel = (action: Action) =>
    action.install ? (action.everyone ? 'Pasang untuk semua guru' : 'Pasang') : action.everyone ? 'Lepas dari semua guru' : 'Lepas';

/* Details of one add-on. */
const detailKey = ref<string | null>(null);
const detail = computed(() => props.addons.find((addon) => addon.key === detailKey.value) ?? null);

/* Every install and removal is confirmed first. */
const pending = ref<{ addon: AddonEntry; action: Action } | null>(null);
const isBusy = ref(false);
const confirmButton = ref<HTMLButtonElement | null>(null);

const ask = (addon: AddonEntry, action: Action) => {
    message.value = '';
    error.value = '';
    pending.value = { addon, action };
    nextTick(() => confirmButton.value?.focus());
};

const cancel = () => {
    if (!isBusy.value) {
        pending.value = null;
    }
};

const urlOf = (template: string, key: string) => template.replace('__addon__', encodeURIComponent(key));

/** Install or remove the add-on asked about, for this teacher or (admins) for every teacher. */
const confirm = async () => {
    if (!pending.value || isBusy.value) {
        return;
    }
    const { addon, action } = pending.value;
    isBusy.value = true;
    try {
        const response = action.install
            ? await axios.post(urlOf(props.installUrl, addon.key), { everyone: action.everyone })
            : await axios.delete(urlOf(props.uninstallUrl, addon.key), { params: { everyone: action.everyone ? 1 : 0 } });
        message.value = response.data.message;
        emit('update:addons', response.data.addons);
        pending.value = null;
    } catch (exception: unknown) {
        const response = (exception as { response?: { data?: { message?: string } } }).response;
        error.value = response?.data?.message ?? 'Add-on belum dapat diubah. Coba lagi.';
        pending.value = null;
    } finally {
        isBusy.value = false;
    }
};

const onKeydown = (event: KeyboardEvent) => {
    if (event.key !== 'Escape') {
        return;
    }
    if (pending.value) {
        event.stopPropagation();
        cancel();
    } else if (detailKey.value) {
        event.stopPropagation();
        detailKey.value = null;
    }
};

watch(
    () => Boolean(pending.value || detailKey.value),
    (open) => (open ? document.addEventListener('keydown', onKeydown, true) : document.removeEventListener('keydown', onKeydown, true)),
);
onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown, true));
</script>

<template>
    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="inline-flex rounded-xl bg-slate-100 p-1" role="tablist" aria-label="Saring add-on">
                <button
                    v-for="option in filters"
                    :key="option.value"
                    type="button"
                    role="tab"
                    :aria-selected="filter === option.value"
                    :class="[
                        'rounded-lg px-3 py-1.5 text-xs font-bold transition',
                        filter === option.value ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-800',
                    ]"
                    @click="filter = option.value"
                >
                    {{ option.label }} <span class="ml-0.5 tabular-nums text-slate-400">{{ option.count }}</span>
                </button>
            </div>
            <p class="text-xs font-semibold text-slate-500">{{ installedCount }} dari {{ addons.length }} add-on terpasang</p>
        </div>

        <p v-if="message" class="flex items-center gap-2 rounded-xl bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-800" role="status">
            <Check class="h-4 w-4 shrink-0" /> {{ message }}
        </p>
        <p v-if="error" class="flex items-center gap-2 rounded-xl bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-800" role="alert">
            <AlertTriangle class="h-4 w-4 shrink-0" /> {{ error }}
        </p>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" data-testid="addon-grid">
            <article
                v-for="addon in shown"
                :key="addon.key"
                :class="[
                    'flex flex-col rounded-2xl border bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md',
                    addon.installed ? 'border-emerald-200' : 'border-slate-200',
                ]"
                :data-addon="addon.key"
            >
                <div class="flex items-start justify-between gap-3">
                    <div :class="['flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl ring-4', toneOf(addon)]">
                        <component :is="ICONS[addon.icon] ?? Puzzle" class="h-6 w-6" />
                    </div>
                    <span
                        v-if="addon.installedForEveryone"
                        class="inline-flex items-center gap-1 rounded-full bg-violet-50 px-2 py-1 text-[10px] font-extrabold uppercase tracking-wide text-violet-700"
                    >
                        <Building2 class="h-3 w-3" /> Semua guru
                    </span>
                    <span
                        v-else-if="addon.installed"
                        class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-1 text-[10px] font-extrabold uppercase tracking-wide text-emerald-700"
                    >
                        <Check class="h-3 w-3" /> Terpasang
                    </span>
                </div>

                <h3 class="mt-4 text-base font-extrabold text-slate-900">{{ addon.name }}</h3>
                <p class="mt-1.5 line-clamp-4 flex-1 text-sm leading-relaxed text-slate-600">{{ addon.tagline }}</p>
                <p class="mt-3 text-[11px] font-semibold text-slate-400">
                    {{
                        !addon.perExam
                            ? `Muncul di ${addon.shownIn} setelah dipasang`
                            : addon.onByDefault
                              ? 'Langsung aktif di ujian setelah dipasang'
                              : 'Dinyalakan per ujian setelah dipasang'
                    }}
                </p>

                <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-4">
                    <button
                        v-for="action in actionsOf(addon)"
                        :key="`${action.install}-${action.everyone}`"
                        type="button"
                        :class="[
                            'inline-flex items-center gap-1.5 rounded-xl px-3.5 py-2 text-xs font-bold transition disabled:opacity-60',
                            action.install && !action.everyone
                                ? 'bg-indigo-600 text-white shadow-sm hover:bg-indigo-700'
                                : action.install
                                  ? 'border border-violet-200 bg-violet-50 text-violet-700 hover:bg-violet-100'
                                  : 'border border-slate-200 bg-white text-slate-600 hover:border-rose-200 hover:bg-rose-50 hover:text-rose-700',
                        ]"
                        :disabled="isBusy"
                        @click="ask(addon, action)"
                    >
                        <component :is="action.install ? (action.everyone ? Building2 : Plus) : Trash2" class="h-3.5 w-3.5" />
                        {{ actionLabel(action) }}
                    </button>
                    <span v-if="!actionsOf(addon).length" class="text-[11px] font-semibold text-slate-400">Hanya admin yang dapat melepasnya</span>
                    <button
                        type="button"
                        class="ml-auto inline-flex items-center gap-1 rounded-lg px-2 py-1.5 text-xs font-bold text-slate-500 hover:bg-slate-50 hover:text-indigo-700"
                        :aria-label="`Detail ${addon.name}`"
                        @click="detailKey = addon.key"
                    >
                        <Info class="h-3.5 w-3.5" /> Detail
                    </button>
                </div>
            </article>

            <div
                v-if="filter !== 'installed'"
                class="flex min-h-[180px] flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 p-5 text-center"
            >
                <Sparkles class="h-6 w-6 text-slate-300" />
                <p class="mt-2 text-xs font-semibold text-slate-400">Add-on lain sedang disiapkan.</p>
            </div>
            <p
                v-if="!shown.length && filter === 'installed'"
                class="col-span-full rounded-2xl border border-dashed border-slate-200 px-4 py-8 text-center text-sm text-slate-500"
            >
                Belum ada add-on terpasang.
            </p>
        </div>

        <Teleport to="body">
            <!-- Details of an add-on -->
            <div
                v-if="detail"
                class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/50 p-4 backdrop-blur-sm"
                @click.self="detailKey = null"
            >
                <section
                    class="flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-3xl bg-white shadow-2xl duration-150 animate-in fade-in zoom-in-95"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="addon-detail-title"
                >
                    <div class="flex items-start gap-4 border-b border-slate-100 p-5 sm:p-6">
                        <div :class="['flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl ring-4', toneOf(detail)]">
                            <component :is="ICONS[detail.icon] ?? Puzzle" class="h-6 w-6" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <h2 id="addon-detail-title" class="text-lg font-extrabold text-slate-900">{{ detail.name }}</h2>
                            <p class="mt-0.5 text-sm text-slate-600">{{ detail.tagline }}</p>
                        </div>
                        <button
                            type="button"
                            class="rounded-full p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                            aria-label="Tutup detail"
                            @click="detailKey = null"
                        >
                            <X class="h-5 w-5" />
                        </button>
                    </div>
                    <div class="space-y-5 overflow-y-auto p-5 text-sm sm:p-6">
                        <p class="leading-relaxed text-slate-600">{{ detail.description }}</p>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="rounded-2xl bg-slate-50 p-4">
                                <p class="flex items-center gap-1.5 text-[11px] font-extrabold uppercase tracking-wider text-slate-500">
                                    <Sparkles class="h-3.5 w-3.5" /> Yang ditambahkan
                                </p>
                                <ul class="mt-2 list-disc space-y-1.5 pl-4 text-xs leading-relaxed text-slate-600">
                                    <li v-for="item in detail.adds" :key="item">{{ item }}</li>
                                </ul>
                            </div>
                            <div class="rounded-2xl bg-slate-50 p-4">
                                <p class="flex items-center gap-1.5 text-[11px] font-extrabold uppercase tracking-wider text-slate-500">
                                    <Eye class="h-3.5 w-3.5" /> Yang dibaca
                                </p>
                                <ul class="mt-2 list-disc space-y-1.5 pl-4 text-xs leading-relaxed text-slate-600">
                                    <li v-for="item in detail.reads" :key="item">{{ item }}</li>
                                </ul>
                            </div>
                        </div>
                        <p v-if="!detail.perExam" class="text-xs leading-relaxed text-slate-500">
                            Setelah dipasang, add-on ini muncul di {{ detail.shownIn }}. Melepasnya menyembunyikannya lagi tanpa menghapus data.
                        </p>
                        <p v-else class="text-xs leading-relaxed text-slate-500">
                            Setelah dipasang, add-on {{ detail.onByDefault ? 'langsung aktif di semua ujian Anda' : 'perlu diaktifkan per ujian' }}.
                            Matikan untuk ujian tertentu lewat tombol Add-on di editor. Melepas add-on mematikannya di semua ujian Anda tanpa
                            menghapus data.
                        </p>
                    </div>
                    <div class="flex flex-wrap justify-end gap-2 border-t border-slate-100 px-5 py-4 sm:px-6">
                        <button
                            v-for="action in actionsOf(detail)"
                            :key="`detail-${action.install}-${action.everyone}`"
                            type="button"
                            :class="[
                                'inline-flex items-center gap-1.5 rounded-xl px-4 py-2 text-xs font-bold transition disabled:opacity-60',
                                action.install && !action.everyone
                                    ? 'bg-indigo-600 text-white hover:bg-indigo-700'
                                    : action.install
                                      ? 'border border-violet-200 bg-violet-50 text-violet-700 hover:bg-violet-100'
                                      : 'border border-slate-200 text-slate-600 hover:border-rose-200 hover:bg-rose-50 hover:text-rose-700',
                            ]"
                            :disabled="isBusy"
                            @click="ask(detail, action)"
                        >
                            <component :is="action.install ? (action.everyone ? Building2 : Plus) : Trash2" class="h-3.5 w-3.5" />
                            {{ actionLabel(action) }}
                        </button>
                    </div>
                </section>
            </div>

            <!-- Confirming an install or a removal -->
            <div
                v-if="pending"
                class="fixed inset-0 z-[80] flex items-center justify-center bg-slate-950/50 p-4 backdrop-blur-sm"
                @click.self="cancel"
            >
                <section
                    class="w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl duration-150 animate-in fade-in zoom-in-95"
                    role="alertdialog"
                    aria-modal="true"
                    aria-labelledby="addon-confirm-title"
                    aria-describedby="addon-confirm-body"
                >
                    <div
                        :class="[
                            'flex h-12 w-12 items-center justify-center rounded-2xl',
                            pending.action.install ? 'bg-indigo-50 text-indigo-600' : 'bg-rose-50 text-rose-600',
                        ]"
                    >
                        <component :is="pending.action.install ? (ICONS[pending.addon.icon] ?? Puzzle) : AlertTriangle" class="h-6 w-6" />
                    </div>
                    <h2 id="addon-confirm-title" class="mt-4 text-lg font-extrabold text-slate-900">
                        {{
                            pending.action.install
                                ? `Pasang ${pending.addon.name}${pending.action.everyone ? ' untuk semua guru' : ''}?`
                                : `Lepas ${pending.addon.name}${pending.action.everyone ? ' dari semua guru' : ''}?`
                        }}
                    </h2>
                    <div id="addon-confirm-body" class="mt-2 space-y-3 text-sm leading-relaxed text-slate-600">
                        <template v-if="pending.action.install">
                            <p>
                                {{
                                    pending.action.everyone
                                        ? 'Semua guru di lembaga ini mendapat add-on ini, dan hanya admin yang dapat melepasnya.'
                                        : pending.addon.perExam
                                          ? 'Add-on ini dipasang untuk ujian-ujian milik Anda.'
                                          : 'Add-on ini dipasang untuk akun Anda.'
                                }}
                                {{
                                    !pending.addon.perExam
                                        ? `Add-on langsung muncul di ${pending.addon.shownIn}.`
                                        : pending.addon.onByDefault
                                          ? 'Add-on langsung aktif di ujian; matikan per ujian lewat tombol Add-on di editor.'
                                          : 'Add-on belum aktif sampai dinyalakan di ujian tertentu.'
                                }}
                            </p>
                            <div class="rounded-2xl bg-slate-50 p-3">
                                <p class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500">Yang dibaca</p>
                                <ul class="mt-1.5 list-disc space-y-1 pl-4 text-xs text-slate-600">
                                    <li v-for="item in pending.addon.reads" :key="item">{{ item }}</li>
                                </ul>
                            </div>
                        </template>
                        <template v-else>
                            <p>
                                {{
                                    !pending.addon.perExam
                                        ? `Add-on ini hilang dari ${pending.addon.shownIn}${pending.action.everyone ? ' semua guru yang tidak memasangnya sendiri' : ''}.`
                                        : pending.action.everyone
                                          ? 'Add-on ini mati di ujian semua guru yang tidak memasangnya sendiri.'
                                          : 'Add-on ini mati di semua ujian Anda: panel, lembar Excel dan tombolnya hilang.'
                                }}
                            </p>
                            <p class="rounded-2xl bg-amber-50 p-3 text-xs font-semibold text-amber-800">
                                {{
                                    pending.addon.perExam
                                        ? 'Data tidak dihapus. Bila dipasang lagi, pilihan tiap ujian kembali seperti semula.'
                                        : 'Tidak ada data yang dihapus, dan add-on dapat dipasang lagi kapan saja.'
                                }}
                            </p>
                        </template>
                    </div>
                    <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button
                            type="button"
                            class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-600 transition hover:bg-slate-50 disabled:opacity-60"
                            :disabled="isBusy"
                            @click="cancel"
                        >
                            Batal
                        </button>
                        <button
                            ref="confirmButton"
                            type="button"
                            :class="[
                                'inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-bold text-white shadow-sm transition disabled:cursor-wait disabled:opacity-70',
                                pending.action.install ? 'bg-indigo-600 hover:bg-indigo-700' : 'bg-rose-600 hover:bg-rose-700',
                            ]"
                            :disabled="isBusy"
                            @click="confirm"
                        >
                            <RefreshCw v-if="isBusy" class="h-4 w-4 animate-spin" />
                            {{
                                isBusy ? (pending.action.install ? 'Memasang...' : 'Melepas...') : pending.action.install ? 'Ya, pasang' : 'Ya, lepas'
                            }}
                        </button>
                    </div>
                </section>
            </div>
        </Teleport>
    </div>
</template>
