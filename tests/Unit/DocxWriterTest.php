<?php

use App\Support\DocxWriter;

beforeEach(function () {
    $this->path = tempnam(sys_get_temp_dir(), 'docx-test-');
});

afterEach(function () {
    @unlink($this->path);
});

test('a document contains every required part as well-formed xml', function () {
    (new DocxWriter)
        ->paragraph([['text' => 'Judul', 'bold' => true, 'size' => 14]], ['align' => 'center', 'keepNext' => true, 'pageBreakBefore' => true])
        ->table([['No', 'Soal'], ['1', 'Isi']], ['header' => true])
        ->save($this->path, 'Templat');

    $zip = new ZipArchive;
    expect($zip->open($this->path))->toBeTrue();

    foreach (['[Content_Types].xml', '_rels/.rels', 'word/_rels/document.xml.rels', 'word/styles.xml', 'docProps/core.xml', 'word/document.xml'] as $part) {
        $xml = $zip->getFromName($part);
        expect($xml)->toBeString("{$part} is missing");
        expect(simplexml_load_string($xml))->not->toBeFalse("{$part} is not well-formed");
    }

    $document = $zip->getFromName('word/document.xml');
    $zip->close();

    expect($document)->toContain('<w:pPr><w:keepNext/><w:pageBreakBefore/>')
        ->and($document)->toContain('<w:tblHeader/>')
        ->and($document)->toContain('<w:jc w:val="center"/>');
});

test('text is escaped and characters not allowed in xml are dropped', function () {
    (new DocxWriter)->paragraph("Jika a < b & c > d \"benar\"\x01")->save($this->path);

    $zip = new ZipArchive;
    $zip->open($this->path);
    $document = $zip->getFromName('word/document.xml');
    $zip->close();

    $xml = simplexml_load_string($document);
    $xml->registerXPathNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
    $text = implode('', array_map('strval', $xml->xpath('//w:t')));
    expect($text)->toBe('Jika a < b & c > d "benar"');
});
