<script setup lang="ts">
import type { AddonEntry } from '@/components/addons/AddonGallery.vue';
import { FlaskConical, GraduationCap, KeyRound, Puzzle } from 'lucide-vue-next';

defineProps<{
    /** Add-ons installed for this teacher or for the exam's owner. */
    addons: AddonEntry[];
    isOn: (addon: AddonEntry) => boolean;
}>();

const emit = defineEmits<{
    toggle: [addon: AddonEntry, on: boolean];
}>();

const ICONS: Record<string, unknown> = { 'flask-conical': FlaskConical, 'key-round': KeyRound, 'graduation-cap': GraduationCap };
</script>

<template>
    <ul class="space-y-1">
        <li v-for="addon in addons" :key="addon.key" class="flex items-center gap-3 rounded-xl px-2 py-2">
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                <component :is="ICONS[addon.icon] ?? Puzzle" class="h-5 w-5" />
            </div>
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-bold text-slate-900">{{ addon.name }}</p>
                <p class="text-[11px] text-slate-500">
                    {{
                        !addon.installedForOwner
                            ? 'Pemilik ujian belum memasang add-on ini'
                            : isOn(addon)
                              ? 'Aktif di ujian ini'
                              : 'Mati di ujian ini'
                    }}
                </p>
            </div>
            <button
                type="button"
                role="switch"
                :aria-checked="isOn(addon)"
                :aria-label="`${addon.name} di ujian ini`"
                :disabled="!addon.installedForOwner"
                :class="[
                    'relative h-5 w-9 shrink-0 rounded-full transition disabled:cursor-not-allowed disabled:opacity-40',
                    isOn(addon) ? 'bg-indigo-500' : 'bg-slate-300',
                ]"
                @click="emit('toggle', addon, !isOn(addon))"
            >
                <span :class="['absolute top-0.5 h-4 w-4 rounded-full bg-white shadow transition', isOn(addon) ? 'left-[18px]' : 'left-0.5']"></span>
            </button>
        </li>
    </ul>
</template>
