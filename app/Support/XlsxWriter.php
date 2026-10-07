<?php

namespace App\Support;

use DateTimeInterface;
use RuntimeException;
use ZipArchive;

/**
 * A small writer for formatted Excel workbooks (.xlsx, Office Open XML) built on ZipArchive,
 * so reports need no extra Composer package on the school server.
 *
 * Cells are scalars, DateTimeInterface values or ['value' => ..., 'style' => ...] arrays made
 * with cell(). Rows are numbered from 1 in the order given; null is an empty row.
 */
class XlsxWriter
{
    /**
     * Named cell styles and their index in styles.xml (cellXfs).
     *
     * @var array<string, int>
     */
    public const STYLES = [
        'default' => 0,
        'title' => 1,
        'subtitle' => 2,
        'header' => 3,
        'headerLeft' => 4,
        'text' => 5,
        'textCenter' => 6,
        'integer' => 7,
        'decimal' => 8,
        'datetime' => 9,
        'correct' => 10,
        'wrong' => 11,
        'ungraded' => 12,
        'pass' => 13,
        'fail' => 14,
        'summaryLabel' => 15,
        'summaryPercent' => 16,
    ];

    /**
     * Styles that wrap text, used to estimate row heights.
     *
     * @var list<string>
     */
    protected const WRAPPING_STYLES = ['header', 'headerLeft', 'text', 'correct', 'wrong', 'ungraded'];

    protected const MAX_CELL_LENGTH = 32767;

    protected const LINE_HEIGHT = 15.0;

    /**
     * @var list<array{name: string, rows: list<list<mixed>|null>, options: array<string, mixed>}>
     */
    protected array $sheets = [];

    /**
     * A cell with a named style.
     *
     * @return array{value: mixed, style: string}
     */
    public static function cell(mixed $value, string $style): array
    {
        return ['value' => $value, 'style' => $style];
    }

    /**
     * The column letters for a 1-based column number (1 = A, 27 = AA).
     */
    public static function columnLetter(int $column): string
    {
        $letters = '';
        while ($column > 0) {
            $column--;
            $letters = chr(65 + $column % 26).$letters;
            $column = intdiv($column, 26);
        }

        return $letters;
    }

    /**
     * Add a worksheet.
     *
     * @param  list<list<mixed>|null>  $rows
     * @param  array{columns?: list<float|int>, freeze?: string, autoFilter?: string, merge?: list<string>, rowHeights?: array<int, float|int>, repeatRows?: array{0: int, 1: int}, landscape?: bool}  $options
     */
    public function addSheet(string $name, array $rows, array $options = []): static
    {
        $this->sheets[] = ['name' => $this->sheetName($name), 'rows' => $rows, 'options' => $options];

        return $this;
    }

