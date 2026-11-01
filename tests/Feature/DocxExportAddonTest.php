<?php

use App\Models\QuestionBankItem;
use App\Models\QuizForm;
use App\Models\User;
use App\Services\Addons;
use App\Services\QuestionBank;
use App\Support\Addon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Ekspor Soal ke Word add-on: the questions of an exam, or questions chosen in the Bank Soal, as a
 * Word file in the import template's format, which the Word import reads back as the same questions.
 */
beforeEach(function () {
    Storage::fake('public');

    $picture = imagecreatetruecolor(80, 40);
    imagefill($picture, 0, 0, imagecolorallocate($picture, 79, 70, 229));
    ob_start();
    imagewebp($picture);
    Storage::disk('public')->put('media/peta.webp', (string) ob_get_clean());

    $this->teacher = User::factory()->create(['role' => 'guru', 'nip' => '198001012005011001', 'name' => 'Bu Rina']);
    $this->exam = QuizForm::factory()->for($this->teacher)->create([
        'title' => 'Ulangan <b>IPA</b> Kelas 7',
        'description' => 'Kerjakan dengan <i>jujur</i>.',
        'questions' => exportedExamQuestions(),
    ]);
});

/**
 * An exam with every kind of question the template knows, their formatting, and items it does not know.
 *
 * @return list<array<string, mixed>>
 */
function exportedExamQuestions(): array
{
    $question = fn (int $id, string $type, string $title, array $more = []): array => [
        'id' => $id, 'type' => $type, 'title' => $title, 'description' => '', 'options' => [], 'answer' => '', 'required' => false, 'points' => 1, 'media' => [], ...$more,
    ];

    return [
        ['id' => 1, 'type' => 'Section', 'title' => 'Bagian A', 'description' => 'Pilihan ganda'],
        $question(2, 'Multiple choice', 'Ibu kota <b>Indonesia</b> adalah <mark data-color="yellow">...</mark>', [
            'description' => 'Pilih <u>satu</u> jawaban.',
            'options' => ['Surabaya', '<i>Jakarta</i>', 'Bandung', 'Medan', 'Makassar', 'Lainnya'],
            'answer' => 1,
            'required' => true,
            'points' => 2,
            'explanation' => 'Jakarta menjadi ibu kota sejak <b>1945</b>.',
            'media' => [
                ['type' => 'image', 'url' => '/storage/media/peta.webp', 'width' => '50%', 'align' => 'center'],
                ['type' => 'video', 'url' => 'https://www.youtube.com/watch?v=abc123'],
            ],
        ]),
        $question(3, 'Checkboxes', 'Manakah yang termasuk bilangan prima?', ['options' => ['2', '4', '5', '9'], 'answer' => [0, 2]]),
        $question(4, 'Drop-down', 'Planet terbesar di tata surya adalah ...', ['options' => ['Mars', 'Bumi', 'Jupiter'], 'answer' => 2]),
        $question(5, 'Multiple choice', 'Matahari terbit dari arah barat.', ['options' => ['Benar', 'Salah'], 'answer' => 1]),
        $question(6, 'Short answer', 'Hasil dari $\frac{1}{2} + \frac{1}{4}$ adalah ...', ['answer' => '0.75 | 3/4', 'points' => 3]),
        $question(7, 'Paragraph', "Perhatikan data berikut:\nA. Jakarta\nB. Bandung\nJelaskan <mark data-color=\"green\">perbedaannya</mark>!", ['answer' => "Jakarta ibu kota negara.\nBandung ibu kota provinsi."]),
        $question(8, 'Multiple choice', 'بِسْمِ ٱللَّهِ ٱلرَّحْمَـٰنِ ٱلرَّحِيمِ: hukum bacaan <b>ٱلرَّحْمَـٰنِ</b> adalah ...', [
            'options' => ['إِدْغَامٌ', 'إِظْهَارٌ', 'Alif lam syamsiyah'],
            'answer' => 2,
        ]),
        $question(9, 'Multiple-choice grid', 'Tentukan benar atau salah setiap pernyataan berikut!', [
            'rows' => ['1. Air mendidih pada suhu 100 °C.', '2. Bulan memancarkan cahaya <i>sendiri</i>.'],
            'columns' => ['Benar', 'Salah'],
            'answer' => [0, 1],
        ]),
        $question(10, 'Multiple-choice grid', 'Pasangkan negara dengan ibu kotanya!', [
            'rows' => ['1. Jepang', '2. Prancis', '3. Mesir'],
            'columns' => ['A. Paris', 'B. Kairo', 'C. Tokyo'],
            'answer' => [2, 0, 1],
        ]),
        $question(11, 'Multiple-choice grid', 'Kelompokkan hewan berikut!', [
            'rows' => ['Paus', '<i>Elang</i>'],
            'columns' => ['Mamalia', '<b>Burung</b>'],
            'answer' => [0, 1],
        ]),
        $question(12, 'Tick box grid', 'Centang ciri setiap hewan!', [
            'rows' => ['Kucing', 'Ayam'],
            'columns' => ['Berkaki empat', 'Bertelur'],
            'answer' => [[0], [1]],
        ]),
        $question(13, 'Linear scale', 'Seberapa paham kamu dengan materi ini?', ['options' => ['1', '2', '3', '4', '5']]),
        $question(14, 'Rating', 'Beri nilai cara guru menjelaskan.', ['options' => ['1', '2', '3', '4', '5'], 'answer' => '4']),
        $question(15, 'Date', 'Kapan Proklamasi dibacakan?', ['answer' => '1945-08-17']),
        $question(16, 'Time', 'Pukul berapa upacara dimulai?', ['answer' => '07:00']),
        $question(17, 'File upload', 'Unggah laporan praktikum'),
        ['id' => 18, 'type' => 'Title and description', 'title' => 'Penutup', 'description' => 'Periksa kembali jawabanmu.'],
        $question(19, 'Short answer', '<b>Seluruh soal ini tebal</b>', ['answer' => 'ya']),
    ];
}

