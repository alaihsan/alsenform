<script setup lang="ts">
import axios from 'axios';
import { Flag, LayoutGrid, LockOpen } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';

type Status = 'locked' | 'left' | 'offline' | 'idle' | 'working' | 'not_started' | 'submitted';

export type ClassMapStudent = {
    id: number;
    name: string;
    kelas: string | null;
    status: Status;
    answered: number;
    total: number;
    doubts: number;
    blurCount: number;
    remainingSeconds: number | null;
    lastSeenSeconds: number | null;
    idleMinutes: number | null;
};

/*
 * Peta Kelas: every student of the exam at a glance while it runs, for the proctor. Students who
 * need a look (locked, left the exam page, lost the connection, no new answer for a while) come first.
 */
const props = defineProps<{
    url: string;
    /** Ids of the attempts being unlocked right now ("map-<session id>"). */
    busyIds: string[];
}>();

const emit = defineEmits<{
    unlock: [student: ClassMapStudent];
}>();

const STATUS: Record<Status, { label: string; chip: string; edge: string; bar: string }> = {
    locked: { label: 'Terkunci', chip: 'bg-rose-100 text-rose-700', edge: 'border-l-rose-500', bar: 'bg-rose-400' },
    left: { label: 'Keluar halaman', chip: 'bg-orange-100 text-orange-700', edge: 'border-l-orange-500', bar: 'bg-orange-400' },
    offline: { label: 'Terputus', chip: 'bg-slate-200 text-slate-700', edge: 'border-l-slate-500', bar: 'bg-slate-400' },
    idle: { label: 'Diam', chip: 'bg-amber-100 text-amber-800', edge: 'border-l-amber-400', bar: 'bg-amber-400' },
    working: { label: 'Mengerjakan', chip: 'bg-indigo-50 text-indigo-700', edge: 'border-l-indigo-500', bar: 'bg-indigo-500' },
    not_started: { label: 'Belum mulai', chip: 'bg-slate-100 text-slate-500', edge: 'border-l-slate-300', bar: 'bg-slate-300' },
    submitted: { label: 'Selesai', chip: 'bg-emerald-50 text-emerald-700', edge: 'border-l-emerald-500', bar: 'bg-emerald-500' },
};
const ORDER: Status[] = ['locked', 'left', 'offline', 'idle', 'working', 'not_started', 'submitted'];

const students = ref<ClassMapStudent[]>([]);
const counts = ref<Partial<Record<Status, number>>>({});
const filter = ref<Status | null>(null);
const hasLoaded = ref(false);
const loadError = ref('');
let timer: ReturnType<typeof setTimeout> | null = null;
let isUnmounted = false;

const load = async () => {
    try {
        const response = await axios.get(props.url, { timeout: 10000 });
        students.value = response.data.students;
        counts.value = response.data.counts;
        loadError.value = '';
    } catch {
        loadError.value = 'Peta kelas belum dapat diperbarui. Mencoba lagi...';
    } finally {
        hasLoaded.value = true;
        if (!isUnmounted) {
            // Every 5 s while the editor is in view, less often in a background tab.
            timer = setTimeout(load, document.hidden ? 15000 : 5000);
        }
    }
};

onMounted(load);
onUnmounted(() => {
    isUnmounted = true;
    if (timer) {
        clearTimeout(timer);
    }
});

const shown = computed(() => (filter.value ? students.value.filter((student) => student.status === filter.value) : students.value));
const presentStatuses = computed(() => ORDER.filter((status) => (counts.value[status] ?? 0) > 0));

const statusLabel = (student: ClassMapStudent) =>
    student.status === 'idle' && student.idleMinutes !== null ? `Diam ${student.idleMinutes} mnt` : STATUS[student.status].label;
const remaining = (seconds: number) => (seconds >= 60 ? `sisa ${Math.floor(seconds / 60)} mnt` : `sisa ${seconds} dtk`);
</script>

