<script setup lang="ts">
import { BRAND_NAME, BRAND_PARENT } from '@/lib/brand';
import { Link } from '@inertiajs/vue3';

/*
 * The line at the foot of the sign-in, help and privacy pages: the product, the company behind it,
 * the licence and the privacy policy.
 */
</script>

<template>
    <footer class="px-4 py-4 text-center text-xs font-semibold text-slate-400">
        &copy; {{ new Date().getFullYear() }} {{ BRAND_NAME }} · oleh {{ BRAND_PARENT }} · Perangkat lunak sumber terbuka berlisensi MIT ·
        <Link :href="route('privacy')" class="underline underline-offset-2 transition hover:text-slate-600">Kebijakan Privasi</Link>
    </footer>
</template>