/**
 * What the import has to give back of a question.
 *
 * @param  array<string, mixed>  $question
 * @return array<string, mixed>
 */
function comparableQuestion(array $question): array
{
    return [
        'type' => $question['type'],
        'title' => $question['title'],
        'description' => $question['description'] ?? '',
        'options' => $question['options'] ?? [],
        'rows' => $question['rows'] ?? [],
        'columns' => $question['columns'] ?? [],
        'answer' => $question['answer'] ?? '',
        'required' => $question['required'] ?? false,
        'points' => $question['points'] ?? 1,
        'explanation' => $question['explanation'] ?? null,
        'pictures' => count(array_filter($question['media'] ?? [], fn (array $media): bool => $media['type'] === 'image')),
    ];
}

/**
 * The questions the Word import reads from a downloaded export.
 *
 * @return array{questions: list<array<string, mixed>>, warnings: list<string>}
 */
function importExport(TestResponse $download, User $teacher): array
{
    $file = new UploadedFile($download->baseResponse->getFile()->getPathname(), 'ekspor.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true);

    return test()->actingAs($teacher)->postJson(route('questions.import'), ['file' => $file])->assertOk()->json();
}

function exportedDocument(TestResponse $download): string
{
    $zip = new ZipArchive;
    $zip->open($download->baseResponse->getFile()->getPathname());
    $document = (string) $zip->getFromName('word/document.xml');
    $zip->close();

    return $document;
}

test('without the add-on there is no export in the editor or the bank, and the routes refuse', function () {
    $this->actingAs($this->teacher)->get(route('forms.edit', $this->exam))
        ->assertInertia(fn (Assert $page) => $page->where('quizForm.addons', fn ($addons) => collect($addons)->firstWhere('key', 'docx-export')['installed'] === false));
    $this->actingAs($this->teacher)->get(route('question-bank.page'))
        ->assertInertia(fn (Assert $page) => $page->component('QuestionBank/Index')->where('docxExportUrl', null));

    app(QuestionBank::class)->sync($this->exam);
    $this->get(route('forms.docx-export', $this->exam))->assertForbidden();
    $this->postJson(route('question-bank.docx-export'), ['ids' => QuestionBankItem::query()->pluck('id')->all()])->assertForbidden();
});

test('an exported exam imports back as the same questions, formatting, keys and points included', function () {
    app(Addons::class)->install(Addon::DocxExport, $this->teacher);

    $download = $this->actingAs($this->teacher)->get(route('forms.docx-export', $this->exam))->assertOk();
    expect($download->headers->get('Content-Disposition'))->toContain('soal_ulangan_ipa_kelas_7.docx')
        ->and($download->headers->get('Content-Type'))->toBe('application/vnd.openxmlformats-officedocument.wordprocessingml.document')
        // The file upload question is only noted.
        ->and($download->headers->get('X-Exported-Questions'))->toBe('16');

    $imported = importExport($download, $this->teacher);
    $expected = array_values(array_filter(exportedExamQuestions(), fn (array $item): bool => ! in_array($item['type'], ['Section', 'Title and description', 'File upload'], true)));

    expect($imported['warnings'])->toBe([])
        ->and(array_map('comparableQuestion', $imported['questions']))->toBe(array_map('comparableQuestion', $expected));
});

test('the exported file is written like the import template', function () {
    app(Addons::class)->install(Addon::DocxExport, $this->teacher);

    $download = $this->actingAs($this->teacher)->get(route('forms.docx-export', $this->exam));
    $document = exportedDocument($download);

    $zip = new ZipArchive;
    $zip->open($download->baseResponse->getFile()->getPathname());
    $picture = $zip->getFromName('word/media/image1.png');
    $zip->close();

    expect($document)
        // The exam above the line the import starts from.
        ->toContain('>Ulangan </w:t>')->toContain('>jujur</w:t>')
        ->toContain('MULAI SOAL')
        // The template's lines.
        ->toContain('>1. </w:t>')
        ->toContain('Tipe: Pilihan Ganda')->toContain('Poin: 2')->toContain('Wajib: Ya')
        ->toContain('>F. </w:t>')
        ->toContain('Jawaban: B')
        ->toContain('Tipe: Benar Salah')->toContain('Jawaban: Salah')
        ->toContain('Jawaban: B, S')
        ->toContain('Tipe: Menjodohkan')->toContain('Jawaban: 1C, 2A, 3B')
        ->toContain('Tipe: Kisi Kotak Centang')->toContain('Jawaban: lihat tabel')
        ->toContain('Skala: 1-5')
        ->toContain('Jawaban: 17-08-1945')
        ->toContain('Keterangan: ')->toContain('Pembahasan: ')
        // Formatting: highlighter, underline, Arabic right to left.
        ->toContain('<w:shd w:val="clear" w:color="auto" w:fill="FDE047"/>')
        ->toContain('<w:u w:val="single"/>')
        ->toContain('<w:rtl/>')
        ->toContain('<w:bidi/>')
        // What the template cannot hold is a note the import skips.
        ->toContain('// Bagian: ')
        ->toContain('// Video: https://www.youtube.com/watch?v=abc123')
        ->toContain('// Soal &quot;Unggah laporan praktikum&quot; tidak diekspor: tipe Unggah Berkas tidak ada di templat Import Soal.')
        // A multi-line title stays one paragraph, so its "A." line is not taken for an option.
        ->toContain('Perhatikan data berikut:</w:t><w:br/><w:t xml:space="preserve">A. Jakarta');

    // The WebP upload goes in as a PNG picture Word shows.
    expect($picture)->not->toBeFalse()
        ->and(getimagesizefromstring($picture)[2])->toBe(IMAGETYPE_PNG);
});

test('questions chosen in the bank are exported in the order they were chosen', function () {
    app(QuestionBank::class)->sync($this->exam);
    $item = fn (string $title): int => QuestionBankItem::query()->where('search_text', 'like', "%{$title}%")->value('id');
    $otherTeachersItem = QuestionBankItem::query()->create([
        'user_id' => User::factory()->create(['role' => 'guru'])->id,
        'source_question_id' => '1',
        'fingerprint' => sha1('lain'),
        'type' => 'Short answer',
        'search_text' => 'soal guru lain',
        'question' => ['title' => 'Soal guru lain', 'type' => 'Short answer', 'answer' => 'x'],
    ]);

    app(Addons::class)->install(Addon::DocxExport, $this->teacher);
    $this->actingAs($this->teacher)->get(route('question-bank.page'))
        ->assertInertia(fn (Assert $page) => $page->where('docxExportUrl', route('question-bank.docx-export')));

    $download = $this->actingAs($this->teacher)
        ->post(route('question-bank.docx-export'), ['ids' => [$item('pasangkan negara'), $item('planet terbesar'), $otherTeachersItem->id]])
        ->assertOk();
    expect($download->headers->get('Content-Disposition'))->toContain('soal_bank_soal_')
        ->and($download->headers->get('X-Exported-Questions'))->toBe('2')
        ->and(exportedDocument($download))->toContain('Bank Soal Bu Rina · 2 soal · 2 poin');

    $imported = importExport($download, $this->teacher);
    $questions = collect(exportedExamQuestions())->keyBy('id');

    expect($imported['warnings'])->toBe([])
        ->and(array_map('comparableQuestion', $imported['questions']))->toBe([comparableQuestion($questions[10]), comparableQuestion($questions[4])]);

    $this->actingAs($this->teacher)->postJson(route('question-bank.docx-export'), ['ids' => []])->assertJsonValidationErrors('ids');
    $this->actingAs($this->teacher)->postJson(route('question-bank.docx-export'), ['ids' => [$otherTeachersItem->id]])->assertStatus(422);
});

test('only teachers who may edit the exam and have the add-on installed export it', function () {
    $collaborator = User::factory()->create(['role' => 'guru']);
    $this->exam->collaborators()->attach($collaborator->id);
    $stranger = User::factory()->create(['role' => 'guru']);
    $addons = app(Addons::class);
    $addons->install(Addon::DocxExport, $this->teacher);
    $addons->install(Addon::DocxExport, $stranger);

    // The add-on is the teacher's own: a collaborator without it has no export.
    $this->actingAs($collaborator)->get(route('forms.docx-export', $this->exam))->assertForbidden();
    $this->actingAs($stranger)->get(route('forms.docx-export', $this->exam))->assertForbidden();

    $addons->install(Addon::DocxExport, $collaborator);
    $this->actingAs($collaborator)->get(route('forms.docx-export', $this->exam))->assertOk();

    $this->actingAs(User::factory()->create(['role' => 'siswa', 'nis' => '20240999']))
        ->get(route('forms.docx-export', $this->exam))->assertForbidden();
});

test('an admin installs the export for every teacher', function () {
    app(Addons::class)->install(Addon::DocxExport, User::factory()->create(['role' => 'admin']), forEveryone: true);

    $this->actingAs($this->teacher)->get(route('forms.docx-export', $this->exam))->assertOk();
});

test('the Word import reads the formatting teachers give text in Word', function () {
    $paragraph = fn (string $runs): string => "<w:p>{$runs}</w:p>";
    $run = fn (string $text, string $properties = ''): string => "<w:r><w:rPr>{$properties}</w:rPr><w:t xml:space=\"preserve\">{$text}</w:t></w:r>";
    $body = $paragraph($run('1. Ibu kota ').$run('Jepang', '<w:b/>').$run(' adalah ').$run('...', '<w:highlight w:val="cyan"/>'))
        .$paragraph($run('A.').'<w:r><w:tab/></w:r>'.$run('Osaka'))
        .$paragraph($run('B. ').$run('Tokyo', '<w:i/><w:u w:val="double"/>'))
        .$paragraph($run('Jawaban: B'))
        .$paragraph($run('Pembahasan: Tokyo ibu kota sejak 1868.'))
        .$paragraph($run('2. Tulis lafaz ').$run('ٱللَّهِ', '<w:bCs/><w:rtl/>').'<w:r><w:br/></w:r>'.$run('dengan benar.'))
        .$paragraph($run('Tipe: Uraian'))
        .$paragraph($run('Jawaban: -'));

    $path = tempnam(sys_get_temp_dir(), 'docx');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/></Types>');
    $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'.$body.'</w:body></w:document>');
    $zip->close();

    $response = $this->actingAs($this->teacher)->postJson(route('questions.import'), [
        'file' => new UploadedFile($path, 'soal.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true),
    ])->assertOk()->assertJsonPath('warnings', []);

    expect($response->json('questions.0'))->toMatchArray([
        'title' => 'Ibu kota <b>Jepang</b> adalah <mark data-color="blue">...</mark>',
        'options' => ['Osaka', '<i><u>Tokyo</u></i>'],
        'answer' => 1,
        'explanation' => 'Tokyo ibu kota sejak 1868.',
    ])->and($response->json('questions.1'))->toMatchArray([
        'type' => 'Paragraph',
        // A line break carries no formatting, as the editor writes it.
        'title' => "Tulis lafaz <b>ٱللَّهِ\n</b>dengan benar.",
    ]);
});
