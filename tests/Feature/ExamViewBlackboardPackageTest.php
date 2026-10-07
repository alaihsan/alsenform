<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Build a ZIP package like the ones ExamView writes with File > Export > Blackboard.
 *
 * @param  array<string, string>  $files
 */
function blackboardPackage(array $files): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'bb_pkg_').'.zip';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    foreach ($files as $name => $content) {
        $zip->addFromString($name, $content);
    }
    $zip->close();

    return new UploadedFile($path, 'examview_export.zip', 'application/zip', null, true);
}

function testImage(string $format, array $color = [40, 120, 200]): string
{
    $image = imagecreatetruecolor(24, 16);
    imagefilledrectangle($image, 0, 0, 24, 16, imagecolorallocate($image, ...$color));
    ob_start();
    match ($format) {
        'gif' => imagegif($image),
        'jpg' => imagejpeg($image),
        'bmp' => imagebmp($image),
        default => imagepng($image),
    };
    imagedestroy($image);

    return (string) ob_get_clean();
}

/**
 * Blackboard 7.1 - 9.x QTI item.
 */
function qtiItem(string $type, string $points, string $presentation, string $resprocessing, string $extra = ''): string
{
    return <<<XML
    <item title="ExamView" maxattempts="0">
      <itemmetadata>
        <bbmd_asi_object_id>_{$type}_1</bbmd_asi_object_id>
        <bbmd_asitype>Item</bbmd_asitype>
        <bbmd_assessmenttype>Test</bbmd_assessmenttype>
        <bbmd_sectiontype>Subsection</bbmd_sectiontype>
        <bbmd_questiontype>{$type}</bbmd_questiontype>
        <bbmd_is_from_cartridge>false</bbmd_is_from_cartridge>
        <bbmd_numbertype>letter_upper</bbmd_numbertype>
        <bbmd_partialcredit>false</bbmd_partialcredit>
        <qmd_absolutescore_max>{$points}</qmd_absolutescore_max>
        <qmd_weighting>0.0</qmd_weighting>
        <qmd_instructornotes/>
      </itemmetadata>
      <presentation>
        <flow class="Block">
          {$presentation}
        </flow>
      </presentation>
      <resprocessing scoremodel="SumOfScores">
        <outcomes>
          <decvar defaultval="0.0" maxvalue="{$points}" minvalue="0.0" varname="SCORE" vartype="Decimal"/>
        </outcomes>
        {$resprocessing}
      </resprocessing>
      {$extra}
    </item>
XML;
}

function qtiQuestionBlock(string $escapedHtml): string
{
    return <<<XML
    <flow class="QUESTION_BLOCK">
      <flow class="FORMATTED_TEXT_BLOCK">
        <material><mat_extension><mat_formattedtext type="HTML">{$escapedHtml}</mat_formattedtext></mat_extension></material>
      </flow>
      <flow class="FILE_BLOCK"><material/></flow>
      <flow class="LINK_BLOCK"><material><mattext charset="us-ascii" texttype="text/plain" uri="" xml:space="default"/></material></flow>
    </flow>
XML;
}

/**
 * @param  array<string, string>  $labels  ident => escaped HTML
 */
function qtiChoices(array $labels, string $cardinality = 'Single', bool $plainText = false): string
{
    $xml = '';
    foreach ($labels as $ident => $html) {
        $material = $plainText
            ? "<flow_mat class=\"Block\"><material><mattext charset=\"us-ascii\" texttype=\"text/plain\" xml:space=\"default\">{$html}</mattext></material></flow_mat>"
            : "<flow_mat class=\"FORMATTED_TEXT_BLOCK\"><material><mat_extension><mat_formattedtext type=\"HTML\">{$html}</mat_formattedtext></mat_extension></material></flow_mat>";
        $xml .= "<response_label ident=\"{$ident}\" rarea=\"Ellipse\" rrange=\"Exact\" shuffle=\"Yes\">{$material}</response_label>\n";
    }

    return <<<XML
    <flow class="RESPONSE_BLOCK">
      <response_lid ident="response" rcardinality="{$cardinality}" rtiming="No">
        <render_choice maxnumber="0" minnumber="0" shuffle="No">
          <flow_label class="Block">{$xml}</flow_label>
        </render_choice>
      </response_lid>
    </flow>
XML;
}

