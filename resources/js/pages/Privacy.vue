<script setup lang="ts">
import BrandFooter from '@/components/BrandFooter.vue';
import AlsenformLayout from '@/layouts/AlsenformLayout.vue';
import { BRAND_NAME, BRAND_PARENT } from '@/lib/brand';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/*
 * Kebijakan Privasi: what Falcon Forms keeps about the people who use it, why, who sees it and for
 * how long. The institution that runs the server is responsible for the data; it can add its own
 * contact through PRIVACY_CONTACT.
 */
defineProps<{
    contact: string | null;
}>();

const page = usePage<{ auth?: { user?: unknown } }>();
const isSignedIn = computed(() => Boolean(page.props.auth?.user));

const storedData: { title: string; items: string }[] = [
    {
        title: 'Akun',
        items: 'Nama, email, NIS atau NIP, kelas, nomor kontak, mata pelajaran, asal sekolah atau lembaga, dan foto profil. Password dan PIN pengawas disimpan dalam bentuk acak (hash), sehingga tidak bisa dibaca siapa pun.',
    },
    {
        title: 'Peserta tanpa akun',
        items: 'Nama, kelas, dan email, sebatas yang diminta formulir, ditambah kode acak di cookie peramban agar jawaban yang sama tidak terkirim dua kali.',
    },
    {
        title: 'Jawaban dan nilai',
        items: 'Jawaban, berkas yang diunggah, tanda ragu-ragu, nilai, dan komentar guru.',
    },
    {
        title: 'Selama ujian',
        items: 'Waktu mulai dan kirim, draf jawaban, catatan pindah tab dan kunci ujian, serta permintaan buka kunci.',
    },
    {
        title: 'Perangkat dan aktivitas',
        items: 'Alamat IP, browser, sistem operasi, dan jenis perangkat; catatan masuk, keluar, mulai ujian, dan kirim jawaban; serta daftar perangkat yang sedang login.',
    },
];
</script>

