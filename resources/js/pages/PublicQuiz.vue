<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Star } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

type Question = {
    id: number;
    title: string;
    description: string;
    type: string;
    options: string[];
    required: boolean;
    media?: { type: 'image' | 'video'; url: string }[];
    points?: number;
};

const props = defineProps<{
    quizForm: {
        title: string;
        description: string;
        questions: Question[];
        settings: {
            isQuiz?: boolean;
            collectEmail: boolean;
            showProgress: boolean;
            shuffleQuestions: boolean;
            confirmationMessage?: string;
            showSubmitAnotherResponse?: boolean;
            questionFont?: string;
            answerFont?: string;
            themeColorClass?: string;
            backgroundColorClass?: string;
            backgroundPatternClass?: string;
        };
        submitUrl: string;
    };
}>();

const answers = ref<Record<number, any>>({});
const email = ref('');
const displayQuestions = ref<Question[]>([]);
const isSubmitted = ref(false);
const isSubmitting = ref(false);

onMounted(() => {
    let qs = [...props.quizForm.questions];
    if (props.quizForm.settings.shuffleQuestions) {
        for (let i = qs.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [qs[i], qs[j]] = [qs[j], qs[i]];
        }
    }
    displayQuestions.value = qs;
});

const getYoutubeEmbedUrl = (url: string): string | null => {
    if (!url) {
        return null;
    }
    const regExp = /^.*(youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=|\&v=)([^#\&\?]*).*/;
    const match = url.match(regExp);
    return match && match[2].length === 11 ? `https://www.youtube.com/embed/${match[2]}` : null;
};

const progress = computed(() => {
    if (!props.quizForm.questions.length) {
        return 0;
    }
    const answeredCount = props.quizForm.questions.filter((q) => {
        const ans = answers.value[q.id];
        if (Array.isArray(ans)) {
            return ans.length > 0;
        }
        return ans !== undefined && ans !== '';
    }).length;
    return Math.round((answeredCount / props.quizForm.questions.length) * 100);
});

const toggleCheckbox = (questionId: number, option: string) => {
    if (!Array.isArray(answers.value[questionId])) {
        answers.value[questionId] = [];
    }
    const idx = answers.value[questionId].indexOf(option);
    if (idx >= 0) {
        answers.value[questionId].splice(idx, 1);
    } else {
        answers.value[questionId].push(option);
    }
};

const isCheckboxChecked = (questionId: number, option: string) => {
    return Array.isArray(answers.value[questionId]) && answers.value[questionId].includes(option);
};

const submitResponse = () => {
    if (isSubmitting.value) {
        return;
    }

    isSubmitting.value = true;
    router.post(
        props.quizForm.submitUrl,
        {
            email: email.value || null,
            answers: answers.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                isSubmitted.value = true;
                window.scrollTo({ top: 0, behavior: 'smooth' });
            },
            onFinish: () => {
                isSubmitting.value = false;
            },
        },
    );
};

const submitAnotherResponse = () => {
    answers.value = {};
    email.value = '';
    isSubmitted.value = false;
    window.scrollTo({ top: 0, behavior: 'smooth' });
};
</script>

<template>
    <Head :title="quizForm.title" />

    <!-- Progress bar at the top of the page if enabled -->
    <div v-if="quizForm.settings.showProgress" class="fixed left-0 right-0 top-0 z-50 h-2 bg-slate-100">
        <div
            :class="['h-full transition-all duration-300', quizForm.settings.themeColorClass ?? 'bg-indigo-600']"
            :style="{ width: `${progress}%` }"
        ></div>
    </div>

    <main
        :class="[
            'min-h-screen px-4 py-8 text-slate-900 transition-all duration-300 sm:px-6',
            quizForm.settings.backgroundColorClass ?? 'bg-violet-50',
            quizForm.settings.backgroundPatternClass ?? 'pattern-none',
        ]"
    >
        <section v-if="isSubmitted" class="mx-auto max-w-3xl rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div :class="['h-3 rounded-t-3xl transition-all duration-300', quizForm.settings.themeColorClass ?? 'bg-indigo-600']"></div>
            <div class="p-6 sm:p-8">
                <h1 class="text-3xl font-semibold" :style="{ fontFamily: quizForm.settings.questionFont ?? 'inherit' }">
                    {{ quizForm.settings.confirmationMessage ?? 'Your response has been recorded' }}
                </h1>
                <button
                    v-if="quizForm.settings.showSubmitAnotherResponse ?? true"
                    type="button"
                    class="mt-6 rounded-2xl border border-slate-300 px-5 py-3 font-bold text-slate-700 transition hover:bg-slate-50"
                    @click="submitAnotherResponse"
                >
                    Submit another response
                </button>
            </div>
        </section>

        <template v-else>
            <section class="mx-auto max-w-3xl rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div :class="['h-3 rounded-t-3xl transition-all duration-300', quizForm.settings.themeColorClass ?? 'bg-indigo-600']"></div>
                <div class="p-6 sm:p-8">
                    <h1 class="text-3xl font-semibold" :style="{ fontFamily: quizForm.settings.questionFont ?? 'inherit' }">{{ quizForm.title }}</h1>
                    <p v-if="quizForm.description" class="mt-3 text-slate-500" :style="{ fontFamily: quizForm.settings.answerFont ?? 'inherit' }">
                        {{ quizForm.description }}
                    </p>
                </div>
            </section>

            <!-- Email Collection Card -->
            <section v-if="quizForm.settings.collectEmail" class="mx-auto mt-4 max-w-3xl rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <label class="block">
                    <span class="text-base font-semibold text-slate-900">Email <span class="text-red-500">*</span></span>
                    <input
                        v-model="email"
                        type="email"
                        required
                        placeholder="Masukkan alamat email Anda"
                        class="mt-3 w-full rounded-2xl border border-slate-300 px-4 py-3 text-slate-800 outline-none focus:border-indigo-500"
                    />
                </label>
            </section>

            <section class="mx-auto mt-5 max-w-3xl space-y-4">
                <article v-for="question in displayQuestions" :key="question.id" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2
                        class="flex flex-wrap items-center text-lg font-semibold"
                        :style="{ fontFamily: quizForm.settings.questionFont ?? 'inherit' }"
                    >
                        <span>{{ question.title }}</span>
                        <span v-if="question.required" class="ml-1 text-red-500">*</span>
                        <span
                            v-if="quizForm.settings.isQuiz !== false && question.points"
                            class="ml-2 rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-bold text-indigo-700"
                        >
                            {{ question.points }} Poin
                        </span>
                    </h2>
                    <p
                        v-if="question.description"
                        class="mt-2 text-sm text-slate-500"
                        :style="{ fontFamily: quizForm.settings.answerFont ?? 'inherit' }"
                    >
                        {{ question.description }}
                    </p>

                    <!-- Media elements rendering -->
                    <div v-if="question.media && question.media.length" class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div v-for="(media, idx) in question.media" :key="idx" class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                            <img v-if="media.type === 'image' && media.url" :src="media.url" class="max-h-72 w-full object-contain p-2" />
                            <iframe
                                v-else-if="media.type === 'video' && media.url && getYoutubeEmbedUrl(media.url)"
                                :src="getYoutubeEmbedUrl(media.url)"
                                class="aspect-video w-full"
                                frameborder="0"
                                allowfullscreen
                            ></iframe>
                            <video
                                v-else-if="media.type === 'video' && media.url"
                                :src="media.url"
                                controls
                                class="max-h-72 w-full bg-slate-950"
                            ></video>
                        </div>
                    </div>

                    <input
                        v-if="question.type === 'Short answer'"
                        v-model="answers[question.id]"
                        type="text"
                        class="mt-5 w-full rounded-2xl border border-slate-300 bg-slate-50/50 px-4 py-3 text-slate-800 outline-none focus:border-indigo-500"
                        placeholder="Jawaban singkat Anda"
                    />
                    <textarea
                        v-else-if="question.type === 'Paragraph'"
                        v-model="answers[question.id]"
                        class="mt-5 min-h-28 w-full rounded-2xl border border-slate-300 bg-slate-50/50 px-4 py-3 text-slate-800 outline-none focus:border-indigo-500"
                        placeholder="Jawaban panjang Anda"
                    ></textarea>
                    <select
                        v-else-if="question.type === 'Drop-down'"
                        v-model="answers[question.id]"
                        class="mt-5 w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-slate-800 outline-none focus:border-indigo-500"
                    >
                        <option value="">Pilih jawaban</option>
                        <option v-for="option in question.options" :key="option" :value="option">{{ option }}</option>
                    </select>
                    <div
                        v-else-if="question.type === 'Linear scale'"
                        class="mt-5 flex items-center justify-between gap-2 rounded-2xl bg-slate-50 p-4"
                    >
                        <button
                            v-for="option in question.options"
                            :key="option"
                            type="button"
                            :class="[
                                'flex h-11 w-11 items-center justify-center rounded-full border text-sm font-bold transition',
                                answers[question.id] === option
                                    ? 'border-indigo-600 bg-indigo-600 text-white shadow-sm'
                                    : 'border-slate-300 bg-white text-slate-700 hover:border-indigo-400',
                            ]"
                            @click="answers[question.id] = option"
                        >
                            {{ option }}
                        </button>
                    </div>
                    <div v-else-if="question.type === 'Rating'" class="mt-5 flex gap-3 rounded-2xl bg-slate-50 p-4">
                        <button
                            v-for="option in question.options"
                            :key="option"
                            type="button"
                            :class="[
                                'transition',
                                Number(answers[question.id]) >= Number(option) ? 'text-amber-400' : 'text-slate-300 hover:text-amber-300',
                            ]"
                            @click="answers[question.id] = option"
                        >
                            <Star class="h-10 w-10 fill-current" />
                        </button>
                    </div>
                    <div v-else-if="question.options?.length" class="mt-5 space-y-3">
                        <label
                            v-for="option in question.options"
                            :key="option"
                            class="flex cursor-pointer items-center gap-3 rounded-2xl border border-slate-200 px-4 py-3 transition hover:bg-slate-50"
                            :style="{ fontFamily: quizForm.settings.answerFont ?? 'inherit' }"
                        >
                            <input
                                :type="question.type === 'Checkboxes' ? 'checkbox' : 'radio'"
                                :name="`question-${question.id}`"
                                :checked="question.type === 'Checkboxes' ? isCheckboxChecked(question.id, option) : answers[question.id] === option"
                                class="h-5 w-5 accent-indigo-600"
                                @change="question.type === 'Checkboxes' ? toggleCheckbox(question.id, option) : (answers[question.id] = option)"
                            />
                            <span class="text-slate-800">{{ option }}</span>
                        </label>
                    </div>
                    <input
                        v-else-if="question.type === 'Date'"
                        v-model="answers[question.id]"
                        type="date"
                        class="mt-5 rounded-2xl border border-slate-300 px-4 py-3 text-slate-800 outline-none focus:border-indigo-500"
                    />
                    <input
                        v-else-if="question.type === 'Time'"
                        v-model="answers[question.id]"
                        type="time"
                        class="mt-5 rounded-2xl border border-slate-300 px-4 py-3 text-slate-800 outline-none focus:border-indigo-500"
                    />
                    <div v-else class="mt-5 rounded-2xl border border-dashed border-slate-300 p-4 text-sm text-slate-500">Area jawaban</div>
                </article>

                <button
                    type="button"
                    :disabled="isSubmitting"
                    :class="[
                        'rounded-2xl px-6 py-3 font-bold text-white shadow-sm transition-all duration-300',
                        quizForm.settings.themeColorClass ?? 'bg-indigo-600',
                        'hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-60',
                    ]"
                    @click="submitResponse"
                >
                    {{ isSubmitting ? 'Submitting...' : 'Submit' }}
                </button>
            </section>
        </template>
    </main>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Fira+Code&family=Inter:wght@400;600;700&family=Lora:ital,wght@0,400;0,700;1,400&family=Merriweather&family=Montserrat:wght@400;600;700&family=Outfit:wght@400;600;700&family=Playfair+Display:ital,wght@0,400;0,700;1,400&family=Plus+Jakarta+Sans:wght@400;600;700&family=Roboto:wght@400;500;700&display=swap');

