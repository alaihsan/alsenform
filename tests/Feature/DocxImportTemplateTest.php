<?php

use App\Models\User;
use App\Services\DocxImportService;
use App\Services\DocxImportTemplate;
use App\Support\DocxWriter;
use App\Support\QuizScoring;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A Word file whose blocks are paragraphs (strings) or tables (lists of rows).
 *
 * @param  list<string|list<list<string>>>  $blocks
 */
function wordImportFile(array $blocks): UploadedFile
{
    $writer = new DocxWriter;
    foreach ($blocks as $block) {
        is_array($block) ? $writer->table($block) : $writer->paragraph($block);
    }

    $path = tempnam(sys_get_temp_dir(), 'docx');
    $writer->save($path);

    return new UploadedFile($path, 'soal.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true);
}

/**
 * The questions of the downloadable template, as the editor receives them (JSON).
 *
 * @return list<array<string, mixed>>
 */
function templateQuestions(): array
{
    $path = tempnam(sys_get_temp_dir(), 'template');
    (new DocxImportTemplate)->save($path);
    $service = app(DocxImportService::class);
    $questions = json_decode(json_encode($service->parseDocx($path)), true);
    unlink($path);

    expect($service->warnings())->toBe([]);

    return $questions;
}

test('teachers download the Word template', function () {
    $teacher = User::factory()->create(['role' => 'guru', 'nip' => '198001012005011001']);

    $response = $this->actingAs($teacher)->get(route('questions.import.template'))->assertOk();

    expect($response->headers->get('Content-Disposition'))->toContain('template_import_soal_alsenform.docx')
        ->and($response->headers->get('Content-Type'))->toBe('application/vnd.openxmlformats-officedocument.wordprocessingml.document');

    $path = $response->baseResponse->getFile()->getPathname();
    $zip = new ZipArchive;
    expect($zip->open($path))->toBeTrue();
    $document = $zip->getFromName('word/document.xml');
    $zip->close();

    $xml = simplexml_load_string($document);
    $xml->registerXPathNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
    $paragraphs = array_map('strval', $xml->xpath('//w:body/w:p/w:r/w:t'));

    // Only sample questions: the explanation lives in the Help page.
    expect($paragraphs[0])->toBe('1. Ibu kota negara Republik Indonesia adalah ...')
        ->and($document)->toContain('Tipe: Kisi Kotak Centang')
        ->and($document)->not->toContain('MULAI SOAL')
        ->and($document)->not->toContain('Aturan');
});

test('students cannot download the Word template', function () {
    $student = User::factory()->create(['role' => 'siswa', 'nis' => '20240999']);

    $this->actingAs($student)->get(route('questions.import.template'))->assertForbidden();
});

test('the template imports one sample of every question type', function () {
    $questions = templateQuestions();

    expect(array_column($questions, 'type'))->toBe([
        'Multiple choice', 'Checkboxes', 'Drop-down', 'Multiple choice', 'Short answer', 'Short answer', 'Paragraph',
        'Multiple-choice grid', 'Multiple-choice grid', 'Multiple-choice grid', 'Tick box grid',
        'Linear scale', 'Rating', 'Date', 'Time',
    ]);

    [$choice, $checkboxes, $dropDown, $trueFalse, $shortAnswer, $range, $essay, $trueFalseTable, $matching, $grid, $tickGrid, $scale, $rating, $date, $time] = $questions;

    expect($choice)->toMatchArray(['title' => 'Ibu kota negara Republik Indonesia adalah ...', 'options' => ['Surabaya', 'Jakarta', 'Bandung', 'Medan'], 'answer' => 1, 'points' => 10, 'required' => true])
        ->and($checkboxes)->toMatchArray(['options' => ['2', '4', '5', '9'], 'answer' => [0, 2], 'required' => false])
        ->and($dropDown['answer'])->toBe(2)
        ->and($trueFalse)->toMatchArray(['options' => ['Benar', 'Salah'], 'answer' => 1])
        ->and($shortAnswer['answer'])->toBe('Bandung | Kota Bandung')
        ->and($range)->toMatchArray(['answer' => '119.5..120.5', 'points' => 5])
        ->and($essay)->toMatchArray(['points' => 20])
        ->and($trueFalseTable)->toMatchArray(['columns' => ['Benar', 'Salah'], 'answer' => [0, 1, 0]])
        ->and($trueFalseTable['rows'])->toHaveCount(3)
        ->and($matching)->toMatchArray(['rows' => ['1. Jepang', '2. Prancis', '3. Mesir'], 'columns' => ['A. Paris', 'B. Kairo', 'C. Tokyo'], 'answer' => [2, 0, 1]])
        ->and($grid)->toMatchArray(['rows' => ['Paus', 'Elang', 'Kadal'], 'columns' => ['Mamalia', 'Burung', 'Reptil'], 'answer' => [0, 1, 2]])
        ->and($tickGrid)->toMatchArray(['columns' => ['Berkaki empat', 'Menyusui', 'Bertelur'], 'answer' => [[0, 1], [2], [0, 1]]])
        ->and($scale)->toMatchArray(['options' => ['1', '2', '3', '4', '5'], 'answer' => '', 'points' => 0])
        ->and($rating)->toMatchArray(['options' => ['1', '2', '3', '4', '5'], 'answer' => '', 'points' => 0])
        ->and($date['answer'])->toBe('1945-08-17')
        ->and($time['answer'])->toBe('07:00');

    $titles = implode("\n", array_column($questions, 'title'));
    expect($titles)->not->toMatch('/^(Tipe|Poin|Wajib|Skala|Jawaban):/m');
});

test('the answer keys of the template score the answers students give', function () {
    $questions = templateQuestions();
    foreach ($questions as $index => $question) {
        $questions[$index]['id'] = $index + 1;
    }

    $correctAnswers = [
        1 => 'Jakarta',
        2 => ['2', '5'],
        3 => 'Jupiter',
        4 => 'Salah',
        5 => 'kota bandung',
        6 => '120,2',
        8 => [0, 1, 0],
        9 => [2, 0, 1],
        10 => [0, 1, 2],
        11 => [[0, 1], [2], [0, 1]],
        14 => '1945-08-17',
        15 => '07:00',
    ];

    $scoring = new QuizScoring;
    foreach ($correctAnswers as $id => $answer) {
        expect($scoring->isCorrect($questions[$id - 1], $answer))->toBeTrue("question {$id}");
    }

    expect($scoring->isCorrect($questions[1], ['2', '4']))->toBeFalse()
        ->and($scoring->isCorrect($questions[6], 'jawaban esai'))->toBeNull()
        ->and($scoring->isCorrect($questions[11], '4'))->toBeNull()
        ->and($scoring->score($questions, $correctAnswers))->toBe(10 * 11 + 5);
});

test('the type, points and required lines accept common spellings', function () {
    $teacher = User::factory()->create(['role' => 'guru', 'nip' => '198001012005011001']);

    $response = $this->actingAs($teacher)->postJson(route('questions.import'), ['file' => wordImportFile([
        'Petunjuk: kerjakan dengan teliti.',
        'MULAI SOAL',
        '1. Pilih dua hewan mamalia.',
        'Jenis soal: PGK',
        'Bobot: 4',
        'Wajib diisi: ya',
        'A. Sapi', 'B. Ayam', 'C. Kucing',
        'Kunci: A dan C',
        '// catatan guru yang tidak ikut diimpor',
        '2. Ceritakan pengalaman liburanmu.',
        'Tipe: Esai',
        'Jawaban: -',
        '3. Seberapa puas kamu?',
        'tipe: skala',
        'Skala: 0-10',
        'Jawaban: -',
        '4. Bumi itu bulat.',
        'Tipe: Benar atau Salah',
        'Jawaban: B',
        '5. Sebutkan warna bendera Indonesia.',
        'Jawaban: merah putih',
        '6. Jarak dua kota pada peta 4 cm. Berapa jarak sebenarnya?',
        'Skala: 1:100.000',
        'Jawaban: 4 km',
    ])])->assertOk()->assertJsonPath('warnings', []);

    $questions = $response->json('questions');
    expect($questions)->toHaveCount(6)
        ->and($questions[0])->toMatchArray(['title' => 'Pilih dua hewan mamalia.', 'type' => 'Checkboxes', 'answer' => [0, 2], 'points' => 4, 'required' => true])
        ->and($questions[1])->toMatchArray(['type' => 'Paragraph', 'answer' => ''])
        ->and($questions[2])->toMatchArray(['type' => 'Linear scale', 'options' => array_map('strval', range(0, 10))])
        ->and($questions[3])->toMatchArray(['type' => 'Multiple choice', 'options' => ['Benar', 'Salah'], 'answer' => 0])
        ->and($questions[4])->toMatchArray(['type' => 'Short answer', 'answer' => 'merah putih', 'points' => 10, 'required' => false])
        ->and($questions[5])->toMatchArray(['type' => 'Short answer', 'title' => "Jarak dua kota pada peta 4 cm. Berapa jarak sebenarnya?\nSkala: 1:100.000"]);
});

test('questions that cannot be read as written come back with a note for the teacher', function () {
    $teacher = User::factory()->create(['role' => 'guru', 'nip' => '198001012005011001']);

    $response = $this->actingAs($teacher)->postJson(route('questions.import'), ['file' => wordImportFile([
        '1. Ibu kota Jepang adalah ...',
        'Tipe: Teka-teki',
        'A. Osaka', 'B. Tokyo',
        'Jawaban: F',
        '2. Kapan hari guru nasional?',
        'Tipe: Tanggal',
        'Jawaban: 25 November',
        '3. Pilih yang benar.',
        'Tipe: Pilihan Ganda',
        'Jawaban: A',
    ])])->assertOk();

    expect($response->json('questions.0'))->toMatchArray(['type' => 'Multiple choice', 'answer' => ''])
        ->and($response->json('questions.1'))->toMatchArray(['type' => 'Date', 'answer' => ''])
        ->and($response->json('questions.2.type'))->toBe('Short answer')
        ->and($response->json('warnings'))->toBe([
            'Soal ke-1: tipe "Teka-teki" tidak dikenal, tipe soal ditentukan otomatis.',
            'Soal ke-1: kunci "F" tidak cocok dengan pilihan mana pun; kunci dikosongkan.',
            'Soal ke-2: tanggal "25 November" tidak terbaca (contoh: 17-08-1945); kunci dikosongkan.',
            'Soal ke-3: tidak ada pilihan A–E, soal dijadikan isian singkat.',
        ]);
});

test('true or false tables can be marked with X and matching rows keep ordinary words', function () {
    $teacher = User::factory()->create(['role' => 'guru', 'nip' => '198001012005011001']);

    $response = $this->actingAs($teacher)->postJson(route('questions.import'), ['file' => wordImportFile([
        '1. Tentukan benar atau salah.',
        'Tipe: Benar Salah',
        [['No', 'Pernyataan', 'Benar', 'Salah'], ['1', 'Teknologi selalu mahal.', '', 'X'], ['2', 'Air itu cair.', 'X', '']],
        'Jawaban: lihat tabel',
        '2. Pasangkan bidang dengan contohnya.',
        'Tipe: Menjodohkan',
        [['1', 'Teknologi', 'Biologi sel'], ['2', 'Sains', 'Komputer']],
        'Jawaban: 1B, 2A',
    ])])->assertOk()->assertJsonPath('warnings', []);

    expect($response->json('questions.0'))->toMatchArray([
        'type' => 'Multiple-choice grid',
        'rows' => ['1. Teknologi selalu mahal.', '2. Air itu cair.'],
        'columns' => ['Benar', 'Salah'],
        'answer' => [1, 0],
    ])->and($response->json('questions.1'))->toMatchArray([
        'rows' => ['1. Teknologi', '2. Sains'],
        'columns' => ['A. Biologi sel', 'B. Komputer'],
        'answer' => [1, 0],
    ]);
});

test('the help page explains every sample of the template to teachers', function () {
    $teacher = User::factory()->create(['role' => 'guru', 'nip' => '198001012005011001']);

    $this->actingAs($teacher)->get(route('help'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Help')
            ->has('docxImportGuide', 15)
            ->where('docxImportGuide.0.title', 'Pilihan Ganda')
            ->where('docxImportGuide.0.lines.0', '1. Ibu kota negara Republik Indonesia adalah ...')
            ->where('docxImportGuide.1.typeAliases', fn ($aliases) => collect($aliases)->contains('kotak centang') && collect($aliases)->contains('pgk'))
            ->where('docxImportGuide.8.table.0', ['No', 'Negara', 'Ibu Kota'])
            ->where('docxImportGuide.8.answer', 'Jawaban: 1C, 2A, 3B')
        );

    $student = User::factory()->create(['role' => 'siswa', 'nis' => '20240999']);
    $this->actingAs($student)->get(route('help'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('docxImportGuide', null));
});
