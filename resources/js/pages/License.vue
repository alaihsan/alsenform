<script setup lang="ts">
import AlsenformLayout from '@/layouts/AlsenformLayout.vue';
import { BRAND_NAME, BRAND_PARENT } from '@/lib/brand';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/*
 * Lisensi: what anyone may do with Falcon Forms (the MIT licence), what they must keep (the
 * copyright and the credit to Falcon Education Partner as its developer), and the paid services
 * of Falcon Education Partner. The official text is the LICENSE file, passed in as it is.
 */
defineProps<{
    licenseText: string;
}>();

const page = usePage<{ auth?: { user?: unknown } }>();
const isSignedIn = computed(() => Boolean(page.props.auth?.user));

const summary: { title: string; tone: string; items: string[] }[] = [
    {
        title: 'Boleh',
        tone: 'border-emerald-200 bg-emerald-50/60',
        items: [
            'Dipakai gratis oleh lembaga mana pun, termasuk untuk kegiatan berbayar.',
            'Disalin dan dipasang sendiri di server lembaga.',
            'Diubah, ditambah fiturnya, dan digabung dengan perangkat lunak lain.',
            'Dibagikan atau dijual, baik asli maupun versi ubahan.',
        ],
    },
    {
        title: 'Wajib',
        tone: 'border-indigo-200 bg-indigo-50/60',
        items: [
            'Menyertakan pemberitahuan hak cipta dan teks lisensi ini di setiap salinan.',
            `Tetap menampilkan ${BRAND_PARENT} sebagai pengembang, misalnya di footer atau halaman Tentang.`,
            'Memakai nama dan logo sendiri bila membagikan versi ubahan.',
        ],
    },
    {
        title: 'Tidak termasuk',
        tone: 'border-slate-200 bg-slate-50',
        items: [
            'Jaminan apa pun: perangkat lunak disediakan apa adanya.',
            `Hak memakai nama ${BRAND_NAME}, ${BRAND_PARENT}, dan logo elang di luar keterangan pengembang.`,
        ],
    },
];
</script>

