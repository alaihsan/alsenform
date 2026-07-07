<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;

test('guests are unauthorized to import questions', function () {
    $this->postJson(route('questions.import'), [
        'file' => UploadedFile::fake()->create('test.docx', 10),
    ])->assertStatus(401);
});

test('authenticated users can import questions from docx template', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    // Create a temporary docx file using ZipArchive
    $tempFile = tempnam(sys_get_temp_dir(), 'docx');
    $zip = new ZipArchive;
    if ($zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>';
        $zip->addFromString('[Content_Types].xml', $contentTypes);

        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>';
        $zip->addFromString('_rels/.rels', $rels);

        $documentXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>1. Apa ibukota Indonesia?</w:t></w:r></w:p>
    <w:p><w:r><w:t>A. Surabaya</w:t></w:r></w:p>
    <w:p><w:r><w:t>B. Jakarta</w:t></w:r></w:p>
    <w:p><w:r><w:t>Jawaban: B</w:t></w:r></w:p>
  </w:body>
</w:document>';
        $zip->addFromString('word/document.xml', $documentXml);
        $zip->close();
    }

    $uploadedFile = new UploadedFile($tempFile, 'test.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true);

    $response = $this->postJson(route('questions.import'), [
        'file' => $uploadedFile,
    ]);

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'questions' => [
            '*' => [
                'id',
                'title',
                'description',
                'type',
                'options',
                'answer',
                'required',
                'points',
            ],
        ],
    ]);

    $data = $response->json();
    expect($data['questions'])->toHaveCount(1);
    expect($data['questions'][0]['title'])->toBe('Apa ibukota Indonesia?');
    expect($data['questions'][0]['options'])->toBe(['Surabaya', 'Jakarta']);
    expect($data['questions'][0]['answer'])->toBe(1); // Jakarta is index 1

    @unlink($tempFile);
});
