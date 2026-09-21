<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
        'nis',
        'kelas',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    /**
     * Calculate default password from NIS (last 6 digits).
     */
    public static function defaultPasswordForNis(string $nis): string
    {
        $clean = trim($nis);
        $digits = preg_replace('/\D/', '', $clean);

        if (strlen($digits) >= 6) {
            return substr($digits, -6);
        }

        if (strlen($clean) >= 6) {
            return substr($clean, -6);
        }

        return $clean;
    }

    /**
     * Check if user is a student.
     */
    public function isStudent(): bool
    {
        return $this->role === 'siswa' || ! empty($this->nis);
    }

    /**
     * Scope query to only students.
     *
     * @param  Builder<User>  $query
     */
    public function scopeStudents($query)
    {
        return $query->where(function ($q): void {
            $q->where('role', 'siswa')->orWhereNotNull('nis');
        });
    }

    /**
     * Scope query by class.
     *
     * @param  Builder<User>  $query
     */
    public function scopeClass($query, string $kelas)
    {
        return $query->where('kelas', $kelas);
    }
}
