<script setup lang="ts">
import DesignPreview from '@/components/form-design/DesignPreview.vue';
import { fonts } from '@/constants/form-editor';
import {
    applyPreset,
    contrast,
    DEFAULT_DESIGN,
    DESIGN_HOOKS,
    DESIGN_PRESETS,
    designVariables,
    pageBackgroundStyle,
    type DesignPreset,
    type FormDesign,
} from '@/lib/formDesign';
import axios from 'axios';
import { Check, ExternalLink, Eye, ImagePlus, Paintbrush, RotateCcw, Trash2, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    modelValue: FormDesign;
    title: string;
    description?: string;
    questionFont?: string;
    answerFont?: string;
    publicUrl?: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [design: FormDesign];
    'update:questionFont': [font: string];
    'update:answerFont': [font: string];
    close: [];
}>();

type Tab = 'tema' | 'warna' | 'huruf' | 'gambar' | 'bentuk' | 'css';
const tabs: { key: Tab; label: string }[] = [
    { key: 'tema', label: 'Tema' },
    { key: 'warna', label: 'Warna' },
    { key: 'huruf', label: 'Huruf' },
    { key: 'gambar', label: 'Gambar' },
    { key: 'bentuk', label: 'Bentuk' },
    { key: 'css', label: 'CSS' },
];
const activeTab = ref<Tab>('tema');
const showPreviewOnPhone = ref(false);

const design = computed(() => props.modelValue);

/** A change by hand: the design no longer follows a ready-made theme. */
const update = (patch: Partial<FormDesign>, keepsPreset = false) => {
    emit('update:modelValue', { ...props.modelValue, ...patch, ...(keepsPreset ? {} : { preset: null }) });
};

const choosePreset = (preset: DesignPreset) => emit('update:modelValue', applyPreset(props.modelValue, preset));

const resetDesign = () => emit('update:modelValue', { ...DEFAULT_DESIGN });

const colorFields: { key: 'accent' | 'background' | 'card' | 'text'; label: string; hint: string }[] = [
    { key: 'accent', label: 'Warna aksen', hint: 'Garis judul, tombol, pilihan terpilih, nomor soal.' },
    { key: 'background', label: 'Latar halaman', hint: 'Di belakang kartu (bila tidak ada foto latar).' },
    { key: 'card', label: 'Warna kartu', hint: 'Kartu judul, bagian dan soal.' },
    { key: 'text', label: 'Warna teks', hint: 'Teks soal dan jawaban di dalam kartu.' },
];

const isHex = (value: string): boolean => /^#[0-9a-f]{6}$/i.test(value);

const setColor = (key: 'accent' | 'background' | 'card' | 'text', value: string) => {
    const color = value.startsWith('#') ? value : `#${value}`;
    if (isHex(color)) {
        update({ [key]: color.toLowerCase() });
    }
};

/** Text on the cards should stay readable (WCAG AA). */
const textContrast = computed(() => contrast(design.value.text, design.value.card));

type Choice<T extends string> = { value: T; label: string };
const titleSizes: Choice<FormDesign['titleSize']>[] = [
    { value: 'sm', label: 'Kecil' },
    { value: 'md', label: 'Sedang' },
    { value: 'lg', label: 'Besar' },
    { value: 'xl', label: 'Sangat besar' },
];
const textSizes: Choice<FormDesign['textSize']>[] = [
    { value: 'sm', label: 'Kecil' },
    { value: 'md', label: 'Sedang' },
    { value: 'lg', label: 'Besar' },
];
const alignments: Choice<FormDesign['titleAlign']>[] = [
    { value: 'left', label: 'Kiri' },
    { value: 'center', label: 'Tengah' },
];
const logoPositions: Choice<FormDesign['logoPosition']>[] = [
    { value: 'left', label: 'Kiri' },
    { value: 'center', label: 'Tengah' },
    { value: 'right', label: 'Kanan' },
];
const radii: Choice<FormDesign['radius']>[] = [
    { value: 'none', label: 'Tajam' },
    { value: 'sm', label: 'Sedikit' },
    { value: 'md', label: 'Sedang' },
    { value: 'lg', label: 'Bulat' },
    { value: 'xl', label: 'Sangat bulat' },
];
const shadows: Choice<FormDesign['shadow']>[] = [
    { value: 'none', label: 'Tanpa' },
    { value: 'soft', label: 'Lembut' },
    { value: 'strong', label: 'Tegas' },
];
const buttonShapes: Choice<FormDesign['buttonShape']>[] = [
    { value: 'square', label: 'Kotak' },
    { value: 'rounded', label: 'Membulat' },
    { value: 'pill', label: 'Kapsul' },
];
const widths: Choice<FormDesign['width']>[] = [
    { value: 'narrow', label: 'Sempit' },
    { value: 'normal', label: 'Normal' },
    { value: 'wide', label: 'Lebar' },
];
const backgroundFits: Choice<FormDesign['backgroundFit']>[] = [
    { value: 'cover', label: 'Penuh satu layar' },
    { value: 'tile', label: 'Diulang (pola)' },
];

