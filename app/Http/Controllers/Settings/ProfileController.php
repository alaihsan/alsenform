<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\QuizForm;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        $sessions = [];
        if (Schema::hasTable('sessions')) {
            $sessions = collect(
                DB::table('sessions')
                    ->where('user_id', $user->id)
                    ->orderBy('last_activity', 'desc')
                    ->get()
            )->map(function ($session) use ($request) {
                return [
                    'id' => $session->id,
                    'ip_address' => $session->ip_address,
                    'is_current_device' => $session->id === $request->session()->getId(),
                    'last_active' => Carbon::createFromTimestamp($session->last_activity)->diffForHumans(),
                    'agent' => $this->parseAgent($session->user_agent),
                ];
            })->all();
        }

        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'canDeleteAccount' => ! $user->isStudent(),
            'sessions' => $sessions,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        if ($request->boolean('remove_avatar')) {
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }
            $user->avatar = null;
        } elseif ($request->hasFile('avatar')) {
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }
            $user->avatar = $request->file('avatar')->store('avatars', 'public');
        }

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'nip' => $validated['nip'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'subject' => $validated['subject'] ?? null,
            'school_origin' => $validated['school_origin'] ?? null,
        ]);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return back(fallback: route('profile.edit'))->with('status', 'profile-updated');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->isStudent()) {
            return back()->withErrors([
                'password' => 'Akun siswa dikelola oleh pihak sekolah dan tidak dapat dihapus secara mandiri untuk menjaga integritas data dan riwayat nilai ujian.',
            ]);
        }

        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    /**
     * Log out from other browser sessions.
     */
    public function logoutOtherSessions(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        Auth::logoutOtherDevices($request->password);

        if (Schema::hasTable('sessions')) {
            DB::table('sessions')
                ->where('user_id', $request->user()->id)
                ->where('id', '!=', $request->session()->getId())
                ->delete();
        }

        return back()->with('status', 'other-sessions-logged-out');
    }

    /**
     * Update default quiz preferences for the teacher.
     */
    public function updatePreferences(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'default_kkm' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'default_duration' => ['nullable', 'integer', 'min:1', 'max:600'],
            'default_shuffle_questions' => ['nullable', 'boolean'],
            'default_shuffle_options' => ['nullable', 'boolean'],
            'default_anti_cheat_blur' => ['nullable', 'boolean'],
            'default_school_name' => ['nullable', 'string', 'max:255'],
            'default_arabic_font' => ['nullable', 'string', 'max:100'],
        ]);

        $user = $request->user();
        $user->quiz_preferences = array_merge($user->quiz_preferences ?? [], $validated);
        $user->save();

        return back()->with('status', 'preferences-updated');
    }

    /**
     * Set or update proctor verification PIN.
     */
    public function updateProctorPin(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'pin' => ['required', 'digits:6'],
        ]);

        $user = $request->user();
        $user->proctor_pin = Hash::make($request->pin);
        $user->save();

        return back()->with('status', 'proctor-pin-updated');
    }

    /**
     * Export all quizzes authored by the user to JSON.
     */
    public function exportQuizzes(Request $request): StreamedResponse
    {
        $user = $request->user();
        $quizzes = QuizForm::query()
            ->where('user_id', $user->id)
            ->get();

        $exportData = [
            'exported_at' => now()->toIso8601String(),
            'platform' => 'Alsenform',
            'author' => [
                'name' => $user->name,
                'email' => $user->email,
                'nip' => $user->nip,
                'school_origin' => $user->school_origin,
            ],
            'total_quizzes' => $quizzes->count(),
            'quizzes' => $quizzes->map(function ($quiz) {
                return [
                    'id' => $quiz->id,
                    'title' => $quiz->title,
                    'description' => $quiz->description,
                    'slug' => $quiz->slug,
                    'template' => $quiz->template,
                    'questions' => $quiz->questions,
                    'settings' => $quiz->settings,
                    'published_at' => $quiz->published_at?->toIso8601String(),
                ];
            }),
        ];

        $filename = 'bank-soal-'.Str::slug($user->name ?: 'guru').'-'.now()->format('Y-m-d').'.json';

        return response()->streamDownload(function () use ($exportData) {
            echo json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }, $filename, [
            'Content-Type' => 'application/json',
        ]);
    }

    /**
     * Parse User-Agent string into readable platform and browser.
     *
     * @return array{platform: string, browser: string, is_desktop: bool}
     */
    protected function parseAgent(?string $userAgent): array
    {
        if (empty($userAgent)) {
            return [
                'platform' => 'Perangkat Tidak Diketahui',
                'browser' => 'Browser Tidak Diketahui',
                'is_desktop' => true,
            ];
        }

        $platform = 'Desktop';
        $isDesktop = true;

        if (preg_match('/(iPhone|iPad|iPod)/i', $userAgent)) {
            $platform = 'iOS';
            $isDesktop = false;
        } elseif (preg_match('/Android/i', $userAgent)) {
            $platform = 'Android';
            $isDesktop = false;
        } elseif (preg_match('/Windows/i', $userAgent)) {
            $platform = 'Windows PC';
        } elseif (preg_match('/Macintosh|Mac OS X/i', $userAgent)) {
            $platform = 'Mac';
        } elseif (preg_match('/Linux/i', $userAgent)) {
            $platform = 'Linux';
        } elseif (preg_match('/CrOS/i', $userAgent)) {
            $platform = 'ChromeOS';
        }

        $browser = 'Browser Lain';
        if (preg_match('/Edg/i', $userAgent)) {
            $browser = 'Microsoft Edge';
        } elseif (preg_match('/Chrome/i', $userAgent)) {
            $browser = 'Google Chrome';
        } elseif (preg_match('/Safari/i', $userAgent) && ! preg_match('/Chrome/i', $userAgent)) {
            $browser = 'Apple Safari';
        } elseif (preg_match('/Firefox/i', $userAgent)) {
            $browser = 'Mozilla Firefox';
        } elseif (preg_match('/Opera|OPR/i', $userAgent)) {
            $browser = 'Opera';
        }

        return [
            'platform' => $platform,
            'browser' => $browser,
            'is_desktop' => $isDesktop,
        ];
    }
}
