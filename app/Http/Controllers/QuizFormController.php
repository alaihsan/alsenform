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

        if ($request->has('cohort_ids')) {
            $quizForm->cohorts()->sync($request->input('cohort_ids', []));
        }

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
            'pilihan-ganda' => [
                'title' => 'Penilaian Tengah Semester (PTS)',
                'description' => 'Petunjuk: Pilihlah satu jawaban yang paling tepat pada butir soal berikut.',
                'question' => 'Manakah di bawah ini yang merupakan fungsi utama Pancasila sebagai dasar negara Republik Indonesia?',
                'options' => ['Pedoman hidup bangsa dan sumber dari segala sumber hukum', 'Alat kekuasaan pemerintah pusat', 'Hukum sementara peralihan', 'Peraturan teknis kementerian'],
            ],
            'pai-arab' => [
                'title' => 'Kuis PAI & Bahasa Arab (Madinah)',
                'description' => 'Ujian pemahaman ayat Al-Qur\'an dan kaidah tajwid standar Mushaf Madinah.',
                'question' => "بِسْمِ ٱللَّهِ ٱلرَّحْمَـٰنِ ٱلرَّحِيمِ ﴿١﴾ - Hukum tajwid pada lafaz 'ٱلرَّحْمَـٰنِ' adalah?",
                'options' => ['Alif Lam Syamsiyah', 'Alif Lam Qamariyah', 'Idgham Bighunnah', 'Ikhfa Haqiqi'],
            ],
            'matematika' => [
                'title' => 'Ujian Matematika & Eksakta',
                'description' => 'Petunjuk: Selesaikan soal matematika berikut dengan perhitungan yang teliti.',
                'question' => 'Jika diketahui persamaan kuadrat $f(x) = x^2 - 5x + 6 = 0$, maka akar-akar persamaan tersebut adalah?',
                'options' => ['$x_1 = 2$ dan $x_2 = 3$', '$x_1 = -2$ dan $x_2 = -3$', '$x_1 = 1$ dan $x_2 = 6$', '$x_1 = -1$ dan $x_2 = -6$'],
            ],
            'esai-analisis' => [
                'title' => 'Ujian Esai & Uraian Analisis',
                'description' => 'Jawablah pertanyaan analisis berikut dengan uraian yang jelas, runut, dan mendalam.',
                'question' => 'Jelaskan dampak perkembangan teknologi kecerdasan buatan terhadap masa depan dunia pendidikan!',
                'options' => ['Tuliskan uraian analisis Anda secara terstruktur.'],
            ],
            'survei-belajar' => [
                'title' => 'Refleksi & Evaluasi Pembelajaran Siswa',
                'description' => 'Kuesioner evaluasi proses belajar mengajar di kelas untuk peningkatan mutu pembelajaran.',
                'question' => 'Seberapa baik pemahaman Anda terhadap materi yang diajarkan pada bab ini?',
                'options' => ['Sangat memahami materi', 'Cukup memahami materi', 'Kurang memahami materi', 'Perlu bimbingan tambahan guru'],
            ],
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
