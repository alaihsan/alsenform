<?php

namespace App\Models;

use App\Services\Addons;
use App\Support\Addon;
use App\Support\FormDesign;
use App\Support\SectionFlow;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\QuizFormFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class QuizForm extends Model
{
    /** @use HasFactory<QuizFormFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * The passing grade (KKM) when the teacher has not set one in their profile.
     */
    public const DEFAULT_KKM = 75;

    public const FOLLOW_UP_REMEDIAL = 'remedial';

    public const FOLLOW_UP_ENRICHMENT = 'pengayaan';

    protected $fillable = [
        'user_id',
        'follow_up_of_id',
        'follow_up_kind',
        'title',
        'description',
        'slug',
        'template',
        'folder',
        'quiz_folder_id',
        'questions',
        'settings',
        'published_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'questions' => 'array',
            'settings' => 'array',
            'published_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function quizFolder(): BelongsTo
    {
        return $this->belongsTo(QuizFolder::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(QuizResponse::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(QuizSession::class);
    }

    public function cohorts(): BelongsToMany
    {
        return $this->belongsToMany(Cohort::class, 'cohort_quiz_form')
            ->withTimestamps();
    }

    /**
     * Students chosen for this exam (a remedial or enrichment exam): only they may take it.
     */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'quiz_form_participants')
            ->withTimestamps();
    }

    /**
     * The exam a remedial or enrichment exam follows up.
     */
    public function followUpOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'follow_up_of_id');
    }

    /**
     * The remedial and enrichment exams made after this exam.
     */
    public function followUps(): HasMany
    {
        return $this->hasMany(self::class, 'follow_up_of_id');
    }

    /**
     * Jadwal ujian: when students may start, from the exam settings (null: no limit).
     */
    public function opensAt(): ?CarbonImmutable
    {
        return $this->scheduleTime('opensAt');
    }

    public function closesAt(): ?CarbonImmutable
    {
        return $this->scheduleTime('closesAt');
    }

    /**
     * Whether students may start now: "open", "not_yet" (before it opens) or "closed".
     */
    public function scheduleState(?CarbonInterface $at = null): string
    {
        $at ??= now();

        return match (true) {
            $this->opensAt()?->gt($at) ?? false => 'not_yet',
            $this->closesAt()?->lte($at) ?? false => 'closed',
            default => 'open',
        };
    }

    /**
     * A time of the schedule as students read it, in the school's time zone.
     */
    public function scheduleLabel(CarbonInterface $time): string
    {
        return $time->copy()->setTimezone((string) config('app.display_timezone', config('app.timezone')))->locale('id')->translatedFormat('l, j F Y \p\u\k\u\l H.i');
    }

    /**
     * When an attempt started now runs out: its time limit, but no later than the closing time.
     */
    public function attemptExpiresAt(CarbonInterface $start, ?int $timeLimitMinutes): ?CarbonInterface
    {
        $byLimit = $timeLimitMinutes ? $start->copy()->addMinutes($timeLimitMinutes) : null;
        $closesAt = $this->closesAt();

        return match (true) {
            $closesAt === null => $byLimit,
            $byLimit === null => $closesAt,
            default => $closesAt->lt($byLimit) ? $closesAt : $byLimit,
        };
    }

    protected function scheduleTime(string $key): ?CarbonImmutable
    {
        $value = (is_array($this->settings) ? $this->settings : [])['schedule'][$key] ?? null;

        if (! is_string($value) || $value === '') {
            return null;
        }

        return rescue(fn (): CarbonImmutable => CarbonImmutable::parse($value), null, report: false);
    }

    /**
     * The passing grade (KKM): the owner's default from their profile, else 75.
     */
    public function kkm(): float
    {
        $kkm = $this->user?->quiz_preferences['default_kkm'] ?? null;

        return is_numeric($kkm) ? (float) $kkm : (float) self::DEFAULT_KKM;
    }

    public function collaborators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'quiz_form_collaborators')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function hasCollaborator(User|int $user): bool
    {
        $userId = $user instanceof User ? $user->id : $user;

        return $this->collaborators()->where('users.id', $userId)->exists();
    }

    public function canBeEditedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->id === $this->user_id || $user->isAdmin()) {
            return true;
        }

        return $this->hasCollaborator($user);
    }

    /**
     * Fingerprint of the content the editor saves. The editor sends back the version it started
     * from, so a save made from an outdated copy (another tab or device) cannot overwrite newer
     * questions. Compare versions of models loaded from the database only.
     */
    public function contentVersion(): string
    {
        return sha1((string) json_encode([
            $this->title,
            $this->description,
            $this->slug,
            $this->questions,
            $this->settings,
            $this->published_at !== null,
        ]));
    }

    /**
     * The settings of a new exam: the defaults the teacher saved in their profile (shuffling,
     * the tab-switch lock, the duration) on top of the general ones.
     *
     * @return array<string, mixed>
     */
    public static function defaultSettingsFor(User $teacher): array
    {
        $preferences = is_array($teacher->quiz_preferences) ? $teacher->quiz_preferences : [];
        $settings = [
            'collectEmail' => false,
            'showProgress' => true,
            'shuffleQuestions' => (bool) ($preferences['default_shuffle_questions'] ?? false),
            'shuffleOptions' => (bool) ($preferences['default_shuffle_options'] ?? false),
        ];

        if (array_key_exists('default_anti_cheat_blur', $preferences)) {
            $settings['lockOnBlur'] = (bool) $preferences['default_anti_cheat_blur'];
        }

        if (is_numeric($preferences['default_duration'] ?? null) && (int) $preferences['default_duration'] > 0) {
            $settings['timeLimit'] = (int) $preferences['default_duration'];
        }

        return $settings;
    }

    /**
     * Determine if an attempt is locked when the student leaves the exam tab or window.
     */
    public function locksOnBlur(): bool
    {
        return filter_var($this->settings['lockOnBlur'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Whether an add-on is on for this exam: installed for its owner and not switched off here.
     */
    public function usesAddon(Addon $addon): bool
    {
        return app(Addons::class)->isActiveFor($this, $addon);
    }

    /**
     * The exam's look from the Desainer Form add-on, or null while the add-on is off for it (the
     * design is kept, so switching the add-on on again brings it back).
     *
     * @return array<string, mixed>|null
     */
    public function design(): ?array
    {
        $settings = is_array($this->settings) ? $this->settings : [];

        return is_array($settings['design'] ?? null) && $this->usesAddon(Addon::FormDesigner)
            ? app(FormDesign::class)->normalize($settings['design'])
            : null;
    }

    /**
     * What the dashboard card shows of the exam's design, or null without one.
     *
     * @return array<string, mixed>|null
     */
    public function cardDesign(): ?array
    {
        $design = $this->design();

        return $design !== null ? app(FormDesign::class)->card($design) : null;
    }

    /**
     * How many times a student may take this quiz: null for no limit. Forms saved before the
     * setting existed have the older one-response switch.
     */
    public function maxAttempts(): ?int
    {
        $settings = is_array($this->settings) ? $this->settings : [];
        $maxAttempts = $settings['maxAttempts'] ?? null;

        if (is_numeric($maxAttempts)) {
            return (int) $maxAttempts >= 1 ? (int) $maxAttempts : null;
        }

        return ! empty($settings['limitOneResponse']) ? 1 : null;
    }

    /**
     * How many times a student handed this quiz in: an account by account, a guest by device.
     */
    public function attemptsUsedBy(?User $user, ?string $respondentIdentifier): int
    {
        if ($user !== null) {
            return $this->responses()->where('user_id', $user->id)->count();
        }

        return filled($respondentIdentifier)
            ? $this->responses()->whereNull('user_id')->where('respondent_identifier', $respondentIdentifier)->count()
            : 0;
    }

    /**
     * The student may not start another attempt: the limit set by the teacher is reached.
     */
    public function hasNoAttemptsLeftFor(?User $user, ?string $respondentIdentifier): bool
    {
        $maxAttempts = $this->maxAttempts();

        return $maxAttempts !== null && $this->attemptsUsedBy($user, $respondentIdentifier) >= $maxAttempts;
    }

    /**
     * What a student who used all attempts is told.
     */
    public function noAttemptsLeftMessage(): string
    {
        $maxAttempts = $this->maxAttempts();

        return $maxAttempts === 1
            ? 'Anda sudah pernah mengisi kuis ini. Setiap peserta hanya diperbolehkan mengirim 1 kali tanggapan.'
            : "Anda sudah mengerjakan kuis ini {$maxAttempts} kali, sesuai batas kesempatan yang diberikan guru.";
    }

    /**
     * How many students handed in at least once: accounts counted by account, guests by their device.
     */
    public function submittedStudentCount(): int
    {
        return $this->responses()->whereNotNull('user_id')->distinct()->count('user_id')
            + $this->responses()->whereNull('user_id')->whereNotNull('respondent_identifier')->distinct()->count('respondent_identifier')
            + $this->responses()->whereNull('user_id')->whereNull('respondent_identifier')->count();
    }

    /**
     * How many students have the exam open right now: a started attempt, not handed in, whose page
     * was in touch with the server within the last minute.
     */
    public function workingStudentCount(): int
    {
        return $this->sessions()
            ->whereNotNull('started_at')
            ->whereNull('submitted_at')
            ->where(fn ($query) => $query
                ->where('last_seen_at', '>=', now()->subMinute())
                ->orWhere('started_at', '>=', now()->subMinute()))
            ->count();
    }

    /**
     * Unlock every locked attempt still in progress and close the pending unlock requests.
     *
     * @return int The number of attempts unlocked.
     */
    public function releaseLockedSessions(): int
    {
        $unlocked = $this->sessions()
            ->where('is_locked', true)
            ->whereNull('submitted_at')
            ->update(['is_locked' => false, 'unlocked_at' => now(), 'left_at' => null]);

        UnlockRequest::query()
            ->whereBelongsTo($this)
            ->where('status', 'pending')
            ->update(['status' => 'approved']);

        return $unlocked;
    }

    /**
     * What a response keeps when the form sends respondents past sections (see SectionFlow): the
     * answers on the respondent's way, and the questions given narrowed to that way.
     *
     * @param  array<int|string, mixed>  $answers
     * @param  list<int|string>|null  $assignedIds
     * @return array{answers: array<int|string, mixed>, questionIds: list<int|string>|null}
     */
    public function keepOnPath(array $answers, ?array $assignedIds): array
    {
        return SectionFlow::keepOnPath(
            is_array($this->questions) ? $this->questions : [],
            is_array($this->settings) ? $this->settings : [],
            $answers,
            $assignedIds,
        );
    }

    /**
     * The ids among the given ones that are among the questions a response was given (all of
     * them when that is null).
     *
     * @param  list<int|string>|null  $ids
     * @param  list<int|string>|null  $givenIds
     * @return list<int|string>|null
     */
    public function onlyGiven(?array $ids, ?array $givenIds): ?array
    {
        if ($ids === null || $givenIds === null) {
            return $ids;
        }

        $given = array_map('strval', $givenIds);

        return array_values(array_filter($ids, fn (mixed $id): bool => in_array((string) $id, $given, true)));
    }

    /**
     * The ids among the given ones that are questions of this quiz (and of the student's draw, when
     * given), as strings without repeats: the questions a student marked "Ragu-ragu".
     *
     * @param  list<int|string>|null  $assignedIds
     * @return list<string>
     */
    public function questionIdsAmong(mixed $ids, ?array $assignedIds = null): array
    {
        if (! is_array($ids)) {
            return [];
        }

        $known = collect(is_array($this->questions) ? $this->questions : [])
            ->filter(fn (mixed $question): bool => is_array($question) && isset($question['id']))
            ->map(fn (array $question): string => (string) $question['id']);

        if ($assignedIds !== null) {
            $known = $known->intersect(array_map('strval', $assignedIds));
        }

        return collect($ids)
            ->filter(fn (mixed $id): bool => is_int($id) || (is_string($id) && $id !== ''))
            ->map(fn (int|string $id): string => (string) $id)
            ->intersect($known->all())
            ->unique()
            ->values()
            ->all();
    }

    public function isRestrictedToCohorts(): bool
    {
        return $this->cohorts()->exists();
    }

    public function isRestrictedToParticipants(): bool
    {
        return $this->participants()->exists();
    }

    /**
     * Whether only some students may take the exam: those of its cohorts and those chosen for it.
     */
    public function isRestricted(): bool
    {
        return $this->isRestrictedToCohorts() || $this->isRestrictedToParticipants();
    }

    public function allowsUser(?User $user): bool
    {
        if (! $this->isRestricted()) {
            return true;
        }

        if (! $user) {
            return false;
        }

        if ($user->isAdmin() || $user->id === $this->user_id) {
            return true;
        }

        return $this->participants()->where('users.id', $user->id)->exists()
            || $this->cohorts()
                ->whereHas('users', fn ($query) => $query->where('users.id', $user->id))
                ->exists();
    }

    public static function uniqueSlug(string $title, ?self $ignore = null): string
    {
        $baseSlug = Str::slug($title) ?: 'untitled-form';
        $slug = $baseSlug;
        $suffix = 2;

        while (self::query()
            ->where('slug', $slug)
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->getKey()))
            ->exists()) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    public static function generateComplexSlug(): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

        do {
            $result = '';
            while (strlen($result) < 59) {
                $remaining = 59 - strlen($result);
                $groupSize = random_int(7, 10);

                if ($groupSize >= $remaining) {
                    $groupSize = $remaining;
                } elseif ($remaining - $groupSize <= 2) {
                    $groupSize = $remaining;
                }

                $group = '';
                for ($i = 0; $i < $groupSize; $i++) {
                    $group .= $chars[random_int(0, strlen($chars) - 1)];
                }

                $result .= $group;
                if (strlen($result) < 59) {
                    $result .= '-';
                }
            }

            if (strlen($result) > 59) {
                $result = substr($result, 0, 59);
            }
        } while (self::query()->where('slug', $result)->exists());

        return $result;
    }
}