function qtiCorrect(string $conditionvar): string
{
    return <<<XML
    <respcondition title="incorrect">
      <conditionvar><other/></conditionvar>
      <setvar action="Set" variablename="SCORE">0.0</setvar>
      <displayfeedback feedbacktype="Response" linkrefid="incorrect"/>
    </respcondition>
    <respcondition title="correct">
      <conditionvar>{$conditionvar}</conditionvar>
      <setvar action="Set" variablename="SCORE">SCORE.max</setvar>
      <displayfeedback feedbacktype="Response" linkrefid="correct"/>
    </respcondition>
XML;
}

function qtiTest(string $items): string
{
    return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<questestinterop>
  <assessment title="Ujian IPA Bab 3">
    <assessmentmetadata>
      <bbmd_asitype>Assessment</bbmd_asitype>
      <bbmd_assessmenttype>Test</bbmd_assessmenttype>
      <qmd_absolutescore_max>99.0</qmd_absolutescore_max>
    </assessmentmetadata>
    <rubric view="All"><flow_mat class="Block"><material><mat_extension><mat_formattedtext type="HTML"/></mat_extension></material></flow_mat></rubric>
    <presentation_material><flow_mat class="Block"><material><mat_extension><mat_formattedtext type="HTML">Petunjuk umum ujian</mat_formattedtext></mat_extension></material></flow_mat></presentation_material>
    <section>
      <sectionmetadata><bbmd_sectiontype>Subsection</bbmd_sectiontype></sectionmetadata>
      {$items}
    </section>
  </assessment>
</questestinterop>
XML;
}

function bb9Manifest(string $resources): string
{
    return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<manifest identifier="man00001" xmlns:bb="http://www.blackboard.com/content-packaging/">
  <organizations default="toc00001"><organization identifier="toc00001"/></organizations>
  <resources>
    {$resources}
  </resources>
</manifest>
XML;
}

function importPackage(UploadedFile $package): array
{
    $teacher = User::factory()->create(['role' => 'guru']);

    return test()->actingAs($teacher)
        ->post(route('questions.import.examview'), ['file' => $package])
        ->assertOk()
        ->json();
}

beforeEach(function () {
    Storage::fake('public');
});

