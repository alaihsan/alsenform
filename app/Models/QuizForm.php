<?php

namespace App\Models;

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

    protected $fillable = [
        'user_id',
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

    public function isRestrictedToCohorts(): bool
    {
        return $this->cohorts()->exists();
    }

    public function allowsUser(?User $user): bool
    {
        if (! $this->isRestrictedToCohorts()) {
            return true;
        }

        if (! $user) {
            return false;
        }

        if ($user->isAdmin() || $user->id === $this->user_id) {
            return true;
        }

        return $this->cohorts()
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
