<?php

use App\Models\QuizForm;
use App\Models\QuizResponse;
use App\Models\User;
use App\Services\Addons;
use App\Support\Addon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->teacher = User::factory()->create(['role' => 'guru']);
    $this->exam = QuizForm::factory()->for($this->teacher)->create([
        'title' => 'Ulangan Gaya',
        'published_at' => now(),
        'questions' => [
            ['id' => 1, 'title' => 'Benda berat jatuh lebih cepat?', 'type' => 'Multiple choice', 'options' => ['Ya', 'Tidak', 'Tergantung'], 'answer' => 'Tidak', 'points' => 1],
            ['id' => 2, 'title' => 'Satuan gaya?', 'type' => 'Multiple choice', 'options' => ['Newton', 'Joule'], 'answer' => 'Newton', 'points' => 1],
            ['id' => 3, 'title' => 'Jelaskan hukum Newton', 'type' => 'Paragraph', 'points' => 5],
        ],
    ]);
    app(Addons::class)->install(Addon::Misconception, $this->teacher);

    $respond = function (string $name, ?string $first, ?array $doubts, string $second = 'Newton') {
        $student = User::factory()->create(['role' => 'siswa', 'name' => $name, 'kelas' => 'VIII-A']);

        return QuizResponse::query()->create([
            'quiz_form_id' => $this->exam->id,
            'user_id' => $student->id,
            'respondent_identifier' => 'user_'.$student->id,
            'answers' => array_filter([1 => $first, 2 => $second]),
            'doubtful_question_ids' => $doubts,
            'score' => 0,
        ]);
    };

    $respond('Ani', 'Tidak', []);            // right, sure: paham
    $respond('Budi', 'Tidak', ['1']);        // right, not sure: menebak
    $this->citra = $respond('Citra', 'Ya', []); // wrong, sure: miskonsepsi
    $respond('Dodi', 'Ya', []);              // wrong, sure: miskonsepsi
    $respond('Eka', 'Tergantung', ['1']);    // wrong, not sure: belum paham
    $respond('Fajar', null, []);             // empty: belum paham
    $respond('Gita', 'Ya', null);            // handed in before the button existed: left out
});

test('every answer falls in one of the four groups, and a question many are sure of wrongly is flagged', function () {
    // Citra's second attempt does not count: the first one shows what she believed.
    QuizResponse::query()->create([
        'quiz_form_id' => $this->exam->id, 'user_id' => $this->citra->user_id, 'respondent_identifier' => $this->citra->respondent_identifier,
        'answers' => [1 => 'Tidak', 2 => 'Newton'], 'doubtful_question_ids' => [], 'score' => 2,
    ]);

    $this->actingAs($this->teacher)->get(route('forms.edit', $this->exam))
        ->assertInertia(fn (Assert $page) => $page
            ->where('quizForm.responses.misconceptions.respondents', 6)
            ->where('quizForm.responses.misconceptions.withoutData', 1)
            ->where('quizForm.responses.misconceptions.usedDoubt', 2)
            ->where('quizForm.responses.misconceptions.trustworthy', true)
            ->where('quizForm.responses.misconceptions.flagged', 1)
            // The essay is not scored automatically: not in the map.
            ->has('quizForm.responses.misconceptions.questions', 2)
            ->where('quizForm.responses.misconceptions.questions.0.number', 1)
            ->where('quizForm.responses.misconceptions.questions.0.paham', 1)
            ->where('quizForm.responses.misconceptions.questions.0.menebak', 1)
            ->where('quizForm.responses.misconceptions.questions.0.belumPaham', 2)
            ->where('quizForm.responses.misconceptions.questions.0.miskonsepsi', 2)
            ->where('quizForm.responses.misconceptions.questions.0.shares.miskonsepsi', 0.33)
            ->where('quizForm.responses.misconceptions.questions.0.flagged', true)
            ->where('quizForm.responses.misconceptions.questions.0.commonWrong', ['answer' => 'A. Ya', 'count' => 2])
            ->where('quizForm.responses.misconceptions.questions.1.paham', 6)
            ->where('quizForm.responses.misconceptions.questions.1.flagged', false)
            ->where('quizForm.responses.misconceptions.questions.1.note', 'Sebagian besar siswa paham.')
            // Students with a misconception first.
            ->where('quizForm.responses.misconceptions.students.0.name', 'Citra')
            ->where('quizForm.responses.misconceptions.students.0.miskonsepsi', [1])
            ->where('quizForm.responses.misconceptions.students.1.name', 'Dodi'));
});

test('with few students using the button the map says it cannot be trusted', function () {
    QuizResponse::query()->whereNotNull('doubtful_question_ids')->update(['doubtful_question_ids' => json_encode([])]);

    $this->actingAs($this->teacher)->get(route('forms.edit', $this->exam))
        ->assertInertia(fn (Assert $page) => $page
            ->where('quizForm.responses.misconceptions.usedDoubt', 0)
            ->where('quizForm.responses.misconceptions.trustworthy', false));
});

test('the Excel workbook gets a Peta Miskonsepsi sheet only with the add-on on', function () {
    $sheetsOf = function (): string {
        $export = $this->actingAs($this->teacher)->get(route('forms.responses.export', $this->exam));
        $zip = new ZipArchive;
        $zip->open($export->baseResponse->getFile()->getPathname());
        $xml = (string) $zip->getFromName('xl/workbook.xml');
        for ($index = 1; $index <= 6; $index++) {
            $xml .= (string) $zip->getFromName("xl/worksheets/sheet{$index}.xml");
        }
        $zip->close();

        return $xml;
    };

    expect($sheetsOf())->toContain('Peta Miskonsepsi')->toContain('A. Ya (2 siswa)');

    $this->exam->update(['settings' => ['addons' => ['misconception' => false]]]);
    app()->forgetScopedInstances();

    expect($sheetsOf())->not->toContain('Peta Miskonsepsi');
    $this->actingAs($this->teacher)->get(route('forms.edit', $this->exam))
        ->assertInertia(fn (Assert $page) => $page->where('quizForm.responses.misconceptions', null));
});
