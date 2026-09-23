<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'avatar',
        'password',
        'must_change_password',
        'is_admin',
        'nis',
        'kelas',
        'role',
        'nip',
        'phone',
        'subject',
        'school_origin',
        'quiz_preferences',
        'proctor_pin',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'proctor_pin',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = [
        'avatar_url',
        'has_proctor_pin',
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
            'must_change_password' => 'boolean',
            'quiz_preferences' => 'array',
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
     * Check if user is an admin.
     */
    public function isAdmin(): bool
    {
        return (bool) $this->is_admin || $this->role === 'admin';
    }

    /**
     * Check if user is a teacher.
     */
    public function isTeacher(): bool
    {
        return $this->role === 'guru' && ! $this->is_admin;
    }

    /**
     * Check if user is a student.
     */
    public function isStudent(): bool
    {
        if ($this->isAdmin() || $this->role === 'guru') {
            return false;
        }

        return $this->role === 'siswa' || $this->role === 'murid' || ! empty($this->nis);
    }

    /**
     * Scope query to admins.
     *
     * @param  Builder<User>  $query
     */
    public function scopeAdmins($query)
    {
        return $query->where(function ($q): void {
            $q->where('role', 'admin')->orWhere('is_admin', true);
        });
    }

    /**
     * Scope query to teachers.
     *
     * @param  Builder<User>  $query
     */
    public function scopeTeachers($query)
    {
        return $query->where('role', 'guru')->where('is_admin', false);
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

    /**
     * The cohorts that the user belongs to.
     */
    public function cohorts(): BelongsToMany
    {
        return $this->belongsToMany(Cohort::class, 'cohort_user')
            ->withTimestamps();
    }

    /**
     * Check if user is in a given cohort.
     */
    public function isInCohort(Cohort|int $cohort): bool
    {
        $cohortId = $cohort instanceof Cohort ? $cohort->id : $cohort;

        return $this->cohorts()->where('cohorts.id', $cohortId)->exists();
    }

    /**
     * The quiz forms where the user is invited as a collaborator.
     */
    public function collaboratingQuizForms(): BelongsToMany
    {
        return $this->belongsToMany(QuizForm::class, 'quiz_form_collaborators')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * The quiz forms created by the user.
     */
    public function quizForms()
    {
        return $this->hasMany(QuizForm::class, 'user_id');
    }

    /**
     * Get the public URL for the user's avatar.
     */
    public function getAvatarUrlAttribute(): ?string
    {
        if (! $this->avatar) {
            return null;
        }

        if (filter_var($this->avatar, FILTER_VALIDATE_URL)) {
            return $this->avatar;
        }

        return asset('storage/'.$this->avatar);
    }

    /**
     * Check whether the user has a proctor PIN configured.
     */
    public function getHasProctorPinAttribute(): bool
    {
        return ! empty($this->proctor_pin);
    }
}
