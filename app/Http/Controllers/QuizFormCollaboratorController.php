<?php

namespace App\Http\Controllers;

use App\Models\QuizForm;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class QuizFormCollaboratorController extends Controller
{
    /**
     * Add a teacher as a collaborator to the quiz form.
     */
    public function store(Request $request, QuizForm $quizForm): RedirectResponse
    {
        $currentUser = $request->user();

        // Only owner or admin can invite collaborators
        if ($quizForm->user_id !== $currentUser->id && ! $currentUser->isAdmin()) {
            abort(403, 'Hanya pemilik kuis atau administrator yang dapat menambahkan kolaborator.');
        }

        $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $targetUser = null;
        if ($request->filled('user_id')) {
            $targetUser = User::find($request->input('user_id'));
        } elseif ($request->filled('email')) {
            $targetUser = User::where('email', trim((string) $request->input('email')))->first();
        }

        $errorKey = $request->filled('user_id') ? 'user_id' : 'email';

        if (! $targetUser) {
            return back()->withErrors([$errorKey => 'Guru tidak ditemukan. Masukkan email atau pilih guru yang valid.']);
        }

        // Must be teacher or admin (strictly restricted to teachers/admins)
        if (! ($targetUser->role === 'guru' || $targetUser->role === 'admin' || $targetUser->is_admin)) {
            return back()->withErrors([$errorKey => 'Kolaborator hanya dapat ditambahkan untuk sesama akun guru atau administrator.']);
        }

        // Cannot add the form owner
        if ($targetUser->id === $quizForm->user_id) {
            return back()->withErrors([$errorKey => 'Guru ini adalah pemilik formulir kuis ini.']);
        }

        // Check if already added
        if ($quizForm->hasCollaborator($targetUser)) {
            return back()->withErrors([$errorKey => 'Guru ini sudah menjadi kolaborator kuis ini.']);
        }

        $quizForm->collaborators()->attach($targetUser->id, [
            'role' => 'editor',
        ]);

        return back()->with('success', "Guru '{$targetUser->name}' berhasil ditambahkan sebagai kolaborator kuis.");
    }

    /**
     * Remove a collaborator from the quiz form.
     */
    public function destroy(Request $request, QuizForm $quizForm, User $user): RedirectResponse
    {
        $currentUser = $request->user();

        // Only owner, admin, or the collaborator themselves can remove
        if ($quizForm->user_id !== $currentUser->id && ! $currentUser->isAdmin() && $currentUser->id !== $user->id) {
            abort(403, 'Anda tidak memiliki hak untuk menghapus kolaborator ini.');
        }

        $quizForm->collaborators()->detach($user->id);

        $msg = $currentUser->id === $user->id
            ? 'Anda telah keluar dari kolaborasi kuis ini.'
            : "Kolaborator '{$user->name}' berhasil dihapus.";

        return back()->with('success', $msg);
    }
}