test('blackboard 7.1-9.0 export: every question type with answers, points and images', function () {
    $items = implode("\n", [
        // 1. Multiple choice: inline image, "<" in the text, sub/superscript, "incorrect" condition listed first.
        qtiItem('Multiple Choice', '2.0',
            qtiQuestionBlock('&lt;p&gt;Perhatikan diagram &lt;img src="@X@EmbeddedFile.location@X@image001.gif" alt="diagram"&gt; berikut. Jika x &amp;lt; 3 maka x&lt;sup&gt;2&lt;/sup&gt; + H&lt;sub&gt;2&lt;/sub&gt;O bernilai ...&lt;/p&gt;')
            .qtiChoices(['a1' => '&lt;p&gt;Kurang dari 9&lt;/p&gt;', 'a2' => 'Sama dengan 9', 'a3' => 'Lebih dari 9 &amp;amp; genap']),
            qtiCorrect('<varequal case="No" respident="response">a1</varequal>')
            .'<respcondition><conditionvar><varequal case="No" respident="a2"/></conditionvar><setvar action="Set" variablename="SCORE">0</setvar></respcondition>'),
        // 2. True / False
        qtiItem('True/False', '1.0',
            qtiQuestionBlock('Bumi berbentuk datar.').qtiChoices(['true' => 'true', 'false' => 'false'], 'Single', true),
            qtiCorrect('<varequal case="No" respident="response">false</varequal>')),
        // 3. Multiple answer
        qtiItem('Multiple Answer', '3.0',
            qtiQuestionBlock('Manakah yang termasuk planet?').qtiChoices(['m1' => 'Merkurius', 'm2' => 'Bulan', 'm3' => 'Mars', 'm4' => 'Matahari'], 'Multiple'),
            qtiCorrect('<and><varequal case="No" respident="response">m1</varequal><not><varequal case="No" respident="response">m2</varequal></not><varequal case="No" respident="response">m3</varequal><not><varequal case="No" respident="response">m4</varequal></not></and>')),
        // 4. Fill in the blank with two accepted answers
        qtiItem('Fill in the Blank', '2.0',
            qtiQuestionBlock('Ibu kota Indonesia adalah ____.')
            .'<flow class="RESPONSE_BLOCK"><response_str ident="response" rcardinality="Single" rtiming="No"><render_fib charset="us-ascii" columns="127" encoding="UTF_8" fibtype="String" maxchars="0" maxnumber="0" minnumber="0" prompt="Box" rows="1"/></response_str></flow>',
            qtiCorrect('<varequal case="No" respident="response">Jakarta</varequal>')
            .'<respcondition title="correct"><conditionvar><varequal case="No" respident="response">DKI Jakarta</varequal></conditionvar><setvar action="Set" variablename="SCORE">SCORE.max</setvar></respcondition>'),
        // 5. Essay with sample answer
        qtiItem('Essay', '5.0',
            qtiQuestionBlock('&lt;p&gt;Jelaskan proses fotosintesis!&lt;/p&gt;')
            .'<flow class="RESPONSE_BLOCK"><response_str ident="response" rcardinality="Single" rtiming="No"><render_fib charset="us-ascii" columns="127" encoding="UTF_8" fibtype="String" maxchars="0" maxnumber="0" minnumber="0" prompt="Box" rows="8"/></response_str></flow>',
            '<respcondition title="correct"><conditionvar/><setvar action="Set" variablename="SCORE">SCORE.max</setvar></respcondition>',
            '<itemfeedback ident="solution" view="All"><solution feedbackstyle="Complete" view="All"><solutionmaterial><flow_mat class="Block"><material><mat_extension><mat_formattedtext type="HTML">&lt;p&gt;Tumbuhan mengubah CO&lt;sub&gt;2&lt;/sub&gt; dan air menjadi glukosa.&lt;/p&gt;</mat_formattedtext></mat_extension></material></flow_mat></solutionmaterial></solution></itemfeedback>'),
        // 6. Matching
        qtiItem('Matching', '3.0',
            qtiQuestionBlock('Pasangkan organ dengan fungsinya.')
            .'<flow class="RESPONSE_BLOCK">'
            .collect(['p1' => 'Jantung', 'p2' => 'Paru-paru', 'p3' => 'Ginjal'])->map(fn ($text, $ident) => "<flow class=\"Block\"><flow class=\"FORMATTED_TEXT_BLOCK\"><material><mat_extension><mat_formattedtext type=\"HTML\">{$text}</mat_formattedtext></mat_extension></material></flow><response_lid ident=\"{$ident}\" rcardinality=\"Single\" rtiming=\"No\"><render_choice maxnumber=\"0\" minnumber=\"0\" shuffle=\"Yes\"><flow_label class=\"Block\"><response_label ident=\"{$ident}c1\" rarea=\"Ellipse\" rrange=\"Exact\" shuffle=\"Yes\"/><response_label ident=\"{$ident}c2\" rarea=\"Ellipse\" rrange=\"Exact\" shuffle=\"Yes\"/><response_label ident=\"{$ident}c3\" rarea=\"Ellipse\" rrange=\"Exact\" shuffle=\"Yes\"/></flow_label></render_choice></response_lid></flow>")->implode('')
            .'</flow><flow class="RIGHT_MATCH_BLOCK">'
            .collect(['Menyaring darah', 'Memompa darah', 'Pertukaran gas'])->map(fn ($text) => "<flow class=\"Block\"><flow class=\"FORMATTED_TEXT_BLOCK\"><material><mat_extension><mat_formattedtext type=\"HTML\">{$text}</mat_formattedtext></mat_extension></material></flow></flow>")->implode('')
            .'</flow>',
            '<respcondition><conditionvar><varequal case="No" respident="p1">p1c2</varequal></conditionvar><setvar action="Set" variablename="SCORE">1</setvar><displayfeedback feedbacktype="Response" linkrefid="p1"/></respcondition>'
            .'<respcondition><conditionvar><varequal case="No" respident="p2">p2c3</varequal></conditionvar><setvar action="Set" variablename="SCORE">1</setvar></respcondition>'
            .'<respcondition><conditionvar><varequal case="No" respident="p3">p3c1</varequal></conditionvar><setvar action="Set" variablename="SCORE">1</setvar></respcondition>'),
        // 7. Multiple choice whose answers are pictures
        qtiItem('Multiple Choice', '1.0',
            qtiQuestionBlock('Grafik manakah yang menunjukkan fungsi kuadrat?')
            .qtiChoices(['g1' => '&lt;img src="@X@EmbeddedFile.location@X@pilihanA.png"&gt;', 'g2' => '&lt;img src="@X@EmbeddedFile.location@X@pilihanB.png"&gt;']),
            qtiCorrect('<varequal case="No" respident="response">g2</varequal>')),
        // 8. Numeric with tolerance
        qtiItem('Numeric', '2.0',
            qtiQuestionBlock('Berapakah hasil 25 : 2,5?')
            .'<flow class="RESPONSE_BLOCK"><response_num ident="response" rcardinality="Single" rtiming="No"><render_fib charset="us-ascii" encoding="UTF_8" fibtype="Decimal" maxnumber="0" minnumber="0" prompt="Box" rows="1" columns="10"/></response_num></flow>',
            qtiCorrect('<varequal case="No" respident="response">10</varequal>')
            .'<respcondition title="range"><conditionvar><vargte respident="response">9.5</vargte><varlte respident="response">10.5</varlte></conditionvar><setvar action="Set" variablename="SCORE">SCORE.max</setvar></respcondition>'),
        // 9. Ordering
        qtiItem('Ordering', '2.0',
            qtiQuestionBlock('Urutkan waktu dari yang paling awal.').qtiChoices(['o1' => 'Pagi', 'o2' => 'Malam', 'o3' => 'Siang'], 'Ordered'),
            qtiCorrect('<varequal case="No" respident="response">o1</varequal><varequal case="No" respident="response">o3</varequal><varequal case="No" respident="response">o2</varequal>')),
        // 10. Either / Or
        qtiItem('Either/Or', '1.0',
            qtiQuestionBlock('Apakah air mendidih pada 100 &amp;deg;C di permukaan laut?').qtiChoices(['yes_no.yes' => 'yes_no.yes', 'yes_no.no' => 'yes_no.no'], 'Single', true),
            qtiCorrect('<varequal case="No" respident="response">yes_no.yes</varequal>')),
    ]);

    $data = importPackage(blackboardPackage([
        'imsmanifest.xml' => bb9Manifest('<resource bb:file="res00001.dat" bb:title="Ujian IPA Bab 3" identifier="res00001" type="assessment/x-bb-qti-test" xml:base="res00001"/>'),
        'res00001.dat' => qtiTest($items),
        'res00001/image001.gif' => testImage('gif'),
        'res00001/pilihanA.png' => testImage('png', [200, 40, 40]),
        'res00001/pilihanB.png' => testImage('png', [40, 200, 40]),
    ]));

    expect($data['total'])->toBe(10)
        ->and($data['warnings'])->toBe([]);

    [$mc, $tf, $ma, $fib, $essay, $matching, $pictureChoice, $numeric, $ordering, $eitherOr] = $data['questions'];

    expect($mc)->toMatchArray([
        'type' => 'Multiple choice',
        'title' => 'Perhatikan diagram [Gambar 1] berikut. Jika x < 3 maka x² + H₂O bernilai ...',
        'options' => ['Kurang dari 9', 'Sama dengan 9', 'Lebih dari 9 & genap'],
        'answer' => 0,
        'points' => 2,
    ])->and($mc['media'])->toHaveCount(1)
        ->and($mc['media'][0]['url'])->toStartWith('/storage/media/examview/')
        ->and($mc['media'][0]['caption'])->toBe('Gambar 1');

    expect($tf)->toMatchArray(['type' => 'Multiple choice', 'options' => ['Benar', 'Salah'], 'answer' => 1, 'points' => 1]);

    expect($ma)->toMatchArray(['type' => 'Checkboxes', 'options' => ['Merkurius', 'Bulan', 'Mars', 'Matahari'], 'answer' => ['Merkurius', 'Mars'], 'points' => 3]);

    expect($fib)->toMatchArray(['type' => 'Short answer', 'title' => 'Ibu kota Indonesia adalah ____.', 'answer' => 'Jakarta | DKI Jakarta', 'points' => 2]);

    expect($essay)->toMatchArray(['type' => 'Paragraph', 'title' => 'Jelaskan proses fotosintesis!', 'answer' => 'Tumbuhan mengubah CO₂ dan air menjadi glukosa.', 'points' => 5]);

    expect($matching)->toMatchArray([
        'type' => 'Multiple-choice grid',
        'rows' => ['Jantung', 'Paru-paru', 'Ginjal'],
        'columns' => ['Menyaring darah', 'Memompa darah', 'Pertukaran gas'],
        'answer' => ['0' => 1, '1' => 2, '2' => 0],
        'points' => 3,
    ]);

    expect($pictureChoice['options'])->toBe(['[Gambar 1]', '[Gambar 2]'])
        ->and($pictureChoice['answer'])->toBe(1)
        ->and($pictureChoice['media'])->toHaveCount(2)
        ->and($pictureChoice['media'][1]['caption'])->toBe('Gambar 2 (pilihan B)');

    expect($numeric)->toMatchArray(['type' => 'Short answer', 'answer' => '9.5..10.5', 'points' => 2]);

    expect($ordering)->toMatchArray([
        'type' => 'Multiple-choice grid',
        'rows' => ['Pagi', 'Malam', 'Siang'],
        'columns' => ['Urutan 1', 'Urutan 2', 'Urutan 3'],
        'answer' => ['0' => 0, '1' => 2, '2' => 1],
    ]);

    expect($eitherOr)->toMatchArray(['options' => ['Ya', 'Tidak'], 'answer' => 0])
        ->and($eitherOr['title'])->toContain('100 °C');

    expect(Storage::disk('public')->allFiles('media/examview'))->toHaveCount(3);
});

