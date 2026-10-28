<script setup lang="ts">
import AlsenformLayout from '@/layouts/AlsenformLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { Activity, CheckCircle2, Gauge, Laptop, Lightbulb, RefreshCw, Server, TriangleAlert, Wifi, XCircle } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    client: { ip: string | null; viaProxy: boolean; device: string };
    pingUrl: string;
    downloadUrl: string;
}>();

const page = usePage<{ auth?: { user?: unknown } }>();
const isSignedIn = computed(() => Boolean(page.props.auth?.user));
const openedHost = typeof window === 'undefined' ? '' : window.location.host;

const PINGS = 20;
const PING_TIMEOUT_MS = 5000;
const DOWNLOADS = 2;
const DOWNLOAD_TIMEOUT_MS = 30000;

type Ping = { totalMs: number; serverMs: number } | null;

const state = ref<'idle' | 'running' | 'done'>('idle');
const progress = ref(0);
const pings = ref<Ping[]>([]);
const speedsMbps = ref<number[]>([]);
const downloadFailures = ref(0);

const serverMsOf = (response: Response): number => {
    const match = /dur=([\d.]+)/.exec(response.headers.get('Server-Timing') ?? '');
    return match ? Number(match[1]) : 0;
};

/** One request with a time limit: a stalled request counts as failed instead of waiting forever. */
const timedFetch = async (url: string, timeoutMs: number) => {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), timeoutMs);
    const started = performance.now();
    try {
        const response = await fetch(`${url}?t=${Date.now()}-${Math.random()}`, { cache: 'no-store', signal: controller.signal });
        const body = await response.arrayBuffer();
        if (!response.ok) {
            return null;
        }
        return { totalMs: performance.now() - started, serverMs: serverMsOf(response), bytes: body.byteLength };
    } catch {
        return null;
    } finally {
        clearTimeout(timer);
    }
};

const run = async () => {
    state.value = 'running';
    progress.value = 0;
    pings.value = [];
    speedsMbps.value = [];
    downloadFailures.value = 0;
    const steps = PINGS + DOWNLOADS;

    for (let index = 0; index < PINGS; index++) {
        const result = await timedFetch(props.pingUrl, PING_TIMEOUT_MS);
        pings.value = [...pings.value, result ? { totalMs: result.totalMs, serverMs: result.serverMs } : null];
        progress.value = Math.round(((index + 1) / steps) * 100);
    }

    for (let index = 0; index < DOWNLOADS; index++) {
        const result = await timedFetch(props.downloadUrl, DOWNLOAD_TIMEOUT_MS);
        if (result) {
            speedsMbps.value = [...speedsMbps.value, (result.bytes * 8) / 1_000_000 / (result.totalMs / 1000)];
        } else {
            downloadFailures.value++;
        }
        progress.value = Math.round(((PINGS + index + 1) / steps) * 100);
    }

    state.value = 'done';
};

const median = (values: number[]) => {
    if (!values.length) {
        return null;
    }
    const sorted = [...values].sort((a, b) => a - b);
    return sorted[Math.floor(sorted.length / 2)];
};

const answered = computed(() => pings.value.filter((ping): ping is NonNullable<Ping> => ping !== null));
const failures = computed(() => pings.value.filter((ping) => ping === null).length + downloadFailures.value);
const medianMs = computed(() => median(answered.value.map((ping) => ping.totalMs)));
const slowestMs = computed(() => (answered.value.length ? Math.max(...answered.value.map((ping) => ping.totalMs)) : null));
const serverMs = computed(() => median(answered.value.map((ping) => ping.serverMs)));
const networkMs = computed(() => (medianMs.value === null ? null : Math.max(0, medianMs.value - (serverMs.value ?? 0))));
const speedMbps = computed(() => median(speedsMbps.value));

type Verdict = { tone: 'good' | 'warn' | 'bad'; title: string; detail: string };

