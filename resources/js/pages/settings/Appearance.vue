<script setup lang="ts">
import { useAppearance } from '@/composables/useAppearance';
import { alsenCard, alsenCardDescription, alsenCardTitle } from '@/constants/alsenform-ui';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { Head } from '@inertiajs/vue3';
import { Check, Monitor, Moon, Palette, Sun } from 'lucide-vue-next';

const { appearance, updateAppearance } = useAppearance();

const options = [
    { value: 'light', icon: Sun, label: 'Terang', description: 'Latar putih, nyaman di ruang kelas yang terang.' },
    { value: 'dark', icon: Moon, label: 'Gelap', description: 'Latar gelap untuk ruangan redup.' },
    { value: 'system', icon: Monitor, label: 'Ikuti perangkat', description: 'Menyesuaikan pengaturan tema perangkat.' },
] as const;
</script>

<template>
    <Head title="Tampilan" />

    <SettingsLayout>
        <div :class="alsenCard">
            <div class="flex items-start gap-3">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600">
                    <Palette class="h-5 w-5" />
                </div>
                <div>
                    <h2 :class="alsenCardTitle">Tampilan</h2>
                    <p :class="alsenCardDescription">
                        Berlaku untuk halaman Bantuan dan halaman masuk di perangkat ini. Halaman ujian, editor, dan pengaturan selalu bertema terang.
                    </p>
                </div>
            </div>

            <div class="mt-6 grid gap-3 sm:grid-cols-3">
                <button
                    v-for="option in options"
                    :key="option.value"
                    type="button"
                    :class="[
                        'relative flex flex-col items-start gap-2 rounded-2xl border p-4 text-left transition',
                        appearance === option.value
                            ? 'border-indigo-500 bg-indigo-50 ring-4 ring-indigo-100'
                            : 'border-slate-200 bg-white hover:border-indigo-300 hover:bg-slate-50',
                    ]"
                    :aria-pressed="appearance === option.value"
                    @click="updateAppearance(option.value)"
                >
                    <span
                        :class="[
                            'flex h-10 w-10 items-center justify-center rounded-xl',
                            appearance === option.value ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600',
                        ]"
                    >
                        <component :is="option.icon" class="h-5 w-5" />
                    </span>
                    <span class="text-sm font-bold text-slate-900">{{ option.label }}</span>
                    <span class="text-xs leading-relaxed text-slate-500">{{ option.description }}</span>
                    <Check v-if="appearance === option.value" class="absolute right-3 top-3 h-4 w-4 text-indigo-600" />
                </button>
            </div>
        </div>
    </SettingsLayout>
</template>