test('blackboard 9 export: content collection images (xid) and the same questions exported as test and pool', function () {
    $question = qtiItem('Multiple Choice', '1.0',
        qtiQuestionBlock('&lt;p&gt;Pulau terbesar di Indonesia ditunjukkan oleh peta &lt;img src="@X@EmbeddedFile.requestUrlStub@X@bbcswebdav/xid-2201_1" /&gt;&lt;/p&gt;')
        .qtiChoices(['k1' => 'Kalimantan', 'k2' => 'Jawa']),
        qtiCorrect('<varequal case="No" respident="response">k1</varequal>'));

    $poolOnly = qtiItem('True/False', '1.0',
        qtiQuestionBlock('Jakarta terletak di Pulau Jawa.').qtiChoices(['true' => 'true', 'false' => 'false'], 'Single', true),
        qtiCorrect('<varequal case="No" respident="response">true</varequal>'));

    $data = importPackage(blackboardPackage([
        'imsmanifest.xml' => bb9Manifest(
            '<resource bb:file="res00001.dat" bb:title="Ujian" identifier="res00001" type="assessment/x-bb-qti-test" xml:base="res00001"/>'
            .'<resource bb:file="res00002.dat" bb:title="Bank Soal" identifier="res00002" type="assessment/x-bb-qti-pool" xml:base="res00002"/>'
            .'<resource bb:file="res00003.dat" bb:title="Survei" identifier="res00003" type="assessment/x-bb-qti-survey" xml:base="res00003"/>'
        ),
        'res00001.dat' => qtiTest($question),
        'res00002.dat' => str_replace('<bbmd_assessmenttype>Test</bbmd_assessmenttype>', '<bbmd_assessmenttype>Pool</bbmd_assessmenttype>', qtiTest($question.$poolOnly)),
        'res00003.dat' => qtiTest($poolOnly),
        'csfiles/home_dir/peta__xid-2201_1.jpg' => testImage('jpg'),
        'csfiles/home_dir/peta__xid-2201_1.jpg.xml' => '<?xml version="1.0" encoding="UTF-8"?><lom/>',
    ]));

    expect($data['total'])->toBe(2)
        ->and($data['questions'][0]['title'])->toBe('Pulau terbesar di Indonesia ditunjukkan oleh peta [Gambar 1]')
        ->and($data['questions'][0]['media'])->toHaveCount(1)
        ->and($data['questions'][0]['answer'])->toBe(0)
        ->and($data['questions'][1]['options'])->toBe(['Benar', 'Salah'])
        ->and($data['warnings'])->toHaveCount(1)
        ->and($data['warnings'][0])->toContain('Survei');
});

