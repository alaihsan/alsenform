<script setup lang="ts">
import BrandMark from '@/components/BrandMark.vue';
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
                <component :is="minimal ? 'div' : Link" :href="minimal ? undefined : route('dashboard')" class="flex min-w-0 items-center">
                    <BrandMark />
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
