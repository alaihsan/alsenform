<?php

namespace App\Http\Controllers;

use App\Models\QuizForm;
use App\Services\Remedial;
use App\Support\Addon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class FollowUpExamController extends Controller
{
    /**
     * Make a remedial or enrichment exam for the chosen students of an exam (Remedial & Pengayaan
     * add-on). It is a draft, opened in the editor next, for the teacher to check and publish.
     */
    public function store(Request $request, QuizForm $quizForm, Remedial $remedial): JsonResponse
    {
        Gate::authorize('update', $quizForm);
        abort_unless($quizForm->usesAddon(Addon::Remedial), 403, 'Add-on Remedial & Pengayaan tidak aktif untuk ujian ini.');

        $kind = $request->input('kind');

        $validated = $request->validate([
            'kind' => ['required', 'string', Rule::in(array_keys(Remedial::SOURCES))],
            'students' => ['required', 'array', 'min:1', 'max:1000'],
            'students.*' => ['required', 'string', 'max:200', 'distinct'],
            'source' => ['required', 'string', Rule::in(is_string($kind) ? (Remedial::SOURCES[$kind] ?? []) : [])],
            'title' => ['nullable', 'string', 'max:255'],
        ], [
            'students.required' => 'Pilih minimal satu siswa.',
            'students.min' => 'Pilih minimal satu siswa.',
            'source.in' => 'Pilih sumber soal.',
        ]);

        ['exam' => $followUp, 'restricted' => $restricted] = $remedial->createFollowUp(
            $quizForm,
            $request->user(),
            $validated['kind'],
            $validated['students'],
            $validated['source'],
            $validated['title'] ?? null,
        );

        $kind = $validated['kind'] === QuizForm::FOLLOW_UP_REMEDIAL ? 'remedial' : 'pengayaan';

        return response()->json([
            'id' => $followUp->id,
            'editUrl' => route('forms.edit', ['quizForm' => $followUp->slug]),
            'message' => $restricted
                ? "Ujian {$kind} dibuat sebagai draf, khusus untuk siswa yang dipilih. Periksa soalnya, lalu terbitkan."
                : "Ujian {$kind} dibuat sebagai draf. Siswa tamu tidak dapat dibatasi, jadi ujian ini terbuka bagi siapa pun yang punya tautannya.",
        ], 201);
    }
}