test('blackboard 6.0-7.0 export: POOL format with every question type', function () {
    $pool = <<<'XML'
<?xml version="1.0" encoding="ISO-8859-1"?>
<POOL>
  <COURSEID value="IMPORT"/>
  <TITLE value="Bank Soal IPA Kelas 8"/>
  <DESCRIPTION><TEXT></TEXT></DESCRIPTION>
  <DATES><CREATED value="2026-09-01 08:00:00"/><UPDATED value="2026-09-01 08:00:00"/></DATES>
  <QUESTIONLIST>
    <QUESTION id="q1" class="QUESTION_MULTIPLECHOICE"/>
    <QUESTION id="q2" class="QUESTION_TRUEFALSE"/>
    <QUESTION id="q3" class="QUESTION_MULTIPLEANSWER"/>
    <QUESTION id="q4" class="QUESTION_ESSAY"/>
    <QUESTION id="q5" class="QUESTION_FILLINBLANK"/>
    <QUESTION id="q6" class="QUESTION_MATCH"/>
    <QUESTION id="q7" class="QUESTION_ORDER"/>
  </QUESTIONLIST>
  <QUESTION_MULTIPLECHOICE id="q1">
    <DATES><CREATED value="2026-09-01 08:00:00"/><UPDATED value="2026-09-01 08:00:00"/></DATES>
    <BODY>
      <TEXT>&lt;p&gt;Perhatikan gambar sel &lt;img src="@X@EmbeddedFile.location@X@sel%20hewan.jpg"&gt;. Bagian yang bertanda X disebut ...&lt;/p&gt;</TEXT>
      <FLAGS value="true"><ISHTML value="true"/><ISNEWLINELITERAL value="false"/></FLAGS>
    </BODY>
    <ANSWER id="q1_a3" position="3"><DATES/><TEXT>Ribosom</TEXT></ANSWER>
    <ANSWER id="q1_a1" position="1"><DATES/><TEXT>Mitokondria</TEXT></ANSWER>
    <ANSWER id="q1_a2" position="2"><DATES/><TEXT>Nukleus</TEXT></ANSWER>
    <GRADABLE><FEEDBACK_WHEN_CORRECT>Bagus</FEEDBACK_WHEN_CORRECT><FEEDBACK_WHEN_INCORRECT/><CORRECTANSWER answer_id="q1_a2"/></GRADABLE>
  </QUESTION_MULTIPLECHOICE>
  <QUESTION_TRUEFALSE id="q2">
    <BODY><TEXT>Kafé di Indonesia menjual kopi.</TEXT><FLAGS value="true"><ISHTML value="true"/></FLAGS></BODY>
    <ANSWER id="q2_a1" position="1"><TEXT>True</TEXT></ANSWER>
    <ANSWER id="q2_a2" position="2"><TEXT>False</TEXT></ANSWER>
    <GRADABLE><CORRECTANSWER answer_id="q2_a1"/></GRADABLE>
  </QUESTION_TRUEFALSE>
  <QUESTION_MULTIPLEANSWER id="q3">
    <BODY><TEXT>Pilih hewan mamalia:</TEXT><FLAGS value="true"><ISHTML value="true"/></FLAGS></BODY>
    <ANSWER id="q3_a1" position="1"><TEXT>Paus</TEXT></ANSWER>
    <ANSWER id="q3_a2" position="2"><TEXT>Hiu</TEXT></ANSWER>
    <ANSWER id="q3_a3" position="3"><TEXT>Kelelawar</TEXT></ANSWER>
    <GRADABLE><CORRECTANSWER answer_id="q3_a1"/><CORRECTANSWER answer_id="q3_a3"/></GRADABLE>
  </QUESTION_MULTIPLEANSWER>
  <QUESTION_ESSAY id="q4">
    <BODY><TEXT>Jelaskan siklus air!</TEXT><FLAGS value="true"><ISHTML value="true"/></FLAGS></BODY>
    <ANSWER id="q4_a1" position="1"><TEXT>Evaporasi, kondensasi, presipitasi.</TEXT></ANSWER>
  </QUESTION_ESSAY>
  <QUESTION_FILLINBLANK id="q5">
    <BODY><TEXT>Rumus kimia air adalah ____.</TEXT><FLAGS value="true"><ISHTML value="true"/></FLAGS></BODY>
    <ANSWER id="q5_a1" position="1"><TEXT>H2O</TEXT></ANSWER>
    <ANSWER id="q5_a2" position="2"><TEXT>H&lt;sub&gt;2&lt;/sub&gt;O</TEXT></ANSWER>
  </QUESTION_FILLINBLANK>
  <QUESTION_MATCH id="q6">
    <BODY><TEXT>Pasangkan alat dengan fungsinya.</TEXT><FLAGS value="true"><ISHTML value="true"/></FLAGS></BODY>
    <ANSWER id="q6_a1" position="1"><TEXT>Termometer</TEXT></ANSWER>
    <ANSWER id="q6_a2" position="2"><TEXT>Barometer</TEXT></ANSWER>
    <CHOICE id="q6_c1" position="1"><TEXT>Mengukur tekanan udara</TEXT></CHOICE>
    <CHOICE id="q6_c2" position="2"><TEXT>Mengukur suhu</TEXT></CHOICE>
    <GRADABLE>
      <CORRECTANSWER answer_id="q6_a1" choice_id="q6_c2"/>
      <CORRECTANSWER answer_id="q6_a2" choice_id="q6_c1"/>
    </GRADABLE>
  </QUESTION_MATCH>
  <QUESTION_ORDER id="q7">
    <BODY><TEXT>Urutkan tahap metamorfosis kupu-kupu.</TEXT><FLAGS value="true"><ISHTML value="true"/></FLAGS></BODY>
    <ANSWER id="q7_a1" position="1"><TEXT>Kepompong</TEXT></ANSWER>
    <ANSWER id="q7_a2" position="2"><TEXT>Telur</TEXT></ANSWER>
    <ANSWER id="q7_a3" position="3"><TEXT>Ulat</TEXT></ANSWER>
    <GRADABLE>
      <CORRECTANSWER answer_id="q7_a2"/>
      <CORRECTANSWER answer_id="q7_a3"/>
      <CORRECTANSWER answer_id="q7_a1"/>
    </GRADABLE>
  </QUESTION_ORDER>
</POOL>
XML;

    $data = importPackage(blackboardPackage([
        'imsmanifest.xml' => '<?xml version="1.0" encoding="UTF-8"?><manifest identifier="man00001"><organizations default="toc00001"><tableofcontents identifier="toc00001"/></organizations><resources><resource baseurl="res00001" file="res00001.dat" identifier="res00001" type="assessment/x-bb-pool"/></resources></manifest>',
        'res00001.dat' => mb_convert_encoding($pool, 'ISO-8859-1', 'UTF-8'),
        'res00001/sel hewan.jpg' => testImage('jpg'),
    ]));

    expect($data['total'])->toBe(7)
        ->and($data['warnings'])->toBe([]);

    [$mc, $tf, $ma, $essay, $fib, $matching, $ordering] = $data['questions'];

    expect($mc)->toMatchArray([
        'type' => 'Multiple choice',
        'title' => 'Perhatikan gambar sel [Gambar 1]. Bagian yang bertanda X disebut ...',
        'options' => ['Mitokondria', 'Nukleus', 'Ribosom'],
        'answer' => 1,
        // The pool gives no points, so the default weight of 1 applies.
        'points' => 1,
    ])->and($mc['media'])->toHaveCount(1);

    expect($tf)->toMatchArray(['options' => ['Benar', 'Salah'], 'answer' => 0, 'title' => 'Kafé di Indonesia menjual kopi.']);
    expect($ma)->toMatchArray(['type' => 'Checkboxes', 'answer' => ['Paus', 'Kelelawar']]);
    expect($essay)->toMatchArray(['type' => 'Paragraph', 'answer' => 'Evaporasi, kondensasi, presipitasi.']);
    expect($fib)->toMatchArray(['type' => 'Short answer', 'answer' => 'H2O | H₂O']);
    expect($matching)->toMatchArray([
        'rows' => ['Termometer', 'Barometer'],
        'columns' => ['Mengukur tekanan udara', 'Mengukur suhu'],
        'answer' => ['0' => 1, '1' => 0],
    ]);
    expect($ordering)->toMatchArray([
        'rows' => ['Kepompong', 'Telur', 'Ulat'],
        'answer' => ['0' => 2, '1' => 0, '2' => 1],
    ]);
});