/** What the numbers mean, the most serious first. */
const verdicts = computed<Verdict[]>(() => {
    if (state.value !== 'done') {
        return [];
    }
    const list: Verdict[] = [];
    if (failures.value > 0) {
        list.push({
            tone: 'bad',
            title: `Koneksi putus-putus: ${failures.value} dari ${PINGS + DOWNLOADS} permintaan gagal`,
            detail: 'Inilah yang membuat halaman kadang tidak terbuka atau muncul halaman "jaringan terputus". Penyebabnya di jaringan, bukan di soal.',
        });
    }
    if (serverMs.value !== null && serverMs.value > 300) {
        list.push({
            tone: 'warn',
            title: `Server sibuk: ${Math.round(serverMs.value)} ms untuk menjawab`,
            detail: 'Server sendiri lambat. Tutup aplikasi lain di komputer server dan jalankan php artisan exam:optimize.',
        });
    }
    if (networkMs.value !== null && (networkMs.value > 300 || (slowestMs.value ?? 0) > 2000)) {
        list.push({
            tone: networkMs.value > 1000 ? 'bad' : 'warn',
            title: `Jaringan lambat: ${Math.round(networkMs.value)} ms di perjalanan (paling lambat ${Math.round(slowestMs.value ?? 0)} ms)`,
            detail: 'Server menjawab cepat, tetapi datanya lama sampai di perangkat ini. Lihat saran di bawah.',
        });
    }
    if (speedMbps.value !== null && speedMbps.value < 2) {
        list.push({
            tone: 'warn',
            title: `Kecepatan unduh rendah: ${speedMbps.value.toFixed(1).replace('.', ',')} Mbps`,
            detail: 'Soal bergambar atau video akan lambat terbuka di perangkat ini.',
        });
    }
    if (!list.length) {
        list.push({
            tone: 'good',
            title: 'Koneksi baik',
            detail: 'Perangkat ini terhubung lancar ke server ujian. Bila halaman tetap lambat, coba di browser lain atau matikan ekstensi browser.',
        });
    }
    return list;
});

const format = (ms: number | null) => (ms === null ? '–' : `${Math.round(ms)} ms`);

const TONES = {
    good: { box: 'border-emerald-200 bg-emerald-50 text-emerald-900', icon: CheckCircle2, iconClass: 'text-emerald-600' },
    warn: { box: 'border-amber-200 bg-amber-50 text-amber-900', icon: TriangleAlert, iconClass: 'text-amber-600' },
    bad: { box: 'border-rose-200 bg-rose-50 text-rose-900', icon: XCircle, iconClass: 'text-rose-600' },
};

const TIPS = [
    'Sambungkan komputer server ke router atau access point dengan kabel LAN, bukan Wi-Fi.',
    'Di server Mac, matikan AirDrop dan Handoff selama ujian: keduanya membuat Wi-Fi Mac tersendat setiap beberapa detik.',
    'Buka aplikasi dengan http://falcon.intranet:2026 atau alamat IP server (misalnya http://192.168.1.10:2026), bukan nama berakhiran .local, terutama di Windows dan Android.',
    'Di laptop Windows: matikan proxy (Settings → Network → Proxy, termasuk "Automatically detect settings") dan VPN, atau kecualikan alamat server.',
    'Antivirus dengan perlindungan web (Web Shield, HTTP scanning) bisa memperlambat atau memutus halaman: kecualikan alamat server.',
    'Atur mode daya laptop ke "Best performance" saat ujian; mode hemat daya ikut menghemat Wi-Fi.',
    'Pilih Wi-Fi 5 GHz bila ada, dan jangan lebih dari sekitar 30–40 perangkat pada satu access point.',
];
</script>

