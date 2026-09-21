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

test('examview: successfully parses XML with default namespace and HTML entities', function () {
    Storage::fake('public');
    $teacher = User::factory()->create(['role' => 'guru']);

    $tempZipPath = tempnam(sys_get_temp_dir(), 'ev_ns_').'.zip';
    $zip = new ZipArchive;
    $zip->open($tempZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    // QTI XML with standard namespace, &deg;, &nbsp;, and unescaped ampersand
    $qtiXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<questestinterop xmlns="http://www.imsglobal.org/xsd/ims_qtiasiv1p2">
  <assessment title="Ujian IPA & Fisika">
    <section>
      <item title="Suhu Air" ident="QUE_101">
        <itemmetadata>
          <bbmd_questiontype>Multiple Choice</bbmd_questiontype>
          <qmd_weighting>20</qmd_weighting>
        </itemmetadata>
        <presentation>
          <flow>
            <material>
              <mattext texttype="text/html"><![CDATA[<p>Titik didih air murni adalah 100 &deg;C &nbsp; pada tekanan 1 atm.</p>]]></mattext>
            </material>
            <response_lid ident="response">
              <render_choice>
                <response_label ident="A">
                  <material><mattext>50 &deg;C</mattext></material>
                </response_label>
                <response_label ident="B">
                  <material><mattext>100 &deg;C</mattext></material>
                </response_label>
                <response_label ident="C">
                  <material><mattext>150 &deg;C</mattext></material>
                </response_label>
              </render_choice>
            </response_lid>
          </flow>
        </presentation>
        <resprocessing>
          <respcondition title="correct">
            <conditionvar>
              <varequal respident="response">B</varequal>
            </conditionvar>
            <setvar action="Set" varname="SCORE">20.0</setvar>
          </respcondition>
        </resprocessing>
      </item>
    </section>
  </assessment>
</questestinterop>
XML;

    $zip->addFromString('res00001.dat', $qtiXml);
    $zip->close();

    $zipFile = new UploadedFile($tempZipPath, 'examview_ns.zip', 'application/zip', null, true);

    $response = $this->actingAs($teacher)->post(route('questions.import.examview'), [
        'file' => $zipFile,
    ]);

    $response->assertOk();
    $questions = $response->json('questions');

    expect($questions)->toHaveCount(1)
        ->and($questions[0]['title'])->toContain('100 °C')
        ->and($questions[0]['options'])->toHaveCount(3)
        ->and($questions[0]['answer'])->toBe(1) // Option B -> index 1
        ->and($questions[0]['points'])->toBe(20);
});

test('examview: successfully parses Blackboard 7.1+ POOL format', function () {
    Storage::fake('public');
    $teacher = User::factory()->create(['role' => 'guru']);

    $tempZipPath = tempnam(sys_get_temp_dir(), 'ev_bb7_').'.zip';
    $zip = new ZipArchive;
    $zip->open($tempZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    $poolXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<POOL>
  <COURSEID value="BIO101"/>
  <TITLE value="Bank Soal Biologi"/>
  <QUESTION id="q_001" type="MC">
    <DATED type="MC"/>
    <BODY>
      <TEXT>Organ tubuh manusia yang berfungsi memompa darah adalah...</TEXT>
    </BODY>
    <ANSWER id="ans_paru" position="1">
      <TEXT>Paru-paru</TEXT>
    </ANSWER>
    <ANSWER id="ans_jantung" position="2">
      <TEXT>Jantung</TEXT>
    </ANSWER>
    <ANSWER id="ans_ginjal" position="3">
      <TEXT>Ginjal</TEXT>
    </ANSWER>
    <GRADABLE>
      <POINTS_POSSIBLE>15</POINTS_POSSIBLE>
      <CORRECTANSWER answer_id="ans_jantung"/>
    </GRADABLE>
  </QUESTION>
</POOL>
XML;

    $zip->addFromString('res00001.dat', $poolXml);
    $zip->close();

    $zipFile = new UploadedFile($tempZipPath, 'examview_bb7.zip', 'application/zip', null, true);

    $response = $this->actingAs($teacher)->post(route('questions.import.examview'), [
        'file' => $zipFile,
    ]);

    $response->assertOk();
    $questions = $response->json('questions');

    expect($questions)->toHaveCount(1)
        ->and($questions[0]['title'])->toBe('Organ tubuh manusia yang berfungsi memompa darah adalah...')
        ->and($questions[0]['options'])->toEqual(['Paru-paru', 'Jantung', 'Ginjal'])
        ->and($questions[0]['answer'])->toBe(1) // Jantung index 1
        ->and($questions[0]['points'])->toBe(15);
});

test('examview: gives clear instruction when raw .bnk file is zipped', function () {
    $teacher = User::factory()->create(['role' => 'guru']);

    $tempZipPath = tempnam(sys_get_temp_dir(), 'ev_raw_bnk_').'.zip';
    $zip = new ZipArchive;
    $zip->open($tempZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('soal_uas.bnk', 'binary bnk content');
    $zip->close();

    $zipFile = new UploadedFile($tempZipPath, 'soal_uas.zip', 'application/zip', null, true);

    $response = $this->actingAs($teacher)->postJson(route('questions.import.examview'), [
        'file' => $zipFile,
    ]);

    $response->assertStatus(422);
    expect($response->json('message'))->toContain('berkas .bnk mentah')
        ->and($response->json('message'))->toContain('File -> Export -> Blackboard');
});

test('examview: successfully parses Blackboard 7.1+ Checkboxes (Multiple Answer) with multiple correct keys', function () {
    $teacher = User::factory()->create(['role' => 'guru']);

    $tempZipPath = tempnam(sys_get_temp_dir(), 'ev_bb7_ma_').'.zip';
    $zip = new ZipArchive;
    $zip->open($tempZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    $manifestXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<manifest identifier="MANIFEST_BB7_MA">
  <resources>
    <resource identifier="res00001" type="assessment/x-bb-pool" href="res00001.dat"/>
  </resources>
</manifest>
XML;
    $zip->addFromString('imsmanifest.xml', $manifestXml);

    $poolXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<POOL>
  <QUESTION id="q_ma_1" type="Multiple Answer">
    <BODY>
      <TEXT>Pilihlah bilangan prima di bawah ini:</TEXT>
    </BODY>
    <ANSWER id="ans_2">
      <TEXT>2</TEXT>
    </ANSWER>
    <ANSWER id="ans_3">
      <TEXT>3</TEXT>
    </ANSWER>
    <ANSWER id="ans_4">
      <TEXT>4</TEXT>
    </ANSWER>
    <GRADABLE>
      <POINTS_POSSIBLE>20</POINTS_POSSIBLE>
      <CORRECTANSWER answer_id="ans_2"/>
      <CORRECTANSWER answer_id="ans_3"/>
    </GRADABLE>
  </QUESTION>
</POOL>
XML;

    $zip->addFromString('res00001.dat', $poolXml);
    $zip->close();

    $zipFile = new UploadedFile($tempZipPath, 'examview_bb7_ma.zip', 'application/zip', null, true);

    $response = $this->actingAs($teacher)->post(route('questions.import.examview'), [
        'file' => $zipFile,
    ]);

    $response->assertOk();
    $questions = $response->json('questions');

    expect($questions)->toHaveCount(1)
        ->and($questions[0]['type'])->toBe('Checkboxes')
        ->and($questions[0]['options'])->toEqual(['2', '3', '4'])
        ->and($questions[0]['answer'])->toEqual(['2', '3'])
        ->and($questions[0]['points'])->toBe(20);
});

test('examview: successfully parses QTI 1.2 mat_formattedtext and Matching (Multiple-choice grid)', function () {
    $teacher = User::factory()->create(['role' => 'guru']);

    $itemsXml = [
        // 1. Multiple Choice with mat_formattedtext inside QUESTION_BLOCK
        <<<'XML'
    <item>
      <itemmetadata>
        <bbmd_questiontype>Multiple Choice</bbmd_questiontype>
        <qmd_weighting>10</qmd_weighting>
      </itemmetadata>
      <presentation>
        <flow class="Block">
          <flow class="QUESTION_BLOCK">
            <material>
              <mat_extension>
                <mat_formattedtext type="SMART_TEXT">Siapakah penjahit bendera Merah Putih?</mat_formattedtext>
              </mat_extension>
            </material>
          </flow>
          <flow class="RESPONSE_BLOCK">
            <response_lid ident="response">
              <render_choice>
                <response_label ident="ans_1">
                  <material>
                    <mat_extension>
                      <mat_formattedtext type="SMART_TEXT">Fatmawati</mat_formattedtext>
                    </mat_extension>
                  </material>
                </response_label>
                <response_label ident="ans_2">
                  <material>
                    <mat_extension>
                      <mat_formattedtext type="SMART_TEXT">Sayuti Melik</mat_formattedtext>
                    </mat_extension>
                  </material>
                </response_label>
              </render_choice>
            </response_lid>
          </flow>
        </flow>
      </presentation>
      <resprocessing>
        <respcondition title="correct">
          <conditionvar>
            <varequal respident="response">ans_1</varequal>
          </conditionvar>
          <setvar action="Set" varname="SCORE">10.0</setvar>
        </respcondition>
      </resprocessing>
    </item>
XML,
        // 2. Matching question
        <<<'XML'
    <item>
      <itemmetadata>
        <bbmd_questiontype>Matching</bbmd_questiontype>
        <qmd_absolutescore_max>4.0</qmd_absolutescore_max>
      </itemmetadata>
      <presentation>
        <flow class="Block">
          <flow class="QUESTION_BLOCK">
            <material>
              <mat_extension>
                <mat_formattedtext type="SMART_TEXT">Pasangkan pernyataan berikut:</mat_formattedtext>
              </mat_extension>
            </material>
          </flow>
          <flow class="RESPONSE_BLOCK">
            <flow class="Block">
              <response_lid ident="answer_1">
                <render_choice>
                  <flow_label class="Block">
                    <response_label ident="answer_2"/>
                    <response_label ident="answer_3"/>
                  </flow_label>
                </render_choice>
              </response_lid>
              <material>
                <mat_extension>
                  <mat_formattedtext type="SMART_TEXT">Pernyataan 1</mat_formattedtext>
                </mat_extension>
              </material>
            </flow>
          </flow>
          <flow class="RIGHT_MATCH_BLOCK">
            <flow class="Block">
              <material>
                <mat_extension>
                  <mat_formattedtext type="SMART_TEXT">Benar</mat_formattedtext>
                </mat_extension>
              </material>
            </flow>
            <flow class="Block">
              <material>
                <mat_extension>
                  <mat_formattedtext type="SMART_TEXT">Salah</mat_formattedtext>
                </mat_extension>
              </material>
            </flow>
          </flow>
        </flow>
      </presentation>
      <resprocessing>
        <respcondition>
          <conditionvar>
            <varequal respident="answer_1">answer_2</varequal>
          </conditionvar>
        </respcondition>
      </resprocessing>
    </item>
XML,
    ];

    $zipFile = createSampleExamViewZip($itemsXml);

    $response = $this->actingAs($teacher)->post(route('questions.import.examview'), [
        'file' => $zipFile,
    ]);

    $response->assertOk();
    $questions = $response->json('questions');

    expect($questions)->toHaveCount(2)
        ->and($questions[0]['title'])->toBe('Siapakah penjahit bendera Merah Putih?')
        ->and($questions[0]['options'])->toEqual(['Fatmawati', 'Sayuti Melik'])
        ->and($questions[0]['answer'])->toBe(0)
        ->and($questions[1]['type'])->toBe('Multiple-choice grid')
        ->and($questions[1]['title'])->toBe('Pasangkan pernyataan berikut:')
        ->and($questions[1]['rows'])->toEqual(['Pernyataan 1'])
        ->and($questions[1]['columns'])->toEqual(['Benar', 'Salah'])
        ->and((array) $questions[1]['answer'])->toEqual(['0' => 0]);
});
