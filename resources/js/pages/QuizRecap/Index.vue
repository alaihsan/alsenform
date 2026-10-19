<script setup lang="ts">
import AlsenformLayout from '@/layouts/AlsenformLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Download, Sheet } from 'lucide-vue-next';
import { computed } from 'vue';

/*
 * Rekap Kuis add-on: the teacher's grades of all their quizzes in one table, a row per student
 * and a column per quiz (oldest first), by class, and as an Excel workbook.
 */
interface Grade {
    grade: number;
    /** Essays or files of this attempt still wait for a grade. */
    provisional: boolean;
}

const props = defineProps<{
    quizzes: { id: number; title: string }[];
    students: { key: string; name: string; kelas: string; grades: (Grade | null)[] }[];
    classes: string[];
    kelas: string | null;
    exportUrl: string;
}>();

const formatGrade = (grade: number) => grade.toLocaleString('id-ID', { maximumFractionDigits: 2 });

const hasProvisional = computed(() => props.students.some((student) => student.grades.some((grade) => grade?.provisional)));

const chooseClass = (kelas: string) => {
    router.get(route('quiz-recap.index'), kelas ? { kelas } : {}, { preserveScroll: true, replace: true });
};
</script>

<template>
    <AlsenformLayout>
        <Head title="Rekap Kuis" />

        <main class="mx-auto max-w-7xl px-4 py-6 sm:px-6 sm:py-8">
            <Link
                :href="route('dashboard')"
                class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 transition hover:text-indigo-700"
            >
                <ArrowLeft class="h-4 w-4" />
                Kembali ke Beranda
            </Link>

            <div class="mt-3 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div class="min-w-0">
                    <p class="flex items-center gap-1.5 text-xs font-black uppercase tracking-[0.18em] text-indigo-500">
                        <Sheet class="h-3.5 w-3.5" />
                        Add-on
                    </p>
                    <h1 class="mt-1 text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">Rekap Kuis</h1>
                    <p class="mt-1 text-sm text-slate-500">
                        Nilai terbaik tiap siswa di setiap kuis Anda, dari kuis paling lama. Kosong berarti belum mengerjakan.
                    </p>
                </div>
                <div class="flex flex-wrap items-end gap-2">
                    <label class="min-w-[11rem]">
                        <span class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-slate-500">Kelas</span>
                        <select
                            id="recap-class"
                            :value="kelas ?? ''"
                            class="h-9 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700 shadow-sm focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                            @change="chooseClass(($event.target as HTMLSelectElement).value)"
                        >
                            <option value="">Semua kelas</option>
                            <option v-for="option in classes" :key="option" :value="option">{{ option }}</option>
                        </select>
                    </label>
                    <a
                        :href="exportUrl"
                        class="inline-flex h-9 items-center gap-2 rounded-xl bg-emerald-600 px-3.5 text-xs font-semibold text-white shadow-sm transition hover:bg-emerald-700"
                    >
                        <Download class="h-3.5 w-3.5" />
                        Unduh Excel
                    </a>
                </div>
            </div>

            <section class="mt-6 overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
                <div v-if="!quizzes.length" class="px-6 py-14 text-center">
                    <p class="font-bold text-slate-800">Belum ada kuis yang dinilai</p>
                    <p class="mt-1 text-sm text-slate-500">Kuis Anda muncul di sini setelah diterbitkan atau dikerjakan siswa.</p>
                </div>
                <div v-else-if="!students.length" class="px-6 py-14 text-center">
                    <p class="font-bold text-slate-800">
                        {{ kelas ? `Belum ada siswa di kelas ${kelas}` : 'Belum ada siswa yang mengerjakan kuis Anda' }}
                    </p>
                    <p class="mt-1 text-sm text-slate-500">Nilai muncul di sini begitu siswa mengirim jawaban.</p>
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-max text-sm">
                        <thead class="bg-slate-50 text-left text-[11px] font-bold uppercase tracking-wider text-slate-500">
                            <tr>
                                <th scope="col" class="sticky left-0 z-10 w-12 min-w-[3rem] bg-slate-50 px-3 py-3 text-center">No</th>
                                <th scope="col" class="sticky left-12 z-10 min-w-[11rem] bg-slate-50 px-4 py-3 shadow-[inset_-1px_0_0_#e2e8f0]">
                                    Nama
                                </th>
                                <th
                                    v-for="quiz in quizzes"
                                    :key="quiz.id"
                                    scope="col"
                                    class="max-w-[10rem] px-4 py-3 text-center normal-case tracking-normal"
                                >
                                    <span class="line-clamp-2 text-xs font-bold text-slate-600" :title="quiz.title">{{ quiz.title }}</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="(student, index) in students" :key="student.key">
                                <td class="sticky left-0 z-10 w-12 min-w-[3rem] bg-white px-3 py-2.5 text-center tabular-nums text-slate-500">
                                    {{ index + 1 }}
                                </td>
                                <td class="sticky left-12 z-10 min-w-[11rem] bg-white px-4 py-2.5 shadow-[inset_-1px_0_0_#e2e8f0]">
                                    <span class="block font-semibold text-slate-900">{{ student.name }}</span>
                                    <span class="block text-xs text-slate-500">{{
                                        student.kelas === '-' ? 'Tanpa kelas' : `Kelas ${student.kelas}`
                                    }}</span>
                                </td>
                                <td v-for="(grade, column) in student.grades" :key="quizzes[column].id" class="px-4 py-2.5 text-center tabular-nums">
                                    <span v-if="grade" class="font-semibold text-slate-800">
                                        {{ formatGrade(grade.grade)
                                        }}<sup
                                            v-if="grade.provisional"
                                            class="ml-0.5 text-amber-600"
                                            title="Sementara: masih ada esai atau berkas yang belum dinilai"
                                            >*</sup
                                        >
                                    </span>
                                    <span v-else class="text-slate-300" aria-label="Belum mengerjakan">–</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <p v-if="students.length" class="mt-3 text-xs text-slate-500">
                {{ students.length }} siswa, {{ quizzes.length }} kuis.
                <template v-if="hasProvisional"> Nilai bertanda * masih sementara karena ada esai atau berkas yang belum dinilai.</template>
            </p>
        </main>
    </AlsenformLayout>
</template>
