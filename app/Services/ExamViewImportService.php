<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use SimpleXMLElement;
use ZipArchive;

class ExamViewImportService
{
    /**
     * Parse an uploaded ExamView Blackboard export ZIP archive.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws \RuntimeException
     */
    public function parseZip(UploadedFile|string $file): array
    {
        $filePath = $file instanceof UploadedFile ? $file->getRealPath() : $file;

        $zip = new ZipArchive;
        if ($zip->open($filePath) !== true) {
            throw new \RuntimeException('Berkas ZIP tidak dapat dibuka atau berkas rusak.');
        }

        try {
            $xmlContents = $this->findAssessmentXmlFiles($zip);

            if (empty($xmlContents)) {
                throw new \RuntimeException('Berkas ZIP tidak memuat data bank soal ExamView / Blackboard yang valid (tidak ditemukan berkas XML/DAT QTI).');
            }

            $questions = [];
            $nextId = 1000;

            foreach ($xmlContents as $xmlString) {
                $parsed = $this->parseQtiXml($xmlString, $zip, $nextId);
                $questions = array_merge($questions, $parsed);
            }

            if (empty($questions)) {
                throw new \RuntimeException('Tidak ditemukan pertanyaan yang dapat diurai di dalam berkas bank soal.');
            }

            return $questions;
        } finally {
            $zip->close();
        }
    }

    /**
     * Find all XML/DAT files containing QTI assessment or pool data in the ZIP.
     *
     * @return list<string>
     */
    private function findAssessmentXmlFiles(ZipArchive $zip): array
    {
        $contents = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = $zip->getNameIndex($i);

            // Skip directory entries, manifest, and non-XML/non-DAT files
            if (str_ends_with($entryName, '/') || strcasecmp($entryName, 'imsmanifest.xml') === 0) {
                continue;
            }

            $ext = strtolower(pathinfo($entryName, PATHINFO_EXTENSION));
            if ($ext === 'dat' || $ext === 'xml') {
                $raw = $zip->getFromIndex($i);
                if ($raw && (str_contains($raw, '<questestinterop') || str_contains($raw, '<item') || str_contains($raw, '<assessment'))) {
                    $contents[] = $raw;
                }
            }
        }