<template>
    <section class="rounded-xl border border-slate-300 bg-white p-5 shadow-sm sm:p-7" aria-labelledby="class-map-heading">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="flex items-start gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                    <LayoutGrid class="h-5 w-5" />
                </div>
                <div>
                    <h2 id="class-map-heading" class="text-base font-semibold text-slate-900">Peta Kelas</h2>
                    <p class="mt-0.5 text-sm text-slate-600">
                        Semua siswa sekilas, diperbarui tiap 5 detik. Yang perlu dilihat pengawas tampil paling depan.
                    </p>
                </div>
            </div>
        </div>

        <div v-if="presentStatuses.length" class="mt-4 flex flex-wrap gap-1.5" role="group" aria-label="Saring menurut status">
            <button
                type="button"
                :class="[
                    'rounded-full px-3 py-1 text-xs font-bold transition',
                    filter === null ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200',
                ]"
                @click="filter = null"
            >
                Semua {{ students.length }}
            </button>
            <button
                v-for="status in presentStatuses"
                :key="status"
                type="button"
                :aria-pressed="filter === status"
                :class="[
                    'rounded-full px-3 py-1 text-xs font-bold transition',
                    STATUS[status].chip,
                    filter === status ? 'ring-2 ring-slate-900' : '',
                ]"
                @click="filter = filter === status ? null : status"
            >
                {{ STATUS[status].label }} {{ counts[status] }}
            </button>
        </div>

        <p v-if="loadError" class="mt-3 text-xs font-semibold text-rose-700" role="alert">{{ loadError }}</p>
        <p
            v-if="hasLoaded && !students.length"
            class="mt-4 rounded-2xl border border-dashed border-slate-200 px-4 py-6 text-center text-sm text-slate-500"
        >
            Belum ada siswa yang membuka ujian ini dalam 6 jam terakhir.
        </p>

        <ul v-if="shown.length" class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-3" data-testid="class-map">
            <li
                v-for="student in shown"
                :key="student.id"
                :class="['rounded-xl border border-l-4 border-slate-200 bg-white px-3 py-2.5', STATUS[student.status].edge]"
            >
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-bold text-slate-900">{{ student.name }}</p>
                        <p class="text-[11px] text-slate-500">
                            {{ student.kelas ? `Kelas ${student.kelas}` : 'Tanpa kelas' }}
                            <template v-if="student.remainingSeconds !== null"> · {{ remaining(student.remainingSeconds) }}</template>
                        </p>
                    </div>
                    <span :class="['shrink-0 rounded-full px-2 py-0.5 text-[10px] font-extrabold uppercase', STATUS[student.status].chip]">{{
                        statusLabel(student)
                    }}</span>
                </div>
                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100">
                    <div
                        :class="['h-full rounded-full transition-all', STATUS[student.status].bar]"
                        :style="{ width: `${student.total ? Math.round((student.answered / student.total) * 100) : 0}%` }"
                    ></div>
                </div>
                <div class="mt-1.5 flex flex-wrap items-center gap-x-2.5 gap-y-1 text-[11px] tabular-nums text-slate-600">
                    <span>{{ student.answered }}/{{ student.total }} dijawab</span>
                    <span v-if="student.doubts" class="inline-flex items-center gap-0.5 text-amber-700"
                        ><Flag class="h-3 w-3 fill-current" /> {{ student.doubts }} ragu</span
                    >
                    <span v-if="student.blurCount" class="text-rose-700">{{ student.blurCount }}x pindah tab</span>
                    <span v-if="student.status === 'offline' && student.lastSeenSeconds !== null" class="text-slate-500"
                        >terakhir {{ Math.round(student.lastSeenSeconds / 60) || 1 }} mnt lalu</span
                    >
                    <button
                        v-if="student.status === 'locked'"
                        type="button"
                        class="ml-auto inline-flex items-center gap-1 rounded-lg bg-emerald-600 px-2 py-1 text-[11px] font-bold text-white transition hover:bg-emerald-700 disabled:opacity-60"
                        :disabled="busyIds.includes(`map-${student.id}`)"
                        @click="emit('unlock', student)"
                    >
                        <LockOpen class="h-3 w-3" /> {{ busyIds.includes(`map-${student.id}`) ? 'Membuka...' : 'Buka kunci' }}
                    </button>
                </div>
            </li>
        </ul>
    </section>
</template>
