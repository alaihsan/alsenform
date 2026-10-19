<script setup lang="ts">
import BrandMark from '@/components/BrandMark.vue';
import { Link } from '@inertiajs/vue3';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="flex min-h-svh flex-col items-center justify-center gap-6 bg-[#f5f4ff] p-6 text-slate-950 md:p-10">
        <div class="w-full max-w-md rounded-[2rem] border-2 border-indigo-100 bg-white p-6 shadow-[0_14px_0_#e0e7ff]">
            <div class="flex flex-col gap-8">
                <div class="flex flex-col items-center gap-4">
                    <Link :href="route('home')" class="max-w-full font-medium">
                        <BrandMark size="lg" tagline="always" />
                        <span class="sr-only">, ke halaman awal</span>
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
