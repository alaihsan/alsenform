<?php

use App\Support\XlsxWriter;

beforeEach(function () {
    $this->path = tempnam(sys_get_temp_dir(), 'xlsx-test-');
});

afterEach(function () {
    @unlink($this->path);
});

/**
 * @return array<string, string>
 */
function xlsxParts(string $path): array
{
    $zip = new ZipArchive;
    $zip->open($path);
    $parts = [];
    for ($index = 0; $index < $zip->numFiles; $index++) {
        $name = $zip->getNameIndex($index);
        $parts[$name] = $zip->getFromIndex($index);
    }
    $zip->close();

    return $parts;
}

test('column numbers become excel column letters', function (int $column, string $letters) {
    expect(XlsxWriter::columnLetter($column))->toBe($letters);
})->with([[1, 'A'], [26, 'Z'], [27, 'AA'], [52, 'AZ'], [702, 'ZZ'], [703, 'AAA']]);

test('a workbook contains every required part as well-formed xml', function () {
    (new XlsxWriter)
        ->addSheet('Rekap Nilai', [[XlsxWriter::cell('Nama', 'header')], ['Budi']])
        ->addSheet('Jawaban', [['x']])
        ->save($this->path, 'Laporan');

    $parts = xlsxParts($this->path);

    expect(array_keys($parts))->toContain(
        '[Content_Types].xml', '_rels/.rels', 'xl/workbook.xml', 'xl/_rels/workbook.xml.rels',
        'xl/styles.xml', 'xl/worksheets/sheet1.xml', 'xl/worksheets/sheet2.xml', 'docProps/core.xml',
    );

    foreach ($parts as $name => $xml) {
        expect(simplexml_load_string($xml))->not->toBeFalse("{$name} is not valid XML");
    }
});

test('cells keep their types, styles and text safely escaped', function () {
    $date = new DateTimeImmutable('2026-10-04 08:30:00', new DateTimeZone('Asia/Jakarta'));

    (new XlsxWriter)->addSheet('Data', [[
        12,
        XlsxWriter::cell(57.1, 'decimal'),
        "A < B & \"C\"\x07",
        $date,
        XlsxWriter::cell(null, 'text'),
    ]])->save($this->path);

    $sheet = xlsxParts($this->path)['xl/worksheets/sheet1.xml'];

    expect($sheet)->toContain('<c r="A1"><v>12</v></c>')
        ->toContain('<c r="B1" s="'.XlsxWriter::STYLES['decimal'].'"><v>57.1</v></c>')
        ->toContain('A &lt; B &amp; &quot;C&quot;</t>')
        ->not->toContain("\x07")
        // 2026-10-04 08:30 local time as an Excel serial date
        ->toContain('<c r="D1" s="'.XlsxWriter::STYLES['datetime'].'"><v>46299.3541666667</v></c>')
        ->toContain('<c r="E1" s="'.XlsxWriter::STYLES['text'].'"/>');
});

test('sheets can freeze panes, filter, merge and repeat the header when printing', function () {
    (new XlsxWriter)->addSheet('Rekap: Nilai/Kelas [X]', [[XlsxWriter::cell('Judul', 'title')], null, null, ['No', 'Nama']], [
        'columns' => [5, 30],
        'freeze' => 'C5',
        'autoFilter' => 'A4:B4',
        'merge' => ['A1:B1'],
        'repeatRows' => [4, 4],
    ])->save($this->path);

    $parts = xlsxParts($this->path);

    expect($parts['xl/worksheets/sheet1.xml'])
        ->toContain('<pane xSplit="2" ySplit="4" topLeftCell="C5" activePane="bottomRight" state="frozen"/>')
        ->toContain('<autoFilter ref="A4:B4"/>')
        ->toContain('<mergeCell ref="A1:B1"/>')
        ->toContain('<col min="2" max="2" width="30" customWidth="1"/>')
        ->and($parts['xl/workbook.xml'])
        ->toContain('name="Rekap  Nilai Kelas  X"')
        ->toContain('_xlnm.Print_Titles');
});

test('long wrapped text gets a taller row', function () {
    (new XlsxWriter)->addSheet('Data', [[XlsxWriter::cell(str_repeat('jawaban panjang ', 10)."\nbaris kedua", 'text')]], ['columns' => [20]])
        ->save($this->path);

    expect(xlsxParts($this->path)['xl/worksheets/sheet1.xml'])->toMatch('/<row r="1" ht="\d+" customHeight="1">/');
});