        return $contents;
    }

    /**
     * Parse QTI XML into Alsenform Question structures.
     *
     * @return list<array<string, mixed>>
     */
    private function parseQtiXml(string $xmlString, ZipArchive $zip, int &$nextId): array
    {
        libxml_use_internal_errors(true);

        try {
            $root = new SimpleXMLElement($xmlString);
        } catch (\Exception $e) {
            return [];
        }

        $items = $root->xpath('//item');
        if (! $items) {
            return [];
        }

        $questions = [];

        foreach ($items as $item) {
            // 1. Determine question type
            $bbType = (string) ($item->xpath('.//itemmetadata/bbmd_questiontype')[0] ?? '');
            $type = $this->mapQuestionType($bbType);

            // 2. Extract question text and embedded media
            $extractedPrompt = $this->extractPromptAndMedia($item, $zip);
            $title = $extractedPrompt['title'];
            $media = $extractedPrompt['media'];

            if (trim($title) === '' && empty($media)) {
                continue;
            }

            // 3. Extract options
            $optionsData = $this->extractOptions($item);
            $options = $optionsData['options'];
            $identMap = $optionsData['identMap']; // ident => index

            // Special handling for True/False if options are missing or empty
            if ($type === 'Multiple choice' && strcasecmp($bbType, 'True/False') === 0 && empty($options)) {
                $options = ['Benar', 'Salah'];
                $identMap['true'] = 0;
                $identMap['false'] = 1;
            }

            // 4. Extract points and correct answer
            $pointsAndAnswer = $this->extractPointsAndAnswer($item, $type, $options, $identMap, $bbType);
            $points = $pointsAndAnswer['points'];
            $answer = $pointsAndAnswer['answer'];

            $questions[] = [
                'id' => $nextId++,
                'title' => $title ?: 'Pertanyaan Tanpa Judul',
                'description' => '',
                'type' => $type,
                'options' => $options,
                'rows' => [],
                'columns' => [],
                'answer' => $answer,
                'required' => false,
                'media' => $media,
                'points' => $points,
            ];
        }

        return $questions;
    }

    /**
     * Map Blackboard/ExamView question type string to Alsenform question type.
     */
    private function mapQuestionType(string $bbType): string
    {
        return match (strtolower(trim($bbType))) {
            'multiple choice' => 'Multiple choice',
            'true/false' => 'Multiple choice',
            'multiple answer' => 'Checkboxes',
            'essay' => 'Paragraph',
            'short response', 'numeric', 'fill in the blank' => 'Short answer',
            'matching' => 'Multiple choice grid',
            default => 'Multiple choice',
        };
    }

    /**
     * Extract prompt text and any embedded image references.
     *
     * @return array{title: string, media: list<array{type: 'image', url: string}>}
     */
    private function extractPromptAndMedia(SimpleXMLElement $item, ZipArchive $zip): array
    {
        $media = [];

        // Prompt text nodes: flow/material/mattext or presentation/material/mattext (excluding render_choice)
        $textNodes = $item->xpath('.//presentation//flow_mat/material/mattext | .//presentation/flow/material/mattext | .//presentation/material/mattext');

        $rawHtml = '';
        if ($textNodes) {
            foreach ($textNodes as $node) {
                // Ensure this node is not inside a render_choice / response_label
                $rawHtml .= ' '.(string) $node;
            }
        }

        // Check for <matimage> tags
        $imageNodes = $item->xpath('.//presentation//matimage | .//material//matimage');
        if ($imageNodes) {
            foreach ($imageNodes as $imgNode) {
                $uri = (string) ($imgNode['uri'] ?? $imgNode['URI'] ?? '');
                if ($uri !== '') {
                    $extractedUrl = $this->extractAndStoreImage($uri, $zip);
                    if ($extractedUrl) {
                        $media[] = [
                            'type' => 'image',
                            'url' => $extractedUrl,
                        ];
                    }
                }
            }
        }

        // Check for <img> tags inside raw HTML (e.g. ExamView embedded file location)
        if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $rawHtml, $matches)) {
            foreach ($matches[1] as $src) {
                // Clean @X@EmbeddedFile.location@X@ pattern
                $cleanPath = preg_replace('/^@X@[^@]+@X@/i', '', $src);
                $extractedUrl = $this->extractAndStoreImage($cleanPath, $zip);
                if ($extractedUrl) {
                    $media[] = [
                        'type' => 'image',
                        'url' => $extractedUrl,
                    ];
                }
            }
        }

        // Clean prompt text: strip HTML tags and decode entities
        $cleanText = html_entity_decode($rawHtml, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Remove @X@EmbeddedFile... tags
        $cleanText = preg_replace('/@X@[^@]+@X@\S*/i', '', $cleanText);
        $cleanText = strip_tags($cleanText);
        $cleanText = trim(preg_replace('/\s+/', ' ', $cleanText));

        return [
            'title' => $cleanText,
            'media' => $media,
        ];
    }

    /**
     * Locate and extract an image from the ZIP archive and store it in public storage.
     */
    private function extractAndStoreImage(string $targetFilename, ZipArchive $zip): ?string
    {
        $baseName = basename($targetFilename);
        if ($baseName === '') {
            return null;
        }

        // Search for file in ZIP
        $entryName = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (strcasecmp(basename($name), $baseName) === 0 || strcasecmp($name, $targetFilename) === 0) {
                $entryName = $name;
                break;
            }
        }

        if (! $entryName) {
            return null;
        }

        $imageBytes = $zip->getFromName($entryName);
        if (! $imageBytes) {
            return null;
        }

        $ext = pathinfo($baseName, PATHINFO_EXTENSION) ?: 'jpg';
        $storedFilename = 'examview_'.md5($imageBytes).'.'.$ext;
        $storagePath = 'media/examview/'.$storedFilename;

        Storage::disk('public')->put($storagePath, $imageBytes);

        return asset('storage/'.$storagePath);
    }

    /**
     * Extract options from response_label elements.
     *
     * @return array{options: list<string>, identMap: array<string, int>}
     */
    private function extractOptions(SimpleXMLElement $item): array
    {
        $options = [];
        $identMap = [];

        $labels = $item->xpath('.//render_choice/response_label');
        if (! $labels) {
            return [
                'options' => $options,
                'identMap' => $identMap,
            ];
        }

        foreach ($labels as $label) {
            $ident = (string) ($label['ident'] ?? '');
            $textNode = $label->xpath('.//material/mattext')[0] ?? null;
            $optText = $textNode ? (string) $textNode : '';

            $cleanOpt = html_entity_decode($optText, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $cleanOpt = trim(strip_tags($cleanOpt));

            if ($cleanOpt === '') {
                $cleanOpt = 'Pilihan '.(count($options) + 1);
            }

            $options[] = $cleanOpt;
            if ($ident !== '') {
                $identMap[$ident] = count($options) - 1;
            }
        }

        return [
            'options' => $options,
            'identMap' => $identMap,
        ];
    }

    /**
     * Extract score weighting and answer key.
     *
     * @param  list<string>  $options
     * @param  array<string, int>  $identMap
     * @return array{points: int, answer: mixed}
     */
    private function extractPointsAndAnswer(SimpleXMLElement $item, string $type, array $options, array $identMap, string $bbType): array
    {
        $points = 10;
        $answer = 0;

        // Try extracting points
        $scoreNode = $item->xpath('.//resprocessing//respcondition[@title="correct"]//setvar[@varname="SCORE"] | .//resprocessing//setvar[@varname="SCORE"]')[0] ?? null;
        if ($scoreNode) {
            $val = (float) $scoreNode;
            if ($val > 0) {
                $points = (int) round($val);
            }
        } else {
            $qmdWeight = $item->xpath('.//itemmetadata/qmd_weighting')[0] ?? null;
            if ($qmdWeight && (float) $qmdWeight > 0) {
                $points = (int) round((float) $qmdWeight);
            }
        }

        // Extract answer for Multiple choice
        if ($type === 'Multiple choice') {
            // Condition for correct answer
            $varequalNodes = $item->xpath('.//resprocessing//respcondition[@title="correct"]//conditionvar//varequal | .//resprocessing//respcondition[.//setvar[@varname="SCORE" and number(.) > 0]]//conditionvar//varequal');

            if ($varequalNodes) {
                $correctIdent = trim((string) $varequalNodes[0]);
                if (isset($identMap[$correctIdent])) {
                    $answer = $identMap[$correctIdent];
                } elseif (strcasecmp($bbType, 'True/False') === 0) {
                    if (strcasecmp($correctIdent, 'true') === 0 || $correctIdent === '1') {
                        $answer = 0; // Benar
                    } else {
                        $answer = 1; // Salah
                    }
                }
            }
        } elseif ($type === 'Checkboxes') {
            // Multiple answers
            $varequalNodes = $item->xpath('.//resprocessing//respcondition//conditionvar//varequal');
            $correctAnswers = [];
            foreach ($varequalNodes as $ve) {
                $id = trim((string) $ve);
                if (isset($identMap[$id]) && isset($options[$identMap[$id]])) {
                    $correctAnswers[] = $options[$identMap[$id]];
                }
            }
            $answer = array_values(array_unique($correctAnswers));
        } elseif ($type === 'Short answer' || $type === 'Paragraph') {
            $varequalNodes = $item->xpath('.//resprocessing//conditionvar//varequal');
            $answer = $varequalNodes ? trim((string) $varequalNodes[0]) : '';
        }

        return [
            'points' => $points,
            'answer' => $answer,
        ];
    }
}
