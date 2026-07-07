<?php

namespace App\Models;

use Database\Factories\QuizFormFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class QuizForm extends Model
{
    /** @use HasFactory<QuizFormFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'slug',
        'template',
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
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
                $groupSize = rand(7, 10);

                if ($groupSize >= $remaining) {
                    $groupSize = $remaining;
                } elseif ($remaining - $groupSize <= 2) {
                    $groupSize = $remaining;
                }

                $group = '';
                for ($i = 0; $i < $groupSize; $i++) {
                    $group .= $chars[rand(0, strlen($chars) - 1)];
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
