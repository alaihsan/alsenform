<?php

namespace App\Services;

use App\Models\QuestionBankItem;
use App\Models\QuizForm;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The question bank of a teacher. Every question written in one of their exams is kept here, also
 * after it is removed from the exam or the exam is deleted, so it can be reused in another exam.
 * A teacher only ever sees their own bank.
 */
class QuestionBank
{
    /**
     * Titles the editor gives a new question before anything is written.
     */
    public const PLACEHOLDER_TITLES = ['untitled question', 'paragraph question', 'short answer question'];

    public const PER_PAGE = 20;

    /**
     * Keep the questions of an exam in its owner's bank: new questions are added, edited ones
     * updated. Questions removed from the exam stay in the bank.
     */
    public function sync(QuizForm $quizForm): void
    {
        $fingerprints = QuestionBankItem::query()
            ->where('quiz_form_id', $quizForm->id)
            ->pluck('fingerprint', 'source_question_id');

        $now = Carbon::now();
        $rows = [];

        foreach (is_array($quizForm->questions) ? $quizForm->questions : [] as $question) {
            if (! is_array($question) || ! isset($question['id']) || ! $this->isWritten($question)) {
                continue;
            }

            $sourceId = Str::limit((string) $question['id'], 64, '');
            $content = $this->content($question);
            $fingerprint = $this->fingerprint($content);

            if (($fingerprints[$sourceId] ?? null) === $fingerprint) {
                continue;
            }

            $rows[] = [
                'user_id' => $quizForm->user_id,
                'quiz_form_id' => $quizForm->id,
                'source_question_id' => $sourceId,
                'fingerprint' => $fingerprint,
                'type' => Str::limit((string) ($content['type'] ?? ''), 100, ''),
                'search_text' => $this->searchText($content),
                'question' => json_encode($content),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            QuestionBankItem::query()->upsert(
                $rows,
                ['quiz_form_id', 'source_question_id'],
                ['user_id', 'fingerprint', 'type', 'search_text', 'question', 'updated_at'],
            );
        }
    }

    /**
     * Bring the bank of a teacher up to date with all their exams (also those in the trash), for
     * the exams written before the bank existed.
     */
    public function syncAllFor(User $user): void
    {
        QuizForm::withTrashed()
            ->where('user_id', $user->id)
            ->select(['id', 'user_id', 'questions'])
            ->chunkById(50, fn (Collection $quizForms) => $quizForms->each(fn (QuizForm $quizForm) => $this->sync($quizForm)));
    }

    /**
     * One page of the bank of a teacher. The same question kept from several exams (a copied exam,
     * a question reused from the bank) is listed once.
     *
     * @param  array{search?: string|null, type?: string|null, form?: int|string|null, page?: int|string|null}  $filters
     * @return LengthAwarePaginator<int, QuestionBankItem>
     */
    public function search(User $user, array $filters): LengthAwarePaginator
    {
        $formId = isset($filters['form']) && $filters['form'] !== '' ? (int) $filters['form'] : null;

        $latestOfEachQuestion = QuestionBankItem::query()
            ->selectRaw('MAX(id)')
            ->where('user_id', $user->id)
            ->when($formId !== null, fn (Builder $query) => $query->where('quiz_form_id', $formId))
            ->groupBy('fingerprint');

        $query = QuestionBankItem::query()
            ->where('user_id', $user->id)
            ->whereIn('id', $latestOfEachQuestion)
            ->when(filled($filters['type'] ?? null), fn (Builder $query) => $query->where('type', $filters['type']));

        foreach ($this->searchWords((string) ($filters['search'] ?? '')) as $word) {
            $query->where('search_text', 'like', '%'.$word.'%');
        }

        return $query
            ->with('quizForm:id,title,deleted_at')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE, ['*'], 'page', max(1, (int) ($filters['page'] ?? 1)));
    }

    /**
     * The question types in the bank of a teacher, for the filter.
     *
     * @return list<string>
     */
    public function types(User $user): array
    {
        return QuestionBankItem::query()
            ->where('user_id', $user->id)
            ->distinct()
            ->orderBy('type')
            ->pluck('type')
            ->all();
    }

    /**
     * The exams the questions in the bank of a teacher come from, for the filter.
     *
     * @return list<array{id: int, title: string}>
     */
    public function forms(User $user): array
    {
        return QuizForm::withTrashed()
            ->where('user_id', $user->id)
            ->whereIn('id', QuestionBankItem::query()->select('quiz_form_id')->where('user_id', $user->id)->whereNotNull('quiz_form_id'))
            ->orderBy('title')
            ->get(['id', 'title', 'deleted_at'])
            ->map(fn (QuizForm $quizForm): array => [
                'id' => $quizForm->id,
                'title' => $quizForm->title.($quizForm->trashed() ? ' (di tempat sampah)' : ''),
            ])
            ->all();
    }

