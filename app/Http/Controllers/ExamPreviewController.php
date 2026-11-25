<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\QuizForm;
use App\Models\QuizSession;
use App\Support\ActivityRecorder;
use App\Support\DisplayTime;
use App\Support\HtmlToPlainText;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pratinjau: the teacher looks at the exam as a student sees it (the editor opens it in a new tab).
 * "Tutup pratinjau" ends that preview and shows when it was opened and closed and how long it took.
 */
class ExamPreviewController extends Controller
{
    /**
     * Tutup pratinjau: the preview ends (its answers are not handed in) and the next one starts afresh.
     */
    public function close(Request $request, QuizForm $quizForm, ActivityRecorder $activityRecorder): RedirectResponse|JsonResponse
    {
        $request->validate([
            'session_token' => ['required', 'string', 'max:100'],
        ]);

        $user = $request->user();
        abort_unless($quizForm->isPreviewedBy($user), 403, 'Hanya guru pemilik atau kolaborator ujian ini yang dapat menutup pratinjau.');

        $session = QuizSession::query()
            ->where('quiz_form_id', $quizForm->id)
            ->where('user_id', $user->id)
            ->where('session_token', $request->input('session_token'))
            ->firstOrFail();

        if ($session->closePreview()) {
            $activityRecorder->record(
                ActivityLog::PREVIEW_CLOSED,
                $request,
                $user,
                $quizForm,
                meta: [
                    'opened_at' => $session->created_at->toISOString(),
                    'closed_at' => $session->preview_closed_at->toISOString(),
                    'seconds' => $session->previewSeconds(),
                ],
            );
        }

        $summaryUrl = route('forms.preview.show', ['quizForm' => $quizForm->slug, 'quizSession' => $session->id]);

        // The exam page asks with JSON, so a failure stays on it (with its message) instead of an error page.
        return $request->wantsJson()
            ? response()->json(['url' => $summaryUrl])
            : redirect($summaryUrl);
    }

    /**
     * What a closed preview took: when it was opened, when it was closed, and for how long.
     */
    public function show(Request $request, QuizForm $quizForm, QuizSession $quizSession, HtmlToPlainText $plainText): Response
    {
        $user = $request->user();

        abort_unless(
            $quizForm->canBeEditedBy($user)
                && $quizSession->quiz_form_id === $quizForm->id
                && $quizSession->user_id === $user->id
                && $quizSession->preview_closed_at !== null,
            404,
        );

        $seconds = (int) $quizSession->previewSeconds();

        return Inertia::render('PreviewSummary', [
            'quizForm' => [
                'title' => Str::limit($plainText->convert((string) $quizForm->title), 160) ?: 'Tanpa judul',
                'editUrl' => route('forms.edit', ['quizForm' => $quizForm->slug]),
                'previewUrl' => route('forms.public', ['quizForm' => $quizForm->slug]),
            ],
            'preview' => [
                'openedAt' => $this->moment($quizSession->created_at),
                'closedAt' => $this->moment($quizSession->preview_closed_at),
                'seconds' => $seconds,
                'duration' => DisplayTime::duration($seconds),
                // Handed in with "Kirim" before it was closed: the answers are among the responses.
                'handedIn' => $quizSession->submitted_at !== null && $quizSession->submitted_at->lt($quizSession->preview_closed_at),
            ],
        ]);
    }

    /**
     * @return array{iso: string, date: string, time: string, timezone: string}
     */
    protected function moment(CarbonInterface $time): array
    {
        $local = DisplayTime::of($time);

        return [
            'iso' => $time->toISOString(),
            'date' => $local->translatedFormat('l, j F Y'),
            'time' => $local->format('H:i:s'),
            'timezone' => $local->format('T'),
        ];
    }
}
