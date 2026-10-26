<?php

use App\Models\QuizForm;
use App\Models\QuizResponse;
use App\Models\User;
use App\Services\Addons;
use App\Support\Addon;
use App\Support\DisplayTime;
use Illuminate\Support\Carbon;

/*
 * Filter Cerdas add-on: the results of an exam as one table to find, filter and sort in the app,
 * with the columns and values of the Excel workbook. Fetched only while the add-on is on.
 */
beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-10-26 08:15:00', 'UTC'));
    $this->teacher = User::factory()->create(['role' => 'guru']);
    $this->exam = QuizForm::factory()->for($this->teacher)->create([
        'title' => 'Ulangan IPA',
        'published_at' => now(),
        'settings' => ['isQuiz' => true, 'kkm' => 75],
        'questions' => [
            ['id' => 1, 'title' => 'Ibu kota Indonesia?', 'type' => 'Multiple choice', 'options' => ['Jakarta', 'Bandung'], 'answer' => 'Jakarta', 'points' => 1],
            ['id' => 2, 'title' => '<b>Rumus</b> air?', 'type' => 'Short answer', 'answer' => 'H2O', 'points' => 1],
            ['id' => 3, 'title' => 'Jelaskan fotosintesis', 'type' => 'Paragraph', 'answer' => '', 'points' => 2],
        ],
    ]);
    $this->ani = User::factory()->create(['role' => 'siswa', 'name' => 'Ani', 'nis' => '1001', 'kelas' => 'X-1']);
    $this->budi = User::factory()->create(['role' => 'siswa', 'name' => 'Budi', 'nis' => '1002', 'kelas' => 'X-2']);

    $this->aniResponse = QuizResponse::factory()->create([
        'quiz_form_id' => $this->exam->id, 'user_id' => $this->ani->id,
        'answers' => [1 => 'Jakarta', 2 => 'H2O', 3 => 'Cahaya diubah menjadi gula'], 'manual_scores' => [3 => 1],
    ]);
    // Budi got two of the three questions (drawn per student) and answered one wrong.
    QuizResponse::factory()->create([
        'quiz_form_id' => $this->exam->id, 'user_id' => $this->budi->id,
        'answers' => [1 => 'Bandung', 3 => '=HYPERLINK("http://contoh")'], 'question_ids' => [1, 3],
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

test('the gallery offers Filter Cerdas, switched per exam and on once installed', function () {
    $addon = collect($this->actingAs($this->teacher)->get(route('addons.index'))->inertiaProps('addons'))->firstWhere('key', 'smart-filter');

    expect($addon)
        ->name->toBe('Filter Cerdas')
        ->icon->toBe('list-filter')
        ->perExam->toBeTrue()
        ->onByDefault->toBeTrue()
        ->installed->toBeFalse()
        ->and($addon['reads'][0])->toContain('sama dengan isi berkas Excel');
});

test('without the add-on there is no Excel mode and no table to fetch', function () {
    expect($this->actingAs($this->teacher)->get(route('forms.edit', $this->exam))->inertiaProps('quizForm.responses.smartFilterUrl'))->toBeNull();

    $this->getJson(route('forms.smart-filter', $this->exam))->assertForbidden()->assertJsonPath('message', 'Add-on Filter Cerdas tidak aktif untuk ujian ini.');
});

test('installed, the table holds the workbook columns and every answer with whether it is right', function () {
    app(Addons::class)->install(Addon::SmartFilter, $this->teacher);

    expect($this->actingAs($this->teacher)->get(route('forms.edit', $this->exam))->inertiaProps('quizForm.responses.smartFilterUrl'))
        ->toBe(route('forms.smart-filter', $this->exam));

    $grid = $this->getJson(route('forms.smart-filter', $this->exam))
        ->assertOk()
        ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
        ->json();

    expect(array_column($grid['columns'], 'key'))->toBe(['no', 'name', 'nis', 'kelas', 'email', 'grade', 'score', 'maxScore', 'result', 'status', 'submittedAt', 'attempt', 'q:1', 'q:2', 'q:3'])
        ->and(array_column($grid['columns'], 'type'))->toBe(['number', 'text', 'text', 'text', 'text', 'number', 'number', 'number', 'text', 'text', 'datetime', 'text', 'answer', 'answer', 'answer'])
        ->and($grid['columns'][12])->toBe(['key' => 'q:1', 'label' => '1. Ibu kota Indonesia? (1 poin)', 'type' => 'answer', 'hint' => 'Kunci: Jakarta'])
        ->and($grid['columns'][13]['label'])->toBe('2. Rumus air? (1 poin)')
        ->and($grid['columns'][14]['hint'])->toBe('Esai, dinilai guru')
        ->and($grid['kkm'])->toEqual(75)
        ->and($grid['rows'])->toHaveCount(2);

    // Ani: everything right, the essay graded 1 of 2 by the teacher (partly right: not scored automatically).
    [$ani, $budi] = $grid['rows'];
    expect($ani['id'])->toBe($this->aniResponse->id)
        ->and(array_slice($ani['cells'], 0, 12))->toEqual([1, 'Ani', '1001', 'X-1', $this->aniResponse->email, 75, 3, 4, 'Tuntas', 'Selesai', DisplayTimeFormat(now()), '1 dari 1'])
        ->and(array_slice($ani['cells'], 12))->toBe(['Jakarta', 'H2O', "Cahaya diubah menjadi gula\nNilai guru: 1/2"])
        ->and($ani['marks'])->toBe(['correct', 'correct', 'ungraded']);

    // Budi: question 2 not drawn, question 1 wrong, the essay still waiting for the teacher (not yet in the maximum).
    expect(array_slice($budi['cells'], 5, 5))->toEqual([0, 0, 1, 'Belum Tuntas', 'Selesai; 1 esai/berkas belum dinilai (nilai sementara)'])
        ->and(array_slice($budi['cells'], 12))->toBe(['Bandung', '— (soal tidak didapat)', '=HYPERLINK("http://contoh")'])
        ->and($budi['marks'])->toBe(['wrong', 'absent', 'ungraded']);
});

test('switched off for one exam, or for someone who may not edit it, the table is refused', function () {
    app(Addons::class)->install(Addon::SmartFilter, $this->teacher);

    $otherTeacher = User::factory()->create(['role' => 'guru']);
    $this->actingAs($otherTeacher)->getJson(route('forms.smart-filter', $this->exam))->assertForbidden();
    $this->actingAs($this->ani)->getJson(route('forms.smart-filter', $this->exam))->assertForbidden();

    // A collaborator sees what the owner's add-on gives.
    $collaborator = User::factory()->create(['role' => 'guru']);
    $this->exam->collaborators()->attach($collaborator->id);
    $this->actingAs($collaborator)->getJson(route('forms.smart-filter', $this->exam))->assertOk()->assertJsonCount(2, 'rows');

    $this->exam->update(['settings' => [...$this->exam->settings, 'addons' => ['smart-filter' => false]]]);
    app()->forgetScopedInstances();
    $this->actingAs($this->teacher)->getJson(route('forms.smart-filter', $this->exam->refresh()))->assertForbidden();
});

test('the Excel workbook keeps the same answers after the cells were shared with the table', function () {
    app(Addons::class)->install(Addon::SmartFilter, $this->teacher);
    $grid = $this->actingAs($this->teacher)->getJson(route('forms.smart-filter', $this->exam))->json();

    $export = $this->get(route('forms.responses.export', $this->exam));
    $zip = new ZipArchive;
    $zip->open($export->baseResponse->getFile()->getPathname());
    // The answers are on the second sheet, "Jawaban Siswa", written as inline strings.
    $answerSheet = (string) $zip->getFromName('xl/worksheets/sheet2.xml');
    $zip->close();

    foreach (['Jakarta', 'H2O', 'Nilai guru: 1/2', '— (soal tidak didapat)', 'Bandung'] as $text) {
        expect($answerSheet)->toContain(htmlspecialchars($text, ENT_XML1));
    }
    expect($grid['rows'][0]['cells'][14])->toContain('Nilai guru: 1/2');
});

/**
 * How the table writes a moment: in the display time zone, minutes precision.
 */
function DisplayTimeFormat(Carbon $moment): string
{
    return $moment->copy()->setTimezone(DisplayTime::timezone())->format('Y-m-d H:i');
}
