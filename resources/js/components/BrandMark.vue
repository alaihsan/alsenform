<script setup lang="ts">
import { BRAND_NAME, BRAND_TAGLINE } from '@/lib/brand';

/*
 * The Alsenform logo (four indigo tiles), its name and tagline: one look on the sign-in page, the
 * dashboard and every other page.
 */
withDefaults(
    defineProps<{
        /** The element of the name: h1 on a page whose title it is. */
        nameAs?: string;
        /** The tagline under the name; on small screens only when "always". */
        tagline?: 'hidden' | 'wide' | 'always';
        size?: 'md' | 'lg';
    }>(),
    { nameAs: 'span', tagline: 'wide', size: 'md' },
);
</script>

<template>
    <span class="flex min-w-0 items-center gap-2.5">
        <span
            :class="[
                'grid shrink-0 grid-cols-2 gap-1 rounded-xl bg-indigo-500 text-white',
                size === 'lg' ? 'h-10 w-10 p-2 shadow-[0_3px_0_#4338ca]' : 'h-8 w-8 p-1.5 shadow-[0_2px_0_#4338ca]',
            ]"
            aria-hidden="true"
        >
            <span class="rounded-lg bg-white/95"></span>
            <span class="rounded-lg bg-white/70"></span>
            <span class="rounded-lg bg-white/70"></span>
            <span class="rounded-lg bg-white/95"></span>
        </span>
        <span class="flex min-w-0 flex-col">
            <component
                :is="nameAs"
                :class="['font-bold leading-tight tracking-tight text-slate-900', size === 'lg' ? 'text-lg' : 'text-base sm:text-lg']"
            >
                {{ BRAND_NAME }}
            </component>
            <span
                v-if="tagline !== 'hidden'"
                :class="[
                    'truncate font-medium leading-tight text-slate-500',
                    size === 'lg' ? 'text-xs' : 'text-[10px]',
                    tagline === 'wide' ? 'hidden sm:block' : '',
                ]"
            >
                {{ BRAND_TAGLINE }}
            </span>
        </span>
    </span>
</template>
