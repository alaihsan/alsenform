<?php

namespace App\Models;

use App\Support\Addon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstalledAddon extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'addon',
        'installed_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'addon' => Addon::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The installations that count for a teacher: their own, and those an admin made for everyone.
     *
     * @param  Builder<InstalledAddon>  $query
     */
    public function scopeFor(Builder $query, ?User $user): void
    {
        $query->where(fn (Builder $installed) => $installed
            ->whereNull('user_id')
            ->when($user !== null, fn (Builder $own) => $own->orWhere('user_id', $user->id)));
    }
}
