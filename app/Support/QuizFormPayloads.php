<?php

namespace App\Support;

use App\Models\Cohort;
use App\Models\QuizFolder;
use App\Models\QuizForm;
use App\Models\QuizResponse;
use App\Models\User;
use Illuminate\Support\Collection;

class QuizFormPayloads
{
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
            'questions' => $quizForm->questions,
            'settings' => $quizForm->settings,
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

        $questions = $quizForm->questions ?? [];
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
            ->get(['id', 'quiz_form_id', 'user_id', 'respondent_identifier', 'email', 'score', 'is_timeout', 'answers', 'created_at']);

        $questions = $this->responseQuestionSummaries(collect($quizForm->questions ?? []), $responses);

        $maxScore = 0;
        foreach ($quizForm->questions ?? [] as $q) {
            $maxScore += isset($q['points']) ? (int) $q['points'] : 1;
        }

        $gradebook = $responses->map(function (QuizResponse $response) use ($maxScore): array {
            $score = $response->score ?? 0;
            $percentage = $maxScore > 0 ? round(($score / $maxScore) * 100, 1) : 0;

            return [
                'id' => $response->id,
                'user_id' => $response->user_id,
                'name' => $response->user?->name ?? 'Anonim',
                'nis' => $response->user?->nis ?? '-',
                'kelas' => $response->user?->kelas ?? '-',
                'email' => $response->email ?? $response->user?->email ?? '-',
                'score' => $score,
                'percentage' => $percentage,
                'is_timeout' => (bool) $response->is_timeout,
                'submittedAt' => $response->created_at->diffForHumans(),
                'formattedDate' => $response->created_at->format('d/m/Y H:i'),
            ];
        })->values()->all();

        return [
            'total' => $responses->count(),
            'maxScore' => $maxScore,
            'exportUrl' => route('forms.responses.export', $quizForm),
            'latest' => $responses->take(5)->map(fn (QuizResponse $response): array => [
                'id' => $response->id,
                'email' => $response->email ?? $response->user?->email,
                'submittedAt' => $response->created_at->diffForHumans(),
            ])->values()->all(),
            'gradebook' => $gradebook,
            'questions' => $questions,
        ];
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

                foreach ((array) $answer as $value) {
                    if ($value === null || $value === '') {
                        continue;
                    }

                    $label = (string) $value;
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
}
