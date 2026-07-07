<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuizResponseRequest;
use App\Http\Requests\UpdateQuizFormRequest;
use App\Models\QuizFolder;
use App\Models\QuizForm;
use App\Models\QuizResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class QuizFormController extends Controller
{
    public function dashboard(Request $request): Response
    {
        $recentForms = QuizForm::query()
            ->withTrashed()
            ->with('quizFolder')
            ->whereBelongsTo($request->user())
            ->latest('updated_at')
            ->get()
            ->map(fn (QuizForm $quizForm): array => $this->recentFormPayload($quizForm));
        $folders = QuizFolder::query()
            ->whereBelongsTo($request->user())
            ->withCount(['forms' => fn ($query) => $query->whereNull('deleted_at')])
            ->orderBy('name')
            ->get()
            ->map(fn (QuizFolder $folder): array => [
                'id' => $folder->id,
                'name' => $folder->name,
                'formsCount' => $folder->forms_count,
                'updateUrl' => route('folders.update', $folder),
                'deleteUrl' => route('folders.destroy', $folder),
            ]);

        return Inertia::render('Dashboard', [
            'recentForms' => $recentForms,
            'folders' => $folders,
            'createFolderUrl' => route('folders.store'),
        ]);
    }

    public function create(Request $request, ?string $template = null): RedirectResponse
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

    public function duplicate(Request $request, QuizForm $quizForm): RedirectResponse
    {
        abort_unless($request->user()->is($quizForm->user), 403);

        $duplicate = $quizForm->replicate([
            'slug',
            'published_at',
        ]);

        $duplicate->forceFill([
            'title' => $quizForm->title.' (Copy)',
            'slug' => QuizForm::generateComplexSlug(),
            'published_at' => null,
        ])->save();

        return to_route('forms.edit', ['quizForm' => $duplicate->slug]);
    }

    public function storeFolder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:80',
                Rule::unique('quiz_folders')->where(fn ($query) => $query->where('user_id', $request->user()->id)),
            ],
        ]);

        QuizFolder::query()->firstOrCreate([
            'user_id' => $request->user()->id,
            'name' => trim($validated['name']),
        ]);

        return to_route('dashboard');
    }

    public function updateFolder(Request $request, QuizFolder $quizFolder): RedirectResponse
    {
        abort_unless($request->user()->is($quizFolder->user), 403);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:80',
                Rule::unique('quiz_folders')
                    ->ignore($quizFolder)
                    ->where(fn ($query) => $query->where('user_id', $request->user()->id)),
            ],
        ]);

        $name = trim($validated['name']);

        $quizFolder->update([
            'name' => $name,
        ]);

        $quizFolder->forms()->update([
            'folder' => $name,
        ]);

        return to_route('dashboard');
    }

    public function destroyFolder(Request $request, QuizFolder $quizFolder): RedirectResponse
    {
        abort_unless($request->user()->is($quizFolder->user), 403);

        $quizFolder->forms()->update([
            'folder' => null,
            'quiz_folder_id' => null,
        ]);

        $quizFolder->delete();

        return to_route('dashboard');
    }

    public function moveToFolder(Request $request, QuizForm $quizForm): RedirectResponse
    {
        abort_unless($request->user()->is($quizForm->user), 403);

        $validated = $request->validate([
            'folder_id' => ['nullable', 'integer', 'exists:quiz_folders,id'],
            'folder' => ['nullable', 'string', 'max:80'],
        ]);

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

    public function destroy(Request $request, QuizForm $quizForm): RedirectResponse
    {
        abort_unless($request->user()->is($quizForm->user), 403);

        $quizForm->delete();

        return to_route('dashboard');
    }

    public function restore(Request $request, QuizForm $quizForm): RedirectResponse
    {
        abort_unless($request->user()->is($quizForm->user), 403);

        $quizForm->restore();

        return to_route('dashboard');
    }

    public function forceDelete(Request $request, QuizForm $quizForm): RedirectResponse
    {
        abort_unless($request->user()->is($quizForm->user), 403);

        $quizForm->forceDelete();

        return to_route('dashboard');
    }

    public function show(QuizForm $quizForm): Response
    {
        return Inertia::render('PublicQuiz', [
            'quizForm' => [
                'title' => $quizForm->title,
                'description' => $quizForm->description,
                'questions' => $quizForm->questions,
                'settings' => $quizForm->settings,
                'submitUrl' => route('forms.responses.store', ['quizForm' => $quizForm->slug]),
            ],
        ]);
    }

    public function storeResponse(StoreQuizResponseRequest $request, QuizForm $quizForm): RedirectResponse
    {
        $validated = $request->validated();

        QuizResponse::query()->create([
            'quiz_form_id' => $quizForm->id,
            'email' => $validated['email'] ?? null,
            'answers' => $validated['answers'],
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);

        return to_route('forms.public', ['quizForm' => $quizForm->slug]);
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
            'responses' => $this->responsesPayload($quizForm),
            'updateUrl' => route('forms.update', $quizForm),
            'publicUrl' => route('forms.public', ['quizForm' => $quizForm->slug]),
            'isPublished' => ! is_null($quizForm->published_at),
        ];
    }

    /**
     * @return array{total: int, latest: array<int, array<string, mixed>>, questions: array<int, array<string, mixed>>}
     */
    private function responsesPayload(QuizForm $quizForm): array
    {
        $responses = $quizForm->responses()
            ->latest()
            ->get(['id', 'email', 'answers', 'created_at']);
        $questions = collect($quizForm->questions ?? [])->map(function (array $question) use ($responses): array {
            $questionId = (string) ($question['id'] ?? '');
            $counts = [];
            $textAnswers = [];

            foreach ($responses as $response) {
                $answer = $response->answers[$questionId] ?? $response->answers[(int) $questionId] ?? null;

                if ($answer === null || $answer === '') {
                    continue;
                }

                foreach ((array) $answer as $value) {
                    if ($value === null || $value === '') {
                        continue;
                    }

                    $label = (string) $value;
                    $counts[$label] = ($counts[$label] ?? 0) + 1;

                    if (count($textAnswers) < 8) {
                        $textAnswers[] = $label;
                    }
                }
            }

            arsort($counts);

            return [
                'id' => $question['id'] ?? null,
                'title' => $question['title'] ?? 'Untitled question',
                'type' => $question['type'] ?? 'Short answer',
                'total' => array_sum($counts),
                'options' => collect($counts)->map(fn (int $count, string $label): array => [
                    'label' => $label,
                    'count' => $count,
                ])->values()->all(),
                'textAnswers' => $textAnswers,
            ];
        })->values()->all();

        return [
            'total' => $responses->count(),
            'latest' => $responses->take(5)->map(fn (QuizResponse $response): array => [
                'id' => $response->id,
                'email' => $response->email,
                'submittedAt' => $response->created_at->diffForHumans(),
            ])->values()->all(),
            'questions' => $questions,
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
            'folderId' => $quizForm->quiz_folder_id,
            'folder' => $quizForm->quizFolder?->name ?? $quizForm->folder,
            'editUrl' => route('forms.edit', ['quizForm' => $quizForm->slug]),
            'publicUrl' => route('forms.public', ['quizForm' => $quizForm->slug]),
            'duplicateUrl' => route('forms.duplicate', $quizForm),
            'moveFolderUrl' => route('forms.folder', $quizForm),
            'deleteUrl' => route('forms.destroy', $quizForm),
            'restoreUrl' => route('forms.restore', $quizForm),
            'forceDeleteUrl' => route('forms.force-delete', $quizForm),
            'updatedLabel' => 'Opened '.$quizForm->updated_at->format('j M Y'),
            'updatedAt' => $quizForm->updated_at->toISOString(),
            'isPublished' => ! is_null($quizForm->published_at),
            'isTrashed' => ! is_null($quizForm->deleted_at),
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