    /**
     * Write the workbook to a file.
     */
    public function save(string $path, string $title = '', string $creator = 'Alsenform'): void
    {
        if ($this->sheets === []) {
            throw new RuntimeException('A workbook needs at least one sheet.');
        }

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Cannot create {$path}.");
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rootRelationships());
        $zip->addFromString('docProps/app.xml', $this->appProperties());
        $zip->addFromString('docProps/core.xml', $this->coreProperties($title, $creator));
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationships());
        $zip->addFromString('xl/styles.xml', $this->styles());

        foreach ($this->sheets as $index => $sheet) {
            $zip->addFromString('xl/worksheets/sheet'.($index + 1).'.xml', $this->worksheet($sheet, $index === 0));
        }

        $zip->close();
    }

    /**
     * @param  array{name: string, rows: list<list<mixed>|null>, options: array<string, mixed>}  $sheet
     */
    protected function worksheet(array $sheet, bool $isActive): string
    {
        $options = $sheet['options'];
        $widths = array_map('floatval', $options['columns'] ?? []);
        $rowsXml = '';
        $lastColumn = max(1, count($widths));
        $lastRow = max(1, count($sheet['rows']));

        foreach ($sheet['rows'] as $rowIndex => $cells) {
            $rowNumber = $rowIndex + 1;
            if ($cells === null || $cells === []) {
                continue;
            }

            $lastColumn = max($lastColumn, count($cells));
            $cellsXml = '';
            foreach (array_values($cells) as $columnIndex => $cell) {
                $cellsXml .= $this->cellXml(self::columnLetter($columnIndex + 1).$rowNumber, $cell);
            }

            $height = $options['rowHeights'][$rowNumber] ?? $this->estimateRowHeight(array_values($cells), $widths);
            $heightXml = $height !== null ? sprintf(' ht="%s" customHeight="1"', $this->number((float) $height)) : '';
            $rowsXml .= "<row r=\"{$rowNumber}\"{$heightXml}>{$cellsXml}</row>";
        }

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>'
            .'<dimension ref="A1:'.self::columnLetter($lastColumn).$lastRow.'"/>'
            .'<sheetViews><sheetView workbookViewId="0"'.($isActive ? ' tabSelected="1"' : '').'>'.$this->paneXml($options['freeze'] ?? null).'</sheetView></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="15"/>';

        if ($widths !== []) {
            $xml .= '<cols>';
            foreach ($widths as $index => $width) {
                $column = $index + 1;
                $xml .= sprintf('<col min="%d" max="%d" width="%s" customWidth="1"/>', $column, $column, $this->number($width));
            }
            $xml .= '</cols>';
        }

        $xml .= "<sheetData>{$rowsXml}</sheetData>";

        if (! empty($options['autoFilter'])) {
            $xml .= '<autoFilter ref="'.$options['autoFilter'].'"/>';
        }

        $merges = $options['merge'] ?? [];
        if ($merges !== []) {
            $xml .= '<mergeCells count="'.count($merges).'">';
            foreach ($merges as $range) {
                $xml .= '<mergeCell ref="'.$range.'"/>';
            }
            $xml .= '</mergeCells>';
        }

        $xml .= '<pageMargins left="0.4" right="0.4" top="0.6" bottom="0.6" header="0.3" footer="0.3"/>'
            .'<pageSetup paperSize="9" orientation="'.(($options['landscape'] ?? true) ? 'landscape' : 'portrait').'" fitToWidth="1" fitToHeight="0"/>'
            .'</worksheet>';

        return $xml;
    }

    protected function cellXml(string $reference, mixed $cell): string
    {
        $style = null;
        $value = $cell;
        if (is_array($cell) && array_key_exists('value', $cell)) {
            $value = $cell['value'];
            $style = $cell['style'] ?? null;
        }

        if ($value instanceof DateTimeInterface) {
            $style ??= 'datetime';
            $value = $this->excelDate($value);
        }

        $styleXml = ($styleIndex = self::STYLES[$style ?? 'default'] ?? 0) > 0 ? " s=\"{$styleIndex}\"" : '';

        if ($value === null || $value === '') {
            return $styleXml === '' ? '' : "<c r=\"{$reference}\"{$styleXml}/>";
        }

        if (is_int($value) || is_float($value)) {
            return "<c r=\"{$reference}\"{$styleXml}><v>".$this->number((float) $value).'</v></c>';
        }

        if (is_bool($value)) {
            return "<c r=\"{$reference}\"{$styleXml} t=\"b\"><v>".($value ? 1 : 0).'</v></c>';
        }

        $text = $this->escape(mb_substr((string) $value, 0, self::MAX_CELL_LENGTH));

        return "<c r=\"{$reference}\"{$styleXml} t=\"inlineStr\"><is><t xml:space=\"preserve\">{$text}</t></is></c>";
    }

    /**
     * Freeze the rows above and the columns left of the given cell (e.g. "C5").
     */
    protected function paneXml(?string $freeze): string
    {
        if (! $freeze || ! preg_match('/^([A-Z]+)(\d+)$/', $freeze, $match)) {
            return '';
        }

        $column = 0;
        foreach (str_split($match[1]) as $letter) {
            $column = $column * 26 + (ord($letter) - 64);
        }
        $xSplit = $column - 1;
        $ySplit = (int) $match[2] - 1;
        if ($xSplit === 0 && $ySplit === 0) {
            return '';
        }

        $pane = match (true) {
            $xSplit > 0 && $ySplit > 0 => 'bottomRight',
            $ySplit > 0 => 'bottomLeft',
            default => 'topRight',
        };

        return '<pane'.($xSplit > 0 ? " xSplit=\"{$xSplit}\"" : '').($ySplit > 0 ? " ySplit=\"{$ySplit}\"" : '')
            ." topLeftCell=\"{$freeze}\" activePane=\"{$pane}\" state=\"frozen\"/>"
            ."<selection pane=\"{$pane}\" activeCell=\"{$freeze}\" sqref=\"{$freeze}\"/>";
    }

    /**
     * Estimate the height of a row whose wrapped text needs more than one line, so it reads
     * well in Excel, LibreOffice and Google Sheets alike. Null keeps the default height.
     *
     * @param  list<mixed>  $cells
     * @param  list<float>  $widths
     */
    protected function estimateRowHeight(array $cells, array $widths): ?float
    {
        $lines = 1;
        foreach ($cells as $index => $cell) {
            if (! is_array($cell) || ! in_array($cell['style'] ?? null, self::WRAPPING_STYLES, true) || ! is_string($cell['value'] ?? null)) {
                continue;
            }

            $charactersPerLine = max(1, (int) floor(($widths[$index] ?? 10) * 1.1));
            $cellLines = 0;
            foreach (explode("\n", $cell['value']) as $line) {
                $cellLines += max(1, (int) ceil(mb_strwidth($line) / $charactersPerLine));
            }
            $lines = max($lines, $cellLines);
        }

        return $lines > 1 ? min(409.0, $lines * self::LINE_HEIGHT + 3) : null;
    }

    /**
     * Excel stores dates as days since 1899-12-30, in the wall-clock time of the value.
     */
    protected function excelDate(DateTimeInterface $date): float
    {
        return 25569 + ($date->getTimestamp() + $date->getOffset()) / 86400;
    }

    protected function number(float $value): string
    {
        return floor($value) === $value && abs($value) < 1e15 ? (string) (int) $value : rtrim(rtrim(sprintf('%.10F', $value), '0'), '.');
    }

    protected function escape(string $value): string
    {
        $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        $value = (string) preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $value);

        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * Excel sheet names: at most 31 characters, none of []:*?/\ and unique in the workbook.
     */
    protected function sheetName(string $name): string
    {
        $name = trim((string) preg_replace('/[\[\]:*?\/\\\\]+/', ' ', $name)) ?: 'Sheet';
        $name = mb_substr($name, 0, 31);
        $existing = array_map(fn (array $sheet) => mb_strtolower($sheet['name']), $this->sheets);

        $candidate = $name;
        for ($suffix = 2; in_array(mb_strtolower($candidate), $existing, true); $suffix++) {
            $candidate = mb_substr($name, 0, 31 - strlen(" ({$suffix})"))." ({$suffix})";
        }

        return $candidate;
    }

    protected function quotedSheetName(string $name): string
    {
        return "'".str_replace("'", "''", $name)."'";
    }

    protected function workbook(): string
    {
        $sheets = '';
        $definedNames = '';
        foreach ($this->sheets as $index => $sheet) {
            $sheets .= sprintf('<sheet name="%s" sheetId="%d" r:id="rId%d"/>', $this->escape($sheet['name']), $index + 1, $index + 1);

            $quoted = $this->escape($this->quotedSheetName($sheet['name']));
            if (! empty($sheet['options']['autoFilter'])) {
                $range = (string) preg_replace('/([A-Z]+)(\d+)/', '\$$1\$$2', $sheet['options']['autoFilter']);
                $definedNames .= "<definedName name=\"_xlnm._FilterDatabase\" localSheetId=\"{$index}\" hidden=\"1\">{$quoted}!{$range}</definedName>";
            }
            if (! empty($sheet['options']['repeatRows'])) {
                [$from, $to] = $sheet['options']['repeatRows'];
                $definedNames .= "<definedName name=\"_xlnm.Print_Titles\" localSheetId=\"{$index}\">{$quoted}!\${$from}:\${$to}</definedName>";
            }
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<bookViews><workbookView activeTab="0"/></bookViews>'
            ."<sheets>{$sheets}</sheets>"
            .($definedNames !== '' ? "<definedNames>{$definedNames}</definedNames>" : '')
            .'</workbook>';
    }

    protected function workbookRelationships(): string
    {
        $relationships = '';
        foreach (array_keys($this->sheets) as $index) {
            $relationships .= sprintf(
                '<Relationship Id="rId%d" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet%d.xml"/>',
                $index + 1,
                $index + 1,
            );
        }
        $relationships .= sprintf(
            '<Relationship Id="rId%d" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>',
            count($this->sheets) + 1,
        );

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$relationships.'</Relationships>';
    }

    protected function contentTypes(): string
    {
        $worksheets = '';
        foreach (array_keys($this->sheets) as $index) {
            $worksheets .= '<Override PartName="/xl/worksheets/sheet'.($index + 1).'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .$worksheets
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            .'<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            .'</Types>';
    }

    protected function rootRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            .'<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            .'</Relationships>';
    }

    protected function appProperties(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"><Application>Alsenform</Application></Properties>';
    }

    protected function coreProperties(string $title, string $creator): string
    {
        $created = gmdate('Y-m-d\TH:i:s\Z');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            .'<dc:title>'.$this->escape($title).'</dc:title>'
            .'<dc:creator>'.$this->escape($creator).'</dc:creator>'
            ."<dcterms:created xsi:type=\"dcterms:W3CDTF\">{$created}</dcterms:created>"
            ."<dcterms:modified xsi:type=\"dcterms:W3CDTF\">{$created}</dcterms:modified>"
            .'</cp:coreProperties>';
    }

    /**
     * Fonts, fills, borders and cell formats in the order of STYLES.
     */
    protected function styles(): string
    {
        $fonts = [
            '<font><sz val="11"/><color rgb="FF1E293B"/><name val="Calibri"/><family val="2"/></font>',
            '<font><b/><sz val="16"/><color rgb="FF1E1B4B"/><name val="Calibri"/><family val="2"/></font>',
            '<font><sz val="10"/><color rgb="FF64748B"/><name val="Calibri"/><family val="2"/></font>',
            '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/><family val="2"/></font>',
            '<font><b/><sz val="11"/><color rgb="FF15803D"/><name val="Calibri"/><family val="2"/></font>',
            '<font><b/><sz val="11"/><color rgb="FFB91C1C"/><name val="Calibri"/><family val="2"/></font>',
            '<font><sz val="11"/><color rgb="FF166534"/><name val="Calibri"/><family val="2"/></font>',
            '<font><sz val="11"/><color rgb="FF991B1B"/><name val="Calibri"/><family val="2"/></font>',
            '<font><b/><sz val="11"/><color rgb="FF312E81"/><name val="Calibri"/><family val="2"/></font>',
        ];

        $solidFill = fn (string $rgb): string => "<fill><patternFill patternType=\"solid\"><fgColor rgb=\"FF{$rgb}\"/><bgColor indexed=\"64\"/></patternFill></fill>";
        $fills = [
            '<fill><patternFill patternType="none"/></fill>',
            '<fill><patternFill patternType="gray125"/></fill>',
            $solidFill('4F46E5'),
            $solidFill('DCFCE7'),
            $solidFill('FEE2E2'),
            $solidFill('F1F5F9'),
            $solidFill('EEF2FF'),
        ];

        $side = fn (string $name): string => "<{$name} style=\"thin\"><color rgb=\"FFCBD5E1\"/></{$name}>";
        $borders = [
            '<border><left/><right/><top/><bottom/><diagonal/></border>',
            '<border>'.$side('left').$side('right').$side('top').$side('bottom').'<diagonal/></border>',
        ];

        $xf = function (int $font = 0, int $fill = 0, int $border = 0, int $numberFormat = 0, string $alignment = ''): string {
            $applied = ($font ? ' applyFont="1"' : '').($fill ? ' applyFill="1"' : '').($border ? ' applyBorder="1"' : '')
                .($numberFormat ? ' applyNumberFormat="1"' : '').($alignment !== '' ? ' applyAlignment="1"' : '');
            $alignmentXml = $alignment !== '' ? "<alignment {$alignment}/>" : '';

            return "<xf numFmtId=\"{$numberFormat}\" fontId=\"{$font}\" fillId=\"{$fill}\" borderId=\"{$border}\" xfId=\"0\"{$applied}>{$alignmentXml}</xf>";
        };

        $wrapTop = 'vertical="top" wrapText="1"';
        $center = 'horizontal="center" vertical="top"';
        $cellFormats = [
            $xf(),                                                          // default
            $xf(1, 0, 0, 0, 'vertical="center"'),                           // title
            $xf(2, 0, 0, 0, 'vertical="center"'),                           // subtitle
            $xf(3, 2, 1, 0, 'horizontal="center" vertical="center" wrapText="1"'), // header
            $xf(3, 2, 1, 0, 'horizontal="left" vertical="top" wrapText="1"'),      // headerLeft
            $xf(0, 0, 1, 0, $wrapTop),                                      // text
            $xf(0, 0, 1, 0, $center),                                       // textCenter
            $xf(0, 0, 1, 1, $center),                                       // integer
            $xf(0, 0, 1, 164, $center),                                     // decimal
            $xf(0, 0, 1, 165, $center),                                     // datetime
            $xf(6, 3, 1, 0, $wrapTop),                                      // correct
            $xf(7, 4, 1, 0, $wrapTop),                                      // wrong
            $xf(0, 5, 1, 0, $wrapTop),                                      // ungraded
            $xf(4, 0, 1, 0, $center),                                       // pass
            $xf(5, 0, 1, 0, $center),                                       // fail
            $xf(8, 6, 1, 0, 'vertical="top"'),                              // summaryLabel
            $xf(8, 6, 1, 9, $center),                                       // summaryPercent
        ];

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="2"><numFmt numFmtId="164" formatCode="0.00"/><numFmt numFmtId="165" formatCode="dd/mm/yyyy hh:mm"/></numFmts>'
            .'<fonts count="'.count($fonts).'">'.implode('', $fonts).'</fonts>'
            .'<fills count="'.count($fills).'">'.implode('', $fills).'</fills>'
            .'<borders count="'.count($borders).'">'.implode('', $borders).'</borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="'.count($cellFormats).'">'.implode('', $cellFormats).'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }
}
