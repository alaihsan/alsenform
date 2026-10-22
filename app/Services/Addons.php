<?php

namespace App\Services;

use App\Models\InstalledAddon;
use App\Models\QuizForm;
use App\Models\User;
use App\Support\Addon;

/**
 * Which add-ons are installed (for a teacher, or by an admin for everyone) and whether one is on
 * for an exam. An add-on is on for an exam when the exam's owner has it installed and the exam has
 * not switched it off; so collaborators see the same panels and the same Excel workbook, and
 * uninstalling turns it off on all the owner's exams while keeping each exam's choice for later.
 */
class Addons
{
    /**
     * How each add-on is installed, by user id (0: nobody signed in): 'everyone' or 'user'.
     *
     * @var array<int, array<string, string>>
     */
    protected array $installations = [];

    /**
     * @return array<string, string> How each installed add-on is installed for the teacher.
     */
    public function installationsFor(?User $user): array
    {
        return $this->installations[$user?->id ?? 0] ??= rescue(
            fn (): array => InstalledAddon::query()
                ->for($user)
                ->get(['user_id', 'addon'])
                ->reduce(function (array $installed, InstalledAddon $installation): array {
                    // Installed by an admin for everyone wins over the teacher's own installation.
                    $installed[$installation->addon->value] = $installation->user_id === null || ($installed[$installation->addon->value] ?? null) === 'everyone'
                        ? 'everyone'
                        : 'user';

                    return $installed;
                }, []),
            [],
            report: false,
        );
    }

    public function isInstalledFor(Addon $addon, ?User $user): bool
    {
        return isset($this->installationsFor($user)[$addon->value]);
    }

    /**
     * The exam's own switch for the add-on, or null when it follows the add-on's default.
     */
    public function switchOf(QuizForm $quizForm, Addon $addon): ?bool
    {
        $switch = (is_array($quizForm->settings) ? $quizForm->settings : [])['addons'][$addon->value] ?? null;

        return is_bool($switch) ? $switch : null;
    }

    public function isActiveFor(QuizForm $quizForm, Addon $addon): bool
    {
        return $this->isInstalledFor($addon, $quizForm->user)
            && ($this->switchOf($quizForm, $addon) ?? $addon->onByDefault());
    }

    public function install(Addon $addon, User $by, bool $forEveryone = false): void
    {
        InstalledAddon::query()->firstOrCreate(
            ['user_id' => $forEveryone ? null : $by->id, 'addon' => $addon->value],
            ['installed_by' => $by->id],
        );
        $this->installations = [];
    }

    public function uninstall(Addon $addon, User $by, bool $forEveryone = false): void
    {
        InstalledAddon::query()
            ->where('addon', $addon->value)
            ->when($forEveryone, fn ($query) => $query->whereNull('user_id'), fn ($query) => $query->where('user_id', $by->id))
            ->delete();
        $this->installations = [];
    }

    /**
     * The add-ons as the gallery and the editor show them to a teacher, with their state for an exam.
     *
     * @return list<array<string, mixed>>
     */
    public function catalog(User $user, ?QuizForm $quizForm = null): array
    {
        $installations = $this->installationsFor($user);

        return array_map(fn (Addon $addon): array => [
            'key' => $addon->value,
            'name' => $addon->label(),
            'tagline' => $addon->tagline(),
            'description' => $addon->description(),
            'icon' => $addon->icon(),
            'adds' => $addon->adds(),
            'reads' => $addon->reads(),
            'onByDefault' => $addon->onByDefault(),
            'perExam' => $addon->perExam(),
            'shownIn' => $addon->shownIn(),
            'installed' => isset($installations[$addon->value]),
            'installedForEveryone' => ($installations[$addon->value] ?? null) === 'everyone',
            // An add-on an admin installed for everyone is removed by an admin only.
            'canUninstall' => ($installations[$addon->value] ?? null) === 'user' || $user->isAdmin(),
            ...($quizForm !== null ? [
                'activeOnExam' => $this->isActiveFor($quizForm, $addon),
                'installedForOwner' => $this->isInstalledFor($addon, $quizForm->user),
            ] : []),
        ], Addon::cases());
    }
}
