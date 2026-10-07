<?php

namespace App\Services;

use App\Support\MediaUrl;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use SimpleXMLElement;
use ZipArchive;

class DocxImportService
{
    /**
     * Words accepted after "Tipe:" and the question type they stand for.
     * "benar salah" and "menjodohkan" are special forms handled separately.
     *
     * @var array<string, string>
     */
    public const TYPE_ALIASES = [
        'pilihan ganda' => 'Multiple choice',
        'pg' => 'Multiple choice',
        'multiple choice' => 'Multiple choice',
        'pilihan ganda kompleks' => 'Checkboxes',
        'pgk' => 'Checkboxes',
        'kotak centang' => 'Checkboxes',
        'checkbox' => 'Checkboxes',
        'checkboxes' => 'Checkboxes',
        'drop down' => 'Drop-down',
        'dropdown' => 'Drop-down',
        'pilihan dropdown' => 'Drop-down',
        'benar salah' => 'true-false',
        'benar atau salah' => 'true-false',
        'true false' => 'true-false',
        'isian singkat' => 'Short answer',
        'isian' => 'Short answer',
        'jawaban singkat' => 'Short answer',
        'short answer' => 'Short answer',
        'uraian' => 'Paragraph',
        'esai' => 'Paragraph',
        'essay' => 'Paragraph',
        'paragraf' => 'Paragraph',
        'paragraph' => 'Paragraph',
        'menjodohkan' => 'matching',
        'jodohkan' => 'matching',
        'pasangkan' => 'matching',
        'matching' => 'matching',
        'kisi pilihan ganda' => 'Multiple-choice grid',
        'grid pilihan ganda' => 'Multiple-choice grid',
        'kisi kotak centang' => 'Tick box grid',
        'grid kotak centang' => 'Tick box grid',
        'skala linear' => 'Linear scale',
        'skala' => 'Linear scale',
        'linear scale' => 'Linear scale',
        'rating' => 'Rating',
        'bintang' => 'Rating',
        'tanggal' => 'Date',
        'date' => 'Date',
        'waktu' => 'Time',
        'jam' => 'Time',
        'time' => 'Time',
    ];

    /**
     * Notes for the teacher about questions that could not be read exactly as written.
     *
     * @var list<string>
     */
    protected array $warnings = [];

    public function __construct(
        protected MediaUrl $mediaUrl = new MediaUrl,
    ) {}

    /**
     * Notes collected during the last import.
     *
     * @return list<string>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /**
     * Parse an uploaded Word (.docx) file into Alsenform Question structures.
     *
     * @return list<array<string, mixed>>
     *
     * @throws \RuntimeException
     */
    public function parseDocx(UploadedFile|string $file): array
    {
        $filePath = $file instanceof UploadedFile ? $file->getRealPath() : $file;
        $this->warnings = [];

        $zip = new ZipArchive;
        if ($zip->open($filePath) !== true) {
            throw new \RuntimeException('Gagal membuka berkas Word (.docx). Berkas mungkin rusak.');
        }

        try {
            $documentXml = $zip->getFromName('word/document.xml');
            if (! $documentXml) {
                throw new \RuntimeException('Format berkas Word tidak valid atau tidak memiliki konten teks.');
            }

            // Parse relationship map for embedded images
            $relMap = [];
            $relsXml = $zip->getFromName('word/_rels/document.xml.rels');
            if ($relsXml) {
                libxml_use_internal_errors(true);
                try {
                    $relsDoc = new SimpleXMLElement($relsXml);
                    foreach ($relsDoc->Relationship as $rel) {
                        $id = (string) $rel['Id'];
                        $target = (string) $rel['Target'];
                        $relMap[$id] = $target;
                    }
                } catch (\Throwable) {
                    // Ignore rel parsing error
                }
            }

            // Extract body elements (paragraphs and tables in document order)
            $elements = $this->extractBodyElements($documentXml, $relMap, $zip);

            if (empty($elements)) {
                throw new \RuntimeException('Tidak ditemukan teks atau pertanyaan di dalam berkas Word.');
            }

            $questions = $this->parseQuestionsFromElements($elements);

            if (empty($questions)) {
                throw new \RuntimeException('Tidak ditemukan butir soal yang valid di dalam berkas Word. Pastikan soal memiliki teks pertanyaan dan opsi jawaban (pilihan ganda/isian/tabel).');
            }

            return $questions;
        } finally {
            $zip->close();
        }
    }

