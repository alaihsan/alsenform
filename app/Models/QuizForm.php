<?php

namespace App\Models;

use Database\Factories\QuizFormFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
