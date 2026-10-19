<script setup lang="ts">
import { BRAND_ICON, BRAND_NAME, BRAND_TAGLINE, BRAND_TAGLINE_SHORT } from '@/lib/brand';

/*
 * The Falcon Forms icon (a white falcon on a purple tile), its name and tagline: one look on the
 * sign-in page, the dashboard and every other page.
 */
withDefaults(
    defineProps<{
        /** The element of the name: h1 on a page whose title it is. */
        nameAs?: string;
        /** The tagline under the name; on small screens only when "always", then the short one. */
        tagline?: 'hidden' | 'wide' | 'always';
        size?: 'md' | 'lg';
    }>(),
    { nameAs: 'span', tagline: 'wide', size: 'md' },
);
</script>

<template>
    <span class="flex min-w-0 items-center gap-2.5">
        <img :src="BRAND_ICON" alt="" :class="['shrink-0', size === 'lg' ? 'h-10 w-10' : 'h-8 w-8']" width="40" height="40" />
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
                    'font-medium leading-tight text-slate-500',
                    size === 'lg' ? 'text-xs' : 'text-[10px]',
                    tagline === 'wide' ? 'hidden truncate sm:block' : '',
                ]"
            >
                <template v-if="tagline === 'always'">
                    <span class="sm:hidden">{{ BRAND_TAGLINE_SHORT }}</span>
                    <span class="hidden sm:inline">{{ BRAND_TAGLINE }}</span>
                </template>
                <template v-else>{{ BRAND_TAGLINE }}</template>
            </span>
        </span>
    </span>
</template>
