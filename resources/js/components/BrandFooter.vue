<script setup lang="ts">
import { BRAND_NAME, BRAND_PARENT } from '@/lib/brand';
import { Link } from '@inertiajs/vue3';

/*
 * The line at the foot of the sign-in, help, licence and privacy pages: the product, the company
 * behind it (the credit the licence requires), the licence and the privacy policy.
 */
</script>

<template>
    <footer class="px-4 py-4 text-center text-xs font-semibold text-slate-400">
        &copy; {{ new Date().getFullYear() }} {{ BRAND_NAME }} · Dikembangkan oleh {{ BRAND_PARENT }} · Perangkat lunak sumber terbuka,
        <Link :href="route('license')" class="underline underline-offset-2 transition hover:text-slate-600">Lisensi MIT dengan atribusi</Link>
        ·
        <Link :href="route('privacy')" class="underline underline-offset-2 transition hover:text-slate-600">Kebijakan Privasi</Link>
    </footer>
</template>
