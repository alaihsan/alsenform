<?php

namespace App\Services;

use App\Models\QuestionBankItem;
use App\Models\QuizForm;
use App\Models\QuizResponse;
use App\Models\User;
use App\Support\HtmlToPlainText;
use App\Support\QuizScoring;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The Remedial & Pengayaan add-on. After an exam the students are split by the KKM, using each
 * student's best attempt: those below it get a remedial exam, the others an enrichment exam. A
 * follow-up exam is an exam of its own (so its grades are kept apart from the original grades),
 * linked to the exam it follows up and taken only by the students chosen for it.
 */
class Remedial
{
    /**
     * The most questions an enrichment exam gets from the bank.
     */
    public const ENRICHMENT_QUESTIONS = 10;

    /**
     * Where the questions of a follow-up exam come from, by kind:
     * - wrong: the questions the chosen students answered wrong;
     * - topic: other bank questions on the topics of those questions;
     * - enrichment: hard (else medium) bank questions on the topics of the exam;
     * - empty: none, the teacher writes them.
     */
    public const SOURCES = [
        QuizForm::FOLLOW_UP_REMEDIAL => ['wrong', 'topic', 'empty'],
        QuizForm::FOLLOW_UP_ENRICHMENT => ['enrichment', 'empty'],
    ];

    public function __construct(
        protected QuizScoring $scoring,
        protected QuestionBank $questionBank,
        protected HtmlToPlainText $htmlToPlainText,
    ) {}

    /**
     * What the panel of the add-on shows on the Responses tab.
     *
     * @return array<string, mixed>
     */
    public function overview(QuizForm $exam): array
    {
        $topicOf = $this->topicsOfQuestions($exam);
        $bankOnTopics = $this->bankOnTopics($exam, array_values(array_unique($topicOf)));

        return [
            'kkm' => $exam->kkm(),
            'students' => $this->students($exam)->all(),
            'followUps' => $exam->followUps()
                ->withCount('participants')
                ->latest('id')
                ->get()
                ->map(fn (QuizForm $followUp): array => [
                    'id' => $followUp->id,
                    'kind' => $followUp->follow_up_kind,
                    'title' => $this->plain((string) $followUp->title),
                    'editUrl' => route('forms.edit', ['quizForm' => $followUp->slug]),
                    'isPublished' => $followUp->published_at !== null,
                    'questionCount' => is_array($followUp->questions) ? count($followUp->questions) : 0,
                    'participants' => $followUp->participants_count,
                    'submitted' => $followUp->submittedStudentCount(),
                    'createdAt' => $followUp->created_at?->diffForHumans(),
                ])
                ->all(),
            // For counting, as students are chosen, the new bank questions a remedial exam can get.
            'topicOf' => (object) $topicOf,
            'bankPerTopic' => (object) $bankOnTopics->countBy('topic')->all(),
            'enrichmentCount' => count($this->enrichmentQuestions($exam, shuffle: false)),
            'createUrl' => route('forms.follow-ups.store', $exam),
            'kkmSettingsUrl' => route('profile.edit'),
        ];
    }

