<script setup lang="ts">
import AppearancePreview from '@/components/appearance/AppearancePreview.vue';
import { PREVIEW_COLORS, type PreviewColors } from '@/components/appearance/previewColors';
import { SCHEDULE_DARK_FROM_HOUR, SCHEDULE_DARK_UNTIL_HOUR, useAppearance, type Appearance, type ColorTheme } from '@/composables/useAppearance';
import { alsenCard, alsenCardDescription, alsenCardTitle } from '@/constants/alsenform-ui';
import { Check, CircleAlert, Clock, Info, Monitor, Moon, Palette, Sun } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, type Component } from 'vue';

/** Profil → Tampilan: light or dark (or following the device or the clock), and the colours. */
const { appearance, colorTheme, setAppearance, setColorTheme } = useAppearance();

const hours = (hour: number) => `${String(hour).padStart(2, '0')}.00`;

const appearanceOptions: { value: Appearance; label: string; description: string; icon: Component; panes: ('light' | 'dark')[] }[] = [
    { value: 'light', label: 'Terang', description: 'Latar cerah, pas untuk ruang kelas yang terang.', icon: Sun, panes: ['light'] },
    { value: 'dark', label: 'Gelap', description: 'Latar gelap yang lembut untuk ruang redup dan malam hari.', icon: Moon, panes: ['dark'] },
    { value: 'system', label: 'Ikuti sistem', description: 'Mengikuti pengaturan terang atau gelap perangkat ini.', icon: Monitor, panes: ['light', 'dark'] },
    {
        value: 'schedule',
        label: 'Ikuti jam',
        description: `Gelap otomatis pukul ${hours(SCHEDULE_DARK_FROM_HOUR)}–${hours(SCHEDULE_DARK_UNTIL_HOUR)} menurut jam perangkat.`,
        icon: Clock,
        panes: ['light', 'dark'],
    },
];

const colorThemeOptions: { value: ColorTheme; label: string; description: string }[] = [
    { value: 'standard', label: 'Standar', description: 'Ungu-biru khas Falcon Forms, bersih dan tegas.' },
    { value: 'warm', label: 'Hangat', description: 'Krem lembut dan aksen terakota, nyaman untuk membaca lama.' },
];

/** The mode drawings use the colours chosen below, so the two choices read together. */
const modePanes = (panes: ('light' | 'dark')[]): PreviewColors[] => panes.map((pane) => PREVIEW_COLORS[colorTheme.value][pane]);
const themePanes = (value: ColorTheme): PreviewColors[] => [PREVIEW_COLORS[value].light, PREVIEW_COLORS[value].dark];

const saveState = ref<'idle' | 'saved' | 'failed'>('idle');
let saveStateTimer: ReturnType<typeof setTimeout> | null = null;

const remember = (saving: Promise<void>) => {
    saving
        .then(() => (saveState.value = 'saved'))
        .catch(() => (saveState.value = 'failed'))
        .finally(() => {
            if (saveStateTimer) {
                clearTimeout(saveStateTimer);
            }
            saveStateTimer = setTimeout(() => (saveState.value = 'idle'), saveState.value === 'failed' ? 6000 : 2500);
        });
};

const chooseAppearance = (value: Appearance) => remember(setAppearance(value));
const chooseColorTheme = (value: ColorTheme) => remember(setColorTheme(value));

const optionClass = (isChosen: boolean) => [
    'relative flex flex-col items-start gap-2 rounded-2xl border p-3 text-left transition focus:outline-none focus-visible:ring-4 focus-visible:ring-indigo-200',
    isChosen ? 'border-indigo-500 bg-indigo-50/60 ring-4 ring-indigo-100' : 'border-slate-200 bg-white hover:border-indigo-300 hover:bg-slate-50',
];

const statusText = computed(() => (saveState.value === 'saved' ? 'Tersimpan' : saveState.value === 'failed' ? 'Belum tersimpan di akun' : ''));

onBeforeUnmount(() => {
    if (saveStateTimer) {
        clearTimeout(saveStateTimer);
    }
});
</script>