<template>
    <Head title="Cek Koneksi" />

    <AlsenformLayout :minimal="!isSignedIn">
        <main class="mx-auto max-w-3xl space-y-5 px-4 py-6 sm:px-6 sm:py-10">
            <div>
                <p class="flex items-center gap-2 text-xs font-extrabold uppercase tracking-widest text-indigo-600">
                    <Activity class="h-4 w-4" /> Cek Koneksi
                </p>
                <h1 class="mt-1 text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">Seberapa lancar perangkat ini ke server ujian?</h1>
                <p class="mt-2 text-sm leading-relaxed text-slate-600">
                    Jalankan tes ini di perangkat yang terasa lambat. Hasilnya memisahkan waktu yang dipakai server dari waktu di jaringan, jadi
                    kelihatan apakah masalahnya di server, di Wi-Fi, atau di pengaturan perangkat.
                </p>
            </div>

            <section class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 text-sm sm:grid-cols-3 sm:p-5">
                <div class="flex items-start gap-2.5">
                    <Server class="mt-0.5 h-4 w-4 shrink-0 text-slate-400" />
                    <div class="min-w-0">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Alamat yang dibuka</p>
                        <p class="truncate font-mono font-semibold text-slate-800">{{ openedHost }}</p>
                    </div>
                </div>
                <div class="flex items-start gap-2.5">
                    <Wifi class="mt-0.5 h-4 w-4 shrink-0 text-slate-400" />
                    <div class="min-w-0">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">IP perangkat menurut server</p>
                        <p class="truncate font-mono font-semibold text-slate-800">{{ client.ip ?? '–' }}</p>
                    </div>
                </div>
                <div class="flex items-start gap-2.5">
                    <Laptop class="mt-0.5 h-4 w-4 shrink-0 text-slate-400" />
                    <div class="min-w-0">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Perangkat</p>
                        <p class="text-slate-800">{{ client.device || '–' }}</p>
                    </div>
                </div>
                <p v-if="client.viaProxy" class="rounded-xl bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-900 sm:col-span-3">
                    Permintaan dari perangkat ini lewat proxy sebelum sampai ke server. Proxy (atau antivirus dengan perlindungan web) sering membuat
                    aplikasi intranet lambat atau terputus: matikan, atau kecualikan alamat server.
                </p>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-slate-600">{{ PINGS }} permintaan kecil dan {{ DOWNLOADS }} unduhan 1 MB, sekitar 10 detik.</p>
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-indigo-700 disabled:opacity-60"
                        :disabled="state === 'running'"
                        @click="run"
                    >
                        <RefreshCw :class="['h-4 w-4', state === 'running' ? 'animate-spin' : '']" />
                        {{ state === 'running' ? `Menguji... ${progress}%` : state === 'done' ? 'Tes lagi' : 'Mulai tes' }}
                    </button>
                </div>
                <div v-if="state === 'running'" class="mt-4 h-2 overflow-hidden rounded-full bg-slate-100">
                    <div class="h-full rounded-full bg-indigo-500 transition-all" :style="{ width: `${progress}%` }"></div>
                </div>

                <template v-if="state === 'done'">
                    <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <div class="rounded-xl bg-slate-50 p-3">
                            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Waktu respons</p>
                            <p class="mt-1 text-xl font-black text-slate-900">{{ format(medianMs) }}</p>
                            <p class="text-[11px] text-slate-500">paling lambat {{ format(slowestMs) }}</p>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-3">
                            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Di jaringan</p>
                            <p class="mt-1 text-xl font-black text-slate-900">{{ format(networkMs) }}</p>
                            <p class="text-[11px] text-slate-500">di server {{ format(serverMs) }}</p>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-3">
                            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Gagal</p>
                            <p :class="['mt-1 text-xl font-black', failures ? 'text-rose-600' : 'text-slate-900']">
                                {{ failures }} / {{ PINGS + DOWNLOADS }}
                            </p>
                            <p class="text-[11px] text-slate-500">batas {{ PING_TIMEOUT_MS / 1000 }} detik per permintaan</p>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-3">
                            <p class="flex items-center gap-1 text-[11px] font-bold uppercase tracking-wider text-slate-500">
                                <Gauge class="h-3 w-3" /> Unduh
                            </p>
                            <p class="mt-1 text-xl font-black text-slate-900">
                                {{ speedMbps === null ? '–' : `${speedMbps.toFixed(1).replace('.', ',')} Mbps` }}
                            </p>
                            <p class="text-[11px] text-slate-500">dari server ke perangkat ini</p>
                        </div>
                    </div>

                    <div class="mt-4 space-y-2">
                        <div
                            v-for="verdict in verdicts"
                            :key="verdict.title"
                            :class="['flex items-start gap-2.5 rounded-xl border px-3.5 py-3', TONES[verdict.tone].box]"
                        >
                            <component :is="TONES[verdict.tone].icon" :class="['mt-0.5 h-4 w-4 shrink-0', TONES[verdict.tone].iconClass]" />
                            <div>
                                <p class="text-sm font-bold">{{ verdict.title }}</p>
                                <p class="mt-0.5 text-xs leading-relaxed opacity-90">{{ verdict.detail }}</p>
                            </div>
                        </div>
                    </div>
                </template>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5">
                <h2 class="flex items-center gap-2 text-sm font-bold text-slate-900">
                    <Lightbulb class="h-4 w-4 text-amber-500" /> Penyebab umum aplikasi intranet lambat
                </h2>
                <ul class="mt-3 list-disc space-y-1.5 pl-5 text-sm leading-relaxed text-slate-600">
                    <li v-for="tip in TIPS" :key="tip">{{ tip }}</li>
                </ul>
            </section>
        </main>
    </AlsenformLayout>
</template>