test('images referenced in other ways are stored too, problems are reported as warnings', function () {
    $png = base64_encode(testImage('png'));

    $items = implode("\n", [
        qtiItem('Multiple Choice', '1.0',
            '<flow class="QUESTION_BLOCK"><flow class="FORMATTED_TEXT_BLOCK"><material><mat_extension><mat_formattedtext type="HTML">Lihat peta berikut.</mat_formattedtext></mat_extension></material></flow>'
            .'<flow class="FILE_BLOCK"><material><matimage imagtype="image/bmp" uri="res00001/peta.bmp"/></material></flow></flow>'
            .qtiChoices(['a' => '&lt;img src="data:image/png;base64,'.$png.'"&gt; Pilihan bergambar', 'b' => 'Pilihan teks']),
            qtiCorrect('<varequal case="No" respident="response">b</varequal>')),
        qtiItem('Multiple Choice', '1.0',
            qtiQuestionBlock('Gambar hilang &lt;img src="@X@EmbeddedFile.location@X@tidak-ada.gif"&gt; dan &lt;img src="@X@EmbeddedFile.location@X@rumus.wmf"&gt;')
            .qtiChoices(['a' => 'A', 'b' => 'B']),
            '<respcondition title="incorrect"><conditionvar><other/></conditionvar><setvar action="Set" variablename="SCORE">0</setvar></respcondition>'),
    ]);

    $data = importPackage(blackboardPackage([
        'imsmanifest.xml' => bb9Manifest('<resource bb:file="res00001.dat" identifier="res00001" type="assessment/x-bb-qti-test" xml:base="res00001"/>'),
        'res00001.dat' => qtiTest($items),
        'res00001/peta.bmp' => testImage('bmp'),
        'res00001/rumus.wmf' => "\xD7\xCD\xC6\x9A".str_repeat("\0", 40),
    ]));

    [$first, $second] = $data['questions'];

    expect($first['title'])->toBe('Lihat peta berikut.'."\n".'[Gambar 1]')
        ->and($first['options'])->toBe(['[Gambar 2] Pilihan bergambar', 'Pilihan teks'])
        ->and($first['media'])->toHaveCount(2)
        ->and($first['media'][0]['url'])->toEndWith('.webp')
        ->and($first['answer'])->toBe(1);

    expect($second['answer'])->toBe('')
        ->and($second['media'])->toBe([])
        ->and($data['warnings'])->toContain('Soal 2: gambar "tidak-ada.gif" tidak ditemukan di dalam ZIP.')
        ->and(collect($data['warnings'])->contains(fn ($warning) => str_contains($warning, 'WMF/EMF')))->toBeTrue()
        ->and(collect($data['warnings'])->contains(fn ($warning) => str_contains($warning, 'kunci jawaban tidak ditemukan')))->toBeTrue();
});

