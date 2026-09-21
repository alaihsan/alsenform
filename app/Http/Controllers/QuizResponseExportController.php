<?php

namespace App\Http\Controllers;

use App\Models\QuizForm;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QuizResponseExportController extends Controller
{
    public function export(QuizForm $quizForm): StreamedResponse
    {
        abort_unless($quizForm->canBeEditedBy(auth()->user()), 403);

        $safeTitle = Str::slug($quizForm->title) ?: 'kuis';
        $filename = "rekap_nilai_{$safeTitle}_".now()->format('Ymd_His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $questions = $quizForm->questions ?? [];
        $maxScore = 0;
        foreach ($questions as $q) {
            $maxScore += isset($q['points']) ? (int) $q['points'] : 1;
        }

        $responses = $quizForm->responses()
            ->with(['user:id,name,email,nis,kelas'])
            ->orderBy('created_at', 'asc')
            ->get(['id', 'quiz_form_id', 'user_id', 'respondent_identifier', 'email', 'score', 'is_timeout', 'created_at']);

        return response()->stream(function () use ($responses, $maxScore): void {
            $file = fopen('php://output', 'w');

            // UTF-8 BOM for Microsoft Excel compatibility
            fwrite($file, "\xEF\xBB\xBF");

            fputcsv($file, [
                'No',
                'NIS',
                'Nama Lengkap',
                'Kelas / Cohort',
                'Email',
                'Skor Poin',
                'Total Poin Maksimal',
                'Nilai Akhir (Skala 100)',
                'Status Pengerjaan',
                'Waktu Selesai',
            ]);

            $index = 1;
            foreach ($responses as $response) {
                $score = $response->score ?? 0;
                $grade = $maxScore > 0 ? round(($score / $maxScore) * 100, 1) : 0;
                $status = $response->is_timeout ? 'Waktu Habis (Timeout)' : 'Selesai';

                fputcsv($file, [
                    $index++,
                    $response->user?->nis ?? '-',
                    $response->user?->name ?? 'Anonim',
                    $response->user?->kelas ?? '-',
                    $response->email ?? $response->user?->email ?? '-',
                    $score,
                    $maxScore,
                    $grade,
                    $status,
                    $response->created_at->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($file);
        }, 200, $headers);
    }
}