    /**
     * Every student who handed the exam in, once, with their best grade, the questions they
     * answered wrong in that attempt, and their grades in the follow-up exams.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function students(QuizForm $exam): Collection
    {
        $questions = $this->questionsOf($exam);
        $maxScore = $this->scoring->maxPoints($questions);
        $kkm = $exam->kkm();
        $followUpGrades = $this->followUpGrades($exam);
        $assigned = $this->assignedKinds($exam);

        return $this->responsesOf($exam)
            ->groupBy(fn (QuizResponse $response): string => $response->studentKey())
            ->map(function (Collection $attempts, string $key) use ($questions, $maxScore, $kkm, $followUpGrades, $assigned): array {
                // The best attempt counts (the latest of equally good ones).
                $best = $attempts
                    ->map(fn (QuizResponse $response): array => ['response' => $response, 'grade' => $this->gradeOf($questions, $maxScore, $response)])
                    ->sortBy([['grade', 'desc'], fn (array $a, array $b): int => $b['response']->id <=> $a['response']->id])
                    ->first();
                $response = $best['response'];
                $grade = $best['grade'];
                $remedialGrade = $followUpGrades[$key][QuizForm::FOLLOW_UP_REMEDIAL] ?? null;
                $enrichmentGrade = $followUpGrades[$key][QuizForm::FOLLOW_UP_ENRICHMENT] ?? null;

                return [
                    'key' => $key,
                    'userId' => $response->user_id,
                    'hasAccount' => $response->user_id !== null,
                    'name' => $response->user?->name ?: ($response->respondent_name ?: 'Anonim'),
                    'nis' => $response->user?->nis ?: '-',
                    'kelas' => $response->user?->kelas ?: ($response->respondent_class ?: '-'),
                    'grade' => $grade,
                    'attempts' => $attempts->count(),
                    'passed' => $grade >= $kkm,
                    'wrong' => $this->wrongQuestionIds($questions, $response),
                    'remedialGrade' => $remedialGrade,
                    'enrichmentGrade' => $enrichmentGrade,
                    'finalGrade' => $this->finalGrade($grade, $remedialGrade, $kkm),
                    'assignedTo' => $response->user_id !== null ? ($assigned[$response->user_id] ?? []) : [],
                ];
            })
            ->sort(fn (array $a, array $b): int => ($a['kelas'] === '-') <=> ($b['kelas'] === '-')
                ?: strnatcasecmp($a['kelas'], $b['kelas'])
                ?: strnatcasecmp($a['name'], $b['name']))
            ->values();
    }

    /**
     * The grade that counts after remedial: the remedial grade, but not above the KKM (the usual
     * rule), and never below the original grade. Students who passed keep their grade.
     */
    public function finalGrade(float $grade, ?float $remedialGrade, float $kkm): float
    {
        if ($grade >= $kkm || $remedialGrade === null) {
            return $grade;
        }

        return max($grade, min($remedialGrade, $kkm));
    }

    /**
     * Make a remedial or enrichment exam for the chosen students: a draft for the teacher to check
     * and publish. Only the chosen students may take it, when all of them have an account (guests
     * of a public quiz cannot be told apart, so the exam is then open to whoever has the link).
     *
     * @param  list<string>  $studentKeys
     * @return array{exam: QuizForm, restricted: bool}
     */
    public function createFollowUp(QuizForm $exam, User $by, string $kind, array $studentKeys, string $source, ?string $title = null): array
    {
        $students = $this->students($exam)->whereIn('key', $studentKeys)->values();
        abort_if($students->isEmpty(), 422, 'Pilih minimal satu siswa yang sudah mengerjakan ujian ini.');

        $questions = match ($source) {
            'wrong' => $this->wrongQuestions($exam, $students),
            'topic' => $this->sameTopicQuestions($exam, $students),
            'enrichment' => $this->enrichmentQuestions($exam),
            default => [],
        };

        abort_if($questions === [] && $source !== 'empty', 422, match ($source) {
            'wrong' => 'Siswa yang dipilih tidak salah menjawab soal yang dinilai otomatis. Pilih sumber soal lain.',
            'topic' => 'Belum ada soal lain setopik di Bank Soal. Beri label topik pada soal di Bank Soal, atau pilih sumber soal lain.',
            default => 'Belum ada soal sulit setopik di Bank Soal. Beri label topik dan tingkat kesulitan di Bank Soal, atau buat ujian kosong.',
        });

        $examTitle = $this->plain((string) $exam->title) ?: 'Ujian';
        $isRemedial = $kind === QuizForm::FOLLOW_UP_REMEDIAL;
        $restricted = $students->every(fn (array $student): bool => $student['hasAccount']);

        $followUp = DB::transaction(function () use ($exam, $by, $kind, $title, $questions, $examTitle, $isRemedial, $restricted, $students): QuizForm {
            $followUp = QuizForm::query()->create([
                // The exam's owner owns it, so it uses the same bank and KKM; the exam's teachers edit it.
                'user_id' => $exam->user_id,
                'follow_up_of_id' => $exam->id,
                'follow_up_kind' => $kind,
                'title' => filled($title) ? Str::limit(trim((string) $title), 255, '') : ($isRemedial ? 'Remedial: ' : 'Pengayaan: ').$examTitle,
                'description' => $isRemedial
                    ? "Remedial untuk siswa yang belum mencapai KKM {$this->decimal($exam->kkm())} pada ujian {$examTitle}."
                    : "Pengayaan untuk siswa yang sudah tuntas pada ujian {$examTitle}.",
                'slug' => QuizForm::generateComplexSlug(),
                'template' => 'blank',
                'questions' => array_values(array_map(
                    fn (array $question, int $index): array => ['id' => $index + 1, ...Arr::except($question, ['id'])],
                    $questions,
                    array_keys($questions),
                )),
                // The rules of the exam (time, lock, add-ons), without a draw meant for its questions.
                'settings' => Arr::except(is_array($exam->settings) ? $exam->settings : [], ['questionDraw']),
            ]);

            if ($restricted) {
                $followUp->participants()->sync($students->pluck('userId')->all());
            }

            $collaborators = $exam->collaborators()->get()
                ->mapWithKeys(fn (User $collaborator): array => [$collaborator->id => ['role' => $collaborator->pivot->role ?? 'editor']])
                ->all();
            if ($by->id !== $exam->user_id) {
                $collaborators[$by->id] ??= ['role' => 'editor'];
            }
            $followUp->collaborators()->sync($collaborators);

            return $followUp;
        });

        rescue(fn () => $this->questionBank->sync($followUp));

        return ['exam' => $followUp, 'restricted' => $restricted];
    }

