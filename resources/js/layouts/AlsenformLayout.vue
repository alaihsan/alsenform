<script setup lang="ts">
import AccountMenu from '@/components/AccountMenu.vue';
import { Link } from '@inertiajs/vue3';

withDefaults(
    defineProps<{
        /** Hide the account menu, e.g. on the mandatory first-login password screen. */
        minimal?: boolean;
    }>(),
    {
        minimal: false,
    },
);
</script>

<template>
    <div class="min-h-screen bg-[#f5f4ff] text-slate-900">
        <header class="sticky top-0 z-30 border-b border-slate-100 bg-white">
            <div class="mx-auto flex h-14 max-w-6xl items-center gap-3 px-4 sm:px-6">
                <component :is="minimal ? 'div' : Link" :href="minimal ? undefined : route('dashboard')" class="flex items-center gap-2.5">
                    <div class="grid h-8 w-8 grid-cols-2 gap-1 rounded-xl bg-indigo-500 p-1.5 text-white shadow-[0_2px_0_#4338ca]">
                        <span class="rounded-lg bg-white/95"></span>
                        <span class="rounded-lg bg-white/70"></span>
                        <span class="rounded-lg bg-white/70"></span>
                        <span class="rounded-lg bg-white/95"></span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-base font-bold leading-tight tracking-tight text-slate-900 sm:text-lg">Alsenform</span>
                        <span class="text-[9px] font-medium leading-none text-slate-500 sm:text-[10px]">CBT & Exam Platform</span>
                    </div>
                </component>

                <div class="ml-auto flex items-center gap-2">
                    <slot name="header-actions" />
                    <AccountMenu v-if="!minimal" />
                </div>
            </div>
        </header>

        <slot />
    </div>
</template>
