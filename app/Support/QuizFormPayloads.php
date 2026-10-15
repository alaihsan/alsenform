<?php

namespace App\Support;

use App\Models\Cohort;
use App\Models\QuizFolder;
use App\Models\QuizForm;
use App\Models\QuizResponse;
use App\Models\User;
use App\Services\Addons;
use App\Services\ItemAnalysis;
use App\Services\Misconceptions;
use App\Services\QuestionBank;
use App\Services\Remedial;
use Illuminate\Support\Collection;

class QuizFormPayloads
{
    public function __construct(
        protected MediaUrl $mediaUrl = new MediaUrl,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function editor(QuizForm $quizForm, bool $includeResponses = true): array
    {
        $quizForm->loadMissing(['user:id,name,email', 'collaborators:id,name,email']);
        $collaboratorIds = $quizForm->collaborators->pluck('id')->all();
        $ownerId = $quizForm->user_id;

        // Available teachers (role = guru or is_admin, strictly excluding owner and existing collaborators)
        $availableTeachers = User::query()
            ->where(function ($q): void {
                $q->where('role', 'guru')
                    ->orWhere('role', 'admin')
                    ->orWhere('is_admin', true);
            })
            ->whereNotIn('id', array_merge([$ownerId], $collaboratorIds))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $currentUserId = auth()->id();

        return [
            'id' => $quizForm->id,
            'title' => $quizForm->title,
            'description' => $quizForm->description,
            'slug' => $quizForm->slug,
            'questions' => $this->mediaUrl->normalizeQuestions($quizForm->questions),
            'settings' => $quizForm->settings,
            'version' => $quizForm->contentVersion(),
            // Labels of each question (kept in the question bank) and suggestions while labelling.
            'questionLabels' => (object) rescue(fn (): array => app(QuestionBank::class)->labelsForExam($quizForm), [], report: false),
            'labelSuggestions' => rescue(fn (): array => app(QuestionBank::class)->labelSuggestions($quizForm->user), ['subjects' => [], 'grades' => [], 'topics' => []], report: false),
            'labelsUrl' => route('forms.questions.labels', ['quizForm' => $quizForm->id, 'questionId' => '__question__']),
            // The add-ons of the teacher looking at the exam, and whether each is on for this exam.
            'addons' => auth()->user() ? app(Addons::class)->catalog(auth()->user(), $quizForm) : [],
            'addonUrls' => [
                'gallery' => route('addons.index'),
                'install' => route('addons.install', ['addon' => '__addon__']),
                'uninstall' => route('addons.uninstall', ['addon' => '__addon__']),
            ],
            'canInstallAddonsForEveryone' => (bool) auth()->user()?->isAdmin(),
            'examTokenUrl' => route('forms.exam-token', $quizForm),
            'classMapUrl' => route('forms.class-map', $quizForm),
            // A remedial or enrichment exam: the exam it follows up and the students chosen for it.
            'followUp' => $quizForm->follow_up_kind !== null ? $this->followUp($quizForm) : null,
            'responses' => $includeResponses ? $this->responses($quizForm) : null,
            'updateUrl' => route('forms.update', $quizForm),
            'publicUrl' => route('forms.public', ['quizForm' => $quizForm->slug]),
            'isPublished' => ! is_null($quizForm->published_at),
            'cohortIds' => $quizForm->cohorts()->pluck('cohorts.id')->all(),
            'availableCohorts' => Cohort::orderBy('name')->get(['id', 'name', 'code'])->all(),
            'isOwner' => $currentUserId === $quizForm->user_id || (bool) auth()->user()?->isAdmin(),
            'owner' => $quizForm->user ? [
                'id' => $quizForm->user->id,
                'name' => $quizForm->user->name,
                'email' => $quizForm->user->email,
            ] : null,
            'collaborators' => $quizForm->collaborators->map(fn (User $u): array => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
            ])->values()->all(),
            'availableTeachers' => $availableTeachers->map(fn (User $u): array => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
            ])->values()->all(),
            'inviteCollaboratorUrl' => route('forms.collaborators.store', $quizForm),
            'exportResponsesUrl' => route('forms.responses.export', $quizForm),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function recentForm(QuizForm $quizForm, ?User $currentUser = null): array
    {
        $user = $currentUser ?? auth()->user();
        $isCollaborator = $user && $quizForm->user_id !== $user->id;

        $questions = QuizItems::questions($quizForm->questions);
        $previewQuestions = [];
        if (! empty($questions) && is_array($questions)) {
            foreach (array_slice($questions, 0, 2) as $q) {
                if (! is_array($q)) {
                    continue;
                }

                $rawTitle = (string) ($q['title'] ?? '');
                $cleanTitle = trim((string) preg_replace('/\s+/', ' ', strip_tags(html_entity_decode($rawTitle, ENT_QUOTES | ENT_HTML5, 'UTF-8'))));

                $options = [];
                if (! empty($q['options']) && is_array($q['options'])) {
                    foreach (array_slice($q['options'], 0, 3) as $opt) {
                        $cleanOpt = trim((string) preg_replace('/\s+/', ' ', strip_tags(html_entity_decode((string) $opt, ENT_QUOTES | ENT_HTML5, 'UTF-8'))));
                        if ($cleanOpt !== '') {
                            $options[] = mb_substr($cleanOpt, 0, 45);
                        }
                    }
                }

                $previewQuestions[] = [
                    'id' => $q['id'] ?? null,
                    'title' => mb_substr($cleanTitle, 0, 120),
                    'type' => (string) ($q['type'] ?? 'Multiple choice'),
                    'options' => $options,
                ];
            }
        }

        $themeColor = $quizForm->settings['themeColorClass'] ?? null;
        $bgColor = $quizForm->settings['backgroundColorClass'] ?? null;

        $tone = $bgColor ?: match ($quizForm->template) {
            'party-invite' => 'bg-fuchsia-50',
            'work-request' => 'bg-cyan-50',
            'rsvp' => 'bg-orange-50',
            't-shirt-sign-up' => 'bg-violet-50',
            default => 'bg-slate-100',
        };

        $stripe = $themeColor ?: match ($quizForm->template) {
            'party-invite' => 'bg-fuchsia-300',
            'work-request' => 'bg-emerald-300',
            'rsvp' => 'bg-orange-300',
            't-shirt-sign-up' => 'bg-violet-500',
            default => 'bg-indigo-500',
        };

        return [
            'id' => $quizForm->id,
            'title' => $quizForm->title,
            'description' => $quizForm->description,
            'isCollaborator' => $isCollaborator,
            'ownerName' => $quizForm->user?->name ?? 'Guru',
            'folderId' => $quizForm->quiz_folder_id,
            'folder' => $quizForm->quizFolder?->name ?? $quizForm->folder,
            'editUrl' => route('forms.edit', ['quizForm' => $quizForm->slug]),
            'publicUrl' => route('forms.public', ['quizForm' => $quizForm->slug]),
            'duplicateUrl' => route('forms.duplicate', $quizForm),
            'moveFolderUrl' => route('forms.folder', $quizForm),
            'deleteUrl' => route('forms.destroy', $quizForm),
            'restoreUrl' => route('forms.restore', $quizForm),
            'forceDeleteUrl' => route('forms.force-delete', $quizForm),
            'updatedLabel' => 'Opened '.$quizForm->updated_at->format('j M Y'),
            'updatedAt' => $quizForm->updated_at->toISOString(),
            'isPublished' => ! is_null($quizForm->published_at),
            'isTrashed' => ! is_null($quizForm->deleted_at),
            'tone' => $tone,
            'stripe' => $stripe,
            'themeColorClass' => $themeColor,
            'backgroundColorClass' => $bgColor,
            'design' => $quizForm->cardDesign(),
            'questionsCount' => is_array($questions) ? count($questions) : 0,
            'previewQuestions' => $previewQuestions,
            'firstQuestion' => $previewQuestions[0] ?? null,
            'accent' => match ($quizForm->template) {
                'rsvp' => 'bg-orange-500',
                'work-request' => 'bg-emerald-500',
                default => 'bg-violet-600',
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function folder(QuizFolder $folder): array
    {
        return [
            'id' => $folder->id,
            'name' => $folder->name,
            'formsCount' => $folder->forms_count,
            'updateUrl' => route('folders.update', $folder),
            'deleteUrl' => route('folders.destroy', $folder),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function responses(QuizForm $quizForm): array
    {
        $responses = $quizForm->responses()
            ->with(['user:id,name,email,nis,kelas'])
            ->latest()
            ->get(['id', 'quiz_form_id', 'user_id', 'respondent_identifier', 'respondent_name', 'respondent_class', 'email', 'score', 'is_timeout', 'is_auto_submitted', 'late_seconds', 'answers', 'doubtful_question_ids', 'manual_scores', 'question_ids', 'created_at']);

        $questions = $this->responseQuestionSummaries(collect(QuizItems::questions($quizForm->questions)), $responses);

        // Scored against the current answer keys, so correcting a key re-grades earlier answers.
        $scoring = new QuizScoring;
        $formQuestions = is_array($quizForm->questions) ? $quizForm->questions : [];
        $maxScore = $scoring->maxPoints($formQuestions);

        // Guests type their own name: the same name (and class) on more than one response is flagged.
        // (Attempts of the same guest device are one student, not a duplicate.)
        $guestNameCounts = $responses
            ->filter(fn (QuizResponse $response): bool => $response->user_id === null && filled($response->respondent_name))
            ->unique(fn (QuizResponse $response): string => $response->studentKey())
            ->countBy(fn (QuizResponse $response): string => $this->guestNameKey($response));

        // A student may take the quiz more than once: every attempt keeps its own answers and grade.
        $attemptNumbers = QuizResponse::attemptNumbers($responses);

        $gradebook = $responses->map(function (QuizResponse $response) use ($scoring, $formQuestions, $guestNameCounts, $attemptNumbers): array {
            // Scored against the current keys, with what the teacher graded by hand. With questions
            // drawn per student, the maximum is that of the questions they got.
            $result = $scoring->result($formQuestions, is_array($response->answers) ? $response->answers : [], $response->question_ids, $response->manual_scores);
            $score = is_array($response->answers) ? $result['score'] : (int) ($response->score ?? 0);
            $responseMax = $result['max'];
            $percentage = $scoring->grade($score, $responseMax);
            $isGuest = $response->user_id === null;

            return [
                'id' => $response->id,
                'user_id' => $response->user_id,
                'name' => $response->user?->name ?? $response->respondent_name ?? 'Anonim',
                'isGuest' => $isGuest,
                'duplicateName' => $isGuest && filled($response->respondent_name) && ($guestNameCounts[$this->guestNameKey($response)] ?? 0) > 1,
                'nis' => $response->user?->nis ?? '-',
                'kelas' => $response->user?->kelas ?? $response->respondent_class ?? '-',
                'email' => $response->email ?? $response->user?->email ?? '-',
                'score' => $score,
                'maxScore' => $responseMax,
                'percentage' => $percentage,
                // Essays and files the teacher has not graded yet: the grade is provisional.
                'ungraded' => $result['ungraded'],
                'lateMinutes' => $response->late_seconds !== null ? (int) ceil($response->late_seconds / 60) : null,
                'attempt' => $attemptNumbers[$response->id]['attempt'] ?? 1,
                'attempts' => $attemptNumbers[$response->id]['attempts'] ?? 1,
                'is_timeout' => (bool) $response->is_timeout,
                // Handed in by the server: the time ran out while the exam page was closed or offline.
                'is_auto_submitted' => (bool) $response->is_auto_submitted,
                'submittedAt' => $response->created_at->diffForHumans(),
                'formattedDate' => $response->created_at->format('d/m/Y H:i'),
                // For sorting by the moment of handing in.
                'submittedAtTimestamp' => $response->created_at->getTimestamp(),
            ];
        })->values()->all();

        return [
            'total' => $responses->count(),
            'students' => $quizForm->submittedStudentCount(),
            'progressUrl' => route('forms.progress', $quizForm),
            'maxScore' => $maxScore,
            'exportUrl' => route('forms.responses.export', $quizForm),
            'latest' => $responses->take(5)->map(fn (QuizResponse $response): array => [
                'id' => $response->id,
                'name' => $response->user?->name ?? $response->respondent_name,
                'email' => $response->email ?? $response->user?->email,
                'submittedAt' => $response->created_at->diffForHumans(),
            ])->values()->all(),
            'gradebook' => $gradebook,
            'questions' => $questions,
            // Analisis butir soal: difficulty, discrimination and distractors of each question.
            // Only with the Analisis Butir Soal add-on on for this exam (not even computed otherwise).
            'analysis' => $quizForm->usesAddon(Addon::ItemAnalysis)
                ? app(ItemAnalysis::class)->analyzeResponses(QuizItems::questions($formQuestions), $responses)
                : null,
            'applyDifficultyUrl' => route('forms.analysis.apply-difficulty', $quizForm),
            // Grading by hand: essays and files still waiting for the teacher, over every answer.
            'gradingUrl' => route('forms.grading.index', $quizForm),
            'ungradedTotal' => array_sum(array_column($gradebook, 'ungraded')),
            'hasGradableQuestions' => collect($formQuestions)->contains(fn (mixed $question): bool => is_array($question) && $scoring->isGradableByHand($question)),
            // Remedial & Pengayaan: the students below and above the KKM, and their follow-up exams.
            'remedial' => $quizForm->usesAddon(Addon::Remedial) ? app(Remedial::class)->overview($quizForm) : null,
            // Peta Miskonsepsi: right or wrong, sure or "Ragu-ragu", per question.
            'misconceptions' => $quizForm->usesAddon(Addon::Misconception)
                ? app(Misconceptions::class)->analyzeResponses(QuizItems::questions($formQuestions), $responses)
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function followUp(QuizForm $quizForm): array
    {
        $original = $quizForm->followUpOf;
        $participants = $quizForm->participants()->orderBy('kelas')->orderBy('name')->get(['users.id', 'users.name', 'users.kelas']);

        return [
            'kind' => $quizForm->follow_up_kind,
            'of' => $original !== null ? [
                'title' => trim((string) preg_replace('/\s+/u', ' ', app(HtmlToPlainText::class)->convert((string) $original->title))),
                'editUrl' => $original->canBeEditedBy(auth()->user()) ? route('forms.edit', ['quizForm' => $original->slug]) : null,
            ] : null,
            'participants' => $participants->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'kelas' => $user->kelas,
            ])->values()->all(),
        ];
    }

    protected function guestNameKey(QuizResponse $response): string
    {
        return mb_strtolower((string) $response->respondent_name).'|'.$response->respondent_class;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $questions
     * @param  Collection<int, QuizResponse>  $responses
     * @return array<int, array<string, mixed>>
     */
    private function responseQuestionSummaries(Collection $questions, Collection $responses): array
    {
        return $questions->map(function (array $question) use ($responses): array {
            $questionId = (string) ($question['id'] ?? '');
            $counts = [];
            $textAnswers = [];

            foreach ($responses as $response) {
                $answer = $response->answers[$questionId] ?? $response->answers[(int) $questionId] ?? null;

                if ($answer === null || $answer === '') {
                    continue;
                }

                foreach ($this->answerLabels($question, $answer) as $label) {
                    $counts[$label] = ($counts[$label] ?? 0) + 1;

                    if (count($textAnswers) < 8) {
                        $textAnswers[] = $label;
                    }
                }
            }

            arsort($counts);

            return [
                'id' => $question['id'] ?? null,
                'title' => $question['title'] ?? 'Untitled question',
                'type' => $question['type'] ?? 'Short answer',
                'total' => array_sum($counts),
                'options' => collect($counts)->map(fn (int $count, string $label): array => [
                    'label' => $label,
                    'count' => $count,
                ])->values()->all(),
                'textAnswers' => $textAnswers,
            ];
        })->values()->all();
    }

    /**
     * An answer as labels for the summary: choices as written, grid answers as "row → column"
     * (a tick box grid answer holds a list of columns per row).
     *
     * @param  array<string, mixed>  $question
     * @return list<string>
     */
    private function answerLabels(array $question, mixed $answer): array
    {
        $labels = [];

        if (in_array($question['type'] ?? '', ['Multiple-choice grid', 'Tick box grid'], true) && is_array($answer)) {
            $rows = is_array($question['rows'] ?? null) ? $question['rows'] : [];
            $columns = is_array($question['columns'] ?? null) ? $question['columns'] : [];

            foreach ($answer as $rowIndex => $selected) {
                foreach ((array) $selected as $columnIndex) {
                    if (! is_scalar($columnIndex) || $columnIndex === '') {
                        continue;
                    }

                    $row = is_scalar($rows[$rowIndex] ?? null) ? (string) $rows[$rowIndex] : 'Baris '.((int) $rowIndex + 1);
                    $column = is_scalar($columns[(int) $columnIndex] ?? null) ? (string) $columns[(int) $columnIndex] : (string) $columnIndex;
                    $labels[] = "{$row} → {$column}";
                }
            }

            return $labels;
        }

        foreach ((array) $answer as $value) {
            if (is_scalar($value) && $value !== '') {
                $labels[] = (string) $value;
            }
        }

        return $labels;
    }
}