    /**
     * The questions of the exam the chosen students answered wrong, in the exam's order.
     *
     * @param  Collection<int, array<string, mixed>>  $students
     * @return list<array<string, mixed>>
     */
    protected function wrongQuestions(QuizForm $exam, Collection $students): array
    {
        $wrong = $students->pluck('wrong')->flatten()->unique()->all();

        return array_values(array_filter($this->questionsOf($exam), fn (array $question): bool => in_array((string) $question['id'], $wrong, true)));
    }

    /**
     * Other bank questions on the topics of the questions the chosen students answered wrong:
     * as many per topic as wrong questions on it, drawn at random.
     *
     * @param  Collection<int, array<string, mixed>>  $students
     * @return list<array<string, mixed>>
     */
    protected function sameTopicQuestions(QuizForm $exam, Collection $students): array
    {
        $topicOf = $this->topicsOfQuestions($exam);
        $needed = collect($this->wrongQuestions($exam, $students))
            ->map(fn (array $question): ?string => $topicOf[(string) $question['id']] ?? null)
            ->filter()
            ->countBy();

        return $this->bankOnTopics($exam, $needed->keys()->all())
            ->groupBy('topic')
            ->flatMap(fn (Collection $items, string|int $topic): Collection => $items->shuffle()->take($needed[$topic] ?? 0))
            ->map(fn (QuestionBankItem $item): array => $item->question)
            ->values()
            ->all();
    }

    /**
     * Hard bank questions on the topics of the exam (medium ones when there are too few hard ones).
     *
     * @return list<array<string, mixed>>
     */
    protected function enrichmentQuestions(QuizForm $exam, bool $shuffle = true): array
    {
        return $this->bankOnTopics($exam, array_values(array_unique($this->topicsOfQuestions($exam))), ['sulit', 'sedang'])
            ->groupBy('difficulty')
            ->sortBy(fn (Collection $items, string $difficulty): int => $difficulty === 'sulit' ? 0 : 1)
            ->flatMap(fn (Collection $items): Collection => $shuffle ? $items->shuffle() : $items)
            ->take(self::ENRICHMENT_QUESTIONS)
            ->map(fn (QuestionBankItem $item): array => $item->question)
            ->values()
            ->all();
    }

    /**
     * Bank questions of the exam's owner on the topics that the exam does not have.
     *
     * @param  list<string>  $topics
     * @param  list<string>  $difficulties
     * @return Collection<int, QuestionBankItem>
     */
    protected function bankOnTopics(QuizForm $exam, array $topics, array $difficulties = []): Collection
    {
        $exam->loadMissing('user');

        if ($topics === [] || $exam->user === null) {
            return collect();
        }

        return rescue(
            fn (): Collection => $this->questionBank->onTopics($exam->user, $topics, $this->questionBank->fingerprintsOf($exam), $difficulties),
            collect(),
            report: false,
        );
    }

