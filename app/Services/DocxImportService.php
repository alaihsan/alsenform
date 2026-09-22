<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use SimpleXMLElement;
use ZipArchive;

class DocxImportService
{
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
                            'url' => asset('storage/'.$storagePath),
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

                    $parsed = $this->buildQuestionFromBlock($currentLines, $currentMedia, $currentTables, $rawAnswer, $nextId++);
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
     * Build question object from accumulated text lines, media, tables, and answer key.
     *
     * @param  list<string>  $lines
     * @param  list<array<string, mixed>>  $media
     * @param  list<array<string, mixed>>  $tables
     * @return array<string, mixed>|null
     */
    protected function buildQuestionFromBlock(array $lines, array $media, array $tables, string $rawAnswer, int $id): ?array
    {
        // 1. If the block contains a table, handle as Grid Question or stimulus table
        if (! empty($tables)) {
            return $this->buildGridQuestionFromTable($tables[0], $lines, $media, $rawAnswer, $id);
        }

        if (empty($lines)) {
            return null;
        }

        // 2. Check if lines have letter prefix (A., B., C., D.)
        $hasLetterPrefix = false;
        foreach ($lines as $line) {
            if (preg_match('/^[A-E][\.\)]\s*/i', $line)) {
                $hasLetterPrefix = true;
                break;
            }
        }

        $title = '';
        $options = [];
        $type = 'Multiple choice';

        if ($hasLetterPrefix) {
            $titleParts = [];
            foreach ($lines as $line) {
                if (preg_match('/^([A-E])[\.\)]\s*(.*)$/i', $line, $lm)) {
                    $options[] = trim($lm[2]);
                } elseif (empty($options)) {
                    $titleParts[] = $line;
                }
            }
            $title = implode("\n", $titleParts);
        } else {
            // Check if multiple choice or checkboxes based on answer pattern
            $isCheckboxes = preg_match('/^[a-e](\s*,\s*[a-e])+/i', $rawAnswer)
                || preg_match('/[a-e]\s+dan\s+[a-e]/i', $rawAnswer);

            if ($isCheckboxes) {
                $type = 'Checkboxes';
                $optCount = 4;
                if (count($lines) > $optCount) {
                    $options = array_slice($lines, -$optCount);
                    $title = implode("\n", array_slice($lines, 0, -$optCount));
                } else {
                    $title = $lines[0] ?? '';
                    $options = array_slice($lines, 1);
                }
            } elseif (preg_match('/^[A-E]$/i', $rawAnswer)) {
                $optCount = 4;
                if (count($lines) > $optCount) {
                    $options = array_slice($lines, -$optCount);
                    $title = implode("\n", array_slice($lines, 0, -$optCount));
                } else {
                    $title = $lines[0] ?? '';
                    $options = array_slice($lines, 1);
                }
            } else {
                $title = implode("\n", $lines);
            }
        }

        // Clean leading question numbering from title (e.g. "1. Soal..." -> "Soal...")
        $cleanTitle = preg_replace('/^\d+[\.\)]\s*/', '', trim($title));
        if ($cleanTitle === '') {
            $cleanTitle = $title;
        }

        // Resolve answer
        $answer = 0;
        if ($type === 'Checkboxes') {
            $cleanAnswer = preg_replace('/\b(?:dan|atau|serta|and|or)\b/i', ' ', $rawAnswer);
            preg_match_all('/\b([A-E])\b/i', $cleanAnswer, $ansLetters);
            $selectedOpts = [];
            foreach (array_unique($ansLetters[1]) as $letter) {
                $idx = ord(strtoupper($letter)) - ord('A');
                if (isset($options[$idx])) {
                    $selectedOpts[] = $options[$idx];
                }
            }
            $answer = ! empty($selectedOpts) ? $selectedOpts : ($options[0] ?? '');
        } elseif (! empty($options)) {
            if (preg_match('/^([A-E])$/i', $rawAnswer, $lm)) {
                $answer = ord(strtoupper($lm[1])) - ord('A');
            } else {
                $foundIdx = -1;
                foreach ($options as $idx => $opt) {
                    if (strcasecmp(trim($opt), $rawAnswer) === 0) {
                        $foundIdx = $idx;
                        break;
                    }
                }
                $answer = $foundIdx !== -1 ? $foundIdx : 0;
            }
        } else {
            $type = 'Short answer';
            $answer = $rawAnswer;
        }

        return [
            'id' => $id,
            'title' => $cleanTitle,
            'description' => '',
            'type' => $type,
            'options' => $options,
            'answer' => $answer,
            'required' => false,
            'points' => 10,
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
    protected function buildGridQuestionFromTable(array $table, array $lines, array $media, string $rawAnswer, int $id): array
    {
        $rows = $table['rows'];
        $title = implode("\n", $lines);
        $cleanTitle = preg_replace('/^\d+[\.\)]\s*/', '', trim($title));

        // 1. Detect if this is a True/False table (Benar / Salah)
        $isTrueFalse = false;
        if (preg_match('/^[BS](\s*,\s*[BS])+/i', $rawAnswer) || preg_match('/(?:benar|salah)/i', $rawAnswer)) {
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
            if (isset($rows[0])) {
                $firstRowText = implode(' ', $rows[0]);
                if (preg_match('/(?:no|nomor|pernyataan|soal|benar|salah)/i', $firstRowText)) {
                    $isHeader = true;
                }
            }

            $dataRows = $isHeader ? array_slice($rows, 1) : $rows;
            $gridRows = [];

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
                }
            }

            // Columns are Benar and Salah
            $columns = ['Benar', 'Salah'];

            // Parse answer key tokens: e.g. "S, S, B, B, B" or "B, S, S, B, B"
            $tokens = preg_split('/[\s,]+/', trim($rawAnswer));
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
                'points' => 10,
                'media' => $media,
            ];
        }

        // 2. Detect if this is a Matching table (Menjodohkan / Pasangkan)
        $isMatching = preg_match('/(\d+)\s*[-:]?\s*([A-Za-z])/i', $rawAnswer)
            || preg_match('/(?:pasangkan|jodohkan|match)/i', $cleanTitle);

        if ($isMatching) {
            if ($cleanTitle === '') {
                $cleanTitle = 'Pasangkanlah pernyataan berikut dengan pilihan yang tepat:';
            }

            // Skip header row if detected
            $isHeader = false;
            if (isset($rows[0])) {
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
                'points' => 10,
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
            'points' => 10,
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
                        'points' => 10,
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
