<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ClipboardList } from 'lucide-vue-next';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="flex min-h-svh flex-col items-center justify-center gap-6 bg-[#f6fbef] p-6 text-slate-950 md:p-10">
        <div class="w-full max-w-md rounded-[2rem] border-2 border-emerald-200 bg-white p-6 shadow-[0_14px_0_#d9f99d]">
            <div class="flex flex-col gap-8">
                <div class="flex flex-col items-center gap-4">
                    <Link :href="route('home')" class="flex flex-col items-center gap-2 font-medium">
                        <div class="mb-1 flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-500 text-white shadow-[0_6px_0_#159447]">
                            <ClipboardList class="h-8 w-8" />
                        </div>
                        <span class="sr-only">{{ title }}</span>
                    </Link>
                    <div class="space-y-2 text-center">
                        <h1 class="text-2xl font-black">{{ title }}</h1>
                        <p class="text-center text-sm font-semibold text-slate-500">{{ description }}</p>
                    </div>
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>
