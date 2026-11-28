<script setup lang="ts">
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { useOnlineAddress } from '@/composables/useOnlineAddress';
import { copyText } from '@/lib/clipboard';
import { Link, usePage } from '@inertiajs/vue3';
import { Activity, Check, Copy, ExternalLink, Globe } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref } from 'vue';

/*
 * Alamat online in the top bar of admins and teachers: whether the server can be opened on the
 * internet now, and its address, always the current one (it changes when the tunnel starts again).
 * Not shown to students, nor on a server meant for the school network only.
 */

const page = usePage<any>();
const user = computed(() => page.props.auth?.user);
const isAdmin = computed<boolean>(() => Boolean(user.value?.is_admin || user.value?.role === 'admin'));
const isStaff = computed<boolean>(() => {
    const current = user.value;
    if (!current) {
        return false;
    }
    if (isAdmin.value || current.role === 'guru') {
        return true;
    }

    return !(current.role === 'siswa' || current.role === 'murid' || Boolean(current.nis));
});

const { configured, url, hasLoaded } = useOnlineAddress(() => isStaff.value);
const isShown = computed<boolean>(() => isStaff.value && hasLoaded.value && configured.value);
const isOpenedHere = computed<boolean>(() => url.value !== null && window.location.origin === url.value);

const copied = ref(false);
let copiedTimer: ReturnType<typeof setTimeout> | null = null;
/** Copied with the menu left open, to see that it worked. */
const copyAddress = async (event: Event) => {
    event.preventDefault();
    if (!url.value || !(await copyText(url.value))) {
        return;
    }
    copied.value = true;
    if (copiedTimer) {
        clearTimeout(copiedTimer);
    }
    copiedTimer = setTimeout(() => (copied.value = false), 2_000);
};
onBeforeUnmount(() => {
    if (copiedTimer) {
        clearTimeout(copiedTimer);
    }
});
</script>

<template>
    <DropdownMenu v-if="isShown">
        <DropdownMenuTrigger :as-child="true">
            <button
                type="button"
                :class="[
                    'inline-flex h-9 items-center gap-1.5 rounded-full border px-2.5 text-xs font-bold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 sm:px-3',
                    url
                        ? 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 focus-visible:ring-emerald-500'
                        : 'border-slate-200 bg-slate-50 text-slate-500 hover:bg-slate-100 focus-visible:ring-slate-400',
                ]"
                :aria-label="url ? `Alamat online menyala: ${url}` : 'Alamat online mati'"
                :title="url ?? 'Akses internet sedang mati'"
                data-testid="online-address"
            >
                <span class="relative flex h-2 w-2" aria-hidden="true">
                    <span v-if="url" class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-60" />
                    <span :class="['relative inline-flex h-2 w-2 rounded-full', url ? 'bg-emerald-500' : 'bg-slate-400']" />
                </span>
                <Globe class="h-4 w-4" />
                <span class="hidden sm:inline">{{ url ? 'Online' : 'Offline' }}</span>
            </button>
        </DropdownMenuTrigger>

        <DropdownMenuContent
            align="end"
            class="z-50 mt-1.5 w-[min(22rem,calc(100vw-2rem))] rounded-2xl border border-slate-200 bg-white p-3.5 shadow-xl"
        >
            <p class="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-slate-500">
                <Globe class="h-3.5 w-3.5" />
                Alamat Online (Internet)
            </p>

            <template v-if="url">
                <a
                    :href="url"
                    target="_blank"
                    rel="noopener"
                    class="mt-2 block break-all rounded-xl border border-emerald-100 bg-emerald-50 px-3 py-2.5 font-mono text-sm font-semibold text-emerald-800 transition hover:bg-emerald-100"
                    data-testid="online-address-url"
                    >{{ url }}</a
                >
                <p v-if="isOpenedHere" class="mt-1.5 text-xs font-semibold text-emerald-700">Anda sedang membuka lewat alamat ini.</p>

                <div class="mt-2.5 grid grid-cols-2 gap-2">
                    <DropdownMenuItem
                        class="flex cursor-pointer items-center justify-center gap-1.5 rounded-xl bg-indigo-600 px-3 py-2 text-sm font-bold text-white transition hover:bg-indigo-700 focus:bg-indigo-700 focus:outline-none"
                        data-testid="online-address-copy"
                        @select="copyAddress"
                    >
                        <Check v-if="copied" class="h-4 w-4" />
                        <Copy v-else class="h-4 w-4" />
                        {{ copied ? 'Disalin' : 'Salin alamat' }}
                    </DropdownMenuItem>
                    <DropdownMenuItem :as-child="true">
                        <a
                            :href="url"
                            target="_blank"
                            rel="noopener"
                            class="flex cursor-pointer items-center justify-center gap-1.5 rounded-xl border border-slate-200 px-3 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-50 focus:bg-slate-50 focus:outline-none"
                        >
                            <ExternalLink class="h-4 w-4" />
                            Buka
                        </a>
                    </DropdownMenuItem>
                </div>

                <p class="mt-2.5 text-xs leading-relaxed text-slate-500">
                    Bisa dibuka dari mana saja lewat internet. Alamat ini berganti setiap kali server atau tunnel dijalankan ulang; yang tampil di
                    sini selalu yang terbaru, jadi salin dari sini. Tautan ujian online ada di tombol Terbitkan pada editor.
                </p>
            </template>

            <template v-else>
                <p class="mt-2 rounded-xl border border-amber-100 bg-amber-50 px-3 py-2.5 text-sm font-semibold text-amber-800">
                    Akses internet sedang mati.
                </p>
                <p class="mt-2 text-xs leading-relaxed text-slate-500">
                    Siswa dan guru hanya bisa membuka di jaringan sekolah. Alamat baru muncul di sini dengan sendirinya begitu tunnel menyala lagi.
                </p>
                <DropdownMenuItem v-if="isAdmin" :as-child="true">
                    <Link
                        :href="route('server-health.index')"
                        class="mt-2 flex cursor-pointer items-center gap-1.5 rounded-lg px-1 py-1 text-xs font-bold text-indigo-600 hover:text-indigo-700 focus:outline-none"
                    >
                        <Activity class="h-3.5 w-3.5" />
                        Cara menyalakan: Kesehatan Server &rarr;
                    </Link>
                </DropdownMenuItem>
            </template>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