<template>
    <Head title="Lisensi" />

    <AlsenformLayout :minimal="!isSignedIn">
        <main class="mx-auto w-full max-w-3xl px-4 py-8 sm:px-6 sm:py-12">
            <Link :href="isSignedIn ? route('dashboard') : route('home')" class="text-sm font-bold text-slate-500 transition hover:text-indigo-600">
                &larr; {{ isSignedIn ? 'Kembali ke Beranda' : 'Kembali ke halaman masuk' }}
            </Link>

            <article
                class="mt-5 space-y-8 rounded-3xl border border-slate-200 bg-white p-6 text-sm leading-relaxed text-slate-700 shadow-sm sm:p-10 sm:text-base"
            >
                <header class="space-y-2">
                    <h1 class="text-2xl font-black text-slate-900 sm:text-3xl">Lisensi {{ BRAND_NAME }}</h1>
                    <p class="text-slate-500">
                        {{ BRAND_NAME }} dikembangkan oleh {{ BRAND_PARENT }} dan dirilis dengan Lisensi MIT dengan ketentuan atribusi: bebas dipakai,
                        diubah, dan dibagikan, selama {{ BRAND_PARENT }} tetap disebut sebagai pengembangnya.
                    </p>
                </header>

                <section class="grid gap-3 sm:grid-cols-3">
                    <div v-for="group in summary" :key="group.title" :class="['rounded-2xl border p-4', group.tone]">
                        <h2 class="font-bold text-slate-900">{{ group.title }}</h2>
                        <ul class="mt-2 list-disc space-y-1.5 pl-4 text-sm">
                            <li v-for="item in group.items" :key="item">{{ item }}</li>
                        </ul>
                    </div>
                </section>

                <section class="space-y-2">
                    <h2 class="text-lg font-bold text-slate-900">Layanan dari {{ BRAND_PARENT }}</h2>
                    <p>
                        Memasang dan memakai {{ BRAND_NAME }} sendiri tetap gratis. Bila lembaga Anda ingin dibantu, {{ BRAND_PARENT }} menyediakan
                        pelatihan guru dan admin, instalasi dan pengaturan server, serta pendampingan saat ujian. Layanan ini berbayar, dan biayanya
                        menjadi kontribusi untuk pengembangan {{ BRAND_NAME }} selanjutnya. Layanan ini terpisah dari lisensi: tanpa layanan pun,
                        semua hak di atas tetap berlaku.
                    </p>
                </section>

                <section class="space-y-3">
                    <h2 class="text-lg font-bold text-slate-900">Teks lisensi dalam bahasa Indonesia</h2>
                    <div class="space-y-3 rounded-2xl border border-slate-200 p-4 text-sm sm:p-5">
                        <p class="font-bold text-slate-900">Lisensi MIT dengan Atribusi</p>
                        <p>Hak cipta (c) 2026 {{ BRAND_PARENT }}</p>
                        <p>
                            Izin diberikan secara gratis kepada siapa pun yang memperoleh salinan perangkat lunak ini beserta dokumentasinya
                            ("Perangkat Lunak") untuk memperlakukan Perangkat Lunak tanpa batasan, termasuk tanpa batasan hak untuk memakai, menyalin,
                            mengubah, menggabungkan, menerbitkan, mendistribusikan, mensublisensikan, dan/atau menjual salinan Perangkat Lunak, serta
                            mengizinkan orang yang menerima Perangkat Lunak untuk melakukan hal yang sama, dengan syarat berikut:
                        </p>
                        <ol class="list-decimal space-y-2 pl-5">
                            <li>
                                Pemberitahuan hak cipta di atas dan pemberitahuan izin ini disertakan dalam semua salinan atau bagian penting
                                Perangkat Lunak.
                            </li>
                            <li>
                                Atribusi. Setiap salinan, versi ubahan, atau pemasangan Perangkat Lunak yang dipakai orang wajib tetap menampilkan
                                keterangan yang menyebut {{ BRAND_PARENT }} sebagai pengembang Perangkat Lunak, misalnya "Dikembangkan oleh
                                {{ BRAND_PARENT }}" atau "Berbasis {{ BRAND_NAME }}, dikembangkan oleh {{ BRAND_PARENT }}", di footer halamannya atau
                                di halaman Tentang. Keterangan itu tidak boleh dihapus, disembunyikan, atau diubah sehingga menyebut pengembang lain.
                            </li>
                            <li>
                                Merek. Lisensi ini tidak memberi hak memakai nama "{{ BRAND_NAME }}" dan "{{ BRAND_PARENT }}" atau logo elang, kecuali
                                untuk menyebut Perangkat Lunak yang tidak diubah dan dalam keterangan yang diwajibkan syarat 2. Versi ubahan yang
                                dibagikan pihak lain wajib memakai nama dan logonya sendiri.
                            </li>
                        </ol>
                        <p class="text-slate-600">
                            PERANGKAT LUNAK INI DISEDIAKAN "APA ADANYA", TANPA JAMINAN APA PUN, TERSURAT MAUPUN TERSIRAT, TERMASUK NAMUN TIDAK
                            TERBATAS PADA JAMINAN DAPAT DIPERDAGANGKAN, KESESUAIAN UNTUK TUJUAN TERTENTU, DAN TIDAK MELANGGAR HAK PIHAK LAIN. DALAM
                            KEADAAN APA PUN, PENULIS ATAU PEMEGANG HAK CIPTA TIDAK BERTANGGUNG JAWAB ATAS KLAIM, KERUGIAN, ATAU KEWAJIBAN LAIN, BAIK
                            DALAM TINDAKAN KONTRAK, PERBUATAN MELAWAN HUKUM, ATAU LAINNYA, YANG TIMBUL DARI, DI LUAR, ATAU SEHUBUNGAN DENGAN PERANGKAT
                            LUNAK ATAU PEMAKAIAN ATAU TRANSAKSI LAIN DALAM PERANGKAT LUNAK.
                        </p>
                    </div>
                </section>

                <section class="space-y-3">
                    <h2 class="text-lg font-bold text-slate-900">Teks lisensi dalam bahasa Inggris</h2>
                    <p class="text-sm text-slate-500">Sama dengan berkas LICENSE yang disertakan bersama kode.</p>
                    <pre
                        class="max-h-[28rem] overflow-auto whitespace-pre-wrap rounded-2xl border border-slate-200 bg-slate-50 p-4 font-mono text-xs leading-relaxed text-slate-700"
                        >{{ licenseText }}</pre
                    >
                </section>
            </article>
        </main>

    </AlsenformLayout>
</template>
