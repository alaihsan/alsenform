<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function createSampleExamViewZip(array $itemsXml, array $files = []): UploadedFile
{
    $tempZipPath = tempnam(sys_get_temp_dir(), 'ev_zip_').'.zip';
    $zip = new ZipArchive;
    $zip->open($tempZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    // Add imsmanifest.xml
    $manifestXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<manifest identifier="MANIFEST_01">
  <resources>
    <resource identifier="res00001" type="assessment/x-bb-pool" href="res00001.dat">
      <file href="res00001.dat"/>
    </resource>
  </resources>
</manifest>
XML;
    $zip->addFromString('imsmanifest.xml', $manifestXml);

    // Build items XML
    $itemsString = implode("\n", $itemsXml);
    $datXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<questestinterop>
  <assessment title="Sample ExamView Test">
    {$itemsString}
  </assessment>
</questestinterop>
XML;
    $zip->addFromString('res00001.dat', $datXml);

    // Add extra files (e.g. images)
    foreach ($files as $name => $content) {
        $zip->addFromString($name, $content);
    }

    $zip->close();

    return new UploadedFile($tempZipPath, 'examview_test.zip', 'application/zip', null, true);
}

test('examview: successfully imports multiple choice, true/false, checkboxes, and essay questions with answer keys', function () {
    Storage::fake('public');
    $teacher = User::factory()->create(['role' => 'guru']);

    $itemsXml = [
        // 1. Multiple Choice Question
        <<<'XML'
    <item title="Question 1">
      <itemmetadata>
        <bbmd_questiontype>Multiple Choice</bbmd_questiontype>
        <qmd_weighting>15</qmd_weighting>
      </itemmetadata>
      <presentation>
        <flow>
          <material>
            <mattext texttype="text/html"><![CDATA[<p>Ibu kota negara Indonesia adalah?</p>]]></mattext>
          </material>
          <response_lid ident="response">
            <render_choice>
              <response_label ident="ans_0">
                <material><mattext>Bandung</mattext></material>
              </response_label>
              <response_label ident="ans_1">
                <material><mattext>Jakarta</mattext></material>
              </response_label>
              <response_label ident="ans_2">
                <material><mattext>Surabaya</mattext></material>
              </response_label>
            </render_choice>
          </response_lid>
        </flow>
      </presentation>
      <resprocessing>
        <respcondition title="correct">
          <conditionvar>
            <varequal respident="response">ans_1</varequal>
          </conditionvar>
          <setvar action="Set" varname="SCORE">15.0</setvar>
        </respcondition>
      </resprocessing>
    </item>
XML,
        // 2. True / False Question
        <<<'XML'
    <item title="Question 2">
      <itemmetadata>
        <bbmd_questiontype>True/False</bbmd_questiontype>
      </itemmetadata>
      <presentation>
        <flow>
          <material>
            <mattext texttype="text/html"><![CDATA[<p>Matahari terbit dari sebelah timur.</p>]]></mattext>
          </material>
          <response_lid ident="response">
            <render_choice>
              <response_label ident="true">
                <material><mattext>Benar</mattext></material>
              </response_label>
              <response_label ident="false">
                <material><mattext>Salah</mattext></material>
              </response_label>
            </render_choice>
          </response_lid>
        </flow>
      </presentation>
      <resprocessing>
        <respcondition title="correct">
          <conditionvar>
            <varequal respident="response">true</varequal>
          </conditionvar>
          <setvar action="Set" varname="SCORE">10.0</setvar>
        </respcondition>
      </resprocessing>
    </item>
XML,
        // 3. Multiple Answer (Checkboxes) Question
        <<<'XML'
    <item title="Question 3">
      <itemmetadata>
        <bbmd_questiontype>Multiple Answer</bbmd_questiontype>
      </itemmetadata>
      <presentation>
        <flow>
          <material>
            <mattext texttype="text/html"><![CDATA[Pilih bilangan prima genap:]]></mattext>
          </material>
          <response_lid ident="response">
            <render_choice>
              <response_label ident="opt_2">
                <material><mattext>2</mattext></material>
              </response_label>
              <response_label ident="opt_4">
                <material><mattext>4</mattext></material>
              </response_label>
            </render_choice>
          </response_lid>
        </flow>
      </presentation>
      <resprocessing>
        <respcondition title="correct">
          <conditionvar>
            <varequal respident="response">opt_2</varequal>
          </conditionvar>
          <setvar action="Set" varname="SCORE">10.0</setvar>
        </respcondition>
      </resprocessing>
    </item>
XML,
        // 4. Essay (Paragraph) Question
        <<<'XML'
    <item title="Question 4">
      <itemmetadata>
        <bbmd_questiontype>Essay</bbmd_questiontype>
      </itemmetadata>
      <presentation>
        <flow>
          <material>
            <mattext texttype="text/html"><![CDATA[Jelaskan proses terjadinya hujan!]]></mattext>
          </material>
        </flow>
      </presentation>
      <resprocessing>
        <setvar action="Set" varname="SCORE">20.0</setvar>
      </resprocessing>
    </item>
XML,
    ];

    $zipFile = createSampleExamViewZip($itemsXml);

    $response = $this->actingAs($teacher)->post(route('questions.import.examview'), [
        'file' => $zipFile,
    ]);

    $response->assertOk();
    $data = $response->json();

    expect($data['success'])->toBeTrue()
        ->and($data['total'])->toBe(4);

    $questions = $data['questions'];

    // Assert Question 1: Multiple Choice
    expect($questions[0]['type'])->toBe('Multiple choice')
        ->and($questions[0]['title'])->toBe('Ibu kota negara Indonesia adalah?')
        ->and($questions[0]['options'])->toEqual(['Bandung', 'Jakarta', 'Surabaya'])
        ->and($questions[0]['answer'])->toBe(1) // 'Jakarta' index 1
        ->and($questions[0]['points'])->toBe(15);

    // Assert Question 2: True/False
    expect($questions[1]['type'])->toBe('Multiple choice')
        ->and($questions[1]['title'])->toBe('Matahari terbit dari sebelah timur.')
        ->and($questions[1]['options'])->toEqual(['Benar', 'Salah'])
        ->and($questions[1]['answer'])->toBe(0); // 'Benar' index 0

    // Assert Question 3: Checkboxes
    expect($questions[2]['type'])->toBe('Checkboxes')
        ->and($questions[2]['title'])->toBe('Pilih bilangan prima genap:')
        ->and($questions[2]['answer'])->toContain('2');

    // Assert Question 4: Essay
    expect($questions[3]['type'])->toBe('Paragraph')
        ->and($questions[3]['title'])->toBe('Jelaskan proses terjadinya hujan!')
        ->and($questions[3]['points'])->toBe(20);
});

test('examview: extracts embedded question images to public storage', function () {
    Storage::fake('public');
    $teacher = User::factory()->create(['role' => 'guru']);

    $sampleImageBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');

    $itemsXml = [
        <<<'XML'
    <item title="Question with Diagram">
      <itemmetadata>
        <bbmd_questiontype>Multiple Choice</bbmd_questiontype>
      </itemmetadata>
      <presentation>
        <flow>
          <material>
            <mattext texttype="text/html"><![CDATA[<p>Perhatikan gambar berikut: <img src="@X@EmbeddedFile.location@X@diagram01.png" /></p>]]></mattext>
          </material>
          <response_lid ident="response">
            <render_choice>
              <response_label ident="ans_0">
                <material><mattext>Pilihan A</mattext></material>
              </response_label>
              <response_label ident="ans_1">
                <material><mattext>Pilihan B</mattext></material>
              </response_label>
            </render_choice>
          </response_lid>
        </flow>
      </presentation>
      <resprocessing>
        <respcondition title="correct">
          <conditionvar>
            <varequal respident="response">ans_0</varequal>
          </conditionvar>
          <setvar action="Set" varname="SCORE">10.0</setvar>
        </respcondition>
      </resprocessing>
    </item>
XML,
    ];

    $zipFile = createSampleExamViewZip($itemsXml, [
        'res00001/diagram01.png' => $sampleImageBytes,
    ]);

    $response = $this->actingAs($teacher)->post(route('questions.import.examview'), [
        'file' => $zipFile,
    ]);

    $response->assertOk();
    $data = $response->json('questions');

    expect($data)->toHaveCount(1)
        ->and($data[0]['media'])->toHaveCount(1)
        ->and($data[0]['media'][0]['type'])->toBe('image')
        ->and($data[0]['media'][0]['url'])->toContain('/storage/media/examview/');

    // Assert file was physically stored
    $storedFiles = Storage::disk('public')->allFiles('media/examview');
    expect($storedFiles)->not->toBeEmpty();
});

test('examview: rejects invalid or non-zip files', function () {
    $teacher = User::factory()->create(['role' => 'guru']);

    // Non-zip file
    $fakeTxt = UploadedFile::fake()->create('soal.txt', 100, 'text/plain');
    $response1 = $this->actingAs($teacher)->postJson(route('questions.import.examview'), [
        'file' => $fakeTxt,
    ]);
    $response1->assertStatus(422);

    // Corrupt zip without QTI XML
    $tempZip = tempnam(sys_get_temp_dir(), 'empty_zip_').'.zip';
    $zip = new ZipArchive;
    $zip->open($tempZip, ZipArchive::CREATE);
    $zip->addFromString('hello.txt', 'not a qti file');
    $zip->close();

    $fakeZip = new UploadedFile($tempZip, 'empty.zip', 'application/zip', null, true);
    $response2 = $this->actingAs($teacher)->postJson(route('questions.import.examview'), [
        'file' => $fakeZip,
    ]);
    $response2->assertStatus(422);
    expect($response2->json('message'))->toContain('tidak memuat data bank soal');
});
