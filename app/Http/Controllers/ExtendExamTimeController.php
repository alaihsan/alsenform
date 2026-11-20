<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExtendExamTimeRequest;
use App\Models\ActivityLog;
use App\Models\QuizForm;
use App\Support\ActivityRecorder;
use Illuminate\Http\JsonResponse;

class ExtendExamTimeController extends Controller
{
    /**
     * "Tambah waktu": give every student still working on the exam extra minutes, for example
     * after the server was down while the timers kept running. Open exam pages pick up the new
     * end with their next status poll.
     */
    public function __invoke(ExtendExamTimeRequest $request, QuizForm $quizForm, ActivityRecorder $activityRecorder): JsonResponse
    {
        $minutes = (int) $request->validated('minutes');

        $extended = $quizForm->extendRunningAttempts($minutes);

        $activityRecorder->record(
            ActivityLog::EXAM_TIME_EXTENDED,
            $request,
            $request->user(),
            $quizForm,
            meta: ['minutes' => $minutes, 'attempts' => $extended],
        );

        return response()->json([
            'message' => $extended > 0
                ? "Waktu ditambah {$minutes} menit untuk {$extended} siswa yang sedang mengerjakan."
                : 'Tidak ada siswa yang sedang mengerjakan ujian ini. Tidak ada waktu yang ditambahkan.',
            'minutes' => $minutes,
            'extended' => $extended,
        ]);
    }
}