    /**
     * Extract structured elements (paragraphs and tables with text + media) in sequential order.
     *
     * @param  array<string, string>  $relMap
     * @return list<array<string, mixed>>
     */
    protected function extractBodyElements(string $documentXml, array $relMap, ZipArchive $zip): array
    {
        libxml_use_internal_errors(true);
        try {
            $doc = new SimpleXMLElement($documentXml);
            $doc->registerXPathNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
            $doc->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');
            $doc->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');

            $elements = [];

            foreach ($doc->xpath('//w:body/*') as $node) {
                $name = $node->getName();

                if ($name === 'p') {
                    $text = '';
                    foreach ($node->xpath('.//w:t') as $tNode) {
                        $text .= (string) $tNode;
                    }
                    $text = trim($text);

                    $media = $this->extractMediaFromXml($node->asXML() ?: '', $relMap, $zip);

                    if ($text !== '' || ! empty($media)) {
                        $elements[] = [
                            'type' => 'paragraph',
                            'text' => $text,
                            'media' => $media,
                        ];
                    }
                } elseif ($name === 'tbl') {
                    $rows = [];
                    foreach ($node->xpath('.//w:tr') as $tr) {
                        $rowCells = [];
                        foreach ($tr->xpath('.//w:tc') as $tc) {
                            $cellText = '';
                            foreach ($tc->xpath('.//w:t') as $tNode) {
                                $cellText .= (string) $tNode;
                            }
                            $rowCells[] = trim($cellText);
                        }
                        if (! empty(array_filter($rowCells, fn ($c) => $c !== ''))) {
                            $rows[] = $rowCells;
                        }
                    }

                    $media = $this->extractMediaFromXml($node->asXML() ?: '', $relMap, $zip);

                    if (! empty($rows) || ! empty($media)) {
                        $elements[] = [
                            'type' => 'table',
                            'rows' => $rows,
                            'media' => $media,
                        ];
                    }
                }
            }

            return $elements;
        } catch (\Throwable $e) {
            throw new \RuntimeException('Gagal mengurai dokumen Word: '.$e->getMessage());
        }
    }

