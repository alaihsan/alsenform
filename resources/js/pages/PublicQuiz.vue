<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

type Question = {
    id: number;
    title: string;
    description: string;
    type: string;
    options: string[];
    required: boolean;
};

defineProps<{
    quizForm: {
        title: string;
        description: string;
        questions: Question[];
        settings: {
            collectEmail: boolean;
            showProgress: boolean;
            shuffleQuestions: boolean;
        };
    };
}>();
</script>

<template>
    <Head :title="quizForm.title" />

    <main class="min-h-screen bg-violet-50 px-4 py-8 text-slate-900 sm:px-6">
        <section class="mx-auto max-w-3xl rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="h-3 rounded-t-3xl bg-indigo-600"></div>
            <div class="p-6 sm:p-8">
                <h1 class="text-3xl font-semibold">{{ quizForm.title }}</h1>
                <p v-if="quizForm.description" class="mt-3 text-slate-500">{{ quizForm.description }}</p>
            </div>
        </section>

        <section class="mx-auto mt-5 max-w-3xl space-y-4">
            <article v-for="question in quizForm.questions" :key="question.id" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold">
                    {{ question.title }}
                    <span v-if="question.required" class="text-red-500">*</span>
                </h2>
                <p v-if="question.description" class="mt-2 text-sm text-slate-500">{{ question.description }}</p>

                <input
                    v-if="question.type === 'Short answer'"
                    type="text"
                    class="mt-5 w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-indigo-500"
                    placeholder="Jawaban singkat"
                />
                <textarea
                    v-else-if="question.type === 'Paragraph'"
                    class="mt-5 min-h-28 w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-indigo-500"
                    placeholder="Jawaban panjang"
                ></textarea>
                <select
                    v-else-if="question.type === 'Drop-down'"
                    class="mt-5 w-full rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-indigo-500"
                >
                    <option value="">Pilih jawaban</option>
                    <option v-for="option in question.options" :key="option" :value="option">{{ option }}</option>
                </select>
                <div v-else-if="question.options?.length" class="mt-5 space-y-3">
                    <label
                        v-for="option in question.options"
                        :key="option"
                        class="flex items-center gap-3 rounded-2xl border border-slate-200 px-4 py-3"
                    >
                        <input
                            :type="question.type === 'Checkboxes' ? 'checkbox' : 'radio'"
                            :name="`question-${question.id}`"
                            class="accent-indigo-600"
                        />
                        <span>{{ option }}</span>
                    </label>
                </div>
                <input
                    v-else-if="question.type === 'Date'"
                    type="date"
                    class="mt-5 rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-indigo-500"
                />
                <input
                    v-else-if="question.type === 'Time'"
                    type="time"
                    class="mt-5 rounded-2xl border border-slate-300 px-4 py-3 outline-none focus:border-indigo-500"
                />
                <div v-else class="mt-5 rounded-2xl border border-dashed border-slate-300 p-4 text-sm text-slate-500">Area jawaban</div>
            </article>

            <button type="button" class="rounded-2xl bg-indigo-600 px-6 py-3 font-bold text-white shadow-sm hover:bg-indigo-700">Submit</button>
        </section>
    </main>
</template>
