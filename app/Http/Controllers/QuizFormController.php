<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateQuizFormRequest;
use App\Models\QuizForm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class QuizFormController extends Controller
{
    public function dashboard(Request $request): Response
    {
        $recentForms = QuizForm::query()
            ->whereBelongsTo($request->user())
            ->latest('updated_at')
            ->get()
            ->map(fn (QuizForm $quizForm): array => $this->recentFormPayload($quizForm));

        return Inertia::render('Dashboard', [
            'recentForms' => $recentForms,
        ]);
    }

    public function create(Request $request, ?string $template = null)
    {
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

    public function edit(Request $request, QuizForm $quizForm): Response
    {
        abort_unless($request->user()->is($quizForm->user), 403);

        $quizForm->touch();

        return Inertia::render('FormEditor', [
            'template' => $quizForm->template,
            'quizForm' => $this->editorPayload($quizForm),
        ]);
    }

    public function update(UpdateQuizFormRequest $request, QuizForm $quizForm)
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

    public function show(QuizForm $quizForm): Response
    {
        return Inertia::render('PublicQuiz', [
            'quizForm' => [
                'title' => $quizForm->title,
                'description' => $quizForm->description,
                'questions' => $quizForm->questions,
                'settings' => $quizForm->settings,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function editorPayload(QuizForm $quizForm): array
    {
        return [
            'id' => $quizForm->id,
            'title' => $quizForm->title,
            'description' => $quizForm->description,
            'slug' => $quizForm->slug,
            'questions' => $quizForm->questions,
            'settings' => $quizForm->settings,
            'updateUrl' => route('forms.update', $quizForm),
            'publicUrl' => route('forms.public', ['quizForm' => $quizForm->slug]),
            'isPublished' => ! is_null($quizForm->published_at),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function recentFormPayload(QuizForm $quizForm): array
    {
        return [
            'id' => $quizForm->id,
            'title' => $quizForm->title,
            'description' => $quizForm->description,
            'editUrl' => route('forms.edit', ['quizForm' => $quizForm->slug]),
            'publicUrl' => route('forms.public', ['quizForm' => $quizForm->slug]),
            'updatedLabel' => 'Opened '.$quizForm->updated_at->format('j M Y'),
            'isPublished' => ! is_null($quizForm->published_at),
            'tone' => match ($quizForm->template) {
                'party-invite' => 'bg-fuchsia-50',
                'work-request' => 'bg-cyan-50',
                'rsvp' => 'bg-orange-50',
                't-shirt-sign-up' => 'bg-violet-50',
                default => 'bg-slate-100',
            },
            'stripe' => match ($quizForm->template) {
                'party-invite' => 'bg-fuchsia-300',
                'work-request' => 'bg-emerald-300',
                'rsvp' => 'bg-orange-300',
                't-shirt-sign-up' => 'bg-violet-500',
                default => 'bg-indigo-500',
            },
            'accent' => match ($quizForm->template) {
                'rsvp' => 'bg-orange-500',
                'work-request' => 'bg-emerald-500',
                default => 'bg-violet-600',
            },
        ];
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

    public function uploadMedia(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp,mp4,webm,mov', 'max:512000'],
        ]);

        $path = $request->file('file')->store('media', 'public');

        return response()->json([
            'url' => asset('storage/'.$path),
        ]);
    }
}