    /**
     * The topic label (from the question bank) of each question of the exam that has one.
     *
     * @return array<string, string>
     */
    protected function topicsOfQuestions(QuizForm $exam): array
    {
        $labels = rescue(fn (): array => $this->questionBank->labelsForExam($exam), [], report: false);

        return collect($labels)
            ->map(fn (array $labels): ?string => filled($labels['topic'] ?? null) ? (string) $labels['topic'] : null)
            ->filter()
            ->mapWithKeys(fn (string $topic, string|int $questionId): array => [(string) $questionId => $topic])
            ->all();
    }

    /**
     * The best grade of each student in the remedial and in the enrichment exams of the exam.
     *
     * @return array<string, array<string, float>>
     */
    protected function followUpGrades(QuizForm $exam): array
    {
        $grades = [];

        foreach ($exam->followUps()->get(['id', 'follow_up_kind', 'questions']) as $followUp) {
            $questions = $this->questionsOf($followUp);
            $maxScore = $this->scoring->maxPoints($questions);

            foreach ($this->responsesOf($followUp) as $response) {
                $key = $response->studentKey();
                $grade = $this->gradeOf($questions, $maxScore, $response);
                $grades[$key][$followUp->follow_up_kind] = max($grade, $grades[$key][$followUp->follow_up_kind] ?? 0.0);
            }
        }

        return $grades;
    }

    /**
     * The kinds of follow-up exams each student (by account) was chosen for.
     *
     * @return array<int, list<string>>
     */
    protected function assignedKinds(QuizForm $exam): array
    {
        return DB::table('quiz_form_participants')
            ->join('quiz_forms', 'quiz_forms.id', '=', 'quiz_form_participants.quiz_form_id')
            ->where('quiz_forms.follow_up_of_id', $exam->id)
            ->whereNull('quiz_forms.deleted_at')
            ->get(['quiz_form_participants.user_id', 'quiz_forms.follow_up_kind'])
            ->groupBy('user_id')
            ->map(fn (Collection $rows): array => $rows->pluck('follow_up_kind')->unique()->values()->all())
            ->all();
    }

    /**
     * @return Collection<int, QuizResponse>
     */
    protected function responsesOf(QuizForm $exam): Collection
    {
        return $exam->responses()
            ->with('user:id,name,nis,kelas')
            ->orderBy('id')
            ->get(['id', 'quiz_form_id', 'user_id', 'respondent_identifier', 'respondent_name', 'respondent_class', 'answers', 'question_ids', 'score', 'manual_scores']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function questionsOf(QuizForm $exam): array
    {
        return array_values(array_filter(
            is_array($exam->questions) ? $exam->questions : [],
            fn (mixed $question): bool => is_array($question) && isset($question['id']),
        ));
    }

    /**
     * Scored against the current answer keys, out of the questions the student was given.
     *
     * @param  list<array<string, mixed>>  $questions
     */
    protected function gradeOf(array $questions, int $maxScore, QuizResponse $response): float
    {
        if (! is_array($response->answers)) {
            return $this->scoring->grade((int) ($response->score ?? 0), $maxScore);
        }

        // With what the teacher graded by hand (essays, files).
        return $this->scoring->result($questions, $response->answers, $response->question_ids, $response->manual_scores)['grade'];
    }

    /**
     * The automatically scored questions a student was given and answered wrong (or left empty).
     *
     * @param  list<array<string, mixed>>  $questions
     * @return list<string>
     */
    protected function wrongQuestionIds(array $questions, QuizResponse $response): array
    {
        $answers = is_array($response->answers) ? $response->answers : [];
        $wrong = [];

        foreach ($this->scoring->questionsGiven($questions, $response->question_ids) as $question) {
            if ($this->scoring->isCorrect($question, $answers[$question['id']] ?? null) === false) {
                $wrong[] = (string) $question['id'];
            }
        }

        return $wrong;
    }

    protected function plain(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', $this->htmlToPlainText->convert($value)) ?? '');
    }

    protected function decimal(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
    }
}
