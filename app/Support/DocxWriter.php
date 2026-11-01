<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

/**
 * A small writer for Word documents (.docx, Office Open XML) built on ZipArchive, used for the
 * question import template and the question export. Supports styled paragraphs, bordered tables
 * and pictures, nothing more.
 *
 * A run is a string or ['text' => ..., 'bold' => bool, 'italic' => bool, 'underline' => bool, 'color' => 'RRGGBB',
 * 'shade' => 'RRGGBB' (a highlighter), 'size' => pt, 'font' => name, 'rtl' => bool (Arabic), 'csFont' => name and
 * 'csSize' => pt (for Arabic)]. A line break in the text is a line break in the paragraph.
 */
class DocxWriter
{
    protected const BORDER_COLOR = 'CBD5E1';

    /**
     * Content types of the picture formats Word shows.
     *
     * @var array<string, string>
     */
    protected const IMAGE_TYPES = ['png' => 'image/png', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif'];

    /**
     * Usable page width in twips (A4 with 2 cm margins).
     */
    public const CONTENT_WIDTH = 9638;

    protected string $body = '';

    /**
     * The pictures of the document, in order.
     *
     * @var list<array{id: string, path: string, data: string}>
     */
    protected array $media = [];

    /**
     * Add a paragraph.
     *
     * @param  string|list<string|array<string, mixed>>  $runs
     * @param  array{align?: string, after?: int, before?: int, fill?: string, border?: string, indent?: int, keepNext?: bool, pageBreakBefore?: bool, bidi?: bool}  $options
     */
    public function paragraph(string|array $runs, array $options = []): static
    {
        $this->body .= $this->paragraphXml($runs, $options);

        return $this;
    }

    /**
     * Add a picture in a paragraph of its own. The size is in EMU (914400 to the inch).
     *
     * @param  string  $extension  png, jpeg or gif
     * @param  array{align?: string, after?: int, before?: int, keepNext?: bool}  $options
     */
    public function image(string $data, string $extension, int $width, int $height, array $options = []): static
    {
        $number = count($this->media) + 1;
        // rId1 is the styles; the import reads pictures by their "rId" and number.
        $id = 'rId'.($number + 1);
        $this->media[] = ['id' => $id, 'path' => "media/image{$number}.{$extension}", 'data' => $data];

        $drawing = '<w:r><w:drawing><wp:inline distT="0" distB="0" distL="0" distR="0">'
            ."<wp:extent cx=\"{$width}\" cy=\"{$height}\"/>"
            ."<wp:docPr id=\"{$number}\" name=\"Gambar {$number}\"/>"
            .'<wp:cNvGraphicFramePr><a:graphicFrameLocks xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" noChangeAspect="1"/></wp:cNvGraphicFramePr>'
            .'<a:graphic xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            .'<pic:pic xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            ."<pic:nvPicPr><pic:cNvPr id=\"{$number}\" name=\"image{$number}.{$extension}\"/><pic:cNvPicPr/></pic:nvPicPr>"
            ."<pic:blipFill><a:blip r:embed=\"{$id}\"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>"
            ."<pic:spPr><a:xfrm><a:off x=\"0\" y=\"0\"/><a:ext cx=\"{$width}\" cy=\"{$height}\"/></a:xfrm><a:prstGeom prst=\"rect\"><a:avLst/></a:prstGeom></pic:spPr>"
            .'</pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing></w:r>';

        $this->body .= (string) preg_replace('/<\/w:p>$/', $drawing.'</w:p>', $this->paragraphXml([], $options));

        return $this;
    }

    /**
     * Add a table. The first row is the header when $options['header'] is true.
     *
     * @param  list<list<string|list<string|array<string, mixed>>>>  $rows
     * @param  array{widths?: list<int>, header?: bool, headerBold?: bool, headerFill?: string, headerColor?: string, fills?: array<int, string>, size?: int}  $options
     */
    public function table(array $rows, array $options = []): static
    {
        $columns = max(array_map('count', $rows) ?: [1]);
        $widths = $options['widths'] ?? array_fill(0, $columns, intdiv(self::CONTENT_WIDTH, $columns));
        $size = $options['size'] ?? 10;

        $border = fn (string $side): string => "<w:{$side} w:val=\"single\" w:sz=\"4\" w:space=\"0\" w:color=\"".self::BORDER_COLOR.'"/>';
        $xml = '<w:tbl><w:tblPr><w:tblW w:w="'.array_sum($widths).'" w:type="dxa"/>'
            .'<w:tblBorders>'.$border('top').$border('left').$border('bottom').$border('right').$border('insideH').$border('insideV').'</w:tblBorders>'
            .'<w:tblLayout w:type="fixed"/>'
            .'<w:tblCellMar><w:top w:w="60" w:type="dxa"/><w:left w:w="100" w:type="dxa"/><w:bottom w:w="60" w:type="dxa"/><w:right w:w="100" w:type="dxa"/></w:tblCellMar>'
            .'</w:tblPr><w:tblGrid>';
        foreach ($widths as $width) {
            $xml .= "<w:gridCol w:w=\"{$width}\"/>";
        }
        $xml .= '</w:tblGrid>';

        foreach ($rows as $rowIndex => $cells) {
            $isHeader = ($options['header'] ?? false) && $rowIndex === 0;
            $xml .= '<w:tr>'.($isHeader ? '<w:trPr><w:tblHeader/></w:trPr>' : '');
            foreach (array_pad($cells, $columns, '') as $columnIndex => $cell) {
                $fill = $isHeader ? ($options['headerFill'] ?? '4F46E5') : ($options['fills'][$columnIndex] ?? null);
                $runs = is_array($cell) ? $cell : [['text' => $cell]];
                $runs = array_map(function ($run) use ($isHeader, $options, $size) {
                    $run = is_array($run) ? $run : ['text' => $run];
                    $run['size'] ??= $size;
                    if ($isHeader) {
                        $run['bold'] = ($options['headerBold'] ?? true) || ! empty($run['bold']);
                        $run['color'] ??= $options['headerColor'] ?? 'FFFFFF';
                    }

                    return $run;
                }, $runs);

                $xml .= '<w:tc><w:tcPr><w:tcW w:w="'.($widths[$columnIndex] ?? 1000).'" w:type="dxa"/>'
                    .($fill ? "<w:shd w:val=\"clear\" w:color=\"auto\" w:fill=\"{$fill}\"/>" : '')
                    .'</w:tcPr>'.$this->paragraphXml($runs, ['after' => 0]).'</w:tc>';
            }
            $xml .= '</w:tr>';
        }

        $this->body .= $xml.'</w:tbl>'.$this->paragraphXml('', ['after' => 60]);

        return $this;
    }

    public function save(string $path, string $title = '', string $creator = 'Falcon Forms'): void
    {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Cannot create {$path}.");
        }

        $created = gmdate('Y-m-d\TH:i:s\Z');

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .implode('', array_map(
                fn (string $extension): string => "<Default Extension=\"{$extension}\" ContentType=\"".self::IMAGE_TYPES[$extension].'"/>',
                array_values(array_unique(array_map(fn (array $media): string => pathinfo($media['path'], PATHINFO_EXTENSION), $this->media))),
            ))
            .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            .'<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
            .'<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            .'</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            .'</Relationships>');
        $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .implode('', array_map(
                fn (array $media): string => "<Relationship Id=\"{$media['id']}\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/image\" Target=\"{$media['path']}\"/>",
                $this->media,
            ))
            .'</Relationships>');
        foreach ($this->media as $media) {
            $zip->addFromString('word/'.$media['path'], $media['data']);
        }
        $zip->addFromString('word/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .'<w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri" w:cs="Calibri" w:eastAsia="Calibri"/><w:sz w:val="22"/><w:szCs w:val="22"/><w:color w:val="1E293B"/><w:lang w:val="id-ID"/></w:rPr></w:rPrDefault>'
            .'<w:pPrDefault><w:pPr><w:spacing w:after="80" w:line="264" w:lineRule="auto"/></w:pPr></w:pPrDefault></w:docDefaults>'
            .'<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/><w:qFormat/></w:style>'
            .'</w:styles>');
        $zip->addFromString('docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            .'<dc:title>'.$this->escape($title).'</dc:title><dc:creator>'.$this->escape($creator).'</dc:creator>'
            ."<dcterms:created xsi:type=\"dcterms:W3CDTF\">{$created}</dcterms:created><dcterms:modified xsi:type=\"dcterms:W3CDTF\">{$created}</dcterms:modified>"
            .'</cp:coreProperties>');
        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing">'
            .'<w:body>'.$this->body
            .'<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1134" w:header="567" w:footer="567" w:gutter="0"/></w:sectPr>'
            .'</w:body></w:document>');

        $zip->close();
    }

    /**
     * @param  string|list<string|array<string, mixed>>  $runs
     * @param  array<string, mixed>  $options
     */
    protected function paragraphXml(string|array $runs, array $options): string
    {
        // Children of w:pPr must follow the schema order, or Word reports the file as damaged.
        $properties = '';
        if (! empty($options['keepNext'])) {
            $properties .= '<w:keepNext/>';
        }
        if (! empty($options['pageBreakBefore'])) {
            $properties .= '<w:pageBreakBefore/>';
        }
        if (! empty($options['border'])) {
            $properties .= '<w:pBdr><w:top w:val="single" w:sz="6" w:space="4" w:color="'.$options['border'].'"/>'
                .'<w:left w:val="single" w:sz="6" w:space="4" w:color="'.$options['border'].'"/>'
                .'<w:bottom w:val="single" w:sz="6" w:space="4" w:color="'.$options['border'].'"/>'
                .'<w:right w:val="single" w:sz="6" w:space="4" w:color="'.$options['border'].'"/></w:pBdr>';
        }
        if (! empty($options['fill'])) {
            $properties .= '<w:shd w:val="clear" w:color="auto" w:fill="'.$options['fill'].'"/>';
        }
        if (! empty($options['bidi'])) {
            $properties .= '<w:bidi/>';
        }
        $properties .= sprintf('<w:spacing w:before="%d" w:after="%d"/>', $options['before'] ?? 0, $options['after'] ?? 80);
        if (! empty($options['indent'])) {
            $properties .= '<w:ind w:left="'.(int) $options['indent'].'"/>';
        }
        if (! empty($options['align'])) {
            $properties .= '<w:jc w:val="'.$options['align'].'"/>';
        }

        $xml = "<w:p><w:pPr>{$properties}</w:pPr>";
        foreach (is_array($runs) ? $runs : [$runs] as $run) {
            $xml .= $this->runXml(is_array($run) ? $run : ['text' => $run]);
        }

        return $xml.'</w:p>';
    }

    /**
     * @param  array<string, mixed>  $run
     */
    protected function runXml(array $run): string
    {
        if (($run['text'] ?? '') === '') {
            return '';
        }

        // Children of w:rPr must follow the schema order too.
        $font = $run['font'] ?? null;
        $csFont = $run['csFont'] ?? $font;
        $size = isset($run['size']) ? (int) round($run['size'] * 2) : null;
        $csSize = isset($run['csSize']) ? (int) round($run['csSize'] * 2) : $size;
        $runProperties = ($font || $csFont ? '<w:rFonts'.($font ? ' w:ascii="'.$font.'" w:hAnsi="'.$font.'"' : '').($csFont ? ' w:cs="'.$csFont.'"' : '').'/>' : '')
            .(! empty($run['bold']) ? '<w:b/><w:bCs/>' : '')
            .(! empty($run['italic']) ? '<w:i/><w:iCs/>' : '')
            .(! empty($run['color']) ? '<w:color w:val="'.$run['color'].'"/>' : '')
            .($size ? '<w:sz w:val="'.$size.'"/>' : '')
            .($csSize ? '<w:szCs w:val="'.$csSize.'"/>' : '')
            .(! empty($run['underline']) ? '<w:u w:val="single"/>' : '')
            .(! empty($run['shade']) ? '<w:shd w:val="clear" w:color="auto" w:fill="'.$run['shade'].'"/>' : '')
            .(! empty($run['rtl']) ? '<w:rtl/><w:lang w:bidi="ar-SA"/>' : '');

        $content = implode('<w:br/>', array_map(
            fn (string $line): string => $line === '' ? '' : '<w:t xml:space="preserve">'.$this->escape($line).'</w:t>',
            explode("\n", (string) $run['text']),
        ));

        return '<w:r>'.($runProperties !== '' ? "<w:rPr>{$runProperties}</w:rPr>" : '').$content.'</w:r>';
    }

    protected function escape(string $value): string
    {
        $value = (string) preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $value);

        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
