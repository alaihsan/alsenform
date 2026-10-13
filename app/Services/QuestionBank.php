<?php

namespace App\Services;

use App\Models\QuestionBankItem;
use App\Models\QuizForm;
use App\Models\User;
use App\Support\ExamLayout;
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
     * Labels a teacher gives questions to find and draw them by: subject, class, chapter or
     * learning goal (Tujuan Pembelajaran), and difficulty (see ExamLayout::DIFFICULTIES).
     */
    public const LABELS = ['subject', 'grade', 'topic', 'difficulty'];

    protected const LABEL_LENGTHS = ['subject' => 100, 'grade' => 50, 'topic' => 150, 'difficulty' => 10];

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

            $rows[$fingerprint.'|'.$sourceId] = [
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

        if ($rows === []) {
            return;
        }

        // A question that is already in the bank (copied, or reused from the bank) brings its labels
        // along. Questions already kept from this exam keep the labels they have.
        $inherited = $this->labelsByFingerprint($quizForm->user_id, array_column($rows, 'fingerprint'));
        $rows = array_values(array_map(
            fn (array $row): array => $row + ($inherited[$row['fingerprint']] ?? array_fill_keys(self::LABELS, null)),
            $rows,
        ));

        QuestionBankItem::query()->upsert(
            $rows,
            ['quiz_form_id', 'source_question_id'],
            ['user_id', 'fingerprint', 'type', 'search_text', 'question', 'updated_at'],
        );
    }

    /**
     * Give the chosen questions labels. Only the labels given change (an empty one is cleared),
     * on every copy of the same question in the teacher's bank.
     *
     * @param  list<int>  $ids
     * @param  array<string, mixed>  $labels
     * @return int The number of bank items changed.
     */
    public function setLabels(User $user, array $ids, array $labels): int
    {
        $values = $this->cleanLabels($labels);

        if ($values === []) {
            return 0;
        }

        return QuestionBankItem::query()
            ->where('user_id', $user->id)
            ->whereIn('fingerprint', $this->fingerprintsOfItems($user, $ids))
            ->update($values);
    }

    /**
     * Move the chosen questions to the archive (hidden from the bank and the import), or back.
     *
     * @param  list<int>  $ids
     */
    public function setArchived(User $user, array $ids, bool $archived): int
    {
        return QuestionBankItem::query()
            ->where('user_id', $user->id)
            ->whereIn('fingerprint', $this->fingerprintsOfItems($user, $ids))
            ->update(['archived_at' => $archived ? Carbon::now() : null]);
    }

    /**
     * The labels of the questions of an exam, by question id.
     *
     * @return array<string, array<string, string|null>>
     */
    public function labelsForExam(QuizForm $quizForm): array
    {
        return QuestionBankItem::query()
            ->where('quiz_form_id', $quizForm->id)
            ->get(['source_question_id', ...self::LABELS])
            ->mapWithKeys(fn (QuestionBankItem $item): array => [$item->source_question_id => $item->only(self::LABELS)])
            ->all();
    }

    /**
     * Label one question of an exam (from the editor). Null when the question is not in the
     * bank: an empty, new question, or one not saved yet.
     *
     * @param  array<string, mixed>  $labels
     * @return array<string, string|null>|null
     */
    public function setLabelsOfQuestion(QuizForm $quizForm, string $questionId, array $labels): ?array
    {
        $find = fn (): ?QuestionBankItem => QuestionBankItem::query()
            ->where('quiz_form_id', $quizForm->id)
            ->where('source_question_id', $questionId)
            ->first();

        $item = $find();
        if ($item === null) {
            $this->sync($quizForm);
            $item = $find();
        }

        if ($item === null) {
            return null;
        }

        $this->setLabels($item->user, [$item->id], $labels);

        return $item->refresh()->only(self::LABELS);
    }

    /**
     * The subjects, classes and chapters a teacher used, to suggest while labelling.
     *
     * @return array{subjects: list<string>, grades: list<string>, topics: list<string>}
     */
    public function labelSuggestions(User $user): array
    {
        $values = fn (string $column): array => QuestionBankItem::query()
            ->where('user_id', $user->id)
            ->whereNotNull($column)
            ->distinct()
            ->orderBy($column)
            ->limit(200)
            ->pluck($column)
            ->all();

        return ['subjects' => $values('subject'), 'grades' => $values('grade'), 'topics' => $values('topic')];
    }

    /**
     * Labels as given by the teacher, trimmed and checked. Only the keys that were given.
     *
     * @param  array<string, mixed>  $labels
     * @return array<string, string|null>
     */
    protected function cleanLabels(array $labels): array
    {
        $values = [];

        foreach (self::LABELS as $label) {
            if (! array_key_exists($label, $labels)) {
                continue;
            }

            $value = is_scalar($labels[$label]) ? trim((string) $labels[$label]) : '';

            if ($label === 'difficulty') {
                $value = mb_strtolower($value);
                $values[$label] = in_array($value, ExamLayout::DIFFICULTIES, true) ? $value : null;

                continue;
            }

            $values[$label] = $value === '' ? null : Str::limit($value, self::LABEL_LENGTHS[$label], '');
        }

        return $values;
    }

    /**
     * @param  list<int>  $ids
     * @return list<string>
     */
    protected function fingerprintsOfItems(User $user, array $ids): array
    {
        return QuestionBankItem::query()
            ->where('user_id', $user->id)
            ->whereIn('id', $ids)
            ->distinct()
            ->pluck('fingerprint')
            ->all();
    }

    /**
     * The labels a question already has elsewhere in the teacher's bank, by fingerprint.
     *
     * @param  list<string>  $fingerprints
     * @return array<string, array<string, string|null>>
     */
    protected function labelsByFingerprint(int $userId, array $fingerprints): array
    {
        return QuestionBankItem::query()
            ->where('user_id', $userId)
            ->whereIn('fingerprint', array_values(array_unique($fingerprints)))
            ->where(fn (Builder $query) => $query->whereNotNull('subject')->orWhereNotNull('grade')->orWhereNotNull('topic')->orWhereNotNull('difficulty'))
            ->orderByDesc('updated_at')
            ->get(['fingerprint', ...self::LABELS])
            ->unique('fingerprint')
            ->mapWithKeys(fn (QuestionBankItem $item): array => [$item->fingerprint => $item->only(self::LABELS)])
            ->all();
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
     * @param  array<string, mixed>  $filters  search, type, form, subject, grade, topic, difficulty ("none": without), archived, page
     * @return LengthAwarePaginator<int, QuestionBankItem>
     */
    public function search(User $user, array $filters): LengthAwarePaginator
    {
        $formId = isset($filters['form']) && $filters['form'] !== '' ? (int) $filters['form'] : null;
        $archived = filter_var($filters['archived'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $latestOfEachQuestion = QuestionBankItem::query()
            ->selectRaw('MAX(id)')
            ->where('user_id', $user->id)
            ->when($formId !== null, fn (Builder $query) => $query->where('quiz_form_id', $formId))
            ->groupBy('fingerprint');

        $query = QuestionBankItem::query()
            ->where('user_id', $user->id)
            ->whereIn('id', $latestOfEachQuestion)
            ->when($archived, fn (Builder $query) => $query->whereNotNull('archived_at'), fn (Builder $query) => $query->whereNull('archived_at'))
            ->when(filled($filters['type'] ?? null), fn (Builder $query) => $query->where('type', $filters['type']));

        foreach (['subject', 'grade', 'topic', 'difficulty'] as $label) {
            $value = $filters[$label] ?? null;
            if ($value === 'none') {
                $query->whereNull($label);
            } elseif (filled($value)) {
                $query->where($label, $value);
            }
        }

        foreach ($this->searchWords((string) ($filters['search'] ?? '')) as $word) {
            $query->where('search_text', 'like', '%'.$word.'%');
        }

        return $query
            ->with('quizForm:id,title,slug,deleted_at')
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
     * Questions of a teacher's bank on the given topics that an exam does not have: for remedial
     * and enrichment exams. One item per question (its latest copy); archived ones are left out.
     *
     * @param  list<string>  $topics
     * @param  list<string>  $excludedFingerprints
     * @param  list<string>  $difficulties  Only these difficulties, when given.
     * @return Collection<int, QuestionBankItem>
     */
    public function onTopics(User $user, array $topics, array $excludedFingerprints = [], array $difficulties = []): Collection
    {
        if ($topics === []) {
            return collect();
        }

        $latestOfEachQuestion = QuestionBankItem::query()
            ->selectRaw('MAX(id)')
            ->where('user_id', $user->id)
            ->groupBy('fingerprint');

        return QuestionBankItem::query()
            ->where('user_id', $user->id)
            ->whereIn('id', $latestOfEachQuestion)
            ->whereNull('archived_at')
            ->whereIn('topic', $topics)
            ->whereNotIn('fingerprint', $excludedFingerprints)
            ->when($difficulties !== [], fn (Builder $query) => $query->whereIn('difficulty', $difficulties))
            ->orderBy('id')
            ->get(['id', 'fingerprint', 'topic', 'difficulty', 'question']);
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
    public function present(QuestionBankItem $item, array $fingerprintsInExam = [], bool $withQuestion = false): array
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
            'labels' => $item->only(self::LABELS),
            'archived' => $item->archived_at !== null,
            'sourceUrl' => $item->quizForm !== null && ! $item->quizForm->trashed() ? route('forms.edit', ['quizForm' => $item->quizForm->slug]) : null,
            // The whole question (answer key included) for the bank page preview; the teacher only.
            'question' => $withQuestion ? $question : null,
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
