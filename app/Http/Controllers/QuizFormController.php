<?php

namespace App\Http\Controllers;

use App\Actions\DuplicateQuizForm;
use App\Http\Requests\MoveQuizFormToFolderRequest;
use App\Http\Requests\UpdateQuizFormRequest;
use App\Http\Requests\UploadQuizFormMediaRequest;
use App\Models\QuizFolder;
use App\Models\QuizForm;
use App\Support\QuizFormPayloads;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class QuizFormController extends Controller
{
    public function create(Request $request, ?string $template = null): RedirectResponse
    {
        Gate::authorize('create', QuizForm::class);

        $preset = $this->preset($template ?? 'blank');

        $quizForm = QuizForm::query()->create([
            'user_id' => $request->user()->id,
            'title' => $preset['title'],
            'description' => $preset['description'],
            'slug' => QuizForm::generateComplexSlug(),
            'template' => $template ?? 'blank',
            'questions' => [
                [
                    'id' => 1,
                    'title' => $preset['question'],
                    'description' => '',
                    'type' => 'Multiple choice',
                    'options' => $preset['options'],
                    'answer' => '',
                    'required' => true,
                    'media' => [],
                ],
            ],
            'settings' => [
                'collectEmail' => false,
                'showProgress' => true,
                'shuffleQuestions' => false,
            ],
        ]);

        return to_route('forms.edit', ['quizForm' => $quizForm->slug]);
    }

    public function edit(Request $request, QuizForm $quizForm, QuizFormPayloads $payloads): Response
    {
        Gate::authorize('update', $quizForm);

        $quizForm->touch();

        return Inertia::render('FormEditor', [
            'template' => $quizForm->template,
            'quizForm' => $payloads->editor($quizForm),
        ]);
    }

    public function update(UpdateQuizFormRequest $request, QuizForm $quizForm): RedirectResponse
    {
        $validated = $request->validated();

        $quizForm->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? '',
            'slug' => $validated['slug'],
            'questions' => $validated['questions'],
            'settings' => $validated['settings'],
            'published_at' => $request->boolean('published') ? ($quizForm->published_at ?? now()) : null,
        ]);

        return to_route('forms.edit', ['quizForm' => $quizForm->slug]);
    }

    public function duplicate(QuizForm $quizForm, DuplicateQuizForm $duplicateQuizForm): RedirectResponse
    {
        Gate::authorize('update', $quizForm);

        $duplicate = $duplicateQuizForm->handle($quizForm);

        return to_route('forms.edit', ['quizForm' => $duplicate->slug]);
    }

    public function moveToFolder(MoveQuizFormToFolderRequest $request, QuizForm $quizForm): RedirectResponse
    {
        $validated = $request->validated();
        $folder = null;

        if (isset($validated['folder_id'])) {
            $folder = QuizFolder::query()
                ->whereBelongsTo($request->user())
                ->findOrFail($validated['folder_id']);
        } elseif (isset($validated['folder']) && trim($validated['folder']) !== '') {
            $folder = QuizFolder::query()->firstOrCreate([
                'user_id' => $request->user()->id,
                'name' => trim($validated['folder']),
            ]);
        }

        $quizForm->update([
            'folder' => $folder?->name,
            'quiz_folder_id' => $folder?->id,
        ]);

        return to_route('dashboard');
    }

    public function destroy(QuizForm $quizForm): RedirectResponse
    {
        Gate::authorize('delete', $quizForm);

        $quizForm->delete();

        return to_route('dashboard');
    }

    public function restore(QuizForm $quizForm): RedirectResponse
    {
        Gate::authorize('restore', $quizForm);

        $quizForm->restore();

        return to_route('dashboard');
    }

    public function forceDelete(QuizForm $quizForm): RedirectResponse
    {
        Gate::authorize('forceDelete', $quizForm);

        $quizForm->forceDelete();

        return to_route('dashboard');
    }

    public function uploadMedia(UploadQuizFormMediaRequest $request): JsonResponse
    {
        $path = $request->file('file')->store('media', 'public');

        return response()->json([
            'url' => asset('storage/'.$path),
        ]);
    }

    /**
     * @return array{title: string, description: string, question: string, options: array<int, string>}
     */
    private function preset(string $template): array
    {
        return match ($template) {
            'contact-information' => [
                'title' => 'Contact Information',
                'description' => 'Kumpulkan informasi kontak responden.',
                'question' => 'Informasi apa yang ingin dikirim?',
                'options' => ['Nama lengkap', 'Email', 'Nomor WhatsApp'],
            ],
            'party-invite' => [
                'title' => 'Party Invite',
                'description' => 'Konfirmasi undangan acara.',
                'question' => 'Apakah kamu akan hadir?',
                'options' => ['Ya, hadir', 'Belum pasti', 'Tidak bisa hadir'],
            ],
            'work-request' => [
                'title' => 'Work Request',
                'description' => 'Detail permintaan pekerjaan untuk tim.',
                'question' => 'Jenis pekerjaan apa yang dibutuhkan?',
                'options' => ['Desain', 'Dokumen', 'Perbaikan teknis'],
            ],
            'rsvp' => [
                'title' => 'RSVP',
                'description' => 'Konfirmasi kehadiran peserta.',
                'question' => 'Status kehadiran kamu?',
                'options' => ['Hadir', 'Tidak hadir', 'Mungkin hadir'],
            ],
            't-shirt-sign-up' => [
                'title' => 'T-Shirt Sign Up',
                'description' => 'Pemesanan kaos dan pilihan ukuran.',
                'question' => 'Ukuran kaos yang dipilih?',
                'options' => ['S', 'M', 'L', 'XL'],
            ],
            default => [
                'title' => 'Untitled form',
                'description' => 'Form description',
                'question' => 'Untitled Question',
                'options' => ['Option 1'],
            ],
        };
    }
}
