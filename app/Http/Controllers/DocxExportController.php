<?php

namespace App\Http\Controllers;

use App\Models\QuizForm;
use App\Services\Addons;
use App\Services\DocxQuestionExport;
use App\Services\QuestionBank;
use App\Support\Addon;
use App\Support\DisplayTime;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Ekspor Soal ke Word add-on: the questions of an exam, or the questions chosen in the Bank Soal,
 * as a Word file in the format of the import template. Only there while the teacher has the
 * add-on installed.
 */
class DocxExportController extends Controller
{
    /**
     * Every question of the exam as it is saved.
     */
    public function form(Request $request, QuizForm $quizForm, Addons $addons, DocxQuestionExport $export): BinaryFileResponse
    {
        abort_unless($quizForm->canBeEditedBy($request->user()), 403);
        $this->ensureInstalled($request, $addons);

        return $this->download($export->filename($quizForm->title), fn (string $path) => $export->save(
            $path,
            is_array($quizForm->questions) ? $quizForm->questions : [],
            $quizForm->title,
            (string) $quizForm->description,
            'Falcon Forms',
        ));
    }

    /**
     * The questions ticked in the Bank Soal, in the order they were ticked.
     */
    public function bank(Request $request, Addons $addons, QuestionBank $questionBank, DocxQuestionExport $export): BinaryFileResponse
    {
        $this->ensureInstalled($request, $addons);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['required', 'integer', 'distinct'],
        ]);

        $questions = $questionBank->questions($request->user(), array_map('intval', $validated['ids']));
        abort_if($questions === [], 422, 'Soal yang dipilih tidak ditemukan di bank soal.');

        return $this->download(
            'soal_bank_soal_'.DisplayTime::now()->format('Y-m-d').'.docx',
            fn (string $path) => $export->save($path, $questions, 'Soal dari Bank Soal', '', 'Bank Soal '.$request->user()->name),
        );
    }

    /**
     * Without the add-on there is no export for the teacher.
     */
    protected function ensureInstalled(Request $request, Addons $addons): void
    {
        abort_unless($addons->isInstalledFor(Addon::DocxExport, $request->user()), 403, 'Add-on Ekspor Soal ke Word belum dipasang.');
    }

    /**
     * The file, with the number of questions written in it (types the template lacks are only noted).
     *
     * @param  callable(string): int  $write
     */
    protected function download(string $filename, callable $write): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'falcon-docx-');
        $written = $write($path);

        return response()
            ->download($path, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'Cache-Control' => 'no-store, max-age=0',
                'X-Exported-Questions' => (string) $written,
            ])
            ->deleteFileAfterSend();
    }
}
