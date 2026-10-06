<?php

namespace App\Http\Controllers;

use App\Services\DocxImportService;
use App\Services\DocxImportTemplate;
use App\Services\ExamViewImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class QuestionImportController extends Controller
{
    /**
     * Download the Word template that explains the import rules with an example of every question type.
     */
    public function template(DocxImportTemplate $template): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'alsenform-template');
        $template->save($path);

        return response()
            ->download($path, $template->filename(), [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ])
            ->deleteFileAfterSend();
    }

    /**
     * Parse uploaded .docx file and return parsed questions.
     */
    public function import(Request $request, DocxImportService $service): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'extensions:docx', 'max:10240'], // Max 10MB
        ]);

        try {
            $questions = $service->parseDocx($request->file('file'));

            return response()->json([
                'success' => true,
                'total' => count($questions),
                'questions' => $questions,
                'warnings' => $service->warnings(),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat memproses dokumen Word: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Parse uploaded ExamView Blackboard ZIP file and return parsed questions with options and media.
     */
    public function importExamView(Request $request, ExamViewImportService $service): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'extensions:zip', 'max:65536'], // Max 64MB for embedded quiz images
        ], [
            'file.uploaded' => 'Berkas ZIP gagal diunggah karena melebihi batas upload server PHP. Jalankan server dengan "php artisan lan:serve" (batas 64MB) atau naikkan upload_max_filesize dan post_max_size di php.ini.',
            'file.max' => 'Ukuran berkas ZIP maksimal 64MB.',
            'file.extensions' => 'Berkas harus berformat .zip hasil ekspor ExamView (Blackboard).',
        ]);

        try {
            $questions = $service->parseZip($request->file('file'));

            return response()->json([
                'success' => true,
                'total' => count($questions),
                'questions' => $questions,
                'warnings' => $service->warnings(),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat memproses file ExamView: '.$e->getMessage(),
            ], 500);
        }
    }
}