    /**
     * The fingerprints of the questions of an exam, to point out bank questions it already has.
     *
     * @return list<string>
     */
    public function fingerprintsOf(QuizForm $quizForm): array
    {
        return collect(is_array($quizForm->questions) ? $quizForm->questions : [])
            ->filter(fn (mixed $question): bool => is_array($question))
            ->map(fn (array $question): string => $this->fingerprint($this->content($question)))
            ->values()
            ->all();
    }

    /**
     * The questions of the chosen bank items, in the order they were chosen. Items of another
     * teacher are left out.
     *
     * @param  list<int>  $ids
     * @return list<array<string, mixed>>
     */
    public function questions(User $user, array $ids): array
    {
        $items = QuestionBankItem::query()
            ->where('user_id', $user->id)
            ->whereIn('id', $ids)
            ->get(['id', 'question'])
            ->keyBy('id');

        return collect($ids)
            ->map(fn (int $id): ?array => $items->get($id)?->question)
            ->filter()
            ->values()
            ->all();
    }

    /**
     * A bank item as listed in the "Bank Soal" tab: what the teacher needs to recognise it.
     *
     * @param  list<string>  $fingerprintsInExam
     * @return array<string, mixed>
     */
    public function present(QuestionBankItem $item, array $fingerprintsInExam = []): array
    {
        $question = $item->question ?? [];
        $options = array_values(array_filter(array_map(
            fn (mixed $option): string => Str::limit($this->plain($option), 80),
            is_array($question['options'] ?? null) ? $question['options'] : [],
        ), fn (string $option): bool => $option !== ''));
        $answer = $question['answer'] ?? null;

        return [
            'id' => $item->id,
            'type' => $item->type,
            'title' => Str::limit($this->plain($question['title'] ?? ''), 300),
            'description' => Str::limit($this->plain($question['description'] ?? ''), 160),
            'options' => array_slice($options, 0, 6),
            'optionCount' => count($options),
            'points' => (int) ($question['points'] ?? 0),
            'hasAnswerKey' => ! ($answer === null || $answer === '' || $answer === []),
            'mediaCount' => is_array($question['media'] ?? null) ? count($question['media']) : 0,
            'source' => $item->quizForm ? [
                'title' => $item->quizForm->title,
                'trashed' => $item->quizForm->trashed(),
            ] : null,
            'updatedAt' => $item->updated_at?->toISOString(),
            'inThisExam' => in_array($item->fingerprint, $fingerprintsInExam, true),
        ];
    }

    /**
     * A question with something written in it (not a new, empty question).
     *
     * @param  array<string, mixed>  $question
     */
    protected function isWritten(array $question): bool
    {
        $title = mb_strtolower($this->plain($question['title'] ?? ''));

        return $title !== '' && ! in_array($title, self::PLACEHOLDER_TITLES, true);
    }

    /**
     * The question as kept in the bank: everything but its place in the exam (id) and the state of
     * an upload in progress.
     *
     * @param  array<string, mixed>  $question
     * @return array<string, mixed>
     */
    protected function content(array $question): array
    {
        unset($question['id']);

        if (is_array($question['media'] ?? null)) {
            $question['media'] = array_values(array_map(function (mixed $media): mixed {
                if (is_array($media)) {
                    unset($media['isUploading'], $media['uploadError']);
                }

                return $media;
            }, $question['media']));
        }

        return $question;
    }

    /**
     * Identifies a question by its content. The order of the keys does not count: MySQL hands JSON
     * back with its keys reordered, the order of options and rows does.
     *
     * @param  array<string, mixed>  $content
     */
    protected function fingerprint(array $content): string
    {
        return sha1((string) json_encode($this->canonical($content)));
    }

    protected function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        return array_map(fn (mixed $item): mixed => $this->canonical($item), $value);
    }

    /**
     * Lowercase plain text of the question, its options, rows and columns, to search in.
     *
     * @param  array<string, mixed>  $content
     */
    protected function searchText(array $content): string
    {
        $parts = [$content['title'] ?? '', $content['description'] ?? '', $content['type'] ?? ''];

        foreach (['options', 'rows', 'columns'] as $list) {
            if (is_array($content[$list] ?? null)) {
                array_push($parts, ...array_values($content[$list]));
            }
        }

        $text = implode(' ', array_map(fn (mixed $part): string => $this->plain($part), $parts));

        return Str::limit(mb_strtolower($text), 5000, '');
    }

    /**
     * The words searched for, each escaped for a LIKE pattern.
     *
     * @return list<string>
     */
    protected function searchWords(string $search): array
    {
        $words = preg_split('/\s+/u', mb_strtolower(trim($search)), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_map(
            fn (string $word): string => str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $word),
            array_slice($words, 0, 8),
        ));
    }

    /**
     * Plain text from a question field that may contain formatting (HTML).
     */
    protected function plain(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        $text = html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], ' ', (string) $value)), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/[\s\x{00A0}]+/u', ' ', $text));
    }
}
