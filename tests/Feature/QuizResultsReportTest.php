<?php

use App\Models\ActivityLog;
use App\Models\QuizForm;
use App\Models\QuizResponse;
use App\Models\User;
use App\Services\QuizResultsReport;
use App\Support\XlsxWriter;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->teacher = User::factory()->create(['role' => 'guru', 'quiz_preferences' => ['default_kkm' => 70]]);
    $this->quizForm = QuizForm::factory()->for($this->teacher)->create([
        'title' => 'PTS IPA Kelas X',
        'questions' => [
            ['id' => 1, 'title' => 'Ibu kota <b>Indonesia</b>?', 'type' => 'Multiple choice', 'options' => ['Bandung', 'Jakarta'], 'answer' => 1, 'points' => 10],
            ['id' => 2, 'title' => '7 × 8 = ...', 'type' => 'Short answer', 'answer' => '56 | lima puluh enam', 'points' => 10],
            ['id' => 3, 'title' => 'Jelaskan fotosintesis', 'type' => 'Paragraph', 'points' => 10],
        ],
    ]);
    $this->path = tempnam(sys_get_temp_dir(), 'report-test-');
});

afterEach(function () {
    @unlink($this->path);
    Carbon::setTestNow();
});

/**
 * Read a worksheet as [cell reference => [value, style name]].
 *
 * @return array<string, array{0: string, 1: string}>
 */
function reportCells(string $path, int $sheet): array
{
    $zip = new ZipArchive;
    $zip->open($path);
    $xml = simplexml_load_string($zip->getFromName("xl/worksheets/sheet{$sheet}.xml"));
    $zip->close();

    $styles = array_flip(XlsxWriter::STYLES);
    $cells = [];
    foreach ($xml->sheetData->row as $row) {
        foreach ($row->c as $cell) {
            $value = isset($cell->is) ? (string) $cell->is->t : (string) $cell->v;
            $cells[(string) $cell['r']] = [$value, $styles[(int) $cell['s']] ?? 'default'];
        }
    }

    return $cells;
}

function respond(QuizForm $quizForm, string $name, string $kelas, array $answers, int $score, string $submittedAt): void
{
    $student = User::factory()->create(['role' => 'siswa', 'name' => $name, 'kelas' => $kelas, 'nis' => fake()->unique()->numerify('2024####')]);

    Carbon::setTestNow(Carbon::parse($submittedAt, 'UTC'));
    QuizResponse::create([
        'quiz_form_id' => $quizForm->id,
        'user_id' => $student->id,
        'respondent_identifier' => 'user_'.$student->id,
        'answers' => $answers,
        'score' => $score,
        'is_timeout' => false,
    ]);
    Carbon::setTestNow();
}

test('the grade recap lists students by class and name with grade, KKM result and local time', function () {
    respond($this->quizForm, 'Zaki', 'X-B', [1 => 'Jakarta', 2 => '56'], 20, '2026-10-04 01:00:00');
    respond($this->quizForm, 'Budi', 'X-A', [1 => 'Bandung'], 0, '2026-10-04 01:05:00');
    respond($this->quizForm, 'Ani', 'X-B', [1 => 'Jakarta'], 10, '2026-10-04 01:10:00');

    app(QuizResultsReport::class)->save($this->quizForm, $this->path);
    $cells = reportCells($this->path, 1);

    expect($cells['A1'][0])->toBe('Rekap Nilai: PTS IPA Kelas X')
        ->and($cells['A2'][0])->toContain('3 peserta')->toContain('KKM 70')->toContain('Tuntas 1 dari 3')
        ->and([$cells['C5'][0], $cells['C6'][0], $cells['C7'][0]])->toBe(['Budi', 'Ani', 'Zaki'])
        // The essay is graded by the teacher, so the grade is out of the 20 points with an answer key.
        ->and($cells['G7'])->toBe(['20', 'integer'])
        // Zaki: 20 of 20 points = 100
        ->and($cells['H7'])->toBe(['100', 'decimal'])
        ->and($cells['I7'])->toBe(['Tuntas', 'pass'])
        // Ani: 10 of 20 points = 50, below the teacher's KKM of 70
        ->and($cells['H6'])->toBe(['50', 'decimal'])
        ->and($cells['I6'])->toBe(['Belum Tuntas', 'fail'])
        // 01:00 UTC is 08:00 WIB
        ->and((float) $cells['K7'][0])->toEqualWithDelta(46299 + 8 / 24, 0.0001);
});