.pattern-none {
    background-image: none;
}
.pattern-dots {
    background-image: radial-gradient(rgba(99, 102, 241, 0.15) 1.5px, transparent 1.5px);
    background-size: 20px 20px;
}
.pattern-grid {
    background-image:
        linear-gradient(rgba(99, 102, 241, 0.08) 1px, transparent 1px), linear-gradient(90deg, rgba(99, 102, 241, 0.08) 1px, transparent 1px);
    background-size: 20px 20px;
}
.pattern-diagonal {
    background-image: repeating-linear-gradient(45deg, rgba(99, 102, 241, 0.05) 0px, rgba(99, 102, 241, 0.05) 2px, transparent 2px, transparent 10px);
}
.pattern-waves {
    background-image:
        radial-gradient(
            circle at 100% 150%,
            transparent 24%,
            rgba(99, 102, 241, 0.06) 24%,
            rgba(99, 102, 241, 0.06) 28%,
            transparent 28%,
            transparent
        ),
        radial-gradient(circle at 0% 150%, transparent 24%, rgba(99, 102, 241, 0.06) 24%, rgba(99, 102, 241, 0.06) 28%, transparent 28%, transparent);
    background-size: 20px 20px;
}
.pattern-zigzag {
    background-image:
        linear-gradient(135deg, rgba(99, 102, 241, 0.05) 25%, transparent 25%),
        linear-gradient(225deg, rgba(99, 102, 241, 0.05) 25%, transparent 25%), linear-gradient(45deg, rgba(99, 102, 241, 0.05) 25%, transparent 25%),
        linear-gradient(315deg, rgba(99, 102, 241, 0.05) 25%, transparent 25%);
    background-position:
        10px 0,
        10px 0,
        0 0,
        0 0;
    background-size: 20px 20px;
    background-repeat: repeat;
}
.pattern-hexagons {
    background-image:
        radial-gradient(circle at 50% 50%, rgba(99, 102, 241, 0.06) 10%, transparent 10%),
        radial-gradient(circle at 0% 0%, rgba(99, 102, 241, 0.06) 10%, transparent 10%),
        radial-gradient(circle at 100% 0%, rgba(99, 102, 241, 0.06) 10%, transparent 10%),
        radial-gradient(circle at 100% 100%, rgba(99, 102, 241, 0.06) 10%, transparent 10%),
        radial-gradient(circle at 0% 100%, rgba(99, 102, 241, 0.06) 10%, transparent 10%);
    background-size: 30px 30px;
}
.pattern-blueprint {
    background-image:
        linear-gradient(rgba(255, 255, 255, 0.2) 1px, transparent 1px), linear-gradient(90deg, rgba(255, 255, 255, 0.2) 1px, transparent 1px);
    background-size: 20px 20px;
}
.pattern-bubbles {
    background-image:
        radial-gradient(circle, rgba(99, 102, 241, 0.04) 20%, transparent 20%), radial-gradient(circle, rgba(99, 102, 241, 0.05) 15%, transparent 15%);
    background-size: 40px 40px;
    background-position:
        0 0,
        20px 20px;
}
.pattern-triangles {
    background-image:
        linear-gradient(
            30deg,
            rgba(99, 102, 241, 0.04) 12%,
            transparent 12.5%,
            transparent 87%,
            rgba(99, 102, 241, 0.04) 87.5%,
            rgba(99, 102, 241, 0.04)
        ),
        linear-gradient(
            150deg,
            rgba(99, 102, 241, 0.04) 12%,
            transparent 12.5%,
            transparent 87%,
            rgba(99, 102, 241, 0.04) 87.5%,
            rgba(99, 102, 241, 0.04)
        ),
        linear-gradient(
            270deg,
            rgba(99, 102, 241, 0.04) 25%,
            transparent 25.5%,
            transparent 74%,
            rgba(99, 102, 241, 0.04) 74.5%,
            rgba(99, 102, 241, 0.04)
        );
    background-size: 30px 52px;
}
</style>
