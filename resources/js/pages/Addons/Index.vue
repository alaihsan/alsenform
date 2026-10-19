<script setup lang="ts">
import AddonGallery, { type AddonEntry } from '@/components/addons/AddonGallery.vue';
import AlsenformLayout from '@/layouts/AlsenformLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Puzzle } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    addons: AddonEntry[];
    canInstallForEveryone: boolean;
}>();

const addons = ref<AddonEntry[]>(props.addons);
</script>

<template>
    <Head title="Add-on" />

    <AlsenformLayout>
        <main class="mx-auto max-w-6xl space-y-6 px-4 py-6 sm:px-6 sm:py-10">
            <Link :href="route('dashboard')" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 hover:text-indigo-700">
                <ArrowLeft class="h-4 w-4" /> Kembali ke Beranda
            </Link>
            <div class="rounded-3xl border border-indigo-100 bg-gradient-to-br from-indigo-50 via-white to-violet-50 p-6 sm:p-8">
                <p class="flex items-center gap-2 text-xs font-extrabold uppercase tracking-widest text-indigo-600">
                    <Puzzle class="h-4 w-4" /> Add-on
                </p>
                <h1 class="mt-1 text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">Tambah kemampuan ke ujian Anda</h1>
                <p class="mt-2 max-w-3xl text-sm leading-relaxed text-slate-600">
                    Pasang add-on sekali, lalu nyalakan atau matikan untuk tiap ujian lewat tombol Add-on di editor. Add-on hanya berlaku untuk ujian
                    milik Anda{{ canInstallForEveryone ? '; sebagai admin, Anda juga dapat memasangnya untuk semua guru' : '' }}.
                </p>
            </div>

            <AddonGallery
                v-model:addons="addons"
                :can-install-for-everyone="canInstallForEveryone"
                :install-url="route('addons.install', { addon: '__addon__' })"
                :uninstall-url="route('addons.uninstall', { addon: '__addon__' })"
            />
        </main>
    </AlsenformLayout>
</template>