test('the answer sheet shows every answer marked right, wrong or not auto scored', function () {
    respond($this->quizForm, 'Budi', 'X-A', [1 => 'Bandung', 2 => 'lima puluh enam', 3 => 'Proses membuat makanan'], 10, '2026-10-04 01:00:00');
    respond($this->quizForm, 'Citra', 'X-A', [1 => 'Jakarta'], 10, '2026-10-04 01:00:00');

    app(QuizResultsReport::class)->save($this->quizForm, $this->path);
    $cells = reportCells($this->path, 2);

    expect($cells['F4'][0])->toBe("1. Ibu kota Indonesia? (10 poin)\nKunci: Jakarta")
        ->and($cells['G4'][0])->toContain('Kunci: 56 | lima puluh enam')
        ->and($cells['H4'][0])->toContain('Esai, dinilai guru')
        ->and($cells['F5'])->toBe(['Bandung', 'wrong'])
        ->and($cells['G5'])->toBe(['lima puluh enam', 'correct'])
        ->and($cells['H5'])->toBe(['Proses membuat makanan', 'ungraded'])
        ->and($cells['G6'])->toBe(['(tidak dijawab)', 'wrong'])
        // Share of correct answers per question
        ->and($cells['A8'][0])->toBe('Persentase jawaban benar')
        ->and($cells['F8'])->toBe(['0.5', 'summaryPercent'])
        ->and($cells['H8'][0])->toBe('-');
});

test('a quiz without responses still produces a readable workbook', function () {
    app(QuizResultsReport::class)->save($this->quizForm, $this->path);

    expect(reportCells($this->path, 1)['A4'])->toBe(['No', 'header'])
        ->and(reportCells($this->path, 2))->toHaveKey('F4');
});

test('the grade recap tells when each attempt started, how long it took, how often the page was left and its receipt', function () {
    $student = User::factory()->create(['role' => 'siswa', 'name' => 'Budi', 'kelas' => 'X-A']);
    $attempt = startedAttempt($this->quizForm, $student, ['started_at' => Carbon::parse('2026-10-04 01:00:00', 'UTC'), 'blur_count' => 3]);
    Carbon::setTestNow(Carbon::parse('2026-10-04 01:25:20', 'UTC'));
    $response = QuizResponse::create([
        'quiz_form_id' => $this->quizForm->id,
        'quiz_session_id' => $attempt->id,
        'user_id' => $student->id,
        'respondent_identifier' => 'user_'.$student->id,
        'answers' => [1 => 'Jakarta'],
    ]);
    Carbon::setTestNow();

    app(QuizResultsReport::class)->save($this->quizForm, $this->path);
    $cells = reportCells($this->path, 1);

    expect([$cells['M4'][0], $cells['N4'][0], $cells['O4'][0], $cells['P4'][0]])->toBe(['Waktu Mulai', 'Lama (menit)', 'Pindah Tab', 'Tanda Terima'])
        // 01:00 UTC is 08:00 WIB
        ->and((float) $cells['M5'][0])->toEqualWithDelta(46299 + 8 / 24, 0.0001)
        ->and($cells['N5'])->toBe(['25', 'integer'])
        ->and($cells['O5'])->toBe(['3', 'integer'])
        ->and($cells['P5'][0])->toBe($response->receiptCode());
});

test('the last sheet has the delivery figures and every error the students met', function () {
    $student = User::factory()->create(['role' => 'siswa', 'name' => 'Ani', 'kelas' => 'X-B']);
    startedAttempt($this->quizForm, $student);
    ActivityLog::factory()->create(['event' => ActivityLog::PAGE_FAILED, 'quiz_form_id' => $this->quizForm->id, 'user_id' => $student->id, 'device' => 'iPhone', 'os' => 'iOS 18.7', 'browser' => 'Safari 27.0.1', 'meta' => ['code' => 'FF-302', 'path' => '/forms/'.$this->quizForm->slug, 'message' => 'null is not an object']]);
    // The teacher's failed save is not an error of the students.
    ActivityLog::factory()->create(['event' => ActivityLog::SERVER_ERROR, 'quiz_form_id' => $this->quizForm->id, 'user_id' => $this->teacher->id, 'meta' => ['path' => '/forms/'.$this->quizForm->id, 'method' => 'PATCH', 'status' => 500]]);

    app(QuizResultsReport::class)->save($this->quizForm, $this->path);

    $zip = new ZipArchive;
    $zip->open($this->path);
    preg_match_all('/<sheet name="([^"]+)"/', $zip->getFromName('xl/workbook.xml'), $names);
    $zip->close();
    expect(end($names[1]))->toBe('Pengiriman &amp; Galat');

    $cells = reportCells($this->path, count($names[1]));
    expect($cells['A6'][0])->toBe('Galat per 100 siswa')
        ->and($cells['B6'])->toBe(['100', 'decimal'])
        ->and($cells['C6'][0])->toBe('1 halaman gagal dimuat atau galat server')
        ->and($cells['G8'][0])->toBe('Penyebab')
        ->and([$cells['C9'][0], $cells['D9'][0], $cells['F9'][0], $cells['G9'][0]])->toBe(['Halaman gagal dimuat', 'Ani', 'iPhone · iOS 18.7 · Safari 27.0.1', 'Halaman gagal ditampilkan (FF-302)'])
        ->and($cells)->not->toHaveKey('A10');
});