test('a Windows-1252 export without encoding declaration keeps its special characters', function () {
    $item = qtiItem('Multiple Choice', '1.0',
        qtiQuestionBlock('“Kutipan” – café').qtiChoices(['a' => 'Ya', 'b' => 'Tidak']),
        qtiCorrect('<varequal case="No" respident="response">a</varequal>'));

    $xml = str_replace('<?xml version="1.0" encoding="UTF-8"?>', '<?xml version="1.0"?>', qtiTest($item));

    $data = importPackage(blackboardPackage([
        'res00001.dat' => mb_convert_encoding($xml, 'Windows-1252', 'UTF-8'),
    ]));

    expect($data['questions'][0]['title'])->toBe('“Kutipan” – café')
        ->and($data['questions'][0]['options'])->toBe(['Ya', 'Tidak'])
        ->and($data['questions'][0]['answer'])->toBe(0);
});

test('svg images are never stored, even when renamed to a raster extension', function () {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><script>alert(document.cookie)</script></svg>';

    $data = importPackage(blackboardPackage([
        'imsmanifest.xml' => bb9Manifest('<resource bb:file="res00001.dat" identifier="res00001" type="assessment/x-bb-qti-test" xml:base="res00001"/>'),
        'res00001.dat' => qtiTest(qtiItem('Multiple Choice', '1.0',
            qtiQuestionBlock('Gambar &lt;img src="@X@EmbeddedFile.location@X@grafik.svg"&gt; dan &lt;img src="@X@EmbeddedFile.location@X@menyamar.gif"&gt;')
            .qtiChoices(['a' => 'A', 'b' => 'B']),
            qtiCorrect('<varequal case="No" respident="response">a</varequal>'))),
        'res00001/grafik.svg' => $svg,
        'res00001/menyamar.gif' => $svg,
    ]));

    expect($data['questions'][0]['media'])->toBe([])
        ->and(Storage::disk('public')->allFiles('media/examview'))->toBe([])
        ->and(collect($data['warnings'])->filter(fn ($warning) => str_contains($warning, 'SVG'))->count())->toBe(2);
});
