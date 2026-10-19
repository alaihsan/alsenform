<script setup lang="ts">
import { BRAND_NAME, BRAND_PARENT } from '@/lib/brand';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/*
 * The line at the foot of every page for teachers and admins: the product, the company behind it
 * (the credit the licence requires), the licence and the privacy policy. Mounted once beside the
 * page (app.ts). Students and visitors who are not signed in (the sign-in page) do not get it.
 */
const page = usePage<{ auth?: { user?: unknown; isStudent?: boolean } }>();
const isShown = computed(() => Boolean(page.props.auth?.user) && !page.props.auth?.isStudent);
</script>

<template>
    <footer
        v-if="isShown"
        class="border-t border-slate-200 bg-white px-4 py-4 text-center text-xs font-semibold text-slate-400"
        data-testid="brand-footer"
    >
        &copy; {{ new Date().getFullYear() }} {{ BRAND_NAME }} · Dikembangkan oleh {{ BRAND_PARENT }} · Perangkat lunak sumber terbuka,
        <Link :href="route('license')" class="underline underline-offset-2 transition hover:text-slate-600">Lisensi MIT dengan atribusi</Link>
        ·
        <Link :href="route('privacy')" class="underline underline-offset-2 transition hover:text-slate-600">Kebijakan Privasi</Link>
    </footer>
</template>