<template>
    <Head title="Kebijakan Privasi" />

    <AlsenformLayout :minimal="!isSignedIn">
        <main class="mx-auto w-full max-w-3xl px-4 py-8 sm:px-6 sm:py-12">
            <Link :href="isSignedIn ? route('dashboard') : route('home')" class="text-sm font-bold text-slate-500 transition hover:text-indigo-600">
                &larr; {{ isSignedIn ? 'Kembali ke Beranda' : 'Kembali ke halaman masuk' }}
            </Link>

            <article
                class="mt-5 space-y-8 rounded-3xl border border-slate-200 bg-white p-6 text-sm leading-relaxed text-slate-700 shadow-sm sm:p-10 sm:text-base"
            >
                <header class="space-y-2">
                    <h1 class="text-2xl font-black text-slate-900 sm:text-3xl">Kebijakan Privasi</h1>
                    <p class="text-slate-500">
                        Apa saja yang disimpan {{ BRAND_NAME }} tentang Anda, untuk apa, siapa yang dapat melihatnya, dan berapa lama.
                    </p>
                </header>

                <section class="space-y-2">
                    <h2 class="text-lg font-bold text-slate-900">Siapa yang mengelola data Anda</h2>
                    <p>
                        {{ BRAND_NAME }} dipasang dan dijalankan oleh lembaga Anda (sekolah, madrasah, bimbel, atau penyelenggara pelatihan) di server
                        lembaga itu. Lembaga Anda adalah pengendali data, dan admin lembaga yang mengelola akun serta datanya.
                        {{ BRAND_PARENT }} membuat perangkat lunak ini dan tidak menerima data dari server lembaga, kecuali lembaga Anda membuat
                        perjanjian terpisah, misalnya bila servernya dikelola oleh {{ BRAND_PARENT }}.
                    </p>
                </section>

                <section class="space-y-3">
                    <h2 class="text-lg font-bold text-slate-900">Data yang disimpan</h2>
                    <dl class="divide-y divide-slate-100 rounded-2xl border border-slate-200">
                        <div v-for="data in storedData" :key="data.title" class="grid gap-1 px-4 py-3 sm:grid-cols-[11rem_1fr] sm:gap-4">
                            <dt class="font-bold text-slate-900">{{ data.title }}</dt>
                            <dd class="min-w-0">{{ data.items }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="space-y-2">
                    <h2 class="text-lg font-bold text-slate-900">Untuk apa data dipakai</h2>
                    <p>
                        Untuk menjalankan formulir dan ujian, menilai jawaban, menjaga kejujuran ujian, mengamankan akun, dan menyusun laporan bagi
                        guru dan admin. Data tidak dipakai untuk iklan dan tidak dijual.
                    </p>
                </section>

                <section class="space-y-2">
                    <h2 class="text-lg font-bold text-slate-900">Siapa yang dapat melihatnya</h2>
                    <ul class="list-disc space-y-1.5 pl-5">
                        <li>
                            Guru melihat jawaban, nilai, dan aktivitas ujian pada formulir miliknya atau yang ia kelola bersama, serta catatan masuk
                            dan keluar akun siswa.
                        </li>
                        <li>Admin lembaga melihat semua akun dan semua laporan aktivitas.</li>
                        <li>Siswa dan peserta melihat datanya sendiri, dan hasil ujiannya bila guru mengizinkan.</li>
                    </ul>
                </section>

                <section class="space-y-2">
                    <h2 class="text-lg font-bold text-slate-900">Cookie dan penyimpanan di perangkat</h2>
                    <p>
                        {{ BRAND_NAME }} memakai cookie untuk sesi login, untuk keamanan formulir, dan untuk mengenali peserta tanpa akun selama 30
                        hari. Draf jawaban dan draf editor juga disimpan di peramban, supaya tidak hilang saat koneksi putus. Tidak ada cookie iklan
                        atau pelacak.
                    </p>
                </section>

                <section class="space-y-2">
                    <h2 class="text-lg font-bold text-slate-900">Layanan di luar server lembaga</h2>
                    <p>
                        Bila perangkat Anda tersambung ke internet, peramban mengambil huruf (font) dari Google Fonts dan Bunny Fonts setelah halaman
                        selesai dimuat; penyedia itu dapat melihat alamat IP perangkat Anda. Video YouTube yang ditempel guru di soal dimuat dari
                        YouTube. Selain itu, tidak ada data yang dikirim ke pihak lain.
                    </p>
                </section>

                <section class="space-y-2">
                    <h2 class="text-lg font-bold text-slate-900">Berapa lama data disimpan</h2>
                    <p>
                        Sampai dihapus oleh admin atau guru. Guru dapat menghapus akunnya sendiri beserta formulirnya. Akun siswa dihapus oleh admin,
                        agar riwayat nilai tetap utuh selama dibutuhkan lembaga.
                    </p>
                </section>

                <section class="space-y-2">
                    <h2 class="text-lg font-bold text-slate-900">Hak Anda</h2>
                    <p>
                        Anda dapat melihat dan memperbaiki data profil sendiri di menu Pengaturan. Untuk meminta salinan, perbaikan, atau penghapusan
                        data lain, hubungi admin lembaga Anda<template v-if="contact">
                            di <strong class="break-all text-slate-900">{{ contact }}</strong></template
                        >.
                    </p>
                </section>

                <section class="space-y-2">
                    <h2 class="text-lg font-bold text-slate-900">Keamanan</h2>
                    <p>
                        Password disimpan dalam bentuk acak (hash). Berkas yang diunggah peserta tidak disimpan di folder publik dan hanya dapat
                        dibuka guru ujian itu.
                    </p>
                </section>

                <p class="rounded-2xl bg-slate-50 p-4 text-xs text-slate-500 sm:text-sm">
                    Halaman ini menjelaskan cara kerja {{ BRAND_NAME }}. Lembaga yang memakainya bertanggung jawab melengkapinya dengan kontak dan
                    kebijakannya sendiri, sesuai Undang-Undang Nomor 27 Tahun 2022 tentang Pelindungan Data Pribadi.
                </p>
            </article>
        </main>

        <BrandFooter />
    </AlsenformLayout>
</template>
