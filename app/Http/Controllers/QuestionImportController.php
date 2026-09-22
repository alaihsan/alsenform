<?php

namespace App\Http\Controllers;

use App\Services\DocxImportService;
use App\Services\ExamViewImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuestionImportController extends Controller
{
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
            'file' => ['required', 'file', 'extensions:zip', 'max:25600'], // Max 25MB for embedded quiz images
        ]);

        try {
            $questions = $service->parseZip($request->file('file'));

            return response()->json([
                'success' => true,
                'total' => count($questions),
                'questions' => $questions,
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