type ImageKey = 'headerImage' | 'logo' | 'backgroundImage';
const imageFields: { key: ImageKey; label: string; hint: string }[] = [
    { key: 'headerImage', label: 'Gambar header', hint: 'Tampil di atas judul. Ukuran ideal 1600 × 400 piksel.' },
    { key: 'logo', label: 'Logo lembaga', hint: 'Tampil di atas judul ujian. PNG transparan paling bagus.' },
    { key: 'backgroundImage', label: 'Foto latar', hint: 'Menggantikan warna latar halaman.' },
];
const uploading = ref<ImageKey | null>(null);
const uploadError = ref('');

const uploadImage = async (key: ImageKey, event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = '';
    if (!file) {
        return;
    }
    if (!file.type.startsWith('image/')) {
        uploadError.value = 'Pilih berkas gambar (JPG, PNG, WebP atau GIF).';

        return;
    }

    const formData = new FormData();
    formData.append('file', file);
    uploading.value = key;
    uploadError.value = '';
    try {
        const response = await axios.post(route('forms.media.upload'), formData, { headers: { 'Content-Type': 'multipart/form-data' } });
        update({ [key]: response.data.url }, true);
    } catch (error: any) {
        uploadError.value = error.response?.data?.message || 'Gambar gagal diunggah. Coba lagi.';
    } finally {
        uploading.value = null;
    }
};

const cssLength = computed(() => design.value.customCss.length);
const insertHook = (selector: string) => {
    const css = design.value.customCss.trimEnd();
    update({ customCss: `${css}${css ? '\n\n' : ''}${selector} {\n    \n}` }, true);
};
</script>