    /**
     * Extract media images referenced in the XML snippet.
     *
     * @param  array<string, string>  $relMap
     * @return list<array<string, mixed>>
     */
    protected function extractMediaFromXml(string $xml, array $relMap, ZipArchive $zip): array
    {
        $media = [];
        if ($xml !== '' && preg_match_all('/(?:r:embed|r:id|embed)=["\'](rId\d+)["\']/i', $xml, $embedMatches)) {
            foreach (array_unique($embedMatches[1]) as $rId) {
                if (isset($relMap[$rId])) {
                    $targetPath = $relMap[$rId];
                    $zipEntryName = str_starts_with($targetPath, 'media/')
                        ? 'word/'.$targetPath
                        : (str_starts_with($targetPath, 'word/') ? $targetPath : 'word/'.$targetPath);

                    $imgData = $zip->getFromName($zipEntryName);
                    if ($imgData !== false && strlen($imgData) > 0) {
                        $ext = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION)) ?: 'png';
                        $fileName = 'docx_img_'.Str::random(12).'.'.$ext;
                        $storagePath = 'media/docx/'.$fileName;

                        Storage::disk('public')->put($storagePath, $imgData);

                        $media[] = [
                            'type' => 'image',
                            'url' => $this->mediaUrl->forPublicPath($storagePath),
                            'name' => basename($targetPath),
                        ];
                    }
                }
            }
        }

        return $media;
    }

    /**
     * Parse extracted body elements into Alsenform Question structures.
     *
     * @param  list<array<string, mixed>>  $elements
     * @return list<array<string, mixed>>
     */
    protected function parseQuestionsFromElements(array $elements): array
    {
        // Everything above a "MULAI SOAL" line (the template's instructions) is not a question.
        foreach ($elements as $index => $el) {
            if ($el['type'] === 'paragraph' && preg_match('/^[\s=*#_\-]*mulai\s+soal[\s=*#_\-:]*$/iu', $el['text'])) {
                $elements = array_slice($elements, $index + 1);
                break;
            }
        }

        // Lines starting with "//" are notes for the teacher, not part of a question.
        $elements = array_values(array_filter(
            $elements,
            fn (array $el) => ! ($el['type'] === 'paragraph' && str_starts_with(ltrim($el['text']), '//')),
        ));

        // Check if the document uses "Jawaban: [A-E]" style markers
        $hasJawabanMarker = false;
        foreach ($elements as $el) {
            if ($el['type'] === 'paragraph' && preg_match('/^(?:kunci\s*jawaban|kunci|jawaban|answer|key)\s*:\s*(.+)$/i', $el['text'])) {
                $hasJawabanMarker = true;
                break;
            }
        }

        if ($hasJawabanMarker) {
            return $this->parseByJawabanMarker($elements);
        }

        return $this->parseByNumberingPrefix($elements);
    }

    /**
     * Parse questions where each question block ends with "Jawaban: ..." or "Kunci: ...".
     *
     * @param  list<array<string, mixed>>  $elements
     * @return list<array<string, mixed>>
     */
    protected function parseByJawabanMarker(array $elements): array
    {
        $questions = [];
        $currentLines = [];
        $currentMedia = [];
        $currentTables = [];
        $nextId = 1000;

        foreach ($elements as $el) {
            if ($el['type'] === 'paragraph') {
                $text = $el['text'];
                $media = $el['media'];

                // Skip preliminary school header/instructions if we haven't found any questions yet
                if (empty($questions) && empty($currentLines) && empty($currentTables) && preg_match('/^(?:yayasan|smp|sma|smk|sd|madrasah|instansi|dinas|jl\.|jalan|tlp|telepon|penilaian|ujian|asesmen|mata pelajaran|kelas|hari|tanggal|waktu|petunjuk|bacalah|tulislah|periksalah|pilihlah salah|pilihan salah)/i', $text)) {
                    continue;
                }

                if (! empty($media)) {
                    $currentMedia = array_merge($currentMedia, $media);
                }

                // Check if this line marks the answer key for the current question
                if (preg_match('/^(?:kunci\s*jawaban|jawaban|kunci|answer|key)\s*:\s*(.+)$/i', $text, $m)) {
                    $rawAnswer = trim($m[1]);

                    $parsed = $this->buildQuestionFromBlock($currentLines, $currentMedia, $currentTables, $rawAnswer, $nextId++, count($questions) + 1);
                    if ($parsed !== null) {
                        $questions[] = $parsed;
                    }

                    $currentLines = [];
                    $currentMedia = [];
                    $currentTables = [];
                } else {
                    if ($text !== '') {
                        $currentLines[] = $text;
                    }
                }
            } elseif ($el['type'] === 'table') {
                $currentTables[] = $el;
                if (! empty($el['media'])) {
                    $currentMedia = array_merge($currentMedia, $el['media']);
                }
            }
        }

        return $questions;
    }

    /**
     * Build a question from the lines, images and tables of one block (ending with "Jawaban:").
     *
     * Optional lines inside the block: "Tipe: ...", "Poin: 10", "Wajib: Ya", "Skala: 1-5".
     * Without "Tipe:", lettered options make a multiple-choice question (or checkboxes when the
     * key names several letters) and a block without options becomes a short answer.
     *
     * @param  list<string>  $lines
     * @param  list<array<string, mixed>>  $media
     * @param  list<array<string, mixed>>  $tables
     * @return array<string, mixed>|null
     */
    protected function buildQuestionFromBlock(array $lines, array $media, array $tables, string $rawAnswer, int $id, int $number = 0): ?array
    {
        $originalLines = $lines;
        [$lines, $meta] = $this->extractMetadata($lines);
        $label = $number > 0 ? "Soal ke-{$number}" : 'Soal';

        $type = null;
        if (isset($meta['type'])) {
            $type = self::TYPE_ALIASES[$this->normalizeKeyword($meta['type'])] ?? null;
            if ($type === null) {
                $this->warnings[] = "{$label}: tipe \"{$meta['type']}\" tidak dikenal, tipe soal ditentukan otomatis.";
            }
        }

        // "Skala:" only belongs to scale questions; elsewhere (e.g. "Skala: 1:100.000" on a map) it is question text.
        if (isset($meta['scale']) && ! in_array($type, ['Linear scale', 'Rating'], true)) {
            [$lines, $meta] = $this->extractMetadata($originalLines, withScale: false);
        }

        $question = null;
        $description = '';

        if (! empty($tables)) {
            if (in_array($type, ['Multiple-choice grid', 'Tick box grid'], true)) {
                $question = $this->buildMarkedGridQuestion($tables[0], $lines, $media, $rawAnswer, $id, $type, $label);
            } elseif ($type === null || $type === 'true-false' || $type === 'matching') {
                $question = $this->buildGridQuestionFromTable($tables[0], $lines, $media, $rawAnswer, $id, $type);
            } else {
                // A table in a regular question is reading material for it.
                $description = $this->tableAsMarkdown($tables[0]['rows']);
            }
        }

        if ($question === null) {
            if (empty($lines)) {
                return null;
            }

            $question = $type === null
                ? $this->buildDetectedQuestion($lines, $media, $rawAnswer, $id, $label)
                : $this->buildTypedQuestion($type, $lines, $media, $rawAnswer, $id, $meta, $label);

            if ($description !== '') {
                $question['description'] = $description;
            }
        }

        if (isset($meta['points'])) {
            $question['points'] = max(0, (int) $meta['points']);
        }
        if (isset($meta['required'])) {
            $question['required'] = in_array($this->normalizeKeyword($meta['required']), ['ya', 'y', 'yes', 'wajib', 'true', '1', 'iya'], true);
        }

        return $question;
    }

    /**
     * Pull the "Tipe:", "Poin:", "Wajib:" and "Skala:" lines out of a block.
     *
     * @param  list<string>  $lines
     * @return array{0: list<string>, 1: array<string, string>}
     */
    protected function extractMetadata(array $lines, bool $withScale = true): array
    {
        $patterns = [
            'type' => '/^(?:tipe(?:\s+soal)?|jenis(?:\s+soal)?|type)\s*:\s*(.+)$/iu',
            'points' => '/^(?:poin|bobot|skor|points?)\s*:\s*(\d+)/iu',
            'required' => '/^(?:wajib(?:\s+diisi)?|required)\s*:\s*(.+)$/iu',
        ];
        if ($withScale) {
            $patterns['scale'] = '/^(?:skala|rentang|scale)\s*:\s*(.+)$/iu';
        }

        $meta = [];
        $remaining = [];
        foreach ($lines as $line) {
            foreach ($patterns as $key => $pattern) {
                if (preg_match($pattern, trim($line), $match)) {
                    $meta[$key] = trim($match[1]);

                    continue 2;
                }
            }
            $remaining[] = $line;
        }

        return [$remaining, $meta];
    }

    /**
     * Split a block into the question text and its lettered options ("A. ...", "B) ...").
     *
     * @param  list<string>  $lines
     * @return array{0: string, 1: list<string>}
     */
    protected function splitTitleAndOptions(array $lines): array
    {
        $titleParts = [];
        $options = [];
        foreach ($lines as $line) {
            if (preg_match('/^([A-E])[\.\)]\s*(.*)$/', $line, $match) && ($options !== [] || $titleParts !== [])) {
                $options[] = trim($match[2]);
            } elseif ($options === []) {
                $titleParts[] = $line;
            } else {
                // Text after the options continues the last option.
                $options[count($options) - 1] .= "\n".$line;
            }
        }

        return [$this->cleanTitle(implode("\n", $titleParts)), $options];
    }

    /**
     * A question whose type is given with "Tipe:".
     *
     * @param  list<string>  $lines
     * @param  list<array<string, mixed>>  $media
     * @param  array<string, string>  $meta
     * @return array<string, mixed>
     */
    protected function buildTypedQuestion(string $type, array $lines, array $media, string $rawAnswer, int $id, array $meta, string $label): array
    {
        [$title, $options] = $this->splitTitleAndOptions($lines);
        $noKey = $this->isNoKey($rawAnswer);

        switch ($type) {
            case 'Multiple choice':
            case 'Drop-down':
                if ($options === []) {
                    $this->warnings[] = "{$label}: tidak ada pilihan A–E, soal dijadikan isian singkat.";

                    return $this->question($id, $title, 'Short answer', [], $noKey ? '' : $rawAnswer, $media);
                }

                return $this->question($id, $title, $type, $options, $noKey ? '' : $this->choiceKey($rawAnswer, $options, $label), $media);

            case 'Checkboxes':
                if ($options === []) {
                    $this->warnings[] = "{$label}: tidak ada pilihan A–E, soal dijadikan isian singkat.";

                    return $this->question($id, $title, 'Short answer', [], $noKey ? '' : $rawAnswer, $media);
                }

                return $this->question($id, $title, 'Checkboxes', $options, $noKey ? [] : $this->checkboxKey($rawAnswer, $options, $label), $media);

            case 'true-false':
                $options = count($options) === 2 ? $options : ['Benar', 'Salah'];
                $key = '';
                if (! $noKey) {
                    $answer = $this->normalizeKeyword($rawAnswer);
                    $key = match (true) {
                        in_array($answer, ['benar', 'b', 'true', 't', 'ya', 'betul'], true) => 0,
                        in_array($answer, ['salah', 's', 'false', 'f', 'tidak', 'keliru'], true) => 1,
                        default => $this->choiceKey($rawAnswer, $options, $label),
                    };
                }

                return $this->question($id, $title, 'Multiple choice', $options, $key, $media);

            case 'Linear scale':
            case 'Rating':
                [$min, $max] = $this->scaleRange($meta['scale'] ?? '', $type === 'Rating' ? 1 : 0, $label);
                $scale = array_map('strval', range($min, $max));
                $key = ! $noKey && is_numeric(trim($rawAnswer)) && in_array((string) (int) trim($rawAnswer), $scale, true) ? (string) (int) trim($rawAnswer) : '';

                return $this->question($id, $title, $type, $scale, $key, $media);

            case 'Date':
                return $this->question($id, $title, 'Date', [], $noKey ? '' : $this->dateKey($rawAnswer, $label), $media);

            case 'Time':
                return $this->question($id, $title, 'Time', [], $noKey ? '' : $this->timeKey($rawAnswer, $label), $media);

            case 'Paragraph':
                // The "Jawaban:" line of an essay is the sample answer / rubric for the teacher.
                return $this->question($id, $title, 'Paragraph', [], $noKey ? '' : $rawAnswer, $media);

            default:
                return $this->question($id, $title, 'Short answer', [], $noKey ? '' : $rawAnswer, $media);
        }
    }

    /**
     * A question without "Tipe:": the type follows from its options and key.
     *
     * @param  list<string>  $lines
     * @param  list<array<string, mixed>>  $media
     * @return array<string, mixed>
     */
    protected function buildDetectedQuestion(array $lines, array $media, string $rawAnswer, int $id, string $label): array
    {
        $noKey = $this->isNoKey($rawAnswer);
        [$title, $options] = $this->splitTitleAndOptions($lines);

        if ($options === []) {
            // Options typed without letters: the last four lines are the options when the key is a letter.
            $isCheckboxes = (bool) preg_match('/^[a-e](\s*,\s*[a-e])+$|^[a-e]\s+dan\s+[a-e]$/i', trim($rawAnswer));
            if (($isCheckboxes || preg_match('/^[A-E]$/i', trim($rawAnswer))) && count($lines) >= 3) {
                $optionCount = min(4, count($lines) - 1);
                $options = array_slice($lines, -$optionCount);
                $title = $this->cleanTitle(implode("\n", array_slice($lines, 0, -$optionCount)));

                return $isCheckboxes
                    ? $this->question($id, $title, 'Checkboxes', $options, $this->checkboxKey($rawAnswer, $options, $label), $media)
                    : $this->question($id, $title, 'Multiple choice', $options, $this->choiceKey($rawAnswer, $options, $label), $media);
            }

            return $this->question($id, $this->cleanTitle(implode("\n", $lines)), 'Short answer', [], $noKey ? '' : $rawAnswer, $media);
        }

        if (! $noKey && preg_match('/^[A-E](\s*(?:,|dan|&)\s*[A-E])+$/i', trim($rawAnswer))) {
            return $this->question($id, $title, 'Checkboxes', $options, $this->checkboxKey($rawAnswer, $options, $label), $media);
        }

        return $this->question($id, $title, 'Multiple choice', $options, $noKey ? '' : $this->choiceKey($rawAnswer, $options, $label), $media);
    }

    /**
     * A grid whose correct cells are marked in the table (X, ✓, √ or v), or given as "1A, 2B".
     *
     * @param  array{rows: list<list<string>>, media: list<array<string, mixed>>}  $table
     * @param  list<string>  $lines
     * @param  list<array<string, mixed>>  $media
     * @return array<string, mixed>
     */
    protected function buildMarkedGridQuestion(array $table, array $lines, array $media, string $rawAnswer, int $id, string $type, string $label): array
    {
        $rows = $table['rows'];
        $header = array_shift($rows) ?? [];
        $columns = array_values(array_filter(array_map('trim', array_slice($header, 1)), fn ($cell) => $cell !== ''));

        $gridRows = [];
        $answer = [];
        foreach ($rows as $row) {
            $rowLabel = trim($row[0] ?? '');
            if ($rowLabel === '') {
                continue;
            }
            $rowIndex = count($gridRows);
            $gridRows[] = $rowLabel;

            $marked = [];
            foreach (array_slice($row, 1, count($columns)) as $columnIndex => $cell) {
                if ($this->isMarkedCell($cell)) {
                    $marked[] = $columnIndex;
                }
            }
            if ($marked !== []) {
                $answer[(string) $rowIndex] = $type === 'Tick box grid' ? $marked : $marked[0];
                if ($type === 'Multiple-choice grid' && count($marked) > 1) {
                    $this->warnings[] = "{$label}: baris \"{$rowLabel}\" menandai lebih dari satu kolom; hanya tanda pertama yang dipakai.";
                }
            }
        }

        // The key can also be written as "1A, 2C" (row number + column letter).
        if ($answer === [] && ! $this->isNoKey($rawAnswer)) {
            preg_match_all('/(\d+)\s*[-:=]?\s*([A-Z])/i', $rawAnswer, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $rowIndex = (int) $match[1] - 1;
                $columnIndex = ord(strtoupper($match[2])) - ord('A');
                if (isset($gridRows[$rowIndex]) && isset($columns[$columnIndex])) {
                    if ($type === 'Tick box grid') {
                        $answer[(string) $rowIndex][] = $columnIndex;
                    } else {
                        $answer[(string) $rowIndex] = $columnIndex;
                    }
                }
            }
        }

        if ($columns === [] || $gridRows === []) {
            $this->warnings[] = "{$label}: tabel kisi butuh baris judul kolom dan minimal satu baris pernyataan.";
        }

        return [
            ...$this->question($id, $this->cleanTitle(implode("\n", $lines)) ?: 'Lengkapi tabel berikut:', $type, [], (object) $answer, $media),
            'rows' => $gridRows,
            'columns' => $columns,
        ];
    }

    /**
     * A table cell ticked as the correct answer (X, v, ✓, √ ...).
     */
    protected function isMarkedCell(string $cell): bool
    {
        return (bool) preg_match('/^\s*(?:x|v|✓|✔|√|☑|☒|benar|1)\s*$/iu', $cell);
    }

    /**
     * Index of the first header cell matching the pattern.
     *
     * @param  list<string>  $header
     */
    protected function columnIndex(array $header, string $pattern): ?int
    {
        foreach ($header as $index => $cell) {
            if (preg_match($pattern, trim($cell))) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $options
     */
    protected function choiceKey(string $rawAnswer, array $options, string $label): int|string
    {
        $answer = trim($rawAnswer);
        if (preg_match('/^([A-E])[\.\)]?$/i', $answer, $match)) {
            $index = ord(strtoupper($match[1])) - ord('A');
            if (isset($options[$index])) {
                return $index;
            }
        }

        foreach ($options as $index => $option) {
            if (strcasecmp(trim($option), $answer) === 0) {
                return $index;
            }
        }

        $this->warnings[] = "{$label}: kunci \"{$answer}\" tidak cocok dengan pilihan mana pun; kunci dikosongkan.";

        return '';
    }

    /**
     * @param  list<string>  $options
     * @return list<int>
     */
    protected function checkboxKey(string $rawAnswer, array $options, string $label): array
    {
        $cleaned = (string) preg_replace('/\b(?:dan|atau|serta|and|or)\b|&/i', ' ', $rawAnswer);
        preg_match_all('/\b([A-E])\b/i', $cleaned, $letters);

        $indices = [];
        foreach (array_unique(array_map('strtoupper', $letters[1])) as $letter) {
            $index = ord($letter) - ord('A');
            if (isset($options[$index])) {
                $indices[] = $index;
            }
        }
        sort($indices);

        if ($indices === []) {
            $this->warnings[] = "{$label}: kunci kotak centang \"{$rawAnswer}\" tidak terbaca; tulis huruf pilihan, misalnya \"A, C\".";
        }

        return $indices;
    }

    /**
     * @return array{0: int, 1: int}
     */
    protected function scaleRange(string $value, int $defaultMin, string $label): array
    {
        if ($value === '') {
            return [$defaultMin === 0 ? 1 : $defaultMin, 5];
        }

        if (preg_match('/^(\d+)\s*(?:-|–|sampai|s\/d|\.\.)\s*(\d+)$/iu', trim($value), $match)) {
            $min = (int) $match[1];
            $max = (int) $match[2];
            if ($min >= 0 && $min <= 1 && $max > $min && $max <= 10) {
                return [$min, $max];
            }
        }

        $this->warnings[] = "{$label}: skala \"{$value}\" tidak valid (contoh: 1-5, paling besar 0-10); dipakai skala 1-5.";

        return [1, 5];
    }

    protected function dateKey(string $rawAnswer, string $label): string
    {
        $answer = trim($rawAnswer);
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $answer, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
        }
        if (preg_match('/^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{4})$/', $answer, $m) && checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }

        $this->warnings[] = "{$label}: tanggal \"{$answer}\" tidak terbaca (contoh: 17-08-1945); kunci dikosongkan.";

        return '';
    }

    protected function timeKey(string $rawAnswer, string $label): string
    {
        $answer = trim($rawAnswer);
        if (preg_match('/^(\d{1,2})[:.](\d{2})$/', $answer, $m) && (int) $m[1] < 24 && (int) $m[2] < 60) {
            return sprintf('%02d:%02d', $m[1], $m[2]);
        }

        $this->warnings[] = "{$label}: waktu \"{$answer}\" tidak terbaca (contoh: 07:30); kunci dikosongkan.";

        return '';
    }

    /**
     * "Jawaban: -" (or empty / "tanpa kunci") means the question has no answer key.
     */
    protected function isNoKey(string $rawAnswer): bool
    {
        return in_array($this->normalizeKeyword($rawAnswer), ['', 'tidak ada', 'tanpa kunci', 'lihat tabel', 'bebas', 'none'], true)
            || preg_match('/^[\s\-–—]*$/u', $rawAnswer) === 1;
    }

    protected function normalizeKeyword(string $value): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower(trim($value))));
    }

    /**
     * Remove the typed question number ("1." / "1)") from the start of the text.
     */
    protected function cleanTitle(string $title): string
    {
        $cleaned = trim((string) preg_replace('/^\d+[\.\)]\s*/', '', trim($title)));

        return $cleaned !== '' ? $cleaned : trim($title);
    }

    /**
     * @param  list<list<string>>  $rows
     */
    protected function tableAsMarkdown(array $rows): string
    {
        if ($rows === []) {
            return '';
        }

        $markdown = '| '.implode(' | ', $rows[0])." |\n";
        $markdown .= '| '.implode(' | ', array_fill(0, count($rows[0]), '---'))." |\n";
        foreach (array_slice($rows, 1) as $row) {
            $markdown .= '| '.implode(' | ', $row)." |\n";
        }

        return trim($markdown);
    }

    /**
     * @param  list<string>  $options
     * @param  list<array<string, mixed>>  $media
     * @return array<string, mixed>
     */
    protected function question(int $id, string $title, string $type, array $options, mixed $answer, array $media): array
    {
        return [
            'id' => $id,
            'title' => $title !== '' ? $title : 'Tanpa judul',
            'description' => '',
            'type' => $type,
            'options' => array_values($options),
            'answer' => $answer,
            'required' => false,
            'points' => 1,
            'media' => $media,
        ];
    }

    /**
     * Parse question containing a table into Multiple-choice grid (True/False or Matching).
     *
     * @param  array{rows: list<list<string>>, media: list<array<string, mixed>>}  $table
     * @param  list<string>  $lines
     * @param  list<array<string, mixed>>  $media
     * @return array<string, mixed>
     */
    protected function buildGridQuestionFromTable(array $table, array $lines, array $media, string $rawAnswer, int $id, ?string $forced = null): array
    {
        $rows = $table['rows'];
        $title = implode("\n", $lines);
        $cleanTitle = preg_replace('/^\d+[\.\)]\s*/', '', trim($title));

        // 1. Detect if this is a True/False table (Benar / Salah)
        $isTrueFalse = $forced === 'true-false';
        if ($forced === 'matching') {
            $isTrueFalse = false;
        } elseif (preg_match('/^[BS](\s*,\s*[BS])+/i', $rawAnswer) || preg_match('/(?:benar|salah)/i', $rawAnswer)) {
            $isTrueFalse = true;
        } elseif (preg_match('/benar\s*(?:atau|\/)\s*salah/i', $cleanTitle) || preg_match('/true\s*(?:or|\/)\s*false/i', $cleanTitle)) {
            $isTrueFalse = true;
        } else {
            foreach ($rows as $row) {
                foreach ($row as $cell) {
                    if (preg_match('/^benar\s*(?:\/|\s)\s*salah$/i', trim($cell)) || preg_match('/^(?:benar|salah)$/i', trim($cell))) {
                        $isTrueFalse = true;
                        break 2;
                    }
                }
            }
        }

        if ($isTrueFalse) {
            if ($cleanTitle === '') {
                $cleanTitle = 'Tentukanlah Benar atau Salah dari setiap pernyataan berikut:';
            }

            // Detect header row (e.g. No | Pernyataan | Benar/salah)
            $isHeader = false;
            if ($forced === 'true-false') {
                // Match whole header cells, so a statement that merely contains "no" or "soal" stays a row.
                $isHeader = isset($rows[0]) && $this->columnIndex($rows[0], '/^(?:no\.?|nomor|pernyataan|soal|benar|salah|benar\s*\/\s*salah|b\s*\/\s*s|keterangan|jawaban)$/i') !== null;
            } elseif (isset($rows[0])) {
                $firstRowText = implode(' ', $rows[0]);
                if (preg_match('/(?:no|nomor|pernyataan|soal|benar|salah)/i', $firstRowText)) {
                    $isHeader = true;
                }
            }

            $dataRows = $isHeader ? array_slice($rows, 1) : $rows;
            $gridRows = [];
            $gridRowCells = [];

            foreach ($dataRows as $rIdx => $r) {
                if (empty(array_filter($r, fn ($c) => $c !== ''))) {
                    continue;
                }

                if (count($r) >= 3) {
                    $num = trim($r[0]);
                    $stmt = trim($r[1]);
                    if ($num !== '') {
                        $rowText = (str_ends_with($num, '.') || str_ends_with($num, ')') ? $num : "$num.").' '.$stmt;
                    } else {
                        $rowText = $stmt;
                    }
                } elseif (count($r) === 2) {
                    $rowText = trim($r[0]);
                } else {
                    $rowText = trim(implode(' ', $r));
                }

                if ($rowText !== '') {
                    $gridRows[] = $rowText;
                    $gridRowCells[] = $r;
                }
            }

            // Columns are Benar and Salah
            $columns = ['Benar', 'Salah'];

            // Parse answer key tokens: e.g. "S, S, B, B, B" or "B, S, S, B, B"
            $tokens = $this->isNoKey($rawAnswer) || preg_match('/^lihat\b/i', trim($rawAnswer)) ? [] : preg_split('/[\s,]+/', trim($rawAnswer));
            $answer = [];
            foreach ($gridRows as $rIdx => $rowLabel) {
                if (isset($tokens[$rIdx])) {
                    $tok = strtoupper(trim($tokens[$rIdx]));
                    if (str_starts_with($tok, 'B') || str_starts_with($tok, 'T')) {
                        $answer[(string) $rIdx] = 0; // Benar
                    } elseif (str_starts_with($tok, 'S') || str_starts_with($tok, 'F')) {
                        $answer[(string) $rIdx] = 1; // Salah
                    }
                }
            }

            // Without a typed key, an X under separate "Benar" and "Salah" columns marks the answer.
            if ($answer === [] && $isHeader) {
                $benarColumn = $this->columnIndex($rows[0], '/^(?:benar|b|true)$/i');
                $salahColumn = $this->columnIndex($rows[0], '/^(?:salah|s|false)$/i');
                if ($benarColumn !== null && $salahColumn !== null) {
                    foreach ($gridRowCells as $rowIndex => $r) {
                        if ($this->isMarkedCell($r[$benarColumn] ?? '')) {
                            $answer[(string) $rowIndex] = 0;
                        } elseif ($this->isMarkedCell($r[$salahColumn] ?? '')) {
                            $answer[(string) $rowIndex] = 1;
                        }
                    }
                }
            }

            return [
                'id' => $id,
                'title' => $cleanTitle,
                'description' => '',
                'type' => 'Multiple-choice grid',
                'options' => [],
                'rows' => $gridRows,
                'columns' => $columns,
                'answer' => (object) $answer,
                'required' => false,
                'points' => 1,
                'media' => $media,
            ];
        }

        // 2. Detect if this is a Matching table (Menjodohkan / Pasangkan)
        $isMatching = $forced === 'matching'
            || preg_match('/(\d+)\s*[-:]?\s*([A-Za-z])/i', $rawAnswer)
            || preg_match('/(?:pasangkan|jodohkan|match)/i', $cleanTitle);

        if ($isMatching) {
            if ($cleanTitle === '') {
                $cleanTitle = 'Pasangkanlah pernyataan berikut dengan pilihan yang tepat:';
            }

            // Skip header row if detected
            $isHeader = false;
            if ($forced === 'matching') {
                // Rows of a "Tipe: Menjodohkan" table start with their number, so a first row without one is the header.
                $isHeader = isset($rows[0]) && ! preg_match('/^\d+[\.\)]?$/', trim($rows[0][0] ?? ''));
            } elseif (isset($rows[0])) {
                $firstRowText = implode(' ', $rows[0]);
                if (preg_match('/(?:no|nomor|kiri|kanan|pilar|negara|bentuk|aspek)/i', $firstRowText)) {
                    $isHeader = true;
                }
            }

            $dataRows = $isHeader ? array_slice($rows, 1) : $rows;
            $gridRows = [];
            $gridCols = [];

            foreach ($dataRows as $rIdx => $r) {
                if (empty(array_filter($r, fn ($c) => $c !== ''))) {
                    continue;
                }

                $num = trim($r[0] ?? (string) ($rIdx + 1));
                $left = trim($r[1] ?? '');
                $right = trim($r[2] ?? '');

                if ($num !== '') {
                    $gridRows[] = (str_ends_with($num, '.') || str_ends_with($num, ')') ? $num : "$num.").' '.$left;
                } else {
                    $gridRows[] = $left;
                }

                $letter = chr(ord('A') + $rIdx);
                $gridCols[] = "$letter. $right";
            }

            // Parse matching answer: e.g. "1B, 2C, 3A, dan 4D"
            preg_match_all('/(\d+)\s*[-:]?\s*([A-Za-z])/i', $rawAnswer, $matches, PREG_SET_ORDER);
            $answer = [];
            foreach ($matches as $m) {
                $rIdx = (int) $m[1] - 1;
                $cIdx = ord(strtoupper($m[2])) - ord('A');
                if ($rIdx >= 0 && $rIdx < count($gridRows) && $cIdx >= 0 && $cIdx < count($gridCols)) {
                    $answer[(string) $rIdx] = $cIdx;
                }
            }

            return [
                'id' => $id,
                'title' => $cleanTitle,
                'description' => '',
                'type' => 'Multiple-choice grid',
                'options' => [],
                'rows' => $gridRows,
                'columns' => $gridCols,
                'answer' => (object) $answer,
                'required' => false,
                'points' => 1,
                'media' => $media,
            ];
        }

        // 3. Fallback: Stimulus table within a regular question
        // Build markdown representation of the table into the question description/title
        $tableMd = "\n\n| ".implode(' | ', $rows[0] ?? [])." |\n";
        $tableMd .= '| '.implode(' | ', array_fill(0, count($rows[0] ?? [1]), '---'))." |\n";
        for ($i = 1; $i < count($rows); $i++) {
            $tableMd .= '| '.implode(' | ', $rows[$i])." |\n";
        }

        return [
            'id' => $id,
            'title' => $cleanTitle !== '' ? $cleanTitle : 'Perhatikan tabel berikut:',
            'description' => trim($tableMd),
            'type' => 'Short answer',
            'options' => [],
            'answer' => $rawAnswer,
            'required' => false,
            'points' => 1,
            'media' => $media,
        ];
    }

    /**
     * Parse questions using standard numbering prefixes (e.g. "1. Soal...", "A. Opsi...").
     *
     * @param  list<array<string, mixed>>  $elements
     * @return list<array<string, mixed>>
     */
    protected function parseByNumberingPrefix(array $elements): array
    {
        $questions = [];
        $currentQuestion = null;
        $currentTables = [];
        $nextId = 1000;

        foreach ($elements as $el) {
            if ($el['type'] === 'paragraph') {
                $text = $el['text'];
                $media = $el['media'];

                // Check if question number: e.g. "1. ..." or "1) ..."
                if (preg_match('/^\d+[\.\)]\s*(.*)$/i', $text, $matches)) {
                    if ($currentQuestion) {
                        $questions[] = $this->finalizeNumberedQuestion($currentQuestion, $currentTables);
                        $currentTables = [];
                    }
                    $currentQuestion = [
                        'id' => $nextId++,
                        'title' => trim($matches[1]) ?: $text,
                        'description' => '',
                        'type' => 'Multiple choice',
                        'options' => [],
                        'answer' => 0,
                        'raw_answer' => '',
                        'required' => false,
                        'points' => 1,
                        'media' => $media,
                    ];
                }
                // Check if option: e.g. "A. ..." or "B) ..."
                elseif (preg_match('/^([A-E])[\.\)]\s*(.*)$/i', $text, $matches)) {
                    if ($currentQuestion) {
                        $currentQuestion['options'][] = trim($matches[2]);
                        if (! empty($media)) {
                            $currentQuestion['media'] = array_merge($currentQuestion['media'], $media);
                        }
                    }
                }
                // Check if answer key: e.g. "Jawaban: B"
                elseif (preg_match('/^(?:kunci\s*jawaban|jawaban|kunci|answer|key)\s*:\s*(.*)$/i', $text, $matches)) {
                    if ($currentQuestion) {
                        $currentQuestion['raw_answer'] = trim($matches[1]);
                    }
                } else {
                    if ($currentQuestion && empty($currentQuestion['options'])) {
                        $currentQuestion['title'] .= "\n".$text;
                        if (! empty($media)) {
                            $currentQuestion['media'] = array_merge($currentQuestion['media'], $media);
                        }
                    }
                }
            } elseif ($el['type'] === 'table') {
                $currentTables[] = $el;
            }
        }

        if ($currentQuestion) {
            $questions[] = $this->finalizeNumberedQuestion($currentQuestion, $currentTables);
        }

        return $questions;
    }

    /**
     * Finalize a question parsed by numbering prefix.
     *
     * @param  array<string, mixed>  $q
     * @param  list<array<string, mixed>>  $tables
     * @return array<string, mixed>
     */
    protected function finalizeNumberedQuestion(array $q, array $tables): array
    {
        if (! empty($tables)) {
            $raw = $q['raw_answer'] ?? '';

            return $this->buildGridQuestionFromTable($tables[0], [$q['title']], $q['media'], $raw, $q['id']);
        }

        if (empty($q['options'])) {
            $q['type'] = 'Short answer';
            $q['answer'] = $q['raw_answer'] ?? '';
        } else {
            $q['type'] = 'Multiple choice';
            $raw = $q['raw_answer'] ?? '';
            if (preg_match('/^([A-E])$/i', $raw, $lm)) {
                $q['answer'] = ord(strtoupper($lm[1])) - ord('A');
            } else {
                $foundIndex = -1;
                foreach ($q['options'] as $idx => $opt) {
                    if (strcasecmp(trim($opt), $raw) === 0) {
                        $foundIndex = $idx;
                        break;
                    }
                }
                $q['answer'] = $foundIndex !== -1 ? $foundIndex : 0;
            }
        }
        unset($q['raw_answer']);

        return $q;
    }
}