<template>
    <div id="tampilan" :class="[alsenCard, 'scroll-mt-24']">
        <div class="flex items-start gap-3">
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600">
                <Palette class="h-5 w-5" />
            </div>
            <div class="min-w-0 flex-1">
                <h2 :class="alsenCardTitle">Tampilan</h2>
                <p :class="alsenCardDescription">Atur terang-gelap dan nuansa warna yang nyaman di mata. Pilihan tersimpan di akun, jadi ikut ke perangkat lain.</p>
            </div>
            <span
                v-if="statusText"
                role="status"
                :class="[
                    'inline-flex shrink-0 items-center gap-1 rounded-full px-2.5 py-1 text-xs font-bold',
                    saveState === 'saved' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700',
                ]"
            >
                <Check v-if="saveState === 'saved'" class="h-3.5 w-3.5" />
                <CircleAlert v-else class="h-3.5 w-3.5" />
                {{ statusText }}
            </span>
        </div>

        <div class="mt-6">
            <p id="appearance-mode-label" class="text-sm font-semibold text-slate-700">Mode</p>
            <div role="radiogroup" aria-labelledby="appearance-mode-label" class="mt-3 grid grid-cols-2 gap-3 lg:grid-cols-4">
                <button
                    v-for="option in appearanceOptions"
                    :key="option.value"
                    type="button"
                    role="radio"
                    :aria-checked="appearance === option.value"
                    :data-appearance-option="option.value"
                    :class="optionClass(appearance === option.value)"
                    @click="chooseAppearance(option.value)"
                >
                    <AppearancePreview :panes="modePanes(option.panes)" />
                    <span class="flex items-center gap-1.5 text-sm font-bold text-slate-900">
                        <component :is="option.icon" :class="['h-4 w-4', appearance === option.value ? 'text-indigo-600' : 'text-slate-400']" />
                        {{ option.label }}
                    </span>
                    <span class="text-xs leading-relaxed text-slate-500">{{ option.description }}</span>
                    <span
                        v-if="appearance === option.value"
                        class="absolute right-2 top-2 flex h-5 w-5 items-center justify-center rounded-full bg-indigo-600 text-white shadow-sm"
                    >
                        <Check class="h-3 w-3" />
                    </span>
                </button>
            </div>
        </div>

        <div class="mt-6">
            <p id="appearance-theme-label" class="text-sm font-semibold text-slate-700">Nuansa warna</p>
            <div role="radiogroup" aria-labelledby="appearance-theme-label" class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                <button
                    v-for="option in colorThemeOptions"
                    :key="option.value"
                    type="button"
                    role="radio"
                    :aria-checked="colorTheme === option.value"
                    :data-color-theme-option="option.value"
                    :class="[...optionClass(colorTheme === option.value), 'sm:flex-row sm:items-center sm:gap-4']"
                    @click="chooseColorTheme(option.value)"
                >
                    <span class="w-full sm:w-40 sm:shrink-0">
                        <AppearancePreview :panes="themePanes(option.value)" />
                    </span>
                    <span class="flex min-w-0 flex-col gap-1 pr-6">
                        <span class="text-sm font-bold text-slate-900">{{ option.label }}</span>
                        <span class="text-xs leading-relaxed text-slate-500">{{ option.description }}</span>
                    </span>
                    <span
                        v-if="colorTheme === option.value"
                        class="absolute right-2 top-2 flex h-5 w-5 items-center justify-center rounded-full bg-indigo-600 text-white shadow-sm"
                    >
                        <Check class="h-3 w-3" />
                    </span>
                </button>
            </div>
        </div>

        <p class="mt-6 flex items-start gap-2 rounded-2xl bg-slate-50 px-4 py-3 text-xs leading-relaxed text-slate-500">
            <Info class="mt-0.5 h-4 w-4 shrink-0 text-slate-400" />
            <span>Halaman ujian siswa selalu terang dan mengikuti desain formulir dari guru. Halaman yang dicetak juga selalu terang.</span>
        </p>
    </div>
</template>