<template>
    <div
        class="fixed inset-0 z-[70] flex bg-slate-900/40 backdrop-blur-sm"
        role="dialog"
        aria-modal="true"
        aria-label="Desainer Formulir"
        @keydown.esc="emit('close')"
    >
        <div class="ml-auto flex h-full w-full max-w-6xl flex-col bg-white shadow-2xl lg:flex-row">
            <!-- Controls -->
            <div
                class="flex min-h-0 w-full flex-col border-slate-200 lg:w-[400px] lg:shrink-0 lg:border-r"
                :class="showPreviewOnPhone ? 'hidden lg:flex' : 'flex'"
            >
                <div class="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600"
                        ><Paintbrush class="h-5 w-5"
                    /></span>
                    <div class="min-w-0 flex-1">
                        <h2 class="text-base font-black text-slate-900">Desainer Formulir</h2>
                        <p class="text-xs text-slate-500">Tampil di editor dan semua halaman siswa.</p>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                        aria-label="Tutup desainer"
                        @click="emit('close')"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <nav class="grid grid-cols-6 gap-1 border-b border-slate-100 px-3 py-2" aria-label="Bagian desainer">
                    <button
                        v-for="tab in tabs"
                        :key="tab.key"
                        type="button"
                        :class="[
                            'rounded-lg px-1 py-1.5 text-xs font-bold transition',
                            activeTab === tab.key ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-100',
                        ]"
                        :aria-pressed="activeTab === tab.key"
                        @click="activeTab = tab.key"
                    >
                        {{ tab.label }}
                    </button>
                </nav>

                <div class="min-h-0 flex-1 space-y-5 overflow-y-auto px-5 py-5">
                    <!-- Ready-made themes -->
                    <div v-if="activeTab === 'tema'" class="grid grid-cols-2 gap-3">
                        <button
                            v-for="preset in DESIGN_PRESETS"
                            :key="preset.key"
                            type="button"
                            :class="[
                                'group overflow-hidden rounded-2xl border text-left transition hover:shadow-md',
                                design.preset === preset.key ? 'border-indigo-500 ring-2 ring-indigo-500/30' : 'border-slate-200',
                            ]"
                            :aria-pressed="design.preset === preset.key"
                            :data-preset="preset.key"
                            @click="choosePreset(preset)"
                        >
                            <div
                                class="af-designed flex h-20 items-center justify-center p-3"
                                :style="{
                                    ...designVariables(applyPreset(DEFAULT_DESIGN, preset)),
                                    ...pageBackgroundStyle(applyPreset(DEFAULT_DESIGN, preset)),
                                }"
                            >
                                <div class="af-card w-full overflow-hidden border">
                                    <div class="af-accent-bg h-1.5"></div>
                                    <div class="flex items-center justify-between gap-2 px-2 py-1.5">
                                        <span class="af-title truncate text-[13px] font-bold" style="font-size: 13px; color: var(--af-text)"
                                            >Aa Ujian</span
                                        >
                                        <span class="af-button af-accent-bg h-3 w-8 shrink-0"></span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-start gap-1.5 px-3 py-2">
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs font-black text-slate-800">{{ preset.name }}</p>
                                    <p class="mt-0.5 line-clamp-2 text-[11px] leading-snug text-slate-500">{{ preset.description }}</p>
                                </div>
                                <Check v-if="design.preset === preset.key" class="h-4 w-4 shrink-0 text-indigo-600" />
                            </div>
                        </button>
                    </div>

                    <!-- Colours -->
                    <template v-else-if="activeTab === 'warna'">
                        <div v-for="field in colorFields" :key="field.key">
                            <label :for="`design-${field.key}`" class="block text-xs font-bold text-slate-700">{{ field.label }}</label>
                            <div class="mt-1.5 flex items-center gap-2">
                                <input
                                    :id="`design-${field.key}`"
                                    type="color"
                                    :value="design[field.key]"
                                    class="h-10 w-12 cursor-pointer rounded-lg border border-slate-300 bg-white p-1"
                                    @input="setColor(field.key, ($event.target as HTMLInputElement).value)"
                                />
                                <input
                                    type="text"
                                    :value="design[field.key]"
                                    maxlength="7"
                                    :aria-label="`${field.label} (kode hex)`"
                                    class="w-28 rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm uppercase text-slate-700 outline-none focus:border-indigo-500"
                                    @change="setColor(field.key, ($event.target as HTMLInputElement).value)"
                                />
                            </div>
                            <p class="mt-1 text-[11px] text-slate-500">{{ field.hint }}</p>
                        </div>
                        <p
                            v-if="textContrast < 4.5"
                            class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-800"
                            role="status"
                        >
                            Teks sulit dibaca di atas kartu (kontras {{ textContrast.toFixed(1) }}:1, sebaiknya minimal 4,5:1).
                        </p>
                        <div>
                            <label for="design-card-opacity" class="flex items-center justify-between text-xs font-bold text-slate-700">
                                <span>Kekeruhan kartu</span>
                                <span class="font-mono text-slate-500">{{ design.cardOpacity }}%</span>
                            </label>
                            <input
                                id="design-card-opacity"
                                type="range"
                                min="50"
                                max="100"
                                step="2"
                                :value="design.cardOpacity"
                                class="mt-2 w-full accent-indigo-600"
                                @input="update({ cardOpacity: Number(($event.target as HTMLInputElement).value) })"
                            />
                            <p class="mt-1 text-[11px] text-slate-500">Di bawah 100% foto latar tembus pandang lewat kartu.</p>
                        </div>
                    </template>

                    <!-- Fonts and sizes -->
                    <template v-else-if="activeTab === 'huruf'">
                        <div
                            v-for="field in [
                                { key: 'titleFont', label: 'Huruf judul', value: design.titleFont },
                                { key: 'bodyFont', label: 'Huruf halaman', value: design.bodyFont },
                            ]"
                            :key="field.key"
                        >
                            <label :for="`design-${field.key}`" class="block text-xs font-bold text-slate-700">{{ field.label }}</label>
                            <select
                                :id="`design-${field.key}`"
                                :value="field.value"
                                class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none focus:border-indigo-500"
                                @change="update({ [field.key]: ($event.target as HTMLSelectElement).value })"
                            >
                                <option v-for="font in fonts" :key="font.value" :value="font.value" :style="{ fontFamily: font.value }">
                                    {{ font.name }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label for="design-question-font" class="block text-xs font-bold text-slate-700">Huruf soal</label>
                            <select
                                id="design-question-font"
                                :value="questionFont"
                                class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none focus:border-indigo-500"
                                @change="emit('update:questionFont', ($event.target as HTMLSelectElement).value)"
                            >
                                <option v-for="font in fonts" :key="font.value" :value="font.value">{{ font.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label for="design-answer-font" class="block text-xs font-bold text-slate-700">Huruf jawaban</label>
                            <select
                                id="design-answer-font"
                                :value="answerFont"
                                class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 outline-none focus:border-indigo-500"
                                @change="emit('update:answerFont', ($event.target as HTMLSelectElement).value)"
                            >
                                <option v-for="font in fonts" :key="font.value" :value="font.value">{{ font.name }}</option>
                            </select>
                        </div>
                        <fieldset>
                            <legend class="text-xs font-bold text-slate-700">Ukuran judul</legend>
                            <div class="mt-1.5 grid grid-cols-4 gap-1.5">
                                <button
                                    v-for="choice in titleSizes"
                                    :key="choice.value"
                                    type="button"
                                    :class="[
                                        'rounded-lg border px-2 py-1.5 text-[11px] font-bold',
                                        design.titleSize === choice.value
                                            ? 'border-indigo-500 bg-indigo-50 text-indigo-700'
                                            : 'border-slate-200 text-slate-600 hover:bg-slate-50',
                                    ]"
                                    :aria-pressed="design.titleSize === choice.value"
                                    @click="update({ titleSize: choice.value })"
                                >
                                    {{ choice.label }}
                                </button>
                            </div>
                        </fieldset>
                        <fieldset>
                            <legend class="text-xs font-bold text-slate-700">Ukuran teks soal & jawaban</legend>
                            <div class="mt-1.5 grid grid-cols-3 gap-1.5">
                                <button
                                    v-for="choice in textSizes"
                                    :key="choice.value"
                                    type="button"
                                    :class="[
                                        'rounded-lg border px-2 py-1.5 text-[11px] font-bold',
                                        design.textSize === choice.value
                                            ? 'border-indigo-500 bg-indigo-50 text-indigo-700'
                                            : 'border-slate-200 text-slate-600 hover:bg-slate-50',
                                    ]"
                                    :aria-pressed="design.textSize === choice.value"
                                    @click="update({ textSize: choice.value })"
                                >
                                    {{ choice.label }}
                                </button>
                            </div>
                        </fieldset>
                        <fieldset>
                            <legend class="text-xs font-bold text-slate-700">Rata judul</legend>
                            <div class="mt-1.5 grid grid-cols-2 gap-1.5">
                                <button
                                    v-for="choice in alignments"
                                    :key="choice.value"
                                    type="button"
                                    :class="[
                                        'rounded-lg border px-2 py-1.5 text-[11px] font-bold',
                                        design.titleAlign === choice.value
                                            ? 'border-indigo-500 bg-indigo-50 text-indigo-700'
                                            : 'border-slate-200 text-slate-600 hover:bg-slate-50',
                                    ]"
                                    :aria-pressed="design.titleAlign === choice.value"
                                    @click="update({ titleAlign: choice.value })"
                                >
                                    {{ choice.label }}
                                </button>
                            </div>
                        </fieldset>
                    </template>

                    <!-- Images -->
                    <template v-else-if="activeTab === 'gambar'">
                        <div v-for="field in imageFields" :key="field.key" class="rounded-2xl border border-slate-200 p-3">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-xs font-bold text-slate-700">{{ field.label }}</p>
                                <button
                                    v-if="design[field.key]"
                                    type="button"
                                    class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-[11px] font-bold text-rose-600 hover:bg-rose-50"
                                    :aria-label="`Hapus ${field.label.toLowerCase()}`"
                                    @click="update({ [field.key]: null }, true)"
                                >
                                    <Trash2 class="h-3.5 w-3.5" /> Hapus
                                </button>
                            </div>
                            <img
                                v-if="design[field.key]"
                                :src="design[field.key] ?? ''"
                                alt=""
                                class="mt-2 max-h-28 w-full rounded-xl border border-slate-100 bg-slate-50 object-contain"
                            />
                            <label
                                :class="[
                                    'mt-2 flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-slate-300 px-3 py-2.5 text-xs font-bold text-slate-600 transition hover:border-indigo-400 hover:bg-indigo-50',
                                    uploading === field.key ? 'pointer-events-none opacity-60' : '',
                                ]"
                            >
                                <ImagePlus class="h-4 w-4" />
                                {{ uploading === field.key ? 'Mengunggah...' : design[field.key] ? 'Ganti gambar' : 'Unggah gambar' }}
                                <input
                                    type="file"
                                    accept="image/*"
                                    class="sr-only"
                                    :aria-label="`Unggah ${field.label.toLowerCase()}`"
                                    @change="uploadImage(field.key, $event)"
                                />
                            </label>
                            <p class="mt-1.5 text-[11px] text-slate-500">{{ field.hint }}</p>

                            <div v-if="field.key === 'logo' && design.logo" class="mt-2 grid grid-cols-3 gap-1.5">
                                <button
                                    v-for="choice in logoPositions"
                                    :key="choice.value"
                                    type="button"
                                    :class="[
                                        'rounded-lg border px-2 py-1.5 text-[11px] font-bold',
                                        design.logoPosition === choice.value
                                            ? 'border-indigo-500 bg-indigo-50 text-indigo-700'
                                            : 'border-slate-200 text-slate-600 hover:bg-slate-50',
                                    ]"
                                    :aria-pressed="design.logoPosition === choice.value"
                                    @click="update({ logoPosition: choice.value }, true)"
                                >
                                    {{ choice.label }}
                                </button>
                            </div>
                            <template v-if="field.key === 'backgroundImage' && design.backgroundImage">
                                <div class="mt-2 grid grid-cols-2 gap-1.5">
                                    <button
                                        v-for="choice in backgroundFits"
                                        :key="choice.value"
                                        type="button"
                                        :class="[
                                            'rounded-lg border px-2 py-1.5 text-[11px] font-bold',
                                            design.backgroundFit === choice.value
                                                ? 'border-indigo-500 bg-indigo-50 text-indigo-700'
                                                : 'border-slate-200 text-slate-600 hover:bg-slate-50',
                                        ]"
                                        :aria-pressed="design.backgroundFit === choice.value"
                                        @click="update({ backgroundFit: choice.value }, true)"
                                    >
                                        {{ choice.label }}
                                    </button>
                                </div>
                                <label for="design-dim" class="mt-3 flex items-center justify-between text-[11px] font-bold text-slate-700">
                                    <span>Peredup foto</span>
                                    <span class="font-mono text-slate-500">{{ design.backgroundDim }}%</span>
                                </label>
                                <input
                                    id="design-dim"
                                    type="range"
                                    min="0"
                                    max="80"
                                    step="5"
                                    :value="design.backgroundDim"
                                    class="mt-1 w-full accent-indigo-600"
                                    @input="update({ backgroundDim: Number(($event.target as HTMLInputElement).value) }, true)"
                                />
                            </template>
                        </div>
                        <p v-if="uploadError" class="text-xs font-semibold text-rose-600" role="alert">{{ uploadError }}</p>
                        <p class="text-[11px] leading-relaxed text-slate-500">
                            Gambar disimpan di server lembaga dan diperkecil otomatis agar ringan dibuka dari HP siswa.
                        </p>
                    </template>

                    <!-- Shapes and layout -->
                    <template v-else-if="activeTab === 'bentuk'">
                        <fieldset
                            v-for="group in [
                                { key: 'radius', label: 'Sudut kartu', choices: radii, columns: 'grid-cols-5' },
                                { key: 'shadow', label: 'Bayangan kartu', choices: shadows, columns: 'grid-cols-3' },
                                { key: 'buttonShape', label: 'Bentuk tombol', choices: buttonShapes, columns: 'grid-cols-3' },
                                { key: 'width', label: 'Lebar halaman siswa', choices: widths, columns: 'grid-cols-3' },
                            ]"
                            :key="group.key"
                        >
                            <legend class="text-xs font-bold text-slate-700">{{ group.label }}</legend>
                            <div :class="['mt-1.5 grid gap-1.5', group.columns]">
                                <button
                                    v-for="choice in group.choices"
                                    :key="choice.value"
                                    type="button"
                                    :class="[
                                        'rounded-lg border px-1.5 py-1.5 text-[11px] font-bold',
                                        design[group.key as keyof FormDesign] === choice.value
                                            ? 'border-indigo-500 bg-indigo-50 text-indigo-700'
                                            : 'border-slate-200 text-slate-600 hover:bg-slate-50',
                                    ]"
                                    :aria-pressed="design[group.key as keyof FormDesign] === choice.value"
                                    @click="update({ [group.key]: choice.value })"
                                >
                                    {{ choice.label }}
                                </button>
                            </div>
                        </fieldset>
                        <label class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 px-3 py-2.5">
                            <span class="text-xs font-bold text-slate-700">Garis tepi kartu</span>
                            <input
                                type="checkbox"
                                :checked="design.cardBorder"
                                class="h-4 w-4 accent-indigo-600"
                                @change="update({ cardBorder: ($event.target as HTMLInputElement).checked })"
                            />
                        </label>
                    </template>

                    <!-- Custom CSS -->
                    <template v-else-if="activeTab === 'css'">
                        <div>
                            <label for="design-css" class="flex items-center justify-between text-xs font-bold text-slate-700">
                                <span>CSS sendiri</span>
                                <span :class="['font-mono', cssLength > 18000 ? 'text-rose-600' : 'text-slate-400']">{{ cssLength }}/20000</span>
                            </label>
                            <textarea
                                id="design-css"
                                :value="design.customCss"
                                rows="12"
                                maxlength="20000"
                                spellcheck="false"
                                placeholder=".af-question-title {&#10;    letter-spacing: 0.01em;&#10;}"
                                class="mt-1.5 w-full rounded-xl border border-slate-300 bg-slate-950 px-3 py-2.5 font-mono text-xs leading-relaxed text-emerald-200 outline-none focus:border-indigo-500"
                                @input="update({ customCss: ($event.target as HTMLTextAreaElement).value }, true)"
                            ></textarea>
                            <p class="mt-1 text-[11px] leading-relaxed text-slate-500">
                                Hanya berlaku di halaman ujian ini. Gambar dari luar server lembaga, <code>@import</code> dan
                                <code>expression()</code> dibuang saat disimpan.
                            </p>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-slate-700">Kelas yang bisa diatur (klik untuk menambah)</p>
                            <ul class="mt-1.5 divide-y divide-slate-100 rounded-xl border border-slate-200">
                                <li v-for="hook in DESIGN_HOOKS" :key="hook.selector">
                                    <button
                                        type="button"
                                        class="flex w-full items-center justify-between gap-2 px-3 py-1.5 text-left hover:bg-slate-50"
                                        @click="insertHook(hook.selector)"
                                    >
                                        <code class="text-[11px] font-bold text-indigo-700">{{ hook.selector }}</code>
                                        <span class="text-[11px] text-slate-500">{{ hook.label }}</span>
                                    </button>
                                </li>
                            </ul>
                        </div>
                    </template>
                </div>

                <div class="flex flex-wrap items-center gap-2 border-t border-slate-100 px-5 py-3">
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-xl px-3 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100"
                        @click="resetDesign"
                    >
                        <RotateCcw class="h-3.5 w-3.5" /> Atur ulang
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-xl px-3 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 lg:hidden"
                        @click="showPreviewOnPhone = true"
                    >
                        <Eye class="h-3.5 w-3.5" /> Pratinjau
                    </button>
                    <a
                        v-if="publicUrl"
                        :href="publicUrl"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex items-center gap-1.5 rounded-xl px-3 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100"
                    >
                        <ExternalLink class="h-3.5 w-3.5" /> Lihat sebagai siswa
                    </a>
                    <button
                        type="button"
                        class="ml-auto rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white hover:bg-indigo-700"
                        @click="emit('close')"
                    >
                        Selesai
                    </button>
                </div>
            </div>

            <!-- Live preview -->
            <div class="relative min-h-0 flex-1 bg-slate-100" :class="showPreviewOnPhone ? 'block' : 'hidden lg:block'">
                <button
                    type="button"
                    class="absolute left-3 top-3 z-10 rounded-xl bg-white px-3 py-1.5 text-xs font-bold text-slate-700 shadow lg:hidden"
                    @click="showPreviewOnPhone = false"
                >
                    Kembali ke pengaturan
                </button>
                <DesignPreview :design="design" :title="title" :description="description" :question-font="questionFont" :answer-font="answerFont" />
            </div>
        </div>
    </div>
</template>
